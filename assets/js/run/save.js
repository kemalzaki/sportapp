/* ============================================================
 * KK Run · save.js  (R52 — Local-First + Chunked Upload + Pending Queue)
 * ------------------------------------------------------------
 * Perubahan dari R49:
 *  - stopSession() tidak lagi mengirim SATU payload raksasa.
 *    Aktivitas dipecah menjadi:
 *       1) upload_init       → membuat run_sessions (server_id)
 *       2) upload_chunk *N   → bulk insert 300 titik / request
 *       3) upload_finalize   → update statistik + upload_harian
 *  - Retry otomatis per-request (3x, exponential backoff).
 *  - Progress callback via window.KKSave.onProgress(pct, msg).
 *  - Jika salah satu request GAGAL:
 *       • data lokal TIDAK dihapus,
 *       • aktivitas dipindah ke antrian "pending" (IndexedDB),
 *       • tracking.js yang memanggil clear() setelah stopSession
 *         akan menjadi NO-OP untuk backup (karena _lastUploadOk=false).
 *  - listPending(), retryPending(id), deletePending(id) tersedia
 *    untuk halaman "Aktivitas Belum Tersinkron".
 *  - Recovery lama (KKSave.recover) tetap ada.
 *  - Logging detail (jumlah titik, ukuran payload, waktu, response).
 * ============================================================ */
(function(){
  'use strict';

  var LS_KEY   = 'kk_run_state_v3';
  var LS_FULL  = 'kk_run_full_v1';
  var DB_NAME  = 'kk_run_db';
  var DB_STORE = 'activity';
  var DB_VER   = 1;
  var _dbP = null;
  var _lastBackup = 0;
  var BACKUP_INTERVAL_MS = 25000;

  // Status upload terakhir (dibaca clear() supaya tidak menghapus
  // backup lokal jika upload gagal).
  var _lastUploadOk = true;

  var CHUNK_SIZE = 300;        // titik / request
  var UPLOAD_TIMEOUT_MS = 45000;
  var MAX_RETRIES = 3;

  function log(){
    try { console.log.apply(console, ['[KKSave]'].concat([].slice.call(arguments))); } catch(e){}
  }
  function progress(pct, msg){
    try {
      if (typeof window.KKSave.onProgress === 'function'){
        window.KKSave.onProgress(pct|0, msg||'');
      }
    } catch(e){}
  }

  function openDB(){
    if (_dbP) return _dbP;
    if (!('indexedDB' in window)) { _dbP = Promise.resolve(null); return _dbP; }
    _dbP = new Promise(function(resolve){
      try {
        var req = indexedDB.open(DB_NAME, DB_VER);
        req.onupgradeneeded = function(){
          var db = req.result;
          if (!db.objectStoreNames.contains(DB_STORE)) db.createObjectStore(DB_STORE);
        };
        req.onsuccess = function(){ resolve(req.result); };
        req.onerror   = function(){ resolve(null); };
      } catch(e){ resolve(null); }
    });
    return _dbP;
  }

  function idbPut(key, val){
    return openDB().then(function(db){
      if (!db) {
        try { localStorage.setItem(LS_FULL, JSON.stringify(val)); } catch(e){}
        return;
      }
      return new Promise(function(resolve){
        try {
          var tx = db.transaction(DB_STORE, 'readwrite');
          tx.objectStore(DB_STORE).put(val, key);
          tx.oncomplete = function(){ resolve(); };
          tx.onerror    = function(){ resolve(); };
        } catch(e){ resolve(); }
      });
    });
  }
  function idbGet(key){
    return openDB().then(function(db){
      if (!db){
        try { return JSON.parse(localStorage.getItem(LS_FULL) || 'null'); } catch(e){ return null; }
      }
      return new Promise(function(resolve){
        try {
          var tx = db.transaction(DB_STORE, 'readonly');
          var rq = tx.objectStore(DB_STORE).get(key);
          rq.onsuccess = function(){ resolve(rq.result || null); };
          rq.onerror   = function(){ resolve(null); };
        } catch(e){ resolve(null); }
      });
    });
  }
  function idbDel(key){
    return openDB().then(function(db){
      if (!db) {
        try { localStorage.removeItem(LS_FULL); } catch(e){}
        return;
      }
      return new Promise(function(resolve){
        try {
          var tx = db.transaction(DB_STORE, 'readwrite');
          tx.objectStore(DB_STORE).delete(key);
          tx.oncomplete = function(){ resolve(); };
          tx.onerror    = function(){ resolve(); };
        } catch(e){ resolve(); }
      });
    });
  }
  function idbAllKeys(){
    return openDB().then(function(db){
      if (!db) return [];
      return new Promise(function(resolve){
        try {
          var tx = db.transaction(DB_STORE, 'readonly');
          var rq = tx.objectStore(DB_STORE).getAllKeys();
          rq.onsuccess = function(){ resolve(rq.result || []); };
          rq.onerror   = function(){ resolve([]); };
        } catch(e){ resolve([]); }
      });
    });
  }

  function snapshot(state){
    return {
      sessionId: state.sessionId,
      startedAt: state.startedAt,
      totalM: state.totalM,
      pausedTotalMs: state.pausedTotalMs,
      paused: state.paused,
      kmSplits: state.kmSplits,
      calories: state.calories,
      sport: state.sport,
      weight: state.weight,
      points: (state.points || []).slice(),
      savedAt: Date.now()
    };
  }

  function slimPoints(pts){
    return pts.map(function(p){
      var o = { lat:+p.lat, lng:+p.lng, t:+p.t||0 };
      if (p.acc != null) o.acc = +p.acc;
      if (p.spd != null) o.spd = +p.spd;
      return o;
    });
  }

  // fetch dengan timeout + retry (exponential backoff)
  function fetchRetry(url, opts, label){
    var attempt = 0;
    function once(){
      attempt++;
      var ctl = ('AbortController' in window) ? new AbortController() : null;
      var to = setTimeout(function(){ try { ctl && ctl.abort(); } catch(e){} }, UPLOAD_TIMEOUT_MS);
      var o = Object.assign({}, opts);
      if (ctl) o.signal = ctl.signal;
      var t0 = Date.now();
      return fetch(url, o).then(function(r){
        clearTimeout(to);
        if (!r.ok) throw new Error('HTTP '+r.status);
        return r.json().catch(function(){ throw new Error('bad_json'); });
      }).then(function(d){
        log(label, 'ok ('+ (Date.now()-t0) +'ms, try#'+attempt+')', d);
        if (!d || !d.ok) throw new Error((d && d.err) || 'server_nok');
        return d;
      }).catch(function(err){
        clearTimeout(to);
        log(label, 'FAIL try#'+attempt, err && err.message);
        if (attempt >= MAX_RETRIES) throw err;
        return new Promise(function(res){ setTimeout(res, 800 * attempt); }).then(once);
      });
    }
    return once();
  }

  // Kirim aktivitas ke server dalam beberapa request (init → chunks → finalize).
  // snapObj: hasil snapshot() ATAU objek dengan {startedAt,totalM,durSec,points}
  function uploadChunked(snapObj){
    var pts       = slimPoints(snapObj.points || []);
    var totalM    = +snapObj.totalM || 0;
    var durSec    = +snapObj.durSec || snapObj.durationSec || 0;
    var startedAt = +snapObj.startedAt || Date.now();
    var csrf      = (window.KK_RUN && window.KK_RUN.csrf) || '';

    var payloadBytes = 0;
    try { payloadBytes = JSON.stringify(pts).length; } catch(e){}
    log('upload begin', { points: pts.length, bytes: payloadBytes, durSec: durSec, totalM: totalM });

    var t0 = Date.now();
    progress(1, 'Menyiapkan aktivitas…');

    // 1) init
    var fdInit = new FormData();
    fdInit.append('csrf', csrf);
    fdInit.append('_action','upload_init');
    fdInit.append('total_m', totalM);
    fdInit.append('durasi', durSec);
    fdInit.append('started_at', startedAt);
    fdInit.append('total_points', pts.length);

    return fetchRetry('/api_run.php', { method:'POST', body: fdInit, credentials:'same-origin', keepalive:true }, 'init')
      .then(function(d){
        var sid = +d.session_id || 0;
        if (!sid) throw new Error('no_session_id');
        progress(5, 'Mengunggah titik GPS 0/' + pts.length);

        // 2) chunks
        var total = pts.length;
        var totalChunks = Math.max(1, Math.ceil(total / CHUNK_SIZE));
        var idx = 0;

        function nextChunk(){
          if (idx >= total){
            return Promise.resolve();
          }
          var slice = pts.slice(idx, idx + CHUNK_SIZE);
          var seq = Math.floor(idx / CHUNK_SIZE) + 1;
          var fd = new FormData();
          fd.append('csrf', csrf);
          fd.append('_action','upload_chunk');
          fd.append('session_id', sid);
          fd.append('seq', seq);
          fd.append('total_seq', totalChunks);
          fd.append('offset', idx);
          fd.append('points', JSON.stringify(slice));

          return fetchRetry('/api_run.php', { method:'POST', body: fd, credentials:'same-origin', keepalive:true }, 'chunk#'+seq+'/'+totalChunks)
            .then(function(){
              idx += slice.length;
              var pct = 5 + Math.round((idx/Math.max(1,total)) * 85); // 5..90
              progress(pct, 'Mengunggah titik GPS ' + idx + '/' + total);
              return nextChunk();
            });
        }

        return nextChunk().then(function(){
          // 3) finalize
          progress(92, 'Menutup aktivitas…');
          var fdFin = new FormData();
          fdFin.append('csrf', csrf);
          fdFin.append('_action','upload_finalize');
          fdFin.append('session_id', sid);
          fdFin.append('total_m', totalM);
          fdFin.append('durasi', durSec);
          fdFin.append('total_points', pts.length);
          return fetchRetry('/api_run.php', { method:'POST', body: fdFin, credentials:'same-origin', keepalive:true }, 'finalize')
            .then(function(fin){
              progress(100, 'Selesai');
              log('upload done in ' + (Date.now()-t0) + 'ms');
              return { ok:true, serverSid: sid, uploadId: +fin.upload_id || null };
            });
        });
      });
  }

  window.KKSave = {
    onProgress: null,

    /* ---- Simpan ringkas + backup penuh berkala ---- */
    save: function(state){
      try {
        localStorage.setItem(LS_KEY, JSON.stringify({
          sessionId: state.sessionId,
          startedAt: state.startedAt,
          totalM: state.totalM,
          pausedTotalMs: state.pausedTotalMs,
          paused: state.paused,
          points: (state.points || []).slice(-2000),
          kmSplits: state.kmSplits,
          calories: state.calories,
          sport: state.sport, weight: state.weight,
          savedAt: Date.now()
        }));
      } catch(e){}
      var now = Date.now();
      if (now - _lastBackup > BACKUP_INTERVAL_MS){
        _lastBackup = now;
        idbPut('current', snapshot(state));
      }
    },
    load: function(){
      try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); }
      catch(e){ return null; }
    },
    recover: function(){ return idbGet('current'); },

    /* ---- clear(): HANYA menghapus backup bila upload terakhir sukses ---- */
    clear: function(){
      _lastBackup = 0;
      if (_lastUploadOk){
        try { localStorage.removeItem(LS_KEY); } catch(e){}
        return idbDel('current');
      }
      // Upload gagal → JANGAN sentuh backup / pending. Reset flag agar
      // panggilan clear() berikutnya (dari sesi baru) tetap normal.
      _lastUploadOk = true;
      log('clear() skipped — last upload failed, local backup preserved');
      return Promise.resolve();
    },

    /* ---- No-op untuk kompat ---- */
    queuePoint: function(){},
    hasQueued: function(){ return false; },
    flush: function(){ return Promise.resolve(); },

    startSession: async function(){ return Date.now(); },

    /* ---- Stop: chunked upload + fallback ke pending queue ---- */
    stopSession: async function(sessionId, totalM, durSec, state){
      var snap = {
        startedAt: (state && state.startedAt) ? state.startedAt : Date.now(),
        totalM: totalM,
        durSec: durSec,
        points: (state && state.points) ? state.points.slice() : [],
        kmSplits: (state && state.kmSplits) ? state.kmSplits.slice() : [],
        calories: state ? state.calories : 0
      };

      // Pastikan snapshot terakhir ada di IDB sebelum upload — safety net.
      try { await idbPut('current', Object.assign({}, snap, { savedAt: Date.now() })); } catch(e){}

      try {
        var res = await uploadChunked(snap);
        _lastUploadOk = true;
        return res;
      } catch(err){
        log('upload FAILED — moving to pending queue', err && err.message);
        _lastUploadOk = false;
        var pid = 'pending:' + Date.now();
        try {
          await idbPut(pid, Object.assign({}, snap, {
            savedAt: Date.now(),
            lastError: (err && err.message) || 'unknown',
            attempts: 1
          }));
        } catch(e){}
        try { progress(100, 'Gagal upload — disimpan lokal'); } catch(e){}
        return { ok:false, serverSid:null, uploadId:null, pendingId: pid, error: (err && err.message) || 'unknown' };
      }
    },

    /* ---- Antrian pending ---- */
    listPending: async function(){
      var keys = await idbAllKeys();
      var out = [];
      for (var i=0;i<keys.length;i++){
        var k = keys[i];
        if (typeof k === 'string' && k.indexOf('pending:') === 0){
          var v = await idbGet(k);
          if (v) out.push({ id:k, data:v });
        }
      }
      out.sort(function(a,b){ return (b.data.savedAt||0) - (a.data.savedAt||0); });
      return out;
    },
    deletePending: function(id){ return idbDel(id); },
    retryPending: async function(id){
      var snap = await idbGet(id);
      if (!snap) return { ok:false, error:'not_found' };
      try {
        var res = await uploadChunked(snap);
        await idbDel(id);
        return res;
      } catch(err){
        // Tingkatkan attempt counter
        try {
          snap.attempts = (snap.attempts || 0) + 1;
          snap.lastError = (err && err.message) || 'unknown';
          await idbPut(id, snap);
        } catch(e){}
        return { ok:false, error: (err && err.message) || 'unknown' };
      }
    }
  };
})();

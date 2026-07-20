/* ============================================================
 * KK Run · save.js  (R49 — Local-First Architecture)
 * ------------------------------------------------------------
 * Perubahan besar dari R40:
 *  - TIDAK LAGI mengirim titik GPS per-titik ke server.
 *  - startSession() tidak menghubungi server; hanya menghasilkan
 *    ID lokal (Date.now()) untuk keperluan state internal.
 *  - Titik GPS disimpan di memory (state.points) + backup ke
 *    IndexedDB tiap ~25 detik. Fallback ke localStorage bila
 *    IndexedDB tidak tersedia.
 *  - Saat Stop: seluruh aktivitas dikirim SATU KALI via
 *    _action=upload_activity dan mengembalikan session_id
 *    (server-side). ID ini dipakai untuk export GPX / delete.
 *  - Setelah upload sukses, seluruh backup lokal dibersihkan.
 *  - Recovery: KKSave.recover() mengembalikan Promise berisi
 *    snapshot aktivitas terakhir yang belum ter-upload (bila ada).
 *  - flush()/queuePoint() dipertahankan sebagai no-op untuk
 *    kompatibilitas dengan pemanggil lama.
 * ============================================================ */
(function(){
  'use strict';

  var LS_KEY   = 'kk_run_state_v3';   // ringkasan (kompat lama)
  var LS_FULL  = 'kk_run_full_v1';    // fallback IDB (points lengkap)
  var DB_NAME  = 'kk_run_db';
  var DB_STORE = 'activity';
  var DB_VER   = 1;
  var _dbP = null;
  var _lastBackup = 0;
  var BACKUP_INTERVAL_MS = 25000;

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
      try { localStorage.removeItem(LS_FULL); } catch(e){}
      if (!db) return;
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

  window.KKSave = {
    /* ---- Simpan ringkas ke localStorage tiap update ---- */
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
      // Backup penuh ke IndexedDB tiap 25 detik
      var now = Date.now();
      if (now - _lastBackup > BACKUP_INTERVAL_MS){
        _lastBackup = now;
        idbPut('current', snapshot(state));
      }
    },
    /* Ringkasan lama (kompat) */
    load: function(){
      try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); }
      catch(e){ return null; }
    },
    /* Recovery lengkap (dipakai tracking.autoResume) */
    recover: function(){ return idbGet('current'); },

    clear: function(){
      try { localStorage.removeItem(LS_KEY); } catch(e){}
      _lastBackup = 0;
      return idbDel('current');
    },

    /* ---- No-op untuk kompatibilitas (tidak ada HTTP per titik) ---- */
    queuePoint: function(){ /* local-first: buffer disimpan lewat state.points */ },
    hasQueued: function(){ return false; },
    flush: function(){ return Promise.resolve(); },

    /* ---- Start: lokal saja, tidak menghubungi server ---- */
    startSession: async function(){
      // ID lokal berbasis waktu; server akan menerbitkan ID sebenarnya
      // saat upload_activity dipanggil oleh stopSession().
      return Date.now();
    },

    /* ---- Stop: kirim SATU KALI seluruh aktivitas ---- */
    stopSession: async function(sessionId, totalM, durSec, state){
      var pts = (state && state.points) ? state.points : [];
      var startedAt = (state && state.startedAt) ? state.startedAt : Date.now();

      // Bersihkan payload agar hemat bandwidth (hilangkan field non-esensial)
      var payload = pts.map(function(p){
        var o = { lat:+p.lat, lng:+p.lng, t:+p.t||0 };
        if (p.acc != null) o.acc = +p.acc;
        if (p.spd != null) o.spd = +p.spd;
        return o;
      });

      var fd = new FormData();
      fd.append('csrf', window.KK_RUN.csrf);
      fd.append('_action','upload_activity');
      fd.append('total_m', totalM);
      fd.append('durasi', durSec);
      fd.append('started_at', startedAt);
      fd.append('points', JSON.stringify(payload));

      var serverSid = null, uploadId = null;
      try {
        var r = await fetch('/api_run.php', { method:'POST', body: fd, credentials:'same-origin', keepalive:true });
        var d = await r.json();
        if (d && d.ok){
          serverSid = +d.session_id || null;
          uploadId  = +d.upload_id || null;
        }
      } catch(e){
        // gagal upload → biarkan backup lokal tetap ada supaya bisa
        // dicoba ulang saat user membuka app lagi (recover()).
        return { ok:false, serverSid:null, uploadId:null };
      }

      // Sukses → buang backup lokal
      if (serverSid){
        try { await idbDel('current'); } catch(e){}
        try { localStorage.removeItem(LS_KEY); } catch(e){}
      }
      return { ok: !!serverSid, serverSid: serverSid, uploadId: uploadId };
    }
  };
})();

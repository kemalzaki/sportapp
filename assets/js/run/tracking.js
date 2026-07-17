/* ============================================================
 * KK Run · tracking.js  (R40 — Refactor Bersih)
 * ------------------------------------------------------------
 * SATU set tombol (#kk-btn-start / -pause / -resume / -stop /
 * -mylocation). Tidak ada hidden button, tidak ada dispatchEvent,
 * tidak ada safeClickHidden.
 *
 * Perpindahan Dashboard <-> Focus TIDAK memengaruhi tracking:
 * KKUI hanya toggle CSS class, Leaflet tidak di-destroy.
 * ============================================================ */
(function(){
  'use strict';

  var METS = { walk:3.5, jog:7.0, run:9.8, bike:8.0 };

  var state = {
    sessionId: window.KK_RUN.sessionId,
    startedAt: null, timerInt: null,
    pauseAt: null, pausedTotalMs: 0, paused: false,
    autoPaused: false, lastMoveAt: 0,
    totalM: 0, points: [], kmSplits: [],
    curSpeed: 0, curElev: null,
    calories: 0,
    lastFix: null,
    voiceInterval: 1000
  };

  function elapsedSec(){
    if (!state.startedAt) return 0;
    var now = state.paused ? state.pauseAt : Date.now();
    return Math.floor((now - state.startedAt - state.pausedTotalMs)/1000);
  }
  function movingPace(){
    var pts = state.points; if (pts.length<2) return null;
    var slice = pts.slice(-30);
    var dist = 0;
    for (var i=1;i<slice.length;i++) dist += KKGps.haversine(slice[i-1], slice[i]);
    var t = (slice[slice.length-1].t - slice[0].t)/1000;
    if (dist < 20 || t <= 0) return null;
    return t/(dist/1000);
  }
  function avgPace(){
    var t = elapsedSec();
    if (state.totalM < 50 || t <= 0) return null;
    return t/(state.totalM/1000);
  }
  function computeCalories(){
    var sportEl = document.getElementById('sportSel');
    var wEl     = document.getElementById('weightInp');
    var sport = sportEl ? sportEl.value : 'run';
    var mets = METS[sport] || 7;
    var w = (wEl && +wEl.value) || 65;
    var kmh = state.curSpeed*3.6;
    if (kmh > 0){
      if (kmh < 4) mets = Math.max(2.5, mets*0.5);
      else if (kmh < 6) mets = Math.max(3.5, mets*0.7);
      else if (kmh < 8) mets = mets*0.85;
      else if (kmh > 12) mets = mets*1.15;
    }
    var hours = elapsedSec()/3600;
    state.calories = Math.max(0, Math.round(mets*w*hours));
    return state.calories;
  }
  function checkSplit(){
    var kmDone = Math.floor(state.totalM/1000);
    while (state.kmSplits.length < kmDone){
      var idx = state.kmSplits.length;
      var target = (idx+1)*1000;
      var accM = 0, tEnd = null;
      for (var i=1;i<state.points.length;i++){
        accM += KKGps.haversine(state.points[i-1], state.points[i]);
        if (accM >= target){ tEnd = state.points[i].t; break; }
      }
      if (!tEnd) tEnd = Date.now();
      var prevT = idx===0 ? state.startedAt : state.kmSplits[idx-1]._absEnd;
      var sec = Math.max(1, Math.round((tEnd-prevT)/1000));
      state.kmSplits.push({ km: idx+1, sec: sec, _absEnd: tEnd });
    }
  }

  function updateUI(){
    var m = {
      km: state.totalM/1000,
      tSec: elapsedSec(),
      paceMoving: movingPace(),
      paceAvg: avgPace(),
      speedKmh: state.curSpeed*3.6,
      calories: computeCalories(),
      elev: state.curElev
    };
    KKUI.renderMetrics(m);
    KKGps.setSpeedRef(state.curSpeed);
    if (KKVoice.isEnabled()) KKVoice.onDistance(state.totalM, m.paceAvg, state.voiceInterval);
    KKBackground.updateNotification({ totalM: state.totalM, elapsedSec: m.tSec });
  }

  /* ---- Handle GPS position ---- */
  var _lastAcceptedAt = 0;
  function handlePosition(pos, err){
    if (err){ KKUI.setGps(null, true); return; }
    if (state.paused && !state.autoPaused) return;
    var nowT = pos.timestamp || Date.now();
    var acc = pos.coords.accuracy;
    var p = {
      lat: pos.coords.latitude, lng: pos.coords.longitude,
      acc: acc, spd: pos.coords.speed,
      elev: pos.coords.altitude, t: nowT,
      hd: pos.coords.heading
    };
    KKUI.setGps(acc, false);
    state.lastFix = p;

    if (state.points.length === 0){
      if (acc && acc > 100) return;
      _acceptFirst(p); return;
    }
    if (acc != null && acc > 30) return;
    var last = state.points[state.points.length-1];
    var d = KKGps.haversine(last, p);
    var dt = Math.max(0.001, (nowT-last.t)/1000);
    var speed = d/dt;

    if (nowT - _lastAcceptedAt < KKGps.adaptiveMinInterval() && d < 30){
      KKMap.updateMarkerOnly(p); return;
    }
    if (d > 150 || dt > 25){
      KKMap.breakSegment(p);
      state.points.push(p);
      _lastAcceptedAt = nowT; state.lastMoveAt = nowT;
      if (state.autoPaused){
        state.pausedTotalMs += (nowT - state.pauseAt);
        state.autoPaused = false; state.paused = false; state.pauseAt = null;
        KKUI.setAutoPaused(false);
      }
      afterPoint(p, false); return;
    }
    if (speed > 12) return;
    if (d < 3){
      KKMap.updateMarkerOnly(p);
      if (!state.autoPaused && !state.paused && state.lastMoveAt && (nowT - state.lastMoveAt) > 20000){
        state.autoPaused = true; state.paused = true; state.pauseAt = nowT;
        KKUI.setAutoPaused(true);
        KKVoice.say('Aktivitas dijeda otomatis.');
      }
      return;
    }
    if (state.autoPaused){
      state.pausedTotalMs += (nowT - state.pauseAt);
      state.autoPaused = false; state.paused = false; state.pauseAt = null;
      KKUI.setAutoPaused(false);
      KKVoice.say('Aktivitas dilanjutkan.');
    }
    if (p.hd != null && !isNaN(p.hd) && state.curSpeed*3.6 > 3){
      KKMap.setHeading(p.hd);
    } else {
      var br = KKGps.bearing(last, p);
      if (state.curSpeed*3.6 > 3) KKMap.setHeading(br);
    }
    state.curSpeed = state.curSpeed*0.7 + (pos.coords.speed && pos.coords.speed>=0 ? pos.coords.speed : speed)*0.3;
    if (p.elev != null){ state.curElev = state.curElev==null?p.elev:(state.curElev*0.7 + p.elev*0.3); }
    state.lastMoveAt = nowT;
    state.totalM += d;
    state.points.push(p);
    KKMap.handleFix(p, false);
    _lastAcceptedAt = nowT;
    afterPoint(p, true);
  }
  function _acceptFirst(p){
    state.points.push(p);
    KKMap.handleFix(p, true);
    state.curSpeed = 0;
    state.curElev = p.elev!=null?p.elev:null;
    state.lastMoveAt = p.t;
    _lastAcceptedAt = p.t;
    afterPoint(p, true);
  }
  function afterPoint(p){
    checkSplit();
    if (state.sessionId){
      KKSave.queuePoint({ lat:p.lat, lng:p.lng, acc:p.acc, spd:p.spd, total_m:state.totalM });
    }
    updateUI();
    var sportEl=document.getElementById('sportSel'), wEl=document.getElementById('weightInp');
    state.sport  = sportEl ? sportEl.value : 'run';
    state.weight = (wEl && +wEl.value) || 65;
    KKSave.save(state);
  }

  /* ---- Tombol tampilan Start/Pause/Resume/Stop ---- */
  function refreshButtons(){
    var start  = document.getElementById('kk-btn-start');
    var pause  = document.getElementById('kk-btn-pause');
    var resume = document.getElementById('kk-btn-resume');
    var stop   = document.getElementById('kk-btn-stop');
    var running = !!state.sessionId;
    if (start)  start.style.display  = running ? 'none' : '';
    if (stop)   stop.style.display   = running ? '' : 'none';
    if (pause)  pause.style.display  = (running && !state.paused) ? '' : 'none';
    if (resume) resume.style.display = (running &&  state.paused) ? '' : 'none';
  }

  /* ---- Start / Pause / Resume / Stop ---- */
  async function startSession(){
    if (!navigator.geolocation){ alert('Browser/perangkat tidak mendukung GPS'); return; }
    KKUI.countdown(async function(){
      try { state.sessionId = await KKSave.startSession(); }
      catch(e){ alert(e.message); return; }
      state.startedAt = Date.now();
      state.totalM = 0; state.points = []; state.kmSplits = [];
      state.pausedTotalMs = 0; state.paused = false; state.autoPaused = false;
      state.curSpeed = 0; state.curElev = null; state.lastMoveAt = 0;
      var vSel = document.getElementById('voiceSel');
      state.voiceInterval = vSel ? (+vSel.value || 0) : 1000;
      KKVoice.reset();
      KKMap.reset();
      var rSel = document.getElementById('rotSel');
      KKMap.setRotationEnabled(rSel ? (rSel.value === 'heading') : true);
      KKUI.setModeChip('● REC');
      refreshButtons();

      state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);
      var bgOk = await KKBackground.startBackgroundGeoloc(handlePosition);
      if (!bgOk) KKGps.start(handlePosition);
      KKGps.startCompass(function(hd){
        if (state.curSpeed*3.6 < 3) KKMap.setHeading(hd);
      });
      KKBackground.acquireWakeLock();
      KKVoice.say('Tracking dimulai. Selamat berolahraga.');
      updateUI();
    });
  }

  function pauseSession(){
    if (!state.sessionId || state.paused) return;
    state.paused = true; state.pauseAt = Date.now();
    refreshButtons();
    KKUI.setModeChip('⏸ JEDA');
    KKVoice.say('Dijeda.');
    KKSave.save(state);
  }
  function resumeSession(){
    if (!state.sessionId || !state.paused) return;
    state.pausedTotalMs += (Date.now() - state.pauseAt);
    state.paused = false; state.pauseAt = null; state.autoPaused = false;
    refreshButtons();
    KKUI.setModeChip('● REC');
    KKUI.setAutoPaused(false);
    KKVoice.say('Lanjut.');
    KKSave.save(state);
  }
  async function stopSession(){
    var dur = elapsedSec();
    KKGps.stop(); await KKBackground.stopBackgroundGeoloc();
    clearInterval(state.timerInt); state.timerInt = null;
    KKBackground.releaseWakeLock();
    KKBackground.clearNotification();
    if (state.sessionId){
      await KKSave.flush(state.sessionId);
      await KKSave.stopSession(state.sessionId, state.totalM, dur);
    }
    KKSave.clear();
    KKVoice.say('Tracking selesai. Kerja bagus.');
    var snap = {
      totalM: state.totalM, durationSec: dur, calories: state.calories,
      points: state.points.slice(), kmSplits: state.kmSplits.slice(),
      startedAt: state.startedAt
    };
    // Reset session state agar tombol Start muncul lagi
    var oldMode = KKUI.currentMode();
    state.sessionId = null; state.startedAt = null;
    state.paused = false; state.autoPaused = false;
    KKUI.clearModeChip();
    refreshButtons();
    if (oldMode === 'focus') KKUI.exitFocusMode();
    KKFinish.open(snap);
  }

  /* ---- Tombol Lokasi Saya Sekarang ---- */
  var _myLocMarker=null, _myLocCircle=null;
  function showMyLocationOnMap(p){
    var m = (window.KKMap && KKMap.getMap) ? KKMap.getMap() : null;
    if (!m || !window.L) return;
    var icon = L.divIcon({
      className: 'kk-mylocation-icon',
      html: '<div class="kk-mylocation-dot"></div><div class="kk-mylocation-pulse"></div>',
      iconSize: [22,22], iconAnchor: [11,11]
    });
    if (_myLocMarker) { try { m.removeLayer(_myLocMarker); } catch(e){} }
    if (_myLocCircle) { try { m.removeLayer(_myLocCircle); } catch(e){} }
    _myLocMarker = L.marker([p.lat, p.lng], { icon: icon, zIndexOffset: 1000 }).addTo(m);
    _myLocMarker.bindPopup('Lokasi Saya<br><small>Akurasi ±'+Math.round(p.acc||0)+' m</small>').openPopup();
    if (p.acc && p.acc > 0){
      _myLocCircle = L.circle([p.lat, p.lng], {
        radius: p.acc, color:'#1E90FF', weight:1, opacity:.6,
        fillColor:'#1E90FF', fillOpacity:.12
      }).addTo(m);
    }
    m.setView([p.lat, p.lng], Math.max(m.getZoom(), 17), { animate:true });
  }
  function getMyLocation(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var btn = document.getElementById('kk-btn-mylocation');
    var orig = btn ? btn.innerHTML : null;
    if (btn){ btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Mencari lokasi…'; }
    navigator.geolocation.getCurrentPosition(function(pos){
      var p = { lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy };
      state.lastFix = p;
      try { showMyLocationOnMap(p); } catch(e){ console.error(e); }
      if (btn){ btn.disabled = false; btn.innerHTML = orig; }
    }, function(err){
      if (btn){ btn.disabled = false; btn.innerHTML = orig; }
      alert('Gagal mendapatkan lokasi: ' + (err && err.message ? err.message : 'unknown'));
    }, { enableHighAccuracy:true, timeout:15000, maximumAge:0 });
  }

  /* ---- Wire tombol (SATU set) ---- */
  function wire(){
    var start=document.getElementById('kk-btn-start');
    var pause=document.getElementById('kk-btn-pause');
    var resume=document.getElementById('kk-btn-resume');
    var stop=document.getElementById('kk-btn-stop');
    var loc=document.getElementById('kk-btn-mylocation');

    if (start)  start.addEventListener('click',  function(e){ e.preventDefault(); startSession(); });
    if (pause)  pause.addEventListener('click',  function(e){ e.preventDefault(); pauseSession(); });
    if (resume) resume.addEventListener('click', function(e){ e.preventDefault(); resumeSession(); });
    if (stop)   stop.addEventListener('click',   function(e){ e.preventDefault(); KKUI.confirmStop(stopSession); });
    if (loc)    loc.addEventListener('click',    function(e){ e.preventDefault(); getMyLocation(); });

    // Setting perubahan realtime
    var rotSel  = document.getElementById('rotSel');
    if (rotSel) rotSel.addEventListener('change', function(){ KKMap.setRotationEnabled(this.value === 'heading'); });
    var voiceSel= document.getElementById('voiceSel');
    if (voiceSel) voiceSel.addEventListener('change', function(){ state.voiceInterval = +this.value || 0; });

    // Finish screen buttons
    var fBack = document.getElementById('kk-finish-back');
    if (fBack) fBack.addEventListener('click', function(){ KKFinish.close(); location.reload(); });
    var fDis  = document.getElementById('f-btn-discard');
    if (fDis)  fDis.addEventListener('click', function(){
      if (confirm('Buang catatan aktivitas ini?')){ KKFinish.close(); location.reload(); }
    });

    // Visibility change → refresh GPS saat kembali ke foreground
    document.addEventListener('visibilitychange', async function(){
      if (document.visibilityState === 'visible' && state.sessionId){
        await KKBackground.hideBubble();
        if (!KKBackground.isNative){ KKGps.stop(); KKGps.start(handlePosition); }
        KKBackground.acquireWakeLock();
        KKMap.invalidate();
      } else if (document.visibilityState === 'hidden' && state.sessionId){
        KKBackground.showBubble({ totalM: state.totalM });
      }
    });
  }

  /* ---- Auto-resume kalau ada sesi aktif ---- */
  function autoResume(){
    if (!state.sessionId) return;
    var st = KKSave.load();
    if (st && st.sessionId === state.sessionId){
      state.startedAt = st.startedAt || Date.now();
      state.totalM = +st.totalM || 0;
      state.points = Array.isArray(st.points) ? st.points : [];
      state.pausedTotalMs = +st.pausedTotalMs || 0;
      state.paused = !!st.paused;
      state.kmSplits = Array.isArray(st.kmSplits) ? st.kmSplits : [];
      var sportEl=document.getElementById('sportSel');
      var wEl=document.getElementById('weightInp');
      if (st.sport && sportEl) sportEl.value = st.sport;
      if (st.weight && wEl)    wEl.value = st.weight;
      state.points.forEach(function(p, i){ KKMap.handleFix(p, i===0); });
    } else {
      state.startedAt = Date.now();
    }
    KKUI.setModeChip(state.paused ? '⏸ JEDA' : '● REC');
    refreshButtons();
    state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);
    KKBackground.startBackgroundGeoloc(handlePosition).then(function(ok){
      if (!ok) KKGps.start(handlePosition);
    });
    KKBackground.acquireWakeLock();
    updateUI();
  }

  function detectNative(){
    if (!KKBackground.isNative){
      var w = document.getElementById('kk-bg-warn');
      if (w) w.classList.remove('d-none');
    }
  }

  /* ---- Init ---- */
  document.addEventListener('DOMContentLoaded', function(){
    KKMap.init();
    wire();
    refreshButtons();
    detectNative();
    autoResume();
    if ('serviceWorker' in navigator){
      navigator.serviceWorker.register('/service-worker.js').catch(function(){});
    }
  });

  window.KKTracking = { state: state, refreshButtons: refreshButtons };
})();

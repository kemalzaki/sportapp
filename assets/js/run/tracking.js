/* ============================================================
 * KK Run · tracking.js — Orchestrator
 * Menggabungkan KKMap / KKGps / KKUI / KKSave / KKVoice / KKBackground
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
    var sport = document.getElementById('sportSel').value;
    var mets = METS[sport] || 7;
    var w = +document.getElementById('weightInp').value || 65;
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
    // Update notification (native)
    KKBackground.updateNotification({ totalM: state.totalM, elapsedSec: m.tSec });
  }

  /* ---- Handle GPS position ---- */
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
    if (acc != null && acc > 30){ return; }
    var last = state.points[state.points.length-1];
    var d = KKGps.haversine(last, p);
    var dt = Math.max(0.001, (nowT-last.t)/1000);
    var speed = d/dt;

    if (nowT - _lastAcceptedAt < KKGps.adaptiveMinInterval() && d < 30){
      KKMap.updateMarkerOnly(p);
      return;
    }
    if (d > 150 || dt > 25){
      KKMap.breakSegment(p);
      state.points.push(p);
      _lastAcceptedAt = nowT;
      state.lastMoveAt = nowT;
      if (state.autoPaused){
        state.pausedTotalMs += (nowT - state.pauseAt);
        state.autoPaused = false; state.paused = false; state.pauseAt = null;
        KKUI.setAutoPaused(false);
      }
      afterPoint(p, false);
      return;
    }
    if (speed > 12){ return; }
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
    // Heading dari GPS jika ada
    if (p.hd != null && !isNaN(p.hd) && state.curSpeed*3.6 > 3){
      KKMap.setHeading(p.hd);
    } else if (state.points.length > 0) {
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
  var _lastAcceptedAt = 0;
  function _acceptFirst(p){
    state.points.push(p);
    KKMap.handleFix(p, true);
    state.curSpeed = 0;
    state.curElev = p.elev!=null?p.elev:null;
    state.lastMoveAt = p.t;
    _lastAcceptedAt = p.t;
    afterPoint(p, true);
  }
  function afterPoint(p, sendDist){
    checkSplit();
    if (state.sessionId){
      KKSave.queuePoint({ lat:p.lat, lng:p.lng, acc:p.acc, spd:p.spd, total_m:state.totalM });
    }
    updateUI();
    state.sport = document.getElementById('sportSel').value;
    state.weight = +document.getElementById('weightInp').value || 65;
    KKSave.save(state);
  }

  /* ---- Start / Pause / Resume / Stop ---- */
  async function startSession(){
    // Cek permission GPS
    if (!navigator.geolocation){ alert('Browser/perangkat tidak mendukung GPS'); return; }
    KKUI.enterFullscreen();
    KKUI.countdown(async function(){
      try { state.sessionId = await KKSave.startSession(); }
      catch(e){ alert(e.message); KKUI.exitFullscreen(); return; }
      state.startedAt = Date.now();
      state.totalM = 0; state.points = []; state.kmSplits = [];
      state.pausedTotalMs = 0; state.paused = false; state.autoPaused = false;
      state.curSpeed = 0; state.curElev = null; state.lastMoveAt = 0;
      state.voiceInterval = +document.getElementById('voiceSel').value || 0;
      KKVoice.reset();
      KKMap.reset();
      KKMap.setRotationEnabled(document.getElementById('rotSel').value === 'heading');
      KKUI.setModeChip('● REC');
      document.getElementById('kk-btn-pause').style.display='';
      document.getElementById('kk-btn-resume').style.display='none';

      state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);
      var bgOk = await KKBackground.startBackgroundGeoloc(handlePosition);
      if (!bgOk) KKGps.start(handlePosition);
      KKGps.startCompass(function(hd){
        // Compass hanya dipakai saat kecepatan sangat rendah (berjalan/berhenti)
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
    document.getElementById('kk-btn-pause').style.display='none';
    document.getElementById('kk-btn-resume').style.display='';
    KKUI.setModeChip('⏸ JEDA');
    KKVoice.say('Dijeda.');
    KKSave.save(state);
  }
  function resumeSession(){
    if (!state.sessionId || !state.paused) return;
    state.pausedTotalMs += (Date.now() - state.pauseAt);
    state.paused = false; state.pauseAt = null; state.autoPaused = false;
    document.getElementById('kk-btn-pause').style.display='';
    document.getElementById('kk-btn-resume').style.display='none';
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
      points: state.points, kmSplits: state.kmSplits, startedAt: state.startedAt
    };
    KKUI.exitFullscreen();
    KKFinish.open(snap);
  }

  /* ---- Wire buttons ---- */
  function wire(){
    document.getElementById('kk-btn-start').addEventListener('click', startSession);
    document.getElementById('kk-btn-pause').addEventListener('click', pauseSession);
    document.getElementById('kk-btn-resume').addEventListener('click', resumeSession);
    document.getElementById('kk-btn-stop').addEventListener('click', function(){
      // Swipe-to-finish untuk anti salah pencet
      KKUI.showSwipeFinish(stopSession);
    });
    // Hold 2 detik sebagai alternatif
    var holdT = null;
    var stopBtn = document.getElementById('kk-btn-stop');
    stopBtn.addEventListener('touchstart', function(){
      holdT = setTimeout(stopSession, 2000);
    });
    stopBtn.addEventListener('touchend', function(){ if(holdT) clearTimeout(holdT); });
    stopBtn.addEventListener('touchcancel', function(){ if(holdT) clearTimeout(holdT); });

    document.getElementById('kk-btn-lock').addEventListener('click', function(){ KKUI.lock(); });
    document.getElementById('kk-btn-mute').addEventListener('click', function(){
      var en = !KKVoice.isEnabled(); KKVoice.setEnabled(en);
      this.innerHTML = en ? '<i class="bi bi-volume-up-fill"></i>' : '<i class="bi bi-volume-mute-fill"></i>';
    });
    document.getElementById('kk-recenter').addEventListener('click', function(){
      var p = state.points[state.points.length-1] || state.lastFix;
      KKMap.recenter(p);
    });
    document.getElementById('kk-finish-back').addEventListener('click', function(){
      KKFinish.close();
      location.reload();
    });
    document.getElementById('f-btn-discard').addEventListener('click', function(){
      if (confirm('Buang catatan aktivitas ini?')){ KKFinish.close(); location.reload(); }
    });

    // Rotasi map ganti
    document.getElementById('rotSel').addEventListener('change', function(){
      KKMap.setRotationEnabled(this.value === 'heading');
    });
    // Voice interval ganti
    document.getElementById('voiceSel').addEventListener('change', function(){
      state.voiceInterval = +this.value || 0;
    });

    // Visibility change → back to foreground, refresh GPS
    document.addEventListener('visibilitychange', async function(){
      if (document.visibilityState === 'visible' && state.sessionId){
        await KKBackground.hideBubble();
        if (!KKBackground.isNative){
          KKGps.stop(); KKGps.start(handlePosition);
        }
        KKBackground.acquireWakeLock();
        KKMap.invalidate();
      } else if (document.visibilityState === 'hidden' && state.sessionId){
        // Coba tampilkan bubble (native only, silent no-op di web)
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
      if (st.sport) document.getElementById('sportSel').value = st.sport;
      if (st.weight) document.getElementById('weightInp').value = st.weight;
      // Restore polyline
      state.points.forEach(function(p, i){ KKMap.handleFix(p, i===0); });
    } else {
      state.startedAt = Date.now();
    }
    KKUI.enterFullscreen();
    KKUI.setModeChip('● REC');
    state.timerInt = setInterval(function(){ updateUI(); checkSplit(); }, 1000);
    KKBackground.startBackgroundGeoloc(handlePosition).then(function(ok){
      if (!ok) KKGps.start(handlePosition);
    });
    KKBackground.acquireWakeLock();
    updateUI();
  }

  /* ---- Detect non-native → tampilkan warning ---- */
  function detectNative(){
    if (!KKBackground.isNative){
      var w = document.getElementById('kk-bg-warn');
      if (w) w.classList.remove('d-none');
    }
  }

  /* ---- Init ---- */
  document.addEventListener('DOMContentLoaded', function(){
    KKMap.init();
    KKUI.installDimHandlers();
    wire();
    detectNative();
    autoResume();
    if ('serviceWorker' in navigator){
      navigator.serviceWorker.register('/service-worker.js').catch(function(){});
    }
  });

  // Expose untuk debug + save.js
  window.KKTracking = { state: state };
})();

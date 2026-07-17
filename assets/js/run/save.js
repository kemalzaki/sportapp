/* ============================================================
 * KK Run · save.js  — Persistence + server sync
 * ============================================================ */
(function(){
  'use strict';
  var LS_KEY = 'kk_run_state_v3';
  var buffer = [];

  window.KKSave = {
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
    },
    load: function(){
      try { return JSON.parse(localStorage.getItem(LS_KEY) || 'null'); }
      catch(e){ return null; }
    },
    clear: function(){ try { localStorage.removeItem(LS_KEY); } catch(e){} },

    queuePoint: function(pl){ buffer.push(pl); },
    hasQueued: function(){ return buffer.length > 0; },

    startSession: async function(){
      var fd = new FormData();
      fd.append('csrf', window.KK_RUN.csrf);
      fd.append('_action','start');
      var r = await fetch('/api_run.php',{method:'POST',body:fd});
      var d = await r.json();
      if (!d.ok) throw new Error('Gagal memulai sesi');
      return d.id;
    },

    flush: async function(sessionId){
      if (!sessionId || !buffer.length) return;
      while (buffer.length){
        var pl = buffer[0];
        var fd = new FormData();
        fd.append('csrf', window.KK_RUN.csrf);
        fd.append('_action','point');
        fd.append('session_id', sessionId);
        fd.append('lat', pl.lat); fd.append('lng', pl.lng);
        fd.append('acc', pl.acc == null ? '' : pl.acc);
        fd.append('spd', pl.spd == null ? '' : pl.spd);
        fd.append('total_m', pl.total_m);
        try {
          var r = await fetch('/api_run.php', { method:'POST', body:fd, keepalive:true });
          if (!r.ok) return;
          buffer.shift();
        } catch(e){ return; }
      }
    },

    stopSession: async function(sessionId, totalM, durSec){
      var fd = new FormData();
      fd.append('csrf', window.KK_RUN.csrf);
      fd.append('_action','stop');
      fd.append('session_id', sessionId);
      fd.append('total_m', totalM);
      fd.append('durasi', durSec);
      try { await fetch('/api_run.php',{method:'POST',body:fd}); } catch(e){}
    }
  };

  // Auto-flush tiap 5 detik
  setInterval(function(){
    if (window.KKTracking && window.KKTracking.state && window.KKTracking.state.sessionId){
      window.KKSave.flush(window.KKTracking.state.sessionId);
    }
  }, 5000);
})();

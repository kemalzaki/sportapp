/* ============================================================
 * KK Run · ui.js  (R40 — Refactor Bersih)
 * ------------------------------------------------------------
 * Hanya bertugas:
 *   - render metric (dashboard + floating focus stats)
 *   - render split
 *   - GPS chip / mode chip / auto-pause chip
 *   - toggle Focus / Dashboard mode (CSS class saja)
 *   - wire floating map controls (Follow / Compass / Fullscreen / Settings)
 *   - countdown 3-2-1
 *   - KKFinish (finish screen)
 *
 * TIDAK memiliki:
 *   - override function existing
 *   - swipe-to-finish (Stop cukup confirm() — SATU alur)
 *   - hidden button / dispatchEvent / safeClickHidden
 *   - lock screen / auto-dim (hilangkan hack yang tidak dipakai)
 * ============================================================ */
(function(){
  'use strict';

  var MODE_KEY = 'kk_run_mode_v1'; // 'dashboard' | 'focus'
  var mode = 'dashboard';

  /* ---------- Helpers format ---------- */
  function pad2(n){ n=Math.floor(n); return (n<10?'0':'')+n; }
  function fmtTime(sec){
    sec = Math.max(0, Math.floor(sec||0));
    var h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
    return h ? (h+':'+pad2(m)+':'+pad2(s)) : (pad2(m)+':'+pad2(s));
  }
  function fmtPace(secPerKm){
    if (!secPerKm || !isFinite(secPerKm) || secPerKm<=0) return "--'--\"";
    var m = Math.floor(secPerKm/60), s = Math.floor(secPerKm%60);
    return m + "'" + pad2(s) + '"';
  }
  function setText(id, txt){ var el=document.getElementById(id); if (el) el.textContent = txt; }

  /* ---------- Render metric (SATU set id, dipakai Dashboard & Focus).
     Focus Mode hanya memindahkan #kk-stats-card via CSS — id tetap sama. */
  function renderMetrics(m){
    var dist = (m.km||0).toFixed(2);
    var t    = fmtTime(m.tSec);
    var pace = fmtPace(m.paceMoving);
    var speed= (m.speedKmh||0).toFixed(1);
    var cal  = Math.max(0, Math.round(m.calories||0));
    var elev = (m.elev==null) ? '–' : Math.round(m.elev)+' m';
    var apc  = fmtPace(m.paceAvg);

    setText('d-dist', dist);
    setText('d-time', t);
    setText('d-pace', pace);
    setText('d-speed', speed);
    setText('d-cal', cal);
    setText('d-elev', elev);
    setText('d-avgpace', apc);

    renderSplits();
  }

  function renderSplits(){
    var host = document.getElementById('d-splits');
    if (!host) return;
    var st = window.KKTracking && window.KKTracking.state;
    if (!st || !st.kmSplits || !st.kmSplits.length){
      host.innerHTML = '<div class="text-muted small">Belum ada split.</div>';
      return;
    }
    var max = 0;
    st.kmSplits.forEach(function(sp){ if (sp.sec>max) max=sp.sec; });
    var html = '';
    st.kmSplits.forEach(function(sp){
      var pct = max ? Math.max(8, Math.round(sp.sec/max*100)) : 100;
      var pace = fmtPace(sp.sec);
      html += '<div class="kk-split-row"><div class="km">KM '+sp.km+'</div>'
           +  '<div class="bar"><i style="width:'+pct+'%"></i></div>'
           +  '<div class="pace">'+pace+'</div></div>';
    });
    host.innerHTML = html;
  }

  /* ---------- Chips ---------- */
  function setGps(acc, err){
    var chip = document.getElementById('kk-gps-chip'); if (!chip) return;
    if (err){ chip.className='kk-chip status-bad'; chip.textContent='● GPS Error'; return; }
    if (acc == null){ chip.className='kk-chip status-warn'; chip.textContent='● GPS…'; return; }
    if (acc <= 15){ chip.className='kk-chip status-ok';   chip.textContent='● GPS '+Math.round(acc)+'m'; }
    else if (acc <= 40){ chip.className='kk-chip status-warn'; chip.textContent='● GPS '+Math.round(acc)+'m'; }
    else { chip.className='kk-chip status-bad'; chip.textContent='● GPS '+Math.round(acc)+'m'; }
  }
  function setAutoPaused(v){
    var c = document.getElementById('kk-auto-chip'); if (!c) return;
    c.style.display = v ? '' : 'none';
  }
  function setModeChip(text){
    var c = document.getElementById('kk-mode-chip'); if (c){ c.textContent = text; c.style.display = ''; }
    var d = document.getElementById('d-mode-chip'); if (d){ d.textContent = text; d.style.display = ''; }
  }
  function clearModeChip(){
    var c = document.getElementById('kk-mode-chip'); if (c) c.style.display = 'none';
    var d = document.getElementById('d-mode-chip'); if (d) d.style.display = 'none';
  }

  /* ---------- Countdown ---------- */
  function countdown(cb){
    var el = document.getElementById('kk-countdown');
    if (!el){ cb && cb(); return; }
    var n = 3;
    el.textContent = n; el.classList.add('show');
    var t = setInterval(function(){
      n--;
      if (n<=0){
        clearInterval(t);
        el.classList.remove('show');
        cb && cb();
      } else {
        el.textContent = n;
        el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
      }
    }, 900);
  }

  /* ---------- Mode: Dashboard <-> Focus (CSS toggle saja) ---------- */
  function saveViewMode(m){ try{ localStorage.setItem(MODE_KEY, m); }catch(e){} }
  function loadSavedViewMode(){
    try{ return localStorage.getItem(MODE_KEY) || 'dashboard'; }catch(e){ return 'dashboard'; }
  }
  function currentMode(){ return mode; }

  function applyMode(m){
    mode = (m==='focus') ? 'focus' : 'dashboard';
    document.body.classList.toggle('kk-focus-mode', mode==='focus');
    document.body.classList.toggle('kk-dashboard-mode', mode==='dashboard');

    var fab = document.getElementById('kk-fab-fullscreen');
    if (fab){
      fab.classList.toggle('active', mode==='focus');
      fab.setAttribute('title', mode==='focus' ? 'Exit Fullscreen' : 'Fullscreen');
      var icon = fab.querySelector('i');
      if (icon) icon.className = (mode==='focus') ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    }
    saveViewMode(mode);
    // Beri waktu CSS transition selesai lalu minta Leaflet re-measure
    if (window.KKMap && KKMap.invalidate) KKMap.invalidate();
    setTimeout(function(){ if (window.KKMap && KKMap.invalidate) KKMap.invalidate(); }, 320);
  }
  function enterFocusMode(){ applyMode('focus'); }
  function exitFocusMode(){ applyMode('dashboard'); }
  function toggleFocusMode(){ applyMode(mode==='focus' ? 'dashboard' : 'focus'); }
  function initDashboardMode(){ applyMode(loadSavedViewMode()); }

  /* ---------- Wire floating map controls ---------- */
  function wireControls(){
    var fabFs = document.getElementById('kk-fab-fullscreen');
    if (fabFs) fabFs.addEventListener('click', function(e){ e.preventDefault(); toggleFocusMode(); });

    var fabLoc = document.getElementById('kk-fab-location');
    if (fabLoc) fabLoc.addEventListener('click', function(e){
      e.preventDefault();
      var st = window.KKTracking && window.KKTracking.state;
      var p = st && (st.points[st.points.length-1] || st.lastFix);
      if (window.KKMap && KKMap.recenter) KKMap.recenter(p);
    });

    var fabComp = document.getElementById('kk-fab-compass');
    if (fabComp) fabComp.addEventListener('click', function(e){
      e.preventDefault();
      var sel = document.getElementById('rotSel');
      if (!sel) return;
      sel.value = (sel.value === 'heading') ? 'north' : 'heading';
      fabComp.classList.toggle('active', sel.value==='heading');
      if (window.KKMap && KKMap.setRotationEnabled)
        KKMap.setRotationEnabled(sel.value === 'heading');
    });

    var fabSet = document.getElementById('kk-fab-settings');
    var popover = document.getElementById('kk-settings-pop');
    if (fabSet && popover){
      fabSet.addEventListener('click', function(e){
        e.preventDefault(); e.stopPropagation();
        popover.classList.toggle('show');
      });
      document.addEventListener('click', function(e){
        if (!popover.contains(e.target) && e.target !== fabSet && !fabSet.contains(e.target))
          popover.classList.remove('show');
      });
    }

    var rc = document.getElementById('kk-recenter');
    if (rc) rc.addEventListener('click', function(e){
      e.preventDefault();
      var st = window.KKTracking && window.KKTracking.state;
      var p = st && (st.points[st.points.length-1] || st.lastFix);
      if (window.KKMap && KKMap.recenter) KKMap.recenter(p);
    });

    // Sinkronkan icon compass dgn rotSel awal
    var rotSel = document.getElementById('rotSel');
    if (rotSel && fabComp) fabComp.classList.toggle('active', rotSel.value==='heading');
  }

  /* ---------- Konfirmasi Stop ---------- */
  function confirmStop(onFinish){
    if (confirm('Selesaikan sesi tracking sekarang? Data akan disimpan.')){
      try { onFinish && onFinish(); } catch(e){ console.error(e); }
    }
  }

  /* ---------- KKUI export ---------- */
  window.KKUI = {
    renderMetrics: renderMetrics,
    setGps: setGps,
    setAutoPaused: setAutoPaused,
    setModeChip: setModeChip,
    clearModeChip: clearModeChip,
    countdown: countdown,
    enterFocusMode: enterFocusMode,
    exitFocusMode: exitFocusMode,
    toggleFocusMode: toggleFocusMode,
    currentMode: currentMode,
    initDashboardMode: initDashboardMode,
    confirmStop: confirmStop,
    wireControls: wireControls
  };

  /* ============================================================
   *  KKFinish — Finish screen
   * ============================================================ */
  window.KKFinish = {
    open: function(snap){
      try {
        document.body.classList.add('kk-finish-open');
        var km = (snap.totalM/1000).toFixed(2);
        setText('f-dist', km);
        setText('f-time', fmtTime(snap.durationSec));
        var pace = snap.totalM>50 ? (snap.durationSec/(snap.totalM/1000)) : 0;
        setText('f-pace', fmtPace(pace));
        var speed = snap.durationSec>0 ? ((snap.totalM/1000)/(snap.durationSec/3600)) : 0;
        setText('f-speed', speed.toFixed(1));
        setText('f-cal', Math.max(0, Math.round(snap.calories||0)));
        var gain = 0, prev=null;
        (snap.points||[]).forEach(function(p){
          if (p.elev!=null){
            if (prev!=null && p.elev>prev) gain += (p.elev-prev);
            prev = p.elev;
          }
        });
        setText('f-elev', Math.round(gain));
        var when = document.getElementById('kk-finish-when');
        if (when) when.textContent = new Date(snap.startedAt||Date.now()).toLocaleString('id-ID');
        var host = document.getElementById('f-splits');
        if (host){
          if (!snap.kmSplits || !snap.kmSplits.length){
            host.innerHTML = '<div class="text-muted small">Belum ada split.</div>';
          } else {
            var max=0; snap.kmSplits.forEach(function(s){ if(s.sec>max) max=s.sec; });
            var html='';
            snap.kmSplits.forEach(function(s){
              var pct = max?Math.max(8, Math.round(s.sec/max*100)):100;
              html += '<div class="kk-split-row"><div class="km">KM '+s.km+'</div>'
                   +  '<div class="bar"><i style="width:'+pct+'%"></i></div>'
                   +  '<div class="pace">'+fmtPace(s.sec)+'</div></div>';
            });
            host.innerHTML = html;
          }
        }
        drawSimpleChart('f-chart-pace',  (snap.kmSplits||[]).map(function(s){return s.sec/60;}), '#1E90FF');
        var speeds = []; (snap.kmSplits||[]).forEach(function(s){ speeds.push(3600/s.sec); });
        drawSimpleChart('f-chart-speed', speeds, '#22c55e');
        var elevs = (snap.points||[]).filter(function(p){return p.elev!=null;}).map(function(p){return p.elev;});
        drawSimpleChart('f-chart-elev',  elevs, '#f59e0b');
        renderFinishMap(snap);
      } catch(e){ console.error(e); }
    },
    close: function(){
      document.body.classList.remove('kk-finish-open');
    }
  };

  function drawSimpleChart(id, data, color){
    var c = document.getElementById(id); if (!c) return;
    var ctx = c.getContext('2d');
    var w = c.width = c.clientWidth * (window.devicePixelRatio||1);
    var h = c.height = c.clientHeight * (window.devicePixelRatio||1);
    ctx.clearRect(0,0,w,h);
    if (!data || !data.length){
      ctx.fillStyle='#94a3b8'; ctx.font='12px sans-serif';
      ctx.fillText('Data tidak cukup', 8, h/2); return;
    }
    var min = Math.min.apply(null, data), max = Math.max.apply(null, data);
    if (max===min) max = min+1;
    ctx.strokeStyle = color; ctx.lineWidth = 2*(window.devicePixelRatio||1);
    ctx.beginPath();
    data.forEach(function(v,i){
      var x = (i/(data.length-1||1))*w;
      var y = h - ((v-min)/(max-min))*h*0.9 - h*0.05;
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
    ctx.lineTo(w,h); ctx.lineTo(0,h); ctx.closePath();
    ctx.fillStyle = color+'22'; ctx.fill();
  }

  var _finishMap = null, _finishPoly = null;
  function renderFinishMap(snap){
    var host = document.getElementById('kk-finish-map'); if (!host || !window.L) return;
    var pts = (snap.points||[]).map(function(p){return [p.lat,p.lng];});
    if (!_finishMap){
      _finishMap = L.map('kk-finish-map', { zoomControl:false, attributionControl:false }).setView([-6.2, 106.816666], 14);
      L.tileLayer(window.KK_RUN.mapboxTileUrl, { maxZoom:19 }).addTo(_finishMap);
    }
    if (_finishPoly){ try{ _finishMap.removeLayer(_finishPoly);}catch(e){} _finishPoly=null; }
    if (pts.length){
      _finishPoly = L.polyline(pts, { color:'#1E90FF', weight:5, opacity:.95 }).addTo(_finishMap);
      _finishMap.fitBounds(_finishPoly.getBounds(), { padding:[24,24] });
    }
    setTimeout(function(){ _finishMap.invalidateSize(); }, 80);
  }

  /* ---------- Boot ---------- */
  document.addEventListener('DOMContentLoaded', function(){
    initDashboardMode();
    wireControls();
  });

})();

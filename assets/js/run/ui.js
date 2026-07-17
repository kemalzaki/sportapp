/* ============================================================
 * KK Run · ui.js  — Dashboard Mode + Focus Mode (R35)
 * ------------------------------------------------------------
 * TIDAK mengubah gps.js / tracking.js / map.js / save.js /
 * background.js / voice.js. Semua ID DOM & nama fungsi lama
 * dipertahankan agar back-compat dengan tracking.js R34.
 *
 * API publik:
 *   KKUI.renderMetrics(m)
 *   KKUI.setGps(acc, err)
 *   KKUI.setAutoPaused(v)
 *   KKUI.setModeChip(text)
 *   KKUI.countdown(cb)
 *   KKUI.enterFullscreen()  // back-compat -> loadSavedViewMode()
 *   KKUI.exitFullscreen()   // back-compat -> exit focus, kembali dashboard
 *   KKUI.lock()
 *   KKUI.showSwipeFinish(onFinish)
 *   KKUI.installDimHandlers()
 *   KKUI.enterFocusMode() / exitFocusMode() / toggleFocusMode()
 *   KKUI.currentMode() / initDashboardMode()
 *   KKUI.saveViewMode(mode) / loadSavedViewMode()
 * KKFinish.open(snap) / KKFinish.close()
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

  /* ---------- Metrics ---------- */
  function renderMetrics(m){
    var dist = (m.km||0).toFixed(2);
    var t    = fmtTime(m.tSec);
    var pace = fmtPace(m.paceMoving);
    var speed= (m.speedKmh||0).toFixed(1);
    var cal  = Math.max(0, Math.round(m.calories||0));
    var elev = (m.elev==null) ? '–' : Math.round(m.elev)+' m';
    var apc  = fmtPace(m.paceAvg);

    // Focus overlay
    setText('m-dist', dist);
    setText('m-time', t);
    setText('m-pace', pace);
    setText('m-speed', speed);
    setText('m-cal', cal);
    setText('m-elev', elev);
    setText('m-avgpace', apc);

    // Dashboard mirror
    setText('d-dist', dist);
    setText('d-time', t);
    setText('d-pace', pace);
    setText('d-speed', speed);
    setText('d-cal', cal);
    setText('d-elev', elev);
    setText('d-avgpace', apc);

    // Lock screen mini
    setText('lk-metrics', dist+' km · '+t);

    // Splits (dashboard)
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
    var c = document.getElementById('kk-mode-chip'); if (!c) return;
    c.textContent = text; c.style.display = '';
    // Show dashboard REC pill
    var d = document.getElementById('d-mode-chip');
    if (d){ d.textContent = text; d.style.display = ''; }
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
        // retrigger animation
        el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
      }
    }, 900);
  }

  /* ---------- Mode: Dashboard <-> Focus ---------- */
  function saveViewMode(m){ try{ localStorage.setItem(MODE_KEY, m); }catch(e){} }
  function loadSavedViewMode(){
    try{ return localStorage.getItem(MODE_KEY) || 'dashboard'; }catch(e){ return 'dashboard'; }
  }
  function currentMode(){ return mode; }

  function applyMode(m){
    mode = (m==='focus') ? 'focus' : 'dashboard';
    document.body.classList.toggle('kk-focus-mode', mode==='focus');
    document.body.classList.toggle('kk-dashboard-mode', mode==='dashboard');
    // Legacy class untuk kompatibilitas selector R34
    document.body.classList.toggle('kk-tracking-fullscreen', mode==='focus');

    // Update tombol fab fullscreen icon state
    var fab = document.getElementById('kk-fab-fullscreen');
    if (fab){
      fab.classList.toggle('active', mode==='focus');
      fab.setAttribute('title', mode==='focus' ? 'Exit Fullscreen' : 'Fullscreen');
    }
    saveViewMode(mode);
    // Let Leaflet re-measure setelah transisi CSS
    if (window.KKMap && KKMap.invalidate) KKMap.invalidate();
    setTimeout(function(){ if (window.KKMap && KKMap.invalidate) KKMap.invalidate(); }, 320);
  }
  function enterFocusMode(){ applyMode('focus'); }
  function exitFocusMode(){ applyMode('dashboard'); }
  function toggleFocusMode(){ applyMode(mode==='focus' ? 'dashboard' : 'focus'); }
  function initDashboardMode(){ applyMode(loadSavedViewMode()); }

  // Back-compat aliases: R34 tracking.js memanggil enterFullscreen saat Start.
  // Sekarang: hormati preferensi user (Dashboard by default).
  function enterFullscreen(){ applyMode(loadSavedViewMode()); }
  function exitFullscreen(){ applyMode('dashboard'); }

  /* ---------- Lock screen ---------- */
  function lock(){
    var el = document.getElementById('kk-lock'); if (!el) return;
    el.classList.add('show');
    var slide = document.getElementById('kk-lock-slide'); if (!slide) return;
    var thumb = slide.querySelector('.lk-thumb');
    var fill  = slide.querySelector('.lk-fill');
    var W = slide.clientWidth - (thumb?thumb.clientWidth:56) - 8;
    var startX = 0, curX = 0, dragging=false;
    function onDown(e){ dragging=true; startX = (e.touches?e.touches[0].clientX:e.clientX); if(thumb)thumb.classList.add('dragging'); }
    function onMove(e){
      if(!dragging) return;
      var x=(e.touches?e.touches[0].clientX:e.clientX);
      curX = Math.max(0, Math.min(W, x-startX));
      if(thumb) thumb.style.transform = 'translateX('+curX+'px)';
      if(fill)  fill.style.width = (curX+((thumb?thumb.clientWidth:56)/2))+'px';
    }
    function onUp(){
      if(!dragging) return; dragging=false;
      if(thumb) thumb.classList.remove('dragging');
      if (curX >= W*0.85){ el.classList.remove('show'); }
      else { if(thumb)thumb.style.transform='translateX(0)'; if(fill)fill.style.width='0'; }
      curX = 0;
      slide.removeEventListener('mousemove', onMove);
      slide.removeEventListener('mouseup', onUp);
    }
    slide.onmousedown = onDown; slide.ontouchstart = onDown;
    slide.onmousemove = onMove; slide.ontouchmove = onMove;
    slide.onmouseup   = onUp;   slide.ontouchend  = onUp;
  }

  /* ---------- Stop confirmation ----------
   * Fix bug R34: di Dashboard Mode swipe UI ada di .kk-ctrl yang display:none,
   * jadi user tak pernah bisa memicu stop. Sekarang:
   *  - Focus Mode → swipe-to-finish (anti salah pencet)
   *  - Dashboard Mode → confirm() biasa
   */
  function showSwipeFinish(onFinish){
    if (mode !== 'focus'){
      if (confirm('Selesaikan sesi tracking sekarang? Data akan disimpan.')){
        try { onFinish && onFinish(); } catch(e){ console.error(e); }
      }
      return;
    }
    var sw = document.getElementById('kk-swipe'); if (!sw){ onFinish && onFinish(); return; }
    sw.classList.add('show');
    var thumb = sw.querySelector('.sw-thumb');
    var fill  = sw.querySelector('.sw-fill');
    var W = sw.clientWidth - (thumb?thumb.clientWidth:50) - 8;
    var startX=0, curX=0, dragging=false, done=false;
    function down(e){ dragging=true; startX=(e.touches?e.touches[0].clientX:e.clientX); if(thumb)thumb.classList.add('dragging'); }
    function move(e){
      if(!dragging||done) return;
      var x=(e.touches?e.touches[0].clientX:e.clientX);
      curX = Math.max(0, Math.min(W, x-startX));
      if(thumb) thumb.style.transform='translateX('+curX+'px)';
      if(fill)  fill.style.width = (curX+((thumb?thumb.clientWidth:50)/2))+'px';
    }
    function up(){
      if(!dragging) return; dragging=false;
      if(thumb) thumb.classList.remove('dragging');
      if (curX >= W*0.85 && !done){
        done = true; sw.classList.remove('show');
        try { onFinish && onFinish(); } catch(e){ console.error(e); }
      } else {
        if(thumb) thumb.style.transform='translateX(0)';
        if(fill)  fill.style.width='0';
      }
      curX=0;
    }
    sw.onmousedown=down; sw.ontouchstart=down;
    sw.onmousemove=move; sw.ontouchmove=move;
    sw.onmouseup=up;     sw.ontouchend=up;
    // Auto hide setelah 8s tanpa interaksi
    setTimeout(function(){ if(!done) sw.classList.remove('show'); }, 8000);
  }

  /* ---------- Auto-dim (focus only) ---------- */
  var dimTimer = null;
  function installDimHandlers(){
    var dim = document.getElementById('kk-dim'); if (!dim) return;
    function reset(){
      dim.classList.remove('on');
      clearTimeout(dimTimer);
      dimTimer = setTimeout(function(){
        if (mode === 'focus') dim.classList.add('on');
      }, 45000);
    }
    ['touchstart','mousedown','keydown','scroll','pointerdown'].forEach(function(ev){
      document.addEventListener(ev, reset, { passive:true });
    });
    dim.addEventListener('click', function(){ dim.classList.remove('on'); reset(); });
    reset();
  }

  /* ---------- Wire floating map controls & mode buttons ---------- */
  function wireControls(){
    var fabFs = document.getElementById('kk-fab-fullscreen');
    if (fabFs) fabFs.addEventListener('click', toggleFocusMode);

    var exit = document.getElementById('kk-exit-focus');
    if (exit) exit.addEventListener('click', exitFocusMode);

    var fabLoc = document.getElementById('kk-fab-location');
    if (fabLoc) fabLoc.addEventListener('click', function(){
      var st = window.KKTracking && window.KKTracking.state;
      var p = st && (st.points[st.points.length-1] || st.lastFix);
      if (window.KKMap && KKMap.recenter) KKMap.recenter(p);
    });

    var fabComp = document.getElementById('kk-fab-compass');
    if (fabComp) fabComp.addEventListener('click', function(){
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
        e.stopPropagation();
        popover.classList.toggle('show');
      });
      document.addEventListener('click', function(e){
        if (!popover.contains(e.target) && e.target !== fabSet)
          popover.classList.remove('show');
      });
    }

    // Ripple sederhana
    document.querySelectorAll('.kk-mapfab').forEach(function(btn){
      btn.addEventListener('click', function(){
        btn.classList.add('kk-ripple');
        setTimeout(function(){ btn.classList.remove('kk-ripple'); }, 320);
      });
    });

    // Sinkronkan icon compass dengan rotSel awal
    var rotSel = document.getElementById('rotSel');
    if (rotSel && fabComp) fabComp.classList.toggle('active', rotSel.value==='heading');
  }

  /* ---------- KKUI export ---------- */
  window.KKUI = {
    renderMetrics: renderMetrics,
    setGps: setGps,
    setAutoPaused: setAutoPaused,
    setModeChip: setModeChip,
    countdown: countdown,
    enterFullscreen: enterFullscreen,
    exitFullscreen: exitFullscreen,
    lock: lock,
    showSwipeFinish: showSwipeFinish,
    installDimHandlers: installDimHandlers,
    enterFocusMode: enterFocusMode,
    exitFocusMode: exitFocusMode,
    toggleFocusMode: toggleFocusMode,
    currentMode: currentMode,
    initDashboardMode: initDashboardMode,
    saveViewMode: saveViewMode,
    loadSavedViewMode: loadSavedViewMode
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
        // Elev gain
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
        // Splits
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
        // Grafik sederhana pada canvas
        drawSimpleChart('f-chart-pace',  (snap.kmSplits||[]).map(function(s){return s.sec/60;}), '#1E90FF');
        var speeds = []; if ((snap.kmSplits||[]).length){
          snap.kmSplits.forEach(function(s){ speeds.push(3600/s.sec); });
        }
        drawSimpleChart('f-chart-speed', speeds, '#22c55e');
        var elevs = (snap.points||[]).filter(function(p){return p.elev!=null;}).map(function(p){return p.elev;});
        drawSimpleChart('f-chart-elev',  elevs, '#f59e0b');
        // Mini finish map
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
    // fill
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

    // Tombol dashboard: Mulai / Pause / Resume / Stop delegasi ke tombol Focus asli
    // sehingga logika di tracking.js tidak berubah.
    function forward(dashId, targetId){
      var d = document.getElementById(dashId); if (!d) return;
      d.addEventListener('click', function(){
        var t = document.getElementById(targetId);
        if (t) t.click();
      });
    }
    forward('kk-dash-btn-start',  'kk-btn-start');
    forward('kk-dash-btn-pause',  'kk-btn-pause');
    forward('kk-dash-btn-resume', 'kk-btn-resume');
    forward('kk-dash-btn-stop',   'kk-btn-stop');
  });

})();

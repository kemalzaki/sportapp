/* ============================================================
 * KK Run · ui.js — Fullscreen mode, floating metrics, lock,
 *                  swipe-to-finish, auto-dim, countdown, finish
 * ============================================================ */
(function(){
  'use strict';

  function $(id){ return document.getElementById(id); }
  function fmtTime(s){
    s = Math.max(0, s|0);
    var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
    if (h) return h+':'+String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
    return String(m).padStart(2,'0')+':'+String(ss).padStart(2,'0');
  }
  function fmtPace(secPerKm){
    if (!isFinite(secPerKm) || secPerKm <= 0 || secPerKm > 60*60) return "--'--\"";
    var m = Math.floor(secPerKm/60), s = Math.floor(secPerKm%60);
    return m+"'"+String(s).padStart(2,'0')+'"';
  }

  var dimTimer = null;
  function bumpDim(){
    var dim = $('kk-dim'); if (!dim) return;
    dim.classList.remove('on');
    if (dimTimer) clearTimeout(dimTimer);
    dimTimer = setTimeout(function(){ dim.classList.add('on'); }, 45000);
  }

  /* --- Slide-to-action (returns cleanup fn) --- */
  function attachSlide(container, thumb, fill, onComplete){
    var startX=0, cur=0, max=0, active=false;
    function reset(){ cur=0; thumb.style.transform=''; fill.style.width='0'; }
    function down(e){
      active=true; thumb.classList.add('dragging');
      startX = (e.touches ? e.touches[0].clientX : e.clientX);
      max = container.clientWidth - thumb.clientWidth - 8;
    }
    function move(e){
      if (!active) return;
      var x = (e.touches ? e.touches[0].clientX : e.clientX);
      cur = Math.max(0, Math.min(max, x - startX));
      thumb.style.transform = 'translateX(' + cur + 'px)';
      fill.style.width = (cur + thumb.clientWidth) + 'px';
      e.preventDefault();
    }
    function up(){
      if (!active) return;
      active=false; thumb.classList.remove('dragging');
      if (cur >= max - 4){ onComplete(); reset(); }
      else { reset(); }
    }
    thumb.addEventListener('touchstart', down, {passive:true});
    thumb.addEventListener('mousedown', down);
    window.addEventListener('touchmove', move, {passive:false});
    window.addEventListener('mousemove', move);
    window.addEventListener('touchend', up);
    window.addEventListener('mouseup', up);
    return function(){
      thumb.removeEventListener('touchstart', down);
      thumb.removeEventListener('mousedown', down);
      window.removeEventListener('touchmove', move);
      window.removeEventListener('mousemove', move);
      window.removeEventListener('touchend', up);
      window.removeEventListener('mouseup', up);
    };
  }

  window.KKUI = {
    fmtTime: fmtTime,
    fmtPace: fmtPace,

    enterFullscreen: function(){
      document.body.classList.add('kk-tracking-fullscreen');
      var root = $('kk-track-root');
      if (root) root.setAttribute('aria-hidden','false');
      bumpDim();
      window.KKMap && window.KKMap.invalidate();
      // Try Fullscreen API pada browser (di APK biasanya di-skip)
      var el = document.documentElement;
      if (el.requestFullscreen){ el.requestFullscreen().catch(function(){}); }
    },
    exitFullscreen: function(){
      document.body.classList.remove('kk-tracking-fullscreen');
      var root = $('kk-track-root');
      if (root) root.setAttribute('aria-hidden','true');
      if (document.exitFullscreen){ document.exitFullscreen().catch(function(){}); }
    },

    setGps: function(acc, lost){
      var chip = $('kk-gps-chip'); if (!chip) return;
      if (lost){ chip.className = 'kk-chip status-bad'; chip.innerHTML = '🔴 GPS Hilang'; return; }
      if (acc == null){ chip.className='kk-chip status-warn'; chip.innerHTML='🟡 GPS…'; return; }
      if (acc < 10){ chip.className='kk-chip status-ok'; chip.innerHTML='🟢 GPS ±'+Math.round(acc)+'m'; }
      else if (acc < 25){ chip.className='kk-chip status-warn'; chip.innerHTML='🟡 GPS ±'+Math.round(acc)+'m'; }
      else { chip.className='kk-chip status-bad'; chip.innerHTML='🔴 GPS ±'+Math.round(acc)+'m'; }
    },

    setAutoPaused: function(on){
      var c = $('kk-auto-chip'); if (!c) return;
      c.style.display = on ? '' : 'none';
      c.textContent = on ? '⏸ Auto-Pause' : '';
    },
    setModeChip: function(txt){
      var c = $('kk-mode-chip'); if (!c) return;
      if (!txt){ c.style.display='none'; return; }
      c.style.display=''; c.textContent = txt;
    },

    renderMetrics: function(m){
      $('m-dist').textContent = (m.km).toFixed(2);
      $('m-time').textContent = fmtTime(m.tSec);
      $('m-pace').textContent = fmtPace(m.paceMoving);
      $('m-speed').textContent = (m.speedKmh).toFixed(1);
      $('m-cal').textContent = m.calories;
      $('m-elev').textContent = m.elev==null?'–':Math.round(m.elev);
      $('m-avgpace').textContent = fmtPace(m.paceAvg);
      var lk = $('lk-metrics');
      if (lk) lk.textContent = (m.km).toFixed(2)+' km · '+fmtTime(m.tSec);
    },

    bumpDim: bumpDim,

    /* ---- Lock screen ---- */
    _lockCleanup: null,
    lock: function(){
      var l = $('kk-lock'); l.classList.add('show');
      var slide = $('kk-lock-slide');
      var thumb = slide.querySelector('.lk-thumb');
      var fill  = slide.querySelector('.lk-fill');
      this._lockCleanup = attachSlide(slide, thumb, fill, function(){
        l.classList.remove('show');
        if (window.KKUI._lockCleanup){ window.KKUI._lockCleanup(); window.KKUI._lockCleanup=null; }
      });
    },

    /* ---- Swipe to finish ---- */
    _swCleanup: null,
    showSwipeFinish: function(onDone){
      var sw = $('kk-swipe'); sw.classList.add('show');
      var thumb = sw.querySelector('.sw-thumb');
      var fill  = sw.querySelector('.sw-fill');
      if (this._swCleanup) this._swCleanup();
      this._swCleanup = attachSlide(sw, thumb, fill, function(){
        sw.classList.remove('show');
        if (window.KKUI._swCleanup){ window.KKUI._swCleanup(); window.KKUI._swCleanup=null; }
        onDone();
      });
      // Auto-hide setelah 8 detik jika tidak digeser
      setTimeout(function(){ sw.classList.remove('show'); }, 8000);
    },

    /* ---- Countdown 3..2..1 sebelum mulai ---- */
    countdown: function(onDone){
      var el = $('kk-countdown');
      var n = 3;
      el.textContent = n; el.classList.add('show');
      var iv = setInterval(function(){
        n--; el.classList.remove('show');
        void el.offsetWidth;
        if (n <= 0){ clearInterval(iv); el.style.display='none'; onDone(); return; }
        el.textContent = n; el.classList.add('show');
      }, 900);
    },

    /* ---- Auto-dim: setiap sentuh reset ---- */
    installDimHandlers: function(){
      var events = ['touchstart','mousedown','keydown'];
      events.forEach(function(e){
        window.addEventListener(e, bumpDim, {passive:true});
      });
    }
  };

  /* ============================================================
   *  Finish screen renderer
   * ============================================================ */
  function drawLineChart(canvas, values, opts){
    if (!canvas || !values || !values.length) return;
    var ctx = canvas.getContext('2d');
    var W = canvas.width = canvas.offsetWidth * 2;
    var H = canvas.height = 240;
    ctx.clearRect(0,0,W,H);
    var pad = 20;
    var lo = Infinity, hi = -Infinity;
    values.forEach(function(v){ if (v!=null){ lo=Math.min(lo,v); hi=Math.max(hi,v); }});
    if (!isFinite(lo)) return;
    if (hi === lo) hi = lo + 1;
    ctx.strokeStyle = (opts && opts.color) || '#fc5200';
    ctx.lineWidth = 3;
    ctx.beginPath();
    values.forEach(function(v, i){
      if (v == null) return;
      var x = pad + (i/(values.length-1))*(W-pad*2);
      var y = H - pad - ((v-lo)/(hi-lo))*(H-pad*2);
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
    // Fill light
    ctx.lineTo(W-pad, H-pad); ctx.lineTo(pad, H-pad); ctx.closePath();
    ctx.fillStyle = (opts && opts.fill) || 'rgba(252,82,0,.12)';
    ctx.fill();
  }

  window.KKFinish = {
    open: function(state){
      document.body.classList.add('kk-finish-open');
      var el = $('kk-finish'); el.setAttribute('aria-hidden','false');
      // Summary
      var km = state.totalM/1000;
      var t = state.durationSec || state.elapsedSec || 0;
      var avgKmh = (km/(Math.max(1,t)/3600));
      $('f-dist').textContent = km.toFixed(2);
      $('f-time').textContent = fmtTime(t);
      $('f-pace').textContent = fmtPace(t/(km||1));
      $('f-speed').textContent = isFinite(avgKmh)?avgKmh.toFixed(1):'0.0';
      $('f-cal').textContent = state.calories||0;
      var elevs = (state.points||[]).map(function(p){ return p.elev; }).filter(function(v){ return v!=null; });
      var elevRange = elevs.length ? Math.round(Math.max.apply(null,elevs)-Math.min.apply(null,elevs)) : 0;
      $('f-elev').textContent = elevRange;
      $('kk-finish-when').textContent = new Date(state.startedAt||Date.now()).toLocaleString('id-ID');

      // Splits
      var sh = $('f-splits');
      if (state.kmSplits && state.kmSplits.length){
        var maxSec = Math.max.apply(null, state.kmSplits.map(function(s){return s.sec;}));
        sh.innerHTML = state.kmSplits.map(function(s){
          var w = Math.min(100, Math.round(s.sec/maxSec*100));
          return '<div class="kk-split-row">'
            + '<div class="km">KM '+s.km+'</div>'
            + '<div class="bar"><i style="width:'+w+'%"></i></div>'
            + '<div class="pace">'+fmtTime(s.sec)+'</div>'
            + '</div>';
        }).join('');
      } else {
        sh.innerHTML = '<div class="text-muted small">Sesi terlalu singkat untuk split.</div>';
      }

      // Charts (pace per menit, speed per menit, elevasi per titik)
      var pts = state.points || [];
      var speedSeries = pts.map(function(p){ return p.spd != null ? p.spd*3.6 : null; });
      var elevSeries  = pts.map(function(p){ return p.elev; });
      // pace = 60/kmh minutes per km
      var paceSeries = speedSeries.map(function(v){
        return (v && v > 0.5) ? (60/v) : null;
      });
      drawLineChart($('f-chart-pace'), paceSeries, {color:'#ef4444', fill:'rgba(239,68,68,.12)'});
      drawLineChart($('f-chart-speed'), speedSeries, {color:'#0ea5e9', fill:'rgba(14,165,233,.12)'});
      drawLineChart($('f-chart-elev'), elevSeries, {color:'#22c55e', fill:'rgba(34,197,94,.12)'});

      // Map besar dengan polyline
      setTimeout(function(){
        var m = L.map('kk-finish-map', { zoomControl: true, attributionControl: false })
                 .setView([-6.2,106.8], 14);
        L.tileLayer(window.KK_RUN.mapboxTileUrl, {maxZoom:19}).addTo(m);
        var coords = pts.map(function(p){ return [p.lat, p.lng]; });
        if (coords.length > 1){
          var ln = L.polyline(coords, {color:'#fc5200', weight:6}).addTo(m);
          m.fitBounds(ln.getBounds(), {padding:[24,24]});
          L.marker(coords[0]).addTo(m).bindTooltip('Start');
          L.marker(coords[coords.length-1]).addTo(m).bindTooltip('Finish');
        }
        m.invalidateSize();
      }, 80);
    },
    close: function(){
      document.body.classList.remove('kk-finish-open');
      var el = $('kk-finish'); el.setAttribute('aria-hidden','true');
    }
  };
})();

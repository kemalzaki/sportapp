/* ============================================================
 * KK Run · ui.js — R35
 * Dashboard Mode (default) + Focus Mode (fullscreen overlay)
 * Floating map controls (Follow, Compass, Fullscreen, Settings)
 * KawanKeringat identity, glassmorphism, persist mode ke localStorage.
 *
 * Modul JS lain (gps.js/tracking.js/map.js/save.js/background.js/voice.js)
 * TIDAK diubah. Semua logic mode berada di sini via toggle CSS class
 * (tanpa destroy / recreate Leaflet).
 * ============================================================ */
(function(){
  'use strict';

  var LS_KEY = 'kk_run_mode_v1';   // 'dashboard' | 'focus'

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
  function readMode(){
    try { var v = localStorage.getItem(LS_KEY); return v === 'focus' ? 'focus' : 'dashboard'; }
    catch(e){ return 'dashboard'; }
  }
  function writeMode(m){
    try { localStorage.setItem(LS_KEY, m); } catch(e){}
  }

  /* -------- Auto-dim -------- */
  var dimTimer = null;
  function bumpDim(){
    var dim = $('kk-dim'); if (!dim) return;
    dim.classList.remove('on');
    if (dimTimer) clearTimeout(dimTimer);
    dimTimer = setTimeout(function(){ dim.classList.add('on'); }, 45000);
  }

  /* -------- Slide-to-action -------- */
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

  /* -------- Ripple effect (untuk .kk-mapfab) -------- */
  function addRipple(el, ev){
    var r = document.createElement('span');
    r.className = 'ripple';
    var rect = el.getBoundingClientRect();
    var d = Math.max(rect.width, rect.height);
    r.style.width = r.style.height = d+'px';
    var x = (ev.touches ? ev.touches[0].clientX : ev.clientX) - rect.left - d/2;
    var y = (ev.touches ? ev.touches[0].clientY : ev.clientY) - rect.top - d/2;
    r.style.left = x+'px'; r.style.top = y+'px';
    el.appendChild(r);
    setTimeout(function(){ r.remove(); }, 600);
  }

  /* ============================================================
   *  Mode Management (Dashboard <-> Focus)
   * ============================================================ */
  function applyMode(mode, opts){
    opts = opts || {};
    var body = document.body;
    if (mode === 'focus'){
      body.classList.add('kk-focus-mode');
      body.classList.add('kk-tracking-fullscreen'); // legacy compat (R34 CSS)
      var f = $('kk-fab-fullscreen'); if (f) f.classList.add('active');
      var ex = $('kk-fab-exit-focus'); if (ex) ex.style.display = 'inline-flex';
      // Try Fullscreen API (browser). Silent skip di APK.
      if (opts.requestFs && document.documentElement.requestFullscreen){
        document.documentElement.requestFullscreen().catch(function(){});
      }
    } else {
      body.classList.remove('kk-focus-mode');
      body.classList.remove('kk-tracking-fullscreen');
      var f2 = $('kk-fab-fullscreen'); if (f2) f2.classList.remove('active');
      var ex2 = $('kk-fab-exit-focus'); if (ex2) ex2.style.display = 'none';
      if (document.fullscreenElement && document.exitFullscreen){
        document.exitFullscreen().catch(function(){});
      }
    }
    writeMode(mode);
    // JANGAN destroy Leaflet — cukup invalidateSize agar peta menyesuaikan.
    setTimeout(function(){
      if (window.KKMap && window.KKMap.invalidate) window.KKMap.invalidate();
    }, 60);
    // Bump dim reset
    bumpDim();
  }

  function currentMode(){
    return document.body.classList.contains('kk-focus-mode') ? 'focus' : 'dashboard';
  }

  /* ============================================================
   *  Public API — window.KKUI
   * ============================================================ */
  window.KKUI = {
    fmtTime: fmtTime,
    fmtPace: fmtPace,
    bumpDim: bumpDim,

    /* ----- Mode ----- */
    enterFocusMode: function(){ applyMode('focus', {requestFs:true}); },
    exitFocusMode:  function(){ applyMode('dashboard', {}); },
    toggleFocusMode:function(){ applyMode(currentMode()==='focus' ? 'dashboard' : 'focus', {requestFs:true}); },
    currentMode: currentMode,

    /* ----- Back-compat dgn tracking.js R34 ----- */
    // tracking.js memanggil enterFullscreen() saat mulai & exitFullscreen() saat stop.
    // Kita routing ke mode terakhir user (default Dashboard).
    enterFullscreen: function(){
      document.body.classList.add('kk-tracking-active');
      var root = $('kk-track-root');
      if (root) root.setAttribute('aria-hidden','false');
      var savedMode = readMode();
      if (savedMode === 'focus') applyMode('focus', {requestFs:false});
      else applyMode('dashboard', {});
    },
    exitFullscreen: function(){
      document.body.classList.remove('kk-tracking-active');
      // Kembali ke Dashboard Mode (bukan hide semua) supaya panel muncul lagi.
      applyMode('dashboard', {});
    },

    /* ----- Chip status ----- */
    setGps: function(acc, lost){
      var chip = $('kk-gps-chip');
      var dgps = $('d-gps');
      if (!chip) return;
      if (lost){
        chip.className = 'kk-chip status-bad'; chip.innerHTML = '🔴 GPS Hilang';
        if (dgps) dgps.textContent = '–'; return;
      }
      if (acc == null){
        chip.className='kk-chip status-warn'; chip.innerHTML='🟡 GPS…';
        if (dgps) dgps.textContent = '–'; return;
      }
      var lvl = acc < 10 ? 'ok' : (acc < 25 ? 'warn' : 'bad');
      var emo = lvl==='ok'?'🟢':(lvl==='warn'?'🟡':'🔴');
      chip.className = 'kk-chip status-'+lvl;
      chip.innerHTML = emo+' GPS ±'+Math.round(acc)+'m';
      if (dgps) dgps.textContent = '±'+Math.round(acc)+'m';
    },
    setAutoPaused: function(on){
      var c = $('kk-auto-chip'); if (!c) return;
      c.style.display = on ? '' : 'none';
      c.textContent = on ? '⏸ Auto-Pause' : '';
      var s = $('kk-dash-status'); if (s) s.textContent = on ? 'Auto-Pause' : 'Berjalan';
    },
    setModeChip: function(txt){
      var c = $('kk-mode-chip'); if (!c) return;
      if (!txt){ c.style.display='none'; return; }
      c.style.display=''; c.textContent = txt;
      // Toggle .rec kelas untuk animasi blink halus
      if (/REC/i.test(txt)) c.classList.add('rec'); else c.classList.remove('rec');
      // Sync tombol dashboard live pause/resume
      var isPause = /JEDA|PAUSE/i.test(txt);
      var p = $('kk-dash-btn-pause'), r = $('kk-dash-btn-resume');
      if (p && r){
        p.style.display = isPause ? 'none' : '';
        r.style.display = isPause ? '' : 'none';
      }
      var s = $('kk-dash-status'); if (s) s.textContent = isPause ? 'Dijeda' : 'Berjalan';
    },

    /* ----- Metrics (rendered di Dashboard + Focus) ----- */
    renderMetrics: function(m){
      // Focus overlay
      var mDist = $('m-dist'); if (mDist) mDist.textContent = (m.km).toFixed(2);
      var el;
      if ((el=$('m-time'))) el.textContent = fmtTime(m.tSec);
      if ((el=$('m-pace'))) el.textContent = fmtPace(m.paceMoving);
      if ((el=$('m-speed'))) el.textContent = (m.speedKmh).toFixed(1);
      if ((el=$('m-cal'))) el.textContent = m.calories;
      if ((el=$('m-elev'))) el.textContent = m.elev==null?'–':Math.round(m.elev);
      if ((el=$('m-avgpace'))) el.textContent = fmtPace(m.paceAvg);
      // Dashboard stat grid
      if ((el=$('d-dist'))) el.textContent = (m.km).toFixed(2);
      if ((el=$('d-time'))) el.textContent = fmtTime(m.tSec);
      if ((el=$('d-pace'))) el.textContent = fmtPace(m.paceMoving);
      if ((el=$('d-speed'))) el.textContent = (m.speedKmh).toFixed(1);
      if ((el=$('d-cal'))) el.textContent = m.calories;
      if ((el=$('d-elev'))) el.textContent = m.elev==null?'–':Math.round(m.elev);
      if ((el=$('d-avgpace'))) el.textContent = fmtPace(m.paceAvg);
      // Lock screen
      var lk = $('lk-metrics');
      if (lk) lk.textContent = (m.km).toFixed(2)+' km · '+fmtTime(m.tSec);
      // Splits di panel dashboard
      if (m.kmSplits && m.kmSplits.length){
        var sh = $('d-splits');
        if (sh){
          var maxSec = Math.max.apply(null, m.kmSplits.map(function(s){return s.sec;}));
          sh.innerHTML = m.kmSplits.map(function(s){
            var w = Math.min(100, Math.round(s.sec/maxSec*100));
            return '<div class="kk-split-row">'
              + '<div class="km">KM '+s.km+'</div>'
              + '<div class="bar"><i style="width:'+w+'%"></i></div>'
              + '<div class="pace">'+fmtTime(s.sec)+'</div>'
              + '</div>';
          }).join('');
        }
      }
    },

    /* ----- Lock ----- */
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

    /* ----- Swipe to finish (Focus Mode) / Confirm (Dashboard Mode) ----- */
    _swCleanup: null,
    showSwipeFinish: function(onDone){
      // Dashboard Mode: swipe UI berada di .kk-ctrl yang display:none,
      // sehingga user tidak akan pernah melihatnya. Gunakan confirm() supaya
      // Stop tetap berfungsi dan proses save/upload berjalan normal.
      if (!document.body.classList.contains('kk-focus-mode')){
        if (confirm('Selesaikan sesi tracking dan simpan aktivitas ini?')){
          onDone();
        }
        return;
      }
      var sw = $('kk-swipe'); if (!sw){ onDone(); return; }
      sw.classList.add('show');
      var thumb = sw.querySelector('.sw-thumb');
      var fill  = sw.querySelector('.sw-fill');
      if (this._swCleanup) this._swCleanup();
      this._swCleanup = attachSlide(sw, thumb, fill, function(){
        sw.classList.remove('show');
        if (window.KKUI._swCleanup){ window.KKUI._swCleanup(); window.KKUI._swCleanup=null; }
        onDone();
      });
      setTimeout(function(){ sw.classList.remove('show'); }, 8000);
    },

    /* ----- Countdown 3..2..1 ----- */
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

    installDimHandlers: function(){
      var events = ['touchstart','mousedown','keydown'];
      events.forEach(function(e){
        window.addEventListener(e, bumpDim, {passive:true});
      });
    },

    /* ============================================================
     *  Dashboard Mode init — dipanggil sekali saat DOM ready.
     *  Menyiapkan peta preview, floating controls, dan restore mode
     *  terakhir dari localStorage.
     * ============================================================ */
    initDashboardMode: function(){
      // Restore body class
      document.body.classList.add('kk-run-page');
      // Preview map sebelum sesi mulai (agar user langsung melihat mini-map).
      // KKMap.init() dari map.js sudah menerima elemen '#kk-map' — panggil
      // sekali jika belum di-init oleh tracking.js.
      try {
        if (window.KKMap && typeof window.KKMap.init === 'function' && !window.KKMap._inited){
          window.KKMap.init('kk-map');
          window.KKMap._inited = true;
        }
      } catch(e){ /* noop */ }

      // ---- Floating map controls ----
      var mapfabs = document.querySelectorAll('.kk-mapfab');
      mapfabs.forEach(function(el){
        el.addEventListener('click', function(ev){ addRipple(el, ev); });
      });

      var followBtn = $('kk-fab-follow');
      if (followBtn){
        followBtn.classList.add('active');
        followBtn.addEventListener('click', function(){
          if (window.KKMap && window.KKMap.recenter){
            try { window.KKMap.recenter(); } catch(e){}
          }
          followBtn.classList.add('active');
        });
      }

      var compassBtn = $('kk-fab-compass');
      var rotSel = $('rotSel');
      if (compassBtn){
        compassBtn.addEventListener('click', function(){
          var nowNorth = compassBtn.classList.toggle('active');
          if (window.KKMap && window.KKMap.setRotationEnabled){
            window.KKMap.setRotationEnabled(!nowNorth); // active = North-up = rotation off
          }
          if (rotSel) rotSel.value = nowNorth ? 'north' : 'heading';
        });
      }
      if (rotSel){
        rotSel.addEventListener('change', function(){
          var north = rotSel.value === 'north';
          if (window.KKMap && window.KKMap.setRotationEnabled) window.KKMap.setRotationEnabled(!north);
          if (compassBtn) compassBtn.classList.toggle('active', north);
        });
      }

      var fsBtn = $('kk-fab-fullscreen');
      if (fsBtn) fsBtn.addEventListener('click', function(){ window.KKUI.toggleFocusMode(); });
      var exitBtn = $('kk-fab-exit-focus');
      if (exitBtn) exitBtn.addEventListener('click', function(){ window.KKUI.exitFocusMode(); });

      var setBtn = $('kk-fab-settings');
      var pop = $('kk-settings-pop');
      if (setBtn && pop){
        setBtn.addEventListener('click', function(ev){
          ev.stopPropagation();
          pop.classList.toggle('show');
          setBtn.classList.toggle('active', pop.classList.contains('show'));
        });
        document.addEventListener('click', function(ev){
          if (!pop.contains(ev.target) && ev.target !== setBtn){
            pop.classList.remove('show'); setBtn.classList.remove('active');
          }
        });
      }

      // ---- Dashboard-mode live control buttons (mirror ke tombol focus) ----
      var dp = $('kk-dash-btn-pause'), dr = $('kk-dash-btn-resume'), ds = $('kk-dash-btn-stop'),
          df = $('kk-dash-btn-focus');
      if (dp) dp.addEventListener('click', function(){ var b=$('kk-btn-pause'); if(b) b.click(); });
      if (dr) dr.addEventListener('click', function(){ var b=$('kk-btn-resume'); if(b) b.click(); });
      if (ds) ds.addEventListener('click', function(){ var b=$('kk-btn-stop'); if(b) b.click(); });
      if (df) df.addEventListener('click', function(){ window.KKUI.enterFocusMode(); });

      // ---- Restore mode terakhir (hanya applyMode kalau user memang sedang
      // dalam sesi aktif atau memilih focus). Saat pertama buka halaman,
      // biarkan Dashboard Mode (spec: default = Dashboard).
      // Focus hanya diaktifkan otomatis kalau tracking sedang berjalan
      // dan mode terakhir = focus.
      if (window.KK_RUN && window.KK_RUN.sessionId){
        // Ada sesi aktif → aktifkan tracking-active + mode tersimpan
        document.body.classList.add('kk-tracking-active');
        if (readMode() === 'focus') applyMode('focus', {requestFs:false});
      }

      // Invalidate size sekali agar peta preview tergambar
      setTimeout(function(){
        if (window.KKMap && window.KKMap.invalidate) window.KKMap.invalidate();
      }, 120);
    }
  };

  /* ============================================================
   *  Finish screen renderer (identity KK — Electric Blue)
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
    ctx.strokeStyle = (opts && opts.color) || '#1E90FF';
    ctx.lineWidth = 3;
    ctx.beginPath();
    values.forEach(function(v, i){
      if (v == null) return;
      var x = pad + (i/(values.length-1))*(W-pad*2);
      var y = H - pad - ((v-lo)/(hi-lo))*(H-pad*2);
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
    ctx.lineTo(W-pad, H-pad); ctx.lineTo(pad, H-pad); ctx.closePath();
    ctx.fillStyle = (opts && opts.fill) || 'rgba(30,144,255,.12)';
    ctx.fill();
  }

  window.KKFinish = {
    open: function(state){
      document.body.classList.add('kk-finish-open');
      var el = $('kk-finish'); el.setAttribute('aria-hidden','false');
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

      var pts = state.points || [];
      var speedSeries = pts.map(function(p){ return p.spd != null ? p.spd*3.6 : null; });
      var elevSeries  = pts.map(function(p){ return p.elev; });
      var paceSeries  = speedSeries.map(function(v){ return (v && v > 0.5) ? (60/v) : null; });
      drawLineChart($('f-chart-pace'),  paceSeries,  {color:'#1E90FF', fill:'rgba(30,144,255,.14)'});
      drawLineChart($('f-chart-speed'), speedSeries, {color:'#4FB0FF', fill:'rgba(79,176,255,.14)'});
      drawLineChart($('f-chart-elev'),  elevSeries,  {color:'#22c55e', fill:'rgba(34,197,94,.12)'});

      setTimeout(function(){
        var m = L.map('kk-finish-map', { zoomControl: true, attributionControl: false })
                 .setView([-6.2,106.8], 14);
        L.tileLayer(window.KK_RUN.mapboxTileUrl, {maxZoom:19}).addTo(m);
        var coords = pts.map(function(p){ return [p.lat, p.lng]; });
        if (coords.length > 1){
          var ln = L.polyline(coords, {color:'#1E90FF', weight:6}).addTo(m);
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

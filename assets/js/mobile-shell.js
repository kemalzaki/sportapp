/* =========================================================================
 * KawanKeringat — Mobile Shell JS v1
 * ADDITIVE. Hanya menambahkan native-feel behaviors:
 *  - Capacitor platform detection (set body[data-platform])
 *  - StatusBar & Keyboard plugin hook (jika tersedia)
 *  - Ripple effect untuk .btn, .bn-item, .ms-ripple, [data-ripple]
 *  - Page transition halus (View Transitions API + fallback)
 *  - Pull-to-refresh (PTR) → window.location.reload()
 *  - Active state otomatis pada bottom-nav berdasarkan URL
 *  - Toast helper: window.MSToast(msg)
 *  - Skeleton helper: window.MSSkeleton(target, count)
 *  - Hindari double-tap submit (sudah ada di preloader.js — tidak duplikasi)
 * ======================================================================= */
(function(){
  'use strict';
  if (window.__MS_INIT__) return; window.__MS_INIT__ = true;

  // ---- 1. Platform detection ----
  var isCapacitor = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
  var isStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
  document.body.setAttribute('data-platform', isCapacitor ? 'capacitor' : (isStandalone ? 'standalone' : 'web'));

  // ---- 2. Capacitor plugins (StatusBar transparan + Keyboard) ----
  if (isCapacitor && window.Capacitor.Plugins){
    try {
      var SB = window.Capacitor.Plugins.StatusBar;
      if (SB){
        SB.setOverlaysWebView({ overlay: true }).catch(function(){});
        SB.setStyle({ style: 'LIGHT' }).catch(function(){});
        SB.setBackgroundColor({ color: '#0f172a' }).catch(function(){});
      }
      var KB = window.Capacitor.Plugins.Keyboard;
      if (KB){
        KB.setResizeMode && KB.setResizeMode({ mode: 'native' }).catch(function(){});
      }
      var App = window.Capacitor.Plugins.App;
      if (App){
        // Hardware back → history back, atau exit di root
        App.addListener('backButton', function(){
          if (window.history.length > 1) window.history.back();
          else App.exitApp && App.exitApp();
        });
      }
    } catch(_){}
  }

  // ---- 3. Ripple effect ----
  var RIPPLE_SEL = '.btn, .bn-item, .ms-ripple, [data-ripple], .dropdown-item, .list-group-item-action';
  document.addEventListener('pointerdown', function(e){
    var t = e.target.closest(RIPPLE_SEL);
    if (!t) return;
    if (t.classList.contains('disabled') || t.hasAttribute('disabled')) return;
    var rect = t.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height);
    var ink = document.createElement('span');
    ink.className = 'ms-ripple-ink';
    ink.style.width = ink.style.height = size + 'px';
    ink.style.left = (e.clientX - rect.left - size/2) + 'px';
    ink.style.top  = (e.clientY - rect.top  - size/2) + 'px';
    var cs = getComputedStyle(t);
    if (cs.position === 'static') t.style.position = 'relative';
    if (cs.overflow !== 'hidden') t.style.overflow = 'hidden';
    t.appendChild(ink);
    setTimeout(function(){ ink.remove(); }, 600);
  }, { passive: true });

  // ---- 4. Page transition (link click → fade-out, target page fade-in) ----
  function isInternalLink(a){
    if (!a || !a.href) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    if (a.getAttribute('href').indexOf('#') === 0) return false;
    if (a.dataset.noTransition === '1') return false;
    var u = new URL(a.href, location.href);
    if (u.origin !== location.origin) return false;
    return true;
  }
  document.addEventListener('click', function(e){
    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    var a = e.target.closest('a');
    if (!isInternalLink(a)) return;
    // gunakan View Transitions API jika tersedia → smoother
    if (document.startViewTransition){
      // biarkan navigasi normal; CSS view-transition akan menanganinya
      return;
    }
    document.body.classList.add('ms-page-leave');
  }, true);
  window.addEventListener('pageshow', function(){
    document.body.classList.remove('ms-page-leave');
    document.body.classList.add('ms-page-enter');
    setTimeout(function(){ document.body.classList.remove('ms-page-enter'); }, 320);
  });

  // ---- 5. Active state pada bottom nav ----
  try {
    var here = location.pathname.toLowerCase();
    document.querySelectorAll('.bottom-nav a').forEach(function(a){
      var hp = new URL(a.href, location.href).pathname.toLowerCase();
      if (hp === here) a.classList.add('active');
    });
  } catch(_){}

  // ---- 6. Pull-to-refresh ----
  (function setupPTR(){
    var ptr = document.createElement('div');
    ptr.className = 'ms-ptr';
    ptr.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
    document.body.appendChild(ptr);

    var startY = 0, pulling = false, dist = 0;
    var THRESHOLD = 70;

    window.addEventListener('touchstart', function(e){
      if (window.scrollY > 0) { pulling = false; return; }
      if (e.touches.length !== 1) return;
      startY = e.touches[0].clientY;
      pulling = true; dist = 0;
    }, { passive: true });

    window.addEventListener('touchmove', function(e){
      if (!pulling) return;
      dist = e.touches[0].clientY - startY;
      if (dist > 0 && window.scrollY <= 0){
        var pct = Math.min(dist / THRESHOLD, 1.4);
        ptr.classList.add('visible');
        ptr.style.transform = 'translate(-50%, ' + (pct * 8) + 'px) scale(' + (0.85 + pct*0.15) + ')';
      }
    }, { passive: true });

    window.addEventListener('touchend', function(){
      if (!pulling) return; pulling = false;
      if (dist > THRESHOLD){
        ptr.classList.add('refreshing');
        setTimeout(function(){ location.reload(); }, 180);
      } else {
        ptr.classList.remove('visible');
        ptr.style.transform = '';
      }
    });
  })();

  // ---- 7. Toast helper ----
  window.MSToast = function(msg, ms){
    var t = document.createElement('div');
    t.className = 'ms-toast'; t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function(){ t.classList.add('show'); });
    setTimeout(function(){
      t.classList.remove('show');
      setTimeout(function(){ t.remove(); }, 300);
    }, ms || 2200);
  };

  // ---- 8. Skeleton helper ----
  window.MSSkeleton = function(target, count){
    var host = (typeof target === 'string') ? document.querySelector(target) : target;
    if (!host) return;
    host.innerHTML = '';
    for (var i = 0; i < (count || 4); i++){
      var s = document.createElement('div');
      s.className = 'ms-skeleton';
      s.style.height = (12 + Math.floor(Math.random()*30)) + 'px';
      s.style.marginBottom = '10px';
      s.style.width = (60 + Math.floor(Math.random()*40)) + '%';
      host.appendChild(s);
    }
  };

  // ---- 9. iOS PWA standalone — pertahankan internal navigation ----
  if (isStandalone){
    document.addEventListener('click', function(e){
      var a = e.target.closest('a');
      if (!a || !isInternalLink(a)) return;
      // sudah dihandle browser, hanya pastikan tidak buka tab baru
      if (a.target === '_blank') a.removeAttribute('target');
    });
  }
})();

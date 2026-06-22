/* ==========================================================================
 * KawanKeringat Global Preloader
 * - Splash on first load (auto fade-out)
 * - Overlay on real navigation (link click, form submit, programmatic)
 * - Button loading state + double-click guard
 * - Public API: window.HFPreloader.show(label) / .hide() / .wrap(promise,label)
 * - Auto-bind: <a>, <form>, [data-loader] elements
 * - Safety: fallback timeout, BFCache aware, no memory leak, no stuck overlay
 * ==========================================================================
 */
(function(){
  'use strict';
  if (window.HFPreloader && window.HFPreloader.__init) return;

  var MIN_VISIBLE_MS    = 280;   // anti-flicker
  var FALLBACK_MS       = 15000; // safety auto-hide
  var SPLASH_MIN_MS     = 450;
  var SPLASH_MAX_MS     = 1200;
  var NAV_DEBOUNCE_MS   = 80;

  var counter = 0;
  var shownAt = 0;
  var hideTimer = null;
  var fallbackTimer = null;
  var currentLabel = '';

  // ---------- DOM helpers ----------
  function el(tag, attrs, html){
    var n = document.createElement(tag);
    if (attrs) for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (html != null) n.innerHTML = html;
    return n;
  }

  function ensureOverlay(){
    var o = document.getElementById('hfOverlay');
    if (o) return o;
    o = el('div', { id:'hfOverlay', 'aria-hidden':'true', role:'status' },
      '<div class="hf-spinner" aria-hidden="true"></div>' +
      '<div class="hf-label">Memuat…</div>');
    document.body.appendChild(o);
    return o;
  }

  function setLabel(text){
    var o = ensureOverlay();
    var lbl = o.querySelector('.hf-label');
    if (lbl) lbl.textContent = text || 'Memuat…';
  }

  function clearTimers(){
    if (hideTimer)     { clearTimeout(hideTimer); hideTimer = null; }
    if (fallbackTimer) { clearTimeout(fallbackTimer); fallbackTimer = null; }
  }

  function doShow(label){
    var o = ensureOverlay();
    currentLabel = label || 'Memuat…';
    setLabel(currentLabel);
    if (!o.classList.contains('hf-show')){
      o.classList.add('hf-show');
      o.setAttribute('aria-hidden','false');
      shownAt = Date.now();
    }
    clearTimers();
    fallbackTimer = setTimeout(function(){
      // Safety: paksa hide
      counter = 0;
      doHide(true);
    }, FALLBACK_MS);
  }

  function doHide(force){
    var o = document.getElementById('hfOverlay');
    if (!o) return;
    var elapsed = Date.now() - shownAt;
    var remain  = Math.max(0, MIN_VISIBLE_MS - elapsed);
    clearTimers();
    hideTimer = setTimeout(function(){
      o.classList.remove('hf-show');
      o.setAttribute('aria-hidden','true');
      // Pastikan pointer-events pulih (CSS sudah handle, ini hanya pengaman)
      o.style.pointerEvents = '';
      currentLabel = '';
    }, force ? 0 : remain);
  }

  // ---------- Public API (refcounted) ----------
  function show(label){
    counter++;
    doShow(label);
  }
  function hide(){
    counter = Math.max(0, counter - 1);
    if (counter === 0) doHide(false);
  }
  function reset(){
    counter = 0; doHide(true);
  }
  function wrap(promiseOrFn, label){
    show(label);
    var p;
    try { p = (typeof promiseOrFn === 'function') ? promiseOrFn() : promiseOrFn; }
    catch(e){ hide(); throw e; }
    if (!p || typeof p.then !== 'function'){ hide(); return p; }
    return p.then(function(v){ hide(); return v; },
                  function(e){ hide(); throw e; });
  }

  // ---------- Splash ----------
  function ensureSplash(){
    var s = document.getElementById('hfSplash');
    if (s) return s;
    s = el('div', { id:'hfSplash', 'aria-hidden':'true' },
      '<div class="hf-splash-logo"><i class="bi bi-lightning-charge-fill"></i></div>' +
      '<div class="hf-splash-title">KawanKeringat</div>' +
      '<div class="hf-splash-dots"><span></span><span></span><span></span></div>');
    // Sisipkan paling depan agar tidak menghalangi event listener konten
    if (document.body.firstChild) document.body.insertBefore(s, document.body.firstChild);
    else document.body.appendChild(s);
    return s;
  }
  function hideSplash(){
    var s = document.getElementById('hfSplash');
    if (!s) return;
    s.classList.add('hf-out');
    setTimeout(function(){
      if (s.parentNode) s.parentNode.removeChild(s);
    }, 500);
  }
  function runSplash(){
    // Lewati jika BFCache restore (halaman sudah ada)
    if (window.__hfBfRestored) return;
    var s = ensureSplash();
    var start = Date.now();
    function done(){
      var wait = Math.max(0, SPLASH_MIN_MS - (Date.now() - start));
      setTimeout(hideSplash, wait);
    }
    if (document.readyState === 'complete') done();
    else window.addEventListener('load', done, { once:true });
    // Maksimum durasi splash (jangan stuck di koneksi lambat)
    setTimeout(hideSplash, SPLASH_MAX_MS);
  }

  // ---------- Navigation triggers ----------
  function isInternalLink(a){
    if (!a || !a.href) return false;
    if (a.target && a.target !== '' && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    if (a.dataset.noLoader === '1') return false;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#') return false;
    if (/^(mailto:|tel:|javascript:|sms:|whatsapp:)/i.test(href)) return false;
    try{
      var url = new URL(a.href, location.href);
      if (url.origin !== location.origin) return false;
      // Hash-only ke halaman yang sama
      if (url.pathname === location.pathname &&
          url.search   === location.search   &&
          url.hash     !== location.hash) return false;
      return true;
    }catch(e){ return false; }
  }

  var navLock = false;
  function navigateShow(label){
    if (navLock) return;
    navLock = true;
    show(label || 'Memuat halaman…');
    // Reset lock setelah debounce singkat
    setTimeout(function(){ navLock = false; }, NAV_DEBOUNCE_MS);
  }

  function onDocClick(ev){
    if (ev.defaultPrevented) return;
    if (ev.button !== 0) return;             // hanya klik kiri
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return; // buka tab baru
    var t = ev.target;
    // Tombol manual loader
    var loaderBtn = t.closest && t.closest('[data-loader]');
    if (loaderBtn){
      if (loaderBtn.dataset.hfLoading === '1'){
        ev.preventDefault(); ev.stopPropagation();
        return;
      }
      loaderBtn.dataset.hfLoading = '1';
      loaderBtn.classList.add('hf-btn-loading');
      show(loaderBtn.getAttribute('data-loader-label') || 'Memproses…');
      // Auto-clear setelah 8 detik (kecuali halaman pindah)
      setTimeout(function(){
        loaderBtn.dataset.hfLoading = '';
        loaderBtn.classList.remove('hf-btn-loading');
        hide();
      }, 8000);
      return;
    }
    var a = t.closest && t.closest('a');
    if (!a) return;
    if (!isInternalLink(a)) return;
    navigateShow('Memuat halaman…');
  }

  function onSubmit(ev){
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (f.dataset.noLoader === '1') return;
    if (f.dataset.hfSubmitting === '1'){
      ev.preventDefault(); ev.stopPropagation();
      return;
    }
    f.dataset.hfSubmitting = '1';
    var btn = f.querySelector('button[type="submit"], input[type="submit"]');
    if (btn){
      btn.classList.add('hf-btn-loading');
      btn.disabled = true;
      // Pulihkan jika navigasi gagal (mis. validasi server-side via JS)
      setTimeout(function(){
        f.dataset.hfSubmitting = '';
        btn.classList.remove('hf-btn-loading');
        btn.disabled = false;
      }, 12000);
    }
    show(f.getAttribute('data-loader-label') || 'Mengirim…');
  }

  // ---------- fetch wrapper (otomatis) ----------
  // Tampilkan loader hanya untuk fetch yang ditandai header X-With-Loader: 1
  // atau opsi { loader:true }. Hindari mengganggu polling otomatis.
  if (window.fetch && !window.fetch.__hfPatched){
    var origFetch = window.fetch.bind(window);
    var patched = function(input, init){
      var useLoader = false, label;
      if (init && init.loader){ useLoader = true; label = init.loaderLabel; delete init.loader; delete init.loaderLabel; }
      try{
        var hdrs = init && init.headers;
        if (hdrs){
          var v = (hdrs instanceof Headers) ? hdrs.get('X-With-Loader') : hdrs['X-With-Loader'];
          if (v === '1' || v === 1) useLoader = true;
        }
      }catch(e){}
      if (!useLoader) return origFetch(input, init);
      show(label || 'Memuat…');
      return origFetch(input, init).then(function(r){ hide(); return r; },
                                         function(e){ hide(); throw e; });
    };
    patched.__hfPatched = true;
    window.fetch = patched;
  }

  // ---------- Lifecycle: BFCache, popstate, unload ----------
  function cleanupOnShow(persisted){
    // Jika halaman direstore dari BFCache, hilangkan overlay yang mungkin masih show.
    counter = 0;
    var o = document.getElementById('hfOverlay');
    if (o) o.classList.remove('hf-show'), o.setAttribute('aria-hidden','true');
    if (persisted) window.__hfBfRestored = true;
    hideSplash();
  }

  window.addEventListener('pageshow', function(e){ cleanupOnShow(!!e.persisted); });
  window.addEventListener('popstate', function(){ /* browser will navigate; no overlay */ });

  // beforeunload: tampilkan overlay saat benar-benar pindah
  window.addEventListener('beforeunload', function(){
    show('Memuat halaman…');
  });

  // ---------- Init ----------
  function init(){
    ensureOverlay();
    runSplash();
    document.addEventListener('click', onDocClick, true);
    document.addEventListener('submit', onSubmit, true);
  }
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init, { once:true });
  } else {
    init();
  }

  window.HFPreloader = {
    __init: true,
    show: show,
    hide: hide,
    reset: reset,
    wrap: wrap,
    setLabel: setLabel
  };
})();

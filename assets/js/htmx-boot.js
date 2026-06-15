/**
 * HTMX boot — SportApp
 * - Register service worker
 * - Auto-attach CSRF token ke semua request HTMX
 * - Tandai bottom-nav aktif sesuai URL setelah swap
 * - View Transitions API (kalau didukung) untuk transisi native
 */
(function(){
  // 1) Service worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
    });
  }

  // 2) CSRF
  const meta = document.querySelector('meta[name="csrf-token"]');
  const csrf = meta ? meta.content : '';
  document.body.addEventListener('htmx:configRequest', (e) => {
    if (csrf) e.detail.headers['X-CSRF-Token'] = csrf;
    // tandai fragment request
    e.detail.headers['HX-Request'] = 'true';
  });

  // 3) Update bottom-nav aktif setelah navigasi HTMX
  document.body.addEventListener('htmx:afterSwap', () => {
    const path = location.pathname;
    document.querySelectorAll('.gj-nav .gj-item').forEach(a => {
      const href = a.getAttribute('href') || '';
      a.classList.toggle('active', href && path.endsWith(href.replace(/^\//,'')));
    });
    // re-init Bootstrap tooltips / Quill / dll. kalau perlu
    document.dispatchEvent(new CustomEvent('app:page-ready'));
  });

  // 4) View Transitions
  if (document.startViewTransition) {
    document.body.addEventListener('htmx:beforeSwap', (e) => {
      e.preventDefault();
      document.startViewTransition(() => {
        e.detail.target.innerHTML = e.detail.serverResponse;
      });
    });
  }

  // 5) Loading indicator universal (top progress bar)
  let timer;
  document.body.addEventListener('htmx:beforeRequest', () => {
    document.documentElement.classList.add('htmx-loading');
    clearTimeout(timer);
  });
  document.body.addEventListener('htmx:afterRequest', () => {
    timer = setTimeout(() => document.documentElement.classList.remove('htmx-loading'), 150);
  });
})();

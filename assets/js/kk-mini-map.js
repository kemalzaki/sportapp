/* =====================================================================
 * KawanKeringat — Mini Map Preview untuk Riwayat Aktivitas Publik
 * (REVISI R43 — Juli 2026)
 * ---------------------------------------------------------------------
 * Membaca setiap elemen `.kk-mini-map[data-points]` di halaman riwayat,
 * lalu menampilkan preview rute (polyline + marker start/finish + fit
 * bounds) memakai Leaflet — READ ONLY, tidak bisa diedit/di-drag.
 *
 * Tidak melakukan request tambahan ke server: koordinat sudah
 * di-render sebagai atribut data oleh riwayat.php (dari tabel
 * run_points via kolom upload_harian.gpx_session_id).
 * ===================================================================== */
(function(){
  if (typeof window === 'undefined') return;

  function _decode(el){
    try {
      var raw = el.getAttribute('data-points') || '[]';
      return JSON.parse(raw);
    } catch(_) { return []; }
  }

  function _initOne(el){
    if (!window.L) return;             // Leaflet belum siap
    if (el.dataset.kkInit === '1') return;
    var pts = _decode(el);
    if (!pts || pts.length < 2){ el.style.display = 'none'; return; }
    el.dataset.kkInit = '1';

    var map = L.map(el, {
      zoomControl: false,
      attributionControl: false,
      dragging: false,
      touchZoom: false,
      doubleClickZoom: false,
      scrollWheelZoom: false,
      boxZoom: false,
      keyboard: false,
      tap: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19
    }).addTo(map);

    var latlngs = pts.map(function(p){ return [p[0], p[1]]; });
    var line = L.polyline(latlngs, { color: '#fc5200', weight: 4, opacity: .95 }).addTo(map);

    var start = latlngs[0], finish = latlngs[latlngs.length-1];
    L.circleMarker(start, {
      radius: 6, color: '#fff', weight: 2, fillColor: '#22c55e', fillOpacity: 1
    }).addTo(map);
    L.circleMarker(finish, {
      radius: 6, color: '#fff', weight: 2, fillColor: '#ef4444', fillOpacity: 1
    }).addTo(map);

    try { map.fitBounds(line.getBounds(), { padding: [12,12] }); }
    catch(_) { map.setView(start, 15); }

    // Klik overlay → buka halaman detail rute penuh
    var sid = el.getAttribute('data-sid');
    if (sid){
      el.style.cursor = 'pointer';
      el.addEventListener('click', function(){
        window.location.href = '/track_view.php?sid=' + encodeURIComponent(sid);
      });
    }

    // Redraw setelah layout stabil (Leaflet size fix)
    setTimeout(function(){ try { map.invalidateSize(); } catch(_){} }, 60);
  }

  function initAll(){
    document.querySelectorAll('.kk-mini-map[data-points]').forEach(_initOne);
  }

  function whenReady(cb){
    if (document.readyState !== 'loading') cb();
    else document.addEventListener('DOMContentLoaded', cb);
  }

  whenReady(function(){
    if (window.L) { initAll(); return; }
    // Fallback: tunggu Leaflet dimuat via <script> defer
    var tries = 0;
    var t = setInterval(function(){
      if (window.L){ clearInterval(t); initAll(); }
      else if (++tries > 40){ clearInterval(t); }
    }, 100);
  });

  // Expose untuk konten AJAX yang dimuat belakangan
  window.KKMiniMap = { initAll: initAll };
})();

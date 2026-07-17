/* =====================================================================
 * KawanKeringat — Mini Map Preview untuk Riwayat Aktivitas Publik
 * (REVISI R45 — Juli 2026)
 * ---------------------------------------------------------------------
 * - Mini map inline: sekarang INTERAKTIF (drag, pinch/tap zoom, tombol
 *   zoom) mirip Strava, tetap READ-ONLY (tidak bisa diedit rute).
 * - Klik area peta tidak lagi redirect ke /track_view.php (yang butuh
 *   token & sering error "Token tidak valid").
 * - Tombol "Lihat Rute" (.kk-route-expand[data-sid]) membuka modal
 *   Leaflet berukuran besar yang bisa di-pan/zoom.
 *
 * Titik GPS di-render server-side sebagai atribut data-points
 * (dari upload_harian.gpx_session_id → run_points). Tidak ada
 * request tambahan ke server.
 * ===================================================================== */
(function(){
  if (typeof window === 'undefined') return;

  function _decode(el){
    try { return JSON.parse(el.getAttribute('data-points') || '[]'); }
    catch(_) { return []; }
  }

  function _tileLayer(){
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    });
  }

  function _drawRoute(map, latlngs){
    var line = L.polyline(latlngs, { color: '#fc5200', weight: 5, opacity: .95 }).addTo(map);
    var start  = latlngs[0];
    var finish = latlngs[latlngs.length-1];
    L.circleMarker(start,  { radius: 7, color:'#fff', weight:2, fillColor:'#22c55e', fillOpacity:1 }).addTo(map);
    L.circleMarker(finish, { radius: 7, color:'#fff', weight:2, fillColor:'#ef4444', fillOpacity:1 }).addTo(map);
    try { map.fitBounds(line.getBounds(), { padding:[16,16] }); }
    catch(_) { map.setView(start, 15); }
    return line;
  }

  function _initOne(el){
    if (!window.L) return;
    if (el.dataset.kkInit === '1') return;
    var pts = _decode(el);
    if (!pts || pts.length < 2){ el.style.display = 'none'; return; }
    el.dataset.kkInit = '1';

    // Interaktif seperti Strava — drag/zoom OK, scrollWheelZoom
    // dimatikan supaya scroll halaman tetap nyaman di desktop.
    var map = L.map(el, {
      zoomControl: true,
      attributionControl: false,
      dragging: true,
      touchZoom: true,
      doubleClickZoom: true,
      scrollWheelZoom: false,
      boxZoom: false,
      keyboard: false,
      tap: true
    });
    _tileLayer().addTo(map);

    var latlngs = pts.map(function(p){ return [p[0], p[1]]; });
    _drawRoute(map, latlngs);

    // Klik area peta TIDAK redirect lagi (hindari track_view.php error).
    el.style.cursor = 'grab';

    setTimeout(function(){ try { map.invalidateSize(); } catch(_){} }, 80);
  }

  // ------- Modal "Lihat Rute" (fullscreen interaktif) -------
  var _modalEl = null, _modalMap = null;

  function _ensureModal(){
    if (_modalEl) return _modalEl;
    var wrap = document.createElement('div');
    wrap.innerHTML = ''
      + '<div class="modal fade" id="kkRouteModal" tabindex="-1" aria-hidden="true">'
      + '  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">'
      + '    <div class="modal-content">'
      + '      <div class="modal-header py-2">'
      + '        <h6 class="modal-title mb-0"><i class="bi bi-map text-primary"></i> Rute Aktivitas</h6>'
      + '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>'
      + '      </div>'
      + '      <div class="modal-body p-0">'
      + '        <div id="kkRouteModalMap" style="height:70vh;width:100%;background:#f1f5f9;"></div>'
      + '      </div>'
      + '    </div>'
      + '  </div>'
      + '</div>';
    document.body.appendChild(wrap.firstElementChild);
    _modalEl = document.getElementById('kkRouteModal');

    _modalEl.addEventListener('hidden.bs.modal', function(){
      if (_modalMap){ try { _modalMap.remove(); } catch(_){} _modalMap = null; }
      var host = document.getElementById('kkRouteModalMap');
      if (host){ host.innerHTML = ''; }
    });
    return _modalEl;
  }

  function _openModalFor(sid, points){
    if (!window.L || !window.bootstrap) return;
    _ensureModal();
    var host = document.getElementById('kkRouteModalMap');
    host.innerHTML = '';
    var modal = bootstrap.Modal.getOrCreateInstance(_modalEl);
    modal.show();

    setTimeout(function(){
      _modalMap = L.map(host, {
        zoomControl: true, attributionControl: true,
        dragging: true, touchZoom: true, doubleClickZoom: true,
        scrollWheelZoom: true, boxZoom: true, keyboard: true, tap: true
      });
      _tileLayer().addTo(_modalMap);
      var latlngs = (points||[]).map(function(p){ return [p[0], p[1]]; });
      if (latlngs.length >= 2) _drawRoute(_modalMap, latlngs);
      try { _modalMap.invalidateSize(); } catch(_){}
    }, 220);
  }

  function _bindExpandButtons(root){
    (root||document).querySelectorAll('.kk-route-expand[data-sid]').forEach(function(btn){
      if (btn.dataset.kkBind === '1') return;
      btn.dataset.kkBind = '1';
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var sid = btn.getAttribute('data-sid');
        // Coba baca points dari mini-map terdekat (satu blok aktivitas)
        var host = btn.closest('.kk-route-preview') || document;
        var mini = host.querySelector('.kk-mini-map[data-sid="'+sid+'"]')
                || document.querySelector('.kk-mini-map[data-sid="'+sid+'"]');
        var pts = mini ? _decode(mini) : [];
        _openModalFor(sid, pts);
      });
    });
  }

  function initAll(){
    document.querySelectorAll('.kk-mini-map[data-points]').forEach(_initOne);
    _bindExpandButtons(document);
  }

  function whenReady(cb){
    if (document.readyState !== 'loading') cb();
    else document.addEventListener('DOMContentLoaded', cb);
  }

  whenReady(function(){
    if (window.L) { initAll(); return; }
    var tries = 0;
    var t = setInterval(function(){
      if (window.L){ clearInterval(t); initAll(); }
      else if (++tries > 40){ clearInterval(t); }
    }, 100);
  });

  window.KKMiniMap = { initAll: initAll };
})();

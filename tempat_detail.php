<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$id = (int)($_GET['id'] ?? 0);
$r = db_one("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto, u.nomor_wa AS pic_wa
             FROM tempat t LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
             LEFT JOIN users u ON u.id=t.pic_user_id WHERE t.id=$1", [$id]);
if (!$r) { http_response_code(404); die('Tempat tidak ditemukan.'); }

/* Revisi 20 Juni 2026 R3 — Resolve GPX rute (file upload atau dari run_routes) */
$isTrail = in_array(mb_strtolower(trim((string)$r['jenis_nama'])), ['hiking','camping'], true);
$gpxUrl  = !empty($r['gpx_path']) ? $r['gpx_path'] : '';
$savedRouteGeoJson = null;
if ($isTrail && !$gpxUrl && !empty($r['run_route_id'])) {
    try {
        $sr = db_one("SELECT geojson FROM run_routes WHERE id=$1", [(int)$r['run_route_id']]);
        if ($sr && !empty($sr['geojson'])) $savedRouteGeoJson = $sr['geojson'];
    } catch (Throwable $e) {}
}
$pageTitle = 'Tempat: '.$r['nama'];
$u = current_user();
$isAdmin = $u && $u['role']==='admin';
$picWa = preg_replace('/^0/','62', preg_replace('/\D+/','', $r['kontak_wa'] ?: ($r['pic_wa'] ?? '')));
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="/tempat_list.php">Daftar Tempat</a></li><li class="breadcrumb-item active"><?= htmlspecialchars($r['nama']) ?></li></ol></nav>
<div class="card shadow-sm mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h3 class="mb-1"><?= htmlspecialchars($r['nama']) ?></h3>
      <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['alamat'] ?? '—') ?></div>
      <?php if($r['jenis_nama']): ?><div class="mt-1"><span class="pill"><?= htmlspecialchars($r['jenis_nama']) ?></span></div><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <?php if($picWa): ?><a href="https://wa.me/<?= htmlspecialchars($picWa) ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> Hubungi</a><?php endif; ?>
    </div>
  </div>
  <hr>
  <div class="row g-3">
    <div class="col-12">
      <table class="table table-sm mb-0">
        <tr><th>Status</th><td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($r['status_booking']) ?></span></td></tr>
        <tr><th>Harga Lapang</th><td>Rp <?= number_format((float)$r['harga_lapang'],0,',','.') ?></td></tr>
        <tr><th>Harga / Jam</th><td>Rp <?= number_format((float)$r['harga_per_jam'],0,',','.') ?></td></tr>
        <tr><th>Harga Tiket</th><td>Rp <?= number_format((float)($r['harga_tiket']??0),0,',','.') ?></td></tr>
        <tr><th>Harga Parkir</th><td>Rp <?= number_format((float)($r['harga_parkir']??0),0,',','.') ?></td></tr>
        <?php if($r['pic_nama']): ?><tr><th>PIC</th><td><?= user_name_with_avatar($r['pic_foto']??null,$r['pic_nama'],false,24) ?></td></tr><?php endif; ?>
        <?php if($isAdmin && $r['kontak_wa']): ?><tr><th>Kontak</th><td><?= htmlspecialchars($r['kontak_wa']) ?></td></tr><?php endif; ?>
      </table>
      <?php if($r['catatan']): ?><div class="mt-3"><strong>Catatan:</strong><p class="small text-muted mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($r['catatan']) ?></p></div><?php endif; ?>
    </div>
  </div>

  <?php if ($isTrail): /* ===== Revisi 20 Juni 2026 R3 — Tampilan Khusus Hiking/Camping ===== */ ?>
    <hr>
    <h6 class="mb-2"><i class="bi bi-tree text-success"></i> Rute Perjalanan</h6>
    <?php if ($gpxUrl || $savedRouteGeoJson): ?>
      <div id="trailMap" style="height:420px;border-radius:12px;overflow:hidden;border:1px solid var(--bs-border-color,#dee2e6)"></div>
      <div class="mt-2 d-flex flex-wrap gap-2" id="trailActions">
        <?php if ($gpxUrl): ?>
          <a class="btn btn-sm btn-success" href="<?= htmlspecialchars($gpxUrl) ?>" download>
            <i class="bi bi-download"></i> Unduh Rute Perjalanan (GPX)
          </a>
        <?php endif; ?>
        <a class="btn btn-sm btn-primary" id="btnGmapsRoute" target="_blank" rel="noopener" href="#">
          <i class="bi bi-google"></i> Lihat Jalur di Google Maps
        </a>
        <a class="btn btn-sm btn-outline-secondary" id="btnStreetView" target="_blank" rel="noopener" href="#">
          <i class="bi bi-camera"></i> Street View titik awal
        </a>
        <span id="trailInfo" class="small text-muted align-self-center"></span>
      </div>
    <?php else: ?>
      <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle"></i>
        Rute belum diunggah oleh admin (file .GPX atau rute tersimpan).</div>
    <?php endif; ?>

    <?php if (!empty($r['parkir_info'])): ?>
      <div class="mt-3">
        <h6 class="mb-1"><i class="bi bi-p-square text-primary"></i> Tempat Parkir yang Disarankan</h6>
        <p class="small mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($r['parkir_info']) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($gpxUrl || $savedRouteGeoJson): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      (function(){
        var GPX_URL = <?= json_encode($gpxUrl) ?>;
        var GEOJSON = <?= $savedRouteGeoJson ? $savedRouteGeoJson : 'null' ?>;
        var map = L.map('trailMap').setView([-6.9,107.6], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);

        function fitAndDraw(latlngs){
          if (!latlngs || !latlngs.length) return;
          var line = L.polyline(latlngs, {color:'#dc2626', weight:5, opacity:.85}).addTo(map);
          L.marker(latlngs[0]).addTo(map).bindPopup('Start');
          L.marker(latlngs[latlngs.length-1]).addTo(map).bindPopup('Finish');
          map.fitBounds(line.getBounds(), {padding:[20,20]});
          // Info jarak
          var distM = 0;
          for (var i=1;i<latlngs.length;i++){
            distM += map.distance(latlngs[i-1], latlngs[i]);
          }
          var info = document.getElementById('trailInfo');
          if (info) info.textContent = (latlngs.length+' titik · '+(distM/1000).toFixed(2)+' km');
          // Tombol Google Maps: dir api2 (origin → finish, via waypoint tengah)
          var s = latlngs[0], f = latlngs[latlngs.length-1];
          var via = latlngs[Math.floor(latlngs.length/2)];
          var gm = 'https://www.google.com/maps/dir/?api=1'
                 + '&origin='+s.lat+','+s.lng
                 + '&destination='+f.lat+','+f.lng
                 + '&waypoints='+via.lat+','+via.lng
                 + '&travelmode=walking';
          var gEl = document.getElementById('btnGmapsRoute'); if (gEl) gEl.href = gm;
          var sv = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint='+s.lat+','+s.lng;
          var svEl = document.getElementById('btnStreetView'); if (svEl) svEl.href = sv;
        }

        function fromGeoJson(geo){
          var pts = [];
          try {
            var g = (typeof geo==='string') ? JSON.parse(geo) : geo;
            var coords = null;
            if (g.type==='FeatureCollection' && g.features && g.features[0] && g.features[0].geometry) coords = g.features[0].geometry.coordinates;
            else if (g.type==='Feature' && g.geometry) coords = g.geometry.coordinates;
            else if (g.coordinates) coords = g.coordinates;
            if (coords && coords[0] && typeof coords[0][0]==='number') {
              // LineString [lng,lat]
              coords.forEach(function(c){ pts.push(L.latLng(c[1], c[0])); });
            } else if (coords && coords[0] && Array.isArray(coords[0][0])) {
              // MultiLineString
              coords[0].forEach(function(c){ pts.push(L.latLng(c[1], c[0])); });
            }
          } catch(e){ console.warn(e); }
          return pts;
        }

        if (GPX_URL){
          fetch(GPX_URL).then(function(r){ return r.text(); }).then(function(xml){
            var doc = new DOMParser().parseFromString(xml,'application/xml');
            var trkpts = doc.getElementsByTagName('trkpt');
            var pts = [];
            for (var i=0;i<trkpts.length;i++){
              pts.push(L.latLng(parseFloat(trkpts[i].getAttribute('lat')), parseFloat(trkpts[i].getAttribute('lon'))));
            }
            if (!pts.length){
              var rtepts = doc.getElementsByTagName('rtept');
              for (var j=0;j<rtepts.length;j++){
                pts.push(L.latLng(parseFloat(rtepts[j].getAttribute('lat')), parseFloat(rtepts[j].getAttribute('lon'))));
              }
            }
            fitAndDraw(pts);
          }).catch(function(e){ document.getElementById('trailInfo').textContent='Gagal memuat GPX: '+e.message; });
        } else if (GEOJSON){
          fitAndDraw(fromGeoJson(GEOJSON));
        }
      })();
    </script>
    <?php endif; ?>

  <?php elseif (!empty($r['lat']) && !empty($r['lng'])): ?>
    <hr>
    <h6 class="mb-2"><i class="bi bi-map text-primary"></i> Lokasi di Peta</h6>
    <div id="tempatMap" style="height:340px;border-radius:12px;overflow:hidden;border:1px solid var(--bs-border-color,#dee2e6)"></div>
    <div class="mt-2 d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-primary" target="_blank" rel="noopener"
         href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$r['lat'] ?>,<?= (float)$r['lng'] ?>">
        <i class="bi bi-signpost-split"></i> Petunjuk Arah (Google Maps)
      </a>
      <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
         href="https://www.openstreetmap.org/?mlat=<?= (float)$r['lat'] ?>&mlon=<?= (float)$r['lng'] ?>#map=17/<?= (float)$r['lat'] ?>/<?= (float)$r['lng'] ?>">
        <i class="bi bi-geo-alt"></i> Buka di OSM
      </a>
      <span class="small text-muted align-self-center">Koordinat: <?= (float)$r['lat'] ?>, <?= (float)$r['lng'] ?></span>
    </div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      (function(){
        var lat=<?= (float)$r['lat'] ?>, lng=<?= (float)$r['lng'] ?>;
        var map=L.map('tempatMap').setView([lat,lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          {maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);
        L.marker([lat,lng]).addTo(map)
          .bindPopup(<?= json_encode($r['nama']) ?>).openPopup();
      })();
    </script>
  <?php else: ?>
    <div class="mt-3 alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle"></i>
      Koordinat lokasi belum diisi oleh admin.</div>
  <?php endif; ?>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

<?php
/**
 * KawanKeringat — Activity Detail (READ-ONLY, seperti detail aktivitas Strava)
 * (REVISI R46 — Juli 2026)
 *
 * Halaman ini menggantikan link lama yang membuka /live_tracking.php
 * ("Token tidak valid" karena butuh sesi tracking aktif).
 *
 * Sifat:
 *  - READ-ONLY: hanya menampilkan peta + polyline + statistik.
 *  - TIDAK memakai token tracking.
 *  - TIDAK memulai / menghentikan sesi live tracking.
 *  - TIDAK menulis ke database (kecuali auto-migrasi yang sudah ada
 *    di riwayat.php — tidak dieksekusi di sini).
 *
 * Sumber data:
 *  - upload_harian (id, tanggal, jenis, jarak_km, kalori, durasi,
 *    pace, gpx_session_id)
 *  - run_points (lat, lng) via gpx_session_id  → polyline.
 *
 * Business logic tracking/GPS/upload/save TIDAK diubah.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
require_login();

$u    = current_user();
$uid  = (int)($u['id'] ?? 0);
$upId = (int)($_GET['id'] ?? 0);
$sId  = (int)($_GET['sid'] ?? 0);

$act = null;
if ($upId > 0) {
    $act = db_one("SELECT uh.id, uh.user_id, uh.tanggal, uh.jenis, uh.durasi_menit,
                          uh.jarak_km, uh.kalori, uh.pace, uh.pace_detik,
                          COALESCE(uh.gear_sepatu,'') AS gear_sepatu,
                          COALESCE(uh.gpx_session_id,0) AS gpx_session_id,
                          COALESCE(uh.deskripsi,'') AS deskripsi,
                          u.nama, u.foto_url
                   FROM upload_harian uh
                   JOIN users u ON u.id = uh.user_id
                   WHERE uh.id = $1", [$upId]);
    if ($act) $sId = (int)$act['gpx_session_id'];
} elseif ($sId > 0) {
    // fallback: cari upload_harian yg gpx_session_id-nya sama.
    $act = db_one("SELECT uh.id, uh.user_id, uh.tanggal, uh.jenis, uh.durasi_menit,
                          uh.jarak_km, uh.kalori, uh.pace, uh.pace_detik,
                          COALESCE(uh.gear_sepatu,'') AS gear_sepatu,
                          COALESCE(uh.gpx_session_id,0) AS gpx_session_id,
                          COALESCE(uh.deskripsi,'') AS deskripsi,
                          u.nama, u.foto_url
                   FROM upload_harian uh
                   JOIN users u ON u.id = uh.user_id
                   WHERE uh.gpx_session_id = $1 LIMIT 1", [$sId]);
}

$pageTitle = 'Detail Aktivitas';

/* Ambil titik GPS (read-only) dari run_points */
$points = [];
if ($sId > 0) {
    try {
        $rows = db_all("SELECT lat, lng FROM run_points
                        WHERE session_id = $1 ORDER BY id", [$sId]);
        foreach ($rows as $r) $points[] = [(float)$r['lat'], (float)$r['lng']];
    } catch (Throwable $e) { /* run_points tidak ada — abaikan */ }
}

/* Format helpers (tidak menyentuh helpers.php global) */
function __fmt_date_id(?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return htmlspecialchars($d);
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    return date('j ', $ts) . $bulan[(int)date('n', $ts)-1] . date(' Y', $ts);
}
function __fmt_pace($sec): string {
    $sec = (int)$sec;
    if ($sec <= 0) return '-';
    return sprintf('%d:%02d /km', intdiv($sec,60), $sec % 60);
}

include __DIR__.'/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  .ad-shell{ max-width:960px; margin:0 auto; padding:12px; }
  .ad-map{
    height: 62vh; min-height: 360px; width:100%;
    border-radius: 14px; overflow:hidden;
    border:1px solid #e5e7eb; background:#f1f5f9;
  }
  .ad-map .leaflet-control-attribution{ font-size:10px; }
  .ad-stats{
    display:grid; grid-template-columns: repeat(4, 1fr);
    gap:8px; margin-top:12px;
  }
  @media (max-width: 540px){
    .ad-stats{ grid-template-columns: repeat(2, 1fr); }
  }
  .ad-stat{
    background:#fff; border:1px solid #e5e7eb; border-radius:12px;
    padding:10px 12px;
  }
  .ad-stat .lbl{ font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.03em; }
  .ad-stat .val{ font-size:20px; font-weight:700; color:#111827; line-height:1.2; }
  .ad-meta{ display:flex; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap; }
  .ad-meta img{ width:36px; height:36px; border-radius:50%; object-fit:cover; }
  .ad-title{ font-size:18px; font-weight:700; margin:0; }
  .ad-sub{ color:#6b7280; font-size:13px; }
  .ad-empty{
    display:flex; align-items:center; justify-content:center;
    height:62vh; color:#6b7280; text-align:center; padding:24px;
  }
</style>

<div class="ad-shell">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <a href="/riwayat.php" class="btn btn-sm btn-light border">
      <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <span class="badge bg-light text-muted border">
      <i class="bi bi-eye"></i> Read-only
    </span>
  </div>

<?php if (!$act): ?>
  <div class="ad-map"><div class="ad-empty">
    <div>
      <div class="mb-2"><i class="bi bi-exclamation-circle fs-3 text-warning"></i></div>
      Aktivitas tidak ditemukan.
    </div>
  </div></div>
<?php else: ?>

  <div class="ad-meta">
    <?php if (!empty($act['foto_url'])): ?>
      <img src="<?= htmlspecialchars($act['foto_url']) ?>" alt="">
    <?php else: ?>
      <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center"
            style="width:36px;height:36px;font-weight:700;">
        <?= htmlspecialchars(mb_strtoupper(mb_substr($act['nama'] ?? '?',0,1))) ?>
      </span>
    <?php endif; ?>
    <div>
      <h1 class="ad-title">
        <i class="bi bi-geo-alt text-primary"></i>
        <?= htmlspecialchars(ucfirst((string)($act['jenis'] ?? 'Aktivitas'))) ?>
      </h1>
      <div class="ad-sub">
        <?= htmlspecialchars($act['nama'] ?? '') ?> ·
        <?= __fmt_date_id($act['tanggal'] ?? null) ?>
      </div>
    </div>
  </div>

  <div id="adMap" class="ad-map" role="img" aria-label="Peta rute aktivitas"></div>

  <?php if (count($points) < 2): ?>
    <div class="alert alert-info small mt-2 mb-0">
      <i class="bi bi-info-circle"></i>
      Aktivitas ini belum memiliki jejak GPS untuk ditampilkan pada peta.
    </div>
  <?php endif; ?>

  <div class="ad-stats">
    <div class="ad-stat">
      <div class="lbl">Jarak</div>
      <div class="val"><?= number_format((float)($act['jarak_km'] ?? 0), 2, ',', '.') ?> <small class="text-muted">km</small></div>
    </div>
    <div class="ad-stat">
      <div class="lbl">Durasi</div>
      <div class="val"><?= (int)($act['durasi_menit'] ?? 0) ?> <small class="text-muted">menit</small></div>
    </div>
    <div class="ad-stat">
      <div class="lbl">Kalori</div>
      <div class="val"><?= (int)($act['kalori'] ?? 0) ?> <small class="text-muted">kcal</small></div>
    </div>
    <div class="ad-stat">
      <div class="lbl">Pace</div>
      <div class="val" style="font-size:16px;">
        <?php
          $paceSec = (int)($act['pace_detik'] ?? 0);
          if ($paceSec > 0) echo __fmt_pace($paceSec);
          elseif (!empty($act['pace'])) echo htmlspecialchars((string)$act['pace']);
          else echo '-';
        ?>
      </div>
    </div>
  </div>

  <?php if (!empty($act['deskripsi'])): ?>
    <div class="mt-3 p-3 bg-white border rounded-3">
      <div class="small text-muted mb-1"><i class="bi bi-journal-text"></i> Catatan</div>
      <div><?= nl2br(htmlspecialchars((string)$act['deskripsi'])) ?></div>
    </div>
  <?php endif; ?>

  <script>
  (function(){
    var POINTS = <?= json_encode($points, JSON_UNESCAPED_UNICODE) ?>;
    function boot(){
      if (!window.L) { setTimeout(boot, 120); return; }
      var host = document.getElementById('adMap');
      if (!host) return;

      // Peta interaktif READ-ONLY: drag, pinch, double-tap, scroll-zoom.
      var map = L.map(host, {
        zoomControl: true,
        attributionControl: true,
        dragging: true,
        touchZoom: true,
        doubleClickZoom: true,
        scrollWheelZoom: true,
        boxZoom: true,
        keyboard: true,
        tap: true
      });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      if (POINTS && POINTS.length >= 2){
        var latlngs = POINTS.map(function(p){ return [p[0], p[1]]; });
        var line = L.polyline(latlngs, { color:'#fc5200', weight:5, opacity:.95 }).addTo(map);
        var start  = latlngs[0];
        var finish = latlngs[latlngs.length-1];
        L.circleMarker(start,  { radius:8, color:'#fff', weight:2, fillColor:'#22c55e', fillOpacity:1 })
          .bindTooltip('Start', {permanent:false}).addTo(map);
        L.circleMarker(finish, { radius:8, color:'#fff', weight:2, fillColor:'#ef4444', fillOpacity:1 })
          .bindTooltip('Finish', {permanent:false}).addTo(map);
        try { map.fitBounds(line.getBounds(), { padding:[24,24] }); }
        catch(_) { map.setView(start, 15); }
      } else {
        // Default view Indonesia bila belum ada titik.
        map.setView([-2.5, 118], 4);
      }
      setTimeout(function(){ try { map.invalidateSize(); } catch(_){} }, 120);
    }
    if (document.readyState !== 'loading') boot();
    else document.addEventListener('DOMContentLoaded', boot);
  })();
  </script>

<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>

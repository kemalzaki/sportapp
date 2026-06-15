<?php
/**
 * Revisi 15 Juni 2026 — Admin "Cek Sistem"
 *
 * Tempatkan file ini di: /admin/sistem.php
 *
 * Menampilkan ringkasan kesehatan sistem:
 *   • Disk usage server (df) — termasuk folder uploads
 *   • Database PostgreSQL (ukuran DB, ukuran 10 tabel terbesar, jumlah koneksi)
 *   • ImageKit (kuota & pemakaian storage / bandwidth via API)
 *   • Bandwidth Render (info dari env / heuristik — Render tidak menyediakan
 *     API publik untuk membaca usage per service tanpa OAuth, jadi
 *     ditampilkan tautan ke dashboard + estimasi traffic bulan ini dari
 *     log apache/nginx bila tersedia).
 *   • PHP / memory / opcache info
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
send_security_headers();
require_login();
$u = current_user();
if (!$u || ($u['role'] ?? '') !== 'admin') { http_response_code(403); exit('Khusus admin.'); }
$pageTitle = 'Cek Sistem';

function fmt_bytes($b){
    $b = (float)$b; if ($b<=0) return '0 B';
    $u = ['B','KB','MB','GB','TB']; $i=0;
    while ($b>=1024 && $i<count($u)-1) { $b/=1024; $i++; }
    return number_format($b, $b<10?2:1).' '.$u[$i];
}

/* ---------- DISK ---------- */
$disk = [
    'total' => @disk_total_space(__DIR__.'/..'),
    'free'  => @disk_free_space(__DIR__.'/..'),
];
$disk['used'] = ($disk['total'] && $disk['free']) ? ($disk['total'] - $disk['free']) : 0;

// uploads folder size (sampling, hemat I/O)
function dir_size($dir, $maxFiles=20000){
    $size = 0; $cnt = 0;
    if (!is_dir($dir)) return [0,0];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile()) { $size += $f->getSize(); $cnt++; if ($cnt>$maxFiles) break; } }
    return [$size, $cnt];
}
[$upSize,$upCount] = dir_size(__DIR__.'/../uploads');

/* ---------- DATABASE ---------- */
$dbSize = db_one("SELECT pg_database_size(current_database()) AS sz, current_database() AS name");
$tables = db_all("SELECT relname AS name, pg_total_relation_size(c.oid) AS sz, n_live_tup AS rows
                  FROM pg_class c LEFT JOIN pg_stat_user_tables s ON s.relid=c.oid
                  WHERE relkind='r' AND relnamespace=(SELECT oid FROM pg_namespace WHERE nspname='public')
                  ORDER BY pg_total_relation_size(c.oid) DESC LIMIT 12");
$conn = db_one("SELECT count(*) AS n FROM pg_stat_activity");
$pgVer = db_one("SHOW server_version")['server_version'] ?? '?';

/* ---------- IMAGEKIT ---------- */
$ikUsage = null; $ikErr = null;
$ikPrivate = null;
if (is_file(__DIR__.'/../config/imagekit.php')) {
    $src = file_get_contents(__DIR__.'/../config/imagekit.php');
    if (preg_match('/"(private_[^"]+)"/', $src, $m)) $ikPrivate = $m[1];
}
if ($ikPrivate) {
    // GET https://api.imagekit.io/v1/accounts/usage — Basic Auth (privateKey:)
    $ch = curl_init('https://api.imagekit.io/v1/accounts/usage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $ikPrivate.':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200) $ikUsage = json_decode($res, true);
    else $ikErr = "HTTP $code · $res";
}

/* ---------- BANDWIDTH RENDER (heuristik) ---------- */
// Render tidak punya API publik tanpa OAuth — kita estimasi dari log Apache/Nginx
$bwEstimate = null; $bwSource = null;
foreach (['/var/log/nginx/access.log','/var/log/apache2/access.log'] as $logf) {
    if (is_readable($logf)) {
        $sz = filesize($logf); $bwEstimate = $sz; $bwSource = $logf; break;
    }
}

/* ---------- PHP / SERVER ---------- */
$phpInfo = [
    'PHP'         => PHP_VERSION,
    'SAPI'        => PHP_SAPI,
    'Memory Limit'=> ini_get('memory_limit'),
    'Upload Max'  => ini_get('upload_max_filesize'),
    'Post Max'    => ini_get('post_max_size'),
    'Max Exec'    => ini_get('max_execution_time').' s',
    'OPcache'     => function_exists('opcache_get_status') && @opcache_get_status() ? 'aktif' : 'nonaktif',
    'Time'        => date('Y-m-d H:i:s'),
];

include __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-2"><i class="bi bi-cpu text-info"></i> Cek Sistem</h2>
<p class="text-muted small mb-3">Ringkasan kesehatan server, database, ImageKit, dan PHP runtime.
Khusus admin. Sumber: <code>df</code>, <code>pg_database_size</code>, API ImageKit, dan log akses (bila ada).</p>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h6 class="fw-bold"><i class="bi bi-hdd text-primary"></i> Disk Server</h6>
      <?php if($disk['total']): $pct = round(($disk['used']/$disk['total'])*100); ?>
        <div class="progress mb-2" style="height:8px"><div class="progress-bar bg-<?= $pct>85?'danger':($pct>70?'warning':'success') ?>" style="width:<?= $pct ?>%"></div></div>
        <div class="small">Terpakai <strong><?= fmt_bytes($disk['used']) ?></strong> / <?= fmt_bytes($disk['total']) ?> (<?= $pct ?>%)</div>
        <div class="small text-muted">Bebas: <?= fmt_bytes($disk['free']) ?></div>
      <?php else: ?><div class="text-muted small">Info disk tidak tersedia.</div><?php endif; ?>
      <hr>
      <h6 class="fw-bold small mb-1"><i class="bi bi-folder2-open"></i> Folder /uploads</h6>
      <div class="small">Ukuran: <strong><?= fmt_bytes($upSize) ?></strong> · <?= number_format($upCount) ?> file</div>
    </div></div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h6 class="fw-bold"><i class="bi bi-database text-success"></i> PostgreSQL</h6>
      <div class="small mb-1">Database: <code><?= htmlspecialchars($dbSize['name'] ?? '?') ?></code></div>
      <div class="small mb-1">Versi: <code><?= htmlspecialchars($pgVer) ?></code></div>
      <div class="small mb-1">Ukuran total DB: <strong><?= fmt_bytes($dbSize['sz'] ?? 0) ?></strong></div>
      <div class="small mb-1">Koneksi aktif: <strong><?= (int)($conn['n'] ?? 0) ?></strong></div>
    </div></div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h6 class="fw-bold"><i class="bi bi-cloud-arrow-up text-warning"></i> ImageKit</h6>
      <?php if ($ikUsage): ?>
        <?php
          // ImageKit response shape: { "usage": {...} } — kunci bisa berubah
          $u = $ikUsage['usage'] ?? $ikUsage;
          $storageUsed = $u['storage']['used']   ?? $u['storageUsed']   ?? null;
          $storageMax  = $u['storage']['limit']  ?? $u['storageLimit']  ?? null;
          $bwUsed      = $u['bandwidth']['used'] ?? $u['bandwidthUsed'] ?? null;
          $bwMax       = $u['bandwidth']['limit']?? $u['bandwidthLimit']?? null;
        ?>
        <?php if ($storageMax): $p = round(($storageUsed/$storageMax)*100); ?>
          <div class="small mb-1">Storage: <strong><?= fmt_bytes($storageUsed) ?></strong> / <?= fmt_bytes($storageMax) ?></div>
          <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-<?= $p>85?'danger':($p>70?'warning':'info') ?>" style="width:<?= $p ?>%"></div></div>
        <?php endif; ?>
        <?php if ($bwMax): $p2 = round(($bwUsed/$bwMax)*100); ?>
          <div class="small mb-1">Bandwidth bulan ini: <strong><?= fmt_bytes($bwUsed) ?></strong> / <?= fmt_bytes($bwMax) ?></div>
          <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-<?= $p2>85?'danger':($p2>70?'warning':'info') ?>" style="width:<?= $p2 ?>%"></div></div>
        <?php endif; ?>
        <details class="small"><summary class="text-muted">Raw API response</summary>
          <pre class="small mt-2" style="max-height:200px;overflow:auto"><?= htmlspecialchars(json_encode($ikUsage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></details>
      <?php elseif ($ikErr): ?>
        <div class="alert alert-warning small py-2 mb-0">Gagal ambil usage ImageKit: <?= htmlspecialchars($ikErr) ?></div>
      <?php else: ?>
        <div class="text-muted small">Private key ImageKit tidak ditemukan di <code>config/imagekit.php</code>.</div>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-table"></i> 12 Tabel Terbesar</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Tabel</th><th class="text-end">Baris</th><th class="text-end">Ukuran</th></tr></thead>
        <tbody>
        <?php foreach($tables as $t): ?>
          <tr><td><code><?= htmlspecialchars($t['name']) ?></code></td>
              <td class="text-end"><?= number_format((int)$t['rows']) ?></td>
              <td class="text-end fw-semibold"><?= fmt_bytes($t['sz']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm h-100"><div class="card-body">
      <h6 class="fw-bold"><i class="bi bi-broadcast text-danger"></i> Bandwidth (Render / Server)</h6>
      <?php if ($bwEstimate !== null): ?>
        <div class="small mb-1">Ukuran log akses sekarang: <strong><?= fmt_bytes($bwEstimate) ?></strong></div>
        <div class="small text-muted">Sumber: <code><?= htmlspecialchars($bwSource) ?></code>. Bukan total bandwidth, hanya indikasi kasar.</div>
      <?php else: ?>
        <div class="small text-muted">Log akses tidak terdeteksi di sandbox ini. Render tidak menyediakan API
        publik bandwidth per service tanpa OAuth — cek manual di dashboard:</div>
      <?php endif; ?>
      <a href="https://dashboard.render.com/" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-box-arrow-up-right"></i> Buka Render Dashboard</a>

      <hr>
      <h6 class="fw-bold mt-2"><i class="bi bi-info-square"></i> PHP / Runtime</h6>
      <table class="table table-sm small mb-0"><tbody>
      <?php foreach($phpInfo as $k=>$v): ?>
        <tr><td><?= htmlspecialchars($k) ?></td><td class="text-end"><code><?= htmlspecialchars((string)$v) ?></code></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div></div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

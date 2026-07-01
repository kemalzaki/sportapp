<?php
/**
 * admin/sistem.php — Cek Sistem
 *
 * Revisi Juli 2026:
 *   Ditambahkan blok "Peringatan Batas / Limit" untuk memantau status kapasitas
 *   pada 3 layer:
 *     1. Server  — disk usage & memory usage host
 *     2. Database (PostgreSQL) — ukuran DB vs limit & connection usage
 *     3. ImageKit — kuota bandwidth & storage bulan berjalan
 *
 *   Ambang batas default (bisa dioverride lewat ENV):
 *     - SYS_DISK_LIMIT_GB      (default 20)
 *     - SYS_DB_LIMIT_MB        (default 500)
 *     - SYS_IMAGEKIT_LIMIT_GB  (default 20)
 *     - SYS_WARN_PERCENT       (default 80)  → mulai peringatan
 *     - SYS_DANGER_PERCENT     (default 95)  → status kritis
 *
 *   Jika file ini SUDAH ada di project Anda (biasanya berisi info versi PHP,
 *   ekstensi, koneksi DB, dll.), cukup salin BLOK "Peringatan Batas / Limit"
 *   di bawah (bagian antara komentar BEGIN…END) ke akhir file lama Anda.
 */

require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers(); require_login();
$u = current_user();
if (!$u || ($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('403 — hanya admin.');
}

/* ============================================================
   Helpers pengukuran
   ============================================================ */
function sys_bytes_to_human($b) {
    if ($b === null || $b === false) return '—';
    $u = ['B','KB','MB','GB','TB']; $i=0; $b = (float)$b;
    while ($b >= 1024 && $i < count($u)-1) { $b /= 1024; $i++; }
    return number_format($b, 2, ',', '.').' '.$u[$i];
}
function sys_pct($used, $limit) {
    if ($limit <= 0) return 0;
    return min(100, ($used / $limit) * 100);
}
function sys_level($pct, $warn, $danger) {
    if ($pct >= $danger) return ['danger',  'Kritis — segera tindak lanjut'];
    if ($pct >= $warn)   return ['warning', 'Peringatan — mendekati batas'];
    return ['success', 'Aman'];
}
function sys_bar($pct, $cls) {
    $pct = max(0, min(100, (float)$pct));
    return '<div class="progress" style="height:14px">
              <div class="progress-bar bg-'.$cls.'" role="progressbar" style="width:'.number_format($pct,1,'.','').'%">
                '.number_format($pct,1,',','.').'%
              </div>
            </div>';
}

$WARN   = (int)(getenv('SYS_WARN_PERCENT')   ?: 80);
$DANGER = (int)(getenv('SYS_DANGER_PERCENT') ?: 95);

/* ---------- 1) Server ---------- */
$diskLimitGb = (float)(getenv('SYS_DISK_LIMIT_GB') ?: 20);
$diskFree    = @disk_free_space(__DIR__) ?: 0;
$diskTotal   = @disk_total_space(__DIR__) ?: 0;
$diskUsed    = max(0, $diskTotal - $diskFree);
// Bila disk_total_space tersedia gunakan itu, jika tidak fallback ke limit ENV.
$diskLimitB  = $diskTotal > 0 ? $diskTotal : ($diskLimitGb * 1024 * 1024 * 1024);
$diskPct     = sys_pct($diskUsed, $diskLimitB);
[$diskCls,$diskLbl] = sys_level($diskPct, $WARN, $DANGER);

$memLimitStr = ini_get('memory_limit');
$memUsed     = memory_get_usage(true);
$memLimitB   = 0;
if (preg_match('/^(\d+)([KMG]?)$/i', trim($memLimitStr), $m)) {
    $n = (int)$m[1]; $s = strtoupper($m[2] ?? '');
    $memLimitB = $n * ($s==='G' ? 1073741824 : ($s==='M' ? 1048576 : ($s==='K' ? 1024 : 1)));
}
$memPct = $memLimitB > 0 ? sys_pct($memUsed, $memLimitB) : 0;
[$memCls,$memLbl] = sys_level($memPct, $WARN, $DANGER);

/* ---------- 2) Database ---------- */
$dbLimitMb = (float)(getenv('SYS_DB_LIMIT_MB') ?: 500);
$dbSizeB   = 0; $dbConnUsed = 0; $dbConnMax = 0;
try { $r = db_one("SELECT pg_database_size(current_database()) AS s"); $dbSizeB = (int)($r['s'] ?? 0); } catch (Throwable $e) {}
try { $r = db_one("SELECT count(*)::int AS c FROM pg_stat_activity WHERE datname = current_database()"); $dbConnUsed = (int)($r['c'] ?? 0); } catch (Throwable $e) {}
try { $r = db_one("SHOW max_connections"); $dbConnMax = (int)($r['max_connections'] ?? 0); } catch (Throwable $e) {}
$dbLimitB = $dbLimitMb * 1024 * 1024;
$dbPct    = sys_pct($dbSizeB, $dbLimitB);
[$dbCls,$dbLbl] = sys_level($dbPct, $WARN, $DANGER);
$connPct  = $dbConnMax > 0 ? sys_pct($dbConnUsed, $dbConnMax) : 0;
[$connCls,$connLbl] = sys_level($connPct, $WARN, $DANGER);

/* ---------- 3) ImageKit ---------- */
$ikLimitGb  = (float)(getenv('SYS_IMAGEKIT_LIMIT_GB') ?: 20);
$ikPrivate  = getenv('IMAGEKIT_PRIVATE_KEY') ?: '';
$ikBw = null; $ikStor = null; $ikErr = null;
if ($ikPrivate) {
    // ImageKit usage endpoint (v1). Butuh Basic Auth: privateKey:.
    $ch = curl_init('https://api.imagekit.io/v1/accounts/usage?startDate='.date('Y-m-01').'&endDate='.date('Y-m-d'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json','Authorization: Basic '.base64_encode($ikPrivate.':')],
        CURLOPT_TIMEOUT => 8,
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200 && $resp) {
        $j = json_decode($resp, true) ?: [];
        // Field bisa berbeda tergantung plan; kita cari yang wajar.
        $ikBw   = (float)($j['bandwidth']   ?? $j['bandwidthUsage']   ?? 0);
        $ikStor = (float)($j['mediaLibrarySize'] ?? $j['storage']     ?? 0);
    } else {
        $ikErr = 'HTTP '.$code.' — periksa IMAGEKIT_PRIVATE_KEY / kuota API.';
    }
} else {
    $ikErr = 'IMAGEKIT_PRIVATE_KEY belum di-set — nilai limit tidak bisa diambil otomatis.';
}
$ikLimitB = $ikLimitGb * 1024 * 1024 * 1024;
$ikBwPct  = ($ikBw   !== null) ? sys_pct($ikBw,   $ikLimitB) : 0;
$ikStPct  = ($ikStor !== null) ? sys_pct($ikStor, $ikLimitB) : 0;
[$ikBwCls,$ikBwLbl] = sys_level($ikBwPct, $WARN, $DANGER);
[$ikStCls,$ikStLbl] = sys_level($ikStPct, $WARN, $DANGER);

$pageTitle = 'Cek Sistem — Admin';
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-cpu text-info"></i> Cek Sistem</h2>

<?php /* =====================================================================
   BEGIN — Blok "Peringatan Batas / Limit" (Revisi Juli 2026)
   Bisa dipindah / disalin ke file admin/sistem.php Anda yang lama.
   ===================================================================== */ ?>
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-exclamation-triangle-fill text-warning"></i>
      Peringatan Batas / Limit</strong>
    <small class="text-muted">Warn ≥ <?= $WARN ?>% · Danger ≥ <?= $DANGER ?>%</small>
  </div>
  <div class="card-body">
    <!-- 1. Server -->
    <h6 class="fw-bold mb-2"><i class="bi bi-hdd-network text-primary"></i> Server</h6>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="small mb-1">Disk (<?= sys_bytes_to_human($diskUsed) ?> / <?= sys_bytes_to_human($diskLimitB) ?>)
          — <span class="text-<?= $diskCls ?> fw-semibold"><?= $diskLbl ?></span></div>
        <?= sys_bar($diskPct, $diskCls) ?>
      </div>
      <div class="col-md-6">
        <div class="small mb-1">Memory PHP (<?= sys_bytes_to_human($memUsed) ?> / <?= $memLimitB>0?sys_bytes_to_human($memLimitB):($memLimitStr ?: '—') ?>)
          — <span class="text-<?= $memCls ?> fw-semibold"><?= $memLbl ?></span></div>
        <?= sys_bar($memPct, $memCls) ?>
      </div>
    </div>

    <!-- 2. Database -->
    <h6 class="fw-bold mb-2"><i class="bi bi-database-fill-check text-success"></i> Database (PostgreSQL)</h6>
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="small mb-1">Ukuran DB (<?= sys_bytes_to_human($dbSizeB) ?> / <?= sys_bytes_to_human($dbLimitB) ?>)
          — <span class="text-<?= $dbCls ?> fw-semibold"><?= $dbLbl ?></span></div>
        <?= sys_bar($dbPct, $dbCls) ?>
      </div>
      <div class="col-md-6">
        <div class="small mb-1">Koneksi aktif (<?= $dbConnUsed ?> / <?= $dbConnMax ?: '—' ?>)
          — <span class="text-<?= $connCls ?> fw-semibold"><?= $connLbl ?></span></div>
        <?= sys_bar($connPct, $connCls) ?>
      </div>
    </div>

    <!-- 3. ImageKit -->
    <h6 class="fw-bold mb-2"><i class="bi bi-images text-info"></i> ImageKit (bulan berjalan)</h6>
    <?php if ($ikErr): ?>
      <div class="alert alert-warning small mb-2"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($ikErr) ?></div>
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="small mb-1">Bandwidth (<?= $ikBw!==null?sys_bytes_to_human($ikBw):'—' ?> / <?= sys_bytes_to_human($ikLimitB) ?>)
          — <span class="text-<?= $ikBwCls ?> fw-semibold"><?= $ikBw!==null?$ikBwLbl:'Data tidak tersedia' ?></span></div>
        <?= sys_bar($ikBwPct, $ikBwCls) ?>
      </div>
      <div class="col-md-6">
        <div class="small mb-1">Storage Media (<?= $ikStor!==null?sys_bytes_to_human($ikStor):'—' ?> / <?= sys_bytes_to_human($ikLimitB) ?>)
          — <span class="text-<?= $ikStCls ?> fw-semibold"><?= $ikStor!==null?$ikStLbl:'Data tidak tersedia' ?></span></div>
        <?= sys_bar($ikStPct, $ikStCls) ?>
      </div>
    </div>

    <hr class="my-3">
    <p class="small text-muted mb-0">
      Anda bisa mengubah ambang &amp; limit lewat environment variable:
      <code>SYS_DISK_LIMIT_GB</code>, <code>SYS_DB_LIMIT_MB</code>,
      <code>SYS_IMAGEKIT_LIMIT_GB</code>, <code>SYS_WARN_PERCENT</code>,
      <code>SYS_DANGER_PERCENT</code>. ImageKit dibaca via API bila
      <code>IMAGEKIT_PRIVATE_KEY</code> tersedia.
    </p>
  </div>
</div>
<?php /* END — Blok "Peringatan Batas / Limit" */ ?>

<?php include __DIR__.'/../includes/footer.php'; ?>

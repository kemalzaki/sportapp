<?php
/**
 * admin/sistem.php — Revisi 2 Juli 2026 #4
 *
 * Halaman "Cek Sistem" milik admin.
 * Tambahan: menampilkan DETAIL SIZE seluruh tabel yang tersedia di database
 * (total size, table size, index size, TOAST size, jumlah baris).
 *
 * Data diambil dari katalog PostgreSQL — hanya schema 'public'.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers();
require_role('admin');

$pageTitle = 'Cek Sistem';

/* Info umum */
$info = [];
try {
    $info['pg_version']   = db_val("SHOW server_version");
    $info['db_name']      = db_val("SELECT current_database()");
    $info['db_size']      = db_val("SELECT pg_size_pretty(pg_database_size(current_database()))");
    $info['now']          = db_val("SELECT now()");
    $info['php_version']  = PHP_VERSION;
    $info['php_sapi']     = PHP_SAPI;
    $info['memory_used']  = number_format(memory_get_usage()/1048576,2).' MB';
    $info['upload_max']   = ini_get('upload_max_filesize');
    $info['post_max']     = ini_get('post_max_size');
} catch (Throwable $e) {}

/* Detail size per tabel */
$tables = [];
try {
    $tables = db_all("
        SELECT
          c.relname                                             AS tabel,
          pg_size_pretty(pg_total_relation_size(c.oid))         AS total,
          pg_total_relation_size(c.oid)                         AS total_bytes,
          pg_size_pretty(pg_relation_size(c.oid))               AS data,
          pg_size_pretty(pg_indexes_size(c.oid))                AS indeks,
          pg_size_pretty(COALESCE(pg_total_relation_size(c.reltoastrelid),0)) AS toast,
          COALESCE(c.reltuples,0)::bigint                       AS baris_estimasi
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relkind = 'r'
        ORDER BY pg_total_relation_size(c.oid) DESC
    ");
} catch (Throwable $e) { $tables = []; }

$sumBytes = 0; foreach ($tables as $t) $sumBytes += (int)$t['total_bytes'];
function _fmt_bytes(int $b): string {
    if ($b < 1024) return $b.' B';
    $u=['KB','MB','GB','TB']; $i=0; $v=$b/1024;
    while ($v>=1024 && $i<3){ $v/=1024; $i++; }
    return number_format($v,2).' '.$u[$i];
}

include __DIR__.'/../includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item">Admin</li>
  <li class="breadcrumb-item active">Cek Sistem</li>
</ol></nav>

<h3 class="mb-3"><i class="bi bi-cpu text-info"></i> Cek Sistem</h3>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-database-fill-check"></i> Database</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between border-bottom py-1"><span>PostgreSQL</span><b><?= htmlspecialchars((string)($info['pg_version'] ?? '-')) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>Nama Database</span><b><?= htmlspecialchars((string)($info['db_name'] ?? '-')) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>Total Ukuran DB</span><b><?= htmlspecialchars((string)($info['db_size'] ?? '-')) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>Jumlah Tabel (public)</span><b><?= count($tables) ?></b></div>
        <div class="d-flex justify-content-between py-1"><span>Waktu Server DB</span><b><?= htmlspecialchars((string)($info['now'] ?? '-')) ?></b></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-filetype-php"></i> PHP Runtime</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between border-bottom py-1"><span>Versi PHP</span><b><?= htmlspecialchars((string)$info['php_version']) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>SAPI</span><b><?= htmlspecialchars((string)$info['php_sapi']) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>Memory Terpakai</span><b><?= htmlspecialchars((string)$info['memory_used']) ?></b></div>
        <div class="d-flex justify-content-between border-bottom py-1"><span>upload_max_filesize</span><b><?= htmlspecialchars((string)$info['upload_max']) ?></b></div>
        <div class="d-flex justify-content-between py-1"><span>post_max_size</span><b><?= htmlspecialchars((string)$info['post_max']) ?></b></div>
      </div>
    </div>
  </div>
</div>

<!-- Revisi 2 Juli 2026 #4: Detail size setiap tabel -->
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><i class="bi bi-hdd-stack text-primary"></i> <strong>Detail Size Semua Tabel Database</strong></div>
    <div class="small text-muted">Total (semua tabel): <b><?= _fmt_bytes($sumBytes) ?></b></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" style="min-width:820px">
      <thead class="table-light">
        <tr>
          <th style="width:36px" class="text-end">#</th>
          <th>Tabel</th>
          <th class="text-end">Total Size</th>
          <th class="text-end">Data</th>
          <th class="text-end">Indeks</th>
          <th class="text-end">TOAST</th>
          <th class="text-end">Baris (est.)</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$tables): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">Tidak dapat membaca katalog tabel.</td></tr>
      <?php else: foreach ($tables as $i=>$t): ?>
        <tr>
          <td class="text-end text-muted small"><?= $i+1 ?></td>
          <td class="font-monospace small"><?= htmlspecialchars($t['tabel']) ?></td>
          <td class="text-end fw-semibold"><?= htmlspecialchars($t['total']) ?></td>
          <td class="text-end small"><?= htmlspecialchars($t['data']) ?></td>
          <td class="text-end small"><?= htmlspecialchars($t['indeks']) ?></td>
          <td class="text-end small text-muted"><?= htmlspecialchars($t['toast']) ?></td>
          <td class="text-end small"><?= number_format((int)$t['baris_estimasi']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

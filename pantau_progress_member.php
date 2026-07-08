<?php
/**
 * pantau_progress_member.php — Revisi Juli 2026.
 * Admin / Koordinator memantau progress islami member:
 *   - Monitoring Tahajud & Duha Bulanan
 *   - Doa Harian (dari tabel doa_user)
 *   - Catatan Hafalan
 *   - Catatan Baca Buku
 *
 * Tidak menambah tabel baru — hanya membaca tabel yang sudah ada.
 * Akses: role = admin ATAU koordinator (fallback: admin saja).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/scope.php'; // Revisi R7 #5
send_security_headers(); require_login();
$u = current_user();
$pageTitle = 'Pantau Progress Islami Member';

$role = strtolower($u['role'] ?? '');
if ($role !== 'superadmin') {
    include __DIR__.'/includes/header.php';
    echo '<div class="alert alert-danger mt-3">Halaman ini khusus admin / koordinator.</div>';
    include __DIR__.'/includes/footer.php'; exit;
}
$__scopeArr = scope_user_ids_sql_array();

$bulan = isset($_GET['bulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$start = $bulan.'-01';
$end   = date('Y-m-t', strtotime($start));
$days  = (int)date('t', strtotime($start));

$q = trim($_GET['q'] ?? '');
$members = [];
try {
    if ($q !== '') {
        $like = '%'.$q.'%';
        $members = db_all("SELECT id,nama,email,username FROM users
                           WHERE (nama ILIKE $1 OR email ILIKE $1 OR username ILIKE $1)
                             AND COALESCE(aktif::int,1)<>0
                             AND id = ANY($2::int[])
                           ORDER BY nama LIMIT 100", [$like, $__scopeArr]);
    } else {
        $members = db_all("SELECT id,nama,email,username FROM users
                           WHERE COALESCE(aktif::int,1)<>0
                             AND id = ANY($1::int[])
                           ORDER BY nama LIMIT 100", [$__scopeArr]);
    }
} catch (Throwable $e) { $members = []; }

$ids = array_map(fn($m) => (int)$m['id'], $members);
$stat = [];
foreach ($ids as $id) {
    $stat[$id] = ['tahajud'=>0,'duha'=>0,'doa'=>0,'hafalan'=>0,'buku'=>0];
}
if ($ids) {
    $inList = implode(',', $ids);
    // shalat sunnah
    try {
        $rows = db_all("SELECT user_id, jenis, COUNT(*)::int AS c FROM shalat_sunnah_log
                        WHERE user_id IN ($inList) AND tanggal BETWEEN $1 AND $2
                        GROUP BY user_id, jenis", [$start,$end]);
        foreach ($rows as $r) {
            if ($r['jenis']==='tahajud') $stat[(int)$r['user_id']]['tahajud'] = (int)$r['c'];
            if ($r['jenis']==='duha')    $stat[(int)$r['user_id']]['duha']    = (int)$r['c'];
        }
    } catch (Throwable $e) {}
    // doa harian (tabel doa_user berisi log klik doa)
    try {
        $rows = db_all("SELECT user_id, COUNT(*)::int AS c FROM doa_user
                        WHERE user_id IN ($inList)
                          AND created_at::date BETWEEN $1 AND $2
                        GROUP BY user_id", [$start,$end]);
        foreach ($rows as $r) $stat[(int)$r['user_id']]['doa'] = (int)$r['c'];
    } catch (Throwable $e) {}
    // catatan hafalan
    try {
        $rows = db_all("SELECT user_id, COUNT(*)::int AS c FROM catatan_hafalan
                        WHERE user_id IN ($inList)
                          AND created_at::date BETWEEN $1 AND $2
                        GROUP BY user_id", [$start,$end]);
        foreach ($rows as $r) $stat[(int)$r['user_id']]['hafalan'] = (int)$r['c'];
    } catch (Throwable $e) {}
    // catatan baca buku
    try {
        $rows = db_all("SELECT user_id, COUNT(*)::int AS c FROM catatan_baca_buku
                        WHERE user_id IN ($inList)
                          AND created_at::date BETWEEN $1 AND $2
                        GROUP BY user_id", [$start,$end]);
        foreach ($rows as $r) $stat[(int)$r['user_id']]['buku'] = (int)$r['c'];
    } catch (Throwable $e) {}
}

$prev = date('Y-m', strtotime($start.' -1 month'));
$next = date('Y-m', strtotime($start.' +1 month'));

include __DIR__.'/includes/header.php'; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
    <li class="breadcrumb-item active">Pantau Progress Islami Member</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-danger"></i> Pantau Progress Islami Member</h2>
<p class="text-muted small">Ringkasan input Islami per member untuk bulan berjalan: Monitoring Tahajud &amp; Duha, Doa Harian, Catatan Hafalan, Catatan Baca Buku.</p>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="small">Bulan</label>
    <div class="input-group input-group-sm">
      <a class="btn btn-outline-secondary" href="?bulan=<?= $prev ?>&q=<?= urlencode($q) ?>">&laquo;</a>
      <input type="month" class="form-control" name="bulan" value="<?= htmlspecialchars($bulan) ?>">
      <a class="btn btn-outline-secondary" href="?bulan=<?= $next ?>&q=<?= urlencode($q) ?>">&raquo;</a>
    </div>
  </div>
  <div class="col-md-5"><label class="small">Cari member</label>
    <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="nama / email / username"></div>
  <div class="col-md-2 align-self-end">
    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Terapkan</button>
  </div>
</form>

<div class="card shadow-sm border-0">
  <div class="card-header bg-gradient bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><i class="bi bi-calendar3"></i> <strong>Rekap Bulan <?= htmlspecialchars(date('F Y', strtotime($start))) ?></strong></div>
    <div class="small">
      <span class="badge bg-light text-primary me-1"><i class="bi bi-people-fill"></i> <?= count($members) ?> member</span>
      <span class="badge bg-light text-primary"><i class="bi bi-calendar-week"></i> <?= $days ?> hari</span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:620px; overflow-y:auto;">
      <table class="table table-sm table-striped table-hover mb-0 align-middle text-nowrap">
        <thead class="table-dark" style="position:sticky;top:0;z-index:2;">
          <tr class="text-center">
            <th style="width:44px">#</th>
            <th class="text-start">Member</th>
            <th style="width:110px"><i class="bi bi-moon-stars text-info"></i> Tahajud</th>
            <th style="width:110px"><i class="bi bi-sun text-warning"></i> Duha</th>
            <th style="width:110px"><i class="bi bi-hand-thumbs-up text-success"></i> Doa</th>
            <th style="width:110px"><i class="bi bi-journal-check text-primary"></i> Hafalan</th>
            <th style="width:110px"><i class="bi bi-book text-secondary"></i> Buku</th>
            <th style="width:90px" class="bg-success text-white"><i class="bi bi-trophy"></i> Total</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
          <tr><td colspan="8" class="text-center text-muted small py-4"><i class="bi bi-inbox"></i> Tidak ada member.</td></tr>
        <?php else: foreach ($members as $i => $m):
          $s = $stat[(int)$m['id']] ?? ['tahajud'=>0,'duha'=>0,'doa'=>0,'hafalan'=>0,'buku'=>0];
          $total = (int)$s['tahajud'] + (int)$s['duha'] + (int)$s['doa'] + (int)$s['hafalan'] + (int)$s['buku'];
        ?>
          <tr>
            <td class="text-center text-muted small fw-bold"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($m['nama'] ?? '-') ?></div>
              <div class="small text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($m['email'] ?? '-') ?></div>
            </td>
            <td class="text-center"><span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle px-2"><?= (int)$s['tahajud'] ?><span class="opacity-50"> / <?= $days ?></span></span></td>
            <td class="text-center"><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2"><?= (int)$s['duha'] ?><span class="opacity-50"> / <?= $days ?></span></span></td>
            <td class="text-center"><span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle px-2"><?= (int)$s['doa'] ?></span></td>
            <td class="text-center"><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-2"><?= (int)$s['hafalan'] ?></span></td>
            <td class="text-center"><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-2"><?= (int)$s['buku'] ?></span></td>
            <td class="text-center fw-bold text-success"><?= $total ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-light small text-muted d-flex justify-content-between flex-wrap gap-2">
    <span><i class="bi bi-info-circle"></i> Badge menampilkan jumlah entri pada bulan berjalan. Kolom Tahajud &amp; Duha ditampilkan sebagai <em>x / total hari</em>.</span>
    <span><i class="bi bi-trophy text-success"></i> Kolom <strong>Total</strong> = jumlah seluruh aktivitas.</span>
  </div>
</div>


<?php include __DIR__.'/includes/footer.php'; ?>

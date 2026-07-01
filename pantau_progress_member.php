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
send_security_headers(); require_login();
$u = current_user();
$pageTitle = 'Pantau Progress Islami Member';

$role = strtolower($u['role'] ?? '');
if (!in_array($role, ['admin','koordinator','pic'], true)) {
    include __DIR__.'/includes/header.php';
    echo '<div class="alert alert-danger mt-3">Halaman ini khusus admin / koordinator.</div>';
    include __DIR__.'/includes/footer.php'; exit;
}

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
                           ORDER BY nama LIMIT 100", [$like]);
    } else {
        $members = db_all("SELECT id,nama,email,username FROM users
                           WHERE COALESCE(aktif::int,1)<>0
                           ORDER BY nama LIMIT 100");
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

<div class="card shadow-sm">
  <div class="card-header bg-light"><strong>Rekap Bulan <?= htmlspecialchars(date('F Y', strtotime($start))) ?></strong> · <?= count($members) ?> member · <?= $days ?> hari</div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light" style="position:sticky;top:0;z-index:2;">
          <tr>
            <th style="width:40px">#</th>
            <th>Member</th>
            <th class="text-center" style="width:110px">Tahajud</th>
            <th class="text-center" style="width:110px">Duha</th>
            <th class="text-center" style="width:110px">Doa Harian</th>
            <th class="text-center" style="width:110px">Hafalan</th>
            <th class="text-center" style="width:110px">Baca Buku</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
          <tr><td colspan="7" class="text-center text-muted small py-4">Tidak ada member.</td></tr>
        <?php else: foreach ($members as $i => $m):
          $s = $stat[(int)$m['id']] ?? ['tahajud'=>0,'duha'=>0,'doa'=>0,'hafalan'=>0,'buku'=>0];
        ?>
          <tr>
            <td class="text-muted small"><?= $i+1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($m['nama'] ?? '-') ?></div>
              <div class="small text-muted"><?= htmlspecialchars($m['email'] ?? '') ?></div>
            </td>
            <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= (int)$s['tahajud'] ?> / <?= $days ?></span></td>
            <td class="text-center"><span class="badge bg-warning-subtle text-warning-emphasis"><?= (int)$s['duha'] ?> / <?= $days ?></span></td>
            <td class="text-center"><span class="badge bg-success-subtle text-success-emphasis"><?= (int)$s['doa'] ?></span></td>
            <td class="text-center"><span class="badge bg-info-subtle text-info-emphasis"><?= (int)$s['hafalan'] ?></span></td>
            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= (int)$s['buku'] ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<p class="text-muted small mt-2"><i class="bi bi-info-circle"></i>
  Kolom yang menampilkan angka 0 pada semua member bisa jadi tabel sumbernya belum ada atau nama kolomnya berbeda. Sesuaikan query di file ini jika perlu.
</p>

<?php include __DIR__.'/includes/footer.php'; ?>

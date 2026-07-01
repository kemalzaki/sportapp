<?php
/**
 * admin/paket_pesanan.php — Revisi 2 Juli 2026 #1
 *
 * Menampilkan Riwayat Pesanan Paket (dari paket_upgrade.php) yang dipesan
 * member, lengkap dengan aksi ubah status oleh admin.
 *
 * Placement: menu drawer "Admin > Member Organize".
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
send_security_headers();
require_role('admin');

/* Idempotent: pastikan tabel & kolom catatan admin ada */
try {
    db_exec("CREATE TABLE IF NOT EXISTS paket_pesanan (
        id BIGSERIAL PRIMARY KEY,
        kode VARCHAR(40) UNIQUE NOT NULL,
        user_id BIGINT NOT NULL,
        paket VARCHAR(20) NOT NULL,
        harga INTEGER NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        snap_token TEXT, snap_redirect TEXT,
        midtrans_status VARCHAR(40), midtrans_raw TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        paid_at TIMESTAMP NULL
    )");
    db_exec("ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS admin_catatan TEXT");
    db_exec("ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP");
} catch (Throwable $e) {}

/* Handle update status */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $note   = trim(substr((string)($_POST['catatan'] ?? ''), 0, 500));
    $allowed = ['menunggu_wa','pending','paid','ditolak','dibatalkan'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        try {
            if ($status === 'paid') {
                db_exec("UPDATE paket_pesanan SET status=$1, paid_at=COALESCE(paid_at,now()), admin_catatan=NULLIF($2,''), updated_at=now() WHERE id=$3",
                    [$status,$note,$id]);
                // Aktifkan paket user
                $row = db_one("SELECT user_id, paket FROM paket_pesanan WHERE id=$1", [$id]);
                if ($row) {
                    try { db_exec("UPDATE users SET paket=$1 WHERE id=$2", [$row['paket'], (int)$row['user_id']]); } catch (Throwable $e) {}
                }
            } else {
                db_exec("UPDATE paket_pesanan SET status=$1, admin_catatan=NULLIF($2,''), updated_at=now() WHERE id=$3",
                    [$status,$note,$id]);
            }
            $_SESSION['flash'] = 'Status pesanan berhasil diperbarui.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Gagal: '.$e->getMessage(); }
    }
    header('Location: /admin/paket_pesanan.php'); exit;
}

$fStatus = trim($_GET['status'] ?? '');
$fQ      = trim($_GET['q'] ?? '');
$where=[]; $args=[];
if ($fStatus !== '') { $args[]=$fStatus; $where[]='p.status = $'.count($args); }
if ($fQ !== '')      { $args[]='%'.$fQ.'%'; $i=count($args); $where[]="(p.kode ILIKE \$$i OR u.nama ILIKE \$$i OR u.email ILIKE \$$i)"; }
$sql = "SELECT p.*, u.nama AS user_nama, u.email AS user_email, u.nomor_wa
        FROM paket_pesanan p LEFT JOIN users u ON u.id=p.user_id"
     . ($where ? ' WHERE '.implode(' AND ',$where) : '')
     . " ORDER BY p.id DESC LIMIT 500";
try { $rows = db_all($sql, $args); } catch (Throwable $e) { $rows=[]; }

$counts = [];
try {
    foreach (db_all("SELECT status, COUNT(*) c FROM paket_pesanan GROUP BY status") as $r) $counts[$r['status']] = (int)$r['c'];
} catch (Throwable $e) {}

$pageTitle = 'Admin — Pesanan Paket Member';
include __DIR__.'/../includes/header.php';
$csrf = csrf_token();
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item">Admin</li>
  <li class="breadcrumb-item active">Pesanan Paket Member</li>
</ol></nav>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h3 class="mb-0"><i class="bi bi-receipt-cutoff text-primary"></i> Riwayat Pesanan Paket Member</h3>
  <div class="small text-muted">
    <?php foreach (['menunggu_wa','pending','paid','ditolak','dibatalkan'] as $k): ?>
      <span class="badge bg-light text-dark border me-1"><?= $k ?>: <b><?= (int)($counts[$k]??0) ?></b></span>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-info py-2"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select form-select-sm" name="status">
      <option value="">— Semua status —</option>
      <?php foreach (['menunggu_wa','pending','paid','ditolak','dibatalkan'] as $s): ?>
        <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($fQ) ?>" placeholder="Cari kode / nama / email"></div>
  <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-filter"></i> Filter</button></div>
  <div class="col-md-3 text-md-end small text-muted align-self-center">Total: <b><?= count($rows) ?></b> pesanan</div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" style="min-width:1100px">
      <thead class="table-light">
        <tr>
          <th>Kode</th><th>Member</th><th>Paket</th><th class="text-end">Harga</th>
          <th>Status</th><th>Dibuat</th><th>Lunas</th><th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada pesanan.</td></tr>
      <?php else: foreach ($rows as $r):
        $st = $r['status']; $cls = $st==='paid'?'success':($st==='menunggu_wa'?'info':($st==='pending'?'secondary':'danger'));
      ?>
        <tr>
          <td class="font-monospace small"><?= htmlspecialchars($r['kode']) ?></td>
          <td>
            <div class="fw-semibold small"><?= htmlspecialchars((string)($r['user_nama'] ?? '#'.$r['user_id'])) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)($r['user_email'] ?? '')) ?><?php if(!empty($r['nomor_wa'])): ?> · <?= htmlspecialchars($r['nomor_wa']) ?><?php endif; ?></div>
          </td>
          <td><span class="badge bg-warning-subtle text-warning-emphasis"><?= strtoupper($r['paket']) ?></span></td>
          <td class="text-end">Rp <?= number_format((int)$r['harga'],0,',','.') ?></td>
          <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span></td>
          <td class="small"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td class="small"><?= htmlspecialchars((string)($r['paid_at'] ?? '')) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#pp<?= (int)$r['id'] ?>">
              <i class="bi bi-pencil-square"></i> Ubah
            </button>
          </td>
        </tr>
        <tr class="collapse" id="pp<?= (int)$r['id'] ?>">
          <td colspan="8" class="bg-light-subtle">
            <form method="post" class="row g-2 align-items-end">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <div class="col-md-3">
                <label class="form-label small mb-1">Status Baru</label>
                <select name="status" class="form-select form-select-sm">
                  <?php foreach (['menunggu_wa','pending','paid','ditolak','dibatalkan'] as $s): ?>
                    <option value="<?= $s ?>" <?= $st===$s?'selected':'' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Catatan Admin (opsional)</label>
                <input class="form-control form-control-sm" name="catatan" maxlength="500" value="<?= htmlspecialchars((string)($r['admin_catatan'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <button class="btn btn-sm btn-success w-100"><i class="bi bi-save"></i> Simpan Perubahan</button>
                <div class="small text-muted mt-1">Ubah ke <b>paid</b> otomatis mengaktifkan paket member.</div>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
/**
 * admin/paket_pesanan.php — Revisi R5 (Juli 2026)
 * Riwayat Pesanan Paket Member + fitur HAPUS.
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_login();
$me = current_user();
if (($me['role'] ?? '') !== 'admin') { http_response_code(403); exit('Khusus admin.'); }

try {
    db_exec("CREATE TABLE IF NOT EXISTS paket_pesanan (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        paket VARCHAR(20) NOT NULL,
        harga INTEGER NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT NOW()
    )");
} catch (Throwable $e) {}

$flash=null; $err=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    try {
        if ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db_exec("DELETE FROM paket_pesanan WHERE id=$1", [$id]);
                $flash = 'Pesanan dihapus.';
            }
        } elseif ($act === 'delete_all') {
            db_exec("DELETE FROM paket_pesanan");
            $flash = 'Semua riwayat pesanan dihapus.';
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$rows = db_all("SELECT p.*, u.nama AS user_nama, u.username
                FROM paket_pesanan p
                LEFT JOIN users u ON u.id = p.user_id
                ORDER BY p.id DESC");
$csrf = csrf_token();
$pageTitle = 'Riwayat Pesanan Paket Member';
include __DIR__ . '/../includes/header.php';
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt-cutoff text-success"></i> Riwayat Pesanan Paket Member</h4>
    <?php if ($rows): ?>
    <form method="post" onsubmit="return confirm('Hapus SELURUH riwayat pesanan?')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="delete_all">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus Semua</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($err)   ?></div><?php endif; ?>

  <div class="table-responsive card border-0 shadow-sm">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Tanggal</th><th>Member</th><th>Paket</th><th>Harga</th><th>Status</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td class="small text-muted"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td><?= htmlspecialchars((string)$r['user_nama']) ?>
              <div class="small text-muted">@<?= htmlspecialchars((string)$r['username']) ?></div></td>
          <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= htmlspecialchars($r['paket']) ?></span></td>
          <td>Rp <?= number_format((int)$r['harga'],0,',','.') ?></td>
          <td>
            <?php $st=(string)$r['status']; $cls = $st==='paid'?'success':($st==='pending'?'warning':'secondary'); ?>
            <span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?>-emphasis"><?= htmlspecialchars($st) ?></span>
          </td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus pesanan #<?= (int)$r['id'] ?>?')">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada pesanan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

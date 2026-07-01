<?php
/**
 * admin/tempat_survei.php — Revisi 2 Juli 2026 #3
 * Menampilkan usulan tempat baru dari member (tabel tempat_survei) supaya
 * admin bisa meninjau, mengubah status, atau menghapus.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
send_security_headers();
require_role('admin');

try {
    db_exec("CREATE TABLE IF NOT EXISTS tempat_survei (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        nama VARCHAR(180) NOT NULL,
        alamat TEXT, jenis VARCHAR(80),
        lat DOUBLE PRECISION, lng DOUBLE PRECISION,
        catatan TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'baru',
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        updated_at TIMESTAMP
    )");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $act = $_POST['_act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    try {
        if ($act === 'status' && $id>0) {
            $st = trim($_POST['status'] ?? '');
            if (in_array($st,['baru','disetujui','ditolak'],true)) {
                db_exec("UPDATE tempat_survei SET status=$1, updated_at=now() WHERE id=$2", [$st,$id]);
            }
        } elseif ($act === 'delete' && $id>0) {
            db_exec("DELETE FROM tempat_survei WHERE id=$1", [$id]);
        }
        $_SESSION['flash'] = 'Berhasil.';
    } catch (Throwable $e) { $_SESSION['flash']='Gagal: '.$e->getMessage(); }
    header('Location: /admin/tempat_survei.php'); exit;
}

$fStatus = trim($_GET['status'] ?? '');
$where=''; $args=[];
if (in_array($fStatus,['baru','disetujui','ditolak'],true)) { $where=' WHERE s.status=$1'; $args=[$fStatus]; }
try {
    $rows = db_all("SELECT s.*, u.nama AS pengusul, u.email AS pengusul_email, u.nomor_wa
                    FROM tempat_survei s LEFT JOIN users u ON u.id=s.user_id
                    $where ORDER BY s.id DESC LIMIT 500", $args);
} catch (Throwable $e) { $rows=[]; }

$pageTitle = 'Admin — Usulan Tempat (Survei)';
include __DIR__.'/../includes/header.php';
$csrf = csrf_token();
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item">Admin</li>
  <li class="breadcrumb-item active">Usulan Tempat (Survei)</li>
</ol></nav>

<h3 class="mb-3"><i class="bi bi-hourglass-split text-warning"></i> Usulan Tempat dari Member</h3>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-info py-2"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
      <option value="">— Semua status —</option>
      <?php foreach (['baru','disetujui','ditolak'] as $s): ?>
        <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-9 text-md-end small text-muted align-self-center">Total: <b><?= count($rows) ?></b></div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" style="min-width:1050px">
      <thead class="table-light">
        <tr><th>Nama Tempat</th><th>Jenis</th><th>Alamat</th><th>Pengusul</th><th>Koordinat</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada usulan.</td></tr>
      <?php else: foreach ($rows as $r):
        $st=$r['status']; $cls=$st==='disetujui'?'success':($st==='ditolak'?'danger':'secondary');
      ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></td>
          <td class="small"><?= htmlspecialchars((string)($r['jenis'] ?? '—')) ?></td>
          <td class="small text-muted"><?= htmlspecialchars((string)($r['alamat'] ?? '—')) ?><?php if(!empty($r['catatan'])): ?><div class="small text-muted fst-italic">Catatan: <?= htmlspecialchars($r['catatan']) ?></div><?php endif; ?></td>
          <td class="small"><?= htmlspecialchars((string)($r['pengusul'] ?? '#'.$r['user_id'])) ?><?php if(!empty($r['nomor_wa'])): ?><div class="text-muted"><?= htmlspecialchars($r['nomor_wa']) ?></div><?php endif; ?></td>
          <td class="small font-monospace">
            <?php if ($r['lat']!==null && $r['lng']!==null): ?>
              <a target="_blank" href="https://www.google.com/maps?q=<?= (float)$r['lat'] ?>,<?= (float)$r['lng'] ?>"><?= number_format((float)$r['lat'],5) ?>, <?= number_format((float)$r['lng'],5) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span></td>
          <td class="small"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="_act" value="status">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                <?php foreach (['baru','disetujui','ditolak'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $st===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus usulan ini?');">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="_act" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
/**
 * admin/komunitas.php — Revisi R4 (Juli 2026)
 * CRUD master Komunitas.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
send_security_headers();
require_role(['admin']);

// Idempotent migration
try {
    db_exec("CREATE TABLE IF NOT EXISTS komunitas (
        id SERIAL PRIMARY KEY,
        nama VARCHAR(120) NOT NULL,
        slug VARCHAR(140) UNIQUE,
        deskripsi TEXT,
        kota VARCHAR(120),
        kontak_wa VARCHAR(30),
        logo_url TEXT,
        warna VARCHAR(20) DEFAULT '#0ea5e9',
        aktif SMALLINT NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        updated_at TIMESTAMP
    )");
} catch (Throwable $e) {}

$flash = null; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['_action'] ?? '';
    try {
        if ($act === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim((string)($_POST['nama'] ?? ''));
            if ($nama === '') throw new RuntimeException('Nama komunitas wajib diisi.');
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($slug === '') $slug = strtolower(preg_replace('~[^a-z0-9]+~i','-', $nama));
            $slug = trim($slug, '-');
            $data = [
                $nama, $slug,
                trim((string)($_POST['deskripsi'] ?? '')),
                trim((string)($_POST['kota'] ?? '')),
                trim((string)($_POST['kontak_wa'] ?? '')),
                trim((string)($_POST['logo_url'] ?? '')),
                trim((string)($_POST['warna'] ?? '#0ea5e9')),
                !empty($_POST['aktif']) ? 1 : 0,
            ];
            if ($id > 0) {
                $data[] = $id;
                db_exec("UPDATE komunitas SET nama=$1,slug=$2,deskripsi=$3,kota=$4,kontak_wa=$5,logo_url=$6,warna=$7,aktif=$8,updated_at=now() WHERE id=$9", $data);
                $flash = 'Komunitas diperbarui.';
            } else {
                db_exec("INSERT INTO komunitas(nama,slug,deskripsi,kota,kontak_wa,logo_url,warna,aktif) VALUES($1,$2,$3,$4,$5,$6,$7,$8)", $data);
                $flash = 'Komunitas ditambahkan.';
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) { db_exec("DELETE FROM komunitas WHERE id=$1", [$id]); $flash = 'Komunitas dihapus.'; }
        }
    } catch (Throwable $e) {
        $flash = 'Gagal: '.$e->getMessage(); $flashType = 'danger';
    }
    header('Location: /admin/komunitas.php?flash='.urlencode((string)$flash).'&t='.$flashType);
    exit;
}

$rows = db_all("SELECT k.*, (SELECT COUNT(*) FROM komunitas_data d WHERE d.komunitas_id=k.id) AS n_data,
                        (SELECT COUNT(*) FROM jadwal j WHERE j.komunitas_id=k.id) AS n_jadwal
                 FROM komunitas k ORDER BY k.aktif DESC, k.nama ASC");

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_one("SELECT * FROM komunitas WHERE id=$1", [(int)$_GET['edit']]);
}

$pageTitle = 'Admin — Komunitas';
include __DIR__.'/../includes/header.php';
?>
<div class="container my-3">
  <nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item">Admin</li>
    <li class="breadcrumb-item active">Komunitas</li>
  </ol></nav>

  <?php if (!empty($_GET['flash'])): $t = $_GET['t'] ?? 'success'; ?>
    <div class="alert alert-<?= htmlspecialchars($t) ?> py-2 small"><?= htmlspecialchars($_GET['flash']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-header bg-success-subtle"><i class="bi bi-people-fill"></i> <?= $edit ? 'Edit' : 'Tambah' ?> Komunitas</div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="col-12"><label class="form-label small mb-0">Nama *</label>
              <input class="form-control form-control-sm" name="nama" required value="<?= htmlspecialchars($edit['nama'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small mb-0">Slug</label>
              <input class="form-control form-control-sm" name="slug" placeholder="auto dari nama" value="<?= htmlspecialchars($edit['slug'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small mb-0">Kota</label>
              <input class="form-control form-control-sm" name="kota" value="<?= htmlspecialchars($edit['kota'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label small mb-0">Deskripsi</label>
              <textarea class="form-control form-control-sm" name="deskripsi" rows="2"><?= htmlspecialchars($edit['deskripsi'] ?? '') ?></textarea></div>
            <div class="col-6"><label class="form-label small mb-0">Kontak WA</label>
              <input class="form-control form-control-sm" name="kontak_wa" placeholder="628xxxx" value="<?= htmlspecialchars($edit['kontak_wa'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small mb-0">Warna</label>
              <input class="form-control form-control-color form-control-sm" type="color" name="warna" value="<?= htmlspecialchars($edit['warna'] ?? '#0ea5e9') ?>"></div>
            <div class="col-12"><label class="form-label small mb-0">Logo URL</label>
              <input class="form-control form-control-sm" name="logo_url" value="<?= htmlspecialchars($edit['logo_url'] ?? '') ?>"></div>
            <div class="col-12 form-check ms-2">
              <input class="form-check-input" type="checkbox" name="aktif" id="aktif" <?= (!$edit || (int)($edit['aktif'] ?? 1)===1) ? 'checked' : '' ?>>
              <label for="aktif" class="form-check-label small">Aktif</label>
            </div>
            <div class="col-12">
              <button class="btn btn-success btn-sm"><i class="bi bi-save"></i> Simpan</button>
              <?php if ($edit): ?><a href="/admin/komunitas.php" class="btn btn-outline-secondary btn-sm">Batal</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-list-ul"></i> Daftar Komunitas (<?= count($rows) ?>)</span>
        </div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
          <thead class="table-light"><tr><th>Nama</th><th>Kota</th><th>Data</th><th>Jadwal</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($r['warna'] ?? '#0ea5e9') ?>;margin-right:6px"></span>
                <strong><?= htmlspecialchars($r['nama']) ?></strong>
                <?php if(!empty($r['slug'])): ?><div class="small text-muted">/<?= htmlspecialchars($r['slug']) ?></div><?php endif; ?>
              </td>
              <td class="small"><?= htmlspecialchars($r['kota'] ?? '—') ?></td>
              <td><span class="badge bg-info-subtle text-info"><?= (int)$r['n_data'] ?></span></td>
              <td><span class="badge bg-primary-subtle text-primary"><?= (int)$r['n_jadwal'] ?></span></td>
              <td>
                <?php if ((int)$r['aktif']===1): ?><span class="badge bg-success">Aktif</span>
                <?php else: ?><span class="badge bg-secondary">Nonaktif</span><?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="/admin/komunitas.php?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
                <a class="btn btn-sm btn-outline-info" href="/admin/komunitas_data.php?komunitas_id=<?= (int)$r['id'] ?>"><i class="bi bi-collection"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus komunitas ini beserta seluruh datanya?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted small py-3">Belum ada komunitas. Tambahkan di form kiri.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
/**
 * admin/komunitas_data.php — Revisi R4 (Juli 2026)
 * CRUD Data Komunitas (berelasi ke komunitas.id).
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
send_security_headers();
require_role(['superadmin']);

try {
    db_exec("CREATE TABLE IF NOT EXISTS komunitas_data (
        id SERIAL PRIMARY KEY,
        komunitas_id INTEGER NOT NULL REFERENCES komunitas(id) ON DELETE CASCADE,
        judul VARCHAR(180) NOT NULL,
        kategori VARCHAR(60),
        isi TEXT,
        tanggal DATE,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        updated_at TIMESTAMP
    )");
} catch (Throwable $e) {}

$komList = db_all("SELECT id,nama FROM komunitas WHERE aktif=1 ORDER BY nama");
$flash = null; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['_action'] ?? '';
    try {
        if ($act === 'save') {
            $id  = (int)($_POST['id'] ?? 0);
            $kid = (int)($_POST['komunitas_id'] ?? 0);
            $jud = trim((string)($_POST['judul'] ?? ''));
            if ($kid <= 0) throw new RuntimeException('Pilih komunitas dahulu.');
            if ($jud === '') throw new RuntimeException('Judul wajib diisi.');
            $kat = trim((string)($_POST['kategori'] ?? ''));
            $isi = trim((string)($_POST['isi'] ?? ''));
            $tgl = trim((string)($_POST['tanggal'] ?? ''));
            $tgl = $tgl === '' ? null : $tgl;
            if ($id > 0) {
                db_exec("UPDATE komunitas_data SET komunitas_id=$1,judul=$2,kategori=$3,isi=$4,tanggal=$5,updated_at=now() WHERE id=$6",
                    [$kid,$jud,$kat,$isi,$tgl,$id]);
                $flash = 'Data komunitas diperbarui.';
            } else {
                db_exec("INSERT INTO komunitas_data(komunitas_id,judul,kategori,isi,tanggal) VALUES($1,$2,$3,$4,$5)",
                    [$kid,$jud,$kat,$isi,$tgl]);
                $flash = 'Data komunitas ditambahkan.';
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) { db_exec("DELETE FROM komunitas_data WHERE id=$1", [$id]); $flash = 'Data dihapus.'; }
        }
    } catch (Throwable $e) {
        $flash = 'Gagal: '.$e->getMessage(); $flashType = 'danger';
    }
    $back = '/admin/komunitas_data.php';
    if (!empty($_POST['komunitas_id'])) $back .= '?komunitas_id='.(int)$_POST['komunitas_id'];
    header('Location: '.$back.((strpos($back,'?')!==false)?'&':'?').'flash='.urlencode((string)$flash).'&t='.$flashType);
    exit;
}

$filterKom = (int)($_GET['komunitas_id'] ?? 0);
$sql = "SELECT d.*, k.nama AS kom_nama, k.warna AS kom_warna FROM komunitas_data d
        JOIN komunitas k ON k.id=d.komunitas_id";
$params = [];
if ($filterKom > 0) { $sql .= " WHERE d.komunitas_id=$1"; $params[] = $filterKom; }
$sql .= " ORDER BY d.tanggal DESC NULLS LAST, d.id DESC";
$rows = db_all($sql, $params);

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_one("SELECT * FROM komunitas_data WHERE id=$1", [(int)$_GET['edit']]);
    if ($edit) $filterKom = (int)$edit['komunitas_id'];
}

$pageTitle = 'Admin — Data Komunitas';
include __DIR__.'/../includes/header.php';
?>
<div class="container my-3">
  <nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item"><a href="/admin/komunitas.php">Komunitas</a></li>
    <li class="breadcrumb-item active">Data Komunitas</li>
  </ol></nav>

  <?php if (!empty($_GET['flash'])): $t = $_GET['t'] ?? 'success'; ?>
    <div class="alert alert-<?= htmlspecialchars($t) ?> py-2 small"><?= htmlspecialchars($_GET['flash']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-header bg-info-subtle"><i class="bi bi-collection"></i> <?= $edit ? 'Edit' : 'Tambah' ?> Data Komunitas</div>
        <div class="card-body">
          <?php if (!$komList): ?>
            <div class="alert alert-warning small mb-0">Belum ada komunitas. <a href="/admin/komunitas.php">Buat komunitas dulu</a>.</div>
          <?php else: ?>
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="col-12"><label class="form-label small mb-0">Komunitas *</label>
              <select class="form-select form-select-sm" name="komunitas_id" required>
                <option value="">— pilih komunitas —</option>
                <?php foreach ($komList as $k): ?>
                  <option value="<?= (int)$k['id'] ?>" <?= ((int)($edit['komunitas_id'] ?? $filterKom) === (int)$k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label small mb-0">Judul *</label>
              <input class="form-control form-control-sm" name="judul" required value="<?= htmlspecialchars($edit['judul'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small mb-0">Kategori</label>
              <input list="katlist" class="form-control form-control-sm" name="kategori" value="<?= htmlspecialchars($edit['kategori'] ?? '') ?>">
              <datalist id="katlist"><option>kegiatan<option>pengurus<option>pengumuman<option>fasilitas</datalist>
            </div>
            <div class="col-6"><label class="form-label small mb-0">Tanggal</label>
              <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= htmlspecialchars($edit['tanggal'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label small mb-0">Isi</label>
              <textarea class="form-control form-control-sm" name="isi" rows="4"><?= htmlspecialchars($edit['isi'] ?? '') ?></textarea></div>
            <div class="col-12">
              <button class="btn btn-info text-white btn-sm"><i class="bi bi-save"></i> Simpan</button>
              <?php if ($edit): ?><a href="/admin/komunitas_data.php" class="btn btn-outline-secondary btn-sm">Batal</a><?php endif; ?>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-list-ul"></i> Data Komunitas (<?= count($rows) ?>)</span>
          <form method="get" class="d-flex gap-1">
            <select name="komunitas_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="0">— semua komunitas —</option>
              <?php foreach ($komList as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= $filterKom===(int)$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
          <thead class="table-light"><tr><th>Judul</th><th>Komunitas</th><th>Kategori</th><th>Tanggal</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($r['judul']) ?></strong>
                <?php if(!empty($r['isi'])): ?><div class="small text-muted"><?= htmlspecialchars(mb_substr($r['isi'],0,90)) ?><?= mb_strlen($r['isi'])>90?'…':'' ?></div><?php endif; ?>
              </td>
              <td>
                <span class="badge" style="background:<?= htmlspecialchars($r['kom_warna'] ?? '#0ea5e9') ?>"><?= htmlspecialchars($r['kom_nama']) ?></span>
              </td>
              <td class="small"><?= htmlspecialchars($r['kategori'] ?? '—') ?></td>
              <td class="small"><?= htmlspecialchars($r['tanggal'] ?? '—') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="/admin/komunitas_data.php?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="komunitas_id" value="<?= (int)$r['komunitas_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted small py-3">Belum ada data.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>

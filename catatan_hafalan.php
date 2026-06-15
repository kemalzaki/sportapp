<?php
// catatan_hafalan.php — CRUD Catatan Hafalan (Revisi 11 Juni 2026)
// Diakses dari islami.php. Pola CRUD sederhana mirip Literatur Buku.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Catatan Hafalan';
$u = current_user();
if (!$u) { header('Location: /login.php?next=/catatan_hafalan.php'); exit; }

/* ---------- Auto-migration ---------- */
try {
  db_exec("CREATE TABLE IF NOT EXISTS catatan_hafalan (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    jenis VARCHAR(40) NOT NULL DEFAULT 'Quran',  -- Quran / Hadist / Doa / Lainnya
    judul VARCHAR(200) NOT NULL,
    referensi VARCHAR(200),                       -- mis: Q.S. Al-Baqarah:255 / HR. Muslim
    target_ayat INTEGER DEFAULT 0,
    sudah_ayat  INTEGER DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'progress', -- belum / progress / selesai / muraja'ah
    catatan TEXT,
    last_review DATE,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
  )");
  db_exec("CREATE INDEX IF NOT EXISTS catatan_hafalan_user_idx ON catatan_hafalan(user_id)");
} catch (Throwable $e) {}

/* ---------- Handle aksi ---------- */
$uid = (int)$u['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $op = $_POST['op'] ?? '';
  if ($op === 'create' || $op === 'update') {
    $id    = (int)($_POST['id'] ?? 0);
    $jenis = trim($_POST['jenis'] ?? 'Quran');
    $judul = trim($_POST['judul'] ?? '');
    $ref   = trim($_POST['referensi'] ?? '');
    $tgt   = max(0,(int)($_POST['target_ayat'] ?? 0));
    $sdh   = max(0,(int)($_POST['sudah_ayat'] ?? 0));
    $st    = trim($_POST['status'] ?? 'progress');
    $cat   = trim($_POST['catatan'] ?? '');
    $lr    = trim($_POST['last_review'] ?? '');
    $lrVal = $lr !== '' ? $lr : null;
    if ($judul === '') { $msg = 'Judul wajib diisi.'; }
    else {
      if ($op === 'create') {
        db_exec("INSERT INTO catatan_hafalan(user_id,jenis,judul,referensi,target_ayat,sudah_ayat,status,catatan,last_review)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)",
                 [$uid,$jenis,$judul,$ref,$tgt,$sdh,$st,$cat,$lrVal]);
        $msg = 'Catatan hafalan ditambahkan.';
      } else {
        db_exec("UPDATE catatan_hafalan
                 SET jenis=$2, judul=$3, referensi=$4, target_ayat=$5, sudah_ayat=$6,
                     status=$7, catatan=$8, last_review=$9, updated_at=now()
                 WHERE id=$1 AND user_id=$10",
                 [$id,$jenis,$judul,$ref,$tgt,$sdh,$st,$cat,$lrVal,$uid]);
        $msg = 'Catatan diperbarui.';
      }
    }
  } elseif ($op === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db_exec("DELETE FROM catatan_hafalan WHERE id=$1 AND user_id=$2",[$id,$uid]);
    $msg = 'Catatan dihapus.';
  }
}

$list = db_all("SELECT * FROM catatan_hafalan WHERE user_id=$1 ORDER BY
                CASE status WHEN 'progress' THEN 0 WHEN 'muraja''ah' THEN 1 WHEN 'belum' THEN 2 ELSE 3 END,
                updated_at DESC", [$uid]);

$edit = null;
if (isset($_GET['edit'])) {
  $edit = db_one("SELECT * FROM catatan_hafalan WHERE id=$1 AND user_id=$2",[(int)$_GET['edit'],$uid]);
}

$stat = db_one("SELECT
  COUNT(*) total,
  COALESCE(SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END),0) selesai,
  COALESCE(SUM(CASE WHEN status='progress' THEN 1 ELSE 0 END),0) progress,
  COALESCE(SUM(target_ayat),0) tgt,
  COALESCE(SUM(sudah_ayat),0) sdh
  FROM catatan_hafalan WHERE user_id=$1",[$uid]);

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Catatan Hafalan');
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item"><a href="/islami.php">Islami</a></li>
    <li class="breadcrumb-item active">Catatan Hafalan</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-bookmark-heart text-success"></i> Catatan Hafalan</h2>
<?php if($msg): ?><div class="alert alert-info py-2 small"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Total</div><div class="h4 mb-0"><?= (int)$stat['total'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Progress</div><div class="h4 mb-0 text-primary"><?= (int)$stat['progress'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Selesai</div><div class="h4 mb-0 text-success"><?= (int)$stat['selesai'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Ayat (Sdh/Tgt)</div><div class="h4 mb-0"><?= (int)$stat['sdh'] ?>/<?= (int)$stat['tgt'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi <?= $edit?'bi-pencil-square':'bi-plus-circle' ?>"></i> <?= $edit ? 'Edit Catatan' : 'Tambah Catatan' ?></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="op" value="<?= $edit?'update':'create' ?>">
          <?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
          <div class="mb-2"><label class="small fw-semibold">Jenis</label>
            <select name="jenis" class="form-select form-select-sm">
              <?php foreach(['Quran','Hadist','Doa','Lainnya'] as $j): ?>
                <option <?= ($edit['jenis']??'Quran')===$j?'selected':'' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="small fw-semibold">Judul</label>
            <input type="text" name="judul" class="form-control form-control-sm" required value="<?= htmlspecialchars($edit['judul'] ?? '') ?>" placeholder="Mis: Surat Al-Mulk"></div>
          <div class="mb-2"><label class="small fw-semibold">Referensi</label>
            <input type="text" name="referensi" class="form-control form-control-sm" value="<?= htmlspecialchars($edit['referensi'] ?? '') ?>" placeholder="Q.S. 67 / HR. Muslim no. 123"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="small fw-semibold">Target (ayat)</label>
              <input type="number" min="0" name="target_ayat" class="form-control form-control-sm" value="<?= (int)($edit['target_ayat'] ?? 0) ?>"></div>
            <div class="col-6"><label class="small fw-semibold">Sudah (ayat)</label>
              <input type="number" min="0" name="sudah_ayat" class="form-control form-control-sm" value="<?= (int)($edit['sudah_ayat'] ?? 0) ?>"></div>
          </div>
          <div class="mb-2"><label class="small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <?php foreach(['belum','progress','muraja\'ah','selesai'] as $s): ?>
                <option <?= ($edit['status']??'progress')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="small fw-semibold">Murajaah terakhir</label>
            <input type="date" name="last_review" class="form-control form-control-sm" value="<?= htmlspecialchars($edit['last_review'] ?? '') ?>"></div>
          <div class="mb-3"><label class="small fw-semibold">Catatan</label>
            <textarea name="catatan" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($edit['catatan'] ?? '') ?></textarea></div>
          <button class="btn btn-success btn-sm"><i class="bi bi-save"></i> Simpan</button>
          <?php if($edit): ?><a class="btn btn-outline-secondary btn-sm" href="/catatan_hafalan.php">Batal</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi bi-list-check"></i> Daftar Catatan</div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Jenis</th><th>Judul / Ref</th><th>Progres</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($list as $r):
          $pct = ($r['target_ayat']>0) ? min(100, round(100*$r['sudah_ayat']/$r['target_ayat'])) : 0;
        ?>
          <tr>
            <td class="small"><?= htmlspecialchars($r['jenis']) ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($r['judul']) ?></div>
              <?php if($r['referensi']): ?><div class="small text-muted"><?= htmlspecialchars($r['referensi']) ?></div><?php endif; ?>
              <?php if($r['catatan']): ?><div class="small mt-1"><?= nl2br(htmlspecialchars($r['catatan'])) ?></div><?php endif; ?>
              <?php if(!empty($r['last_review'])): ?><div class="small text-muted">Murajaah: <?= htmlspecialchars($r['last_review']) ?></div><?php endif; ?>
            </td>
            <td style="min-width:120px">
              <div class="small text-muted"><?= (int)$r['sudah_ayat'] ?>/<?= (int)$r['target_ayat'] ?> ayat</div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><span class="badge bg-<?= $r['status']==='selesai'?'success':($r['status']==='progress'?'primary':($r['status']==='muraja\'ah'?'info':'secondary')) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus catatan ini?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; if(!$list): ?><tr><td colspan="5" class="text-center text-muted small py-3">Belum ada catatan hafalan. Tambahkan dari form di kiri.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php htmx_layout_end(); ?>

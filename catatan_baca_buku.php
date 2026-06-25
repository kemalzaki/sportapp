<?php
// catatan_baca_buku.php — CRUD Catatan Progress Baca Buku (Revisi 25 Juni 2026)
// Diakses dari islami.php. Pola CRUD mirip Catatan Hafalan.
// Data buku diambil dari Kajian Literatur Buku (tabel islami_kajian).
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Catatan Baca Buku';
$u = current_user();
if (!$u) { header('Location: /login.php?next=/catatan_baca_buku.php'); exit; }

/* ---------- Auto-migration ---------- */
try {
  db_exec("CREATE TABLE IF NOT EXISTS catatan_baca_buku (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    kajian_id INTEGER,                              -- referensi ke islami_kajian (boleh NULL)
    judul_buku VARCHAR(200) NOT NULL,              -- snapshot judul agar tetap ada bila kajian dihapus
    penulis VARCHAR(150),
    halaman_total   INTEGER DEFAULT 0,
    halaman_dibaca  INTEGER DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'baca',    -- belum / baca / selesai / pause
    rating SMALLINT DEFAULT 0,                      -- 0..5
    catatan TEXT,
    last_read DATE,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
  )");
  db_exec("CREATE INDEX IF NOT EXISTS catatan_baca_buku_user_idx ON catatan_baca_buku(user_id)");
} catch (Throwable $e) {}

$uid = (int)$u['id'];
$msg = '';

/* ---------- Daftar buku dari Kajian Literatur ---------- */
$kajianList = [];
try {
  $kajianList = db_all("SELECT id, judul, penulis FROM islami_kajian ORDER BY created_at DESC");
} catch (Throwable $e) { $kajianList = []; }
$kajianMap = [];
foreach ($kajianList as $k) $kajianMap[(int)$k['id']] = $k;

/* ---------- Handle aksi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $op = $_POST['op'] ?? '';
  if ($op === 'create' || $op === 'update') {
    $id    = (int)($_POST['id'] ?? 0);
    $kid   = (int)($_POST['kajian_id'] ?? 0);
    // Ambil snapshot judul & penulis dari kajian yang dipilih (jika ada)
    if ($kid > 0 && isset($kajianMap[$kid])) {
      $judul   = substr($kajianMap[$kid]['judul'] ?? '', 0, 200);
      $penulis = substr($kajianMap[$kid]['penulis'] ?? '', 0, 150);
    } else {
      $kid     = 0;
      $judul   = substr(trim($_POST['judul_buku'] ?? ''), 0, 200);
      $penulis = substr(trim($_POST['penulis'] ?? ''), 0, 150);
    }
    $hTot  = max(0,(int)($_POST['halaman_total'] ?? 0));
    $hDib  = max(0,(int)($_POST['halaman_dibaca'] ?? 0));
    $st    = in_array($_POST['status'] ?? 'baca', ['belum','baca','selesai','pause'], true) ? $_POST['status'] : 'baca';
    $rate  = max(0, min(5,(int)($_POST['rating'] ?? 0)));
    $cat   = trim($_POST['catatan'] ?? '');
    $lr    = trim($_POST['last_read'] ?? '');
    $lrVal = $lr !== '' ? $lr : null;
    $kidVal = $kid > 0 ? $kid : null;
    if ($judul === '') { $msg = 'Pilih buku dari Kajian Literatur atau isi judul buku.'; }
    else {
      if ($op === 'create') {
        db_exec("INSERT INTO catatan_baca_buku(user_id,kajian_id,judul_buku,penulis,halaman_total,halaman_dibaca,status,rating,catatan,last_read)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
                 [$uid,$kidVal,$judul,$penulis,$hTot,$hDib,$st,$rate,$cat,$lrVal]);
        $msg = 'Catatan baca buku ditambahkan.';
      } else {
        db_exec("UPDATE catatan_baca_buku
                 SET kajian_id=$2, judul_buku=$3, penulis=$4, halaman_total=$5, halaman_dibaca=$6,
                     status=$7, rating=$8, catatan=$9, last_read=$10, updated_at=now()
                 WHERE id=$1 AND user_id=$11",
                 [$id,$kidVal,$judul,$penulis,$hTot,$hDib,$st,$rate,$cat,$lrVal,$uid]);
        $msg = 'Catatan diperbarui.';
      }
    }
  } elseif ($op === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db_exec("DELETE FROM catatan_baca_buku WHERE id=$1 AND user_id=$2",[$id,$uid]);
    $msg = 'Catatan dihapus.';
  }
}

/* ---------- Pagination ---------- */
$perPage = 10;
$totalRows = (int) db_val("SELECT COUNT(*) FROM catatan_baca_buku WHERE user_id=$1", [$uid]);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$list = db_all("SELECT * FROM catatan_baca_buku WHERE user_id=$1 ORDER BY
                CASE status WHEN 'baca' THEN 0 WHEN 'pause' THEN 1 WHEN 'belum' THEN 2 ELSE 3 END,
                updated_at DESC LIMIT $perPage OFFSET $offset", [$uid]);

$edit = null;
if (isset($_GET['edit'])) {
  $edit = db_one("SELECT * FROM catatan_baca_buku WHERE id=$1 AND user_id=$2",[(int)$_GET['edit'],$uid]);
}

$stat = db_one("SELECT
  COUNT(*) total,
  COALESCE(SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END),0) selesai,
  COALESCE(SUM(CASE WHEN status='baca' THEN 1 ELSE 0 END),0) baca,
  COALESCE(SUM(halaman_total),0) tgt,
  COALESCE(SUM(halaman_dibaca),0) sdh
  FROM catatan_baca_buku WHERE user_id=$1",[$uid]);

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item"><a href="/islami.php">Islami</a></li>
    <li class="breadcrumb-item"><a href="/kajian.php">Kajian Literatur</a></li>
    <li class="breadcrumb-item active">Catatan Baca Buku</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-journal-check text-info"></i> Catatan Progress Baca Buku</h2>
<p class="text-muted small">Pantau progress membaca buku/literatur yang sudah ditambahkan di <a href="/kajian.php">Kajian Literatur Buku</a>.</p>
<?php if($msg): ?><div class="alert alert-info py-2 small"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Total Buku</div><div class="h4 mb-0"><?= (int)$stat['total'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Sedang Baca</div><div class="h4 mb-0 text-primary"><?= (int)$stat['baca'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Selesai</div><div class="h4 mb-0 text-success"><?= (int)$stat['selesai'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Halaman (Dibaca/Total)</div><div class="h4 mb-0"><?= (int)$stat['sdh'] ?>/<?= (int)$stat['tgt'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi <?= $edit?'bi-pencil-square':'bi-plus-circle' ?>"></i> <?= $edit ? 'Edit Catatan' : 'Tambah Catatan' ?></div>
      <div class="card-body">
        <?php if(!$kajianList): ?>
          <div class="alert alert-warning small">Belum ada buku di Kajian Literatur. <a href="/kajian.php">Tambahkan dulu di sini</a>, atau isi judul buku manual di bawah.</div>
        <?php endif; ?>
        <form method="post" id="formBaca">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="op" value="<?= $edit?'update':'create' ?>">
          <?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
          <div class="mb-2"><label class="small fw-semibold">Buku (dari Kajian Literatur)</label>
            <select name="kajian_id" class="form-select form-select-sm" id="kajianSelect">
              <option value="0">— Pilih / isi manual —</option>
              <?php foreach($kajianList as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= ((int)($edit['kajian_id']??0)===(int)$k['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($k['judul']) ?><?= $k['penulis']? ' — '.htmlspecialchars($k['penulis']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text small">Pilih dari daftar agar judul & penulis terisi otomatis.</div>
          </div>
          <div class="mb-2" id="manualBox" style="<?= ((int)($edit['kajian_id']??0)>0)?'display:none':'' ?>">
            <div class="row g-2">
              <div class="col-12"><label class="small fw-semibold">Judul Buku (manual)</label>
                <input type="text" name="judul_buku" class="form-control form-control-sm" value="<?= htmlspecialchars($edit['judul_buku'] ?? '') ?>" placeholder="Judul buku"></div>
              <div class="col-12"><label class="small fw-semibold">Penulis (manual)</label>
                <input type="text" name="penulis" class="form-control form-control-sm" value="<?= htmlspecialchars($edit['penulis'] ?? '') ?>" placeholder="Penulis"></div>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="small fw-semibold">Total Halaman</label>
              <input type="number" min="0" name="halaman_total" class="form-control form-control-sm" value="<?= (int)($edit['halaman_total'] ?? 0) ?>"></div>
            <div class="col-6"><label class="small fw-semibold">Sudah Dibaca</label>
              <input type="number" min="0" name="halaman_dibaca" class="form-control form-control-sm" value="<?= (int)($edit['halaman_dibaca'] ?? 0) ?>"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="small fw-semibold">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php foreach(['belum','baca','pause','selesai'] as $s): ?>
                  <option <?= ($edit['status']??'baca')===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label class="small fw-semibold">Rating (0–5)</label>
              <input type="number" min="0" max="5" name="rating" class="form-control form-control-sm" value="<?= (int)($edit['rating'] ?? 0) ?>"></div>
          </div>
          <div class="mb-2"><label class="small fw-semibold">Terakhir dibaca</label>
            <input type="date" name="last_read" class="form-control form-control-sm" value="<?= htmlspecialchars($edit['last_read'] ?? '') ?>"></div>
          <div class="mb-3"><label class="small fw-semibold">Catatan</label>
            <textarea name="catatan" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($edit['catatan'] ?? '') ?></textarea></div>
          <button class="btn btn-info btn-sm text-white"><i class="bi bi-save"></i> Simpan</button>
          <button type="button" class="btn btn-outline-warning btn-sm" id="btnBersihkanBaca"><i class="bi bi-eraser"></i> Bersihkan</button>
          <?php if($edit): ?><a class="btn btn-outline-secondary btn-sm" href="/catatan_baca_buku.php"><i class="bi bi-x-circle"></i> Batal / Form Baru</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi bi-list-check"></i> Daftar Bacaan</div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Buku</th><th>Progres</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($list as $r):
          $pct = ($r['halaman_total']>0) ? min(100, round(100*$r['halaman_dibaca']/$r['halaman_total'])) : 0;
        ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($r['judul_buku']) ?></div>
              <?php if($r['penulis']): ?><div class="small text-muted">oleh <?= htmlspecialchars($r['penulis']) ?></div><?php endif; ?>
              <?php if((int)$r['rating']>0): ?><div class="small text-warning"><?= str_repeat('★',(int)$r['rating']).str_repeat('☆',5-(int)$r['rating']) ?></div><?php endif; ?>
              <?php if($r['catatan']): ?><div class="small mt-1"><?= nl2br(htmlspecialchars($r['catatan'])) ?></div><?php endif; ?>
              <?php if(!empty($r['last_read'])): ?><div class="small text-muted">Terakhir: <?= htmlspecialchars($r['last_read']) ?></div><?php endif; ?>
            </td>
            <td style="min-width:120px">
              <div class="small text-muted"><?= (int)$r['halaman_dibaca'] ?>/<?= (int)$r['halaman_total'] ?> hal (<?= $pct ?>%)</div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-info" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><span class="badge bg-<?= $r['status']==='selesai'?'success':($r['status']==='baca'?'primary':($r['status']==='pause'?'warning':'secondary')) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
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
        <?php endforeach; if(!$list): ?><tr><td colspan="4" class="text-center text-muted small py-3">Belum ada catatan baca buku. Tambahkan dari form di kiri.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if($totalPages > 1): ?>
      <div class="card-footer">
        <nav><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
          <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$page-1) ?>">«</a></li>
          <?php for($p=1;$p<=$totalPages;$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($totalPages,$page+1) ?>">»</a></li>
        </ul></nav>
        <div class="text-center small text-muted mt-1">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= $totalRows ?> buku</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  // Sembunyikan field manual ketika sebuah buku dipilih dari daftar kajian
  var sel = document.getElementById('kajianSelect');
  var box = document.getElementById('manualBox');
  if(sel && box){
    sel.addEventListener('change', function(){
      box.style.display = (sel.value && sel.value!=='0') ? 'none' : '';
    });
  }
  // Tombol bersihkan
  var form = document.getElementById('formBaca');
  var btn = document.getElementById('btnBersihkanBaca');
  if(btn && form){
    btn.addEventListener('click', function(){
      form.querySelectorAll('input[type=text],input[type=number],input[type=date],textarea').forEach(function(el){
        if(el.type==='number') el.value='0'; else el.value='';
      });
      if(sel) sel.value='0';
      if(box) box.style.display='';
      var st=form.querySelector('[name=status]'); if(st) st.value='baca';
    });
  }
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

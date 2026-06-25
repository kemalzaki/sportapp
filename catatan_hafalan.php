<?php
// catatan_hafalan.php — CRUD Catatan Hafalan (Revisi R13 - 25 Juni 2026)
// Perubahan R13:
//  (#1) Statistik "Total Ayat" eksplisit diambil dari SUM(target_ayat).
//  (#2) Filter pencarian kata kunci + surat via AJAX (endpoint ?ajax=list).
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php'; // $ISLAMI_SURAH
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Catatan Hafalan';
$u = current_user();
if (!$u) { header('Location: /login.php?next=/catatan_hafalan.php'); exit; }

/* ---------- Auto-migration ---------- */
try {
  db_exec("CREATE TABLE IF NOT EXISTS catatan_hafalan (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    jenis VARCHAR(40) NOT NULL DEFAULT 'Quran',
    judul VARCHAR(200) NOT NULL,
    referensi VARCHAR(200),
    target_ayat INTEGER DEFAULT 0,
    sudah_ayat  INTEGER DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'progress',
    catatan TEXT,
    last_review DATE,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now()
  )");
  db_exec("CREATE INDEX IF NOT EXISTS catatan_hafalan_user_idx ON catatan_hafalan(user_id)");
} catch (Throwable $e) {}

$uid = (int)$u['id'];

/* ---------- (#2) AJAX endpoint: list / search ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
  header('Content-Type: text/html; charset=utf-8');
  $kw     = trim($_GET['q'] ?? '');
  $surat  = trim($_GET['surat'] ?? '');
  $perPage = 10;
  $page    = max(1, (int)($_GET['page'] ?? 1));

  $where = ['user_id=$1']; $params = [$uid]; $i = 2;
  if ($kw !== '') {
    $where[] = "(judul ILIKE \$$i OR referensi ILIKE \$$i OR catatan ILIKE \$$i)";
    $params[] = '%'.$kw.'%'; $i++;
  }
  if ($surat !== '' && ctype_digit($surat)) {
    // cocokkan baik "Q.S. 67" atau nama surah dari $ISLAMI_SURAH
    $nm = $ISLAMI_SURAH[(int)$surat][0] ?? '';
    $where[] = "(referensi ~* \$$i OR judul ILIKE \$".($i+1)." OR referensi ILIKE \$".($i+1).")";
    $params[] = '(^|\D)'.((int)$surat).'(\D|$)';
    $params[] = '%'.$nm.'%';
    $i += 2;
  }
  $wsql = 'WHERE '.implode(' AND ',$where);
  $totalRows = (int) db_val("SELECT COUNT(*) FROM catatan_hafalan $wsql", $params);
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $perPage;
  $list = db_all("SELECT * FROM catatan_hafalan $wsql
                  ORDER BY CASE status WHEN 'progress' THEN 0 WHEN 'muraja''ah' THEN 1 WHEN 'belum' THEN 2 ELSE 3 END,
                  updated_at DESC LIMIT $perPage OFFSET $offset", $params);
  ?>
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Jenis</th><th>Judul / Ref</th><th>Progres</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach($list as $r):
      $pct = ($r['target_ayat']>0) ? min(100, round(100*$r['sudah_ayat']/$r['target_ayat'])) : 0;
      $isQuran = (strtolower($r['jenis'])==='quran'); ?>
      <tr>
        <td class="small"><?= htmlspecialchars($r['jenis']) ?></td>
        <td>
          <?php if($isQuran): ?>
            <a href="#" class="fw-semibold text-decoration-none js-ayat-pop" data-ref="<?= htmlspecialchars($r['referensi'] ?? '') ?>" data-judul="<?= htmlspecialchars($r['judul']) ?>"><?= htmlspecialchars($r['judul']) ?> <i class="bi bi-box-arrow-up-right small"></i></a>
            <?php if($r['referensi']): ?><div class="small"><a href="#" class="text-muted js-ayat-pop" data-ref="<?= htmlspecialchars($r['referensi']) ?>" data-judul="<?= htmlspecialchars($r['judul']) ?>"><?= htmlspecialchars($r['referensi']) ?></a></div><?php endif; ?>
          <?php else: ?>
            <div class="fw-semibold"><?= htmlspecialchars($r['judul']) ?></div>
            <?php if($r['referensi']): ?><div class="small text-muted"><?= htmlspecialchars($r['referensi']) ?></div><?php endif; ?>
          <?php endif; ?>
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
    <?php endforeach; if(!$list): ?>
      <tr><td colspan="5" class="text-center text-muted small py-3">Tidak ada hasil untuk filter ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <?php if($totalPages > 1): ?>
    <div class="p-2">
      <nav><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link js-page" href="#" data-page="<?= max(1,$page-1) ?>">«</a></li>
        <?php for($p=1;$p<=$totalPages;$p++): ?>
          <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link js-page" href="#" data-page="<?= $p ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link js-page" href="#" data-page="<?= min($totalPages,$page+1) ?>">»</a></li>
      </ul></nav>
      <div class="text-center small text-muted mt-1">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= $totalRows ?> catatan</div>
    </div>
  <?php endif; ?>
  <?php
  exit;
}

/* ---------- Handle CRUD ---------- */
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

$edit = null;
if (isset($_GET['edit'])) {
  $edit = db_one("SELECT * FROM catatan_hafalan WHERE id=$1 AND user_id=$2",[(int)$_GET['edit'],$uid]);
}

/* ---------- (#1) Statistik: Total Ayat = SUM(target_ayat) ---------- */
$stat = db_one("SELECT
  COUNT(*) total,
  COALESCE(SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END),0) selesai,
  COALESCE(SUM(CASE WHEN status='progress' THEN 1 ELSE 0 END),0) progress,
  COALESCE(SUM(target_ayat),0) total_ayat,
  COALESCE(SUM(sudah_ayat),0) sdh
  FROM catatan_hafalan WHERE user_id=$1",[$uid]);

include __DIR__.'/includes/header.php';
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
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Total Catatan</div><div class="h4 mb-0"><?= (int)$stat['total'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Progress</div><div class="h4 mb-0 text-primary"><?= (int)$stat['progress'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><div class="text-muted small">Selesai</div><div class="h4 mb-0 text-success"><?= (int)$stat['selesai'] ?></div></div></div></div>
  <!-- (#1) Total Ayat = SUM(target_ayat) -->
  <div class="col-6 col-md-3"><div class="card shadow-sm border-success"><div class="card-body text-center"><div class="text-muted small">Total Ayat <span class="text-muted">(target)</span></div><div class="h4 mb-0 text-success"><?= (int)$stat['total_ayat'] ?></div><div class="small text-muted">Sudah: <?= (int)$stat['sdh'] ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi <?= $edit?'bi-pencil-square':'bi-plus-circle' ?>"></i> <?= $edit ? 'Edit Catatan' : 'Tambah Catatan' ?></div>
      <div class="card-body">
        <form method="post" id="formHafalan">
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
          <button type="button" class="btn btn-outline-warning btn-sm" id="btnBersihkan"><i class="bi bi-eraser"></i> Bersihkan</button>
          <?php if($edit): ?>
            <a class="btn btn-outline-secondary btn-sm" href="/catatan_hafalan.php"><i class="bi bi-x-circle"></i> Batal / Form Baru</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-list-check"></i> Daftar Catatan</span>
      </div>
      <!-- (#2) Filter AJAX -->
      <div class="card-body border-bottom py-2">
        <form id="filterForm" class="row g-2" onsubmit="return false;">
          <div class="col-md-6">
            <input type="text" id="fKw" class="form-control form-control-sm" placeholder="Cari kata kunci (judul / catatan / referensi)...">
          </div>
          <div class="col-md-4">
            <select id="fSurat" class="form-select form-select-sm">
              <option value="">— Semua surat —</option>
              <?php foreach($ISLAMI_SURAH as $no=>$x): ?>
                <option value="<?= $no ?>"><?= $no.'. '.htmlspecialchars($x[0]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="fReset"><i class="bi bi-x"></i> Reset</button>
          </div>
        </form>
      </div>
      <div class="table-responsive" id="listBox">
        <div class="text-center text-muted py-4 small"><div class="spinner-border spinner-border-sm"></div> Memuat…</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal popup ayat -->
<div class="modal fade" id="ayatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-book text-success"></i> <span id="ayatModalTitle">Ayat</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="ayatModalBody">
        <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat…</div>
      </div>
      <div class="modal-footer">
        <a href="#" id="ayatModalOpen" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-box-arrow-up-right"></i> Buka di Al-Qur'an Digital</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  /* ---- Bersihkan ---- */
  var form = document.getElementById('formHafalan');
  var btnClear = document.getElementById('btnBersihkan');
  if(btnClear && form){
    btnClear.addEventListener('click', function(){
      form.querySelectorAll('input[type=text],input[type=number],input[type=date],textarea').forEach(function(el){
        if(el.type==='number') el.value='0'; else el.value='';
      });
      var jns=form.querySelector('[name=jenis]'); if(jns) jns.selectedIndex=0;
      var st=form.querySelector('[name=status]'); if(st){ st.value='progress'; }
      var judul=form.querySelector('[name=judul]'); if(judul) judul.focus();
    });
  }

  /* ---- (#2) AJAX list + filter ---- */
  var listBox = document.getElementById('listBox');
  var fKw = document.getElementById('fKw');
  var fSurat = document.getElementById('fSurat');
  var fReset = document.getElementById('fReset');
  var curPage = 1, debounceT = null;

  function loadList(page){
    curPage = page || 1;
    var params = new URLSearchParams({
      ajax:'list', q: fKw.value, surat: fSurat.value, page: curPage
    });
    listBox.innerHTML = '<div class="text-center text-muted py-4 small"><div class="spinner-border spinner-border-sm"></div> Memuat…</div>';
    fetch('/catatan_hafalan.php?'+params.toString(), {credentials:'same-origin'})
      .then(function(r){ return r.text(); })
      .then(function(html){
        listBox.innerHTML = html;
        bindRowActions();
      })
      .catch(function(){
        listBox.innerHTML = '<div class="alert alert-danger small m-2">Gagal memuat daftar.</div>';
      });
  }
  function debounce(fn){ clearTimeout(debounceT); debounceT = setTimeout(fn, 300); }
  fKw.addEventListener('input', function(){ debounce(function(){ loadList(1); }); });
  fSurat.addEventListener('change', function(){ loadList(1); });
  fReset.addEventListener('click', function(){ fKw.value=''; fSurat.value=''; loadList(1); });

  function bindRowActions(){
    listBox.querySelectorAll('.js-page').forEach(function(a){
      a.addEventListener('click', function(e){
        e.preventDefault();
        loadList(+a.dataset.page || 1);
      });
    });
    listBox.querySelectorAll('.js-ayat-pop').forEach(function(el){
      el.addEventListener('click', function(e){
        e.preventDefault();
        showAyat(el.dataset.ref, el.dataset.judul);
      });
    });
  }

  /* ---- Popup ayat ---- */
  var SURAH = <?= json_encode(array_map(function($x){return $x[0];}, $ISLAMI_SURAH), JSON_UNESCAPED_UNICODE) ?>;
  function norm(s){ return (s||'').toString().toLowerCase().replace(/[^a-z0-9]/g,''); }
  function findSurah(text){
    if(!text) return null;
    var qsNum = text.match(/q\.?\s*s\.?\s*(\d{1,3})/i);
    if(qsNum && +qsNum[1]>=1 && +qsNum[1]<=114) return +qsNum[1];
    var nt = norm(text);
    for(var no in SURAH){ if(nt.indexOf(norm(SURAH[no]))>=0) return +no; }
    var num = text.match(/\b(\d{1,3})\b/);
    if(num && +num[1]>=1 && +num[1]<=114) return +num[1];
    return null;
  }
  function findAyat(text){
    if(!text) return null;
    var m = text.match(/[:：]\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?/);
    if(!m) m = text.match(/ayat\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?/i);
    if(!m) return null;
    return [ +m[1], m[2]? +m[2] : +m[1] ];
  }
  var modalEl = document.getElementById('ayatModal');
  var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  var body = document.getElementById('ayatModalBody');
  var titleEl = document.getElementById('ayatModalTitle');
  var openEl = document.getElementById('ayatModalOpen');

  async function showAyat(ref, judul){
    var combined = (ref||'') + ' ' + (judul||'');
    var s = findSurah(combined);
    var range = findAyat(combined);
    if(!s){
      titleEl.textContent = judul || 'Ayat';
      body.innerHTML = '<div class="alert alert-warning small mb-0">Tidak bisa mengenali surah dari referensi "<b>'+ (ref||judul) +'</b>".</div>';
      openEl.href = '/quran.php'; modal.show(); return;
    }
    titleEl.textContent = 'QS '+SURAH[s]+(range? ' : '+range[0]+(range[1]!==range[0]?'-'+range[1]:''):'');
    openEl.href = '/quran_surah.php?s='+s + (range? '&a='+range[0]+'#a'+range[0] : '');
    body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat ayat…</div>';
    modal.show();
    try{
      var r = await fetch('https://equran.id/api/v2/surat/'+s);
      var j = await r.json();
      var ayatArr = (j && j.data && j.data.ayat) ? j.data.ayat : [];
      var from = range? range[0] : 1;
      var to   = range? range[1] : Math.min(ayatArr.length, 7);
      var html = '<div class="mb-2 small text-muted">Surah '+SURAH[s]+' · '+(j.data.arti||'')+'</div>';
      ayatArr.forEach(function(a){
        if(a.nomorAyat>=from && a.nomorAyat<=to){
          html += '<div class="border-bottom py-2">'
            + '<div class="text-end" dir="rtl" style="font-family:\'Amiri\',serif;font-size:1.6rem;line-height:2.2">'+a.teksArab+' <span class="badge bg-success">'+a.nomorAyat+'</span></div>'
            + '<div class="small text-success fst-italic mt-1">'+(a.teksLatin||'')+'</div>'
            + '<div class="small mt-1">'+(a.teksIndonesia||'')+'</div>'
            + '</div>';
        }
      });
      body.innerHTML = html || '<div class="alert alert-warning small mb-0">Ayat tidak ditemukan.</div>';
    }catch(e){
      body.innerHTML = '<div class="alert alert-danger small mb-0">Gagal memuat ayat (perlu koneksi internet).</div>';
    }
  }

  // initial load
  loadList(1);
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

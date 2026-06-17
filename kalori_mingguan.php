<?php
// kalori_mingguan.php — Pencatatan kalori mingguan + AI estimasi kalori dari foto
// AI: gunakan OPENAI_API_KEY (model gpt-4o-mini vision). Bila tidak ada, input manual saja.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/ai_gemini.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalori Mingguan';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }
$uid = (int)$u['id'];

// Auto-buat tabel (idempotent) — dipisah dari kalori_log (workout) agar tidak bentrok skema.
try {
    db_exec("CREATE TABLE IF NOT EXISTS kalori_target (
        user_id        INT PRIMARY KEY,
        target_harian  INT NOT NULL DEFAULT 2000,
        updated_at     TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE TABLE IF NOT EXISTS kalori_makanan_log (
        id            SERIAL PRIMARY KEY,
        user_id       INT NOT NULL,
        tanggal       DATE NOT NULL DEFAULT CURRENT_DATE,
        waktu         TIME NOT NULL DEFAULT CURRENT_TIME,
        nama_makanan  VARCHAR(200) NOT NULL,
        kalori        INT NOT NULL DEFAULT 0,
        foto_url      TEXT,
        ai_estimasi   BOOLEAN NOT NULL DEFAULT FALSE,
        catatan       TEXT,
        created_at    TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_kalori_mkn_user_tgl ON kalori_makanan_log(user_id, tanggal DESC)");
} catch (Throwable $e) {}

// === Helper AI (Revisi 17 Juni 2026) ===
// Pipeline: Foto makanan → Gemini 2.5 Flash mengenali → Estimasi kalori → Masuk database.
function ai_estimate_kalori($imagePath, $hint=''){
    @set_time_limit(90);
    if (!is_file($imagePath)) return ['err'=>'file gambar tidak ditemukan'];
    $prompt = "Lihat FOTO MAKANAN ini. Sebutkan NAMA MAKANAN singkat (Bahasa Indonesia) dan PERKIRAAN TOTAL KALORI (kcal, INTEGER tanpa pemisah ribuan) untuk porsi yang terlihat. "
            . "Bila ada beberapa item, jumlahkan kalorinya. "
            . "Balas HANYA JSON murni TANPA fence ```json``` dan TANPA kalimat pengantar: "
            . "{\"nama\":\"...\",\"kalori\":<angka_integer>,\"rincian\":\"<opsional 1 kalimat singkat>\"}. "
            . ($hint ? "Petunjuk pengguna: $hint" : "");
    // Revisi 17 Juni 2026 — naikkan max_tokens (sebelumnya 300 → sering kepotong di fence ```)
    $g = gemini_vision($prompt, $imagePath,
            ['json'=>true,'temperature'=>0.2,'max_tokens'=>1024]);
    if (!$g['ok']) return ['err'=>'Gemini: '.$g['err']];
    $obj = gemini_extract_json($g['text']);
    if (is_array($obj) && isset($obj['kalori'])) {
        return ['nama'=>$obj['nama'] ?? '', 'kalori'=>(int)$obj['kalori'], 'rincian'=>$obj['rincian'] ?? ''];
    }
    // Revisi 17 Juni 2026 — fallback regex bila JSON gagal (tangkap "kalori": 123 ATAU 123 kkal)
    $raw = (string)$g['text'];
    $nama = ''; $kal = 0;
    if (preg_match('/"nama"\s*:\s*"([^"]{2,80})"/i', $raw, $nn)) $nama = $nn[1];
    if (preg_match('/"kalori"\s*:\s*"?(\d{2,5})/i', $raw, $kk)) $kal = (int)$kk[1];
    elseif (preg_match('/(\d{2,4})\s*(?:kkal|kcal|kal)/i', $raw, $mm)) $kal = (int)$mm[1];
    if ($kal > 0) return ['nama'=>$nama, 'kalori'=>$kal, 'rincian'=>trim(substr($raw,0,120))];
    return ['err'=>'AI gagal mengurai JSON. Raw: '.substr($raw,0,200)];
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='target') {
        $t = max(500, (int)$_POST['target_harian']);
        db_exec("INSERT INTO kalori_target(user_id,target_harian) VALUES($1,$2)
                 ON CONFLICT (user_id) DO UPDATE SET target_harian=EXCLUDED.target_harian", [$uid,$t]);
        $_SESSION['flash_ok'] = "Target diperbarui: $t kkal/hari.";
    } elseif ($a==='add') {
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $jam = $_POST['waktu'] ?: date('H:i');
        $nama = trim($_POST['nama_makanan'] ?? '');
        $kal  = (int)($_POST['kalori'] ?? 0);
        $cat  = trim($_POST['catatan'] ?? '');
        $foto = null; $aiUsed = false;

        // Handle upload foto
        if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $dir = __DIR__.'/uploads/kalori';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION) ?: 'jpg');
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext='jpg';
            $fn = 'k_'.$uid.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$ext;
            $dst = $dir.'/'.$fn;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dst)) {
                $foto = '/uploads/kalori/'.$fn;
                // AI estimate jika diminta atau kalori kosong
                if (!empty($_POST['use_ai']) || $kal <= 0) {
                    $ai = ai_estimate_kalori($dst, $nama);
                    if (is_array($ai) && isset($ai['kalori'])) {
                        if ($kal <= 0) $kal = (int)$ai['kalori'];
                        if ($nama === '' && !empty($ai['nama'])) $nama = $ai['nama'];
                        $aiUsed = true;
                    } else if (!empty($_POST['use_ai'])) {
                        $_SESSION['flash_err'] = 'AI gagal menebak kalori. '.(is_array($ai) && !empty($ai['err']) ? $ai['err'] : 'Input manual saja.');
                    }
                }
            }
        }
        if ($nama === '') $nama = 'Tanpa nama';
        if ($kal < 0) $kal = 0;
        db_exec("INSERT INTO kalori_makanan_log(user_id,tanggal,waktu,nama_makanan,kalori,foto_url,ai_estimasi,catatan)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
            [$uid,$tgl,$jam,$nama,$kal,$foto,$aiUsed?'t':'f',$cat]);
        if (!isset($_SESSION['flash_err'])) $_SESSION['flash_ok'] = "Makanan ditambahkan ($kal kkal)".($aiUsed?' [AI]':'').".";
    } elseif ($a==='delete') {
        db_exec("DELETE FROM kalori_makanan_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
    } elseif ($a==='edit') {
        // Revisi 13 Juni 2026 — CRUD Riwayat Minggu ini
        $id  = (int)($_POST['id'] ?? 0);
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $jam = $_POST['waktu'] ?: date('H:i');
        $nama= trim($_POST['nama_makanan'] ?? '') ?: 'Tanpa nama';
        $kal = max(0,(int)($_POST['kalori'] ?? 0));
        $cat = trim($_POST['catatan'] ?? '');
        if ($id>0) {
          db_exec("UPDATE kalori_makanan_log
                      SET tanggal=$1, waktu=$2, nama_makanan=$3, kalori=$4, catatan=$5
                    WHERE id=$6 AND user_id=$7",
                  [$tgl,$jam,$nama,$kal,$cat?:null,$id,$uid]);
          $_SESSION['flash_ok'] = "Entri diperbarui.";
        }
    }
    header('Location: kalori_mingguan.php'); exit;
}

// Data
$target = (int)(db_one("SELECT target_harian FROM kalori_target WHERE user_id=$1",[$uid])['target_harian'] ?? 2000);

// Revisi 15 Juni 2026 — dukung riwayat minggu sebelumnya via ?week=offset (0=ini, -1=mgg lalu, dst)
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
if ($weekOffset > 0) $weekOffset = 0; // tidak boleh ke depan
$weekStart = date('Y-m-d', strtotime('monday this week '.($weekOffset).' week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week '.($weekOffset).' week'));

$logs = db_all("SELECT * FROM kalori_makanan_log WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3 ORDER BY tanggal DESC, waktu DESC",
    [$uid,$weekStart,$weekEnd]);
$byDay = db_all("SELECT tanggal::text AS tgl, SUM(kalori)::int AS total
                 FROM kalori_makanan_log WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                 GROUP BY tanggal ORDER BY tanggal",[$uid,$weekStart,$weekEnd]);
$map = [];
foreach($byDay as $r) $map[$r['tgl']] = (int)$r['total'];
$labels=[]; $data=[]; $sisa=[];
for($i=0;$i<7;$i++){
    $d = date('Y-m-d', strtotime($weekStart." +$i day"));
    $labels[] = date('D d/m', strtotime($d));
    $cons = $map[$d] ?? 0;
    $data[] = $cons;
    $sisa[] = max(0, $target - $cons);
}
$totalWeek = array_sum($data);
$avgDay = round($totalWeek/7);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$err= $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$aiEnabled = (bool) (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: ''));

// Daftar 8 minggu terakhir utk dropdown navigasi
$weekChoices = [];
for ($i=0; $i<=8; $i++){
    $s = date('Y-m-d', strtotime('monday this week -'.$i.' week'));
    $e = date('Y-m-d', strtotime('sunday this week -'.$i.' week'));
    $weekChoices[-$i] = ($i===0?'Minggu Ini':($i===1?'Minggu Lalu':$i.' minggu lalu')).' ('.date('d M', strtotime($s)).' – '.date('d M', strtotime($e)).')';
}
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Kalori Mingguan</li>
</ol></nav>

<h2 class="mb-1"><i class="bi bi-egg-fried text-warning"></i> Pencatatan Kalori Mingguan</h2>
<p class="text-muted small mb-2">Minggu <?= htmlspecialchars($weekStart) ?> – <?= htmlspecialchars($weekEnd) ?>. Target harian: <strong><?= $target ?></strong> kkal.
<?= $aiEnabled ? '<span class="badge bg-success ms-2">AI Foto Aktif</span>' : '<span class="badge bg-secondary ms-2">AI Foto Nonaktif</span>' ?></p>

<!-- Revisi 15 Juni 2026 — Navigasi minggu (riwayat minggu lalu, dsb) -->
<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="?week=<?= $weekOffset-1 ?>"><i class="bi bi-chevron-left"></i> Minggu sebelumnya</a>
  <select name="week" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
    <?php foreach($weekChoices as $off=>$lab): ?>
      <option value="<?= $off ?>" <?= $off===$weekOffset?'selected':'' ?>><?= htmlspecialchars($lab) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($weekOffset < 0): ?>
    <a class="btn btn-outline-secondary btn-sm" href="?week=<?= $weekOffset+1 ?>">Minggu berikutnya <i class="bi bi-chevron-right"></i></a>
    <a class="btn btn-primary btn-sm" href="?week=0"><i class="bi bi-arrow-counterclockwise"></i> Kembali ke Minggu Ini</a>
  <?php endif; ?>
</form>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-warning py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-bullseye fs-4 text-primary"></i>
    <div class="fw-bold"><?= $target ?> kkal</div><div class="small text-muted">Target Harian</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-fire fs-4 text-danger"></i>
    <div class="fw-bold"><?= $totalWeek ?> kkal</div><div class="small text-muted">Total Minggu</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-calendar3 fs-4 text-info"></i>
    <div class="fw-bold"><?= $avgDay ?> kkal</div><div class="small text-muted">Rata-rata / hari</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-arrow-down-circle fs-4 text-success"></i>
    <div class="fw-bold"><?= max(0, ($target*7) - $totalWeek) ?> kkal</div><div class="small text-muted">Sisa minggu</div></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-bar-chart-line"></i> Statistik Konsumsi vs Sisa Target</div>
      <div class="card-body"><canvas id="weekChart" height="160"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-plus-circle"></i> Catat Makanan</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="add">
          <div class="col-7"><label class="form-label small">Tanggal</label><input class="form-control form-control-sm" type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-5"><label class="form-label small">Waktu</label><input class="form-control form-control-sm" type="time" name="waktu" value="<?= date('H:i') ?>"></div>
          <div class="col-8"><label class="form-label small">Nama makanan</label><input class="form-control form-control-sm" name="nama_makanan" placeholder="cth: Nasi padang"></div>
          <div class="col-4"><label class="form-label small">Kalori (kkal)</label><input class="form-control form-control-sm" type="number" min="0" name="kalori" placeholder="0"></div>
          <div class="col-12"><label class="form-label small">Foto (opsional, AI dapat menebak kalori)</label>
            <input class="form-control form-control-sm" type="file" name="foto" accept="image/*"></div>
          <?php if($aiEnabled): ?>
          <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="use_ai" id="uai" value="1" checked>
            <label class="form-check-label small" for="uai">Gunakan AI untuk estimasi kalori dari foto</label></div>
          <?php endif; ?>
          <div class="col-12"><label class="form-label small">Catatan</label><input class="form-control form-control-sm" name="catatan"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Simpan</button></div>
        </form>
        <hr>
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="target">
          <div class="col-8"><input class="form-control form-control-sm" type="number" min="500" name="target_harian" value="<?= $target ?>"></div>
          <div class="col-4"><button class="btn btn-outline-secondary btn-sm w-100">Set Target</button></div>
        </form>
        <?php if(!$aiEnabled): ?>
          <div class="alert alert-info small mt-2 mb-0"><i class="bi bi-info-circle"></i> Untuk mengaktifkan AI estimasi kalori dari foto, set environment variable <code>GEMINI_API_KEY</code> di server.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-list-ul"></i> Riwayat <?= $weekOffset===0?'Minggu Ini':($weekOffset===-1?'Minggu Lalu':abs($weekOffset).' Minggu Lalu') ?> (<?= count($logs) ?> entri)</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Tanggal</th><th>Waktu</th><th>Foto</th><th>Makanan</th><th class="text-end">Kalori</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
      <?php foreach($logs as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['tanggal']) ?></td>
          <td><?= htmlspecialchars(substr($r['waktu'],0,5)) ?></td>
          <td><?php if(!empty($r['foto_url'])): ?><img src="<?= htmlspecialchars($r['foto_url']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px"><?php endif; ?></td>
          <td><?= htmlspecialchars($r['nama_makanan']) ?>
            <?php if($r['ai_estimasi']==='t'||$r['ai_estimasi']===true||$r['ai_estimasi']==='1'): ?><span class="badge bg-success-subtle text-success ms-1">AI</span><?php endif; ?>
          </td>
          <td class="text-end fw-semibold text-danger"><?= (int)$r['kalori'] ?></td>
          <td class="small text-muted"><?= htmlspecialchars($r['catatan']??'') ?></td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-kal"
                    data-id="<?= (int)$r['id'] ?>"
                    data-tanggal="<?= htmlspecialchars($r['tanggal']) ?>"
                    data-waktu="<?= htmlspecialchars(substr($r['waktu'],0,5)) ?>"
                    data-nama="<?= htmlspecialchars($r['nama_makanan']) ?>"
                    data-kalori="<?= (int)$r['kalori'] ?>"
                    data-catatan="<?= htmlspecialchars($r['catatan'] ?? '') ?>"
                    title="Edit"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$logs): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada catatan minggu ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Modal Edit Riwayat Kalori (Revisi 13 Juni 2026) -->
<div class="modal fade" id="editKalModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="edit">
  <input type="hidden" name="id" id="ek_id">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Entri Kalori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-7"><label class="form-label small">Tanggal</label><input type="date" name="tanggal" id="ek_tgl" class="form-control form-control-sm" required></div>
    <div class="col-5"><label class="form-label small">Waktu</label><input type="time" name="waktu" id="ek_jam" class="form-control form-control-sm" required></div>
    <div class="col-8"><label class="form-label small">Nama makanan</label><input name="nama_makanan" id="ek_nama" class="form-control form-control-sm" required></div>
    <div class="col-4"><label class="form-label small">Kalori (kkal)</label><input type="number" min="0" name="kalori" id="ek_kal" class="form-control form-control-sm" required></div>
    <div class="col-12"><label class="form-label small">Catatan</label><input name="catatan" id="ek_cat" class="form-control form-control-sm"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<script>
(function(){
  var m = new bootstrap.Modal(document.getElementById('editKalModal'));
  document.querySelectorAll('.btn-edit-kal').forEach(function(b){
    b.addEventListener('click', function(){
      document.getElementById('ek_id').value   = this.dataset.id;
      document.getElementById('ek_tgl').value  = this.dataset.tanggal;
      document.getElementById('ek_jam').value  = this.dataset.waktu;
      document.getElementById('ek_nama').value = this.dataset.nama;
      document.getElementById('ek_kal').value  = this.dataset.kalori;
      document.getElementById('ek_cat').value  = this.dataset.catatan;
      m.show();
    });
  });
})();
</script>

<script>
new Chart(document.getElementById('weekChart'), {
  type:'bar',
  data:{ labels: <?= json_encode($labels) ?>,
    datasets:[
      {label:'Konsumsi (kkal)', data: <?= json_encode($data) ?>, backgroundColor:'#dc3545'},
      {label:'Sisa Target', data: <?= json_encode($sisa) ?>, backgroundColor:'#198754'}
    ]
  },
  options:{ responsive:true, scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}} }
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

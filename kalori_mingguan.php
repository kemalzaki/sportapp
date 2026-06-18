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
$pageSkeleton = 'table';
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
    // Revisi 17 Juni 2026 Part J — kolom file_id untuk hapus dari ImageKit
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS foto_file_id TEXT");
    db_exec("CREATE INDEX IF NOT EXISTS idx_kalori_mkn_user_tgl ON kalori_makanan_log(user_id, tanggal DESC)");
} catch (Throwable $e) {}

// === Helper AI (Revisi 17 Juni 2026 Part J) ===
// Pipeline: Foto + nama teks → Gemini 2.5 Flash mengenali → Estimasi kalori → DB.
// AI membaca KEDUA input (nama makanan yang diketik user + gambar) untuk akurasi maksimal.
function ai_estimate_kalori($imagePath, $namaTeks=''){
    @set_time_limit(90);
    if (!is_file($imagePath)) return ['err'=>'file gambar tidak ditemukan'];
    $hintTxt = trim((string)$namaTeks);
    $prompt = "Anda adalah ahli gizi. Anda menerima DUA input: "
            . "(1) FOTO MAKANAN, dan "
            . "(2) TEKS NAMA MAKANAN yang diketik pengguna: \""
            . ($hintTxt !== '' ? $hintTxt : '(tidak diisi)') . "\". "
            . "Tugas: BACA KEDUANYA. Jika teks nama diisi, gunakan sebagai petunjuk utama dan verifikasi dengan foto. "
            . "Jika teks nama kosong, tebak dari foto saja. Jika foto dan teks berbeda, prioritaskan foto namun sebut keduanya di 'rincian'. "
            . "Hitung PERKIRAAN TOTAL KALORI (kcal, INTEGER) untuk porsi yang terlihat. Jumlahkan bila ada beberapa item. "
            . "Balas HANYA JSON murni TANPA fence ```json``` dan TANPA kalimat pengantar: "
            . "{\"nama\":\"...\",\"kalori\":<angka_integer>,\"rincian\":\"<opsional 1 kalimat singkat>\"}.";
    $g = gemini_vision($prompt, $imagePath,
            ['json'=>true,'temperature'=>0.2,'max_tokens'=>1024]);
    if (!$g['ok']) return ['err'=>'Gemini: '.$g['err']];
    $obj = gemini_extract_json($g['text']);
    if (is_array($obj) && isset($obj['kalori'])) {
        return ['nama'=>$obj['nama'] ?? '', 'kalori'=>(int)$obj['kalori'], 'rincian'=>$obj['rincian'] ?? ''];
    }
    // Fallback regex bila JSON gagal
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
        $foto = null; $fotoFileId = null; $aiUsed = false;
        $errs = []; $warns = [];

        // === Revisi 18 Juni 2026 — perbaiki upload kamera + AI kalori ===
        // Bug sebelumnya:
        //  (a) Foto dari kamera HP sering >5 MB, melebihi php upload_max_filesize default → tmp_name kosong,
        //      tapi error UPLOAD_ERR_INI_SIZE tidak ditampilkan ke user.
        //  (b) Jika AI gagal (quota habis) dan user mengandalkan AI, kalori tetap 0 dan user bingung.
        //  (c) Pesan error ImageKit tidak rinci → user tidak tahu kenapa foto tidak masuk.
        $hasFile = isset($_FILES['foto']) && (!empty($_FILES['foto']['name']) || !empty($_FILES['foto']['tmp_name']));
        if ($hasFile) {
            $upErr = (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($upErr !== UPLOAD_ERR_OK) {
                $upErrMap = [
                    UPLOAD_ERR_INI_SIZE=>'Ukuran foto melebihi limit server (upload_max_filesize). Coba foto resolusi lebih kecil.',
                    UPLOAD_ERR_FORM_SIZE=>'Ukuran foto melebihi limit form (MAX_FILE_SIZE).',
                    UPLOAD_ERR_PARTIAL=>'Foto hanya terupload sebagian. Coba lagi.',
                    UPLOAD_ERR_NO_FILE=>'Tidak ada file foto.',
                    UPLOAD_ERR_NO_TMP_DIR=>'Server: tmp dir tidak ada.',
                    UPLOAD_ERR_CANT_WRITE=>'Server: gagal menulis ke disk.',
                    UPLOAD_ERR_EXTENSION=>'Upload diblokir ekstensi PHP.',
                ];
                $errs[] = 'Upload gagal: '.($upErrMap[$upErr] ?? ('kode '.$upErr));
            } elseif (!is_uploaded_file($_FILES['foto']['tmp_name'])) {
                $errs[] = 'Upload tidak valid (is_uploaded_file=false).';
            } else {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION) ?: 'jpg');
                if (!in_array($ext, ['jpg','jpeg','png','webp','heic','heif'])) $ext='jpg';
                $tmpDst = sys_get_temp_dir().'/k_'.$uid.'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $tmpDst)) {
                    $errs[] = 'Gagal memindahkan file upload ke folder tmp server.';
                } else {
                    // === AI dulu (file lokal masih ada) ===
                    if (!empty($_POST['use_ai']) || $kal <= 0) {
                        $ai = ai_estimate_kalori($tmpDst, $nama);
                        if (is_array($ai) && isset($ai['kalori'])) {
                            if ($kal <= 0) $kal = (int)$ai['kalori'];
                            // Revisi 18 Juni 2026 — SELALU prioritaskan nama hasil scan AI bila tersedia,
                            // supaya user benar-benar tahu "ini makanan apa" dari foto.
                            if (!empty($ai['nama'])) {
                                $nama = ($nama === '' || strtolower($nama)==='tanpa nama')
                                        ? $ai['nama']
                                        : $nama.' ('.$ai['nama'].')';
                            }
                            // Simpan rincian AI ke catatan bila kosong supaya tetap terlihat di tabel.
                            if ($cat === '' && !empty($ai['rincian'])) $cat = 'AI: '.$ai['rincian'];
                            $aiUsed = true;
                        } else {
                            $errMsg = (is_array($ai) && !empty($ai['err'])) ? $ai['err'] : 'AI tidak menjawab.';
                            if (stripos($errMsg,'quota')!==false || stripos($errMsg,'exceeded')!==false || stripos($errMsg,'rate limit')!==false) {
                                $errMsg = 'Kuota AI Gemini habis (free tier). Coba lagi 1 menit, atau set GEMINI_API_KEYS=key1,key2,key3. Detail: '.mb_substr($errMsg,0,180);
                            }
                            $warns[] = 'AI gagal menebak kalori — '.$errMsg.' Foto tetap diupload, silakan edit angka kalori manual.';
                        }
                    }
                    // === Upload ke ImageKit (terpisah dari status AI) ===
                    try {
                        require_once __DIR__.'/config/imagekit.php';
                        global $imageKit;
                        $bin = @file_get_contents($tmpDst);
                        if ($bin === false || strlen($bin) === 0) {
                            $errs[] = 'File foto kosong setelah dipindah.';
                        } else {
                            $safeNama = preg_replace('/[^a-z0-9]/i','_', $nama ?: 'makanan');
                            $fileName = 'kalori-'.$uid.'-'.$tgl.'-'.$safeNama.'-'.time().'.'.$ext;
                            $up = $imageKit->uploadFile([
                                'file'     => base64_encode($bin),
                                'fileName' => $fileName,
                                'folder'   => '/sportapp/kalori/'.date('F_Y', strtotime($tgl))
                            ]);
                            if (!empty($up->error)) {
                                $emsg = is_object($up->error) ? json_encode($up->error) : (string)$up->error;
                                $errs[] = 'ImageKit menolak upload: '.mb_substr($emsg, 0, 250);
                            } elseif (!empty($up->result) && !empty($up->result->url)) {
                                $foto       = $up->result->url;
                                $fotoFileId = $up->result->fileId ?? null;
                            } else {
                                $errs[] = 'ImageKit tidak mengembalikan URL. Cek API key di config/imagekit.php.';
                            }
                        }
                    } catch (Throwable $e) {
                        $errs[] = 'ImageKit exception: '.$e->getMessage();
                    }
                    @unlink($tmpDst);
                }
            }
        }
        if ($nama === '') $nama = 'Tanpa nama';
        if ($kal < 0) $kal = 0;
        // SELALU simpan entri agar data tidak hilang — meski foto gagal / AI gagal.
        db_exec("INSERT INTO kalori_makanan_log(user_id,tanggal,waktu,nama_makanan,kalori,foto_url,foto_file_id,ai_estimasi,catatan)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)",
            [$uid,$tgl,$jam,$nama,$kal,$foto,$fotoFileId,$aiUsed?'t':'f',$cat]);
        if ($errs)  $_SESSION['flash_err'] = implode(' • ', $errs);
        if ($warns) $_SESSION['flash_err'] = ($_SESSION['flash_err'] ?? '') . ' ' . implode(' • ', $warns);
        $okMsg = "Makanan ditambahkan ($kal kkal)".($aiUsed?' [AI]':'').($foto?' · foto OK':'');
        $_SESSION['flash_ok'] = $okMsg;
    } elseif ($a==='delete') {
        // Hapus juga dari ImageKit jika ada file_id
        $row = db_one("SELECT foto_file_id FROM kalori_makanan_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
        if ($row && !empty($row['foto_file_id'])) {
            try { require_once __DIR__.'/config/imagekit.php'; global $imageKit; $imageKit->deleteFile($row['foto_file_id']); } catch (Throwable $e) {}
        }
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

// Revisi 18 Juni 2026 — Kalori TERBAKAR dari olahraga (tabel kalori_log) untuk menghitung defisit/surplus.
$burnMap = []; $burnDetail = [];
try {
    $burnRows = db_all(
        "SELECT (dibuat_pada::date)::text AS tgl, SUM(kalori)::int AS total
           FROM kalori_log
          WHERE user_id=$1 AND dibuat_pada::date BETWEEN $2 AND $3
       GROUP BY dibuat_pada::date ORDER BY 1",
        [$uid,$weekStart,$weekEnd]
    );
    foreach ($burnRows as $r) $burnMap[$r['tgl']] = (int)$r['total'];
    // Detail per-jenis untuk ditampilkan
    $burnDetail = db_all(
        "SELECT jenis, SUM(menit)::int AS menit, SUM(kalori)::int AS kalori, COUNT(*)::int AS sesi
           FROM kalori_log
          WHERE user_id=$1 AND dibuat_pada::date BETWEEN $2 AND $3
       GROUP BY jenis ORDER BY kalori DESC",
        [$uid,$weekStart,$weekEnd]
    );
} catch (Throwable $e) { /* tabel kalori_log belum ada → diabaikan */ }

$labels=[]; $data=[]; $sisa=[]; $burnData=[]; $netData=[]; $deficitData=[];
for($i=0;$i<7;$i++){
    $d = date('Y-m-d', strtotime($weekStart." +$i day"));
    $labels[] = date('D d/m', strtotime($d));
    $cons = $map[$d] ?? 0;
    $burn = $burnMap[$d] ?? 0;
    $data[] = $cons;
    $burnData[] = $burn;
    $net  = $cons - $burn;          // Net asupan setelah dikurangi olahraga
    $netData[] = $net;
    $sisa[] = max(0, $target - $cons);
    // Defisit harian = target - net. Positif = defisit (penurunan BB), Negatif = surplus.
    $deficitData[] = $target - $net;
}
$totalWeek    = array_sum($data);
$totalBurn    = array_sum($burnData);
$totalNet     = $totalWeek - $totalBurn;
$totalTarget  = $target * 7;
$weekDeficit  = $totalTarget - $totalNet;   // >0 = defisit (bagus utk diet); <0 = surplus
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
    <div class="fw-bold"><?= $totalWeek ?> kkal</div><div class="small text-muted">Total Konsumsi</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-lightning-charge-fill fs-4 text-warning"></i>
    <div class="fw-bold"><?= $totalBurn ?> kkal</div><div class="small text-muted">Terbakar (olahraga)</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-calendar3 fs-4 text-info"></i>
    <div class="fw-bold"><?= $avgDay ?> kkal</div><div class="small text-muted">Rata-rata / hari</div></div></div></div>
</div>

<!-- Revisi 18 Juni 2026 — Defisit / Surplus mingguan (memperhitungkan olahraga) -->
<div class="row g-2 mb-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100 <?= $weekDeficit>=0 ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-3">
          <i class="bi <?= $weekDeficit>=0 ? 'bi-arrow-down-circle-fill text-success' : 'bi-arrow-up-circle-fill text-danger' ?> fs-1"></i>
          <div class="flex-fill">
            <div class="small text-muted"><?= $weekDeficit>=0 ? 'Defisit Kalori Minggu Ini' : 'Surplus Kalori Minggu Ini' ?></div>
            <div class="fs-3 fw-bold <?= $weekDeficit>=0 ? 'text-success' : 'text-danger' ?>">
              <?= ($weekDeficit>=0?'−':'+') ?><?= number_format(abs($weekDeficit)) ?> kkal
            </div>
            <div class="small text-muted">
              Konsumsi <strong><?= number_format($totalWeek) ?></strong>
              − Terbakar <strong><?= number_format($totalBurn) ?></strong>
              = Net <strong><?= number_format($totalNet) ?></strong> kkal
              <br>Target mingguan: <strong><?= number_format($totalTarget) ?></strong> kkal
              <?php if ($weekDeficit>=0): ?>
                · <span class="text-success">cocok untuk penurunan berat badan (~<?= number_format($weekDeficit/7700, 2) ?> kg)</span>
              <?php else: ?>
                · <span class="text-danger">cenderung penambahan berat badan</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-activity"></i> Aktivitas Pembakaran Minggu Ini</div>
      <div class="card-body p-2">
        <?php if (empty($burnDetail)): ?>
          <div class="small text-muted py-2">Belum ada catatan olahraga minggu ini. Catat di
            <a href="/kalori_badminton.php">Badminton</a>, <a href="/kalori_futsal.php">Futsal</a>,
            <a href="/kalori_pingpong.php">Pingpong</a>, <a href="/kalori_renang.php">Renang</a>.</div>
        <?php else: ?>
          <table class="table table-sm mb-0 small">
            <thead class="table-light"><tr><th>Jenis</th><th class="text-end">Sesi</th><th class="text-end">Menit</th><th class="text-end">Kkal</th></tr></thead>
            <tbody>
            <?php foreach($burnDetail as $b): ?>
              <tr><td class="text-capitalize"><?= htmlspecialchars($b['jenis']) ?></td>
                  <td class="text-end"><?= (int)$b['sesi'] ?></td>
                  <td class="text-end"><?= (int)$b['menit'] ?></td>
                  <td class="text-end fw-semibold text-warning"><?= (int)$b['kalori'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
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
          <div class="col-12"><label class="form-label small">Foto makanan (opsional, AI dapat menebak kalori)</label>
            <!-- Revisi 18 Juni 2026: capture="environment" → di mobile langsung buka kamera belakang.
                 Tombol "Pilih dari Galeri" sebagai fallback agar tetap fleksibel.
                 Auto-resize sisi terpanjang → 1280 px sebelum upload supaya tidak ditolak server
                 (upload_max_filesize default 2 MB di banyak hosting). -->
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
            <div class="d-flex gap-2 flex-wrap align-items-start">
              <label class="btn btn-primary btn-sm mb-0 flex-fill">
                <i class="bi bi-camera-fill"></i> Buka Kamera Langsung
                <input class="d-none" type="file" name="foto" accept="image/*" capture="environment" id="kmFoto" onchange="kmFotoPreview(this)">
              </label>
              <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="kmPickGallery()">
                <i class="bi bi-image"></i> Pilih dari Galeri
              </button>
            </div>
            <div id="kmFotoPrev" class="small text-muted mt-2">Belum ada foto dipilih.</div>
            <div class="form-text small">Foto akan otomatis dikecilkan ke max 1280 px agar tidak ditolak limit upload server.</div>
          </div>
          <script>
          // Tombol Galeri: hapus atribut capture sementara → buka picker file biasa.
          function kmPickGallery(){
            var inp = document.getElementById('kmFoto');
            if (!inp) return;
            inp.removeAttribute('capture');
            inp.click();
            // Pasang kembali capture untuk klik berikut (kamera).
            setTimeout(function(){ inp.setAttribute('capture','environment'); }, 1000);
          }
          function kmFotoPreview(inp){
            var box = document.getElementById('kmFotoPrev');
            if (!(inp.files && inp.files[0])){ box.textContent = 'Belum ada foto dipilih.'; return; }
            var f = inp.files[0];
            // Revisi 18 Juni 2026: auto-resize sisi terpanjang → 1280 px sebelum upload
            // supaya tidak ditolak upload_max_filesize / post_max_size.
            var MAX = 1280;
            var img = new Image();
            img.onload = function(){
              var w=img.width, h=img.height, scale=Math.min(1, MAX/Math.max(w,h));
              if (scale >= 1 && f.size < 4*1024*1024){
                box.innerHTML = '<img src="'+img.src+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+f.name+' ('+(Math.round(f.size/1024))+' KB)</span>';
                return;
              }
              var c=document.createElement('canvas'); c.width=Math.round(w*scale); c.height=Math.round(h*scale);
              c.getContext('2d').drawImage(img,0,0,c.width,c.height);
              c.toBlob(function(blob){
                if(!blob){ return; }
                var nf = new File([blob], (f.name||'foto.jpg').replace(/\.(heic|heif|png|webp)$/i,'.jpg'), {type:'image/jpeg'});
                var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
                var url = URL.createObjectURL(nf);
                box.innerHTML = '<img src="'+url+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+nf.name+' ('+(Math.round(nf.size/1024))+' KB · dikompres dari '+(Math.round(f.size/1024))+' KB)</span>';
              }, 'image/jpeg', 0.85);
            };
            img.onerror = function(){
              box.innerHTML = '<img src="'+URL.createObjectURL(f)+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+f.name+'</span>';
            };
            img.src = URL.createObjectURL(f);
          }
          </script>
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
          <td><?php if(!empty($r['foto_url'])): ?>
            <img src="<?= htmlspecialchars($r['foto_url']) ?>"
                 class="km-foto-thumb"
                 data-full="<?= htmlspecialchars($r['foto_url']) ?>"
                 data-nama="<?= htmlspecialchars($r['nama_makanan']) ?>"
                 alt="Foto <?= htmlspecialchars($r['nama_makanan']) ?>"
                 style="width:50px;height:50px;object-fit:cover;border-radius:6px;cursor:zoom-in"
                 title="Klik untuk perbesar">
          <?php endif; ?></td>
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
// Revisi 18 Juni 2026 — Wrap dgn window.load supaya bootstrap.bundle.js (di footer.php)
// SUDAH siap saat new bootstrap.Modal() dipanggil. Sebelumnya ReferenceError → tombol Edit & klik foto tidak bereaksi.
function kmInitEditAndZoom(){
  if (typeof bootstrap === 'undefined' || !bootstrap.Modal){ setTimeout(kmInitEditAndZoom, 120); return; }
  var editEl = document.getElementById('editKalModal');
  if (editEl){
    var m = new bootstrap.Modal(editEl);
    document.querySelectorAll('.btn-edit-kal').forEach(function(b){
      b.addEventListener('click', function(){
        document.getElementById('ek_id').value   = this.dataset.id;
        document.getElementById('ek_tgl').value  = this.dataset.tanggal;
        document.getElementById('ek_jam').value  = this.dataset.waktu;
        document.getElementById('ek_nama').value = this.dataset.nama;
        document.getElementById('ek_kal').value  = this.dataset.kalori;
        document.getElementById('ek_cat').value  = this.dataset.catatan || '';
        m.show();
      });
    });
  }
  var zEl = document.getElementById('zoomFotoModal');
  if (zEl){
    var zm = new bootstrap.Modal(zEl);
    var imgEl = document.getElementById('zoomFotoImg');
    var ttlEl = document.getElementById('zoomFotoTitle');
    document.querySelectorAll('.km-foto-thumb').forEach(function(t){
      t.addEventListener('click', function(){
        imgEl.src = this.dataset.full || this.src;
        ttlEl.textContent = this.dataset.nama || 'Foto Makanan';
        zm.show();
      });
    });
  }
}
if (document.readyState === 'complete') kmInitEditAndZoom();
else window.addEventListener('load', kmInitEditAndZoom);
</script>

<script>
// Revisi 18 Juni 2026 — Chart kini menampilkan Konsumsi vs Terbakar vs Defisit harian.
window.addEventListener('load', function(){
  if (typeof Chart === 'undefined') return;
  var ctx = document.getElementById('weekChart'); if (!ctx) return;
  new Chart(ctx, {
    type:'bar',
    data:{ labels: <?= json_encode($labels) ?>,
      datasets:[
        {label:'Konsumsi (kkal)', data: <?= json_encode($data) ?>, backgroundColor:'#dc3545', stack:'a'},
        {label:'Terbakar olahraga (kkal)', data: <?= json_encode($burnData) ?>, backgroundColor:'#f59e0b', stack:'b'},
        {label:'Defisit (+) / Surplus (−) harian', data: <?= json_encode($deficitData) ?>, type:'line',
          borderColor:'#16a34a', backgroundColor:'#16a34a', tension:0.3, yAxisID:'y1'}
      ]
    },
    options:{ responsive:true,
      scales:{
        x:{stacked:false},
        y:{beginAtZero:true, title:{display:true,text:'kkal'}},
        y1:{beginAtZero:false, position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'Defisit / Surplus'}}
      },
      plugins:{ tooltip:{ callbacks:{ label:function(c){
        if (c.dataset.label.indexOf('Defisit')>=0){
          var v=c.parsed.y; return c.dataset.label+': '+(v>=0?'−':'+')+Math.abs(v)+' kkal';
        }
        return c.dataset.label+': '+c.parsed.y+' kkal';
      }}}}
    }
  });
});
</script>

<!-- Revisi 17 Juni 2026 Part J — Modal Zoom Foto Makanan -->
<div class="modal fade" id="zoomFotoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-zoom-in"></i> <span id="zoomFotoTitle">Foto Makanan</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-2" style="background:#0f172a">
        <img id="zoomFotoImg" src="" alt="" style="max-width:100%;max-height:80vh;border-radius:8px">
      </div>
    </div>
  </div>
</div>
<!-- Inisialisasi modal zoom dilakukan oleh kmInitEditAndZoom() di atas (load-safe). -->
<?php include __DIR__.'/includes/footer.php'; ?>

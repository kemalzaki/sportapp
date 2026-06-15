<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Challenge Islami';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'log') {
        $key = preg_replace('/[^a-z_]/','', $_POST['key'] ?? '');
        // izinkan semua kunci yang terdaftar di challenge_master
        $allowed = array_column(db_all("SELECT kunci FROM challenge_master WHERE aktif=1"), 'kunci');
        if (in_array($key, $allowed, true)) {
            islami_log_challenge((int)$u['id'], $key, $_POST['catatan'] ?? null);
            $touchMap = ['ayat_harian'=>'quran_done','subuh_walk'=>'subuh_walk','dzikir_pagi'=>'dzikir_pagi','dzikir_petang'=>'dzikir_petang'];
            if (isset($touchMap[$key])) islami_touch_streak((int)$u['id'], $touchMap[$key]);
        }
        $_SESSION['flash'] = 'Challenge tercatat. Barakallah!';
    }
    header('Location: /challenge.php'); exit;
}

$myLogs = $u ? db_all("SELECT challenge_key, COUNT(*) AS n, MAX(tanggal) AS last
                       FROM challenge_log WHERE user_id=$1 GROUP BY challenge_key", [(int)$u['id']]) : [];
$counts = [];
foreach ($myLogs as $r) $counts[$r['challenge_key']] = $r;

// Ambil daftar challenge dari DB (CRUD via admin), fallback ke daftar bawaan
$challenges = [];
$dbRows = db_all("SELECT kunci,judul,deskripsi,icon,warna FROM challenge_master WHERE aktif=1 ORDER BY id");
foreach ($dbRows as $r) {
    $challenges[] = [$r['kunci'], $r['judul'], $r['deskripsi'], $r['icon'] ?: 'bi-trophy', $r['warna'] ?: 'success'];
}
if (!$challenges) {
    $challenges = [
      ['ayat_harian','1 Hari 1 Ayat','Baca minimal 1 ayat Al-Qur\'an setiap hari.','bi-book','success'],
      ['subuh_walk','Subuh Walk Challenge','Jalan kaki ≥10 menit setelah sholat Subuh.','bi-sunrise','warning'],
      ['puasa_seninkamis','Puasa Senin-Kamis','Catat puasa sunnah Senin/Kamis hari ini.','bi-droplet-half','info'],
      ['dzikir_pagi','Dzikir Pagi','Selesaikan rangkaian dzikir pagi.','bi-brightness-high','primary'],
      ['dzikir_petang','Dzikir Petang','Selesaikan rangkaian dzikir petang.','bi-moon-stars','dark'],
    ];
}
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Challenge');
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<h4 class="mb-3"><i class="bi bi-trophy text-warning"></i> Challenge Islami</h4>
<div class="row g-3">
<?php
// === Validasi waktu puasa: disable tombol "catat hari ini" jika belum waktunya ===
function puasa_schedule_info(string $key): array {
    // weekday: 1=Sen ... 7=Min (ISO)
    $w = (int)date('N');
    // Hijri day (mendekati) untuk Ayyamul Bidh / Asyura / Arafah
    $h = function_exists('masehi_ke_hijriyah') ? masehi_ke_hijriyah() : ['hari'=>0,'bulan'=>0];
    $hd = (int)($h['hari'] ?? 0); $hm = (int)($h['bulan'] ?? 0);
    switch ($key) {
        case 'puasa_seninkamis':
            return [in_array($w,[1,4],true), 'Puasa Senin/Kamis hanya bisa dicatat hari Senin atau Kamis.'];
        case 'puasa_ayyamul_bidh':
            return [in_array($hd,[13,14,15],true), 'Ayyamul Bidh hanya tanggal 13/14/15 Hijriyah.'];
        case 'puasa_arafah':
            return [($hm===12 && $hd===9), 'Puasa Arafah hanya 9 Dzulhijjah.'];
        case 'puasa_asyura':
            return [($hm===1 && in_array($hd,[9,10],true)), 'Puasa Asyura hanya 9–10 Muharram.'];
        case 'puasa_syawal':
            return [($hm===10 && $hd>=2 && $hd<=30), 'Puasa Syawal hanya di bulan Syawal (selain 1 Syawal).'];
        case 'puasa_ramadhan':
            return [($hm===9), 'Puasa Ramadhan hanya di bulan Ramadhan.'];
    }
    // Default: jika diawali "puasa_" tapi tidak dikenal, tetap aktif
    return [true, ''];
}
foreach ($challenges as $c):
  $info = $counts[$c[0]] ?? null;
  [$bisaCatat, $puasaInfo] = (strpos($c[0],'puasa_')===0) ? puasa_schedule_info($c[0]) : [true,''];
?>
  <div class="col-md-6 col-lg-4"><div class="card h-100 border-<?= $c[4] ?>"><div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-2"><i class="bi <?= $c[3] ?> fs-3 text-<?= $c[4] ?>"></i>
      <h5 class="m-0"><?= htmlspecialchars($c[1]) ?></h5></div>
    <div class="small text-muted mb-2"><?= htmlspecialchars($c[2]) ?></div>
    <div class="small">Total: <strong><?= $info ? (int)$info['n'] : 0 ?>×</strong>
      <?php if($info): ?> · terakhir <?= htmlspecialchars($info['last']) ?><?php endif; ?></div>
    <?php if (!$bisaCatat): ?>
      <div class="alert alert-warning py-1 px-2 small mt-2 mb-0"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($puasaInfo) ?></div>
    <?php endif; ?>
    <?php if ($u): ?>
    <form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="log"><input type="hidden" name="key" value="<?= $c[0] ?>">
      <button class="btn btn-<?= $c[4] ?> btn-sm w-100" <?= $bisaCatat ? '' : 'disabled title="Belum waktunya"' ?>>
        <i class="bi bi-check2-circle"></i> <?= $bisaCatat ? 'Catat hari ini' : 'Belum waktunya' ?>
      </button>
    </form>
    <?php endif; ?>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php htmx_layout_end(); ?>

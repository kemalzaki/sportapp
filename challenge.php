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
        if (in_array($key, ['ayat_harian','subuh_walk','puasa_seninkamis','dzikir_pagi','dzikir_petang'], true)) {
            islami_log_challenge((int)$u['id'], $key, $_POST['catatan'] ?? null);
            if ($key === 'ayat_harian') islami_touch_streak((int)$u['id'], 'quran_done');
            if ($key === 'subuh_walk')  islami_touch_streak((int)$u['id'], 'subuh_walk');
            if ($key === 'dzikir_pagi') islami_touch_streak((int)$u['id'], 'dzikir_pagi');
            if ($key === 'dzikir_petang') islami_touch_streak((int)$u['id'], 'dzikir_petang');
        }
        $_SESSION['flash'] = 'Challenge tercatat. Barakallah!';
    }
    header('Location: /challenge.php'); exit;
}

$myLogs = $u ? db_all("SELECT challenge_key, COUNT(*) AS n, MAX(tanggal) AS last
                       FROM challenge_log WHERE user_id=$1 GROUP BY challenge_key", [(int)$u['id']]) : [];
$counts = [];
foreach ($myLogs as $r) $counts[$r['challenge_key']] = $r;

$challenges = [
  ['ayat_harian','1 Hari 1 Ayat','Baca minimal 1 ayat Al-Qur\'an setiap hari.','bi-book','success'],
  ['subuh_walk','Subuh Walk Challenge','Jalan kaki ≥10 menit setelah sholat Subuh.','bi-sunrise','warning'],
  ['puasa_seninkamis','Puasa Senin-Kamis','Catat puasa sunnah Senin/Kamis hari ini.','bi-droplet-half','info'],
  ['dzikir_pagi','Dzikir Pagi','Selesaikan rangkaian dzikir pagi.','bi-brightness-high','primary'],
  ['dzikir_petang','Dzikir Petang','Selesaikan rangkaian dzikir petang.','bi-moon-stars','dark'],
];
include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<h4 class="mb-3"><i class="bi bi-trophy text-warning"></i> Challenge Islami</h4>
<div class="row g-3">
<?php foreach ($challenges as $c):
  $info = $counts[$c[0]] ?? null; ?>
  <div class="col-md-6 col-lg-4"><div class="card h-100 border-<?= $c[4] ?>"><div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-2"><i class="bi <?= $c[3] ?> fs-3 text-<?= $c[4] ?>"></i>
      <h5 class="m-0"><?= htmlspecialchars($c[1]) ?></h5></div>
    <div class="small text-muted mb-2"><?= htmlspecialchars($c[2]) ?></div>
    <div class="small">Total: <strong><?= $info ? (int)$info['n'] : 0 ?>×</strong>
      <?php if($info): ?> · terakhir <?= htmlspecialchars($info['last']) ?><?php endif; ?></div>
    <?php if ($u): ?>
    <form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="log"><input type="hidden" name="key" value="<?= $c[0] ?>">
      <button class="btn btn-<?= $c[4] ?> btn-sm w-100"><i class="bi bi-check2-circle"></i> Catat hari ini</button>
    </form>
    <?php endif; ?>
  </div></div></div>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>

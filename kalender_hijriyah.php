<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers();
$pageTitle = 'Kalender Hijriyah';
include __DIR__.'/includes/header.php';

$today = new DateTime('today');
$year  = (int)($_GET['y'] ?? $today->format('Y'));
$month = max(1, min(12, (int)($_GET['m'] ?? $today->format('n'))));
$first = new DateTime("$year-$month-01");
$dim = (int)$first->format('t');
$startDow = (int)$first->format('w'); // 0=Sun

$ramadhan = hijri_event_to_gregorian(9, 1);
$iedAdha  = hijri_event_to_gregorian(12, 10);

// Senin-Kamis dalam bulan ini sebagai jadwal puasa sunnah
$puasaSet = [];
for ($d=1; $d<=$dim; $d++) {
    $dt = new DateTime("$year-$month-$d");
    $w = (int)$dt->format('N');
    if ($w === 1 || $w === 4) $puasaSet[$d] = $w === 1 ? 'Senin' : 'Kamis';
}
$nextSK = next_puasa_seninkamis();
?>
<h4 class="mb-3"><i class="bi bi-calendar3 text-success"></i> Kalender Hijriyah & Puasa Sunnah</h4>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body">
    <div class="small text-muted">Countdown Ramadhan</div>
    <div class="fw-bold"><?= $ramadhan->format('d M Y') ?></div>
    <div id="cdR">…</div>
  </div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body">
    <div class="small text-muted">Countdown Idul Adha</div>
    <div class="fw-bold"><?= $iedAdha->format('d M Y') ?></div>
    <div id="cdI">…</div>
  </div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body">
    <div class="small text-muted">Puasa Sunnah Senin/Kamis berikutnya</div>
    <div class="fw-bold"><?= $nextSK->format('l, d M Y') ?></div>
    <div class="small text-success"><i class="bi bi-bell"></i> Reminder otomatis di dashboard</div>
  </div></div></div>
</div>

<div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
  <div>
    <a href="?y=<?= $month==1?$year-1:$year ?>&m=<?= $month==1?12:$month-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <strong><?= $first->format('F Y') ?></strong>
    <a href="?y=<?= $month==12?$year+1:$year ?>&m=<?= $month==12?1:$month+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>
<div class="card-body table-responsive">
<table class="table table-bordered text-center align-middle">
  <thead><tr><th>Min</th><th>Sen</th><th>Sel</th><th>Rab</th><th>Kam</th><th>Jum</th><th>Sab</th></tr></thead>
  <tbody>
  <?php $cell=0; echo '<tr>'; for($i=0;$i<$startDow;$i++){echo '<td></td>';$cell++;}
  for ($d=1; $d<=$dim; $d++):
    $dt = new DateTime("$year-$month-$d");
    $h  = masehi_ke_hijriyah($dt);
    $isToday = $dt->format('Y-m-d') === $today->format('Y-m-d');
    $puasa = $puasaSet[$d] ?? null;
  ?>
    <td class="<?= $isToday?'bg-success-subtle':'' ?>" style="min-width:80px">
      <div class="fw-bold"><?= $d ?></div>
      <div class="small text-muted"><?= $h['hari'] ?> <?= htmlspecialchars(substr(hijriyah_nama_bulan($h['bulan']),0,3)) ?></div>
      <?php if($puasa): ?><div class="small badge bg-warning-subtle text-warning">Puasa <?= $puasa ?></div><?php endif; ?>
    </td>
  <?php $cell++; if($cell%7===0 && $d<$dim) echo '</tr><tr>'; endfor;
  while($cell%7!==0){echo '<td></td>';$cell++;} echo '</tr>'; ?>
  </tbody>
</table>
</div></div>

<script src="/assets/js/islami.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (window.islamiCountdown) {
    window.islamiCountdown('cdR', '<?= $ramadhan->format('Y-m-d') ?>T00:00:00');
    window.islamiCountdown('cdI', '<?= $iedAdha->format('Y-m-d') ?>T00:00:00');
  }
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

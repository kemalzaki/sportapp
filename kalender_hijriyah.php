<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Kalender Hijriyah & Puasa Sunnah';
include __DIR__.'/includes/header.php';

$today = new DateTime('today');
$year  = (int)($_GET['y'] ?? $today->format('Y'));
$month = max(1, min(12, (int)($_GET['m'] ?? $today->format('n'))));
$first = new DateTime("$year-$month-01");
$dim = (int)$first->format('t');
$startDow = (int)$first->format('w'); // 0=Sun

$ramadhan = hijri_event_to_gregorian(9, 1);
$iedAdha  = hijri_event_to_gregorian(12, 10);
$iedFitri = hijri_event_to_gregorian(10, 1);
$tahunBaru= hijri_event_to_gregorian(1, 1);
$asyura   = hijri_event_to_gregorian(1, 10);
$tasua    = hijri_event_to_gregorian(1, 9);
$arafah   = hijri_event_to_gregorian(12, 9);
$isra     = hijri_event_to_gregorian(7, 27);
$nisfu    = hijri_event_to_gregorian(8, 15);
$maulid   = hijri_event_to_gregorian(3, 12);

// Cari Ayyamul Bidh berikutnya (13,14,15 Hijriyah)
function next_ayyamul_bidh(): DateTime {
    $d = new DateTime('today');
    for ($i=0;$i<60;$i++) {
        $h = masehi_ke_hijriyah($d);
        if (in_array($h['hari'], [13,14,15], true)) return $d;
        $d->modify('+1 day');
    }
    return new DateTime('today');
}
function next_puasa_daud(): DateTime {
    // Puasa Daud = sehari puasa, sehari tidak. Kita anggap "besok" sebagai opsi terdekat.
    return (new DateTime('today'))->modify('+1 day');
}
function next_puasa_syawal(): ?DateTime {
    // 6 hari di bulan Syawal (bulan 10 Hijriyah), idealnya 2-7 Syawal
    $d = new DateTime('today');
    for ($i=0;$i<400;$i++) {
        $h = masehi_ke_hijriyah($d);
        if ($h['bulan']===10 && $h['hari']>=2 && $h['hari']<=7) return $d;
        $d->modify('+1 day');
    }
    return null;
}

$nextSK   = next_puasa_seninkamis();
$nextAB   = next_ayyamul_bidh();
$nextDaud = next_puasa_daud();
$nextSyawal = next_puasa_syawal();

// Susun jenis-jenis puasa sunnah untuk kalender bulan ini
$puasaSet = [];
for ($d=1; $d<=$dim; $d++) {
    $dt = new DateTime("$year-$month-$d");
    $w = (int)$dt->format('N');
    $h = masehi_ke_hijriyah($dt);
    $tags = [];
    if ($w === 1) $tags[] = 'Senin';
    if ($w === 4) $tags[] = 'Kamis';
    if (in_array($h['hari'], [13,14,15], true)) $tags[] = 'Ayyamul Bidh';
    if ($h['bulan']===1 && $h['hari']===9)  $tags[] = 'Tasu\'a';
    if ($h['bulan']===1 && $h['hari']===10) $tags[] = 'Asyura';
    if ($h['bulan']===12 && $h['hari']===9) $tags[] = 'Arafah';
    if ($h['bulan']===8 && $h['hari']===15) $tags[] = 'Nisfu Sya\'ban';
    if ($h['bulan']===9) $tags[] = 'Ramadhan';
    if ($h['bulan']===10 && $h['hari']>=2 && $h['hari']<=7) $tags[] = 'Syawal';
    if ($tags) $puasaSet[$d] = $tags;
}
?>
<h4 class="mb-3"><i class="bi bi-calendar3 text-success"></i> Kalender Hijriyah &amp; Puasa Sunnah</h4>

<h5 class="mt-2"><i class="bi bi-droplet-half text-info"></i> Jenis Puasa Sunnah &amp; Countdown</h5>
<div class="row g-3 mb-3">
  <?php
  $puasaCards = [
    ['Senin / Kamis','Puasa setiap Senin & Kamis (HR. Tirmidzi). Amalan yang dicintai Rasulullah ﷺ.', $nextSK, 'success','bi-droplet'],
    ['Ayyamul Bidh (13-14-15 Hijriyah)','Puasa "hari putih" 3 hari setiap bulan Hijriyah. Pahala seperti puasa setahun.', $nextAB, 'info','bi-moon'],
    ['Puasa Daud','Puasa selang-seling: sehari puasa, sehari berbuka. Puasa paling dicintai Allah.', $nextDaud, 'warning','bi-arrow-left-right'],
    ['Asyura (10 Muharram)','Menghapus dosa setahun yang lalu (HR. Muslim).', $asyura, 'primary','bi-calendar-event'],
    ['Tasu\'a (9 Muharram)','Disunnahkan menyertai puasa Asyura agar berbeda dengan Yahudi.', $tasua, 'primary','bi-calendar2'],
    ['Arafah (9 Dzulhijjah)','Bagi yang tidak haji — menghapus dosa setahun lalu &amp; setahun mendatang.', $arafah, 'danger','bi-sun'],
    ['Nisfu Sya\'ban (15 Sya\'ban)','Memperbanyak puasa di bulan Sya\'ban (HR. Bukhari).', $nisfu, 'secondary','bi-stars'],
    ['Syawal 6 hari','Setelah Idul Fitri — pahala seperti puasa setahun penuh.', $nextSyawal, 'success','bi-calendar-check'],
  ];
  foreach ($puasaCards as $idx => $pc):
    $cdId = 'cdPuasa'.$idx;
    $tgl = $pc[2];
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100 border-<?= $pc[3] ?>"><div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-1">
        <i class="bi <?= $pc[4] ?> fs-3 text-<?= $pc[3] ?>"></i>
        <h6 class="m-0"><?= $pc[0] ?></h6>
      </div>
      <div class="small text-muted mb-2"><?= $pc[1] ?></div>
      <?php if ($tgl): ?>
        <div class="small">Jadwal terdekat: <strong><?= $tgl->format('l, d M Y') ?></strong></div>
        <div class="small">Hijriyah: <?php $h=masehi_ke_hijriyah($tgl); ?><?= $h['hari'].' '.hijriyah_nama_bulan($h['bulan']).' '.$h['tahun'].' H' ?></div>
        <div class="mt-1 fw-semibold text-<?= $pc[3] ?>" id="<?= $cdId ?>">…</div>
      <?php endif; ?>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<h5 class="mt-4"><i class="bi bi-calendar-event text-danger"></i> Countdown Hari Raya Besar Islam</h5>
<div class="row g-3 mb-3">
  <?php
  $holidays = [
    ['Ramadhan',          $ramadhan, 'success', 'cdR'],
    ['Idul Fitri',        $iedFitri, 'warning', 'cdF'],
    ['Idul Adha',         $iedAdha,  'danger',  'cdI'],
    ['Tahun Baru Hijriyah',$tahunBaru,'info',   'cdTH'],
    ['Maulid Nabi',       $maulid,   'primary', 'cdM'],
    ['Isra Mi\'raj',      $isra,     'info',    'cdIs'],
    ['Nisfu Sya\'ban',    $nisfu,    'secondary','cdNS'],
    ['Asyura',            $asyura,   'dark',    'cdAS'],
    ['Arafah',            $arafah,   'warning', 'cdAR'],
  ];
  foreach ($holidays as $h): ?>
    <div class="col-md-4 col-lg-3"><div class="card border-<?= $h[2] ?>"><div class="card-body py-2">
      <div class="small text-muted">Countdown <?= $h[0] ?></div>
      <div class="fw-bold"><?= $h[1]->format('d M Y') ?></div>
      <div id="<?= $h[3] ?>" class="text-<?= $h[2] ?>">…</div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
  <div>
    <a href="?y=<?= $month==1?$year-1:$year ?>&m=<?= $month==1?12:$month-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <strong><?= $first->format('F Y') ?></strong>
    <a href="?y=<?= $month==12?$year+1:$year ?>&m=<?= $month==12?1:$month+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
  </div>
  <a href="?y=<?= (int)$today->format('Y') ?>&m=<?= (int)$today->format('n') ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-calendar-day"></i> Hari Ini</a>
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
    $tags = $puasaSet[$d] ?? null;
  ?>
    <td class="<?= $isToday?'bg-success-subtle':'' ?>" style="min-width:90px;vertical-align:top">
      <div class="fw-bold"><?= $d ?></div>
      <div class="small text-muted"><?= $h['hari'] ?> <?= htmlspecialchars(substr(hijriyah_nama_bulan($h['bulan']),0,3)) ?></div>
      <?php if($tags): foreach ($tags as $t): ?>
        <div class="small badge bg-warning-subtle text-warning d-block mt-1" title="Puasa Sunnah <?= htmlspecialchars($t) ?>">🌙 <?= htmlspecialchars($t) ?></div>
      <?php endforeach; endif; ?>
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
    <?php foreach ($holidays as $h): ?>
      window.islamiCountdown('<?= $h[3] ?>', '<?= $h[1]->format('Y-m-d') ?>T00:00:00');
    <?php endforeach; ?>
    <?php foreach ($puasaCards as $idx => $pc): if ($pc[2]): ?>
      window.islamiCountdown('cdPuasa<?= $idx ?>', '<?= $pc[2]->format('Y-m-d') ?>T00:00:00');
    <?php endif; endforeach; ?>
  }
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

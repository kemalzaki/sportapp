<?php
/**
 * cuaca.php — Revisi 20 Juni 2026
 * Halaman Perkiraan Cuaca (di grup "Info dan Wawasan").
 * Sumber data: Open-Meteo (gratis, tanpa API key).
 *   - Geocoding: https://geocoding-api.open-meteo.com/v1/search
 *   - Forecast : https://api.open-meteo.com/v1/forecast
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require_login();
send_security_headers();
$pageTitle = 'Perkiraan Cuaca';
$u = current_user();

$q = trim((string)($_GET['q'] ?? 'Jakarta'));
if ($q === '') $q = 'Jakarta';
if (mb_strlen($q) > 80) $q = mb_substr($q, 0, 80);

function _cuaca_curl($url, $timeout=6) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'SportApp/1.0 (+cuaca)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

// 1) Geocoding
$geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?'.http_build_query([
    'name'=>$q, 'count'=>1, 'language'=>'id', 'format'=>'json'
]);
[$gc, $gb] = _cuaca_curl($geoUrl);
$geo = $gc===200 ? json_decode($gb, true) : null;
$loc = $geo['results'][0] ?? null;

$forecast = null; $err = '';
if (!$loc) {
    $err = 'Lokasi "'.htmlspecialchars($q).'" tidak ditemukan. Coba kata kunci lain.';
} else {
    $fcUrl = 'https://api.open-meteo.com/v1/forecast?'.http_build_query([
        'latitude'  => $loc['latitude'],
        'longitude' => $loc['longitude'],
        'current'   => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,wind_speed_10m',
        'hourly'    => 'temperature_2m,precipitation_probability,weather_code',
        'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,sunrise,sunset,uv_index_max,wind_speed_10m_max',
        'timezone'  => 'auto',
        'forecast_days' => 7,
    ]);
    [$fc, $fb] = _cuaca_curl($fcUrl, 8);
    if ($fc===200) $forecast = json_decode($fb, true);
    else $err = 'Gagal mengambil data cuaca (HTTP '.$fc.'). Coba lagi sebentar.';
}

// Mapping kode cuaca WMO → label + ikon Bootstrap Icons
function wmo_label($code) {
    $c = (int)$code;
    $map = [
        0=>['Cerah','bi-sun-fill text-warning'],
        1=>['Cerah berawan','bi-cloud-sun-fill text-warning'],
        2=>['Berawan sebagian','bi-cloud-sun text-secondary'],
        3=>['Berawan','bi-clouds-fill text-secondary'],
        45=>['Berkabut','bi-cloud-fog2-fill text-secondary'],
        48=>['Kabut beku','bi-cloud-fog2-fill text-info'],
        51=>['Gerimis ringan','bi-cloud-drizzle-fill text-info'],
        53=>['Gerimis','bi-cloud-drizzle-fill text-info'],
        55=>['Gerimis lebat','bi-cloud-drizzle-fill text-primary'],
        61=>['Hujan ringan','bi-cloud-rain-fill text-info'],
        63=>['Hujan','bi-cloud-rain-fill text-primary'],
        65=>['Hujan lebat','bi-cloud-rain-heavy-fill text-primary'],
        71=>['Salju ringan','bi-cloud-snow-fill text-info'],
        73=>['Salju','bi-cloud-snow-fill text-info'],
        75=>['Salju lebat','bi-cloud-snow-fill text-primary'],
        80=>['Hujan lokal','bi-cloud-rain-fill text-info'],
        81=>['Hujan lebat lokal','bi-cloud-rain-heavy-fill text-primary'],
        82=>['Hujan badai','bi-cloud-rain-heavy-fill text-danger'],
        95=>['Badai petir','bi-cloud-lightning-rain-fill text-danger'],
        96=>['Badai petir + hujan es','bi-cloud-hail-fill text-danger'],
        99=>['Badai petir + hujan es lebat','bi-cloud-hail-fill text-danger'],
    ];
    return $map[$c] ?? ['Kondisi '.$c,'bi-cloud text-secondary'];
}

include __DIR__.'/includes/header.php'; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h2 class="mb-0"><i class="bi bi-cloud-sun-fill text-primary"></i> Perkiraan Cuaca</h2>
</div>

<form method="get" class="card card-body shadow-sm mb-3">
  <label class="form-label small fw-bold mb-1">Cari Kota / Daerah</label>
  <div class="input-group">
    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="mis. Bandung, Surabaya, Yogyakarta" maxlength="80">
    <button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button>
  </div>
  <div class="small text-muted mt-1">Tip: gunakan nama kota Indonesia (atau dunia). Data diperbarui otomatis tiap kunjungan.</div>
</form>

<?php if ($err): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?= $err ?></div>
<?php endif; ?>

<?php if ($loc && $forecast):
  $cur = $forecast['current'] ?? [];
  [$lbl,$ico] = wmo_label($cur['weather_code'] ?? 0);

  /* ============================================================
   * Revisi — Hitung rekomendasi jogging / outdoor lebih AWAL agar
   * kartu Rekomendasi bisa ditampilkan tepat di bawah form pencarian
   * (di atas Prakiraan 7 Hari & Per Jam).
   * ============================================================ */
  $h = $forecast['hourly'] ?? null;
  $now = $cur['time'] ?? null; $startIdx = 0;
  if ($h && !empty($h['time']) && $now) {
      foreach ($h['time'] as $idx=>$t) { if ($t >= $now) { $startIdx = $idx; break; } }
  }

  $temp = (float)($cur['temperature_2m'] ?? 0);
  $hum  = (int)($cur['relative_humidity_2m'] ?? 0);
  $wind = (float)($cur['wind_speed_10m'] ?? 0);
  $rain = (int)(($forecast['daily']['precipitation_probability_max'][0] ?? 0));
  $uv   = (float)(($forecast['daily']['uv_index_max'][0] ?? 0));
  $code = (int)($cur['weather_code'] ?? 0);
  $isDay= (int)($cur['is_day'] ?? 1);

  $skor = 100; $alasan = [];
  if ($temp > 33)        { $skor -= 30; $alasan[] = "suhu panas {$temp}°C"; }
  elseif ($temp > 30)    { $skor -= 15; $alasan[] = "suhu agak panas {$temp}°C"; }
  elseif ($temp < 18)    { $skor -= 10; $alasan[] = "suhu dingin {$temp}°C"; }
  if ($hum > 85)         { $skor -= 15; $alasan[] = "kelembaban tinggi {$hum}%"; }
  if ($wind > 25)        { $skor -= 20; $alasan[] = "angin kencang {$wind} km/j"; }
  if ($rain > 70)        { $skor -= 40; $alasan[] = "peluang hujan {$rain}%"; }
  elseif ($rain > 40)    { $skor -= 20; $alasan[] = "peluang hujan {$rain}%"; }
  if ($uv > 8)           { $skor -= 25; $alasan[] = "UV ekstrem {$uv}"; }
  elseif ($uv > 6)       { $skor -= 10; $alasan[] = "UV tinggi {$uv}"; }
  if (in_array($code, [95,96,99,82], true)) { $skor -= 60; $alasan[] = "badai/petir"; }
  if (!$isDay)           { $alasan[] = "saat ini malam"; }
  $skor = max(0, min(100, $skor));

  if ($skor >= 75)      { $cat = 'success'; $ico2='bi-emoji-smile-fill'; $kata='SANGAT BAIK untuk jogging / outdoor'; }
  elseif ($skor >= 55)  { $cat = 'info';    $ico2='bi-emoji-neutral';     $kata='CUKUP BAIK — outdoor masih disarankan'; }
  elseif ($skor >= 35)  { $cat = 'warning'; $ico2='bi-emoji-expressionless';$kata='KURANG IDEAL — pertimbangkan menunda atau latihan indoor'; }
  else                  { $cat = 'danger';  $ico2='bi-emoji-frown-fill';  $kata='TIDAK DISARANKAN — pilih latihan indoor'; }

  $bestHours = [];
  if ($h && !empty($h['time'])) {
      $scored = [];
      for ($i=$startIdx; $i<min($startIdx+24,count($h['time'])); $i++) {
          $sc = 100;
          $tt = (float)$h['temperature_2m'][$i];
          $pp = (int)($h['precipitation_probability'][$i] ?? 0);
          if ($tt > 32) $sc -= 25; elseif ($tt < 19) $sc -= 10;
          if ($pp > 60) $sc -= 40; elseif ($pp > 30) $sc -= 15;
          $scored[] = ['t'=>$h['time'][$i],'s'=>$sc,'tt'=>$tt,'pp'=>$pp];
      }
      usort($scored, fn($a,$b)=>$b['s']<=>$a['s']);
      $bestHours = array_slice($scored, 0, 3);
  }
?>

  <!-- Revisi — Rekomendasi Jogging / Outdoor ditampilkan tepat di bawah
       form Cari Kota / Daerah (di atas kondisi saat ini & prakiraan). -->
  <div class="card shadow-sm mb-3 border-<?= $cat ?>">
    <div class="card-header bg-<?= $cat ?>-subtle text-<?= $cat ?>-emphasis">
      <i class="bi bi-person-walking"></i> <strong>Rekomendasi Jogging / Outdoor (Auto)</strong>
      <span class="badge bg-<?= $cat ?> ms-2">Skor <?= $skor ?>/100</span>
    </div>
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 mb-2">
        <i class="bi <?= $ico2 ?> fs-1 text-<?= $cat ?>"></i>
        <div>
          <div class="fw-bold fs-5"><?= htmlspecialchars($kata) ?></div>
          <?php if ($alasan): ?><div class="small text-muted">Faktor: <?= htmlspecialchars(implode(', ', $alasan)) ?>.</div><?php endif; ?>
        </div>
      </div>
      <?php if ($bestHours): ?>
        <div class="small mb-1"><strong>Jam terbaik (24 jam ke depan):</strong></div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($bestHours as $bh): ?>
            <span class="badge bg-light text-dark border">
              <i class="bi bi-clock"></i> <?= htmlspecialchars(substr($bh['t'],11,5)) ?> ·
              <?= number_format($bh['tt'],0) ?>° · hujan <?= $bh['pp'] ?>%
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i>
        Pedoman umum: hindari outdoor saat UV &gt; 7, peluang hujan &gt; 70%, atau ada badai petir.
        Untuk lari jauh, pilih jam saat suhu 22–28°C &amp; kelembaban &lt; 80%.
      </div>
    </div>
  </div>

<?php /* Kondisi saat ini + Prakiraan 7 Hari (spoiler) */ ?>
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center">
          <div class="text-muted small"><?= htmlspecialchars(($loc['name'] ?? '').(!empty($loc['admin1'])?', '.$loc['admin1']:'').(!empty($loc['country'])?', '.$loc['country']:'')) ?></div>
          <i class="bi <?= $ico ?>" style="font-size:5rem;line-height:1"></i>
          <div class="display-4 fw-bold mt-1"><?= number_format((float)($cur['temperature_2m'] ?? 0),1) ?>°C</div>
          <div class="fs-5"><?= htmlspecialchars($lbl) ?></div>
          <div class="small text-muted mt-1">Terasa <?= number_format((float)($cur['apparent_temperature'] ?? 0),1) ?>°C · Kelembaban <?= (int)($cur['relative_humidity_2m'] ?? 0) ?>% · Angin <?= number_format((float)($cur['wind_speed_10m'] ?? 0),1) ?> km/j</div>
          <div class="small text-muted">Diperbarui: <?= htmlspecialchars($cur['time'] ?? '') ?> (<?= htmlspecialchars($forecast['timezone'] ?? '') ?>)</div>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <details class="cuaca-spoiler" open>
          <summary class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:none;">
            <span><i class="bi bi-calendar-week text-primary"></i> Prakiraan 7 Hari</span>
            <i class="bi bi-chevron-down small spoiler-caret"></i>
          </summary>
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0 text-center">
              <thead class="table-light">
                <tr>
                  <th class="text-start">Tanggal</th>
                  <th class="text-start">Kondisi</th>
                  <th>Min / Maks</th>
                  <th>Hujan</th>
                  <th>UV</th>
                </tr>
              </thead>
              <tbody>
                <?php $d = $forecast['daily'] ?? []; $n = count($d['time'] ?? []);
                for ($i=0;$i<$n;$i++): [$dl,$di] = wmo_label($d['weather_code'][$i] ?? 0); ?>
                  <tr>
                    <td class="text-start"><strong><?= htmlspecialchars($d['time'][$i] ?? '') ?></strong><br><small class="text-muted"><?= hari_id($d['time'][$i] ?? '') ?></small></td>
                    <td class="text-start"><i class="bi <?= $di ?>"></i> <?= htmlspecialchars($dl) ?></td>
                    <td><?= number_format((float)$d['temperature_2m_min'][$i],0) ?>° / <strong><?= number_format((float)$d['temperature_2m_max'][$i],0) ?>°</strong></td>
                    <td><?= (int)($d['precipitation_probability_max'][$i] ?? 0) ?>%<br><small class="text-muted"><?= number_format((float)$d['precipitation_sum'][$i],1) ?> mm</small></td>
                    <td><?= number_format((float)($d['uv_index_max'][$i] ?? 0),1) ?></td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </details>
      </div>
    </div>
  </div>

  <?php if ($h && !empty($h['time'])): ?>
    <div class="card shadow-sm mt-3">
      <details class="cuaca-spoiler">
        <summary class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:none;">
          <span><i class="bi bi-clock-history text-primary"></i> Per Jam (24 jam ke depan)</span>
          <i class="bi bi-chevron-down small spoiler-caret"></i>
        </summary>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover align-middle mb-0 text-center">
            <thead class="table-light">
              <tr>
                <th class="text-start">Jam</th>
                <th>Suhu</th>
                <th class="text-start">Kondisi</th>
                <th>Peluang Hujan</th>
              </tr>
            </thead>
            <tbody>
            <?php for ($i=$startIdx;$i<min($startIdx+24,count($h['time']));$i++):
                [$hl,$hi] = wmo_label($h['weather_code'][$i] ?? 0); ?>
              <tr>
                <td class="text-start"><?= htmlspecialchars(substr($h['time'][$i],11,5)) ?> <small class="text-muted"><?= htmlspecialchars(substr($h['time'][$i],5,5)) ?></small></td>
                <td><?= number_format((float)$h['temperature_2m'][$i],1) ?>°C</td>
                <td class="text-start"><i class="bi <?= $hi ?>"></i> <?= htmlspecialchars($hl) ?></td>
                <td><?= (int)($h['precipitation_probability'][$i] ?? 0) ?>%</td>
              </tr>
            <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>
  <?php endif; ?>

<?php endif; ?>

<style>
  .cuaca-spoiler > summary::-webkit-details-marker{ display:none; }
  .cuaca-spoiler > summary{ user-select:none; }
  .cuaca-spoiler[open] .spoiler-caret{ transform:rotate(180deg); }
  .spoiler-caret{ transition:transform .15s ease; }
</style>

<?php include __DIR__.'/includes/footer.php'; ?>

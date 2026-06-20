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
  <span class="badge bg-info text-dark"><i class="bi bi-info-circle"></i> Sumber: Open-Meteo</span>
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

<?php if ($loc && $forecast): $cur = $forecast['current'] ?? []; [$lbl,$ico] = wmo_label($cur['weather_code'] ?? 0); ?>
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
        <div class="card-header"><i class="bi bi-calendar-week text-primary"></i> Prakiraan 7 Hari</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>Kondisi</th><th>Min/Maks</th><th>Hujan</th><th>UV</th></tr></thead>
            <tbody>
              <?php $d = $forecast['daily'] ?? []; $n = count($d['time'] ?? []);
              for ($i=0;$i<$n;$i++): [$dl,$di] = wmo_label($d['weather_code'][$i] ?? 0); ?>
                <tr>
                  <td><strong><?= htmlspecialchars($d['time'][$i] ?? '') ?></strong><br><small class="text-muted"><?= hari_id($d['time'][$i] ?? '') ?></small></td>
                  <td><i class="bi <?= $di ?>"></i> <?= htmlspecialchars($dl) ?></td>
                  <td><?= number_format((float)$d['temperature_2m_min'][$i],0) ?>° / <strong><?= number_format((float)$d['temperature_2m_max'][$i],0) ?>°</strong></td>
                  <td><?= (int)($d['precipitation_probability_max'][$i] ?? 0) ?>%<br><small class="text-muted"><?= number_format((float)$d['precipitation_sum'][$i],1) ?> mm</small></td>
                  <td><?= number_format((float)($d['uv_index_max'][$i] ?? 0),1) ?></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php $h = $forecast['hourly'] ?? null; if ($h && !empty($h['time'])): ?>
    <div class="card shadow-sm mt-3">
      <div class="card-header"><i class="bi bi-clock-history text-primary"></i> Per Jam (24 jam ke depan)</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Jam</th><th>Suhu</th><th>Kondisi</th><th>Peluang Hujan</th></tr></thead>
          <tbody>
          <?php
            // Cari index sekarang
            $now = $cur['time'] ?? null; $startIdx = 0;
            if ($now) { foreach ($h['time'] as $idx=>$t) { if ($t >= $now) { $startIdx = $idx; break; } } }
            for ($i=$startIdx;$i<min($startIdx+24,count($h['time']));$i++):
              [$hl,$hi] = wmo_label($h['weather_code'][$i] ?? 0); ?>
            <tr>
              <td><?= htmlspecialchars(substr($h['time'][$i],11,5)) ?> <small class="text-muted"><?= htmlspecialchars(substr($h['time'][$i],5,5)) ?></small></td>
              <td><?= number_format((float)$h['temperature_2m'][$i],1) ?>°C</td>
              <td><i class="bi <?= $hi ?>"></i> <?= htmlspecialchars($hl) ?></td>
              <td><?= (int)($h['precipitation_probability'][$i] ?? 0) ?>%</td>
            </tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="alert alert-info small mt-3 mb-0">
    <i class="bi bi-lightbulb"></i> <strong>Tips olahraga:</strong> hindari latihan outdoor saat UV &gt; 7 atau peluang hujan &gt; 70%. Pertimbangkan latihan indoor atau geser jadwal ke pagi/sore.
  </div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
// kalkulator_jantung.php — Revisi 11 Juni 2026
// Kalkulator Detak Jantung: HR max, zona latihan, tanda kesehatan dari resting HR.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalkulator Detak Jantung';
$u = current_user();
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Kalkulator Jantung');
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Kalkulator Detak Jantung</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Detak Jantung</h2>
<p class="text-muted small mb-3">Hitung Heart Rate Max (HRmax), zona latihan, dan dapatkan <strong>indikator kesehatan</strong> berdasarkan resting HR.</p>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body">
      <h3 class="h6"><i class="bi bi-sliders"></i> Input</h3>
      <div class="mb-2"><label class="small fw-semibold">Usia (tahun)</label>
        <input type="number" id="age" class="form-control" min="5" max="100" value="30"></div>
      <div class="mb-2"><label class="small fw-semibold">Jenis Kelamin</label>
        <select id="sex" class="form-select"><option value="m">Pria</option><option value="f">Wanita</option></select></div>
      <div class="mb-2"><label class="small fw-semibold">Resting Heart Rate (bpm) — opsional</label>
        <input type="number" id="rhr" class="form-control" min="30" max="120" placeholder="cek pagi sebelum bangun"></div>
      <div class="mb-3"><label class="small fw-semibold">Rumus HRmax</label>
        <select id="formula" class="form-select">
          <option value="tanaka">Tanaka (208 − 0.7 × usia) — direkomendasikan</option>
          <option value="haskell">Haskell-Fox (220 − usia) — klasik</option>
          <option value="gulati">Gulati (206 − 0.88 × usia) — wanita</option>
        </select>
      </div>
      <button class="btn btn-danger w-100" onclick="hitung()"><i class="bi bi-calculator"></i> Hitung</button>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm mb-3"><div class="card-body">
      <h3 class="h6"><i class="bi bi-graph-up"></i> Hasil</h3>
      <div id="hasil" class="small text-muted">Isi data lalu klik Hitung.</div>
    </div></div>

    <div class="card shadow-sm"><div class="card-body">
      <h3 class="h6"><i class="bi bi-info-circle"></i> Tanda Kesehatan dari Resting HR</h3>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Resting HR (bpm)</th><th>Kategori</th><th>Interpretasi</th></tr></thead>
        <tbody>
          <tr><td>40 – 60</td><td><span class="badge bg-success">Sangat Baik</span></td><td>Khas atlet/orang sangat bugar.</td></tr>
          <tr><td>60 – 70</td><td><span class="badge bg-success-subtle text-success">Baik</span></td><td>Sehat dan terlatih.</td></tr>
          <tr><td>70 – 80</td><td><span class="badge bg-warning text-dark">Rata-rata</span></td><td>Normal — bisa ditingkatkan dengan kardio.</td></tr>
          <tr><td>80 – 100</td><td><span class="badge bg-warning">Kurang Bugar</span></td><td>Perlu rutin olahraga aerobik.</td></tr>
          <tr><td>&gt; 100</td><td><span class="badge bg-danger">Waspada (Takikardia)</span></td><td>Konsultasi dokter — bisa terkait stres, dehidrasi, atau gangguan jantung.</td></tr>
          <tr><td>&lt; 40</td><td><span class="badge bg-info">Bradikardia</span></td><td>Normal pada atlet; jika disertai pusing/lemas konsultasi dokter.</td></tr>
        </tbody>
      </table></div>
    </div></div>
  </div>
</div>

<script>
function hitung(){
  const age = parseInt(document.getElementById('age').value)||0;
  const sex = document.getElementById('sex').value;
  const rhr = parseInt(document.getElementById('rhr').value)||0;
  const f   = document.getElementById('formula').value;
  if(age<5||age>100){ alert('Usia tidak valid'); return; }
  let hrmax;
  if(f==='haskell') hrmax = 220 - age;
  else if(f==='gulati') hrmax = Math.round(206 - 0.88*age);
  else hrmax = Math.round(208 - 0.7*age);

  // Zona (% HRmax)
  const zones = [
    [0.50,0.60,'Zona 1 — Pemulihan',           'success',  'Sangat ringan, recovery / warm-up.'],
    [0.60,0.70,'Zona 2 — Aerobik Dasar',       'info',     'Bakar lemak, daya tahan dasar. Bisa ngobrol santai.'],
    [0.70,0.80,'Zona 3 — Aerobik',             'primary',  'Endurance, kebugaran kardio meningkat.'],
    [0.80,0.90,'Zona 4 — Ambang Laktat',       'warning',  'Intens — meningkatkan VO2max & kecepatan.'],
    [0.90,1.00,'Zona 5 — Maksimal',            'danger',   'Sprint pendek — hanya untuk atlet terlatih.'],
  ];
  let html = `<div class="mb-2"><span class="badge bg-danger">HRmax</span> <b>${hrmax} bpm</b></div>`;
  if(rhr){
    const hrr = hrmax - rhr;
    html += `<div class="mb-2 small">Heart Rate Reserve (Karvonen): <b>${hrr} bpm</b> · Resting HR: <b>${rhr} bpm</b></div>`;
    let kat='', col='secondary', tip='';
    if(rhr<40){kat='Bradikardia';col='info';tip='Normal pada atlet; jika pusing/lemas konsultasi.';}
    else if(rhr<=60){kat='Sangat Baik';col='success';tip='Khas atlet/orang sangat bugar.';}
    else if(rhr<=70){kat='Baik';col='success';tip='Sehat & terlatih.';}
    else if(rhr<=80){kat='Rata-rata';col='warning';tip='Tingkatkan dengan kardio rutin.';}
    else if(rhr<=100){kat='Kurang Bugar';col='warning';tip='Perlu latihan aerobik.';}
    else {kat='Takikardia';col='danger';tip='Konsultasi dokter — periksa stres/dehidrasi/jantung.';}
    html += `<div class="alert alert-${col} py-2 small mb-2"><b>Resting HR: ${kat}</b> — ${tip}</div>`;
  }
  html += '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Zona</th><th>Rentang BPM</th><th>Manfaat</th></tr></thead><tbody>';
  zones.forEach(z=>{
    let lo, hi;
    if(rhr){
      // Karvonen: target = ((HRmax - RHR) × %) + RHR
      lo = Math.round((hrmax-rhr)*z[0]+rhr);
      hi = Math.round((hrmax-rhr)*z[1]+rhr);
    } else {
      lo = Math.round(hrmax*z[0]);
      hi = Math.round(hrmax*z[1]);
    }
    html += `<tr><td><span class="badge bg-${z[3]}">${z[2]}</span></td><td><b>${lo}–${hi}</b> bpm</td><td class="small">${z[4]}</td></tr>`;
  });
  html += '</tbody></table></div>';
  html += '<div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Rentang BPM dihitung dari %HRmax' + (rhr?' menggunakan formula Karvonen (lebih personal).':' (tanpa Karvonen).') + '</div>';
  document.getElementById('hasil').innerHTML = html;
}
</script>

<?php htmx_layout_end(); ?>

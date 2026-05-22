<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalkulator Sehat';

$u = current_user();
$prefBerat = $prefTinggi = $prefUmur = $prefJk = '';
if ($u) {
    try {
        $row = db_one("SELECT berat_kg, tinggi_cm, tanggal_lahir, jenis_kelamin FROM users WHERE id=$1", [(int)$u['id']]);
        if ($row) {
            $prefBerat = $row['berat_kg'] ?? '';
            $prefTinggi = $row['tinggi_cm'] ?? '';
            $prefJk = $row['jenis_kelamin'] ?? '';
            if (!empty($row['tanggal_lahir'])) {
                try { $prefUmur = (new DateTime($row['tanggal_lahir']))->diff(new DateTime('today'))->y; } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}
}
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-heart-pulse text-danger"></i> Kalkulator Sehat</h2>
<p class="text-muted">Hitung skor kesehatan dasar dari <strong>umur</strong>, <strong>berat badan</strong>, dan <strong>tinggi badan</strong>. Hasil bersifat estimasi (BMI + zona usia), bukan diagnosis medis.</p>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card shadow-sm"><div class="card-body">
      <form id="kalkForm" class="row g-2">
        <div class="col-6">
          <label class="form-label small fw-semibold">Umur (tahun)</label>
          <input type="number" min="5" max="120" id="kUmur" class="form-control" value="<?= htmlspecialchars($prefUmur) ?>" required>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Jenis Kelamin</label>
          <select id="kJk" class="form-select">
            <option value="">— pilih —</option>
            <option value="L" <?= $prefJk==='L'?'selected':'' ?>>Laki-laki</option>
            <option value="P" <?= $prefJk==='P'?'selected':'' ?>>Perempuan</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Berat Badan (kg)</label>
          <input type="number" step="0.1" min="20" max="300" id="kBerat" class="form-control" value="<?= htmlspecialchars($prefBerat) ?>" required>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Tinggi Badan (cm)</label>
          <input type="number" step="0.1" min="80" max="250" id="kTinggi" class="form-control" value="<?= htmlspecialchars($prefTinggi) ?>" required>
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary"><i class="bi bi-calculator"></i> Hitung Skor Sehat</button>
          <button type="reset" class="btn btn-outline-secondary">Reset</button>
        </div>
      </form>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm"><div class="card-body">
      <h5 class="mb-3"><i class="bi bi-clipboard2-pulse text-primary"></i> Hasil</h5>
      <div id="kHasil"><p class="text-muted small mb-0">Isi form di sebelah untuk melihat hasil.</p></div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm mt-3"><div class="card-body small">
  <strong>Catatan rumus:</strong>
  <ul class="mb-0">
    <li>BMI = berat (kg) / (tinggi (m))²</li>
    <li>Kategori BMI: &lt;18.5 Kurus · 18.5–24.9 Normal · 25–29.9 Gemuk · ≥30 Obesitas</li>
    <li>Berat ideal (Devine): pria 50 + 0.9 × (TB-152) · wanita 45.5 + 0.9 × (TB-152)</li>
    <li>BMR (Mifflin-St Jeor): pria 10·BB + 6.25·TB − 5·umur + 5 · wanita 10·BB + 6.25·TB − 5·umur − 161</li>
    <li>Skor sehat (0–100): kombinasi deviasi BMI dari 22 + zona umur.</li>
  </ul>
</div></div>

<script>
document.getElementById('kalkForm').addEventListener('submit', function(ev){
  ev.preventDefault();
  const umur = +document.getElementById('kUmur').value;
  const jk = document.getElementById('kJk').value;
  const berat = +document.getElementById('kBerat').value;
  const tinggi = +document.getElementById('kTinggi').value;
  if(!umur || !berat || !tinggi){ return; }
  const hM = tinggi/100;
  const bmi = berat/(hM*hM);
  let cat='—', col='secondary';
  if (bmi<18.5){cat='Kurus';col='warning';}
  else if (bmi<25){cat='Normal';col='success';}
  else if (bmi<30){cat='Gemuk';col='warning';}
  else {cat='Obesitas';col='danger';}
  // Berat ideal (Devine)
  let ideal = null;
  if (tinggi>=152 && jk){
    ideal = (jk==='L'?50:45.5) + 0.9*(tinggi-152);
  }
  // BMR (Mifflin)
  let bmr = null;
  if (jk){
    bmr = 10*berat + 6.25*tinggi - 5*umur + (jk==='L'?5:-161);
  }
  // Skor sehat sederhana
  const devBmi = Math.min(40, Math.abs(bmi-22)*4); // 0..40
  let zonaUmur = 0;
  if (umur<13) zonaUmur=10; else if (umur<=29) zonaUmur=0; else if (umur<=44) zonaUmur=5; else if (umur<=59) zonaUmur=10; else zonaUmur=18;
  let skor = Math.max(0, Math.round(100 - devBmi - zonaUmur));
  let skorBadge = skor>=80?'success':(skor>=60?'primary':(skor>=40?'warning':'danger'));
  let tip = '';
  if (cat==='Kurus') tip='Tingkatkan asupan kalori bergizi dan latihan kekuatan.';
  else if (cat==='Normal') tip='Pertahankan pola makan & olahraga rutin 3-5x seminggu.';
  else if (cat==='Gemuk') tip='Defisit kalori ringan + kardio 150 menit/minggu.';
  else tip='Konsultasi ke dokter/ahli gizi untuk program penurunan berat aman.';

  document.getElementById('kHasil').innerHTML = `
    <div class="row g-2">
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">BMI</div><div class="stat-value">${bmi.toFixed(1)} <small class="text-${col}">(${cat})</small></div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">Skor Sehat</div><div class="stat-value text-${skorBadge}">${skor}/100</div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">Berat Ideal</div><div class="stat-value">${ideal?ideal.toFixed(1)+' kg':'—'}</div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">BMR (kalori/hari)</div><div class="stat-value">${bmr?Math.round(bmr):'—'}</div></div></div></div>
      <div class="col-12"><div class="alert alert-${col} mb-0 small"><i class="bi bi-lightbulb"></i> ${tip}</div></div>
    </div>`;
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

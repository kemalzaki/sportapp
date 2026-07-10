<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalkulator Sehat';

/* Revisi 26 Juni 2026 — Gating Paket PRO & KOMUNITAS.
   Paket Gratis dikunci, ditampilkan banner upgrade + tombol pesan via WA. */
require_once __DIR__.'/includes/paket_helpers.php';
if (!isset($u) || !$u) { require_login(); $u = current_user(); }
$USER_PAKET = paket_user($u);
if (!in_array($USER_PAKET, ['pro','komunitas'], true)) {
    $__lockTitle = isset($pageTitle) && $pageTitle ? $pageTitle : 'Fitur PRO';
    include __DIR__.'/includes/header.php';
    echo '<h2 class="mb-3"><i class="bi bi-lock-fill text-warning"></i> '.htmlspecialchars($__lockTitle).'</h2>';
    echo paket_pro_lock_banner($__lockTitle,
        'Fitur ini hanya tersedia untuk paket PRO & KOMUNITAS. Paket Gratis tidak dapat mengakses fitur ini. Status paket Anda saat ini: '.strtoupper($USER_PAKET).'. Silakan upgrade untuk membuka akses.');
    include __DIR__.'/includes/footer.php';
    exit;
}


$u = current_user();
// Revisi #8 — Fitur Indikator Hormon Gairah Seksual hanya untuk komunitas KawanKeringat Kantor
require_once __DIR__.'/includes/scope.php';
$SHOW_HORMON = scope_is_kawankeringat_kantor();
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
        <div class="col-12 mt-2">
          <label class="form-label small fw-semibold"><i class="bi bi-clipboard2-pulse text-danger"></i> Gejala / Keluhan yang dirasakan (centang yang sesuai)</label>
          <div class="row g-1 small">
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gTekanan" value="tekanan_tinggi" data-label="Sering pusing / tekanan darah tinggi"><label class="form-check-label" for="gTekanan">Sering pusing / darah tinggi</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gGula" value="gula_tinggi" data-label="Sering haus & sering buang air kecil"><label class="form-check-label" for="gGula">Sering haus & sering kencing</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gSendi" value="nyeri_sendi" data-label="Nyeri sendi / lutut"><label class="form-check-label" for="gSendi">Nyeri sendi / lutut</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gSesak" value="sesak" data-label="Mudah lelah / sesak napas"><label class="form-check-label" for="gSesak">Mudah lelah / sesak napas</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gKolesterol" value="kolesterol" data-label="Riwayat kolesterol tinggi"><label class="form-check-label" for="gKolesterol">Kolesterol tinggi</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gKeluarga" value="keturunan" data-label="Riwayat keluarga (diabetes/jantung)"><label class="form-check-label" for="gKeluarga">Riwayat keluarga DM/jantung</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gRokok" value="merokok" data-label="Perokok aktif"><label class="form-check-label" for="gRokok">Perokok aktif</label></div></div>
            <div class="col-6"><div class="form-check"><input class="form-check-input k-gejala" type="checkbox" id="gJarang" value="jarang_olahraga" data-label="Jarang berolahraga"><label class="form-check-label" for="gJarang">Jarang berolahraga</label></div></div>
          </div>
          <input type="text" id="kKeterangan" class="form-control form-control-sm mt-2" placeholder="Keterangan tambahan (opsional)">
        </div>

        <!-- ====== HORMON GAIRAH SEKSUAL (anak muda / belum menikah) ====== -->
        <?php if ($SHOW_HORMON): ?>
        <div class="col-12 mt-3 border-top pt-3">
          <label class="form-label small fw-semibold text-danger">
            <i class="bi bi-fire"></i> Indikator Hormon Gairah Seksual (anjuran sunnah bagi yang belum menikah)
          </label>
          <p class="small text-muted mb-2">
            Centang kondisi yang kamu alami akhir-akhir ini. Skor ini bersifat estimasi perilaku/psikologis untuk membantu evaluasi diri,
            <strong>bukan pemeriksaan medis</strong>. Rekomendasi mengacu hadist:
            <em>"Wahai para pemuda, siapa di antara kalian yang mampu menikah maka menikahlah… dan siapa yang belum mampu, hendaklah ia berpuasa, karena puasa itu perisai (peredam syahwat) baginya."</em> (HR. Bukhari &amp; Muslim).
          </p>
          <div class="row g-2 small">
            <div class="col-md-4"><label class="small fw-semibold">Status pernikahan</label>
              <select id="hStatus" class="form-select form-select-sm">
                <option value="belum">Belum menikah</option>
                <option value="sudah">Sudah menikah</option>
              </select>
            </div>
            <div class="col-md-4"><label class="small fw-semibold">Frekuensi olahraga / pekan</label>
              <select id="hOlahraga" class="form-select form-select-sm">
                <option value="0">Hampir tidak pernah</option>
                <option value="1">1–2x</option>
                <option value="2" selected>3–4x</option>
                <option value="3">5x atau lebih</option>
              </select>
            </div>
            <div class="col-md-4"><label class="small fw-semibold">Puasa sunnah / pekan</label>
              <select id="hShaum" class="form-select form-select-sm">
                <option value="0" selected>Tidak ada</option>
                <option value="1">1 hari (Senin/Kamis)</option>
                <option value="2">2 hari (Senin &amp; Kamis)</option>
                <option value="3">Lebih sering</option>
              </select>
            </div>
          </div>
          <div class="row g-1 small mt-1">
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h1" value="3"><label class="form-check-label" for="h1">Pikiran tentang hal seksual muncul hampir setiap hari</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h2" value="3"><label class="form-check-label" for="h2">Mimpi basah lebih dari 1x dalam seminggu</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h3" value="2"><label class="form-check-label" for="h3">Sulit menahan pandangan terhadap lawan jenis</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h4" value="2"><label class="form-check-label" for="h4">Mudah marah / gelisah / cepat tersulut emosi</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h5" value="3"><label class="form-check-label" for="h5">Sulit tidur karena dorongan / fantasi</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h6" value="3"><label class="form-check-label" for="h6">Sering terpapar konten dewasa / pornografi</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h7" value="2"><label class="form-check-label" for="h7">Konsumsi kafein/energi tinggi / kurang tidur</label></div></div>
            <div class="col-md-6"><div class="form-check"><input class="form-check-input k-hormon" type="checkbox" id="h8" value="2"><label class="form-check-label" for="h8">Banyak waktu luang / sering sendirian</label></div></div>
          </div>
        </div>
        <?php endif; ?>
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

  // ===== Kesimpulan riwayat penyakit terdeteksi =====
  const gejala = Array.from(document.querySelectorAll('.k-gejala:checked')).map(c=>c.value);
  const keterangan = (document.getElementById('kKeterangan').value || '').trim();
  const has = v => gejala.includes(v);
  let risiko = [];
  if ((cat==='Gemuk' || cat==='Obesitas') && (has('gula_tinggi') || has('keturunan')))
    risiko.push({n:'Diabetes Melitus Tipe 2', d:'Kombinasi berat badan berlebih dengan gejala gula darah / faktor keturunan.'});
  if ((cat==='Gemuk' || cat==='Obesitas') && (has('tekanan_tinggi') || has('kolesterol')))
    risiko.push({n:'Hipertensi', d:'Berat badan berlebih disertai keluhan tekanan darah / kolesterol tinggi.'});
  if (has('kolesterol') && (has('tekanan_tinggi') || has('merokok') || has('sesak')))
    risiko.push({n:'Penyakit Jantung Koroner', d:'Kolesterol tinggi dengan faktor risiko tambahan (rokok/tekanan darah/sesak).'});
  if (has('nyeri_sendi') && (cat==='Gemuk' || cat==='Obesitas'))
    risiko.push({n:'Osteoartritis (radang sendi)', d:'Beban berlebih pada sendi akibat berat badan.'});
  if (has('sesak') && has('merokok'))
    risiko.push({n:'Gangguan Pernapasan (PPOK/asma)', d:'Sesak napas pada perokok aktif.'});
  if (cat==='Kurus' && has('jarang_olahraga'))
    risiko.push({n:'Kurang Gizi / Massa Otot Rendah', d:'Berat badan kurang dengan aktivitas fisik rendah.'});

  let kesimpulan;
  if (risiko.length){
    kesimpulan = '<div class="alert alert-warning mb-0 small"><strong><i class="bi bi-exclamation-triangle"></i> Riwayat penyakit yang berpotensi terdeteksi:</strong><ul class="mb-1 mt-1">' +
      risiko.map(r=>'<li><strong>'+r.n+'</strong> — '+r.d+'</li>').join('') +
      '</ul><span class="text-muted">Ini hanya deteksi dini berbasis input, bukan diagnosis. Disarankan periksa ke tenaga medis.</span></div>';
  } else if (gejala.length){
    kesimpulan = '<div class="alert alert-info mb-0 small"><i class="bi bi-info-circle"></i> Dari keluhan yang dicentang belum terdeteksi pola penyakit spesifik. Tetap jaga pola hidup sehat.</div>';
  } else {
    kesimpulan = '<div class="alert alert-success mb-0 small"><i class="bi bi-check-circle"></i> Tidak ada keluhan dicentang — tidak ada indikasi riwayat penyakit. Pertahankan kondisi sehat Anda.</div>';
  }
  if (keterangan) kesimpulan += '<div class="small text-muted mt-1"><i class="bi bi-pencil"></i> Catatan Anda: '+keterangan.replace(/[<>&]/g,'')+'</div>';

  // ===== Kalkulasi Hormon Gairah Seksual =====
  var SHOW_HORMON = <?= $SHOW_HORMON ? 'true' : 'false' ?>;
  let hormonHtml = '';
  if (SHOW_HORMON) {
  // Skor mentah: jumlah nilai checkbox yang dicentang (maks ~20).
  // Modifier: usia muda (15–29) menaikkan skor, olahraga rutin & puasa sunnah menurunkan skor.
  let hSkor = 0;
  document.querySelectorAll('.k-hormon:checked').forEach(c => hSkor += (+c.value || 0));
  const hStatus  = document.getElementById('hStatus').value;
  const hOlahraga = +document.getElementById('hOlahraga').value;
  const hShaum   = +document.getElementById('hShaum').value;
  if (umur >= 15 && umur <= 24) hSkor += 4;
  else if (umur >= 25 && umur <= 29) hSkor += 2;
  else if (umur >= 30 && umur <= 39) hSkor += 1;
  if (jk === 'L') hSkor += 1; // testosteron laki-laki cenderung lebih tinggi
  hSkor -= hOlahraga; // tiap level olahraga -1
  hSkor -= hShaum;    // tiap level shaum -1
  if (hSkor < 0) hSkor = 0;
  // Normalisasi ke 0–100
  const hMax = 25; // estimasi skor maksimum praktis
  const hPersen = Math.min(100, Math.round((hSkor / hMax) * 100));

  let hLevel, hCol, hRekom;
  if (hStatus === 'sudah') {
    hLevel = 'Tidak relevan (sudah menikah)';
    hCol = 'success';
    hRekom = 'Salurkan dengan jalan yang halal bersama pasangan. Tetap jaga olahraga rutin dan pola tidur agar hormon stabil.';
  } else if (hPersen < 35) {
    hLevel = 'Aman / terkendali';
    hCol = 'success';
    hRekom = 'Hormonmu masih dalam tahap aman, belum mencapai titik maksimal. Pertahankan olahraga, tidur cukup, dan jaga pandangan.';
  } else if (hPersen < 65) {
    hLevel = 'Mulai meningkat';
    hCol = 'warning';
    hRekom = 'Mulai naik. Perbanyak olahraga (kardio 3–5x/pekan), kurangi paparan konten dewasa, dan biasakan tidur cukup. Sebagai tambahan, mulai latih puasa Senin–Kamis.';
  } else if (hPersen < 85) {
    hLevel = 'Berlebih';
    hCol = 'danger';
    hRekom = 'Hormon cenderung berlebih. <strong>Sangat dianjurkan berpuasa sunnah (Senin–Kamis / Daud)</strong> sebagai perisai sesuai hadist, dibarengi olahraga rutin, menjaga pandangan, dan menjauhi pemicu (konten dewasa, waktu luang sendirian).';
  } else {
    hLevel = 'Sangat berlebih (titik maksimal)';
    hCol = 'danger';
    hRekom = 'Berada di titik maksimal. <strong>Wajib mengambil langkah serius: rutinkan shaum (Senin–Kamis atau Daud), olahraga harian, batasi gadget, perbanyak ibadah malam, dan jika sudah mampu — segera menikah</strong> sesuai anjuran Nabi ﷺ.';
  }

  hormonHtml = `
    <div class="card card-stat border-${hCol}"><div class="card-body">
      <div class="stat-label"><i class="bi bi-fire text-${hCol}"></i> Indeks Hormon Gairah Seksual</div>
      <div class="stat-value text-${hCol}">${hPersen}/100 <small class="text-muted">(${hLevel})</small></div>
      <div class="progress mt-1" style="height:8px"><div class="progress-bar bg-${hCol}" style="width:${hPersen}%"></div></div>
      <div class="small mt-2"><strong>Rekomendasi sunnah:</strong> ${hRekom}</div>
      <div class="small text-muted mt-1"><em>Catatan: indikator ini estimasi perilaku, bukan pengukuran kadar testosteron secara medis.</em></div>
    </div></div>`;
  }

  document.getElementById('kHasil').innerHTML = `
    <div class="row g-2">
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">BMI</div><div class="stat-value">${bmi.toFixed(1)} <small class="text-${col}">(${cat})</small></div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">Skor Sehat</div><div class="stat-value text-${skorBadge}">${skor}/100</div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">Berat Ideal</div><div class="stat-value">${ideal?ideal.toFixed(1)+' kg':'—'}</div></div></div></div>
      <div class="col-6"><div class="card card-stat"><div class="card-body"><div class="stat-label">BMR (kalori/hari)</div><div class="stat-value">${bmr?Math.round(bmr):'—'}</div></div></div></div>
      <div class="col-12"><div class="alert alert-${col} mb-0 small"><i class="bi bi-lightbulb"></i> ${tip}</div></div>
      ${hormonHtml ? '<div class="col-12">'+hormonHtml+'</div>' : ''}
      <div class="col-12"><h6 class="mt-2 mb-1"><i class="bi bi-clipboard2-heart text-danger"></i> Kesimpulan Riwayat Penyakit</h6>${kesimpulan}</div>
    </div>`;
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

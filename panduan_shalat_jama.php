<?php
/**
 * panduan_shalat_jama.php — Revisi (28 Juni 2026).
 * Menu baru di Hub Islami: Panduan Shalat Jama' (menggabungkan dua shalat)
 * untuk kondisi berkegiatan / di lapangan / safar, beserta syarat-syarat sah.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Panduan Shalat Jama\'';
$u = current_user();
$USER_PAKET = paket_user($u);
$IS_KOMUNITAS = ($USER_PAKET === 'komunitas');

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Panduan Shalat Jama'</li>
</ol></nav>

<?php if (!$IS_KOMUNITAS): ?>
  <div class="alert alert-warning small">
    Halaman ini bagian dari <b>Hub Islami</b> yang eksklusif untuk paket <b>KOMUNITAS</b>.
    Paket Anda saat ini: <b><?= htmlspecialchars(strtoupper($USER_PAKET)) ?></b>.
    <a href="/paket_upgrade.php" class="alert-link">Upgrade paket →</a>
  </div>
  <?php include __DIR__.'/includes/footer.php'; exit; ?>
<?php endif; ?>

<div class="hero-sport-islami hero-islami mb-3">
  <div class="hero-overlay">
    <span class="badge bg-light text-warning mb-2"><i class="bi bi-arrow-left-right"></i> SHALAT JAMA'</span>
    <h1 class="h3 mb-1 fw-bold">Panduan Shalat Jama' Saat Berkegiatan / Di Lapangan</h1>
    <p class="small mb-0 opacity-85">
      Menggabungkan dua shalat (Zuhur+Ashar / Maghrib+Isya) bagi musafir, pekerja lapangan,
      atau saat kondisi sulit — lengkap dengan syarat sah &amp; tata caranya.
    </p>
  </div>
</div>

<!-- Revisi (29 Juni 2026) — Spoiler / collapse keseluruhan konten. -->
<div class="d-flex justify-content-end mb-2">
  <button class="btn btn-sm btn-outline-warning" type="button"
          data-bs-toggle="collapse" data-bs-target="#spoilerShalatJama"
          aria-expanded="true" aria-controls="spoilerShalatJama">
    <i class="bi bi-eye-slash"></i> Sembunyikan / Tampilkan Panduan
  </button>
</div>
<div class="collapse show" id="spoilerShalatJama">
<div class="row g-3">
  <!-- ===== PENGERTIAN ===== -->
  <div class="col-12">
    <div class="card shadow-sm border-warning">
      <div class="card-header bg-warning-subtle text-warning-emphasis">
        <i class="bi bi-question-circle-fill"></i> <strong>Apa itu Shalat Jama'?</strong>
      </div>
      <div class="card-body small">
        <p class="mb-2">
          <b>Shalat Jama'</b> adalah menggabungkan dua shalat fardhu yang berurutan ke dalam
          satu waktu. Hanya dua pasangan shalat yang boleh dijama':
        </p>
        <ul class="mb-2">
          <li><b>Zuhur + Ashar</b> (digabung di waktu Zuhur = <em>Jama' Taqdim</em>, atau di waktu Ashar = <em>Jama' Takhir</em>)</li>
          <li><b>Maghrib + Isya</b> (digabung di waktu Maghrib = <em>Taqdim</em>, atau di waktu Isya = <em>Takhir</em>)</li>
        </ul>
        <p class="mb-0">
          <b>Subuh tidak boleh dijama'</b> dengan shalat lain. Bila digabung dengan <em>qashar</em>
          (memendekkan rakaat 4 → 2) saat safar disebut <b>Jama' Qashar</b>.
        </p>
      </div>
    </div>
  </div>

  <!-- ===== SYARAT SAH ===== -->
  <div class="col-md-6">
    <div class="card shadow-sm border-success h-100">
      <div class="card-header bg-success-subtle text-success-emphasis">
        <i class="bi bi-check2-square"></i> <strong>Syarat Sah Shalat Jama'</strong>
      </div>
      <div class="card-body small">
        <h6 class="fw-bold text-success mb-1">A. Sebab dibolehkannya jama' (salah satu):</h6>
        <ol class="mb-2">
          <li><b>Safar</b> (perjalanan ≥ 80 km dengan tujuan mubah, bukan maksiat).</li>
          <li><b>Hujan deras</b> yang menyulitkan keluar rumah / ke masjid (HR. Bukhari no. 543).</li>
          <li><b>Sakit</b> yang memberatkan jika shalat tepat waktu (pendapat jumhur).</li>
          <li><b>Hajat / kebutuhan mendesak</b> selama tidak menjadikannya kebiasaan
            (HR. Muslim no. 705 — Ibnu Abbas).</li>
          <li><b>Pekerjaan lapangan / dinas</b> yang sulit ditinggalkan (operasi, perang,
            pertolongan SAR, profesi yang menuntut konsentrasi penuh).</li>
        </ol>

        <h6 class="fw-bold text-success mb-1">B. Syarat tambahan Jama' Taqdim:</h6>
        <ol class="mb-2">
          <li>Tertib — shalat pertama (Zuhur / Maghrib) dilakukan dulu.</li>
          <li>Niat jama' dilakukan saat shalat pertama (sebelum salam).</li>
          <li>Muwalah — antara dua shalat tidak ada jeda lama selain iqamah dan ibadah ringan.</li>
          <li>Sebab jama' (safar / hujan / sakit) masih berlangsung saat shalat kedua dimulai.</li>
        </ol>

        <h6 class="fw-bold text-success mb-1">C. Syarat tambahan Jama' Takhir:</h6>
        <ol class="mb-0">
          <li>Niat jama' takhir dilakukan saat masih di waktu shalat pertama (sebelum waktunya habis).</li>
          <li>Sebab jama' masih ada sampai selesai kedua shalat di waktu shalat kedua.</li>
          <li>Tertib (boleh dibalik bila ada uzur, tapi sebaiknya tetap urut).</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- ===== TATA CARA ===== -->
  <div class="col-md-6">
    <div class="card shadow-sm border-primary h-100">
      <div class="card-header bg-primary-subtle text-primary-emphasis">
        <i class="bi bi-list-ol"></i> <strong>Tata Cara Praktis</strong>
      </div>
      <div class="card-body small">
        <h6 class="fw-bold text-primary mb-1">1. Jama' Taqdim Zuhur + Ashar (di waktu Zuhur)</h6>
        <ol class="mb-2">
          <li>Adzan + iqamah Zuhur.</li>
          <li>Shalat Zuhur 4 rakaat (atau 2 rakaat bila qashar) dengan niat jama' taqdim.</li>
          <li>Iqamah lagi (tanpa adzan ulang).</li>
          <li>Langsung shalat Ashar 4 rakaat (atau 2 rakaat bila qashar). Tidak ada shalat sunnah di antaranya.</li>
        </ol>

        <h6 class="fw-bold text-primary mb-1">2. Jama' Takhir Maghrib + Isya (di waktu Isya)</h6>
        <ol class="mb-2">
          <li>Saat masih di waktu Maghrib, berniat: <em>"Aku akan menjama' Maghrib ke waktu Isya."</em></li>
          <li>Setelah masuk waktu Isya: adzan + iqamah Maghrib.</li>
          <li>Shalat Maghrib 3 rakaat (tidak diqashar).</li>
          <li>Iqamah → shalat Isya 4 / 2 rakaat. Langsung berurutan.</li>
        </ol>

        <h6 class="fw-bold text-primary mb-1">3. Niat Singkat (di hati cukup)</h6>
        <p class="mb-0">
          <em>"Ushalli fardhazh-zhuhri arba'a raka'ātin majmū'an ilaihil-'ashru jam'a taqdīm lillāhi ta'ālā."</em><br>
          (Aku niat shalat Zuhur 4 rakaat dijama' bersama Ashar, jama' taqdim, karena Allah Ta'ala.)
        </p>
      </div>
    </div>
  </div>

  <!-- ===== TABEL RINGKAS ===== -->
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi bi-table text-info"></i> <strong>Tabel Ringkas Kombinasi Jama'</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Kombinasi</th>
                <th>Waktu Pelaksanaan</th>
                <th>Jenis Jama'</th>
                <th>Boleh + Qashar?</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody class="small">
              <tr><td>Zuhur + Ashar</td><td>Waktu Zuhur</td><td>Taqdim</td><td>Ya (jika safar)</td><td>Niat sebelum salam Zuhur.</td></tr>
              <tr><td>Zuhur + Ashar</td><td>Waktu Ashar</td><td>Takhir</td><td>Ya (jika safar)</td><td>Niat takhir saat masih di waktu Zuhur.</td></tr>
              <tr><td>Maghrib + Isya</td><td>Waktu Maghrib</td><td>Taqdim</td><td>Isya saja yang boleh diqashar</td><td>Maghrib tetap 3 rakaat.</td></tr>
              <tr><td>Maghrib + Isya</td><td>Waktu Isya</td><td>Takhir</td><td>Isya saja yang boleh diqashar</td><td>Maghrib tetap 3 rakaat.</td></tr>
              <tr class="table-warning"><td>Subuh</td><td>—</td><td>—</td><td>—</td><td><b>Tidak boleh dijama'</b> dengan shalat manapun.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== CATATAN PENTING ===== -->
  <div class="col-12">
    <div class="card shadow-sm border-danger">
      <div class="card-header bg-danger-subtle text-danger-emphasis">
        <i class="bi bi-exclamation-triangle-fill"></i> <strong>Catatan Penting</strong>
      </div>
      <div class="card-body small">
        <ul class="mb-0">
          <li><b>Jangan menjadikan jama' sebagai kebiasaan</b> tanpa uzur syar'i. Rasulullah ﷺ tidak
            pernah meninggalkan shalat tepat waktu kecuali ada sebab.</li>
          <li>Bagi pekerja lapangan rutin (proyek, dinas malam), upayakan shalat di awal/akhir waktu
            sebelum mengambil rukhshah jama'.</li>
          <li>Saat di kendaraan (mobil, kapal, pesawat) yang tidak bisa berhenti: shalat tetap wajib
            dengan duduk / menghadap arah perjalanan; menjama' dianjurkan setelah turun bila masih
            di waktu shalat kedua.</li>
          <li>Wanita haid / nifas tidak menjama' shalat yang ditinggalkan saat berhalangan — shalat
            tersebut gugur, bukan diqadha.</li>
          <li>Lihat juga: <a href="/panduan_adzan.php">Panduan Adzan di Lapangan</a> dan
            <a href="/jadwal_sholat.php">Jadwal Sholat</a> kota Anda.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
</div><!-- /#spoilerShalatJama -->

<!-- Revisi Juli 2026 — spoiler individual per section (Apa itu, Syarat, Tata Cara, Tabel, Catatan). -->
<script>
(function(){
  var root = document.getElementById('spoilerShalatJama');
  if (!root) return;
  var cards = root.querySelectorAll('.card');
  cards.forEach(function(card, i){
    var head = card.querySelector('.card-header');
    var body = card.querySelector('.card-body, .card-body.small, .card-body.p-0');
    if (!head || !body) return;
    var id = 'sjSec_' + i;
    body.id = id;
    body.classList.add('collapse','show');
    head.style.cursor = 'pointer';
    head.setAttribute('role','button');
    head.setAttribute('data-bs-toggle','collapse');
    head.setAttribute('data-bs-target','#'+id);
    head.setAttribute('aria-expanded','true');
    head.setAttribute('aria-controls', id);
    var chev = document.createElement('i');
    chev.className = 'bi bi-chevron-down float-end';
    head.appendChild(chev);
    body.addEventListener('hidden.bs.collapse', function(){ chev.className='bi bi-chevron-right float-end'; });
    body.addEventListener('shown.bs.collapse',  function(){ chev.className='bi bi-chevron-down float-end';  });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>


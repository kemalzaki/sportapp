<?php
/**
 * jam_tidur_islami.php — Revisi R26 (28 Juni 2026).
 * Menu baru di Hub Islami: "Jam Tidur yang Disarankan dan yang Dilarang"
 * menurut sunnah Rasulullah ﷺ + tinjauan sains tidur modern.
 *
 * Halaman statis (tidak butuh tabel DB baru). Mengikuti pola halaman
 * Islami lain (header.php / footer.php, kelas Bootstrap, ikon bi-*).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Jam Tidur — Sunnah & Sains';
$u = current_user();
$USER_PAKET = paket_user($u);
$IS_KOMUNITAS = ($USER_PAKET === 'komunitas');

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Jam Tidur Disarankan &amp; Dilarang</li>
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
    <span class="badge bg-light text-success mb-2"><i class="bi bi-moon-stars-fill"></i> SUNNAH TIDUR</span>
    <h1 class="h3 mb-1 fw-bold">Jam Tidur yang Disarankan &amp; yang Dilarang</h1>
    <p class="small mb-0 opacity-85">
      Panduan tidur sesuai sunnah Rasulullah ﷺ dan tinjauan ilmu kesehatan modern (sirkadian, hormon melatonin &amp; growth hormone).
    </p>
  </div>
</div>

<div class="row g-3 jt-spoilers">
  <!-- ========== DISARANKAN ========== -->
  <div class="col-lg-6">
    <div class="card shadow-sm border-success h-100">
      <div class="card-header bg-success-subtle text-success-emphasis">
        <i class="bi bi-check2-circle"></i> <strong>Jam Tidur yang DISARANKAN</strong>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <h6 class="fw-bold text-success mb-1"><i class="bi bi-moon-fill"></i> 1. Tidur lebih awal setelah Isya</h6>
          <p class="small mb-1">
            Rasulullah ﷺ tidak suka bercakap-cakap setelah Isya (HR. Bukhari no. 568, Muslim no. 647).
            <b>Waktu ideal: 21.00 – 22.00 WIB</b> agar bangun di sepertiga malam.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-heart-pulse text-danger"></i> Sains: hormon <b>melatonin</b> mulai disekresi pukul 21.00,
            puncak 02.00–04.00. Tidur sebelum 23.00 memaksimalkan <b>growth hormone</b> &amp; perbaikan sel.
          </p>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-success mb-1"><i class="bi bi-stars"></i> 2. Bangun di sepertiga malam terakhir (Tahajud)</h6>
          <p class="small mb-1">
            "Rabb kita turun ke langit dunia pada sepertiga malam terakhir" (HR. Bukhari no. 1145, Muslim no. 758).
            <b>Waktu ideal: 02.30 – 04.00 WIB</b>, ambil wudhu, shalat Tahajud 2–11 rakaat.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-brain text-info"></i> Sains: bangun di akhir siklus tidur REM membuat pikiran jernih,
            tekanan darah &amp; kortisol mulai naik secara alami untuk persiapan beraktivitas.
          </p>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-success mb-1"><i class="bi bi-sun"></i> 3. Qailulah (tidur siang singkat)</h6>
          <p class="small mb-1">
            "Tidur sianglah, sesungguhnya setan-setan tidak tidur siang" (HR. Abu Nu'aim, hasan).
            <b>Waktu ideal: 12.30 – 13.30 WIB, 15–30 menit</b> setelah Zuhur.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-battery-charging text-success"></i> Sains: <b>power nap</b> 20 menit meningkatkan
            kewaspadaan 54% &amp; menurunkan risiko penyakit jantung (NASA, Harvard Medical School).
          </p>
        </div>

        <div class="mb-0">
          <h6 class="fw-bold text-success mb-1"><i class="bi bi-clock-history"></i> 4. Total tidur 6–8 jam, dipecah malam + qailulah</h6>
          <p class="small mb-0">
            Contoh ideal: <b>22.00 – 03.00 (5 jam) + qailulah 30 menit</b> = total 5.5 jam berkualitas
            tinggi karena selaras dengan ritme sirkadian &amp; sunnah Nabi ﷺ.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== DILARANG / MAKRUH ========== -->
  <div class="col-lg-6">
    <div class="card shadow-sm border-danger h-100">
      <div class="card-header bg-danger-subtle text-danger-emphasis">
        <i class="bi bi-x-octagon-fill"></i> <strong>Jam Tidur yang DILARANG / MAKRUH</strong>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <h6 class="fw-bold text-danger mb-1"><i class="bi bi-sunrise"></i> 1. Tidur setelah Subuh hingga matahari terbit</h6>
          <p class="small mb-1">
            "Ya Allah, berkahilah umatku di waktu paginya" (HR. Abu Dawud no. 2606, Tirmidzi no. 1212, sahih).
            Tidur subuh = <b>menutup pintu rezeki</b> (perkataan Ibnu Abbas radhiyallahu 'anhuma).
            <b>Hindari tidur 04.30 – 06.30 WIB</b>.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-droplet-half text-warning"></i> Sains: tidur saat kortisol &amp; testosteron pagi
            sedang naik mengganggu siklus, menyebabkan <b>grogi, sakit kepala,</b> &amp; metabolisme lambat.
          </p>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-danger mb-1"><i class="bi bi-moon"></i> 2. Begadang setelah Isya (tanpa kebutuhan syar'i)</h6>
          <p class="small mb-1">
            Rasulullah ﷺ membenci tidur sebelum Isya &amp; ngobrol setelah Isya (HR. Bukhari no. 568).
            <b>Hindari begadang 23.00 ke atas</b> kecuali untuk ibadah, belajar, menjaga keluarga, atau pekerjaan mendesak.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-graph-down-arrow text-danger"></i> Sains: begadang menurunkan growth hormone hingga 70%,
            menaikkan kortisol, memicu obesitas, diabetes tipe 2, &amp; gangguan mood.
          </p>
        </div>

        <div class="mb-3">
          <h6 class="fw-bold text-danger mb-1"><i class="bi bi-sun-fill"></i> 3. Tidur antara Ashar – Maghrib</h6>
          <p class="small mb-1">
            "Barangsiapa tidur setelah Ashar lalu hilang akalnya, jangan menyalahkan kecuali dirinya sendiri"
            (HR. Abu Ya'la — hadits ini diperdebatkan kesahihannya, namun para ulama tetap menganjurkan menghindarinya).
            <b>Hindari tidur 15.30 – 17.45 WIB</b>.
          </p>
          <p class="small text-muted mb-0">
            <i class="bi bi-clouds text-secondary"></i> Sains: tidur sore mengganggu <b>sleep pressure</b> →
            sulit tidur malam, gampang pusing &amp; mual saat bangun (sleep inertia berat).
          </p>
        </div>

        <div class="mb-0">
          <h6 class="fw-bold text-danger mb-1"><i class="bi bi-emoji-dizzy"></i> 4. Tidur tengkurap</h6>
          <p class="small mb-0">
            "Sesungguhnya itu adalah cara tidur yang dibenci Allah" (HR. Abu Dawud no. 5040, hasan).
            Posisi sunnah: <b>miring ke kanan</b>, tangan kanan di bawah pipi.
            Sains: tidur tengkurap menekan paru &amp; jantung, memperburuk GERD &amp; nyeri leher.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== JADWAL HARIAN IDEAL ========== -->
  <div class="col-12">
    <div class="card shadow-sm border-primary">
      <div class="card-header bg-primary-subtle text-primary-emphasis">
        <i class="bi bi-calendar2-week"></i> <strong>Contoh Jadwal Harian Ideal (WIB)</strong>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:120px">Waktu</th>
                <th>Aktivitas</th>
                <th style="width:140px" class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $jadwal = [
                ['04.00 – 04.30','Bangun Tahajud (2–11 rakaat) + dzikir','sunnah','success'],
                ['04.30 – 05.00','Shalat Subuh berjamaah','wajib','success'],
                ['05.00 – 06.30','Dzikir pagi, baca Qur\'an, olahraga ringan — JANGAN tidur','wajib aktif','success'],
                ['06.30 – 12.00','Bekerja / belajar produktif','disarankan','success'],
                ['12.00 – 12.30','Shalat Zuhur','wajib','success'],
                ['12.30 – 13.00','Qailulah (tidur siang 15–30 menit)','sunnah','success'],
                ['13.00 – 15.00','Lanjut kerja','disarankan','success'],
                ['15.00 – 15.30','Shalat Ashar','wajib','success'],
                ['15.30 – 17.45','Aktivitas ringan, olahraga sore — JANGAN tidur','makruh kalau tidur','danger'],
                ['17.45 – 18.30','Shalat Maghrib + dzikir petang','wajib','success'],
                ['18.30 – 19.30','Makan malam ringan, waktu keluarga','disarankan','success'],
                ['19.30 – 20.00','Shalat Isya berjamaah','wajib','success'],
                ['20.00 – 21.30','Murajaah, baca buku, persiapan tidur','disarankan','success'],
                ['21.30 – 22.00','TIDUR — matikan layar (cahaya biru hambat melatonin)','sunnah','success'],
                ['22.00 – 04.00','Tidur nyenyak (6 jam berkualitas)','—','secondary'],
              ];
              foreach ($jadwal as $j):
                $cls = 'bg-'.$j[3].'-subtle text-'.$j[3].'-emphasis';
              ?>
                <tr>
                  <td class="small fw-bold"><?= htmlspecialchars($j[0]) ?></td>
                  <td class="small"><?= htmlspecialchars($j[1]) ?></td>
                  <td class="text-center"><span class="badge <?= $cls ?>"><?= htmlspecialchars($j[2]) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-info-circle"></i>
          Jadwal di atas indikatif untuk wilayah WIB Indonesia bagian barat (Jakarta dsk).
          Cek <a href="/jadwal_sholat.php">Jadwal Sholat</a> dan
          <a href="/monitoring_tahajud.php">Monitoring Tahajud &amp; Duha</a> untuk waktu presisi
          berdasarkan kota Anda.
        </div>
      </div>
    </div>
  </div>

  <!-- ========== ADAB TIDUR ========== -->
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi bi-bookmark-star text-warning"></i> <strong>Adab Tidur Sunnah Rasulullah ﷺ</strong></div>
      <div class="card-body">
        <ol class="small mb-0">
          <li>Berwudhu sebelum tidur (HR. Bukhari no. 247, Muslim no. 2710).</li>
          <li>Tidur miring ke kanan, tangan kanan di bawah pipi (HR. Bukhari no. 6314).</li>
          <li>Membaca ayat Kursi, Al-Ikhlas, Al-Falaq, An-Naas (3x), tiup ke telapak tangan lalu usap ke seluruh tubuh.</li>
          <li>Membaca doa tidur: <em>Bismika-llahumma amuutu wa ahyaa</em>.</li>
          <li>Mematikan lampu &amp; api (HR. Bukhari no. 6296). Sains modern: ruangan gelap meningkatkan melatonin.</li>
          <li>Menutup pintu, menutup bejana, menutupi makanan (HR. Muslim no. 2012) — kebersihan &amp; keamanan.</li>
          <li>Membaca doa bangun tidur: <em>Alhamdulillāhilladzī ahyānā ba'da mā amātanā wa ilaihin nusyūr</em>.</li>
          <li>Bersiwak / sikat gigi setelah bangun (HR. Bukhari no. 245).</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<style>
/* Revisi (28 Juni 2026) — Spoiler/akordeon untuk 4 section utama. */
.jt-spoilers > [class*="col-"] > .card > .card-header{cursor:pointer; user-select:none;}
.jt-spoilers > [class*="col-"] > .card > .card-header::after{
  content:"\25BC"; float:right; transition:transform .25s ease; font-size:.8em; opacity:.7;
}
.jt-spoilers > [class*="col-"] > .card.collapsed > .card-header::after{transform:rotate(-90deg);}
.jt-spoilers > [class*="col-"] > .card.collapsed > .card-body{display:none;}
</style>
<script>
(function(){
  document.querySelectorAll('.jt-spoilers > [class*="col-"] > .card').forEach(function(c, i){
    // Default: kartu pertama terbuka, sisanya collapsed
    if (i > 0) c.classList.add('collapsed');
    var h = c.querySelector(':scope > .card-header');
    if (!h) return;
    h.addEventListener('click', function(){ c.classList.toggle('collapsed'); });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

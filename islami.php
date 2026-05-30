<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/cities_data.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/shalat_data.php';
send_security_headers(); require_login();
$pageTitle = 'Hub Islami';
$u = current_user();

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'challenge_done') {
        $key = preg_replace('/[^a-z_]/','', $_POST['key'] ?? '');
        if ($key !== '') {
            islami_log_challenge((int)$u['id'], $key, $_POST['catatan'] ?? null);
            if ($key === 'ayat_harian') islami_touch_streak((int)$u['id'], 'quran_done');
            if ($key === 'dzikir_pagi') islami_touch_streak((int)$u['id'], 'dzikir_pagi');
            if ($key === 'dzikir_petang') islami_touch_streak((int)$u['id'], 'dzikir_petang');
            if ($key === 'subuh_walk') islami_touch_streak((int)$u['id'], 'subuh_walk');
            if ($key === 'doa') islami_touch_streak((int)$u['id'], 'doa_done');
        }
        $_SESSION['flash'] = 'Tercatat. Semoga istiqamah.';
        header('Location: /islami.php'); exit;
    } elseif ($a === 'save_pref') {
        islami_set_pref((int)$u['id'], [
            'kota' => substr(trim($_POST['kota'] ?? 'Jakarta'),0,60),
            'negara' => substr(trim($_POST['negara'] ?? 'Indonesia'),0,40),
            'mode_tenang' => isset($_POST['mode_tenang']) ? 1 : 0,
        ]);
        $_SESSION['flash'] = 'Preferensi disimpan.';
        header('Location: /islami.php'); exit;
    } elseif ($a === 'hide_sapa') {
        islami_set_pref((int)$u['id'], ['hide_sapa' => 1]);
        header('Location: /index.php'); exit;
    }
}

$pref = $u ? islami_pref((int)$u['id']) : null;
$streak = $u ? islami_streak_count((int)$u['id']) : 0;
$badges = $u ? db_all("SELECT badge_key, earned_at FROM islami_badges WHERE user_id=$1 ORDER BY earned_at DESC", [(int)$u['id']]) : [];

$hijri = masehi_ke_hijriyah();
$ramadhan = hijri_event_to_gregorian(9, 1);
$iedAdha  = hijri_event_to_gregorian(12, 10);

include __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/sport-islami.css">
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<div class="hero-sport-islami hero-islami mb-3">
  <div class="hero-overlay d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
      <span class="badge bg-light text-success mb-2"><i class="bi bi-stars"></i> HUB ISLAMI</span>
      <h1 class="h3 mb-1 fw-bold">Assalāmu‘alaikum, semoga hari ini berkah</h1>
      <p class="small mb-0 opacity-85">Qur'an · Sholat · Dzikir · Doa · Kalender Hijriyah · Kompas Kiblat</p>
    </div>
    <span class="badge bg-light text-success fs-6 px-3 py-2"><i class="bi bi-moon-stars"></i> <?= $hijri['hari'] ?> <?= htmlspecialchars(hijriyah_nama_bulan($hijri['bulan'])) ?> <?= $hijri['tahun'] ?> H</span>
  </div>
</div>

<!-- ====== KOMPAS KIBLAT (revisi 31 Mei 2026 — kompas bergerak sesuai gerakan HP) ====== -->
<div class="card shadow-sm mb-3 border-success qibla-card">
  <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-compass"></i> <strong>KOMPAS KIBLAT</strong> — Arahkan HP ke arah kiblat</span>
    <button id="qiblaPerm" type="button" class="btn btn-sm btn-light d-none"><i class="bi bi-shield-check"></i> Aktifkan Sensor</button>
  </div>
  <div class="card-body text-center">
    <div class="qibla-wrap mx-auto">
      <div class="qibla-dial" id="qiblaDial">
        <span class="dial-n">N</span><span class="dial-e">E</span><span class="dial-s">S</span><span class="dial-w">W</span>
        <div class="dial-ticks"></div>
      </div>
      <div class="qibla-needle" id="qiblaNeedle">
        <div class="needle-arrow"></div>
        <div class="needle-kaaba">🕋</div>
      </div>
      <div class="qibla-center"></div>
    </div>
    <div class="mt-3 small">
      <div><span class="text-muted">Arah kiblat dari lokasi Anda:</span> <strong id="qiblaBearing">—</strong></div>
      <div><span class="text-muted">Arah HP saat ini (kompas):</span> <strong id="qiblaHeading">—</strong></div>
      <div id="qiblaStatus" class="text-muted">Mendeteksi lokasi & sensor orientasi…</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><a href="/quran.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book fs-2 text-success"></i><div class="fw-semibold mt-1">Al-Qur'an Digital</div></div></a></div>
  <div class="col-md-3"><a href="/jadwal_sholat.php" class="card text-decoration-none h-100 border-primary"><div class="card-body text-center"><i class="bi bi-clock-history fs-2 text-primary"></i><div class="fw-semibold mt-1">Jadwal Sholat</div><div class="small text-muted">Waktu sholat 5 waktu</div></div></a></div>
  <div class="col-md-3"><a href="/doa.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-chat-quote fs-2 text-warning"></i><div class="fw-semibold mt-1">Doa Harian</div></div></a></div>
  <div class="col-md-3"><a href="/dzikir.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-brightness-high fs-2 text-info"></i><div class="fw-semibold mt-1">Dzikir Pagi & Petang</div></div></a></div>

  <div class="col-md-3"><a href="/kalender_hijriyah.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-calendar3 fs-2 text-success"></i><div class="fw-semibold mt-1">Kalender Hijriyah</div></div></a></div>
  <div class="col-md-3"><a href="/challenge.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-trophy fs-2 text-warning"></i><div class="fw-semibold mt-1">Challenge Islami</div></div></a></div>
  <div class="col-md-3"><a href="/leaderboard_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-bar-chart-line fs-2 text-danger"></i><div class="fw-semibold mt-1">Leaderboard Amal</div></div></a></div>
  <div class="col-md-3"><a href="/statistik_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-graph-up fs-2 text-primary"></i><div class="fw-semibold mt-1">Statistik & Streak</div></div></a></div>

  <div class="col-md-3"><a href="/kajian.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-journal-bookmark fs-2 text-info"></i><div class="fw-semibold mt-1">Kajian Literatur Buku</div></div></a></div>
  <div class="col-md-3"><a href="/artikel_sunnah.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-journal-text fs-2 text-success"></i><div class="fw-semibold mt-1">Artikel Sunnah</div></div></a></div>
  <div class="col-md-3"><a href="/feed_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-chat-dots fs-2 text-warning"></i><div class="fw-semibold mt-1">Feed Quote Komunitas</div></div></a></div>
  <div class="col-md-3"><a href="/doa_antar_member.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-heart fs-2 text-danger"></i><div class="fw-semibold mt-1">Saling Mendoakan</div></div></a></div>

  <div class="col-md-3"><a href="/hadist.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book-half fs-2 text-success"></i><div class="fw-semibold mt-1">Ensiklopedia Hadist</div></div></a></div>
  <div class="col-md-3"><a href="/sejarah_nabi.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book fs-2 text-warning"></i><div class="fw-semibold mt-1">Sejarah Nabi &amp; Rasul</div><div class="small text-muted">25 Nabi &amp; Rasul</div></div></a></div>
</div>

<!-- ====== TATA CARA SHALAT (Bacaan + Latin + Arti) ====== -->
<div class="card shadow-sm mb-3 border-primary">
  <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-person-arms-up"></i> <strong>TATA CARA SHALAT</strong> — Urutan, Bacaan Arab, Latin & Terjemah</span>
    <small class="opacity-75">Panduan ringkas; untuk pendalaman, rujuk kitab fiqih</small>
  </div>
  <div class="card-body">
    <div class="accordion" id="accTataCaraShalat">
      <?php foreach ($SHALAT_TATA_CARA as $i=>$t): ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="hTC<?= $i ?>">
          <button class="accordion-button <?= $i>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse"
                  data-bs-target="#cTC<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="cTC<?= $i ?>">
            <strong><?= htmlspecialchars($t['judul']) ?></strong>
          </button>
        </h2>
        <div id="cTC<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="hTC<?= $i ?>" data-bs-parent="#accTataCaraShalat">
          <div class="accordion-body">
            <div class="mb-2 text-end" dir="rtl" lang="ar" style="font-size:1.5rem;line-height:2.2;font-family:'Scheherazade New','Amiri','Times New Roman',serif;"><?= htmlspecialchars($t['arab']) ?></div>
            <div class="mb-2"><span class="badge bg-primary-subtle text-primary me-1">Latin</span><em><?= htmlspecialchars($t['latin']) ?></em></div>
            <div class="small"><span class="badge bg-success-subtle text-success me-1">Arti</span><?= htmlspecialchars($t['arti']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ====== SHALAT SUNNAH RAWATIB ====== -->
<div class="card shadow-sm mb-3 border-warning">
  <div class="card-header bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-between">
    <span><i class="bi bi-stars"></i> <strong>SHALAT SUNNAH RAWATIB</strong> — Sunnah Mengiringi Shalat Fardhu</span>
    <small class="opacity-75">Muakkad = sangat ditekankan</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>Shalat Fardhu</th>
            <th>Qabliyah (Sebelum)</th>
            <th>Ba‘diyah (Sesudah)</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($SHALAT_RAWATIB as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['waktu']) ?></strong></td>
              <td><?= htmlspecialchars($r['qabliyah']) ?></td>
              <td><?= htmlspecialchars($r['badiyah']) ?></td>
              <td class="small text-muted"><?= htmlspecialchars($r['catatan']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 small text-muted border-top">
      <strong>Ringkasan rawatib muakkad (12 rakaat):</strong>
      2 sebelum Subuh · 4 sebelum Zhuhur · 2 sesudah Zhuhur · 2 sesudah Maghrib · 2 sesudah Isya.
      Nabi ﷺ bersabda: "Barangsiapa shalat 12 rakaat dalam sehari semalam, Allah bangunkan baginya rumah di surga." (HR. Muslim)
    </div>
  </div>
</div>

<!-- ====== SHALAT SUNNAH LAINNYA: DUHA & TAHAJUD (revisi 31 Mei 2026) ====== -->
<?php if (!empty($SHALAT_SUNNAH_LAIN)): ?>
<div class="card shadow-sm mb-3 border-info">
  <div class="card-header bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between">
    <span><i class="bi bi-sun"></i> <strong>SHALAT SUNNAH DUHA &amp; TAHAJUD</strong></span>
    <small class="opacity-75">Penambah pahala &amp; pintu rezeki</small>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($SHALAT_SUNNAH_LAIN as $sh): ?>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100 bg-light-subtle">
          <h6 class="fw-bold text-info-emphasis mb-2"><i class="bi bi-moon-stars"></i> <?= htmlspecialchars($sh['nama']) ?></h6>
          <div class="small mb-2"><strong>Waktu:</strong> <?= htmlspecialchars($sh['waktu']) ?></div>
          <div class="small mb-2"><strong>Jumlah Rakaat:</strong> <?= htmlspecialchars($sh['rakaat']) ?></div>
          <div class="small mb-2"><strong>Tata Cara:</strong>
            <ol class="mb-1 ps-3">
              <?php foreach ($sh['tata_cara'] as $tc): ?><li><?= htmlspecialchars($tc) ?></li><?php endforeach; ?>
            </ol>
          </div>
          <div class="small mb-2"><strong>Doa Setelah Shalat:</strong>
            <div class="p-2 bg-white border rounded mt-1" style="font-family:'Scheherazade New','Amiri',serif;font-size:1.1rem;line-height:1.9;direction:rtl;text-align:right"><?= htmlspecialchars($sh['doa']) ?></div>
          </div>
          <div class="small text-muted"><strong>Fadhilah:</strong> <?= htmlspecialchars($sh['fadhilah']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
  </div>
</div>

<!-- ====== RUKUN ISLAM ====== -->
<?php
$RUKUN_ISLAM = [
  [
    'judul' => '1. Syahadat',
    'icon'  => 'bi-patch-check-fill',
    'warna' => 'success',
    'rukun' => ['Mengucapkan dua kalimat syahadat: <em>Asyhadu an laa ilaaha illallaah wa asyhadu anna Muhammadar rasuulullaah</em>.'],
    'syarat_sah' => [
      'Mengetahui makna dan kandungannya.',
      'Yakin tanpa keraguan.',
      'Menerima konsekuensinya, tidak menolaknya.',
      'Tunduk (inqiyad) pada tuntutannya.',
      'Jujur (shidq) dari hati, bukan sekadar lisan.',
      'Ikhlas, bukan karena riya atau dunia.',
      'Mencintai kalimat tauhid dan ahlinya.',
    ],
    'syarat_wajib' => [
      'Berakal (mukallaf).',
      'Baligh atau sudah mumayyiz.',
      'Mendengar dakwah Islam.',
      'Mampu mengucapkan (kecuali bisu, cukup dengan isyarat/hati).',
    ],
  ],
  [
    'judul' => '2. Sholat',
    'icon'  => 'bi-person-arms-up',
    'warna' => 'primary',
    'rukun' => [
      'Niat.', 'Berdiri bagi yang mampu.', 'Takbiratul ihram.',
      'Membaca Al-Fatihah pada setiap rakaat.',
      'Ruku dengan thuma\'ninah.', 'I\'tidal dengan thuma\'ninah.',
      'Sujud dua kali dengan thuma\'ninah.', 'Duduk di antara dua sujud.',
      'Duduk tasyahud akhir & membacanya.',
      'Membaca shalawat atas Nabi ﷺ pada tasyahud akhir.',
      'Salam pertama.', 'Tertib (urut).',
    ],
    'syarat_sah' => [
      'Suci dari hadats kecil dan besar.',
      'Suci badan, pakaian, dan tempat dari najis.',
      'Menutup aurat.',
      'Menghadap kiblat.',
      'Telah masuk waktu sholat.',
      'Mengetahui tata cara sholat.',
      'Meninggalkan pembatal sholat.',
    ],
    'syarat_wajib' => [
      'Islam.', 'Baligh.', 'Berakal.', 'Suci dari haid & nifas.',
      'Telah sampai dakwah/seruan sholat.',
    ],
  ],
  [
    'judul' => '3. Zakat',
    'icon'  => 'bi-coin',
    'warna' => 'warning',
    'rukun' => [
      'Niat zakat karena Allah.',
      'Memindahkan kepemilikan harta zakat kepada yang berhak (8 ashnaf).',
    ],
    'syarat_sah' => [
      'Niat saat menunaikannya.',
      'Diberikan kepada mustahik yang berhak (8 ashnaf).',
      'Tamlik: menjadi milik penuh mustahik.',
      'Harta zakat berasal dari sumber yang halal.',
    ],
    'syarat_wajib' => [
      'Islam.', 'Merdeka.', 'Kepemilikan penuh atas harta.',
      'Harta mencapai nishab.',
      'Telah mencapai haul (1 tahun hijriyah) — kecuali zakat pertanian & rikaz.',
      'Harta berkembang (produktif) atau berpotensi berkembang.',
      'Lebih dari kebutuhan pokok.', 'Bebas dari hutang yang menggugurkan nishab.',
    ],
  ],
  [
    'judul' => '4. Puasa Ramadhan',
    'icon'  => 'bi-moon-stars-fill',
    'warna' => 'info',
    'rukun' => [
      'Niat puasa di malam hari (sebelum fajar) untuk puasa wajib.',
      'Menahan diri dari segala pembatal puasa sejak terbit fajar sampai terbenam matahari.',
    ],
    'syarat_sah' => [
      'Islam.', 'Berakal & mumayyiz.',
      'Suci dari haid dan nifas.',
      'Pada waktu yang dibolehkan berpuasa (bukan hari raya / tasyrik).',
      'Niat (untuk puasa wajib: dilakukan sebelum fajar).',
    ],
    'syarat_wajib' => [
      'Islam.', 'Baligh.', 'Berakal.', 'Mampu (sehat, tidak sakit berat).',
      'Mukim (tidak dalam safar yang memberatkan).',
      'Suci dari haid & nifas.',
    ],
  ],
  [
    'judul' => '5. Haji (bagi yang mampu)',
    'icon'  => 'bi-geo-alt-fill',
    'warna' => 'danger',
    'rukun' => [
      'Ihram (dengan niat haji).',
      'Wukuf di Arafah (9 Dzulhijjah).',
      'Thawaf Ifadhah.',
      'Sa\'i antara Shafa dan Marwah.',
      'Tahallul (mencukur/memendekkan rambut).',
      'Tertib pada sebagian besar rukun.',
    ],
    'syarat_sah' => [
      'Dilaksanakan pada waktunya (bulan-bulan haji: Syawal, Dzulqa\'dah, 10 hari Dzulhijjah).',
      'Dilakukan di tempat-tempat manasik (Mekkah, Arafah, Muzdalifah, Mina).',
      'Mengikuti urutan manasik sesuai tuntunan.',
      'Berihram dari miqat.',
    ],
    'syarat_wajib' => [
      'Islam.', 'Baligh.', 'Berakal.', 'Merdeka.',
      'Mampu (istitha\'ah): fisik, bekal, kendaraan, & keamanan perjalanan.',
      'Bagi wanita: disertai mahram atau rombongan yang aman menurut sebagian ulama.',
    ],
  ],
];
?>
<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-stars"></i> <strong>RUKUN ISLAM</strong> — 5 Pilar Beserta Rukun, Syarat Sah & Syarat Wajib</span>
    <small class="opacity-75">Ringkasan ringkas (rujuk kitab fiqih untuk pembahasan lengkap)</small>
  </div>
  <div class="card-body">
    <div class="accordion" id="accRukunIslam">
      <?php foreach ($RUKUN_ISLAM as $i=>$r): ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="hRI<?= $i ?>">
          <button class="accordion-button <?= $i>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse"
                  data-bs-target="#cRI<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="cRI<?= $i ?>">
            <i class="bi <?= $r['icon'] ?> text-<?= $r['warna'] ?> me-2 fs-5"></i>
            <strong><?= $r['judul'] ?></strong>
          </button>
        </h2>
        <div id="cRI<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="hRI<?= $i ?>" data-bs-parent="#accRukunIslam">
          <div class="accordion-body">
            <div class="row g-3">
              <div class="col-md-4">
                <h6 class="text-success"><i class="bi bi-check2-square"></i> Rukun</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['rukun'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
              <div class="col-md-4">
                <h6 class="text-primary"><i class="bi bi-shield-check"></i> Syarat Sah</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['syarat_sah'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
              <div class="col-md-4">
                <h6 class="text-danger"><i class="bi bi-exclamation-octagon"></i> Syarat Wajib</h6>
                <ol class="ps-3 small mb-0">
                  <?php foreach ($r['syarat_wajib'] as $x): ?><li><?= $x ?></li><?php endforeach; ?>
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-fire text-danger"></i> Streak & Badge Saya</div><div class="card-body">
      <?php if ($u): ?>
        <div class="display-6"><?= $streak ?> <small class="fs-6 text-muted">hari berturut-turut</small></div>
        <div class="mt-2">
          <?php foreach ($badges as $b): ?>
            <span class="badge bg-success me-1 mb-1"><i class="bi bi-award"></i> <?= htmlspecialchars(islami_badge_label($b['badge_key'])) ?></span>
          <?php endforeach; if(!$badges): ?><div class="small text-muted">Belum ada badge. Mulai dari "1 hari 1 ayat".</div><?php endif; ?>
        </div>
      <?php else: ?><div>Login untuk mencatat streak.</div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-hourglass-split text-success"></i> Countdown Hari Raya & Peristiwa</div><div class="card-body">
      <?php
        $cdEvents = [
          ['Ramadhan',       hijri_event_to_gregorian(9, 1),  'cdRamadhan',   'success'],
          ['Idul Fitri',     hijri_event_to_gregorian(10,1),  'cdIedFitri',   'warning'],
          ['Idul Adha',      hijri_event_to_gregorian(12,10), 'cdIedAdha',    'danger'],
          ['Tahun Baru Hijriyah', hijri_event_to_gregorian(1, 1),  'cdMuharram','info'],
          ['Asyura (10 Muharram)',hijri_event_to_gregorian(1,10),  'cdAsyura', 'secondary'],
          ['Maulid Nabi (12 Rabiul Awal)', hijri_event_to_gregorian(3,12), 'cdMaulid', 'primary'],
          ['Isra Mi\'raj (27 Rajab)',     hijri_event_to_gregorian(7,27), 'cdIsra',   'info'],
          ['Nisfu Sya\'ban (15 Sya\'ban)',hijri_event_to_gregorian(8,15), 'cdNisfu',  'dark'],
          ['Arafah (9 Dzulhijjah)',       hijri_event_to_gregorian(12,9), 'cdArafah', 'warning'],
        ];
        foreach ($cdEvents as $e): ?>
          <div class="mb-1 small"><strong class="text-<?= $e[3] ?>"><?= $e[0] ?></strong>
            <span class="text-muted">(<?= $e[1]->format('d M Y') ?>)</span>:
            <span id="<?= $e[2] ?>">…</span></div>
      <?php endforeach; ?>
    </div></div>

    <?php if ($u): ?>
    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-sliders text-primary"></i> Preferensi</div><div class="card-body">
      <form method="post" id="prefForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="save_pref">
        <div class="mb-2"><label class="small">Negara</label>
          <select class="form-select form-select-sm" name="negara" id="prefNegara">
            <?php foreach (array_keys($CITIES_BY_COUNTRY) as $neg): ?>
              <option value="<?= htmlspecialchars($neg) ?>" <?= ($pref['negara']===$neg)?'selected':'' ?>><?= htmlspecialchars($neg) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="small">Kota (autocomplete sesuai negara)</label>
          <input class="form-control form-control-sm" name="kota" id="prefKota" list="kotaList" value="<?= htmlspecialchars($pref['kota']) ?>" autocomplete="off">
          <datalist id="kotaList">
            <?php foreach (($CITIES_BY_COUNTRY[$pref['negara']] ?? []) as $kt): ?>
              <option value="<?= htmlspecialchars($kt) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="modeT" name="mode_tenang" <?= !empty($pref['mode_tenang'])?'checked':'' ?>>
          <label for="modeT" class="form-check-label small">Aktifkan Mode Tenang saat adzan</label></div>
        <button class="btn btn-sm btn-primary mt-2">Simpan</button>
      </form>
      <script>
        (function(){
          var citiesByCountry = <?= json_encode($CITIES_BY_COUNTRY, JSON_UNESCAPED_UNICODE) ?>;
          var sel = document.getElementById('prefNegara');
          var dl  = document.getElementById('kotaList');
          var kt  = document.getElementById('prefKota');
          if (!sel || !dl) return;
          function refresh(){
            var list = citiesByCountry[sel.value] || [];
            dl.innerHTML = list.map(function(c){ return '<option value="'+c.replace(/"/g,'&quot;')+'"></option>'; }).join('');
            if (list.length && list.indexOf(kt.value) === -1) kt.value = list[0];
          }
          sel.addEventListener('change', refresh);
        })();
      </script>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<script src="/assets/js/islami.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (!window.islamiCountdown) return;
  <?php foreach ($cdEvents as $e): ?>
    window.islamiCountdown('<?= $e[2] ?>', '<?= $e[1]->format('Y-m-d') ?>T00:00:00');
  <?php endforeach; ?>
});
</script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    if (window.islamiCountdown) {
      window.islamiCountdown('cdRamadhan', '<?= $ramadhan->format('Y-m-d') ?>T00:00:00');
      window.islamiCountdown('cdIedAdha', '<?= $iedAdha->format('Y-m-d') ?>T00:00:00');
    }
  });
</script>
<script>
/* ===== KOMPAS KIBLAT — bergerak sesuai gerakan HP =====
 * Perbaikan 31 Mei 2026: sebelumnya jarum tidak bergerak karena
 *  (1) iOS butuh permission DeviceOrientationEvent.requestPermission()
 *  (2) Chrome Android pakai event "deviceorientationabsolute" + alpha = arah utara
 *      sementara iOS pakai webkitCompassHeading.
 *  (3) listener tidak terdaftar sebelum permission diterima.
 * Sekarang ditangani semuanya + fallback bila tidak ada sensor.
 */
(function(){
  var needle  = document.getElementById('qiblaNeedle');
  var status  = document.getElementById('qiblaStatus');
  var elBear  = document.getElementById('qiblaBearing');
  var elHead  = document.getElementById('qiblaHeading');
  var permBtn = document.getElementById('qiblaPerm');
  if (!needle) return;

  // Lokasi Ka'bah (Makkah)
  var KAABA = { lat: 21.4225, lng: 39.8262 };
  var qiblaBearing = null;   // derajat dari utara ke kiblat (lokasi user → Ka'bah)
  var deviceHeading = 0;     // derajat HP saat ini (0 = HP menghadap utara)

  function toRad(d){ return d * Math.PI/180; }
  function toDeg(r){ return r * 180/Math.PI; }
  function bearingTo(lat1,lng1,lat2,lng2){
    var dLng = toRad(lng2 - lng1);
    var y = Math.sin(dLng) * Math.cos(toRad(lat2));
    var x = Math.cos(toRad(lat1))*Math.sin(toRad(lat2)) -
            Math.sin(toRad(lat1))*Math.cos(toRad(lat2))*Math.cos(dLng);
    var b = toDeg(Math.atan2(y, x));
    return (b + 360) % 360;
  }
  function render(){
    if (qiblaBearing === null) { needle.style.transform = 'translate(-50%, -100%) rotate('+(-deviceHeading)+'deg)'; return; }
    // Jarum harus menunjuk ke kiblat relatif terhadap arah HP saat ini.
    var rel = (qiblaBearing - deviceHeading + 360) % 360;
    needle.style.transform = 'translate(-50%, -100%) rotate(' + rel + 'deg)';
    elHead.textContent = Math.round(deviceHeading) + '°';
    elBear.textContent = Math.round(qiblaBearing) + '° dari Utara';
  }

  // 1) Ambil lokasi user.
  if (!navigator.geolocation) {
    status.textContent = 'GPS tidak didukung browser. Kompas tidak bisa menentukan arah kiblat.';
  } else {
    navigator.geolocation.getCurrentPosition(function(pos){
      qiblaBearing = bearingTo(pos.coords.latitude, pos.coords.longitude, KAABA.lat, KAABA.lng);
      status.textContent = 'Lokasi terdeteksi. Putar HP perlahan sampai jarum menunjuk lurus ke atas (🕋).';
      render();
    }, function(err){
      status.textContent = 'Tidak bisa membaca lokasi (' + err.message + '). Memakai Jakarta sebagai default.';
      qiblaBearing = bearingTo(-6.2, 106.816666, KAABA.lat, KAABA.lng);
      render();
    }, {enableHighAccuracy:true, timeout:10000, maximumAge:60000});
  }

  // 2) Dengarkan sensor orientasi.
  function onOrient(e){
    var h = null;
    if (typeof e.webkitCompassHeading === 'number') {
      // iOS: 0 = utara, naik searah jarum jam — siap pakai.
      h = e.webkitCompassHeading;
    } else if (e.absolute && typeof e.alpha === 'number') {
      // Android Chrome: alpha 0 = utara, naik berlawanan jarum jam → balik tanda.
      h = 360 - e.alpha;
    } else if (typeof e.alpha === 'number') {
      h = 360 - e.alpha;
    }
    if (h === null || isNaN(h)) return;
    deviceHeading = (h + 360) % 360;
    render();
  }
  function attachSensors(){
    // Coba absolute (Android), lalu fallback non-absolute.
    window.addEventListener('deviceorientationabsolute', onOrient, true);
    window.addEventListener('deviceorientation', onOrient, true);
  }
  // 3) iOS 13+ butuh permission eksplisit dari user gesture.
  if (typeof DeviceOrientationEvent !== 'undefined' &&
      typeof DeviceOrientationEvent.requestPermission === 'function') {
    permBtn.classList.remove('d-none');
    permBtn.addEventListener('click', function(){
      DeviceOrientationEvent.requestPermission().then(function(state){
        if (state === 'granted') { attachSensors(); permBtn.classList.add('d-none'); status.textContent = 'Sensor aktif. Putar HP perlahan.'; }
        else { status.textContent = 'Izin sensor ditolak. Tidak bisa mendeteksi arah HP.'; }
      }).catch(function(err){ status.textContent = 'Gagal meminta izin sensor: ' + err.message; });
    });
  } else if (window.DeviceOrientationEvent) {
    attachSensors();
  } else {
    status.textContent = 'Browser tidak mendukung sensor orientasi. Kompas tidak bisa bergerak.';
  }
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

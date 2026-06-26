<?php
/** Revisi R15 (25 Juni 2026) — Halaman Tata Cara Wudhu
 *  Ganti ilustrasi pollinations.ai (sering gagal) dengan gambar yang
 *  sudah digenerate Lovable AI dan disimpan lokal di
 *  /assets/img/wudhu/{n}.jpg.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Tata Cara Wudhu';

$WUDHU = [
  ['Niat Wudhu (dalam hati)',
   'نَوَيْتُ الْوُضُوْءَ لِرَفْعِ الْحَدَثِ الْأَصْغَرِ فَرْضًا لِلهِ تَعَالَى',
   'Nawaitul-wudhū’a li-raf‘il-hadatsil-asghari fardhal lillāhi ta‘ālā.',
   'Aku berniat berwudhu untuk menghilangkan hadats kecil, fardhu karena Allah Ta‘ala.',
   'muslim man making intention for wudhu ablution, hands placed near water basin, peaceful expression, soft natural light, instructional illustration'],
  ['Membaca Basmalah & Mencuci Kedua Telapak Tangan (3×)',
   'بِسْمِ اللهِ الرَّحْمٰنِ الرَّحِيْمِ',
   'Bismillāhir-rahmānir-rahīm.',
   'Dengan menyebut nama Allah Yang Maha Pengasih lagi Maha Penyayang. Cuci kedua telapak tangan sebanyak 3 kali sampai pergelangan.',
   'close-up of muslim man washing both palms with clean flowing water from a brass tap, instructional illustration, soft daylight'],
  ['Berkumur-kumur & Membersihkan Lubang Hidung (3×)',
   '—',
   'Madmadhah wa istinsyāq.',
   'Berkumur-kumur dengan air bersih 3 kali, lalu menghirup air ke hidung lalu mengeluarkannya 3 kali.',
   'muslim man performing madmadhah, rinsing mouth with water cupped in right hand, ablution scene, clean bathroom, instructional illustration'],
  ['Membasuh Wajah (3×)',
   '—',
   'Ghaslul-wajh tsalātsan.',
   'Membasuh seluruh wajah dari tempat tumbuhnya rambut sampai dagu, dari telinga kanan ke telinga kiri, sebanyak 3 kali.',
   'muslim man washing his face with water during wudhu, both hands holding water to face, soft light, instructional illustration'],
  ['Membasuh Kedua Tangan sampai Siku (3×, dahulukan kanan)',
   '—',
   'Ghaslul-yadain ilal-marāfiq.',
   'Membasuh tangan kanan dari ujung jari hingga siku 3 kali, lalu tangan kiri 3 kali.',
   'muslim man washing right arm up to the elbow with running water during wudhu, focused expression, instructional illustration'],
  ['Mengusap Sebagian Kepala (1×)',
   '—',
   'Mashul-ra’s.',
   'Membasahi kedua tangan lalu mengusap sebagian kepala (atau seluruhnya) satu kali.',
   'muslim man wiping wet hands over his head during wudhu, calm gesture, instructional illustration'],
  ['Mengusap Kedua Telinga (1×)',
   '—',
   'Mashul-udzunain.',
   'Mengusap bagian dalam telinga dengan jari telunjuk dan bagian luar dengan ibu jari sekaligus.',
   'muslim man wiping both ears with wet fingers during wudhu, close-up illustration, soft daylight'],
  ['Membasuh Kedua Kaki sampai Mata Kaki (3×, dahulukan kanan)',
   '—',
   'Ghaslul-rijlain ilal-ka‘bain.',
   'Membasuh kaki kanan dari ujung jari sampai melewati mata kaki 3 kali, lalu kaki kiri 3 kali. Sela-sela jari kaki dibersihkan.',
   'muslim man washing his right foot up to the ankle from a tap during wudhu, clean wet feet, instructional illustration'],
  ['Tertib (berurutan) & Doa Setelah Wudhu',
   'أَشْهَدُ أَنْ لَا إِلَهَ إِلَّا اللهُ وَحْدَهُ لَا شَرِيْكَ لَهُ، وَأَشْهَدُ أَنَّ مُحَمَّدًا عَبْدُهُ وَرَسُوْلُهُ. اللَّهُمَّ اجْعَلْنِيْ مِنَ التَّوَّابِيْنَ، وَاجْعَلْنِيْ مِنَ الْمُتَطَهِّرِيْنَ',
   'Asyhadu allā ilāha illallāh wahdahu lā syarīka lah, wa asyhadu anna Muhammadan ‘abduhu wa rasūluh. Allāhummaj‘alnī minat-tawwābīna waj‘alnī minal-mutathahhirīn.',
   'Aku bersaksi tiada Tuhan selain Allah, Yang Maha Esa, tidak ada sekutu bagi-Nya, dan aku bersaksi bahwa Muhammad adalah hamba dan Rasul-Nya. Ya Allah, jadikanlah aku termasuk orang-orang yang bertaubat dan jadikanlah aku termasuk orang-orang yang menyucikan diri.',
   'muslim man raising both hands palms-up after wudhu reciting doa, peaceful, soft warm light, instructional illustration'],
];

// R17 #8: Gambar ilustrasi dihapus pada halaman ini.

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
  <li class="breadcrumb-item active">Tata Cara Wudhu</li>
</ol></nav>

<div class="card shadow-sm mb-3 border-info">
  <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
    <span><i class="bi bi-droplet-fill"></i> <strong>TATA CARA WUDHU</strong> — Urutan &amp; Bacaan</span>
  </div>
  <div class="card-body">
    <div class="accordion" id="accTataCaraWudhu">
      <?php foreach ($WUDHU as $i=>$w): ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="hW<?= $i ?>">
          <button class="accordion-button <?= $i>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse"
                  data-bs-target="#cW<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" aria-controls="cW<?= $i ?>">
            <strong><?= ($i+1).'. '.htmlspecialchars($w[0]) ?></strong>
          </button>
        </h2>
        <div id="cW<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>"
             aria-labelledby="hW<?= $i ?>" data-bs-parent="#accTataCaraWudhu">
          <div class="accordion-body">
            <?php if($w[1]!=='—'): ?>
            <div class="mb-2 text-end" dir="rtl" lang="ar" style="font-size:1.4rem;line-height:2.2;font-family:'Scheherazade New','Amiri',serif;"><?= htmlspecialchars($w[1]) ?></div>
            <?php endif; ?>
            <div class="mb-2"><span class="badge bg-primary-subtle text-primary me-1">Latin</span><em><?= htmlspecialchars($w[2]) ?></em></div>
            <div class="small"><span class="badge bg-success-subtle text-success me-1">Arti / Keterangan</span><?= htmlspecialchars($w[3]) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="text-end mb-3">
  <a href="/shalat_tatacara.php" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> Lanjut ke Tata Cara Shalat</a>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

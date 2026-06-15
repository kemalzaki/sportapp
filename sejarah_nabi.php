<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Sejarah Nabi & Rasul';

/**
 * 25 Nabi & Rasul yang wajib diketahui. Data ringkasan dari sumber Islam
 * (Al-Qur'an, Tafsir Ibnu Katsir, Qashashul Anbiya). Tidak ada API tunggal
 * gratis yang baku; data ini disimpan offline supaya selalu tersedia di lokal.
 */

$RASUL = [
  ['no'=>1,'nama'=>'Adam','gelar'=>'AS','umat'=>'Manusia pertama','tempat'=>'Surga → Bumi','ringkas'=>'Manusia pertama, dijadikan khalifah di bumi. Diturunkan ke bumi setelah memakan buah khuldi, lalu bertaubat dan diterima Allah.','ayat'=>'QS. Al-Baqarah: 30-39'],
  ['no'=>2,'nama'=>'Idris','gelar'=>'AS','umat'=>'Bani Qabil','tempat'=>'Babil (Irak)','ringkas'=>'Manusia pertama yang menulis dengan pena, ahli ilmu falak. Diangkat ke langit dalam keadaan hidup.','ayat'=>'QS. Maryam: 56-57'],
  ['no'=>3,'nama'=>'Nuh','gelar'=>'AS','umat'=>'Bani Rasib','tempat'=>'Selatan Irak','ringkas'=>'Berdakwah 950 tahun. Membangun bahtera atas perintah Allah saat kaumnya ditenggelamkan banjir besar.','ayat'=>'QS. Hud: 25-49'],
  ['no'=>4,'nama'=>'Hud','gelar'=>'AS','umat'=>'Kaum ‘Aad','tempat'=>'Al-Ahqaf (Yaman)','ringkas'=>'Mendakwahi kaum ‘Aad yang berperawakan kuat dan menyembah berhala. Mereka dibinasakan dengan angin dingin yang kencang.','ayat'=>'QS. Hud: 50-60'],
  ['no'=>5,'nama'=>'Shaleh','gelar'=>'AS','umat'=>'Kaum Tsamud','tempat'=>'Al-Hijr','ringkas'=>'Diberi mukjizat unta betina dari batu. Kaumnya dibinasakan dengan suara dahsyat (shaihah) karena membunuh unta itu.','ayat'=>'QS. Hud: 61-68'],
  ['no'=>6,'nama'=>'Ibrahim','gelar'=>'AS (Khalilullah)','umat'=>'Kaum Babilonia','tempat'=>'Babil → Palestina','ringkas'=>'Bapak para nabi. Menghancurkan berhala, dibakar Namrud namun api menjadi dingin. Membangun Ka’bah bersama Ismail.','ayat'=>'QS. Al-Anbiya: 51-71'],
  ['no'=>7,'nama'=>'Luth','gelar'=>'AS','umat'=>'Penduduk Sodom','tempat'=>'Sodom (Yordania)','ringkas'=>'Mendakwahi kaum yang berperilaku menyimpang. Negerinya dijungkirbalikkan dan dihujani batu sebagai azab.','ayat'=>'QS. Hud: 77-83'],
  ['no'=>8,'nama'=>'Ismail','gelar'=>'AS','umat'=>'Suku Amaliq & Jurhum','tempat'=>'Mekkah','ringkas'=>'Putra Ibrahim. Hampir disembelih ayahnya atas perintah Allah, diganti dengan domba — asal-usul ibadah qurban.','ayat'=>'QS. Ash-Shaffat: 100-111'],
  ['no'=>9,'nama'=>'Ishaq','gelar'=>'AS','umat'=>'Bani Kan’an','tempat'=>'Palestina','ringkas'=>'Putra Ibrahim dari Sarah. Ayah dari Ya’qub. Melanjutkan dakwah tauhid di Kan’an.','ayat'=>'QS. Ash-Shaffat: 112-113'],
  ['no'=>10,'nama'=>'Ya’qub','gelar'=>'AS (Israil)','umat'=>'Bani Kan’an','tempat'=>'Kan’an → Mesir','ringkas'=>'Putra Ishaq, ayah 12 putra (asal Bani Israil). Sangat sabar saat berpisah dengan Yusuf.','ayat'=>'QS. Yusuf: 4-6'],
  ['no'=>11,'nama'=>'Yusuf','gelar'=>'AS','umat'=>'Bangsa Mesir & saudara','tempat'=>'Mesir','ringkas'=>'Dibuang ke sumur, dijual sebagai budak, menjadi pejabat Mesir. Ahli takwil mimpi. Kisahnya disebut "ahsanul qashash".','ayat'=>'QS. Yusuf (seluruh surah)'],
  ['no'=>12,'nama'=>'Ayyub','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Teladan kesabaran. Diuji penyakit kulit & kehilangan harta-keluarga puluhan tahun, tetap bersabar.','ayat'=>'QS. Al-Anbiya: 83-84'],
  ['no'=>13,'nama'=>'Syu’aib','gelar'=>'AS','umat'=>'Kaum Madyan & Aikah','tempat'=>'Madyan (Yordania)','ringkas'=>'Mendakwahi kaum yang curang dalam timbangan. Dibinasakan dengan gempa dahsyat.','ayat'=>'QS. Hud: 84-95'],
  ['no'=>14,'nama'=>'Musa','gelar'=>'AS (Kalimullah)','umat'=>'Bani Israil','tempat'=>'Mesir → Sinai','ringkas'=>'Berbicara langsung dengan Allah. Membelah Laut Merah dengan tongkat, mengalahkan Fir’aun, menerima Taurat di Bukit Thur.','ayat'=>'QS. Al-Qashash: 3-46'],
  ['no'=>15,'nama'=>'Harun','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Mesir → Sinai','ringkas'=>'Saudara dan pendamping Musa, fasih bertutur kata. Membantu dakwah menghadapi Fir’aun.','ayat'=>'QS. Thaha: 29-36'],
  ['no'=>16,'nama'=>'Dzulkifli','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Dikenal sangat sabar dan adil sebagai hakim. Memenuhi janjinya dengan teguh.','ayat'=>'QS. Al-Anbiya: 85-86'],
  ['no'=>17,'nama'=>'Dawud','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Raja & nabi yang mengalahkan Jalut. Diberi kitab Zabur dan suara merdu. Besi dilunakkan di tangannya.','ayat'=>'QS. Saba: 10-11'],
  ['no'=>18,'nama'=>'Sulaiman','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Putra Dawud. Diberi kerajaan besar — memahami bahasa hewan, jin tunduk padanya, angin di bawah perintahnya.','ayat'=>'QS. An-Naml: 15-44'],
  ['no'=>19,'nama'=>'Ilyas','gelar'=>'AS','umat'=>'Bani Israil di Ba’labak','tempat'=>'Lebanon','ringkas'=>'Mendakwahi kaum yang menyembah berhala Ba’l. Memohon kemarau bertahun-tahun sebagai pelajaran.','ayat'=>'QS. Ash-Shaffat: 123-132'],
  ['no'=>20,'nama'=>'Ilyasa','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Penerus dakwah Nabi Ilyas, termasuk hamba pilihan Allah.','ayat'=>'QS. Shad: 48'],
  ['no'=>21,'nama'=>'Yunus','gelar'=>'AS','umat'=>'Penduduk Ninawa','tempat'=>'Irak','ringkas'=>'Pergi meninggalkan kaumnya, ditelan ikan besar, bertasbih di perut ikan, lalu kembali berdakwah dan kaumnya beriman.','ayat'=>'QS. Yunus: 98 & Ash-Shaffat: 139-148'],
  ['no'=>22,'nama'=>'Zakariya','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Berdoa di usia tua mendapat anak — dikabulkan dengan kelahiran Yahya. Pemelihara Maryam.','ayat'=>'QS. Maryam: 2-11'],
  ['no'=>23,'nama'=>'Yahya','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Diberi hikmah sejak kecil, berani menegakkan kebenaran kepada penguasa zalim.','ayat'=>'QS. Maryam: 12-15'],
  ['no'=>24,'nama'=>'Isa','gelar'=>'AS (Ruhullah)','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Lahir tanpa ayah dari Maryam. Mukjizat: berbicara di buaian, menyembuhkan buta & kusta, menghidupkan orang mati. Diangkat ke langit.','ayat'=>'QS. Ali Imran: 45-55'],
  ['no'=>25,'nama'=>'Muhammad','gelar'=>'ﷺ (Khatamun Nabiyyin)','umat'=>'Seluruh manusia','tempat'=>'Mekkah → Madinah','ringkas'=>'Penutup para nabi. Membawa Al-Qur’an sebagai pedoman akhir zaman. Hijrah, perang Badar-Uhud-Khandaq, Fathu Makkah. Wafat 11 H di Madinah.','ayat'=>'QS. Al-Ahzab: 40'],
];

$sel = isset($_GET['n']) ? max(1, min(25, (int)$_GET['n'])) : 0;

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Sejarah Nabi'); ?>

<?php ip_card_open('Sejarah Nabi & Rasul (25 Rasul)', 'bi-book'); ?>

<p class="text-muted small mb-3">
  Ringkasan kisah 25 Nabi & Rasul yang wajib diketahui. Disusun dari Al-Qur’an, Tafsir Ibnu Katsir, dan kitab Qashashul Anbiya.
</p>

<?php if ($sel):
  $r = $RASUL[$sel-1];
?>
  <a href="sejarah_nabi.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Daftar 25 Rasul</a>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-weight:700;font-size:1.2rem;"><?= $r['no'] ?></div>
        <div>
          <h2 class="h4 mb-0">Nabi <?= htmlspecialchars($r['nama']) ?> <small class="text-muted"><?= htmlspecialchars($r['gelar']) ?></small></h2>
          <div class="small text-muted"><i class="bi bi-people"></i> Umat: <?= htmlspecialchars($r['umat']) ?> · <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['tempat']) ?></div>
        </div>
      </div>
      <p><?= htmlspecialchars($r['ringkas']) ?></p>
      <div class="alert alert-success small mb-0"><i class="bi bi-bookmark-star"></i> Rujukan: <?= htmlspecialchars($r['ayat']) ?></div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach($RASUL as $r): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="?n=<?= $r['no'] ?>" class="text-decoration-none">
          <div class="card h-100 shadow-sm border-0 text-center">
            <div class="card-body">
              <div class="rounded-circle bg-primary-subtle text-primary mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;font-weight:700;"><?= $r['no'] ?></div>
              <div class="fw-semibold">Nabi <?= htmlspecialchars($r['nama']) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($r['gelar']) ?></div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php?>
<?php htmx_layout_end(); ?>

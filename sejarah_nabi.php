<?php
// sejarah_nabi.php — Sejarah 25 Nabi & Rasul (Revisi R13 - 25 Juni 2026)
// Perubahan R13:
//  (#6) Klik "Rujukan Al-Qur'an" -> modal popup ayat (equran.id).
//  (#7) Tabel kaum, nabi, kondisi sosial, jenis azab, pemimpin zalim, peninggalan azab.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
require __DIR__.'/includes/islami_data.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Sejarah Nabi & Rasul';

$RASUL = [
  ['no'=>1,'nama'=>'Adam','gelar'=>'AS','umat'=>'Manusia pertama','tempat'=>'Surga → Bumi','ringkas'=>'Manusia pertama, dijadikan khalifah di bumi. Diturunkan ke bumi setelah memakan buah khuldi, lalu bertaubat dan diterima Allah.','ayat'=>'QS. Al-Baqarah: 30-39'],
  ['no'=>2,'nama'=>'Idris','gelar'=>'AS','umat'=>'Bani Qabil','tempat'=>'Babil (Irak)','ringkas'=>'Manusia pertama yang menulis dengan pena, ahli ilmu falak. Diangkat ke langit dalam keadaan hidup.','ayat'=>'QS. Maryam: 56-57'],
  ['no'=>3,'nama'=>'Nuh','gelar'=>'AS','umat'=>'Bani Rasib','tempat'=>'Selatan Irak','ringkas'=>'Berdakwah 950 tahun. Membangun bahtera atas perintah Allah saat kaumnya ditenggelamkan banjir besar.','ayat'=>'QS. Hud: 25-49'],
  ['no'=>4,'nama'=>'Hud','gelar'=>'AS','umat'=>'Kaum \'Aad','tempat'=>'Al-Ahqaf (Yaman)','ringkas'=>'Mendakwahi kaum \'Aad yang berperawakan kuat dan menyembah berhala. Mereka dibinasakan dengan angin dingin yang kencang.','ayat'=>'QS. Hud: 50-60'],
  ['no'=>5,'nama'=>'Shaleh','gelar'=>'AS','umat'=>'Kaum Tsamud','tempat'=>'Al-Hijr','ringkas'=>'Diberi mukjizat unta betina dari batu. Kaumnya dibinasakan dengan suara dahsyat (shaihah) karena membunuh unta itu.','ayat'=>'QS. Hud: 61-68'],
  ['no'=>6,'nama'=>'Ibrahim','gelar'=>'AS (Khalilullah)','umat'=>'Kaum Babilonia','tempat'=>'Babil → Palestina','ringkas'=>'Bapak para nabi. Menghancurkan berhala, dibakar Namrud namun api menjadi dingin. Membangun Ka\'bah bersama Ismail.','ayat'=>'QS. Al-Anbiya: 51-71'],
  ['no'=>7,'nama'=>'Luth','gelar'=>'AS','umat'=>'Penduduk Sodom','tempat'=>'Sodom (Yordania)','ringkas'=>'Mendakwahi kaum yang berperilaku menyimpang. Negerinya dijungkirbalikkan dan dihujani batu sebagai azab.','ayat'=>'QS. Hud: 77-83'],
  ['no'=>8,'nama'=>'Ismail','gelar'=>'AS','umat'=>'Suku Amaliq & Jurhum','tempat'=>'Mekkah','ringkas'=>'Putra Ibrahim. Hampir disembelih ayahnya atas perintah Allah, diganti dengan domba — asal-usul ibadah qurban.','ayat'=>'QS. Ash-Shaffat: 100-111'],
  ['no'=>9,'nama'=>'Ishaq','gelar'=>'AS','umat'=>'Bani Kan\'an','tempat'=>'Palestina','ringkas'=>'Putra Ibrahim dari Sarah. Ayah dari Ya\'qub. Melanjutkan dakwah tauhid di Kan\'an.','ayat'=>'QS. Ash-Shaffat: 112-113'],
  ['no'=>10,'nama'=>'Ya\'qub','gelar'=>'AS (Israil)','umat'=>'Bani Kan\'an','tempat'=>'Kan\'an → Mesir','ringkas'=>'Putra Ishaq, ayah 12 putra (asal Bani Israil). Sangat sabar saat berpisah dengan Yusuf.','ayat'=>'QS. Yusuf: 4-6'],
  ['no'=>11,'nama'=>'Yusuf','gelar'=>'AS','umat'=>'Bangsa Mesir & saudara','tempat'=>'Mesir','ringkas'=>'Dibuang ke sumur, dijual sebagai budak, menjadi pejabat Mesir. Ahli takwil mimpi. Kisahnya disebut "ahsanul qashash".','ayat'=>'QS. Yusuf: 1-111'],
  ['no'=>12,'nama'=>'Ayyub','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Teladan kesabaran. Diuji penyakit kulit & kehilangan harta-keluarga puluhan tahun, tetap bersabar.','ayat'=>'QS. Al-Anbiya: 83-84'],
  ['no'=>13,'nama'=>'Syu\'aib','gelar'=>'AS','umat'=>'Kaum Madyan & Aikah','tempat'=>'Madyan (Yordania)','ringkas'=>'Mendakwahi kaum yang curang dalam timbangan. Dibinasakan dengan gempa dahsyat.','ayat'=>'QS. Hud: 84-95'],
  ['no'=>14,'nama'=>'Musa','gelar'=>'AS (Kalimullah)','umat'=>'Bani Israil','tempat'=>'Mesir → Sinai','ringkas'=>'Berbicara langsung dengan Allah. Membelah Laut Merah dengan tongkat, mengalahkan Fir\'aun, menerima Taurat di Bukit Thur.','ayat'=>'QS. Al-Qashash: 3-46'],
  ['no'=>15,'nama'=>'Harun','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Mesir → Sinai','ringkas'=>'Saudara dan pendamping Musa, fasih bertutur kata. Membantu dakwah menghadapi Fir\'aun.','ayat'=>'QS. Thaha: 29-36'],
  ['no'=>16,'nama'=>'Dzulkifli','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Dikenal sangat sabar dan adil sebagai hakim. Memenuhi janjinya dengan teguh.','ayat'=>'QS. Al-Anbiya: 85-86'],
  ['no'=>17,'nama'=>'Dawud','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Raja & nabi yang mengalahkan Jalut. Diberi kitab Zabur dan suara merdu. Besi dilunakkan di tangannya.','ayat'=>'QS. Saba: 10-11'],
  ['no'=>18,'nama'=>'Sulaiman','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Putra Dawud. Diberi kerajaan besar — memahami bahasa hewan, jin tunduk padanya, angin di bawah perintahnya.','ayat'=>'QS. An-Naml: 15-44'],
  ['no'=>19,'nama'=>'Ilyas','gelar'=>'AS','umat'=>'Bani Israil di Ba\'labak','tempat'=>'Lebanon','ringkas'=>'Mendakwahi kaum yang menyembah berhala Ba\'l. Memohon kemarau bertahun-tahun sebagai pelajaran.','ayat'=>'QS. Ash-Shaffat: 123-132'],
  ['no'=>20,'nama'=>'Ilyasa','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Syam','ringkas'=>'Penerus dakwah Nabi Ilyas, termasuk hamba pilihan Allah.','ayat'=>'QS. Shad: 48'],
  ['no'=>21,'nama'=>'Yunus','gelar'=>'AS','umat'=>'Penduduk Ninawa','tempat'=>'Irak','ringkas'=>'Pergi meninggalkan kaumnya, ditelan ikan besar, bertasbih di perut ikan, lalu kembali berdakwah dan kaumnya beriman.','ayat'=>'QS. Yunus: 98'],
  ['no'=>22,'nama'=>'Zakariya','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Berdoa di usia tua mendapat anak — dikabulkan dengan kelahiran Yahya. Pemelihara Maryam.','ayat'=>'QS. Maryam: 2-11'],
  ['no'=>23,'nama'=>'Yahya','gelar'=>'AS','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Diberi hikmah sejak kecil, berani menegakkan kebenaran kepada penguasa zalim.','ayat'=>'QS. Maryam: 12-15'],
  ['no'=>24,'nama'=>'Isa','gelar'=>'AS (Ruhullah)','umat'=>'Bani Israil','tempat'=>'Palestina','ringkas'=>'Lahir tanpa ayah dari Maryam. Mukjizat: berbicara di buaian, menyembuhkan buta & kusta, menghidupkan orang mati. Diangkat ke langit.','ayat'=>'QS. Ali Imran: 45-55'],
  ['no'=>25,'nama'=>'Muhammad','gelar'=>'SAW (Khatamun Nabiyyin)','umat'=>'Seluruh manusia','tempat'=>'Mekkah → Madinah','ringkas'=>'Penutup para nabi. Membawa Al-Qur\'an sebagai pedoman akhir zaman. Hijrah, perang Badar-Uhud-Khandaq, Fathu Makkah. Wafat 11 H di Madinah.','ayat'=>'QS. Al-Ahzab: 40'],
];

/* (#7) Tabel ringkas kaum yang diazab. Disusun dari Al-Qur'an dan riwayat sahih
   (Tafsir Ibnu Katsir, Qashashul Anbiya). */
$KAUM = [
  ['kaum'=>'Kaum Nabi Nuh','nabi'=>'Nuh AS','sosial'=>'Menyembah berhala (Wadd, Suwa\', Yaghuts, Ya\'uq, Nasr); menolak dakwah selama 950 tahun.','azab'=>'Banjir besar yang menenggelamkan seluruh negeri.','musuh'=>'Para pembesar kaum & anak Nabi Nuh (Kan\'an) yang ingkar.','peninggalan'=>'Sisa bahtera Nabi Nuh di Bukit Judi (Turki). Ayat: QS. Hud: 25-49.'],
  ['kaum'=>'Kaum \'Aad','nabi'=>'Hud AS','sosial'=>'Bangsa berperawakan tinggi & kuat, sombong dengan kekuasaan, membangun istana megah di Iram, menyembah berhala.','azab'=>'Angin dingin yang sangat kencang (rih shorshor) 7 malam 8 hari.','musuh'=>'Para pembesar \'Aad yang menantang azab.','peninggalan'=>'Reruntuhan Iram Dzatil \'Imad di Al-Ahqaf (Yaman). Ayat: QS. Al-Haqqah: 6-8; QS. Fushshilat: 15-16.'],
  ['kaum'=>'Kaum Tsamud','nabi'=>'Shaleh AS','sosial'=>'Memahat rumah di gunung batu, kaya & kufur, menolak mukjizat unta dari batu.','azab'=>'Suara dahsyat (shaihah) & gempa, semua mati dalam keadaan bertelungkup.','musuh'=>'9 pemuka kaum yang merencanakan membunuh unta & Nabi Shaleh.','peninggalan'=>'Situs Mada\'in Saleh / Al-Hijr di Arab Saudi (rumah-rumah pahat). Ayat: QS. Al-A\'raf: 73-79.'],
  ['kaum'=>'Kaum Nabi Luth','nabi'=>'Luth AS','sosial'=>'Penduduk Sodom & Gomorah; perilaku menyimpang (homoseksual), perampokan, kemungkaran terbuka.','azab'=>'Negeri dijungkirbalikkan & dihujani batu sijjil (tanah membatu).','musuh'=>'Pembesar kaum Sodom; istri Nabi Luth termasuk yang binasa karena berpihak pada kaumnya.','peninggalan'=>'Cekungan Laut Mati (Bahrul Mayyit) di perbatasan Yordania-Palestina. Ayat: QS. Hud: 77-83.'],
  ['kaum'=>'Kaum Madyan & Aikah','nabi'=>'Syu\'aib AS','sosial'=>'Pedagang yang curang dalam takaran & timbangan, merampas hak orang lain, kufur.','azab'=>'Gempa dahsyat (rajfah) & awan panas (azab yaumizh-zhullah).','musuh'=>'Pembesar pedagang Madyan yang mengusir Syu\'aib.','peninggalan'=>'Situs Madyan di barat laut Arab Saudi (Al-Bad\'). Ayat: QS. Al-A\'raf: 85-93.'],
  ['kaum'=>'Bani Israil & bangsa Mesir','nabi'=>'Musa AS & Harun AS','sosial'=>'Bangsa Mesir di bawah Fir\'aun memperbudak Bani Israil, membunuh bayi laki-laki, menuhankan Fir\'aun.','azab'=>'10 azab: katak, kutu, belalang, darah, kegelapan, dst.; Fir\'aun & bala tentaranya ditenggelamkan di Laut Merah.','musuh'=>'Fir\'aun (Ramses II / diperdebatkan), Haman (penasihat), Qarun (hartawan kafir).','peninggalan'=>'Jasad Fir\'aun yang utuh, kini disimpan di Museum Mesir/Kairo, sebagai tanda kebenaran QS. Yunus: 92.'],
  ['kaum'=>'Bani Israil (di masa Dawud)','nabi'=>'Dawud AS','sosial'=>'Sebagian Bani Israil melanggar Sabtu (hari Sabtu) dengan menangkap ikan secara curang.','azab'=>'Diubah wujudnya menjadi kera yang hina (maskh).','musuh'=>'Pelanggar Sabtu (Ashabus Sabt) di kampung Ailah/Eilat.','peninggalan'=>'Situs Ailah/Eilat di pesisir Laut Merah. Ayat: QS. Al-A\'raf: 163-166; QS. Al-Baqarah: 65.'],
  ['kaum'=>'Penduduk Saba\'','nabi'=>'Sulaiman AS (sebelumnya) — peringatan tanpa nabi khusus saat azab','sosial'=>'Kerajaan makmur dengan dua kebun subur di Yaman, kufur nikmat & berpaling.','azab'=>'Jebolnya bendungan Ma\'rib (sayl al-\'arim) yang menenggelamkan kebun-kebunnya.','musuh'=>'Para pemuka Saba\' yang menolak peringatan.','peninggalan'=>'Reruntuhan Bendungan Ma\'rib di Yaman. Ayat: QS. Saba\': 15-19.'],
  ['kaum'=>'Penduduk Ninawa','nabi'=>'Yunus AS','sosial'=>'Penduduk Ninawa (Irak) menyembah berhala. Berbeda dari kaum lain: mereka beriman setelah peringatan.','azab'=>'Azab diangkat setelah mereka bertaubat — satu-satunya kaum yang selamat dari azab kolektif.','musuh'=>'Pembesar kota awalnya menentang, lalu ikut bertaubat.','peninggalan'=>'Situs Niniwe di dekat Mosul, Irak. Ayat: QS. Yunus: 98.'],
  ['kaum'=>'Penduduk Mekkah jahiliyah','nabi'=>'Muhammad SAW','sosial'=>'Masyarakat Quraisy menyembah 360 berhala di Ka\'bah, mengubur bayi perempuan, riba, perbudakan.','azab'=>'Tidak dibinasakan kolektif — karena ada Nabi & ada yang beriman; sebagian pembesar tewas di Perang Badar.','musuh'=>'Abu Jahal, Abu Lahab, Umayyah bin Khalaf, Utbah bin Rabi\'ah.','peninggalan'=>'Berhala-berhala dihancurkan saat Fathu Makkah; Ka\'bah dimurnikan. Ayat: QS. An-Nashr.'],
  // R17 #7: Tambahan 15 kisah nabi / kaum / peristiwa azab & ujian umat
  ['kaum'=>'Bani Qabil (zaman Nabi Idris)','nabi'=>'Idris AS','sosial'=>'Keturunan Qabil yang fasik, mengabaikan syariat & ilmu yang diajarkan Nabi Idris.','azab'=>'Kekeringan & paceklik panjang sebagai peringatan.','musuh'=>'Pemuka Bani Qabil yang sombong terhadap ilmu Nabi Idris.','peninggalan'=>'Tidak ada situs spesifik; kisah disebut dalam QS. Maryam: 56-57 & riwayat tafsir.'],
  ['kaum'=>'Penyembah berhala Babilonia','nabi'=>'Ibrahim AS','sosial'=>'Menyembah berhala besar pimpinan Raja Namrud; mendewakan raja, kufur kepada Allah.','azab'=>'Namrud disiksa dengan seekor nyamuk yang masuk ke hidungnya hingga mati; kerajaannya runtuh.','musuh'=>'Raja Namrud bin Kan\'an.','peninggalan'=>'Reruntuhan kota Babil di Irak; situs Ur tempat lahir Ibrahim. Ayat: QS. Al-Baqarah: 258.'],
  ['kaum'=>'Saudara-saudara Nabi Yusuf','nabi'=>'Yusuf AS','sosial'=>'Saudara-saudara Yusuf iri & berbuat zalim — membuang Yusuf ke sumur lalu menjualnya sebagai budak.','azab'=>'Ditimpa paceklik 7 tahun di Kan\'an hingga harus meminta gandum ke Mesir.','musuh'=>'10 saudara Yusuf yang dipimpin yang paling tua.','peninggalan'=>'Sumur Yusuf (dipercayai) di dekat Nablus, Palestina. Ayat: QS. Yusuf: 7-18, 88.'],
  ['kaum'=>'Penguji Nabi Ayyub','nabi'=>'Ayyub AS','sosial'=>'Ujian individual: kekayaan, kesehatan & keluarga Nabi Ayyub diambil; banyak orang menjauhinya.','azab'=>'Bukan azab kaum; tetapi mereka yang mencemooh diberi pelajaran ketika Ayyub disembuhkan & dikembalikan kekayaannya berlipat.','musuh'=>'Setan & orang-orang yang berputus asa dari rahmat Allah.','peninggalan'=>'Maqam Nabi Ayyub di Salalah (Oman) & Urfa (Turki). Ayat: QS. Shad: 41-44.'],
  ['kaum'=>'Penyembah Ba\'l (Ba\'labak)','nabi'=>'Ilyas AS','sosial'=>'Bani Israil di Ba\'labak menyembah berhala Ba\'l, menolak dakwah tauhid.','azab'=>'Kemarau panjang bertahun-tahun atas doa Nabi Ilyas; tanaman & ternak musnah.','musuh'=>'Raja Ahab & istrinya Izebel (riwayat Israiliyat).','peninggalan'=>'Reruntuhan kuil Ba\'l di Ba\'labak, Lebanon. Ayat: QS. Ash-Shaffat: 123-132.'],
  ['kaum'=>'Penentang Nabi Ilyasa\'','nabi'=>'Ilyasa\' AS','sosial'=>'Penerus dakwah Ilyas; sebagian Bani Israil tetap menyembah berhala & menolak.','azab'=>'Kekalahan & penjajahan oleh bangsa asing sebagai pelajaran.','musuh'=>'Pemuka Bani Israil yang murtad.','peninggalan'=>'Tidak ada situs khusus; ayat: QS. Shad: 48; QS. Al-An\'am: 86.'],
  ['kaum'=>'Penguasa Filistin (zaman Dawud)','nabi'=>'Dawud AS','sosial'=>'Bangsa Filistin menyerang Bani Israil dipimpin raksasa Jalut (Goliat).','azab'=>'Jalut tewas oleh lemparan batu Nabi Dawud muda; pasukan Filistin kalah total.','musuh'=>'Jalut (Goliat) & pasukan Filistin.','peninggalan'=>'Lembah Elah di Palestina (lokasi duel Dawud–Jalut). Ayat: QS. Al-Baqarah: 249-251.'],
  ['kaum'=>'Ratu Bilqis & kaum Saba\' (zaman Sulaiman)','nabi'=>'Sulaiman AS','sosial'=>'Kerajaan Saba\' menyembah matahari, tidak mengenal Allah.','azab'=>'Bukan azab — Ratu Bilqis & kaumnya akhirnya beriman setelah melihat mukjizat Sulaiman (singgasana dipindahkan, lantai kaca).','musuh'=>'Awalnya para penyembah matahari di Saba\'.','peninggalan'=>'Situs kerajaan Saba\' di Ma\'rib, Yaman; reruntuhan singgasana. Ayat: QS. An-Naml: 22-44.'],
  ['kaum'=>'Pembunuh Nabi Zakariya','nabi'=>'Zakariya AS','sosial'=>'Bani Israil yang menolak dakwah; Nabi Zakariya dikejar & dibunuh.','azab'=>'Kehancuran kota & penindasan bangsa lain atas Bani Israil.','musuh'=>'Pemuka Bani Israil yang fasik.','peninggalan'=>'Maqam Nabi Zakariya di Aleppo, Suriah (di dalam Masjid Umayyah). Ayat: QS. Maryam: 2-15.'],
  ['kaum'=>'Pembunuh Nabi Yahya','nabi'=>'Yahya AS','sosial'=>'Raja zalim & istri pelacur menuntut kepala Nabi Yahya karena ditegur atas pernikahan haram.','azab'=>'Bani Israil ditimpa kekalahan & pembantaian oleh bangsa Babilonia kemudian Romawi.','musuh'=>'Raja Herodes (Hirdis) & Herodias.','peninggalan'=>'Maqam kepala Nabi Yahya di Masjid Umayyah, Damaskus. Ayat: QS. Maryam: 12-15.'],
  ['kaum'=>'Yahudi penentang Nabi Isa','nabi'=>'Isa AS','sosial'=>'Sebagian Bani Israil menolak Isa, menuduh Maryam, & merencanakan pembunuhan Isa.','azab'=>'Mereka diserupakan dengan orang yang mereka salib (yang sebenarnya bukan Isa); kemudian dihinakan dengan pengusiran oleh Romawi (70 M).','musuh'=>'Pemuka Sanhedrin & pengkhianat dari murid Isa.','peninggalan'=>'Reruntuhan Baitul Maqdis & Tembok Ratapan. Ayat: QS. An-Nisa: 157-158.'],
  ['kaum'=>'Ashabul Ukhdud','nabi'=>'(tanpa nabi langsung — peringatan umat sebelum Islam)','sosial'=>'Penguasa zalim membuat parit api & membakar hidup-hidup orang beriman yang menolak murtad.','azab'=>'Penguasa & pasukannya dibinasakan; ada riwayat ditimpa api yang berbalik membakar mereka.','musuh'=>'Raja Dzu Nuwas (Yaman) menurut sebagian riwayat.','peninggalan'=>'Situs Najran di selatan Arab Saudi. Ayat: QS. Al-Buruj: 4-10.'],
  ['kaum'=>'Pasukan Gajah (Ashabul Fil)','nabi'=>'(menjelang lahirnya Nabi Muhammad SAW)','sosial'=>'Raja Abrahah dari Habasyah menyerang Ka\'bah dengan pasukan gajah untuk menghancurkannya.','azab'=>'Allah mengirim burung Ababil membawa batu sijjil; pasukan gajah binasa seperti daun dimakan ulat.','musuh'=>'Raja Abrahah Al-Asyram.','peninggalan'=>'Lembah Muhassir dekat Mekkah. Ayat: QS. Al-Fil: 1-5.'],
  ['kaum'=>'Diqyanus & Ashabul Kahfi','nabi'=>'(tanpa nabi langsung)','sosial'=>'Raja Diqyanus memaksa rakyat menyembah berhala; 7 pemuda beriman bersembunyi di gua.','azab'=>'Diqyanus & dinastinya runtuh; pemuda gua ditidurkan 309 tahun lalu dibangkitkan sebagai tanda kebenaran hari kebangkitan.','musuh'=>'Raja Diqyanus.','peninggalan'=>'Gua Ashabul Kahfi di Yordania (Ar-Raqim) & Ephesus, Turki. Ayat: QS. Al-Kahfi: 9-26.'],
  ['kaum'=>'Qarun & pengikutnya','nabi'=>'Musa AS','sosial'=>'Qarun sangat kaya raya, sombong, kikir, mengaku kekayaannya hasil ilmunya sendiri.','azab'=>'Ia & istananya ditenggelamkan ke dalam bumi (khasf).','musuh'=>'Qarun & para pengikut yang mengaguminya.','peninggalan'=>'Danau Qarun (Birket Qarun) di Fayyum, Mesir. Ayat: QS. Al-Qashash: 76-82.'],
  ['kaum'=>'Pelanggar perjanjian Bani Quraizhah','nabi'=>'Muhammad SAW','sosial'=>'Yahudi Bani Quraizhah berkhianat saat Perang Ahzab (Khandaq), melanggar piagam Madinah.','azab'=>'Setelah Perang Khandaq, mereka dihukum sesuai hukum kitab mereka sendiri (Sa\'ad bin Mu\'adz sebagai hakim).','musuh'=>'Ka\'ab bin Asad & pemuka Bani Quraizhah.','peninggalan'=>'Lokasi benteng Bani Quraizhah di selatan Madinah. Sirah Ibnu Hisyam; QS. Al-Ahzab: 26-27.'],
];

$sel = isset($_GET['n']) ? max(1, min(25, (int)$_GET['n'])) : 0;
$tab = $_GET['tab'] ?? 'rasul'; // rasul | kaum

include __DIR__.'/includes/header.php'; ?>

<?php ip_card_open('Sejarah Nabi & Rasul (25 Rasul)', 'bi-book'); ?>

<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab==='rasul'?'active':'' ?>" href="?tab=rasul">25 Rasul</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='kaum'?'active':'' ?>" href="?tab=kaum">Tabel Kaum & Azab</a></li>
</ul>

<?php if ($tab === 'kaum'): ?>
  <p class="text-muted small">Ringkasan kaum-kaum yang dibinasakan / diazab, nabi yang diutus, kondisi sosial, jenis azab, pemimpin zalim (musuh), serta peninggalan azabnya. Sumber: Al-Qur\'an & Tafsir Ibnu Katsir.</p>
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-top small">
      <thead class="table-warning">
        <tr>
          <th style="width:14%">Kaum</th>
          <th style="width:12%">Nabi</th>
          <th style="width:18%">Kondisi Sosial</th>
          <th style="width:16%">Jenis Azab</th>
          <th style="width:18%">Pemimpin Zalim / Musuh</th>
          <th style="width:22%">Peninggalan Azab</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($KAUM as $k): ?>
        <tr>
          <td><strong><?= htmlspecialchars($k['kaum']) ?></strong></td>
          <td><?= htmlspecialchars($k['nabi']) ?></td>
          <td><?= htmlspecialchars($k['sosial']) ?></td>
          <td><?= htmlspecialchars($k['azab']) ?></td>
          <td><?= htmlspecialchars($k['musuh']) ?></td>
          <td><?= htmlspecialchars($k['peninggalan']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($sel):
  $r = $RASUL[$sel-1]; ?>
  <a href="?tab=rasul" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Daftar 25 Rasul</a>
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
      <!-- (#6) Rujukan klik -> popup ayat -->
      <div class="alert alert-success small mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-bookmark-star"></i> Rujukan: <strong><?= htmlspecialchars($r['ayat']) ?></strong></span>
        <button type="button" class="btn btn-sm btn-success js-ayat-pop" data-ref="<?= htmlspecialchars($r['ayat']) ?>" data-judul="Nabi <?= htmlspecialchars($r['nama']) ?>">
          <i class="bi bi-eye"></i> Lihat Ayat
        </button>
      </div>
    </div>
  </div>
<?php else: ?>
  <p class="text-muted small mb-3">Ringkasan kisah 25 Nabi & Rasul yang wajib diketahui. Klik kartu untuk detail.</p>
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

<!-- (#6) Modal popup ayat -->
<div class="modal fade" id="ayatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-book text-success"></i> <span id="ayatModalTitle">Ayat</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="ayatModalBody">
        <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat…</div>
      </div>
      <div class="modal-footer">
        <a href="#" id="ayatModalOpen" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-box-arrow-up-right"></i> Buka di Al-Qur'an Digital</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var SURAH = <?= json_encode(array_map(function($x){return $x[0];}, $ISLAMI_SURAH), JSON_UNESCAPED_UNICODE) ?>;
  function norm(s){ return (s||'').toString().toLowerCase().replace(/[^a-z0-9]/g,''); }
  function findSurah(text){
    if(!text) return null;
    var qsNum = text.match(/q\.?\s*s\.?\s*(\d{1,3})/i);
    if(qsNum && +qsNum[1]>=1 && +qsNum[1]<=114) return +qsNum[1];
    var nt = norm(text);
    for(var no in SURAH){ if(nt.indexOf(norm(SURAH[no]))>=0) return +no; }
    var num = text.match(/\b(\d{1,3})\b/);
    if(num && +num[1]>=1 && +num[1]<=114) return +num[1];
    return null;
  }
  function findAyat(text){
    if(!text) return null;
    var m = text.match(/[:：]\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?/);
    if(!m) m = text.match(/ayat\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?/i);
    if(!m) return null;
    return [ +m[1], m[2]? +m[2] : +m[1] ];
  }
  var modalEl = document.getElementById('ayatModal');
  if(!modalEl) return;
  var modal = new bootstrap.Modal(modalEl);
  var body = document.getElementById('ayatModalBody');
  var titleEl = document.getElementById('ayatModalTitle');
  var openEl = document.getElementById('ayatModalOpen');

  async function showAyat(ref, judul){
    var s = findSurah(ref);
    var range = findAyat(ref);
    if(!s){
      titleEl.textContent = judul || 'Ayat';
      body.innerHTML = '<div class="alert alert-warning small mb-0">Tidak bisa mengenali surah dari "'+ ref +'".</div>';
      openEl.href = '/quran.php'; modal.show(); return;
    }
    titleEl.textContent = (judul?judul+' — ':'') + 'QS '+SURAH[s]+(range? ' : '+range[0]+(range[1]!==range[0]?'-'+range[1]:''):'');
    openEl.href = '/quran_surah.php?s='+s + (range? '&a='+range[0]+'#a'+range[0] : '');
    body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat ayat…</div>';
    modal.show();
    // R15 fix #4: gunakan proxy server-side /api_quran_ayat.php agar tidak
    // tergantung CORS/CSP browser ke equran.id.
    try{
      var from = range ? range[0] : 1;
      var to   = range ? range[1] : (range ? range[1] : 10);
      // R16 (#3): URL relatif agar tetap bekerja saat app dijalankan di subfolder lokal.
      var url  = 'api_quran_ayat.php?s='+s+'&from='+from+'&to='+to;
      var r = await fetch(url, { credentials:'same-origin' });
      var html = await r.text();
      body.innerHTML = html || '<div class="alert alert-warning small mb-0">Ayat tidak ditemukan.</div>';
    }catch(e){
      body.innerHTML = '<div class="alert alert-danger small mb-0">Gagal memuat ayat. Periksa koneksi server.</div>';
    }
  }
  document.querySelectorAll('.js-ayat-pop').forEach(function(el){
    el.addEventListener('click', function(e){ e.preventDefault(); showAyat(el.dataset.ref, el.dataset.judul); });
  });
})();
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

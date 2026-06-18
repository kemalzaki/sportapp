<?php
/**
 * artikel_olahraga.php — Revisi 18 Juni 2026 (pembaruan)
 *
 * Perubahan 18 Juni 2026 (revisi lanjutan):
 *  - Foto cover olahraga DIHAPUS — hanya video YouTube tetap tampil.
 *  - Tiap olahraga kini punya 3 gambar ilustrasi (diagram lapangan / pemain)
 *    untuk bagian: Cara Main, Pembagian Tim, Sistem Skoring — semua bersumber
 *    Wikimedia Commons / SVG publik agar mirip animasi/visual yang mudah dipahami.
 *  - Tambahan blok "Peralatan" lengkap dengan foto kecil di tiap olahraga.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Artikel Olahraga & Teknik';
$pageSkeleton = 'feed';

$ytId = function($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;
  if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $s, $m)) return $m[1];
  return '';
};

/* ============================================================
 * Revisi 18 Juni 2026 — Helper visual (animasi SVG inline)
 *  - Ilustrasi cara_img / tim_img / skoring_img sebelumnya memakai URL
 *    Wikimedia Commons yang sebagian besar 404 (gambar rusak).
 *  - Diganti dengan SVG inline beranimasi (data URI) per olahraga,
 *    menggambarkan lapangan/lintasan + bola/pemain bergerak.
 *  - Tidak butuh akses internet, tetap muncul di lokal/offline.
 * ============================================================ */
function ao_anim_svg($slug, $section) {
  $palette = [
    'lari'      => ['#16a34a', '#dcfce7', '#065f46'],
    'badminton' => ['#dc2626', '#fee2e2', '#7f1d1d'],
    'renang'    => ['#0284c7', '#e0f2fe', '#0c4a6e'],
    'hiking'    => ['#15803d', '#dcfce7', '#14532d'],
    'pingpong'  => ['#ca8a04', '#fef9c3', '#713f12'],
    'futsal'    => ['#2563eb', '#dbeafe', '#1e3a8a'],
    'biliard'   => ['#1f2937', '#e5e7eb', '#0f172a'],
  ];
  $pal = $palette[$slug] ?? ['#0ea5e9', '#e0f2fe', '#0c4a6e'];
  $fg = $pal[0]; $bg = $pal[1]; $dark = $pal[2];

  $capMap = ['cara'=>'Cara Main', 'tim'=>'Pembagian Tim', 'skor'=>'Sistem Skoring'];
  $cap = isset($capMap[$section]) ? $capMap[$section] : '';

  $W = 480; $H = 220;
  $field = ''; $motion = '';

  switch ($slug) {
    case 'lari':
      $field = '<ellipse cx="240" cy="110" rx="200" ry="80" fill="none" stroke="'.$fg.'" stroke-width="4"/>'
             . '<ellipse cx="240" cy="110" rx="160" ry="55" fill="none" stroke="'.$fg.'" stroke-width="2" stroke-dasharray="6 4"/>'
             . '<line x1="240" y1="22" x2="240" y2="48" stroke="'.$dark.'" stroke-width="3"/>'
             . '<text x="248" y="42" font-size="11" fill="'.$dark.'">START/FINISH</text>';
      $motion = '<circle r="9" fill="'.$dark.'"><animateMotion dur="3s" repeatCount="indefinite" path="M 240,30 A 200,80 0 1 1 239.9,30 Z"/></circle>';
      if ($section==='tim') {
        $motion .= '<circle r="9" fill="'.$fg.'"><animateMotion dur="3s" begin="0.5s" repeatCount="indefinite" path="M 240,30 A 200,80 0 1 1 239.9,30 Z"/></circle>'
                 . '<circle r="9" fill="#f59e0b"><animateMotion dur="3s" begin="1s" repeatCount="indefinite" path="M 240,30 A 200,80 0 1 1 239.9,30 Z"/></circle>'
                 . '<circle r="9" fill="#ef4444"><animateMotion dur="3s" begin="1.5s" repeatCount="indefinite" path="M 240,30 A 200,80 0 1 1 239.9,30 Z"/></circle>';
      }
      break;

    case 'badminton':
      $field = '<rect x="80" y="30" width="320" height="160" fill="#ffffff" stroke="'.$dark.'" stroke-width="3"/>'
             . '<line x1="240" y1="30" x2="240" y2="190" stroke="'.$dark.'" stroke-width="3" stroke-dasharray="6 4"/>'
             . '<rect x="80" y="76" width="320" height="68" fill="none" stroke="'.$dark.'" stroke-width="1.5"/>'
             . '<line x1="160" y1="30" x2="160" y2="190" stroke="'.$dark.'" stroke-width="1"/>'
             . '<line x1="320" y1="30" x2="320" y2="190" stroke="'.$dark.'" stroke-width="1"/>'
             . '<text x="240" y="20" text-anchor="middle" font-size="11" fill="'.$dark.'">NET</text>';
      $motion = '<circle r="7" fill="'.$fg.'"><animateMotion dur="2s" repeatCount="indefinite" path="M 130,110 Q 240,40 350,110 Q 240,180 130,110 Z"/></circle>';
      if ($section==='tim') {
        $motion .= '<circle cx="130" cy="80" r="10" fill="'.$dark.'"/><circle cx="130" cy="140" r="10" fill="'.$dark.'"/>'
                 . '<circle cx="350" cy="80" r="10" fill="'.$fg.'"/><circle cx="350" cy="140" r="10" fill="'.$fg.'"/>';
      }
      break;

    case 'renang':
      $field = '<rect x="40" y="30" width="400" height="160" fill="#bae6fd" stroke="'.$dark.'" stroke-width="3"/>';
      for ($i=1;$i<=5;$i++) { $y = 30 + $i*26;
        $field .= '<line x1="40" y1="'.$y.'" x2="440" y2="'.$y.'" stroke="#ffffff" stroke-width="2" stroke-dasharray="10 6"/>'; }
      $count = $section==='tim' ? 4 : 1;
      for ($i=0;$i<$count;$i++) {
        $y = 56 + $i*40; $delay = $i*0.3;
        $motion .= '<circle r="8" fill="'.$dark.'"><animateMotion dur="2.5s" begin="'.$delay.'s" repeatCount="indefinite" path="M 50,'.$y.' L 430,'.$y.' L 50,'.$y.'"/></circle>';
      }
      break;

    case 'hiking':
      $field = '<rect x="0" y="0" width="480" height="220" fill="#e0f2fe"/>'
             . '<polygon points="0,200 120,80 200,140 300,40 400,130 480,90 480,220 0,220" fill="'.$fg.'" opacity="0.85"/>'
             . '<polygon points="0,220 180,160 280,180 400,150 480,170 480,220" fill="'.$dark.'" opacity="0.6"/>'
             . '<circle cx="420" cy="40" r="18" fill="#fbbf24"/>';
      $motion = '<g><animateMotion dur="6s" repeatCount="indefinite" path="M 20,200 L 120,80 L 200,140 L 300,40"/>'
              . '<circle r="6" fill="#dc2626"/></g>';
      if ($section==='tim') {
        $motion .= '<g><animateMotion dur="6s" begin="0.6s" repeatCount="indefinite" path="M 20,200 L 120,80 L 200,140 L 300,40"/><circle r="6" fill="#1d4ed8"/></g>'
                 . '<g><animateMotion dur="6s" begin="1.2s" repeatCount="indefinite" path="M 20,200 L 120,80 L 200,140 L 300,40"/><circle r="6" fill="#7c3aed"/></g>';
      }
      break;

    case 'pingpong':
      $field = '<rect x="60" y="40" width="360" height="140" fill="#1e40af" stroke="'.$dark.'" stroke-width="3" rx="4"/>'
             . '<rect x="68" y="48" width="344" height="124" fill="none" stroke="#ffffff" stroke-width="2"/>'
             . '<line x1="240" y1="30" x2="240" y2="190" stroke="#ffffff" stroke-width="2" stroke-dasharray="6 4"/>'
             . '<line x1="68" y1="110" x2="412" y2="110" stroke="#ffffff" stroke-width="1" stroke-dasharray="3 3"/>';
      $motion = '<circle r="6" fill="#ffffff" stroke="'.$dark.'" stroke-width="1"><animateMotion dur="1.2s" repeatCount="indefinite" path="M 100,90 Q 240,30 380,90 Q 240,180 100,90 Z"/></circle>';
      if ($section==='tim') {
        $motion .= '<circle cx="120" cy="110" r="9" fill="'.$fg.'"/><circle cx="360" cy="110" r="9" fill="'.$dark.'"/>';
      }
      break;

    case 'futsal':
      $field = '<rect x="30" y="30" width="420" height="160" fill="#15803d" stroke="#ffffff" stroke-width="3"/>'
             . '<line x1="240" y1="30" x2="240" y2="190" stroke="#ffffff" stroke-width="2"/>'
             . '<circle cx="240" cy="110" r="30" fill="none" stroke="#ffffff" stroke-width="2"/>'
             . '<rect x="30" y="70" width="50" height="80" fill="none" stroke="#ffffff" stroke-width="2"/>'
             . '<rect x="400" y="70" width="50" height="80" fill="none" stroke="#ffffff" stroke-width="2"/>'
             . '<rect x="20" y="92" width="10" height="36" fill="#ffffff"/>'
             . '<rect x="450" y="92" width="10" height="36" fill="#ffffff"/>';
      $motion = '<circle r="6" fill="#ffffff" stroke="'.$dark.'" stroke-width="1"><animateMotion dur="3s" repeatCount="indefinite" path="M 100,110 Q 200,40 300,110 Q 400,180 100,110 Z"/></circle>';
      if ($section==='tim') {
        for ($i=0;$i<5;$i++){ $y=50+$i*22;
          $motion .= '<circle cx="120" cy="'.$y.'" r="7" fill="'.$fg.'"/>';
          $motion .= '<circle cx="360" cy="'.$y.'" r="7" fill="#fbbf24"/>'; }
      }
      break;

    case 'biliard':
    default:
      $field = '<rect x="40" y="30" width="400" height="160" fill="#065f46" stroke="#78350f" stroke-width="8" rx="6"/>'
             . '<circle cx="40" cy="30" r="10" fill="#1f2937"/><circle cx="240" cy="30" r="10" fill="#1f2937"/><circle cx="440" cy="30" r="10" fill="#1f2937"/>'
             . '<circle cx="40" cy="190" r="10" fill="#1f2937"/><circle cx="240" cy="190" r="10" fill="#1f2937"/><circle cx="440" cy="190" r="10" fill="#1f2937"/>';
      $motion = '<circle cx="340" cy="110" r="7" fill="#facc15"/><circle cx="354" cy="102" r="7" fill="#ef4444"/><circle cx="354" cy="118" r="7" fill="#3b82f6"/>'
              . '<circle cx="368" cy="94" r="7" fill="#10b981"/><circle cx="368" cy="110" r="7" fill="#000000"/><circle cx="368" cy="126" r="7" fill="#a855f7"/>'
              . '<circle r="8" fill="#ffffff" stroke="#1f2937" stroke-width="1"><animateMotion dur="2.5s" repeatCount="indefinite" path="M 100,110 L 330,110 L 100,110"/></circle>';
      break;
  }

  $badge = '';
  if ($cap !== '') {
    $w = strlen($cap)*7 + 22;
    $badge = '<rect x="10" y="10" width="'.$w.'" height="22" rx="11" fill="'.$dark.'" opacity="0.85"/>'
           . '<text x="22" y="25" font-size="12" fill="#ffffff" font-family="Arial,sans-serif" font-weight="bold">'.htmlspecialchars($cap).'</text>';
  }

  $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$W.' '.$H.'" width="'.$W.'" height="'.$H.'">'
       . '<rect width="100%" height="100%" fill="'.$bg.'"/>'
       . $field . $motion . $badge
       . '</svg>';
  return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/* Foto peralatan: pakai placehold.co (selalu hidup) sebagai pengganti
 * URL Wikimedia yang banyak rusak. Warna mengikuti palet olahraga. */
function ao_eq_img($slug, $name) {
  $hex = [
    'lari'=>'16a34a','badminton'=>'dc2626','renang'=>'0284c7','hiking'=>'15803d',
    'pingpong'=>'ca8a04','futsal'=>'2563eb','biliard'=>'1f2937',
  ];
  $c = isset($hex[$slug]) ? $hex[$slug] : '0ea5e9';
  $label = rawurlencode(mb_strimwidth($name, 0, 22, '…'));
  return 'https://placehold.co/200x200/'.$c.'/ffffff/png?text='.$label;
}

// ====== Data Artikel Olahraga ======
// Setiap olahraga: definisi, cara+gambar, tim+gambar, skoring+gambar, menang, peralatan[].
$ARTIKEL = [
  [
    'slug'   => 'lari',
    'judul'  => 'Lari',
    'icon'   => 'bi-person-walking', 'warna' => 'success',
    'definisi'=> 'Lari adalah gerakan tubuh berpindah cepat dengan kedua kaki bergantian, di mana selalu ada fase melayang (kedua kaki tidak menyentuh tanah). Dipertandingkan sebagai cabang atletik (sprint, jarak menengah, jarak jauh, marathon, lari estafet, halang rintang).',
    'cara'   => 'Mulai dari posisi start (berdiri / jongkok untuk sprint). Tubuh sedikit condong ke depan, ayunkan lengan setinggi pinggang–dada, kaki mendarat di mid-foot, nafas ritmis 2:2 (2 langkah tarik, 2 langkah hembus).',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Running_technique_diagram.svg/640px-Running_technique_diagram.svg.png',
    'tim'    => 'Individu untuk sebagian besar nomor. Estafet 4×100 m / 4×400 m dimainkan tim 4 pelari. Lomba beregu (cross country) biasanya 5–7 pelari per tim.',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Running_track_with_lane_numbers.svg/640px-Running_track_with_lane_numbers.svg.png',
    'skoring'=> 'Pemenang ditentukan oleh waktu tercepat menyentuh garis finish. Pada lomba beregu, total/akumulasi posisi finish anggota tim menentukan skor.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f4/Athletics_track_2.svg/640px-Athletics_track_2.svg.png',
    'menang' => 'Menang: catatan waktu terbaik atau finish pertama. Kalah/diskualifikasi: salah jalur (lintasan), false start ≥1 kali (sprint), menerima estafet di luar zona, atau bantuan tidak sah.',
    'video'  => 'https://www.youtube.com/watch?v=A3D0ONk17dg&t=76s',
    'videoLabel' => 'Teknik Lari yang Benar',
    'peralatan' => [
      ['nama'=>'Sepatu lari (running shoes)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/Asics_Gel-Kayano_25.jpg/200px-Asics_Gel-Kayano_25.jpg','desc'=>'Bantalan EVA, drop 8–10 mm untuk pemula.'],
      ['nama'=>'Kaos & celana lari','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/Running_clothes.jpg/200px-Running_clothes.jpg','desc'=>'Bahan dry-fit, ringan, menyerap keringat.'],
      ['nama'=>'Jam GPS / Smartwatch','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fc/Garmin_Forerunner_245.jpg/200px-Garmin_Forerunner_245.jpg','desc'=>'Mengukur pace, jarak, HR, ketinggian.'],
      ['nama'=>'Botol air / hydration belt','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9f/Hydration_belt.jpg/200px-Hydration_belt.jpg','desc'=>'Untuk lari ≥ 8 km dan cuaca panas.'],
    ],
  ],
  [
    'slug'   => 'badminton',
    'judul'  => 'Bulu Tangkis (Badminton)',
    'icon'   => 'bi-trophy', 'warna' => 'danger',
    'definisi'=> 'Olahraga raket yang memukul shuttlecock (kok) melewati net. Dimainkan di lapangan 13,40 m × 6,10 m (ganda) atau 5,18 m (tunggal). Pertandingan resmi diatur oleh BWF.',
    'cara'   => 'Servis dari kotak servis menyilang. Pukul kok agar jatuh di area lawan dan tidak bisa dikembalikan. Teknik dasar: forehand, backhand, smash, drop shot, netting, dan lob.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/97/Badminton_court_3d.svg/640px-Badminton_court_3d.svg.png',
    'tim'    => 'Tunggal (1 vs 1), Ganda (2 vs 2), Ganda Campuran (1 putra + 1 putri vs sama). Beregu (Thomas/Uber Cup) terdiri dari 3 tunggal + 2 ganda per partai.',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/Badminton_court.svg/640px-Badminton_court.svg.png',
    'skoring'=> 'Sistem rally point — setiap rally menghasilkan poin tanpa memandang siapa yang servis. Set dimenangkan pemain yang lebih dulu mencapai 21 poin (selisih ≥ 2). Maks 30 poin.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Badminton_service_courts.svg/640px-Badminton_service_courts.svg.png',
    'menang' => 'Menang: 2 set kemenangan dari best of 3. Kalah/pelanggaran: kok menyentuh net & jatuh di sisi sendiri, keluar lapangan, double hit, badan/raket menyentuh net, atau servis tidak sah.',
    'video'  => 'https://www.youtube.com/watch?v=f9jsnYh6CCE&t=82s',
    'videoLabel' => 'Teknik Dasar Badminton',
    'peralatan' => [
      ['nama'=>'Raket bulu tangkis','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Badminton_rackets_and_shuttlecock.jpg/200px-Badminton_rackets_and_shuttlecock.jpg','desc'=>'Berat 80–95 g, senar 22–28 lbs.'],
      ['nama'=>'Shuttlecock (kok)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/0/06/Federballschl%C3%A4ger.jpg/200px-Federballschl%C3%A4ger.jpg','desc'=>'Bulu angsa untuk turnamen, plastik untuk latihan.'],
      ['nama'=>'Sepatu badminton','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Yonex_badminton_shoes.jpg/200px-Yonex_badminton_shoes.jpg','desc'=>'Sol gum non-marking, ringan, grip kuat.'],
      ['nama'=>'Net & tiang','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/49/Badminton_net.jpg/200px-Badminton_net.jpg','desc'=>'Tinggi net 1,55 m di tepi, 1,524 m di tengah.'],
    ],
  ],
  [
    'slug'   => 'renang',
    'judul'  => 'Renang',
    'icon'   => 'bi-water', 'warna' => 'info',
    'definisi'=> 'Cabang olahraga akuatik yang berlomba menempuh jarak tertentu di kolam (50 m / 25 m) dengan gaya tertentu: gaya bebas (crawl), gaya dada, gaya punggung, dan gaya kupu-kupu.',
    'cara'   => 'Start dari balok start (kecuali gaya punggung). Berenang sesuai gaya yang dilombakan tanpa mengganggu lintasan lawan, melakukan tumbling turn pada ujung kolam, dan menyentuh dinding finish sesuai aturan tiap gaya.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3f/Front_crawl_swimmer.svg/640px-Front_crawl_swimmer.svg.png',
    'tim'    => 'Individu untuk semua gaya. Estafet 4×100 m / 4×200 m gaya bebas, dan estafet 4×100 m gaya ganti (4 perenang, masing-masing 1 gaya).',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/de/Swimming_pool_lanes.svg/640px-Swimming_pool_lanes.svg.png',
    'skoring'=> 'Catatan waktu (menit:detik:milidetik) sejak start sampai menyentuh papan finish elektronik. Tidak ada poin — murni waktu.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/Olympic-size_swimming_pool.svg/640px-Olympic-size_swimming_pool.svg.png',
    'menang' => 'Menang: waktu tercepat menyentuh finish. Diskualifikasi: false start, gaya tidak sah (mis. gaya dada mengangkat kepala terlalu lama, gaya kupu-kupu tangan tidak simetris), tidak menyentuh dinding di turn, atau menyeberang lintasan.',
    'video'  => 'https://www.youtube.com/watch?v=LVy9mwWfXxc&t=101s',
    'videoLabel' => 'Teknik Dasar Renang',
    'peralatan' => [
      ['nama'=>'Kacamata renang','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Swimming_goggles.jpg/200px-Swimming_goggles.jpg','desc'=>'Anti-fog & UV, karet silikon empuk.'],
      ['nama'=>'Baju renang','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Speedo_swimsuit.jpg/200px-Speedo_swimsuit.jpg','desc'=>'Polyester/Lycra, jahitan flat untuk hidrodinamis.'],
      ['nama'=>'Swimming cap','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Swim_cap.jpg/200px-Swim_cap.jpg','desc'=>'Silikon, mengurangi hambatan air.'],
      ['nama'=>'Pull buoy & papan luncur','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4f/Pull_buoy.jpg/200px-Pull_buoy.jpg','desc'=>'Alat bantu latih teknik tangan & kaki.'],
    ],
  ],
  [
    'slug'   => 'hiking',
    'judul'  => 'Hiking / Mendaki Gunung',
    'icon'   => 'bi-tree', 'warna' => 'success',
    'definisi'=> 'Kegiatan berjalan kaki menyusuri jalur alam (gunung, bukit, hutan) dengan tujuan rekreasi, olahraga ketahanan, atau pencapaian puncak. Bukan kompetisi waktu murni, tetapi mengandalkan stamina, navigasi, dan manajemen logistik.',
    'cara'   => 'Susun rencana perjalanan (waktu, ketinggian, cuaca). Gunakan sepatu hiking, beban ransel ≤ 25% berat badan. Atur ritme langkah pelan-konstan (rest step). Hidrasi tiap 20–30 menit, snack tiap 1 jam.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Hiker_silhouette.svg/320px-Hiker_silhouette.svg.png',
    'tim'    => 'Bisa solo, namun disarankan minimal 3 orang (kaidah pendakian: leader, sweeper, navigator). Ekspedisi besar 5–10 orang dengan pembagian peran logistik, medis, dan dokumentasi.',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5a/Hiking_group_silhouette.svg/640px-Hiking_group_silhouette.svg.png',
    'skoring'=> 'Bukan kompetisi skor. Capaian diukur dari: ketinggian (mdpl) yang tercapai, jarak tempuh, total elevasi naik (m gain), dan waktu tempuh.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/Elevation_profile_example.svg/640px-Elevation_profile_example.svg.png',
    'menang' => 'Tujuan: kembali pulang dengan selamat. "Kalah" = harus turun (turun gunung) sebelum puncak karena cuaca, cedera, atau kondisi tubuh. Etika: leave no trace — jangan tinggalkan sampah.',
    'video'  => 'https://www.youtube.com/watch?v=w_4K5Pm0Qsc&t=143s',
    'videoLabel' => 'Teknik Hiking yang Benar',
    'peralatan' => [
      ['nama'=>'Sepatu hiking','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Hiking_boots.jpg/200px-Hiking_boots.jpg','desc'=>'Sol Vibram, water-resistant, ankle support.'],
      ['nama'=>'Ransel / carrier','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/Backpacking_backpack.jpg/200px-Backpacking_backpack.jpg','desc'=>'Kapasitas 40–60 L untuk pendakian 1–2 malam.'],
      ['nama'=>'Tenda & sleeping bag','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Camping_tent.jpg/200px-Camping_tent.jpg','desc'=>'Tenda double layer & SB rating sesuai suhu.'],
      ['nama'=>'Headlamp & kompas/GPS','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Headlamp.jpg/200px-Headlamp.jpg','desc'=>'Navigasi malam & jalur tertutup kabut.'],
    ],
  ],
  [
    'slug'   => 'pingpong',
    'judul'  => 'Tenis Meja (PingPong)',
    'icon'   => 'bi-circle-fill', 'warna' => 'warning',
    'definisi'=> 'Olahraga raket yang memukul bola seluloid berdiameter 40 mm di atas meja 2,74 × 1,525 m yang dibatasi net 15,25 cm. Diatur oleh ITTF.',
    'cara'   => 'Servis dengan bola dilempar minimal 16 cm tegak lurus, lalu dipukul agar memantul sekali di meja sendiri dan sekali di meja lawan. Pukul kembali bola sebelum memantul dua kali di meja sendiri. Teknik: forehand drive, backhand drive, push, chop, smash, dan spin.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7d/Table_tennis_diagram.svg/640px-Table_tennis_diagram.svg.png',
    'tim'    => 'Tunggal (1 vs 1), Ganda (2 vs 2 dengan giliran pukul bergantian), Ganda Campuran. Beregu: 3–5 pemain (sistem Swaythling/Corbillon Cup).',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Table_tennis_doubles.svg/640px-Table_tennis_doubles.svg.png',
    'skoring'=> 'Rally point — set dimenangkan pemain pertama yang mencapai 11 poin dengan selisih ≥ 2. Servis berganti tiap 2 poin (atau tiap 1 poin saat skor 10-10/deuce).',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/81/Table_tennis_table.svg/640px-Table_tennis_table.svg.png',
    'menang' => 'Menang: 4 set lebih dulu dari best of 7 (atau 3 dari best of 5). Pelanggaran: servis menyentuh net & masuk berkali-kali (let), bola memantul dua kali, badan menyentuh meja, atau memukul sebelum bola memantul.',
    'video'  => 'https://www.youtube.com/watch?v=MasmG88gzFA',
    'videoLabel' => 'Teknik Dasar PingPong',
    'peralatan' => [
      ['nama'=>'Bet (raket)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b3/Table_tennis_racket.jpg/200px-Table_tennis_racket.jpg','desc'=>'Karet 2 mm, 1 sisi merah & 1 sisi hitam.'],
      ['nama'=>'Bola seluloid 40+ mm','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Table_tennis_ball.jpg/200px-Table_tennis_ball.jpg','desc'=>'Diameter 40 mm, bintang ★★★ untuk turnamen.'],
      ['nama'=>'Meja standar ITTF','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Table_tennis_table_layout.svg/200px-Table_tennis_table_layout.svg.png','desc'=>'2,74 × 1,525 m, tinggi 76 cm.'],
      ['nama'=>'Net & klem','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d8/Table_tennis_net.jpg/200px-Table_tennis_net.jpg','desc'=>'Tinggi net 15,25 cm di atas permukaan meja.'],
    ],
  ],
  [
    'slug'   => 'futsal',
    'judul'  => 'Futsal',
    'icon'   => 'bi-dribbble', 'warna' => 'primary',
    'definisi'=> 'Sepak bola versi indoor di lapangan keras 25–42 m × 16–25 m dengan bola lebih kecil dan lebih sedikit pantul. Diatur oleh FIFA. Permainan cepat, menekankan kontrol bola dan umpan pendek.',
    'cara'   => 'Mainkan bola dengan kaki (kiper boleh tangan di kotak penalti). Cetak gol ke gawang lawan. Pelanggaran fisik dihitung sebagai akumulasi foul tim; foul ke-6 dalam 1 babak = tendangan bebas tanpa pagar dari titik 10 m.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c0/Futsal_pitch.svg/640px-Futsal_pitch.svg.png',
    'tim'    => '5 pemain per tim di lapangan (1 kiper + 4 pemain), maks 9 cadangan. Pergantian "rolling" tidak terbatas dan bisa kapan saja.',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/97/Futsal_formation_diamond.svg/640px-Futsal_formation_diamond.svg.png',
    'skoring'=> '1 gol = 1 poin. Pertandingan 2 × 20 menit waktu bersih (jam berhenti saat bola mati). Time-out 1 menit per tim per babak.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Futsal_goal.svg/640px-Futsal_goal.svg.png',
    'menang' => 'Menang: poin (gol) lebih banyak setelah 2 babak. Imbang di kompetisi: extra time 2 × 5 menit lalu adu penalti. Kalah teknis: pemain di lapangan < 3 (kiper merah / cedera tanpa cadangan).',
    'video'  => 'https://www.youtube.com/watch?v=tRrEHQSsfiA&t=8s',
    'videoLabel' => 'Teknik Dasar Futsal',
    'peralatan' => [
      ['nama'=>'Bola futsal (low bounce)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0a/Futsal_ball.jpg/200px-Futsal_ball.jpg','desc'=>'Ukuran 4, pantulan rendah 50–65 cm dari 2 m.'],
      ['nama'=>'Sepatu futsal (sol karet)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4b/Futsal_shoes.jpg/200px-Futsal_shoes.jpg','desc'=>'Non-marking, cocok untuk lantai parket/vinyl.'],
      ['nama'=>'Jersey & celana pendek','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9b/Futsal_team_Spain.jpg/200px-Futsal_team_Spain.jpg','desc'=>'Bahan dry-fit, nomor punggung jelas.'],
      ['nama'=>'Shin guard & sarung tangan kiper','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/Shin_guard.jpg/200px-Shin_guard.jpg','desc'=>'Pelindung tulang kering + glove kiper.'],
    ],
  ],
  [
    'slug'   => 'biliard',
    'judul'  => 'Biliar (Billiard / Pool)',
    'icon'   => 'bi-circle', 'warna' => 'dark',
    'definisi'=> 'Olahraga presisi di atas meja berlapis kain (felt) dengan bola-bola berwarna dan stik (cue). Varian populer: 8-Ball, 9-Ball, Snooker, dan Carom. Diatur oleh WPA / WCBS (snooker).',
    'cara'   => 'Letakkan cue ball, arahkan stik (bridge tangan stabil), pukul cue ball agar menyentuh bola target dan masukkan ke lubang (pocket). Pukulan pertama (break) memecah formasi bola.',
    'cara_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3b/Pool_table_diagram.svg/640px-Pool_table_diagram.svg.png',
    'tim'    => 'Umumnya 1 vs 1 (tunggal). Ada juga format ganda (Scotch doubles — pukulan bergantian) dan beregu (mis. Mosconi Cup 5 pemain per tim).',
    'tim_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3a/Eight_ball_rack.svg/640px-Eight_ball_rack.svg.png',
    'skoring'=> '8-Ball/9-Ball: hitung bola yang dimasukkan (frame win). Snooker: bola berwarna punya nilai 1–7. Match = best of X frame.',
    'skoring_img'=> 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8c/Snooker_table_setup.svg/640px-Snooker_table_setup.svg.png',
    'menang' => 'Menang 8-Ball: masukkan semua bola grup (solid/stripe) lalu masukkan bola 8 di pocket yang dideklarasikan. Foul: cue ball masuk lubang (scratch), menyentuh bola lawan duluan, atau tidak ada bola yang menyentuh ban.',
    'video'  => 'https://www.youtube.com/watch?v=taf9JBG9iLA',
    'videoLabel' => 'Teknik Dasar Biliar',
    'peralatan' => [
      ['nama'=>'Stik biliar (cue)','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/5/52/Pool_cue.jpg/200px-Pool_cue.jpg','desc'=>'Panjang 145–150 cm, tip 12–13 mm.'],
      ['nama'=>'Set bola pool','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/8_ball_break.jpg/200px-8_ball_break.jpg','desc'=>'1 cue ball putih + 15 bola bernomor.'],
      ['nama'=>'Meja biliar','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Pool_table_8ft.jpg/200px-Pool_table_8ft.jpg','desc'=>'Ukuran 7/8/9 ft, kain wool felt.'],
      ['nama'=>'Kapur stik & rack segitiga','img'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/Pool_chalk.jpg/200px-Pool_chalk.jpg','desc'=>'Kapur biru + rack segitiga untuk break.'],
    ],
  ],
];

$VIDEO_PAKET = [
  ['judul'=>'Paket Pemanasan Olahraga','warna'=>'warning','icon'=>'bi-fire','desc'=>'Wajib dilakukan 5–10 menit sebelum olahraga untuk meningkatkan suhu otot, jangkauan gerak, dan mencegah cedera.','url'=>'https://www.youtube.com/watch?v=Ks5dz69gsDk&t=19s'],
  ['judul'=>'Paket Pendinginan Olahraga','warna'=>'info','icon'=>'bi-snow','desc'=>'Lakukan 5–10 menit setelah olahraga untuk menurunkan detak jantung perlahan, mempercepat recovery, dan mengurangi nyeri otot (DOMS).','url'=>'https://www.youtube.com/watch?v=uXznjq2BLMI&t=54s'],
];

include __DIR__.'/includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/sport-islami.css">
<style>
.ao-illust{width:100%;max-height:160px;object-fit:contain;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:6px;}
[data-bs-theme=dark] .ao-illust{background:#0f172a;border-color:#1f2937;}
.ao-illust-wrap{margin-top:.35rem;margin-bottom:.5rem;}
.ao-illust-cap{font-size:.72rem;color:#6b7280;margin-top:.2rem;text-align:center;}
.ao-eq-card{display:flex;gap:.6rem;align-items:center;padding:.5rem;border:1px solid #e5e7eb;border-radius:10px;background:#fff;height:100%;}
[data-bs-theme=dark] .ao-eq-card{background:#0b1220;border-color:#1f2937;}
.ao-eq-img{width:56px;height:56px;object-fit:cover;border-radius:8px;flex:0 0 56px;background:#f1f5f9;}
.ao-eq-card strong{font-size:.85rem;line-height:1.15;}
.ao-eq-card .ao-eq-desc{font-size:.72rem;color:#6b7280;line-height:1.2;}
</style>

<div class="hero-sport-islami hero-artikel mb-3">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-journal-richtext"></i> Artikel &amp; Teknik Olahraga</h1>
    <p class="small mb-0 opacity-85">Panduan ringkas tiap olahraga: <strong>Definisi · Cara Main · Pembagian Tim · Skoring · Sistem Menang-Kalah · Peralatan</strong> · plus video teknik &amp; paket pemanasan/pendinginan.</p>
  </div>
</div>

<!-- Paket Pemanasan & Pendinginan -->
<div class="row g-3 mb-4">
  <?php foreach($VIDEO_PAKET as $p): $vid = $ytId($p['url']); ?>
    <div class="col-md-6">
      <div class="card shadow-sm h-100 border-<?= $p['warna'] ?>">
        <div class="card-header bg-<?= $p['warna'] ?>-subtle text-<?= $p['warna'] ?>-emphasis">
          <i class="bi <?= $p['icon'] ?>"></i> <strong><?= htmlspecialchars($p['judul']) ?></strong>
        </div>
        <div class="card-body">
          <p class="small mb-2"><?= htmlspecialchars($p['desc']) ?></p>
          <?php if ($vid): ?>
            <div class="ratio ratio-16x9 rounded overflow-hidden border">
              <iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($vid) ?>"
                title="<?= htmlspecialchars($p['judul']) ?>"
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<h2 class="h5 mb-3"><i class="bi bi-trophy text-warning"></i> Daftar Olahraga (video teknik · ilustrasi · peralatan)</h2>

<div class="row g-3">
  <?php foreach($ARTIKEL as $a): $vid = $ytId($a['video']); ?>
    <div class="col-12">
      <div class="card shadow-sm border-<?= $a['warna'] ?>" id="<?= htmlspecialchars($a['slug']) ?>">
        <div class="card-header bg-<?= $a['warna'] ?>-subtle text-<?= $a['warna'] ?>-emphasis d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><i class="bi <?= $a['icon'] ?>"></i> <strong><?= htmlspecialchars($a['judul']) ?></strong></span>
          <a href="#<?= htmlspecialchars($a['slug']) ?>" class="small text-decoration-none">#<?= htmlspecialchars($a['slug']) ?></a>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-5">
              <?php if ($vid): ?>
                <div class="ratio ratio-16x9 rounded overflow-hidden border">
                  <iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($vid) ?>"
                    title="<?= htmlspecialchars($a['videoLabel']) ?>"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
                <div class="small text-muted mt-1"><i class="bi bi-youtube text-danger"></i> <?= htmlspecialchars($a['videoLabel']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-7">
              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Definisi:</strong>
                <div class="small"><?= htmlspecialchars($a['definisi']) ?></div>
              </div>

              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Cara Main:</strong>
                <div class="small"><?= htmlspecialchars($a['cara']) ?></div>
                <div class="ao-illust-wrap">
                  <img src="<?= ao_anim_svg($a['slug'], 'cara') ?>"
                       alt="Animasi cara main <?= htmlspecialchars($a['judul']) ?>"
                       class="ao-illust" loading="lazy">
                  <div class="ao-illust-cap"><i class="bi bi-play-circle"></i> Animasi cara main / posisi pemain</div>
                </div>
              </div>

              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Pembagian Tim:</strong>
                <div class="small"><?= htmlspecialchars($a['tim']) ?></div>
                <div class="ao-illust-wrap">
                  <img src="<?= ao_anim_svg($a['slug'], 'tim') ?>"
                       alt="Animasi pembagian tim <?= htmlspecialchars($a['judul']) ?>"
                       class="ao-illust" loading="lazy">
                  <div class="ao-illust-cap"><i class="bi bi-diagram-3"></i> Diagram pembagian tim / formasi</div>
                </div>
              </div>

              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Sistem Skoring:</strong>
                <div class="small"><?= htmlspecialchars($a['skoring']) ?></div>
                <div class="ao-illust-wrap">
                  <img src="<?= ao_anim_svg($a['slug'], 'skor') ?>"
                       alt="Animasi sistem skoring <?= htmlspecialchars($a['judul']) ?>"
                       class="ao-illust" loading="lazy">
                  <div class="ao-illust-cap"><i class="bi bi-bullseye"></i> Visualisasi area skoring / lapangan</div>
                </div>
              </div>

              <div class="mb-3"><strong class="text-<?= $a['warna'] ?>">Sistem Pemenang &amp; Kalah:</strong>
                <div class="small"><?= htmlspecialchars($a['menang']) ?></div>
              </div>

              <?php if(!empty($a['peralatan'])): ?>
              <div>
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-tools"></i> Peralatan yang Dibutuhkan:</strong>
                <div class="row g-2 mt-1">
                  <?php foreach($a['peralatan'] as $eq): ?>
                    <div class="col-12 col-sm-6">
                      <div class="ao-eq-card">
                        <img src="<?= htmlspecialchars(ao_eq_img($a['slug'], $eq['nama'])) ?>"
                             alt="<?= htmlspecialchars($eq['nama']) ?>"
                             class="ao-eq-img" loading="lazy"
                             onerror="this.style.background='#e2e8f0';this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56"><rect width="56" height="56" fill="#94a3b8"/><text x="28" y="32" text-anchor="middle" font-size="10" fill="#fff" font-family="Arial">No Img</text></svg>') ?>'">
                        <div>
                          <strong><?= htmlspecialchars($eq['nama']) ?></strong>
                          <div class="ao-eq-desc"><?= htmlspecialchars($eq['desc']) ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="alert alert-info small mt-4 mb-0">
  <i class="bi bi-info-circle"></i> Konten edukatif umum yang dirangkum dari aturan resmi tiap federasi (BWF, ITTF, FIFA, WPA, FINA, IAU). Untuk pertandingan resmi, ikuti regulasi terbaru dari federasi yang relevan.
</div>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

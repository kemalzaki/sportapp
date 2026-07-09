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
require __DIR__.'/includes/paket_helpers.php'; // Revisi R13 — gate PRO
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
// Revisi R13 — Artikel Olahraga & Teknik khusus paket PRO (Komunitas juga bisa akses)
paket_require_or_lock('pro', $u, 'Artikel Olahraga & Teknik',
    'Kumpulan artikel olahraga & teknik mendalam tersedia untuk paket PRO.');
$pageTitle = 'Artikel Olahraga & Teknik';
$pageSkeleton = 'feed';
$u = current_user();
$uid = (int)$u['id'];

/* ============================================================
 * Revisi 19 Juni 2026 — Tabel Tanya Jawab AI Olahraga (idempotent)
 * Pola sama dengan islami_qa_saved di islami.php.
 * ============================================================ */
try {
    db_exec("CREATE TABLE IF NOT EXISTS sport_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        jenis VARCHAR(50) NOT NULL DEFAULT '',
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS sport_qa_user_idx ON sport_qa_saved(user_id, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'qa_save') {
        header('Content-Type: application/json');
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban']    ?? ''));
        $jenis = substr(trim((string)($_POST['jenis'] ?? '')),0,50);
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000)  $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(),
            "INSERT INTO sport_qa_saved(user_id,jenis,pertanyaan,jawaban) VALUES($1,$2,$3,$4) RETURNING id",
            [$uid,$jenis,$q,$j]);
        echo json_encode(['ok'=>true,'id'=>(int)(pg_fetch_row($r)[0] ?? 0)]); exit;
    } elseif ($a === 'qa_delete') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM sport_qa_saved WHERE id=$1 AND user_id=$2", [$id,$uid]);
        echo json_encode(['ok'=>true]); exit;
    }
}

$qaSaved = db_all(
    "SELECT id, jenis, pertanyaan, jawaban, created_at
       FROM sport_qa_saved WHERE user_id=$1 ORDER BY id DESC LIMIT 50", [$uid]);

/* Revisi 23 Juni 2026 — Ambil daftar kata terlarang (blocklist) untuk filter pencarian video.
   Dikelola di admin/keywords.php (kategori: kasar / abuse / porno). */
$BLOCKED_WORDS = [];
try {
    $br = db_all("SELECT kata FROM search_keywords WHERE aktif=TRUE AND kategori IN('kasar','abuse','porno')");
    foreach ($br as $brow) {
        $w = mb_strtolower(trim((string)$brow['kata']));
        if ($w !== '') $BLOCKED_WORDS[] = $w;
    }
} catch (Throwable $e) { $BLOCKED_WORDS = []; }



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
    'basket'    => ['#ea580c', '#ffedd5', '#7c2d12'],
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

    case 'basket':
      $field = '<rect x="40" y="30" width="400" height="160" fill="#fed7aa" stroke="'.$dark.'" stroke-width="3"/>'
             . '<line x1="240" y1="30" x2="240" y2="190" stroke="'.$dark.'" stroke-width="2"/>'
             . '<circle cx="240" cy="110" r="32" fill="none" stroke="'.$dark.'" stroke-width="2"/>'
             . '<rect x="40" y="70" width="60" height="80" fill="none" stroke="'.$dark.'" stroke-width="2"/>'
             . '<rect x="380" y="70" width="60" height="80" fill="none" stroke="'.$dark.'" stroke-width="2"/>'
             . '<circle cx="70" cy="110" r="22" fill="none" stroke="'.$dark.'" stroke-width="2"/>'
             . '<circle cx="410" cy="110" r="22" fill="none" stroke="'.$dark.'" stroke-width="2"/>';
      $motion = '<circle r="9" fill="'.$fg.'" stroke="'.$dark.'" stroke-width="1"><animateMotion dur="2.5s" repeatCount="indefinite" path="M 90,110 Q 240,30 390,110 Q 240,180 90,110 Z"/></circle>';
      if ($section==='tim') {
        for ($i=0;$i<5;$i++){ $y=50+$i*22;
          $motion .= '<circle cx="130" cy="'.$y.'" r="8" fill="'.$fg.'"/>';
          $motion .= '<circle cx="350" cy="'.$y.'" r="8" fill="#1f2937"/>'; }
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
    'pingpong'=>'ca8a04','futsal'=>'2563eb','basket'=>'ea580c','biliard'=>'1f2937',
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
    'manfaat' => 'Meningkatkan kapasitas jantung &amp; paru (VO₂max), membakar lemak efektif (±400–700 kkal/jam), memperkuat otot kaki &amp; tulang, melepaskan endorfin (mengurangi stres &amp; depresi), serta memperbaiki kualitas tidur. Lari rutin terbukti menurunkan risiko penyakit jantung, diabetes tipe-2, dan hipertensi.',
    'penyembuhan' => 'Membantu mengontrol <b>diabetes tipe-2</b> (meningkatkan sensitivitas insulin), menurunkan <b>hipertensi</b> ringan-sedang, memperbaiki <b>kolesterol</b> (HDL naik, LDL turun), mengurangi gejala <b>depresi &amp; gangguan cemas ringan</b>, menurunkan risiko <b>stroke</b> dan <b>penyakit jantung koroner</b>, serta meredakan <b>insomnia</b> non-klinis.',
    'hormon'      => '<b>Endorfin</b> (pereda nyeri alami / runner\'s high), <b>dopamin</b> (motivasi &amp; reward), <b>serotonin</b> (mood &amp; tidur), <b>norepinefrin</b> (fokus), <b>BDNF</b> (regenerasi neuron), <b>HGH / growth hormone</b> (regenerasi otot), serta penurunan <b>kortisol</b> kronis.',
    'mental'      => '<b>Disiplin</b> (jadwal latihan), <b>ketekunan</b> (menyelesaikan jarak), <b>ketahanan mental</b> (push through fatigue), <b>fokus</b>, <b>kepercayaan diri</b> (mencapai PR pribadi), <b>manajemen stres</b>, dan <b>kesadaran tubuh</b> (mind-body awareness).',
    'peralatan' => [
      ['nama'=>'Sepatu lari (running shoes)','img'=>'/assets/img/peralatan/eq00.jpg','desc'=>'Bantalan EVA, drop 8–10 mm untuk pemula.'],
      ['nama'=>'Kaos & celana lari','img'=>'/assets/img/peralatan/eq01.jpg','desc'=>'Bahan dry-fit, ringan, menyerap keringat.'],
      ['nama'=>'Jam GPS / Smartwatch','img'=>'/assets/img/peralatan/eq02.jpg','desc'=>'Mengukur pace, jarak, HR, ketinggian.'],
      ['nama'=>'Botol air / hydration belt','img'=>'/assets/img/peralatan/eq03.jpg','desc'=>'Untuk lari ≥ 8 km dan cuaca panas.'],
    ],
    'tips_perawatan' => [
      'Cuci sepatu lari dengan lap basah, hindari mesin cuci agar bantalan tidak rusak.',
      'Angin-anginkan sepatu di tempat teduh setelah dipakai, jangan dijemur langsung di matahari.',
      'Rotasi 2 pasang sepatu agar busa midsole sempat memulihkan kepadatan (24–48 jam).',
      'Ganti sepatu lari setelah 600–800 km pemakaian (busa sudah mati & meningkatkan risiko cedera).',
      'Simpan pakaian dry-fit di tempat kering & berventilasi untuk mencegah bakteri penyebab bau.',
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
    'manfaat' => 'Melatih refleks &amp; koordinasi mata–tangan, meningkatkan kelincahan (agility) dan kekuatan otot tungkai/lengan, membakar 350–500 kkal/jam, melatih kapasitas anaerobik, memperbaiki postur tubuh, serta menjadi olahraga sosial yang menyenangkan untuk semua usia.',
    'penyembuhan' => 'Mengurangi risiko <b>penyakit jantung &amp; hipertensi</b>, memperbaiki <b>profil lipid</b>, membantu <b>obesitas</b> (kalori tinggi), meningkatkan <b>fungsi kognitif lansia</b> (mencegah dementia ringan), meredakan <b>gejala depresi</b>, memperbaiki <b>postur</b> &amp; nyeri punggung bawah ringan, serta menjaga <b>kepadatan tulang</b>.',
    'hormon'      => '<b>Adrenalin / epinefrin</b> (lonjakan saat rally), <b>dopamin</b> (kesenangan menang poin), <b>endorfin</b>, <b>serotonin</b>, <b>HGH</b>, serta penurunan <b>kortisol</b>.',
    'mental'      => '<b>Konsentrasi cepat</b>, <b>pengambilan keputusan sepersekian detik</b>, <b>ketenangan di bawah tekanan</b>, <b>sportivitas</b>, <b>strategi</b> (membaca pola lawan), <b>kerja sama</b> (di ganda), dan <b>resiliensi</b> (bangkit setelah kalah set).',
    'peralatan' => [
      ['nama'=>'Raket bulu tangkis','img'=>'/assets/img/peralatan/eq04.jpg','desc'=>'Berat 80–95 g, senar 22–28 lbs.'],
      ['nama'=>'Shuttlecock (kok)','img'=>'/assets/img/peralatan/eq05.jpg','desc'=>'Bulu angsa untuk turnamen, plastik untuk latihan.'],
      ['nama'=>'Sepatu badminton','img'=>'/assets/img/peralatan/eq06.jpg','desc'=>'Sol gum non-marking, ringan, grip kuat.'],
      ['nama'=>'Net & tiang','img'=>'/assets/img/peralatan/eq07.jpg','desc'=>'Tinggi net 1,55 m di tepi, 1,524 m di tengah.'],
    ],
    'tips_perawatan' => [
      'Lap raket dengan kain microfiber setelah pakai, hindari paparan suhu ekstrem (mobil panas).',
      'Senar raket: ganti tiap 3–6 bulan atau saat tegangan turun (suara tidak nyaring lagi).',
      'Pakai cover raket saat tidak digunakan untuk mencegah benturan & debu.',
      'Sepatu badminton: bersihkan sol karet dari debu agar grip tetap maksimal di lapangan.',
      'Shuttlecock bulu: simpan di tabung tertutup dengan kelembapan rendah agar bulu tidak rapuh.',
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
    'manfaat' => 'Olahraga full-body low-impact (ramah sendi), memperkuat otot punggung, dada, bahu, dan core, meningkatkan kapasitas paru-paru, melatih pernapasan ritmis, membakar 400–700 kkal/jam, serta sangat baik untuk rehabilitasi cedera dan penderita asma.',
    'penyembuhan' => 'Sangat baik untuk <b>asma</b> (latihan pernapasan terkontrol), <b>rehabilitasi cedera sendi</b> &amp; pasca operasi, <b>osteoarthritis</b> &amp; <b>nyeri lutut</b> (tanpa beban impact), <b>nyeri punggung bawah kronis</b>, <b>skoliosis ringan</b>, mengurangi <b>hipertensi</b>, membantu <b>obesitas</b>, dan mendukung <b>kehamilan</b> (tetap aktif aman).',
    'hormon'      => '<b>Endorfin</b>, <b>serotonin</b>, <b>dopamin</b>, <b>oksitosin</b> (efek menenangkan air), <b>HGH</b> (regenerasi), dan penurunan signifikan <b>kortisol</b> berkat efek hidrostatis air.',
    'mental'      => '<b>Ketenangan</b> (efek meditatif air), <b>kontrol pernapasan</b>, <b>kesabaran</b> (teknik berulang), <b>keberanian</b> (mengatasi takut air), <b>fokus internal</b>, dan <b>mindfulness</b>.',
    'peralatan' => [
      ['nama'=>'Kacamata renang','img'=>'/assets/img/peralatan/eq08.jpg','desc'=>'Anti-fog & UV, karet silikon empuk.'],
      ['nama'=>'Baju renang','img'=>'/assets/img/peralatan/eq09.jpg','desc'=>'Polyester/Lycra, jahitan flat untuk hidrodinamis.'],
      ['nama'=>'Swimming cap','img'=>'/assets/img/peralatan/eq10.jpg','desc'=>'Silikon, mengurangi hambatan air.'],
      ['nama'=>'Pull buoy & papan luncur','img'=>'/assets/img/peralatan/eq11.jpg','desc'=>'Alat bantu latih teknik tangan & kaki.'],
    ],
    'tips_perawatan' => [
      'Bilas kacamata renang & baju renang dengan air bersih segera setelah pakai (klorin merusak karet & serat).',
      'Jangan jemur baju renang langsung di matahari — gantung di tempat teduh berangin.',
      'Anti-fog kacamata: jangan diusap bagian dalam, cukup bilas — usapan menghapus lapisan anti-kabut.',
      'Simpan kacamata di kotak khusus agar lensa tidak tergores.',
      'Ganti baju renang tiap 6–12 bulan pemakaian aktif (elastisitas berkurang).',
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
    'manfaat' => 'Meningkatkan daya tahan kardiovaskular pada elevasi tinggi, memperkuat otot tungkai &amp; gluteus, membakar 350–550 kkal/jam, menurunkan stres berkat paparan alam (forest bathing), meningkatkan kepadatan tulang, serta melatih mental tangguh dan kerja sama tim ekspedisi.',
    'penyembuhan' => 'Menurunkan <b>tekanan darah</b>, memperbaiki <b>kolesterol</b>, menurunkan <b>gula darah</b> (diabetes tipe-2), mengatasi <b>depresi ringan-sedang</b> (terapi alam terbukti efektif), meredakan <b>burnout</b> &amp; <b>kelelahan mental kronis</b>, memperkuat <b>sistem imun</b> (phytoncides hutan), serta mengurangi risiko <b>osteoporosis</b>.',
    'hormon'      => '<b>Endorfin</b>, <b>serotonin</b> (paparan sinar matahari), <b>vitamin D</b> (pro-hormon), <b>dopamin</b>, <b>oksitosin</b> (ikatan sosial tim), <b>melatonin</b> (tidur lebih baik), serta penurunan drastis <b>kortisol</b> di lingkungan hijau.',
    'mental'      => '<b>Mental tangguh</b> (grit), <b>kesabaran</b>, <b>kemandirian</b>, <b>problem-solving</b> (navigasi &amp; cuaca), <b>kerjasama tim</b>, <b>rendah hati</b> (menghadapi alam), <b>fokus jangka panjang</b>, serta <b>rasa syukur</b> &amp; <b>spiritualitas</b>.',
    'peralatan' => [
      ['nama'=>'Sepatu hiking','img'=>'/assets/img/peralatan/eq12.jpg','desc'=>'Sol Vibram, water-resistant, ankle support.'],
      ['nama'=>'Ransel / carrier','img'=>'/assets/img/peralatan/eq13.jpg','desc'=>'Kapasitas 40–60 L untuk pendakian 1–2 malam.'],
      ['nama'=>'Tenda & sleeping bag','img'=>'/assets/img/peralatan/eq14.jpg','desc'=>'Tenda double layer & SB rating sesuai suhu.'],
      ['nama'=>'Headlamp & kompas/GPS','img'=>'/assets/img/peralatan/eq15.jpg','desc'=>'Navigasi malam & jalur tertutup kabut.'],
    ],
    'tips_perawatan' => [
      'Cuci sepatu hiking dengan sikat lembut & sabun netral, keringkan dengan koran di dalam sepatu.',
      'Re-waterproofing sepatu/jaket gore-tex tiap 6 bulan dengan spray khusus.',
      'Tas carrier: cuci dengan tangan, tidak boleh mesin cuci (jahitan & frame bisa rusak).',
      'Tenda: keringkan sempurna sebelum disimpan agar tidak berjamur.',
      'Periksa kompor portabel & gas sebelum trip, simpan terpisah dari makanan.',
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
    'manfaat' => 'Melatih kecepatan reaksi (refleks ekstrem), koordinasi mata–tangan, dan fokus mental, membakar 200–350 kkal/jam, meningkatkan keseimbangan, baik untuk kesehatan otak lansia (terbukti menunda demensia), serta bisa dimainkan di ruang sempit.',
    'penyembuhan' => 'Memperbaiki <b>fungsi kognitif</b> (terbukti memperlambat <b>Alzheimer</b> &amp; <b>demensia</b>), bermanfaat untuk pemulihan <b>stroke ringan</b> (motorik halus), <b>parkinson tahap awal</b> (koordinasi), menurunkan <b>kecemasan</b>, serta menjaga <b>kebugaran lansia</b> tanpa beban impact pada sendi.',
    'hormon'      => '<b>Dopamin</b> (kepuasan reaksi cepat), <b>endorfin</b>, <b>asetilkolin</b> (neurotransmitter fokus), <b>norepinefrin</b> (kewaspadaan), serta <b>BDNF</b> (neuroplastisitas otak).',
    'mental'      => '<b>Fokus laser-sharp</b>, <b>refleks cepat</b>, <b>kontrol emosi</b> (tidak panik di rally cepat), <b>ketangkasan kognitif</b>, <b>strategi membaca lawan</b>, dan <b>kesabaran</b>.',
    'peralatan' => [
      ['nama'=>'Bet (raket)','img'=>'/assets/img/peralatan/eq16.jpg','desc'=>'Karet 2 mm, 1 sisi merah & 1 sisi hitam.'],
      ['nama'=>'Bola seluloid 40+ mm','img'=>'/assets/img/peralatan/eq17.jpg','desc'=>'Diameter 40 mm, bintang ★★★ untuk turnamen.'],
      ['nama'=>'Meja standar ITTF','img'=>'/assets/img/peralatan/eq18.jpg','desc'=>'2,74 × 1,525 m, tinggi 76 cm.'],
      ['nama'=>'Net & klem','img'=>'/assets/img/peralatan/eq19.jpg','desc'=>'Tinggi net 15,25 cm di atas permukaan meja.'],
    ],
    'tips_perawatan' => [
      'Cuci karet bet dengan air & spons lembut atau cleaner khusus, lalu lap kering.',
      'Pakai pelindung karet (rubber protector) saat bet disimpan agar karet tidak teroksidasi.',
      'Hindari menyimpan bet di tempat panas/lembap — karet bisa mengeras & kehilangan spin.',
      'Ganti karet tiap 3–6 bulan untuk pemain rutin (saat permukaan sudah licin).',
      'Bola: cek bentuk bulat sempurna sebelum main; bola peyot mengganggu pantulan.',
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
    'manfaat' => 'Olahraga kardio interval intens, membakar 500–800 kkal/jam, meningkatkan kelincahan, kecepatan, daya ledak otot kaki, melatih kerja sama tim &amp; pengambilan keputusan cepat, memperkuat tulang &amp; otot inti, serta mengembangkan jiwa kompetitif yang sehat.',
    'penyembuhan' => 'Membakar lemak agresif (bantu <b>obesitas</b>), memperbaiki <b>kontrol gula darah</b>, menurunkan <b>tekanan darah</b>, mengurangi <b>stres</b> &amp; <b>agresivitas terpendam</b> (penyaluran energi positif), memperkuat <b>kepadatan tulang</b>, serta memperbaiki <b>kualitas tidur</b>.',
    'hormon'      => '<b>Adrenalin</b> (intensitas tinggi), <b>endorfin</b>, <b>dopamin</b> (mencetak gol), <b>testosteron</b> (kompetitif), <b>HGH</b> (pemulihan otot), <b>oksitosin</b> (ikatan tim), serta penurunan <b>kortisol</b>.',
    'mental'      => '<b>Kerja sama tim</b>, <b>komunikasi cepat</b>, <b>kepemimpinan</b>, <b>sportivitas</b>, <b>pengambilan keputusan di bawah tekanan</b>, <b>fair play</b>, <b>resiliensi</b> (bangkit setelah kebobolan), dan <b>kepercayaan rekan</b>.',
    'peralatan' => [
      ['nama'=>'Bola futsal (low bounce)','img'=>'/assets/img/peralatan/eq20.jpg','desc'=>'Ukuran 4, pantulan rendah 50–65 cm dari 2 m.'],
      ['nama'=>'Sepatu futsal (sol karet)','img'=>'/assets/img/peralatan/eq21.jpg','desc'=>'Non-marking, cocok untuk lantai parket/vinyl.'],
      ['nama'=>'Jersey & celana pendek','img'=>'/assets/img/peralatan/eq22.jpg','desc'=>'Bahan dry-fit, nomor punggung jelas.'],
      ['nama'=>'Shin guard & sarung tangan kiper','img'=>'/assets/img/peralatan/eq23.jpg','desc'=>'Pelindung tulang kering + glove kiper.'],
    ],
    'tips_perawatan' => [
      'Cuci sepatu futsal dengan sikat halus & sabun netral; jangan rendam terlalu lama.',
      'Sol karet harus bersih dari debu agar grip di lantai parket/vinyl tetap optimal.',
      'Bola futsal: bersihkan dengan lap basah, jangan ditendang di permukaan kasar (aspal) — kulit cepat sobek.',
      'Pompa angin bola sesuai standar (0,6–0,9 atm) — terlalu kencang bisa pecahkan jahitan.',
      'Shin guard: cuci dengan sabun antibakteri tiap habis pakai untuk cegah jamur kulit.',
    ],
  ],
  [
    'slug'   => 'basket',
    'judul'  => 'Basket (Bola Basket)',
    'icon'   => 'bi-basket', 'warna' => 'warning',
    'definisi'=> 'Olahraga bola besar dimainkan 2 tim, masing-masing 5 pemain di lapangan, dengan tujuan memasukkan bola ke ring lawan setinggi 3,05 m. Diatur oleh FIBA (internasional) dan NBA (Amerika). Lapangan keras (parket / sintetis) ukuran 28 × 15 m (FIBA).',
    'cara'   => 'Pemain menggiring bola (dribble) dengan satu tangan, memberi umpan (pass) ke rekan, atau menembak (shoot) ke ring lawan. Dilarang berjalan tanpa men-dribble (travel), men-dribble dua kali (double dribble), atau menyerang lawan secara fisik (foul). Pertandingan dimulai dengan jump ball di lingkaran tengah.',
    'cara_img'=> '',
    'tim'    => '5 pemain per tim di lapangan: 1 point guard, 2 shooting guard / small forward, 2 power forward / center. Maksimal 7 cadangan. Pergantian pemain bebas saat bola mati.',
    'tim_img'=> '',
    'skoring'=> 'Tembakan di dalam garis 3 angka = 2 poin. Tembakan di luar garis 3 angka = 3 poin. Free throw (lemparan bebas) = 1 poin. Pertandingan FIBA: 4 × 10 menit (NBA: 4 × 12 menit). Waktu serangan dibatasi shot clock 24 detik.',
    'skoring_img'=> '',
    'menang' => 'Menang: tim dengan poin terbanyak setelah 4 quarter. Bila imbang: overtime 5 menit, diulang sampai ada pemenang. Pelanggaran: travel, double dribble, 3 detik di area lawan, foul (5 foul personal = keluar di FIBA, 6 di NBA), goaltending (menghadang bola yang sedang turun ke ring).',
    'video'  => 'https://www.youtube.com/watch?v=tYqqOYHN0xU',
    'videoLabel' => 'Teknik Dasar Basket',
    'manfaat' => 'Olahraga kardio intens, membakar 500–750 kkal/jam, meningkatkan tinggi badan (lompatan vertikal merangsang lempeng pertumbuhan), memperkuat tungkai &amp; inti tubuh, melatih koordinasi tangan-mata, kerja sama tim, dan kelincahan (agility). Sangat baik untuk perkembangan postural remaja.',
    'penyembuhan' => 'Membantu mengatasi <b>obesitas</b>, memperbaiki <b>postur bungkuk</b> (karena terbiasa menjangkau ke atas), menurunkan <b>tekanan darah</b>, meningkatkan <b>kepadatan tulang</b> (pencegahan osteoporosis dini), melatih jantung &amp; paru, serta membantu pemulihan <b>kecemasan sosial</b> berkat interaksi tim.',
    'hormon'      => '<b>Adrenalin</b> (sprint pendek), <b>endorfin</b>, <b>dopamin</b> (mencetak poin), <b>HGH</b> (growth hormone karena lompatan vertikal), <b>testosteron</b> (kompetisi sehat), serta <b>oksitosin</b> (ikatan tim).',
    'mental'      => '<b>Kerja sama tim</b>, <b>komunikasi non-verbal</b> (eye contact / hand signal), <b>kepemimpinan</b>, <b>pengambilan keputusan cepat</b>, <b>kontrol emosi</b> (tidak panik di akhir kuarter), <b>resiliensi</b>, &amp; <b>sportivitas</b>.',
    'peralatan' => [
      ['nama'=>'Bola basket ukuran 7 (pria) / 6 (wanita)','img'=>'/assets/basket_bola.jpg','desc'=>'Karet/komposit, keliling 75–78 cm, berat 567–650 g.'],
      ['nama'=>'Sepatu basket (high cut)','img'=>'/assets/basket_sepatu.jpg','desc'=>'Sol non-marking, melindungi pergelangan kaki dari cedera engsel.'],
      ['nama'=>'Jersey &amp; celana basket','img'=>'/assets/basket_jersey.jpg','desc'=>'Bahan dry-fit longgar, nomor punggung &amp; nama jelas.'],
      ['nama'=>'Ring basket setinggi 3,05 m + papan pantul','img'=>'/assets/basket_ring.jpg','desc'=>'Diameter ring 45 cm, papan 1,8 × 1,05 m (FIBA).'],
    ],
    'tips_perawatan' => [
      'Lap bola basket dengan kain kering setelah pakai, simpan di tempat teduh.',
      'Hindari memantulkan bola indoor di permukaan kasar (aspal) — kulit/komposit cepat aus.',
      'Periksa tekanan angin bola (0,5–0,6 atm) seminggu sekali; pompa pelan-pelan dengan jarum yang dibasahi.',
      'Sepatu basket: jangan dipakai di luar lapangan agar sol non-marking tidak kotor & licin.',
      'Bersihkan ring & jaring secara berkala, jaring nilon ganti tiap 6–12 bulan bila sobek.',
      'Simpan jersey dry-fit dengan dilipat lembut, jangan disetrika panas (serat bisa meleleh).',
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
    'manfaat' => 'Melatih konsentrasi tinggi, kesabaran, dan presisi geometri (sudut &amp; gaya), memperbaiki postur tegak saat membungkuk, melatih koordinasi tangan halus, serta menjadi sarana sosialisasi dan strategi mental seperti catur fisik — cocok untuk relaksasi pikiran setelah kerja.',
    'penyembuhan' => 'Terapi <b>tremor halus</b> (Parkinson tahap awal), pemulihan <b>koordinasi pasca stroke ringan</b>, terapi <b>kecemasan</b> (efek meditatif), mencegah <b>demensia lansia</b>, membantu <b>nyeri leher/punggung ringan</b> berkat postur sadar, serta relaksasi bagi penderita <b>insomnia non-klinis</b>.',
    'hormon'      => '<b>Dopamin</b> (kepuasan presisi), <b>serotonin</b> (mood relaks), <b>endorfin</b>, <b>asetilkolin</b> (fokus), serta penurunan <b>kortisol</b> di lingkungan santai.',
    'mental'      => '<b>Kesabaran ekstrem</b>, <b>fokus berkelanjutan</b>, <b>kontrol emosi</b> (tidak terburu-buru), <b>perencanaan strategis</b> (memvisualisasikan beberapa langkah), <b>ketenangan</b>, dan <b>kepercayaan diri</b>.',
    'peralatan' => [
      ['nama'=>'Stik biliar (cue)','img'=>'/assets/img/peralatan/eq24.jpg','desc'=>'Panjang 145–150 cm, tip 12–13 mm.'],
      ['nama'=>'Set bola pool','img'=>'/assets/img/peralatan/eq25.jpg','desc'=>'1 cue ball putih + 15 bola bernomor.'],
      ['nama'=>'Meja biliar','img'=>'/assets/img/peralatan/eq26.jpg','desc'=>'Ukuran 7/8/9 ft, kain wool felt.'],
      ['nama'=>'Kapur stik & rack segitiga','img'=>'/assets/img/peralatan/eq27.jpg','desc'=>'Kapur biru + rack segitiga untuk break.'],
    ],
    'tips_perawatan' => [
      'Bersihkan stik (cue) dengan kain microfiber kering, hindari air & alkohol.',
      'Kapur tip stik secara merata sebelum tiap pukulan untuk cegah miscue.',
      'Ganti tip stik (kulit) tiap 6–12 bulan atau saat sudah pipih/keras.',
      'Bola biliar: lap dengan kain lembut & cleaner khusus, simpan di rak agar tidak berbenturan.',
      'Kain meja (felt): sikat searah serat dengan sikat khusus billiard, tutup dengan cover saat tidak dipakai.',
      'Jangan letakkan minuman/makanan di atas meja billiard — noda pada felt sulit dihilangkan.',
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
        <!-- Revisi 20 Juni 2026 R3 — Spoiler mode per jenis olahraga (klik untuk buka/tutup) -->
        <button class="card-header bg-<?= $a['warna'] ?>-subtle text-<?= $a['warna'] ?>-emphasis d-flex justify-content-between align-items-center flex-wrap gap-2 w-100 border-0 ao-spoiler-btn collapsed"
          type="button" data-bs-toggle="collapse"
          data-bs-target="#aoBody_<?= htmlspecialchars($a['slug']) ?>"
          aria-expanded="false" aria-controls="aoBody_<?= htmlspecialchars($a['slug']) ?>"
          style="text-align:left;cursor:pointer;">
          <span><i class="bi <?= $a['icon'] ?>"></i> <strong><?= htmlspecialchars($a['judul']) ?></strong>
            <span class="small ms-2 d-none d-sm-inline opacity-75">— klik untuk membuka detail</span>
          </span>
          <span class="d-flex align-items-center gap-2">
            <a href="#<?= htmlspecialchars($a['slug']) ?>" class="small text-decoration-none" onclick="event.stopPropagation()">#<?= htmlspecialchars($a['slug']) ?></a>
            <i class="bi bi-chevron-down ao-spoiler-caret"></i>
          </span>
        </button>
        <div class="collapse" id="aoBody_<?= htmlspecialchars($a['slug']) ?>">
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

              <?php /* Revisi 19 Juni 2026 — Manfaat olahraga */ ?>
              <?php if (!empty($a['manfaat'])): ?>
              <div class="mb-3">
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-heart-pulse-fill"></i> Manfaat untuk Tubuh:</strong>
                <div class="small alert alert-light border mt-1 mb-0 py-2"><?= $a['manfaat'] /* sudah HTML-safe (hanya &amp;) */ ?></div>
              </div>
              <?php endif; ?>

              <?php /* Revisi 19 Juni 2026 (R2) — Manfaat penyembuhan penyakit & khasiat */ ?>
              <?php if (!empty($a['penyembuhan'])): ?>
              <div class="mb-3">
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-bandaid-fill"></i> Khasiat &amp; Manfaat Penyembuhan Penyakit:</strong>
                <div class="small alert alert-success border mt-1 mb-0 py-2"><?= $a['penyembuhan'] ?></div>
              </div>
              <?php endif; ?>

              <?php /* Revisi 19 Juni 2026 (R2) — Hormon yang keluar */ ?>
              <?php if (!empty($a['hormon'])): ?>
              <div class="mb-3">
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-droplet-half"></i> Hormon yang Dikeluarkan:</strong>
                <div class="small alert alert-info border mt-1 mb-0 py-2"><?= $a['hormon'] ?></div>
              </div>
              <?php endif; ?>

              <?php /* Revisi 19 Juni 2026 (R2) — Mental yang terasah */ ?>
              <?php if (!empty($a['mental'])): ?>
              <div class="mb-3">
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-brain"></i> Mental yang Diasah:</strong>
                <div class="small alert alert-warning border mt-1 mb-0 py-2"><?= $a['mental'] ?></div>
              </div>
              <?php endif; ?>


              <?php /* Revisi 19 Juni 2026 — Tombol Pesan Tour Guide khusus hiking */ ?>
              <?php if (in_array($a['slug'], ['hiking'], true)):
                  $waGuideH = 'https://wa.me/6281234567890?text='.rawurlencode('Halo Admin SportApp, saya tertarik memesan Tour Guide untuk Hiking. Mohon info paket & jadwal.');
              ?>
              <div class="mb-3">
                <a class="btn btn-warning btn-sm fw-semibold" href="<?= $waGuideH ?>" target="_blank" rel="noopener">
                  <i class="bi bi-person-badge"></i> Pesan Tour Guide Hiking
                </a>
                <small class="text-muted ms-2">via WhatsApp Admin</small>
              </div>
              <?php endif; ?>


              <?php if(!empty($a['peralatan'])): ?>
              <div>
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-tools"></i> Peralatan yang Dibutuhkan:</strong>
                <div class="row g-2 mt-1">
                  <?php foreach($a['peralatan'] as $eq): ?>
                    <div class="col-12 col-sm-6">
                      <div class="ao-eq-card">
                        <?php
                          // Revisi 18 Juni 2026 (D) — Pakai foto asli di assets/img/peralatan/
                          // (eq00.jpg .. eq27.jpg). Fallback ke placehold.co bila file hilang,
                          // dan terakhir fallback inline SVG via onerror.
                          $eq_img = !empty($eq['img']) ? $eq['img'] : ao_eq_img($a['slug'], $eq['nama']);
                        ?>
                        <img src="<?= htmlspecialchars($eq_img) ?>"
                             alt="<?= htmlspecialchars($eq['nama']) ?>"
                             class="ao-eq-img" loading="lazy"
                             onerror="this.onerror=null;this.src='<?= htmlspecialchars(ao_eq_img($a['slug'], $eq['nama'])) ?>';this.dataset.fb=1;setTimeout(()=>{if(this.dataset.fb&&!this.complete){this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56"><rect width="56" height="56" fill="#94a3b8"/><text x="28" y="32" text-anchor="middle" font-size="10" fill="#fff" font-family="Arial">No Img</text></svg>') ?>';}},1500);">

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

              <?php /* Revisi R23 (27 Juni 2026) — Tips Merawat Alat Olahraga per jenis */ ?>
              <?php if (!empty($a['tips_perawatan'])): ?>
              <div class="mt-3">
                <strong class="text-<?= $a['warna'] ?>"><i class="bi bi-droplet-half"></i> Tips Merawat Alat Olahraga:</strong>
                <div class="alert alert-info border small mt-1 mb-0 py-2">
                  <ul class="mb-0 ps-3">
                    <?php foreach ($a['tips_perawatan'] as $tip): ?>
                      <li><?= htmlspecialchars($tip) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <?php endif; ?>

              <!-- ============================================================
                   Revisi 19 Juni 2026 — Pencarian Video Olahraga (YouTube Embed)
                   ============================================================ -->
              <div class="border rounded p-2 mt-3 bg-light-subtle ao-yt-box" data-slug="<?= htmlspecialchars($a['slug']) ?>">
                <div class="d-flex align-items-center mb-1 gap-2">
                  <i class="bi bi-youtube text-danger fs-5"></i>
                  <strong class="small">Cari Video <?= htmlspecialchars($a['judul']) ?> di YouTube</strong>
                </div>
                <div class="input-group input-group-sm mb-1">
                  <input type="text" class="form-control form-control-sm ao-yt-q"
                         placeholder="cth: tutorial <?= htmlspecialchars($a['judul']) ?> pemula"
                         value="tutorial <?= htmlspecialchars($a['judul']) ?>">
                  <button type="button" class="btn btn-danger btn-sm ao-yt-btn"><i class="bi bi-search"></i> Cari & Putar</button>
                  <?php /* Revisi 19 Juni 2026 (R2) — Tombol "buka di YouTube" dihapus sesuai permintaan. */ ?>

                </div>
                <div class="ao-yt-result small text-muted">Tekan tombol <b>Cari & Putar</b> — video akan langsung diputar di sini.</div>
              </div>

              <!-- ============================================================
                   Revisi 19 Juni 2026 — AI Problem Solver per olahraga
                   Pola sama dengan Tanya Jawab Islami di islami.php.
                   ============================================================ -->
              <div class="card border-<?= $a['warna'] ?> mt-3">
                <div class="card-header bg-<?= $a['warna'] ?>-subtle text-<?= $a['warna'] ?>-emphasis py-2">
                  <i class="bi bi-robot"></i> <strong>AI Problem Solver — <?= htmlspecialchars($a['judul']) ?></strong>
                </div>
                <div class="card-body py-2 ao-ai-box" data-slug="<?= htmlspecialchars($a['slug']) ?>" data-jenis="<?= htmlspecialchars($a['judul']) ?>">
                  <form class="ao-ai-form vstack gap-2 mb-2">
                    <textarea class="form-control form-control-sm ao-ai-inp" rows="2"
                              placeholder="Ceritakan kesulitan Anda di <?= htmlspecialchars($a['judul']) ?> (cedera, teknik, peralatan, pola latihan, dll)"></textarea>
                    <div class="d-flex gap-2 flex-wrap">
                      <button class="btn btn-<?= $a['warna'] ?> btn-sm" type="submit"><i class="bi bi-send"></i> Tanya AI</button>
                      <button class="btn btn-outline-secondary btn-sm ao-ai-clear" type="button"><i class="bi bi-eraser"></i> Bersihkan</button>
                      <small class="text-muted ms-auto align-self-center">Jawaban berbasis sport science, bukan diagnosis medis.</small>
                    </div>
                  </form>
                  <div class="ao-ai-out border rounded p-2 bg-body-tertiary small text-muted" style="min-height:60px">Tulis pertanyaan lalu klik <b>Tanya AI</b>.</div>
                  <div class="ao-ai-actions d-flex gap-2 mt-2" style="display:none !important">
                    <button type="button" class="btn btn-outline-<?= $a['warna'] ?> btn-sm ao-ai-save"><i class="bi bi-bookmark-plus"></i> Simpan Konsultasi</button>
                    <span class="ao-ai-stat small text-muted align-self-center"></span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
        </div><!-- /.collapse Revisi 20 Juni 2026 R3 -->
  <?php endforeach; ?>
</div>

<!-- ============================================================
     Revisi 19 Juni 2026 — Daftar Konsultasi AI Tersimpan
     ============================================================ -->
<div class="card shadow-sm mt-4">
  <div class="card-header bg-primary-subtle text-primary-emphasis">
    <i class="bi bi-bookmark-star"></i> <strong>Konsultasi AI Olahraga Tersimpan</strong>
    (<span id="qaSavedCount"><?= count($qaSaved) ?></span>)
  </div>
  <div class="card-body" id="qaSavedBox">
    <?php if (!$qaSaved): ?>
      <div class="small text-muted">Belum ada konsultasi tersimpan. Klik <b>Simpan Konsultasi</b> setelah AI menjawab.</div>
    <?php else: foreach ($qaSaved as $qa): ?>
      <div class="border rounded p-2 mb-2 small" data-qa-id="<?= (int)$qa['id'] ?>">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <strong class="text-primary"><i class="bi bi-patch-question"></i>
            [<?= htmlspecialchars($qa['jenis'] ?: 'umum') ?>]
            <?= htmlspecialchars(mb_strimwidth($qa['pertanyaan'],0,200,'…')) ?></strong>
          <button type="button" class="btn btn-sm btn-link text-danger p-0 qa-del-btn" data-id="<?= (int)$qa['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
        </div>
        <div class="text-muted small mb-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($qa['created_at']))) ?></div>
        <details><summary class="text-primary">Lihat jawaban</summary>
          <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($qa['jawaban']) ?></div>
        </details>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';

  /* Revisi 23 Juni 2026 — blocklist kata terlarang dari admin/keywords.php */
  window.AO_BLOCKED = <?= json_encode($BLOCKED_WORDS) ?>;
  window.AO_isBlocked = function(q){
    if (!q) return false;
    var ql = String(q).toLowerCase();
    var list = window.AO_BLOCKED || [];
    for (var i=0;i<list.length;i++){
      var w = String(list[i]||'').toLowerCase();
      if (w && ql.indexOf(w) !== -1) return true;
    }
    return false;
  };

  /* ===== Pencarian Video YouTube (Revisi R2 — Top 5 hasil, tanpa link YT) ===== */
  document.querySelectorAll('.ao-yt-box').forEach(function(box){
    var btn = box.querySelector('.ao-yt-btn');
    var inp = box.querySelector('.ao-yt-q');
    var out = box.querySelector('.ao-yt-result');
    function esc(s){ return String(s).replace(/[<>&"']/g,function(c){return ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'})[c];});}
    async function doSearch(){
      var q = (inp.value||'').trim();
      if (!q) return;
      /* Revisi 23 Juni 2026 — cek kata terlarang sebelum hit API */
      if (window.AO_isBlocked && window.AO_isBlocked(q)) {
        out.innerHTML = '<div class="alert alert-danger small mb-0"><i class="bi bi-shield-exclamation"></i> Kata pencarian mengandung kata terlarang. Video tidak ditampilkan.</div>';
        try { alert('⚠ Peringatan: kata pencarian "' + q + '" mengandung kata kasar / abuse / pornografi. Pencarian dibatalkan & video tidak akan ditampilkan.'); } catch(e){}
        return;
      }
      out.innerHTML = '<div class="d-flex align-items-center gap-2 small text-muted py-2"><span class="spinner-border spinner-border-sm"></span> Mencari video di YouTube…</div>';
      var oldHtml = btn.innerHTML; btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari…';
      try {
        var r = await fetch('/api_yt_search.php?q=' + encodeURIComponent(q), {credentials:'same-origin'});
        var j = await r.json();
        if (!j.ok) throw new Error(j.err || 'tidak ada hasil');
        var ids = (j.ids && j.ids.length) ? j.ids : (j.video ? [j.video] : []);
        if (!ids.length) throw new Error('tidak ada hasil');
        ids = ids.slice(0,5);
        var html = '<div class="small text-muted mb-2">Menampilkan <b>'+ids.length+'</b> video teratas untuk <b>'+esc(q)+'</b>:</div>';
        html += '<div class="row g-2">';
        ids.forEach(function(vid, i){
          html += '<div class="col-12 col-md-6">' +
            '<div class="ratio ratio-16x9 rounded overflow-hidden border">' +
              '<iframe loading="lazy" allowfullscreen ' +
                'src="https://www.youtube-nocookie.com/embed/'+encodeURIComponent(vid)+'?rel=0" ' +
                'allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' +
                'referrerpolicy="strict-origin-when-cross-origin"></iframe>' +
            '</div>' +
            '<div class="small text-muted mt-1">#'+(i+1)+' Hasil teratas</div>' +
          '</div>';
        });
        html += '</div>';
        out.innerHTML = html;
      } catch(e) {
        out.innerHTML = '<div class="small text-danger py-2"><i class="bi bi-exclamation-triangle"></i> Gagal mencari: ' +
          esc(e.message||String(e)) + '. Coba kata kunci lain.</div>';
      } finally {
        btn.disabled = false; btn.innerHTML = oldHtml;
      }
    }
    btn.addEventListener('click', doSearch);
    inp.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); doSearch(); }});
  });


  /* ===== AI Problem Solver per olahraga ===== */
  document.querySelectorAll('.ao-ai-box').forEach(function(box){
    var form  = box.querySelector('.ao-ai-form');
    var inp   = box.querySelector('.ao-ai-inp');
    var out   = box.querySelector('.ao-ai-out');
    var acts  = box.querySelector('.ao-ai-actions');
    var stat  = box.querySelector('.ao-ai-stat');
    var btnS  = box.querySelector('.ao-ai-save');
    var btnC  = box.querySelector('.ao-ai-clear');
    var jenis = box.dataset.jenis || '';
    var lastQ = '', lastA = '';
    var loading = false;

    btnC.addEventListener('click', function(){
      inp.value=''; out.className='ao-ai-out border rounded p-2 bg-body-tertiary small text-muted';
      out.textContent='Tulis pertanyaan lalu klik Tanya AI.';
      acts.style.display='none'; lastQ=''; lastA=''; stat.textContent='';
    });

    form.addEventListener('submit', async function(e){
      e.preventDefault();
      if (loading) return;
      var q = (inp.value||'').trim(); if (!q) return;
      if (q === lastQ && lastA){ stat.textContent='Pertanyaan sama — pakai jawaban sebelumnya.'; return; }
      loading = true;
      var b = form.querySelector('button[type=submit]'); var oh = b.innerHTML;
      b.disabled=true; b.innerHTML='<span class="spinner-border spinner-border-sm"></span> AI menjawab...';
      out.className='ao-ai-out border rounded p-2 bg-body-tertiary small text-muted';
      out.textContent='Sedang menjawab... (mohon tunggu)';
      acts.style.display='none';
      try {
        var fd = new FormData();
        fd.append('csrf', CSRF);
        fd.append('task', 'tanya_olahraga');
        fd.append('jenis', jenis);
        fd.append('prompt', q);
        var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
        var j = await r.json();
        if (!j.ok){
          out.className='ao-ai-out border rounded p-2 bg-warning-subtle small';
          out.textContent = 'Gagal: '+(j.err||'?');
        } else {
          out.className='ao-ai-out border rounded p-2 bg-body-tertiary small';
          var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                       .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                       .replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
          out.innerHTML = '<p>'+html+'</p>';
          lastQ = q; lastA = j.text||'';
          acts.style.display='flex';
        }
      } catch(err){
        out.className='ao-ai-out border rounded p-2 bg-warning-subtle small';
        out.textContent = 'Error: '+err.message;
      }
      b.disabled=false; b.innerHTML=oh; loading=false;
    });

    btnS.addEventListener('click', async function(){
      if (!lastQ || !lastA) return;
      btnS.disabled = true;
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action','qa_save');
      fd.append('jenis', jenis);
      fd.append('pertanyaan', lastQ);
      fd.append('jawaban', lastA);
      try {
        var r = await fetch('/artikel_olahraga.php',{method:'POST', body:fd, credentials:'same-origin'});
        var j = await r.json();
        if (j.ok){ stat.innerHTML='<i class="bi bi-check-circle text-success"></i> Tersimpan (#'+j.id+').'; }
        else stat.textContent='Gagal menyimpan.';
      } catch(e){ stat.textContent='Error: '+e.message; }
      btnS.disabled = false;
    });
  });

  /* Hapus konsultasi tersimpan */
  document.querySelectorAll('.qa-del-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      if (!confirm('Hapus konsultasi ini?')) return;
      var id = b.dataset.id;
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action','qa_delete');
      fd.append('id', id);
      var r = await fetch('/artikel_olahraga.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){
        var el = document.querySelector('[data-qa-id="'+id+'"]'); if (el) el.remove();
        var c = document.getElementById('qaSavedCount'); if (c) c.textContent = Math.max(0, (parseInt(c.textContent,10)||1)-1);
      }
    });
  });
})();
</script>

<div class="alert alert-info small mt-4 mb-0">
  <i class="bi bi-info-circle"></i> Konten edukatif umum yang dirangkum dari aturan resmi tiap federasi (BWF, ITTF, FIFA, WPA, FINA, IAU). Untuk pertandingan resmi, ikuti regulasi terbaru dari federasi yang relevan.
</div>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

<?php
/**
 * artikel_olahraga.php — Revisi 18 Juni 2026
 *
 *  + Teknik Olahraga (video YouTube): Lari, Badminton, Renang, Hiking, PingPong, Futsal, Biliard
 *  + Paket Pemanasan Olahraga (video) & Paket Pendinginan Olahraga (video)
 *  + Setiap olahraga menampilkan: Definisi, Cara Main, Pembagian Tim,
 *    Sistem Skoring, Sistem Pemenang/Kalah, dan Foto.
 *  + Renang menggunakan foto perenang laki-laki.
 *  + Tambahan: Artikel Biliar.
 *
 * Halaman ini menggabungkan menu lama "Panduan Olahraga", "Pemanasan Olahraga",
 * dan "Pendinginan Olahraga" dari navigasi mobile.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Artikel Olahraga & Teknik';

$ytId = function($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;
  if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $s, $m)) return $m[1];
  return '';
};

// ====== Data Artikel Olahraga (Definisi, Cara Main, Tim, Skoring, Menang-Kalah, Foto, Video) ======
$ARTIKEL = [
  [
    'slug'   => 'lari',
    'judul'  => 'Lari',
    'icon'   => 'bi-person-walking', 'warna' => 'success',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Olympic_marathon_2012_running_close_to_finish.jpg/640px-Olympic_marathon_2012_running_close_to_finish.jpg',
    'definisi'=> 'Lari adalah gerakan tubuh berpindah cepat dengan kedua kaki bergantian, di mana selalu ada fase melayang (kedua kaki tidak menyentuh tanah). Dipertandingkan sebagai cabang atletik (sprint, jarak menengah, jarak jauh, marathon, lari estafet, halang rintang).',
    'cara'   => 'Mulai dari posisi start (berdiri / jongkok untuk sprint). Tubuh sedikit condong ke depan, ayunkan lengan setinggi pinggang–dada, kaki mendarat di mid-foot, nafas ritmis 2:2 (2 langkah tarik, 2 langkah hembus).',
    'tim'    => 'Individu untuk sebagian besar nomor. Estafet 4×100 m / 4×400 m dimainkan tim 4 pelari. Lomba beregu (cross country) biasanya 5–7 pelari per tim.',
    'skoring'=> 'Pemenang ditentukan oleh waktu tercepat menyentuh garis finish. Pada lomba beregu, total/akumulasi posisi finish anggota tim menentukan skor.',
    'menang' => 'Menang: catatan waktu terbaik atau finish pertama. Kalah/diskualifikasi: salah jalur (lintasan), false start ≥1 kali (sprint), menerima estafet di luar zona, atau bantuan tidak sah.',
    'video'  => 'https://www.youtube.com/watch?v=A3D0ONk17dg&t=76s',
    'videoLabel' => 'Teknik Lari yang Benar',
  ],
  [
    'slug'   => 'badminton',
    'judul'  => 'Bulu Tangkis (Badminton)',
    'icon'   => 'bi-trophy', 'warna' => 'danger',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a3/Badminton_at_the_2012_Summer_Olympics_9335.jpg/640px-Badminton_at_the_2012_Summer_Olympics_9335.jpg',
    'definisi'=> 'Olahraga raket yang memukul shuttlecock (kok) melewati net. Dimainkan di lapangan 13,40 m × 6,10 m (ganda) atau 5,18 m (tunggal). Pertandingan resmi diatur oleh BWF.',
    'cara'   => 'Servis dari kotak servis menyilang. Pukul kok agar jatuh di area lawan dan tidak bisa dikembalikan. Teknik dasar: forehand, backhand, smash, drop shot, netting, dan lob.',
    'tim'    => 'Tunggal (1 vs 1), Ganda (2 vs 2), Ganda Campuran (1 putra + 1 putri vs sama). Beregu (Thomas/Uber Cup) terdiri dari 3 tunggal + 2 ganda per partai.',
    'skoring'=> 'Sistem rally point — setiap rally menghasilkan poin tanpa memandang siapa yang servis. Set dimenangkan pemain yang lebih dulu mencapai 21 poin (selisih ≥ 2). Maks 30 poin.',
    'menang' => 'Menang: 2 set kemenangan dari best of 3. Kalah/pelanggaran: kok menyentuh net & jatuh di sisi sendiri, keluar lapangan, double hit, badan/raket menyentuh net, atau servis tidak sah.',
    'video'  => 'https://www.youtube.com/watch?v=f9jsnYh6CCE&t=82s',
    'videoLabel' => 'Teknik Dasar Badminton',
  ],
  [
    'slug'   => 'renang',
    'judul'  => 'Renang',
    'icon'   => 'bi-water', 'warna' => 'info',
    // Revisi 18 Juni 2026: foto renang menggunakan perenang laki-laki.
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/96/Michael_Phelps_Rio_Olympics_2016.jpg/640px-Michael_Phelps_Rio_Olympics_2016.jpg',
    'definisi'=> 'Cabang olahraga akuatik yang berlomba menempuh jarak tertentu di kolam (50 m / 25 m) dengan gaya tertentu: gaya bebas (crawl), gaya dada, gaya punggung, dan gaya kupu-kupu.',
    'cara'   => 'Start dari balok start (kecuali gaya punggung). Berenang sesuai gaya yang dilombakan tanpa mengganggu lintasan lawan, melakukan tumbling turn pada ujung kolam, dan menyentuh dinding finish sesuai aturan tiap gaya.',
    'tim'    => 'Individu untuk semua gaya. Estafet 4×100 m / 4×200 m gaya bebas, dan estafet 4×100 m gaya ganti (4 perenang, masing-masing 1 gaya).',
    'skoring'=> 'Catatan waktu (menit:detik:milidetik) sejak start sampai menyentuh papan finish elektronik. Tidak ada poin — murni waktu.',
    'menang' => 'Menang: waktu tercepat menyentuh finish. Diskualifikasi: false start, gaya tidak sah (mis. gaya dada mengangkat kepala terlalu lama, gaya kupu-kupu tangan tidak simetris), tidak menyentuh dinding di turn, atau menyeberang lintasan.',
    'video'  => 'https://www.youtube.com/watch?v=LVy9mwWfXxc&t=101s',
    'videoLabel' => 'Teknik Dasar Renang',
  ],
  [
    'slug'   => 'hiking',
    'judul'  => 'Hiking / Mendaki Gunung',
    'icon'   => 'bi-tree', 'warna' => 'success',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/Hikers_on_top_of_mountain.jpg/640px-Hikers_on_top_of_mountain.jpg',
    'definisi'=> 'Kegiatan berjalan kaki menyusuri jalur alam (gunung, bukit, hutan) dengan tujuan rekreasi, olahraga ketahanan, atau pencapaian puncak. Bukan kompetisi waktu murni, tetapi mengandalkan stamina, navigasi, dan manajemen logistik.',
    'cara'   => 'Susun rencana perjalanan (waktu, ketinggian, cuaca). Gunakan sepatu hiking, beban ransel ≤ 25% berat badan. Atur ritme langkah pelan-konstan (rest step). Hidrasi tiap 20–30 menit, snack tiap 1 jam.',
    'tim'    => 'Bisa solo, namun disarankan minimal 3 orang (kaidah pendakian: leader, sweeper, navigator). Ekspedisi besar 5–10 orang dengan pembagian peran logistik, medis, dan dokumentasi.',
    'skoring'=> 'Bukan kompetisi skor. Capaian diukur dari: ketinggian (mdpl) yang tercapai, jarak tempuh, total elevasi naik (m gain), dan waktu tempuh.',
    'menang' => 'Tujuan: kembali pulang dengan selamat. "Kalah" = harus turun (turun gunung) sebelum puncak karena cuaca, cedera, atau kondisi tubuh. Etika: leave no trace — jangan tinggalkan sampah.',
    'video'  => 'https://www.youtube.com/watch?v=w_4K5Pm0Qsc&t=143s',
    'videoLabel' => 'Teknik Hiking yang Benar',
  ],
  [
    'slug'   => 'pingpong',
    'judul'  => 'Tenis Meja (PingPong)',
    'icon'   => 'bi-circle-fill', 'warna' => 'warning',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/Table_tennis_2008_Olympics_PRK-CHN.jpg/640px-Table_tennis_2008_Olympics_PRK-CHN.jpg',
    'definisi'=> 'Olahraga raket yang memukul bola seluloid berdiameter 40 mm di atas meja 2,74 × 1,525 m yang dibatasi net 15,25 cm. Diatur oleh ITTF.',
    'cara'   => 'Servis dengan bola dilempar minimal 16 cm tegak lurus, lalu dipukul agar memantul sekali di meja sendiri dan sekali di meja lawan. Pukul kembali bola sebelum memantul dua kali di meja sendiri. Teknik: forehand drive, backhand drive, push, chop, smash, dan spin.',
    'tim'    => 'Tunggal (1 vs 1), Ganda (2 vs 2 dengan giliran pukul bergantian), Ganda Campuran. Beregu: 3–5 pemain (sistem Swaythling/Corbillon Cup).',
    'skoring'=> 'Rally point — set dimenangkan pemain pertama yang mencapai 11 poin dengan selisih ≥ 2. Servis berganti tiap 2 poin (atau tiap 1 poin saat skor 10-10/deuce).',
    'menang' => 'Menang: 4 set lebih dulu dari best of 7 (atau 3 dari best of 5). Pelanggaran: servis menyentuh net & masuk berkali-kali (let), bola memantul dua kali, badan menyentuh meja, atau memukul sebelum bola memantul.',
    'video'  => 'https://www.youtube.com/watch?v=MasmG88gzFA',
    'videoLabel' => 'Teknik Dasar PingPong',
  ],
  [
    'slug'   => 'futsal',
    'judul'  => 'Futsal',
    'icon'   => 'bi-dribbble', 'warna' => 'primary',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9b/Futsal_team_Spain.jpg/640px-Futsal_team_Spain.jpg',
    'definisi'=> 'Sepak bola versi indoor di lapangan keras 25–42 m × 16–25 m dengan bola lebih kecil dan lebih sedikit pantul. Diatur oleh FIFA. Permainan cepat, menekankan kontrol bola dan umpan pendek.',
    'cara'   => 'Mainkan bola dengan kaki (kiper boleh tangan di kotak penalti). Cetak gol ke gawang lawan. Pelanggaran fisik dihitung sebagai akumulasi foul tim; foul ke-6 dalam 1 babak = tendangan bebas tanpa pagar dari titik 10 m.',
    'tim'    => '5 pemain per tim di lapangan (1 kiper + 4 pemain), maks 9 cadangan. Pergantian "rolling" tidak terbatas dan bisa kapan saja.',
    'skoring'=> '1 gol = 1 poin. Pertandingan 2 × 20 menit waktu bersih (jam berhenti saat bola mati). Time-out 1 menit per tim per babak.',
    'menang' => 'Menang: poin (gol) lebih banyak setelah 2 babak. Imbang di kompetisi: extra time 2 × 5 menit lalu adu penalti. Kalah teknis: pemain di lapangan < 3 (kiper merah / cedera tanpa cadangan).',
    'video'  => 'https://www.youtube.com/watch?v=tRrEHQSsfiA&t=8s',
    'videoLabel' => 'Teknik Dasar Futsal',
  ],
  [
    'slug'   => 'biliard',
    'judul'  => 'Biliar (Billiard / Pool)',
    'icon'   => 'bi-circle', 'warna' => 'dark',
    'foto'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/8_ball_break.jpg/640px-8_ball_break.jpg',
    'definisi'=> 'Olahraga presisi di atas meja berlapis kain (felt) dengan bola-bola berwarna dan stik (cue). Varian populer: 8-Ball, 9-Ball, Snooker, dan Carom. Diatur oleh WPA / WCBS (snooker).',
    'cara'   => 'Letakkan cue ball, arahkan stik (bridge tangan stabil), pukul cue ball agar menyentuh bola target dan masukkan ke lubang (pocket). Pukulan pertama (break) memecah formasi bola. Giliran lanjut bila berhasil memasukkan bola legal; salah → berpindah giliran.',
    'tim'    => 'Umumnya 1 vs 1 (tunggal). Ada juga format ganda (Scotch doubles — pukulan bergantian) dan beregu (mis. Mosconi Cup 5 pemain per tim).',
    'skoring'=> '8-Ball/9-Ball: hitung bola yang dimasukkan (frame win). Snooker: bola berwarna punya nilai 1–7, pemain dengan total poin tertinggi memenangkan frame. Match = best of X frame.',
    'menang' => 'Menang 8-Ball: masukkan semua bola grup (solid/stripe) lalu masukkan bola 8 di pocket yang dideklarasikan. Foul: cue ball masuk lubang (scratch), menyentuh bola lawan duluan, atau tidak ada bola yang menyentuh ban. Kalah otomatis: memasukkan bola 8 sebelum waktunya atau di pocket salah.',
    'video'  => 'https://www.youtube.com/watch?v=taf9JBG9iLA',
    'videoLabel' => 'Teknik Dasar Biliar',
  ],
];

$VIDEO_PAKET = [
  ['judul'=>'Paket Pemanasan Olahraga','warna'=>'warning','icon'=>'bi-fire','desc'=>'Wajib dilakukan 5–10 menit sebelum olahraga untuk meningkatkan suhu otot, jangkauan gerak, dan mencegah cedera.','url'=>'https://www.youtube.com/watch?v=Ks5dz69gsDk&t=19s'],
  ['judul'=>'Paket Pendinginan Olahraga','warna'=>'info','icon'=>'bi-snow','desc'=>'Lakukan 5–10 menit setelah olahraga untuk menurunkan detak jantung perlahan, mempercepat recovery, dan mengurangi nyeri otot (DOMS).','url'=>'https://www.youtube.com/watch?v=uXznjq2BLMI&t=54s'],
];

include __DIR__.'/includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami hero-artikel mb-3">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-journal-richtext"></i> Artikel &amp; Teknik Olahraga</h1>
    <p class="small mb-0 opacity-85">Panduan ringkas tiap olahraga: <strong>Definisi · Cara Main · Pembagian Tim · Skoring · Sistem Menang-Kalah</strong> · plus video teknik &amp; paket pemanasan/pendinginan.</p>
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

<h2 class="h5 mb-3"><i class="bi bi-trophy text-warning"></i> Daftar Olahraga (lengkap dengan video teknik)</h2>

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
              <?php if (!empty($a['foto'])): ?>
                <img src="<?= htmlspecialchars($a['foto']) ?>" class="img-fluid rounded mb-2"
                     style="width:100%;max-height:240px;object-fit:cover" loading="lazy"
                     alt="Foto <?= htmlspecialchars($a['judul']) ?>">
              <?php endif; ?>
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
              </div>
              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Pembagian Tim:</strong>
                <div class="small"><?= htmlspecialchars($a['tim']) ?></div>
              </div>
              <div class="mb-2"><strong class="text-<?= $a['warna'] ?>">Sistem Skoring:</strong>
                <div class="small"><?= htmlspecialchars($a['skoring']) ?></div>
              </div>
              <div class="mb-0"><strong class="text-<?= $a['warna'] ?>">Sistem Pemenang &amp; Kalah:</strong>
                <div class="small"><?= htmlspecialchars($a['menang']) ?></div>
              </div>
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

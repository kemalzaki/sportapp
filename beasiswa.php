<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Info Beasiswa';
$pageSkeleton = 'list'; // Skeleton sesuai data: daftar beasiswa

/**
 * Sumber data:
 * - Daftar beasiswa kurasi (tautan resmi penyelenggara), karena tidak ada API
 *   publik tunggal yang terstandar untuk seluruh beasiswa S1/S2/S3 Indonesia.
 * - Berita beasiswa terkini diambil real-time via API publik (CNN “edukasi”)
 *   lalu di-filter kata kunci "beasiswa".
 */

$level = $_GET['lvl'] ?? 'all';
if (!in_array($level, ['all','s1','s2','s3'], true)) $level = 'all';

$BEASISWA = [
  // ===== S1 =====
  ['lvl'=>'s1','nama'=>'KIP Kuliah (Kemdikbud)','penyelenggara'=>'Kementerian Pendidikan','negara'=>'Indonesia','url'=>'https://kip-kuliah.kemdikbud.go.id/','desc'=>'Bantuan biaya pendidikan untuk lulusan SMA/SMK tidak mampu yang berprestasi.'],
  ['lvl'=>'s1','nama'=>'Beasiswa Indonesia Maju (BIM) S1','penyelenggara'=>'Puspresnas','negara'=>'Indonesia / Luar Negeri','url'=>'https://beasiswa.kemdikbud.go.id/bim','desc'=>'Beasiswa penuh untuk siswa berprestasi melanjutkan S1 dalam/luar negeri.'],
  ['lvl'=>'s1','nama'=>'Tanoto Foundation TELADAN','penyelenggara'=>'Tanoto Foundation','negara'=>'Indonesia','url'=>'https://www.tanotofoundation.org/id/scholarships-tanoto/','desc'=>'Beasiswa S1 + pelatihan kepemimpinan untuk mahasiswa baru di PTN mitra.'],
  ['lvl'=>'s1','nama'=>'Djarum Beasiswa Plus','penyelenggara'=>'Djarum Foundation','negara'=>'Indonesia','url'=>'https://djarumbeasiswaplus.org/','desc'=>'Untuk mahasiswa semester 4 berprestasi akademik & non-akademik.'],
  ['lvl'=>'s1','nama'=>'Beasiswa BCA Finance','penyelenggara'=>'BCA Finance','negara'=>'Indonesia','url'=>'https://www.bcafinance.co.id/beasiswa','desc'=>'Beasiswa S1 untuk mahasiswa aktif minimal semester 2 dengan IPK ≥ 3,00.'],

  // ===== S2 =====
  ['lvl'=>'s2','nama'=>'LPDP Reguler','penyelenggara'=>'Kementerian Keuangan','negara'=>'Indonesia / Luar Negeri','url'=>'https://lpdp.kemenkeu.go.id/','desc'=>'Beasiswa penuh S2 di PT terbaik dalam dan luar negeri.'],
  ['lvl'=>'s2','nama'=>'LPDP Targeted (PNS/TNI/Polri/Guru)','penyelenggara'=>'Kementerian Keuangan','negara'=>'Indonesia / Luar Negeri','url'=>'https://lpdp.kemenkeu.go.id/beasiswa/all','desc'=>'Skema sasaran untuk profesi tertentu.'],
  ['lvl'=>'s2','nama'=>'Australia Awards Scholarships','penyelenggara'=>'Pemerintah Australia','negara'=>'Australia','url'=>'https://www.australiaawardsindonesia.org/','desc'=>'Beasiswa penuh S2/S3 di universitas Australia bagi WNI.'],
  ['lvl'=>'s2','nama'=>'Chevening Scholarship','penyelenggara'=>'UK Government','negara'=>'Inggris','url'=>'https://www.chevening.org/','desc'=>'Beasiswa S2 1 tahun di universitas Inggris untuk calon pemimpin.'],
  ['lvl'=>'s2','nama'=>'Fulbright Master (AMINEF)','penyelenggara'=>'AMINEF / US Gov','negara'=>'Amerika Serikat','url'=>'https://www.aminef.or.id/','desc'=>'Beasiswa penuh S2 di Amerika Serikat.'],
  ['lvl'=>'s2','nama'=>'StuNed (Studeren in Nederland)','penyelenggara'=>'Nuffic Neso','negara'=>'Belanda','url'=>'https://www.nesoindonesia.or.id/beasiswa/stuned','desc'=>'Beasiswa S2 short course di Belanda untuk profesional Indonesia.'],
  ['lvl'=>'s2','nama'=>'MEXT (Monbukagakusho)','penyelenggara'=>'Pemerintah Jepang','negara'=>'Jepang','url'=>'https://www.id.emb-japan.go.jp/sch.html','desc'=>'Beasiswa penuh S2/S3 di Jepang, jalur Kedubes & U-to-U.'],

  // ===== S3 =====
  ['lvl'=>'s3','nama'=>'LPDP Doktoral','penyelenggara'=>'Kementerian Keuangan','negara'=>'Indonesia / Luar Negeri','url'=>'https://lpdp.kemenkeu.go.id/beasiswa/all','desc'=>'Beasiswa penuh S3 dalam dan luar negeri.'],
  ['lvl'=>'s3','nama'=>'BPI Dosen (PMDSU)','penyelenggara'=>'Kemdikbud','negara'=>'Indonesia','url'=>'https://beasiswa.kemdikbud.go.id/','desc'=>'Program Magister menuju Doktor untuk Sarjana Unggul.'],
  ['lvl'=>'s3','nama'=>'DAAD Doctoral','penyelenggara'=>'DAAD Jerman','negara'=>'Jerman','url'=>'https://www.daad.id/id/','desc'=>'Beasiswa S3/riset di universitas Jerman.'],
  ['lvl'=>'s3','nama'=>'Erasmus Mundus Joint Doctorate','penyelenggara'=>'Uni Eropa','negara'=>'Eropa','url'=>'https://erasmus-plus.ec.europa.eu/','desc'=>'Beasiswa S3 lintas universitas di Eropa.'],
  ['lvl'=>'s3','nama'=>'New Zealand Manaaki Scholarship','penyelenggara'=>'NZ Government','negara'=>'Selandia Baru','url'=>'https://www.nzscholarships.govt.nz/','desc'=>'Beasiswa penuh S2/S3 di Selandia Baru.'],
];

$filtered = $level==='all' ? $BEASISWA : array_values(array_filter($BEASISWA, fn($x)=>$x['lvl']===$level));

// Berita terkini beasiswa (filter dari RSS Antara — kategori edukasi/tekno).
// Catatan: api-berita-indonesia.vercel.app sudah tidak aktif, diganti RSS Antara.
$posts = ip_fetch_rss('https://www.antaranews.com/rss/tekno', 900);
$beasiswaNews = [];
foreach ($posts as $p) {
    $t = mb_strtolower(($p['title'] ?? '').' '.($p['description'] ?? ''));
    if (str_contains($t, 'beasiswa') || str_contains($t, 'scholarship')) $beasiswaNews[] = $p;
}

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Beasiswa'); ?>

<?php ip_card_open('Info Beasiswa S1 / S2 / S3', 'bi-mortarboard'); ?>

<p class="text-muted small mb-3">
  Daftar di bawah adalah <strong>kurasi</strong> beasiswa aktif beserta tautan resmi penyelenggara
  (LPDP, Kemdikbud, Tanoto, Djarum, Chevening, Fulbright, MEXT, DAAD, dst). Selalu cek situs resmi untuk jadwal terbaru.
</p>

<ul class="nav nav-pills mb-3 gap-2 flex-wrap">
  <?php foreach([['all','Semua'],['s1','S1'],['s2','S2'],['s3','S3']] as [$k,$lab]): ?>
    <li class="nav-item">
      <a class="nav-link <?= $level===$k?'active':'' ?>" href="?lvl=<?= $k ?>"><?= $lab ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="row g-3 mb-4">
  <?php foreach($filtered as $b): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-primary-subtle text-primary text-uppercase"><?= htmlspecialchars($b['lvl']) ?></span>
            <span class="badge bg-light text-dark"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($b['negara']) ?></span>
          </div>
          <h2 class="h6 mb-1"><?= htmlspecialchars($b['nama']) ?></h2>
          <div class="text-muted small mb-2"><i class="bi bi-building"></i> <?= htmlspecialchars($b['penyelenggara']) ?></div>
          <p class="small mb-2"><?= htmlspecialchars($b['desc']) ?></p>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($b['url']) ?>" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right"></i> Buka situs resmi
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($beasiswaNews): ?>
<h2 class="h5 mt-4 mb-3"><i class="bi bi-megaphone text-warning"></i> Berita Beasiswa Terkini</h2>
<div class="list-group mb-4">
  <?php foreach(array_slice($beasiswaNews,0,6) as $n): ?>
    <a href="<?= htmlspecialchars($n['link'] ?? '#') ?>" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
      <div class="d-flex justify-content-between">
        <strong><?= htmlspecialchars($n['title'] ?? '-') ?></strong>
        <small class="text-muted ms-2"><?= $n['pubDate'] ? date('d M', strtotime($n['pubDate'])) : '' ?></small>
      </div>
      <div class="small text-muted"><?= htmlspecialchars(mb_strimwidth($n['description'] ?? '',0,140,'…')) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php?>
<?php htmx_layout_end(); ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Koleksi Buku Terbaru (Indonesia)';

/**
 * REVISI: hanya menampilkan buku berbahasa Indonesia.
 * Sumber: OpenLibrary search.json dengan filter language=ind.
 *  - Endpoint: https://openlibrary.org/search.json?subject=<sub>&language=ind&limit=24&sort=new
 *  - Cover:    https://covers.openlibrary.org/b/id/<cover_i>-M.jpg
 */
$KATEGORI = [
  'baru'      => ['label'=>'Terbaru',          'icon'=>'bi-stars',          'subject'=>'indonesian'],
  'islami'    => ['label'=>'Islami',           'icon'=>'bi-book-half',      'subject'=>'islam'],
  'bisnis'    => ['label'=>'Bisnis & Ekonomi', 'icon'=>'bi-graph-up-arrow', 'subject'=>'business'],
  'teknologi' => ['label'=>'Teknologi',        'icon'=>'bi-cpu',            'subject'=>'technology'],
  'kesehatan' => ['label'=>'Kesehatan',        'icon'=>'bi-heart-pulse',    'subject'=>'health'],
  'anak'      => ['label'=>'Anak',             'icon'=>'bi-emoji-smile',    'subject'=>'juvenile_fiction'],
  'sejarah'   => ['label'=>'Sejarah',          'icon'=>'bi-hourglass-split','subject'=>'history'],
  'sastra'    => ['label'=>'Sastra & Novel',   'icon'=>'bi-feather',        'subject'=>'indonesian fiction'],
];
$cat = $_GET['cat'] ?? 'baru';
if (!isset($KATEGORI[$cat])) $cat = 'baru';
$subject = $KATEGORI[$cat]['subject'];

// Hanya buku berbahasa Indonesia (language=ind = ISO 639-2 untuk Bahasa Indonesia).
$base = 'https://openlibrary.org/search.json?language=ind&limit=24&sort=new&subject='.rawurlencode($subject);
$data = ip_fetch_json($base, 1800);
$docs = $data['docs'] ?? [];

// Fallback jika subject terlalu sempit: cari tanpa subject, hanya language=ind
if (!$docs) {
  $base2 = 'https://openlibrary.org/search.json?language=ind&limit=24&sort=new&q='.rawurlencode($KATEGORI[$cat]['label']);
  $data = ip_fetch_json($base2, 1800);
  $docs = $data['docs'] ?? [];
}

$items = [];
foreach ($docs as $d) {
  // Pastikan benar-benar berbahasa Indonesia
  $langs = $d['language'] ?? [];
  if (is_array($langs) && $langs && !in_array('ind', $langs, true)) continue;
  $items[] = [
    'title'              => $d['title'] ?? '-',
    'authors'            => array_map(fn($n)=>['name'=>$n], $d['author_name'] ?? []),
    'cover_id'           => $d['cover_i'] ?? null,
    'key'                => $d['key'] ?? '',
    'first_publish_year' => $d['first_publish_year'] ?? '',
    'subject'            => $d['subject'] ?? [],
  ];
}

// Daftar toko buku di Bandung (kurasi internal)
$TOKO_BANDUNG = [
  ['nama'=>'Gramedia Merdeka',        'alamat'=>'Jl. Merdeka No.43, Babakan Ciamis, Bandung','jam'=>'09.00–21.00','maps'=>'https://maps.google.com/?q=Gramedia+Merdeka+Bandung'],
  ['nama'=>'Gramedia Trans Studio Mall','alamat'=>'TSM Lt.1, Jl. Gatot Subroto, Bandung',     'jam'=>'10.00–22.00','maps'=>'https://maps.google.com/?q=Gramedia+TSM+Bandung'],
  ['nama'=>'Togamas Bandung',         'alamat'=>'Jl. Supratman No.45, Bandung',              'jam'=>'09.00–21.00','maps'=>'https://maps.google.com/?q=Togamas+Bandung'],
  ['nama'=>'Periplus Paris Van Java', 'alamat'=>'PVJ Mall, Jl. Sukajadi No.137-139, Bandung','jam'=>'10.00–22.00','maps'=>'https://maps.google.com/?q=Periplus+PVJ+Bandung'],
  ['nama'=>'Rumah Buku',              'alamat'=>'Jl. Hegarmanah No.52, Bandung',             'jam'=>'10.00–20.00','maps'=>'https://maps.google.com/?q=Rumah+Buku+Hegarmanah'],
  ['nama'=>'Toko Buku Palasari',      'alamat'=>'Pasar Buku Palasari, Jl. Palasari, Bandung','jam'=>'08.00–17.00','maps'=>'https://maps.google.com/?q=Pasar+Buku+Palasari+Bandung'],
  ['nama'=>'Bookoopedia Dago',        'alamat'=>'Jl. Ir. H. Juanda (Dago), Bandung',         'jam'=>'09.00–21.00','maps'=>'https://maps.google.com/?q=Toko+Buku+Dago+Bandung'],
];

include __DIR__.'/includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami mb-3" style="background-image:linear-gradient(135deg, rgba(13,71,99,.85), rgba(20,108,99,.85)), url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=1200&q=70');">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-journals"></i> Koleksi Buku Berbahasa Indonesia</h1>
    <p class="small mb-0 opacity-85">Hanya buku berbahasa Indonesia dari berbagai kategori.</p>
  </div>
</div>

<?php ip_card_open('Koleksi Buku Indonesia di Pasaran', 'bi-journals'); ?>

<p class="text-muted small mb-3">
  Sumber: <strong>Open Library</strong> (gratis, tanpa API key, oleh Internet Archive) — difilter <code>language=ind</code>.
  Daftar toko buku di Bandung adalah kurasi internal aplikasi. Hasil di-cache 30 menit di server.
</p>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 gap-2" style="white-space:nowrap;">
  <?php foreach($KATEGORI as $k=>$v): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k===$cat?'active':'' ?>" href="?cat=<?= $k ?>">
        <i class="bi <?= $v['icon'] ?>"></i> <?= htmlspecialchars($v['label']) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (!$items): ?>
  <div class="alert alert-warning">
    <i class="bi bi-wifi-off"></i> Gagal mengambil koleksi buku saat ini. Periksa koneksi internet server, lalu muat ulang.
  </div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <?php foreach($items as $it):
      $title   = $it['title'] ?? '-';
      $authors = '-';
      if (!empty($it['authors']) && is_array($it['authors'])) {
        $names = array_map(fn($a)=>$a['name'] ?? '', $it['authors']);
        $authors = implode(', ', array_filter($names));
      }
      $coverId = $it['cover_id'] ?? ($it['cover_i'] ?? null);
      $img = $coverId ? ('https://covers.openlibrary.org/b/id/'.intval($coverId).'-M.jpg') : '';
      $key  = $it['key'] ?? '';
      $link = $key ? ('https://openlibrary.org'.$key) : 'https://openlibrary.org/';
      $year = $it['first_publish_year'] ?? '';
      $subs = '';
      if (!empty($it['subject']) && is_array($it['subject'])) {
        $subs = implode(', ', array_slice($it['subject'], 0, 3));
      }
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="row g-0 h-100">
            <div class="col-4">
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="img-fluid rounded-start h-100" style="object-fit:cover;width:100%;" alt="" onerror="this.style.display='none'">
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center bg-light h-100 text-muted"><i class="bi bi-book fs-1"></i></div>
              <?php endif; ?>
            </div>
            <div class="col-8">
              <div class="card-body d-flex flex-column h-100">
                <h2 class="h6 mb-1"><a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= htmlspecialchars($title) ?></a></h2>
                <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= htmlspecialchars($authors) ?></div>
                <?php if ($year): ?><div class="small text-muted mb-1"><i class="bi bi-calendar"></i> Terbit pertama <?= htmlspecialchars($year) ?></div><?php endif; ?>
                <?php if ($subs): ?><div class="small mb-2"><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($subs) ?></span></div><?php endif; ?>
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-auto">Detail <i class="bi bi-box-arrow-up-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php ip_card_close(); ?>

<?php ip_card_open('Toko Buku di Bandung', 'bi-shop'); ?>
<div class="row g-3">
  <?php foreach($TOKO_BANDUNG as $t): ?>
    <div class="col-md-6 col-lg-4">
      <div class="border rounded p-3 h-100">
        <div class="fw-semibold"><i class="bi bi-shop text-primary"></i> <?= htmlspecialchars($t['nama']) ?></div>
        <div class="small text-muted mb-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($t['alamat']) ?></div>
        <div class="small text-muted mb-2"><i class="bi bi-clock"></i> <?= htmlspecialchars($t['jam']) ?></div>
        <a href="<?= htmlspecialchars($t['maps']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-map"></i> Lihat di Maps
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php ip_card_close(); ?>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

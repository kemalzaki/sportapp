<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Koleksi Buku Terbaru';

// Kategori → query Google Books API (gratis, tanpa API key untuk volume search)
$KATEGORI = [
  'baru'        => ['label'=>'Terbaru',         'icon'=>'bi-stars',         'q'=>'subject:fiction',           'order'=>'newest'],
  'islami'      => ['label'=>'Islami',          'icon'=>'bi-book-half',     'q'=>'subject:religion+islam',    'order'=>'newest'],
  'bisnis'      => ['label'=>'Bisnis & Ekonomi','icon'=>'bi-graph-up-arrow','q'=>'subject:business',          'order'=>'newest'],
  'teknologi'   => ['label'=>'Teknologi',       'icon'=>'bi-cpu',           'q'=>'subject:computers',         'order'=>'newest'],
  'kesehatan'   => ['label'=>'Kesehatan',       'icon'=>'bi-heart-pulse',   'q'=>'subject:health',            'order'=>'newest'],
  'anak'        => ['label'=>'Anak',            'icon'=>'bi-emoji-smile',   'q'=>'subject:juvenile+fiction',  'order'=>'newest'],
  'sejarah'     => ['label'=>'Sejarah',         'icon'=>'bi-hourglass-split','q'=>'subject:history',          'order'=>'newest'],
  'sastra'      => ['label'=>'Sastra & Novel',  'icon'=>'bi-feather',       'q'=>'subject:fiction+novel',     'order'=>'newest'],
];
$cat = $_GET['cat'] ?? 'baru';
if (!isset($KATEGORI[$cat])) $cat = 'baru';

$q = $KATEGORI[$cat]['q'];
$url = 'https://www.googleapis.com/books/v1/volumes?q='.rawurlencode($q).'&orderBy='.$KATEGORI[$cat]['order'].'&printType=books&maxResults=24&langRestrict=id';
$data = ip_fetch_json($url, 1800);
$items = $data['items'] ?? [];
// Fallback: kalau hasil bahasa Indonesia kosong, coba tanpa langRestrict
if (!$items) {
    $url2 = 'https://www.googleapis.com/books/v1/volumes?q='.rawurlencode($q).'&orderBy='.$KATEGORI[$cat]['order'].'&printType=books&maxResults=24';
    $data = ip_fetch_json($url2, 1800);
    $items = $data['items'] ?? [];
}

// Daftar toko buku di Bandung (kurasi internal — alamat & jam buka pendek)
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

<?php ip_card_open('Koleksi Buku Terbaru di Pasaran', 'bi-journals'); ?>

<p class="text-muted small mb-3">
  Sumber: <strong>Google Books API</strong> (gratis). Daftar toko buku di Bandung adalah kurasi internal aplikasi.
  Hasil di-cache 30 menit di server.
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
    <i class="bi bi-wifi-off"></i> Gagal mengambil koleksi buku saat ini. Coba muat ulang dalam beberapa saat.
  </div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <?php foreach($items as $it):
      $v = $it['volumeInfo'] ?? [];
      $title = $v['title'] ?? '-';
      $authors = isset($v['authors']) ? implode(', ', $v['authors']) : '-';
      $img = $v['imageLinks']['thumbnail'] ?? ($v['imageLinks']['smallThumbnail'] ?? '');
      if ($img) $img = preg_replace('#^http://#', 'https://', $img);
      $desc = $v['description'] ?? '';
      $link = $v['infoLink'] ?? ($v['previewLink'] ?? '#');
      $publ = $v['publisher'] ?? '';
      $year = isset($v['publishedDate']) ? substr($v['publishedDate'],0,4) : '';
      $cat_b = isset($v['categories']) ? implode(', ', $v['categories']) : '';
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="row g-0 h-100">
            <div class="col-4">
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="img-fluid rounded-start h-100" style="object-fit:cover;width:100%;" alt="">
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center bg-light h-100 text-muted"><i class="bi bi-book fs-1"></i></div>
              <?php endif; ?>
            </div>
            <div class="col-8">
              <div class="card-body d-flex flex-column h-100">
                <h2 class="h6 mb-1"><a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= htmlspecialchars($title) ?></a></h2>
                <div class="small text-muted mb-1"><i class="bi bi-person"></i> <?= htmlspecialchars($authors) ?></div>
                <?php if ($publ || $year): ?><div class="small text-muted mb-1"><i class="bi bi-building"></i> <?= htmlspecialchars(trim($publ.' '.$year)) ?></div><?php endif; ?>
                <?php if ($cat_b): ?><div class="small text-muted mb-2"><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($cat_b) ?></span></div><?php endif; ?>
                <p class="small flex-grow-1 text-muted mb-2"><?= htmlspecialchars(mb_strimwidth(strip_tags($desc),0,140,'…')) ?></p>
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-auto">Detail <i class="bi bi-box-arrow-up-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Toko Buku di Bandung -->
<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success text-white">
    <i class="bi bi-shop"></i> <strong>Tersedia di Bandung — Toko Buku Rekomendasi</strong>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3">Berikut daftar toko buku yang umum menyediakan buku-buku terbaru di kota Bandung. Klik <em>Lihat di Maps</em> untuk navigasi.</p>
    <div class="row g-3">
      <?php foreach($TOKO_BANDUNG as $t): ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded p-3 h-100 d-flex flex-column">
            <div class="fw-semibold mb-1"><i class="bi bi-geo-alt text-danger"></i> <?= htmlspecialchars($t['nama']) ?></div>
            <div class="small text-muted mb-1"><?= htmlspecialchars($t['alamat']) ?></div>
            <div class="small text-muted mb-2"><i class="bi bi-clock"></i> <?= htmlspecialchars($t['jam']) ?></div>
            <a href="<?= htmlspecialchars($t['maps']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success mt-auto">
              <i class="bi bi-map"></i> Lihat di Maps
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Berita Terkini 2026';
$pageSkeleton = 'list'; // Skeleton sesuai data: daftar berita

/**
 * REVISI 31 Mei 2026:
 *  - Pagination per 5 berita
 *  - Fitur pencarian (judul / ringkasan / sumber)
 *  - Tetap hanya menampilkan berita TAHUN 2026
 */
$KATEGORI = [
  'politik' => [
    'label'=>'Politik','icon'=>'bi-bank',
    'rss'=>[
      'CNN Indonesia' => 'https://www.cnnindonesia.com/nasional/rss',
      'Detik'         => 'https://rss.detik.com/index.php/detikcom_nasional',
      'Kompas'        => 'https://www.kompas.com/rss/news',
      'Antara'        => 'https://www.antaranews.com/rss/politik',
    ],
  ],
  'ekonomi' => [
    'label'=>'Ekonomi','icon'=>'bi-cash-coin',
    'rss'=>[
      'CNN Indonesia' => 'https://www.cnnindonesia.com/ekonomi/rss',
      'Detik'         => 'https://rss.detik.com/index.php/finance',
      'Kompas'        => 'https://www.kompas.com/rss/money',
      'Antara'        => 'https://www.antaranews.com/rss/ekonomi',
    ],
  ],
  'olahraga' => [
    'label'=>'Olahraga','icon'=>'bi-trophy',
    'rss'=>[
      'CNN Indonesia' => 'https://www.cnnindonesia.com/olahraga/rss',
      'Detik'         => 'https://rss.detik.com/index.php/sport',
      'Kompas'        => 'https://www.kompas.com/rss/sports',
      'Antara'        => 'https://www.antaranews.com/rss/olahraga',
    ],
  ],
  'teknologi' => [
    'label'=>'Teknologi','icon'=>'bi-cpu',
    'rss'=>[
      'CNN Indonesia' => 'https://www.cnnindonesia.com/teknologi/rss',
      'Detik'         => 'https://rss.detik.com/index.php/inet',
      'Kompas'        => 'https://www.kompas.com/rss/tekno',
      'Antara'        => 'https://www.antaranews.com/rss/tekno',
    ],
  ],
];
$cat = $_GET['cat'] ?? 'politik';
if (!isset($KATEGORI[$cat])) $cat = 'politik';

$q   = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;

// Gabungkan semua sumber untuk kategori terpilih.
$allPosts = [];
foreach ($KATEGORI[$cat]['rss'] as $sumber => $url) {
    $items = ip_fetch_rss($url, 600);
    foreach ($items as $it) {
        $it['sumber'] = $sumber;
        $allPosts[] = $it;
    }
}

// Filter HANYA tahun 2026, urutkan terbaru → lama.
$TAHUN_FILTER = 2026;
$posts = [];
foreach ($allPosts as $p) {
    $ts = !empty($p['pubDate']) ? strtotime($p['pubDate']) : 0;
    if (!$ts) continue;
    if ((int)date('Y', $ts) !== $TAHUN_FILTER) continue;
    $p['_ts'] = $ts;
    $posts[] = $p;
}
// Dedup berdasarkan judul (lintas sumber).
$seen = []; $uniq = [];
foreach ($posts as $p) {
    $k = mb_strtolower(preg_replace('/\s+/u', ' ', trim($p['title'])));
    if (isset($seen[$k])) continue;
    $seen[$k] = 1; $uniq[] = $p;
}
$posts = $uniq;

// Filter pencarian (judul / deskripsi / sumber).
if ($q !== '') {
    $needle = mb_strtolower($q);
    $posts = array_values(array_filter($posts, function($p) use ($needle) {
        $hay = mb_strtolower(($p['title'] ?? '').' '.strip_tags($p['description'] ?? '').' '.($p['sumber'] ?? ''));
        return mb_strpos($hay, $needle) !== false;
    }));
}

usort($posts, function($a,$b){ return $b['_ts'] <=> $a['_ts']; });

// Pagination.
$total = count($posts);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pagePosts = array_slice($posts, $offset, $perPage);

// Helper URL pagination.
$buildUrl = function(array $over = []) use ($cat, $q, $page) {
    $params = array_merge(['cat'=>$cat, 'q'=>$q, 'page'=>$page], $over);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?'.http_build_query($params);
};

include __DIR__.'/includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami hero-berita mb-3">
  <div class="hero-overlay">
    <span class="badge bg-light text-dark mb-2"><i class="bi bi-calendar-event"></i> Edisi <?= $TAHUN_FILTER ?></span>
    <h1 class="h4 mb-1"><i class="bi bi-newspaper"></i> Berita Terkini <?= $TAHUN_FILTER ?></h1>
    <p class="small mb-0 opacity-85">Gabungan sumber resmi: CNN Indonesia · Detik · Kompas · Antara — hanya berita tahun <?= $TAHUN_FILTER ?>, diurutkan dari yang paling baru.</p>
  </div>
</div>

<?php ip_card_open('Berita Terkini '.$TAHUN_FILTER, 'bi-newspaper'); ?>

<p class="text-muted small mb-3">
  Sumber: <strong>CNN Indonesia</strong>, <strong>Detik</strong>, <strong>Kompas</strong>, <strong>Antara</strong> (RSS resmi) — gratis tanpa API key.
</p>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 gap-2" style="white-space:nowrap;">
  <?php foreach($KATEGORI as $k=>$v): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k===$cat?'active':'' ?>" href="?cat=<?= $k ?><?= $q!==''?'&q='.urlencode($q):'' ?>">
        <i class="bi <?= $v['icon'] ?>"></i> <?= $v['label'] ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<!-- Form pencarian -->
<form method="get" class="mb-3" role="search">
  <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
  <div class="input-group">
    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari judul / sumber berita...">
    <button class="btn btn-primary" type="submit">Cari</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline-secondary" href="?cat=<?= htmlspecialchars($cat) ?>">Reset</a>
    <?php endif; ?>
  </div>
  <?php if ($q !== ''): ?>
    <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Menampilkan hasil pencarian untuk <strong>"<?= htmlspecialchars($q) ?>"</strong> — <?= $total ?> berita ditemukan.</div>
  <?php endif; ?>
</form>

<?php if (!$posts): ?>
  <div class="alert alert-warning">
    <i class="bi bi-wifi-off"></i> <?= $q !== '' ? 'Tidak ada berita yang cocok dengan pencarian kamu.' : 'Belum ada berita tahun '.$TAHUN_FILTER.' untuk kategori ini, atau server tidak dapat menghubungi sumber RSS.' ?>
  </div>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
    <span>Menampilkan <strong><?= $offset+1 ?>–<?= min($offset+$perPage, $total) ?></strong> dari <strong><?= $total ?></strong> berita</span>
    <span>Halaman <?= $page ?> / <?= $totalPages ?></span>
  </div>
  <div class="row g-3">
    <?php foreach($pagePosts as $p):
      $img = $p['thumbnail'] ?? '';
      $title = $p['title'] ?? '-';
      $desc  = $p['description'] ?? '';
      $link  = $p['link'] ?? '#';
      $sumber= $p['sumber'] ?? '';
      $dateTxt = date('d M Y · H:i', $p['_ts']);
      $payload = htmlspecialchars(json_encode([
        'title'=>$title,'desc'=>$desc,'img'=>$img,'link'=>$link,'date'=>$dateTxt,
        'cat'=>$KATEGORI[$cat]['label'],'sumber'=>$sumber,
      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm berita-card">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="card-img-top" style="height:170px;object-fit:cover;cursor:pointer;" alt="" data-berita="<?= $payload ?>">
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <div class="d-flex gap-1 mb-2 flex-wrap">
              <span class="badge bg-primary-subtle text-primary"><i class="bi bi-broadcast"></i> <?= htmlspecialchars($sumber) ?></span>
              <span class="badge bg-success-subtle text-success">2026</span>
            </div>
            <h2 class="h6">
              <a href="#" class="text-decoration-none berita-trigger" data-berita="<?= $payload ?>">
                <?= htmlspecialchars($title) ?>
              </a>
            </h2>
            <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth(strip_tags($desc),0,160,'…')) ?></p>
            <div class="d-flex justify-content-between align-items-center small">
              <span class="text-muted"><i class="bi bi-clock"></i> <?= htmlspecialchars($dateTxt) ?></span>
              <button type="button" class="btn btn-sm btn-outline-primary berita-trigger" data-berita="<?= $payload ?>">
                <i class="bi bi-eye"></i> Baca
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination nav -->
  <nav class="mt-4" aria-label="Navigasi halaman berita">
    <ul class="pagination justify-content-center flex-wrap">
      <li class="page-item <?= $page<=1?'disabled':'' ?>">
        <a class="page-link" href="<?= $page<=1?'#':$buildUrl(['page'=>$page-1]) ?>">&laquo; Sebelumnya</a>
      </li>
      <?php
        $start = max(1, $page-2);
        $end   = min($totalPages, $page+2);
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="'.htmlspecialchars($buildUrl(['page'=>1])).'">1</a></li>';
            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($i=$start; $i<=$end; $i++) {
            $active = $i===$page ? ' active' : '';
            echo '<li class="page-item'.$active.'"><a class="page-link" href="'.htmlspecialchars($buildUrl(['page'=>$i])).'">'.$i.'</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            echo '<li class="page-item"><a class="page-link" href="'.htmlspecialchars($buildUrl(['page'=>$totalPages])).'">'.$totalPages.'</a></li>';
        }
      ?>
      <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
        <a class="page-link" href="<?= $page>=$totalPages?'#':$buildUrl(['page'=>$page+1]) ?>">Berikutnya &raquo;</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<!-- Modal popup berita -->
<div class="modal fade" id="modalBerita" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mbTitle">Berita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center small text-muted mb-2 flex-wrap gap-2">
          <span><i class="bi bi-tag"></i> <span id="mbCat">-</span> · <i class="bi bi-broadcast"></i> <span id="mbSumber">-</span></span>
          <span><i class="bi bi-clock"></i> <span id="mbDate">-</span></span>
        </div>
        <img id="mbImg" src="" alt="" class="img-fluid rounded mb-3 d-none" style="max-height:340px;width:100%;object-fit:cover;">
        <div id="mbDesc" class="berita-isi"></div>
        <div class="alert alert-light border mt-3 small mb-0">
          <i class="bi bi-info-circle"></i> Ringkasan diambil dari RSS resmi. Untuk artikel lengkap, buka sumber asli.
        </div>
      </div>
      <div class="modal-footer">
        <a id="mbLink" href="#" target="_blank" rel="noopener" class="btn btn-primary">
          <i class="bi bi-box-arrow-up-right"></i> Buka Sumber Asli
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function(){
  var modalEl = document.getElementById('modalBerita');
  if (!modalEl || typeof bootstrap === 'undefined') {
    document.querySelectorAll('.berita-trigger, img[data-berita]').forEach(function(el){
      el.addEventListener('click', function(e){
        e.preventDefault();
        try { var d = JSON.parse(this.getAttribute('data-berita')); if (d && d.link) window.open(d.link, '_blank'); } catch(_){}
      });
    });
    return;
  }
  var modal = new bootstrap.Modal(modalEl);
  document.querySelectorAll('.berita-trigger, img[data-berita]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      var data; try { data = JSON.parse(this.getAttribute('data-berita')); } catch(_) { return; }
      document.getElementById('mbTitle').textContent  = data.title  || 'Berita';
      document.getElementById('mbCat').textContent    = data.cat    || '-';
      document.getElementById('mbSumber').textContent = data.sumber || '-';
      document.getElementById('mbDate').textContent   = data.date   || '-';
      document.getElementById('mbDesc').innerHTML     = data.desc   || '<em class="text-muted">Tidak ada ringkasan.</em>';
      document.getElementById('mbLink').href          = data.link   || '#';
      var imgEl = document.getElementById('mbImg');
      if (data.img) { imgEl.src = data.img; imgEl.classList.remove('d-none'); }
      else { imgEl.classList.add('d-none'); imgEl.src=''; }
      modal.show();
    });
  });
});
</script>

<?php /* bottom_nav.php sudah di-include oleh footer.php — hindari include ganda */ ?>
<?php include __DIR__.'/includes/footer.php'; ?>

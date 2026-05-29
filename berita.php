<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Berita Terkini';

// Sumber baru: RSS Antara News (gratis, tanpa API key, stabil).
// Sebelumnya pakai api-berita-indonesia.vercel.app (CNN) yang sudah tidak aktif.
$KATEGORI = [
  'politik'   => ['label'=>'Politik',   'icon'=>'bi-bank',     'rss'=>'https://www.antaranews.com/rss/politik'],
  'ekonomi'   => ['label'=>'Ekonomi',   'icon'=>'bi-cash-coin','rss'=>'https://www.antaranews.com/rss/ekonomi'],
  'olahraga'  => ['label'=>'Olahraga',  'icon'=>'bi-trophy',   'rss'=>'https://www.antaranews.com/rss/olahraga'],
  'teknologi' => ['label'=>'Teknologi', 'icon'=>'bi-cpu',      'rss'=>'https://www.antaranews.com/rss/tekno'],
];
$cat = $_GET['cat'] ?? 'politik';
if (!isset($KATEGORI[$cat])) $cat = 'politik';

$posts = ip_fetch_rss($KATEGORI[$cat]['rss'], 600);

include __DIR__.'/includes/header.php'; ?>

<?php ip_card_open('Berita Terkini', 'bi-newspaper'); ?>

<p class="text-muted small mb-3">
  Sumber: <strong>Antara News</strong> (RSS resmi <code>antaranews.com</code>) — gratis tanpa API key.
  Hasil di-cache 10 menit di server.
</p>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 gap-2" style="white-space:nowrap;">
  <?php foreach($KATEGORI as $k=>$v): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k===$cat?'active':'' ?>" href="?cat=<?= $k ?>">
        <i class="bi <?= $v['icon'] ?>"></i> <?= $v['label'] ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (!$posts): ?>
  <div class="alert alert-warning">
    <i class="bi bi-wifi-off"></i> Gagal mengambil berita saat ini. Coba muat ulang dalam beberapa saat.
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach(array_slice($posts, 0, 18) as $p):
      $img = $p['thumbnail'] ?? '';
      $title = $p['title'] ?? '-';
      $desc  = $p['description'] ?? '';
      $link  = $p['link'] ?? '#';
      $date  = $p['pubDate'] ?? '';
      $ts = $date ? strtotime($date) : 0;
      $dateTxt = $ts ? date('d M Y · H:i', $ts) : '';
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="card-img-top" style="height:170px;object-fit:cover;" alt="">
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <h2 class="h6"><a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= htmlspecialchars($title) ?></a></h2>
            <p class="small text-muted flex-grow-1"><?= htmlspecialchars(mb_strimwidth($desc,0,160,'…')) ?></p>
            <div class="d-flex justify-content-between align-items-center small">
              <span class="text-muted"><i class="bi bi-clock"></i> <?= htmlspecialchars($dateTxt) ?></span>
              <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Baca <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

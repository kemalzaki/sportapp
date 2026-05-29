<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Berita Terkini';

// Sumber: RSS Antara News (gratis, tanpa API key).
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

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami mb-3">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-newspaper"></i> Berita Terkini</h1>
    <p class="small mb-0 opacity-85">Sumber resmi Antara News — diperbarui otomatis.</p>
  </div>
</div>

<?php ip_card_open('Berita Terkini', 'bi-newspaper'); ?>

<p class="text-muted small mb-3">
  Sumber: <strong>Antara News</strong> (RSS resmi <code>antaranews.com</code>) — gratis tanpa API key.
  Klik judul atau tombol <em>Baca</em> untuk membuka isi berita dalam popup.
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
    <?php foreach(array_slice($posts, 0, 18) as $i=>$p):
      $img = $p['thumbnail'] ?? '';
      $title = $p['title'] ?? '-';
      $desc  = $p['description'] ?? '';
      $link  = $p['link'] ?? '#';
      $date  = $p['pubDate'] ?? '';
      $ts = $date ? strtotime($date) : 0;
      $dateTxt = $ts ? date('d M Y · H:i', $ts) : '';
      // simpan data untuk popup
      $payload = htmlspecialchars(json_encode([
        'title'=>$title,'desc'=>$desc,'img'=>$img,'link'=>$link,'date'=>$dateTxt,
        'cat'=>$KATEGORI[$cat]['label'],
      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm berita-card">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" loading="lazy" class="card-img-top" style="height:170px;object-fit:cover;cursor:pointer;" alt="" data-berita="<?= $payload ?>">
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
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
        <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
          <span><i class="bi bi-tag"></i> <span id="mbCat">-</span></span>
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
(function(){
  const modalEl = document.getElementById('modalBerita');
  if (!modalEl || typeof bootstrap === 'undefined') return;
  const modal = new bootstrap.Modal(modalEl);
  document.querySelectorAll('.berita-trigger, img[data-berita]').forEach(el=>{
    el.addEventListener('click', function(e){
      e.preventDefault();
      let data; try { data = JSON.parse(this.getAttribute('data-berita')); } catch(_) { return; }
      document.getElementById('mbTitle').textContent = data.title || 'Berita';
      document.getElementById('mbCat').textContent   = data.cat   || '-';
      document.getElementById('mbDate').textContent  = data.date  || '-';
      document.getElementById('mbDesc').innerHTML    = data.desc  || '<em class="text-muted">Tidak ada ringkasan.</em>';
      document.getElementById('mbLink').href         = data.link  || '#';
      const imgEl = document.getElementById('mbImg');
      if (data.img) { imgEl.src = data.img; imgEl.classList.remove('d-none'); }
      else { imgEl.classList.add('d-none'); imgEl.src=''; }
      modal.show();
    });
  });
})();
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

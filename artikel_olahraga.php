<?php
/**
 * Halaman "Artikel Olahraga & Teknik" — bacaan singkat dari Wikipedia REST API
 * (id.wikipedia.org). Revisi 31 Mei 2026:
 *  - Gambar Senam, Tinju, Renang, Peregangan dihapus (pakai ikon fallback)
 *  - Artikel Pemanasan, Plank, Sprint, Latihan Interval dihapus dari daftar
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Artikel Olahraga & Teknik';

// Daftar topik (judul artikel di Wikipedia bahasa Indonesia).
$TOPIK = [
  'Cabang Olahraga' => [
    'Sepak_bola','Bulu_tangkis','Bola_basket','Bola_voli','Tenis_meja',
    'Lari','Renang','Bersepeda','Yoga','Senam','Tinju','Karate','Pencak_silat',
    'Angkat_besi','Panahan','Catur',
  ],
  'Teknik & Latihan' => [
    'Peregangan','Push-up','Pull-up','Lari_jarak_jauh','Kalistenik','Aerobik',
  ],
];

// Slug yang gambar thumbnail-nya disembunyikan (pakai ikon).
$HIDE_IMG = ['Senam','Tinju','Renang','Peregangan'];

$slug = preg_replace('/[^A-Za-z0-9_()\-\.]/','', $_GET['t'] ?? '');
$detail = null;
if ($slug) {
    $j = ip_fetch_json('https://id.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($slug), 86400);
    if (!empty($j['title'])) $detail = $j;
}

include __DIR__.'/includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami hero-artikel mb-3">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-journal-richtext"></i> Artikel Olahraga &amp; Teknik</h1>
    <p class="small mb-0 opacity-85">Bacaan ringkas dari Wikipedia tentang macam-macam olahraga dan teknik yang benar.</p>
  </div>
</div>

<?php ip_card_open('Artikel Olahraga & Teknik', 'bi-journal-richtext'); ?>

<?php if ($detail): ?>
  <a href="/artikel_olahraga.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Daftar artikel</a>
  <div class="card shadow-sm border-0 mb-3">
    <?php
      $showHeroImg = !in_array($slug, $HIDE_IMG, true);
      if ($showHeroImg && !empty($detail['originalimage']['source'])): ?>
      <img src="<?= htmlspecialchars($detail['originalimage']['source']) ?>" class="card-img-top" style="max-height:320px;object-fit:cover" alt="">
    <?php elseif ($showHeroImg && !empty($detail['thumbnail']['source'])): ?>
      <img src="<?= htmlspecialchars($detail['thumbnail']['source']) ?>" class="card-img-top" style="max-height:320px;object-fit:cover" alt="">
    <?php endif; ?>
    <div class="card-body">
      <h2 class="h4"><?= htmlspecialchars($detail['title']) ?></h2>
      <?php if (!empty($detail['description'])): ?>
        <p class="text-muted small mb-2"><?= htmlspecialchars($detail['description']) ?></p>
      <?php endif; ?>
      <div class="berita-isi"><?= nl2br(htmlspecialchars($detail['extract'] ?? '')) ?></div>
      <?php if (!empty($detail['content_urls']['desktop']['page'])): ?>
        <a href="<?= htmlspecialchars($detail['content_urls']['desktop']['page']) ?>" target="_blank" rel="noopener" class="btn btn-primary mt-3">
          <i class="bi bi-box-arrow-up-right"></i> Baca selengkapnya di Wikipedia
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <p class="text-muted small mb-3">Klik salah satu artikel untuk membaca ringkasan lengkapnya.</p>
  <?php foreach ($TOPIK as $grup => $list): ?>
    <h2 class="h6 text-uppercase text-muted mt-3 mb-2"><?= htmlspecialchars($grup) ?></h2>
    <div class="row g-3 mb-2">
      <?php foreach ($list as $t):
        $sum = ip_fetch_json('https://id.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($t), 86400);
        $thumb = in_array($t, $HIDE_IMG, true) ? '' : ($sum['thumbnail']['source'] ?? '');
        $judul = $sum['title']       ?? str_replace('_',' ', $t);
        $desc  = $sum['description'] ?? '';
        $extract = $sum['extract']   ?? '';
      ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a href="?t=<?= urlencode($t) ?>" class="text-decoration-none">
            <div class="card h-100 shadow-sm gerakan-card">
              <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" class="card-img-top" style="height:130px;object-fit:cover" loading="lazy" alt="">
              <?php else: ?>
                <div class="card-img-top d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="height:130px"><i class="bi bi-trophy fs-1"></i></div>
              <?php endif; ?>
              <div class="card-body">
                <div class="fw-semibold small"><?= htmlspecialchars($judul) ?></div>
                <?php if ($desc): ?><div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars(mb_strimwidth($desc,0,60,'…')) ?></div><?php endif; ?>
                <?php if ($extract): ?><div class="small mt-1" style="font-size:.78rem"><?= htmlspecialchars(mb_strimwidth($extract,0,90,'…')) ?></div><?php endif; ?>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php ip_card_close(); ?>
<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

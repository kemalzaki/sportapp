<?php
/**
 * toko_olahraga.php — Revisi R23 (27 Juni 2026)
 * Halaman user: daftar toko perlengkapan olahraga terdekat.
 * Data dari tabel `toko_olahraga` (lihat migrations_r23_27jun2026.sql).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Toko Perlengkapan Olahraga Terdekat';
$u = current_user();

try {
    $rows = db_all("SELECT * FROM toko_olahraga WHERE aktif=TRUE ORDER BY sort_order, LOWER(nama)");
} catch (Throwable $e) {
    $rows = [];
    $tableMissing = true;
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $needle = mb_strtolower($q);
    $rows = array_values(array_filter($rows, function($r) use ($needle) {
        $hay = mb_strtolower(($r['nama'] ?? '').' '.($r['kota'] ?? '').' '.($r['kategori'] ?? '').' '.($r['alamat'] ?? '').' '.($r['deskripsi'] ?? ''));
        return mb_strpos($hay, $needle) !== false;
    }));
}

include __DIR__.'/includes/header.php';
?>
<style>
.toko-card{transition:transform .15s ease, box-shadow .15s ease;}
.toko-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.08);}
.toko-foto{width:100%;height:160px;object-fit:cover;background:#f1f5f9;border-radius:.5rem .5rem 0 0;}
.toko-foto-fallback{height:160px;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0ea5e9,#10b981);color:#fff;font-size:2.5rem;
  border-radius:.5rem .5rem 0 0;}
</style>

<div class="container py-3">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
      <li class="breadcrumb-item active">Toko Perlengkapan Olahraga</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-shop text-primary"></i> Toko Perlengkapan Olahraga Terdekat</h1>
      <div class="small text-muted">Daftar toko sepatu, jersey, bola, &amp; perlengkapan olahraga di sekitar Anda.</div>
    </div>
    <form class="d-flex" role="search" method="get">
      <input class="form-control form-control-sm me-2" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="🔍 Cari nama / kota / kategori...">
      <button class="btn btn-primary btn-sm" type="submit">Cari</button>
    </form>
  </div>

  <?php if (!empty($tableMissing)): ?>
    <div class="alert alert-warning small">
      <i class="bi bi-exclamation-triangle"></i>
      Tabel <code>toko_olahraga</code> belum tersedia. Jalankan migrasi
      <code>migrations_r23_27jun2026.sql</code> terlebih dahulu.
    </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="alert alert-light border text-center text-muted">
      <i class="bi bi-shop-window fs-3 d-block mb-2"></i>
      Belum ada toko terdaftar<?= $q!=='' ? ' untuk pencarian "'.htmlspecialchars($q).'"' : '' ?>.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach($rows as $r):
        $wa = preg_replace('/\D+/', '', $r['wa_nomor'] ?? '');
        $teks = 'Halo, saya menemukan toko Anda di KawanKeringat. Saya ingin bertanya & memesan perlengkapan olahraga di "'.$r['nama'].'". Terima kasih.';
        $waLink = $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode($teks) : '';
        $mapLink = !empty($r['map_url']) ? $r['map_url']
                : ((!empty($r['lat']) && !empty($r['lng'])) ? ('https://www.google.com/maps/search/?api=1&query='.$r['lat'].','.$r['lng']) : '');
      ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card toko-card shadow-sm h-100 border-0">
            <?php if (!empty($r['foto_url'])): ?>
              <img class="toko-foto" src="<?= htmlspecialchars($r['foto_url']) ?>" alt="<?= htmlspecialchars($r['nama']) ?>" loading="lazy"
                   onerror="this.outerHTML='<div class=&quot;toko-foto-fallback&quot;><i class=&quot;bi bi-shop&quot;></i></div>'">
            <?php else: ?>
              <div class="toko-foto-fallback"><i class="bi bi-shop"></i></div>
            <?php endif; ?>
            <div class="card-body">
              <h2 class="h6 mb-1"><?= htmlspecialchars($r['nama']) ?></h2>
              <?php if (!empty($r['kategori'])): ?>
                <span class="badge bg-light text-secondary border mb-1"><?= htmlspecialchars($r['kategori']) ?></span>
              <?php endif; ?>
              <?php if (!empty($r['alamat']) || !empty($r['kota'])): ?>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt"></i>
                  <?= htmlspecialchars(trim(($r['alamat'] ?? '').' '.($r['kota'] ? '· '.$r['kota'] : ''))) ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($r['jam_buka'])): ?>
                <div class="small text-muted mb-1"><i class="bi bi-clock"></i> <?= htmlspecialchars($r['jam_buka']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['deskripsi'])): ?>
                <p class="small mb-2"><?= htmlspecialchars($r['deskripsi']) ?></p>
              <?php endif; ?>
              <div class="d-flex gap-2 flex-wrap mt-2">
                <?php if ($waLink): ?>
                  <a class="btn btn-success btn-sm" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp"></i> Tanyakan &amp; Pesan via WhatsApp
                  </a>
                <?php endif; ?>
                <?php if (!empty($r['telp'])): ?>
                  <a class="btn btn-outline-secondary btn-sm" href="tel:<?= htmlspecialchars($r['telp']) ?>">
                    <i class="bi bi-telephone"></i> Telepon
                  </a>
                <?php endif; ?>
                <?php if ($mapLink): ?>
                  <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($mapLink) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-map"></i> Lihat di Peta
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="alert alert-info small mt-4 mb-0">
    <i class="bi bi-info-circle"></i> Ingin toko Anda terdaftar?
    Hubungi admin KawanKeringat untuk mendaftarkan toko perlengkapan olahraga Anda.
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

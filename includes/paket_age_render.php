<?php
/**
 * includes/paket_age_render.php — Revisi R23 (27 Juni 2026)
 * Helper render halaman "Paket Anak" / "Paket Lansia" berdasarkan
 * kelompok usia. Dipakai bersama oleh paket_anak_*.php & paket_lansia_*.php.
 *
 * Variabel yang harus di-set sebelum include:
 *   $pa_judul    : judul halaman (mis. "Paket Anak — Usia 2-4 Tahun")
 *   $pa_subjudul : keterangan ringkas
 *   $pa_warna    : bootstrap color (success/info/warning/primary/danger)
 *   $pa_icon     : bi-* icon
 *   $pa_rincian  : deskripsi panjang
 *   $pa_aktivitas: array of string daftar aktivitas
 *   $pa_tips     : array of string tips keamanan/perawatan
 *   $pa_pesan_wa : string nomor WA admin (opsional)
 */
$pa_warna = $pa_warna ?? 'primary';
$pa_icon  = $pa_icon  ?? 'bi-people-fill';
$pa_pesan_wa = $pa_pesan_wa ?? '6281234567890';
$pa_aktivitas = $pa_aktivitas ?? [];
$pa_tips = $pa_tips ?? [];
$waLink = 'https://wa.me/'.$pa_pesan_wa.'?text='.rawurlencode('Halo Admin KawanKeringat, saya ingin bertanya & memesan '.($pa_judul ?? 'Paket Olahraga').'.');
?>
<div class="container py-3">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($pa_judul) ?></li>
    </ol>
  </nav>

  <div class="card shadow-sm border-<?= $pa_warna ?> mb-3">
    <div class="card-header bg-<?= $pa_warna ?>-subtle text-<?= $pa_warna ?>-emphasis">
      <i class="bi <?= htmlspecialchars($pa_icon) ?>"></i>
      <strong><?= htmlspecialchars($pa_judul) ?></strong>
    </div>
    <div class="card-body">
      <p class="mb-2 small text-muted"><?= htmlspecialchars($pa_subjudul ?? '') ?></p>

      <h2 class="h6 mt-3"><i class="bi bi-journal-text text-<?= $pa_warna ?>"></i> Rincian Lengkap</h2>
      <div class="alert alert-light border small mb-3"><?= nl2br(htmlspecialchars($pa_rincian ?? '')) ?></div>

      <?php if ($pa_aktivitas): ?>
      <h2 class="h6 mt-3"><i class="bi bi-list-check text-<?= $pa_warna ?>"></i> Aktivitas yang Disarankan</h2>
      <ul class="mb-3">
        <?php foreach ($pa_aktivitas as $a): ?>
          <li class="small"><?= htmlspecialchars($a) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($pa_tips): ?>
      <h2 class="h6 mt-3"><i class="bi bi-shield-check text-<?= $pa_warna ?>"></i> Tips Keamanan &amp; Pendampingan</h2>
      <ul class="mb-3">
        <?php foreach ($pa_tips as $t): ?>
          <li class="small"><?= htmlspecialchars($t) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="d-flex gap-2 flex-wrap mt-3">
        <a class="btn btn-<?= $pa_warna ?> btn-sm" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">
          <i class="bi bi-whatsapp"></i> Tanyakan / Pesan via WhatsApp
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="/artikel_olahraga.php">
          <i class="bi bi-journal-richtext"></i> Lihat Artikel Olahraga
        </a>
      </div>

      <div class="alert alert-warning small mt-3 mb-0">
        <i class="bi bi-info-circle"></i> Konten ini bersifat edukatif. Untuk anak/lansia
        dengan kondisi kesehatan khusus, konsultasikan dahulu dengan dokter atau pelatih bersertifikat.
      </div>
    </div>
  </div>
</div>

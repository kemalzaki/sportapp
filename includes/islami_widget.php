<?php
/**
 * Widget Islami untuk dashboard (dipakai di index.php).
 * Memerlukan: islami_data.php sudah di-include, dan $u (current user) tersedia.
 */
require_once __DIR__ . '/islami_data.php';
require_once __DIR__ . '/islami_helpers.php';

global $ISLAMI_AYAT_HARIAN, $ISLAMI_HADIST, $ISLAMI_QUOTES, $ISLAMI_DOA;

$_ayat   = islami_pick_today($ISLAMI_AYAT_HARIAN, 'ayat');
$_hadist = islami_pick_today($ISLAMI_HADIST, 'hadist');
$_quote  = islami_pick_session($ISLAMI_QUOTES, 'quote');
$_doa    = islami_pick_today($ISLAMI_DOA, 'doa');

$_pref = null;
if (isset($u) && $u) $_pref = islami_pref((int)$u['id']);
$_kota = $_pref['kota'] ?? 'Jakarta';
$_negara = $_pref['negara'] ?? 'Indonesia';
$_modeTenang = (int)($_pref['mode_tenang'] ?? 1);

$_hijri = masehi_ke_hijriyah();
?>
<div class="card shadow-sm mb-3 border-success-subtle" id="islamiWidget">
  <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
    <span><i class="bi bi-stars text-success"></i> <strong>Sentuhan Islami Hari Ini</strong>
      <span class="badge bg-success-subtle text-success ms-1"><?= $_hijri['hari'] ?> <?= htmlspecialchars(hijriyah_nama_bulan($_hijri['bulan'])) ?> <?= $_hijri['tahun'] ?> H</span>
    </span>
    <a href="/islami.php" class="btn btn-sm btn-outline-success">Buka Hub Islami <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="p-3 rounded bg-success-subtle h-100">
          <div class="small text-success fw-semibold mb-1"><i class="bi bi-book"></i> Ayat Harian — <?= htmlspecialchars($_ayat[0]) ?></div>
          <div class="fs-5 text-end" style="font-family:'Amiri','Scheherazade',serif;line-height:1.9"><?= htmlspecialchars($_ayat[1]) ?></div>
          <div class="small mt-2 fst-italic">"<?= htmlspecialchars($_ayat[2]) ?>"</div>
          <?php if (isset($u) && $u): ?>
          <form method="post" action="/islami.php" class="mt-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="challenge_done">
            <input type="hidden" name="key" value="ayat_harian">
            <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Sudah baca (1 ayat / hari)</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-3 rounded border h-100">
          <div class="small text-primary fw-semibold mb-1"><i class="bi bi-heart-pulse"></i> Hadist Kesehatan & Disiplin</div>
          <div><?= htmlspecialchars($_hadist[1]) ?></div>
          <div class="small text-muted mt-1">— <?= htmlspecialchars($_hadist[0]) ?></div>
          <hr class="my-2">
          <div class="small text-warning fw-semibold mb-1"><i class="bi bi-chat-quote"></i> Doa Singkat — <?= htmlspecialchars($_doa[0]) ?></div>
          <div class="text-end" style="font-family:'Amiri',serif"><?= htmlspecialchars($_doa[1]) ?></div>
          <div class="small fst-italic mt-1"><?= htmlspecialchars($_doa[2]) ?></div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-6">
        <div class="p-3 rounded border h-100" id="prayerCard" data-kota="<?= htmlspecialchars($_kota) ?>" data-negara="<?= htmlspecialchars($_negara) ?>" data-mode-tenang="<?= $_modeTenang ?>">
          <div class="d-flex justify-content-between align-items-center">
            <div class="small fw-semibold text-success"><i class="bi bi-mosque"></i> Jadwal Adzan <?= htmlspecialchars($_kota) ?></div>
            <a href="/jadwal_sholat.php" class="small">Detail</a>
          </div>
          <div class="mt-2" id="prayerNext">Memuat…</div>
          <div class="mt-1 small text-muted" id="prayerList"></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-3 rounded bg-warning-subtle h-100">
          <div class="small fw-semibold text-warning mb-1"><i class="bi bi-quote"></i> Quote Islami</div>
          <div class="fst-italic">"<?= htmlspecialchars($_quote[1]) ?>"</div>
          <div class="small text-muted mt-1">— <?= htmlspecialchars($_quote[0]) ?></div>
          <div class="mt-2 d-flex gap-2 flex-wrap">
            <a href="/dzikir.php?w=pagi" class="btn btn-sm btn-outline-warning"><i class="bi bi-sunrise"></i> Dzikir Pagi</a>
            <a href="/dzikir.php?w=petang" class="btn btn-sm btn-outline-warning"><i class="bi bi-sunset"></i> Dzikir Petang</a>
            <a href="/challenge.php" class="btn btn-sm btn-outline-success"><i class="bi bi-trophy"></i> Challenge</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/islami.js" defer></script>

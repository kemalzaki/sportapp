<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/htmx.php';

$u = current_user(); if (!$u) htmx_redirect('/login.php');

htmx_layout_start('Riwayat Aktivitas');
?>
<div class="container py-3">
  <h2>Riwayat</h2>

  <!-- Tab filter, klik = swap parsial tanpa reload -->
  <div class="btn-group mb-3" role="group">
    <a class="btn btn-outline-primary" hx-get="/riwayat.php?f=week"  hx-target="#riwayat-list">Minggu</a>
    <a class="btn btn-outline-primary" hx-get="/riwayat.php?f=month" hx-target="#riwayat-list">Bulan</a>
    <a class="btn btn-outline-primary" hx-get="/riwayat.php?f=all"   hx-target="#riwayat-list">Semua</a>
  </div>

  <div id="riwayat-list">
    <!-- Skeleton awal -->
    <div class="skeleton skel-line"></div>
    <div class="skeleton skel-block"></div>
  </div>
</div>
<?php
htmx_layout_end();

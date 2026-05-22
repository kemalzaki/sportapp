<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/notifications.php';
send_security_headers(); enforce_session_timeout();
$u = current_user();
if ($u) touch_online();
$navFoto = null; $nUnread = 0; $darkMode = 0;
if ($u) {
  $_uf = db_one("SELECT foto_url, COALESCE(dark_mode,0) AS dark_mode FROM users WHERE id=$1", [(int)$u['id']]);
  $navFoto = $_uf['foto_url'] ?? null;
  $darkMode = (int)($_uf['dark_mode'] ?? 0);
  $nUnread = unread_notif_count((int)$u['id']);
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0ea5e9">
<title><?= htmlspecialchars(($pageTitle ?? 'HapFam SportApp') . ' · HapFam SportApp') ?></title>
<link rel="manifest" href="/manifest.php">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<!-- Favicon / shortcut icon browser -->
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/assets/icon-512.png">
<link rel="shortcut icon" href="/assets/icon-192.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/app-v3.css">
<style>
.user-with-avatar{display:inline-flex;align-items:center;gap:.4rem;position:relative;}
.user-avatar-fallback{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;}
.online-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px #fff;display:inline-block;position:absolute;bottom:0;right:0;}
.chat-bubble{background:var(--bs-tertiary-bg,#f1f5f9);border-radius:12px;padding:.5rem .75rem;margin-bottom:.4rem;}
.chat-reply{margin-left:2rem;border-left:3px solid #0ea5e9;}
.chat-meta{font-size:.7rem;color:var(--bs-secondary-color,#64748b);}
.pill{display:inline-block;padding:.15rem .6rem;border-radius:999px;background:var(--bs-tertiary-bg,#f1f5f9);font-size:.75rem;color:var(--bs-secondary-color,#475569);}
.card-stat .stat-icon{width:38px;height:38px;border-radius:10px;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.4rem;}
.card-stat .stat-label{font-size:.75rem;color:var(--bs-secondary-color,#64748b);}
.card-stat .stat-value{font-size:1.4rem;font-weight:700;}
.hero{padding:.5rem 0;}
.badge-soft{display:inline-block;padding:.25rem .75rem;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:.8rem;font-weight:500;}
.ql-container{min-height:140px;}
.ql-editor{min-height:140px;}
.wysiwyg-wrap{margin-bottom:1.25rem;}
.heatmap{display:grid;grid-auto-flow:column;grid-template-rows:repeat(7,12px);gap:3px;overflow-x:auto;padding:4px 0;}
.heatmap .cell{width:12px;height:12px;border-radius:2px;background:#ebedf0;}
.heatmap .l1{background:#9be9a8;} .heatmap .l2{background:#40c463;} .heatmap .l3{background:#30a14e;} .heatmap .l4{background:#216e39;}
[data-bs-theme=dark] .heatmap .cell{background:#1f2937;}
/* === Navigasi rapi (item tidak tabrakan) === */
.navbar .navbar-nav .nav-link{padding-left:.7rem;padding-right:.7rem;white-space:nowrap;}
.navbar .navbar-nav .nav-link i{margin-right:.25rem;}
@media (min-width:992px){
  .navbar .navbar-nav.me-auto{column-gap:.15rem;}
  .navbar form[role=search]{margin-right:.5rem;}
  .navbar .navbar-nav.align-items-lg-center .nav-item + .nav-item{margin-left:.25rem;}
}
.navbar .dropdown-menu{min-width:240px;}
/* === Preloader === */
#appPreloader{position:fixed;inset:0;background:rgba(255,255,255,.92);z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.75rem;transition:opacity .25s ease;}
#appPreloader .spinner{width:54px;height:54px;border:5px solid #e2e8f0;border-top-color:#0ea5e9;border-radius:50%;animation:hfspin 1s linear infinite;}
#appPreloader .lbl{font-weight:600;color:#0f172a;letter-spacing:.02em;}
#appPreloader.hidden{opacity:0;pointer-events:none;}
@keyframes hfspin{to{transform:rotate(360deg);}}
#liveRefreshBadge{position:fixed;right:14px;bottom:78px;z-index:1080;display:none;}
</style>
</head>
<body>
<!-- Global preloader -->
<div id="appPreloader"><div class="spinner"></div><div class="lbl">Memuat HapFam SportApp…</div></div>
<div id="liveRefreshBadge" class="badge bg-success rounded-pill shadow"><i class="bi bi-arrow-clockwise"></i> Data diperbarui</div>
<nav class="navbar navbar-expand-lg sticky-top" data-bs-theme="dark" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/index.php"><i class="bi bi-lightning-charge-fill text-warning"></i> HapFam <span class="opacity-75">SportApp</span></a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/index.php"><i class="bi bi-house-door"></i> Beranda</a></li>
        <li class="nav-item"><a class="nav-link" href="/calendar.php"><i class="bi bi-calendar3"></i> Kalender</a></li>
        <li class="nav-item"><a class="nav-link" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat</a></li>
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link" href="/checkin.php"><i class="bi bi-qr-code-scan"></i> Check-in</a></li>
          <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload</a></li>
          <li class="nav-item"><a class="nav-link" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring</a></li>
          <li class="nav-item"><a class="nav-link" href="/event.php"><i class="bi bi-trophy"></i> Event</a></li>
          <li class="nav-item"><a class="nav-link" href="/tempat.php"><i class="bi bi-calendar2-week"></i> Booking</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="/kalkulator.php"><i class="bi bi-heart-pulse"></i> Kalkulator Sehat</a></li>
        <?php if ($u && $u['role']==='admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-shield-lock"></i> Admin</a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item" href="/admin/jadwal.php">Manajemen Jadwal</a></li>
              <li><a class="dropdown-item" href="/admin/absensi.php">Input Absensi</a></li>
              <li><a class="dropdown-item" href="/admin/qr_show.php">QR Check-in</a></li>
              <li><a class="dropdown-item" href="/admin/members.php">Member</a></li>
              <li><a class="dropdown-item" href="/admin/tim.php">Tim</a></li>
              <li><a class="dropdown-item" href="/admin/tempat.php">Tempat</a></li>
              <li><a class="dropdown-item" href="/admin/event.php">Event / Tournament</a></li>
              <li><a class="dropdown-item" href="/admin/stats.php">📊 Statistik Pintar</a></li>
              <li><a class="dropdown-item" href="/admin/jenis.php">Jenis Olahraga</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Export Data</h6></li>
              <li><a class="dropdown-item" href="/export.php?type=members&format=csv">Member · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=jadwal&format=csv">Jadwal · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=tempat&format=csv">Tempat · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=aktivitas&format=csv">Aktivitas · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=booking&format=csv">Booking · Excel</a></li>
              <li><a class="dropdown-item" href="/export.php?type=members&format=pdf">Member · PDF</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <form class="d-flex me-2" role="search" action="/search.php">
        <input class="form-control form-control-sm" name="q" placeholder="🔍 Cari semua..." style="min-width:180px">
      </form>
      <ul class="navbar-nav align-items-lg-center">
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link position-relative" href="/profile.php" title="Profil">
            <?= user_avatar($navFoto, $u['nama'], 28) ?>
            <?php if($nUnread): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;"><?= $nUnread ?></span><?php endif; ?>
          </a></li>
          <li class="nav-item"><a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/register.php">Daftar</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-3">

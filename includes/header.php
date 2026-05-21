<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$u = current_user();
if ($u) touch_online();
// ambil foto user untuk navbar
$navFoto = null;
if ($u) {
  $_uf = db_one("SELECT foto_url FROM users WHERE id=$1", [(int)$u['id']]);
  $navFoto = $_uf['foto_url'] ?? null;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= htmlspecialchars(($pageTitle ?? 'HapFam SportApp') . ' · HapFam SportApp') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
.user-with-avatar{display:inline-flex;align-items:center;gap:.4rem;position:relative;}
.user-avatar-fallback{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;}
.online-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px #fff;display:inline-block;}
.captcha-box{padding:.45rem .75rem;background:#f1f5f9;border-radius:8px;font-weight:700;letter-spacing:.05em;}
.news-slider .carousel-item img{height:280px;object-fit:cover;width:100%;border-radius:12px;}
.chat-bubble{background:#f1f5f9;border-radius:12px;padding:.5rem .75rem;margin-bottom:.4rem;}
.chat-meta{font-size:.7rem;color:#64748b;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg app-nav sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/index.php">
      <span class="brand-logo"><i class="bi bi-lightning-charge-fill"></i></span>
      <span class="brand-text">HapFam <span>SportApp</span></span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="menu">
      <i class="bi bi-list text-white fs-3"></i>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/index.php"><i class="bi bi-house-door"></i> Beranda</a></li>
        <li class="nav-item"><a class="nav-link" href="/riwayat.php"><i class="bi bi-clock-history"></i> Riwayat</a></li>
        <?php if ($u): ?>
          <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="bi bi-cloud-upload"></i> Upload Harian</a></li>
          <li class="nav-item"><a class="nav-link" href="/monitoring.php"><i class="bi bi-graph-up-arrow"></i> Monitoring</a></li>
        <?php endif; ?>
        <?php if ($u && $u['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-shield-lock"></i> Admin</a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item" href="/admin/jadwal.php"><i class="bi bi-calendar-event me-2"></i>Manajemen Jadwal</a></li>
              <li><a class="dropdown-item" href="/admin/absensi.php"><i class="bi bi-check2-square me-2"></i>Input Absensi</a></li>
              <li><a class="dropdown-item" href="/admin/members.php"><i class="bi bi-people me-2"></i>Member</a></li>
              <li><a class="dropdown-item" href="/admin/jenis.php"><i class="bi bi-tags me-2"></i>Jenis Olahraga</a></li>
              <li><a class="dropdown-item" href="/admin/berita.php"><i class="bi bi-newspaper me-2"></i>Berita</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <?php if ($u): ?>
          <li class="nav-item me-lg-2">
            <a href="/profile.php" class="text-decoration-none">
            <span class="user-chip">
              <?= user_avatar($navFoto, $u['nama'], 22) ?>
              <?= htmlspecialchars($u['nama']) ?>
              <span class="role role-<?= $u['role'] ?>"><?= $u['role'] ?></span>
            </span></a>
          </li>
          <li class="nav-item"><a class="btn btn-sm btn-light" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-sm btn-light" href="/login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4 page-fade">

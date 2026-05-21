<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
$pageTitle='Login'; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    if (!captcha_check($captcha)) {
        $err = 'Jawaban captcha salah.';
    } else {
        $u = db_one("SELECT * FROM users WHERE email=$1", [$email]);
        if ($u && password_verify($pass, $u['password_hash'])) {
            $_SESSION['user'] = ['id'=>$u['id'],'nama'=>$u['nama'],'email'=>$u['email'],'role'=>$u['role']];
            unset($_SESSION['captcha_answer']);
            header('Location: /index.php'); exit;
        }
        $err = 'Email atau password salah.';
    }
}

[$ca, $cb] = captcha_new();
include __DIR__.'/includes/header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-head">
      <div class="ic mb-2"><i class="bi bi-box-arrow-in-right"></i></div>
      <h4 class="mb-0 fw-bold">Masuk ke HapFam SportApp</h4>
      <small class="opacity-75">Lanjutkan untuk catat aktivitas olahraga</small>
    </div>
    <div class="auth-body">
      <?php if($err): ?><div class="alert alert-danger py-2 small"><?= $err ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" name="email" type="email" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input class="form-control" name="password" type="password" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Verifikasi (captcha)</label>
          <div class="d-flex gap-2 align-items-center">
            <div class="captcha-box"><i class="bi bi-shield-check text-primary"></i> <?= $ca ?> + <?= $cb ?> =</div>
            <input class="form-control" name="captcha" type="number" inputmode="numeric" required placeholder="?">
          </div>
        </div>
        <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
        <p class="text-center mt-3 mb-0 small">Belum punya akun? <a href="register.php" class="fw-semibold">Daftar di sini</a></p>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

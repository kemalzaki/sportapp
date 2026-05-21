<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
$pageTitle='Daftar'; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $nama=trim($_POST['nama']??''); $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if(!$nama || !$email || strlen($pass)<6){
        $err='Lengkapi data, password minimal 6 karakter.';
    } else {
        try{
            db_exec("INSERT INTO users(nama,email,password_hash,role) VALUES($1,$2,$3,'member')",
                    [$nama, $email, password_hash($pass, PASSWORD_BCRYPT)]);
            header('Location: /login.php?registered=1'); exit;
        } catch (Throwable $e) {
            $err='Email sudah terdaftar.';
        }
    }
}
include __DIR__.'/includes/header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-head">
      <div class="ic mb-2"><i class="bi bi-person-plus"></i></div>
      <h4 class="mb-0 fw-bold">Daftar Member Baru</h4>
      <small class="opacity-75">Mulai pantau performa olahragamu</small>
    </div>
    <div class="auth-body">
      <?php if($err): ?><div class="alert alert-danger py-2 small"><?= $err ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-3"><label class="form-label small fw-semibold">Nama Lengkap</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
          <input class="form-control" name="nama" required></div></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Email</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input class="form-control" name="email" type="email" required></div></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Password (min 6)</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input class="form-control" name="password" type="password" required></div></div>
        <button class="btn btn-primary w-100"><i class="bi bi-person-check me-1"></i> Daftar</button>
        <p class="text-center mt-3 mb-0 small">Sudah punya akun? <a href="login.php" class="fw-semibold">Masuk</a></p>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

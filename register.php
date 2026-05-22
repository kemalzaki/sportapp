<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
$pageTitle = 'Daftar';
$err = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('register:'.($_SERVER['REMOTE_ADDR'] ?? '0'), 5, 600);
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    $jk   = $_POST['jenis_kelamin'] ?? '';
    $wa   = preg_replace('/[^0-9+]/','', trim($_POST['nomor_wa'] ?? ''));
    if (strlen($nama) < 2 || strlen($nama) > 80) $err = 'Nama 2-80 karakter.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email tidak valid.';
    elseif (strlen($pass) < 8) $err = 'Password minimal 8 karakter.';
    elseif (!in_array($jk, ['L','P'], true)) $err = 'Jenis kelamin wajib dipilih.';
    elseif (strlen($wa) < 8 || strlen($wa) > 20) $err = 'Nomor WhatsApp tidak valid.';
    elseif (db_one("SELECT id FROM users WHERE LOWER(email)=$1", [$email])) $err = 'Email sudah terdaftar.';
    else {
        $hash = hash_password($pass);
        try {
            db_exec("INSERT INTO users(nama,email,password_hash,role,jenis_kelamin,nomor_wa) VALUES($1,$2,$3,'member',$4,$5)",
                [$nama, $email, $hash, $jk, $wa]);
            header('Location: /login.php'); exit;
        } catch (Throwable $e) {
            try {
                db_exec("INSERT INTO users(nama,email,password,role,jenis_kelamin,nomor_wa) VALUES($1,$2,$3,'member',$4,$5)",
                    [$nama,$email,$hash,$jk,$wa]);
                header('Location: /login.php'); exit;
            } catch (Throwable $e2) { $err = 'Pendaftaran gagal: '.$e2->getMessage(); }
        }
    }
}
include __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5"><div class="card shadow-sm"><div class="card-body p-4">
<h4 class="mb-3 text-center">Daftar Akun</h4>
<?php if($err): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="mb-2"><label class="small fw-semibold">Nama</label><input class="form-control" name="nama" required maxlength="80" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"></div>
  <div class="mb-2"><label class="small fw-semibold">Email</label><input class="form-control" name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
  <div class="mb-2"><label class="small fw-semibold">Jenis Kelamin</label>
    <select class="form-select" name="jenis_kelamin" required>
      <option value="">— pilih —</option>
      <option value="L" <?= (($_POST['jenis_kelamin']??'')==='L'?'selected':'') ?>>Laki-laki</option>
      <option value="P" <?= (($_POST['jenis_kelamin']??'')==='P'?'selected':'') ?>>Perempuan</option>
    </select></div>
  <div class="mb-2"><label class="small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Nomor WhatsApp</label>
    <input class="form-control" name="nomor_wa" required placeholder="cth: 081234567890" value="<?= htmlspecialchars($_POST['nomor_wa'] ?? '') ?>"></div>
  <div class="mb-3"><label class="small fw-semibold">Password (min 8)</label><input class="form-control" name="password" type="password" minlength="8" required></div>
  <button class="btn btn-primary w-100">Daftar</button>
</form>
<div class="text-center small mt-3">Sudah punya akun? <a href="/login.php">Masuk</a></div>
</div></div></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

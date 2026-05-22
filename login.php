<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
$pageTitle = 'Login';
$err = null;
if (!empty($_GET['expired'])) $err = 'Sesi habis. Silakan login kembali.';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $uid   = (int)($_POST['user_id'] ?? 0);
    $pass  = $_POST['password'] ?? '';
    $cap   = $_POST['captcha'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0';
    if (!rate_limit("login:$ip", 10, 60)) { http_response_code(429); $err = 'Terlalu banyak percobaan. Coba lagi sebentar.'; }
    elseif (!captcha_check($cap)) $err = 'Captcha salah.';
    else {
        $u = $uid ? db_one("SELECT * FROM users WHERE id=$1", [$uid]) : null;
        $emailKey = strtolower((string)($u['email'] ?? ('user-'.$uid)));
        if (too_many_failed_logins($emailKey)) { $err = 'Akun sementara dikunci karena terlalu banyak login gagal. Coba lagi 10 menit.'; }
        else {
            $ok = false;
            if ($u) {
                $stored = $u['password'] ?? $u['password_hash'] ?? '';
                if ($stored && (str_starts_with($stored,'$2y$') || str_starts_with($stored,'$argon'))) {
                    $ok = verify_password($pass, $stored);
                } else {
                    $ok = hash_equals((string)$stored, (string)$pass);
                    if ($ok) { try { db_exec("UPDATE users SET password=$1 WHERE id=$2", [hash_password($pass), (int)$u['id']]); } catch(Throwable $e) {} }
                }
            }
            log_login_attempt($emailKey, $ok);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id'=>(int)$u['id'], 'nama'=>$u['nama'], 'email'=>$u['email'] ?? '', 'role'=>$u['role']];
                $_SESSION['last_activity'] = time();
                header('Location: /index.php'); exit;
            }
            $err = 'Nama atau password salah.';
        }
    }
}
[$a,$b] = captcha_new();
// Daftar user untuk select box (hanya nama + id)
$userList = [];
try { $userList = db_all("SELECT id, nama, role FROM users ORDER BY nama ASC"); } catch(Throwable $e) {}
include __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5">
<div class="card shadow-sm"><div class="card-body p-4">
  <h4 class="mb-3 text-center">Masuk</h4>
  <?php if($err): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" data-skip-preloader>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="mb-2">
      <label class="small fw-semibold">Nama</label>
      <select class="form-select" name="user_id" required>
        <option value="">-- Pilih nama --</option>
        <?php foreach($userList as $uu): ?>
          <option value="<?= (int)$uu['id'] ?>"><?= htmlspecialchars($uu['nama']) ?><?= $uu['role']==='admin'?' (admin)':'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2"><label class="small fw-semibold">Password</label><input class="form-control" name="password" type="password" required></div>
    <div class="mb-3 d-flex align-items-center gap-2">
      <span class="captcha-box bg-light px-3 py-2 rounded"><?= $a ?> + <?= $b ?> = ?</span>
      <input class="form-control" name="captcha" required style="max-width:120px">
    </div>
    <button class="btn btn-primary w-100">Masuk</button>
  </form>
  <div class="text-center small mt-3">Belum punya akun? <a href="/register.php">Daftar</a></div>
</div></div>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

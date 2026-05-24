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
    // FIX 1: trim password — banyak password gagal karena ikut spasi/newline saat di-paste
    $pass  = trim((string)($_POST['password'] ?? ''));
    $cap   = trim((string)($_POST['captcha'] ?? ''));
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0';

    if (!rate_limit("login:$ip", 10, 60)) {
        http_response_code(429);
        $err = 'Terlalu banyak percobaan. Coba lagi sebentar.';
    } elseif (!captcha_check($cap)) {
        $err = 'Captcha salah.';
    } else {
        $u = $uid ? db_one("SELECT * FROM users WHERE id=$1", [$uid]) : null;
        $emailKey = strtolower((string)($u['email'] ?? ('user-'.$uid)));

        if (too_many_failed_logins($emailKey)) {
            $err = 'Akun sementara dikunci karena terlalu banyak login gagal. Coba lagi 10 menit.';
        } else {
            $ok = false;
            if ($u) {
                // FIX 2: pilih hash yang benar2 berisi — kolom legacy `password` bisa
                // ada tapi kosong ("") sehingga `??` tidak fall-through dan login gagal
                $stored = '';
                if (!empty($u['password_hash'])) {
                    $stored = (string)$u['password_hash'];
                } elseif (!empty($u['password'])) {
                    $stored = (string)$u['password'];
                }

                if ($stored !== '' && (str_starts_with($stored,'$2y$')
                        || str_starts_with($stored,'$2a$')
                        || str_starts_with($stored,'$2b$')
                        || str_starts_with($stored,'$argon'))) {
                    // Hash modern (bcrypt / argon)
                    $ok = verify_password($pass, $stored);
                } elseif ($stored !== '') {
                    // Legacy: bisa plain-text, md5, atau sha1 — coba semuanya
                    if (hash_equals($stored, $pass)
                        || hash_equals($stored, md5($pass))
                        || hash_equals($stored, sha1($pass))) {
                        $ok = true;
                    }
                    // FIX 3: kalau cocok, upgrade ke bcrypt — gunakan kolom yang benar
                    if ($ok) {
                        try {
                            db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",
                                [hash_password($pass), (int)$u['id']]);
                        } catch (Throwable $e) { /* abaikan */ }
                    }
                }
            }

            log_login_attempt($emailKey, $ok);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'    => (int)$u['id'],
                    'nama'  => $u['nama'],
                    'email' => $u['email'] ?? '',
                    'role'  => $u['role'],
                ];
                $_SESSION['last_activity'] = time();
                // FIX 4: bersihkan captcha biar tidak dipakai-ulang
                unset($_SESSION['captcha_answer']);
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
  <form method="post" data-skip-preloader autocomplete="off">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="mb-2">
      <label class="small fw-semibold">Nama</label>
      <select class="form-select" name="user_id" required>
        <option value="">-- Pilih nama --</option>
        <?php foreach($userList as $uu): ?>
          <option value="<?= (int)$uu['id'] ?>" <?= (isset($_POST['user_id']) && (int)$_POST['user_id']===(int)$uu['id'])?'selected':'' ?>><?= htmlspecialchars($uu['nama']) ?><?= $uu['role']==='admin'?' (admin)':'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2"><label class="small fw-semibold">Password</label><input class="form-control" name="password" type="password" required></div>
    <div class="mb-3 d-flex align-items-center gap-2">
      <span class="captcha-box bg-light px-3 py-2 rounded"><?= $a ?> + <?= $b ?> = ?</span>
      <input class="form-control" name="captcha" required style="max-width:120px" autocomplete="off">
    </div>
    <button class="btn btn-primary w-100">Masuk</button>
  </form>
  <div class="text-center small mt-3">Belum punya akun? <a href="/register.php">Daftar</a></div>
</div></div>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

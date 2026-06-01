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
                $stored = '';
                if (!empty($u['password_hash'])) $stored = (string)$u['password_hash'];
                elseif (!empty($u['password']))  $stored = (string)$u['password'];

                if ($stored !== '' && (str_starts_with($stored,'$2y$')||str_starts_with($stored,'$2a$')||str_starts_with($stored,'$2b$')||str_starts_with($stored,'$argon'))) {
                    $ok = verify_password($pass, $stored);
                } elseif ($stored !== '') {
                    if (hash_equals($stored,$pass)||hash_equals($stored,md5($pass))||hash_equals($stored,sha1($pass))) $ok = true;
                    if ($ok) { try { db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",[hash_password($pass),(int)$u['id']]); } catch (Throwable $e) {} }
                }
            }
            log_login_attempt($emailKey, $ok);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id'=>(int)$u['id'],'nama'=>$u['nama'],'email'=>$u['email']??'','role'=>$u['role']];
                $_SESSION['last_activity'] = time();
                unset($_SESSION['captcha_answer']);
                header('Location: /index.php'); exit;
            }
            $err = 'Nama atau password salah.';
        }
    }
}

[$a,$b] = captcha_new();
$userList = [];
try { $userList = db_all("SELECT id, nama, role FROM users ORDER BY nama ASC"); } catch(Throwable $e) {}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0ea5e9">
<title>Masuk · HapFam SportApp</title>
<link rel="icon" href="/assets/icon-192.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* === Revisi: gradient sky→indigo selaras dengan dashboard aplikasi === */
:root{ --brand:#0ea5e9; --brand-2:#6366f1; --brand-glow:#38bdf8; --ink:#0f172a; --muted:#64748b; }
*{ box-sizing:border-box; }
html,body{ margin:0; padding:0; min-height:100dvh; }
body{
  font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;
  color:var(--ink); background:#fff;
  min-height:100dvh; display:flex; flex-direction:column;
  padding-bottom:env(safe-area-inset-bottom,0);
}
.lg-hero{
  position:relative; min-height:42dvh;
  background:
    radial-gradient(120% 80% at 10% 10%, rgba(125,211,252,.35), transparent 60%),
    linear-gradient(135deg,#0ea5e9 0%, #6366f1 100%);
  color:#fff; padding:2.4rem 1.5rem 2rem;
  border-bottom-left-radius:36px; border-bottom-right-radius:36px;
  overflow:hidden;
}
.lg-hero::after{
  content:""; position:absolute; right:-60px; top:-60px; width:240px; height:240px;
  background:radial-gradient(circle, rgba(255,255,255,.55) 0%, transparent 65%); opacity:.35; border-radius:50%;
}
.lg-logo{
  width:56px; height:56px; border-radius:16px; background:rgba(255,255,255,.14);
  display:flex; align-items:center; justify-content:center; font-size:1.8rem;
  backdrop-filter:blur(8px); margin-bottom:1.4rem;
  box-shadow:0 8px 22px -8px rgba(0,0,0,.55);
}
.lg-title{ font-size:2rem; font-weight:800; line-height:1.15; margin:0 0 .35rem; letter-spacing:-.02em; }
.lg-sub{ font-size:.98rem; opacity:.92; margin:0; max-width:32ch; }
.lg-card{
  flex:1 1 auto;
  background:#fff; margin-top:-28px; border-top-left-radius:32px; border-top-right-radius:32px;
  padding:1.6rem 1.5rem 1.6rem; position:relative; z-index:2;
  box-shadow:0 -8px 24px -16px rgba(0,0,0,.18);
  display:flex; flex-direction:column;
}
.lg-card .form-label{ font-size:.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.3rem; }
.lg-input, .lg-select{
  border:1.5px solid #e2e8f0; border-radius:14px; padding:.85rem 1rem;
  font-size:1rem; width:100%; background:#f8fafc; transition:all .18s ease;
}
.lg-input:focus, .lg-select:focus{ border-color:var(--brand-glow); background:#fff; outline:none; box-shadow:0 0 0 4px rgba(56,189,248,.22); }
.lg-cap{ display:flex; align-items:center; gap:.6rem; }
.lg-cap .q{
  background:#e0f2fe; border:1.5px dashed #7dd3fc; color:#0369a1;
  padding:.7rem 1rem; border-radius:14px; font-weight:700; font-size:1.05rem; letter-spacing:.04em; white-space:nowrap;
}
.btn-lg-primary{
  background:linear-gradient(135deg,#0ea5e9 0%, #6366f1 100%); color:#fff;
  border:0; border-radius:14px; padding:.95rem 1rem; font-weight:700; font-size:1rem; width:100%;
  box-shadow:0 10px 22px -10px rgba(14,165,233,.6);
  transition:transform .12s ease, box-shadow .18s ease;
}
.btn-lg-primary:active{ transform:translateY(1px); }
.btn-lg-primary:disabled{ opacity:.7; }
.btn-lg-outline{
  background:#fff; color:var(--brand); border:1.5px solid var(--brand);
  border-radius:14px; padding:.85rem 1rem; font-weight:700; width:100%; font-size:.98rem;
}
.btn-lg-ghost{
  background:transparent; color:var(--muted); border:0;
  border-radius:14px; padding:.85rem 1rem; font-weight:600; width:100%; font-size:.95rem;
  display:inline-flex; align-items:center; justify-content:center; gap:.4rem;
}
.btn-lg-ghost:hover{ color:var(--brand); }
.lg-divider{ display:flex; align-items:center; gap:.7rem; margin:1rem 0 .8rem; color:#94a3b8; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.08em; }
.lg-divider::before,.lg-divider::after{ content:""; flex:1; height:1px; background:#e2e8f0; }
.lg-alert{
  background:#fef2f2; color:#991b1b; border:1px solid #fecaca;
  padding:.65rem .85rem; border-radius:12px; font-size:.85rem; margin-bottom:1rem;
}
.lg-footer-note{ text-align:center; color:var(--muted); font-size:.8rem; margin-top:.4rem; }
@media (min-width:720px){
  body{ display:grid; grid-template-columns:1fr; place-items:center; background:#0f172a; }
  .lg-wrap{
    width:100%; max-width:430px; margin:1.5rem auto; background:#fff;
    border-radius:32px; overflow:hidden; box-shadow:0 30px 60px -25px rgba(0,0,0,.55);
  }
  .lg-hero{ border-radius:0; padding:2.8rem 2rem 2.2rem; }
  .lg-card{ margin-top:0; border-radius:0; padding:1.8rem 2rem 2rem; }
}
</style>
</head>
<body>
<div class="lg-wrap">
  <header class="lg-hero">
    <div class="lg-logo"><i class="bi bi-lightning-charge-fill"></i></div>
    <h1 class="lg-title">Selamat datang<br>kembali 👋</h1>
    <p class="lg-sub">Masuk untuk lanjut olahraga, jajan favorit, & ngumpul bareng komunitas HapFam.</p>
  </header>

  <section class="lg-card">
    <?php if ($err): ?>
      <div class="lg-alert"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="loginForm">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label">Nama</label>
        <select class="lg-select" name="user_id" required>
          <option value="">-- Pilih nama --</option>
          <?php foreach($userList as $uu): ?>
            <option value="<?= (int)$uu['id'] ?>" <?= (isset($_POST['user_id']) && (int)$_POST['user_id']===(int)$uu['id'])?'selected':'' ?>>
              <?= htmlspecialchars($uu['nama']) ?><?= $uu['role']==='admin'?' (admin)':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <div style="position:relative">
          <input class="lg-input" name="password" id="pw" type="password" required style="padding-right:3rem">
          <button type="button" id="togglePw" class="btn-lg-ghost" aria-label="Lihat password"
            style="position:absolute;right:.25rem;top:50%;transform:translateY(-50%);width:auto;padding:.4rem .7rem">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Captcha</label>
        <div class="lg-cap">
          <span class="q"><?= $a ?> + <?= $b ?> = ?</span>
          <input class="lg-input" name="captcha" required autocomplete="off" inputmode="numeric" placeholder="Jawab">
        </div>
      </div>

      <button type="submit" class="btn-lg-primary" id="btnSubmit">
        <i class="bi bi-box-arrow-in-right"></i> Masuk
      </button>
    </form>

    <div class="lg-divider">atau</div>

    <a href="/register.php" class="btn-lg-outline text-decoration-none d-block text-center mb-2">
      <i class="bi bi-person-plus"></i> Daftar Akun Baru
    </a>

    <a href="/index.php?guest=1" class="btn-lg-ghost text-decoration-none" id="btnGuest">
      <i class="bi bi-arrow-right-circle"></i> Lanjut ke Dashboard tanpa Login
    </a>

    <div class="lg-footer-note mt-3">
      &copy; 2026 HapFam SportApp · By Yuk-Mari CyberLab
    </div>
  </section>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function(){
  var pw = document.getElementById('pw');
  var ic = this.querySelector('i');
  if (pw.type === 'password'){ pw.type='text'; ic.className='bi bi-eye-slash'; }
  else { pw.type='password'; ic.className='bi bi-eye'; }
});
document.getElementById('loginForm').addEventListener('submit', function(){
  var b = document.getElementById('btnSubmit');
  b.disabled = true;
  b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses…';
});
// Tombol "Daftar Akun Baru" & "Lanjut tanpa Login": tampilkan efek loading
// dengan ikon spinner + tulisan "Memproses…" sebelum berpindah halaman.
(function(){
  function attachLoader(el, label){
    if (!el) return;
    el.addEventListener('click', function(ev){
      // biarkan navigasi default berjalan, hanya ubah tampilan
      el.style.pointerEvents = 'none';
      el.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> '+label;
    });
  }
  attachLoader(document.querySelector('a[href="/register.php"]'), 'Memproses…');
  attachLoader(document.getElementById('btnGuest'), 'Memproses…');
})();
</script>
</body>
</html>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
$pageTitle = 'Login';
$err = null;
if (!empty($_GET['expired'])) $err = 'Sesi habis. Silakan login kembali.';

/* === Revisi: alur splash + onboarding sebelum login (sekali pakai per device) === */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_COOKIE['hf_onboarded']) && empty($_GET['skip_intro'])) {
    header('Location: /splash.php'); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    // Revisi R5 (Juli 2026) — Login pakai USERNAME (bukan lagi dropdown user_id)
    $uname = trim((string)($_POST['username'] ?? ''));
    $pass  = trim((string)($_POST['password'] ?? ''));
    $cap   = trim((string)($_POST['captcha'] ?? ''));
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0';

    if (!rate_limit("login:$ip", 10, 60)) {
        http_response_code(429);
        $err = 'Terlalu banyak percobaan. Coba lagi sebentar.';
    } elseif (!captcha_check($cap)) {
        $err = 'Captcha salah.';
    } elseif ($uname === '') {
        $err = 'Username wajib diisi.';
    } else {
        // Cari via username (case-insensitive); fallback ke email agar akun lama tetap bisa masuk.
        $u = db_one("SELECT * FROM users WHERE LOWER(username)=LOWER($1) LIMIT 1", [$uname]);
        if (!$u) {
            $u = db_one("SELECT * FROM users WHERE LOWER(email)=LOWER($1) LIMIT 1", [$uname]);
        }
        $emailKey = strtolower((string)($u['email'] ?? ('u-'.$uname)));

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
                $aktifRaw = $u['aktif'] ?? null;
                $aktifBool = ($aktifRaw === null) ? true
                    : in_array(strtolower((string)$aktifRaw), ['1','t','true','y','yes'], true);
                if (!$aktifBool && ($u['role'] ?? '') !== 'admin') {
                    $catatan = trim((string)($u['nonaktif_catatan'] ?? ''));
                    $err = 'Akun Anda berstatus NON-AKTIF dan tidak dapat masuk.'
                         . ($catatan !== '' ? ' Catatan admin: '.$catatan : '')
                         . ' Hubungi admin untuk mengaktifkan kembali.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id'=>(int)$u['id'],'nama'=>$u['nama'],'email'=>$u['email']??'','role'=>$u['role']];
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['captcha_answer']);
                    app_login_cookie_set($_SESSION['user']);
                    session_write_close();
                    header('Location: /index.php'); exit;
                }
            } else {
                $err = 'Username atau password salah.';
            }
        }
    }
}

[$a,$b] = captcha_new();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0ea5e9">
<title>Masuk · KawanKeringat — AI Sport & Healthy Lifestyle Super App</title>
<meta name="description" content="KawanKeringat: AI Sport & Healthy Lifestyle Super App untuk Indonesia — menggabungkan olahraga, komunitas, AI, kesehatan, dan aktivitas outdoor dalam satu aplikasi.">
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
/* Revisi R8 Juli 2026: hero slideshow 5 gambar dengan efek fade. */
.lg-hero{
  position:relative; min-height:42dvh;
  background: linear-gradient(135deg,#0ea5e9 0%, #6366f1 100%);
  color:#fff; padding:2.4rem 1.5rem 2rem;
  border-bottom-left-radius:36px; border-bottom-right-radius:36px;
  overflow:hidden; isolation:isolate;
}
.lg-hero-bg{
  position:absolute; inset:0; z-index:-2; overflow:hidden;
  border-bottom-left-radius:36px; border-bottom-right-radius:36px;
}
.lg-hero-bg .slide{
  position:absolute; inset:0; background-size:cover; background-position:center;
  opacity:0; transition:opacity 1.6s ease-in-out;
  animation:lgFade 25s infinite;
}
.lg-hero-bg .slide.s1{ background-image:url('/assets/img/sport-auth-hero1.png'); animation-delay:0s; }
.lg-hero-bg .slide.s2{ background-image:url('/assets/img/sport-auth-hero2.png'); animation-delay:5s; }
.lg-hero-bg .slide.s3{ background-image:url('/assets/img/sport-auth-hero3.png'); animation-delay:10s; }
.lg-hero-bg .slide.s4{ background-image:url('/assets/img/sport-auth-hero4.png'); animation-delay:15s; }
.lg-hero-bg .slide.s5{ background-image:url('/assets/img/sport-auth-hero5.png'); animation-delay:20s; }
@keyframes lgFade{
  0%   { opacity:0; }
  4%   { opacity:1; }
  20%  { opacity:1; }
  24%  { opacity:0; }
  100% { opacity:0; }
}
.lg-hero::after{
  content:""; position:absolute; inset:0; z-index:-1;
  background:linear-gradient(180deg, rgba(15,23,42,.35) 0%, rgba(15,23,42,.78) 100%);
  border-bottom-left-radius:36px; border-bottom-right-radius:36px;
}
.lg-hero .lg-glow{
  content:""; position:absolute; left:-40px; bottom:-40px; width:200px; height:200px;
  background:radial-gradient(circle, rgba(14,165,233,.45) 0%, transparent 65%); opacity:.4; border-radius:50%;
  pointer-events:none;
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
    <div class="lg-hero-bg" aria-hidden="true">
      <div class="slide s1"></div><div class="slide s2"></div><div class="slide s3"></div><div class="slide s4"></div><div class="slide s5"></div>
    </div>
    <span class="lg-glow" aria-hidden="true"></span>
    <div class="lg-logo"><i class="bi bi-lightning-charge-fill"></i></div>
    <h1 class="lg-title">KawanKeringat 🏃‍♂️⚡</h1>
    <p class="lg-sub"><strong>AI Sport &amp; Healthy Lifestyle Super App</strong> untuk Indonesia — olahraga, komunitas, AI, kesehatan, dan aktivitas outdoor dalam satu aplikasi.</p>
    <div style="display:flex;gap:.4rem;margin-top:1rem;flex-wrap:wrap;">
      <span style="background:rgba(255,255,255,.18);backdrop-filter:blur(6px);padding:.35rem .75rem;border-radius:999px;font-size:.78rem;font-weight:600;"><i class="bi bi-trophy-fill"></i> Event 2026</span>
      <span style="background:rgba(255,255,255,.18);backdrop-filter:blur(6px);padding:.35rem .75rem;border-radius:999px;font-size:.78rem;font-weight:600;"><i class="bi bi-stopwatch-fill"></i> Run Tracker</span>
      <span style="background:rgba(255,255,255,.18);backdrop-filter:blur(6px);padding:.35rem .75rem;border-radius:999px;font-size:.78rem;font-weight:600;"><i class="bi bi-heart-pulse-fill"></i> Sehat</span>
    </div>
  </header>

  <section class="lg-card">
    <?php if ($err): ?>
      <div class="lg-alert"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="loginForm">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label">Username</label>
        <input class="lg-input" name="username" type="text" required autocomplete="username"
               placeholder="Masukkan username"
               value="<?= htmlspecialchars((string)($_POST['username'] ?? '')) ?>">
        <div class="form-text small">Gunakan <b>username</b> akun kamu.</div>
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

      
      <!-- Revisi 2 Jun 2026: persetujuan kebijakan privasi (UU PDP) -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="pdp" required>
        <label class="form-check-label small" for="pdp">
          Saya telah membaca &amp; menyetujui
          <a href="#" class="text-decoration-underline" data-privasi-open>Kebijakan Privasi &amp; UU PDP</a>.
        </label>
      </div>
      <button type="submit" class="btn-lg-primary" id="btnSubmit">
        <i class="bi bi-box-arrow-in-right"></i> Masuk
      </button>
    </form>

    <div class="lg-divider">atau</div>

    <a href="/register.php" class="btn-lg-outline text-decoration-none d-block text-center mb-2">
      <i class="bi bi-person-plus"></i> Daftar Akun Baru
    </a>

    <!-- Revisi: Tombol Pintasan ke HP (PWA install) di halaman login -->
    <button type="button" id="installBtn" class="btn-lg-outline mt-2" data-sfx="tap">
      <i class="bi bi-phone"></i> Tambahkan Pintasan ke HP kamu
    </button>

    <div class="lg-footer-note mt-3">
      &copy; 2026 KawanKeringat · By Yuk-Mari CyberLab
    </div>
  </section>
</div>

<!-- Revisi 1 Jun 2026: efek suara saat klik & submit -->
<script src="/assets/js/sfx.js?v=1jun2026"></script>
<script>
// Tagging tombol untuk SFX
document.getElementById('togglePw').setAttribute('data-sfx','tap');
document.getElementById('btnSubmit').setAttribute('data-sfx','tap');
var _btnGuest = document.getElementById('btnGuest'); if (_btnGuest) _btnGuest.setAttribute('data-sfx','tap');
document.getElementById('loginForm').addEventListener('submit', function(){
  try { window.SFX && SFX.notify(); } catch(e){}
});
<?php if ($err): ?>
window.addEventListener('load', function(){ try { window.SFX && SFX.error(); } catch(e){} });
<?php endif; ?>
</script>
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
<!-- Revisi R8 Juli 2026: Popup PWA install (modal) — URL website disembunyikan -->
<script>
let _deferredInstall = null;
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); _deferredInstall = e; });
document.addEventListener('DOMContentLoaded', () => {
  const _installBtn = document.getElementById('installBtn');
  if (!_installBtn) return;
  _installBtn.addEventListener('click', async () => {
    if (_deferredInstall) { _deferredInstall.prompt(); _deferredInstall = null; }
    else { const m = document.getElementById('pwaInstallModal'); if (m) new bootstrap.Modal(m).show(); }
  });
});
</script>

<div class="modal fade" id="pwaInstallModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:22px;overflow:hidden;border:0;box-shadow:0 20px 40px -18px rgba(15,23,42,.4)">
      <div class="modal-header border-0 pb-1" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;">
        <div class="d-flex align-items-center gap-2">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.4rem;"><i class="bi bi-phone-fill"></i></div>
          <div>
            <h5 class="modal-title mb-0" style="font-weight:800;letter-spacing:-.01em;">Pasang ke Layar Utama</h5>
            <div style="font-size:.8rem;opacity:.88;">KawanKeringat siap dipakai seperti aplikasi</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pt-3">
        <p class="small text-muted mb-3">Ikuti langkah di bawah agar ikon aplikasi muncul di layar utama HP kamu.</p>
        <div class="mb-3 p-3" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:14px;">
          <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-android2 text-success fs-4"></i><strong>Android · Chrome / Edge</strong></div>
          <ol class="small mb-0 ps-3">
            <li>Ketuk tombol menu <i class="bi bi-three-dots-vertical"></i> di pojok kanan atas browser.</li>
            <li>Pilih <strong>“Tambahkan ke Layar Utama”</strong> atau <strong>“Install app”</strong>.</li>
            <li>Konfirmasi. Ikon KawanKeringat akan muncul di home screen.</li>
          </ol>
        </div>
        <div class="mb-1 p-3" style="background:#fdf4ff;border:1px solid #f5d0fe;border-radius:14px;">
          <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-apple text-dark fs-4"></i><strong>iPhone / iPad · Safari</strong></div>
          <ol class="small mb-0 ps-3">
            <li>Ketuk tombol <strong>Bagikan</strong> <i class="bi bi-box-arrow-up"></i> di bagian bawah Safari.</li>
            <li>Gulir dan pilih <strong>“Tambahkan ke Layar Utama”</strong>.</li>
            <li>Ketuk <strong>Tambah</strong>. Ikon aplikasi akan muncul.</li>
          </ol>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal" style="border-radius:12px;padding:.7rem 1rem;font-weight:700;background:linear-gradient(135deg,#0ea5e9,#6366f1);border:0;">
          <i class="bi bi-check2-circle me-1"></i> Mengerti
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Revisi 2 Juli 2026 #6: Popup Kebijakan Privasi & UU PDP -->
<div class="modal fade" id="privasiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:0">
        <h5 class="modal-title"><i class="bi bi-shield-check"></i> Kebijakan Privasi &amp; UU PDP</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" style="min-height:60vh">
        <iframe id="privasiFrame" src="about:blank" style="width:100%;height:65vh;border:0;background:#fff"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><i class="bi bi-check2"></i> Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var modalEl=document.getElementById('privasiModal');
  var frame=document.getElementById('privasiFrame');
  var modal=new bootstrap.Modal(modalEl);
  document.querySelectorAll('[data-privasi-open]').forEach(function(a){
    a.addEventListener('click',function(e){
      e.preventDefault();
      frame.src='/privasi.php?embed=1&t='+Date.now();
      modal.show();
    });
  });
})();
</script>

</body>
</html>

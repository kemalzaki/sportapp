<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
$pageTitle = 'Daftar';
$err = null;

$ADMIN_WA_FIRDAM = getenv('ADMIN_WA_FIRDAM') ?: '6281386369207';

try {
    @pg_query(db(), "ALTER TABLE users ADD COLUMN IF NOT EXISTS kode_referal VARCHAR(32)");
    @pg_query(db(), "ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by_code VARCHAR(32)");
    @pg_query(db(), "CREATE UNIQUE INDEX IF NOT EXISTS users_kode_referal_uidx ON users(kode_referal) WHERE kode_referal IS NOT NULL");
} catch (Throwable $e) {}
require_once __DIR__.'/includes/islami_migrations.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('register:'.($_SERVER['REMOTE_ADDR'] ?? '0'), 30, 600);
    $nama = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    $jk   = $_POST['jenis_kelamin'] ?? '';
    $wa   = preg_replace('/[^0-9+]/','', trim($_POST['nomor_wa'] ?? ''));
    $ref  = strtoupper(preg_replace('/[^A-Z0-9_-]/i','', trim($_POST['kode_referal'] ?? '')));
    if (strlen($nama) < 2 || strlen($nama) > 80) $err = 'Nama 2-80 karakter.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email tidak valid.';
    elseif (strlen($pass) < 8) $err = 'Password minimal 8 karakter.';
    elseif (!in_array($jk, ['L','P'], true)) $err = 'Jenis kelamin wajib dipilih.';
    elseif (strlen($wa) < 8 || strlen($wa) > 20) $err = 'Nomor WhatsApp tidak valid.';
    elseif ($ref === '' || strlen($ref) < 3 || strlen($ref) > 32) $err = 'Kode referal wajib diisi (3-32 karakter). Hubungi admin Firdam via WhatsApp jika belum punya.';
    elseif (db_one("SELECT id FROM users WHERE LOWER(email)=$1", [$email])) $err = 'Email sudah terdaftar.';
    elseif (($_validRef = db_one("SELECT * FROM referal_codes WHERE UPPER(kode)=$1 AND aktif=1 AND (expired_at IS NULL OR expired_at >= CURRENT_DATE) AND (max_pakai IS NULL OR jumlah_terpakai < max_pakai)", [$ref])) === null
            && (int)db_val("SELECT COUNT(*) FROM referal_codes")>0) $err='Kode referal tidak valid / sudah habis kuota / kadaluarsa.';
    else {
        $hash = hash_password($pass);
        try {
            db_exec("INSERT INTO users(nama,email,password_hash,role,jenis_kelamin,nomor_wa,referred_by_code) VALUES($1,$2,$3,'member',$4,$5,$6)",
                [$nama, $email, $hash, $jk, $wa, $ref]);
            header('Location: /login.php'); exit;
        } catch (Throwable $e) {
            try {
                db_exec("INSERT INTO users(nama,email,password,role,jenis_kelamin,nomor_wa,referred_by_code) VALUES($1,$2,$3,'member',$4,$5,$6)",
                    [$nama,$email,$hash,$jk,$wa,$ref]);
                header('Location: /login.php'); exit;
            } catch (Throwable $e2) { $err = 'Pendaftaran gagal: '.$e2->getMessage(); }
        }
    }
}
$csrf = csrf_token();
$waLink = 'https://wa.me/'.preg_replace('/\D+/','',$ADMIN_WA_FIRDAM).'?text='.rawurlencode('Halo Admin Firdam, saya ingin mendaftar di HapFam SportApp. Mohon kode referalnya. Terima kasih.');
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1d3a">
<title>Daftar · HapFam SportApp</title>
<link rel="icon" href="/assets/icon-192.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{ --brand:#1e3a8a; --brand-2:#0b1d3a; --brand-glow:#3b82f6; --ink:#0f172a; --muted:#64748b; }
*{ box-sizing:border-box; }
html,body{ margin:0; padding:0; min-height:100dvh; }
body{
  font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;
  color:var(--ink); background:#fff;
  min-height:100dvh; display:flex; flex-direction:column;
  padding-bottom:env(safe-area-inset-bottom,0);
}
.lg-hero{
  position:relative; min-height:32dvh;
  background:
    radial-gradient(120% 80% at 10% 10%, rgba(59,130,246,.28), transparent 60%),
    linear-gradient(135deg,#0b1d3a 0%, #1e3a8a 55%, #0f172a 100%);
  color:#fff; padding:2.2rem 1.5rem 1.8rem;
  border-bottom-left-radius:36px; border-bottom-right-radius:36px;
  overflow:hidden;
}
.lg-hero::after{
  content:""; position:absolute; right:-60px; top:-60px; width:240px; height:240px;
  background:radial-gradient(circle, rgba(59,130,246,.6) 0%, transparent 65%); opacity:.35; border-radius:50%;
}
.lg-logo{
  width:56px; height:56px; border-radius:16px; background:rgba(255,255,255,.14);
  display:flex; align-items:center; justify-content:center; font-size:1.8rem;
  backdrop-filter:blur(8px); margin-bottom:1rem;
  box-shadow:0 8px 22px -8px rgba(0,0,0,.55);
}
.lg-title{ font-size:1.7rem; font-weight:800; line-height:1.15; margin:0 0 .35rem; letter-spacing:-.02em; }
.lg-sub{ font-size:.95rem; opacity:.92; margin:0; max-width:34ch; }
.lg-card{
  flex:1 1 auto;
  background:#fff; margin-top:-28px; border-top-left-radius:32px; border-top-right-radius:32px;
  padding:1.6rem 1.5rem 1.6rem; position:relative; z-index:2;
  box-shadow:0 -8px 24px -16px rgba(0,0,0,.18);
  display:flex; flex-direction:column;
}
.lg-card .form-label{ font-size:.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.3rem; }
.lg-input, .lg-select{
  border:1.5px solid #e2e8f0; border-radius:14px; padding:.8rem 1rem;
  font-size:.98rem; width:100%; background:#f8fafc; transition:all .18s ease;
}
.lg-input:focus, .lg-select:focus{ border-color:var(--brand-glow); background:#fff; outline:none; box-shadow:0 0 0 4px rgba(59,130,246,.18); }
.lg-input-group{ display:flex; gap:.5rem; }
.lg-input-group .lg-input{ flex:1 1 auto; }
.lg-input-group .lg-wa-btn{
  background:#16a34a; color:#fff; border:0; border-radius:14px; padding:.6rem .9rem;
  font-weight:600; font-size:.85rem; display:inline-flex; align-items:center; gap:.3rem; text-decoration:none;
  box-shadow:0 6px 16px -8px rgba(22,163,74,.6);
}
.btn-lg-primary{
  background:linear-gradient(135deg,var(--brand-2),var(--brand) 55%,var(--brand-glow)); color:#fff;
  border:0; border-radius:14px; padding:.95rem 1rem; font-weight:700; font-size:1rem; width:100%;
  box-shadow:0 10px 22px -10px rgba(30,58,138,.65);
  transition:transform .12s ease, box-shadow .18s ease;
}
.btn-lg-primary:active{ transform:translateY(1px); }
.btn-lg-primary:disabled{ opacity:.7; }
.lg-divider{ display:flex; align-items:center; gap:.7rem; margin:1rem 0 .8rem; color:#94a3b8; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.08em; }
.lg-divider::before,.lg-divider::after{ content:""; flex:1; height:1px; background:#e2e8f0; }
.lg-alert{
  background:#fef2f2; color:#991b1b; border:1px solid #fecaca;
  padding:.65rem .85rem; border-radius:12px; font-size:.85rem; margin-bottom:1rem;
}
.lg-hint{ font-size:.78rem; color:var(--muted); margin-top:.3rem; }
.lg-footer-note{ text-align:center; color:var(--muted); font-size:.8rem; margin-top:.4rem; }
@media (min-width:720px){
  body{ display:grid; grid-template-columns:1fr; place-items:center; background:#0f172a; }
  .lg-wrap{
    width:100%; max-width:460px; margin:1.5rem auto; background:#fff;
    border-radius:32px; overflow:hidden; box-shadow:0 30px 60px -25px rgba(0,0,0,.55);
  }
  .lg-hero{ border-radius:0; padding:2.4rem 2rem 2rem; }
  .lg-card{ margin-top:0; border-radius:0; padding:1.8rem 2rem 2rem; }
}
</style>
</head>
<body>
<div class="lg-wrap">
  <header class="lg-hero">
    <div class="lg-logo"><i class="bi bi-person-plus-fill"></i></div>
    <h1 class="lg-title">Buat akun baru 🚀</h1>
    <p class="lg-sub">Daftar untuk mulai olahraga, pesan jajan, & gabung komunitas HapFam.</p>
  </header>

  <section class="lg-card">
    <?php if ($err): ?>
      <div class="lg-alert"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="regForm">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label">Nama Lengkap</label>
        <input class="lg-input" name="nama" required maxlength="80" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" placeholder="cth: Andi Saputra">
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="lg-input" name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="nama@email.com">
      </div>

      <div class="mb-3">
        <label class="form-label">Jenis Kelamin</label>
        <select class="lg-select" name="jenis_kelamin" required>
          <option value="">— pilih —</option>
          <option value="L" <?= (($_POST['jenis_kelamin']??'')==='L'?'selected':'') ?>>Laki-laki</option>
          <option value="P" <?= (($_POST['jenis_kelamin']??'')==='P'?'selected':'') ?>>Perempuan</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><i class="bi bi-whatsapp text-success"></i> Nomor WhatsApp</label>
        <input class="lg-input" name="nomor_wa" required placeholder="cth: 081234567890" value="<?= htmlspecialchars($_POST['nomor_wa'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label"><i class="bi bi-ticket-perforated"></i> Kode Referal <span class="text-danger">*</span></label>
        <div class="lg-input-group">
          <input class="lg-input" name="kode_referal" required maxlength="32" placeholder="cth: FIRDAM2026"
                 value="<?= htmlspecialchars($_POST['kode_referal'] ?? '') ?>" style="text-transform:uppercase">
          <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener" class="lg-wa-btn" title="Tanya admin Firdam via WhatsApp">
            <i class="bi bi-whatsapp"></i> Tanya
          </a>
        </div>
        <div class="lg-hint">Belum punya kode? Klik <strong>Tanya</strong> untuk minta ke admin Firdam via WhatsApp.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Password <span class="text-muted" style="text-transform:none">(min. 8 karakter)</span></label>
        <div style="position:relative">
          <input class="lg-input" name="password" id="pw" type="password" minlength="8" required style="padding-right:3rem">
          <button type="button" id="togglePw" aria-label="Lihat password"
            style="position:absolute;right:.25rem;top:50%;transform:translateY(-50%);background:transparent;border:0;color:#64748b;padding:.4rem .7rem;border-radius:10px">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-lg-primary" id="btnSubmit">
        <i class="bi bi-person-check"></i> Daftar
      </button>
    </form>

    <div class="lg-divider">atau</div>

    <div class="lg-footer-note">
      Sudah punya akun? <a href="/login.php" style="color:var(--brand);font-weight:700;text-decoration:none">Masuk di sini</a>
    </div>
    <div class="lg-footer-note mt-2">
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
document.getElementById('regForm').addEventListener('submit', function(){
  var b = document.getElementById('btnSubmit');
  b.disabled = true;
  b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mendaftarkan…';
});
</script>
</body>
</html>

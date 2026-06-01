<?php
// Revisi 1 Jun 2026 — Splash Screen
// Tampil di kunjungan pertama device sebelum onboarding & login.
require __DIR__.'/includes/security.php';
send_security_headers();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0ea5e9">
<title>HapFam SportApp</title>
<link rel="icon" href="/assets/icon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100dvh;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;
  background:radial-gradient(120% 80% at 10% 10%, rgba(125,211,252,.55), transparent 60%),
             linear-gradient(135deg,#0ea5e9 0%, #6366f1 60%, #4338ca 100%);
  color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:2rem;overflow:hidden;position:relative;}
body::before,body::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.08);}
body::before{width:280px;height:280px;top:-80px;left:-80px;}
body::after{width:340px;height:340px;bottom:-120px;right:-120px;}
.logo{width:96px;height:96px;border-radius:28px;background:rgba(255,255,255,.18);backdrop-filter:blur(10px);
  display:flex;align-items:center;justify-content:center;font-size:3rem;margin-bottom:1.5rem;
  box-shadow:0 16px 40px -10px rgba(0,0,0,.45);animation:pop .7s ease both;}
@keyframes pop{from{transform:scale(.6);opacity:0;}to{transform:scale(1);opacity:1;}}
h1{font-size:2.1rem;font-weight:800;letter-spacing:-.02em;margin-bottom:.4rem;animation:fade .8s .15s both;}
p{font-size:.98rem;opacity:.92;max-width:30ch;animation:fade .8s .3s both;}
@keyframes fade{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;}}
.spinner{margin-top:2.2rem;width:36px;height:36px;border:3px solid rgba(255,255,255,.3);
  border-top-color:#fff;border-radius:50%;animation:spin .9s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.brand-foot{position:absolute;bottom:1.4rem;font-size:.78rem;opacity:.75;letter-spacing:.04em;}
</style>
</head>
<body>
  <div class="logo">⚡</div>
  <h1>HapFam SportApp</h1>
  <p>Olahraga · Komunitas · Jajanan · Islami</p>
  <div class="spinner" aria-hidden="true"></div>
  <div class="brand-foot">By Yuk-Mari CyberLab</div>
<script>
  setTimeout(function(){ location.href = '/onboarding.php'; }, 1800);
</script>
</body>
</html>

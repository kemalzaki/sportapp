<?php
// Revisi 6 Jun 2026 — Splash Screen baru (logo PNG modern + animasi ring)
require __DIR__.'/includes/security.php';
send_security_headers();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4338ca">
<title>HapFam SportApp</title>
<link rel="icon" href="/assets/icon-192.png">
<link rel="preload" as="image" href="/assets/icon-512.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100dvh;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;
  background:
    radial-gradient(120% 80% at 90% 10%, rgba(236,72,153,.35), transparent 60%),
    radial-gradient(120% 80% at 10% 90%, rgba(56,189,248,.45), transparent 60%),
    linear-gradient(135deg,#0f172a 0%, #1e293b 40%, #4338ca 100%);
  color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:2rem;overflow:hidden;position:relative;}
.glow{position:absolute;border-radius:50%;filter:blur(60px);opacity:.6;animation:float 6s ease-in-out infinite;}
.g1{width:260px;height:260px;background:#06b6d4;top:-60px;left:-60px;}
.g2{width:320px;height:320px;background:#a855f7;bottom:-90px;right:-90px;animation-delay:-3s;}
@keyframes float{50%{transform:translateY(20px);}}
.logo-wrap{position:relative;width:160px;height:160px;display:flex;align-items:center;justify-content:center;margin-bottom:1.6rem;}
.ring{position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.25);}
.ring.r2{inset:-14px;border-style:dashed;animation:spin 14s linear infinite;}
.ring.r3{inset:-28px;border-color:rgba(255,255,255,.12);}
@keyframes spin{to{transform:rotate(360deg);}}
.logo{width:128px;height:128px;border-radius:34px;
  box-shadow:0 30px 60px -20px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.15) inset;
  animation:pop .8s cubic-bezier(.2,.9,.3,1.4) both;}
@keyframes pop{from{transform:scale(.55);opacity:0;}to{transform:scale(1);opacity:1;}}
h1{font-size:2.2rem;font-weight:800;letter-spacing:-.02em;margin-bottom:.4rem;
  background:linear-gradient(90deg,#fff,#bae6fd);-webkit-background-clip:text;background-clip:text;color:transparent;
  animation:fade .8s .15s both;}
p.tag{font-size:1rem;opacity:.92;max-width:30ch;animation:fade .8s .3s both;}
@keyframes fade{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:none;}}
.bar{margin-top:2.4rem;width:180px;height:4px;border-radius:99px;background:rgba(255,255,255,.18);overflow:hidden;}
.bar > i{display:block;height:100%;width:30%;background:linear-gradient(90deg,#38bdf8,#a855f7);border-radius:99px;animation:slide 1.5s ease-in-out infinite;}
@keyframes slide{0%{transform:translateX(-120%);}100%{transform:translateX(420%);}}
.brand-foot{position:absolute;bottom:1.4rem;font-size:.78rem;opacity:.75;letter-spacing:.06em;}
</style>
</head>
<body>
  <div class="glow g1"></div><div class="glow g2"></div>
  <div class="logo-wrap">
    <div class="ring r3"></div><div class="ring r2"></div><div class="ring"></div>
    <img class="logo" src="/assets/icon-512.png" alt="HapFam SportApp">
  </div>
  <h1>HapFam SportApp</h1>
  <p class="tag">Olahraga • Komunitas • Jajanan • Islami</p>
  <div class="bar"><i></i></div>
  <div class="brand-foot">By Yuk-Mari CyberLab</div>
<script>
  setTimeout(function(){ location.href = '/onboarding.php'; }, 1800);
</script>
</body>
</html>
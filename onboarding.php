<?php
// Revisi — Onboarding: memperkenalkan KawanKeringat sebagai AI Sport & Healthy Lifestyle Super App
require __DIR__.'/includes/security.php';
send_security_headers();
if (!empty($_GET['done'])) {
    setcookie('hf_onboarded', '1', [
        'expires'  => time() + 60*60*24*365,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    header('Location: /login.php?skip_intro=1'); exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0ea5e9">
<title>Selamat Datang · KawanKeringat</title>
<meta name="description" content="KawanKeringat: AI Sport & Healthy Lifestyle Super App untuk Indonesia — olahraga, komunitas, AI, kesehatan, dan outdoor dalam satu aplikasi.">
<link rel="icon" href="/assets/icon-192.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100dvh;overflow:hidden;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#0f172a;background:#fff;
  display:flex;flex-direction:column;}
.slides{flex:1 1 auto;display:flex;width:400%;height:100%;transition:transform .4s ease;}
.slide{width:25%;height:100%;display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:2.2rem 1.6rem 1rem;text-align:center;}
.illu{width:74%;max-width:320px;aspect-ratio:1/1;border-radius:42px;display:flex;
  align-items:center;justify-content:center;margin-bottom:1.6rem;font-size:5.2rem;color:#fff;
  box-shadow:0 24px 50px -20px rgba(15,23,42,.35);}
.illu.a{background:linear-gradient(135deg,#0ea5e9,#6366f1);}
.illu.b{background:linear-gradient(135deg,#f59e0b,#ef4444);}
.illu.c{background:linear-gradient(135deg,#8b5cf6,#06b6d4);}
.illu.d{background:linear-gradient(135deg,#10b981,#0ea5e9);}
.illu i{filter:drop-shadow(0 6px 14px rgba(0,0,0,.25));}
h2{font-size:1.55rem;font-weight:800;letter-spacing:-.02em;margin-bottom:.5rem;}
p{font-size:.98rem;color:#64748b;max-width:36ch;line-height:1.55;}
.foot{padding:1.1rem 1.6rem 1.5rem;display:flex;align-items:center;gap:.8rem;}
.dots{flex:1;display:flex;gap:.4rem;justify-content:center;}
.dot{width:8px;height:8px;border-radius:50%;background:#cbd5e1;transition:all .25s;}
.dot.active{background:#0ea5e9;width:22px;border-radius:6px;}
.btn-skip{background:transparent;border:0;color:#64748b;font-weight:600;font-size:.9rem;
  padding:.7rem 1rem;cursor:pointer;}
.btn-next{background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:0;border-radius:14px;
  padding:.85rem 1.6rem;font-weight:700;font-size:.95rem;cursor:pointer;display:inline-flex;
  align-items:center;gap:.45rem;box-shadow:0 10px 22px -10px rgba(14,165,233,.6);}
.btn-next:active{transform:translateY(1px);}
@media (min-width:720px){
  body{align-items:center;justify-content:center;background:#0f172a;}
  .frame{width:100%;max-width:430px;height:84dvh;max-height:780px;background:#fff;border-radius:32px;
    overflow:hidden;box-shadow:0 30px 60px -25px rgba(0,0,0,.6);display:flex;flex-direction:column;}
  .slides{height:auto;flex:1;}
}
</style>
</head>
<body>
<div class="frame" id="frame">
  <div class="slides" id="slides">
    <section class="slide">
      <div class="illu a"><i class="bi bi-stars"></i></div>
      <h2>KawanKeringat</h2>
      <p><strong>AI Sport &amp; Healthy Lifestyle Super App</strong> untuk Indonesia — menggabungkan olahraga, komunitas, AI, kesehatan, dan aktivitas outdoor dalam <em>satu aplikasi</em>.</p>
    </section>
    <section class="slide">
      <div class="illu b"><i class="bi bi-lightning-charge-fill"></i></div>
      <h2>Olahraga &amp; Outdoor</h2>
      <p>Catat lari, hiking, gowes, badminton — lengkap dengan Live GPS, jalur, flyover 3D, dan tempat olahraga terbaru.</p>
    </section>
    <section class="slide">
      <div class="illu c"><i class="bi bi-cpu-fill"></i></div>
      <h2>Asisten AI Kesehatan</h2>
      <p>AI Coach, kalori olahraga &amp; makanan, cedera, pertolongan pertama, survival mode, sampai smart search fasilitas kesehatan.</p>
    </section>
    <section class="slide">
      <div class="illu d"><i class="bi bi-people-fill"></i></div>
      <h2>Komunitas Aktif</h2>
      <p>Story, feed, chat, event, dan manajemen komunitas — tumbuh bareng lari, hiking, badminton, gowes, dan banyak lagi.</p>
    </section>
  </div>
  <div class="foot">
    <button class="btn-skip" id="btnSkip">Lewati</button>
    <div class="dots">
      <span class="dot active" data-i="0"></span>
      <span class="dot" data-i="1"></span>
      <span class="dot" data-i="2"></span>
      <span class="dot" data-i="3"></span>
    </div>
    <button class="btn-next" id="btnNext">Lanjut <i class="bi bi-arrow-right"></i></button>
  </div>
</div>
<script>
(function(){
  var TOTAL = 4;
  var idx = 0;
  var slides = document.getElementById('slides');
  var dots = document.querySelectorAll('.dot');
  var btnNext = document.getElementById('btnNext');
  var btnSkip = document.getElementById('btnSkip');
  function go(i){
    idx = Math.max(0, Math.min(TOTAL-1, i));
    slides.style.transform = 'translateX(-'+(idx*(100/TOTAL))+'%)';
    dots.forEach(function(d,k){ d.classList.toggle('active', k===idx); });
    btnNext.innerHTML = (idx===TOTAL-1) ? 'Mulai <i class="bi bi-check2-circle"></i>' : 'Lanjut <i class="bi bi-arrow-right"></i>';
  }
  btnNext.addEventListener('click', function(){
    if (idx >= TOTAL-1) { location.href = '/onboarding.php?done=1'; return; }
    go(idx+1);
  });
  btnSkip.addEventListener('click', function(){ location.href = '/onboarding.php?done=1'; });
  dots.forEach(function(d){ d.addEventListener('click', function(){ go(parseInt(d.dataset.i,10)); }); });
  var sx = 0, dx = 0;
  slides.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; dx = 0; }, {passive:true});
  slides.addEventListener('touchmove',  function(e){ dx = e.touches[0].clientX - sx; }, {passive:true});
  slides.addEventListener('touchend',   function(){
    if (Math.abs(dx) > 60) go(dx < 0 ? idx+1 : idx-1);
  });
})();
</script>
</body>
</html>

<?php
// Revisi 1 Jun 2026 — Skeleton loading global (mobile-app feel).
// Disisipkan via includes/header.php agar semua halaman ikut otomatis.
?>
<style>
/* === Skeleton shimmer (global) === */
.sk{position:relative;overflow:hidden;background:#e5e7eb;border-radius:8px;display:block;}
[data-bs-theme=dark] .sk{background:#1f2937;}
.sk::after{content:"";position:absolute;inset:0;transform:translateX(-100%);
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);
  animation:hfShimmer 1.25s infinite;}
[data-bs-theme=dark] .sk::after{background:linear-gradient(90deg,transparent,rgba(255,255,255,.08),transparent);}
@keyframes hfShimmer{100%{transform:translateX(100%);}}
.sk-line{height:.85rem;margin:.4rem 0;border-radius:6px;}
.sk-line.lg{height:1.1rem;}
.sk-line.sm{height:.65rem;}
.sk-line.w-25{width:25%;} .sk-line.w-40{width:40%;} .sk-line.w-50{width:50%;}
.sk-line.w-60{width:60%;} .sk-line.w-75{width:75%;} .sk-line.w-90{width:90%;}
.sk-circle{width:42px;height:42px;border-radius:50%;}
.sk-thumb{width:100%;aspect-ratio:16/10;border-radius:12px;}
.sk-card{padding:.9rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff;margin-bottom:.75rem;}
[data-bs-theme=dark] .sk-card{background:#0f172a;border-color:#1f2937;}
.sk-row{display:flex;gap:.7rem;align-items:center;}
.sk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.7rem;}
/* Page-level skeleton overlay yang muncul saat navigasi keluar / form submit */
#hfPageSkeleton{position:fixed;inset:0;background:rgba(255,255,255,.92);z-index:99998;
  display:none;flex-direction:column;padding:1.2rem;gap:.6rem;overflow:auto;}
[data-bs-theme=dark] #hfPageSkeleton{background:rgba(15,23,42,.95);}
#hfPageSkeleton.show{display:flex;}
</style>
<div id="hfPageSkeleton" aria-hidden="true">
  <div class="sk-row" style="margin-bottom:.6rem">
    <div class="sk sk-circle"></div>
    <div style="flex:1">
      <div class="sk sk-line w-50"></div>
      <div class="sk sk-line w-75 sm"></div>
    </div>
  </div>
  <div class="sk sk-thumb"></div>
  <div class="sk-card">
    <div class="sk sk-line w-75 lg"></div>
    <div class="sk sk-line w-90"></div>
    <div class="sk sk-line w-60"></div>
  </div>
  <div class="sk-grid">
    <div class="sk-card"><div class="sk sk-line w-60 lg"></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-50"></div></div>
    <div class="sk-card"><div class="sk sk-line w-60 lg"></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-50"></div></div>
    <div class="sk-card"><div class="sk sk-line w-60 lg"></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-50"></div></div>
    <div class="sk-card"><div class="sk sk-line w-60 lg"></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-50"></div></div>
  </div>
</div>
<script>
(function(){
  window.HFSkel = {
    cardList: function(n){
      n = n||4; var h = '';
      for (var i=0;i<n;i++){
        h += '<div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem">'+
             '<div class="sk sk-circle"></div><div style="flex:1">'+
             '<div class="sk sk-line w-50"></div><div class="sk sk-line w-75 sm"></div>'+
             '</div></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-60"></div></div>';
      }
      return h;
    },
    grid: function(n){
      n = n||6; var h = '<div class="sk-grid">';
      for (var i=0;i<n;i++){
        h += '<div class="sk-card"><div class="sk sk-thumb" style="aspect-ratio:1/1;margin-bottom:.4rem"></div>'+
             '<div class="sk sk-line w-75"></div><div class="sk sk-line w-50 sm"></div></div>';
      }
      return h + '</div>';
    },
    inject: function(el, html){ if (typeof el === 'string') el = document.querySelector(el); if (el) el.innerHTML = html; },
    showPage: function(){ var p=document.getElementById('hfPageSkeleton'); if(p) p.classList.add('show'); },
    hidePage: function(){ var p=document.getElementById('hfPageSkeleton'); if(p) p.classList.remove('show'); }
  };

  // Auto-upgrade: ganti spinner "Memuat…" generik dengan skeleton agar terasa app-like
  function autoUpgrade(){
    var sel = '.fb-empty, .text-muted, .text-center';
    document.querySelectorAll(sel).forEach(function(el){
      if (el.dataset.hfSkelDone) return;
      var t = (el.textContent||'').trim().toLowerCase();
      if (!/(memuat|loading|mencari|menghitung|mendeteksi)/.test(t)) return;
      if (!el.querySelector('.spinner-border')) return;
      el.dataset.hfSkelDone = '1';
      el.innerHTML = '<div class="sk sk-line w-75"></div><div class="sk sk-line w-50 sm"></div>';
    });
    // Auto inject pada elemen yang punya atribut data-hfskel
    document.querySelectorAll('[data-hfskel]:not([data-hfskel-done])').forEach(function(el){
      var kind = el.getAttribute('data-hfskel') || 'cards';
      var n = parseInt(el.getAttribute('data-hfskel-n')||'',10) || 4;
      el.dataset.hfskelDone = '1';
      el.innerHTML = (kind === 'grid') ? HFSkel.grid(n) : HFSkel.cardList(n);
    });
  }
  document.addEventListener('DOMContentLoaded', autoUpgrade);
  // Re-run setiap 1.5 dtk selama 6 dtk untuk menangkap elemen async
  var t0 = Date.now();
  var iv = setInterval(function(){ autoUpgrade(); if (Date.now()-t0 > 6000) clearInterval(iv); }, 1500);

  // Page transition skeleton: tampilkan overlay saat klik link internal
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href')||'';
    if (!href || href.charAt(0)==='#' || a.target==='_blank' || a.hasAttribute('download')) return;
    if (/^(javascript:|mailto:|tel:|whatsapp:|wa\.me)/i.test(href)) return;
    if (/^https?:\/\//i.test(href) && href.indexOf(location.origin)!==0) return;
    if (a.hasAttribute('data-no-skel')) return;
    HFSkel.showPage();
  }, true);
  window.addEventListener('pageshow', function(){ HFSkel.hidePage(); });
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (f && f.tagName==='FORM' && !f.hasAttribute('data-no-skel')) HFSkel.showPage();
  }, true);
})();
</script>

<?php
// Revisi 4 Jun 2026 — Skeleton loading per-halaman.
// Pola: halaman muncul DULU, skeleton loading muncul di area data,
// lalu hilang otomatis setelah konten/data siap.
// Plus: ketika user klik link navigasi, skeleton langsung tampil
// sebagai feedback agar halaman terasa lincah & tidak lemot.
//
// API JS:  HFSkel.list(n) | grid(n) | feed(n) | chat(n) | table(r,c) | video() | profile()
//          HFSkel.inject(selector, html)
// Halaman opt-in via: <body data-skeleton="grid|list|chat|feed|video|profile|table">
?>
<style>
.sk{position:relative;overflow:hidden;background:#e5e7eb;border-radius:8px;display:block;}
[data-bs-theme=dark] .sk{background:#1f2937;}
.sk::after{content:"";position:absolute;inset:0;transform:translateX(-100%);
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);
  animation:hfShimmer 1.25s infinite;}
[data-bs-theme=dark] .sk::after{background:linear-gradient(90deg,transparent,rgba(255,255,255,.08),transparent);}
@keyframes hfShimmer{100%{transform:translateX(100%);}}
.sk-line{height:.85rem;margin:.4rem 0;border-radius:6px;}
.sk-line.lg{height:1.1rem;} .sk-line.sm{height:.65rem;}
.sk-line.w-25{width:25%;} .sk-line.w-40{width:40%;} .sk-line.w-50{width:50%;}
.sk-line.w-60{width:60%;} .sk-line.w-75{width:75%;} .sk-line.w-90{width:90%;}
.sk-circle{width:42px;height:42px;border-radius:50%;}
.sk-square{width:56px;height:56px;border-radius:12px;}
.sk-thumb{width:100%;aspect-ratio:16/10;border-radius:12px;}
.sk-video{width:100%;aspect-ratio:16/9;border-radius:14px;}
.sk-card{padding:.9rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff;margin-bottom:.75rem;}
[data-bs-theme=dark] .sk-card{background:#0f172a;border-color:#1f2937;}
.sk-row{display:flex;gap:.7rem;align-items:center;}
.sk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.7rem;}
.sk-table{width:100%;border-collapse:separate;border-spacing:0 6px;}
.sk-table td{padding:6px 8px;}
.hf-skel-container{padding:.5rem 0;}
/* Skeleton tidak menutup konten penuh, tampil di area data saja. */
.hf-skel-overlay{position:static;display:block;max-width:960px;margin:8px auto;padding:0 12px;opacity:.9;}
.hf-skel-overlay .hf-skel-container{max-width:960px;margin:0 auto;}

/* Overlay navigasi: muncul ketika user klik link, sebelum halaman tujuan termuat.
   Halaman lama tetap kelihatan di belakang, skeleton tipis tampil sebagai feedback. */
#hf-nav-skel{position:fixed;left:0;right:0;top:0;z-index:2000;background:rgba(255,255,255,.85);
  backdrop-filter:saturate(120%) blur(2px);padding:14px;display:none;}
[data-bs-theme=dark] #hf-nav-skel{background:rgba(15,23,42,.85);}
#hf-nav-skel.show{display:block;}
#hf-nav-skel .bar{height:3px;width:100%;background:linear-gradient(90deg,#0ea5e9,#22c55e,#0ea5e9);
  background-size:200% 100%;animation:hfBar 1s linear infinite;border-radius:2px;margin-bottom:10px;}
@keyframes hfBar{0%{background-position:0 0}100%{background-position:-200% 0}}
#hf-nav-skel .inner{max-width:960px;margin:0 auto;}
</style>
<div id="hf-nav-skel" aria-hidden="true">
  <div class="bar"></div>
  <div class="inner">
    <div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem">
      <div class="sk sk-circle"></div><div style="flex:1">
        <div class="sk sk-line w-50"></div><div class="sk sk-line w-75 sm"></div>
      </div></div>
      <div class="sk sk-line w-90"></div><div class="sk sk-line w-60"></div>
    </div>
  </div>
</div>
<script>
(function(){
  var HFSkel = {
    list: function(n){ n=n||4; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem"><div class="sk sk-circle"></div><div style="flex:1"><div class="sk sk-line w-50"></div><div class="sk sk-line w-75 sm"></div></div></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-60"></div></div>';} return h; },
    grid: function(n){ n=n||6; var h='<div class="sk-grid">'; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk sk-thumb" style="aspect-ratio:1/1;margin-bottom:.4rem"></div><div class="sk sk-line w-75"></div><div class="sk sk-line w-50 sm"></div></div>';} return h+'</div>'; },
    table: function(r,c){ r=r||6;c=c||4; var h='<table class="sk-table"><tbody>'; for(var i=0;i<r;i++){h+='<tr class="sk-card" style="display:table-row">'; for(var j=0;j<c;j++){ h+='<td><div class="sk sk-line '+(j===0?'w-40':(j===c-1?'w-25':'w-60'))+'"></div></td>';} h+='</tr>';} return h+'</tbody></table>'; },
    chat: function(n){ n=n||5; var h=''; for(var i=0;i<n;i++){ var r=i%2===1; h+='<div class="sk-row" style="justify-content:'+(r?'flex-end':'flex-start')+';margin:.45rem 0">'+(r?'':'<div class="sk sk-circle"></div>')+'<div style="max-width:65%"><div class="sk sk-line '+(r?'w-60':'w-75')+' lg"></div><div class="sk sk-line w-40 sm"></div></div>'+(r?'<div class="sk sk-circle"></div>':'')+'</div>';} return h; },
    feed: function(n){ n=n||3; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem"><div class="sk sk-circle"></div><div style="flex:1"><div class="sk sk-line w-40"></div><div class="sk sk-line w-25 sm"></div></div></div><div class="sk sk-thumb"></div><div class="sk sk-line w-90" style="margin-top:.5rem"></div><div class="sk sk-line w-60"></div></div>';} return h; },
    video: function(){ return '<div class="sk-card"><div class="sk sk-video"></div><div class="sk sk-line w-60 lg" style="margin-top:.6rem"></div><div class="sk sk-line w-40 sm"></div></div>'; },
    profile: function(){ return '<div class="sk-card" style="text-align:center;padding:1.2rem"><div class="sk sk-circle" style="width:84px;height:84px;margin:0 auto .6rem"></div><div class="sk sk-line w-50" style="margin:.4rem auto"></div><div class="sk sk-line w-25 sm" style="margin:.3rem auto"></div></div>'; },
    inject: function(t,h){ if(typeof t==='string') t=document.querySelector(t); if(t) t.innerHTML=h; }
  };
  window.HFSkel = HFSkel;

  // === A. Skeleton untuk konten halaman tujuan ===
  // Tampilkan halaman dulu (DOMContentLoaded), lalu sisipkan skeleton di #skel-host
  // sebagai indikator data sedang dimuat. Hilang otomatis saat siap.
  function renderPageSkel(){
    var mode = document.body.getAttribute('data-skeleton');
    if (!mode) return;
    var host = document.getElementById('skel-host');
    if (!host) return;
    var html = '';
    if (mode==='list') html=HFSkel.list(5);
    else if (mode==='grid') html=HFSkel.grid(8);
    else if (mode==='feed') html=HFSkel.feed(3);
    else if (mode==='chat') html=HFSkel.chat(6);
    else if (mode==='video') html=HFSkel.video();
    else if (mode==='profile') html=HFSkel.profile();
    else if (mode==='table') html=HFSkel.table(8,4);
    host.innerHTML = '<div class="hf-skel-container">'+html+'</div>';
  }
  function hideSkelHost(){
    var host = document.getElementById('skel-host');
    if (host){ host.innerHTML=''; host.parentNode && host.parentNode.removeChild(host); }
  }
  // Halaman muncul DULU → setelah DOM siap, baru tempelkan skeleton sebentar.
  if (document.readyState !== 'loading') { renderPageSkel(); }
  else document.addEventListener('DOMContentLoaded', renderPageSkel);
  window.addEventListener('load', function(){ setTimeout(hideSkelHost, 600); });
  setTimeout(hideSkelHost, 4000); // pengaman

  // === B. Skeleton ketika klik link navigasi ===
  // Begitu user klik menu, langsung tampilkan overlay tipis berisi skeleton
  // sebagai feedback bahwa halaman sedang dibuka. Hilang otomatis pada pageshow.
  function showNavSkel(){
    var n = document.getElementById('hf-nav-skel');
    if (n) n.classList.add('show');
  }
  function hideNavSkel(){
    var n = document.getElementById('hf-nav-skel');
    if (n) n.classList.remove('show');
  }
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    if (a.target === '_blank' || a.hasAttribute('download')) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
    if (a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-no-skel')) return;
    // hanya untuk navigasi internal (same-origin atau relatif)
    try {
      var u = new URL(href, location.href);
      if (u.origin !== location.origin) return;
      if (u.pathname === location.pathname && u.search === location.search) return;
    } catch(_){}
    showNavSkel();
  }, true);
  // Form submit juga tampilkan skeleton
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (!f || f.hasAttribute('data-ajax') || f.hasAttribute('data-no-skel')) return;
    showNavSkel();
  }, true);
  window.addEventListener('pageshow', hideNavSkel);
  window.addEventListener('beforeunload', function(){ /* keep visible until next page paints */ });
})();
</script>

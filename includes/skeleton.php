<?php
// Revisi 3 Jun 2026 — Skeleton loading "shape-aware".
// Bukan preloader. Bentuk skeleton menyesuaikan jenis data tiap section.
// Tidak ada lagi overlay full-screen / progress bar.
//
// Cara pakai:
//   1) Pastikan section data dibungkus dengan: <div data-live="<key>">...</div>
//      ATAU tambahkan atribut: data-skel-shape="feed|forum|online|stories|grid|list|table|profile|chat|video"
//   2) Saat user klik tombol [data-soft-refresh] / saat soft-refresh berjalan,
//      kontainer di-isi skeleton sesuai shape lalu diganti dgn data fresh.
//   3) Page-level $pageSkeleton masih boleh dipakai utk inject inline pada
//      kontainer kosong (mis. data API yg dimuat client-side).
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
.sk-circle.sm{width:28px;height:28px;}
.sk-circle.lg{width:56px;height:56px;}
.sk-square{width:56px;height:56px;border-radius:12px;}
.sk-thumb{width:100%;aspect-ratio:16/10;border-radius:12px;}
.sk-video{width:100%;aspect-ratio:16/9;border-radius:14px;}
.sk-card{padding:.75rem;border:1px solid #e5e7eb;border-radius:12px;background:transparent;margin-bottom:.6rem;}
[data-bs-theme=dark] .sk-card{border-color:#1f2937;}
.sk-row{display:flex;gap:.7rem;align-items:center;}
.sk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;}
.sk-stories{display:flex;gap:.6rem;overflow:hidden;padding:.25rem 0;}
.sk-stories .it{flex:0 0 64px;text-align:center;}
.sk-stories .it .sk{margin:0 auto;}
.sk-list-item{display:flex;align-items:center;justify-content:space-between;padding:.45rem .25rem;border-bottom:1px solid rgba(0,0,0,.05);}
[data-bs-theme=dark] .sk-list-item{border-color:rgba(255,255,255,.06);}
.sk-table{width:100%;border-collapse:separate;border-spacing:0 6px;}
.sk-table td{padding:6px 8px;}
.hf-skel-wrap{padding:.25rem;}
</style>
<script>
(function(){
  var S = {
    list: function(n){ n=n||4; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-list-item"><div class="sk-row" style="flex:1"><div class="sk sk-circle sm"></div><div style="flex:1"><div class="sk sk-line w-50"></div><div class="sk sk-line w-75 sm"></div></div></div><div class="sk sk-line w-25 sm" style="margin-left:.5rem"></div></div>';} return h; },
    online: function(n){ n=n||5; var h=''; for(var i=0;i<n;i++){ h+='<li class="list-group-item"><div class="sk-row"><div class="sk sk-circle sm"></div><div class="sk sk-line w-50" style="flex:1"></div><div class="sk sk-line w-25 sm"></div></div></li>';} return '<ul class="list-group list-group-flush">'+h+'</ul>'; },
    forum: function(n){ n=n||4; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row" style="margin-bottom:.4rem"><div class="sk sk-circle sm"></div><div style="flex:1"><div class="sk sk-line w-40"></div><div class="sk sk-line w-25 sm"></div></div></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-75"></div><div class="sk-row" style="margin-top:.4rem;gap:1rem"><div class="sk sk-line w-25 sm"></div><div class="sk sk-line w-25 sm"></div></div></div>';} return h; },
    feed: function(n){ n=n||3; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem"><div class="sk sk-circle"></div><div style="flex:1"><div class="sk sk-line w-40"></div><div class="sk sk-line w-25 sm"></div></div></div><div class="sk sk-thumb"></div><div class="sk sk-line w-90" style="margin-top:.5rem"></div><div class="sk sk-line w-60"></div></div>';} return h; },
    stories: function(n){ n=n||8; var h='<div class="sk-stories">'; for(var i=0;i<n;i++){ h+='<div class="it"><div class="sk sk-circle lg"></div><div class="sk sk-line w-75 sm" style="margin:.3rem auto 0"></div></div>';} return h+'</div>'; },
    newmembers: function(n){ n=n||6; var h='<div class="sk-grid" style="grid-template-columns:repeat(auto-fill,minmax(90px,1fr))">'; for(var i=0;i<n;i++){ h+='<div style="text-align:center"><div class="sk sk-circle lg" style="margin:0 auto"></div><div class="sk sk-line w-75 sm" style="margin:.35rem auto 0"></div></div>';} return h+'</div>'; },
    grid: function(n){ n=n||6; var h='<div class="sk-grid">'; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk sk-thumb" style="aspect-ratio:1/1;margin-bottom:.4rem"></div><div class="sk sk-line w-75"></div><div class="sk sk-line w-50 sm"></div></div>';} return h+'</div>'; },
    table: function(r,c){ r=r||6;c=c||4; var h='<table class="sk-table"><tbody>'; for(var i=0;i<r;i++){h+='<tr>'; for(var j=0;j<c;j++){ h+='<td><div class="sk sk-line '+(j===0?'w-40':(j===c-1?'w-25':'w-60'))+'"></div></td>';} h+='</tr>';} return h+'</tbody></table>'; },
    chat: function(n){ n=n||5; var h=''; for(var i=0;i<n;i++){ var r=i%2===1; h+='<div class="sk-row" style="justify-content:'+(r?'flex-end':'flex-start')+';margin:.45rem 0">'+(r?'':'<div class="sk sk-circle"></div>')+'<div style="max-width:65%"><div class="sk sk-line '+(r?'w-60':'w-75')+' lg"></div><div class="sk sk-line w-40 sm"></div></div>'+(r?'<div class="sk sk-circle"></div>':'')+'</div>';} return h; },
    video: function(){ return '<div class="sk-card"><div class="sk sk-video"></div><div class="sk sk-line w-60 lg" style="margin-top:.6rem"></div><div class="sk sk-line w-40 sm"></div></div>'; },
    profile: function(){ return '<div class="sk-card" style="text-align:center;padding:1.2rem"><div class="sk sk-circle" style="width:84px;height:84px;margin:0 auto .6rem"></div><div class="sk sk-line w-50" style="margin:.4rem auto"></div><div class="sk sk-line w-25 sm" style="margin:.3rem auto"></div></div>'; },
    jadwal: function(n){ n=n||3; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row"><div class="sk sk-square"></div><div style="flex:1"><div class="sk sk-line w-60"></div><div class="sk sk-line w-40 sm"></div><div class="sk sk-line w-25 sm"></div></div></div></div>';} return h; },
    guestbook: function(n){ n=n||4; var h=''; for(var i=0;i<n;i++){ h+='<div class="sk-card"><div class="sk-row" style="margin-bottom:.3rem"><div class="sk sk-circle sm"></div><div class="sk sk-line w-40"></div></div><div class="sk sk-line w-90"></div></div>';} return h; }
  };

  // Pemetaan key data-live → shape default.
  var SHAPE_BY_KEY = {
    forum:'forum', online:'online', feed:'feed', stories:'stories', newmembers:'newmembers',
    event_terdekat:'jadwal', jadwal:'jadwal', guestbook:'guestbook', 'guestbook-profile':'guestbook',
    'perlengkapan-profile':'grid'
  };

  function shapeFor(node){
    var s = node.getAttribute('data-skel-shape');
    if (s && S[s]) return s;
    var k = node.getAttribute('data-live');
    if (k && SHAPE_BY_KEY[k]) return SHAPE_BY_KEY[k];
    return 'list';
  }
  function render(shape){
    var fn = S[shape] || S.list;
    return '<div class="hf-skel-wrap">'+fn()+'</div>';
  }
  function fill(node){
    if(!node) return;
    // Simpan tinggi agar layout tidak melompat ketika konten kembali.
    var h = node.offsetHeight;
    if (h > 40) node.style.minHeight = h+'px';
    node.innerHTML = render(shapeFor(node));
  }
  function fillAllLive(){
    document.querySelectorAll('[data-live],[data-skel-shape]').forEach(fill);
  }

  window.HFSkel = {
    shapes: S,
    fill: fill,
    fillAllLive: fillAllLive,
    inject: function(t,h){ if(typeof t==='string') t=document.querySelector(t); if(t) t.innerHTML=h; }
  };

  // Skeleton tampil saat user men-trigger refresh manual.
  document.addEventListener('click', function(ev){
    var b = ev.target.closest && ev.target.closest('[data-soft-refresh]');
    if(!b) return;
    var card = b.closest('.card,[data-live-host]');
    var nodes = card ? card.querySelectorAll('[data-live],[data-skel-shape]') : document.querySelectorAll('[data-live],[data-skel-shape]');
    nodes.forEach(fill);
  }, true);

  // Auto: tampilkan skeleton sebentar pada setiap pemuatan halaman supaya
  // setiap section [data-live] / [data-skel-shape] menampilkan bentuk loading
  // sesuai datanya, lalu konten asli muncul kembali.
  function autoFlash(){
    var nodes = document.querySelectorAll('[data-live],[data-skel-shape]');
    if(!nodes.length) return;
    nodes.forEach(function(node){
      var original = node.innerHTML;
      var h = node.offsetHeight;
      if (h > 40) node.style.minHeight = h+'px';
      node.innerHTML = render(shapeFor(node));
      setTimeout(function(){
        node.innerHTML = original;
        // biarkan minHeight tetap sebentar agar tidak meloncat
        setTimeout(function(){ node.style.minHeight = ''; }, 150);
      }, 650);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoFlash);
  } else {
    autoFlash();
  }
})();
</script>

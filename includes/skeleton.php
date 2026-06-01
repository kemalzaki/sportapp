<?php
// Revisi 4 Jun 2026 — Skeleton loading per-halaman (tidak generic untuk semua).
// API: HFSkel.list(n) | HFSkel.grid(n) | HFSkel.table(rows,cols) | HFSkel.chat(n)
//      HFSkel.video()  | HFSkel.profile() | HFSkel.feed(n)
//      HFSkel.inject(selector, html)
// Setiap halaman memilih bentuk skeleton yang SESUAI dengan data yang akan dimuat.
// Halaman BISA opt-in overlay penuh dengan: <body data-skeleton="grid|list|chat|feed|video|profile|table">
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
/* Container overlay per-page (BUKAN overlay generic full-screen) */
.hf-skel-container{padding:.5rem 0;}
</style>
<script>
(function(){
  function el(tag, cls, html){ var x=document.createElement(tag); if(cls)x.className=cls; if(html!=null)x.innerHTML=html; return x; }

  var HFSkel = {
    /* List card (untuk feed-like sederhana, kartu kanan-kiri avatar+title) */
    list: function(n){
      n = n||4; var h = '';
      for (var i=0;i<n;i++){
        h += '<div class="sk-card"><div class="sk-row" style="margin-bottom:.5rem">'+
             '<div class="sk sk-circle"></div><div style="flex:1">'+
             '<div class="sk sk-line w-50"></div><div class="sk sk-line w-75 sm"></div>'+
             '</div></div><div class="sk sk-line w-90"></div><div class="sk sk-line w-60"></div></div>';
      }
      return h;
    },
    /* Grid (thumbnail kotak — untuk halaman gambar/produk/tempat) */
    grid: function(n){
      n = n||6; var h = '<div class="sk-grid">';
      for (var i=0;i<n;i++){
        h += '<div class="sk-card"><div class="sk sk-thumb" style="aspect-ratio:1/1;margin-bottom:.4rem"></div>'+
             '<div class="sk sk-line w-75"></div><div class="sk sk-line w-50 sm"></div></div>';
      }
      return h + '</div>';
    },
    /* Tabel (untuk halaman admin/laporan) */
    table: function(rows, cols){
      rows = rows||6; cols = cols||4;
      var h = '<table class="sk-table"><tbody>';
      for (var r=0;r<rows;r++){
        h += '<tr class="sk-card" style="display:table-row">';
        for (var c=0;c<cols;c++){
          h += '<td><div class="sk sk-line '+(c===0?'w-40':(c===cols-1?'w-25':'w-60'))+'"></div></td>';
        }
        h += '</tr>';
      }
      return h + '</tbody></table>';
    },
    /* Chat (pesan, dm) */
    chat: function(n){
      n = n||5; var h = '';
      for (var i=0;i<n;i++){
        var right = i%2===1;
        h += '<div class="sk-row" style="justify-content:'+(right?'flex-end':'flex-start')+';margin:.45rem 0">'+
             (right?'':'<div class="sk sk-circle"></div>')+
             '<div style="max-width:65%"><div class="sk sk-line '+(right?'w-60':'w-75')+' lg"></div>'+
             '<div class="sk sk-line w-40 sm"></div></div>'+
             (right?'<div class="sk sk-circle"></div>':'')+
             '</div>';
      }
      return h;
    },
    /* Feed sosial (gambar + caption) */
    feed: function(n){
      n = n||3; var h = '';
      for (var i=0;i<n;i++){
        h += '<div class="sk-card">'+
             '<div class="sk-row" style="margin-bottom:.5rem">'+
             '<div class="sk sk-circle"></div>'+
             '<div style="flex:1"><div class="sk sk-line w-40"></div><div class="sk sk-line w-25 sm"></div></div>'+
             '</div>'+
             '<div class="sk sk-thumb"></div>'+
             '<div class="sk sk-line w-90" style="margin-top:.5rem"></div>'+
             '<div class="sk sk-line w-60"></div>'+
             '</div>';
      }
      return h;
    },
    /* Video player skeleton (IPTV / live / video) */
    video: function(){
      return '<div class="sk-card">'+
             '<div class="sk sk-video"></div>'+
             '<div class="sk sk-line w-60 lg" style="margin-top:.6rem"></div>'+
             '<div class="sk sk-line w-40 sm"></div></div>';
    },
    /* Profil */
    profile: function(){
      return '<div class="sk-card" style="text-align:center;padding:1.2rem">'+
             '<div class="sk sk-circle" style="width:84px;height:84px;margin:0 auto .6rem"></div>'+
             '<div class="sk sk-line w-50" style="margin:.4rem auto"></div>'+
             '<div class="sk sk-line w-25 sm" style="margin:.3rem auto"></div>'+
             '<div class="sk-row" style="justify-content:space-around;margin-top:.8rem">'+
               '<div style="flex:1;text-align:center"><div class="sk sk-line w-50" style="margin:0 auto"></div><div class="sk sk-line w-25 sm" style="margin:.2rem auto"></div></div>'+
               '<div style="flex:1;text-align:center"><div class="sk sk-line w-50" style="margin:0 auto"></div><div class="sk sk-line w-25 sm" style="margin:.2rem auto"></div></div>'+
               '<div style="flex:1;text-align:center"><div class="sk sk-line w-50" style="margin:0 auto"></div><div class="sk sk-line w-25 sm" style="margin:.2rem auto"></div></div>'+
             '</div></div>';
    },
    inject: function(target, html){
      if (typeof target === 'string') target = document.querySelector(target);
      if (target) target.innerHTML = html;
    }
  };
  window.HFSkel = HFSkel;

  // Auto-upgrade <body data-skeleton="..."> — sisipkan placeholder yang tepat
  // ke dalam #skel-host, jika ada. Hilang otomatis setelah window.load.
  document.addEventListener('DOMContentLoaded', function(){
    var mode = document.body.getAttribute('data-skeleton');
    if (!mode) return;
    var host = document.getElementById('skel-host');
    if (!host) return;
    var html = '';
    if (mode === 'list')        html = HFSkel.list(5);
    else if (mode === 'grid')   html = HFSkel.grid(8);
    else if (mode === 'feed')   html = HFSkel.feed(3);
    else if (mode === 'chat')   html = HFSkel.chat(6);
    else if (mode === 'video')  html = HFSkel.video();
    else if (mode === 'profile')html = HFSkel.profile();
    else if (mode === 'table')  html = HFSkel.table(8,4);
    host.innerHTML = '<div class="hf-skel-container">'+html+'</div>';
  });
  window.addEventListener('load', function(){
    var host = document.getElementById('skel-host');
    if (host) host.innerHTML = '';
  });
})();
</script>

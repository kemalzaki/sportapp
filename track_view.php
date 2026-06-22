<?php
/**
 * Revisi 15 Juni 2026 — Halaman PUBLIK untuk penerima tautan Live Tracking.
 * Tidak butuh login. Hanya butuh token yang valid pada query string.
 */
require __DIR__.'/config/db.php';
$tok = trim((string)($_GET['token'] ?? ''));
?><!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Live Tracking · KawanKeringat</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  body{background:#0f172a;color:#e2e8f0;margin:0}
  .hdr{background:linear-gradient(135deg,#0ea5e9,#6366f1);padding:14px 18px;color:#fff}
  .wrap{max-width:980px;margin:0 auto;padding:14px}
  #m{height:62vh;border-radius:12px;border:2px solid #1e293b}
  .stat{background:#111827;border:1px solid #1f2937;border-radius:10px;padding:10px;text-align:center}
  .stat b{font-size:1.25rem;display:block}
  .pulse{width:14px;height:14px;border-radius:50%;background:#22c55e;display:inline-block;
         box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:p 1.5s infinite}
  @keyframes p{0%{box-shadow:0 0 0 0 rgba(34,197,94,.7)}70%{box-shadow:0 0 0 14px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
</style>
</head><body>

<div class="hdr">
  <div class="wrap d-flex align-items-center gap-2">
    <i class="bi bi-broadcast fs-3"></i>
    <div>
      <div class="fw-bold" id="tTitle">Live Tracking</div>
      <div class="small opacity-75" id="tSub">Memuat…</div>
    </div>
    <span class="ms-auto pulse" id="dot" title="status"></span>
  </div>
</div>

<div class="wrap">
  <?php if ($tok === ''): ?>
    <div class="alert alert-danger mt-3">Token tidak valid.</div>
  <?php else: ?>
    <div id="m" class="mb-3"></div>
    <div class="row g-2">
      <div class="col-4"><div class="stat"><small class="text-secondary">Jarak</small><b id="sDist">0.00 km</b></div></div>
      <div class="col-4"><div class="stat"><small class="text-secondary">Update terakhir</small><b id="sSeen">—</b></div></div>
      <div class="col-4"><div class="stat"><small class="text-secondary">Berlaku s/d</small><b id="sExp">—</b></div></div>
    </div>
    <div class="text-center small text-secondary mt-3" id="msg"></div>
    <div class="text-center small text-secondary mt-2">
      Tautan ini akan otomatis kedaluwarsa setelah waktu yang ditentukan oleh
      pemilik. Tidak ada data pribadi lain yang diakses.
    </div>
  <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const TOKEN = <?= json_encode($tok) ?>;
if (TOKEN) {
  const map = L.map('m').setView([-6.2,106.8], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OSM'}).addTo(map);
  const poly = L.polyline([], {color:'#22d3ee', weight:5}).addTo(map);
  let me=null, firstFit=true;

  function haversine(a,b){
    const R=6371000, toRad=x=>x*Math.PI/180;
    const dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    const s=Math.sin(dLat/2)**2 + Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function totalKm(pts){ let d=0; for(let i=1;i<pts.length;i++) d+=haversine(pts[i-1],pts[i]); return (d/1000).toFixed(2); }

  async function tick(){
    try{
      const j = await (await fetch('/api_live_tracking.php?action=view&token='+encodeURIComponent(TOKEN))).json();
      if(!j.ok){ document.getElementById('msg').textContent = j.err||'Gagal memuat.'; return; }
      const s = j.session;
      document.getElementById('tTitle').textContent = s.judul + ' · ' + s.user_nama;
      document.getElementById('tSub').textContent   = (s.pesan||'Memantau posisi langsung').substr(0,140);
      document.getElementById('sExp').textContent   = (s.expires_at||'').substr(0,16).replace('T',' ');
      document.getElementById('sSeen').textContent  = s.last_seen_at ? s.last_seen_at.substr(11,8) : '—';
      const dot = document.getElementById('dot');
      dot.style.background = (s.is_active==='t'||s.is_active===true) && !j.expired ? '#22c55e' : '#ef4444';
      const pts = j.points.map(p=>[p.lat,p.lng]);
      poly.setLatLngs(pts);
      document.getElementById('sDist').textContent = totalKm(pts)+' km';
      if(pts.length){
        const last = pts[pts.length-1];
        if(!me){ me = L.marker(last).addTo(map); } else { me.setLatLng(last); }
        if(firstFit){ map.fitBounds(poly.getBounds().pad(0.2)); firstFit=false; }
      }
      if(j.expired){ document.getElementById('msg').innerHTML = '<span class="text-warning">Sesi sudah berakhir.</span>'; }
    }catch(e){ document.getElementById('msg').textContent = 'Koneksi terputus, mencoba lagi…'; }
  }
  tick(); setInterval(tick, 5000);
}
</script>
</body></html>

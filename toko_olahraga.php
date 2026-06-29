<?php
/**
 * toko_olahraga.php — Revisi (29 Juni 2026)
 * Mekanisme diubah meniru lacak_faskes.php: otomatis melacak toko
 * perlengkapan olahraga di sekitar lokasi user via OpenStreetMap
 * (Overpass API). Tidak lagi bergantung pada tabel statis.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Lacak Toko Perlengkapan Olahraga Terdekat';
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Lacak Toko Perlengkapan Olahraga</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-shop text-primary"></i> Lacak Toko Perlengkapan Olahraga Terdekat</h2>
<p class="text-muted small mb-3">
  Aktifkan akses lokasi, lalu sistem otomatis mencari toko olahraga di sekitar Anda dari OpenStreetMap
  dan menyediakan tombol rute langsung ke Google Maps.
</p>

<div class="alert alert-info small">
  <i class="bi bi-info-circle"></i>
  Data berasal dari kontributor publik OpenStreetMap (tag <code>shop=sports</code>, <code>shop=outdoor</code>, <code>shop=bicycle</code>).
  Jika di area Anda hasil belum lengkap, silakan bantu kontribusi di
  <a href="https://www.openstreetmap.org" target="_blank" rel="noopener">openstreetmap.org</a>.
</div>

<div class="card shadow-sm border-primary" id="tokoTerdekat">
  <div class="card-header bg-primary-subtle text-primary-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-shop-window"></i> <strong>Toko Perlengkapan Olahraga Terdekat</strong></span>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <select id="radSel" class="form-select form-select-sm" style="width:auto">
        <option value="2000">Radius 2 km</option>
        <option value="5000" selected>Radius 5 km</option>
        <option value="10000">Radius 10 km</option>
        <option value="25000">Radius 25 km</option>
        <option value="50000">Radius 50 km</option>
      </select>
      <button type="button" id="btnLocate" class="btn btn-primary btn-sm"><i class="bi bi-crosshair"></i> Gunakan Lokasi Saya</button>
      <button type="button" id="btnRefreshToko" class="btn btn-outline-primary btn-sm" disabled><i class="bi bi-arrow-repeat"></i> Muat Ulang</button>
    </div>
  </div>
  <div class="card-body">
    <div id="tokoStatus" class="alert alert-info small py-2 mb-2">Klik <b>Gunakan Lokasi Saya</b> untuk mulai melacak otomatis.</div>
    <div id="tokoInfo"   class="small text-muted mb-2"></div>
    <div id="tokoMap" style="height:420px;border-radius:8px;overflow:hidden;background:#eef2f7"></div>
    <div id="tokoList" class="list-group list-group-flush mt-3"></div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var map, userMarker, routeLayer, shopLayer;
  var userLatLng = null;
  var status = document.getElementById('tokoStatus');
  var infoEl = document.getElementById('tokoInfo');
  var listEl = document.getElementById('tokoList');
  var btnLoc = document.getElementById('btnLocate');
  var btnRef = document.getElementById('btnRefreshToko');
  var radSel = document.getElementById('radSel');

  function initMap(lat,lng){
    if (map){ map.setView([lat,lng],14); return; }
    map = L.map('tokoMap').setView([lat,lng],14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);
    shopLayer  = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup().addTo(map);
  }
  function distKm(a,b){
    var R=6371, toRad=function(x){return x*Math.PI/180;};
    var dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    var s=Math.sin(dLat/2)**2+Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  async function reverseGeocode(lat,lng){
    try {
      var r = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng+'&zoom=14&addressdetails=1',
        {headers:{'Accept-Language':'id'}});
      var j = await r.json();
      return j.display_name || '';
    } catch(e){ return ''; }
  }
  async function fetchShops(lat,lng,radius){
    var q='[out:json][timeout:25];(' +
            'node["shop"~"sports|outdoor|bicycle|fishing|hunting"](around:'+radius+','+lat+','+lng+');' +
            'way ["shop"~"sports|outdoor|bicycle|fishing|hunting"](around:'+radius+','+lat+','+lng+');' +
          ');out center 80;';
    var r = await fetch('https://overpass-api.de/api/interpreter',{method:'POST', body:q});
    var j = await r.json();
    var els = (j.elements||[]).map(function(e){
      var la = e.lat || (e.center && e.center.lat);
      var ln = e.lon || (e.center && e.center.lon);
      if (!la||!ln) return null;
      var t = e.tags||{};
      var kategori = t.shop || '-';
      var nama = t.name || (kategori==='sports'?'Toko Olahraga': kategori==='outdoor'?'Toko Outdoor': kategori==='bicycle'?'Toko Sepeda': kategori==='fishing'?'Toko Pancing': kategori==='hunting'?'Toko Berburu':'Toko Perlengkapan');
      return {
        lat:la, lng:ln, nama:nama, tipe:kategori,
        alamat:(t['addr:street']||'')+(t['addr:housenumber']?' '+t['addr:housenumber']:''),
        kota:(t['addr:city']||t['addr:town']||t['addr:village']||''),
        telp:t.phone||t['contact:phone']||'',
        jam:t.opening_hours||'',
        web:t.website||t['contact:website']||''
      };
    }).filter(Boolean);
    els.forEach(function(e){ e.km = distKm([lat,lng],[e.lat,e.lng]); });
    els.sort(function(a,b){return a.km-b.km;});
    return els.slice(0,40);
  }
  function iconFor(tipe){
    if (tipe==='bicycle') return 'bi-bicycle text-success';
    if (tipe==='outdoor') return 'bi-backpack text-warning';
    if (tipe==='fishing') return 'bi-bullseye text-info';
    if (tipe==='hunting') return 'bi-bullseye text-danger';
    return 'bi-shop text-primary';
  }
  function renderList(items){
    shopLayer.clearLayers();
    routeLayer.clearLayers();
    listEl.innerHTML='';
    if (!items.length){
      listEl.innerHTML='<div class="text-muted small p-2">Tidak ada toko perlengkapan olahraga ditemukan. Coba perbesar radius.</div>';
      return;
    }
    var bnds = [userLatLng];
    items.forEach(function(it){
      var m = L.marker([it.lat,it.lng]).addTo(shopLayer).bindPopup('<b>'+it.nama+'</b><br>'+it.tipe+'<br>'+it.km.toFixed(2)+' km');
      bnds.push([it.lat,it.lng]);
      var gmapUrl = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&origin='+userLatLng[0]+','+userLatLng[1]+'&destination='+it.lat+','+it.lng;
      var item = document.createElement('div');
      item.className='list-group-item d-flex justify-content-between align-items-start gap-2';
      item.innerHTML =
        '<div class="flex-grow-1" style="cursor:pointer" data-route="1">'+
          '<i class="bi '+iconFor(it.tipe)+'"></i> <strong>'+it.nama+'</strong> '+
          '<span class="badge bg-secondary ms-1">'+it.tipe+'</span><br>'+
          '<small class="text-muted">'+
            (it.alamat || it.kota || 'Alamat tidak tersedia') +
            (it.jam?' · 🕘 '+it.jam:'') +
            (it.telp?' · ☎ '+it.telp:'') +
          '</small>'+
        '</div>'+
        '<div class="text-end">'+
          '<span class="badge bg-primary rounded-pill mb-1 d-block">'+it.km.toFixed(2)+' km</span>'+
          '<a href="'+gmapUrl+'" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-1"><i class="bi bi-google"></i> Rute</a>'+
          (it.web?'<br><a href="'+it.web+'" target="_blank" rel="noopener" class="small">Web</a>':'')+
        '</div>';
      item.querySelector('[data-route]').addEventListener('click', function(){ showRoute(it,m); });
      listEl.appendChild(item);
    });
    if (bnds.length>1) map.fitBounds(bnds,{padding:[40,40]});
  }
  async function showRoute(it,marker){
    routeLayer.clearLayers(); marker.openPopup();
    status.className='alert alert-info small py-2 mb-2';
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Menghitung rute ke <b>'+it.nama+'</b>…';
    try {
      var url='https://router.project-osrm.org/route/v1/driving/'+userLatLng[1]+','+userLatLng[0]+';'+it.lng+','+it.lat+'?overview=full&geometries=geojson';
      var r = await fetch(url); var j = await r.json();
      if (j.routes && j.routes[0]){
        var rt = j.routes[0];
        var line = L.geoJSON(rt.geometry,{style:{color:'#0d6efd',weight:5,opacity:0.8}}).addTo(routeLayer);
        map.fitBounds(line.getBounds(),{padding:[40,40]});
        var km = (rt.distance/1000).toFixed(2);
        var menit = Math.round(rt.duration/60);
        var gmapUrl = 'https://www.google.com/maps/dir/?api=1&origin='+userLatLng[0]+','+userLatLng[1]+'&destination='+it.lat+','+it.lng;
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = '<i class="bi bi-signpost-2-fill"></i> Rute ke <b>'+it.nama+'</b>: <strong>'+km+' km</strong> · estimasi '+menit+' menit. <a href="'+gmapUrl+'" target="_blank" class="ms-2">Buka di Google Maps</a>';
      } else throw new Error('Rute tidak ditemukan');
    } catch(err){
      status.className='alert alert-warning small py-2 mb-2';
      status.textContent='Gagal mengambil rute: '+err.message;
    }
  }
  async function locateAndLoad(){
    if (!navigator.geolocation){ status.textContent='Browser tidak mendukung Geolocation.'; return; }
    status.className='alert alert-info small py-2 mb-2';
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi lokasi…';
    navigator.geolocation.getCurrentPosition(async function(pos){
      userLatLng = [pos.coords.latitude, pos.coords.longitude];
      initMap(userLatLng[0], userLatLng[1]);
      if (userMarker) userMarker.remove();
      userMarker = L.marker(userLatLng,{title:'Lokasi Anda'}).addTo(map).bindPopup('📍 Lokasi Anda').openPopup();
      btnRef.disabled = false;
      reverseGeocode(userLatLng[0], userLatLng[1]).then(function(nm){
        if (nm) infoEl.innerHTML = '<i class="bi bi-geo-alt"></i> '+nm;
      });
      try {
        status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mencari toko perlengkapan olahraga dalam radius '+(parseInt(radSel.value,10)/1000)+' km…';
        var items = await fetchShops(userLatLng[0], userLatLng[1], parseInt(radSel.value,10));
        renderList(items);
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = 'Ditemukan <b>'+items.length+'</b> toko perlengkapan olahraga di sekitar Anda. Klik baris untuk lihat rute, atau tombol hijau untuk buka di Google Maps.';
      } catch(e){
        status.className='alert alert-warning small py-2 mb-2';
        status.textContent='Gagal: '+e.message;
      }
    }, function(err){
      status.className='alert alert-danger small py-2 mb-2';
      status.textContent='Gagal mendapatkan lokasi: '+err.message;
    }, {enableHighAccuracy:true,timeout:10000,maximumAge:60000});
  }
  btnLoc.addEventListener('click', locateAndLoad);
  btnRef.addEventListener('click', locateAndLoad);
  radSel.addEventListener('change', function(){ if (userLatLng) locateAndLoad(); });

  // Auto-trigger jika user sudah mengizinkan lokasi sebelumnya.
  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({name:'geolocation'}).then(function(p){
      if (p.state === 'granted') locateAndLoad();
    }).catch(function(){});
  }
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

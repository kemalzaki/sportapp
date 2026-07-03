<?php
/**
 * lacak_faskes.php — Revisi R24 (28 Juni 2026)
 * Halaman tersendiri: Lacak Puskesmas / Rumah Sakit Terdekat.
 * Sumber: OpenStreetMap (Overpass API) + OSRM (rute) + Google Maps (deep-link rute).
 * Dipindahkan dari cedera_olahraga.php menjadi menu navigasi sendiri.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
require_once __DIR__.'/includes/paket_helpers.php';
require_login();
// Revisi R6 (Juli 2026) — Halaman ini dikunci untuk paket Pro & Komunitas.
paket_require_or_lock('pro', current_user(), 'Lacak Puskesmas / RS Terdekat');
$pageTitle = 'Lacak Puskesmas / Rumah Sakit Terdekat';
include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item"><a href="/cedera_olahraga.php">Cedera Olahraga</a></li>
    <li class="breadcrumb-item active">Lacak Puskesmas / RS Terdekat</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-hospital-fill text-danger"></i> Lacak Puskesmas / Rumah Sakit Terdekat</h2>
<p class="text-muted small mb-3">Aktifkan akses lokasi, lalu sistem mencari fasilitas kesehatan di sekitar Anda dari OpenStreetMap dan menyediakan tombol rute langsung ke Google Maps.</p>

<div class="alert alert-danger small">
  <i class="bi bi-telephone-fill"></i> <strong>Darurat medis:</strong> hubungi <strong>119</strong> (Gawat Darurat) atau <strong>118</strong> (Ambulans) bila perlu pertolongan segera.
</div>

<div class="card shadow-sm border-danger" id="rsTerdekat">
  <div class="card-header bg-danger-subtle text-danger-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-hospital-fill"></i> <strong>Puskesmas &amp; Rumah Sakit Terdekat</strong></span>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <select id="radSel" class="form-select form-select-sm" style="width:auto">
        <option value="2000">Radius 2 km</option>
        <option value="5000" selected>Radius 5 km</option>
        <option value="10000">Radius 10 km</option>
        <option value="25000">Radius 25 km</option>
      </select>
      <button type="button" id="btnLocate" class="btn btn-danger btn-sm"><i class="bi bi-crosshair"></i> Gunakan Lokasi Saya</button>
      <button type="button" id="btnRefreshRs" class="btn btn-outline-danger btn-sm" disabled><i class="bi bi-arrow-repeat"></i> Muat Ulang</button>
    </div>
  </div>
  <div class="card-body">
    <div id="rsStatus" class="alert alert-info small py-2 mb-2">Klik <b>Gunakan Lokasi Saya</b> untuk mulai.</div>
    <div id="rsInfo"   class="small text-muted mb-2"></div>
    <div id="rsMap" style="height:420px;border-radius:8px;overflow:hidden;background:#eef2f7"></div>
    <div id="rsList" class="list-group list-group-flush mt-3"></div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var map, userMarker, routeLayer, facilityLayer;
  var userLatLng = null;
  var status = document.getElementById('rsStatus');
  var infoEl = document.getElementById('rsInfo');
  var listEl = document.getElementById('rsList');
  var btnLoc = document.getElementById('btnLocate');
  var btnRef = document.getElementById('btnRefreshRs');
  var radSel = document.getElementById('radSel');

  function initMap(lat,lng){
    if (map){ map.setView([lat,lng],14); return; }
    map = L.map('rsMap').setView([lat,lng],14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);
    facilityLayer = L.layerGroup().addTo(map);
    routeLayer    = L.layerGroup().addTo(map);
  }
  function distKm(a,b){
    var R=6371, toRad=function(x){return x*Math.PI/180;};
    var dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    var s=Math.sin(dLat/2)**2+Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  async function reverseGeocode(lat,lng){
    try {
      var r = await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+lat+'&lon='+lng+'&accept-language=id',{headers:{'Accept':'application/json'}});
      var j = await r.json();
      return j.display_name || '';
    } catch(_){ return ''; }
  }
  async function fetchFacilities(lat,lng,radius){
    status.className='alert alert-info small py-2 mb-2';
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mencari fasilitas kesehatan dalam radius '+(radius/1000)+' km…';
    var q = '[out:json][timeout:25];(' +
            'node["amenity"~"hospital|clinic|doctors|pharmacy"](around:'+radius+','+lat+','+lng+');' +
            'way ["amenity"~"hospital|clinic|doctors|pharmacy"](around:'+radius+','+lat+','+lng+');' +
            'node["healthcare"~"hospital|clinic|doctor|centre"](around:'+radius+','+lat+','+lng+');' +
            ');out center 80;';
    var r = await fetch('https://overpass-api.de/api/interpreter',{method:'POST', body:q});
    var j = await r.json();
    var els = (j.elements||[]).map(function(e){
      var la = e.lat || (e.center && e.center.lat);
      var ln = e.lon || (e.center && e.center.lon);
      if (!la||!ln) return null;
      var t = e.tags||{};
      var nama = t.name || (t.amenity==='hospital'?'Rumah Sakit': t.amenity==='clinic'?'Klinik': t.amenity==='doctors'?'Praktik Dokter': t.amenity==='pharmacy'?'Apotek':'Fasilitas Kesehatan');
      return { lat:la, lng:ln, nama:nama, tipe:t.amenity||t.healthcare||'-', alamat:(t['addr:full']||t['addr:street']||''), telp:t.phone||t['contact:phone']||'' };
    }).filter(Boolean);
    els.forEach(function(e){ e.km = distKm([lat,lng],[e.lat,e.lng]); });
    els.sort(function(a,b){return a.km-b.km;});
    return els.slice(0,30);
  }
  function renderList(items){
    facilityLayer.clearLayers();
    listEl.innerHTML='';
    if (!items.length){ listEl.innerHTML='<div class="text-muted small p-2">Tidak ada fasilitas kesehatan ditemukan. Coba perbesar radius.</div>'; return; }
    items.forEach(function(it){
      var m = L.marker([it.lat,it.lng]).addTo(facilityLayer).bindPopup('<b>'+it.nama+'</b><br>'+it.tipe+'<br>'+it.km.toFixed(2)+' km');
      var icon = it.tipe==='hospital'?'bi-hospital-fill text-danger': it.tipe==='clinic'?'bi-bandaid-fill text-warning': it.tipe==='pharmacy'?'bi-capsule text-success':'bi-person-vcard text-info';
      var gmapUrl = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&origin='+userLatLng[0]+','+userLatLng[1]+'&destination='+it.lat+','+it.lng;
      var item = document.createElement('div');
      item.className='list-group-item d-flex justify-content-between align-items-start gap-2';
      item.innerHTML =
        '<div class="flex-grow-1" style="cursor:pointer" data-route="1">'+
          '<i class="bi '+icon+'"></i> <strong>'+it.nama+'</strong> '+
          '<span class="badge bg-secondary ms-1">'+it.tipe+'</span><br>'+
          '<small class="text-muted">'+(it.alamat||'Alamat tidak tersedia')+(it.telp?' · ☎ '+it.telp:'')+'</small>'+
        '</div>'+
        '<div class="text-end">'+
          '<span class="badge bg-danger rounded-pill mb-1 d-block">'+it.km.toFixed(2)+' km</span>'+
          '<a href="'+gmapUrl+'" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-1"><i class="bi bi-google"></i> Rute Google Maps</a>'+
        '</div>';
      item.querySelector('[data-route]').addEventListener('click', function(){ showRoute(it,m); });
      listEl.appendChild(item);
    });
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
        var line = L.geoJSON(rt.geometry,{style:{color:'#dc3545',weight:5,opacity:0.8}}).addTo(routeLayer);
        map.fitBounds(line.getBounds(),{padding:[40,40]});
        var km = (rt.distance/1000).toFixed(2);
        var menit = Math.round(rt.duration/60);
        var gmapUrl = 'https://www.google.com/maps/dir/?api=1&origin='+userLatLng[0]+','+userLatLng[1]+'&destination='+it.lat+','+it.lng;
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = '<i class="bi bi-signpost-2-fill"></i> Rute ke <b>'+it.nama+'</b>: <strong>'+km+' km</strong> · estimasi '+menit+' menit (berkendara). <a href="'+gmapUrl+'" target="_blank" class="ms-2">Buka di Google Maps</a>';
      } else throw new Error('Rute tidak ditemukan');
    } catch(err){
      status.className='alert alert-warning small py-2 mb-2';
      status.textContent='Gagal mengambil rute: '+err.message;
    }
  }
  async function locateAndLoad(){
    if (!navigator.geolocation){ status.textContent='Browser tidak mendukung Geolocation.'; return; }
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
        var items = await fetchFacilities(userLatLng[0], userLatLng[1], parseInt(radSel.value,10));
        renderList(items);
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = 'Ditemukan <b>'+items.length+'</b> fasilitas kesehatan. Klik baris untuk lihat rute, atau tombol hijau untuk buka di Google Maps.';
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
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

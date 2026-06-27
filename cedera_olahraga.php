<?php
// cedera_olahraga.php — Revisi 18 Juni 2026: + AI Health Tanya Jawab (simpan jawaban)
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php'; // R22 — gate KOMUNITAS
send_security_headers(); enforce_session_timeout();
require_login();
$pageTitle = 'Cedera Olahraga & Penanganan';
$u = current_user(); $uid = (int)$u['id'];

// Revisi R22 — Cedera Olahraga khusus paket KOMUNITAS
paket_require_or_lock('komunitas', $u, 'Cedera Olahraga & Penanganan',
    'Panduan cedera olahraga + pencarian Puskesmas/RS terdekat tersedia untuk paket Komunitas.');

// Revisi 18 Juni 2026 — tabel penyimpanan Q&A AI Health (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS health_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        kategori VARCHAR(20) NOT NULL DEFAULT 'health',
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS health_qa_user_idx ON health_qa_saved(user_id, kategori, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    header('Content-Type: application/json');
    $a = $_POST['_action'] ?? '';
    if ($a === 'qa_save') {
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban'] ?? ''));
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000) $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(), "INSERT INTO health_qa_saved(user_id,kategori,pertanyaan,jawaban) VALUES($1,'health',$2,$3) RETURNING id",
            [$uid,$q,$j]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    } elseif ($a === 'qa_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM health_qa_saved WHERE id=$1 AND user_id=$2 AND kategori='health'",[$id,$uid]);
        echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'err'=>'unknown']); exit;
}

$qaSaved = db_all("SELECT id, pertanyaan, jawaban, created_at FROM health_qa_saved
                   WHERE user_id=$1 AND kategori='health' ORDER BY id DESC LIMIT 50", [$uid]);

$ytId = function($s){
  $s = trim((string)$s);
  if ($s === '') return '';
  if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;
  if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $s, $m)) return $m[1];
  return '';
};

$CEDERA = [
  [
    'nama'=>'Keseleo / Sprain (Pergelangan Kaki/Tangan)',
    'icon'=>'bi-bandaid', 'warna'=>'warning',
    'gejala'=>['Nyeri tajam saat bergerak','Bengkak, memar','Sulit menahan beban'],
    'penanganan'=>[
      'Prinsip RICE: Rest (istirahatkan), Ice (kompres es 15-20 menit per 2 jam, 1-2 hari pertama).',
      'Compression: balut dengan elastic bandage (jangan terlalu kencang).',
      'Elevation: angkat bagian cedera lebih tinggi dari jantung.',
      'Jangan dipijat keras pada 48 jam pertama.',
      'Konsultasi dokter bila tidak bisa menumpu berat badan / nyeri parah > 3 hari.',
    ],
    'mitigasi'=>['Pemanasan dinamis 5-10 menit','Gunakan sepatu sesuai aktivitas','Latihan keseimbangan & propriosepsi','Hindari permukaan tidak rata'],
    'videos'=>[
      ['Penanganan Keseleo (RICE)','https://www.youtube.com/watch?v=pdBX5lKW-hg'],
      ['Latihan Rehab Ankle Sprain','https://www.youtube.com/watch?v=C5ettc0onck'],
    ],
  ],
  [
    'nama'=>'Kram Otot',
    'icon'=>'bi-lightning-charge', 'warna'=>'danger',
    'gejala'=>['Otot mengeras tiba-tiba','Nyeri menusuk','Sering di betis/paha belakang'],
    'penanganan'=>[
      'Hentikan aktivitas, regangkan otot perlahan ke arah berlawanan kontraksi.',
      'Pijat lembut, kompres hangat (atau dingin bila baru terjadi).',
      'Minum air + elektrolit (oralit/isotonik).',
    ],
    'mitigasi'=>['Hidrasi cukup sebelum & selama olahraga','Pemanasan & peregangan','Cukup elektrolit (natrium, kalium, magnesium)','Tidak overtraining'],
    'videos'=>[
      ['Cara Mengatasi Kram Otot','https://www.youtube.com/watch?v=rjxEfbBwj30'],
      ['Stretching Anti Kram Betis','https://www.youtube.com/watch?v=9twPWWIGu-o'],
    ],
  ],
  [
    'nama'=>'Strain Otot (Tarikan/Robekan Ringan)',
    'icon'=>'bi-activity', 'warna'=>'warning',
    'gejala'=>['Nyeri saat kontraksi','Bengkak/memar lokal','Kelemahan otot'],
    'penanganan'=>['RICE 48-72 jam pertama','Hindari aktivitas memicu nyeri','Setelah 3 hari mulai mobilisasi ringan & peregangan'],
    'mitigasi'=>['Pemanasan dinamis','Tingkatkan beban latihan bertahap (≤10%/minggu)','Latihan kekuatan rutin'],
    'videos'=>[
      ['Muscle Strain: Penyebab & Penanganan','https://www.youtube.com/watch?v=sGKHzfdzs2A'],
      ['Rehab Hamstring Strain','https://www.youtube.com/watch?v=hBcmkInzZKE'],
    ],
  ],
  [
    'nama'=>"Cedera Lutut (Runner's Knee)",
    'icon'=>'bi-person-walking', 'warna'=>'info',
    'gejala'=>['Nyeri di sekitar tempurung lutut','Bertambah sakit saat naik/turun tangga'],
    'penanganan'=>['Istirahat & kurangi beban','Ice 15-20 menit setelah aktivitas','Latihan penguat quadriceps & glutes','Konsultasi fisioterapi bila menetap'],
    'mitigasi'=>['Ganti sepatu lari tiap 500-800 km','Hindari menambah jarak >10%/minggu','Latihan core & hip strength'],
    'videos'=>[
      ["Apa itu Runner's Knee?",'https://www.youtube.com/watch?v=q59peAoaCSo'],
      ['5 Latihan Penguat Lutut','https://www.youtube.com/watch?v=ekdpK5FsqiY'],
    ],
  ],
  [
    'nama'=>'Lecet / Blister',
    'icon'=>'bi-droplet-half', 'warna'=>'secondary',
    'gejala'=>['Gelembung berisi cairan','Nyeri saat ditekan'],
    'penanganan'=>['Jangan dipecahkan kecuali sangat besar','Tutup dengan plester blister/hydrocolloid','Jaga kebersihan, ganti plester rutin'],
    'mitigasi'=>['Pakai kaus kaki olahraga (anti gesek)','Sepatu pas, tidak longgar','Gunakan vaseline pada titik gesekan'],
    'videos'=>[
      ['Cara Merawat Blister di Kaki','https://www.youtube.com/watch?v=s4Qj8w5wxDo'],
    ],
  ],
  [
    'nama'=>'Heat Exhaustion (Kelelahan Akibat Panas)',
    'icon'=>'bi-thermometer-sun', 'warna'=>'danger',
    'gejala'=>['Pusing, mual','Keringat berlebih','Kulit dingin & lembap','Denyut nadi cepat'],
    'penanganan'=>[
      'Pindah ke tempat sejuk/teduh, longgarkan pakaian.',
      'Minum air dingin / oralit perlahan.',
      'Kompres dingin di leher, ketiak, selangkangan.',
      'Bila tidak membaik 30 menit / suhu >40°C → segera ke IGD (waspada heat stroke).',
    ],
    'mitigasi'=>['Olahraga di pagi/sore','Hidrasi 500 ml 1 jam sebelumnya','Pakaian ringan & breathable','Aklimatisasi bertahap di cuaca panas'],
    'videos'=>[
      ['Heat Exhaustion vs Heat Stroke','https://www.youtube.com/watch?v=oynSAL8v8aY'],
      ['Pertolongan Pertama Kelelahan Panas','https://www.youtube.com/watch?v=Ev78iB4PE40'],
    ],
  ],
  [
    'nama'=>'Pingsan (Sinkop) Saat Olahraga',
    'icon'=>'bi-emoji-dizzy', 'warna'=>'danger',
    'gejala'=>['Pusing, pandangan gelap','Berkeringat dingin','Kehilangan kesadaran sesaat'],
    'penanganan'=>[
      'Baringkan korban telentang, angkat kaki ~30 cm (posisi syok).',
      'Longgarkan pakaian, pastikan jalan napas bebas.',
      'Jangan beri makan/minum saat masih belum sadar.',
      'Setelah sadar, beri air manis perlahan. Istirahatkan minimal 15-30 menit.',
      'Panggil bantuan medis bila tidak sadar >1 menit, kejang, dada nyeri, sesak napas, atau cedera kepala.',
      'CPR bila tidak ada napas/nadi (30 kompresi : 2 napas) — hubungi 119/118.',
    ],
    'mitigasi'=>[
      'Tidak olahraga berat saat sakit/demam/dehidrasi.',
      'Makan ringan 1-2 jam sebelum olahraga.',
      'Pemanasan & pendinginan bertahap (hindari berhenti mendadak).',
      'Periksa tensi/gula darah rutin bila punya riwayat hipotensi atau hipoglikemia.',
      'Awasi tanda kelelahan: hentikan bila pusing, dada berdebar, atau pandangan kabur.',
    ],
    'videos'=>[
      ['Pertolongan Pertama Pingsan','https://www.youtube.com/watch?v=wMiXQeV84AY'],
      ['CPR Dewasa (BHD)','https://www.youtube.com/watch?v=Rn6c6F88vc4'],
    ],
  ],
  [
    'nama'=>'Cedera Punggung Bawah',
    'icon'=>'bi-person-arms-up', 'warna'=>'info',
    'gejala'=>['Nyeri tumpul di pinggang','Sulit membungkuk'],
    'penanganan'=>['Istirahat aktif (tetap bergerak ringan)','Kompres dingin 48 jam pertama lalu hangat','Penguatan core & peregangan hamstring','Konsultasi bila menjalar ke kaki'],
    'mitigasi'=>['Teknik mengangkat yang benar (jongkok, bukan membungkuk)','Latihan core (plank, bird-dog)','Hindari beban berlebih'],
    'videos'=>[
      ['Latihan Aman untuk Nyeri Punggung Bawah','https://www.youtube.com/watch?v=e91iGG39KLM'],
      ['Stretching Low Back Pain','https://www.youtube.com/watch?v=ZpBOwQaxBZc'],
    ],
  ],
];

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Cedera Olahraga &amp; Penanganan</li>
  </ol>
</nav>

<h2 class="mb-1"><i class="bi bi-bandaid text-danger"></i> Cedera Olahraga &amp; Penanganan</h2>
<p class="text-muted small mb-3">Panduan ringkas — termasuk <strong>pingsan</strong> dan <strong>mitigasi sebelum cedera</strong>. Tidak menggantikan saran medis profesional.</p>

<div class="alert alert-warning small d-flex gap-2 align-items-start">
  <i class="bi bi-shield-exclamation fs-4"></i>
  <div><strong>Mitigasi Umum:</strong> pemanasan 5–10 menit, hidrasi cukup, sepatu sesuai aktivitas, peningkatan intensitas bertahap (≤10%/minggu), dengarkan tubuh — berhenti bila nyeri tajam atau pusing.</div>
</div>

<?php
// Revisi 18 Juni 2026 — Widget AI Health Tanya Jawab
$aiTitle = 'AI Health — Tanya Jawab Cedera & Penanganan';
$aiTask = 'ai_health';
$aiColor = 'danger';
$aiIcon = 'bi-heart-pulse';
$aiPlaceholder = 'Contoh: Bagaimana cara menangani keseleo pergelangan kaki saat lari? Atau: Apa beda strain dan sprain?';
$aiPostUrl = '/cedera_olahraga.php';
$aiSaved = $qaSaved;
$aiKey = 'aiHealth';
$aiDisclaim = 'Jawaban AI bersifat panduan umum — bukan pengganti pemeriksaan tenaga medis.';
include __DIR__.'/includes/ai_qa_widget.php';
?>

<div class="row g-3">
  <?php foreach($CEDERA as $idx=>$c): $cid='ced_'.$idx; ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm border-<?= $c['warna'] ?>">
        <!-- Revisi 22 Juni 2026 R7 — spoiler/collapse per item agar tidak memanjang ke bawah -->
        <button class="card-header bg-<?= $c['warna'] ?>-subtle text-<?= $c['warna'] ?>-emphasis d-flex justify-content-between align-items-center w-100 border-0 ced-spoiler-btn collapsed"
                type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>" aria-expanded="false" aria-controls="<?= $cid ?>"
                style="text-align:left;cursor:pointer;">
          <span><i class="bi <?= $c['icon'] ?>"></i> <strong><?= htmlspecialchars($c['nama']) ?></strong>
            <span class="small ms-2 d-none d-sm-inline opacity-75">— klik untuk buka/tutup</span>
          </span>
          <i class="bi bi-chevron-down ced-spoiler-caret"></i>
        </button>
        <div class="collapse" id="<?= $cid ?>">
        <div class="card-body">
          <div class="small mb-2"><strong>Gejala:</strong>
            <ul class="mb-2"><?php foreach($c['gejala'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ul>
          </div>
          <div class="small mb-2"><strong class="text-success">Penanganan:</strong>
            <ol class="mb-2"><?php foreach($c['penanganan'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ol>
          </div>
          <div class="small mb-3"><strong class="text-primary">Mitigasi (sebelum cedera):</strong>
            <ul class="mb-0"><?php foreach($c['mitigasi'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?></ul>
          </div>

          <?php if (!empty($c['videos'])): ?>
            <div class="small mb-1"><strong class="text-danger"><i class="bi bi-youtube"></i> Video Edukasi:</strong></div>
            <div class="row g-2">
              <?php foreach($c['videos'] as $v): $vid = $ytId($v[1]); if(!$vid) continue; ?>
                <div class="col-12 col-sm-6">
                  <div class="ratio ratio-16x9 rounded overflow-hidden border">
                    <iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($vid) ?>"
                      title="<?= htmlspecialchars($v[0]) ?>"
                      allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                  </div>
                  <div class="small text-muted mt-1 text-truncate" title="<?= htmlspecialchars($v[0]) ?>"><?= htmlspecialchars($v[0]) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        </div><!-- /.collapse -->
      </div>
    </div>
  <?php endforeach; ?>
</div>

<style>
.ced-spoiler-btn .ced-spoiler-caret{transition:transform .25s ease;}
.ced-spoiler-btn[aria-expanded="true"] .ced-spoiler-caret{transform:rotate(180deg);}
</style>

<div class="alert alert-danger mt-4 small">
  <i class="bi bi-telephone-fill"></i> <strong>Darurat medis:</strong> hubungi <strong>119</strong> (Layanan Gawat Darurat) atau <strong>118</strong> (Ambulans) bila terjadi tidak sadar &gt; 1 menit, sesak napas berat, nyeri dada, atau perdarahan tidak berhenti.
</div>



<!-- ============================================================
     Revisi R22 — Pencarian Otomatis Puskesmas / Rumah Sakit Terdekat
     Sumber: OpenStreetMap (Overpass API) + OSRM (rute), tanpa API key.
     ============================================================ -->
<div class="card shadow-sm mt-4 border-danger" id="rsTerdekat">
  <div class="card-header bg-danger-subtle text-danger-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-hospital-fill"></i> <strong>Puskesmas &amp; Rumah Sakit Terdekat</strong></span>
    <div class="btn-group btn-group-sm">
      <button type="button" id="btnLocate" class="btn btn-danger"><i class="bi bi-crosshair"></i> Gunakan Lokasi Saya</button>
      <button type="button" id="btnRefreshRs" class="btn btn-outline-danger" disabled><i class="bi bi-arrow-repeat"></i> Muat Ulang</button>
    </div>
  </div>
  <div class="card-body">
    <div class="small text-muted mb-2">
      Aktifkan akses lokasi browser, lalu sistem mencari fasilitas kesehatan dalam radius 5 km
      dan menampilkan rute (jarak dalam km) ke titik pilihan.
    </div>
    <div id="rsStatus" class="alert alert-info small py-2 mb-2">Klik <b>Gunakan Lokasi Saya</b> untuk mulai.</div>
    <div id="rsMap" style="height:380px;border-radius:8px;overflow:hidden;background:#eef2f7"></div>
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
  var listEl = document.getElementById('rsList');
  var btnLoc = document.getElementById('btnLocate');
  var btnRef = document.getElementById('btnRefreshRs');

  function initMap(lat,lng){
    if (map){ map.setView([lat,lng],14); return; }
    map = L.map('rsMap').setView([lat,lng],14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
      maxZoom:19, attribution:'&copy; OpenStreetMap'
    }).addTo(map);
    facilityLayer = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup().addTo(map);
  }
  function distKm(a,b){
    var R=6371, toRad=function(x){return x*Math.PI/180;};
    var dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    var s=Math.sin(dLat/2)**2+Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  async function fetchFacilities(lat,lng){
    status.className='alert alert-info small py-2 mb-2';
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mencari fasilitas kesehatan dalam radius 5 km…';
    var q = `[out:json][timeout:25];(
      node["amenity"~"hospital|clinic|doctors"](around:5000,${lat},${lng});
      way["amenity"~"hospital|clinic|doctors"](around:5000,${lat},${lng});
      node["healthcare"~"hospital|clinic|doctor"](around:5000,${lat},${lng});
    );out center 60;`;
    try {
      var r = await fetch('https://overpass-api.de/api/interpreter',
                {method:'POST', body:q});
      var j = await r.json();
      var els = (j.elements||[]).map(function(e){
        var la = e.lat || (e.center && e.center.lat);
        var ln = e.lon || (e.center && e.center.lon);
        if (!la||!ln) return null;
        var tags = e.tags||{};
        var nama = tags.name || (tags.amenity==='hospital'?'Rumah Sakit':
                  tags.amenity==='clinic'?'Klinik':
                  tags.amenity==='doctors'?'Praktik Dokter':'Fasilitas Kesehatan');
        return { lat:la, lng:ln, nama:nama, tipe: tags.amenity || tags.healthcare || '-',
                 alamat: (tags['addr:full']||tags['addr:street']||''),
                 telp: tags.phone || tags['contact:phone'] || '' };
      }).filter(Boolean);
      els.forEach(function(e){ e.km = distKm([lat,lng],[e.lat,e.lng]); });
      els.sort(function(a,b){return a.km-b.km;});
      return els.slice(0,15);
    } catch(err){
      throw new Error('Gagal mengakses Overpass API: '+err.message);
    }
  }
  function renderList(items){
    facilityLayer.clearLayers();
    listEl.innerHTML = '';
    if (!items.length){
      listEl.innerHTML = '<div class="text-muted small p-2">Tidak ada fasilitas kesehatan dalam radius 5 km.</div>';
      return;
    }
    items.forEach(function(it,i){
      var m = L.marker([it.lat,it.lng]).addTo(facilityLayer)
        .bindPopup('<b>'+it.nama+'</b><br>'+it.tipe+'<br>'+it.km.toFixed(2)+' km');
      var icon = it.tipe==='hospital' ? 'bi-hospital-fill text-danger' :
                 it.tipe==='clinic'   ? 'bi-bandaid-fill text-warning' :
                                        'bi-person-vcard text-info';
      var item = document.createElement('button');
      item.type='button';
      item.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      item.innerHTML =
        '<div><i class="bi '+icon+'"></i> <strong>'+it.nama+'</strong> '+
        '<span class="badge bg-secondary ms-1">'+it.tipe+'</span><br>'+
        '<small class="text-muted">'+(it.alamat||'Alamat tidak tersedia')+'</small></div>'+
        '<span class="badge bg-danger rounded-pill">'+it.km.toFixed(2)+' km</span>';
      item.addEventListener('click', function(){ showRoute(it,m); });
      listEl.appendChild(item);
    });
  }
  async function showRoute(it, marker){
    routeLayer.clearLayers();
    marker.openPopup();
    status.className='alert alert-info small py-2 mb-2';
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Menghitung rute ke <b>'+it.nama+'</b>…';
    try {
      var url='https://router.project-osrm.org/route/v1/driving/'+
        userLatLng[1]+','+userLatLng[0]+';'+it.lng+','+it.lat+
        '?overview=full&geometries=geojson';
      var r = await fetch(url);
      var j = await r.json();
      if (j.routes && j.routes[0]){
        var rt = j.routes[0];
        var line = L.geoJSON(rt.geometry,{style:{color:'#dc3545',weight:5,opacity:0.8}}).addTo(routeLayer);
        map.fitBounds(line.getBounds(),{padding:[40,40]});
        var km = (rt.distance/1000).toFixed(2);
        var menit = Math.round(rt.duration/60);
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = '<i class="bi bi-signpost-2-fill"></i> Rute ke <b>'+it.nama+'</b>: '+
          '<strong>'+km+' km</strong> · estimasi '+menit+' menit (berkendara). '+
          '<a href="https://www.google.com/maps/dir/?api=1&origin='+userLatLng[0]+','+userLatLng[1]+
          '&destination='+it.lat+','+it.lng+'" target="_blank" class="ms-2">Buka di Google Maps</a>';
      } else throw new Error('Rute tidak ditemukan');
    } catch(err){
      status.className='alert alert-warning small py-2 mb-2';
      status.textContent='Gagal mengambil rute: '+err.message;
    }
  }
  btnLoc.addEventListener('click', function(){
    if (!navigator.geolocation){ status.textContent='Browser tidak mendukung Geolocation.'; return; }
    status.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi lokasi…';
    navigator.geolocation.getCurrentPosition(async function(pos){
      userLatLng = [pos.coords.latitude, pos.coords.longitude];
      initMap(userLatLng[0], userLatLng[1]);
      if (userMarker) userMarker.remove();
      userMarker = L.marker(userLatLng, {title:'Lokasi Anda'}).addTo(map).bindPopup('📍 Lokasi Anda').openPopup();
      btnRef.disabled = false;
      try {
        var items = await fetchFacilities(userLatLng[0], userLatLng[1]);
        renderList(items);
        status.className='alert alert-success small py-2 mb-2';
        status.innerHTML = 'Ditemukan <b>'+items.length+'</b> fasilitas kesehatan di sekitar Anda. Klik salah satu untuk melihat rute.';
      } catch(e){
        status.className='alert alert-warning small py-2 mb-2';
        status.textContent=e.message;
      }
    }, function(err){
      status.className='alert alert-danger small py-2 mb-2';
      status.textContent='Gagal mendapatkan lokasi: '+err.message;
    },{enableHighAccuracy:true,timeout:10000,maximumAge:60000});
  });
  btnRef.addEventListener('click', function(){ if (userLatLng) btnLoc.click(); });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

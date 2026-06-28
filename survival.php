<?php
/**
 * survival.php — Revisi 19 Juni 2026 Part O #3
 * Survival Mode: AI interaksi (mirip islami.php) + pengetahuan survival hutan,
 * makanan boleh/tidak, mitigasi tersesat, dan SOP jika sudah tersesat.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/paket_helpers.php'; // R22 — gate KOMUNITAS
send_security_headers(); require_login();
$pageTitle = 'Survival Mode';
$u = current_user();

// Revisi R22 — Survival Mode khusus paket KOMUNITAS
paket_require_or_lock('komunitas', $u, 'Survival Mode',
    'Pengetahuan survival hutan, makanan boleh/tidak, dan AI Survival Coach tersedia untuk paket Komunitas.');

// Tabel penyimpanan Q&A Survival (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS survival_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS survival_qa_user_idx ON survival_qa_saved(user_id, created_at DESC)");
} catch (Throwable $e) {}

/* Revisi (28 Juni 2026) — Koleksi foto tambahan utk panel "Hewan Liar".
   Tabel idempotent menyimpan foto yang diunggah user per slug hewan. */
try {
    db_exec("CREATE TABLE IF NOT EXISTS hewan_liar_foto (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        slug TEXT NOT NULL,
        path TEXT NOT NULL,
        caption TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS hewan_liar_foto_slug_idx ON hewan_liar_foto(slug, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'qa_save') {
        header('Content-Type: application/json');
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban'] ?? ''));
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000)  $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(), "INSERT INTO survival_qa_saved(user_id,pertanyaan,jawaban) VALUES($1,$2,$3) RETURNING id",
            [(int)$u['id'],$q,$j]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    } elseif ($a === 'qa_delete') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM survival_qa_saved WHERE id=$1 AND user_id=$2",[$id,(int)$u['id']]);
        echo json_encode(['ok'=>true]); exit;
    } elseif ($a === 'hewan_foto_add') {
        /* Revisi (28 Juni 2026) — Tambah foto hewan liar (upload user). */
        header('Content-Type: application/json');
        $slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($_POST['slug'] ?? '')));
        $cap  = mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 240);
        if ($slug==='' || !isset($_FILES['foto'])) { echo json_encode(['ok'=>false,'err'=>'Slug/file kosong']); exit; }
        $f = $_FILES['foto'];
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'err'=>'Upload error']); exit; }
        if ($f['size'] > 5*1024*1024) { echo json_encode(['ok'=>false,'err'=>'Max 5MB']); exit; }
        $info = @getimagesize($f['tmp_name']);
        if (!$info) { echo json_encode(['ok'=>false,'err'=>'Bukan gambar']); exit; }
        $extMap = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp'];
        if (!isset($extMap[$info[2]])) { echo json_encode(['ok'=>false,'err'=>'Format harus JPG/PNG/WEBP']); exit; }
        $dir = __DIR__.'/uploads/hewan';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fname = 'user_'.$slug.'_'.(int)$u['id'].'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$extMap[$info[2]];
        $dest = $dir.'/'.$fname;
        if (!move_uploaded_file($f['tmp_name'], $dest)) { echo json_encode(['ok'=>false,'err'=>'Gagal menyimpan']); exit; }
        $rel = '/uploads/hewan/'.$fname;
        $r = pg_query_params(db(), "INSERT INTO hewan_liar_foto(user_id,slug,path,caption) VALUES($1,$2,$3,$4) RETURNING id",
            [(int)$u['id'], $slug, $rel, $cap]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id,'path'=>$rel,'caption'=>$cap]); exit;
    } elseif ($a === 'hewan_foto_del') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) {
            $r = pg_query_params(db(), "SELECT path FROM hewan_liar_foto WHERE id=$1 AND user_id=$2",[$id,(int)$u['id']]);
            $row = pg_fetch_assoc($r);
            if ($row) {
                $p = __DIR__.$row['path'];
                if (is_file($p)) @unlink($p);
                db_exec("DELETE FROM hewan_liar_foto WHERE id=$1 AND user_id=$2",[$id,(int)$u['id']]);
            }
        }
        echo json_encode(['ok'=>true]); exit;
    } elseif ($a === 'forest_ai') {
        /* Revisi R25 (28 Juni 2026) — Rekomendasi Hutan via AI (Gemini).
           Input: daftar kota/kabupaten (string dipisah koma). Output JSON:
           { items: [ {nama, kota, lat, lng, level (1-5), deskripsi} ] }. */
        header('Content-Type: application/json');
        require_once __DIR__.'/includes/ai_gemini.php';
        $citiesRaw = trim((string)($_POST['cities'] ?? ''));
        if ($citiesRaw==='') { echo json_encode(['ok'=>false,'err'=>'Daftar kota kosong']); exit; }
        if (mb_strlen($citiesRaw) > 600) $citiesRaw = mb_substr($citiesRaw, 0, 600);
        $cities = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $citiesRaw))));
        if (!$cities) { echo json_encode(['ok'=>false,'err'=>'Format kota tidak valid']); exit; }

        $prompt = "Anda adalah ahli survival hutan Indonesia. Untuk SETIAP kota/kabupaten berikut, "
                . "berikan 1–2 rekomendasi HUTAN atau kawasan lindung TERDEKAT yang relevan untuk latihan "
                . "survival (taman nasional, hutan lindung, cagar alam, hutan kota besar bila tidak ada hutan asli). "
                . "Sertakan koordinat GPS (lat,lng) yang akurat (desimal), serta level survival makanan 1–5 "
                . "(1 = sangat terbatas, 5 = sangat melimpah air/buah/ikan/umbi).\n\n"
                . "Daftar kota: " . implode(', ', $cities) . "\n\n"
                . "Balas HANYA dalam JSON valid (tanpa markdown), bentuk:\n"
                . "{\"items\":[{\"nama\":\"...\",\"kota\":\"...\",\"lat\":-6.7,\"lng\":106.5,"
                . "\"level\":4,\"deskripsi\":\"singkat (maks 140 char), sebut sumber makanan/air khas\"}]}";
        try {
            $txt = gemini_text($prompt, ['temperature'=>0.4, 'max_tokens'=>2048]);
            $data = gemini_extract_json($txt);
            $items = [];
            foreach (($data['items'] ?? []) as $it) {
                $lat = (float)($it['lat'] ?? 0); $lng = (float)($it['lng'] ?? 0);
                if ($lat==0 && $lng==0) continue;
                $items[] = [
                    'nama'      => (string)($it['nama'] ?? '-'),
                    'kota'      => (string)($it['kota'] ?? ''),
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'level'     => max(1, min(5, (int)($it['level'] ?? 3))),
                    'deskripsi' => (string)($it['deskripsi'] ?? ''),
                ];
            }
            if (!$items) { echo json_encode(['ok'=>false,'err'=>'AI tidak mengembalikan lokasi valid']); exit; }
            echo json_encode(['ok'=>true, 'items'=>$items, 'cities'=>$cities]); exit;
        } catch (Throwable $e) {
            echo json_encode(['ok'=>false,'err'=>'Gagal memanggil AI: '.$e->getMessage()]); exit;
        }
    }
}

$qaSaved = $u ? db_all("SELECT id, pertanyaan, jawaban, created_at FROM survival_qa_saved WHERE user_id=$1 ORDER BY id DESC LIMIT 50", [(int)$u['id']]) : [];

include __DIR__.'/includes/header.php';
?>

<div class="hero-sport-islami mb-3" style="background:linear-gradient(135deg,#14532d,#166534);color:#fff;border-radius:14px;padding:1.25rem">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
      <span class="badge bg-light text-success mb-2"><i class="bi bi-tree-fill"></i> SURVIVAL MODE</span>
      <h1 class="h3 mb-1 fw-bold">Bertahan Hidup di Alam Liar</h1>
      <p class="small mb-0 opacity-85">Pengetahuan dasar &amp; AI Survival Coach untuk pendaki, pelari trail, dan petualang outdoor.</p>
    </div>
    <div class="d-flex flex-column gap-2 align-items-end">
      <span class="badge bg-light text-dark fs-6 px-3 py-2"><i class="bi bi-telephone-fill"></i> Darurat: 115 (Basarnas) · 112</span>
      <?php /* Revisi 20 Juni 2026 — Tombol "Pesan Tour Guide" dihapus sesuai permintaan. */ ?>
    </div>
  </div>
</div>

<!-- ============================================================
     Revisi 19 Juni 2026 — Video Edukasi Survival (YouTube)
     ============================================================ -->
<div class="card shadow-sm mb-3">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <i class="bi bi-play-btn-fill"></i> <strong>Video Edukasi Survival</strong>
    <small class="text-muted ms-2">Tonton sebelum berangkat ke alam liar</small>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $survVideos = [
        ['AzZ2pqK7V9A','Cara Membangun Shelter Darurat','bi-house-heart'],
        ['rSKc15X6Bes','Cara Menyalakan Api Survival di Hutan','bi-fire'],
        ['N340_KCX5bY','Cara Mencari Makanan di Alam Liar','bi-basket'],
        ['jUlL3hc4qXE','Cara Menggunakan Navigasi Pohon (Wayfinding)','bi-tree'],
      ];
      foreach ($survVideos as $v): ?>
        <div class="col-md-6">
          <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
            <iframe src="https://www.youtube-nocookie.com/embed/<?= $v[0] ?>"
                    title="<?= htmlspecialchars($v[1]) ?>" loading="lazy"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
          </div>
          <div class="small mt-1"><i class="bi <?= $v[2] ?> text-success"></i>
            <strong><?= htmlspecialchars($v[1]) ?></strong>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- AI Survival Interaction -->
<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <i class="bi bi-robot"></i> <strong>AI Survival Interaction</strong> &mdash; tanya seputar bertahan hidup di hutan
  </div>
  <div class="card-body">
    <form id="survForm" class="vstack gap-2 mb-2">
      <textarea id="survInput" class="form-control" rows="3"
                placeholder="Contoh: Bagaimana cara membuat api tanpa korek di hutan basah? · Apa tanda sumber air aman diminum? · Saya tersesat di hutan, langkah pertama apa yang harus saya lakukan?" required></textarea>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-send"></i> Tanyakan</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="survClear"><i class="bi bi-eraser"></i> Bersihkan</button>
        <small class="text-muted ms-auto align-self-center">Jawaban AI bersifat panduan, BUKAN pengganti pelatihan SAR / dokter.</small>
      </div>
    </form>
    <div id="survOut" class="border rounded p-3 bg-body-tertiary small text-muted" style="min-height:80px">
      Tulis pertanyaan lalu klik <b>Tanyakan</b>. Contoh prompt: <em>"Saya tersasar saat trail running, baterai HP 10%, hari mulai gelap — apa yang harus dilakukan?"</em>
    </div>
    <div id="survActions" class="d-flex gap-2 mt-2" style="display:none !important">
      <button type="button" id="btnSimpanQAsv" class="btn btn-outline-success btn-sm"><i class="bi bi-bookmark-plus"></i> Simpan Q&amp;A ini</button>
      <span id="qaSaveStatSv" class="small text-muted align-self-center"></span>
    </div>

    <?php if ($u): ?>
    <div class="mt-3">
      <a class="small" data-bs-toggle="collapse" href="#qaSavedBoxSv" role="button" aria-expanded="false">
        <i class="bi bi-bookmark-star"></i> Tanya Jawab Tersimpan (<?= count($qaSaved) ?>)
      </a>
      <div class="collapse mt-2" id="qaSavedBoxSv">
        <?php if (!$qaSaved): ?>
          <div class="small text-muted">Belum ada Q&amp;A tersimpan. Klik <b>Simpan Q&amp;A ini</b> setelah AI menjawab.</div>
        <?php else: foreach ($qaSaved as $qa): ?>
          <div class="border rounded p-2 mb-2 small" data-qa-id="<?= (int)$qa['id'] ?>">
            <div class="d-flex justify-content-between">
              <strong class="text-success"><i class="bi bi-patch-question"></i> <?= htmlspecialchars(mb_strimwidth($qa['pertanyaan'],0,200,'…')) ?></strong>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 qa-del-btn-sv" data-id="<?= (int)$qa['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
            <div class="text-muted small mb-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($qa['created_at']))) ?></div>
            <details><summary class="text-primary">Lihat jawaban</summary>
              <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($qa['jawaban']) ?></div>
            </details>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================
     Revisi 19 Juni 2026 — Pencarian Video Survival (YouTube)
     Pola sama dengan artikel_olahraga.php (api_yt_search.php).
     ============================================================ -->
<div class="card shadow-sm mb-3 border-success ao-yt-box">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <i class="bi bi-youtube"></i> <strong>Pencarian Video Survival</strong>
    <small class="text-muted ms-2">Cari teknik / situasi tertentu di YouTube</small>
  </div>
  <div class="card-body">
    <div class="input-group input-group-sm mb-2">
      <input type="text" class="form-control ao-yt-q"
             placeholder="Contoh: cara membuat api dengan ferro rod, water filter darurat, navigasi tanpa kompas">
      <button type="button" class="btn btn-success ao-yt-btn"><i class="bi bi-search"></i> Cari &amp; Putar</button>
    </div>
    <div class="ao-yt-result small text-muted">Ketik kata kunci lalu klik <b>Cari &amp; Putar</b> — 5 video teratas akan tampil di sini.</div>
  </div>
</div>


<!-- ============================================================
     Revisi R25 (28 Juni 2026) — Rekomendasi Hutan via AI (Gemini)
     Sebelumnya: pilih provinsi dari array statis.
     Sekarang: user mengetik daftar kota/kabupaten (string, dipisah koma),
     AI menghasilkan rekomendasi hutan/kawasan lindung terdekat per kota
     lengkap dengan koordinat & level survival makanan.
     ============================================================ -->
<div class="card shadow-sm mb-3 border-success" id="forestFinder">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span><i class="bi bi-stars"></i> <strong>Rekomendasi Hutan (Saran AI)</strong></span>
      <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
        <i class="bi bi-cpu"></i> Powered by Gemini
      </span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 w-100">
      <input id="forestCities" class="form-control form-control-sm" style="min-width:260px;flex:1 1 280px"
             placeholder="Ketik daftar kota/kabupaten dipisah koma — cth: Bandung, Bogor, Sukabumi"
             value="Bandung, Bogor, Sukabumi">
      <button id="btnForestAI" class="btn btn-sm btn-success">
        <i class="bi bi-magic"></i> Generate via AI
      </button>
      <button id="btnFindNear" class="btn btn-sm btn-outline-success" title="Cari hutan/kawasan lindung di sekitar lokasi Anda">
        <i class="bi bi-geo-fill"></i> Cari dari Lokasi Saya
      </button>
      <select id="radiusSel" class="form-select form-select-sm" style="width:auto" title="Radius pencarian">
        <option value="10000">10 km</option>
        <option value="25000" selected>25 km</option>
        <option value="50000">50 km</option>
        <option value="100000">100 km</option>
      </select>
    </div>
  </div>
  <div class="card-body">
    <div class="small text-muted mb-2">Tingkat survival makanan: ⭐ semakin tinggi semakin mudah menemukan air, buah, ikan, dan tumbuhan dapat dimakan. Hasil AI bersifat saran — selalu verifikasi sebelum berangkat.</div>
    <div id="forestStatus" class="alert alert-info small py-2 mb-2">Masukkan daftar kota lalu klik <b>Generate via AI</b>.</div>
    <div id="forestMap" style="height:380px;border-radius:8px;overflow:hidden;background:#eef2f7"></div>
    <div id="forestList" class="list-group list-group-flush mt-3"></div>
  </div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.__CSRF = <?= json_encode(csrf_token()) ?>;
(function(){
  var map, layer;
  function init(lat,lng,zoom){
    if (!map){
      map = L.map('forestMap').setView([lat,lng], zoom||7);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);
      layer = L.layerGroup().addTo(map);
    } else { map.setView([lat,lng], zoom||7); }
  }
  function renderItems(items, label){
    var st   = document.getElementById('forestStatus');
    var list = document.getElementById('forestList');
    list.innerHTML='';
    if (!items.length){ st.className='alert alert-warning small py-2 mb-2'; st.textContent='AI tidak mengembalikan lokasi.'; return; }
    init(items[0].lat, items[0].lng, 7);
    if (layer) layer.clearLayers();
    var bounds = [];
    items.forEach(function(f){
      var lvl = f.level||3;
      var stars='⭐'.repeat(lvl)+'☆'.repeat(5-lvl);
      var color = lvl>=4?'#198754':(lvl===3?'#fd7e14':'#dc3545');
      L.circleMarker([f.lat,f.lng],{radius:10,color:color,fillColor:color,fillOpacity:0.65})
        .addTo(layer)
        .bindPopup('<b>'+f.nama+'</b><br><small>'+(f.kota||'')+'</small><br>'+stars+' (Survival '+lvl+'/5)<br><small>'+(f.deskripsi||'')+'</small>');
      bounds.push([f.lat,f.lng]);
      var btn=document.createElement('div');
      btn.className='list-group-item';
      btn.innerHTML='<div class="d-flex justify-content-between align-items-start gap-2">'+
        '<div><i class="bi bi-tree-fill" style="color:'+color+'"></i> <strong>'+f.nama+'</strong>'+
        (f.kota?' <span class="text-muted small">— '+f.kota+'</span>':'')+'<br>'+
        '<small class="text-muted">'+(f.deskripsi||'')+'</small></div>'+
        '<span class="badge bg-success-subtle text-success-emphasis flex-shrink-0">'+stars+'</span></div>';
      list.appendChild(btn);
    });
    if (bounds.length>1) map.fitBounds(bounds,{padding:[40,40]});
    st.className='alert alert-success small py-2 mb-2';
    st.innerHTML='AI menemukan <b>'+items.length+'</b> rekomendasi untuk: <b>'+label+'</b>.';
  }

  document.getElementById('btnForestAI').addEventListener('click', async function(){
    var st   = document.getElementById('forestStatus');
    var btn  = this;
    var cities = (document.getElementById('forestCities').value||'').trim();
    if (!cities){ st.className='alert alert-warning small py-2 mb-2'; st.textContent='Isi minimal 1 kota.'; return; }
    btn.disabled=true; var oldH=btn.innerHTML; btn.innerHTML='<i class="bi bi-hourglass-split"></i> AI sedang menyiapkan…';
    st.className='alert alert-info small py-2 mb-2'; st.textContent='AI sedang mencari hutan terdekat untuk: '+cities+'…';
    try {
      var fd = new FormData();
      fd.append('_action','forest_ai');
      fd.append('csrf', window.__CSRF);
      fd.append('cities', cities);
      var r = await fetch('/survival.php',{method:'POST',body:fd,credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok){ st.className='alert alert-danger small py-2 mb-2'; st.textContent='Gagal: '+(j.err||'unknown'); return; }
      renderItems(j.items, (j.cities||[]).join(', '));
    } catch(e){
      st.className='alert alert-danger small py-2 mb-2'; st.textContent='Kesalahan jaringan: '+e.message;
    } finally { btn.disabled=false; btn.innerHTML=oldH; }
  });

  /* Cari dari lokasi terkini (Overpass) — tetap dipertahankan dari R23. */
  function distKm(a,b){
    var R=6371, toRad=function(x){return x*Math.PI/180;};
    var dLat=toRad(b[0]-a[0]), dLng=toRad(b[1]-a[1]);
    var s=Math.sin(dLat/2)**2+Math.cos(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  document.getElementById('btnFindNear').addEventListener('click', function(){
    var st   = document.getElementById('forestStatus');
    var list = document.getElementById('forestList');
    var rad  = parseInt(document.getElementById('radiusSel').value||'25000',10);
    if (!navigator.geolocation){ st.className='alert alert-danger small py-2 mb-2'; st.textContent='Browser tidak mendukung Geolocation.'; return; }
    st.className='alert alert-info small py-2 mb-2';
    st.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi lokasi…';
    navigator.geolocation.getCurrentPosition(async function(pos){
      var ulat=pos.coords.latitude, ulng=pos.coords.longitude;
      init(ulat,ulng);
      if (layer) layer.clearLayers();
      L.marker([ulat,ulng]).addTo(layer).bindPopup('📍 Lokasi Anda').openPopup();
      st.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mencari hutan/kawasan dalam radius '+(rad/1000)+' km…';
      var q='[out:json][timeout:25];('+
            'way["natural"="wood"](around:'+rad+','+ulat+','+ulng+');'+
            'way["landuse"="forest"](around:'+rad+','+ulat+','+ulng+');'+
            'relation["boundary"="protected_area"](around:'+rad+','+ulat+','+ulng+');'+
            'relation["boundary"="national_park"](around:'+rad+','+ulat+','+ulng+');'+
            ');out center 40;';
      try {
        var r = await fetch('https://overpass-api.de/api/interpreter',{method:'POST',body:q});
        var j = await r.json();
        var els = (j.elements||[]).map(function(e){
          var la=e.lat||(e.center&&e.center.lat); var ln=e.lon||(e.center&&e.center.lon);
          if(!la||!ln) return null;
          var tg=e.tags||{};
          var nama = tg.name || (tg.boundary==='national_park'?'Taman Nasional':
                     tg.boundary==='protected_area'?'Kawasan Lindung':
                     tg.natural==='wood'?'Hutan':'Hutan/Kawasan');
          var tipe = tg.boundary || tg.natural || tg.landuse || 'forest';
          return {nama:nama,lat:la,lng:ln,tipe:tipe};
        }).filter(Boolean);
        els.forEach(function(e){ e.km = distKm([ulat,ulng],[e.lat,e.lng]); });
        els.sort(function(a,b){return a.km-b.km;});
        els = els.slice(0,25);
        list.innerHTML='';
        if (!els.length){
          st.className='alert alert-warning small py-2 mb-2';
          st.textContent='Tidak ditemukan hutan/kawasan lindung dalam radius '+(rad/1000)+' km. Coba perbesar radius.';
          return;
        }
        var bnds=[[ulat,ulng]];
        els.forEach(function(it){
          var color = it.tipe==='national_park'?'#198754':(it.tipe==='protected_area'?'#0d6efd':'#20c997');
          L.circleMarker([it.lat,it.lng],{radius:9,color:color,fillColor:color,fillOpacity:0.6})
            .addTo(layer).bindPopup('<b>'+it.nama+'</b><br>'+it.tipe+'<br>'+it.km.toFixed(2)+' km');
          bnds.push([it.lat,it.lng]);
          var btn=document.createElement('div');
          btn.className='list-group-item d-flex justify-content-between align-items-center';
          btn.innerHTML='<div><i class="bi bi-tree-fill" style="color:'+color+'"></i> <strong>'+
            it.nama+'</strong> <span class="badge bg-secondary ms-1">'+it.tipe+'</span><br>'+
            '<small class="text-muted">'+it.km.toFixed(2)+' km dari Anda</small></div>'+
            '<a class="btn btn-sm btn-success" target="_blank" rel="noopener" '+
            'href="https://www.google.com/maps/dir/?api=1&travelmode=driving&origin='+
            ulat+','+ulng+'&destination='+it.lat+','+it.lng+'">'+
            '<i class="bi bi-google"></i> Rute</a>';
          list.appendChild(btn);
        });
        if (bnds.length>1) map.fitBounds(bnds,{padding:[40,40]});
        st.className='alert alert-success small py-2 mb-2';
        st.innerHTML='Ditemukan <b>'+els.length+'</b> lokasi hutan/kawasan lindung di sekitar Anda (radius '+(rad/1000)+' km). Klik <b>Rute</b> untuk arahan via Google Maps.';
      } catch(e){
        st.className='alert alert-danger small py-2 mb-2';
        st.textContent='Gagal mengakses Overpass API: '+e.message;
      }
    }, function(err){
      st.className='alert alert-danger small py-2 mb-2';
      st.textContent='Gagal mendapatkan lokasi: '+err.message;
    },{enableHighAccuracy:true,timeout:10000,maximumAge:60000});
  });
})();
</script>

<!-- Pengetahuan Survival di Hutan (Revisi R22 — spoiler/collapse) -->
<div class="alert alert-success-subtle small py-2 mb-2"><i class="bi bi-info-circle"></i>
  Klik judul kartu untuk membuka/menutup isi.</div>
<div class="row g-3 surv-spoilers">
  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-success">
      <div class="card-header"><i class="bi bi-tree text-success"></i> <strong>Pengetahuan Dasar Survival di Hutan</strong></div>
      <div class="card-body">
        <p class="small text-muted mb-2">Prinsip <strong>STOP</strong> (Stop, Think, Observe, Plan) &amp; aturan <strong>3-3-3</strong>:
          3 menit tanpa udara, 3 jam tanpa naungan di cuaca ekstrem, 3 hari tanpa air, 3 minggu tanpa makanan.
          Atur prioritas dengan urutan tsb.</p>
        <ul class="small mb-2">
          <li><b>Shelter</b>: gunakan ponco/tenda darurat di bawah pohon besar, jauh dari sungai (banjir bandang) &amp; pohon mati.</li>
          <li><b>Api</b>: kumpulkan tinder kering (serbuk kayu lapuk, kulit kayu, daun pinus), kindling kecil, fuel besar. Pakai
            ferro rod / korek tahan air. Buat reflektor batang kayu agar panas memantul ke shelter.</li>
          <li><b>Air</b>: cari aliran air mengalir → SARING (kain) → REBUS minimal 1 menit pada didih kuat (3 menit di ketinggian &gt;2.000 m).
            Hindari air diam berbusa / berbau / dekat bangkai hewan.</li>
          <li><b>Sinyal</b>: 3 ledakan peluit / 3 kepulan asap / 3 nyala api = sinyal SOS internasional. Cermin/HP refleksi
            ke arah pesawat di siang hari.</li>
          <li><b>Navigasi</b>: matahari terbit Timur — terbenam Barat. Lumut tumbuh lebih tebal di sisi pohon yang lembap
            (umumnya selatan di Indonesia). Tetap di jalur, jangan turun ke jurang.</li>
        </ul>
        <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle"></i>
          Hindari sungai sebagai jalur turun saat hujan — risiko air bah. Pilih punggungan (ridge).
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-warning">
      <div class="card-header"><i class="bi bi-egg-fried text-warning"></i> <strong>Makanan: Boleh vs Dilarang</strong></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-12">
            <div class="small fw-semibold text-success mb-1"><i class="bi bi-check2-circle"></i> Umumnya AMAN dimakan (Indonesia)</div>
            <!-- Revisi 19 Juni 2026 — Ilustrasi makanan AMAN (AI-generated) -->
            <img src="/assets/img/survival/makanan_aman.jpg" alt="Contoh makanan aman di hutan: pisang hutan, pakis muda, rebung, jambu hutan, ikan sungai, belalang"
                 class="img-fluid rounded shadow-sm mb-2 border border-success-subtle" loading="lazy">
            <ul class="small mb-2">
              <li>Pisang hutan (buah &amp; jantung), pakis muda (digodok), bambu muda (rebung) — buang air rebusan pertama.</li>
              <li>Daun selada air liar di tepi sungai bersih — rebus dulu.</li>
              <li>Buah jambu hutan, markisa hutan, kelapa muda, salak hutan.</li>
              <li>Ikan kecil sungai, belalang &amp; jangkrik (buang sayap &amp; kaki, panggang sampai matang).</li>
              <li>Cacing tanah (rendam &amp; rebus untuk membersihkan).</li>
            </ul>
          </div>
          <div class="col-12">
            <div class="small fw-semibold text-danger mb-1"><i class="bi bi-x-octagon"></i> JANGAN dimakan / waspada tinggi</div>
            <!-- Revisi 19 Juni 2026 — Ilustrasi makanan BERBAHAYA (AI-generated) -->
            <img src="/assets/img/survival/makanan_bahaya.jpg" alt="Contoh makanan berbahaya di hutan: jamur beracun, kodok berwarna mencolok, jarak pagar, bangkai hewan"
                 class="img-fluid rounded shadow-sm mb-2 border border-danger-subtle" loading="lazy">
            <ul class="small mb-0">
              <li>Jamur liar berwarna mencolok (merah, kuning cerah, putih bercak) — banyak yang mematikan, sulit dibedakan untuk awam. <b>Lewati.</b></li>
              <li>Buah bergetah putih susu, biji yang sangat pahit, atau yang membuat bibir kebas — tanda alkaloid beracun.</li>
              <li>Tanaman dengan duri tajam &amp; getah lengket (misal jarak pagar) — beracun.</li>
              <li>Hewan amfibi berwarna mencolok (kodok panah, kodok bufo) — racun di kulit.</li>
              <li>Daging hewan yang ditemukan sudah mati — risiko bakteri &amp; penyakit zoonosis.</li>
            </ul>
          </div>
          <div class="col-12">
            <div class="alert alert-info small mb-0">
              <b>Tes Edibilitas Universal (darurat, 24 jam):</b> oles ke kulit pergelangan → tunggu 15 menit; bila tidak gatal,
              oles ke bibir → tunggu 15 menit; bila aman, kunyah sedikit, jangan ditelan dulu → tunggu 15 menit; bila tidak ada reaksi,
              telan sangat sedikit, tunggu <b>8 jam</b> sebelum makan lebih banyak.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-primary">
      <div class="card-header"><i class="bi bi-compass text-primary"></i> <strong>Mitigasi Agar Tidak Tersesat</strong></div>
      <div class="card-body">
        <ul class="small mb-0">
          <li>Beritahu rencana perjalanan (waktu berangkat, jalur, estimasi pulang) ke 2 orang berbeda + grup komunitas.</li>
          <li>Bawa <b>peta offline</b> (mis. Maps.me, Locus, AlpineQuest) + kompas analog. Jangan andalkan GPS HP saja.</li>
          <li>Aktifkan <a href="/live_tracking.php">Live Tracking</a> SportApp sebelum masuk hutan — kontak darurat dapat melihat posisi terakhir.</li>
          <li>Bawa <b>survival kit</b>: peluit, ferro rod, pisau lipat, headlamp + baterai cadangan, ponco, garam, tablet purifikasi air, P3K.</li>
          <li>Setiap 15 menit, balik badan &amp; rekam <b>mental snapshot</b> jalur (untuk navigasi pulang).</li>
          <li>Tandai jalur dengan <b>flagging tape</b> biodegradable di percabangan; jangan rusak vegetasi (gunakan kembali saat pulang).</li>
          <li>Kembali sebelum gelap. Jika hujan deras tiba, berhenti &amp; berlindung — JANGAN paksa jalan.</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Revisi R26 (28 Juni 2026) — Menu BARU: Penanganan terhadap Hewan Liar.
       Diletakkan tepat di bawah "Mitigasi Agar Tidak Tersesat" agar urut
       (mitigasi → penanganan hewan → jika tersesat). -->
  <div class="col-12">
    <div class="card shadow-sm border-warning">
      <div class="card-header bg-warning-subtle text-warning-emphasis">
        <i class="bi bi-bug-fill"></i> <strong>Penanganan terhadap Hewan Liar</strong>
        <span class="small text-muted ms-2">(panduan singkat per jenis hewan)</span>
      </div>
      <div class="card-body">
        <div class="alert alert-warning small py-2 mb-3">
          <i class="bi bi-shield-exclamation"></i>
          Prinsip umum: <b>JANGAN lari</b> (memicu insting kejar), <b>JANGAN tatap mata langsung</b>
          pada predator besar, mundur perlahan menghadap hewan, lindungi leher &amp; kepala.
          Bawa pluit, headlamp terang, dan obat anti-bisa ular polivalen bila masuk hutan dalam.
        </div>
        <div class="row g-2">
          <?php
          /* Revisi (28 Juni 2026) — Tambah kolom slug + foto bawaan (digenerate Lovable, disimpan di /uploads/hewan/<slug>.jpg). */
          $hewan = [
            ['ular','Ular berbisa (kobra, weling, viper hijau)','bi-virus','danger',
              'Jangan dipukul/dikejar. Diam mematung, biarkan ular menjauh. JANGAN ikat tourniquet atau sayat luka. Imobilisasi anggota tubuh yang digigit (lebih rendah dari jantung), evakuasi ke faskes untuk SABU (Serum Anti Bisa Ular) polivalen Bio Farma. Foto ular bila aman untuk identifikasi.'],
            ['babi_hutan','Babi hutan / celeng','bi-piggy-bank','warning',
              'Sangat agresif jika ada anaknya. Naik pohon/batu setinggi >2 m. Jangan lari lurus — zig-zag. Bila terpaksa konfrontasi, gunakan ranting panjang & teriak keras menjaga jarak.'],
            ['beruang_madu','Beruang madu (sun bear)','bi-emoji-dizzy','danger',
              'Hindari kontak mata, mundur perlahan sambil bicara tenang. Jangan panjat pohon (beruang madu pandai memanjat). Bila diserang: tiarap meringkuk, lindungi tengkuk dengan tangan, diam — beruang sering pergi setelah merasa "menang".'],
            ['harimau','Harimau / macan tutul','bi-x-octagon-fill','danger',
              'Berdiri tegak, angkat tangan/jaket agar terlihat besar. JANGAN berbalik / lari. Pelan-pelan mundur sambil tetap menghadap. Hindari berjongkok (tampak seperti mangsa). Bila menyerang: lawan dengan apa pun — pukul moncong & mata.'],
            ['buaya','Buaya muara (di sungai/rawa)','bi-water','danger',
              'Jangan mandi/cuci di tepi sungai berlumpur saat senja-malam. Bila bertemu di darat: lari zig-zag menjauh ≥10 m (buaya cepat tapi pendek nafas). Bila diserang di air: serang mata & lubang hidung, sumbat reflek tutup matanya.'],
            ['monyet','Monyet ekor panjang / lutung','bi-emoji-laughing','info',
              'Jangan menatap mata & jangan tunjukkan makanan/botol. Lepaskan tas/topi bila direbut — jangan tarik ulur. Suara tegas & langkah mantap menjauh; hindari senyum (gigi terlihat = ancaman).'],
            ['tawon','Lebah/tawon liar (vespa, tawon ndas)','bi-bug','warning',
              'Bila satu-dua mengikuti: jalan tenang menjauh, jangan menepuk. Bila kawanan menyerang: tutup wajah, lari lurus menjauh ke semak rapat / masuk air dangkal. Sengatan banyak (>20) = darurat anafilaktik, segera ke faskes.'],
            ['lintah','Lintah & pacet','bi-droplet-half','secondary',
              'JANGAN ditarik (kepala bisa tertinggal → infeksi). Taburi garam, tembakau, atau tetesi minyak kayu putih — lintah lepas sendiri. Bersihkan luka dengan antiseptik, tutup plester.'],
            ['kalajengking','Kalajengking & lipan besar','bi-bug-fill','warning',
              'Sengatan menyakitkan tapi jarang fatal pada spesies Indonesia. Cuci luka dengan sabun, kompres dingin, minum parasetamol. Awasi gejala alergi (sesak, bengkak wajah) → segera ke faskes.'],
            ['ajak','Anjing hutan / ajak (dhole)','bi-emoji-angry','danger',
              'Berkelompok 4–10 ekor. Jangan lari. Berdiri tegak, lempar batu/ranting ke arah salah satu, mundur ke pohon/tebing agar punggung terlindung. Nyalakan api bila bisa — anjing liar takut api.'],
            ['komodo','Komodo / biawak besar (Flores, NTT)','bi-emoji-frown-fill','danger',
              'Jaga jarak minimal 5 m. Jalan mundur perlahan, jangan berlari (lari = mangsa). Bila tergigit: cuci luka dengan air mengalir + antiseptik kuat (air liur penuh bakteri patogen), segera evakuasi ke RS untuk antibiotik IV.'],
            ['gajah','Gajah liar (Sumatera/Kalimantan)','bi-emoji-expressionless','danger',
              'Hindari posisi antara induk & anak. Bila gajah mengibaskan telinga & menggenjot kaki = peringatan. Mundur menyamping ke balik pohon besar; gajah sulit berbelok cepat. Jangan menatap mata.'],
            ['nyamuk','Nyamuk Anopheles (malaria) & Aedes (DBD)','bi-droplet','info',
              'Pakai baju lengan panjang warna terang + repelen DEET 20–30%. Tidur pakai kelambu. Pulang trekking, awasi 14 hari: demam tinggi mendadak → cek darah malaria/DBD di puskesmas terdekat.'],
          ];

          /* Ambil semua foto user (semua slug sekaligus) agar tidak n+1 query. */
          $fotoBySlug = [];
          try {
              $rs = pg_query(db(), "SELECT id, slug, path, caption, user_id FROM hewan_liar_foto ORDER BY created_at DESC LIMIT 500");
              while ($rs && ($row = pg_fetch_assoc($rs))) {
                  $fotoBySlug[$row['slug']][] = $row;
              }
          } catch (Throwable $e) {}
          $myId = (int)($u['id'] ?? 0);
          $csrf = csrf_token();

          foreach ($hewan as $h):
            [$slug,$nama,$icon,$warna,$desc] = $h;
            $imgMain = '/uploads/hewan/'.$slug.'.jpg';
            $fotos = $fotoBySlug[$slug] ?? [];
          ?>
            <div class="col-md-6">
              <div class="border rounded p-2 h-100 hewan-card" data-slug="<?= htmlspecialchars($slug) ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi <?= $icon ?> text-<?= $warna ?> fs-5"></i>
                  <strong class="small flex-grow-1"><?= htmlspecialchars($nama) ?></strong>
                  <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 hewan-toggle-add" title="Tambah foto">
                    <i class="bi bi-plus-circle"></i> Tambah
                  </button>
                </div>
                <div class="ratio ratio-4x3 rounded overflow-hidden border mb-2 bg-body-tertiary">
                  <img src="<?= $imgMain ?>" alt="<?= htmlspecialchars($nama) ?>"
                       loading="lazy" style="object-fit:cover;width:100%;height:100%"
                       onerror="this.style.display='none'">
                </div>
                <div class="small text-muted mb-2"><?= $desc ?></div>

                <details class="mb-2">
                  <summary class="small fw-semibold text-primary" style="cursor:pointer">
                    <i class="bi bi-images"></i> Koleksi Foto
                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1 hewan-count"><?= count($fotos) ?></span>
                  </summary>
                  <div class="row g-1 mt-2 hewan-gallery">
                    <?php if (!$fotos): ?>
                      <div class="col-12 small text-muted fst-italic hewan-empty">Belum ada foto tambahan dari pengguna.</div>
                    <?php else: foreach ($fotos as $ft): ?>
                      <div class="col-4" data-foto-id="<?= (int)$ft['id'] ?>">
                        <div class="ratio ratio-1x1 rounded overflow-hidden border position-relative">
                          <a href="<?= htmlspecialchars($ft['path']) ?>" target="_blank" rel="noopener">
                            <img src="<?= htmlspecialchars($ft['path']) ?>" loading="lazy" alt="<?= htmlspecialchars($ft['caption'] ?? '') ?>"
                                 style="object-fit:cover;width:100%;height:100%">
                          </a>
                          <?php if ($myId && (int)$ft['user_id'] === $myId): ?>
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 px-1 hewan-del-btn"
                                    data-id="<?= (int)$ft['id'] ?>" title="Hapus foto saya">
                              <i class="bi bi-x"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($ft['caption'])): ?>
                          <div class="small text-muted text-truncate" title="<?= htmlspecialchars($ft['caption']) ?>">
                            <?= htmlspecialchars($ft['caption']) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </details>

                <form class="hewan-add-form border-top pt-2 mt-1" style="display:none" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="_action" value="hewan_foto_add">
                  <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                  <div class="mb-1">
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-1">
                    <input type="text" name="caption" maxlength="240" placeholder="Keterangan singkat (opsional)" class="form-control form-control-sm">
                  </div>
                  <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">
                      <i class="bi bi-upload"></i> Upload
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary hewan-cancel-add">Batal</button>
                    <span class="small ms-2 hewan-add-stat text-muted align-self-center"></span>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-telephone-fill"></i> <b>Hotline darurat satwa &amp; SAR:</b>
          Basarnas <b>115</b> · Polisi <b>110</b> · BKSDA setempat (cek nomor lokal sebelum berangkat).
          Bawa P3K berisi: antiseptik povidon iodin, kasa steril, plester, perban elastis untuk imobilisasi gigitan ular,
          antihistamin (cetirizine), epinephrine auto-injector bila alergi sengatan.
        </div>
      </div>
    </div>
  </div>


  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-danger">
      <div class="card-header"><i class="bi bi-exclamation-octagon-fill text-danger"></i> <strong>Jika Sudah Tersesat — Lakukan Ini</strong></div>
      <div class="card-body">
        <ol class="small mb-2">
          <li><b>STOP.</b> Tarik napas. JANGAN panik dan JANGAN terus berjalan — 80% korban hilang ditemukan lebih jauh karena terus bergerak acak.</li>
          <li><b>Hubungi 115 (Basarnas) atau 112</b> jika ada sinyal. Kirim koordinat HP (Google Maps → bagikan lokasi → WhatsApp). Hemat baterai: aktifkan mode pesawat saat tidak digunakan.</li>
          <li><b>Tetap di tempat terbuka</b> yang mudah terlihat dari udara (lapangan kecil, tepi sungai lebar). Tim SAR mencari sesuai jalur terakhir yang dilaporkan.</li>
          <li><b>Sinyal</b>: tiga bunyi peluit pendek berulang setiap 1 menit. Susun batu/ranting membentuk <b>SOS</b> atau <b>panah</b> ke arah pergerakan terakhir Anda.</li>
          <li><b>Buat shelter sebelum gelap</b>: cari naungan, tinggikan badan dari tanah (alas daun/ranting), tutupi dengan ponco untuk menghindari hipotermia.</li>
          <li><b>Air dulu</b>, makanan belakangan. Cari air mengalir → saring → rebus. Tubuh dapat bertahan 3 hari tanpa air, 3 minggu tanpa makanan.</li>
          <li>Saat helikopter / drone SAR mendekat: lambaikan kain berwarna terang, bukan kain hijau (sulit terlihat di hutan).</li>
        </ol>
        <div class="alert alert-danger small mb-0">
          <b>Hipotermia</b> (menggigil hebat, bingung) = darurat. Ganti pakaian basah, nyalakan api, pelukan kontak kulit ke kulit jika ada rekan.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var form = document.getElementById('survForm');
  var inp  = document.getElementById('survInput');
  var out  = document.getElementById('survOut');
  var actions = document.getElementById('survActions');
  var btnSimpan = document.getElementById('btnSimpanQAsv');
  var qaStat = document.getElementById('qaSaveStatSv');
  if (!form) return;
  var isLoading = false, lastQ='', lastA='', lastSavedKey='';
  document.getElementById('survClear').addEventListener('click', function(){
    inp.value=''; out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Tulis pertanyaan lalu klik Tanyakan.';
    if (actions) actions.style.display='none'; lastQ=''; lastA=''; lastSavedKey='';
  });
  form.addEventListener('submit', async function(e){
    e.preventDefault(); if (isLoading) return;
    var q = (inp.value||'').trim(); if (!q) return;
    if (q === lastQ && lastA){ qaStat.textContent = 'Pertanyaan sama — gunakan jawaban sebelumnya.'; return; }
    isLoading = true;
    var btn = form.querySelector('button[type=submit]'); var oh = btn.innerHTML;
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> AI menjawab...';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Sedang menjawab... (hanya 1x kirim, mohon tunggu)';
    if (actions) actions.style.display='none';
    try {
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('task','tanya_survival');
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Gagal: '+(j.err||'?'); }
      else {
        out.className='border rounded p-3 bg-body-tertiary';
        var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
        out.innerHTML = '<p>'+html+'</p>';
        lastQ = q; lastA = j.text || '';
        if (actions) actions.style.display='flex'; qaStat.textContent='';
      }
    } catch(err){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Error: '+err.message; }
    btn.disabled=false; btn.innerHTML=oh; isLoading=false;
  });

  if (btnSimpan) btnSimpan.addEventListener('click', async function(){
    if (!lastQ || !lastA) return;
    var key = lastQ+'|'+lastA.substring(0,32);
    if (key === lastSavedKey){ qaStat.textContent='Sudah disimpan sebelumnya.'; return; }
    btnSimpan.disabled = true;
    var fd = new FormData();
    fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','qa_save');
    fd.append('pertanyaan', lastQ); fd.append('jawaban', lastA);
    try {
      var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ lastSavedKey = key; qaStat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Tersimpan (#'+j.id+').'; }
      else qaStat.textContent = 'Gagal menyimpan.';
    } catch(e){ qaStat.textContent='Error: '+e.message; }
    btnSimpan.disabled = false;
  });

  document.querySelectorAll('.qa-del-btn-sv').forEach(function(b){
    b.addEventListener('click', async function(){
      if (!confirm('Hapus Q&A ini?')) return;
      var id = b.dataset.id;
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','qa_delete'); fd.append('id', id);
      var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ var el = document.querySelector('[data-qa-id="'+id+'"]'); if(el) el.remove(); }
    });
  });

  /* ===== Pencarian Video YouTube (Revisi 19 Juni 2026) ===== */
  document.querySelectorAll('.ao-yt-box').forEach(function(box){
    var btn = box.querySelector('.ao-yt-btn');
    var inp = box.querySelector('.ao-yt-q');
    var out = box.querySelector('.ao-yt-result');
    if (!btn || !inp || !out) return;
    function esc(s){ return String(s).replace(/[<>&"']/g,function(c){return ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'})[c];});}
    async function doSearch(){
      var q = (inp.value||'').trim(); if (!q) return;
      out.innerHTML = '<div class="d-flex align-items-center gap-2 small text-muted py-2"><span class="spinner-border spinner-border-sm"></span> Mencari video di YouTube…</div>';
      var oldHtml = btn.innerHTML; btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari…';
      try {
        var r = await fetch('/api_yt_search.php?cat=survival&q=' + encodeURIComponent(q), {credentials:'same-origin'});
        var j = await r.json();
        if (!j.ok) throw new Error(j.err || 'tidak ada hasil');
        var ids = (j.ids && j.ids.length) ? j.ids : (j.video ? [j.video] : []);
        if (!ids.length) throw new Error('tidak ada hasil');
        ids = ids.slice(0,5);
        var html = '<div class="small text-muted mb-2">Menampilkan <b>'+ids.length+'</b> video teratas untuk <b>'+esc(q)+'</b>:</div><div class="row g-2">';
        ids.forEach(function(vid, i){
          html += '<div class="col-12 col-md-6"><div class="ratio ratio-16x9 rounded overflow-hidden border">'+
            '<iframe loading="lazy" allowfullscreen src="https://www.youtube-nocookie.com/embed/'+encodeURIComponent(vid)+'?rel=0" '+
            'allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>'+
            '</div><div class="small text-muted mt-1">#'+(i+1)+' Hasil teratas</div></div>';
        });
        html += '</div>';
        out.innerHTML = html;
      } catch(e){
        out.innerHTML = '<div class="small text-danger py-2"><i class="bi bi-exclamation-triangle"></i> Gagal mencari: '+esc(e.message||String(e))+'. Coba kata kunci lain.</div>';
      } finally {
        btn.disabled = false; btn.innerHTML = oldHtml;
      }
    }
    btn.addEventListener('click', doSearch);
    inp.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); doSearch(); }});
  });
})();
</script>


<style>
.surv-spoilers .card .card-header{cursor:pointer; user-select:none;}
.surv-spoilers .card .card-header::after{content:"\25BC"; float:right; transition:transform .25s ease; font-size:.8em;}
.surv-spoilers .card.collapsed .card-header::after{transform:rotate(-90deg);}
.surv-spoilers .card.collapsed .card-body{display:none;}
</style>
<script>
(function(){
  document.querySelectorAll('.surv-spoilers > div > .card').forEach(function(c, i){
    if (i>0) c.classList.add('collapsed'); // default: kartu pertama terbuka
    var h = c.querySelector('.card-header');
    if (!h) return;
    h.addEventListener('click', function(){ c.classList.toggle('collapsed'); });
  });
})();
</script>

<script>
/* Revisi R24 (28 Juni 2026) — Cari hutan/kawasan per KOTA/KABUPATEN via Nominatim. */
(function(){
  var btn=document.getElementById('btnFindCity');
  if(!btn) return;
  btn.addEventListener('click', async function(){
    var st=document.getElementById('forestStatus');
    var q=(document.getElementById('forestCity').value||'').trim();
    if(!q){ st.className='alert alert-warning small py-2 mb-2'; st.textContent='Ketik nama kota terlebih dulu.'; return; }
    st.className='alert alert-info small py-2 mb-2';
    st.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mencari koordinat "'+q+'"…';
    try{
      var u='https://nominatim.openstreetmap.org/search?format=json&limit=1&accept-language=id&q='+encodeURIComponent(q+', Indonesia');
      var r=await fetch(u,{headers:{'Accept':'application/json'}});
      var j=await r.json();
      if(!j||!j.length) throw new Error('Kota tidak ditemukan');
      var lat=parseFloat(j[0].lat), lng=parseFloat(j[0].lon);
      // Override geolocation sekali pakai lalu trigger tombol "Cari dari Lokasi Saya".
      var orig=navigator.geolocation.getCurrentPosition.bind(navigator.geolocation);
      navigator.geolocation.getCurrentPosition=function(ok){ ok({coords:{latitude:lat,longitude:lng,accuracy:50}}); };
      document.getElementById('btnFindNear').click();
      setTimeout(function(){ navigator.geolocation.getCurrentPosition=orig; }, 1500);
    }catch(e){
      st.className='alert alert-danger small py-2 mb-2';
      st.textContent='Gagal mencari kota: '+e.message;
    }
  });
})();
</script>
<script>
/* Revisi (28 Juni 2026) — Koleksi Foto Hewan Liar: upload & hapus via fetch. */
(function(){
  function esc(s){ return String(s||'').replace(/[<>&"']/g,function(c){return ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'})[c];});}
  document.querySelectorAll('.hewan-card').forEach(function(card){
    var btnAdd  = card.querySelector('.hewan-toggle-add');
    var btnCcl  = card.querySelector('.hewan-cancel-add');
    var form    = card.querySelector('.hewan-add-form');
    var gallery = card.querySelector('.hewan-gallery');
    var emptyEl = gallery ? gallery.querySelector('.hewan-empty') : null;
    var countEl = card.querySelector('.hewan-count');
    var stat    = card.querySelector('.hewan-add-stat');

    if (btnAdd) btnAdd.addEventListener('click', function(){
      if (!form) return;
      form.style.display = (form.style.display==='none' || !form.style.display) ? 'block' : 'none';
    });
    if (btnCcl) btnCcl.addEventListener('click', function(){
      form.reset(); form.style.display='none'; if (stat) stat.textContent='';
    });

    if (form) form.addEventListener('submit', async function(e){
      e.preventDefault();
      var fd = new FormData(form);
      stat.textContent = 'Mengunggah…'; stat.className='small ms-2 hewan-add-stat text-muted align-self-center';
      var btn = form.querySelector('button[type=submit]');
      btn.disabled = true;
      try {
        var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
        var j = await r.json();
        if (!j.ok) throw new Error(j.err || 'Gagal');
        if (emptyEl) { emptyEl.remove(); }
        var col = document.createElement('div');
        col.className = 'col-4';
        col.dataset.fotoId = j.id;
        col.innerHTML = '<div class="ratio ratio-1x1 rounded overflow-hidden border position-relative">'+
          '<a href="'+esc(j.path)+'" target="_blank" rel="noopener">'+
            '<img src="'+esc(j.path)+'" loading="lazy" alt="'+esc(j.caption||'')+'" style="object-fit:cover;width:100%;height:100%">'+
          '</a>'+
          '<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 px-1 hewan-del-btn" data-id="'+j.id+'" title="Hapus foto saya"><i class="bi bi-x"></i></button>'+
          '</div>' + (j.caption ? '<div class="small text-muted text-truncate" title="'+esc(j.caption)+'">'+esc(j.caption)+'</div>' : '');
        gallery.prepend(col);
        bindDel(col.querySelector('.hewan-del-btn'));
        if (countEl) countEl.textContent = (parseInt(countEl.textContent||'0',10)+1);
        stat.textContent='Terunggah ✓'; stat.classList.add('text-success');
        form.reset();
      } catch(err){
        stat.textContent='Gagal: '+err.message; stat.classList.add('text-danger');
      }
      btn.disabled = false;
    });

    function bindDel(b){
      if (!b) return;
      b.addEventListener('click', async function(){
        if (!confirm('Hapus foto ini?')) return;
        var id = b.dataset.id;
        var fd = new FormData();
        fd.append('csrf','<?= csrf_token() ?>');
        fd.append('_action','hewan_foto_del');
        fd.append('id', id);
        try {
          var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
          var j = await r.json();
          if (j.ok){
            var col = b.closest('[data-foto-id]');
            if (col) col.remove();
            if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent||'1',10)-1);
            if (gallery && !gallery.querySelector('[data-foto-id]')){
              var em = document.createElement('div');
              em.className='col-12 small text-muted fst-italic hewan-empty';
              em.textContent='Belum ada foto tambahan dari pengguna.';
              gallery.appendChild(em);
            }
          }
        } catch(e){}
      });
    }
    card.querySelectorAll('.hewan-del-btn').forEach(bindDel);
  });
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

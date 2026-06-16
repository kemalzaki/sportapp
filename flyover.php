<?php
/**
 * Revisi 15 Juni 2026 — Video Animasi Rute 3D (Flyover)
 *
 * Mengubah rute GPS dari `run_sessions` + `run_points` menjadi animasi 3D
 * sinematik dari udara, lalu MEREKAM canvas peta menjadi file video .webm
 * di sisi browser (MediaRecorder API). Tidak ada job server / encoding di
 * back-end — cocok untuk dijalankan di local tanpa setup tambahan.
 *
 * Library:
 *   - MapLibre GL JS  (peta vektor 3D, gratis, tanpa API key)
 *   - maplibre-gl-rtl-text  TIDAK dipakai
 *   - Style dasar memakai "demotiles" MapLibre + opsi raster OSM.
 *
 * Catatan kejujuran:
 *   - "3D" di sini = pitch kamera + bearing yang berputar mengikuti rute,
 *     dengan opsi terrain bila pengguna memilih style yang mendukung.
 *     Style demotiles tidak memuat terrain hi-res; untuk hasil mirip Relive
 *     yang penuh terrain, pengguna bisa mengganti `STYLE_URL` di bawah ke
 *     style MapTiler/Mapbox milik mereka (butuh API key).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Video Flyover 3D';

@db_exec("CREATE TABLE IF NOT EXISTS flyover_renders (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    run_session_id BIGINT, judul TEXT NOT NULL DEFAULT 'Flyover Route',
    durasi_detik INTEGER NOT NULL DEFAULT 20, style_preset TEXT NOT NULL DEFAULT 'satellite',
    file_url TEXT, created_at TIMESTAMP NOT NULL DEFAULT now()
)");

$sessions = db_all("SELECT id, COALESCE(NULLIF(catatan,''), 'Sesi #'||id) AS nama, jarak_m, mulai_at
                    FROM run_sessions WHERE user_id=$1 ORDER BY id DESC LIMIT 30", [$uid]);

include __DIR__.'/includes/header.php';
?>
<link href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" rel="stylesheet">
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<style>
/* Revisi 16 Juni 2026 — Tampilan video flyover lebih menarik: HUD popup, ikon start/finish/km, kontrol musik */
.fly-wrap{position:relative}
.fly-hud{position:absolute;left:14px;top:14px;z-index:5;background:linear-gradient(135deg,rgba(15,23,42,.85),rgba(30,41,59,.72));
  color:#f8fafc;border-radius:14px;padding:12px 16px;backdrop-filter:blur(8px);
  box-shadow:0 10px 30px rgba(0,0,0,.35);font-family:ui-sans-serif,system-ui;min-width:200px;
  border:1px solid rgba(255,255,255,.15);transform:translateY(-8px);opacity:0;transition:.4s cubic-bezier(.2,.8,.2,1)}
.fly-hud.show{opacity:1;transform:translateY(0)}
.fly-hud h6{margin:0 0 6px;font-size:.78rem;letter-spacing:.5px;color:#fbbf24;text-transform:uppercase;display:flex;align-items:center;gap:6px}
.fly-hud .row-stat{display:flex;justify-content:space-between;gap:14px;font-size:.85rem;line-height:1.55}
.fly-hud .row-stat span:first-child{opacity:.7}
.fly-hud .row-stat strong{color:#fff;font-variant-numeric:tabular-nums}
.fly-badge{position:absolute;right:14px;top:14px;z-index:5;background:rgba(239,68,68,.92);color:#fff;
  padding:6px 10px;border-radius:999px;font-size:.72rem;font-weight:700;letter-spacing:.5px;display:none;
  box-shadow:0 4px 14px rgba(239,68,68,.5)}
.fly-badge.show{display:inline-flex;align-items:center;gap:6px;animation:pulseRec 1.2s infinite}
@keyframes pulseRec{50%{box-shadow:0 0 0 6px rgba(239,68,68,.18)}}
.fly-icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;
  font-size:1rem;color:#fff;box-shadow:0 4px 10px rgba(0,0,0,.35);border:2px solid #fff;transform:translate(-50%,-50%)}
.fly-icon.start{background:#10b981}
.fly-icon.finish{background:#1f2937;background-image:repeating-conic-gradient(#000 0 25%,#fff 0 50%);background-size:10px 10px}
.fly-icon.km{width:24px;height:24px;background:#f59e0b;font-size:.7rem;font-weight:700;color:#1f2937;border-color:#fff7ed}
.fly-icon.runner{background:#3b82f6;width:38px;height:38px;font-size:1.1rem;border-color:#dbeafe}
.fly-popup{position:absolute;left:50%;bottom:18px;transform:translateX(-50%) translateY(20px);z-index:5;
  background:rgba(17,24,39,.92);color:#fff;padding:10px 16px;border-radius:12px;font-size:.85rem;
  opacity:0;transition:.4s;pointer-events:none;border:1px solid rgba(255,255,255,.15)}
.fly-popup.show{opacity:1;transform:translateX(-50%) translateY(0)}
.fly-popup b{color:#fde68a}
</style>

<h4 class="mb-1"><i class="bi bi-camera-reels text-info"></i> Video Animasi Rute 3D (Flyover)</h4>
<p class="text-muted small mb-3">
  Ubah hasil tracking olahraga Anda menjadi video sinematik dari udara.
  Pilih sesi, atur durasi &amp; gaya, lalu klik <b>Rekam Video</b>.
  Video <code>.webm</code> akan otomatis ter-download saat selesai.
</p>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-sliders"></i> Konfigurasi</h6>
        <label class="form-label small">Pilih Sesi Lari</label>
        <select class="form-select form-select-sm mb-2" id="selSession">
          <option value="">— pilih sesi —</option>
          <?php foreach ($sessions as $s): ?>
            <option value="<?= (int)$s['id'] ?>">
              <?= htmlspecialchars($s['nama']) ?> · <?= number_format(((float)$s['jarak_m'])/1000,2) ?> km · <?= htmlspecialchars(substr($s['mulai_at'],0,16)) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="form-label small mt-2">Durasi Video (detik)</label>
        <input type="range" id="dur" min="8" max="40" value="18" class="form-range">
        <div class="small text-muted text-end mb-2"><span id="durOut">18</span> detik</div>

        <label class="form-label small">Gaya Peta</label>
        <select class="form-select form-select-sm mb-2" id="styleSel">
          <option value="mapbox-outdoors" selected>Mapbox Outdoors (Strava-like)</option>
          <option value="mapbox-satellite">Mapbox Satellite Streets</option>
          <option value="raster-osm">OpenStreetMap</option>
          <option value="voyager">Carto Voyager (cerah, detail)</option>
          <option value="light">Carto Light (minimalis terang)</option>
          <option value="dark">Carto Dark (gelap)</option>
          <option value="satellite">Satelit (Esri World Imagery)</option>
          <option value="topo">OpenTopoMap (kontur)</option>
          <option value="terrain">Stamen Terrain</option>
          <option value="watercolor">Stamen Watercolor (artistik)</option>
          <option value="cycle">CyclOSM (jalur sepeda/lari)</option>
          <option value="demo">MapLibre Demotiles (vektor)</option>
        </select>

        <label class="form-label small">Pitch Kamera</label>
        <input type="range" id="pitch" min="40" max="75" value="65" class="form-range">
        <div class="small text-muted text-end mb-2"><span id="pitchOut">65°</span></div>

        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="trailDraw" checked>
          <label class="form-check-label small" for="trailDraw">Gambar lintasan sambil kamera bergerak</label>
        </div>

        <!-- Revisi 16 Juni 2026 — opsi musik latar & ikon meriah -->
        <div class="form-check form-switch mb-1">
          <input class="form-check-input" type="checkbox" id="optIcons" checked>
          <label class="form-check-label small" for="optIcons">Tampilkan ikon Start/Finish & marker per-km</label>
        </div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="optHud" checked>
          <label class="form-check-label small" for="optHud">Tampilkan popup statistik (HUD) saat playback</label>
        </div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="optMusic">
          <label class="form-check-label small" for="optMusic"><i class="bi bi-music-note-beamed text-success"></i> Musik latar saat playback &amp; rekaman</label>
        </div>
        <div id="musicBox" class="mb-2" style="display:none">
          <input type="file" id="musicFile" class="form-control form-control-sm" accept="audio/*">
          <small class="text-muted d-block mt-1">Atau biarkan kosong → pakai musik bawaan (instrumental upbeat, gratis).</small>
          <audio id="musicAudio" preload="auto" controls class="w-100 mt-2" style="height:34px"></audio>
        </div>

        <hr>
        <button id="btnPreview" class="btn btn-outline-primary w-100 mb-2" disabled><i class="bi bi-play-circle"></i> Preview Animasi</button>
        <button id="btnRecord"  class="btn btn-danger w-100" disabled><i class="bi bi-record-circle"></i> Rekam Video (.webm)</button>
        <div class="small text-muted mt-2" id="recStat">Menunggu pilihan sesi…</div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body p-2">
        <div class="fly-wrap">
          <div id="map3d" style="height:560px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
          <!-- Revisi 16 Juni 2026 — HUD overlay -->
          <div id="flyHud" class="fly-hud">
            <h6><i class="bi bi-broadcast"></i> Live Flyover</h6>
            <div class="row-stat"><span><i class="bi bi-rulers"></i> Jarak</span><strong id="hudDist">0.00 km</strong></div>
            <div class="row-stat"><span><i class="bi bi-stopwatch"></i> Waktu</span><strong id="hudTime">0.0 s</strong></div>
            <div class="row-stat"><span><i class="bi bi-speedometer2"></i> Kecepatan</span><strong id="hudSpeed">— km/j</strong></div>
            <div class="row-stat"><span><i class="bi bi-flag"></i> Progres</span><strong id="hudProg">0%</strong></div>
          </div>
          <div id="flyRec" class="fly-badge"><i class="bi bi-record-circle-fill"></i> REC</div>
          <div id="flyPopup" class="fly-popup"></div>
        </div>
        <div class="small text-muted mt-2 px-2">
          <i class="bi bi-info-circle"></i>
          Browser akan menggunakan WebGL untuk merender peta. Pastikan tab
          aktif selama proses perekaman. Jika musik diaktifkan, audio juga ikut terekam ke video.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* ============================================================
   Util
   ============================================================ */
function rasterStyle(tiles, attr){
  return { version:8, sources:{ x:{ type:'raster', tiles:tiles, tileSize:256, attribution:attr } }, layers:[ { id:'x', type:'raster', source:'x' } ] };
}
const MAPBOX_TOKEN_JS = 'pk.eyJ1IjoiYWRhbXNhc21pdGE1MzQiLCJhIjoiY21xZnRsbWxjMXZldDJ0cHlhN2Jycnd1dCJ9.2E00ey-sgX9jUmf5kIRoEA';
const STYLES = {
  'mapbox-outdoors': rasterStyle(['https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/tiles/256/{z}/{x}/{y}@2x?access_token='+MAPBOX_TOKEN_JS], '&copy; Mapbox &copy; OSM'),
  'mapbox-satellite': rasterStyle(['https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v12/tiles/256/{z}/{x}/{y}@2x?access_token='+MAPBOX_TOKEN_JS], '&copy; Mapbox &copy; OSM'),
  'raster-osm': rasterStyle(['https://a.tile.openstreetmap.org/{z}/{x}/{y}.png','https://b.tile.openstreetmap.org/{z}/{x}/{y}.png','https://c.tile.openstreetmap.org/{z}/{x}/{y}.png'], '© OpenStreetMap'),
  'demo':  'https://demotiles.maplibre.org/style.json',
  'dark':  rasterStyle(['https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png','https://b.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png'], '© Carto © OSM'),
  'light': rasterStyle(['https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png','https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png'], '© Carto © OSM'),
  'voyager': rasterStyle(['https://a.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png','https://b.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png'], '© Carto © OSM'),
  'satellite': rasterStyle(['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'], 'Tiles © Esri'),
  'topo': rasterStyle(['https://a.tile.opentopomap.org/{z}/{x}/{y}.png','https://b.tile.opentopomap.org/{z}/{x}/{y}.png'], '© OpenTopoMap (CC-BY-SA)'),
  'terrain': rasterStyle(['https://stamen-tiles.a.ssl.fastly.net/terrain/{z}/{x}/{y}.png'], '© Stamen Design © OSM'),
  'watercolor': rasterStyle(['https://stamen-tiles.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.jpg'], '© Stamen Design © OSM'),
  'cycle': rasterStyle(['https://a.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png'], '© CyclOSM © OSM')
};

const $ = id => document.getElementById(id);
$('dur').oninput   = e => $('durOut').textContent   = e.target.value;
$('pitch').oninput = e => { $('pitchOut').textContent = e.target.value+'°'; if(map) map.setPitch(+e.target.value); };

let map, routePts = [], sessionId = null;
let kmMarkers = [], startMarker = null, finishMarker = null, runnerMarker = null;

/* Revisi 16 Juni 2026 — util ikon DOM untuk start/finish/km/runner */
function makeIcon(cls, html, lngLat){
  const el = document.createElement('div');
  el.className = 'fly-icon '+cls;
  el.innerHTML = html;
  return new maplibregl.Marker({element:el, anchor:'center'}).setLngLat(lngLat).addTo(map);
}
function clearMarkers(){
  kmMarkers.forEach(m=>m.remove()); kmMarkers=[];
  if (startMarker){startMarker.remove();startMarker=null;}
  if (finishMarker){finishMarker.remove();finishMarker=null;}
  if (runnerMarker){runnerMarker.remove();runnerMarker=null;}
}
function haversineKm(a,b){
  const R=6371, dLat=(b[0]-a[0])*Math.PI/180, dLng=(b[1]-a[1])*Math.PI/180;
  const s=Math.sin(dLat/2)**2+Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
  return 2*R*Math.asin(Math.sqrt(s));
}
function buildKmMarkers(){
  if (!$('optIcons').checked || routePts.length<2) return;
  startMarker  = makeIcon('start',  '<i class="bi bi-flag-fill"></i>', [routePts[0][1], routePts[0][0]]);
  finishMarker = makeIcon('finish', '', [routePts[routePts.length-1][1], routePts[routePts.length-1][0]]);
  // KM marker tiap 1 km
  let cum = 0, nextKm = 1;
  for (let i=1;i<routePts.length;i++){
    cum += haversineKm(routePts[i-1], routePts[i]);
    while (cum >= nextKm){
      kmMarkers.push(makeIcon('km', String(nextKm), [routePts[i][1], routePts[i][0]]));
      nextKm++;
    }
  }
}

function buildMap(styleKey){
  clearMarkers();
  if (map) { map.remove(); map = null; }
  map = new maplibregl.Map({
    container:'map3d',
    style: STYLES[styleKey],
    center:[106.8, -6.2], zoom: 11, pitch: +$('pitch').value, bearing: 0,
    preserveDrawingBuffer: true   // WAJIB agar canvas bisa di-capture
  });
  map.addControl(new maplibregl.NavigationControl());
  map.on('load', () => {
    map.addSource('rt', { type:'geojson', data:{ type:'Feature', geometry:{ type:'LineString', coordinates:[] } } });
    map.addLayer({ id:'rt-line', type:'line', source:'rt',
      paint:{ 'line-color':'#ef4444', 'line-width':5, 'line-opacity':0.95 } });
    map.addLayer({ id:'rt-glow', type:'line', source:'rt',
      paint:{ 'line-color':'#fde68a', 'line-width':12, 'line-blur':8, 'line-opacity':0.4 } });
    if (routePts.length) { drawAll(); buildKmMarkers(); }
  });
}
buildMap('mapbox-outdoors');
$('styleSel').onchange = e => buildMap(e.target.value);

function drawAll(){
  const coords = routePts.map(p=>[p[1], p[0]]); // [lng,lat]
  map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: coords } });
  const lats = routePts.map(p=>p[0]), lngs = routePts.map(p=>p[1]);
  map.fitBounds([[Math.min(...lngs), Math.min(...lats)],[Math.max(...lngs), Math.max(...lats)]], { padding:60, duration:0 });
}

/* Revisi 16 Juni 2026 — Musik latar */
$('optMusic').onchange = e => { $('musicBox').style.display = e.target.checked ? '' : 'none'; setupMusicSrc(); };
$('musicFile').addEventListener('change', setupMusicSrc);
function setupMusicSrc(){
  const a = $('musicAudio');
  const f = $('musicFile').files[0];
  if (f){ a.src = URL.createObjectURL(f); }
  else if (!a.src){
    // Default: instrumental upbeat dari Pixabay (lisensi gratis untuk komersial).
    a.src = 'https://cdn.pixabay.com/download/audio/2022/03/15/audio_8e3a8af6c4.mp3?filename=energetic-indie-rock-30sec-117279.mp3';
    a.crossOrigin = 'anonymous';
  }
  a.loop = true; a.load();
}

/* HUD helpers */
function showHud(on){ $('flyHud').classList.toggle('show', !!on && $('optHud').checked); }
function setHud(distKm, tSec, totalKm){
  $('hudDist').textContent  = distKm.toFixed(2)+' km';
  $('hudTime').textContent  = tSec.toFixed(1)+' s';
  const sp = tSec>0 ? (distKm/(tSec/3600)) : 0;
  $('hudSpeed').textContent = sp.toFixed(1)+' km/j';
  const pct = totalKm>0 ? Math.min(100, (distKm/totalKm)*100) : 0;
  $('hudProg').textContent  = pct.toFixed(0)+'%';
}
function popupSay(html){
  const p = $('flyPopup'); p.innerHTML = html; p.classList.add('show');
  clearTimeout(popupSay._t); popupSay._t = setTimeout(()=>p.classList.remove('show'), 2200);
}

/* ============================================================
   Load sesi → ambil titik dari api_run.php
   ============================================================ */
$('selSession').addEventListener('change', async (e) => {
  sessionId = +e.target.value || null;
  $('btnPreview').disabled = $('btnRecord').disabled = true;
  clearMarkers();
  if (!sessionId) { $('recStat').textContent = 'Menunggu pilihan sesi…'; return; }
  $('recStat').textContent = 'Mengunduh titik rute…';
  try {
    const j = await (await fetch('/api_run.php?route='+sessionId, {credentials:'same-origin'})).json();
    if (!j.ok || !j.points || j.points.length < 1) {
      $('recStat').textContent = 'Sesi tidak memiliki titik GPS sama sekali — tidak dapat dibuat video.'; return;
    }
    // Revisi 16 Juni 2026: hapus pembatasan minimum 5 titik. Semua titik bisa dibuat video.
    // Jika hanya 1 titik, duplikasi agar bbox/polyline tetap valid; flyover akan jadi statis di titik tsb.
    routePts = j.points.length === 1 ? [j.points[0], j.points[0]] : j.points;
    drawAll();
    buildKmMarkers();
    $('btnPreview').disabled = $('btnRecord').disabled = false;
    $('recStat').textContent = 'Siap. '+j.points.length+' titik rute dimuat'+(j.points.length<5?' (sedikit titik — flyover tetap dibuat)':'')+'.';
  } catch (err) {
    $('recStat').textContent = 'Gagal memuat titik: '+err.message;
  }
});

/* ============================================================
   Animasi flyover
   ============================================================ */
function lerp(a,b,t){ return a + (b-a)*t; }
function bearing(a,b){
  const toRad=x=>x*Math.PI/180, toDeg=x=>x*180/Math.PI;
  const dLng = toRad(b[1]-a[1]);
  const y = Math.sin(dLng)*Math.cos(toRad(b[0]));
  const x = Math.cos(toRad(a[0]))*Math.sin(toRad(b[0])) - Math.sin(toRad(a[0]))*Math.cos(toRad(b[0]))*Math.cos(dLng);
  return (toDeg(Math.atan2(y,x))+360)%360;
}

async function runFlyover({record=false}={}) {
  if (!routePts.length) return;
  const totalSec = +$('dur').value;
  const fps = 30;
  const totalFrames = totalSec * fps;
  const drawTrail = $('trailDraw').checked;
  const coords = routePts.map(p=>[p[1], p[0]]); // [lng,lat]

  // Intro: zoom out bird's-eye lalu turun.
  const lats = routePts.map(p=>p[0]), lngs = routePts.map(p=>p[1]);
  const bbox = [[Math.min(...lngs),Math.min(...lats)],[Math.max(...lngs),Math.max(...lats)]];
  map.fitBounds(bbox,{padding:80, duration:0, pitch:0, bearing:0});

  // Revisi 16 Juni 2026 — Hitung total km utk HUD & ikon
  let totalKm = 0;
  for (let i=1;i<routePts.length;i++) totalKm += haversineKm(routePts[i-1], routePts[i]);

  // Setup audio (musik latar) — mix ke stream rekaman jika aktif
  const useMusic = $('optMusic').checked;
  const audioEl = $('musicAudio');
  let audioCtx=null, audioDest=null;
  if (useMusic){
    if (!audioEl.src) setupMusicSrc();
    audioEl.currentTime = 0;
    try { await audioEl.play(); } catch(_) {}
  }

  let recorder, chunks=[];
  if (record) {
    const canvas = map.getCanvas();
    const vStream = canvas.captureStream(fps);
    let stream = vStream;
    if (useMusic){
      try {
        audioCtx = new (window.AudioContext||window.webkitAudioContext)();
        const src = audioCtx.createMediaElementSource(audioEl);
        audioDest = audioCtx.createMediaStreamDestination();
        src.connect(audioDest); src.connect(audioCtx.destination); // tetap kedengaran user
        stream = new MediaStream([...vStream.getVideoTracks(), ...audioDest.stream.getAudioTracks()]);
      } catch(e){ console.warn('Audio mix gagal:', e); }
    }
    recorder = new MediaRecorder(stream, { mimeType:'video/webm;codecs=vp9', videoBitsPerSecond: 6_000_000 });
    recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    recorder.start();
    $('flyRec').classList.add('show');
  }

  showHud(true);
  popupSay('<i class="bi bi-rocket-takeoff"></i> <b>Mulai!</b> Selamat menikmati flyover.');
  $('recStat').textContent = record ? 'Merekam…' : 'Preview…';
  const tStart = performance.now();

  // Pre-roll intro 1.5 detik (bird-eye sweep)
  const introFrames = Math.round(1.5*fps);
  for (let i=0;i<introFrames;i++){
    const t = i/introFrames;
    map.jumpTo({ pitch: lerp(0, +$('pitch').value, t), bearing: lerp(0, 25, t) });
    await new Promise(r=>requestAnimationFrame(r));
  }

  // Kosongkan lintasan jika "draw trail" aktif
  if (drawTrail) map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: [] } });
  else            map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: coords } });

  // Marker pelari (runner) yang ikut bergerak — opsional ikon
  if ($('optIcons').checked){
    if (runnerMarker) runnerMarker.remove();
    runnerMarker = makeIcon('runner', '<i class="bi bi-person-walking"></i>', coords[0]);
  }
  let kmAnnounced = 0;

  // Fly along route
  for (let f=0; f<totalFrames; f++){
    const t = f/(totalFrames-1);
    const idx = t*(coords.length-1);
    const i0 = Math.floor(idx), i1 = Math.min(coords.length-1, i0+1);
    const frac = idx - i0;
    const cur = [ lerp(coords[i0][0], coords[i1][0], frac), lerp(coords[i0][1], coords[i1][1], frac) ];
    const look = coords[Math.min(coords.length-1, i0+4)];
    const brg  = bearing([cur[1],cur[0]],[look[1],look[0]]);
    map.jumpTo({ center: cur, zoom: 16.2, pitch: +$('pitch').value, bearing: brg });
    if (drawTrail) {
      map.getSource('rt').setData({ type:'Feature',
        geometry:{ type:'LineString', coordinates: coords.slice(0, i0+1).concat([cur]) } });
    }
    if (runnerMarker) runnerMarker.setLngLat(cur);
    const distSoFar = totalKm * t;
    setHud(distSoFar, (performance.now()-tStart)/1000, totalKm);
    if (Math.floor(distSoFar) > kmAnnounced){
      kmAnnounced = Math.floor(distSoFar);
      popupSay('<i class="bi bi-flag-fill text-warning"></i> Melewati KM <b>'+kmAnnounced+'</b>');
    }
    await new Promise(r=>requestAnimationFrame(r));
  }

  // Outro: zoom out kembali lihat keseluruhan rute
  const outroFrames = Math.round(2*fps);
  const startZoom = map.getZoom();
  for (let i=0;i<outroFrames;i++){
    const t = i/outroFrames;
    map.jumpTo({ pitch: lerp(+$('pitch').value, 35, t), zoom: lerp(startZoom, 13, t), bearing: lerp(map.getBearing(), 0, t) });
    await new Promise(r=>requestAnimationFrame(r));
  }
  map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: coords } });
  map.fitBounds(bbox,{padding:80, duration:800, pitch:35});
  popupSay('<i class="bi bi-trophy-fill text-warning"></i> <b>Finish!</b> '+totalKm.toFixed(2)+' km selesai.');

  if (record && recorder) {
    recorder.stop();
    await new Promise(r => recorder.onstop = r);
    $('flyRec').classList.remove('show');
    if (useMusic){ try{ audioEl.pause(); }catch(_){ } if (audioCtx) try{audioCtx.close();}catch(_){ } }
    const blob = new Blob(chunks, { type:'video/webm' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'flyover_'+(sessionId||'route')+'.webm';
    document.body.appendChild(a); a.click(); a.remove();
    $('recStat').innerHTML = 'Selesai. Video diunduh ('+(blob.size/1024/1024).toFixed(2)+' MB).';
  } else {
    if (useMusic){ try{ audioEl.pause(); }catch(_){ } }
    $('recStat').textContent = 'Preview selesai.';
  }
  setTimeout(()=>showHud(false), 1800);
}

$('btnPreview').onclick = ()=> runFlyover({record:false});
$('btnRecord').onclick  = ()=> {
  if (!('MediaRecorder' in window)) { alert('Browser tidak mendukung MediaRecorder.'); return; }
  runFlyover({record:true});
};
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>

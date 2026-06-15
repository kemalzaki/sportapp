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
          <option value="raster-osm">OpenStreetMap (raster, default)</option>
          <option value="demo">MapLibre Demotiles (vektor)</option>
          <option value="dark">Gelap (raster Carto)</option>
        </select>

        <label class="form-label small">Pitch Kamera</label>
        <input type="range" id="pitch" min="40" max="75" value="65" class="form-range">
        <div class="small text-muted text-end mb-2"><span id="pitchOut">65°</span></div>

        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="trailDraw" checked>
          <label class="form-check-label small" for="trailDraw">Gambar lintasan sambil kamera bergerak</label>
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
        <div id="map3d" style="height:560px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
        <div class="small text-muted mt-2 px-2">
          <i class="bi bi-info-circle"></i>
          Browser akan menggunakan WebGL untuk merender peta. Pastikan tab
          aktif selama proses perekaman.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* ============================================================
   Util
   ============================================================ */
const STYLES = {
  'raster-osm': {
    version:8,
    sources:{ osm:{ type:'raster', tiles:['https://a.tile.openstreetmap.org/{z}/{x}/{y}.png','https://b.tile.openstreetmap.org/{z}/{x}/{y}.png','https://c.tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize:256, attribution:'© OpenStreetMap' } },
    layers:[ { id:'osm', type:'raster', source:'osm' } ]
  },
  'demo':  'https://demotiles.maplibre.org/style.json',
  'dark':  {
    version:8,
    sources:{ c:{ type:'raster', tiles:['https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png','https://b.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png'], tileSize:256, attribution:'© Carto © OSM' } },
    layers:[ { id:'c', type:'raster', source:'c' } ]
  }
};

const $ = id => document.getElementById(id);
$('dur').oninput   = e => $('durOut').textContent   = e.target.value;
$('pitch').oninput = e => { $('pitchOut').textContent = e.target.value+'°'; if(map) map.setPitch(+e.target.value); };

let map, routePts = [], sessionId = null;

function buildMap(styleKey){
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
    if (routePts.length) drawAll();
  });
}
buildMap('raster-osm');
$('styleSel').onchange = e => buildMap(e.target.value);

function drawAll(){
  const coords = routePts.map(p=>[p[1], p[0]]); // [lng,lat]
  map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: coords } });
  const lats = routePts.map(p=>p[0]), lngs = routePts.map(p=>p[1]);
  map.fitBounds([[Math.min(...lngs), Math.min(...lats)],[Math.max(...lngs), Math.max(...lats)]], { padding:60, duration:0 });
}

/* ============================================================
   Load sesi → ambil titik dari api_run.php
   ============================================================ */
$('selSession').addEventListener('change', async (e) => {
  sessionId = +e.target.value || null;
  $('btnPreview').disabled = $('btnRecord').disabled = true;
  if (!sessionId) { $('recStat').textContent = 'Menunggu pilihan sesi…'; return; }
  $('recStat').textContent = 'Mengunduh titik rute…';
  try {
    const j = await (await fetch('/api_run.php?session_id='+sessionId, {credentials:'same-origin'})).json();
    if (!j.ok || !j.points || j.points.length < 5) {
      $('recStat').textContent = 'Sesi tidak memiliki cukup titik (<5).'; return;
    }
    routePts = j.points;
    drawAll();
    $('btnPreview').disabled = $('btnRecord').disabled = false;
    $('recStat').textContent = 'Siap. '+routePts.length+' titik rute dimuat.';
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

  let recorder, chunks=[];
  if (record) {
    const canvas = map.getCanvas();
    const stream = canvas.captureStream(fps);
    recorder = new MediaRecorder(stream, { mimeType:'video/webm;codecs=vp9', videoBitsPerSecond: 6_000_000 });
    recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    recorder.start();
  }

  $('recStat').textContent = record ? 'Merekam…' : 'Preview…';

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

  if (record && recorder) {
    recorder.stop();
    await new Promise(r => recorder.onstop = r);
    const blob = new Blob(chunks, { type:'video/webm' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'flyover_'+(sessionId||'route')+'.webm';
    document.body.appendChild(a); a.click(); a.remove();
    $('recStat').innerHTML = 'Selesai. Video diunduh ('+(blob.size/1024/1024).toFixed(2)+' MB).';
  } else {
    $('recStat').textContent = 'Preview selesai.';
  }
}

$('btnPreview').onclick = ()=> runFlyover({record:false});
$('btnRecord').onclick  = ()=> {
  if (!('MediaRecorder' in window)) { alert('Browser tidak mendukung MediaRecorder.'); return; }
  runFlyover({record:true});
};
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>

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

        <!-- Revisi 17 Juni 2026 — Rute dari Upload Screenshot Strava -->
        <div class="border rounded p-2 mb-2 bg-warning-subtle">
          <label class="form-label small fw-bold mb-1"><i class="bi bi-image text-warning"></i> Atau: Rute dari Screenshot Strava</label>
          <input type="file" id="stravaShot" class="form-control form-control-sm mb-1" accept="image/*">
          <input type="text" id="stravaHint" class="form-control form-control-sm mb-1" placeholder="Petunjuk area (cth: Bandung Selatan)">
          <button type="button" id="btnStravaAI" class="btn btn-sm btn-warning w-100"><i class="bi bi-magic"></i> Ekstrak Rute dari Gambar</button>
          <div id="stravaStat" class="small text-muted mt-1"></div>
        </div>

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
          <!-- Revisi 18 Juni 2026 — Pustaka musik realtime (iTunes Search API, gratis, tanpa key) -->
          <label class="form-label small mb-1"><i class="bi bi-search"></i> Cari Musik (Pustaka iTunes)</label>
          <div class="input-group input-group-sm mb-1">
            <input type="text" id="musicQ" class="form-control form-control-sm" placeholder="Judul / artis (mis. Coldplay yellow)">
            <button type="button" class="btn btn-outline-secondary" id="btnMusicSearch"><i class="bi bi-search"></i></button>
          </div>
          <div id="musicResults" class="list-group list-group-flush small mb-1" style="max-height:180px;overflow:auto;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:8px;display:none"></div>

          <label class="form-label small mb-1 mt-1">Atau upload file audio sendiri</label>
          <input type="file" id="musicFile" class="form-control form-control-sm" accept="audio/*">
          <small class="text-muted d-block mt-1">Preview iTunes ±30 detik. Kalau kosong, dipakai musik bawaan.</small>
          <audio id="musicAudio" preload="auto" controls class="w-100 mt-2" style="height:34px"></audio>
          <div id="musicMeta" class="small text-muted mt-1"></div>

          <!-- Revisi 18 Juni 2026 — Trim audio (potong start/end detik) -->
          <div class="border rounded p-2 mt-2 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label small fw-bold mb-1"><i class="bi bi-scissors"></i> Potong Audio</label>
              <span class="small text-muted">Durasi: <span id="audDur">–</span></span>
            </div>
            <!-- Revisi 18 Juni 2026 (B) — slider rentang (range) menggantikan input detik manual -->
            <div class="mt-1">
              <label class="form-label small mb-0 d-flex justify-content-between">
                <span>Mulai: <strong id="trimStartLbl">0.0</strong>s</span>
                <span>Akhir: <strong id="trimEndLbl">0.0</strong>s</span>
              </label>
              <input type="range" id="trimStart" class="form-range" min="0" max="100" step="0.1" value="0">
              <input type="range" id="trimEnd"   class="form-range" min="0" max="100" step="0.1" value="0">
            </div>
            <div class="d-flex gap-1 mt-1">
              <button type="button" id="btnTrimApply" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-check2-circle"></i> Terapkan Trim</button>
              <button type="button" id="btnTrimReset" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
            <small class="text-muted d-block mt-1" id="trimStat">Belum ada trim.</small>
          </div>

          <!-- Revisi 18 Juni 2026 (C) — Lirik dari pencarian (lyrics.ovh), bukan AI. Otomatis terisi ketika musik dipilih. -->
          <div class="border rounded p-2 mt-2 bg-info-subtle">
            <label class="form-label small fw-bold mb-1"><i class="bi bi-badge-cc text-info"></i> Tampilkan Lirik (Subtitle Karaoke)</label>
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" id="optLyric">
              <label class="form-check-label small" for="optLyric">Aktifkan subtitle lirik di video</label>
            </div>
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" id="optLyricAuto" checked>
              <label class="form-check-label small" for="optLyricAuto">Auto-ambil lirik tiap kali memilih musik (deteksi otomatis)</label>
            </div>
            <!-- Cari lirik manual via pencarian (mirip pencarian musik) -->
            <label class="form-label small mb-1 mt-1"><i class="bi bi-search"></i> Cari Lirik</label>
            <div class="input-group input-group-sm mb-1">
              <input type="text" id="lyricQ" class="form-control form-control-sm" placeholder="Judul / artis (mis. Coldplay Yellow)">
              <button type="button" class="btn btn-outline-info" id="btnLyricSearch"><i class="bi bi-search"></i></button>
            </div>
            <div id="lyricResults" class="list-group list-group-flush small mb-1" style="max-height:160px;overflow:auto;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:8px;display:none"></div>
            <input type="hidden" id="lyricTitle">
            <input type="hidden" id="lyricArtist">
            <textarea id="lyricManual" class="form-control form-control-sm mt-1" rows="3" placeholder="Atau tempel lirik manual (1 baris = 1 subtitle, atau format LRC [mm:ss.xx]baris)"></textarea>
            <small id="lyricStat" class="text-muted d-block mt-1">Belum ada lirik.</small>
          </div>


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
          <!-- Revisi 18 Juni 2026 — subtitle lirik di preview -->
          <div id="flyLyric" style="position:absolute;left:50%;bottom:78px;transform:translateX(-50%);z-index:6;
               background:linear-gradient(180deg,rgba(8,47,73,.92),rgba(14,116,144,.92));color:#fef9c3;
               font-weight:800;font-size:1.05rem;padding:10px 22px;border-radius:14px;
               border:1px solid rgba(186,230,253,.45);box-shadow:0 8px 24px rgba(0,0,0,.45);
               max-width:80%;text-align:center;display:none"></div>
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
let kmMarkers = [], kmMarkerPoints = [], startMarker = null, finishMarker = null, runnerMarker = null;
let runnerLngLat = null, activePopup = { text:'', kind:'info', until:0 };

/* Revisi 16 Juni 2026 — util ikon DOM untuk start/finish/km/runner */
function makeIcon(cls, html, lngLat){
  const el = document.createElement('div');
  el.className = 'fly-icon '+cls;
  el.innerHTML = html;
  return new maplibregl.Marker({element:el, anchor:'center'}).setLngLat(lngLat).addTo(map);
}
function clearMarkers(){
  kmMarkers.forEach(m=>m.remove()); kmMarkers=[]; kmMarkerPoints=[];
  if (startMarker){startMarker.remove();startMarker=null;}
  if (finishMarker){finishMarker.remove();finishMarker=null;}
  if (runnerMarker){runnerMarker.remove();runnerMarker=null;}
  runnerLngLat = null;
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
      const kmLngLat = [routePts[i][1], routePts[i][0]];
      kmMarkerPoints.push({ n: nextKm, lngLat: kmLngLat });
      kmMarkers.push(makeIcon('km', String(nextKm), kmLngLat));
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
  if (f){ a.src = URL.createObjectURL(f); a.crossOrigin = null; MUSIC.currentTitle=f.name; MUSIC.currentArtist=''; }
  else if (!a.src){
    a.src = 'https://cdn.pixabay.com/download/audio/2022/03/15/audio_8e3a8af6c4.mp3?filename=energetic-indie-rock-30sec-117279.mp3';
    a.crossOrigin = 'anonymous';
    MUSIC.currentTitle='Energetic Indie Rock'; MUSIC.currentArtist='Pixabay (free)';
  }
  a.loop = true; a.load();
  TRIM.start = 0; TRIM.end = 0;
  a.addEventListener('loadedmetadata', onAudioMeta, { once:true });
}
function onAudioMeta(){
  const a = $('musicAudio');
  const d = a.duration || 0;
  $('audDur').textContent = d.toFixed(1)+'s';
  // Slider range mengikuti durasi audio
  $('trimStart').max = d.toFixed(1); $('trimEnd').max = d.toFixed(1);
  $('trimStart').value = 0; $('trimEnd').value = d.toFixed(1);
  $('trimStartLbl').textContent = '0.0';
  $('trimEndLbl').textContent = d.toFixed(1);
  if (!$('lyricTitle').value)  $('lyricTitle').value  = MUSIC.currentTitle || '';
  if (!$('lyricArtist').value) $('lyricArtist').value = MUSIC.currentArtist || '';
}
// Update label slider trim secara realtime + jaga start < end
['trimStart','trimEnd'].forEach(function(id){
  document.getElementById(id).addEventListener('input', function(){
    var s = parseFloat($('trimStart').value||0);
    var e = parseFloat($('trimEnd').value||0);
    if (e < s + 0.3) {
      if (id === 'trimStart') { e = Math.min(parseFloat($('trimEnd').max||0), s + 0.3); $('trimEnd').value = e.toFixed(1); }
      else                    { s = Math.max(0, e - 0.3); $('trimStart').value = s.toFixed(1); }
    }
    $('trimStartLbl').textContent = s.toFixed(1);
    $('trimEndLbl').textContent   = e.toFixed(1);
  });
});


/* ============================================================
   Revisi 18 Juni 2026 — Pustaka musik realtime (iTunes Search API)
   ============================================================ */
var MUSIC = { currentTitle:'', currentArtist:'' };
$('btnMusicSearch').addEventListener('click', searchMusic);
$('musicQ').addEventListener('keydown', e => { if (e.key==='Enter'){ e.preventDefault(); searchMusic(); } });
async function searchMusic(){
  const q = $('musicQ').value.trim();
  if (!q) return;
  const box = $('musicResults');
  box.style.display = 'block';
  box.innerHTML = '<div class="list-group-item small text-muted"><span class="spinner-border spinner-border-sm"></span> Mencari…</div>';
  try {
    const r = await fetch('https://itunes.apple.com/search?media=music&entity=song&limit=12&term='+encodeURIComponent(q));
    const j = await r.json();
    if (!j.results || !j.results.length){ box.innerHTML = '<div class="list-group-item small text-muted">Tidak ada hasil.</div>'; return; }
    box.innerHTML = '';
    j.results.forEach(t => {
      if (!t.previewUrl) return;
      const it = document.createElement('button');
      it.type='button'; it.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2 py-1';
      it.innerHTML = '<img src="'+(t.artworkUrl60||'')+'" width="32" height="32" style="border-radius:4px">'
                   + '<div class="text-start flex-fill"><div class="fw-semibold" style="font-size:.85rem">'
                   + escapeHtml(t.trackName||'?')+'</div><div class="text-muted" style="font-size:.72rem">'
                   + escapeHtml(t.artistName||'')+(t.trackTimeMillis?(' · '+Math.round(t.trackTimeMillis/1000)+'s'):'')+'</div></div>'
                   + '<i class="bi bi-play-circle-fill text-success"></i>';
      it.onclick = () => pickMusic(t);
      box.appendChild(it);
    });
  } catch(e){ box.innerHTML = '<div class="list-group-item small text-danger">Error: '+e.message+'</div>'; }
}
function escapeHtml(s){ return String(s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c])); }
function pickMusic(t){
  const a = $('musicAudio');
  a.crossOrigin = 'anonymous';
  a.src = t.previewUrl; a.loop = true; a.load();
  $('musicMeta').innerHTML = '<i class="bi bi-music-note"></i> '+escapeHtml(t.trackName)+' — '+escapeHtml(t.artistName);
  MUSIC.currentTitle = t.trackName||''; MUSIC.currentArtist = t.artistName||'';
  $('lyricTitle').value = MUSIC.currentTitle;
  $('lyricArtist').value = MUSIC.currentArtist;
  TRIM.start=0; TRIM.end=0;
  a.addEventListener('loadedmetadata', onAudioMeta, { once:true });
  // Revisi 18 Juni 2026 (C) — Auto-deteksi: ambil lirik otomatis (lyrics.ovh, BUKAN AI)
  if ($('optLyricAuto') && $('optLyricAuto').checked) {
    fetchLyricsByMeta(MUSIC.currentArtist, MUSIC.currentTitle);
  }
}


/* ============================================================
   Revisi 18 Juni 2026 — Trim Audio (potong start/end)
   ============================================================ */
var TRIM = { start:0, end:0, applied:false };
$('btnTrimReset').onclick = () => {
  const a = $('musicAudio');
  TRIM = { start:0, end:0, applied:false };
  $('trimStart').value = 0; $('trimEnd').value = (a.duration||0).toFixed(1);
  $('trimStat').textContent = 'Trim direset.';
  if (a.dataset.originalSrc){ a.src = a.dataset.originalSrc; a.load(); }
};
$('btnTrimApply').onclick = async () => {
  const a = $('musicAudio');
  const s = Math.max(0, parseFloat($('trimStart').value||0));
  const e = Math.max(s+0.5, parseFloat($('trimEnd').value||0));
  if (!a.src){ $('trimStat').textContent='Pilih lagu dulu.'; return; }
  $('trimStat').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses…';
  try {
    if (!a.dataset.originalSrc) a.dataset.originalSrc = a.src;
    const resp = await fetch(a.dataset.originalSrc, { mode:'cors' });
    const buf  = await resp.arrayBuffer();
    const ac   = new (window.AudioContext||window.webkitAudioContext)();
    const decoded = await ac.decodeAudioData(buf.slice(0));
    const sr = decoded.sampleRate;
    const startS = Math.min(decoded.length, Math.floor(s*sr));
    const endS   = Math.min(decoded.length, Math.floor(e*sr));
    if (endS - startS < sr*0.3){ throw new Error('Range terlalu pendek.'); }
    const ch = decoded.numberOfChannels, len = endS - startS;
    const out = ac.createBuffer(ch, len, sr);
    for (let c=0;c<ch;c++){ out.getChannelData(c).set(decoded.getChannelData(c).subarray(startS, endS)); }
    const wavBlob = audioBufferToWav(out);
    a.src = URL.createObjectURL(wavBlob); a.crossOrigin = null; a.load();
    TRIM = { start:s, end:e, applied:true };
    $('trimStat').textContent = 'Trim diterapkan: '+s.toFixed(2)+'s → '+e.toFixed(2)+'s ('+(e-s).toFixed(2)+'s).';
    try{ ac.close(); }catch(_){}
  } catch(err){ $('trimStat').textContent = 'Gagal: '+err.message+' (mungkin CORS audio sumber)'; }
};
function audioBufferToWav(buf){
  const ch = buf.numberOfChannels, sr = buf.sampleRate, len = buf.length*ch*2;
  const ab = new ArrayBuffer(44+len), dv = new DataView(ab);
  let p=0; function w(s){ for(let i=0;i<s.length;i++) dv.setUint8(p++, s.charCodeAt(i)); }
  function u32(v){ dv.setUint32(p, v, true); p+=4; } function u16(v){ dv.setUint16(p, v, true); p+=2; }
  w('RIFF'); u32(36+len); w('WAVE'); w('fmt '); u32(16); u16(1); u16(ch); u32(sr); u32(sr*ch*2); u16(ch*2); u16(16);
  w('data'); u32(len);
  const chans = []; for (let c=0;c<ch;c++) chans.push(buf.getChannelData(c));
  for (let i=0;i<buf.length;i++){
    for (let c=0;c<ch;c++){ let v = Math.max(-1, Math.min(1, chans[c][i])); dv.setInt16(p, v<0?v*0x8000:v*0x7FFF, true); p+=2; }
  }
  return new Blob([ab], { type:'audio/wav' });
}

/* ============================================================
   Revisi 18 Juni 2026 — Lirik AI (Gemini) sebagai subtitle karaoke
   ============================================================ */
var LYRICS = { lines: [], src: '' };
/* ============================================================
   Revisi 18 Juni 2026 (C) — Lirik dari pencarian publik (iTunes + lyrics.ovh)
   Tidak menggunakan AI. Saat user memilih lagu dari pencarian musik (atau
   pencarian lirik di bawah), lirik diambil otomatis dari lyrics.ovh dan
   langsung mengisi textbox/subtitle.
   ============================================================ */
var LYRICS = { lines: [], src: '' };

async function fetchLyricsByMeta(artist, title){
  if (!title){ $('lyricStat').textContent='Belum ada judul lagu.'; return; }
  $('lyricStat').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari lirik…';
  try {
    // 1) coba lyrics.ovh langsung dengan artist+title
    let lyr = '';
    try {
      const r = await fetch('https://api.lyrics.ovh/v1/'+encodeURIComponent(artist||'')+'/'+encodeURIComponent(title||''));
      if (r.ok){ const j = await r.json(); lyr = (j.lyrics||'').trim(); }
    } catch(_){}
    // 2) fallback: pakai iTunes hasil pertama utk ambil artist resmi
    if (!lyr) {
      try {
        const q = (artist? artist+' ':'')+title;
        const r2 = await fetch('https://itunes.apple.com/search?media=music&entity=song&limit=1&term='+encodeURIComponent(q));
        const j2 = await r2.json();
        if (j2.results && j2.results[0]){
          const art = j2.results[0].artistName, tit = j2.results[0].trackName;
          const r3 = await fetch('https://api.lyrics.ovh/v1/'+encodeURIComponent(art)+'/'+encodeURIComponent(tit));
          if (r3.ok){ const j3 = await r3.json(); lyr = (j3.lyrics||'').trim(); }
        }
      } catch(_){}
    }
    if (!lyr){ $('lyricStat').textContent='Lirik tidak ditemukan. Bisa tempel manual di bawah.'; return; }
    // Isi textbox manual, dan biarkan handler 'input' textbox memparse + distribusi waktu
    $('lyricManual').value = lyr;
    $('lyricManual').dispatchEvent(new Event('input'));
    $('optLyric').checked = true;
    LYRICS.src = 'lyrics.ovh';
    $('lyricStat').textContent = LYRICS.lines.length+' baris lirik siap (sumber: lyrics.ovh).';
  } catch(e){ $('lyricStat').textContent='Error: '+e.message; }
}

// Tombol cari lirik manual (mirip pencarian musik): pakai iTunes utk list pilihan
$('btnLyricSearch').addEventListener('click', searchLyricChoices);
$('lyricQ').addEventListener('keydown', e => { if (e.key==='Enter'){ e.preventDefault(); searchLyricChoices(); } });
async function searchLyricChoices(){
  const q = $('lyricQ').value.trim();
  if (!q) return;
  const box = $('lyricResults');
  box.style.display='block';
  box.innerHTML = '<div class="list-group-item small text-muted"><span class="spinner-border spinner-border-sm"></span> Mencari lagu…</div>';
  try {
    const r = await fetch('https://itunes.apple.com/search?media=music&entity=song&limit=12&term='+encodeURIComponent(q));
    const j = await r.json();
    if (!j.results || !j.results.length){ box.innerHTML='<div class="list-group-item small text-muted">Tidak ada hasil.</div>'; return; }
    box.innerHTML='';
    j.results.forEach(t => {
      const it = document.createElement('button');
      it.type='button'; it.className='list-group-item list-group-item-action d-flex align-items-center gap-2 py-1';
      it.innerHTML = '<img src="'+(t.artworkUrl60||'')+'" width="28" height="28" style="border-radius:4px">'
                   + '<div class="text-start flex-fill"><div class="fw-semibold" style="font-size:.83rem">'
                   + escapeHtml(t.trackName||'?')+'</div><div class="text-muted" style="font-size:.7rem">'
                   + escapeHtml(t.artistName||'')+'</div></div>'
                   + '<i class="bi bi-badge-cc text-info"></i>';
      it.onclick = () => {
        $('lyricTitle').value = t.trackName||''; $('lyricArtist').value = t.artistName||'';
        box.style.display='none';
        fetchLyricsByMeta(t.artistName||'', t.trackName||'');
      };
      box.appendChild(it);
    });
  } catch(e){ box.innerHTML='<div class="list-group-item small text-danger">Error: '+e.message+'</div>'; }
}

// Auto deteksi musik upload sendiri: ketika audio mulai play tapi belum ada lirik,
// coba ambil lirik dari nama file (judul) — opsional.
document.getElementById('musicAudio').addEventListener('play', function(){
  if (!$('optLyricAuto') || !$('optLyricAuto').checked) return;
  if (LYRICS.lines.length>0) return;
  const t = ($('lyricTitle').value||MUSIC.currentTitle||'').trim();
  const a = ($('lyricArtist').value||MUSIC.currentArtist||'').trim();
  if (t) fetchLyricsByMeta(a, t);
});

$('lyricManual').addEventListener('input', () => {
  const txt = $('lyricManual').value.trim();
  if (!txt){ return; }

  const a = $('musicAudio');
  const dur = (TRIM.applied ? (TRIM.end-TRIM.start) : (a.duration||180)) || 180;
  const lrc = /\[(\d+):(\d+(?:\.\d+)?)\]\s*(.+)/;
  const lines = [];
  txt.split(/\r?\n/).forEach(ln => {
    ln = ln.trim(); if (!ln) return;
    const m = ln.match(lrc);
    if (m){ lines.push({ t: (+m[1])*60 + parseFloat(m[2]), line: m[3].trim() }); }
    else { lines.push({ t: -1, line: ln }); }
  });
  const untimed = lines.filter(l=>l.t<0);
  if (untimed.length === lines.length && lines.length>0){
    const gap = dur / (lines.length+1);
    lines.forEach((l,i)=> l.t = gap*(i+1) - gap*0.5);
  }
  LYRICS.lines = lines.filter(l=>l.t>=0).sort((a,b)=>a.t-b.t);
  LYRICS.src = 'manual';
  $('optLyric').checked = true;
  $('lyricStat').textContent = LYRICS.lines.length+' baris lirik (manual) siap.';
});
function currentLyricLine(audioTime){
  if (!$('optLyric').checked || !LYRICS.lines.length) return '';
  let cur = '';
  for (const l of LYRICS.lines){ if (l.t <= audioTime+0.05) cur = l.line; else break; }
  return cur;
}
// Ticker live untuk overlay HTML subtitle di preview
setInterval(() => {
  const el = document.getElementById('flyLyric'); if (!el) return;
  const a = $('musicAudio');
  if (!$('optLyric').checked || !LYRICS.lines.length || !a || a.paused){ el.style.display='none'; return; }
  const line = currentLyricLine(a.currentTime);
  if (line){ el.textContent = line; el.style.display=''; } else { el.style.display='none'; }
}, 120);

/* HUD helpers — Revisi 17 Juni 2026: kecepatan memakai DURASI REAL aktivitas
 * (jarak_m / durasi_dtk dari run_sessions), bukan waktu animasi.
 */
var SESSION_INFO = { jarak_m: 0, durasi_dtk: 0 };
function realAvgSpeedKmh(){
  if (!SESSION_INFO.durasi_dtk || SESSION_INFO.durasi_dtk <= 0) return 0;
  return (SESSION_INFO.jarak_m/1000) / (SESSION_INFO.durasi_dtk/3600);
}
function showHud(on){ $('flyHud').classList.toggle('show', !!on && $('optHud').checked); }
function setHud(distKm, tSec, totalKm){
  $('hudDist').textContent  = distKm.toFixed(2)+' km';
  // Tampilkan estimasi waktu aktivitas pada titik ini (proporsional progres),
  // bukan waktu playback animasi.
  var realDur = SESSION_INFO.durasi_dtk || 0;
  var elapsedReal = totalKm>0 ? realDur*(distKm/totalKm) : 0;
  $('hudTime').textContent  = elapsedReal>0
      ? (Math.floor(elapsedReal/60)+'m '+Math.round(elapsedReal%60)+'s')
      : tSec.toFixed(1)+' s';
  var sp = realAvgSpeedKmh();
  if (sp<=0) sp = (tSec>0 ? (distKm/(tSec/3600)) : 0); // fallback
  $('hudSpeed').textContent = sp.toFixed(1)+' km/j';
  var pct = totalKm>0 ? Math.min(100, (distKm/totalKm)*100) : 0;
  $('hudProg').textContent  = pct.toFixed(0)+'%';
}
function popupSay(html){
  const p = $('flyPopup'); p.innerHTML = html; p.classList.add('show');
  const plain = String(html).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
  const kind = /finish|trophy|selesai/i.test(plain) ? 'finish' : (/km/i.test(plain) ? 'km' : 'start');
  activePopup = { text: plain, kind: kind, until: performance.now() + 2400 };
  clearTimeout(popupSay._t); popupSay._t = setTimeout(()=>p.classList.remove('show'), 2200);
}

function rr(ctx,x,y,w,h,r){
  ctx.beginPath(); ctx.moveTo(x+r,y); ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r);
  ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w,y,r); ctx.closePath();
}
function drawTextFit(ctx, text, x, y, maxWidth){
  let t = String(text || '');
  while (ctx.measureText(t).width > maxWidth && t.length > 3) t = t.slice(0, -2) + '…';
  ctx.fillText(t, x, y);
}
function drawCircleIcon(ctx, x, y, r, bg, fg, label, sub){
  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 10; ctx.shadowOffsetY = 3;
  ctx.fillStyle = bg; ctx.beginPath(); ctx.arc(x,y,r,0,Math.PI*2); ctx.fill();
  ctx.shadowColor = 'transparent'; ctx.lineWidth = Math.max(3, r*.14); ctx.strokeStyle = '#fff'; ctx.stroke();
  ctx.fillStyle = fg || '#fff'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.font = '700 '+Math.max(12, r*.78)+'px system-ui, sans-serif';
  ctx.fillText(label, x, y + (sub ? -r*.12 : 0));
  if (sub){ ctx.font = '700 '+Math.max(9, r*.36)+'px system-ui, sans-serif'; ctx.fillText(sub, x, y+r*.46); }
  ctx.restore();
}
function drawFlyoverComposite(ctx, target, mapCanvas, o){
  const w = target.width, h = target.height;
  ctx.clearRect(0,0,w,h);
  ctx.drawImage(mapCanvas, 0, 0, w, h);
  const cssW = mapCanvas.clientWidth || w, cssH = mapCanvas.clientHeight || h;
  const sx = w / cssW, sy = h / cssH;
  const project = (lngLat) => { const p = map.project(lngLat); return [p.x*sx, p.y*sy]; };

  if ($('optIcons').checked && routePts.length){
    const start = [routePts[0][1], routePts[0][0]];
    const finish = [routePts[routePts.length-1][1], routePts[routePts.length-1][0]];
    let p = project(start); drawCircleIcon(ctx, p[0], p[1], 24*sx, '#10b981', '#fff', '⚑');
    p = project(finish); drawCircleIcon(ctx, p[0], p[1], 24*sx, '#111827', '#fff', '🏁');
    kmMarkerPoints.forEach(k => { const q = project(k.lngLat); drawCircleIcon(ctx, q[0], q[1], 18*sx, '#f59e0b', '#111827', String(k.n), 'KM'); });
    if (runnerLngLat){ const r = project(runnerLngLat); drawCircleIcon(ctx, r[0], r[1], 28*sx, '#2563eb', '#fff', '🏃'); }
  }

  if ($('optHud').checked){
    const boxW = Math.min(310*sx, w-28*sx), boxH = 142*sy, x = 18*sx, y = 18*sy;
    ctx.save(); ctx.shadowColor='rgba(0,0,0,.35)'; ctx.shadowBlur=24; ctx.shadowOffsetY=8;
    const g = ctx.createLinearGradient(x,y,x+boxW,y+boxH); g.addColorStop(0,'rgba(15,23,42,.92)'); g.addColorStop(1,'rgba(30,41,59,.82)');
    ctx.fillStyle=g; rr(ctx,x,y,boxW,boxH,18*sx); ctx.fill(); ctx.shadowColor='transparent'; ctx.strokeStyle='rgba(255,255,255,.25)'; ctx.stroke();
    ctx.fillStyle='#fbbf24'; ctx.font='800 '+(16*sx)+'px system-ui, sans-serif'; ctx.textAlign='left'; ctx.textBaseline='alphabetic';
    ctx.fillText('📡 LIVE FLYOVER', x+18*sx, y+28*sy);
    ctx.font='600 '+(15*sx)+'px system-ui, sans-serif';
    const dist = (o.distKm||0).toFixed(2)+' km';
    const realDur = (SESSION_INFO.durasi_dtk||0);
    const elapsedReal = (o.totalKm>0 ? realDur*((o.distKm||0)/o.totalKm) : 0);
    const elapsed = elapsedReal>0
      ? (Math.floor(elapsedReal/60)+'m '+Math.round(elapsedReal%60)+'s')
      : (o.tSec||0).toFixed(1)+' s';
    let sp = realAvgSpeedKmh();
    if (sp<=0) sp = (o.tSec>0 ? ((o.distKm||0)/(o.tSec/3600)) : 0);
    const speed = sp.toFixed(1)+' km/j';
    const pct = (o.totalKm>0 ? Math.min(100, ((o.distKm||0)/o.totalKm)*100) : 0).toFixed(0)+'%';
    const rows = [['📏 Jarak',dist],['⏱ Waktu',elapsed],['⚡ Kecepatan',speed],['🏁 Progres',pct]];
    rows.forEach((row,i)=>{ const yy=y+(54+i*20)*sy; ctx.fillStyle='rgba(248,250,252,.72)'; ctx.fillText(row[0], x+18*sx, yy); ctx.fillStyle='#fff'; ctx.textAlign='right'; ctx.fillText(row[1], x+boxW-18*sx, yy); ctx.textAlign='left'; });
    ctx.restore();
  }

  if (o.recording){
    ctx.save(); const rw=92*sx, rh=34*sy, x=w-rw-18*sx, y=18*sy;
    ctx.fillStyle='rgba(239,68,68,.95)'; rr(ctx,x,y,rw,rh,17*sx); ctx.fill();
    ctx.fillStyle='#fff'; ctx.font='800 '+(14*sx)+'px system-ui, sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('● REC', x+rw/2, y+rh/2); ctx.restore();
  }

  if (activePopup.until > performance.now() && activePopup.text){
    ctx.save();
    const icon = activePopup.kind === 'finish' ? '🏆' : (activePopup.kind === 'km' ? '🚩' : '🚀');
    ctx.font='700 '+(18*sx)+'px system-ui, sans-serif';
    const text = icon+' '+activePopup.text;
    const maxW = Math.min(w-60*sx, 520*sx);
    const tw = Math.min(maxW, ctx.measureText(text).width + 34*sx);
    const th = 46*sy, x=(w-tw)/2, y=h-th-24*sy;
    ctx.shadowColor='rgba(0,0,0,.42)'; ctx.shadowBlur=18; ctx.shadowOffsetY=6;
    ctx.fillStyle='rgba(17,24,39,.94)'; rr(ctx,x,y,tw,th,16*sx); ctx.fill();
    ctx.shadowColor='transparent'; ctx.strokeStyle='rgba(255,255,255,.22)'; ctx.stroke();
    ctx.fillStyle='#fff'; ctx.textAlign='left'; ctx.textBaseline='middle';
    drawTextFit(ctx, text, x+17*sx, y+th/2, tw-34*sx);
    ctx.restore();
  }

  /* Revisi 18 Juni 2026 — Subtitle lirik karaoke pada video */
  if ($('optLyric') && $('optLyric').checked && LYRICS.lines.length){
    const a = $('musicAudio');
    const tNow = a && !a.paused ? a.currentTime : (o.tSec||0);
    const lyric = currentLyricLine(tNow);
    if (lyric){
      ctx.save();
      const fs = Math.max(20, 26*sx);
      ctx.font = '800 '+fs+'px system-ui, sans-serif';
      const padX = 22*sx, padY = 14*sy;
      const tw = Math.min(w-40*sx, ctx.measureText(lyric).width + padX*2);
      const th = fs + padY*2;
      // Posisikan agak di atas popup (~96 px dari bawah)
      const x = (w-tw)/2, y = h - th - 90*sy;
      ctx.shadowColor='rgba(0,0,0,.55)'; ctx.shadowBlur=22; ctx.shadowOffsetY=6;
      const g = ctx.createLinearGradient(x,y,x,y+th);
      g.addColorStop(0,'rgba(8,47,73,.92)'); g.addColorStop(1,'rgba(14,116,144,.92)');
      ctx.fillStyle=g; rr(ctx,x,y,tw,th,18*sx); ctx.fill();
      ctx.shadowColor='transparent';
      ctx.strokeStyle='rgba(186,230,253,.45)'; ctx.lineWidth=2; ctx.stroke();
      ctx.fillStyle='#fef9c3'; ctx.textAlign='center'; ctx.textBaseline='middle';
      drawTextFit(ctx, lyric, x+tw/2, y+th/2, tw-padX*2);
      ctx.restore();
    }
  }

  /* Revisi 18 Juni 2026 (E,F) — Watermark Copyright "HapFam 2026" + foto profil */
  drawHapFamBrand(ctx, w, h, sx, sy);
}

/* ============================================================
   Revisi 18 Juni 2026 — Brand HapFam (logo + copyright) untuk video
   ============================================================ */
var HAPFAM_LOGO = new Image();
HAPFAM_LOGO.crossOrigin = 'anonymous';
HAPFAM_LOGO.src = '/assets/img/hapfam-logo.png';
var HAPFAM_LOGO_READY = false;
HAPFAM_LOGO.onload = function(){ HAPFAM_LOGO_READY = true; };

function drawHapFamBrand(ctx, w, h, sx, sy){
  // Foto profil bulat di pojok kanan-bawah
  var size = Math.max(56, 64*sx);
  var pad  = 18*sx;
  var cx = w - size/2 - pad;
  var cy = h - size/2 - pad;
  ctx.save();
  // background bulat putih
  ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 14; ctx.shadowOffsetY = 4;
  ctx.fillStyle = '#ffffff';
  ctx.beginPath(); ctx.arc(cx, cy, size/2 + 3, 0, Math.PI*2); ctx.fill();
  ctx.shadowColor = 'transparent';
  // clip lingkaran utk gambar
  ctx.beginPath(); ctx.arc(cx, cy, size/2, 0, Math.PI*2); ctx.closePath(); ctx.clip();
  if (HAPFAM_LOGO_READY){
    try { ctx.drawImage(HAPFAM_LOGO, cx-size/2, cy-size/2, size, size); } catch(_){}
  } else {
    // fallback teks
    ctx.fillStyle = '#0ea5e9'; ctx.fillRect(cx-size/2, cy-size/2, size, size);
    ctx.fillStyle = '#fff'; ctx.font = '800 '+(size*0.32)+'px system-ui, sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('HF', cx, cy);
  }
  ctx.restore();
  // ring border lingkaran
  ctx.save();
  ctx.lineWidth = Math.max(2, size*0.06);
  ctx.strokeStyle = '#0ea5e9';
  ctx.beginPath(); ctx.arc(cx, cy, size/2, 0, Math.PI*2); ctx.stroke();
  ctx.restore();

  // Copyright bar di bawah-tengah
  ctx.save();
  var txt = '© HapFam 2026 • Sport';
  var fs = Math.max(12, 14*sx);
  ctx.font = '700 '+fs+'px system-ui, sans-serif';
  var tw = ctx.measureText(txt).width + 24*sx, th = fs + 10*sy;
  var tx = 18*sx, ty = h - th - 18*sy;
  ctx.shadowColor='rgba(0,0,0,.5)'; ctx.shadowBlur=12; ctx.shadowOffsetY=4;
  ctx.fillStyle = 'rgba(15,23,42,.78)';
  rr(ctx, tx, ty, tw, th, 10*sx); ctx.fill();
  ctx.shadowColor='transparent';
  ctx.fillStyle = '#f8fafc'; ctx.textAlign='left'; ctx.textBaseline='middle';
  ctx.fillText(txt, tx + 12*sx, ty + th/2);
  ctx.restore();
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
    // Revisi 17 Juni 2026: simpan info durasi & jarak nyata sesi → dipakai utk HUD kecepatan
    if (j.session) {
      SESSION_INFO.jarak_m    = +j.session.jarak_m    || 0;
      SESSION_INFO.durasi_dtk = +j.session.durasi_dtk || 0;
    } else { SESSION_INFO = { jarak_m: 0, durasi_dtk: 0 }; }
    routePts = j.points.length === 1 ? [j.points[0], j.points[0]] : j.points;
    drawAll();
    buildKmMarkers();
    $('btnPreview').disabled = $('btnRecord').disabled = false;
    var avg = realAvgSpeedKmh();
    $('recStat').textContent = 'Siap. '+j.points.length+' titik · '
      + (SESSION_INFO.jarak_m? (SESSION_INFO.jarak_m/1000).toFixed(2)+' km · ':'')
      + (SESSION_INFO.durasi_dtk? Math.round(SESSION_INFO.durasi_dtk/60)+' menit · ':'')
      + (avg>0? 'kecepatan rata-rata '+avg.toFixed(1)+' km/j':'');
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

  let recorder, chunks=[], recCanvas=null, recCtx=null;
  const mapCanvas = map.getCanvas();
  if (record) {
    recCanvas = document.createElement('canvas');
    recCanvas.width = mapCanvas.width;
    recCanvas.height = mapCanvas.height;
    recCtx = recCanvas.getContext('2d');
    drawFlyoverComposite(recCtx, recCanvas, mapCanvas, {distKm:0,tSec:0,totalKm,recording:true});
    const vStream = recCanvas.captureStream(fps);
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
    const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9') ? 'video/webm;codecs=vp9' : 'video/webm';
    recorder = new MediaRecorder(stream, { mimeType:mime, videoBitsPerSecond: 6_000_000 });
    recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    recorder.start();
    $('flyRec').classList.add('show');
  }

  showHud(true);
  setHud(0, 0, totalKm);
  popupSay('<i class="bi bi-rocket-takeoff"></i> <b>Mulai!</b> Selamat menikmati flyover.');
  $('recStat').textContent = record ? 'Merekam…' : 'Preview…';
  const tStart = performance.now();

  // Pre-roll intro 1.5 detik (bird-eye sweep)
  const introFrames = Math.round(1.5*fps);
  for (let i=0;i<introFrames;i++){
    const t = i/introFrames;
    map.jumpTo({ pitch: lerp(0, +$('pitch').value, t), bearing: lerp(0, 25, t) });
    await new Promise(r=>requestAnimationFrame(r));
    if (recCtx) drawFlyoverComposite(recCtx, recCanvas, mapCanvas, {distKm:0,tSec:(performance.now()-tStart)/1000,totalKm,recording:record});
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
    runnerLngLat = cur;
    if (runnerMarker) runnerMarker.setLngLat(cur);
    const distSoFar = totalKm * t;
    const elapsedSec = (performance.now()-tStart)/1000;
    setHud(distSoFar, elapsedSec, totalKm);
    if (Math.floor(distSoFar) > kmAnnounced){
      kmAnnounced = Math.floor(distSoFar);
      popupSay('<i class="bi bi-flag-fill text-warning"></i> Melewati KM <b>'+kmAnnounced+'</b>');
    }
    await new Promise(r=>requestAnimationFrame(r));
    if (recCtx) drawFlyoverComposite(recCtx, recCanvas, mapCanvas, {distKm:distSoFar,tSec:elapsedSec,totalKm,recording:record});
  }

  // Outro: zoom out kembali lihat keseluruhan rute
  const outroFrames = Math.round(2*fps);
  const startZoom = map.getZoom();
  for (let i=0;i<outroFrames;i++){
    const t = i/outroFrames;
    map.jumpTo({ pitch: lerp(+$('pitch').value, 35, t), zoom: lerp(startZoom, 13, t), bearing: lerp(map.getBearing(), 0, t) });
    await new Promise(r=>requestAnimationFrame(r));
    if (recCtx) drawFlyoverComposite(recCtx, recCanvas, mapCanvas, {distKm:totalKm,tSec:(performance.now()-tStart)/1000,totalKm,recording:record});
  }
  map.getSource('rt').setData({ type:'Feature', geometry:{ type:'LineString', coordinates: coords } });
  map.fitBounds(bbox,{padding:80, duration:800, pitch:35});
  popupSay('<i class="bi bi-trophy-fill text-warning"></i> <b>Finish!</b> '+totalKm.toFixed(2)+' km selesai.');
  if (recCtx) {
    for (let i=0;i<Math.round(1.1*fps);i++){
      await new Promise(r=>requestAnimationFrame(r));
      drawFlyoverComposite(recCtx, recCanvas, mapCanvas, {distKm:totalKm,tSec:(performance.now()-tStart)/1000,totalKm,recording:record});
    }
  }

  if (record && recorder) {
    const stopped = new Promise(r => recorder.onstop = r);
    recorder.stop();
    await stopped;
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

/* ===== Revisi 17 Juni 2026 — Handler Upload Screenshot Strava → Rute ===== */
(function(){
  var btn = document.getElementById('btnStravaAI');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    var inp = document.getElementById('stravaShot');
    var hintEl = document.getElementById('stravaHint');
    var stat = document.getElementById('stravaStat');
    var f = inp.files[0];
    if (!f) { stat.textContent = 'Pilih gambar screenshot Strava dulu.'; return; }
    var oh = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Membaca rute…';
    stat.innerHTML = '<span class="spinner-border spinner-border-sm"></span> AI menganalisa screenshot…';
    try {
      var fd = new FormData();
      fd.append('csrf', '<?= csrf_token() ?>');
      fd.append('_action', 'ai_route_from_image');
      fd.append('hint', (hintEl.value||'').trim());
      fd.append('image', f);
      var r = await fetch('/api_run.php', { method:'POST', body: fd, credentials:'same-origin' });
      var d = await r.json();
      if (!d.ok) { stat.textContent = 'Gagal: '+(d.err||'?'); return; }
      if (!d.coords || d.coords.length < 2) { stat.textContent = 'Rute kurang dari 2 titik.'; return; }
      // Pakai sebagai routePts untuk flyover
      routePts = d.coords;
      SESSION_INFO = { jarak_m: 0, durasi_dtk: 0 };
      drawAll(); buildKmMarkers();
      document.getElementById('btnPreview').disabled = false;
      document.getElementById('btnRecord').disabled = false;
      // hitung total km untuk info
      var km = 0;
      for (var i=1;i<d.coords.length;i++){
        var a=d.coords[i-1], b=d.coords[i], R=6371;
        var dLat=(b[0]-a[0])*Math.PI/180, dLng=(b[1]-a[1])*Math.PI/180;
        var s=Math.sin(dLat/2)**2+Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
        km += 2*R*Math.asin(Math.sqrt(s));
      }
      stat.innerHTML = 'Berhasil! '+d.coords.length+' titik · ~'+km.toFixed(2)+' km dari screenshot.'+(d.note?'<br><em>'+d.note+'</em>':'');
    } catch(e){ stat.textContent = 'Error: '+e.message; }
    btn.disabled = false; btn.innerHTML = oh;
  });
})();
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>

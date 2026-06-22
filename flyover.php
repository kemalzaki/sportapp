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

// Revisi 19 Juni 2026 — Ambil foto profil user untuk dipakai sebagai ikon pelari di video
$userRow = db_one("SELECT foto_url FROM users WHERE id=$1", [$uid]);
$userPhoto = trim((string)($userRow['foto_url'] ?? ''));
if ($userPhoto === '') $userPhoto = '/assets/img/avatar-default.png';

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
/* Revisi 19 Juni 2026 — Ikon runner kini memakai foto profil user (background image). */
.fly-icon.runner{width:42px;height:42px;border-color:#dbeafe;background:#3b82f6 center/cover no-repeat;}
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

        <!-- Revisi 19 Juni 2026 — Rute dari Import GPX (Strava / Garmin / Komoot / dll) -->
        <div class="border rounded p-2 mb-2 bg-warning-subtle">
          <label class="form-label small fw-bold mb-1"><i class="bi bi-file-earmark-arrow-down text-warning"></i> Atau: Import Rute dari File GPX</label>
          <input type="file" id="gpxFile" class="form-control form-control-sm mb-1" accept=".gpx,application/gpx+xml,text/xml">
          <button type="button" id="btnGpxLoad" class="btn btn-sm btn-warning w-100"><i class="bi bi-cloud-arrow-up"></i> Muat Rute dari GPX</button>
          <div id="gpxStat" class="small text-muted mt-1"></div>
          <details class="small mt-1">
            <summary class="text-warning-emphasis fw-semibold" style="cursor:pointer">
              <i class="bi bi-question-circle"></i> Cara ekspor GPX dari Strava (klik untuk lihat)
            </summary>
            <ol class="small ps-3 mt-1 mb-0">
              <li>Buka <a href="https://www.strava.com" target="_blank" rel="noopener">strava.com</a> di browser desktop dan login.</li>
              <li>Masuk ke menu <b>Training → My Activities</b>, klik aktivitas (sesi lari/sepeda) yang ingin di-ekspor.</li>
              <li>Di halaman detail aktivitas, klik ikon <b>Actions (titik tiga / panah ⋯)</b> di pojok kanan atas, lalu pilih <b>“Export GPX”</b>.</li>
              <li>File <code>.gpx</code> akan terunduh. Tarik / pilih file tersebut di kolom di atas, lalu klik <b>Muat Rute dari GPX</b>.</li>
              <li>Catatan: untuk aktivitas dari perangkat (Garmin/Coros), gunakan <b>“Export Original”</b> bila tersedia agar lebih akurat.</li>
            </ol>
          </details>
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
        <!-- Revisi 19 Juni 2026 — Toggle Logo & Copyright HapFam pada video rekaman -->
        <div class="form-check form-switch mb-1">
          <input class="form-check-input" type="checkbox" id="optBrandLogo" checked>
          <label class="form-check-label small" for="optBrandLogo"><i class="bi bi-image text-info"></i> Tampilkan Logo HapFam (pojok kanan-bawah)</label>
        </div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="optBrandCopyright" checked>
          <label class="form-check-label small" for="optBrandCopyright"><i class="bi bi-c-circle text-info"></i> Tampilkan Copyright "© HapFam 2026 • Sport"</label>
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
            <button type="button" class="btn btn-outline-danger" id="btnMusicYt" title="Cari di YouTube (alternatif iTunes)"><i class="bi bi-youtube"></i></button>
          </div>
          <div id="musicResults" class="list-group list-group-flush small mb-1" style="max-height:180px;overflow:auto;border:1px solid var(--bs-border-color,#e5e7eb);border-radius:8px;display:none"></div>
          <!-- Revisi 22 Juni 2026 R7 — hasil YouTube (alternatif iTunes) -->
          <div id="musicYtResults" class="mb-1" style="display:none"></div>

          <label class="form-label small mb-1 mt-1">Atau upload file audio sendiri</label>
          <input type="file" id="musicFile" class="form-control form-control-sm" accept="audio/*">
          <small class="text-muted d-block mt-1">Preview iTunes ±30 detik. Kalau kosong, dipakai musik bawaan.</small>
          <audio id="musicAudio" preload="auto" controls class="w-100 mt-2" style="height:34px"></audio>
          <!-- Revisi 20 Juni 2026 R3 — Tombol Refresh Preview iTunes (atasi audio tidak bisa play saat pilih lagu ke-2) -->
          <button type="button" id="btnMusicRefresh" class="btn btn-sm btn-outline-warning w-100 mt-1">
            <i class="bi bi-arrow-clockwise"></i> Refresh Preview iTunes
          </button>
          <small class="text-muted d-block">Tekan jika musik tidak bisa diputar setelah memilih lagu lain.</small>
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
            <!-- Revisi 20 Juni 2026 R3 — Terjemah lirik EN → ID (subtitle ganda) -->
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" id="optLyricTranslate">
              <label class="form-check-label small" for="optLyricTranslate"><i class="bi bi-translate text-primary"></i> Tampilkan terjemahan Indonesia di bawah lirik EN</label>
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
            <!-- Revisi 20 Juni 2026 R4 — Tombol direct Google + Generate lirik via AI -->
            <div class="d-flex gap-1 mt-1 flex-wrap">
              <button type="button" id="btnLyricGoogle" class="btn btn-outline-danger btn-sm flex-fill">
                <i class="bi bi-google"></i> Cari di Google
              </button>
              <button type="button" id="btnLyricGen" class="btn btn-success btn-sm flex-fill">
                <i class="bi bi-magic"></i> Generate Lirik (AI)
              </button>
            </div>
            <small class="text-muted d-block mt-1">
              <i class="bi bi-info-circle"></i> <b>Cari di Google</b> membuka tab baru hasil pencarian lirik (mudah copy-paste). <b>Generate Lirik (AI)</b> meminta Google Gemini menuliskan lirik lengkap berdasarkan judul/artis.
            </small>
            <!-- Revisi 19 Juni 2026 — Sinkron Lirik & Musik via Gemini AI (→ format LRC) -->
            <button type="button" id="btnLrcAI" class="btn btn-info btn-sm w-100 mt-1">
              <i class="bi bi-stars"></i> Sinkron Lirik dgn Musik via AI (LRC)
            </button>
            <small id="lyricStat" class="text-muted d-block mt-1">Belum ada lirik.</small>

            <!-- Revisi 19 Juni 2026 Part R — Pengaturan tampilan subtitle & foto pelari -->
            <hr class="my-2">
            <label class="form-label small fw-bold mb-1"><i class="bi bi-sliders"></i> Desain Subtitle & Foto Pelari</label>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small mb-0">Ukuran subtitle</label>
                <select id="optLyricSize" class="form-select form-select-sm">
                  <option value="12">Subtitle Film XS (12px)</option>
                  <option value="14" selected>Subtitle Film (14px) — default</option>
                  <option value="16">Subtitle Film Sedang (16px)</option>
                  <option value="20">Sedang (20px)</option>
                  <option value="26">Normal (26px)</option>
                  <option value="32">Besar (32px)</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-0">Jenis huruf subtitle</label>
                <select id="optLyricFont" class="form-select form-select-sm">
                  <option value="system-ui, sans-serif" selected>System Sans</option>
                  <option value="'Poppins', system-ui, sans-serif">Poppins</option>
                  <option value="'Inter', system-ui, sans-serif">Inter</option>
                  <option value="'Roboto', system-ui, sans-serif">Roboto</option>
                  <option value="'Montserrat', system-ui, sans-serif">Montserrat</option>
                  <option value="Georgia, 'Times New Roman', serif">Georgia (serif)</option>
                  <option value="'Courier New', monospace">Monospace</option>
                  <option value="'Comic Sans MS', cursive">Comic Sans</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-0">Warna subtitle</label>
                <input type="color" id="optLyricColor" class="form-control form-control-color form-control-sm" value="#fef9c3">
              </div>
              <div class="col-6">
                <label class="form-label small mb-0">Ukuran foto pelari</label>
                <select id="optRunnerSize" class="form-select form-select-sm">
                  <option value="20">Kecil</option>
                  <option value="26" selected>Ideal</option>
                  <option value="34">Sedang</option>
                  <option value="44">Besar</option>
                </select>
              </div>
              <!-- Revisi 21 Juni 2026 R4 — Posisi subtitle di video -->
              <div class="col-12">
                <label class="form-label small mb-0"><i class="bi bi-arrows-move"></i> Posisi Subtitle</label>
                <select id="optLyricPos" class="form-select form-select-sm">
                  <option value="bottom-center" selected>Bawah · Tengah (default)</option>
                  <option value="bottom-left">Bawah · Kiri</option>
                  <option value="bottom-right">Bawah · Kanan</option>
                  <option value="top-center">Atas · Tengah</option>
                  <option value="top-left">Atas · Kiri</option>
                  <option value="top-right">Atas · Kanan</option>
                  <option value="middle-center">Tengah Layar</option>
                </select>
              </div>
            </div>
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
          <!-- Revisi 20 Juni 2026 — Subtitle gaya FILM: tanpa kotak,
               teks putih kecil dengan outline hitam, multi-baris bila panjang. -->
          <div id="flyLyric" style="position:absolute;left:50%;bottom:54px;transform:translateX(-50%);z-index:6;
               color:#fff;font-weight:600;font-size:14px;line-height:1.25;
               text-shadow:0 0 4px #000,0 0 4px #000,1px 1px 2px #000,-1px 1px 2px #000,1px -1px 2px #000,-1px -1px 2px #000;
               max-width:84%;text-align:center;display:none;
               font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
               white-space:pre-line"></div>
          <!-- Revisi 20 Juni 2026 R3 — Subtitle terjemahan EN→ID di bawah lirik asli -->
          <div id="flyLyricID" style="position:absolute;left:50%;bottom:30px;transform:translateX(-50%);z-index:6;
               color:#fde68a;font-weight:600;font-size:12px;line-height:1.2;
               text-shadow:0 0 4px #000,0 0 4px #000,1px 1px 2px #000,-1px 1px 2px #000,1px -1px 2px #000,-1px -1px 2px #000;
               max-width:84%;text-align:center;display:none;font-style:italic;
               font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;white-space:pre-line"></div>
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
/* Revisi 19 Juni 2026 — Foto profil user untuk ikon pelari di flyover */
const USER_PHOTO_URL = <?= json_encode($userPhoto) ?>;
var USER_PHOTO_IMG = new Image();
USER_PHOTO_IMG.crossOrigin = 'anonymous';
USER_PHOTO_IMG.src = USER_PHOTO_URL;
var USER_PHOTO_READY = false;
USER_PHOTO_IMG.onload = function(){ USER_PHOTO_READY = true; };
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
  let a = $('musicAudio');
  const f = $('musicFile').files[0];
  if (f){
    // Revisi 21 Juni 2026 R4 — Upload sendiri TIDAK butuh CORS (blob: URL same-origin).
    // Ganti elemen agar MediaElementSource lama (yg ber-CORS anonymous) tidak ikut.
    const newA = a.cloneNode(false);
    newA.id='musicAudio'; newA.preload='auto'; newA.controls=true; newA.className='w-100 mt-2'; newA.style.height='34px';
    try { a.pause(); a.removeAttribute('src'); a.load(); } catch(_){}
    a.replaceWith(newA); a = newA;
    a.crossOrigin = null;
    a.removeAttribute('crossorigin');
    const blobUrl = URL.createObjectURL(f);
    a.src = blobUrl; a.dataset.originalSrc = blobUrl;
    MUSIC.currentTitle=f.name; MUSIC.currentArtist='';
  } else if (!a.src){
    a.src = 'https://cdn.pixabay.com/download/audio/2022/03/15/audio_8e3a8af6c4.mp3?filename=energetic-indie-rock-30sec-117279.mp3';
    a.crossOrigin = 'anonymous';
    a.dataset.originalSrc = a.src;
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

/* Revisi 22 Juni 2026 R7 — Tombol pencarian lagu via YouTube (alternatif iTunes
   yang sering tidak ada hasil). Menggunakan endpoint /api_yt_search.php (pola
   sama dengan artikel_olahraga.php). Hasil ditampilkan sebagai iframe embed yang
   bisa diputar — tidak terhubung ke trim/record, hanya pemutar tambahan. */
document.addEventListener('DOMContentLoaded', function(){
  var btnYt = document.getElementById('btnMusicYt');
  if (!btnYt) return;
  btnYt.addEventListener('click', async function(){
    var q = ($('musicQ').value||'').trim();
    var out = document.getElementById('musicYtResults');
    if (!q) { out.style.display='none'; return; }
    out.style.display='block';
    out.innerHTML = '<div class="small text-muted py-2"><span class="spinner-border spinner-border-sm"></span> Mencari di YouTube…</div>';
    try {
      var r = await fetch('/api_yt_search.php?q='+encodeURIComponent(q+' lagu'), {credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok) throw new Error(j.err||'tidak ada hasil');
      var ids = (j.ids&&j.ids.length) ? j.ids : (j.video?[j.video]:[]);
      if (!ids.length) throw new Error('tidak ada hasil');
      ids = ids.slice(0,3);
      var html = '<div class="small text-muted mb-1"><b>YouTube</b> — alternatif jika iTunes kosong. Putar untuk dengar musik (tidak terhubung ke trim/record).</div>';
      ids.forEach(function(vid){
        html += '<div class="ratio ratio-16x9 mb-1 rounded overflow-hidden border">'+
          '<iframe loading="lazy" allowfullscreen src="https://www.youtube-nocookie.com/embed/'+encodeURIComponent(vid)+'?rel=0" '+
          'allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>'+
          '</div>';
      });
      out.innerHTML = html;
    } catch(e) {
      out.innerHTML = '<div class="small text-danger py-2"><i class="bi bi-exclamation-triangle"></i> Gagal: '+escapeHtml(e.message||String(e))+'</div>';
    }
  });
});

function escapeHtml(s){ return String(s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c])); }
function pickMusic(t){
  // Revisi 21 Juni 2026 R4 — Ganti elemen audio sepenuhnya supaya:
  //  (1) Lagu ke-2 dst PASTI bisa diputar (lepas dari MediaElementSource lama).
  //  (2) Tidak bentrok bila sebelumnya di-record (createMediaElementSource hanya boleh sekali per elemen).
  let a = $('musicAudio');
  const newA = a.cloneNode(false);
  newA.id = 'musicAudio'; newA.preload='auto'; newA.controls=true; newA.className='w-100 mt-2'; newA.style.height='34px';
  // Putus referensi MediaElementSource pada elemen lama
  try { a.pause(); a.removeAttribute('src'); a.load(); } catch(_){}
  a.replaceWith(newA);
  a = newA;
  a.crossOrigin = 'anonymous';
  a.dataset.originalSrc = t.previewUrl;
  a.src = t.previewUrl; a.loop = true; a.load();
  $('musicMeta').innerHTML = '<i class="bi bi-music-note"></i> '+escapeHtml(t.trackName)+' — '+escapeHtml(t.artistName);
  MUSIC.currentTitle = t.trackName||''; MUSIC.currentArtist = t.artistName||'';
  $('lyricTitle').value = MUSIC.currentTitle;
  $('lyricArtist').value = MUSIC.currentArtist;
  TRIM.start=0; TRIM.end=0;
  LYRICS.trans = {};
  a.addEventListener('loadedmetadata', onAudioMeta, { once:true });
  if ($('optLyricAuto') && $('optLyricAuto').checked) {
    fetchLyricsByMeta(MUSIC.currentArtist, MUSIC.currentTitle);
  }
}

/* Revisi 20 Juni 2026 R3 — Tombol refresh preview iTunes */
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('btnMusicRefresh');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var a = document.getElementById('musicAudio');
    if (!a) return;
    var src = a.dataset.originalSrc || a.currentSrc || a.src;
    if (!src){ alert('Belum ada musik dipilih.'); return; }
    try { a.pause(); } catch(_){}
    a.removeAttribute('src'); a.load();
    setTimeout(function(){
      a.src = src; a.load();
      var p = a.play(); if (p && p.catch) p.catch(function(){});
    }, 80);
  });
});


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
    var srcRaw = a.dataset.originalSrc;
    /* Revisi 22 Juni 2026 R7 — Atasi "Failed to fetch" (CORS audio sumber).
       Audio iTunes / mzstatic kadang tidak mengembalikan header CORS, sehingga
       fetch() di browser gagal walau MediaElement bisa memutarnya. Solusi:
       route URL melalui /api_audio_proxy.php (whitelist host) yang merespon
       dengan Access-Control-Allow-Origin: *. URL blob:/data: dilewatkan. */
    var fetchUrl = srcRaw;
    if (/^https?:/i.test(srcRaw)) {
      try {
        var h = (new URL(srcRaw)).host.toLowerCase();
        if (/(\.mzstatic\.com|\.apple\.com|itunes\.apple\.com)$/.test(h)) {
          fetchUrl = '/api_audio_proxy.php?u=' + encodeURIComponent(srcRaw);
        }
      } catch(_) {}
    }
    var resp = await fetch(fetchUrl, srcRaw.startsWith('blob:')?{}:{mode:'cors', credentials:'same-origin'});
    if (!resp.ok) throw new Error('HTTP '+resp.status);
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
    if ($('lyricManual').value.trim()){ $('lyricManual').dispatchEvent(new Event('input')); }
    try{ ac.close(); }catch(_){}
  } catch(err){ $('trimStat').textContent = 'Gagal: '+err.message+' — coba refresh preview iTunes lalu trim lagi.'; }
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
/* (legacy LYRICS var dideklarasikan ulang di bawah dengan field trans) */
/* ============================================================
   Revisi 18 Juni 2026 (C) — Lirik dari pencarian publik (iTunes + lyrics.ovh)
   Tidak menggunakan AI. Saat user memilih lagu dari pencarian musik (atau
   pencarian lirik di bawah), lirik diambil otomatis dari lyrics.ovh dan
   langsung mengisi textbox/subtitle.
   ============================================================ */
var LYRICS = { lines: [], src: '', trans: {} };

async function fetchLyricsByMeta(artist, title){
  if (!title){ $('lyricStat').textContent='Belum ada judul lagu.'; return; }
  $('lyricStat').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari lirik…';
  // Revisi 20 Juni 2026 — pakai proxy /api_lyrics.php (server-side, paralel
  // ke lrclib.net + lyrics.ovh) supaya pencarian CEPAT dan banyak yang ketemu.
  // Pakai AbortController dengan timeout 8 detik agar tidak pernah "lama".
  const ctrl = new AbortController();
  const tid = setTimeout(()=>ctrl.abort(), 8000);
  try {
    const r = await fetch('/api_lyrics.php?artist='+encodeURIComponent(artist||'')+'&title='+encodeURIComponent(title||''),
                         { signal: ctrl.signal, cache:'no-store' });
    clearTimeout(tid);
    const j = r.ok ? await r.json() : null;
    const lyr = (j && j.ok) ? ((j.lrc||'').trim() || (j.lyrics||'').trim()) : '';
    if (!lyr){
      $('lyricStat').textContent = 'Lirik tidak ditemukan ('+(j && j.err ? j.err : 'sumber tidak punya')+'). Tempel manual di bawah.';
      return;
    }
    $('lyricManual').value = lyr;
    $('lyricManual').dispatchEvent(new Event('input'));
    $('optLyric').checked = true;
    LYRICS.src = (j.source||'proxy');
    $('lyricStat').textContent = LYRICS.lines.length+' baris lirik siap (sumber: '+LYRICS.src+').';
  } catch(e){
    clearTimeout(tid);
    if (e.name === 'AbortError') $('lyricStat').textContent = 'Pencarian lirik timeout (8 dtk). Coba lagi atau tempel manual.';
    else $('lyricStat').textContent = 'Error: '+e.message;
  }
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
// Revisi 18 Juni 2026 (D) — bila durasi audio baru diketahui setelah metadata
// dimuat, hitung ulang tempo lirik agar pas dengan lagu.
document.getElementById('musicAudio').addEventListener('loadedmetadata', function(){
  if ($('lyricManual').value.trim()) $('lyricManual').dispatchEvent(new Event('input'));
});
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
    const intro = Math.min(4, Math.max(1.2, dur*0.06));
    const outro = Math.min(3, Math.max(0.8, dur*0.04));
    const usable = Math.max(2, dur - intro - outro);
    const weights = lines.map(l => Math.max(4, l.line.replace(/\s+/g,' ').length));
    const sumW = weights.reduce((a,b)=>a+b, 0);
    const minDur = 1.2, maxDur = 6.0, lead = 0.25;
    let durs = weights.map(w => (w/sumW) * usable);
    durs = durs.map(d => Math.min(maxDur, Math.max(minDur, d)));
    const total = durs.reduce((a,b)=>a+b, 0);
    const scale = usable / total;
    durs = durs.map(d => d*scale);
    let t = intro;
    lines.forEach((l, i) => {
      l.t = Math.max(0, t - lead);
      t += durs[i];
    });
  }
  LYRICS.lines = lines.filter(l=>l.t>=0).sort((a,b)=>a.t-b.t);
  LYRICS.src = 'manual';
  $('optLyric').checked = true;
  $('lyricStat').textContent = LYRICS.lines.length+' baris lirik siap (estimasi tempo). Menganalisa tempo musik…';
  // Revisi 18 Juni 2026 (E) — Snap timestamp lirik ke onset/beat musik agar PAS dengan tempo.
  syncLyricsToBeats().then(ok=>{
    if (ok) $('lyricStat').textContent = LYRICS.lines.length+' baris lirik tersinkron ke tempo musik ('+LYRICS.src+').';
  }).catch(()=>{});
});

/* ============================================================
   Revisi 18 Juni 2026 (E) — Sinkronisasi Lirik ↔ Tempo Musik
   ------------------------------------------------------------
   Pipeline:
   1) Decode audio (WebAudio).
   2) Hitung envelope energi (RMS) per ~23 ms.
   3) Cari ONSET: titik di mana energi naik tajam dibanding window
      sebelumnya (spectral-flux sederhana di domain waktu).
   4) Estimasi BPM via autocorrelation envelope (60–180 BPM).
   5) Snap timestamp tiap baris lirik ke onset terdekat (toleransi
      ±0.5 detik dari estimasi awal). Sisa baris di-spread ke beat
      bila onset kurang.
   ============================================================ */
async function syncLyricsToBeats(){
  if (!LYRICS.lines.length) return false;
  const a = $('musicAudio');
  const srcUrl = a.dataset.originalSrc || a.currentSrc || a.src;
  if (!srcUrl) return false;
  try {
    const resp = await fetch(srcUrl, srcUrl.startsWith('blob:')?{}:{mode:'cors'});
    if (!resp.ok) throw new Error('fetch audio gagal');
    const buf  = await resp.arrayBuffer();
    const ac   = new (window.AudioContext||window.webkitAudioContext)();
    const dec  = await ac.decodeAudioData(buf.slice(0));
    // Mix-down ke mono
    const ch0 = dec.getChannelData(0);
    const ch1 = dec.numberOfChannels>1 ? dec.getChannelData(1) : ch0;
    const sr  = dec.sampleRate;
    // Range yang dianalisa: kalau ada trim, gunakan rentang trim
    const sStart = TRIM.applied ? Math.floor(TRIM.start*sr) : 0;
    const sEnd   = TRIM.applied ? Math.floor(TRIM.end*sr)   : dec.length;
    const frame = Math.max(256, Math.floor(sr*0.023)); // ~23 ms
    const env = [];
    for (let i=sStart; i<sEnd; i+=frame){
      let s=0, n=Math.min(frame, sEnd-i);
      for (let j=0;j<n;j++){ const v=(ch0[i+j]+ch1[i+j])*0.5; s+=v*v; }
      env.push(Math.sqrt(s/n));
    }
    try{ ac.close(); }catch(_){}
    // Smooth envelope
    const sm = new Float32Array(env.length);
    for (let i=0;i<env.length;i++){
      let s=0, c=0;
      for (let k=-2;k<=2;k++){ const idx=i+k; if(idx>=0 && idx<env.length){ s+=env[idx]; c++; } }
      sm[i]=s/c;
    }
    // Spectral-flux / onset: positive diff > threshold
    const diff = new Float32Array(env.length);
    for (let i=1;i<env.length;i++){ diff[i] = Math.max(0, sm[i]-sm[i-1]); }
    let mean=0; for (let i=0;i<diff.length;i++) mean+=diff[i]; mean/=diff.length;
    let stdev=0; for (let i=0;i<diff.length;i++){ const d=diff[i]-mean; stdev+=d*d; } stdev=Math.sqrt(stdev/diff.length);
    const thr = mean + 1.3*stdev;
    const minGap = Math.floor(0.18 * sr / frame); // min 180 ms antar onset
    const onsets = []; let last=-minGap*2;
    for (let i=2;i<diff.length-1;i++){
      if (diff[i]>thr && diff[i]>=diff[i-1] && diff[i]>=diff[i+1] && (i-last)>=minGap){
        onsets.push((i*frame)/sr); last=i;
      }
    }
    if (onsets.length < 4) return false;
    // BPM via inter-onset interval modus (60–180 bpm)
    const ioi = [];
    for (let i=1;i<onsets.length;i++) ioi.push(onsets[i]-onsets[i-1]);
    ioi.sort((a,b)=>a-b);
    const med = ioi[Math.floor(ioi.length/2)] || 0.5;
    let bpm = 60/med; while (bpm<60) bpm*=2; while (bpm>180) bpm/=2;
    // Snap setiap baris lirik ke onset TERDEKAT (toleransi ±0.6s); fallback ke beat grid bila terlalu jauh.
    const beat = 60/bpm;
    const tol  = Math.max(0.45, beat*0.55);
    const used = new Set();
    LYRICS.lines.forEach(l => {
      let bestIdx=-1, bestD=Infinity;
      for (let k=0;k<onsets.length;k++){
        if (used.has(k)) continue;
        const d = Math.abs(onsets[k] - l.t);
        if (d<bestD){ bestD=d; bestIdx=k; }
      }
      if (bestIdx>=0 && bestD<=tol){
        l.t = onsets[bestIdx]; used.add(bestIdx);
      } else {
        // snap ke grid beat terdekat
        l.t = Math.round(l.t/beat)*beat;
      }
    });
    LYRICS.lines.sort((a,b)=>a.t-b.t);
    // Jaga jarak minimum antar baris (≥ beat/2) supaya tidak tumpang tindih
    for (let i=1;i<LYRICS.lines.length;i++){
      if (LYRICS.lines[i].t - LYRICS.lines[i-1].t < beat*0.5){
        LYRICS.lines[i].t = LYRICS.lines[i-1].t + beat*0.5;
      }
    }
    LYRICS.src = (LYRICS.src||'manual')+' • beat-sync '+bpm.toFixed(0)+'bpm';
    return true;
  } catch(e){
    console.warn('syncLyricsToBeats gagal:', e);
    return false;
  }
}
function currentLyricLine(audioTime){
  if (!$('optLyric').checked || !LYRICS.lines.length) return '';
  let cur = '';
  for (const l of LYRICS.lines){ if (l.t <= audioTime+0.05) cur = l.line; else break; }
  return cur;
}
// Ticker live untuk overlay HTML subtitle di preview
setInterval(() => {
  const el = document.getElementById('flyLyric');
  const elId = document.getElementById('flyLyricID');
  if (!el) return;
  const a = $('musicAudio');
  if (!$('optLyric').checked || !LYRICS.lines.length || !a || a.paused){
    el.style.display='none'; if(elId) elId.style.display='none'; return;
  }
  const line = currentLyricLine(a.currentTime);
  // Revisi 21 Juni 2026 R4 — terapkan posisi subtitle pada overlay HTML
  applyLyricPos(el, elId);
  if (line){
    const sizeOpt = document.getElementById('optLyricSize');
    const fontOpt = document.getElementById('optLyricFont');
    const colorOpt = document.getElementById('optLyricColor');
    if (sizeOpt) el.style.fontSize = (parseFloat(sizeOpt.value)||26)+'px';
    if (fontOpt) el.style.fontFamily = fontOpt.value;
    if (colorOpt) el.style.color = colorOpt.value;
    el.textContent = line; el.style.display='';
    if (elId){
      const trOn = document.getElementById('optLyricTranslate');
      if (trOn && trOn.checked){
        if (fontOpt) elId.style.fontFamily = fontOpt.value;
        if (sizeOpt) elId.style.fontSize = (Math.max(11,(parseFloat(sizeOpt.value)||26)*0.78))+'px';
        const cached = LYRICS.trans[line];
        if (cached){ elId.textContent = cached; elId.style.display=''; }
        else { elId.style.display='none'; translateLineToID(line); }
      } else { elId.style.display='none'; }
    }
  } else { el.style.display='none'; if(elId) elId.style.display='none'; }
}, 120);

/* Revisi 21 Juni 2026 R4 — Hitung & terapkan posisi subtitle (atas/bawah/kiri/kanan/tengah) */
function getLyricPos(){
  var s = document.getElementById('optLyricPos');
  return s ? (s.value || 'bottom-center') : 'bottom-center';
}
function applyLyricPos(el, elId){
  var pos = getLyricPos();
  // Reset
  [el, elId].forEach(function(x){ if(!x) return;
    x.style.left=''; x.style.right=''; x.style.top=''; x.style.bottom=''; x.style.transform='';
    x.style.textAlign='center';
  });
  if (!el) return;
  var topMain, bottomMain;
  if (pos.indexOf('top-')===0)        { el.style.top='14px';    topMain=true; }
  else if (pos.indexOf('middle-')===0){ el.style.top='50%';     el.style.transform='translate(-50%,-50%)'; }
  else                                { el.style.bottom='54px'; bottomMain=true; }
  if (pos.endsWith('-left'))      { el.style.left='12px';  el.style.textAlign='left';  if(el.style.transform)el.style.transform=''; }
  else if (pos.endsWith('-right')){ el.style.right='12px'; el.style.textAlign='right'; if(el.style.transform)el.style.transform=''; }
  else                            { el.style.left='50%'; el.style.transform = (pos.indexOf('middle-')===0?'translate(-50%,-50%)':'translateX(-50%)'); }
  if (elId){
    if (pos.indexOf('top-')===0)        { elId.style.top='38px'; }
    else if (pos.indexOf('middle-')===0){ elId.style.top='calc(50% + 22px)'; elId.style.transform='translate(-50%,-50%)'; }
    else                                { elId.style.bottom='30px'; }
    if (pos.endsWith('-left'))      { elId.style.left='12px';  elId.style.textAlign='left';  if(elId.style.transform)elId.style.transform=''; }
    else if (pos.endsWith('-right')){ elId.style.right='12px'; elId.style.textAlign='right'; if(elId.style.transform)elId.style.transform=''; }
    else                            { elId.style.left='50%'; elId.style.transform = (pos.indexOf('middle-')===0?'translate(-50%,-50%)':'translateX(-50%)'); }
  }
}

/* Revisi 20 Juni 2026 R3 — Terjemah baris lirik EN → ID via MyMemory (gratis, tanpa key) */
var _trInflight = {};
async function translateLineToID(line){
  if (!line || LYRICS.trans[line] || _trInflight[line]) return;
  _trInflight[line] = true;
  try{
    const url = 'https://api.mymemory.translated.net/get?q='+encodeURIComponent(line)+'&langpair=en|id';
    const r = await fetch(url, { cache: 'force-cache' });
    const j = await r.json();
    const t = (j && j.responseData && j.responseData.translatedText) ? String(j.responseData.translatedText) : '';
    if (t) LYRICS.trans[line] = t;
  }catch(_){ /* abaikan */ }
  finally { delete _trInflight[line]; }
}

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
    if (runnerLngLat){
      // Revisi 19 Juni 2026 Part R — ukuran foto pelari dapat diatur user (default ideal 26px)
      const runnerOpt = document.getElementById('optRunnerSize');
      const runnerBase = runnerOpt ? parseFloat(runnerOpt.value)||26 : 26;
      const r = project(runnerLngLat); const rad = runnerBase*sx;
      // Revisi 19 Juni 2026 — gambar foto profil user di posisi pelari
      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,.4)'; ctx.shadowBlur = 12; ctx.shadowOffsetY = 4;
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(r[0],r[1],rad+3,0,Math.PI*2); ctx.fill();
      ctx.shadowColor = 'transparent';
      ctx.save();
      ctx.beginPath(); ctx.arc(r[0],r[1],rad,0,Math.PI*2); ctx.clip();
      if (USER_PHOTO_READY){
        try { ctx.drawImage(USER_PHOTO_IMG, r[0]-rad, r[1]-rad, rad*2, rad*2); }
        catch(_){ ctx.fillStyle='#3b82f6'; ctx.fillRect(r[0]-rad,r[1]-rad,rad*2,rad*2); ctx.fillStyle='#fff'; ctx.font='700 '+(rad*0.8)+'px system-ui'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('🏃', r[0], r[1]); }
      } else {
        ctx.fillStyle='#3b82f6'; ctx.fillRect(r[0]-rad,r[1]-rad,rad*2,rad*2);
        ctx.fillStyle='#fff'; ctx.font='700 '+(rad*0.8)+'px system-ui'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('🏃', r[0], r[1]);
      }
      ctx.restore();
      ctx.lineWidth = Math.max(2, rad*0.12); ctx.strokeStyle = '#3b82f6';
      ctx.beginPath(); ctx.arc(r[0],r[1],rad,0,Math.PI*2); ctx.stroke();
      ctx.restore();
    }
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

  /* Revisi 20 Juni 2026 — Subtitle gaya FILM pada video rekaman:
     - Tanpa kotak/background (clean cinematic).
     - Ukuran default KECIL (≈14px logical, mirip subtitle film).
     - Word-wrap multi-baris bila kalimat panjang agar tidak terpotong.
     - Outline hitam (text-shadow) supaya tetap terbaca di atas peta. */
  if ($('optLyric') && $('optLyric').checked && LYRICS.lines.length){
    const a = $('musicAudio');
    const tNow = a && !a.paused ? a.currentTime : (o.tSec||0);
    const lyric = currentLyricLine(tNow);
    if (lyric){
      ctx.save();
      const sizeOpt = document.getElementById('optLyricSize');
      const fontOpt = document.getElementById('optLyricFont');
      const colorOpt = document.getElementById('optLyricColor');
      const posOpt  = document.getElementById('optLyricPos');
      const trOpt   = document.getElementById('optLyricTranslate');
      const userSize = sizeOpt ? (parseFloat(sizeOpt.value)||14) : 14;
      const fontFam = fontOpt ? (fontOpt.value || "system-ui, sans-serif") : "system-ui, sans-serif";
      const subtitleColor = colorOpt ? (colorOpt.value || '#ffffff') : '#ffffff';
      const pos = posOpt ? (posOpt.value || 'bottom-center') : 'bottom-center';
      const fs = Math.max(12, userSize*sx);

      // Helper word-wrap & draw a block of text at (anchorX, baseY) with align
      function wrap(text, ctxx, maxWW){
        const ws = String(text).split(/\s+/).filter(Boolean);
        const ls = []; let curw = '';
        ws.forEach(wd => {
          const test = curw ? (curw+' '+wd) : wd;
          if (ctxx.measureText(test).width > maxWW && curw){ ls.push(curw); curw = wd; }
          else { curw = test; }
        });
        if (curw) ls.push(curw);
        const maxLines = 3;
        let shown = ls.slice(0, maxLines);
        if (ls.length > maxLines){
          let last = shown[maxLines-1];
          while (ctxx.measureText(last+'…').width > maxWW && last.length>3) last = last.slice(0,-2);
          shown[maxLines-1] = last + '…';
        }
        return shown;
      }
      function blockPos(blockH, fsz){
        // Returns {x, y, align} - x=anchor, y=top baseline of first line
        let align='center', ax=w/2, ay;
        if (pos.endsWith('-left'))      { align='left';  ax = 18*sx; }
        else if (pos.endsWith('-right')){ align='right'; ax = w - 18*sx; }
        if (pos.indexOf('top-')===0)        ay = 18*sy + fsz*0.85;
        else if (pos.indexOf('middle-')===0)ay = (h - blockH)/2 + fsz*0.85;
        else                                ay = h - 30*sy - blockH + fsz*0.85;
        return { ax: ax, ay: ay, align: align };
      }
      function drawBlock(text, fsz, color){
        ctx.font = '600 '+fsz+'px '+fontFam;
        const maxW = (pos.endsWith('-left')||pos.endsWith('-right')) ? w*0.6 : w*0.84;
        const shown = wrap(text, ctx, maxW);
        const lh = fsz * 1.22;
        const blockH = lh * shown.length;
        const p = blockPos(blockH, fsz);
        ctx.textAlign = p.align; ctx.textBaseline='alphabetic';
        ctx.lineJoin='round'; ctx.miterLimit=2;
        ctx.strokeStyle='rgba(0,0,0,0.92)';
        ctx.lineWidth = Math.max(3, fsz*0.22);
        shown.forEach((ln,i)=>{ ctx.strokeText(ln, p.ax, p.ay + i*lh); });
        ctx.fillStyle = color;
        shown.forEach((ln,i)=>{ ctx.fillText(ln, p.ax, p.ay + i*lh); });
        return blockH + lh*0.25; // total used vertical space for stacking
      }

      // Draw EN line
      const usedH = drawBlock(lyric, fs, subtitleColor);

      // Revisi 21 Juni 2026 R4 — Render terjemahan EN→ID di bawah lirik utama (ke video rekaman juga)
      if (trOpt && trOpt.checked){
        const tline = LYRICS.trans[lyric];
        if (tline){
          const fs2 = Math.max(11, fs*0.78);
          // Geser baseline: untuk top — di bawah; untuk middle — di bawah; untuk bottom — di atas? Simplify: stack below for top/middle, above for bottom.
          ctx.font = '600 '+fs2+'px '+fontFam;
          const maxW2 = (pos.endsWith('-left')||pos.endsWith('-right')) ? w*0.6 : w*0.84;
          const shown2 = wrap(tline, ctx, maxW2);
          const lh2 = fs2 * 1.22;
          const blockH2 = lh2 * shown2.length;
          // Anchor selalu pakai blockPos dengan offset
          const p = blockPos(blockH2, fs2);
          let offY;
          if (pos.indexOf('bottom-')===0) {
            // letakkan di atas lirik utama (sebelumnya bottom = h-30-blockH+fs*.85)
            // baris EN paling atas berada di h-30sy - usedH + fs*.85
            offY = -(usedH - blockH2*0.05);
          } else {
            // letakkan di bawah lirik utama
            offY = usedH + lh2*0.1;
          }
          ctx.textAlign = p.align; ctx.textBaseline='alphabetic';
          ctx.lineJoin='round'; ctx.miterLimit=2;
          ctx.strokeStyle='rgba(0,0,0,0.92)';
          ctx.lineWidth = Math.max(2.5, fs2*0.22);
          shown2.forEach((ln,i)=>{ ctx.strokeText(ln, p.ax, p.ay + offY + i*lh2); });
          ctx.fillStyle = '#fde68a';
          shown2.forEach((ln,i)=>{ ctx.fillText(ln, p.ax, p.ay + offY + i*lh2); });
        } else {
          // Belum ada terjemahan — picu fetch
          if (typeof translateLineToID === 'function') translateLineToID(lyric);
        }
      }
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
  // Revisi 19 Juni 2026 — Toggle logo & copyright dari UI
  var elLogo = document.getElementById('optBrandLogo');
  var elCopy = document.getElementById('optBrandCopyright');
  var showLogo = elLogo ? !!elLogo.checked : true;
  var showCopy = elCopy ? !!elCopy.checked : true;
  if (!showLogo && !showCopy) return;

  // Foto profil bulat di pojok kanan-bawah
  var size = Math.max(56, 64*sx);
  var pad  = 18*sx;
  var cx = w - size/2 - pad;
  var cy = h - size/2 - pad;
  if (showLogo) {
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
  }

  if (showCopy) {
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
        // Revisi 21 Juni 2026 R4 — cache MediaElementSource per <audio> agar
        // record ke-2 dst tidak gagal ("HTMLMediaElement already connected").
        if (audioEl._audioCtx && audioEl._audioCtx.state === 'closed') { audioEl._audioCtx = null; audioEl._mediaSrc = null; }
        if (!audioEl._audioCtx) audioEl._audioCtx = new (window.AudioContext||window.webkitAudioContext)();
        audioCtx = audioEl._audioCtx;
        if (audioCtx.state === 'suspended') { try { await audioCtx.resume(); } catch(_){} }
        if (!audioEl._mediaSrc) audioEl._mediaSrc = audioCtx.createMediaElementSource(audioEl);
        const src = audioEl._mediaSrc;
        audioDest = audioCtx.createMediaStreamDestination();
        try { src.disconnect(); } catch(_){}
        src.connect(audioDest); src.connect(audioCtx.destination);
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

  // Marker pelari (runner) yang ikut bergerak — pakai foto profil user (Revisi 19 Juni 2026)
  if ($('optIcons').checked){
    if (runnerMarker) runnerMarker.remove();
    const el = document.createElement('div');
    el.className = 'fly-icon runner';
    el.style.backgroundImage = 'url("'+USER_PHOTO_URL.replace(/"/g,'%22')+'")';
    // Revisi 19 Juni 2026 Part R — ukuran marker pelari mengikuti pilihan user (diameter px)
    const runnerOpt = document.getElementById('optRunnerSize');
    const runnerPx = runnerOpt ? Math.max(16, (parseFloat(runnerOpt.value)||26)*2) : 52;
    el.style.width = runnerPx+'px';
    el.style.height = runnerPx+'px';
    runnerMarker = new maplibregl.Marker({element:el, anchor:'center'}).setLngLat(coords[0]).addTo(map);
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
    if (useMusic){ try{ audioEl.pause(); }catch(_){ } /* JANGAN tutup audioCtx — direuse pada record berikutnya */ }
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

/* ===== Revisi 19 Juni 2026 — Handler Import GPX (Strava / Garmin / Komoot) ===== */
(function(){
  var btn = document.getElementById('btnGpxLoad');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    var inp  = document.getElementById('gpxFile');
    var stat = document.getElementById('gpxStat');
    var f = inp.files && inp.files[0];
    if (!f){ stat.textContent = 'Pilih file .gpx dulu.'; return; }
    var oh = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses…';
    try {
      var txt = await f.text();
      var doc = new DOMParser().parseFromString(txt, 'application/xml');
      if (doc.getElementsByTagName('parsererror').length){ throw new Error('File GPX tidak valid (XML rusak).'); }
      // Ambil trkpt → fallback rtept → fallback wpt
      var pts = doc.getElementsByTagName('trkpt');
      if (!pts.length) pts = doc.getElementsByTagName('rtept');
      if (!pts.length) pts = doc.getElementsByTagName('wpt');
      if (!pts.length) throw new Error('Tidak ada titik (trkpt/rtept/wpt) di GPX ini.');
      var coords = [];
      for (var i=0;i<pts.length;i++){
        var la = parseFloat(pts[i].getAttribute('lat'));
        var lo = parseFloat(pts[i].getAttribute('lon'));
        if (!isNaN(la) && !isNaN(lo)) coords.push([la, lo]);
      }
      if (coords.length < 2) throw new Error('Hanya '+coords.length+' titik valid — minimal 2 dibutuhkan.');
      // Simplifikasi: jika > 2000 titik, downsample agar peta cepat
      if (coords.length > 2000) {
        var step = Math.ceil(coords.length / 2000);
        var ds = [];
        for (var i=0;i<coords.length;i+=step) ds.push(coords[i]);
        ds.push(coords[coords.length-1]);
        coords = ds;
      }
      routePts = coords;
      SESSION_INFO = { jarak_m: 0, durasi_dtk: 0 };
      // Cari metadata waktu (durasi total)
      var times = doc.getElementsByTagName('time');
      if (times.length >= 2) {
        try {
          var t0 = Date.parse(times[0].textContent);
          var tN = Date.parse(times[times.length-1].textContent);
          if (!isNaN(t0) && !isNaN(tN) && tN > t0) SESSION_INFO.durasi_dtk = Math.round((tN-t0)/1000);
        } catch(_){}
      }
      // Hitung jarak total
      var km = 0;
      for (var i=1;i<coords.length;i++){
        km += haversineKm(coords[i-1], coords[i]);
      }
      SESSION_INFO.jarak_m = Math.round(km*1000);
      drawAll(); buildKmMarkers();
      document.getElementById('btnPreview').disabled = false;
      document.getElementById('btnRecord').disabled = false;
      stat.innerHTML = 'GPX dimuat! <b>'+coords.length+'</b> titik · ~<b>'+km.toFixed(2)+' km</b>'
                     + (SESSION_INFO.durasi_dtk>0?' · durasi '+Math.round(SESSION_INFO.durasi_dtk/60)+' menit':'')
                     + '. File: <em>'+(f.name||'')+'</em>';
    } catch(e){ stat.innerHTML = '<span class="text-danger">Gagal: '+e.message+'</span>'; }
    btn.disabled = false; btn.innerHTML = oh;
  });
})();

/* ===== Revisi 19 Juni 2026 — Sinkronisasi Lirik via Gemini AI (audio + lirik → LRC) ===== */
(function(){
  var btn = document.getElementById('btnLrcAI');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    var stat = document.getElementById('lyricStat');
    var ta   = document.getElementById('lyricManual');
    var lirik = (ta.value||'').trim();
    if (!lirik){ stat.textContent = 'Tempel lirik manual dulu di textarea.'; return; }
    var a = document.getElementById('musicAudio');
    if (!a.src){ stat.textContent = 'Pilih lagu (iTunes / upload) dulu.'; return; }
    var oh = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim ke Gemini AI…';
    stat.innerHTML = '<span class="spinner-border spinner-border-sm"></span> AI menganalisa musik + lirik (bisa 30–60 detik)…';
    try {
      // Ambil audio blob dari elemen player (URL bisa berupa blob:, data:, atau http remote)
      var resp = await fetch(a.src, (a.src||'').startsWith('blob:')?{}:{mode:'cors'});
      var blob = await resp.blob();
      var ext  = 'mp3';
      if (blob.type) {
        if (blob.type.indexOf('wav')>=0) ext='wav';
        else if (blob.type.indexOf('mp4')>=0 || blob.type.indexOf('m4a')>=0) ext='m4a';
        else if (blob.type.indexOf('ogg')>=0) ext='ogg';
        else if (blob.type.indexOf('webm')>=0) ext='webm';
      }
      var fd = new FormData();
      fd.append('csrf', '<?= csrf_token() ?>');
      fd.append('task', 'lyric_to_lrc');
      fd.append('lirik', lirik);
      fd.append('audio', new File([blob], 'song.'+ext, { type: blob.type || 'audio/mpeg' }));
      var r = await fetch('/api_ai.php', { method:'POST', body:fd, credentials:'same-origin' });
      var j = await r.json();
      if (!j.ok){ stat.innerHTML = '<span class="text-danger">Gagal: '+(j.err||'?')+'</span>'; }
      else if (!j.lrc || j.lrc.length < 5){ stat.innerHTML = '<span class="text-warning">AI tidak menghasilkan LRC.</span>'; }
      else {
        ta.value = j.lrc;
        ta.dispatchEvent(new Event('input'));
        $('optLyric').checked = true;
        LYRICS.src = 'gemini-lrc';
        stat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Lirik tersinkron format LRC oleh Gemini AI ('+LYRICS.lines.length+' baris).';
      }
    } catch(e){ stat.innerHTML = '<span class="text-danger">Error: '+e.message+' (kemungkinan CORS pada sumber audio — coba upload file audio sendiri).</span>'; }
    btn.disabled = false; btn.innerHTML = oh;
  });
})();

/* === Revisi 20 Juni 2026 R4 — Tombol Google + Generate Lirik via AI === */
(function(){
  function pickQuery(){
    var t = (document.getElementById('lyricTitle')||{}).value || '';
    var a = (document.getElementById('lyricArtist')||{}).value || '';
    var q = document.getElementById('lyricQ');
    if ((!t && !a) && q) return q.value || '';
    return (a+' '+t).trim();
  }
  var bG = document.getElementById('btnLyricGoogle');
  if (bG) bG.addEventListener('click', function(){
    var q = pickQuery();
    if (!q){ alert('Isi judul/artis dulu (atau pilih musik).'); return; }
    var url = 'https://www.google.com/search?q='+encodeURIComponent('lirik '+q);
    window.open(url, '_blank', 'noopener');
  });
  var bAI = document.getElementById('btnLyricGen');
  if (bAI) bAI.addEventListener('click', async function(){
    var q = pickQuery();
    if (!q){ alert('Isi judul/artis dulu (atau pilih musik).'); return; }
    var stat = document.getElementById('lyricStat');
    var ta   = document.getElementById('lyricManual');
    var orig = bAI.innerHTML;
    bAI.disabled = true; bAI.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating…';
    if (stat) stat.textContent = 'Meminta lirik ke Google Gemini…';
    try {
      var fd = new FormData();
      fd.append('csrf', '<?= csrf_token() ?>');
      fd.append('task', 'lyrics_gen');
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php', { method:'POST', body:fd, credentials:'same-origin' });
      var j = await r.json();
      if (!j.ok){ if (stat) stat.innerHTML = '<span class="text-danger">Gagal: '+(j.err||'?')+'</span>'; return; }
      var lyr = (j.text||j.lyrics||'').trim();
      if (!lyr){ if (stat) stat.innerHTML = '<span class="text-warning">AI tidak menghasilkan lirik.</span>'; return; }
      if (ta){ ta.value = lyr; ta.dispatchEvent(new Event('input')); }
      var opt = document.getElementById('optLyric'); if (opt) opt.checked = true;
      if (stat) stat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Lirik di-generate oleh Google Gemini ('+lyr.split(/\n/).filter(Boolean).length+' baris).';
    } catch(e){
      if (stat) stat.innerHTML = '<span class="text-danger">Error: '+e.message+'</span>';
    } finally {
      bAI.disabled = false; bAI.innerHTML = orig;
    }
  });
})();
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>

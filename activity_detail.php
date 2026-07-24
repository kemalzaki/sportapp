<?php
/**
 * KawanKeringat — Activity Detail (READ-ONLY, Strava-style analysis)
 * REVISI R51 — redesign TOTAL: peta + split KM + grafik pace/speed/accuracy + insight.
 *
 * TIDAK MENGUBAH:
 *  - tracking.js, gps.js, save.js, api_run.php, run.php
 *  - Skema database (kolom yang dipakai: run_points.lat/lng/ts/speed_mps/accuracy_m)
 *  - Endpoint, ID/CLASS utama yang dipakai halaman lain
 *
 * Sumber data:
 *  - upload_harian (id, tanggal, jenis, jarak_km, kalori, durasi_menit, pace_detik, gpx_session_id)
 *  - run_points   (lat, lng, ts, speed_mps, accuracy_m) via gpx_session_id
 *
 * Catatan elevasi:
 *  - Skema run_points saat ini TIDAK memiliki kolom altitude → grafik elevasi
 *    akan menampilkan "Tidak tersedia" (bukan data palsu). Kompatibel dengan
 *    data lama. Bila nanti ditambahkan kolom `altitude_m DOUBLE PRECISION NULL`
 *    pada run_points, halaman ini otomatis memakainya (lihat query di bawah).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
require_login();

$u    = current_user();
$uid  = (int)($u['id'] ?? 0);
$upId = (int)($_GET['id'] ?? 0);
$sId  = (int)($_GET['sid'] ?? 0);

$act = null;
if ($upId > 0) {
    $act = db_one("SELECT uh.id, uh.user_id, uh.tanggal, uh.jenis, uh.durasi_menit,
                          uh.jarak_km, uh.kalori, uh.pace, uh.pace_detik,
                          COALESCE(uh.gear_sepatu,'') AS gear_sepatu,
                          COALESCE(uh.gpx_session_id,0) AS gpx_session_id,
                          COALESCE(uh.deskripsi,'') AS deskripsi,
                          uh.created_at,
                          u.nama, u.foto_url
                   FROM upload_harian uh
                   JOIN users u ON u.id = uh.user_id
                   WHERE uh.id = $1", [$upId]);
    if ($act) $sId = (int)$act['gpx_session_id'];
} elseif ($sId > 0) {
    $act = db_one("SELECT uh.id, uh.user_id, uh.tanggal, uh.jenis, uh.durasi_menit,
                          uh.jarak_km, uh.kalori, uh.pace, uh.pace_detik,
                          COALESCE(uh.gear_sepatu,'') AS gear_sepatu,
                          COALESCE(uh.gpx_session_id,0) AS gpx_session_id,
                          COALESCE(uh.deskripsi,'') AS deskripsi,
                          uh.created_at,
                          u.nama, u.foto_url
                   FROM upload_harian uh
                   JOIN users u ON u.id = uh.user_id
                   WHERE uh.gpx_session_id = $1 LIMIT 1", [$sId]);
}

$pageTitle = 'Detail Aktivitas';

/* ------------------------------------------------------------------
 * Ambil titik GPS. Kolom altitude opsional (jika belum ada di DB
 * tetap jalan — kita cek information_schema sekali).
 * ------------------------------------------------------------------ */
$points   = [];
$hasAlt   = false;
if ($sId > 0) {
    try {
        $col = db_one("SELECT 1 AS ok FROM information_schema.columns
                       WHERE table_name='run_points' AND column_name='altitude_m'");
        $hasAlt = !empty($col);
    } catch (Throwable $e) { $hasAlt = false; }

    try {
        $sql = $hasAlt
          ? "SELECT lat, lng, ts, speed_mps, accuracy_m, altitude_m
             FROM run_points WHERE session_id = $1 ORDER BY id"
          : "SELECT lat, lng, ts, speed_mps, accuracy_m
             FROM run_points WHERE session_id = $1 ORDER BY id";
        $rows = db_all($sql, [$sId]);
        foreach ($rows as $r) {
            $points[] = [
                'lat' => (float)$r['lat'],
                'lng' => (float)$r['lng'],
                'ts'  => $r['ts'],
                'sp'  => isset($r['speed_mps']) && $r['speed_mps']!==null ? (float)$r['speed_mps'] : null,
                'ac'  => isset($r['accuracy_m']) && $r['accuracy_m']!==null ? (float)$r['accuracy_m'] : null,
                'alt' => $hasAlt && isset($r['altitude_m']) && $r['altitude_m']!==null ? (float)$r['altitude_m'] : null,
            ];
        }
    } catch (Throwable $e) { /* run_points tidak ada — abaikan */ }
}

/* Format helpers lokal */
function __fmt_date_id(?string $d): string {
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return htmlspecialchars($d);
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    return date('j ', $ts) . $bulan[(int)date('n', $ts)-1] . date(' Y H:i', $ts);
}
function __fmt_pace($sec): string {
    $sec = (int)$sec;
    if ($sec <= 0) return '-';
    return sprintf('%d:%02d', intdiv($sec,60), $sec % 60);
}
function __fmt_dur($sec): string {
    $sec = max(0,(int)$sec);
    $h = intdiv($sec,3600); $m = intdiv($sec%3600,60); $s = $sec%60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

include __DIR__.'/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
  :root{
    --kk-bg:#F5F7FA;
    --kk-bg-2:#F8FAFC;
    --kk-card:#FFFFFF;
    --kk-border:#E8EEF5;
    --kk-blue:#3b82f6;
    --kk-blue-2:#60a5fa;
    --kk-blue-3:#2563eb;
    --kk-cyan:#22d3ee;
    --kk-line:#E8EEF5;
    --kk-text:#1E293B;
    --kk-body:#475569;
    --kk-mute:#64748B;
    --kk-warn:#f59e0b;
    --kk-danger:#ef4444;
    --kk-ok:#22c55e;
  }
  .ad-shell{
    background: var(--kk-bg);
    min-height: 100vh;
    padding: 18px 14px 110px;
    color: var(--kk-text);
  }
  .ad-wrap{ max-width:960px; margin:0 auto; }
  .ad-topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .ad-btn{
    display:inline-flex; align-items:center; gap:6px;
    background:#FFFFFF; border:1px solid var(--kk-border);
    color:var(--kk-text); text-decoration:none; border-radius:12px;
    padding:9px 14px; font-size:14px; font-weight:500;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    transition: background .15s ease, border-color .15s ease, transform .05s ease;
  }
  .ad-btn:hover{ background:#F1F5F9; color:var(--kk-text); border-color:#D8E2EE; }
  .ad-btn.primary{
    background:linear-gradient(135deg,var(--kk-blue-3),var(--kk-blue-2));
    border-color:transparent; color:#FFFFFF; font-weight:600;
    box-shadow: 0 6px 16px -6px rgba(59,130,246,.55);
  }
  .ad-btn.primary:hover{ color:#FFFFFF; filter:brightness(1.03); }
  .ad-head{
    display:flex; align-items:center; gap:12px; margin:16px 0 18px;
  }
  .ad-head img,.ad-head .avf{
    width:46px; height:46px; border-radius:50%; object-fit:cover;
    border:1px solid var(--kk-border); background:#FFFFFF;
  }
  .ad-head .avf{ display:flex; align-items:center; justify-content:center; background:#E8F0FE; font-weight:700; color:#1d4ed8; }
  .ad-title{ margin:0; font-size:22px; font-weight:700; letter-spacing:.2px; color:var(--kk-text); }
  .ad-sub{ color:var(--kk-mute); font-size:13px; }
  .glass{
    background: var(--kk-card);
    border: 1px solid var(--kk-border);
    border-radius: 18px;
    box-shadow: 0 4px 14px rgba(15,23,42,.06);
    padding: 18px;
    margin-top: 18px;
  }
  .glass h3{ margin:0 0 14px; font-size:15px; color:var(--kk-text); font-weight:700; letter-spacing:.2px; }
  .glass h3 .sub{ color:var(--kk-mute); font-weight:400; font-size:12px; margin-left:6px; }
  .ad-map{
    height: 52vh; min-height: 340px; width:100%;
    border-radius: 14px; overflow:hidden; border:1px solid var(--kk-border);
    background:#EEF3F8;
  }
  .ad-map .leaflet-control-attribution{ font-size:10px; opacity:.75; }
  .ad-map-actions{ display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
  .stat-grid{
    display:grid; grid-template-columns: repeat(4, 1fr); gap:12px;
  }
  @media (max-width: 720px){ .stat-grid{ grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 480px){ .stat-grid{ grid-template-columns: repeat(2, 1fr); gap:10px; } }
  .stat{
    background:#F8FAFC; border:1px solid var(--kk-border);
    border-radius:14px; padding:12px 14px;
  }
  .stat .lbl{ font-size:11px; color:var(--kk-mute); text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
  .stat .val{ font-size:20px; font-weight:700; color:var(--kk-text); line-height:1.2; margin-top:4px; }
  .stat .val small{ font-size:12px; color:var(--kk-mute); font-weight:500; }
  /* Highlight utama: Jarak, Durasi Total, Pace Rata-rata */
  .stat:nth-child(1), .stat:nth-child(2), .stat:nth-child(4){
    background: linear-gradient(180deg, #EFF6FF, #FFFFFF);
    border-color:#DBE7FA;
  }
  .stat:nth-child(1) .val, .stat:nth-child(2) .val, .stat:nth-child(4) .val{
    color: var(--kk-blue-3);
  }

  .split-row{
    display:grid; grid-template-columns: 48px 62px 1fr 78px; gap:10px;
    align-items:center; padding:9px 4px; border-bottom:1px solid var(--kk-border);
  }
  .split-row:last-child{ border-bottom:none; }
  .split-km{ font-weight:700; color:var(--kk-text); font-size:13px; }
  .split-pace{ font-variant-numeric: tabular-nums; color:var(--kk-text); font-weight:600; }
  .split-elev{ font-size:12px; color:var(--kk-mute); text-align:right; font-variant-numeric: tabular-nums; }
  .split-bar{ position:relative; height:10px; background:#EEF2F7; border-radius:6px; overflow:hidden; }
  .split-bar > i{
    display:block; height:100%;
    background:linear-gradient(90deg, var(--kk-blue-3), var(--kk-blue-2));
    border-radius:6px;
  }
  .split-row.best .split-bar > i{ background:linear-gradient(90deg,#16a34a,#22c55e); }
  .split-row.slow .split-bar > i{ background:linear-gradient(90deg,#f59e0b,#ef4444); }

  .insight-list{ display:grid; grid-template-columns: repeat(2,1fr); gap:12px; }
  @media (max-width:520px){ .insight-list{ grid-template-columns: 1fr; } }
  .insight{
    background:#F8FAFC; border:1px solid var(--kk-border);
    border-radius:14px; padding:12px 14px; display:flex; gap:12px; align-items:flex-start;
  }
  .insight .ic{
    width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, #DBEAFE, #E0F2FE);
    color:var(--kk-blue-3); flex:0 0 auto; font-size:16px;
  }
  .insight .t{ font-size:12px; color:var(--kk-mute); }
  .insight .v{ font-size:15px; font-weight:700; color:var(--kk-text); margin-top:2px; }
  .chart-wrap{ position:relative; height: 240px; }
  .chart-wrap.tall{ height: 260px; }
  .na{
    display:flex; align-items:center; justify-content:center; padding:24px;
    color:var(--kk-mute); font-size:13px; text-align:center;
  }
  .ad-empty{
    display:flex; align-items:center; justify-content:center;
    height:52vh; color:var(--kk-mute); text-align:center; padding:24px;
  }
  .fs-map{ position:fixed; inset:0; z-index:9999; background:#000; }
  .fs-map .close{
    position:absolute; top:14px; right:14px; z-index:10001;
    background:#FFFFFF; color:var(--kk-text); border:1px solid var(--kk-border);
    border-radius:12px; padding:8px 12px; box-shadow:0 4px 12px rgba(0,0,0,.25);
  }
  @media (max-width:480px){
    .ad-shell{ padding:14px 12px 110px; }
    .glass{ padding:14px; border-radius:16px; }
    .ad-title{ font-size:20px; }
  }
</style>

<div class="ad-shell">
  <div class="ad-wrap">
    <div class="ad-topbar">
      <a href="/riwayat.php" class="ad-btn"><i class="bi bi-arrow-left"></i> Kembali</a>
      <div style="display:flex; gap:8px;">
        <?php if ($act && $sId>0): ?>
          <a class="ad-btn" href="/api_run.php?action=export_gpx&session_id=<?= (int)$sId ?>" title="Download GPX">
            <i class="bi bi-download"></i> GPX
          </a>
        <?php endif; ?>
        <button type="button" class="ad-btn" id="adShareBtn"><i class="bi bi-share"></i> Bagikan</button>
      </div>
    </div>

<?php if (!$act): ?>
  <div class="glass"><div class="ad-empty">
    <div>
      <div class="mb-2"><i class="bi bi-exclamation-circle fs-3" style="color:var(--kk-warn)"></i></div>
      Aktivitas tidak ditemukan.
    </div>
  </div></div>
<?php else: ?>

  <div class="ad-head">
    <?php if (!empty($act['foto_url'])): ?>
      <img src="<?= htmlspecialchars($act['foto_url']) ?>" alt="">
    <?php else: ?>
      <span class="avf"><?= htmlspecialchars(mb_strtoupper(mb_substr($act['nama'] ?? '?',0,1))) ?></span>
    <?php endif; ?>
    <div style="flex:1; min-width:0;">
      <h1 class="ad-title">
        <?= htmlspecialchars(ucfirst((string)($act['jenis'] ?? 'Aktivitas'))) ?>
      </h1>
      <div class="ad-sub">
        <?= htmlspecialchars($act['nama'] ?? '') ?> · <?= __fmt_date_id($act['created_at'] ?? $act['tanggal'] ?? null) ?>
      </div>
    </div>
  </div>

  <!-- MAP -->
  <div class="glass" style="padding:10px;">
    <div id="adMap" class="ad-map" role="img" aria-label="Peta rute aktivitas"></div>
    <div class="ad-map-actions">
      <button type="button" class="ad-btn" id="adFullscreenBtn"><i class="bi bi-arrows-fullscreen"></i> Fullscreen</button>
      <?php if ($sId>0): ?>
        <a class="ad-btn" href="/api_run.php?action=export_gpx&session_id=<?= (int)$sId ?>"><i class="bi bi-filetype-gpx"></i> Download GPX</a>
      <?php endif; ?>
      <button type="button" class="ad-btn primary" id="adShareBtn2"><i class="bi bi-share-fill"></i> Bagikan Aktivitas</button>
    </div>
    <?php if (count($points) < 2): ?>
      <div class="na" style="margin-top:8px;">
        <i class="bi bi-info-circle me-2"></i>
        Aktivitas ini belum memiliki jejak GPS untuk ditampilkan pada peta.
      </div>
    <?php endif; ?>
  </div>

  <!-- STATS -->
  <div class="glass">
    <h3>Statistik Ringkas</h3>
    <div class="stat-grid" id="adStats">
      <div class="stat"><div class="lbl">Jarak</div><div class="val"><?= number_format((float)($act['jarak_km'] ?? 0), 2, ',', '.') ?> <small>km</small></div></div>
      <div class="stat"><div class="lbl">Durasi Total</div><div class="val" id="stDurTotal">-</div></div>
      <div class="stat"><div class="lbl">Durasi Bergerak</div><div class="val" id="stDurMove">-</div></div>
      <div class="stat"><div class="lbl">Pace Rata-rata</div><div class="val" id="stPaceAvg">
        <?php $ps=(int)($act['pace_detik']??0); echo $ps>0 ? __fmt_pace($ps).' <small>/km</small>' : '-'; ?>
      </div></div>
      <div class="stat"><div class="lbl">Pace Terbaik</div><div class="val" id="stPaceBest">-</div></div>
      <div class="stat"><div class="lbl">Kecepatan Rata-rata</div><div class="val" id="stSpdAvg">-</div></div>
      <div class="stat"><div class="lbl">Kecepatan Maks</div><div class="val" id="stSpdMax">-</div></div>
      <div class="stat"><div class="lbl">Kalori</div><div class="val"><?= (int)($act['kalori'] ?? 0) ?> <small>kcal</small></div></div>
      <div class="stat"><div class="lbl">Elevasi Naik</div><div class="val" id="stElUp">-</div></div>
      <div class="stat"><div class="lbl">Elevasi Turun</div><div class="val" id="stElDn">-</div></div>
      <div class="stat"><div class="lbl">Elevasi Maks</div><div class="val" id="stElMax">-</div></div>
      <div class="stat"><div class="lbl">Total Titik GPS</div><div class="val"><?= count($points) ?></div></div>
    </div>
  </div>

  <!-- SPLITS -->
  <div class="glass">
    <h3>Split per Kilometer <span class="sub">— pace tiap km</span></h3>
    <div id="adSplits"><div class="na">Menghitung split…</div></div>
  </div>

  <!-- PACE CHART -->
  <div class="glass">
    <h3>Grafik Pace <span class="sub">— menit per km</span></h3>
    <div class="chart-wrap"><canvas id="chartPace"></canvas></div>
  </div>

  <!-- ELEV CHART -->
  <div class="glass">
    <h3>Grafik Elevasi</h3>
    <div class="chart-wrap" id="elevWrap"><canvas id="chartElev"></canvas></div>
  </div>

  <!-- SPEED CHART -->
  <div class="glass">
    <h3>Grafik Kecepatan <span class="sub">— km/jam</span></h3>
    <div class="chart-wrap"><canvas id="chartSpeed"></canvas></div>
  </div>

  <!-- ACCURACY CHART -->
  <div class="glass">
    <h3>Akurasi GPS <span class="sub">— meter (semakin kecil semakin baik)</span></h3>
    <div class="chart-wrap" id="accWrap"><canvas id="chartAcc"></canvas></div>
  </div>

  <!-- INSIGHT -->
  <div class="glass">
    <h3>Insight Otomatis</h3>
    <div class="insight-list" id="adInsights"><div class="na">Menganalisis…</div></div>
  </div>

  <?php if (!empty($act['deskripsi'])): ?>
    <div class="glass">
      <h3><i class="bi bi-journal-text"></i> Catatan</h3>
      <div style="white-space:pre-wrap; color:#dbe6ff;"><?= htmlspecialchars((string)$act['deskripsi']) ?></div>
    </div>
  <?php endif; ?>

  <script>
  (function(){
    // ==== payload dari server ====
    var RAW = <?= json_encode($points, JSON_UNESCAPED_UNICODE) ?>;
    var HAS_ALT_COL = <?= $hasAlt ? 'true':'false' ?>;
    var ACT = {
      jarak_km:  <?= json_encode((float)($act['jarak_km'] ?? 0)) ?>,
      dur_menit: <?= (int)($act['durasi_menit'] ?? 0) ?>,
      kalori:    <?= (int)($act['kalori'] ?? 0) ?>,
      pace_sec:  <?= (int)($act['pace_detik'] ?? 0) ?>,
      jenis:     <?= json_encode((string)($act['jenis'] ?? 'Aktivitas')) ?>,
      tanggal:   <?= json_encode((string)($act['tanggal'] ?? '')) ?>
    };

    // ==== util ====
    function fmtPace(sec){ sec = Math.round(sec||0); if (!sec || !isFinite(sec)) return '-';
      var m=Math.floor(sec/60), s=sec%60; return m+':'+(s<10?'0':'')+s; }
    function fmtDur(sec){ sec=Math.max(0,Math.round(sec||0));
      var h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60;
      return (h>0? h+':'+(m<10?'0':'')+m : m)+':'+(s<10?'0':'')+s; }
    function haversine(a,b){
      var R=6371000, toRad=Math.PI/180;
      var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
      var la1=a.lat*toRad, la2=b.lat*toRad;
      var x=Math.sin(dLat/2)**2 + Math.cos(la1)*Math.cos(la2)*Math.sin(dLng/2)**2;
      return 2*R*Math.asin(Math.min(1,Math.sqrt(x)));
    }

    // Normalisasi + hitung kumulatif jarak & delta waktu
    var PTS = (RAW||[]).map(function(p){
      return {
        lat:+p.lat, lng:+p.lng,
        ts: p.ts ? new Date((p.ts+'').replace(' ','T')+ (/(Z|[+\-]\d\d:?\d\d)$/.test(p.ts)?'':'')).getTime() : null,
        sp: (p.sp==null?null:+p.sp),
        ac: (p.ac==null?null:+p.ac),
        alt:(p.alt==null?null:+p.alt)
      };
    }).filter(function(p){ return isFinite(p.lat)&&isFinite(p.lng); });

    var cum=0, prev=null, hasTs = PTS.length>0 && PTS[0].ts!=null;
    for (var i=0;i<PTS.length;i++){
      var p=PTS[i];
      if (prev){
        var d = haversine(prev,p);
        p._d = d;
        p._dt= (p.ts!=null && prev.ts!=null) ? Math.max(0,(p.ts-prev.ts)/1000) : 0;
      } else { p._d=0; p._dt=0; }
      cum += p._d; p._cum=cum;
      prev=p;
    }
    var totalDistM = cum;
    var totalTimeS = (PTS.length>1 && hasTs) ? Math.max(0,(PTS[PTS.length-1].ts-PTS[0].ts)/1000) : (ACT.dur_menit*60);
    var movingTimeS = 0;
    for (var j=0;j<PTS.length;j++){
      var q=PTS[j];
      if (q._dt>0 && q._d>0 && (q._d/q._dt) > 0.5) movingTimeS += q._dt; // >0.5 m/s dianggap bergerak
    }
    if (movingTimeS<=0) movingTimeS = totalTimeS;

    // Kecepatan (m/s) fallback dari delta jarak/waktu
    var spdArr = PTS.map(function(p){
      if (p.sp!=null && isFinite(p.sp)) return p.sp;
      return (p._dt>0? p._d/p._dt : 0);
    });
    var maxSpd = spdArr.reduce(function(a,b){ return b>a?b:a; },0);
    var avgSpd = movingTimeS>0 ? totalDistM/movingTimeS : 0;
    var avgPaceSec = avgSpd>0 ? 1000/avgSpd : (ACT.pace_sec||0);

    // ==== isi statistik ====
    var $=function(id){ return document.getElementById(id); };
    $('stDurTotal').innerHTML = fmtDur(totalTimeS);
    $('stDurMove').innerHTML  = fmtDur(movingTimeS);
    if (avgPaceSec>0) $('stPaceAvg').innerHTML = fmtPace(avgPaceSec)+' <small>/km</small>';
    $('stSpdAvg').innerHTML   = (avgSpd*3.6).toFixed(2)+' <small>km/j</small>';
    $('stSpdMax').innerHTML   = (maxSpd*3.6).toFixed(2)+' <small>km/j</small>';

    // ==== SPLIT per km ====
    var splits = [];
    if (totalDistM >= 100 && hasTs){
      var kmIndex=1, kmStartTs=PTS[0].ts, kmStartCum=0;
      var elevUpKm=0, elevDnKm=0, prevAlt=(PTS[0].alt);
      for (var k=1;k<PTS.length;k++){
        var pt=PTS[k];
        if (pt.alt!=null && prevAlt!=null){
          var dA=pt.alt-prevAlt;
          if (dA>0) elevUpKm+=dA; else elevDnKm+=(-dA);
        }
        if (pt.alt!=null) prevAlt=pt.alt;

        while (pt._cum >= kmIndex*1000){
          // interpolasi lurus (perkiraan waktu di titik km)
          var prevP = PTS[k-1];
          var need = kmIndex*1000 - prevP._cum;
          var seg  = pt._cum - prevP._cum;
          var frac = seg>0 ? need/seg : 1;
          var tAtKm = prevP.ts + (pt.ts-prevP.ts)*frac;
          var dur = (tAtKm - kmStartTs)/1000;
          splits.push({
            km: kmIndex,
            dur: dur,
            paceSec: dur, // pace per 1000m = detik yg dipakai
            elevUp: elevUpKm, elevDn: elevDnKm
          });
          kmStartTs = tAtKm; kmStartCum = kmIndex*1000;
          elevUpKm=0; elevDnKm=0;
          kmIndex++;
        }
      }
      // sisa < 1 km
      var lastCum = PTS[PTS.length-1]._cum;
      var lastTs  = PTS[PTS.length-1].ts;
      var restM = lastCum - (kmIndex-1)*1000;
      if (restM > 50){
        var restDur = (lastTs - kmStartTs)/1000;
        var paceSec = restDur * (1000/restM);
        splits.push({
          km: kmIndex, partial: restM/1000,
          dur: restDur, paceSec: paceSec,
          elevUp: elevUpKm, elevDn: elevDnKm
        });
      }
    }
    var splitBox = $('adSplits');
    if (splits.length){
      var minPace=Infinity,maxPace=0;
      splits.forEach(function(s){ if(!s.partial){ if(s.paceSec<minPace)minPace=s.paceSec; if(s.paceSec>maxPace)maxPace=s.paceSec; } });
      var html='';
      splits.forEach(function(s){
        var isBest = !s.partial && s.paceSec===minPace;
        var isSlow = !s.partial && s.paceSec===maxPace && minPace!==maxPace;
        // panjang bar: pace tercepat -> 100%, terlambat -> ~35%
        var range = Math.max(1,maxPace-minPace);
        var w = s.partial ? 40 : Math.round(100 - ((s.paceSec-minPace)/range)*65);
        var elev = (s.elevUp>0||s.elevDn>0) ? ('↑'+Math.round(s.elevUp)+' ↓'+Math.round(s.elevDn)+' m') : '';
        html += '<div class="split-row '+(isBest?'best':'')+' '+(isSlow?'slow':'')+'">'
             + '<div class="split-km">'+(s.partial? ('~'+s.partial.toFixed(2)) : ('KM '+s.km))+'</div>'
             + '<div class="split-pace">'+fmtPace(s.paceSec)+'</div>'
             + '<div class="split-bar"><i style="width:'+w+'%"></i></div>'
             + '<div class="split-elev">'+elev+'</div>'
             + '</div>';
      });
      splitBox.innerHTML = html;
    } else {
      splitBox.innerHTML = '<div class="na">Data belum cukup untuk menghitung split per km.</div>';
    }

    // Pace terbaik dari split
    if (splits.length){
      var best = splits.filter(function(s){return !s.partial;}).reduce(function(a,b){ return (a==null||b.paceSec<a.paceSec)?b:a; },null);
      if (best) $('stPaceBest').innerHTML = fmtPace(best.paceSec)+' <small>/km</small>';
    }

    // ==== Elevasi (jika ada) ====
    var altSeries = [], altLabels=[], elMin=Infinity, elMax=-Infinity, elUp=0, elDn=0;
    var hasAltData = false;
    for (var a=0;a<PTS.length;a++){
      var pa=PTS[a];
      if (pa.alt!=null){ hasAltData=true; altSeries.push(pa.alt); altLabels.push((pa._cum/1000).toFixed(2));
        if (pa.alt<elMin) elMin=pa.alt; if (pa.alt>elMax) elMax=pa.alt;
        if (a>0 && PTS[a-1].alt!=null){ var dd=pa.alt-PTS[a-1].alt; if (dd>0) elUp+=dd; else elDn+=(-dd); }
      }
    }
    if (hasAltData){
      $('stElUp').innerHTML  = Math.round(elUp)+' <small>m</small>';
      $('stElDn').innerHTML  = Math.round(elDn)+' <small>m</small>';
      $('stElMax').innerHTML = Math.round(elMax)+' <small>m</small>';
    } else {
      $('stElUp').innerHTML='<span style="color:var(--kk-mute); font-size:14px;">Tidak tersedia</span>';
      $('stElDn').innerHTML='<span style="color:var(--kk-mute); font-size:14px;">Tidak tersedia</span>';
      $('stElMax').innerHTML='<span style="color:var(--kk-mute); font-size:14px;">Tidak tersedia</span>';
    }

    // ==== Chart.js common opts ====
    var gridC='rgba(15,23,42,.08)', tickC='#64748B';
    function baseOpts(yLabel){
      return {
        responsive:true, maintainAspectRatio:false,
        interaction:{mode:'index', intersect:false},
        plugins:{ legend:{ labels:{ color:tickC } }, tooltip:{ enabled:true } },
        scales:{
          x:{ ticks:{color:tickC, maxRotation:0, autoSkip:true, maxTicksLimit:8}, grid:{color:gridC} },
          y:{ ticks:{color:tickC}, grid:{color:gridC}, title:{display:!!yLabel, text:yLabel||'', color:tickC} }
        }
      };
    }

    // ==== PACE CHART (per titik = 1000/speed) ====
    (function(){
      var labels=[], data=[];
      for (var i=1;i<PTS.length;i++){
        var s = spdArr[i]; if (!(s>0.3)) { continue; }
        var pSec = 1000/s;
        if (pSec>1800) continue; // > 30:00/km → outlier
        labels.push((PTS[i]._cum/1000).toFixed(2));
        data.push(pSec/60); // menit/km
      }
      if (!labels.length){ document.getElementById('chartPace').parentNode.innerHTML='<div class="na">Data pace belum tersedia.</div>'; return; }
      var avgLine = avgPaceSec>0 ? (avgPaceSec/60) : null;
      var ctx=document.getElementById('chartPace').getContext('2d');
      new Chart(ctx,{
        type:'line',
        data:{ labels:labels, datasets:[
          { label:'Pace (menit/km)', data:data, borderColor:'#60a5fa', backgroundColor:'rgba(96,165,250,.2)', borderWidth:2, pointRadius:0, tension:.25, fill:true },
          avgLine? { label:'Rata-rata', data:labels.map(function(){return avgLine;}), borderColor:'#22d3ee', borderDash:[6,4], borderWidth:1.5, pointRadius:0, fill:false } : null
        ].filter(Boolean) },
        options: Object.assign(baseOpts('menit/km'),{
          scales:{
            x:{ ticks:{color:tickC, maxRotation:0, autoSkip:true, maxTicksLimit:8}, grid:{color:gridC}, title:{display:true, text:'Jarak (km)', color:tickC} },
            y:{ reverse:true, ticks:{color:tickC, callback:function(v){var m=Math.floor(v),s=Math.round((v-m)*60); return m+':'+(s<10?'0':'')+s;}}, grid:{color:gridC} }
          }
        })
      });
    })();

    // ==== ELEV CHART ====
    (function(){
      if (!hasAltData){
        document.getElementById('elevWrap').innerHTML =
          '<div class="na">Perangkat tidak menyimpan data altitude untuk aktivitas ini. <br>Elevasi <b>Tidak tersedia</b>.</div>';
        return;
      }
      var ctx=document.getElementById('chartElev').getContext('2d');
      new Chart(ctx,{
        type:'line',
        data:{ labels:altLabels, datasets:[{ label:'Elevasi (m)', data:altSeries, borderColor:'#22d3ee', backgroundColor:'rgba(34,211,238,.25)', borderWidth:2, pointRadius:0, tension:.3, fill:true }] },
        options: Object.assign(baseOpts('meter'),{
          scales:{
            x:{ ticks:{color:tickC, autoSkip:true, maxTicksLimit:8}, grid:{color:gridC}, title:{display:true, text:'Jarak (km)', color:tickC} },
            y:{ ticks:{color:tickC}, grid:{color:gridC} }
          }
        })
      });
    })();

    // ==== SPEED CHART (km/h vs waktu) ====
    (function(){
      var labels=[], data=[]; if (!hasTs){ document.getElementById('chartSpeed').parentNode.innerHTML='<div class="na">Data waktu belum tersedia.</div>'; return; }
      var t0=PTS[0].ts;
      for (var i=1;i<PTS.length;i++){
        var s=spdArr[i]; if (!(s>=0)) continue;
        labels.push(fmtDur((PTS[i].ts-t0)/1000));
        data.push(+(s*3.6).toFixed(2));
      }
      if (!labels.length){ document.getElementById('chartSpeed').parentNode.innerHTML='<div class="na">Data kecepatan belum tersedia.</div>'; return; }
      var ctx=document.getElementById('chartSpeed').getContext('2d');
      new Chart(ctx,{ type:'line',
        data:{ labels:labels, datasets:[{ label:'Kecepatan (km/j)', data:data, borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,.2)', borderWidth:2, pointRadius:0, tension:.25, fill:true }] },
        options: Object.assign(baseOpts('km/j'),{
          scales:{
            x:{ ticks:{color:tickC, autoSkip:true, maxTicksLimit:8}, grid:{color:gridC}, title:{display:true, text:'Waktu', color:tickC} },
            y:{ ticks:{color:tickC}, grid:{color:gridC}, beginAtZero:true }
          }
        })
      });
    })();

    // ==== ACC CHART ====
    (function(){
      var labels=[], data=[], has=false;
      var t0 = hasTs ? PTS[0].ts : null;
      for (var i=0;i<PTS.length;i++){
        if (PTS[i].ac==null) continue; has=true;
        labels.push(hasTs? fmtDur((PTS[i].ts-t0)/1000) : String(i));
        data.push(+PTS[i].ac.toFixed(2));
      }
      if (!has){
        document.getElementById('accWrap').innerHTML = '<div class="na">Data akurasi GPS tidak tersedia.</div>';
        return;
      }
      var ctx=document.getElementById('chartAcc').getContext('2d');
      new Chart(ctx,{ type:'line',
        data:{ labels:labels, datasets:[{ label:'Akurasi (m)', data:data, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,.18)', borderWidth:2, pointRadius:0, tension:.2, fill:true }] },
        options: Object.assign(baseOpts('meter'),{
          scales:{
            x:{ ticks:{color:tickC, autoSkip:true, maxTicksLimit:8}, grid:{color:gridC}, title:{display:true, text:'Waktu', color:tickC} },
            y:{ ticks:{color:tickC}, grid:{color:gridC}, beginAtZero:true }
          }
        })
      });
    })();

    // ==== INSIGHT OTOMATIS ====
    (function(){
      var box = $('adInsights'); var items=[];
      function push(icon, title, val){ items.push('<div class="insight"><div class="ic"><i class="bi '+icon+'"></i></div><div><div class="t">'+title+'</div><div class="v">'+val+'</div></div></div>'); }

      // stop time
      var stopS = Math.max(0, totalTimeS - movingTimeS);
      push('bi-lightning-charge','Kecepatan Maksimum', (maxSpd*3.6).toFixed(2)+' km/j');
      push('bi-speedometer2','Kecepatan Rata-rata', (avgSpd*3.6).toFixed(2)+' km/j');
      push('bi-pause-circle','Total Waktu Berhenti', fmtDur(stopS));

      if (splits.length){
        var normal = splits.filter(function(s){return !s.partial;});
        if (normal.length){
          var fastest = normal.reduce(function(a,b){ return b.paceSec<a.paceSec?b:a; });
          var slowest = normal.reduce(function(a,b){ return b.paceSec>a.paceSec?b:a; });
          push('bi-trophy','Kilometer Tercepat','KM '+fastest.km+' · '+fmtPace(fastest.paceSec)+'/km');
          push('bi-hourglass-split','Kilometer Terlambat','KM '+slowest.km+' · '+fmtPace(slowest.paceSec)+'/km');

          // pace paling stabil = km dengan deviasi terkecil dari rata-rata
          var avgP = normal.reduce(function(a,b){return a+b.paceSec;},0)/normal.length;
          var stable = normal.reduce(function(a,b){ return Math.abs(b.paceSec-avgP)<Math.abs(a.paceSec-avgP)?b:a; });
          push('bi-bullseye','Pace Paling Stabil','KM '+stable.km+' · '+fmtPace(stable.paceSec)+'/km');
        }
      }

      // Estimasi VO2 Max (opsional, hanya jika pace masuk akal)
      if (avgSpd>0 && avgPaceSec>0){
        // Rumus Daniels sederhana (sangat kasar, opsional): VO2 = -4.6 + 0.182258*(m/min) + 0.000104*(m/min)^2
        var mpm = avgSpd*60;
        var vo2 = -4.6 + 0.182258*mpm + 0.000104*mpm*mpm;
        if (vo2>10 && vo2<90){
          push('bi-heart-pulse','Estimasi VO₂ Max', vo2.toFixed(1)+' ml/kg/min <small style="color:var(--kk-mute);font-weight:400">(opsional)</small>');
        }
      }

      // Zona intensitas berdasarkan pace lari sederhana
      if (avgPaceSec>0 && (ACT.jenis||'').toLowerCase().indexOf('jog')>=0 || (ACT.jenis||'').toLowerCase().indexOf('lari')>=0 || (ACT.jenis||'').toLowerCase().indexOf('run')>=0){
        var zona='Recovery';
        if (avgPaceSec<300) zona='Zona 5 · VO₂ Max';
        else if (avgPaceSec<360) zona='Zona 4 · Threshold';
        else if (avgPaceSec<420) zona='Zona 3 · Tempo';
        else if (avgPaceSec<510) zona='Zona 2 · Endurance';
        else zona='Zona 1 · Recovery';
        push('bi-activity','Zona Intensitas', zona);
      }

      box.innerHTML = items.length? items.join('') : '<div class="na">Belum ada insight.</div>';
    })();

    // ==== MAP ====
    var mapEl=document.getElementById('adMap'), map=null, mapLine=null;
    function initMap(container){
      var m = L.map(container, {
        zoomControl:true, dragging:true, touchZoom:true, doubleClickZoom:true,
        scrollWheelZoom:true, boxZoom:true, keyboard:true, tap:true
      });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'&copy; OpenStreetMap' }).addTo(m);
      if (PTS.length>=2){
        var latlngs = PTS.map(function(p){ return [p.lat,p.lng]; });
        var line = L.polyline(latlngs, { color:'#22d3ee', weight:5, opacity:.95 }).addTo(m);
        L.circleMarker(latlngs[0],{ radius:8, color:'#fff', weight:2, fillColor:'#22c55e', fillOpacity:1 }).bindTooltip('Start').addTo(m);
        L.circleMarker(latlngs[latlngs.length-1],{ radius:8, color:'#fff', weight:2, fillColor:'#ef4444', fillOpacity:1 }).bindTooltip('Finish').addTo(m);
        try { m.fitBounds(line.getBounds(), { padding:[24,24] }); } catch(_){ m.setView(latlngs[0],15); }
      } else {
        m.setView([-2.5,118], 4);
      }
      setTimeout(function(){ try{ m.invalidateSize(); }catch(_){ } }, 120);
      return m;
    }
    function boot(){
      if (!window.L){ setTimeout(boot,120); return; }
      map = initMap(mapEl);
    }
    if (document.readyState!=='loading') boot(); else document.addEventListener('DOMContentLoaded', boot);

    // Fullscreen map
    var fsBtn=document.getElementById('adFullscreenBtn');
    if (fsBtn){
      fsBtn.addEventListener('click', function(){
        var overlay=document.createElement('div'); overlay.className='fs-map';
        var host=document.createElement('div'); host.style.cssText='position:absolute;inset:0;';
        var close=document.createElement('button'); close.className='close'; close.innerHTML='<i class="bi bi-x-lg"></i> Tutup';
        overlay.appendChild(host); overlay.appendChild(close); document.body.appendChild(overlay);
        var fm = initMap(host);
        close.addEventListener('click', function(){ try{ fm.remove(); }catch(_){ } overlay.remove(); });
      });
    }

    // Share
    function shareAct(){
      var url = location.href;
      var text = 'Aktivitas '+ACT.jenis+' — '+ (ACT.jarak_km||0)+' km · '+ (ACT.dur_menit||0)+' menit';
      if (navigator.share){ navigator.share({ title:'KawanKeringat', text:text, url:url }).catch(function(){}); }
      else if (navigator.clipboard){ navigator.clipboard.writeText(url).then(function(){ alert('Link disalin: '+url); }); }
      else { prompt('Salin link:', url); }
    }
    var s1=document.getElementById('adShareBtn'), s2=document.getElementById('adShareBtn2');
    if (s1) s1.addEventListener('click', shareAct);
    if (s2) s2.addEventListener('click', shareAct);

  })();
  </script>

<?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>

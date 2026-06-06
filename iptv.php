<?php
// iptv.php — Revisi 6 Juni 2026 (revisi-2)
// Menampilkan daftar channel IPTV Indonesia dari repo iptv-org
// Sumber: https://raw.githubusercontent.com/iptv-org/iptv/master/streams/id.m3u
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'IPTV Indonesia';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }

/* ---------- Ambil & parse playlist M3U dengan cache 6 jam ---------- */
$cacheDir = sys_get_temp_dir().'/sportapp_iptv';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
$cacheFile = $cacheDir.'/id.m3u';
$cacheTtl  = 6 * 3600;
$src = 'https://raw.githubusercontent.com/iptv-org/iptv/master/streams/id.m3u';
$raw = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $raw = @file_get_contents($cacheFile);
}
if (!$raw) {
    $ctx = stream_context_create(['http'=>['timeout'=>10,'user_agent'=>'HapFamSportApp/1.0']]);
    $raw = @file_get_contents($src, false, $ctx);
    if ($raw) @file_put_contents($cacheFile, $raw);
}
$channels = [];
if ($raw) {
    $lines = preg_split('/\r?\n/', $raw);
    $cur = null;
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln === '#EXTM3U') continue;
        if (strpos($ln, '#EXTINF') === 0) {
            $name = '';
            if (preg_match('/,(.+)$/', $ln, $m)) $name = trim($m[1]);
            $logo = '';
            if (preg_match('/tvg-logo="([^"]*)"/', $ln, $m)) $logo = $m[1];
            $group = '';
            if (preg_match('/group-title="([^"]*)"/', $ln, $m)) $group = $m[1];
            $cur = ['name'=>$name, 'logo'=>$logo, 'group'=>$group, 'url'=>''];
        } elseif (strpos($ln, '#') === 0) {
            // skip other directives
        } else {
            if ($cur !== null) { $cur['url'] = $ln; $channels[] = $cur; $cur = null; }
        }
    }
}
$total = count($channels);
include __DIR__.'/includes/header.php';
?>
<style>
.iptv-card{transition:transform .15s ease, box-shadow .15s ease;cursor:pointer;}
.iptv-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.08)!important;}
.iptv-logo{width:56px;height:56px;object-fit:contain;background:#f1f5f9;border-radius:10px;padding:4px;}
.iptv-logo-fallback{width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,#10b981,#0ea5e9);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;}
#iptvPlayerWrap{position:relative;background:#000;border-radius:12px;overflow:hidden;}
#iptvPlayer{width:100%;aspect-ratio:16/9;background:#000;}
</style>

<div class="container-fluid py-2">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
      <li class="breadcrumb-item active">IPTV Indonesia</li>
    </ol>
  </nav>

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-tv-fill text-success"></i> IPTV Indonesia</h1>
      <div class="small text-muted">Sumber: <a href="https://github.com/iptv-org/iptv" target="_blank" rel="noopener">iptv-org/iptv</a> · <?= (int)$total ?> channel</div>
    </div>
    <div class="d-flex gap-2">
      <input id="iptvSearch" type="search" class="form-control form-control-sm" placeholder="Cari channel…" style="min-width:200px">
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle"></i>
      Gagal memuat daftar channel dari iptv-org. Pastikan server bisa mengakses
      <code>raw.githubusercontent.com</code>, lalu refresh halaman ini.
    </div>
  <?php else: ?>

  <!-- Player -->
  <div class="card border-0 shadow-sm mb-3" id="iptvPlayerCard" style="display:none">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" id="iptvNowPlaying">—</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="iptvClose()"><i class="bi bi-x-lg"></i> Tutup</button>
      </div>
      <div id="iptvPlayerWrap">
        <video id="iptvPlayer" controls autoplay playsinline></video>
      </div>
      <div class="small text-muted mt-2">
        <i class="bi bi-info-circle"></i> Beberapa channel mungkin tidak bisa diputar di browser jika geo-blocked atau
        membutuhkan DRM. Coba channel lain bila gagal.
      </div>
    </div>
  </div>

  <!-- Grid channels -->
  <div class="row g-2" id="iptvGrid">
    <?php foreach($channels as $i=>$c): ?>
      <div class="col-6 col-md-4 col-lg-3 iptv-item"
           data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>"
           data-group="<?= htmlspecialchars(strtolower($c['group'])) ?>">
        <div class="card iptv-card h-100 border-0 shadow-sm"
             onclick="iptvPlay(<?= $i ?>)">
          <div class="card-body d-flex align-items-center gap-2">
            <?php if (!empty($c['logo'])): ?>
              <img class="iptv-logo" src="<?= htmlspecialchars($c['logo']) ?>" alt="" loading="lazy"
                   onerror="this.outerHTML='<div class=\'iptv-logo-fallback\'><?= htmlspecialchars(mb_substr($c['name'],0,2)) ?></div>'">
            <?php else: ?>
              <div class="iptv-logo-fallback"><?= htmlspecialchars(mb_substr($c['name'],0,2)) ?></div>
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?= htmlspecialchars($c['name'] ?: 'Channel') ?></div>
              <?php if (!empty($c['group'])): ?>
                <div class="small text-muted text-truncate"><?= htmlspecialchars($c['group']) ?></div>
              <?php endif; ?>
            </div>
            <i class="bi bi-play-circle-fill text-success fs-4"></i>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
<script>
const IPTV_CHANNELS = <?= json_encode(array_map(fn($c)=>['name'=>$c['name'],'url'=>$c['url']], $channels), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
let _hls = null;

function iptvPlay(idx){
  const c = IPTV_CHANNELS[idx]; if(!c) return;
  const card = document.getElementById('iptvPlayerCard');
  const v = document.getElementById('iptvPlayer');
  document.getElementById('iptvNowPlaying').textContent = c.name;
  card.style.display = 'block';
  card.scrollIntoView({behavior:'smooth', block:'start'});
  if (_hls) { try{_hls.destroy();}catch(e){} _hls=null; }
  v.removeAttribute('src'); v.load();
  const url = c.url;
  if (window.Hls && Hls.isSupported() && /\.m3u8(\?|$)/i.test(url)) {
    _hls = new Hls({ maxBufferLength: 20 });
    _hls.loadSource(url);
    _hls.attachMedia(v);
    _hls.on(Hls.Events.ERROR, (e,data)=>{
      if (data && data.fatal) console.warn('HLS fatal', data);
    });
  } else {
    v.src = url;
  }
  v.play().catch(()=>{});
}
function iptvClose(){
  const v = document.getElementById('iptvPlayer');
  if (_hls) { try{_hls.destroy();}catch(e){} _hls=null; }
  v.pause(); v.removeAttribute('src'); v.load();
  document.getElementById('iptvPlayerCard').style.display = 'none';
}
document.getElementById('iptvSearch')?.addEventListener('input', (e)=>{
  const q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('.iptv-item').forEach(el=>{
    const hay = (el.dataset.name||'') + ' ' + (el.dataset.group||'');
    el.style.display = (!q || hay.includes(q)) ? '' : 'none';
  });
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

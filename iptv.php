<?php
// iptv.php — Revisi 7 Juni 2026 (revisi-3): tampilan tabel list, responsive mobile, klik untuk putar
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
            // skip
        } else {
            if ($cur !== null) { $cur['url'] = $ln; $channels[] = $cur; $cur = null; }
        }
    }
}
// Urutkan alfabet berdasarkan nama channel agar list rapi
usort($channels, fn($a,$b)=>strcasecmp($a['name'] ?: '', $b['name'] ?: ''));
$total = count($channels);

// Kumpulkan daftar grup untuk filter
$groups = [];
foreach ($channels as $c) {
    $g = trim((string)$c['group']);
    if ($g !== '' && !in_array($g, $groups, true)) $groups[] = $g;
}
sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

include __DIR__.'/includes/header.php';
?>
<style>
/* ====== IPTV — tampilan tabel list, responsive ====== */
.iptv-toolbar{position:sticky;top:0;z-index:5;background:#fff;padding:.5rem 0;border-bottom:1px solid #eef2f7;}
.iptv-logo{width:40px;height:40px;object-fit:contain;background:#f1f5f9;border-radius:8px;padding:3px;}
.iptv-logo-fallback{width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,#10b981,#0ea5e9);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;}
.iptv-table{margin-bottom:0;}
.iptv-table tbody tr{cursor:pointer;transition:background .12s ease;}
.iptv-table tbody tr:hover{background:#f8fafc;}
.iptv-table tbody tr.active{background:#ecfdf5;}
.iptv-table .col-no{width:54px;color:#94a3b8;font-variant-numeric:tabular-nums;}
.iptv-table .col-logo{width:56px;}
.iptv-table .col-group{white-space:nowrap;}
.iptv-table .col-action{width:90px;text-align:right;}
.iptv-name{font-weight:600;}
.iptv-sub{font-size:.78rem;color:#64748b;}
#iptvPlayerWrap{position:relative;background:#000;border-radius:12px;overflow:hidden;}
#iptvPlayer{width:100%;aspect-ratio:16/9;background:#000;}

/* Mobile: sembunyikan kolom sekunder, padatkan tabel jadi list */
@media (max-width: 575.98px){
  .iptv-table .col-no,
  .iptv-table .col-group,
  .iptv-table .col-action{display:none;}
  .iptv-table td{padding:.55rem .5rem;}
  .iptv-logo, .iptv-logo-fallback{width:36px;height:36px;}
  .iptv-name{font-size:.95rem;}
}
</style>

<div class="container-fluid py-2">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
      <li class="breadcrumb-item active">IPTV Indonesia</li>
    </ol>
  </nav>

  <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-tv-fill text-success"></i> IPTV Indonesia</h1>
      <div class="small text-muted">
        Sumber: <a href="https://github.com/iptv-org/iptv" target="_blank" rel="noopener">iptv-org/iptv</a>
        · <span id="iptvCount"><?= (int)$total ?></span> dari <?= (int)$total ?> channel
      </div>
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
        <i class="bi bi-info-circle"></i> Beberapa channel mungkin tidak bisa diputar di browser jika geo-blocked atau membutuhkan DRM. Coba channel lain bila gagal.
      </div>
    </div>
  </div>

  <!-- Toolbar: search + filter grup -->
  <div class="iptv-toolbar">
    <div class="row g-2 align-items-center">
      <div class="col-12 col-sm">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input id="iptvSearch" type="search" class="form-control" placeholder="Cari channel atau grup…" autocomplete="off">
        </div>
      </div>
      <?php if (!empty($groups)): ?>
      <div class="col-12 col-sm-auto">
        <select id="iptvGroup" class="form-select form-select-sm">
          <option value="">Semua grup</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= htmlspecialchars(strtolower($g)) ?>"><?= htmlspecialchars($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabel list channel -->
  <div class="table-responsive">
    <table class="table table-hover align-middle iptv-table" id="iptvTable">
      <thead class="table-light d-none d-sm-table-header-group">
        <tr>
          <th class="col-no text-center">#</th>
          <th class="col-logo"></th>
          <th>Nama Channel</th>
          <th class="col-group d-none d-md-table-cell">Grup</th>
          <th class="col-action">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($channels as $i=>$c): ?>
          <tr class="iptv-item"
              data-idx="<?= $i ?>"
              data-name="<?= htmlspecialchars(strtolower($c['name'])) ?>"
              data-group="<?= htmlspecialchars(strtolower($c['group'])) ?>"
              onclick="iptvPlay(<?= $i ?>, this)">
            <td class="col-no text-center"><?= $i+1 ?></td>
            <td class="col-logo">
              <?php if (!empty($c['logo'])): ?>
                <img class="iptv-logo" src="<?= htmlspecialchars($c['logo']) ?>" alt="" loading="lazy"
                     onerror="this.outerHTML='<div class=\'iptv-logo-fallback\'><?= htmlspecialchars(mb_substr($c['name'],0,2)) ?></div>'">
              <?php else: ?>
                <div class="iptv-logo-fallback"><?= htmlspecialchars(mb_substr($c['name'] ?: '?',0,2)) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="iptv-name text-truncate"><?= htmlspecialchars($c['name'] ?: 'Channel') ?></div>
              <?php if (!empty($c['group'])): ?>
                <div class="iptv-sub d-md-none text-truncate"><?= htmlspecialchars($c['group']) ?></div>
              <?php endif; ?>
            </td>
            <td class="col-group d-none d-md-table-cell">
              <?php if (!empty($c['group'])): ?>
                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($c['group']) ?></span>
              <?php endif; ?>
            </td>
            <td class="col-action">
              <button type="button" class="btn btn-sm btn-success" onclick="event.stopPropagation(); iptvPlay(<?= $i ?>, this.closest('tr'))">
                <i class="bi bi-play-fill"></i> Putar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div id="iptvEmpty" class="text-center text-muted small py-4" style="display:none">
      <i class="bi bi-search"></i> Tidak ada channel yang cocok.
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
<script>
const IPTV_CHANNELS = <?= json_encode(array_map(fn($c)=>['name'=>$c['name'],'url'=>$c['url']], $channels), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
let _hls = null;

function iptvPlay(idx, rowEl){
  const c = IPTV_CHANNELS[idx]; if(!c) return;
  document.querySelectorAll('.iptv-item.active').forEach(el=>el.classList.remove('active'));
  if (rowEl) rowEl.classList.add('active');

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
    _hls.on(Hls.Events.ERROR, (e,data)=>{ if (data && data.fatal) console.warn('HLS fatal', data); });
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
  document.querySelectorAll('.iptv-item.active').forEach(el=>el.classList.remove('active'));
}

function iptvFilter(){
  const q = (document.getElementById('iptvSearch')?.value || '').trim().toLowerCase();
  const g = (document.getElementById('iptvGroup')?.value || '').trim().toLowerCase();
  let shown = 0;
  document.querySelectorAll('.iptv-item').forEach(el=>{
    const name = el.dataset.name || '';
    const grp  = el.dataset.group || '';
    const hay  = name + ' ' + grp;
    const okQ  = !q || hay.includes(q);
    const okG  = !g || grp === g;
    const ok   = okQ && okG;
    el.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });
  const cnt = document.getElementById('iptvCount'); if (cnt) cnt.textContent = shown;
  const empty = document.getElementById('iptvEmpty'); if (empty) empty.style.display = shown ? 'none' : '';
}
document.getElementById('iptvSearch')?.addEventListener('input', iptvFilter);
document.getElementById('iptvGroup')?.addEventListener('change', iptvFilter);
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
// iptv.php — Revisi: sumber playlist baru + CRUD aktif/nonaktif via tabel iptv_channels
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'IPTV Indonesia';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }

// Ambil channel dari DB (hanya yang aktif), urut sort_order lalu nama
$channels = db_all("SELECT id, nama, logo_url, group_name, url
                    FROM iptv_channels
                    WHERE aktif = TRUE
                    ORDER BY COALESCE(sort_order,9999), LOWER(nama)");
$total = count($channels);

$groups = [];
foreach ($channels as $c) {
    $g = trim((string)($c['group_name'] ?? ''));
    if ($g !== '' && !in_array($g, $groups, true)) $groups[] = $g;
}
sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$isAdmin = (($u['role'] ?? '') === 'admin');
include __DIR__.'/includes/header.php';
?>
<style>
.iptv-toolbar{position:sticky;top:0;z-index:5;background:#fff;padding:.5rem 0;border-bottom:1px solid #eef2f7;}
.iptv-logo{width:40px;height:40px;object-fit:contain;background:#f1f5f9;border-radius:8px;padding:3px;}
.iptv-logo-fallback{width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,#10b981,#0ea5e9);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;}
.iptv-table tbody tr{cursor:pointer;}
.iptv-table tbody tr.active{background:#ecfdf5;}
#iptvPlayer{width:100%;aspect-ratio:16/9;background:#000;}
@media (max-width:575.98px){.iptv-table .col-no,.iptv-table .col-group,.iptv-table .col-action{display:none;}}
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
        Sumber: <a href="https://github.com/riotryulianto/iptv-playlists" target="_blank" rel="noopener">riotryulianto/iptv-playlists</a>
        · <span id="iptvCount"><?= (int)$total ?></span> dari <?= (int)$total ?> channel aktif
      </div>
    </div>
    <?php if ($isAdmin): ?>
      <a href="/admin/iptv.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-gear"></i> Kelola Channel</a>
    <?php endif; ?>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle"></i>
      Belum ada channel aktif. <?php if($isAdmin): ?>Buka <a href="/admin/iptv.php">Kelola Channel</a> dan klik <em>Import dari Playlist</em>.<?php endif; ?>
    </div>
  <?php else: ?>

  <div class="card border-0 shadow-sm mb-3" id="iptvPlayerCard" style="display:none">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" id="iptvNowPlaying">—</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="iptvClose()"><i class="bi bi-x-lg"></i> Tutup</button>
      </div>
      <div style="position:relative;background:#000;border-radius:12px;overflow:hidden;">
        <video id="iptvPlayer" controls autoplay playsinline></video>
      </div>
      <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Beberapa channel mungkin tidak bisa diputar di browser jika geo-blocked / DRM.</div>
    </div>
  </div>

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

  <div class="table-responsive">
    <table class="table table-hover align-middle iptv-table" id="iptvTable">
      <thead class="table-light d-none d-sm-table-header-group">
        <tr><th class="col-no text-center" style="width:54px">#</th><th style="width:56px"></th><th>Nama Channel</th>
        <th class="col-group d-none d-md-table-cell">Grup</th><th class="col-action text-end" style="width:100px">Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach($channels as $i=>$c): ?>
          <tr class="iptv-item" data-idx="<?= $i ?>"
              data-name="<?= htmlspecialchars(strtolower($c['nama'])) ?>"
              data-group="<?= htmlspecialchars(strtolower($c['group_name'] ?? '')) ?>"
              onclick="iptvPlay(<?= $i ?>, this)">
            <td class="col-no text-center text-muted"><?= $i+1 ?></td>
            <td>
              <?php if (!empty($c['logo_url'])): ?>
                <img class="iptv-logo" src="<?= htmlspecialchars($c['logo_url']) ?>" alt="" loading="lazy"
                  onerror="this.outerHTML='<div class=\'iptv-logo-fallback\'><?= htmlspecialchars(mb_substr($c['nama'],0,2)) ?></div>'">
              <?php else: ?>
                <div class="iptv-logo-fallback"><?= htmlspecialchars(mb_substr($c['nama'] ?: '?',0,2)) ?></div>
              <?php endif; ?>
            </td>
            <td><div class="fw-semibold text-truncate"><?= htmlspecialchars($c['nama']) ?></div>
              <?php if(!empty($c['group_name'])): ?><div class="small text-muted d-md-none"><?= htmlspecialchars($c['group_name']) ?></div><?php endif; ?>
            </td>
            <td class="col-group d-none d-md-table-cell">
              <?php if(!empty($c['group_name'])): ?><span class="badge bg-light text-secondary border"><?= htmlspecialchars($c['group_name']) ?></span><?php endif; ?>
            </td>
            <td class="col-action text-end">
              <button type="button" class="btn btn-sm btn-success" onclick="event.stopPropagation(); iptvPlay(<?= $i ?>, this.closest('tr'))">
                <i class="bi bi-play-fill"></i> Putar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div id="iptvEmpty" class="text-center text-muted small py-4" style="display:none"><i class="bi bi-search"></i> Tidak ada channel yang cocok.</div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
<script>
const IPTV_CHANNELS = <?= json_encode(array_map(fn($c)=>['name'=>$c['nama'],'url'=>$c['url']], $channels), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
let _hls = null;
function iptvPlay(idx, rowEl){
  const c = IPTV_CHANNELS[idx]; if(!c) return;
  document.querySelectorAll('.iptv-item.active').forEach(el=>el.classList.remove('active'));
  if (rowEl) rowEl.classList.add('active');
  const card = document.getElementById('iptvPlayerCard');
  const v = document.getElementById('iptvPlayer');
  document.getElementById('iptvNowPlaying').textContent = c.name;
  card.style.display='block'; card.scrollIntoView({behavior:'smooth',block:'start'});
  if(_hls){try{_hls.destroy();}catch(e){} _hls=null;}
  v.removeAttribute('src'); v.load();
  if (window.Hls && Hls.isSupported() && /\.m3u8(\?|$)/i.test(c.url)){
    _hls=new Hls({maxBufferLength:20}); _hls.loadSource(c.url); _hls.attachMedia(v);
  } else { v.src = c.url; }
  v.play().catch(()=>{});
}
function iptvClose(){
  const v=document.getElementById('iptvPlayer');
  if(_hls){try{_hls.destroy();}catch(e){} _hls=null;}
  v.pause(); v.removeAttribute('src'); v.load();
  document.getElementById('iptvPlayerCard').style.display='none';
  document.querySelectorAll('.iptv-item.active').forEach(el=>el.classList.remove('active'));
}
function iptvFilter(){
  const q=(document.getElementById('iptvSearch')?.value||'').trim().toLowerCase();
  const g=(document.getElementById('iptvGroup')?.value||'').trim().toLowerCase();
  let shown=0;
  document.querySelectorAll('.iptv-item').forEach(el=>{
    const hay=(el.dataset.name||'')+' '+(el.dataset.group||'');
    const ok=(!q||hay.includes(q))&&(!g||el.dataset.group===g);
    el.style.display=ok?'':'none'; if(ok)shown++;
  });
  const cnt=document.getElementById('iptvCount'); if(cnt)cnt.textContent=shown;
  const empty=document.getElementById('iptvEmpty'); if(empty)empty.style.display=shown?'none':'';
}
document.getElementById('iptvSearch')?.addEventListener('input',iptvFilter);
document.getElementById('iptvGroup')?.addEventListener('change',iptvFilter);
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
// admin/iptv.php — CRUD channel IPTV + import dari playlist M3U
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require_role('superadmin');
$pageTitle = 'Kelola Channel IPTV';

$DEFAULT_PLAYLIST = 'https://raw.githubusercontent.com/riotryulianto/iptv-playlists/main/playlist.m3u';

function parse_m3u($raw){
    $out=[]; if(!$raw) return $out;
    $lines=preg_split('/\r?\n/',$raw); $cur=null;
    foreach($lines as $ln){
        $ln=trim($ln);
        if($ln===''||$ln==='#EXTM3U') continue;
        if(strpos($ln,'#EXTINF')===0){
            $name=''; if(preg_match('/,(.+)$/',$ln,$m)) $name=trim($m[1]);
            $logo=''; if(preg_match('/tvg-logo="([^"]*)"/',$ln,$m)) $logo=$m[1];
            $group=''; if(preg_match('/group-title="([^"]*)"/',$ln,$m)) $group=$m[1];
            $cur=['nama'=>$name,'logo'=>$logo,'group'=>$group,'url'=>''];
        } elseif(strpos($ln,'#')===0){
            // skip metadata
        } else {
            if($cur!==null){$cur['url']=$ln; $out[]=$cur; $cur=null;}
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='create') {
        $nama  = trim($_POST['nama'] ?? '');
        $url   = trim($_POST['url'] ?? '');
        $logo  = trim($_POST['logo_url'] ?? '');
        $grp   = trim($_POST['group_name'] ?? '');
        $aktif = !empty($_POST['aktif']) ? 't' : 'f';
        $sort  = (int)($_POST['sort_order'] ?? 0);
        if ($nama!=='' && $url!=='') {
            db_exec("INSERT INTO iptv_channels(nama,url,logo_url,group_name,aktif,sort_order) VALUES($1,$2,$3,$4,$5,$6)",
                [$nama,$url,$logo,$grp,$aktif,$sort]);
            $_SESSION['flash_ok']='Channel ditambahkan.';
        }
    } elseif ($a==='edit') {
        $id=(int)$_POST['id'];
        db_exec("UPDATE iptv_channels SET nama=$1,url=$2,logo_url=$3,group_name=$4,aktif=$5,sort_order=$6 WHERE id=$7",
            [trim($_POST['nama']),trim($_POST['url']),trim($_POST['logo_url']??''),trim($_POST['group_name']??''),
             !empty($_POST['aktif'])?'t':'f',(int)($_POST['sort_order']??0),$id]);
        $_SESSION['flash_ok']='Channel diperbarui.';
    } elseif ($a==='toggle') {
        db_exec("UPDATE iptv_channels SET aktif = NOT aktif WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM iptv_channels WHERE id=$1", [(int)$_POST['id']]);
        $_SESSION['flash_ok']='Channel dihapus.';
    } elseif ($a==='import') {
        $src = trim($_POST['playlist_url'] ?? $DEFAULT_PLAYLIST);
        $replace = !empty($_POST['replace_all']);
        $ctx = stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'KawanKeringat/1.0']]);
        $raw = @file_get_contents($src,false,$ctx);
        if (!$raw) {
            $_SESSION['flash_err']='Gagal mengunduh playlist: '.htmlspecialchars($src);
        } else {
            $items = parse_m3u($raw);
            if ($replace) db_exec("DELETE FROM iptv_channels");
            $n=0;
            foreach ($items as $it) {
                if ($it['url']==='' || $it['nama']==='') continue;
                // upsert by url
                db_exec("INSERT INTO iptv_channels(nama,url,logo_url,group_name,aktif,sort_order)
                         VALUES($1,$2,$3,$4,TRUE,0)
                         ON CONFLICT (url) DO UPDATE
                            SET nama=EXCLUDED.nama, logo_url=EXCLUDED.logo_url, group_name=EXCLUDED.group_name",
                    [$it['nama'],$it['url'],$it['logo'],$it['group']]);
                $n++;
            }
            $_SESSION['flash_ok']="Berhasil import $n channel dari playlist.";
        }
    }
    header('Location: iptv.php'); exit;
}

$rows = db_all("SELECT * FROM iptv_channels ORDER BY aktif DESC, COALESCE(sort_order,9999), LOWER(nama)");
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$err= $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-tv text-success"></i> Kelola Channel IPTV</h2>
<p class="text-muted">CRUD channel IPTV, toggle aktif/nonaktif, atau import langsung dari URL playlist M3U.</p>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-cloud-download text-primary"></i> Import dari Playlist M3U</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="import">
          <div class="col-12"><label class="form-label small fw-semibold">URL Playlist (.m3u)</label>
            <input class="form-control" name="playlist_url" value="<?= htmlspecialchars($DEFAULT_PLAYLIST) ?>" required></div>
          <div class="col-12 form-check ms-2 mt-2">
            <input class="form-check-input" type="checkbox" name="replace_all" id="rall" value="1">
            <label class="form-check-label small" for="rall">Hapus semua channel sebelum import (replace)</label>
          </div>
          <div class="col-12 mt-2"><button class="btn btn-primary"><i class="bi bi-download"></i> Import Playlist</button></div>
        </form>
        <div class="small text-muted mt-2">Default: <code>riotryulianto/iptv-playlists/playlist.m3u</code>. URL yang sama akan di-<em>upsert</em> (tidak duplikat).</div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-plus-circle text-primary"></i> Tambah Channel Manual</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="create">
          <div class="col-md-6"><input class="form-control" name="nama" placeholder="Nama channel" required></div>
          <div class="col-md-6"><input class="form-control" name="group_name" placeholder="Grup (cth: Indonesia)"></div>
          <div class="col-12"><input class="form-control" name="url" placeholder="URL stream (.m3u8 / .ts / http)" required></div>
          <div class="col-md-8"><input class="form-control" name="logo_url" placeholder="URL logo (opsional)"></div>
          <div class="col-md-2"><input class="form-control" type="number" name="sort_order" value="0" title="Urutan"></div>
          <div class="col-md-2 form-check ms-2 mt-2"><input class="form-check-input" type="checkbox" name="aktif" id="ac" value="1" checked><label class="form-check-label small" for="ac">Aktif</label></div>
          <div class="col-12"><button class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between"><span><i class="bi bi-list-ul"></i> Daftar Channel (<?= count($rows) ?>)</span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>#</th><th>Logo</th><th>Nama</th><th>Grup</th><th>URL</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php foreach($rows as $i=>$r): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td><?php if(!empty($r['logo_url'])): ?><img src="<?= htmlspecialchars($r['logo_url']) ?>" style="width:32px;height:32px;object-fit:contain;background:#f1f5f9;border-radius:6px;padding:2px" onerror="this.style.display='none'"><?php endif; ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></td>
          <td><?php if(!empty($r['group_name'])): ?><span class="badge bg-light text-secondary border"><?= htmlspecialchars($r['group_name']) ?></span><?php endif; ?></td>
          <td class="small text-muted text-truncate" style="max-width:300px"><?= htmlspecialchars($r['url']) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="toggle">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm <?= $r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1'?'btn-success':'btn-outline-secondary' ?>" title="Klik untuk toggle">
                <?= ($r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1')?'Aktif':'Nonaktif' ?>
              </button>
            </form>
          </td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus channel ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada channel. Klik <em>Import Playlist</em> di atas.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach($rows as $r): $isAct = ($r['aktif']==='t'||$r['aktif']===true||$r['aktif']==='1'); ?>
<div class="modal fade" id="edit<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Edit Channel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-8"><label class="form-label small">Nama</label><input class="form-control" name="nama" value="<?= htmlspecialchars($r['nama']) ?>" required></div>
          <div class="col-md-4"><label class="form-label small">Grup</label><input class="form-control" name="group_name" value="<?= htmlspecialchars($r['group_name']??'') ?>"></div>
          <div class="col-12"><label class="form-label small">URL Stream</label><input class="form-control" name="url" value="<?= htmlspecialchars($r['url']) ?>" required></div>
          <div class="col-12"><label class="form-label small">URL Logo</label><input class="form-control" name="logo_url" value="<?= htmlspecialchars($r['logo_url']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label small">Sort Order</label><input class="form-control" type="number" name="sort_order" value="<?= (int)($r['sort_order']??0) ?>"></div>
          <div class="col-md-6 form-check align-self-end ms-2"><input class="form-check-input" type="checkbox" name="aktif" id="ae<?= $r['id'] ?>" value="1" <?= $isAct?'checked':'' ?>><label class="form-check-label small" for="ae<?= $r['id'] ?>">Aktif</label></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>

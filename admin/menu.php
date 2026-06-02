<?php
/**
 * admin/menu.php
 * CRUD Navigasi Menu gaya CMS WordPress (parent/child 1 level).
 * Posisi: drawer (default), top, bottom.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Navigasi Menu (CMS)';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'add' || $a === 'edit') {
            $label  = substr(trim($_POST['label'] ?? ''),0,80);
            $url    = substr(trim($_POST['url'] ?? '#'),0,255);
            $icon   = substr(trim($_POST['icon'] ?? ''),0,60);
            $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
            $urut   = (int)($_POST['urutan'] ?? 0);
            $aktif  = !empty($_POST['aktif']);
            $target = in_array($_POST['target'] ?? '_self', ['_self','_blank'], true) ? $_POST['target'] : '_self';
            $posisi = in_array($_POST['posisi'] ?? 'drawer', ['drawer','top','bottom'], true) ? $_POST['posisi'] : 'drawer';
            if ($label === '') throw new RuntimeException('Label wajib diisi.');
            if ($a === 'add') {
                db_exec("INSERT INTO nav_menu(label,url,icon,parent_id,urutan,aktif,target,posisi)
                         VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
                    [$label,$url,$icon?:null,$parent,$urut,$aktif?'t':'f',$target,$posisi]);
            } else {
                $id = (int)$_POST['id'];
                db_exec("UPDATE nav_menu SET label=$1,url=$2,icon=$3,parent_id=$4,urutan=$5,aktif=$6,target=$7,posisi=$8 WHERE id=$9",
                    [$label,$url,$icon?:null,$parent,$urut,$aktif?'t':'f',$target,$posisi,$id]);
            }
            $_SESSION['flash'] = 'Menu disimpan.';
        } elseif ($a === 'delete') {
            db_exec("DELETE FROM nav_menu WHERE id=$1",[(int)$_POST['id']]);
            $_SESSION['flash'] = 'Menu dihapus.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: menu.php'); exit;
}

$rows = db_all("SELECT m.*, p.label AS parent_label
                FROM nav_menu m LEFT JOIN nav_menu p ON p.id=m.parent_id
                ORDER BY m.posisi, COALESCE(m.parent_id,0), m.urutan, m.id");
$parents = db_all("SELECT id, label FROM nav_menu WHERE parent_id IS NULL ORDER BY label");

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-list-nested text-primary"></i> Navigasi Menu (CMS-style)</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Item Menu</div>
  <form method="post" class="card-body row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-3"><label class="small">Label</label><input class="form-control form-control-sm" name="label" required></div>
    <div class="col-md-3"><label class="small">URL</label><input class="form-control form-control-sm" name="url" value="#"></div>
    <div class="col-md-2"><label class="small">Icon (bi-…)</label><input class="form-control form-control-sm" name="icon" placeholder="bi-house-door"></div>
    <div class="col-md-2"><label class="small">Parent</label>
      <select name="parent_id" class="form-select form-select-sm">
        <option value="">(root)</option>
        <?php foreach ($parents as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1"><label class="small">Urutan</label><input type="number" class="form-control form-control-sm" name="urutan" value="0"></div>
    <div class="col-md-1"><label class="small">Posisi</label>
      <select name="posisi" class="form-select form-select-sm">
        <option value="drawer">drawer</option><option value="top">top</option><option value="bottom">bottom</option>
      </select>
    </div>
    <div class="col-md-1"><label class="small">Target</label>
      <select name="target" class="form-select form-select-sm">
        <option value="_self">_self</option><option value="_blank">_blank</option>
      </select>
    </div>
    <div class="col-md-1 form-check mt-4 ms-2"><input class="form-check-input" type="checkbox" name="aktif" id="ak" checked><label for="ak" class="small">aktif</label></div>
    <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>#</th><th>Label</th><th>URL</th><th>Posisi</th><th>Parent</th><th>Urut</th><th>Aktif</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= $r['icon']?'<i class="bi '.htmlspecialchars($r['icon']).'"></i> ':'' ?><?= htmlspecialchars($r['label']) ?></td>
      <td class="small text-muted"><?= htmlspecialchars($r['url']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($r['posisi']) ?></span></td>
      <td class="small"><?= htmlspecialchars($r['parent_label'] ?? '—') ?></td>
      <td><?= (int)$r['urutan'] ?></td>
      <td><?= ($r['aktif']==='t'||$r['aktif']===true)?'✅':'⬜' ?></td>
      <td>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus menu ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<p class="text-muted small mt-3">Tip: panggil <code>nav_menu_html('drawer')</code> dari <code>includes/menu_render.php</code> di tempat manapun untuk merender menu yang dikelola di sini.</p>

<?php include __DIR__.'/../includes/footer.php'; ?>

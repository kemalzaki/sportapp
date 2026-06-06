<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Manajemen Member';
// Revisi 6 Juni 2026 — idempotent migration kolom koordinator_id
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS koordinator_id INTEGER REFERENCES users(id) ON DELETE SET NULL"); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='update_role') {
        db_exec("UPDATE users SET role=$1 WHERE id=$2", [$_POST['role'], (int)$_POST['id']]);
    } elseif ($a==='update_pic') {
        $pic = ($_POST['pic_admin_id'] ?? '') !== '' ? (int)$_POST['pic_admin_id'] : null;
        db_exec("UPDATE users SET pic_admin_id=$1 WHERE id=$2", [$pic, (int)$_POST['id']]);
    } elseif ($a==='update_koor') {
        $koor = ($_POST['koordinator_id'] ?? '') !== '' ? (int)$_POST['koordinator_id'] : null;
        db_exec("UPDATE users SET koordinator_id=$1 WHERE id=$2", [$koor, (int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM users WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='create') {
        $pwd = $_POST['password'] ?: 'changeme';
        $jk = in_array(($_POST['jenis_kelamin'] ?? ''), ['L','P'], true) ? $_POST['jenis_kelamin'] : null;
        $wa = trim($_POST['wa'] ?? '') ?: null;
        db_exec("INSERT INTO users(nama,email,password_hash,role,jenis_kelamin,wa) VALUES($1,$2,$3,$4,$5,$6)",
                [$_POST['nama'], $_POST['email'], password_hash($pwd, PASSWORD_BCRYPT), $_POST['role'], $jk, $wa]);
    } elseif ($a==='reset_pwd') {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) >= 6) {
            db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",
                    [password_hash($new, PASSWORD_BCRYPT), (int)$_POST['id']]);
            $_SESSION['flash'] = 'Password member berhasil diubah.';
        } else { $_SESSION['flash_err'] = 'Password minimal 6 karakter.'; }
    } elseif ($a==='edit') {
        $jk = in_array(($_POST['jenis_kelamin'] ?? ''), ['L','P'], true) ? $_POST['jenis_kelamin'] : null;
        $wa = trim($_POST['wa'] ?? '') ?: null;
        db_exec("UPDATE users SET nama=$1, email=$2, jenis_kelamin=$3, wa=$4 WHERE id=$5",
                [$_POST['nama'], $_POST['email'], $jk, $wa, (int)$_POST['id']]);
    } elseif ($a==='upload_foto') {
        $id = (int)$_POST['id'];
        $target = db_one("SELECT * FROM users WHERE id=$1", [$id]);
        if ($target && !empty($_FILES['foto']['name'])) {
            require_once __DIR__.'/../config/imagekit.php';
            global $imageKit;
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $safe = preg_replace('/[^a-z0-9]/i','_',$target['nama'])."-avatar-".time().".".$ext;
            $up = $imageKit->uploadFile([
                'file' => base64_encode(file_get_contents($_FILES['foto']['tmp_name'])),
                'fileName' => $safe,
                'folder' => '/sportapp/avatar'
            ]);
            if (!$up->error) {
                if (!empty($target['foto_file_id'])) { try { $imageKit->deleteFile($target['foto_file_id']); } catch(Throwable $e){} }
                db_exec("UPDATE users SET foto_url=$1, foto_file_id=$2 WHERE id=$3",
                        [$up->result->url, $up->result->fileId, $id]);
            }
        }
    } elseif ($a==='delete_foto') {
        $id = (int)$_POST['id'];
        $target = db_one("SELECT foto_file_id FROM users WHERE id=$1", [$id]);
        if ($target && !empty($target['foto_file_id'])) {
            require_once __DIR__.'/../config/imagekit.php';
            global $imageKit;
            try { $imageKit->deleteFile($target['foto_file_id']); } catch(Throwable $e){}
        }
        db_exec("UPDATE users SET foto_url=NULL, foto_file_id=NULL WHERE id=$1", [$id]);
    }
    header('Location: members.php'); exit;
}

$users = db_all("SELECT u.*, p.nama AS pic_nama, k.nama AS koor_nama
                 FROM users u
                 LEFT JOIN users p ON p.id = u.pic_admin_id
                 LEFT JOIN users k ON k.id = u.koordinator_id
                 ORDER BY u.role, u.nama");
$admins = db_all("SELECT id, nama FROM users WHERE role='admin' ORDER BY nama");
// Revisi 6 Juni 2026 — perluas daftar kandidat Koordinator Penghubung
// agar semua user (apapun role-nya) bisa dipilih sebagai penghubung antar member.
$koordinatorCandidates = db_all("SELECT id, nama FROM users WHERE nama IS NOT NULL AND nama <> '' ORDER BY nama");
$flash = $_SESSION['flash'] ?? null; $flashE = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-people text-primary"></i> Manajemen Member</h2>

<?php if($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if($flashE): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($flashE) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-person-plus me-1 text-primary"></i> Tambah Member</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="create">
    <div class="col-md-3"><input class="form-control" name="nama" placeholder="Nama lengkap" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" name="password" placeholder="Password (default: changeme)"></div>
    <div class="col-md-2"><input class="form-control" name="wa" placeholder="No. WhatsApp"></div>
    <div class="col-md-1"><select class="form-select" name="jenis_kelamin"><option value="">JK</option><option value="L">L</option><option value="P">P</option></select></div>
    <div class="col-md-1"><select class="form-select" name="role"><option value="member">member</option><option value="admin">admin</option></select></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Member</button></div>
  </form>
</div></div>

<div class="card shadow-sm">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span><i class="bi bi-table"></i> Daftar Member</span>
    <div class="d-flex gap-2 align-items-center">
      <input id="memberSearch" class="form-control form-control-sm" style="max-width:220px" placeholder="🔍 Cari nama / email...">
      <select id="memberPageSize" class="form-select form-select-sm" style="max-width:110px">
        <option value="10">10 / hal</option>
        <option value="25" selected>25 / hal</option>
        <option value="50">50 / hal</option>
        <option value="100">100 / hal</option>
      </select>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0 align-middle" id="memberTable" data-paginate="10">
  <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>WA</th><th>JK</th><th>PIC Admin</th><th>Koordinator Penghubung</th><th>Role</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($users as $i=>$u): $on = is_online($u['last_seen'] ?? null);
    $waDigits = preg_replace('/\D+/', '', $u['wa'] ?? '');
    if ($waDigits && str_starts_with($waDigits, '0')) $waDigits = '62'.substr($waDigits,1);
  ?>
    <tr data-search="<?= htmlspecialchars(strtolower(($u['nama'] ?? '').' '.($u['email'] ?? ''))) ?>">
      <td class="text-muted row-num"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= user_name_with_avatar($u['foto_url'] ?? null, $u['nama'], $on, 32) ?></td>
      <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
      <td><?= $u['wa'] ? '<span class="small">'.htmlspecialchars($u['wa']).'</span>' : '<span class="text-muted small">—</span>' ?></td>
      <td><?php $jk=$u['jenis_kelamin']??null; echo $jk==='L'?'<span class="pill">L</span>':($jk==='P'?'<span class="pill">P</span>':'<span class="text-muted small">—</span>'); ?></td>
      <td>
        <form method="post" class="d-flex">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_pic">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="pic_admin_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:130px">
            <option value="">— belum —</option>
            <?php foreach($admins as $ad): ?>
              <option value="<?= (int)$ad['id'] ?>" <?= (string)$u['pic_admin_id']===(string)$ad['id']?'selected':'' ?>><?= htmlspecialchars($ad['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <form method="post" class="d-flex">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_koor">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="koordinator_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:150px" title="Koordinator Penghubung antar member">
            <option value="">— belum —</option>
            <?php foreach($koordinatorCandidates as $kc): if((int)$kc['id']===(int)$u['id']) continue; ?>
              <option value="<?= (int)$kc['id'] ?>" <?= (string)($u['koordinator_id']??'')===(string)$kc['id']?'selected':'' ?>><?= htmlspecialchars($kc['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_role">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach(['publik','member','admin'] as $r): ?><option <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
          </select>
        </form>
      </td>
      <td><?= $on ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-secondary">Offline</span>' ?></td>
      <td class="text-end text-nowrap">
        <?php if($waDigits): ?>
          <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="Hubungi via WhatsApp"><i class="bi bi-whatsapp"></i></a>
        <?php else: ?>
          <button class="btn btn-sm btn-outline-secondary" disabled title="No. WA belum diisi"><i class="bi bi-whatsapp"></i></button>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#foto<?= $u['id'] ?>" title="Foto"><i class="bi bi-image"></i></button>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#pwd<?= $u['id'] ?>" title="Reset Password"><i class="bi bi-key"></i></button>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edt<?= $u['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['nama']) ?>?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center small">
    <div id="memberPageInfo" class="text-muted"></div>
    <nav><ul class="pagination pagination-sm mb-0" id="memberPager"></ul></nav>
  </div>
</div>

<?php foreach($users as $u): ?>
<div class="modal fade" id="foto<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-image"></i> Foto: <?= htmlspecialchars($u['nama']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center">
    <div class="mb-3"><?= user_avatar($u['foto_url'] ?? null, $u['nama'], 96) ?></div>
    <input type="file" name="foto" class="form-control" accept="image/*" required>
    <small class="text-muted">Foto akan diunggah ke ImageKit.</small>
  </div>
  <div class="modal-footer">
    <?php if(!empty($u['foto_url'])): ?>
    <button type="submit" name="_action" value="delete_foto" class="btn btn-outline-danger" onclick="return confirm('Hapus foto?')"><i class="bi bi-trash"></i> Hapus Foto</button>
    <?php endif; ?>
    <button type="submit" name="_action" value="upload_foto" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
  </div>
</form></div></div>

<div class="modal fade" id="pwd<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="reset_pwd"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small fw-semibold">Password Baru (min 6)</label>
    <input type="text" name="new_password" class="form-control" minlength="6" required>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-warning"><i class="bi bi-shield-check"></i> Reset</button></div>
</form></div></div>

<div class="modal fade" id="edt<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small fw-semibold">Nama</label><input name="nama" class="form-control" value="<?= htmlspecialchars($u['nama']) ?>" required></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Email</label><input name="email" type="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
    <div class="mb-2"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> No. WhatsApp</label><input name="wa" class="form-control" value="<?= htmlspecialchars($u['wa'] ?? '') ?>" placeholder="08xxxxxxxxxx"></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Jenis Kelamin</label>
      <select class="form-select" name="jenis_kelamin">
        <option value="" <?= empty($u['jenis_kelamin'])?'selected':'' ?>>— Tidak diisi —</option>
        <option value="L" <?= ($u['jenis_kelamin']??'')==='L'?'selected':'' ?>>Laki-laki</option>
        <option value="P" <?= ($u['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option>
      </select></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<script>
// === Client-side pagination & search untuk tabel member (tanpa reload) ===
(function(){
  var table = document.getElementById('memberTable');
  if(!table) return;
  var rows = Array.from(table.tBodies[0].rows);
  var searchInput = document.getElementById('memberSearch');
  var pageSizeSel = document.getElementById('memberPageSize');
  var pager = document.getElementById('memberPager');
  var info = document.getElementById('memberPageInfo');
  var page = 1;
  function filtered(){
    var q = (searchInput.value||'').toLowerCase().trim();
    return rows.filter(r => !q || (r.dataset.search||'').includes(q));
  }
  function render(){
    var ps = parseInt(pageSizeSel.value,10)||25;
    var data = filtered();
    var total = data.length;
    var pages = Math.max(1, Math.ceil(total/ps));
    if(page>pages) page = pages;
    var start = (page-1)*ps, end = Math.min(start+ps, total);
    rows.forEach(r => r.style.display='none');
    data.slice(start,end).forEach((r,i)=>{
      r.style.display='';
      var c = r.querySelector('.row-num'); if(c) c.textContent = start+i+1;
    });
    info.textContent = total ? ('Menampilkan '+(start+1)+'–'+end+' dari '+total+' member') : 'Tidak ada data';
    // pager
    pager.innerHTML = '';
    function btn(label, p, dis, act){
      var li = document.createElement('li');
      li.className = 'page-item'+(dis?' disabled':'')+(act?' active':'');
      var a = document.createElement('a');
      a.className='page-link'; a.href='#'; a.textContent=label;
      a.addEventListener('click', function(e){e.preventDefault(); if(!dis && !act){ page=p; render(); }});
      li.appendChild(a); pager.appendChild(li);
    }
    btn('«', Math.max(1,page-1), page<=1, false);
    var maxBtn = 7;
    var from = Math.max(1, page - 3), to = Math.min(pages, from + maxBtn - 1);
    from = Math.max(1, to - maxBtn + 1);
    for(var p=from; p<=to; p++) btn(String(p), p, false, p===page);
    btn('»', Math.min(pages,page+1), page>=pages, false);
  }
  searchInput.addEventListener('input', function(){ page=1; render(); });
  pageSizeSel.addEventListener('change', function(){ page=1; render(); });
  render();
})();
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>

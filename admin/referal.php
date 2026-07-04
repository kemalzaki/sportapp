<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/islami_migrations.php'; // ensure referal_codes exists
require_role(['superadmin']);
$pageTitle='CRUD Kode Referal';
$u=current_user();
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check();
 $a=$_POST['_action']??'';
 if($a==='create'){
  $kode=strtoupper(preg_replace('/[^A-Z0-9_-]/i','',trim($_POST['kode']??'')));
  if($kode==='') $kode='REF'.strtoupper(bin2hex(random_bytes(3)));
  try{
   db_exec("INSERT INTO referal_codes(kode,deskripsi,aktif,max_pakai,expired_at,dibuat_oleh) VALUES($1,$2,$3,$4,$5,$6)",
    [$kode, substr($_POST['deskripsi']??'',0,255), isset($_POST['aktif'])?1:0,
     $_POST['max_pakai']!==''?(int)$_POST['max_pakai']:null, $_POST['expired_at']?:null, (int)$u['id']]);
   $_SESSION['flash']="Kode $kode dibuat.";
  }catch(Throwable $e){ $_SESSION['flash_err']='Kode sudah ada.'; }
 } elseif($a==='update'){
  db_exec("UPDATE referal_codes SET deskripsi=$1, aktif=$2, max_pakai=$3, expired_at=$4 WHERE id=$5",
   [substr($_POST['deskripsi']??'',0,255), isset($_POST['aktif'])?1:0,
    $_POST['max_pakai']!==''?(int)$_POST['max_pakai']:null, $_POST['expired_at']?:null, (int)$_POST['id']]);
 } elseif($a==='delete'){ db_exec("DELETE FROM referal_codes WHERE id=$1",[(int)$_POST['id']]); }
 header('Location: /admin/referal.php'); exit; }

// Update jumlah_terpakai berdasar users.referred_by_code
db_exec("UPDATE referal_codes r SET jumlah_terpakai = (SELECT COUNT(*) FROM users u WHERE u.referred_by_code = r.kode)");
$rows=db_all("SELECT * FROM referal_codes ORDER BY created_at DESC");
include __DIR__.'/../includes/header.php'; ?>
<?php if(!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if(!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>
<h4 class="mb-3"><i class="bi bi-ticket-perforated text-primary"></i> CRUD Kode Referal</h4>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="create">
 <div class="row g-2">
  <div class="col-md-3"><input class="form-control" name="kode" placeholder="Kode (kosong=auto)" style="text-transform:uppercase"></div>
  <div class="col-md-4"><input class="form-control" name="deskripsi" placeholder="Deskripsi"></div>
  <div class="col-md-2"><input class="form-control" type="number" name="max_pakai" placeholder="Max pakai"></div>
  <div class="col-md-2"><input class="form-control" type="date" name="expired_at"></div>
  <div class="col-md-1 d-grid"><button class="btn btn-primary">+</button></div>
 </div>
 <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="aktif" id="ak" checked><label for="ak" class="form-check-label small">Aktif</label></div>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr><th>Kode</th><th>Deskripsi</th><th>Aktif</th><th>Pakai / Max</th><th>Expired</th><th>Dibuat</th><th></th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
 <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="update"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
 <td><strong><?= htmlspecialchars($r['kode']) ?></strong></td>
 <td><input class="form-control form-control-sm" name="deskripsi" value="<?= htmlspecialchars($r['deskripsi']??'') ?>"></td>
 <td><input type="checkbox" name="aktif" <?= $r['aktif']?'checked':'' ?>></td>
 <td><span class="badge bg-info"><?= (int)$r['jumlah_terpakai'] ?></span> / <input style="width:80px" class="form-control form-control-sm d-inline" type="number" name="max_pakai" value="<?= $r['max_pakai']!==null?(int)$r['max_pakai']:'' ?>"></td>
 <td><input class="form-control form-control-sm" type="date" name="expired_at" value="<?= htmlspecialchars($r['expired_at']??'') ?>"></td>
 <td class="small"><?= htmlspecialchars($r['created_at']) ?></td>
 <td><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button></td>
 </form>
 <td><form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
</tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted">Belum ada kode. Buat di atas.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__.'/../includes/footer.php'; ?>

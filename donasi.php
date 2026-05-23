<?php
require __DIR__.'/config/db.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/security.php'; require __DIR__.'/includes/helpers.php'; require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); $pageTitle='Donasi & Sedekah Challenge'; $u=current_user();
$tab=($_GET['tab']??'donasi')==='sedekah'?'sedekah':'donasi';
if($_SERVER['REQUEST_METHOD']==='POST' && $u){ csrf_check();
 $a=$_POST['_action']??'';
 if($a==='add' && $u['role']==='admin'){
  db_exec("INSERT INTO sedekah_program(judul,deskripsi,jenis,target_amount,deadline,dibuat_oleh) VALUES($1,$2,$3,$4,$5,$6)",
   [substr(trim($_POST['judul']??''),0,180),substr($_POST['deskripsi']??'',0,2000),
    in_array($_POST['jenis']??'',['donasi','sedekah'],true)?$_POST['jenis']:'donasi',
    (int)($_POST['target_amount']??0), $_POST['deadline']?:null, (int)$u['id']]); }
 elseif($a==='donate'){
  $pid=(int)$_POST['program_id']; $jml=max(1000,(int)$_POST['jumlah']);
  db_exec("INSERT INTO sedekah_log(program_id,user_id,jumlah,catatan) VALUES($1,$2,$3,$4)",
   [$pid,(int)$u['id'],$jml,substr($_POST['catatan']??'',0,200)]);
  db_exec("UPDATE sedekah_program SET terkumpul=terkumpul+$1 WHERE id=$2",[$jml,$pid]);
  islami_touch_streak((int)$u['id'],'sedekah'); $_SESSION['flash']='Jazakallahu khairan 🤍'; }
 elseif($a==='delete' && $u['role']==='admin'){ db_exec("DELETE FROM sedekah_program WHERE id=$1",[(int)$_POST['id']]); }
 header('Location: /donasi.php?tab='.$tab); exit; }
$rows=db_all("SELECT * FROM sedekah_program WHERE jenis=$1 AND active=1 ORDER BY created_at DESC",[$tab]);
include __DIR__.'/includes/header.php'; ?>
<?php if(!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
 <h4 class="m-0"><i class="bi bi-<?= $tab==='sedekah'?'gift':'cash-stack' ?> text-success"></i> <?= $tab==='sedekah'?'Sedekah Challenge Komunitas':'Donasi Masjid / Event Sosial' ?></h4>
 <div><a href="?tab=donasi" class="btn btn-sm <?= $tab==='donasi'?'btn-success':'btn-outline-success' ?>">Donasi</a>
  <a href="?tab=sedekah" class="btn btn-sm <?= $tab==='sedekah'?'btn-success':'btn-outline-success' ?>">Sedekah Challenge</a></div>
</div>
<?php if($u&&$u['role']==='admin'): ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add">
 <input type="hidden" name="jenis" value="<?= $tab ?>">
 <div class="row g-2"><div class="col-md-5"><input class="form-control" name="judul" placeholder="Judul" required></div>
  <div class="col-md-3"><input class="form-control" type="number" name="target_amount" placeholder="Target (Rp)" required></div>
  <div class="col-md-3"><input class="form-control" type="date" name="deadline"></div>
  <div class="col-md-1"><button class="btn btn-success w-100">+</button></div></div>
 <textarea class="form-control mt-2" name="deskripsi" rows="2" placeholder="Deskripsi"></textarea>
</form>
<?php endif; ?>
<?php foreach($rows as $r): $pct = $r['target_amount']>0 ? min(100, round(100*$r['terkumpul']/$r['target_amount'])) : 0; ?>
<div class="card mb-2"><div class="card-body">
 <div class="d-flex justify-content-between"><h6 class="m-0"><?= htmlspecialchars($r['judul']) ?></h6>
  <?php if($u&&$u['role']==='admin'): ?><form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form><?php endif; ?>
 </div>
 <?php if(!empty($r['deskripsi'])): ?><div class="small text-muted"><?= nl2br(htmlspecialchars($r['deskripsi'])) ?></div><?php endif; ?>
 <div class="progress my-2" style="height:18px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div>
 <div class="small">Rp <?= number_format($r['terkumpul'],0,',','.') ?> / Rp <?= number_format($r['target_amount'],0,',','.') ?>
  <?php if($r['deadline']): ?>· deadline <?= htmlspecialchars($r['deadline']) ?><?php endif; ?></div>
 <?php if($u): ?>
 <form method="post" class="d-flex gap-2 mt-2"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="donate"><input type="hidden" name="program_id" value="<?= (int)$r['id'] ?>">
  <input class="form-control form-control-sm" type="number" name="jumlah" min="1000" step="1000" placeholder="Jumlah (Rp)" required>
  <input class="form-control form-control-sm" name="catatan" maxlength="200" placeholder="Catatan (opsional)">
  <button class="btn btn-success btn-sm">Bantu</button></form>
 <?php endif; ?>
</div></div>
<?php endforeach; if(!$rows): ?><div class="text-muted">Belum ada program.</div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

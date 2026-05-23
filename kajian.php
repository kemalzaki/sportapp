<?php
require __DIR__.'/config/db.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/security.php'; require __DIR__.'/includes/helpers.php'; require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login(); $pageTitle='Kajian Kesehatan Islami'; $u=current_user();
if($_SERVER['REQUEST_METHOD']==='POST' && $u){ csrf_check();
 $a=$_POST['_action']??'';
 if($a==='post'){ db_exec("INSERT INTO islami_kajian(user_id,judul,isi,link_video) VALUES($1,$2,$3,$4)",[(int)$u['id'],substr(trim($_POST['judul']??''),0,180),substr($_POST['isi']??'',0,3000),substr(trim($_POST['link_video']??''),0,255)]); }
 elseif($a==='delete'){ $id=(int)$_POST['id']; $o=db_one("SELECT user_id FROM islami_kajian WHERE id=$1",[$id]); if($o&&((int)$o['user_id']===(int)$u['id']||$u['role']==='admin')) db_exec("DELETE FROM islami_kajian WHERE id=$1",[$id]); }
 header('Location: /kajian.php'); exit; }
$rows=db_all("SELECT k.*, u.nama FROM islami_kajian k LEFT JOIN users u ON u.id=k.user_id ORDER BY k.created_at DESC");
include __DIR__.'/includes/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-mic text-info"></i> Kajian Kesehatan Islami</h4>
<?php if($u): ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post">
 <input class="form-control mb-2" name="judul" maxlength="180" placeholder="Judul kajian" required>
 <input class="form-control mb-2" name="link_video" maxlength="255" placeholder="Link video YouTube (opsional)">
 <textarea class="form-control mb-2" name="isi" rows="3" placeholder="Ringkasan / catatan kajian..."></textarea>
 <button class="btn btn-info">Bagikan Kajian</button></form>
<?php endif; ?>
<?php foreach($rows as $r): ?>
<div class="card mb-2"><div class="card-body">
 <div class="d-flex justify-content-between"><h6 class="m-0"><?= htmlspecialchars($r['judul']) ?></h6>
  <?php if($u&&((int)$r['user_id']===(int)$u['id']||$u['role']==='admin')): ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
  <?php endif; ?></div>
 <div class="small text-muted">Oleh <?= htmlspecialchars($r['nama']??'Anon') ?> · <?= htmlspecialchars($r['created_at']) ?></div>
 <?php if(!empty($r['link_video'])): ?><div class="mt-2"><a href="<?= htmlspecialchars($r['link_video']) ?>" target="_blank" rel="noopener"><i class="bi bi-play-circle"></i> Tonton video</a></div><?php endif; ?>
 <?php if(!empty($r['isi'])): ?><div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div><?php endif; ?>
 <?php if($u): ?><a class="btn btn-sm btn-outline-success mt-2" href="https://wa.me/?text=<?= rawurlencode($r['judul'].' - '.($r['link_video']??'')) ?>" target="_blank"><i class="bi bi-share"></i> Sharing</a><?php endif; ?>
</div></div>
<?php endforeach; if(!$rows): ?><div class="text-muted">Belum ada kajian.</div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

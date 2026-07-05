<?php
require __DIR__.'/config/db.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/security.php'; require __DIR__.'/includes/helpers.php'; require __DIR__.'/includes/islami_helpers.php';
require_once __DIR__.'/includes/scope.php';
send_security_headers(); require_login(); $pageTitle='Saling Mendoakan'; $u=current_user();
$__scopeUsers = scope_user_ids_sql_array();
$__isSuper    = scope_is_super();
if($_SERVER['REQUEST_METHOD']==='POST' && $u){ csrf_check();
 $a=$_POST['_action']??'';
 if($a==='post'){ $isi=substr(trim($_POST['isi']??''),0,500); if($isi!=='') db_exec("INSERT INTO doa_request(user_id,isi) VALUES($1,$2)",[(int)$u['id'],htmlspecialchars($isi)]); }
 elseif($a==='aamiin'){ try{ db_exec("INSERT INTO doa_aamiin(doa_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING",[(int)$_POST['id'],(int)$u['id']]); }catch(Throwable $e){} }
 elseif($a==='delete'){ $id=(int)$_POST['id']; $o=db_one("SELECT user_id FROM doa_request WHERE id=$1",[$id]); if($o&&((int)$o['user_id']===(int)$u['id']||$u['role']==='admin')) db_exec("DELETE FROM doa_request WHERE id=$1",[$id]); }
 header('Location: /doa_antar_member.php'); exit; }
// Revisi Juli 2026 — filter doa hanya dari member komunitas user login (superadmin lihat semua)
$rows=db_all("SELECT d.*, u.nama, u.foto_url, k.nama AS komunitas_nama,
 (SELECT COUNT(*) FROM doa_aamiin a WHERE a.doa_id=d.id) AS jml_aamiin
 FROM doa_request d
 JOIN users u ON u.id=d.user_id
 LEFT JOIN komunitas k ON k.id=u.komunitas_id
 WHERE d.user_id = ANY($1::int[])
 ORDER BY d.created_at DESC LIMIT 100", [$__scopeUsers]);
include __DIR__.'/includes/header.php'; ?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="/index.php">Beranda</a></li><li class="breadcrumb-item"><a href="/islami.php">Islami</a></li><li class="breadcrumb-item active">Saling Mendoakan</li></ol></nav>

<h4 class="mb-3"><i class="bi bi-heart text-danger"></i> Saling Mendoakan Antar Member</h4>
<div class="small text-muted mb-2">Menampilkan doa dari <?= $__isSuper ? '<strong>semua komunitas</strong> (SuperAdmin)' : '<strong>komunitas Anda</strong>' ?>.</div>
<?php if($u): ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post">
 <textarea class="form-control mb-2" name="isi" rows="2" placeholder="Mohon doa untuk... (max 500 karakter)" required maxlength="500"></textarea>
 <button class="btn btn-danger btn-sm">Kirim Permohonan Doa</button></form>
<?php endif; ?>
<?php foreach($rows as $r): ?>
<div class="card mb-2"><div class="card-body">
 <div class="d-flex justify-content-between"><div class="d-flex align-items-center gap-2 flex-wrap"><?= user_avatar($r['foto_url']??null,$r['nama'],28) ?> <strong><?= htmlspecialchars($r['nama']) ?></strong><?php if(!empty($r['komunitas_nama'])): ?> <span class="badge bg-light text-dark border">🏷️ <?= htmlspecialchars($r['komunitas_nama']) ?></span><?php endif; ?> <span class="small text-muted">· <?= htmlspecialchars($r['created_at']) ?></span></div>
 <?php if($u&&((int)$r['user_id']===(int)$u['id']||$u['role']==='admin')): ?>
 <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
 <?php endif; ?></div>
 <div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div>
 <?php if($u): ?>
 <form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="aamiin"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
  <button class="btn btn-sm btn-outline-success">🤲 Aamiin (<?= (int)$r['jml_aamiin'] ?>)</button></form>
 <?php else: ?><div class="small text-muted">🤲 <?= (int)$r['jml_aamiin'] ?> aamiin</div><?php endif; ?>
</div></div>
<?php endforeach; if(!$rows): ?><div class="text-muted">Belum ada permohonan doa.</div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

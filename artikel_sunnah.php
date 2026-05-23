<?php
require __DIR__.'/config/db.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/security.php'; require __DIR__.'/includes/helpers.php'; require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login(); $pageTitle='Artikel Sunnah Menjaga Kesehatan'; $u=current_user();
if($_SERVER['REQUEST_METHOD']==='POST' && $u){ csrf_check();
 $a=$_POST['_action']??'';
 if($a==='post'){ db_exec("INSERT INTO islami_artikel(user_id,judul,isi) VALUES($1,$2,$3)",[(int)$u['id'],substr(trim($_POST['judul']??''),0,180),substr($_POST['isi']??'',0,8000)]); }
 elseif($a==='delete'){ $id=(int)$_POST['id']; $o=db_one("SELECT user_id FROM islami_artikel WHERE id=$1",[$id]); if($o&&((int)$o['user_id']===(int)$u['id']||$u['role']==='admin')) db_exec("DELETE FROM islami_artikel WHERE id=$1",[$id]); }
 header('Location: /artikel_sunnah.php'); exit; }
// Seed artikel default jika kosong
if (!(int)db_val("SELECT COUNT(*) FROM islami_artikel")) {
  $seed = [
   ['Pola Tidur ala Rasulullah','Tidur lebih awal setelah Isya, bangun sebelum Subuh. Posisi tidur miring ke kanan, dengan dzikir sebelum tidur.'],
   ['Makan Tidak Berlebihan','Sepertiga untuk makanan, sepertiga air, sepertiga udara. Pola makan yang menjaga kesehatan jangka panjang.'],
   ['Berbekam (Hijamah)','Sunnah Nabi yang baik dilakukan di tanggal 17, 19, 21 bulan hijriyah untuk membantu sirkulasi darah.'],
   ['Madu, Habbatussauda, Kurma','Tiga makanan sunnah yang memiliki manfaat kesehatan luar biasa.'],
   ['Berjalan Kaki & Olahraga','Rasulullah menganjurkan memanah, berenang, dan menunggang kuda. Bergeraklah setiap hari.'],
  ];
  foreach($seed as $s) db_exec("INSERT INTO islami_artikel(user_id,judul,isi) VALUES(NULL,$1,$2)", [$s[0],$s[1]]);
}
$rows=db_all("SELECT a.*, u.nama FROM islami_artikel a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC");
include __DIR__.'/includes/header.php'; ?>
<h4 class="mb-3"><i class="bi bi-journal-text text-success"></i> Artikel Sunnah Menjaga Kesehatan</h4>
<?php if($u&&$u['role']==='admin'): ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post">
 <input class="form-control mb-2" name="judul" maxlength="180" placeholder="Judul artikel" required>
 <textarea class="form-control mb-2" name="isi" rows="4" required></textarea>
 <button class="btn btn-success">Publikasikan</button></form>
<?php endif; ?>
<?php foreach($rows as $r): ?>
<div class="card mb-2"><div class="card-body">
 <h6 class="m-0"><?= htmlspecialchars($r['judul']) ?></h6>
 <div class="small text-muted">Oleh <?= htmlspecialchars($r['nama']??'Admin') ?> · <?= htmlspecialchars($r['created_at']) ?></div>
 <div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div>
 <?php if($u&&$u['role']==='admin'): ?>
 <form method="post" class="mt-2" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form>
 <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

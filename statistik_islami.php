<?php
require __DIR__.'/config/db.php'; require __DIR__.'/includes/auth.php'; require __DIR__.'/includes/security.php'; require __DIR__.'/includes/helpers.php'; require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login(); $pageTitle='Statistik & Streak'; $u=current_user();
include __DIR__.'/includes/header.php';
if(!$u){ echo '<div class="alert alert-warning">Login dulu.</div>'; include __DIR__.'/includes/footer.php'; exit; }
$rows=db_all("SELECT tanggal, quran_done, dzikir_pagi, dzikir_petang, doa_done, subuh_walk, sedekah, poin FROM islami_streak WHERE user_id=$1 ORDER BY tanggal DESC LIMIT 60",[(int)$u['id']]);
$streak=islami_streak_count((int)$u['id']);
$totalPoin=(int)db_val("SELECT COALESCE(SUM(poin),0) FROM islami_streak WHERE user_id=$1",[(int)$u['id']]);
$hadirOlahraga=(int)db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1 AND hadir=1",[(int)$u['id']]);
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0"><li class="breadcrumb-item"><a href="/index.php">Beranda</a></li><li class="breadcrumb-item"><a href="/islami.php">Islami</a></li><li class="breadcrumb-item active">Statistik & Streak</li></ol></nav>

<h4 class="mb-3"><i class="bi bi-graph-up text-primary"></i> Statistik Hari Produktif & Sehat</h4>
<div class="row g-3 mb-3">
 <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-6"><?= $streak ?></div><div class="small text-muted">Streak hari</div></div></div></div>
 <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-6"><?= $totalPoin ?></div><div class="small text-muted">Total poin islami</div></div></div></div>
 <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-6"><?= count($rows) ?></div><div class="small text-muted">Hari aktif (60 hari)</div></div></div></div>
 <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="display-6"><?= $hadirOlahraga ?></div><div class="small text-muted">Hadir olahraga</div></div></div></div>
</div>
<div class="card shadow-sm"><div class="card-header">Log Harian (60 hari terakhir)</div>
<div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Tanggal</th><th>Qur'an</th><th>Dz Pagi</th><th>Dz Petang</th><th>Doa</th><th>Subuh Walk</th><th>Sedekah</th><th class="text-end">Poin</th></tr></thead>
<tbody>
<?php foreach($rows as $r): $ck=fn($v)=>$v?'✅':'—'; ?>
<tr><td><?= htmlspecialchars($r['tanggal']) ?></td><td><?= $ck($r['quran_done']) ?></td><td><?= $ck($r['dzikir_pagi']) ?></td><td><?= $ck($r['dzikir_petang']) ?></td><td><?= $ck($r['doa_done']) ?></td><td><?= $ck($r['subuh_walk']) ?></td><td><?= $ck($r['sedekah']) ?></td><td class="text-end"><?= (int)$r['poin'] ?></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="8" class="text-center text-muted">Belum ada aktivitas.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

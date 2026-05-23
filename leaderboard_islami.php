<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Leaderboard Amal & Aktivitas Sehat';
include __DIR__.'/includes/header.php';

$rows = db_all("SELECT u.id, u.nama, u.foto_url,
                COALESCE(SUM(s.poin),0) AS poin_islami,
                COALESCE((SELECT COUNT(*) FROM islami_streak s2 WHERE s2.user_id=u.id),0) AS hari_aktif,
                COALESCE((SELECT COUNT(*) FROM absensi a WHERE a.user_id=u.id AND a.hadir=1),0) AS hadir_olahraga
                FROM users u
                LEFT JOIN islami_streak s ON s.user_id=u.id
                WHERE u.role IN ('member','admin')
                GROUP BY u.id, u.nama, u.foto_url
                ORDER BY poin_islami DESC, hadir_olahraga DESC
                LIMIT 50");
?>
<h4 class="mb-3"><i class="bi bi-bar-chart-line text-danger"></i> Leaderboard Amal & Aktivitas Sehat</h4>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table mb-0 align-middle">
  <thead><tr><th>#</th><th>Member</th><th class="text-end">Poin Islami</th><th class="text-end">Hari Aktif</th><th class="text-end">Hadir Olahraga</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $i=>$r): ?>
    <tr>
      <td><?= $i+1 ?> <?php if($i<3): ?><i class="bi bi-trophy-fill text-warning"></i><?php endif; ?></td>
      <td><?= user_avatar($r['foto_url'] ?? null, $r['nama'], 28) ?> <?= htmlspecialchars($r['nama']) ?></td>
      <td class="text-end"><span class="badge bg-success"><?= (int)$r['poin_islami'] ?></span></td>
      <td class="text-end"><?= (int)$r['hari_aktif'] ?></td>
      <td class="text-end"><?= (int)$r['hadir_olahraga'] ?></td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>

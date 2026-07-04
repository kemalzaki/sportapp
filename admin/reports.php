<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
send_security_headers(); require_role('superadmin');
$pageTitle = 'Laporan Postingan';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $rid = (int)($_POST['id'] ?? 0);
    $a = $_POST['_action'] ?? '';
    if ($rid && $a==='resolve') {
        db_exec("UPDATE post_reports SET status='resolved', resolved_at=now() WHERE id=$1", [$rid]);
    } elseif ($rid && $a==='hapus_post') {
        $pid = (int)db_val("SELECT post_id FROM post_reports WHERE id=$1", [$rid]);
        if ($pid) {
            db_exec("DELETE FROM post_comments WHERE post_id=$1", [$pid]);
            db_exec("DELETE FROM post_likes WHERE post_id=$1", [$pid]);
            db_exec("DELETE FROM posts WHERE id=$1", [$pid]);
        }
        db_exec("UPDATE post_reports SET status='resolved', resolved_at=now() WHERE post_id=$2", [$pid]);
    }
    header('Location: /admin/reports.php'); exit;
}

$rows = db_all("SELECT r.*, p.caption, p.foto_url, pu.nama AS post_author, ru.nama AS reporter
                FROM post_reports r
                LEFT JOIN posts p ON p.id=r.post_id
                LEFT JOIN users pu ON pu.id=p.user_id
                JOIN users ru ON ru.id=r.reporter_id
                ORDER BY (r.status='open') DESC, r.created_at DESC LIMIT 200");

include __DIR__.'/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-flag text-danger"></i> Laporan Postingan</h4>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-sm mb-0 align-middle">
<thead><tr><th>#</th><th>Waktu</th><th>Pelapor</th><th>Post</th><th>Alasan</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td class="small"><?= htmlspecialchars($r['created_at']) ?></td>
    <td><?= htmlspecialchars($r['reporter']) ?></td>
    <td class="small"><strong><?= htmlspecialchars($r['post_author']??'(post hilang)') ?></strong><br>
      <span class="text-muted"><?= htmlspecialchars(mb_strimwidth($r['caption']??'',0,80,'…')) ?></span></td>
    <td><span class="badge bg-warning-subtle text-warning"><?= htmlspecialchars($r['alasan']) ?></span>
      <?php if($r['catatan']): ?><div class="small text-muted"><?= htmlspecialchars($r['catatan']) ?></div><?php endif; ?></td>
    <td><span class="badge bg-<?= $r['status']==='open'?'danger':'success' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    <td>
      <?php if($r['status']==='open'): ?>
      <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="_action" value="resolve"><button class="btn btn-sm btn-outline-success">Tutup</button></form>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus post terlapor?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="_action" value="hapus_post"><button class="btn btn-sm btn-outline-danger">Hapus Post</button></form>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-muted text-center p-3">Tidak ada laporan.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php include __DIR__.'/../includes/footer.php'; ?>

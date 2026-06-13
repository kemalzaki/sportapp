<?php
// Riwayat Login Member — Revisi 13 Juni 2026
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle='Riwayat Login Member';
try {
  db_exec("CREATE TABLE IF NOT EXISTS login_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    ip VARCHAR(64), user_agent VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT now()
  )");
  db_exec("CREATE INDEX IF NOT EXISTS idx_login_logs_user ON login_logs(user_id, created_at DESC)");
} catch (Throwable $e) {}
$rows = db_all("SELECT l.*, u.nama, u.foto_url, u.email
                FROM login_logs l JOIN users u ON u.id=l.user_id
                ORDER BY l.created_at DESC LIMIT 300");
$last = db_all("SELECT u.id,u.nama,u.foto_url,u.email,
                       (SELECT MAX(created_at) FROM login_logs ll WHERE ll.user_id=u.id) AS terakhir
                FROM users u WHERE u.role IN ('member','admin')
                ORDER BY terakhir DESC NULLS LAST, u.nama LIMIT 200");
include __DIR__.'/../includes/header.php'; ?>
<h2 class="mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat Login Member</h2>
<div class="row g-3">
  <div class="col-lg-5"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-person-check"></i> Terakhir Login per Member</div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
      <thead class="table-light"><tr><th>Member</th><th>Terakhir Login</th></tr></thead><tbody>
      <?php foreach($last as $r): ?>
        <tr><td><?= user_name_with_avatar($r['foto_url']??null, $r['nama'], false, 26) ?><div class="small text-muted"><?= htmlspecialchars($r['email']) ?></div></td>
            <td class="small"><?= $r['terakhir'] ? htmlspecialchars($r['terakhir']) : '<span class="text-muted">belum pernah</span>' ?></td></tr>
      <?php endforeach; if(!$last): ?><tr><td colspan="2" class="text-center text-muted small py-3">Belum ada data.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header"><i class="bi bi-list-ul"></i> Log Login (300 terbaru)</div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
      <thead class="table-light"><tr><th>Waktu</th><th>Member</th><th>IP</th><th>User-Agent</th></tr></thead><tbody>
      <?php foreach($rows as $r): ?>
        <tr><td class="small"><?= htmlspecialchars($r['created_at']) ?></td>
            <td><?= user_name_with_avatar($r['foto_url']??null, $r['nama'], false, 22) ?></td>
            <td class="small"><?= htmlspecialchars($r['ip'] ?? '-') ?></td>
            <td class="small text-muted"><?= htmlspecialchars(mb_substr($r['user_agent'] ?? '-',0,80)) ?></td></tr>
      <?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-center text-muted small py-3">Belum ada log login.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
<p class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Tambahkan baris berikut di <code>login.php</code> setelah login sukses agar log terisi otomatis:
<br><code>db_exec("INSERT INTO login_logs(user_id,ip,user_agent) VALUES(\$1,\$2,\$3)", [(int)\$u['id'], \$_SERVER['REMOTE_ADDR']??null, substr(\$_SERVER['HTTP_USER_AGENT']??'',0,250)]);</code></p>
<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Profil Saya';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='update_bio') {
        $bio = substr(trim($_POST['bio'] ?? ''), 0, 300);
        db_exec("UPDATE users SET bio=$1 WHERE id=$2", [$bio, (int)$u['id']]);
    } elseif ($a==='mark_notif') {
        db_exec("UPDATE notifications SET dibaca=1 WHERE user_id=$1", [(int)$u['id']]);
    } elseif ($a==='update_foto') {
        if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $mime = mime_content_type($_FILES['foto']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp'], true) && $_FILES['foto']['size'] < 5*1024*1024) {
                $ext = $mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');
                $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama'])."-avatar-".time().".".$ext;
                require_once __DIR__.'/config/imagekit.php';
                global $imageKit;
                $upl = $imageKit->uploadFile([
                    'file' => base64_encode(file_get_contents($_FILES['foto']['tmp_name'])),
                    'fileName' => $safe,
                    'folder' => '/sportapp/avatar'
                ]);
                if (!$upl->error) {
                    db_exec("UPDATE users SET foto_url=$1 WHERE id=$2", [$upl->result->url, (int)$u['id']]);
                }
            }
        }
    }
    header('Location: profile.php'); exit;
}

recompute_badges((int)$u['id']);
$me = db_one("SELECT * FROM users WHERE id=$1", [(int)$u['id']]);
$allBadges = db_all("SELECT * FROM badges ORDER BY xp DESC");
$ownBadgeIds = array_column(db_all("SELECT badge_id FROM user_badges WHERE user_id=$1", [(int)$u['id']]), 'badge_id');
$ownBadgeIds = array_map('intval', $ownBadgeIds);
$notifs = db_all("SELECT * FROM notifications WHERE user_id=$1 ORDER BY created_at DESC LIMIT 30", [(int)$u['id']]);

// ===== Achievement statistics =====
$statHadir = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1 AND hadir=1", [(int)$u['id']]);
$statSesi  = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1", [(int)$u['id']]);
$statOlahraga = (int) db_val("SELECT COUNT(DISTINCT j.jenis) FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1", [(int)$u['id']]);
$totalKalori = (int) db_val("SELECT COALESCE(SUM(kalori),0) FROM upload_harian WHERE user_id=$1", [(int)$u['id']]);
$totalJarak  = (float) db_val("SELECT COALESCE(SUM(jarak_km),0) FROM upload_harian WHERE user_id=$1", [(int)$u['id']]);
$favRow = db_one("SELECT j.jenis, COUNT(*) AS c FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1 GROUP BY j.jenis ORDER BY c DESC LIMIT 1", [(int)$u['id']]);
$favOlahraga = $favRow['jenis'] ?? '—';

// ranking komunitas berdasarkan total hadir
$ranking = (int) db_val("SELECT rnk FROM (SELECT user_id, RANK() OVER (ORDER BY COUNT(*) DESC) AS rnk FROM absensi WHERE hadir=1 GROUP BY user_id) t WHERE user_id=$1", [(int)$u['id']]);
$totalMember = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");

// Heatmap data 1 tahun terakhir (per tanggal)
$heatRows = db_all("SELECT j.tanggal::date AS d, COUNT(*) AS c FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                    WHERE a.user_id=$1 AND a.hadir=1 AND j.tanggal >= CURRENT_DATE - INTERVAL '365 days'
                    GROUP BY j.tanggal::date", [(int)$u['id']]);
$heatMap = [];
foreach ($heatRows as $r) $heatMap[$r['d']] = (int)$r['c'];

$xp = (int)$me['xp']; $level = (int)$me['level'];
$xpInLevel = $xp % 200; $xpToNext = 200 - $xpInLevel;
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-person-circle text-primary"></i> Profil Saya</h2>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm"><div class="card-body text-center">
      <?= user_avatar($me['foto_url'] ?? null, $me['nama'], 96) ?>
      <h4 class="mt-2 mb-0"><?= htmlspecialchars($me['nama']) ?></h4>
      <div class="small text-muted"><?= htmlspecialchars($me['email']) ?></div>
      <div class="mt-2"><span class="pill">Level <?= $level ?></span>
        <span class="pill" data-bs-toggle="tooltip" title="Streak (mgg) = jumlah minggu berturut-turut Anda upload aktivitas atau hadir di sesi. Reset jika 1 minggu kosong.">🔥 <?= (int)$me['streak_minggu'] ?> minggu</span>
        <span class="pill">⭐ <?= $xp ?> XP</span></div>
      <div class="xp-bar mt-2"><div style="width:<?= min(100,$xpInLevel/2) ?>%"></div></div>
      <small class="text-muted">Butuh <?= $xpToNext ?> XP lagi ke Level <?= $level+1 ?></small>

      <form method="post" enctype="multipart/form-data" class="mt-3 text-start">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_foto">
        <label class="form-label small fw-semibold">Ganti Foto Profil</label>
        <div class="input-group input-group-sm">
          <input type="file" name="foto" accept="image/*" class="form-control" required>
          <button class="btn btn-outline-primary"><i class="bi bi-upload"></i></button>
        </div>
        <div class="form-text">JPG/PNG/WebP · maks 5MB</div>
      </form>


      <form method="post" class="mt-3 text-start">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_bio">
        <label class="form-label small fw-semibold">Bio singkat</label>
        <textarea name="bio" class="form-control" rows="2" maxlength="300"><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
        <button class="btn btn-sm btn-primary mt-2">Simpan Bio</button>
      </form>
    </div></div>

    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-bell"></i> Notifikasi
      <form method="post" class="float-end"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="mark_notif">
        <button class="btn btn-link btn-sm p-0">Tandai sudah dibaca</button>
      </form>
    </div>
    <ul class="list-group list-group-flush notif-list">
      <?php foreach($notifs as $n): ?>
      <li class="list-group-item notif-item <?= $n['dibaca']==0?'unread':'' ?>">
        <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($n['judul']) ?></strong>
          <small class="text-muted"><?= date('d M H:i', strtotime($n['created_at'])) ?></small></div>
        <div class="small text-muted"><?= htmlspecialchars($n['isi']) ?></div>
        <?php if($n['url']): ?><a class="small" href="<?= htmlspecialchars($n['url']) ?>">Buka</a><?php endif; ?>
      </li>
      <?php endforeach; if(!$notifs): ?><li class="list-group-item text-muted text-center small">Belum ada notifikasi.</li><?php endif; ?>
    </ul></div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-stars text-warning"></i> Achievement Profile</div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Hadir</div><div class="stat-value"><?= $statHadir ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Sesi</div><div class="stat-value"><?= $statSesi ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Jenis Olahraga</div><div class="stat-value"><?= $statOlahraga ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat" data-bs-toggle="tooltip" title="Streak (mgg) = jumlah minggu berturut-turut aktif (upload aktivitas atau hadir di sesi). Reset jika ada 1 minggu kosong."><div class="card-body"><div class="stat-label">Streak (mgg) <i class="bi bi-info-circle small text-muted"></i></div><div class="stat-value"><?= (int)$me['streak_minggu'] ?></div><div class="small text-muted" style="font-size:.7rem">Minggu aktif beruntun</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Badge</div><div class="stat-value"><?= count($ownBadgeIds) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Olahraga Favorit</div><div class="stat-value" style="font-size:1rem"><?= htmlspecialchars($favOlahraga) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Kalori</div><div class="stat-value"><?= number_format($totalKalori) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Jarak (km)</div><div class="stat-value"><?= number_format($totalJarak,1) ?></div></div></div></div>
        <div class="col-12"><div class="alert alert-info py-2 mb-0 small"><i class="bi bi-trophy"></i> Ranking Komunitas: <strong>#<?= $ranking ?: '-' ?></strong> dari <?= $totalMember ?> member</div></div>
      </div>
    </div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-grid-3x3 text-success"></i> Attendance Heatmap (1 tahun terakhir)</div>
    <div class="card-body">
      <div class="heatmap">
        <?php
          $start = strtotime('-365 days');
          // align ke awal minggu (Minggu)
          $start = strtotime('-'.date('w',$start).' days', $start);
          for ($t=$start; $t<=time(); $t += 86400) {
            $d = date('Y-m-d',$t);
            $c = $heatMap[$d] ?? 0;
            $lvl = $c<=0?0:($c==1?1:($c==2?2:($c==3?3:4)));
            echo '<div class="cell '.($lvl?'l'.$lvl:'').'" title="'.$d.': '.$c.' sesi"></div>';
          }
        ?>
      </div>
      <div class="d-flex align-items-center gap-2 mt-2 small text-muted">Less
        <span class="d-inline-block" style="width:12px;height:12px;background:#ebedf0;border-radius:2px;"></span>
        <span class="d-inline-block" style="width:12px;height:12px;background:#9be9a8;border-radius:2px;"></span>
        <span class="d-inline-block" style="width:12px;height:12px;background:#40c463;border-radius:2px;"></span>
        <span class="d-inline-block" style="width:12px;height:12px;background:#30a14e;border-radius:2px;"></span>
        <span class="d-inline-block" style="width:12px;height:12px;background:#216e39;border-radius:2px;"></span>
        More
      </div>
    </div></div>

    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-award-fill text-warning"></i> Badge & Achievement</div>
    <div class="card-body">
      <div class="row g-2">
      <?php foreach($allBadges as $b): $owned = in_array((int)$b['id'], $ownBadgeIds, true); ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="badge-tile <?= $owned?'':'locked' ?>" title="<?= htmlspecialchars($b['deskripsi']) ?>">
            <i class="bi <?= htmlspecialchars($b['icon']) ?> text-<?= htmlspecialchars($b['warna']) ?>"></i>
            <div class="fw-semibold mt-1 small"><?= htmlspecialchars($b['nama']) ?></div>
            <div class="text-muted small">+<?= (int)$b['xp'] ?> XP <?= $owned?'· ✅':'· terkunci' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div></div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  if(window.bootstrap){document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));}
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

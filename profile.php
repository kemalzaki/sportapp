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

// Pastikan tabel olahraga favorit ada (idempotent — aman tiap load)
try {
    db_exec("CREATE TABLE IF NOT EXISTS user_olahraga_favorit (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        nama VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        UNIQUE(user_id, nama)
    )");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS berat_kg NUMERIC(5,2)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tinggi_cm NUMERIC(5,2)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tanggal_lahir DATE");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS riwayat_penyakit TEXT");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='update_bio') {
        $bio = substr(trim($_POST['bio'] ?? ''), 0, 300);
        db_exec("UPDATE users SET bio=$1 WHERE id=$2", [$bio, (int)$u['id']]);
    } elseif ($a==='update_wa') {
        $wa = preg_replace('/[^0-9+]/','', trim($_POST['nomor_wa'] ?? ''));
        $jk = $_POST['jenis_kelamin'] ?? '';
        if (!in_array($jk, ['L','P',''], true)) $jk = '';
        if ($wa === '' || (strlen($wa) >= 8 && strlen($wa) <= 20)) {
            db_exec("UPDATE users SET nomor_wa=$1, jenis_kelamin=NULLIF($2,'') WHERE id=$3",
                [$wa ?: null, $jk, (int)$u['id']]);
        }
    } elseif ($a==='delete_wa') {
        db_exec("UPDATE users SET nomor_wa=NULL WHERE id=$1", [(int)$u['id']]);
    } elseif ($a==='mark_notif') {
        db_exec("UPDATE notifications SET dibaca=1 WHERE user_id=$1", [(int)$u['id']]);
    } elseif ($a==='fav_add') {
        $nama = trim(substr($_POST['nama'] ?? '', 0, 80));
        if ($nama !== '') {
            try { db_exec("INSERT INTO user_olahraga_favorit(user_id,nama) VALUES($1,$2) ON CONFLICT DO NOTHING", [(int)$u['id'], $nama]); } catch (Throwable $e) {}
        }
    } elseif ($a==='fav_edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim(substr($_POST['nama'] ?? '', 0, 80));
        if ($id && $nama !== '') {
            try { db_exec("UPDATE user_olahraga_favorit SET nama=$1 WHERE id=$2 AND user_id=$3", [$nama, $id, (int)$u['id']]); } catch (Throwable $e) {}
        }
    } elseif ($a==='fav_del') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) db_exec("DELETE FROM user_olahraga_favorit WHERE id=$1 AND user_id=$2", [$id, (int)$u['id']]);
    } elseif ($a==='update_kesehatan') {
        $berat = trim($_POST['berat_kg'] ?? '');
        $tinggi = trim($_POST['tinggi_cm'] ?? '');
        $tgl = trim($_POST['tanggal_lahir'] ?? '');
        $riwayat = substr(trim($_POST['riwayat_penyakit'] ?? ''), 0, 2000);
        $beratV = ($berat !== '' && is_numeric($berat)) ? (float)$berat : null;
        $tinggiV = ($tinggi !== '' && is_numeric($tinggi)) ? (float)$tinggi : null;
        $tglV = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : null;
        db_exec("UPDATE users SET berat_kg=$1, tinggi_cm=$2, tanggal_lahir=$3, riwayat_penyakit=$4 WHERE id=$5",
            [$beratV, $tinggiV, $tglV, $riwayat ?: null, (int)$u['id']]);
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

$favList = db_all("SELECT id, nama FROM user_olahraga_favorit WHERE user_id=$1 ORDER BY nama ASC", [(int)$u['id']]);

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
      <?php if(!empty($me['foto_url'])): ?>
        <img src="<?= htmlspecialchars($me['foto_url']) ?>" alt="" class="user-avatar zoomable" style="width:96px;height:96px;border-radius:50%;object-fit:cover;">
      <?php else: ?>
        <?= user_avatar(null, $me['nama'], 96) ?>
      <?php endif; ?>
      <h4 class="mt-2 mb-0"><?= htmlspecialchars($me['nama']) ?></h4>
      <div class="small text-muted"><?= htmlspecialchars($me['email']) ?></div>
      <div class="mt-2"><span class="pill">Level <?= $level ?></span>
        <span class="pill" data-bs-toggle="tooltip" title="Streak (mgg) = jumlah minggu berturut-turut Anda upload aktivitas atau hadir di sesi. Reset jika 1 minggu kosong.">🔥 <?= (int)$me['streak_minggu'] ?> minggu</span>
        <span class="pill">⭐ <?= $xp ?> XP</span></div>
      <div class="xp-bar mt-2"><div style="width:<?= min(100,$xpInLevel/2) ?>%"></div></div>
      <small class="text-muted">Butuh <?= $xpToNext ?> XP lagi ke Level <?= $level+1 ?></small>

      <form data-ajax method="post" enctype="multipart/form-data" class="mt-3 text-start">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_foto">
        <label class="form-label small fw-semibold">Ganti Foto Profil</label>
        <div class="input-group input-group-sm">
          <input type="file" name="foto" accept="image/*" class="form-control" data-compress required>
          <button class="btn btn-outline-primary"><i class="bi bi-upload"></i></button>
        </div>
        <div class="form-text compress-info">JPG/PNG/WebP · otomatis dikompresi</div>
      </form>


      <form data-ajax method="post" class="mt-3 text-start">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_bio">
        <label class="form-label small fw-semibold">Bio singkat</label>
        <textarea name="bio" class="form-control" rows="2" maxlength="300"><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
        <button class="btn btn-sm btn-primary mt-2">Simpan Bio</button>
      </form>

      <form data-ajax method="post" class="mt-3 text-start border-top pt-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_wa">
        <label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> Nomor WhatsApp</label>
        <div class="input-group input-group-sm">
          <input class="form-control" name="nomor_wa" maxlength="20" placeholder="cth: 081234567890" value="<?= htmlspecialchars($me['nomor_wa'] ?? '') ?>">
          <button class="btn btn-outline-primary" title="Simpan"><i class="bi bi-save"></i></button>
        </div>
        <label class="form-label small fw-semibold mt-2">Jenis Kelamin</label>
        <select name="jenis_kelamin" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— belum diisi —</option>
          <option value="L" <?= (($me['jenis_kelamin']??'')==='L'?'selected':'') ?>>Laki-laki</option>
          <option value="P" <?= (($me['jenis_kelamin']??'')==='P'?'selected':'') ?>>Perempuan</option>
        </select>
        <?php if(!empty($me['nomor_wa'])): ?>
          <div class="mt-2 d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-success" target="_blank" href="https://wa.me/<?= preg_replace('/^0/','62',preg_replace('/\D+/','',$me['nomor_wa'])) ?>"><i class="bi bi-whatsapp"></i> Chat saya</a>
            <button class="btn btn-sm btn-outline-danger" formaction="" type="submit" name="_action" value="delete_wa" onclick="return confirm('Hapus nomor WhatsApp?')"><i class="bi bi-trash"></i></button>
          </div>
        <?php endif; ?>
      </form>
    </div></div>

    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-bell"></i> Notifikasi
      <form data-ajax method="post" class="float-end"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="mark_notif">
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
    <?php
      $bmiP = null; $bmiCatP = '—';
      if ((float)$me['berat_kg']>0 && (float)$me['tinggi_cm']>0) {
        $hM = (float)$me['tinggi_cm']/100;
        if ($hM>0) {
          $bmiP = round((float)$me['berat_kg']/($hM*$hM),1);
          if ($bmiP<18.5) $bmiCatP='Kurus'; elseif ($bmiP<25) $bmiCatP='Normal'; elseif ($bmiP<30) $bmiCatP='Gemuk'; else $bmiCatP='Obesitas';
        }
      }
    ?>
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-heart-pulse text-danger"></i> Data Kesehatan (Publik) <a href="/kalkulator.php" class="btn btn-sm btn-outline-primary float-end"><i class="bi bi-calculator"></i> Kalkulator Sehat</a></div>
    <div class="card-body">
      <form data-ajax method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_kesehatan">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Berat Badan (kg)</label>
          <input type="number" step="0.1" min="20" max="300" name="berat_kg" class="form-control form-control-sm" value="<?= htmlspecialchars($me['berat_kg'] ?? '') ?>" placeholder="cth: 65.5">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Tinggi Badan (cm)</label>
          <input type="number" step="0.1" min="80" max="250" name="tinggi_cm" class="form-control form-control-sm" value="<?= htmlspecialchars($me['tinggi_cm'] ?? '') ?>" placeholder="cth: 170">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Tanggal Lahir</label>
          <input type="date" name="tanggal_lahir" class="form-control form-control-sm" value="<?= htmlspecialchars($me['tanggal_lahir'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Riwayat Penyakit</label>
          <textarea name="riwayat_penyakit" rows="3" maxlength="2000" class="form-control form-control-sm" placeholder="cth: Asma ringan, alergi seafood..."><?= htmlspecialchars($me['riwayat_penyakit'] ?? '') ?></textarea>
        </div>
        <div class="col-12 d-flex justify-content-between align-items-center">
          <div class="small text-muted">
            <?php if($bmiP !== null): ?>
              <strong>BMI: <?= $bmiP ?></strong> <span class="badge bg-<?= $bmiCatP==='Normal'?'success':($bmiCatP==='Kurus'?'warning':'danger') ?>"><?= $bmiCatP ?></span>
            <?php else: ?>
              Isi berat & tinggi untuk melihat BMI.
            <?php endif; ?>
          </div>
          <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan Data Kesehatan</button>
        </div>
      </form>
      <div class="form-text mt-2"><i class="bi bi-eye"></i> Data ini akan tampil publik di halaman profil Anda.</div>
    </div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-stars text-warning"></i> Achievement Profile</div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Hadir</div><div class="stat-value"><?= $statHadir ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Total Sesi</div><div class="stat-value"><?= $statSesi ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Jenis Olahraga</div><div class="stat-value"><?= $statOlahraga ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat" data-bs-toggle="tooltip" title="Streak (mgg) = jumlah minggu berturut-turut aktif (upload aktivitas atau hadir di sesi). Reset jika ada 1 minggu kosong."><div class="card-body"><div class="stat-label">Streak (mgg) <i class="bi bi-info-circle small text-muted"></i></div><div class="stat-value"><?= (int)$me['streak_minggu'] ?></div><div class="small text-muted" style="font-size:.7rem">Minggu aktif beruntun</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat"><div class="card-body"><div class="stat-label">Badge</div><div class="stat-value"><?= count($ownBadgeIds) ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card card-stat" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#favModal">
          <div class="card-body">
            <div class="stat-label d-flex justify-content-between">Olahraga Favorit <i class="bi bi-pencil-square text-primary"></i></div>
            <div class="stat-value" style="font-size:1rem;line-height:1.2">
              <?php if($favList): ?>
                <?php foreach(array_slice($favList,0,3) as $f): ?><span class="badge bg-primary-subtle text-primary me-1 mb-1"><?= htmlspecialchars($f['nama']) ?></span><?php endforeach; ?>
                <?php if(count($favList)>3): ?><span class="small text-muted">+<?= count($favList)-3 ?></span><?php endif; ?>
              <?php else: ?>
                <span class="small text-muted">Klik untuk tambah</span>
              <?php endif; ?>
            </div>
            <div class="small text-muted" style="font-size:.7rem">Tersering: <?= htmlspecialchars($favOlahraga) ?></div>
          </div>
        </div></div>
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
<!-- Modal: CRUD Olahraga Favorit -->
<div class="modal fade" id="favModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-heart-fill text-danger"></i> Olahraga Favorit Saya</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form data-ajax method="post" class="d-flex gap-2 mb-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="fav_add">
        <input name="nama" class="form-control" maxlength="80" placeholder="Tambah olahraga (mis. Lari, Futsal, Renang)..." required>
        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
      </form>
      <?php if($favList): ?>
      <ul class="list-group">
        <?php foreach($favList as $f): ?>
        <li class="list-group-item d-flex align-items-center gap-2">
          <form data-ajax method="post" class="d-flex gap-2 flex-grow-1">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="fav_edit">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <input name="nama" class="form-control form-control-sm" maxlength="80" value="<?= htmlspecialchars($f['nama']) ?>" required>
            <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-check2"></i></button>
          </form>
          <form data-ajax method="post" onsubmit="return confirm('Hapus olahraga ini?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="fav_del">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="text-muted small text-center mb-0">Belum ada olahraga favorit. Tambahkan di atas ya!</p>
      <?php endif; ?>
    </div>
  </div>
</div></div>

<?php include __DIR__.'/includes/footer.php'; ?>

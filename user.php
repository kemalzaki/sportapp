<?php
// Public profile (klik nama user)
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/migrations_v7.php';
send_security_headers(); enforce_session_timeout();
require_login(); // wajib login utk melihat profil user lain (revisi 30 Mei 2026)
$id = (int)($_GET['id'] ?? 0);

// ===== Idempotent migrasi tabel untuk fitur baru =====
try {
    // Kolom tambahan profil kesehatan
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nomor_wa VARCHAR(20)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS berat_kg NUMERIC(5,2)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tinggi_cm NUMERIC(5,2)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tanggal_lahir DATE");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS riwayat_penyakit TEXT");
    // Revisi 19 Juni 2026 Part Q
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS strava_account VARCHAR(120)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname VARCHAR(80)");
    // Tabel titip pesan (guestbook) — bisa di-reply
    db_exec("CREATE TABLE IF NOT EXISTS guest_messages (
        id SERIAL PRIMARY KEY,
        owner_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        sender_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        parent_id INTEGER REFERENCES guest_messages(id) ON DELETE CASCADE,
        pesan TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        updated_at TIMESTAMP
    )");
} catch (Throwable $e) {}

$user = db_one("SELECT id,nama,email,foto_url,xp,level,streak_minggu,bio,role,last_seen,nomor_wa,berat_kg,tinggi_cm,tanggal_lahir,riwayat_penyakit,strava_account,nickname FROM users WHERE id=$1", [$id]);
if (!$user) { http_response_code(404); die('User tidak ditemukan.'); }
$pageTitle = 'Profil '.$user['nama'];

// ===== CRUD Titip Pesan =====
$me = current_user();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    if (!$me) { header('Location: /login.php'); exit; }
    $act = $_POST['_action'] ?? '';
    $meId = (int)$me['id'];
    if ($act==='gm_add') {
        $pesan = trim(substr($_POST['pesan'] ?? '', 0, 1000));
        $pid = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($pesan !== '') {
            db_exec("INSERT INTO guest_messages(owner_user_id,sender_user_id,parent_id,pesan) VALUES($1,$2,$3,$4)", [$id, $meId, $pid, $pesan]);
            // === Push notification ke pemilik halaman (member aktif yang dititipi pesan)
            try {
                $cuplikan = mb_substr($pesan, 0, 120);
                $urlNotif = '/user.php?id='.(int)$id.'#titip-pesan';
                if ((int)$id !== $meId) {
                    notify((int)$id, 'titip_pesan',
                        '💌 Titip pesan baru dari '.$me['nama'],
                        $cuplikan, $urlNotif);
                }
                // Jika ini reply, kabari juga pengirim pesan induk
                if ($pid) {
                    $parentSender = (int) db_val("SELECT sender_user_id FROM guest_messages WHERE id=$1", [$pid]);
                    if ($parentSender && $parentSender !== $meId && $parentSender !== (int)$id) {
                        notify($parentSender, 'titip_pesan_reply',
                            '↩️ '.$me['nama'].' membalas pesanmu',
                            $cuplikan, $urlNotif);
                    }
                }
            } catch (Throwable $e) {}
        }
    } elseif ($act==='gm_edit') {
        $mid = (int)($_POST['id'] ?? 0);
        $pesan = trim(substr($_POST['pesan'] ?? '', 0, 1000));
        if ($mid && $pesan !== '') {
            db_exec("UPDATE guest_messages SET pesan=$1, updated_at=now() WHERE id=$2 AND sender_user_id=$3", [$pesan, $mid, $meId]);
        }
    } elseif ($act==='gm_del') {
        $mid = (int)($_POST['id'] ?? 0);
        if ($mid) {
            // Pengirim atau pemilik halaman boleh hapus
            db_exec("DELETE FROM guest_messages WHERE id=$1 AND (sender_user_id=$2 OR owner_user_id=$2)", [$mid, $meId]);
        }
    }
    header('Location: user.php?id='.$id); exit;
}

$badges = user_badges($id);
$hadir = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1 AND hadir=1", [$id]);
$sesi  = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1", [$id]);
$lastPosts = db_all("SELECT * FROM posts WHERE user_id=$1 AND jenis='post' ORDER BY created_at DESC LIMIT 6", [$id]);
// Favorit olahraga (bersifat publik)
$favList = [];
try {
    $favList = db_all("SELECT nama FROM user_olahraga_favorit WHERE user_id=$1 ORDER BY nama ASC", [$id]);
} catch (Throwable $e) { $favList = []; }
$favTopRow = db_one("SELECT j.jenis, COUNT(*) AS c FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1 GROUP BY j.jenis ORDER BY c DESC LIMIT 1", [$id]);
$favTop = $favTopRow['jenis'] ?? null;
// Performa Lari Mingguan (7 hari terakhir) — bersifat publik
$weeklyRuns = db_all("SELECT tanggal, durasi_menit, jarak_km, kalori, pace
                      FROM upload_harian
                      WHERE user_id=$1 AND tanggal >= CURRENT_DATE - INTERVAL '7 days'
                      ORDER BY tanggal ASC", [$id]);
$wkTotalKm    = 0; $wkTotalMin = 0; $wkTotalKcal = 0;
foreach ($weeklyRuns as $r) {
    $wkTotalKm   += (float)$r['jarak_km'];
    $wkTotalMin  += (int)$r['durasi_menit'];
    $wkTotalKcal += (int)$r['kalori'];
}

// Ambil daftar titip pesan (root) + replies
$gmRoots = db_all("SELECT g.*, u.nama AS sender_nama, u.foto_url AS sender_foto
                   FROM guest_messages g JOIN users u ON u.id=g.sender_user_id
                   WHERE g.owner_user_id=$1 AND g.parent_id IS NULL
                   ORDER BY g.created_at DESC LIMIT 200", [$id]);
$gmReplies = db_all("SELECT g.*, u.nama AS sender_nama, u.foto_url AS sender_foto
                     FROM guest_messages g JOIN users u ON u.id=g.sender_user_id
                     WHERE g.owner_user_id=$1 AND g.parent_id IS NOT NULL
                     ORDER BY g.created_at ASC", [$id]);
$gmByParent = [];
foreach ($gmReplies as $rep) { $gmByParent[(int)$rep['parent_id']][] = $rep; }

// ===== v7: data publik =====
$kondisiPub = db_one("SELECT status, keterangan, updated_at FROM user_kondisi WHERE user_id=$1", [$id]);
$pengPub = db_all("SELECT * FROM user_pengalaman WHERE user_id=$1 ORDER BY tanggal DESC NULLS LAST, id DESC LIMIT 50", [$id]);
$perlPub = db_all("SELECT * FROM user_perlengkapan WHERE user_id=$1 ORDER BY jenis_nama, nama", [$id]);

// ===== Helper kalkulasi sehat (BMI sederhana) =====
$bmi = null; $bmiCat = '—';
if ((float)$user['berat_kg'] > 0 && (float)$user['tinggi_cm'] > 0) {
    $hM = (float)$user['tinggi_cm'] / 100;
    if ($hM > 0) {
        $bmi = round((float)$user['berat_kg'] / ($hM*$hM), 1);
        if ($bmi < 18.5) $bmiCat = 'Kurus';
        elseif ($bmi < 25) $bmiCat = 'Normal';
        elseif ($bmi < 30) $bmiCat = 'Gemuk';
        else $bmiCat = 'Obesitas';
    }
}
$umur = null;
if (!empty($user['tanggal_lahir'])) {
    try { $umur = (new DateTime($user['tanggal_lahir']))->diff(new DateTime('today'))->y; } catch (Throwable $e) {}
}

include __DIR__.'/includes/header.php';
?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex gap-3 align-items-center">
  <?php $_avSrc = $user['foto_url'] ?? null; ?>
  <?php if($_avSrc): ?>
    <img src="<?= htmlspecialchars($_avSrc) ?>" alt="" class="user-avatar zoomable" style="width:88px;height:88px;border-radius:50%;object-fit:cover;">
  <?php else: ?>
    <?= user_avatar(null, $user['nama'], 88) ?>
  <?php endif; ?>
  <div class="flex-grow-1">
    <h4 class="mb-0"><?= htmlspecialchars($user['nama']) ?>
      <?php if(!empty($user['nickname'])): ?><span class="badge bg-info-subtle text-info border" title="Nickname"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($user['nickname']) ?></span><?php endif; ?>
      <span class="badge bg-light text-dark">Lv <?= (int)$user['level'] ?></span>
    </h4>
    <div class="text-muted small"><?= htmlspecialchars($user['role']) ?> · ⭐ <?= (int)$user['xp'] ?> XP · 🔥 <?= (int)$user['streak_minggu'] ?> minggu</div>
    <?php if(!empty($user['strava_account'])):
      $sv = trim($user['strava_account']);
      $sUrl = preg_match('~^https?://~i',$sv) ? $sv : 'https://www.strava.com/athletes/'.urlencode(ltrim($sv,'@'));
    ?>
      <div class="mt-1"><a class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener" href="<?= htmlspecialchars($sUrl) ?>"><i class="bi bi-bicycle"></i> Strava: <?= htmlspecialchars($sv) ?></a></div>
    <?php endif; ?>
    <?php if($user['bio']): ?><p class="mb-0 mt-1"><?= htmlspecialchars($user['bio']) ?></p><?php endif; ?>
    <?php if(!empty($user['nomor_wa'])):
        $waNum = preg_replace('/^0/','62', preg_replace('/\D+/','', $user['nomor_wa'])); ?>
      <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= htmlspecialchars($waNum) ?>"><i class="bi bi-whatsapp"></i> Chat WA Langsung</a>
        <span class="small text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($user['nomor_wa']) ?></span>
      </div>
    <?php endif; ?>
  </div>
  <div class="text-end">
    <div class="small text-muted">Hadir</div><div class="h5 mb-0"><?= $hadir ?>/<?= $sesi ?></div>
  </div>
</div></div>

<!-- Info kesehatan publik -->
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-heart-pulse text-danger"></i> Profil Kesehatan</div>
<div class="card-body">
  <div class="row g-2 mb-2">
    <div class="col-6 col-md-3"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Umur</div><div class="stat-value"><?= $umur !== null ? (int)$umur.' th' : '—' ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Berat</div><div class="stat-value"><?= $user['berat_kg'] ? htmlspecialchars($user['berat_kg']).' kg' : '—' ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Tinggi</div><div class="stat-value"><?= $user['tinggi_cm'] ? htmlspecialchars($user['tinggi_cm']).' cm' : '—' ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">BMI</div><div class="stat-value"><?= $bmi !== null ? $bmi.' <small class="text-muted" style="font-size:.7rem">('.$bmiCat.')</small>' : '—' ?></div></div></div></div>
  </div>
  <div class="small">
    <strong><i class="bi bi-clipboard2-pulse text-primary"></i> Riwayat Penyakit:</strong>
    <div class="text-muted mt-1" style="white-space:pre-wrap"><?= $user['riwayat_penyakit'] ? htmlspecialchars($user['riwayat_penyakit']) : '— belum diisi —' ?></div>
  </div>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-heart-fill text-danger"></i> Olahraga Favorit</div>
<div class="card-body">
  <?php if($favList || $favTop): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <?php foreach($favList as $f): ?>
        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-star-fill me-1"></i><?= htmlspecialchars($f['nama']) ?></span>
      <?php endforeach; ?>
      <?php if(!$favList): ?><span class="text-muted small">Belum mengisi olahraga favorit pilihan sendiri.</span><?php endif; ?>
    </div>
    <?php if($favTop): ?><div class="small text-muted"><i class="bi bi-graph-up"></i> Paling sering dihadiri: <strong><?= htmlspecialchars($favTop) ?></strong></div><?php endif; ?>
  <?php else: ?>
    <p class="text-muted small mb-0">Belum ada data favorit.</p>
  <?php endif; ?>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-speedometer2 text-success"></i> Performa Lari Mingguan (7 hari terakhir)</div>
<div class="card-body">
  <div class="row g-2 mb-2">
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Jarak</div><div class="stat-value"><?= number_format($wkTotalKm,2) ?> km</div></div></div></div>
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Durasi</div><div class="stat-value"><?= (int)$wkTotalMin ?> mnt</div></div></div></div>
    <div class="col-4"><div class="card card-stat text-center"><div class="card-body p-2"><div class="stat-label">Total Kalori</div><div class="stat-value"><?= number_format($wkTotalKcal) ?></div></div></div></div>
  </div>
  <?php if($weeklyRuns): ?>
  <div class="table-responsive"><table class="table table-sm mb-0" data-paginate="7">
    <thead><tr><th>Tanggal</th><th>Jarak (km)</th><th>Durasi</th><th>Pace</th><th>Kalori</th></tr></thead>
    <tbody>
    <?php foreach($weeklyRuns as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['tanggal']) ?></td>
        <td><?= htmlspecialchars($r['jarak_km'] ?? '0') ?></td>
        <td><?= (int)$r['durasi_menit'] ?> mnt</td>
        <td><?= htmlspecialchars($r['pace'] ?? '-') ?: '-' ?></td>
        <td><?= (int)$r['kalori'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?><p class="text-muted small text-center mb-0">Belum ada lari minggu ini.</p><?php endif; ?>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-award text-warning"></i> Badge (<?= count($badges) ?>)</div>
<div class="card-body"><div class="row g-2">
<?php foreach($badges as $b): ?>
  <div class="col-6 col-md-3"><div class="badge-tile">
    <i class="bi <?= htmlspecialchars($b['icon']) ?> text-<?= htmlspecialchars($b['warna']) ?>"></i>
    <div class="fw-semibold small mt-1"><?= htmlspecialchars($b['nama']) ?></div>
    <div class="text-muted small"><?= date('d M Y', strtotime($b['earned_at'])) ?></div>
  </div></div>
<?php endforeach; if(!$badges): ?><div class="col-12 text-muted small">Belum punya badge.</div><?php endif; ?>
</div></div></div>

<?php if($lastPosts): ?>
<!-- Revisi 13 Juni 2026: tampilan Postingan Terbaru dirapihkan (grid seragam, aspek 1:1, caption rapi). -->
<style>
.post-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
@media (max-width:575px){.post-grid{grid-template-columns:repeat(2,1fr)}}
.post-tile{position:relative;border-radius:10px;overflow:hidden;background:#f1f5f9;aspect-ratio:1/1;border:1px solid #e2e8f0;transition:transform .15s ease,box-shadow .15s ease}
.post-tile:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.08)}
.post-tile img{width:100%;height:100%;object-fit:cover;display:block}
.post-tile .post-overlay{position:absolute;left:0;right:0;bottom:0;padding:.4rem .55rem;color:#fff;font-size:.75rem;line-height:1.2;background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.65));display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-tile.no-img{background:linear-gradient(135deg,#e0f2fe,#fef9c3);display:flex;align-items:center;justify-content:center;padding:.6rem;text-align:center;color:#334155;font-size:.8rem}
.post-tile .post-date{position:absolute;top:.4rem;right:.4rem;background:rgba(15,23,42,.7);color:#fff;font-size:.65rem;padding:.1rem .4rem;border-radius:6px}
</style>
<div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between align-items-center">
  <span><i class="bi bi-images text-primary"></i> Postingan Terbaru</span>
  <span class="small text-muted"><?= count($lastPosts) ?> item</span>
</div>
<div class="card-body">
  <div class="post-grid">
    <?php foreach($lastPosts as $p):
      $cap = trim((string)($p['caption'] ?? ''));
      $tgl = !empty($p['created_at']) ? date('d M', strtotime($p['created_at'])) : '';
    ?>
      <?php if(!empty($p['foto_url'])): ?>
        <div class="post-tile">
          <img src="<?= htmlspecialchars($p['foto_url']) ?>" alt="" class="zoomable" loading="lazy">
          <?php if($tgl): ?><span class="post-date"><?= htmlspecialchars($tgl) ?></span><?php endif; ?>
          <?php if($cap !== ''): ?><div class="post-overlay"><?= htmlspecialchars(mb_substr($cap,0,120)) ?></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="post-tile no-img">
          <div>
            <?php if($tgl): ?><div class="small text-muted mb-1"><?= htmlspecialchars($tgl) ?></div><?php endif; ?>
            <?= htmlspecialchars(mb_substr($cap !== '' ? $cap : '(tanpa caption)',0,140)) ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div></div>
<?php endif; ?>

<!-- ===== Titip Pesan (Guestbook) ===== -->
<!-- ===== v7: Kondisi Terkini (publik) ===== -->
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-activity text-danger"></i> Kondisi Terkini</div>
<div class="card-body">
  <?php if($kondisiPub): $st=$kondisiPub['status']; ?>
    <span class="badge bg-<?= $st==='sehat'?'success':'danger' ?> me-2"><?= $st==='sehat'?'🟢 Sehat':'🔴 Sakit' ?></span>
    <?php if($st==='sakit' && $kondisiPub['keterangan']): ?><span class="small text-muted"><?= htmlspecialchars($kondisiPub['keterangan']) ?></span><?php endif; ?>
    <?php if($kondisiPub['updated_at']): ?><div class="small text-muted mt-1">Diperbarui: <?= date('d M Y H:i', strtotime($kondisiPub['updated_at'])) ?></div><?php endif; ?>
  <?php else: ?><span class="text-muted small">Belum mengisi kondisi.</span><?php endif; ?>
</div></div>

<!-- ===== v7: Pengalaman Hiking & Camping (publik) ===== -->
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-mountain text-success"></i> Pengalaman Hiking & Camping (<?= count($pengPub) ?>)</div>
<div class="card-body">
  <?php if(!$pengPub): ?><p class="text-muted small mb-0 text-center">Belum ada pengalaman.</p><?php else: ?>
  <div class="row g-2">
  <?php foreach($pengPub as $p): ?>
    <div class="col-md-6">
      <div class="border rounded p-2 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <span class="badge bg-<?= $p['jenis']==='hiking'?'success':'warning' ?>-subtle text-<?= $p['jenis']==='hiking'?'success':'warning' ?>"><i class="bi bi-<?= $p['jenis']==='hiking'?'signpost-split':'fire' ?>"></i> <?= htmlspecialchars($p['jenis']) ?></span>
          <?php if($p['tanggal']): ?><small class="text-muted"><?= htmlspecialchars($p['tanggal']) ?></small><?php endif; ?>
        </div>
        <div class="fw-semibold mt-1"><?= htmlspecialchars($p['judul']) ?></div>
        <?php if($p['lokasi']): ?><div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['lokasi']) ?></div><?php endif; ?>
        <?php if($p['foto_url']): ?><img src="<?= htmlspecialchars($p['foto_url']) ?>" class="img-fluid rounded mt-2 zoomable" style="max-height:180px" onerror="this.style.display='none'"><?php endif; ?>
        <?php if($p['deskripsi']): ?><div class="small mt-2" style="white-space:pre-wrap"><?= htmlspecialchars($p['deskripsi']) ?></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div></div>

<!-- ===== v7: Perlengkapan Olahraga (publik) ===== -->
<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-bag-check text-primary"></i> Perlengkapan Olahraga</div>
<div class="card-body">
  <?php if(!$perlPub): ?><p class="text-muted small mb-0 text-center">Belum ada perlengkapan tercatat.</p><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0" data-paginate="8">
    <thead><tr><th>Jenis</th><th>Perlengkapan</th><th class="text-end">Jumlah</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php foreach($perlPub as $p): ?>
      <tr>
        <td><span class="pill"><?= htmlspecialchars($p['jenis_nama']) ?></span></td>
        <td><?= htmlspecialchars($p['nama']) ?></td>
        <td class="text-end fw-semibold"><?= (int)$p['jumlah'] ?></td>
        <td class="small text-muted"><?= htmlspecialchars($p['catatan'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Otomatis terintegrasi ke jadwal sesuai jenis olahraga.</div>
</div></div>

<!-- ===== Titip Pesan (Guestbook) ===== -->
<div class="card shadow-sm mt-3" data-live="guestbook"><div class="card-header"><i class="bi bi-envelope-heart text-primary"></i> Titip Pesan untuk <?= htmlspecialchars($user['nama']) ?> <span class="badge bg-secondary"><?= count($gmRoots) ?></span></div>
<div class="card-body">
  <?php if($me): ?>
  <form data-ajax method="post" class="mb-3">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="gm_add">
    <textarea name="pesan" class="form-control" rows="2" maxlength="1000" placeholder="Tulis pesan untuk <?= htmlspecialchars($user['nama']) ?>..." required></textarea>
    <div class="text-end mt-2"><button class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Kirim Pesan</button></div>
  </form>
  <?php else: ?>
    <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle"></i> <a href="/login.php">Login</a> untuk titip pesan.</div>
  <?php endif; ?>

  <?php if(!$gmRoots): ?>
    <p class="text-muted small text-center mb-0">Belum ada pesan. Jadilah yang pertama!</p>
  <?php else: ?>
    <div class="list-group list-group-flush">
    <?php foreach($gmRoots as $g):
        $isMine = $me && (int)$me['id']===(int)$g['sender_user_id'];
        $isOwner = $me && (int)$me['id']===(int)$user['id'];
    ?>
      <div class="list-group-item px-0">
        <div class="d-flex gap-2">
          <?php if($g['sender_foto']): ?>
            <img src="<?= htmlspecialchars($g['sender_foto']) ?>" class="rounded-circle zoomable" style="width:38px;height:38px;object-fit:cover">
          <?php else: ?>
            <?= user_avatar(null, $g['sender_nama'], 38) ?>
          <?php endif; ?>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center">
              <div><a href="/user.php?id=<?= (int)$g['sender_user_id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($g['sender_nama']) ?></a>
                <small class="text-muted ms-2"><?= date('d M Y H:i', strtotime($g['created_at'])) ?><?= $g['updated_at']?' <em>(edited)</em>':'' ?></small></div>
              <div class="btn-group btn-group-sm">
                <?php if($me): ?><button class="btn btn-link btn-sm p-0 me-2" type="button" onclick="document.getElementById('gmReply<?= (int)$g['id'] ?>').classList.toggle('d-none')"><i class="bi bi-reply"></i> Reply</button><?php endif; ?>
                <?php if($isMine): ?><button class="btn btn-link btn-sm p-0 me-2 text-primary" type="button" onclick="document.getElementById('gmEdit<?= (int)$g['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button><?php endif; ?>
                <?php if($isMine || $isOwner): ?>
                <form data-ajax method="post" onsubmit="return confirm('Hapus pesan ini?')" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="gm_del">
                  <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                  <button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($g['pesan']) ?></div>

            <?php if($isMine): ?>
            <form data-ajax method="post" id="gmEdit<?= (int)$g['id'] ?>" class="d-none mt-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="gm_edit">
              <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
              <textarea name="pesan" rows="2" maxlength="1000" class="form-control form-control-sm" required><?= htmlspecialchars($g['pesan']) ?></textarea>
              <div class="text-end mt-1"><button class="btn btn-sm btn-primary">Simpan</button></div>
            </form>
            <?php endif; ?>

            <?php if($me): ?>
            <form data-ajax method="post" id="gmReply<?= (int)$g['id'] ?>" class="d-none mt-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="gm_add">
              <input type="hidden" name="parent_id" value="<?= (int)$g['id'] ?>">
              <textarea name="pesan" rows="2" maxlength="1000" class="form-control form-control-sm" placeholder="Balas pesan..." required></textarea>
              <div class="text-end mt-1"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-reply"></i> Balas</button></div>
            </form>
            <?php endif; ?>

            <?php $reps = $gmByParent[(int)$g['id']] ?? []; if($reps): ?>
              <div class="mt-2 ps-3 border-start">
              <?php foreach($reps as $rp):
                $isMineRp = $me && (int)$me['id']===(int)$rp['sender_user_id']; ?>
                <div class="d-flex gap-2 mt-2">
                  <?php if($rp['sender_foto']): ?>
                    <img src="<?= htmlspecialchars($rp['sender_foto']) ?>" class="rounded-circle zoomable" style="width:28px;height:28px;object-fit:cover">
                  <?php else: ?>
                    <?= user_avatar(null, $rp['sender_nama'], 28) ?>
                  <?php endif; ?>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <div><a href="/user.php?id=<?= (int)$rp['sender_user_id'] ?>" class="fw-semibold text-decoration-none small"><?= htmlspecialchars($rp['sender_nama']) ?></a>
                        <small class="text-muted ms-2"><?= date('d M H:i', strtotime($rp['created_at'])) ?><?= $rp['updated_at']?' <em>(edited)</em>':'' ?></small></div>
                      <div>
                        <?php if($isMineRp): ?><button class="btn btn-link btn-sm p-0 me-2 text-primary" type="button" onclick="document.getElementById('gmEdit<?= (int)$rp['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button><?php endif; ?>
                        <?php if($isMineRp || $isOwner): ?>
                        <form data-ajax method="post" onsubmit="return confirm('Hapus balasan?')" class="d-inline">
                          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                          <input type="hidden" name="_action" value="gm_del">
                          <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                          <button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="small" style="white-space:pre-wrap"><?= htmlspecialchars($rp['pesan']) ?></div>
                    <?php if($isMineRp): ?>
                    <form data-ajax method="post" id="gmEdit<?= (int)$rp['id'] ?>" class="d-none mt-1">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                      <input type="hidden" name="_action" value="gm_edit">
                      <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                      <textarea name="pesan" rows="2" maxlength="1000" class="form-control form-control-sm" required><?= htmlspecialchars($rp['pesan']) ?></textarea>
                      <div class="text-end mt-1"><button class="btn btn-sm btn-primary">Simpan</button></div>
                    </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></div>

<?php include __DIR__.'/includes/footer.php'; ?>

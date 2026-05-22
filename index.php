<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/badges.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Beranda';
$u = current_user();

// ---- Handle forum + social feed actions ----
if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    rate_limit_or_die('post:'.$u['id'], 30, 60);
    $a = $_POST['_action'] ?? '';
    if ($a === 'chat_post') {
        $pesan = trim($_POST['pesan'] ?? '');
        $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($pesan !== '') {
            $clean = sanitize_html($pesan);
            db_exec("INSERT INTO chat_forum(user_id,pesan,parent_id) VALUES($1,$2,$3)", [(int)$u['id'], $clean, $parent]);
            recompute_badges((int)$u['id']);
        }
    } elseif ($a === 'chat_delete' && $u['role']==='admin') {
        db_exec("DELETE FROM chat_forum WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a === 'chat_react') {
        $cid = (int)$_POST['chat_id']; $val = (int)$_POST['val'];
        if (!in_array($val, [-1,1], true)) $val = 1;
        // toggle: hapus dulu, lalu insert
        db_exec("DELETE FROM chat_reactions WHERE chat_id=$1 AND user_id=$2", [$cid, (int)$u['id']]);
        db_exec("INSERT INTO chat_reactions(chat_id,user_id,val) VALUES($1,$2,$3)", [$cid, (int)$u['id'], $val]);
    } elseif ($a === 'post_new') {
        $caption = substr(trim($_POST['caption'] ?? ''), 0, 500);
        $jenis = $_POST['jenis'] === 'story' ? 'story' : 'post';
        $fotoUrl = null;
        if (!empty($_FILES['foto']['name'])) {
            [$ok, $extOrErr] = validate_image_upload($_FILES['foto']);
            if ($ok) {
                $name = 'post_'.bin2hex(random_bytes(8)).'.'.$extOrErr;
                @mkdir(__DIR__.'/uploads', 0775, true);
                move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__.'/uploads/'.$name);
                $fotoUrl = '/uploads/'.$name;
            }
        }
        $exp = $jenis==='story' ? "now() + interval '24 hours'" : "NULL";
        db_exec("INSERT INTO posts(user_id,caption,foto_url,jenis,expired_at) VALUES($1,$2,$3,$4, $exp::timestamp)",
            [(int)$u['id'], htmlspecialchars($caption), $fotoUrl, $jenis]);
    } elseif ($a === 'like') {
        $pid = (int)$_POST['post_id'];
        try { db_exec("INSERT INTO post_likes(post_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING", [$pid, (int)$u['id']]); } catch (Throwable $e) {}
    } elseif ($a === 'comment') {
        $pid = (int)$_POST['post_id'];
        $isi = substr(trim($_POST['isi'] ?? ''), 0, 300);
        if ($isi !== '') db_exec("INSERT INTO post_comments(post_id,user_id,isi) VALUES($1,$2,$3)", [$pid, (int)$u['id'], htmlspecialchars($isi)]);
    }
    header('Location: /index.php#feed'); exit;
}

$totalSesi    = (int) db_val("SELECT COUNT(*) FROM jadwal");
$totalHadir   = (int) db_val("SELECT COUNT(*) FROM absensi WHERE hadir=1");
$totalMember  = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");

$jadwalTerdekat = db_all("SELECT j.*, u.nama AS koordinator, u.foto_url AS koord_foto, t.nama AS tim_nama
                          FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id LEFT JOIN tim t ON t.id=j.tim_id
                          WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 5");
$onlineMembers = db_all("SELECT id, nama, foto_url, last_seen FROM users
                         WHERE last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL '2 minutes' ORDER BY nama");

// Forum: ambil top-level + replies, plus aggregate like/dislike
$chats = db_all("SELECT c.*, u.nama, u.foto_url,
                   COALESCE((SELECT SUM(CASE WHEN val=1 THEN 1 ELSE 0 END) FROM chat_reactions r WHERE r.chat_id=c.id),0) AS likes,
                   COALESCE((SELECT SUM(CASE WHEN val=-1 THEN 1 ELSE 0 END) FROM chat_reactions r WHERE r.chat_id=c.id),0) AS dislikes
                 FROM chat_forum c LEFT JOIN users u ON u.id=c.user_id
                 ORDER BY c.created_at DESC LIMIT 60");
// kelompokkan reply per parent
$top = []; $replies = [];
foreach ($chats as $c) {
    if (empty($c['parent_id'])) $top[] = $c;
    else $replies[(int)$c['parent_id']][] = $c;
}

// Social feed
$stories = db_all("SELECT p.*, u.nama, u.foto_url AS user_foto FROM posts p JOIN users u ON u.id=p.user_id
                   WHERE p.jenis='story' AND (p.expired_at IS NULL OR p.expired_at > now())
                   ORDER BY p.created_at DESC LIMIT 20");
$uidMe = (int)($u['id'] ?? 0);
$feed = db_all("SELECT p.*, u.nama, u.foto_url,
                  (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS likes,
                  (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comments,
                  (SELECT COUNT(*) FROM post_likes pl2 WHERE pl2.post_id=p.id AND pl2.user_id=$1) AS liked_by_me
                FROM posts p JOIN users u ON u.id=p.user_id
                WHERE p.jenis='post' ORDER BY p.created_at DESC LIMIT 12", [$uidMe]);

$activeQr = db_all("SELECT q.token, j.id, j.tanggal, j.jenis, j.tempat
                    FROM qr_tokens q JOIN jadwal j ON j.id=q.jadwal_id
                    WHERE q.valid_until > now() AND q.valid_from <= now()
                    ORDER BY q.id DESC LIMIT 3");

include __DIR__.'/includes/header.php'; ?>

<section class="hero mb-3">
  <span class="badge-soft mb-2"><i class="bi bi-stars me-1"></i> Komunitas HapFam</span>
  <h1 class="h3 mb-1">Dashboard Olahraga Komunitas</h1>
  <p class="mb-0 text-muted">Check-in, kompetisi, dan komunitas dalam satu tempat.</p>
</section>

<?php if ($u): ?>
<div class="card shadow-sm mb-3 border-0" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;">
  <div class="card-body d-flex align-items-center gap-3 flex-wrap">
    <div style="font-size:2rem"><i class="bi bi-qr-code-scan"></i></div>
    <div class="flex-grow-1">
      <div class="fw-bold">QR Check-in Sesi Olahraga</div>
      <small>Scan QR di lokasi, sistem otomatis catat hadir + validasi GPS.</small>
    </div>
    <a href="/checkin.php" class="btn btn-light fw-semibold"><i class="bi bi-camera"></i> Scan QR</a>
  </div>
  <?php if($activeQr): ?>
  <div class="card-body pt-0">
    <div class="swipe-row">
      <?php foreach($activeQr as $aq): ?>
        <a href="/checkin.php" class="swipe-card text-decoration-none" style="background:#ffffff22;color:#fff;flex-basis:240px;">
          <div class="p-3">
            <div class="small opacity-75"><?= htmlspecialchars($aq['tanggal']) ?></div>
            <div class="fw-bold"><?= htmlspecialchars($aq['jenis']) ?></div>
            <div class="small"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($aq['tempat']) ?></div>
            <div class="mt-2"><span class="badge bg-light text-dark">QR aktif</span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
    <div class="stat-label">Total Sesi</div><div class="stat-value"><?= $totalSesi ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
    <div class="stat-label">Total Hadir</div><div class="stat-value"><?= $totalHadir ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
    <div class="stat-label">Member</div><div class="stat-value"><?= $totalMember ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card card-stat shadow-sm"><div class="card-body">
    <div class="stat-icon"><i class="bi bi-broadcast"></i></div>
    <div class="stat-label">Online</div><div class="stat-value"><?= count($onlineMembers) ?></div></div></div></div>
</div>

<?php if($u): ?>
<div class="card shadow-sm mb-3" id="feed"><div class="card-header d-flex justify-content-between">
  <span><i class="bi bi-collection-play text-primary"></i> Story Hari Ini</span>
  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#postModal"><i class="bi bi-plus-lg"></i> Posting</button>
</div>
<div class="card-body">
  <div class="story-strip">
    <?php foreach($stories as $s): ?>
      <div class="story-item">
        <div class="story-ring">
          <?php if ($s['foto_url'] || $s['caption']): ?>
            <img src="<?= htmlspecialchars($s['foto_url'] ?: '/assets/icon-192.png') ?>">
          <?php else: ?><div><?= htmlspecialchars(mb_substr($s['nama'],0,1)) ?></div><?php endif; ?>
        </div>
        <small><?= htmlspecialchars($s['nama']) ?></small>
      </div>
    <?php endforeach; if(!$stories): ?><div class="text-muted small">Belum ada story.</div><?php endif; ?>
  </div>
</div></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-calendar3 me-1 text-primary"></i> Jadwal Terdekat</div>
      <div class="table-responsive"><table class="table table-hover table-stack mb-0">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Tempat</th><th>Tim</th><th>Koordinator</th></tr></thead><tbody>
        <?php foreach($jadwalTerdekat as $j): ?>
          <tr>
            <td data-label="Tanggal"><?= htmlspecialchars($j['tanggal']) ?></td>
            <td data-label="Jenis"><span class="pill"><?= htmlspecialchars($j['jenis']) ?></span></td>
            <td data-label="Tempat"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($j['tempat']) ?></td>
            <td data-label="Tim"><?= htmlspecialchars($j['tim_nama'] ?? '—') ?></td>
            <td data-label="Koord"><a class="text-decoration-none" href="/user.php?id=<?= (int)$j['koordinator_id'] ?>"><?= user_name_with_avatar($j['koord_foto'] ?? null, $j['koordinator'] ?? '-', false, 24) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>

    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-images text-primary"></i> Social Feed</div>
    <div class="card-body">
      <?php foreach($feed as $p): ?>
        <div class="border-bottom pb-3 mb-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <a href="/user.php?id=<?= (int)$p['user_id'] ?>" class="text-decoration-none"><?= user_avatar($p['user_foto'] ?? null, $p['nama'], 32) ?></a>
            <a href="/user.php?id=<?= (int)$p['user_id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($p['nama']) ?></a>
            <small class="text-muted ms-auto"><?= date('d M H:i', strtotime($p['created_at'])) ?></small>
          </div>
          <?php if($p['foto_url']): ?><img src="<?= htmlspecialchars($p['foto_url']) ?>" class="img-fluid rounded mb-2" style="max-height:400px;"><?php endif; ?>
          <div class="mb-2"><?= nl2br(htmlspecialchars($p['caption'] ?? '')) ?></div>
          <div class="d-flex gap-2 small">
            <?php if($u): ?>
            <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="like"><input type="hidden" name="post_id" value="<?= $p['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-heart"></i> <?= (int)$p['likes'] ?></button></form>
            <?php endif; ?>
            <span class="text-muted align-self-center"><i class="bi bi-chat"></i> <?= (int)$p['comments'] ?></span>
          </div>
          <?php if($u): ?>
          <form method="post" class="d-flex gap-2 mt-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="comment"><input type="hidden" name="post_id" value="<?= $p['id'] ?>">
            <input class="form-control form-control-sm" name="isi" placeholder="Tulis komentar..." maxlength="300" required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; if(!$feed): ?><p class="text-muted small text-center mb-0">Belum ada postingan.</p><?php endif; ?>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-broadcast text-success me-1"></i> Online (<?= count($onlineMembers) ?>)</div>
      <ul class="list-group list-group-flush">
        <?php foreach($onlineMembers as $om): ?>
          <li class="list-group-item d-flex align-items-center justify-content-between">
            <a href="/user.php?id=<?= (int)$om['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($om['foto_url'] ?? null, $om['nama'], true, 28) ?></a>
            <small class="text-muted"><?= date('H:i', strtotime($om['last_seen'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ul></div>

    <div class="card shadow-sm" id="forum"><div class="card-header"><i class="bi bi-chat-square-text text-primary me-1"></i> Forum Komunitas</div>
    <div class="card-body">
      <?php if($u): ?>
      <form method="post" class="d-flex gap-2 mb-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_post">
        <input class="form-control" name="pesan" placeholder="Tulis pesan..." maxlength="500" required>
        <button class="btn btn-primary"><i class="bi bi-send"></i></button>
      </form>
      <?php endif; ?>
      <div style="max-height:520px;overflow-y:auto;">
      <?php foreach($top as $c):
        $rs = $replies[(int)$c['id']] ?? []; ?>
        <div class="chat-bubble">
          <div class="d-flex align-items-center gap-2 mb-1">
            <a href="/user.php?id=<?= (int)$c['user_id'] ?>" class="text-decoration-none"><?= user_avatar($c['foto_url'] ?? null, $c['nama'] ?? '?', 24) ?></a>
            <a href="/user.php?id=<?= (int)$c['user_id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($c['nama'] ?? 'Anon') ?></a>
            <small class="chat-meta"><?= date('d M H:i', strtotime($c['created_at'])) ?></small>
          </div>
          <div><?= sanitize_html($c['pesan']) ?></div>
          <div class="d-flex gap-2 mt-1 small">
            <?php if($u): ?>
            <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $c['id'] ?>"><input type="hidden" name="val" value="1">
              <button class="btn btn-sm btn-link text-success p-0 me-2"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$c['likes'] ?></button></form>
            <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $c['id'] ?>"><input type="hidden" name="val" value="-1">
              <button class="btn btn-sm btn-link text-danger p-0 me-2"><i class="bi bi-hand-thumbs-down"></i> <?= (int)$c['dislikes'] ?></button></form>
            <button type="button" class="btn btn-sm btn-link p-0" onclick="document.getElementById('reply<?= $c['id'] ?>').classList.toggle('d-none')"><i class="bi bi-reply"></i> Reply</button>
            <?php else: ?>
              <span class="text-muted"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$c['likes'] ?> · <i class="bi bi-hand-thumbs-down"></i> <?= (int)$c['dislikes'] ?></span>
            <?php endif; ?>
          </div>
          <?php if($u): ?>
          <form method="post" id="reply<?= $c['id'] ?>" class="d-flex gap-2 mt-2 d-none">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_post"><input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
            <input class="form-control form-control-sm" name="pesan" placeholder="Balas pesan..." maxlength="500" required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
          </form>
          <?php endif; ?>
          <?php foreach($rs as $rep): ?>
            <div class="chat-bubble chat-reply mt-2">
              <div class="d-flex align-items-center gap-2 mb-1">
                <?= user_avatar($rep['foto_url'] ?? null, $rep['nama'] ?? '?', 20) ?>
                <span class="fw-semibold small"><?= htmlspecialchars($rep['nama'] ?? 'Anon') ?></span>
                <small class="chat-meta"><?= date('d M H:i', strtotime($rep['created_at'])) ?></small>
              </div>
              <div class="small"><?= sanitize_html($rep['pesan']) ?></div>
              <?php if($u): ?>
              <div class="d-flex gap-2 mt-1 small">
                <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $rep['id'] ?>"><input type="hidden" name="val" value="1">
                  <button class="btn btn-sm btn-link text-success p-0 me-2"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$rep['likes'] ?></button></form>
                <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $rep['id'] ?>"><input type="hidden" name="val" value="-1">
                  <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-hand-thumbs-down"></i> <?= (int)$rep['dislikes'] ?></button></form>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; if(!$top): ?><p class="text-muted text-center mb-0 small">Belum ada pesan.</p><?php endif; ?>
      </div>
    </div></div>
  </div>
</div>

<?php if($u): ?>
<div class="modal fade" id="postModal" tabindex="-1"><div class="modal-dialog">
  <form class="modal-content" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post_new">
    <div class="modal-header"><h5 class="modal-title">Posting baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <label class="form-label small">Tipe</label>
      <select name="jenis" class="form-select mb-2"><option value="post">Post (feed)</option><option value="story">Story (24 jam)</option></select>
      <label class="form-label small">Foto (opsional)</label>
      <input type="file" name="foto" accept="image/*" class="form-control mb-2">
      <label class="form-label small">Caption</label>
      <textarea name="caption" class="form-control" rows="3" maxlength="500" placeholder="Tulis caption..."></textarea>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Posting</button></div>
  </form>
</div></div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>

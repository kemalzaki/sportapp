<?php
// Riwayat + Leaderboard + Riwayat Aktivitas — Revisi 11 Juni 2026
// Tambahan:
//  1. Monitoring Upload Harian: tampilkan member yang BELUM olahraga (upload) 1× selama 7 hari terakhir.
//  2. Kalender (2 buah):
//      - Kalender Aktivitas Publik: hari yang ada aktivitas dari siapapun, klik → daftar siapa yang olahraga.
//      - Kalender Aktivitas Saya: hari yang saya upload, klik → detail aktivitas saya hari itu.
//  3. Like, Comment, Share untuk Riwayat Aktivitas Publik.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Riwayat & Leaderboard';
$u = current_user();

/* ---------- Auto-migration: tabel like & comment untuk upload_harian ---------- */
try {
  db_exec("CREATE TABLE IF NOT EXISTS upload_harian_likes (
              upload_id INTEGER NOT NULL,
              user_id   INTEGER NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT now(),
              PRIMARY KEY (upload_id, user_id)
            )");
  db_exec("CREATE TABLE IF NOT EXISTS upload_harian_comments (
              id SERIAL PRIMARY KEY,
              upload_id INTEGER NOT NULL,
              user_id   INTEGER NOT NULL,
              isi       TEXT NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT now()
            )");
  db_exec("CREATE INDEX IF NOT EXISTS uhc_upload_idx ON upload_harian_comments(upload_id)");
} catch (Throwable $e) {}

/* ---------- AJAX endpoints (like / comment) ---------- */
if (($_GET['action'] ?? '') !== '' || ($_POST['action'] ?? '') !== '') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$u) { echo json_encode(['ok'=>false,'msg'=>'login dulu']); exit; }
  $act = $_POST['action'] ?? $_GET['action'] ?? '';
  $uid = (int)$u['id'];
  try {
    if ($act === 'like_toggle') {
      $upId = (int)($_POST['upload_id'] ?? 0);
      if ($upId<=0) throw new RuntimeException('upload_id invalid');
      $existing = db_one("SELECT 1 FROM upload_harian_likes WHERE upload_id=$1 AND user_id=$2",[$upId,$uid]);
      if ($existing) {
        db_exec("DELETE FROM upload_harian_likes WHERE upload_id=$1 AND user_id=$2",[$upId,$uid]);
        $liked = false;
      } else {
        db_exec("INSERT INTO upload_harian_likes(upload_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING",[$upId,$uid]);
        $liked = true;
      }
      $cnt = (int)db_val("SELECT COUNT(*) FROM upload_harian_likes WHERE upload_id=$1",[$upId]);
      echo json_encode(['ok'=>true,'liked'=>$liked,'count'=>$cnt]); exit;
    }
    if ($act === 'comment_add') {
      $upId = (int)($_POST['upload_id'] ?? 0);
      $isi  = trim((string)($_POST['isi'] ?? ''));
      if ($upId<=0 || $isi==='') throw new RuntimeException('input invalid');
      if (mb_strlen($isi) > 500) $isi = mb_substr($isi,0,500);
      db_exec("INSERT INTO upload_harian_comments(upload_id,user_id,isi) VALUES($1,$2,$3)",[$upId,$uid,$isi]);
      $rows = db_all("SELECT c.id, c.isi, c.created_at, u.nama, u.foto_url
                      FROM upload_harian_comments c JOIN users u ON u.id=c.user_id
                      WHERE c.upload_id=$1 ORDER BY c.created_at ASC",[$upId]);
      echo json_encode(['ok'=>true,'comments'=>$rows]); exit;
    }
    if ($act === 'comment_list') {
      $upId = (int)($_GET['upload_id'] ?? 0);
      $rows = db_all("SELECT c.id, c.isi, c.created_at, u.nama, u.foto_url
                      FROM upload_harian_comments c JOIN users u ON u.id=c.user_id
                      WHERE c.upload_id=$1 ORDER BY c.created_at ASC",[$upId]);
      echo json_encode(['ok'=>true,'comments'=>$rows]); exit;
    }
    if ($act === 'day_public_detail') {
      $d = $_GET['date'] ?? '';
      $rows = db_all("SELECT uh.id,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.kalori,uh.deskripsi,uh.file_path,
                             u.id AS uid,u.nama,u.foto_url
                      FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                      WHERE uh.tanggal=$1::date ORDER BY uh.id DESC",[$d]);
      echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }
    if ($act === 'day_mine_detail') {
      $d = $_GET['date'] ?? '';
      $rows = db_all("SELECT id,jenis,durasi_menit,jarak_km,kalori,deskripsi,file_path
                      FROM upload_harian WHERE user_id=$1 AND tanggal=$2::date ORDER BY id DESC",
                     [$uid,$d]);
      echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'unknown action']); exit;
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); exit;
  }
}

$period = $_GET['period'] ?? 'monthly';
$cat = $_GET['cat'] ?? 'konsisten';

$periodSql = "j.tanggal >= CURRENT_DATE - INTERVAL '30 days'";
if ($period === 'weekly') $periodSql = "j.tanggal >= CURRENT_DATE - INTERVAL '7 days'";
if ($period === 'all')    $periodSql = "TRUE";
$uPeriodSql = str_replace('j.tanggal','uh.tanggal',$periodSql);

$lb = [];
if ($cat === 'konsisten') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COUNT(*) AS skor
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id JOIN users u ON u.id=a.user_id
                  WHERE a.hadir=1 AND $periodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'jarak') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.jarak_km),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'pace') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, MIN(uh.pace_detik) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql AND uh.pace_detik IS NOT NULL
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor ASC LIMIT 20");
} elseif ($cat === 'kalori') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.kalori),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} else {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url,
                    (COUNT(DISTINCT j.jenis)*10 + COUNT(*)) AS skor
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id JOIN users u ON u.id=a.user_id
                  WHERE a.hadir=1 AND $periodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
}

$riwayat = db_all("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto,
                          (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id AND a.hadir=1) AS hadir,
                          (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id) AS total,
                          (SELECT COUNT(*) FROM member_eksternal me WHERE me.jadwal_id=j.id) AS tamu
                   FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id
                   ORDER BY j.tanggal DESC LIMIT 50");

$sesiDetail = [];
$jids = array_map(fn($r)=>(int)$r['id'], $riwayat);
if ($jids) {
    $inList = implode(',', $jids);
    $absRows = db_all("SELECT a.jadwal_id, a.hadir, a.keterangan, u.nama, u.foto_url
                       FROM absensi a JOIN users u ON u.id=a.user_id
                       WHERE a.jadwal_id IN ($inList) ORDER BY a.hadir DESC, u.nama");
    foreach ($absRows as $ar) $sesiDetail[(int)$ar['jadwal_id']]['anggota'][] = $ar;
    $tamuRows = db_all("SELECT jadwal_id, nama_tamu AS nama FROM member_eksternal WHERE jadwal_id IN ($inList)");
    foreach ($tamuRows as $tr) $sesiDetail[(int)$tr['jadwal_id']]['tamu'][] = $tr;
}

/* ---------- (1) Monitoring upload harian — yg BELUM olahraga 1× / 7 hari ---------- */
$belumOlahraga = db_all("
  SELECT u.id, u.nama, u.foto_url,
         (SELECT MAX(uh.tanggal) FROM upload_harian uh WHERE uh.user_id=u.id) AS terakhir
  FROM users u
  WHERE COALESCE(u.aktif,1)=1
    AND NOT EXISTS (
      SELECT 1 FROM upload_harian uh
       WHERE uh.user_id=u.id
         AND uh.tanggal >= CURRENT_DATE - INTERVAL '7 days'
    )
  ORDER BY terakhir NULLS FIRST, u.nama
  LIMIT 100
");

/* ---------- Aktivitas publik dengan like/comment count ---------- */
$publicActs = db_all("
  SELECT uh.id,uh.tanggal,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.kalori,uh.file_path,uh.deskripsi,
         u.id AS uid,u.nama,u.foto_url,
         (SELECT COUNT(*) FROM upload_harian_likes l WHERE l.upload_id=uh.id) AS like_count,
         (SELECT COUNT(*) FROM upload_harian_comments c WHERE c.upload_id=uh.id) AS comment_count,
         ".($u? "(SELECT 1 FROM upload_harian_likes l WHERE l.upload_id=uh.id AND l.user_id=".(int)$u['id'].") " : "NULL ")."AS liked
  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
  ORDER BY uh.tanggal DESC, uh.id DESC LIMIT 30");

$myActs = $u ? db_all("SELECT id,tanggal,jenis,durasi_menit,jarak_km,kalori,file_path,deskripsi
                       FROM upload_harian WHERE user_id=$1 ORDER BY tanggal DESC LIMIT 30", [(int)$u['id']]) : [];

/* ---------- (2) Data kalender (last 90 days) ---------- */
$publicDays = db_all("
  SELECT to_char(uh.tanggal,'YYYY-MM-DD') AS d, COUNT(*) AS n,
         COUNT(DISTINCT uh.user_id) AS users
  FROM upload_harian uh
  WHERE uh.tanggal >= CURRENT_DATE - INTERVAL '90 days'
  GROUP BY uh.tanggal ORDER BY uh.tanggal");
$myDays = $u ? db_all("
  SELECT to_char(tanggal,'YYYY-MM-DD') AS d, COUNT(*) AS n
  FROM upload_harian WHERE user_id=$1
    AND tanggal >= CURRENT_DATE - INTERVAL '90 days'
  GROUP BY tanggal ORDER BY tanggal", [(int)$u['id']]) : [];

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat & Leaderboard</h2>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2 align-items-end">
    <div class="col-md-3"><label class="small fw-semibold">Kategori</label>
      <select name="cat" class="form-select" onchange="this.form.submit()">
        <?php foreach(['konsisten'=>'Paling Konsisten','jarak'=>'Jarak Terbanyak','pace'=>'Pace Terbaik','kalori'=>'Kalori Terbanyak','all'=>'All Rounder'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $cat===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="small fw-semibold">Periode</label>
      <select name="period" class="form-select" onchange="this.form.submit()">
        <option value="weekly"  <?= $period==='weekly'?'selected':'' ?>>Mingguan</option>
        <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Bulanan</option>
        <option value="all"     <?= $period==='all'?'selected':'' ?>>All-time</option>
      </select></div>
  </form>
</div></div>

<!-- ====== (1) Monitoring upload harian — yang belum olahraga 1×/minggu ====== -->
<div class="card shadow-sm mb-3 border-warning">
  <div class="card-header bg-warning-subtle text-warning-emphasis d-flex justify-content-between align-items-center">
    <span><i class="bi bi-exclamation-octagon"></i> Monitoring Upload Harian — Belum olahraga 1× minggu ini</span>
    <span class="badge bg-warning text-dark"><?= count($belumOlahraga) ?> member</span>
  </div>
  <div class="card-body p-0">
    <?php if(!$belumOlahraga): ?>
      <div class="p-3 text-success small"><i class="bi bi-check-circle"></i> Semua member sudah upload aktivitas minimal 1× dalam 7 hari terakhir. 👏</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Nama</th><th>Terakhir Upload</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($belumOlahraga as $b): ?>
          <tr>
            <td><a href="/user.php?id=<?= (int)$b['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($b['foto_url']??null, $b['nama'], false, 24) ?></a></td>
            <td class="small"><?= $b['terakhir'] ? htmlspecialchars($b['terakhir']).' <span class="text-muted">('.(int)((time()-strtotime($b['terakhir']))/86400).' hari lalu)</span>' : '<span class="text-danger">Belum pernah</span>' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-warning" href="/dm.php?to=<?= (int)$b['id'] ?>"><i class="bi bi-chat-dots"></i> Ingatkan</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- ====== (2) Kalender Aktivitas ====== -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card shadow-sm h-100"><div class="card-header"><i class="bi bi-calendar2-week text-primary"></i> Kalender Aktivitas Publik</div>
      <div class="card-body" id="calPublicWrap"></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100"><div class="card-header"><i class="bi bi-calendar2-heart text-success"></i> Kalender Aktivitas Saya</div>
      <div class="card-body" id="calMineWrap"><?php if(!$u): ?><div class="text-muted small">Login dulu untuk melihat kalender pribadi.</div><?php endif; ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Leaderboard — <?= htmlspecialchars($cat) ?></div>
    <ol class="list-group list-group-flush list-group-numbered">
      <?php foreach($lb as $i=>$row): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <a href="/user.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none">
            <?= user_name_with_avatar($row['foto_url'] ?? null, $row['nama'], false, 28) ?>
          </a>
          <span class="badge bg-primary rounded-pill">
            <?php
              if ($cat==='jarak') echo number_format((float)$row['skor'],2).' km';
              elseif ($cat==='pace') { $s=(int)$row['skor']; echo sprintf('%d:%02d /km', intdiv($s,60), $s%60); }
              elseif ($cat==='kalori') echo number_format((int)$row['skor']).' kkal';
              else echo (int)$row['skor'];
            ?>
          </span>
        </li>
      <?php endforeach; if(!$lb): ?><li class="list-group-item text-muted text-center small">Belum ada data.</li><?php endif; ?>
    </ol></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-calendar3 text-primary"></i> Riwayat Sesi</div>
    <div class="table-responsive"><table class="table table-hover table-stack mb-0" data-paginate="5">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Tempat</th><th>Koordinator</th><th>Durasi</th><th>Tamu Eks.</th><th>Kehadiran</th></tr></thead>
      <tbody>
      <?php foreach($riwayat as $r): ?>
        <tr>
          <td data-label="Tanggal"><?= htmlspecialchars($r['tanggal']) ?> <span class="pill"><?= hari_id($r['tanggal']) ?></span></td>
          <td data-label="Jenis"><?= htmlspecialchars($r['jenis']) ?></td>
          <td data-label="Tempat"><?= htmlspecialchars($r['tempat']) ?></td>
          <td data-label="Koordinator"><?= user_name_with_avatar($r['koord_foto'] ?? null, $r['koord'] ?? '-', false, 22) ?></td>
          <td data-label="Durasi"><?= !empty($r['durasi_menit']) ? (int)$r['durasi_menit'].' mnt' : '<span class="text-muted small">—</span>' ?></td>
          <td data-label="Tamu"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'tamu')" class="badge bg-info-subtle text-info-emphasis text-decoration-none"><?= (int)$r['tamu'] ?> <i class="bi bi-zoom-in"></i></a></td>
          <td data-label="Hadir"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'anggota')" class="text-decoration-none"><?= (int)$r['hadir'] ?>/<?= (int)$r['total'] ?> <i class="bi bi-zoom-in text-muted small"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </div>

    <!-- ====== Riwayat Aktivitas Publik dengan Like/Comment/Share ====== -->
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-globe text-primary"></i> Riwayat Aktivitas Publik</div>
    <div class="list-group list-group-flush" id="publicFeed">
      <?php foreach($publicActs as $a): ?>
      <div class="list-group-item" data-up="<?= (int)$a['id'] ?>">
        <div class="d-flex gap-2">
          <div class="flex-shrink-0">
            <?php if(!empty($a['foto_url'])): ?>
              <img src="<?= htmlspecialchars($a['foto_url']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #eee">
            <?php else: ?>
              <div style="width:40px;height:40px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:600"><?= htmlspecialchars(mb_substr($a['nama'],0,1)) ?></div>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <a class="fw-semibold text-decoration-none" href="/user.php?id=<?= (int)$a['uid'] ?>"><?= htmlspecialchars($a['nama']) ?></a>
                <div class="small text-muted"><?= htmlspecialchars($a['tanggal']) ?> · <span class="pill"><?= htmlspecialchars($a['jenis']) ?></span> · <?= (int)$a['durasi_menit'] ?> mnt · <?= htmlspecialchars($a['jarak_km'] ?? '0') ?> km</div>
              </div>
              <?php if(!empty($a['file_path'])): ?>
                <a href="#" onclick="showBukti(event,'<?= htmlspecialchars($a['file_path'],ENT_QUOTES) ?>','<?= htmlspecialchars($a['tanggal']) ?>')">
                  <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="bukti" style="height:50px;width:50px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd">
                </a>
              <?php endif; ?>
            </div>
            <?php if(!empty($a['deskripsi'])): ?><div class="small mt-1"><?= nl2br(htmlspecialchars($a['deskripsi'])) ?></div><?php endif; ?>
            <div class="mt-2 d-flex gap-3 align-items-center small">
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none lcs-like <?= !empty($a['liked'])?'text-danger':'text-muted' ?>" onclick="toggleLike(<?= (int)$a['id'] ?>,this)">
                <i class="bi <?= !empty($a['liked'])?'bi-heart-fill':'bi-heart' ?>"></i>
                <span class="lcs-like-count"><?= (int)$a['like_count'] ?></span>
              </button>
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-muted" onclick="toggleComments(<?= (int)$a['id'] ?>)">
                <i class="bi bi-chat"></i> <span class="lcs-comment-count"><?= (int)$a['comment_count'] ?></span>
              </button>
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-muted" onclick="shareAct(<?= (int)$a['id'] ?>,<?= json_encode($a['nama']) ?>,<?= json_encode($a['jenis']) ?>)">
                <i class="bi bi-share"></i> Share
              </button>
            </div>
            <div class="lcs-comments mt-2" id="cmt-<?= (int)$a['id'] ?>" style="display:none">
              <div class="lcs-comment-list small"></div>
              <?php if($u): ?>
              <form class="d-flex gap-2 mt-2" onsubmit="return submitComment(event,<?= (int)$a['id'] ?>)">
                <input type="text" class="form-control form-control-sm" name="isi" maxlength="500" placeholder="Tulis komentar…" required>
                <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
              </form>
              <?php else: ?><div class="small text-muted">Login untuk berkomentar.</div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; if(!$publicActs): ?><div class="list-group-item text-center text-muted small py-3">Belum ada aktivitas.</div><?php endif; ?>
    </div>
    </div>

    <?php if($u): ?>
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity text-primary"></i> Riwayat Aktifitas Saya</div>
    <div class="table-responsive"><table class="table table-hover mb-0" data-paginate="5">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Durasi</th><th>Jarak</th><th>Kalori</th><th>Bukti</th></tr></thead>
      <tbody>
        <?php foreach($myActs as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['tanggal']) ?></td>
          <td><span class="pill"><?= htmlspecialchars($a['jenis']) ?></span></td>
          <td><?= (int)$a['durasi_menit'] ?> mnt</td>
          <td><?= htmlspecialchars($a['jarak_km'] ?? '0') ?> km</td>
          <td><?= (int)$a['kalori'] ?></td>
          <td>
            <?php if(!empty($a['file_path'])): ?>
              <a href="#" onclick="showBukti(event,'<?= htmlspecialchars($a['file_path'],ENT_QUOTES) ?>','<?= htmlspecialchars($a['tanggal']) ?>')">
                <img src="<?= htmlspecialchars($a['file_path']) ?>" style="height:38px;width:38px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd">
              </a>
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(!$myActs): ?><tr><td colspan="6" class="text-center text-muted small py-3">Belum ada aktivitas.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bukti modal -->
<div class="modal fade" id="buktiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-image"></i> Bukti Aktivitas <small id="bDate" class="text-muted ms-2"></small></h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center"><img id="bImg" src="" style="max-width:100%;border-radius:8px;"></div>
    <div class="modal-footer"><a id="bOpen" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka di tab baru</a></div>
  </div></div>
</div>

<!-- Sesi Detail modal -->
<div class="modal fade" id="sesiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar3"></i> Detail Sesi</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="sesiBody"></div>
  </div></div>
</div>

<!-- Day detail modal (calendar click) -->
<div class="modal fade" id="dayModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar-event"></i> <span id="dayTitle">Detail Hari</span></h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="dayBody">Memuat…</div>
  </div></div>
</div>

<script>
let _bModal=null;
function showBukti(ev, src, date){
  if(ev) ev.preventDefault();
  if(!_bModal) _bModal = new bootstrap.Modal(document.getElementById('buktiModal'));
  document.getElementById('bImg').src = src;
  document.getElementById('bOpen').href = src;
  document.getElementById('bDate').textContent = date || '';
  _bModal.show();
}

/* ===== Like / Comment / Share ===== */
function _post(data){
  const fd=new FormData(); Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  return fetch('riwayat.php', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
}
function toggleLike(id, btn){
  _post({action:'like_toggle',upload_id:id}).then(j=>{
    if(!j.ok){ alert(j.msg||'gagal'); return; }
    btn.classList.toggle('text-danger', j.liked);
    btn.classList.toggle('text-muted', !j.liked);
    btn.querySelector('i').className = 'bi ' + (j.liked?'bi-heart-fill':'bi-heart');
    btn.querySelector('.lcs-like-count').textContent = j.count;
  });
}
function toggleComments(id){
  const w = document.getElementById('cmt-'+id);
  if(w.style.display==='none'){
    w.style.display='block';
    fetch('riwayat.php?action=comment_list&upload_id='+id).then(r=>r.json()).then(j=>{
      if(j.ok) renderComments(id,j.comments);
    });
  } else { w.style.display='none'; }
}
function renderComments(id, list){
  const root = document.querySelector('#cmt-'+id+' .lcs-comment-list');
  if(!list || !list.length){ root.innerHTML='<div class="text-muted">Belum ada komentar.</div>'; return; }
  root.innerHTML = list.map(c=>{
    const ava = c.foto_url ? `<img src="${c.foto_url}" style="width:22px;height:22px;border-radius:50%;object-fit:cover;margin-right:6px">` : '';
    return `<div class="mb-1">${ava}<b>${escapeHtml(c.nama||'')}</b> <span class="text-muted small">${(c.created_at||'').substring(0,16)}</span><div class="ms-4">${escapeHtml(c.isi||'')}</div></div>`;
  }).join('');
  // update count
  const cnt = document.querySelector('[data-up="'+id+'"] .lcs-comment-count');
  if (cnt) cnt.textContent = list.length;
}
function submitComment(e,id){
  e.preventDefault();
  const f=e.target; const isi=f.isi.value.trim(); if(!isi) return false;
  _post({action:'comment_add',upload_id:id,isi:isi}).then(j=>{
    if(!j.ok){ alert(j.msg||'gagal'); return; }
    f.isi.value=''; renderComments(id,j.comments);
  });
  return false;
}
function shareAct(id, nama, jenis){
  const url = location.origin + '/riwayat.php#act-' + id;
  const text = `${nama} baru saja olahraga ${jenis}`;
  if (navigator.share) {
    navigator.share({title:'Aktivitas '+nama, text:text, url:url}).catch(()=>{});
  } else {
    navigator.clipboard.writeText(url).then(()=>alert('Link disalin: '+url));
  }
}
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }

/* ===== Sesi modal (existing) ===== */
<?php
$_sesiJs = [];
foreach($riwayat as $r){
  $jid = (int)$r['id'];
  $_sesiJs[$jid] = [
    'tanggal'=>$r['tanggal'],'jenis'=>$r['jenis'],'tempat'=>$r['tempat'],
    'koord'=>$r['koord']??'-','durasi'=>(int)$r['durasi_menit'],
    'hadir'=>(int)$r['hadir'],'total'=>(int)$r['total'],
    'anggota'=>$sesiDetail[$jid]['anggota']??[], 'tamu'=>$sesiDetail[$jid]['tamu']??[],
  ];
}
?>
const SESI_DATA = <?= json_encode($_sesiJs, JSON_UNESCAPED_UNICODE) ?>;
let _sModal=null;
function showSesi(id, focus){
  const d=SESI_DATA[id]; if(!d) return;
  if(!_sModal) _sModal=new bootstrap.Modal(document.getElementById('sesiModal'));
  let html=`<div class="mb-2"><span class="pill">${d.tanggal}</span> <span class="pill">${d.jenis}</span> <span class="pill">${d.tempat}</span></div>`;
  html+=`<div class="small text-muted mb-3">Koordinator: <b>${d.koord}</b> · Durasi: ${d.durasi||'-'} mnt · Hadir: <b>${d.hadir}/${d.total}</b></div>`;
  html+=`<h6 class="mb-2"><i class="bi bi-people"></i> Daftar Anggota Hadir</h6>`;
  if((d.anggota||[]).length){
    html+=`<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Nama</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>`;
    d.anggota.forEach(a=>{
      const ava=a.foto_url?`<img src="${a.foto_url}" style="width:26px;height:26px;border-radius:50%;object-fit:cover" class="me-1">`:'';
      const st=a.hadir==1?'<span class="badge bg-success">Hadir</span>':'<span class="badge bg-secondary">Tidak hadir</span>';
      html+=`<tr><td>${ava}${a.nama||'-'}</td><td>${st}</td><td class="small text-muted">${a.keterangan||''}</td></tr>`;
    });
    html+=`</tbody></table></div>`;
  } else html+=`<div class="text-muted small">Belum ada data absensi.</div>`;
  html+=`<h6 class="mt-3 mb-2"><i class="bi bi-person-plus"></i> Tamu Eksternal (${(d.tamu||[]).length})</h6>`;
  if((d.tamu||[]).length){
    html+=`<ul class="mb-0">`; d.tamu.forEach(t=>{html+=`<li>${t.nama||'-'}</li>`;}); html+=`</ul>`;
  } else html+=`<div class="text-muted small">Tidak ada tamu eksternal.</div>`;
  document.getElementById('sesiBody').innerHTML=html;
  _sModal.show();
}

/* ===== Mini Calendars (3 bulan terakhir) ===== */
const PUBLIC_DAYS = <?= json_encode(array_column($publicDays,null,'d'), JSON_UNESCAPED_UNICODE) ?>;
const MINE_DAYS   = <?= json_encode(array_column($myDays,null,'d'), JSON_UNESCAPED_UNICODE) ?>;
const HAS_USER    = <?= $u ? 'true':'false' ?>;
let _dayModal=null;

function buildCalendar(rootId, days, kind){
  const root = document.getElementById(rootId);
  if (!root) return;
  if (kind==='mine' && !HAS_USER) return;
  const now = new Date();
  const monthsToShow = 3;
  let html='';
  for (let m=monthsToShow-1; m>=0; m--){
    const ref = new Date(now.getFullYear(), now.getMonth()-m, 1);
    const Y = ref.getFullYear(), M = ref.getMonth();
    const monthName = ref.toLocaleDateString('id-ID',{month:'long',year:'numeric'});
    const firstDow = new Date(Y,M,1).getDay(); // 0=Sun
    const daysInMonth = new Date(Y,M+1,0).getDate();
    html += `<div class="mb-3"><div class="small fw-semibold mb-1">${monthName}</div>`;
    html += `<table class="table table-sm table-bordered text-center mb-0" style="font-size:.75rem"><thead><tr>`;
    ['M','S','S','R','K','J','S'].forEach(d=>html+=`<th>${d}</th>`);
    html+=`</tr></thead><tbody><tr>`;
    for(let i=0;i<firstDow;i++) html+='<td></td>';
    for(let d=1; d<=daysInMonth; d++){
      const ds = `${Y}-${String(M+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const hit = days[ds];
      const cls = hit ? (kind==='mine'?'bg-success-subtle text-success-emphasis fw-bold':'bg-primary-subtle text-primary-emphasis fw-bold') : '';
      const click = hit ? `onclick="openDay('${ds}','${kind}')" style="cursor:pointer"` : '';
      const badge = hit ? `<sup class="ms-1">${hit.n}</sup>` : '';
      html += `<td class="${cls}" ${click}>${d}${badge}</td>`;
      if ((firstDow + d) % 7 === 0 && d<daysInMonth) html+='</tr><tr>';
    }
    html += `</tr></tbody></table></div>`;
  }
  root.innerHTML = html;
}
function openDay(date, kind){
  if(!_dayModal) _dayModal = new bootstrap.Modal(document.getElementById('dayModal'));
  document.getElementById('dayTitle').textContent = (kind==='mine'?'Aktivitas Saya — ':'Aktivitas Publik — ')+date;
  document.getElementById('dayBody').innerHTML = '<div class="text-center text-muted small py-3">Memuat…</div>';
  _dayModal.show();
  const act = kind==='mine' ? 'day_mine_detail' : 'day_public_detail';
  fetch('riwayat.php?action='+act+'&date='+encodeURIComponent(date)).then(r=>r.json()).then(j=>{
    if(!j.ok){ document.getElementById('dayBody').innerHTML='<div class="text-danger">'+(j.msg||'gagal')+'</div>'; return; }
    if(!j.rows || !j.rows.length){ document.getElementById('dayBody').innerHTML='<div class="text-muted small">Tidak ada data.</div>'; return; }
    let html='<div class="list-group list-group-flush">';
    j.rows.forEach(r=>{
      const who = r.nama ? `<a href="/user.php?id=${r.uid}" class="text-decoration-none">${escapeHtml(r.nama)}</a> · ` : '';
      const img = r.file_path ? `<img src="${r.file_path}" style="width:42px;height:42px;border-radius:6px;object-fit:cover;border:1px solid #ddd" class="ms-2">` : '';
      html += `<div class="list-group-item d-flex justify-content-between align-items-start">
        <div><div class="small">${who}<span class="pill">${escapeHtml(r.jenis||'')}</span></div>
        <div class="small text-muted">${r.durasi_menit||0} mnt · ${r.jarak_km||0} km · ${r.kalori||0} kkal</div>
        ${r.deskripsi?('<div class="small mt-1">'+escapeHtml(r.deskripsi)+'</div>'):''}</div>${img}</div>`;
    });
    html+='</div>';
    document.getElementById('dayBody').innerHTML = html;
  });
}
buildCalendar('calPublicWrap', PUBLIC_DAYS, 'public');
buildCalendar('calMineWrap',   MINE_DAYS,   'mine');
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

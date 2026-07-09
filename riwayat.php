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
require_once __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/scope.php'; // Revisi R7 #5
send_security_headers(); enforce_session_timeout();
require_login();
$pageTitle = 'Riwayat & Leaderboard';
$u = current_user();
$__vids = scope_user_ids_sql_array();
/* Revisi Juli 2026 R10 — anggota komunitas 'SuperDuperAdmin' (non-superadmin)
   TIDAK boleh melihat: Monitoring Upload, Kalender Aktivitas Publik/Saya,
   Leaderboard, Tren Kehadiran Mingguan, Riwayat Sesi. Superadmin tetap boleh. */
$__hideSuper = scope_is_superduper_kom_member();

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
        // Revisi 13 Juni 2026: notifikasi ke pemilik postingan (mirip FB/IG).
        try {
          $owner = db_one("SELECT user_id FROM upload_harian WHERE id=$1",[$upId]);
          $oid = (int)($owner['user_id'] ?? 0);
          if ($oid && $oid !== $uid) {
            notify($oid, 'like_aktivitas',
              '❤️ '.$u['nama'].' menyukai aktivitasmu',
              'Klik untuk melihat postinganmu di Riwayat Aktivitas Publik.',
              '/riwayat.php#act-'.$upId);
          }
        } catch (Throwable $e) {}
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
      // Revisi 13 Juni 2026: notifikasi komentar ke pemilik postingan.
      try {
        $owner = db_one("SELECT user_id FROM upload_harian WHERE id=$1",[$upId]);
        $oid = (int)($owner['user_id'] ?? 0);
        if ($oid && $oid !== $uid) {
          notify($oid, 'komentar_aktivitas',
            '💬 '.$u['nama'].' mengomentari aktivitasmu',
            mb_substr($isi,0,120),
            '/riwayat.php#act-'.$upId);
        }
      } catch (Throwable $e) {}
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
      // Revisi Juli 2026 R11 — DIFILTER per komunitas (scope) supaya popup Aktivitas Publik
      // tidak menampilkan aktivitas member dari komunitas lain.
      $d = $_GET['date'] ?? '';
      $__vidsAjax = scope_user_ids_sql_array();
      $rows = db_all("SELECT uh.id,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.kalori,uh.deskripsi,uh.file_path,
                             u.id AS uid,u.nama,u.foto_url
                      FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                      WHERE uh.tanggal=$1::date AND uh.user_id = ANY($2::int[])
                      ORDER BY uh.id DESC",[$d, $__vidsAjax]);
      echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }
    if ($act === 'day_mine_detail') {
      $d = $_GET['date'] ?? '';
      $rows = db_all("SELECT id,jenis,durasi_menit,jarak_km,kalori,deskripsi,file_path
                      FROM upload_harian WHERE user_id=$1 AND tanggal=$2::date ORDER BY id DESC",
                     [$uid,$d]);
      echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }
    if ($act === 'ext_list') {
      // Revisi 26 Juni 2026 #2 — daftar member eksternal yang dibawa oleh user tertentu
      $tid = (int)($_GET['user_id'] ?? 0);
      $per = $_GET['period'] ?? 'all';
      $where = "me.dibawa_oleh_id=$1 AND COALESCE(TRIM(me.nama_tamu),'') <> ''";
      if ($per === 'weekly')  $where .= " AND j.tanggal >= CURRENT_DATE - INTERVAL '7 days'";
      if ($per === 'monthly') $where .= " AND j.tanggal >= CURRENT_DATE - INTERVAL '30 days'";
      try {
        $rows = db_all("SELECT TRIM(me.nama_tamu) AS nama,
                               COUNT(*) AS kali,
                               MAX(j.tanggal) AS terakhir
                        FROM member_eksternal me
                        JOIN jadwal j ON j.id=me.jadwal_id
                        WHERE $where
                        GROUP BY LOWER(TRIM(me.nama_tamu)), TRIM(me.nama_tamu)
                        ORDER BY kali DESC, terakhir DESC", [$tid]);
      } catch (Throwable $e) { $rows = []; }
      $owner = db_one("SELECT nama FROM users WHERE id=$1", [$tid]);
      echo json_encode(['ok'=>true,'owner'=>$owner['nama']??'-','rows'=>$rows]); exit;
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
// Revisi R7 #5 — scope komunitas: sisipkan filter user_id ke setiap leaderboard.
// Revisi Juli 2026 #8/#9 — pg_escape_string tanpa argumen koneksi = Deprecated (PHP 8.1+).
$__scopeFrag = " AND u.id = ANY('".pg_escape_string(db(), $__vids)."'::int[])";
$uPeriodSql .= $__scopeFrag;
$periodSql  .= $__scopeFrag;

$lb = [];
if ($cat === 'konsisten') {
    // Revisi: konsisten = jumlah hari unik upload aktivitas dalam periode
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COUNT(DISTINCT uh.tanggal) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url
                  HAVING COUNT(DISTINCT uh.tanggal) > 0
                  ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'jarak') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.jarak_km),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql AND uh.jarak_km IS NOT NULL
                  GROUP BY u.id,u.nama,u.foto_url
                  HAVING COALESCE(SUM(uh.jarak_km),0) > 0
                  ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'pace') {
    // Revisi 19 Juni 2026 Part Q — leaderboard Pace Terbaik
    // pace_detik banyak yang NULL pada data lama; fallback hitung dari durasi/jarak.
    $lb = db_all("SELECT u.id,u.nama,u.foto_url,
                         MIN( COALESCE(NULLIF(uh.pace_detik,0),
                              CASE WHEN uh.jarak_km>0 AND uh.durasi_menit>0
                                   THEN (uh.durasi_menit*60.0/uh.jarak_km)::int
                                   ELSE NULL END) ) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url
                  HAVING MIN( COALESCE(NULLIF(uh.pace_detik,0),
                              CASE WHEN uh.jarak_km>0 AND uh.durasi_menit>0
                                   THEN (uh.durasi_menit*60.0/uh.jarak_km)::int
                                   ELSE NULL END) ) BETWEEN 120 AND 900
                  ORDER BY skor ASC LIMIT 20");
} elseif ($cat === 'penggaet_eksternal') {
    // Revisi 24 Juni 2026 — Leaderboard "Penggaet Teman Eksternal Terbanyak":
    // Hitung berapa tamu eksternal (member_eksternal.nama_tamu DISTINCT) yang
    // dibawa oleh tiap user, terbatas pada periode jadwal terpilih.
    try {
      $lb = db_all("SELECT u.id, u.nama, u.foto_url,
                           COUNT(DISTINCT LOWER(TRIM(me.nama_tamu))) AS skor
                    FROM member_eksternal me
                    JOIN users u ON u.id = me.dibawa_oleh_id
                    JOIN jadwal j ON j.id = me.jadwal_id
                    WHERE $periodSql AND me.dibawa_oleh_id IS NOT NULL
                      AND COALESCE(TRIM(me.nama_tamu),'') <> ''
                    GROUP BY u.id, u.nama, u.foto_url
                    HAVING COUNT(DISTINCT LOWER(TRIM(me.nama_tamu))) > 0
                    ORDER BY skor DESC LIMIT 20");
    } catch (Throwable $e) { $lb = []; }
} elseif ($cat === 'kalori') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.kalori),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url
                  HAVING COALESCE(SUM(uh.kalori),0) > 0
                  ORDER BY skor DESC LIMIT 20");
} else {
    // All Rounder: skor gabungan dari variasi jenis + hari aktif + total jarak + kalori
    $lb = db_all("SELECT u.id,u.nama,u.foto_url,
                    (COUNT(DISTINCT uh.jenis)*20
                     + COUNT(DISTINCT uh.tanggal)*5
                     + COALESCE(SUM(uh.jarak_km),0)
                     + COALESCE(SUM(uh.kalori),0)/100.0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url
                  HAVING COUNT(*) > 0
                  ORDER BY skor DESC LIMIT 20");
}

/* Revisi Juli 2026 #3 — Riwayat Sesi difilter per komunitas: hanya jadwal
   yang komunitas_id-nya termasuk dalam scope user login. */
$__vkids = scope_kom_ids_sql_array();
// Revisi R9 Juli 2026 — Riwayat Sesi strict per komunitas (drop NULL fallback).
$__jadwalScopeSql = scope_is_super() ? '' : ' WHERE (j.komunitas_id = ANY($1::int[]))';
$__jadwalScopeParams = scope_is_super() ? [] : [$__vkids];
$riwayat = db_all("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto,
                          jj.nama AS jj_nama, jj.warna_bg AS jj_bg, jj.warna_text AS jj_text,
                          (SELECT COUNT(DISTINCT a.user_id) FROM absensi a WHERE a.jadwal_id=j.id AND a.hadir=1) AS hadir,
                          (SELECT COUNT(DISTINCT a.user_id) FROM absensi a WHERE a.jadwal_id=j.id AND a.status='telat') AS telat,
                          (SELECT COUNT(DISTINCT a.user_id) FROM absensi a WHERE a.jadwal_id=j.id) AS total,
                          (SELECT COUNT(DISTINCT me.nama_tamu) FROM member_eksternal me WHERE me.jadwal_id=j.id) AS tamu
                   FROM jadwal j
                   LEFT JOIN users u ON u.id=j.koordinator_id
                   LEFT JOIN jenis_jadwal jj ON jj.id=j.jenis_jadwal_id
                   $__jadwalScopeSql
                   ORDER BY j.tanggal DESC LIMIT 50", $__jadwalScopeParams);


$sesiDetail = [];
$jids = array_map(fn($r)=>(int)$r['id'], $riwayat);
if ($jids) {
    $inList = implode(',', $jids);
    // Revisi 22 Juni 2026 R5 — DISTINCT ON (jadwal_id,user_id) supaya nama tidak
    // double saat data absensi punya baris ganda untuk satu jadwal+user (data lama).
    // Revisi Juli 2026 #3 — Detail Sesi (anggota) DIFILTER per komunitas:
    // hanya user yang berada di scope komunitas user login.
    $__scopeCsv = implode(',', array_map('intval', scope_visible_user_ids())) ?: '0';
    try {
      $absRows = db_all("SELECT DISTINCT ON (a.jadwal_id, a.user_id)
                                a.jadwal_id, a.hadir, a.status, a.keterangan, u.id AS uid, u.nama, u.foto_url
                         FROM absensi a JOIN users u ON u.id=a.user_id
                         WHERE a.jadwal_id IN ($inList)
                           AND u.id IN ($__scopeCsv)
                         ORDER BY a.jadwal_id, a.user_id, a.id DESC");
    } catch (Throwable $e) {
      $absRows = db_all("SELECT DISTINCT ON (a.jadwal_id, a.user_id)
                                a.jadwal_id, a.hadir, a.keterangan, u.id AS uid, u.nama, u.foto_url
                         FROM absensi a JOIN users u ON u.id=a.user_id
                         WHERE a.jadwal_id IN ($inList)
                           AND u.id IN ($__scopeCsv)
                         ORDER BY a.jadwal_id, a.user_id, a.id DESC");
    }

    // Sortir akhir di PHP supaya tetap urut hadir-dulu lalu nama
    usort($absRows, function($a,$b){
        if ($a['jadwal_id'] != $b['jadwal_id']) return $a['jadwal_id'] <=> $b['jadwal_id'];
        $ha = (int)($a['hadir'] ?? 0); $hb = (int)($b['hadir'] ?? 0);
        if ($ha !== $hb) return $hb <=> $ha;
        return strcmp((string)$a['nama'], (string)$b['nama']);
    });
    foreach ($absRows as $ar) $sesiDetail[(int)$ar['jadwal_id']]['anggota'][] = $ar;
    $tamuRows = db_all("SELECT DISTINCT ON (jadwal_id, nama_tamu) jadwal_id, nama_tamu AS nama
                        FROM member_eksternal WHERE jadwal_id IN ($inList)
                        ORDER BY jadwal_id, nama_tamu, id DESC");
    foreach ($tamuRows as $tr) $sesiDetail[(int)$tr['jadwal_id']]['tamu'][] = $tr;
}

/* ---------- (1) Monitoring upload harian — yg BELUM olahraga 1× / 7 hari ----------
   Revisi Juli 2026 #3 — DIFILTER per komunitas (scope). */
$belumOlahraga = db_all("
  SELECT u.id, u.nama, u.foto_url, u.nomor_wa,
         (SELECT MAX(uh.tanggal) FROM upload_harian uh WHERE uh.user_id=u.id) AS terakhir
  FROM users u
  WHERE TRUE
    AND u.id = ANY($1::int[])
    AND NOT EXISTS (
      SELECT 1 FROM upload_harian uh
       WHERE uh.user_id=u.id
         AND uh.tanggal >= CURRENT_DATE - INTERVAL '7 days'
    )
  ORDER BY terakhir NULLS FIRST, u.nama
  LIMIT 100
", [$__vids]);

/* ---------- Aktivitas publik dengan like/comment count ----------
   Revisi Juli 2026 #3 — Riwayat Aktivitas Publik DIFILTER per komunitas. */
/* Revisi Nov 2026 R12 — tambah tanggal upload (created_at), pace, kalori, komunitas nama. */
$publicActs = db_all("
  SELECT uh.id,uh.tanggal,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.kalori,uh.file_path,uh.deskripsi,
         uh.pace, uh.pace_detik,
         COALESCE(uh.created_at, uh.tanggal::timestamp) AS uploaded_at,
         u.id AS uid,u.nama,u.foto_url,
         k.nama AS kom_nama,
         (SELECT COUNT(*) FROM upload_harian_likes l WHERE l.upload_id=uh.id) AS like_count,
         (SELECT COUNT(*) FROM upload_harian_comments c WHERE c.upload_id=uh.id) AS comment_count,
         ".($u? "(SELECT 1 FROM upload_harian_likes l WHERE l.upload_id=uh.id AND l.user_id=".(int)$u['id'].") " : "NULL ")."AS liked
  FROM upload_harian uh
  JOIN users u ON u.id=uh.user_id
  LEFT JOIN komunitas k ON k.id = u.komunitas_id
  WHERE u.id = ANY($1::int[])
  ORDER BY uh.tanggal DESC, uh.id DESC LIMIT 30", [$__vids]);

/* Revisi Nov 2026 R11 — Ambil daftar gear "Sepatu" per user dari user_perlengkapan
   untuk ditampilkan sebagai keterangan di Riwayat Aktivitas Publik. */
$sepatuByUid = [];
try {
  $uidsAct = array_values(array_unique(array_map(function($r){ return (int)$r['uid']; }, $publicActs)));
  if ($uidsAct) {
    $rowsSp = db_all(
      "SELECT user_id, nama, jenis_nama, jumlah, catatan
       FROM user_perlengkapan
       WHERE user_id = ANY(\$1::int[])
         AND (LOWER(nama) LIKE '%sepatu%' OR LOWER(COALESCE(catatan,'')) LIKE '%sepatu%')
       ORDER BY id DESC",
      ['{'.implode(',', array_map('intval', $uidsAct)).'}']
    );
    foreach ($rowsSp as $rs) {
      $uidX = (int)$rs['user_id'];
      if (!isset($sepatuByUid[$uidX])) $sepatuByUid[$uidX] = [];
      $sepatuByUid[$uidX][] = $rs;
    }
  }
} catch (Throwable $e) { $sepatuByUid = []; }




$myActs = $u ? db_all("SELECT id,tanggal,jenis,durasi_menit,jarak_km,kalori,file_path,deskripsi
                       FROM upload_harian WHERE user_id=$1 ORDER BY tanggal DESC LIMIT 30", [(int)$u['id']]) : [];

/* ---------- (2) Data kalender (last 90 days) ---------- */
// Revisi Juli 2026 R8 #1 — Kalender Aktivitas Publik DIFILTER per komunitas.
$publicDays = db_all("
  SELECT to_char(uh.tanggal,'YYYY-MM-DD') AS d, COUNT(*) AS n,
         COUNT(DISTINCT uh.user_id) AS users
  FROM upload_harian uh
  WHERE uh.tanggal >= CURRENT_DATE - INTERVAL '90 days'
    AND uh.user_id = ANY(\$1::int[])
  GROUP BY uh.tanggal ORDER BY uh.tanggal", [$__vids]);
$myDays = $u ? db_all("
  SELECT to_char(tanggal,'YYYY-MM-DD') AS d, COUNT(*) AS n
  FROM upload_harian WHERE user_id=$1
    AND tanggal >= CURRENT_DATE - INTERVAL '90 days'
  GROUP BY tanggal ORDER BY tanggal", [(int)$u['id']]) : [];

/* Revisi 24 Juni 2026 — Tren Kehadiran Mingguan SEMUA ANGGOTA (dipindah dari monitoring.php). */
$wkAllLabels = []; $wkAllVals = [];
try {
  // Revisi Juli 2026 R8 #1 — Tren Kehadiran Mingguan DIFILTER per komunitas.
  // Revisi Juli 2026 R11 — Tren Kehadiran Mingguan difilter per KEGIATAN (jadwal)
  // dari komunitas user login, sehingga tidak lagi menghitung kehadiran pada
  // kegiatan komunitas lain (walau usernya kebetulan tergabung di sana).
  $__vkidsTren = scope_kom_ids_sql_array();
  $__trenKomFilter = scope_is_super() ? '' : ' AND (j.komunitas_id = ANY($2::int[]))';
  $__trenParams = scope_is_super() ? [$__vids] : [$__vids, $__vkidsTren];
  $wkRows = db_all("SELECT to_char(date_trunc('week', j.tanggal), 'IYYY-\"W\"IW') AS wk, COUNT(*) AS c
                    FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                    WHERE a.hadir=1 AND j.tanggal >= CURRENT_DATE - INTERVAL '12 weeks'
                      AND a.user_id = ANY(\$1::int[])
                      $__trenKomFilter
                    GROUP BY 1 ORDER BY 1", $__trenParams);
  foreach ($wkRows as $r) { $wkAllLabels[] = $r['wk']; $wkAllVals[] = (int)$r['c']; }
} catch (Throwable $e) {}

/* Revisi 22 Juni 2026 R12 — AJAX endpoint: return hanya fragmen leaderboard
   sehingga filter Kategori & Periode tidak perlu reload halaman penuh. */
if (!empty($_GET['ajax_lb'])) {
    header('Content-Type: text/html; charset=utf-8'); ?>
    <div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Leaderboard — <?= htmlspecialchars($cat) ?></div>
    <ol class="list-group list-group-flush list-group-numbered" id="lbList" data-lb-page-size="5">
      <?php foreach($lb as $i=>$row): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <a href="/user.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none">
            <?= user_name_with_avatar($row['foto_url'] ?? null, $row['nama'], false, 28) ?>
          </a>
          <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary rounded-pill">
              <?php
                if ($cat==='jarak') echo number_format((float)$row['skor'],2).' km';
                elseif ($cat==='pace') { $s=(int)$row['skor']; echo sprintf('%d:%02d /km', intdiv($s,60), $s%60); }
                elseif ($cat==='kalori') echo number_format((int)$row['skor']).' kkal';
                elseif ($cat==='penggaet_eksternal') echo (int)$row['skor'].' teman';
                else echo (int)$row['skor'];
              ?>
            </span>
            <?php if ($cat==='penggaet_eksternal'): /* Revisi 26 Juni 2026 #2 */ ?>
              <button type="button" class="btn btn-sm btn-outline-info"
                      onclick="showEksternal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama']), ENT_QUOTES) ?>')"
                      title="Lihat siapa saja member eksternal yang dibawa">
                <i class="bi bi-people"></i> Eksternal
              </button>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; if(!$lb): ?><li class="list-group-item text-muted text-center small">Belum ada data.</li><?php endif; ?>
    </ol>
    <?php if(!empty($lb) && count($lb) > 5): ?>
    <div class="card-footer d-flex justify-content-between align-items-center" id="lbPager">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="lbPrev"><i class="bi bi-chevron-left"></i> Sebelumnya</button>
      <span class="small text-muted" id="lbPageInfo">Halaman 1</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="lbNext">Berikutnya <i class="bi bi-chevron-right"></i></button>
    </div>
    <?php endif; ?>
    <?php exit;
}

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat & Leaderboard</h2>

<div class="card shadow-sm mb-3"><div class="card-body">
  <!-- Revisi 22 Juni 2026 R12 — Filter kategori & periode pakai AJAX (tidak reload halaman) -->
  <form class="row g-2 align-items-end" id="lbFilterForm" onsubmit="return false">
    <div class="col-md-3"><label class="small fw-semibold">Kategori</label>
      <select name="cat" id="lbCat" class="form-select">
        <?php foreach(['konsisten'=>'Paling Konsisten','jarak'=>'Jarak Terbanyak','pace'=>'Pace Terbaik','kalori'=>'Kalori Terbanyak','all'=>'All Rounder','penggaet_eksternal'=>'Penggaet Teman Eksternal Terbanyak'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $cat===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="small fw-semibold">Periode</label>
      <select name="period" id="lbPeriod" class="form-select">
        <option value="weekly"  <?= $period==='weekly'?'selected':'' ?>>Mingguan</option>
        <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Bulanan</option>
        <option value="all"     <?= $period==='all'?'selected':'' ?>>All-time</option>
      </select></div>
    <div class="col-md-2"><span id="lbAjaxStat" class="small text-muted"></span></div>
  </form>
</div></div>

<?php if(!$__hideSuper): /* R10 — hide dari komunitas SuperDuperAdmin */ ?>
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
      <div class="table-responsive"><table class="table table-sm mb-0 align-middle" data-paginate="5">
        <thead class="table-light"><tr><th>Nama</th><th>Terakhir Upload</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($belumOlahraga as $b): ?>
          <tr>
            <td><a href="/user.php?id=<?= (int)$b['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($b['foto_url']??null, $b['nama'], false, 24) ?></a></td>
            <td class="small"><?= $b['terakhir'] ? htmlspecialchars($b['terakhir']).' <span class="text-muted">('.(int)((time()-strtotime($b['terakhir']))/86400).' hari lalu)</span>' : '<span class="text-danger">Belum pernah</span>' ?></td>
            <td class="text-end">
              <?php
                $waRaw = preg_replace('/\D+/','', (string)($b['nomor_wa'] ?? ''));
                if ($waRaw !== '') {
                    if (str_starts_with($waRaw,'0')) $waRaw = '62'.substr($waRaw,1);
                    elseif (!str_starts_with($waRaw,'62')) $waRaw = '62'.$waRaw;
                    $msg = rawurlencode('Halo '.($b['nama']??'').", yuk olahraga lagi! Kamu belum upload aktivitas minggu ini di KawanKeringat.");
                    echo '<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/'.htmlspecialchars($waRaw).'?text='.$msg.'"><i class="bi bi-whatsapp"></i> Ingatkan</a>';
                } else {
                    echo '<a class="btn btn-sm btn-outline-secondary" href="/dm.php?to='.(int)$b['id'].'" title="Nomor WA belum diisi, kirim via DM"><i class="bi bi-chat-dots"></i> Ingatkan</a>';
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<!-- ====== (2) Kalender Aktivitas — per bulan, dengan pilihan bulan ====== -->

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar2-week text-primary"></i> Kalender Aktivitas Publik</span>
        <div class="d-flex gap-1 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="shiftCal('public',-1)" title="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></button>
          <select id="calPublicMonth" class="form-select form-select-sm" style="width:auto"></select>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="shiftCal('public',1)" title="Bulan berikutnya"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
      <div class="card-body" id="calPublicWrap"></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar2-heart text-success"></i> Kalender Aktivitas Saya</span>
        <?php if($u): ?>
        <div class="d-flex gap-1 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="shiftCal('mine',-1)" title="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></button>
          <select id="calMineMonth" class="form-select form-select-sm" style="width:auto"></select>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="shiftCal('mine',1)" title="Bulan berikutnya"><i class="bi bi-chevron-right"></i></button>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-body" id="calMineWrap"><?php if(!$u): ?><div class="text-muted small">Login dulu untuk melihat kalender pribadi.</div><?php endif; ?></div>
    </div>
  </div>
</div>
<?php endif; /* R10 hide superduper — Monitoring + Kalender */ ?>

<div class="row g-3">
  <div class="col-lg-5">
    <?php if(!$__hideSuper): /* R10 — sembunyikan Leaderboard */ ?>
    <!-- Revisi Nov 2026 R12 — spacing ke widget bawah (Monitoring Upload) + pagination per 5 -->
    <div class="card shadow-sm mb-4" id="lbCard" style="margin-bottom:1.5rem !important;"><div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Leaderboard — <?= htmlspecialchars($cat) ?></div>
    <ol class="list-group list-group-flush list-group-numbered" id="lbList" data-lb-page-size="5">
      <?php foreach($lb as $i=>$row): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <a href="/user.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none">
            <?= user_name_with_avatar($row['foto_url'] ?? null, $row['nama'], false, 28) ?>
          </a>
          <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary rounded-pill">
              <?php
                if ($cat==='jarak') echo number_format((float)$row['skor'],2).' km';
                elseif ($cat==='pace') { $s=(int)$row['skor']; echo sprintf('%d:%02d /km', intdiv($s,60), $s%60); }
                elseif ($cat==='kalori') echo number_format((int)$row['skor']).' kkal';
                elseif ($cat==='penggaet_eksternal') echo (int)$row['skor'].' teman';
                else echo (int)$row['skor'];
              ?>
            </span>
            <?php if ($cat==='penggaet_eksternal'): /* Revisi 26 Juni 2026 #2 */ ?>
              <button type="button" class="btn btn-sm btn-outline-info"
                      onclick="showEksternal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama']), ENT_QUOTES) ?>')"
                      title="Lihat member eksternal yang dibawa">
                <i class="bi bi-people"></i> Eksternal
              </button>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; if(!$lb): ?><li class="list-group-item text-muted text-center small">Belum ada data.</li><?php endif; ?>
    </ol>
    <?php if(!empty($lb) && count($lb) > 5): ?>
    <div class="card-footer d-flex justify-content-between align-items-center" id="lbPager">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="lbPrev"><i class="bi bi-chevron-left"></i> Sebelumnya</button>
      <span class="small text-muted" id="lbPageInfo">Halaman 1</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="lbNext">Berikutnya <i class="bi bi-chevron-right"></i></button>
    </div>
    <?php endif; ?>
    </div>
    <?php endif; /* R10 endif Leaderboard */ ?>
  </div>

  <div class="col-lg-7">
    <?php if(!$__hideSuper): /* R10 — sembunyikan Tren Kehadiran + Riwayat Sesi */ ?>
    <!-- Revisi 26 Juni 2026 #4 — Tren Kehadiran Mingguan dipindah ke ATAS Riwayat Sesi -->
    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-people text-primary"></i> Tren Kehadiran Mingguan — Semua Anggota</div>
      <div class="card-body">
        <canvas id="rwAllAttendChart" height="140"></canvas>
        <small class="text-muted d-block mt-2">Total kehadiran semua anggota per minggu (12 minggu terakhir).</small>
      </div>
    </div>
    <script>
    (function(){
      var labels = <?= json_encode($wkAllLabels ?: []) ?>;
      var vals   = <?= json_encode($wkAllVals ?: []) ?>;
      function draw(){
        if (typeof Chart === 'undefined') { return setTimeout(draw, 250); }
        var el = document.getElementById('rwAllAttendChart'); if(!el) return;
        new Chart(el, {
          type:'line',
          data:{ labels: labels.length? labels:['—'], datasets:[{ label:'Total hadir', data: vals.length? vals:[0], tension:.3, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.15)', fill:true }]},
          options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true, ticks:{precision:0} } } }
        });
      }
      draw();
    })();
    </script>

    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-calendar3 text-primary"></i> Riwayat Sesi</div>
    <div class="table-responsive"><table class="table table-hover table-stack mb-0" data-paginate="5">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Jenis Kegiatan</th><th>Tempat</th><th>Koordinator</th><th>Durasi</th><th>Tamu Eks.</th><th>Kehadiran</th></tr></thead>
      <tbody>
      <?php foreach($riwayat as $r): ?>
        <tr>
          <td data-label="Tanggal"><?= htmlspecialchars($r['tanggal']) ?> <span class="pill"><?= hari_id($r['tanggal']) ?></span></td>
          <td data-label="Jenis"><?= htmlspecialchars($r['jenis']) ?></td>
          <td data-label="Jenis Kegiatan">
            <?php if(!empty($r['jj_nama'])): ?>
              <!-- Revisi — Tim Kantor KK / Tim Public KK (warna BG dari tabel jenis_jadwal) -->
              <span class="badge" style="background:<?= htmlspecialchars($r['jj_bg'] ?? '#0ea5e9') ?>;color:<?= htmlspecialchars($r['jj_text'] ?? '#ffffff') ?>"><?= htmlspecialchars($r['jj_nama']) ?></span>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td data-label="Tempat"><?= htmlspecialchars($r['tempat']) ?></td>
          <td data-label="Koordinator"><?= user_name_with_avatar($r['koord_foto'] ?? null, $r['koord'] ?? '-', false, 22) ?></td>
          <td data-label="Durasi"><?= !empty($r['durasi_menit']) ? (int)$r['durasi_menit'].' mnt' : '<span class="text-muted small">—</span>' ?></td>
          <td data-label="Tamu"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'tamu')" class="badge bg-info-subtle text-info-emphasis text-decoration-none"><?= (int)$r['tamu'] ?> <i class="bi bi-zoom-in"></i></a></td>
          <td data-label="Hadir"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'anggota')" class="text-decoration-none">
            <span class="badge bg-success-subtle text-success" title="Hadir">H <?= (int)$r['hadir'] ?></span>
            <?php if((int)$r['telat']>0): ?><span class="badge bg-warning text-dark" title="Telat">T <?= (int)$r['telat'] ?></span><?php endif; ?>
            <span class="text-muted small">/<?= (int)$r['total'] ?></span>
            <i class="bi bi-zoom-in text-muted small"></i>
          </a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php endif; /* R10 endif Tren+Riwayat Sesi */ ?>

    <!-- ====== Riwayat Aktivitas Publik dengan Like/Comment/Share ====== -->
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-globe text-primary"></i> Riwayat Aktivitas Publik</div>
    <div class="list-group list-group-flush" id="publicFeed" data-paginate-list="5">
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
                <?php
                  /* Revisi Nov 2026 R12 — Tanggal Upload, Pace, Kalori, Komunitas */
                  $uploadAt = !empty($a['uploaded_at']) ? date('d M Y H:i', strtotime($a['uploaded_at'])) : '-';
                  $paceTxt  = '';
                  if (!empty($a['pace'])) {
                      $__pv = trim($a['pace']);
                      $paceTxt = (stripos($__pv, '/km') !== false) ? $__pv : ($__pv.' /km');
                  } elseif (!empty($a['pace_detik']) && (int)$a['pace_detik']>0) {
                      $ps = (int)$a['pace_detik']; $paceTxt = sprintf('%d:%02d /km', intdiv($ps,60), $ps%60);
                  } elseif (!empty($a['durasi_menit']) && !empty($a['jarak_km']) && (float)$a['jarak_km']>0) {
                      $ps = (int) round(((int)$a['durasi_menit']*60) / (float)$a['jarak_km']);
                      $paceTxt = sprintf('%d:%02d /km', intdiv($ps,60), $ps%60);
                  } else { $paceTxt = '—'; }
                  $kalTxt   = !empty($a['kalori']) ? number_format((int)$a['kalori']).' kkal' : '—';
                  $komTxt   = !empty($a['kom_nama']) ? $a['kom_nama'] : 'Umum';
                ?>
                <div class="small text-muted mt-1">
                  <span class="me-2"><i class="bi bi-clock-history"></i> <b>Upload:</b> <?= htmlspecialchars($uploadAt) ?></span>
                  <span class="me-2"><i class="bi bi-speedometer2"></i> <b>Pace:</b> <?= htmlspecialchars($paceTxt) ?></span>
                  <span class="me-2"><i class="bi bi-fire text-danger"></i> <b>Kalori:</b> <?= htmlspecialchars($kalTxt) ?></span>
                  <span class="me-2"><i class="bi bi-people-fill text-primary"></i> <b>Komunitas:</b> <?= htmlspecialchars($komTxt) ?></span>
                </div>
              </div>
              <?php if(!empty($a['file_path'])): ?>
                <a href="#" onclick="showBukti(event,this.dataset.src,this.dataset.date)" data-src="<?= htmlspecialchars($a['file_path'],ENT_QUOTES) ?>" data-date="<?= htmlspecialchars($a['tanggal']) ?>">
                  <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="bukti" style="height:50px;width:50px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd">
                </a>
              <?php endif; ?>
            </div>
            <?php if(!empty($a['deskripsi'])): ?><div class="small mt-1"><?= nl2br(htmlspecialchars($a['deskripsi'])) ?></div><?php endif; ?>
            <?php /* Revisi Nov 2026 R11 — Keterangan Gear Sepatu milik user pada tiap kartu aktivitas publik. */
              $__sp = $sepatuByUid[(int)$a['uid']] ?? [];
              if ($__sp): ?>
              <div class="small mt-1 text-muted">
                <i class="bi bi-boot"></i> <strong>Gear Sepatu:</strong>
                <?php foreach($__sp as $ii=>$sp): ?>
                  <span class="badge bg-light text-dark border me-1"><i class="bi bi-tag"></i>
                    <?= htmlspecialchars($sp['nama']) ?><?= !empty($sp['jenis_nama']) ? ' · '.htmlspecialchars($sp['jenis_nama']) : '' ?>
                    <?= ((int)$sp['jumlah']>1) ? ' ('.(int)$sp['jumlah'].')' : '' ?>
                    <?php if(!empty($sp['catatan'])): ?><em class="text-muted"> — <?= htmlspecialchars($sp['catatan']) ?></em><?php endif; ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="mt-2 d-flex gap-3 align-items-center small">
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none lcs-like <?= !empty($a['liked'])?'text-danger':'text-muted' ?>" onclick="toggleLike(<?= (int)$a['id'] ?>,this)">
                <i class="bi <?= !empty($a['liked'])?'bi-heart-fill':'bi-heart' ?>"></i>
                <span class="lcs-like-count"><?= (int)$a['like_count'] ?></span>
              </button>
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-muted" onclick="toggleComments(<?= (int)$a['id'] ?>)">
                <i class="bi bi-chat"></i> <span class="lcs-comment-count"><?= (int)$a['comment_count'] ?></span>
              </button>
              <?php
                // Revisi 17 Juni 2026 — Share WA direct anchor (samakan dengan tombol "Ingatkan")
                $shareUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'http://').$_SERVER['HTTP_HOST'].'/riwayat.php#act-'.(int)$a['id'];
                $shareMsg = ($a['nama'] ?? 'Member').' baru saja olahraga '.($a['jenis'] ?? '').' 💪'."\nLihat aktivitasnya: ".$shareUrl;
              ?>
              <a href="https://wa.me/?text=<?= rawurlencode($shareMsg) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-link p-0 text-decoration-none text-success">
                <i class="bi bi-whatsapp"></i> Share WA
              </a>
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
    <div class="modal-body text-center" style="overflow:auto"><img id="bImg" src="" style="max-width:100%;border-radius:8px;cursor:zoom-in;transition:transform .2s ease;transform-origin:center center;" onclick="toggleZoom(this)"></div>
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
// Revisi 13 Juni 2026: keyframes spinner ringan untuk tombol like.
(function(){ if(!document.getElementById('riwSpinCss')){
  const s=document.createElement('style'); s.id='riwSpinCss';
  s.textContent='@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
}})();
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
  // Revisi 13 Juni 2026: loading state mirip social feed di index.php
  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';
  const ico = btn.querySelector('i');
  const origCls = ico.className;
  ico.className = 'bi bi-arrow-clockwise';
  ico.style.animation = 'spin 1s linear infinite';
  _post({action:'like_toggle',upload_id:id}).then(j=>{
    if(!j.ok){ alert(j.msg||'gagal'); ico.className = origCls; ico.style.animation=''; btn.dataset.busy='0'; return; }
    btn.classList.toggle('text-danger', j.liked);
    btn.classList.toggle('text-muted', !j.liked);
    ico.className = 'bi ' + (j.liked?'bi-heart-fill':'bi-heart');
    ico.style.animation='';
    btn.querySelector('.lcs-like-count').textContent = j.count;
    btn.dataset.busy = '0';
  }).catch(()=>{ ico.className = origCls; ico.style.animation=''; btn.dataset.busy='0'; });
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
  const btn = f.querySelector('button[type=submit],button:not([type])');
  const orig = btn ? btn.innerHTML : '';
  if (btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
  _post({action:'comment_add',upload_id:id,isi:isi}).then(j=>{
    if(!j.ok){ alert(j.msg||'gagal'); }
    else { f.isi.value=''; renderComments(id,j.comments); }
  }).finally(()=>{ if(btn){ btn.disabled=false; btn.innerHTML=orig; } });
  return false;
}
function shareAct(id, nama, jenis){
  // Revisi 14 Juni 2026: arahkan klik ke WhatsApp Web/App. window.open dulu
  // (desktop) lalu fallback location.href (mobile yang memblok popup).
  const url = location.origin + '/riwayat.php#act-' + id;
  const text = `${nama} baru saja olahraga ${jenis} 💪\nLihat aktivitasnya: ${url}`;
  const wa = 'https://wa.me/?text=' + encodeURIComponent(text);
  const win = window.open(wa, '_blank', 'noopener');
  if (!win || win.closed || typeof win.closed === 'undefined') {
    location.href = wa;
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
      // Revisi 13 Juni 2026: jika telat -> tampilkan "Telat" (bukan "Hadir").
      let st;
      const s = (a.status||'').toLowerCase();
      if (s === 'telat')      st = '<span class="badge bg-warning text-dark">Telat</span>';
      else if (s === 'izin')  st = '<span class="badge bg-info">Izin</span>';
      else if (s === 'sakit') st = '<span class="badge bg-secondary">Sakit</span>';
      else if (a.hadir == 1)  st = '<span class="badge bg-success">Hadir</span>';
      else                    st = '<span class="badge bg-secondary">Tidak hadir</span>';
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

function openDay(dateStr, kind){
  if(!_dayModal) _dayModal = new bootstrap.Modal(document.getElementById('dayModal'));
  document.getElementById('dayTitle').textContent =
    (kind==='mine' ? 'Aktivitas Saya · ' : 'Aktivitas Publik · ') + dateStr;
  const body = document.getElementById('dayBody');
  body.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-arrow-clockwise"></i> Memuat…</div>';
  _dayModal.show();
  const act = kind==='mine' ? 'day_mine_detail' : 'day_public_detail';
  fetch('riwayat.php?action='+act+'&date='+encodeURIComponent(dateStr))
    .then(r=>r.json()).then(j=>{
      if(!j.ok){ body.innerHTML = '<div class="text-danger small">'+(j.msg||'Gagal memuat')+'</div>'; return; }
      if(!j.rows || !j.rows.length){ body.innerHTML = '<div class="text-muted small">Tidak ada aktivitas pada tanggal ini.</div>'; return; }
      let html = '<div class="list-group list-group-flush">';
      j.rows.forEach(r=>{
        const ava = (kind!=='mine' && r.foto_url) ? `<img src="${r.foto_url}" style="width:32px;height:32px;border-radius:50%;object-fit:cover" class="me-2">` : '';
        const who = (kind!=='mine') ? `<a class="fw-semibold text-decoration-none" href="/user.php?id=${r.uid}">${escapeHtml(r.nama||'')}</a>` : '';
        const img = r.file_path ? `<a href="#" onclick="showBukti(event,'${(r.file_path||'').replace(/'/g,"\\'")}','${dateStr}')"><img src="${r.file_path}" style="height:56px;width:56px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd"></a>` : '';
        const meta = `<span class="pill">${escapeHtml(r.jenis||'-')}</span> · ${parseInt(r.durasi_menit||0)} mnt · ${r.jarak_km||0} km · ${parseInt(r.kalori||0)} kkal`;
        html += `<div class="list-group-item d-flex gap-2">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center mb-1">${ava}${who}</div>
            <div class="small text-muted">${meta}</div>
            ${r.deskripsi ? '<div class="small mt-1">'+escapeHtml(r.deskripsi)+'</div>' : ''}
          </div>
          <div class="flex-shrink-0">${img}</div>
        </div>`;
      });
      html += '</div>';
      body.innerHTML = html;
    }).catch(e=>{ body.innerHTML = '<div class="text-danger small">Gagal memuat: '+e+'</div>'; });
}

/* state per-kind: {year, month0} */
const CAL_STATE = { public:null, mine:null };
function _fmtMonth(y,m){
  return new Date(y,m,1).toLocaleDateString('id-ID',{month:'long',year:'numeric'});
}
function _fillMonthOptions(selId, kind){
  const sel = document.getElementById(selId); if(!sel) return;
  const now = new Date();
  // 24 bulan: 23 ke belakang + bulan ini
  let html = '';
  for (let i=0; i<24; i++){
    const ref = new Date(now.getFullYear(), now.getMonth()-i, 1);
    const v = `${ref.getFullYear()}-${String(ref.getMonth()+1).padStart(2,'0')}`;
    html += `<option value="${v}">${_fmtMonth(ref.getFullYear(),ref.getMonth())}</option>`;
  }
  sel.innerHTML = html;
  sel.onchange = ()=>{
    const [y,m] = sel.value.split('-').map(Number);
    CAL_STATE[kind] = { y, m: m-1 };
    renderCal(kind);
  };
}
function buildCalendar(rootId, days, kind, Y, M){
  const root = document.getElementById(rootId);
  if (!root) return;
  if (kind==='mine' && !HAS_USER) return;
  const monthName = new Date(Y,M,1).toLocaleDateString('id-ID',{month:'long',year:'numeric'});
  const firstDow = new Date(Y,M,1).getDay();
  const daysInMonth = new Date(Y,M+1,0).getDate();
  let html = `<div class="small fw-semibold mb-2 text-center">${monthName}</div>`;
  html += `<table class="table table-sm table-bordered text-center mb-0" style="font-size:.8rem"><thead><tr>`;
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
  html += `</tr></tbody></table>`;
  root.innerHTML = html;
}
function renderCal(kind){
  const st = CAL_STATE[kind]; if(!st) return;
  if (kind==='public') buildCalendar('calPublicWrap', PUBLIC_DAYS, 'public', st.y, st.m);
  else buildCalendar('calMineWrap', MINE_DAYS, 'mine', st.y, st.m);
  const sel = document.getElementById(kind==='public'?'calPublicMonth':'calMineMonth');
  if (sel) sel.value = `${st.y}-${String(st.m+1).padStart(2,'0')}`;
}
function shiftCal(kind, delta){
  const st = CAL_STATE[kind]; if(!st) return;
  const ref = new Date(st.y, st.m + delta, 1);
  CAL_STATE[kind] = { y: ref.getFullYear(), m: ref.getMonth() };
  renderCal(kind);
}
(function initCalendars(){
  const now = new Date();
  CAL_STATE.public = { y: now.getFullYear(), m: now.getMonth() };
  CAL_STATE.mine   = { y: now.getFullYear(), m: now.getMonth() };
  _fillMonthOptions('calPublicMonth','public');
  renderCal('public');
  if (HAS_USER) { _fillMonthOptions('calMineMonth','mine'); renderCal('mine'); }
})();

/* ===== Zoom-on-click untuk gambar bukti ===== */
function toggleZoom(img){
  if (img.dataset.zoom === '1') {
    img.style.transform = 'scale(1)';
    img.style.cursor = 'zoom-in';
    img.dataset.zoom = '0';
  } else {
    img.style.transform = 'scale(2)';
    img.style.cursor = 'zoom-out';
    img.dataset.zoom = '1';
  }
}

/* ===== Pagination generik (tabel & list) ===== */
function paginate(items, n, mountFn){
  if (!items.length || items.length <= n) return null;
  let page = 1;
  const pages = Math.ceil(items.length / n);
  function render(){
    items.forEach((el,i)=>{ el.style.display = (i>=(page-1)*n && i<page*n) ? '' : 'none'; });
    return page+'/'+pages;
  }
  render();
  return { next:()=>{ if(page<pages){page++; render();} }, prev:()=>{ if(page>1){page--; render();} }, info:()=>page+'/'+pages, pages:()=>pages, page:()=>page };
}
function _ctlHtml(p){
  return `<button class="btn btn-sm btn-outline-secondary me-2" data-pg="prev"><i class="bi bi-chevron-left"></i></button>
          <span class="text-muted">Hal. ${p.info()}</span>
          <button class="btn btn-sm btn-outline-secondary ms-2" data-pg="next"><i class="bi bi-chevron-right"></i></button>`;
}
function bindPager(ctlEl, p, redraw){
  function rerender(){ ctlEl.innerHTML = _ctlHtml(p);
    ctlEl.querySelector('[data-pg="prev"]').onclick = ()=>{ p.prev(); rerender(); redraw && redraw(); };
    ctlEl.querySelector('[data-pg="next"]').onclick = ()=>{ p.next(); rerender(); redraw && redraw(); };
  }
  rerender();
}
document.querySelectorAll('table[data-paginate]').forEach(tbl=>{
  const n = parseInt(tbl.dataset.paginate||'0',10); if(!n) return;
  const tbody = tbl.tBodies[0]; if(!tbody) return;
  const rows = Array.from(tbody.rows);
  const p = paginate(rows, n); if(!p) return;
  const ctl = document.createElement('tr');
  const td  = document.createElement('td');
  td.colSpan = (tbl.tHead?.rows?.[0]?.cells?.length) || (rows[0]?.cells?.length || 1);
  td.className = 'text-center small bg-light';
  ctl.appendChild(td); tbody.appendChild(ctl);
  bindPager(td, p);
});
document.querySelectorAll('[data-paginate-list]').forEach(root=>{
  const n = parseInt(root.dataset.paginateList||'0',10); if(!n) return;
  const items = Array.from(root.children);
  const p = paginate(items, n); if(!p) return;
  const ctl = document.createElement('div');
  ctl.className = 'text-center small py-2 border-top bg-light';
  root.appendChild(ctl);
  bindPager(ctl, p);
});

</script>
<script>
/* Revisi Juli 2026 — Leaderboard pagination (per 5). Reusable agar tetap
   aktif setelah AJAX filter kategori/periode mengganti isi #lbCard. */
window.lbInitPagination = function(){
  var list = document.getElementById('lbList'); if(!list) return;
  var ps = parseInt(list.getAttribute('data-lb-page-size')||'5',10);
  var items = Array.prototype.slice.call(list.querySelectorAll(':scope > li'));
  var total = items.length;
  var prevBtn = document.getElementById('lbPrev');
  var nextBtn = document.getElementById('lbNext');
  var info    = document.getElementById('lbPageInfo');
  if (total <= ps || !prevBtn || !nextBtn) return;
  var pages = Math.max(1, Math.ceil(total/ps)); var page = 1;
  function render(){
    items.forEach(function(li,i){ var p = Math.floor(i/ps)+1; li.style.display = (p===page)?'':'none'; });
    if (info) info.textContent = 'Halaman '+page+' / '+pages;
    prevBtn.disabled = (page<=1);
    nextBtn.disabled = (page>=pages);
  }
  prevBtn.onclick = function(){ if(page>1){page--;render();} };
  nextBtn.onclick = function(){ if(page<pages){page++;render();} };
  render();
};
document.addEventListener('DOMContentLoaded', window.lbInitPagination);
</script>
<script>
/* Revisi 22 Juni 2026 R12 — AJAX filter Leaderboard (kategori & periode). */
(function(){
  var f = document.getElementById('lbFilterForm');
  var card = document.getElementById('lbCard');
  if (!f || !card) return;
  var stat = document.getElementById('lbAjaxStat');
  function reload(){
    var cat = document.getElementById('lbCat').value;
    var per = document.getElementById('lbPeriod').value;
    stat.textContent = 'Memuat...';
    fetch('/riwayat.php?ajax_lb=1&cat='+encodeURIComponent(cat)+'&period='+encodeURIComponent(per), {headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.text(); })
      .then(function(html){ card.innerHTML = html; stat.textContent=''; if (typeof window.lbInitPagination === 'function') window.lbInitPagination(); try{ var qs=new URL(location.href); qs.searchParams.set('cat',cat); qs.searchParams.set('period',per); history.replaceState(null,'',qs.toString()); }catch(e){} })
      .catch(function(){ stat.textContent='Gagal memuat.'; });
  }
  document.getElementById('lbCat').addEventListener('change', reload);
  document.getElementById('lbPeriod').addEventListener('change', reload);
})();
</script>

<!-- Revisi 26 Juni 2026 #4 — Tren Kehadiran Mingguan dipindah ke atas Riwayat Sesi. -->
<!-- Revisi 26 Juni 2026 #2 — Modal daftar member eksternal per user -->
<div class="modal fade" id="extModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people"></i> Member Eksternal — <span id="extOwner">-</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="extBody">
        <div class="text-center text-muted py-3"><i class="bi bi-arrow-clockwise"></i> Memuat…</div>
      </div>
    </div>
  </div>
</div>
<script>
var _extModal=null;
function showEksternal(userId, nama){
  if(!_extModal) _extModal = new bootstrap.Modal(document.getElementById('extModal'));
  document.getElementById('extOwner').textContent = nama || '-';
  var body = document.getElementById('extBody');
  body.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-arrow-clockwise"></i> Memuat…</div>';
  _extModal.show();
  var per = (document.getElementById('lbPeriod')||{}).value || 'all';
  fetch('riwayat.php?action=ext_list&user_id='+encodeURIComponent(userId)+'&period='+encodeURIComponent(per))
    .then(function(r){return r.json();})
    .then(function(j){
      if (!j.ok) { body.innerHTML = '<div class="text-danger small">Gagal memuat.</div>'; return; }
      if (!j.rows || !j.rows.length) { body.innerHTML = '<div class="text-muted small">Belum ada member eksternal pada periode ini.</div>'; return; }
      var html = '<div class="small text-muted mb-2">Total: <b>'+j.rows.length+'</b> member eksternal unik</div>';
      html += '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>#</th><th>Nama Tamu</th><th class="text-center">Kali Hadir</th><th>Terakhir</th></tr></thead><tbody>';
      j.rows.forEach(function(r,i){
        html += '<tr><td>'+(i+1)+'</td><td>'+escapeHtml(r.nama||'-')+'</td><td class="text-center"><span class="badge bg-info-subtle text-info-emphasis">'+(r.kali||0)+'×</span></td><td class="small text-muted">'+(r.terakhir||'-')+'</td></tr>';
      });
      html += '</tbody></table></div>';
      body.innerHTML = html;
    })
    .catch(function(){ body.innerHTML = '<div class="text-danger small">Gagal memuat.</div>'; });
}
</script>


<!-- Revisi Juli 2026 — Reorder + spoiler untuk section riwayat -->
<style>
  .rv-spoiler-head{ cursor:pointer; user-select:none; }
  .rv-spoiler-head .rv-chev{ transition: transform .2s ease; margin-left:.5rem; }
  .rv-spoiler-head[aria-expanded="false"] .rv-chev{ transform: rotate(-90deg); }
</style>
<script>
(function(){
  function norm(t){ return (t||'').replace(/\s+/g,' ').trim().toLowerCase(); }

  // Label yang ingin dibuat sebagai spoiler (default tertutup).
  var SPOILER_LABELS = [
    'monitoring upload harian',
    'kalender aktivitas publik',
    'kalender aktivitas saya',
    'tren kehadiran mingguan',
    'riwayat sesi',
    'riwayat aktifitas saya',
    'riwayat aktivitas saya'
  ];

  function matchLabel(txt){
    return SPOILER_LABELS.some(function(l){ return txt.indexOf(l) !== -1; });
  }

  function findCardByHeader(includes){
    var cards = document.querySelectorAll('.card');
    for (var i=0;i<cards.length;i++){
      var h = cards[i].querySelector(':scope > .card-header');
      if (h && norm(h.textContent).indexOf(includes) !== -1) return cards[i];
    }
    return null;
  }

  function run(){
    // 1) Pindahkan "Riwayat Aktivitas Publik" ke paling atas (tanpa spoiler)
    var publicCard = findCardByHeader('riwayat aktivitas publik');
    var h2 = document.querySelector('h2.mb-3');
    if (publicCard && h2 && h2.parentNode){
      // Bungkus supaya bisa memakai margin bawah standar
      publicCard.classList.add('mb-3');
      h2.parentNode.insertBefore(publicCard, h2.nextSibling);
    }

    // 2) Bungkus card lain menjadi spoiler default TERTUTUP
    document.querySelectorAll('.card').forEach(function(card){
      var h = card.querySelector(':scope > .card-header');
      if (!h) return;
      if (h.dataset.rvSpoiler === '1') return;
      var t = norm(h.textContent);
      if (!matchLabel(t)) return;
      // Jangan spoiler kartu "Riwayat Aktivitas Publik"
      if (t.indexOf('riwayat aktivitas publik') !== -1) return;

      var kids = Array.from(card.children).filter(function(x){ return x !== h; });
      if (!kids.length) return;
      var id = 'rvspoil_' + Math.random().toString(36).slice(2,9);
      var wrap = document.createElement('div');
      wrap.className = 'collapse';
      wrap.id = id;
      kids.forEach(function(k){ wrap.appendChild(k); });
      card.appendChild(wrap);
      h.classList.add('rv-spoiler-head');
      h.setAttribute('role','button');
      h.setAttribute('aria-expanded','false');
      h.setAttribute('aria-controls', id);
      var chev = document.createElement('i');
      chev.className = 'bi bi-chevron-down rv-chev float-end';
      h.appendChild(chev);
      h.dataset.rvSpoiler = '1';
      h.addEventListener('click', function(ev){
        if (ev.target.closest('button, a, input, select, textarea, form')) return;
        var open = wrap.classList.toggle('show');
        h.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
</script>


<!-- Revisi Nov 2026 R11 — Di tampilan mobile, Leaderboard dipindahkan tepat di bawah kartu
     Pemilihan Kategori & Periode agar mudah dibaca tanpa harus scroll melewati Monitoring & Kalender. -->
<script>
(function(){
  var MQ = window.matchMedia('(max-width: 991.98px)');
  var lbCard = document.getElementById('lbCard');
  var filterForm = document.getElementById('lbFilterForm');
  if (!lbCard || !filterForm) return;
  var filterCard = filterForm.closest('.card');
  if (!filterCard) return;
  var originalParent = lbCard.parentNode;
  var originalNext   = lbCard.nextSibling;

  function apply(){
    if (MQ.matches) {
      // Sisipkan Leaderboard tepat setelah kartu Pemilihan Kategori & Periode.
      if (lbCard.previousElementSibling !== filterCard) {
        filterCard.parentNode.insertBefore(lbCard, filterCard.nextSibling);
      }
    } else {
      // Kembalikan ke posisi semula (col-lg-5) untuk desktop.
      if (lbCard.parentNode !== originalParent) {
        originalParent.insertBefore(lbCard, originalNext);
      }
    }
  }
  apply();
  if (MQ.addEventListener) MQ.addEventListener('change', apply);
  else if (MQ.addListener) MQ.addListener(apply);
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>


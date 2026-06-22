<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/migrations_v7.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Beranda';
$pageSkeleton = 'feed'; // Skeleton sesuai data: social feed + kartu
$u = current_user();

/* === Revisi: ketika pertama buka aplikasi, paksa ke /login.php (kecuali mode guest eksplisit) === */
if (!$u && empty($_GET['guest']) && empty($_SESSION['guest_ok'])) {
    header('Location: /login.php'); exit;
}
if (!$u && !empty($_GET['guest'])) { $_SESSION['guest_ok'] = 1; }

// ---- Handle forum + social feed actions ----
if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    rate_limit_or_die('post:'.$u['id'], 30, 60);
    $a = $_POST['_action'] ?? '';

    // Revisi 20 Juni 2026 R4 — upload video bisa besar; naikkan batas runtime
    // supaya tidak putus di tengah jalan (penyebab "Failed to fetch" di klien).
    $isVideoPost = ($a === 'post_new' && !empty($_FILES['video']['name']));
    $isAjaxPost  = !empty($_POST['_ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest');
    if ($isVideoPost) {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }
    $postNewErr = ''; $postNewOk = false;
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
    } elseif ($a === 'chat_edit') {
        // Pemilik pesan bisa mengedit pesannya sendiri; admin juga bisa
        $cid = (int)$_POST['id'];
        $newPesan = trim($_POST['pesan'] ?? '');
        if ($cid && $newPesan !== '') {
            $own = db_one("SELECT user_id FROM chat_forum WHERE id=$1", [$cid]);
            if ($own && ((int)$own['user_id']===(int)$u['id'] || $u['role']==='admin')) {
                db_exec("UPDATE chat_forum SET pesan=$1, updated_at=now() WHERE id=$2",
                    [sanitize_html($newPesan), $cid]);
            }
        }
    } elseif ($a === 'story_delete' && $u['role']==='admin') {
        $pid = (int)$_POST['post_id'];
        db_exec("DELETE FROM post_comments WHERE post_id=$1", [$pid]);
        db_exec("DELETE FROM post_likes WHERE post_id=$1", [$pid]);
        db_exec("DELETE FROM posts WHERE id=$1 AND jenis='story'", [$pid]);
    } elseif ($a === 'post_delete' && $u['role']==='admin') {
        $pid = (int)$_POST['post_id'];
        db_exec("DELETE FROM post_comments WHERE post_id=$1", [$pid]);
        db_exec("DELETE FROM post_likes WHERE post_id=$1", [$pid]);
        db_exec("DELETE FROM posts WHERE id=$1", [$pid]);
    } elseif ($a === 'comment_delete' && $u['role']==='admin') {
        db_exec("DELETE FROM post_comments WHERE id=$1", [(int)$_POST['comment_id']]);
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
        $mediaType = 'image';
        $imagesUrls = []; // Revisi 21 Juni 2026 R4 — multi-image post

        /* ============================================================
         * Revisi 21 Juni 2026 R4 — Posting video DIGANTI menjadi multi-image.
         * Field 'fotos[]' (multiple) dipakai sebagai sumber utama. Field
         * 'foto' lama (single) tetap diterima untuk kompatibilitas mundur.
         * Maksimum 10 gambar / posting.
         * ============================================================ */
        $allFiles = [];
        if (!empty($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
            $cnt = count($_FILES['fotos']['name']);
            for ($i=0; $i<$cnt && count($allFiles)<10; $i++) {
                if (empty($_FILES['fotos']['name'][$i])) continue;
                $allFiles[] = [
                    'name'     => $_FILES['fotos']['name'][$i],
                    'type'     => $_FILES['fotos']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['fotos']['tmp_name'][$i] ?? '',
                    'error'    => $_FILES['fotos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $_FILES['fotos']['size'][$i] ?? 0,
                ];
            }
        }
        if (empty($allFiles) && !empty($_FILES['foto']['name'])) {
            $allFiles[] = $_FILES['foto'];
        }

        if (!empty($allFiles)) {
            require_once __DIR__.'/config/imagekit.php';
            global $imageKit;
            foreach ($allFiles as $idx => $fImg) {
                [$ok, $extOrErr] = validate_image_upload($fImg);
                if (!$ok) { $postNewErr = $postNewErr ?: ('Gambar #'.($idx+1).': '.$extOrErr); continue; }
                $name = preg_replace('/[^a-z0-9]/i','_', $u['nama']).'-'.$jenis.'-'.time().'-'.$idx.'-'.bin2hex(random_bytes(3)).'.'.$extOrErr;
                try {
                    $uploadFile = $imageKit->uploadFile([
                        'file' => base64_encode(file_get_contents($fImg['tmp_name'])),
                        'fileName' => $name,
                        'folder' => '/sportapp/social/'.date('F_Y'),
                    ]);
                    if (!$uploadFile->error && !empty($uploadFile->result->url)) {
                        $imagesUrls[] = $uploadFile->result->url;
                    }
                } catch (Throwable $e) { /* lewati gambar yang gagal */ }
            }
            if (!empty($imagesUrls)) {
                $fotoUrl = $imagesUrls[0]; // first image jadi cover (kompatibilitas mundur)
                $mediaType = 'image';
                $postNewErr = ''; // sukses paling tidak satu gambar terupload
            }
        }

        // Pastikan kolom media_type & images_json ada (idempotent).
        try { db_exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS media_type VARCHAR(10) NOT NULL DEFAULT 'image'"); } catch (Throwable $e) {}
        try { db_exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS images_json TEXT"); } catch (Throwable $e) {}

        if ($postNewErr === '') {
            $imagesJson = !empty($imagesUrls) ? json_encode(array_values($imagesUrls), JSON_UNESCAPED_SLASHES) : null;
            if ($jenis === 'story') {
                $newId = (int)db_val("INSERT INTO posts(user_id,caption,foto_url,jenis,media_type,images_json,expired_at) VALUES($1,$2,$3,$4,$5,$6, now() + interval '24 hours') RETURNING id",
                    [(int)$u['id'], htmlspecialchars($caption), $fotoUrl, $jenis, $mediaType, $imagesJson]);
            } else {
                $newId = (int)db_val("INSERT INTO posts(user_id,caption,foto_url,jenis,media_type,images_json,expired_at) VALUES($1,$2,$3,$4,$5,$6, NULL) RETURNING id",
                    [(int)$u['id'], htmlspecialchars($caption), $fotoUrl, $jenis, $mediaType, $imagesJson]);
            }
            if (function_exists('sync_post_tags') && $newId) { sync_post_tags($newId, $caption); }
            $postNewOk = true;
        }
    } elseif ($a === 'like') {
        // Revisi 22 Juni 2026 R6 — TOGGLE like: klik pertama = suka, klik berikutnya = batalkan.
        // Defensif terhadap DB yang belum punya UNIQUE constraint di post_likes (tetap aman
        // setelah migrations_r6.sql dijalankan).
        $pid = (int)$_POST['post_id'];
        try {
            $exists = db_one("SELECT 1 FROM post_likes WHERE post_id=$1 AND user_id=$2 LIMIT 1",
                             [$pid, (int)$u['id']]);
            if ($exists) {
                // Sudah suka → batalkan (unlike). Hapus semua baris (untuk data lama yang ganda).
                db_exec("DELETE FROM post_likes WHERE post_id=$1 AND user_id=$2",
                        [$pid, (int)$u['id']]);
            } else {
                db_exec("INSERT INTO post_likes(post_id,user_id) VALUES($1,$2)",
                        [$pid, (int)$u['id']]);
                try {
                    $own = db_one("SELECT user_id FROM posts WHERE id=$1", [$pid]);
                    if ($own && (int)$own['user_id'] !== (int)$u['id']) {
                        notify((int)$own['user_id'], 'like',
                            '❤️ '.$u['nama'].' menyukai post Anda',
                            '', '/index.php#feed');
                    }
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {}
    } elseif ($a === 'comment') {
        // Revisi 20 Juni 2026 R4 — kirim notifikasi ke pemilik post saat ada komentar baru.
        $pid = (int)$_POST['post_id'];
        $isi = substr(trim($_POST['isi'] ?? ''), 0, 300);
        if ($isi !== '') {
            db_exec("INSERT INTO post_comments(post_id,user_id,isi) VALUES($1,$2,$3)", [$pid, (int)$u['id'], $isi]);
            try {
                $own = db_one("SELECT user_id FROM posts WHERE id=$1", [$pid]);
                if ($own && (int)$own['user_id'] !== (int)$u['id']) {
                    notify((int)$own['user_id'], 'comment',
                        '💬 '.$u['nama'].' mengomentari post Anda',
                        mb_substr($isi, 0, 120), '/index.php#feed');
                }
            } catch (Throwable $e) {}
        }
    } elseif ($a === 'comment_edit') {
        // Revisi 19 Juni 2026 Part Q — pemilik komentar dapat mengedit komentarnya
        try { db_exec("ALTER TABLE post_comments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP"); } catch (Throwable $e) {}
        $cid = (int)($_POST['comment_id'] ?? 0);
        $isi = substr(trim($_POST['isi'] ?? ''), 0, 300);
        if ($cid && $isi !== '') {
            $own = db_one("SELECT user_id FROM post_comments WHERE id=$1", [$cid]);
            // Revisi 19 Juni 2026 Part R — HANYA pemilik komentar yang boleh mengedit.
            if ($own && (int)$own['user_id']===(int)$u['id']) {
                db_exec("UPDATE post_comments SET isi=$1, updated_at=now() WHERE id=$2", [$isi, $cid]);
            }
        }
    } elseif ($a === 'sapa_send') {
        $target = (int)($_POST['target_id'] ?? 0);
        $pesan  = trim(substr($_POST['pesan'] ?? '', 0, 500));
        if ($target && $target !== (int)$u['id'] && $pesan !== '') {
            try {
                db_exec("CREATE TABLE IF NOT EXISTS guest_messages (
                  id SERIAL PRIMARY KEY,
                  owner_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  sender_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  parent_id INTEGER REFERENCES guest_messages(id) ON DELETE CASCADE,
                  pesan TEXT NOT NULL,
                  created_at TIMESTAMP NOT NULL DEFAULT now(),
                  updated_at TIMESTAMP
                )");
                // Cek apakah sudah pernah disapa
                $exists = db_val("SELECT 1 FROM sapa_log WHERE sender_user_id=$1 AND target_user_id=$2",
                  [(int)$u['id'], $target]);
                if (!$exists) {
                    db_exec("INSERT INTO guest_messages(owner_user_id,sender_user_id,pesan) VALUES($1,$2,$3)",
                      [$target, (int)$u['id'], '👋 '.$pesan]);
                    // Revisi R8 — hindari ON CONFLICT (tidak semua DB sudah punya UNIQUE
                    // pada sapa_log). Pakai pola check-then-insert agar selalu aman.
                    $already = db_val("SELECT 1 FROM sapa_log WHERE sender_user_id=$1 AND target_user_id=$2",
                        [(int)$u['id'], $target]);
                    if (!$already) {
                        db_exec("INSERT INTO sapa_log(sender_user_id,target_user_id) VALUES($1,$2)",
                            [(int)$u['id'], $target]);
                    }
                }
            } catch (Throwable $e) {}
        }
    } elseif ($a === 'quick_absen') {
        // Quick check-in dari Jadwal Terdekat — terintegrasi dengan absensi.php
        $jid = (int)($_POST['jadwal_id'] ?? 0);
        $st  = $_POST['status'] ?? 'hadir';
        if (!in_array($st, ['hadir','izin','sakit'], true)) $st = 'hadir';
        if ($jid) {
            try {
                db_exec("DELETE FROM absensi WHERE jadwal_id=$1 AND user_id=$2", [$jid, (int)$u['id']]);
                db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir,status,keterangan,metode,checkin_at)
                         VALUES($1,$2,$3,$4,$5,'quick',now())",
                    [$jid, (int)$u['id'], $st==='hadir'?1:0, $st, $_POST['keterangan'] ?? '']);
            } catch (Throwable $e) {}
        }
    } elseif ($a === 'quick_event_absen') {
        // Quick check-in dari Event Terdekat — terintegrasi dengan event.php
        $eid = (int)($_POST['event_id'] ?? 0);
        $st  = $_POST['status'] ?? 'hadir';
        if (!in_array($st, ['hadir','izin','sakit'], true)) $st = 'hadir';
        if ($eid) {
            try {
                $exists = db_val("SELECT id FROM event_peserta WHERE event_id=$1 AND user_id=$2", [$eid, (int)$u['id']]);
                if ($exists) {
                    db_exec("UPDATE event_peserta SET status=$1 WHERE id=$2", [$st, (int)$exists]);
                } else {
                    db_exec("INSERT INTO event_peserta(event_id,user_id,status) VALUES($1,$2,$3)", [$eid, (int)$u['id'], $st]);
                }
            } catch (Throwable $e) {}
        }
    }
    // Revisi 20 Juni 2026 R4 — bila video posting via AJAX, kirim JSON agar klien
    // bisa menampilkan pesan error yang sesungguhnya (bukan "Failed to fetch").
    if ($a === 'post_new' && $isAjaxPost) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>$postNewOk, 'err'=>$postNewErr]); exit;
    }
    header('Location: /index.php#feed'); exit;
}

$totalSesi    = (int) db_val("SELECT COUNT(*) FROM jadwal");
$totalHadir   = (int) db_val("SELECT COUNT(*) FROM absensi WHERE hadir=1");
$totalMember  = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");
// Revisi 13 Juni 2026 (fix 2): hitung member aktif & non-aktif.
// Auto-migrasi kolom aktif/nonaktif_catatan jika belum ada.
// Catatan: di beberapa instalasi lama, kolom "aktif" ter-create sebagai SMALLINT
// (mis. 0/1) bukan BOOLEAN. Agar query kompatibel keduanya, kita normalisasi
// nilainya lewat ekspresi teks: '1','t','true','y' dianggap aktif.
try {
  db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN NOT NULL DEFAULT TRUE");
  db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nonaktif_catatan TEXT");
} catch (Throwable $e) {}

// Ekspresi tahan-tipe: aman untuk kolom BOOLEAN maupun SMALLINT/INT.
$aktifExpr = "(LOWER(COALESCE(aktif::text,'true')) IN ('1','t','true','y','yes'))";
$memberAktif    = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND $aktifExpr");
$memberNonaktif = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND NOT $aktifExpr");

// ====== REVISI 31 Mei 2026: Total Visitor ======
// Tabel auto-create (tidak menghapus data lama). Lihat catatan SQL di README.
try {
    db_exec("CREATE TABLE IF NOT EXISTS site_visitors (
        id BIGSERIAL PRIMARY KEY,
        ip VARCHAR(64),
        user_agent TEXT,
        path VARCHAR(255),
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_site_visitors_created_at ON site_visitors(created_at)");
    // Throttle 1 jam per IP supaya tidak inflasi.
    $visitorIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $recent = db_val("SELECT 1 FROM site_visitors WHERE ip=$1 AND created_at > now() - interval '1 hour' LIMIT 1", [$visitorIp]);
    if (!$recent) {
        db_exec("INSERT INTO site_visitors(ip,user_agent,path) VALUES($1,$2,$3)", [
            $visitorIp,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            substr((string)($_SERVER['REQUEST_URI'] ?? '/'), 0, 255),
        ]);
    }
    $totalVisitor = (int) db_val("SELECT COUNT(*) FROM site_visitors");
    $visitorHariIni = (int) db_val("SELECT COUNT(*) FROM site_visitors WHERE created_at::date = CURRENT_DATE");
} catch (Throwable $e) {
    $totalVisitor = 0; $visitorHariIni = 0;
}

$jadwalTerdekat = db_all("SELECT j.*, u.nama AS koordinator, u.foto_url AS koord_foto, t.nama AS tim_nama,
                          tp.lat AS tp_lat, tp.lng AS tp_lng, tp.nama AS tp_nama
                          FROM jadwal j
                          LEFT JOIN users u ON u.id=j.koordinator_id
                          LEFT JOIN tim t ON t.id=j.tim_id
                          LEFT JOIN tempat tp ON tp.id=j.tempat_id
                          WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 5");
// Perlengkapan per jadwal (berdasarkan jenis olahraga jadwal)
$perlByJadwal = [];
if ($jadwalTerdekat) {
  $jenisList = array_unique(array_map(fn($j)=>$j['jenis'], $jadwalTerdekat));
  $jenisList = array_values(array_filter($jenisList));
  if ($jenisList) {
    $arr = '{'.implode(',', array_map(fn($s)=>'"'.str_replace('"','\"',$s).'"', $jenisList)).'}';
    try {
      $perlRows = db_all(
        "SELECT p.jenis_nama, p.nama AS item, p.jumlah, u.id AS uid, u.nama AS uname, u.foto_url
         FROM user_perlengkapan p JOIN users u ON u.id=p.user_id
         WHERE p.jenis_nama ILIKE ANY($1::text[])
         ORDER BY p.jenis_nama, u.nama, p.nama", [$arr]);
      foreach ($perlRows as $pr) {
        $key = mb_strtolower($pr['jenis_nama']);
        $perlByJadwal[$key][] = $pr;
      }
    } catch (Throwable $e) {}
  }
}
// Member baru 7 hari terakhir (sapa) — sembunyikan yg sudah disapa user ini, dan honor pref hide_sapa per akun
$newMembers = [];
$hideSapaForMe = 0;
if ($u) {
  $_p = islami_pref((int)$u['id']);
  $hideSapaForMe = (int)($_p['hide_sapa'] ?? 0);
  if (!$hideSapaForMe) {
    $newMembers = db_all(
      "SELECT id, nama, foto_url, created_at FROM users
       WHERE created_at >= NOW() - INTERVAL '7 days'
         AND role IN ('member','admin')
         AND id <> $1
         AND id NOT IN (SELECT target_user_id FROM sapa_log WHERE sender_user_id=$1)
       ORDER BY created_at DESC LIMIT 10",
      [(int)$u['id']]
    );
  }
} else {
  $newMembers = db_all(
    "SELECT id, nama, foto_url, created_at FROM users
     WHERE created_at >= NOW() - INTERVAL '7 days' AND role IN ('member','admin')
     ORDER BY created_at DESC LIMIT 10"
  );
}

// === Kabari Member (Koordinator PIC):
// Tampil untuk admin/PIC. Daftar = SEMUA user yang pic_admin_id = id admin yang login
// (sesuai pengaturan di /admin/members.php). WA diambil dari kolom 'wa' (yg
// dipakai admin/members.php) dengan fallback ke 'nomor_wa'. Member tanpa WA tetap
// ditampilkan namun tombol WhatsApp dinonaktifkan, sehingga PIC sadar siapa yang
// belum punya nomor.
$kabariKawan = [];
$jadwalDekat1 = null;
$isPicAdmin = false;
if ($u) {
  // Jadwal terdekat (1 item) untuk template pesan WA
  $jadwalDekat1 = db_one(
    "SELECT j.id, j.tanggal, j.jenis, j.tempat, j.jam_mulai, j.jam_selesai
     FROM jadwal j WHERE j.tanggal >= CURRENT_DATE ORDER BY j.tanggal ASC, j.jam_mulai ASC NULLS LAST LIMIT 1"
  );
  $isPic = (int) db_val("SELECT COUNT(*) FROM users WHERE pic_admin_id=$1", [(int)$u['id']]);
  $isPicAdmin = ($u['role'] === 'admin') || ($isPic > 0);
  if ($isPicAdmin) {
    // Ambil SEMUA member yang ditunjuk dengan admin ini sebagai PIC.
    // Tidak memfilter role agar admin sub-PIC pun ikut muncul; tidak memfilter WA
    // agar member tanpa nomor tetap terlihat (badge "belum ada WA").
    $kabariKawan = db_all(
      "SELECT id, nama, foto_url,
              COALESCE(NULLIF(wa,''), NULLIF(nomor_wa,'')) AS nomor_wa
       FROM users
       WHERE pic_admin_id = $1
       ORDER BY nama LIMIT 200",
      [(int)$u['id']]
    );
    // Fallback admin: jika belum ada member yang ditunjuk sebagai PIC-nya,
    // tampilkan semua member sebagai daftar awal supaya PIC bisa menghubungi.
    if ($u['role'] === 'admin' && !$kabariKawan) {
      $kabariKawan = db_all(
        "SELECT id, nama, foto_url,
                COALESCE(NULLIF(wa,''), NULLIF(nomor_wa,'')) AS nomor_wa
         FROM users
         WHERE role = 'member'
         ORDER BY nama LIMIT 200"
      );
    }
  }
}

// Detail absensi per jadwal terdekat (siapa hadir/izin/sakit/telat/absen + catatan)
$absByJadwal = [];
$_jids = array_map(fn($j)=>(int)$j['id'], $jadwalTerdekat);
if ($_jids) {
    $_rows = db_all(
      "SELECT a.jadwal_id, a.status, a.hadir, a.keterangan, u.id AS uid, u.nama, u.foto_url
       FROM absensi a JOIN users u ON u.id=a.user_id
       WHERE a.jadwal_id = ANY($1::int[])
       ORDER BY
         CASE a.status WHEN 'hadir' THEN 1 WHEN 'telat' THEN 2 WHEN 'izin' THEN 3 WHEN 'sakit' THEN 4 ELSE 5 END,
         u.nama",
      ['{'.implode(',', $_jids).'}']
    );
    foreach ($_rows as $r) $absByJadwal[(int)$r['jadwal_id']][] = $r;
}
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
$uidMe = (int)($u['id'] ?? 0);
$stories = db_all("SELECT p.*, u.nama, u.foto_url AS user_foto,
                          (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS likes,
                          (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=$1) AS liked_by_me,
                          (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comments
                   FROM posts p JOIN users u ON u.id=p.user_id
                   WHERE p.jenis='story' AND (p.expired_at IS NULL OR p.expired_at > now())
                   ORDER BY p.created_at DESC LIMIT 20", [$uidMe]);
// Revisi 19 Juni 2026 Part Q — komentar untuk Story Hari Ini (untuk modal interaksi)
$storyCommentsByPost = [];
if ($stories) {
    $sIds = array_map(fn($x)=>(int)$x['id'], $stories);
    $sRows = db_all("SELECT pc.id, pc.post_id, pc.user_id, pc.isi, pc.created_at, u.nama, u.foto_url
                     FROM post_comments pc JOIN users u ON u.id=pc.user_id
                     WHERE pc.post_id = ANY($1::int[]) ORDER BY pc.created_at ASC",
                     ['{'.implode(',',$sIds).'}']);
    foreach ($sRows as $r) $storyCommentsByPost[(int)$r['post_id']][] = $r;
}

// === Revisi: Social feed pagination — 2 data per halaman (server-side) ===
$feedPerPage = 2;
$feedPage = max(1, (int)($_GET['fp'] ?? 1));
$feedTotal = (int) db_val("SELECT COUNT(*) FROM posts WHERE jenis='post'");
$feedPages = max(1, (int)ceil($feedTotal / $feedPerPage));
if ($feedPage > $feedPages) $feedPage = $feedPages;
$feedOffset = ($feedPage - 1) * $feedPerPage;
// Revisi 21 Juni 2026 R4 — pastikan kolom images_json ada lalu sertakan dalam SELECT
try { db_exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS images_json TEXT"); } catch (Throwable $e) {}
$feed = db_all("SELECT p.id, p.user_id, p.caption, p.foto_url AS post_foto, p.jenis,
                  COALESCE(p.media_type,'image') AS media_type, p.images_json, p.created_at,
                  u.nama, u.foto_url AS user_foto,
                  (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS likes,
                  (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comments,
                  (SELECT COUNT(*) FROM post_likes pl2 WHERE pl2.post_id=p.id AND pl2.user_id=$1) AS liked_by_me
                FROM posts p JOIN users u ON u.id=p.user_id
                WHERE p.jenis='post' ORDER BY p.created_at DESC LIMIT $2 OFFSET $3",
                [$uidMe, $feedPerPage, $feedOffset]);

// Komentar per post (untuk ditampilkan inline)
$feedIds = array_map(fn($x)=>(int)$x['id'], $feed);
$commentsByPost = [];
if ($feedIds) {
    // Revisi 19 Juni 2026 Part Q — sertakan user_id & updated_at untuk fitur edit komentar.
    try { db_exec("ALTER TABLE post_comments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP"); } catch (Throwable $e) {}
    $rows = db_all("SELECT pc.id, pc.post_id, pc.user_id, pc.isi, pc.created_at, pc.updated_at, u.nama, u.foto_url
                    FROM post_comments pc JOIN users u ON u.id=pc.user_id
                    WHERE pc.post_id = ANY($1::int[]) ORDER BY pc.created_at ASC", ['{'.implode(',', $feedIds).'}']);
    foreach ($rows as $r) $commentsByPost[(int)$r['post_id']][] = $r;
}

$activeQr = db_all("SELECT q.token, j.id, j.tanggal, j.jenis, j.tempat
                    FROM qr_tokens q JOIN jadwal j ON j.id=q.jadwal_id
                    WHERE q.valid_until > now() AND q.valid_from <= now()
                    ORDER BY q.id DESC LIMIT 3");

// === Event Terdekat (untuk index.php) — diambil dari submit event admin ===
$eventTerdekat = [];
$myEventStatus = [];
$eventPesertaByEvent = [];
try {
    $eventTerdekat = db_all(
        "SELECT e.id, e.nama, e.jenis, e.tipe, e.tanggal_mulai, e.tanggal_selesai, e.jam_mulai, e.lokasi, e.status,
                (SELECT COUNT(*) FROM event_peserta p WHERE p.event_id=e.id) AS jml
         FROM event e
         WHERE COALESCE(e.tanggal_selesai, e.tanggal_mulai) >= CURRENT_DATE
         ORDER BY e.tanggal_mulai ASC LIMIT 5"
    );
    if ($u && $eventTerdekat) {
        $eids = array_map(fn($e)=>(int)$e['id'], $eventTerdekat);
        $myRows = db_all("SELECT event_id, status FROM event_peserta WHERE user_id=$1 AND event_id = ANY($2::int[])",
            [(int)$u['id'], '{'.implode(',',$eids).'}']);
        foreach ($myRows as $r) $myEventStatus[(int)$r['event_id']] = $r['status'];
    }
    // Detail peserta + status per event terdekat (dedup: prefer baris ber-status)
    if ($eventTerdekat) {
        $_eids2 = array_map(fn($e)=>(int)$e['id'], $eventTerdekat);
        $_perows = db_all(
          "SELECT ep.event_id, ep.status, ep.keterangan,
                  u.id AS uid, u.nama AS user_nama, u.foto_url,
                  t.id AS tim_id, t.nama AS tim_nama
           FROM (
             SELECT DISTINCT ON (event_id, COALESCE(user_id,0), COALESCE(tim_id,0)) *
             FROM event_peserta
             WHERE event_id = ANY($1::int[])
             ORDER BY event_id, COALESCE(user_id,0), COALESCE(tim_id,0),
               CASE WHEN status IS NOT NULL AND status<>'absen' THEN 0 ELSE 1 END, id
           ) ep
           LEFT JOIN users u ON u.id=ep.user_id
           LEFT JOIN tim t ON t.id=ep.tim_id
           ORDER BY ep.event_id,
             CASE COALESCE(ep.status,'belum')
               WHEN 'hadir' THEN 1 WHEN 'telat' THEN 2 WHEN 'izin' THEN 3
               WHEN 'sakit' THEN 4 ELSE 5 END,
             COALESCE(u.nama, t.nama)",
          ['{'.implode(',', $_eids2).'}']
        );
        foreach ($_perows as $r) $eventPesertaByEvent[(int)$r['event_id']][] = $r;
    }
} catch (Throwable $e) { $eventTerdekat = []; }

// Status absen-saya per jadwal terdekat (untuk highlight tombol quick absen)
$myAbsenByJadwal = [];
if ($u && !empty($_jids)) {
    try {
        // Revisi R8 (#6) — abaikan entri auto ([AUTO-SAKIT]) supaya user tidak
        // melihat dirinya "sudah absen" padahal belum menekan tombol.
        $rs = db_all("SELECT jadwal_id, status, hadir, COALESCE(keterangan,'') AS keterangan
                        FROM absensi WHERE user_id=$1 AND jadwal_id = ANY($2::int[])",
            [(int)$u['id'], '{'.implode(',',$_jids).'}']);
        foreach ($rs as $r) {
            if (strncmp((string)$r['keterangan'], '[AUTO-', 6) === 0) continue;
            $st = $r['status'] ?: ((int)$r['hadir']===1?'hadir':'absen');
            $myAbsenByJadwal[(int)$r['jadwal_id']] = $st;
        }
    } catch (Throwable $e) {}
}


/* Revisi 2 Jun 2026: render blok CMS dari tabel index_blok */
if (!function_exists('render_index_blok')) {
    function render_index_blok(string $posisi): void {
        try {
            $rs = db_all("SELECT judul, konten FROM index_blok WHERE aktif=true AND posisi=$1 ORDER BY urutan, id", [$posisi]);
        } catch (Throwable $e) { $rs = []; }
        foreach ($rs as $b) {
            echo '<section class="container my-3"><div class="card shadow-sm"><div class="card-header fw-semibold">'
               . htmlspecialchars($b['judul']) . '</div><div class="card-body">' . $b['konten'] . '</div></div></section>';
        }
    }
}
include __DIR__.'/includes/header.php'; ?>
<?php render_index_blok('top'); ?>

<section class="hero mb-3 p-3 p-md-4 rounded-3 text-white" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);box-shadow:0 6px 18px rgba(14,165,233,.25);">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="flex-grow-1" style="min-width:240px;">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="badge-soft" style="background:rgba(255,255,255,.18);color:#fff;"><i class="bi bi-stars me-1"></i> Komunitas HapFam</span>
      </div>
      <h1 class="h3 mb-1 text-white" style="line-height:1.25;word-break:break-word;">Dashboard Olahraga Komunitas</h1>
      <p class="mb-2 text-white-50" style="line-height:1.5;">Check-in, kompetisi, dan komunitas dalam satu tempat.</p>
      <button id="installBtn" class="btn btn-sm btn-light fw-semibold"><i class="bi bi-phone"></i> Tambahkan Pintasan ke HP kamu</button>
    </div>
    <img src="assets/img/card-olahraga.jpg" alt="Komunitas jogging" loading="lazy" width="180" height="120" class="rounded-3 d-none d-sm-block" style="width:180px;height:120px;object-fit:cover;box-shadow:0 6px 16px rgba(0,0,0,.25);">
  </div>
</section>

<script>
let _deferredInstall = null;
const _installBtn = document.getElementById('installBtn');
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); _deferredInstall = e; });
document.addEventListener('DOMContentLoaded', () => {
  if (!_installBtn) return;
  _installBtn.addEventListener('click', async () => {
    if (_deferredInstall) { _deferredInstall.prompt(); _deferredInstall = null; }
    else { alert('Buka menu browser (⋮) lalu pilih "Tambahkan ke Layar Utama / Install app". Setelah itu, ikon HapFam akan muncul di home screen HP kamu.'); }
  });
});
</script>

<!-- Section "Info & Wawasan" dipindah ke Menu Navigasi Mobile (revisi 12 Juni 2026). -->


<!-- Section "Nonton Streaming Live" dihapus sesuai revisi. -->

<?php if($u): ?>
<button id="btnEnableNotif" class="btn btn-sm btn-outline-primary mb-3" type="button">
  <i class="bi bi-bell"></i> Aktifkan Notifikasi
</button>
<?php endif; ?>

<?php if($u && $kabariKawan):
  if ($jadwalDekat1) {
    $jamTxt = $jadwalDekat1['jam_mulai'] ? (' pukul '.substr($jadwalDekat1['jam_mulai'],0,5).(($jadwalDekat1['jam_selesai'])?('-'.substr($jadwalDekat1['jam_selesai'],0,5)):'')) : '';
    $msgTpl = "Halo Kawan! Jangan lupa ada jadwal *".$jadwalDekat1['jenis']."* tanggal ".date('d M Y', strtotime($jadwalDekat1['tanggal'])).$jamTxt." di ".$jadwalDekat1['tempat'].". Yuk ikutan! — dari ".$u['nama'];
    $headerJadwal = '<small class="text-muted">Jadwal terdekat: '.date('d M', strtotime($jadwalDekat1['tanggal'])).' · '.htmlspecialchars($jadwalDekat1['jenis']).'</small>';
  } else {
    $msgTpl = "Halo Kawan! Pengingat dari koordinator olahraga kita ya, tetap jaga kebugaran. — dari ".$u['nama'];
    $headerJadwal = '<small class="text-muted">Belum ada jadwal terdekat</small>';
  }
?>
<div class="card shadow-sm mb-3" id="sec-kabari">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-megaphone text-warning"></i> Kabari Member (Koordinator PIC)</span>
    <?= $headerJadwal ?>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-2">Sebagai PIC, klik WhatsApp untuk mengabari setiap member di bawah koordinasi kamu. Daftar diambil dari pengaturan PIC di halaman <em>Admin → Members</em>.</p>
    <div class="row g-2">
      <?php foreach($kabariKawan as $k):
        $rawWa = preg_replace('/\D+/','', $k['nomor_wa'] ?? '');
        $hasWa = $rawWa !== '';
        $wa = $hasWa ? preg_replace('/^0/','62', $rawWa) : '';
        $waUrl = $hasWa ? 'https://wa.me/'.$wa.'?text='.rawurlencode($msgTpl) : '#';
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded p-2 d-flex align-items-center gap-2">
            <?= user_avatar($k['foto_url'] ?? null, $k['nama'], 32) ?>
            <div class="flex-grow-1 small">
              <div class="fw-semibold text-truncate"><?= htmlspecialchars($k['nama']) ?></div>
              <?php if ($hasWa): ?>
                <div class="text-muted text-truncate">📱 <?= htmlspecialchars($k['nomor_wa']) ?></div>
              <?php else: ?>
                <div class="text-danger text-truncate"><i class="bi bi-exclamation-triangle"></i> Belum ada nomor WA</div>
              <?php endif; ?>
            </div>
            <?php if ($hasWa): ?>
              <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="Kirim WhatsApp">
                <i class="bi bi-whatsapp"></i>
              </a>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Tidak ada nomor WA"><i class="bi bi-whatsapp"></i></button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>



<?php /* Revisi 6 Juni 2026: Kartu "QR Check-in Sesi Olahraga" dihapus dari index. */ ?>

<!-- REVISI 31 Mei 2026: Total Visitor -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6">
    <div class="card shadow-sm border-0" style="background:linear-gradient(135deg,#22c55e,#0ea5e9);color:#fff;">
      <div class="card-body d-flex align-items-center gap-3">
        <div style="font-size:2rem"><i class="bi bi-people"></i></div>
        <div class="flex-grow-1">
          <div class="small opacity-75">Total Visitor</div>
          <div class="h3 mb-0 fw-bold"><?= number_format($totalVisitor, 0, ',', '.') ?></div>
        </div>
        <div class="text-end">
          <div class="small opacity-75">Hari ini</div>
          <div class="h5 mb-0 fw-bold"><?= number_format($visitorHariIni, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* Revisi 13 Juni 2026: status member aktif & tidak aktif */ ?>
<div class="row g-2 mb-3">
  <div class="col-6"><div class="card card-stat shadow-sm border-success-subtle"><div class="card-body">
    <div class="stat-icon" style="background:#dcfce7;color:#166534"><i class="bi bi-person-check-fill"></i></div>
    <div class="stat-label">Member Aktif</div><div class="stat-value text-success"><?= $memberAktif ?></div></div></div></div>
  <div class="col-6"><div class="card card-stat shadow-sm border-danger-subtle"><div class="card-body">
    <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-person-x-fill"></i></div>
    <div class="stat-label">Member Tidak Aktif</div><div class="stat-value text-danger"><?= $memberNonaktif ?></div></div></div></div>
</div>
<div class="row g-3 mb-3" id="sec-dashboard-stats">
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
  <div class="story-strip" data-live="stories">
    <?php foreach($stories as $s):
      $sFotoDisp = $s['foto_url'] ? ltrim($s['foto_url'],'/') : '';
      $sIsVideo  = (isset($s['media_type']) && $s['media_type']==='video') || ($sFotoDisp && preg_match('/\.(mp4|webm|mov|m4v|ogg)(\?|$)/i', $sFotoDisp));
    ?>
      <div class="story-item position-relative" style="cursor:pointer">
        <div onclick='showStory(<?= json_encode([
          "id"=>(int)$s["id"],
          "nama"=>$s["nama"],"foto"=>$sFotoDisp,"user_foto"=>$s["user_foto"] ?? "",
          "is_video"=>$sIsVideo?1:0,
          "caption"=>$s["caption"] ?? "","created_at"=>$s["created_at"] ?? "",
          // Revisi 19 Juni 2026 Part Q — data interaksi like/komentar untuk Story Hari Ini
          "likes"=>(int)($s["likes"] ?? 0),
          "liked_by_me"=>(int)($s["liked_by_me"] ?? 0),
          "comments_count"=>(int)($s["comments"] ?? 0),
          "comments"=>array_map(function($c){
            return ["nama"=>$c["nama"],"foto"=>$c["foto_url"] ?? "","isi"=>$c["isi"],"created_at"=>$c["created_at"]];
          }, $storyCommentsByPost[(int)$s["id"]] ?? []),
        ], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
          <div class="story-ring">
            <?php if ($sFotoDisp && $sIsVideo): ?>
              <div style="position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#000;color:#fff;border-radius:50%">
                <i class="bi bi-play-circle-fill" style="font-size:1.6rem"></i>
              </div>
            <?php elseif ($sFotoDisp): ?>
              <img src="<?= htmlspecialchars($sFotoDisp) ?>" alt="story" class="zoomable" onerror="this.style.display='none'">
            <?php elseif ($s['caption']): ?>
              <div title="<?= htmlspecialchars($s['caption']) ?>"><?= htmlspecialchars(mb_substr($s['nama'],0,1)) ?></div>
            <?php else: ?><div><?= htmlspecialchars(mb_substr($s['nama'],0,1)) ?></div><?php endif; ?>
          </div>
          <small><?= htmlspecialchars($s['nama']) ?></small>
        </div>
        <?php if($u && $u['role']==='admin'): ?>
          <form method="post" class="position-absolute" data-ajax style="top:-4px;right:-4px;z-index:5;" onsubmit="event.stopPropagation();return confirm('Hapus story ini?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="story_delete">
            <input type="hidden" name="post_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-sm btn-danger rounded-circle p-1" style="width:24px;height:24px;line-height:1;" title="Hapus story (admin)"><i class="bi bi-x" style="font-size:.8rem"></i></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; if(!$stories): ?><div class="text-muted small">Belum ada story.</div><?php endif; ?>
  </div>
</div></div>
<?php endif; ?>

<style>
/* Revisi: jarak antar kartu di mobile agar Forum tidak tabrakan dengan Social Feed */
@media (max-width: 991.98px){
  #sec-social-feed, #sec-online, #forum, #sapaMemberCard { margin-bottom: 1rem !important; }
  .row.g-3 > [class*="col-"] { margin-bottom: .25rem; }
}
</style>
<div class="row g-3">
  <div class="col-lg-7">
    <?php /* Sentuhan Islami Hari Ini & kata-katanya dihapus sesuai revisi. */ ?>
    <?php if($newMembers): ?>
    <div class="card shadow-sm mb-3" id="sapaMemberCard"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-emoji-smile text-warning"></i> Sapa Member Baru <span class="badge bg-primary"><?= count($newMembers) ?></span></span>
      <?php if($u): ?>
      <form method="post" action="/islami.php" class="m-0" onsubmit="return confirm('Sembunyikan widget Sapa untuk akun Anda? Member lain tetap melihatnya.')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="hide_sapa">
        <button type="submit" class="btn btn-sm btn-link text-muted p-0" title="Sembunyikan untuk akun saya saja"><i class="bi bi-x-lg"></i></button>
      </form>
      <?php endif; ?>
    </div>
      <div class="card-body" data-live="newmembers">
        <div class="row g-2">
        <?php foreach($newMembers as $nm): ?>
          <div class="col-md-6">
            <div class="border rounded p-2 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <a href="/user.php?id=<?= (int)$nm['id'] ?>" class="text-decoration-none"><?= user_avatar($nm['foto_url']??null, $nm['nama'], 36) ?></a>
                <div class="flex-grow-1">
                  <a href="/user.php?id=<?= (int)$nm['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($nm['nama']) ?></a>
                  <div class="small text-muted">Bergabung <?= date('d M', strtotime($nm['created_at'])) ?></div>
                </div>
                <span class="badge bg-success-subtle text-success">Baru</span>
              </div>
              <?php if($u): ?>
              <form method="post" class="d-flex gap-1" data-ajax data-ajax-label="Mengirim sapaan...">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="sapa_send">
                <input type="hidden" name="target_id" value="<?= (int)$nm['id'] ?>">
                <input class="form-control form-control-sm" name="pesan" maxlength="500" placeholder="Sapa <?= htmlspecialchars($nm['nama']) ?>..." required>
                <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
              </form>
              <?php else: ?><div class="small text-muted"><a href="/login.php">Login</a> untuk menyapa.</div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php endif; ?>

    <?php if (!empty($eventTerdekat)): ?>
    <div class="card shadow-sm mb-3" id="sec-event-terdekat"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-trophy text-warning me-1"></i> Event Terdekat</span>
      <a href="/event.php" class="small text-decoration-none">Lihat semua &raquo;</a>
    </div>
      <div data-live="event_terdekat">
      <div class="table-responsive"><table class="table table-hover table-stack mb-0" data-paginate="5">
        <thead><tr><th style="width:32px"></th><th>Tanggal</th><th>Nama Event</th><th>Jenis</th><th>Lokasi</th><th>Absensi</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php foreach($eventTerdekat as $ev):
          $eid=(int)$ev['id']; $myS = $myEventStatus[$eid] ?? null;
          $epList = $eventPesertaByEvent[$eid] ?? [];
          $eCnt = ['hadir'=>0,'telat'=>0,'izin'=>0,'sakit'=>0,'absen'=>0,'belum'=>0];
          foreach($epList as $ep){ $s = $ep['status'] ?: 'belum'; if(isset($eCnt[$s])) $eCnt[$s]++; else $eCnt['belum']++; }
        ?>
          <tr>
            <td data-label=""><button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#edetail<?= $eid ?>" title="Lihat absensi"><i class="bi bi-chevron-down"></i></button></td>
            <td data-label="Tanggal"><?= htmlspecialchars($ev['tanggal_mulai']) ?><?php if(!empty($ev['jam_mulai'])): ?> <span class="text-muted small"><?= htmlspecialchars(substr($ev['jam_mulai'],0,5)) ?></span><?php endif; ?></td>
            <td data-label="Nama"><a class="text-decoration-none fw-semibold" href="/event.php?id=<?= $eid ?>"><?= htmlspecialchars($ev['nama']) ?></a>
              <div class="small text-muted"><i class="bi bi-people"></i> <?= (int)$ev['jml'] ?> peserta</div></td>
            <td data-label="Jenis"><span class="pill"><?= htmlspecialchars($ev['jenis'] ?: ($ev['tipe']??'-')) ?></span></td>
            <td data-label="Lokasi"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($ev['lokasi'] ?: '-') ?></td>
            <td data-label="Absensi" class="small">
              <?php if($eCnt['hadir']): ?><span class="badge bg-success-subtle text-success" title="Hadir">H <?= $eCnt['hadir'] ?></span> <?php endif; ?>
              <?php if($eCnt['telat']): ?><span class="badge bg-warning-subtle text-warning" title="Telat">T <?= $eCnt['telat'] ?></span> <?php endif; ?>
              <?php if($eCnt['izin']): ?><span class="badge bg-info-subtle text-info" title="Izin">I <?= $eCnt['izin'] ?></span> <?php endif; ?>
              <?php if($eCnt['sakit']): ?><span class="badge bg-secondary-subtle text-secondary" title="Sakit">S <?= $eCnt['sakit'] ?></span> <?php endif; ?>
              <?php if($eCnt['absen']): ?><span class="badge bg-danger-subtle text-danger" title="Absen">A <?= $eCnt['absen'] ?></span> <?php endif; ?>
              <?php if($eCnt['belum']): ?><span class="badge bg-light text-muted border" title="Belum diabsen">- <?= $eCnt['belum'] ?></span><?php endif; ?>
              <?php if(!array_sum($eCnt)): ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td data-label="Aksi" class="text-end">
              <?php if($u):
                $mk = function($st,$cls,$label,$icon) use($myS,$eid){
                  $active = $myS===$st ? '' : '-outline';
                  echo '<form method="post" class="d-inline" data-ajax data-ajax-label="Menyimpan...">'
                     . '<input type="hidden" name="csrf" value="'.csrf_token().'">'
                     . '<input type="hidden" name="_action" value="quick_event_absen">'
                     . '<input type="hidden" name="event_id" value="'.$eid.'">'
                     . '<input type="hidden" name="status" value="'.$st.'">'
                     . '<button class="btn btn-sm btn'.$active.'-'.$cls.' me-1" title="'.$label.'"><i class="bi bi-'.$icon.'"></i> '.$label.'</button>'
                     . '</form>';
                };
                $mk('hadir','success','Hadir','check2-circle');
                $mk('izin','info','Izin','envelope-paper');
                $mk('sakit','secondary','Sakit','bandaid');
                if($myS): ?><div class="small text-muted mt-1">Status: <b><?= htmlspecialchars(strtoupper($myS)) ?></b></div><?php endif;
              else: ?><a href="/login.php" class="small">Login untuk daftar</a><?php endif; ?>
            </td>
          </tr>
          <tr class="collapse" id="edetail<?= $eid ?>">
            <td colspan="7" class="bg-light">
              <?php if(!$epList): ?>
                <div class="text-muted small">Belum ada peserta / data absensi event ini.</div>
              <?php else: ?>
                <div class="table-responsive"><table class="table table-sm mb-0" data-paginate="8">
                  <thead><tr><th>Peserta</th><th>Status</th><th>Catatan</th></tr></thead>
                  <tbody>
                  <?php foreach($epList as $ep):
                    $st = $ep['status'] ?: 'belum';
                    $stMap = ['hadir'=>'success','telat'=>'warning','izin'=>'info','sakit'=>'secondary','absen'=>'danger','belum'=>'light'];
                    $cls = $stMap[$st] ?? 'secondary';
                    $label = $ep['user_nama'] ?: ($ep['tim_nama'] ? 'Tim: '.$ep['tim_nama'] : '—');
                  ?>
                    <tr>
                      <td>
                        <?php if(!empty($ep['user_nama'])): ?>
                          <a class="text-decoration-none" href="/user.php?id=<?= (int)$ep['uid'] ?>"><?= user_name_with_avatar($ep['foto_url'] ?? null, $ep['user_nama'], false, 22) ?></a>
                          <?php if(!empty($ep['tim_nama'])): ?> <small class="text-muted">· <?= htmlspecialchars($ep['tim_nama']) ?></small><?php endif; ?>
                        <?php else: ?>
                          <i class="bi bi-people-fill text-warning"></i> <?= htmlspecialchars($label) ?>
                        <?php endif; ?>
                      </td>
                      <td><span class="badge bg-<?= $cls ?>-subtle text-<?= $cls==='light'?'muted':$cls ?> text-uppercase border"><?= htmlspecialchars($st) ?></span></td>
                      <td class="small"><?= $ep['keterangan'] ? htmlspecialchars($ep['keterangan']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3" id="sec-jadwal-terdekat"><div class="card-header"><i class="bi bi-calendar3 me-1 text-primary"></i> Jadwal Terdekat</div>
      <div data-live="jadwal">
      <div class="table-responsive"><table class="table table-hover table-stack mb-0" data-paginate="5">
        <thead><tr><th style="width:32px"></th><th>Tanggal</th><th>Jenis</th><th>Tempat</th><th>Lokasi</th><th>Koordinator</th><th>Absensi Saya</th><th class="text-end">Absen</th></tr></thead><tbody>
        <?php foreach($jadwalTerdekat as $j):
          $jid=(int)$j['id']; $absList = $absByJadwal[$jid] ?? [];
          $cnt = ['hadir'=>0,'telat'=>0,'izin'=>0,'sakit'=>0,'absen'=>0];
          // Revisi R8 (#6) — abaikan baris absensi otomatis ([AUTO-SAKIT] dari
          // apply_kondisi_to_absensi), supaya badge "Absen" Jadwal Terdekat
          // hanya menghitung user yg benar-benar mengisi absen sendiri.
          foreach($absList as $a){
            if (strncmp((string)($a['keterangan'] ?? ''), '[AUTO-', 6) === 0) continue;
            $s=$a['status']?:'absen'; if(isset($cnt[$s])) $cnt[$s]++;
          }
        ?>
          <tr>
            <td data-label=""><button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#jdetail<?= $jid ?>" title="Lihat absen"><i class="bi bi-chevron-down"></i></button></td>
            <td data-label="Tanggal">
              <?= htmlspecialchars($j['tanggal']) ?>
              <?php
                // Revisi 13 Juni 2026: tampilkan waktu (jam_mulai – jam_selesai) di Jadwal Terdekat.
                $jm = !empty($j['jam_mulai']) ? substr($j['jam_mulai'],0,5) : '';
                $js = !empty($j['jam_selesai']) ? substr($j['jam_selesai'],0,5) : '';
                if ($jm !== '' || $js !== '') {
                  echo '<div class="small text-muted"><i class="bi bi-clock"></i> '
                     . htmlspecialchars($jm ?: '—')
                     . ($js !== '' ? ' – '.htmlspecialchars($js) : '')
                     . '</div>';
                }
              ?>
            </td>
            <td data-label="Jenis"><span class="pill"><?= htmlspecialchars($j['jenis']) ?></span></td>
            <td data-label="Tempat"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($j['tempat']) ?></td>
            <td data-label="Lokasi">
              <?php
                $maps = ($j['tp_lat'] && $j['tp_lng'])
                  ? 'https://www.google.com/maps?q='.$j['tp_lat'].','.$j['tp_lng']
                  : 'https://www.google.com/maps/search/'.urlencode($j['tempat']);
              ?>
              <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?= htmlspecialchars($maps) ?>" title="Lihat di Google Maps"><i class="bi bi-google"></i> Lokasi</a>
            </td>
            <td data-label="Koord"><a class="text-decoration-none" href="/user.php?id=<?= (int)$j['koordinator_id'] ?>"><?= user_name_with_avatar($j['koord_foto'] ?? null, $j['koordinator'] ?? '-', false, 24) ?></a></td>
            <td data-label="Absensi Saya">
              <?php if($u):
                $myS = $myAbsenByJadwal[$jid] ?? null;
                $btnDef = function($st,$cls,$label,$icon) use($myS,$jid){
                  $active = $myS===$st ? '' : '-outline';
                  echo '<form method="post" class="d-inline" data-ajax data-ajax-label="Menyimpan absen...">'
                     . '<input type="hidden" name="csrf" value="'.csrf_token().'">'
                     . '<input type="hidden" name="_action" value="quick_absen">'
                     . '<input type="hidden" name="jadwal_id" value="'.$jid.'">'
                     . '<input type="hidden" name="status" value="'.$st.'">'
                     . '<button class="btn btn-sm btn'.$active.'-'.$cls.' me-1" title="'.$label.'"><i class="bi bi-'.$icon.'"></i> '.$label.'</button>'
                     . '</form>';
                };
                $btnDef('hadir','success','Hadir','check2-circle');
                $btnDef('izin','info','Izin','envelope-paper');
                $btnDef('sakit','secondary','Sakit','bandaid');
                if($myS): ?><div class="small text-muted mt-1">Status saya: <b><?= htmlspecialchars(strtoupper($myS)) ?></b></div><?php endif;
              else: ?><a href="/login.php" class="small">Login untuk absen</a><?php endif; ?>
            </td>
            <td data-label="Absen" class="text-end small">
              <span class="badge bg-success-subtle text-success" title="Hadir">H <?= $cnt['hadir'] ?></span>
              <?php if($cnt['telat']): ?><span class="badge bg-warning-subtle text-warning" title="Telat">T <?= $cnt['telat'] ?></span><?php endif; ?>
              <?php if($cnt['izin']): ?><span class="badge bg-info-subtle text-info" title="Izin">I <?= $cnt['izin'] ?></span><?php endif; ?>
              <?php if($cnt['sakit']): ?><span class="badge bg-secondary-subtle text-secondary" title="Sakit">S <?= $cnt['sakit'] ?></span><?php endif; ?>
              <?php if($cnt['absen']): ?><span class="badge bg-danger-subtle text-danger" title="Belum/Absen">A <?= $cnt['absen'] ?></span><?php endif; ?>
            </td>
          </tr>
          <tr class="collapse" id="jdetail<?= $jid ?>">
            <td colspan="8" class="bg-light">
              <?php if(!$absList): ?>
                <div class="text-muted small">Belum ada data absensi untuk sesi ini.</div>
              <?php else: ?>
                <div class="table-responsive"><table class="table table-sm mb-0" data-paginate="8">
                  <thead><tr><th>Anggota</th><th>Status</th><th>Catatan</th></tr></thead>
                  <tbody>
                  <?php foreach($absList as $a):
                    $st = $a['status'] ?: ((int)$a['hadir']===1?'hadir':'absen');
                    $stMap = ['hadir'=>'success','telat'=>'warning','izin'=>'info','sakit'=>'secondary','absen'=>'danger'];
                    $cls = $stMap[$st] ?? 'secondary';
                  ?>
                    <tr>
                      <td><a class="text-decoration-none" href="/user.php?id=<?= (int)$a['uid'] ?>"><?= user_name_with_avatar($a['foto_url'] ?? null, $a['nama'], false, 22) ?></a></td>
                      <td><span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?> text-uppercase"><?= htmlspecialchars($st) ?></span></td>
                      <td class="small"><?= $a['keterangan'] ? htmlspecialchars($a['keterangan']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table></div>
              <?php endif; ?>
              <?php
                $perlKey = mb_strtolower($j['jenis']);
                $perlItems = $perlByJadwal[$perlKey] ?? [];
              ?>
              <div class="mt-2"><strong class="small"><i class="bi bi-bag-check text-primary"></i> Perlengkapan yang akan dibawa member:</strong>
                <?php if(!$perlItems): ?>
                  <span class="small text-muted">— belum ada member yang mendaftarkan perlengkapan untuk <?= htmlspecialchars($j['jenis']) ?> —</span>
                <?php else: ?>
                  <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Member</th><th>Perlengkapan</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                    <?php foreach($perlItems as $pi): ?>
                      <tr>
                        <td><a class="text-decoration-none" href="/user.php?id=<?= (int)$pi['uid'] ?>"><?= user_name_with_avatar($pi['foto_url']??null,$pi['uname'],false,20) ?></a></td>
                        <td><?= htmlspecialchars($pi['item']) ?></td>
                        <td class="text-end fw-semibold"><?= (int)$pi['jumlah'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>

<?php if ($u && in_array($u['role'] ?? '', ['member','admin'], true)): ?>
    <div class="card shadow-sm" id="sec-social-feed"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-images text-primary"></i> Social Feed</span><button class="btn btn-sm btn-link p-0" data-soft-refresh title="Muat data terbaru"><i class="bi bi-arrow-clockwise"></i></button></div>
     <div class="card-body" data-live="feed">
      <?php foreach($feed as $p): ?>
        <div class="border-bottom pb-3 mb-3" id="post-<?= (int)$p['id'] ?>">
          <div class="d-flex align-items-center gap-2 mb-2">
            <a href="/user.php?id=<?= (int)$p['user_id'] ?>" class="text-decoration-none"><?= user_avatar($p['user_foto'] ?? null, $p['nama'], 32) ?></a>
            <a href="/user.php?id=<?= (int)$p['user_id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($p['nama']) ?></a>
            <small class="text-muted ms-auto"><?= date('d M H:i', strtotime($p['created_at'])) ?></small>
            <?php if($u && $u['role']==='admin'): ?>
              <form method="post" class="d-inline" data-ajax onsubmit="return confirm('Hapus postingan ini?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="post_delete">
                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0" title="Hapus postingan (admin)"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
          <?php
            // Revisi 21 Juni 2026 R4 — Multi-image: render sebagai Bootstrap carousel (slider) bila > 1 gambar.
            $imgList = [];
            if (!empty($p['images_json'])) {
                $tmp = json_decode($p['images_json'], true);
                if (is_array($tmp)) $imgList = array_values(array_filter(array_map('strval', $tmp)));
            }
            if (empty($imgList) && !empty($p['post_foto'])) $imgList = [$p['post_foto']];
            $pfDisp = !empty($p['post_foto']) ? ltrim($p['post_foto'],'/') : '';
            $isVid  = (($p['media_type'] ?? 'image')==='video') || ($pfDisp && preg_match('/\.(mp4|webm|mov|m4v|ogg)(\?|$)/i', $pfDisp));
          ?>
          <?php if ($isVid && $pfDisp): ?>
            <!-- Posting video LEGACY (sebelum R4): tetap dirender supaya data lama tidak hilang. -->
            <video src="<?= htmlspecialchars($pfDisp) ?>" controls preload="metadata" class="rounded mb-2 d-block" style="max-height:320px;max-width:100%;background:#000"></video>
          <?php elseif (count($imgList) > 1): ?>
            <?php $cid = 'pcar'.(int)$p['id']; ?>
            <div id="<?= $cid ?>" class="carousel slide rounded mb-2" data-bs-ride="false" data-bs-interval="false" style="max-width:100%">
              <div class="carousel-indicators">
                <?php foreach($imgList as $ii=>$im): ?>
                  <button type="button" data-bs-target="#<?= $cid ?>" data-bs-slide-to="<?= $ii ?>" <?= $ii===0?'class="active" aria-current="true"':'' ?> aria-label="Slide <?= $ii+1 ?>"></button>
                <?php endforeach; ?>
              </div>
              <div class="carousel-inner rounded" style="background:#000">
                <?php foreach($imgList as $ii=>$im): $imd = ltrim($im,'/'); ?>
                  <div class="carousel-item <?= $ii===0?'active':'' ?>" style="text-align:center">
                    <img src="<?= htmlspecialchars($imd) ?>" class="d-block mx-auto zoomable" data-full="<?= htmlspecialchars($imd) ?>" style="max-height:380px;max-width:100%;object-fit:contain;cursor:zoom-in" onerror="this.style.display='none'">
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#<?= $cid ?>" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Sebelumnya</span></button>
              <button class="carousel-control-next" type="button" data-bs-target="#<?= $cid ?>" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Berikutnya</span></button>
              <div class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75 small"><i class="bi bi-images"></i> <span class="js-cur">1</span>/<?= count($imgList) ?></div>
            </div>
            <script>
              (function(){
                var el = document.getElementById('<?= $cid ?>'); if (!el) return;
                el.addEventListener('slid.bs.carousel', function(ev){
                  var n = el.querySelector('.js-cur'); if (n) n.textContent = (ev.to+1);
                });
              })();
            </script>
          <?php elseif (count($imgList) === 1): $imd = ltrim($imgList[0],'/'); ?>
            <img src="<?= htmlspecialchars($imd) ?>" data-full="<?= htmlspecialchars($imd) ?>" class="rounded mb-2 zoomable d-block" style="max-height:380px;max-width:100%;width:auto;object-fit:cover;cursor:zoom-in;" onerror="this.style.display='none'">
          <?php endif; ?>
          <div class="mb-2"><?= nl2br(render_tags_and_mentions(htmlspecialchars($p['caption'] ?? ''))) ?></div>
          <div class="d-flex flex-wrap gap-2 small">
            <?php if($u): ?>
            <form method="post" class="d-inline" data-ajax><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="like"><input type="hidden" name="post_id" value="<?= $p['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-heart<?= ((int)$p['liked_by_me']>0?'-fill':'') ?>"></i> <?= (int)$p['likes'] ?></button></form>
            <form method="post" action="/bookmark.php" class="d-inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" title="Simpan ke bookmark"><i class="bi bi-bookmark-plus"></i></button>
            </form>
            <form method="post" action="/repost.php" class="d-inline" onsubmit="this.querySelector('[name=caption]').value=prompt('Tambahkan komentar (opsional):','')||''">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="caption" value="">
              <button class="btn btn-sm btn-outline-info" title="Repost"><i class="bi bi-arrow-repeat"></i></button>
            </form>
            <a href="/report.php?post_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Laporkan"><i class="bi bi-flag"></i></a>
            <?php endif; ?>
            <span class="text-muted align-self-center"><i class="bi bi-chat"></i> <?= (int)$p['comments'] ?></span>
          </div>
          <?php $pcs = $commentsByPost[(int)$p['id']] ?? []; if($pcs): ?>
          <div class="mt-2 ps-2 border-start">
            <?php foreach($pcs as $pc):
                // Revisi 19 Juni 2026 Part R — hanya pemilik komentar yang boleh mengedit.
                $canEdit = $u && (int)$pc['user_id']===(int)$u['id'];
            ?>
              <div class="d-flex align-items-start gap-2 mb-1" id="cmt-<?= (int)$pc['id'] ?>">
                <?= user_avatar($pc['foto_url'] ?? null, $pc['nama'], 22) ?>
                <div class="small flex-grow-1"><strong><?= htmlspecialchars($pc['nama']) ?></strong>
                  <span class="text-muted ms-1" style="font-size:.7rem"><?= date('d M H:i', strtotime($pc['created_at'])) ?><?php if(!empty($pc['updated_at'])): ?> · diedit<?php endif; ?></span><br>
                  <span class="cmt-body"><?= nl2br(htmlspecialchars($pc['isi'])) ?></span>
                  <?php if($canEdit): ?>
                  <!-- Revisi 19 Juni 2026 Part Q — edit komentar oleh pemilik/admin -->
                  <form method="post" class="d-none cmt-edit-form mt-1" data-ajax>
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="_action" value="comment_edit">
                    <input type="hidden" name="comment_id" value="<?= (int)$pc['id'] ?>">
                    <div class="input-group input-group-sm">
                      <input class="form-control" name="isi" maxlength="300" value="<?= htmlspecialchars($pc['isi']) ?>" required>
                      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i></button>
                      <button class="btn btn-outline-secondary" type="button" onclick="toggleCmtEdit(<?= (int)$pc['id'] ?>,false)"><i class="bi bi-x"></i></button>
                    </div>
                  </form>
                  <?php endif; ?>
                </div>
                <?php if($canEdit): ?>
                  <button class="btn btn-sm btn-link text-secondary p-0" type="button" title="Edit komentar" onclick="toggleCmtEdit(<?= (int)$pc['id'] ?>,true)"><i class="bi bi-pencil"></i></button>
                <?php endif; ?>
                <?php if($u && $u['role']==='admin'): ?>
                  <form method="post" class="d-inline" data-ajax onsubmit="return confirm('Hapus komentar ini?')">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="_action" value="comment_delete">
                    <input type="hidden" name="comment_id" value="<?= (int)$pc['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-0" title="Hapus komentar (admin)"><i class="bi bi-x-lg"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <script>
            function toggleCmtEdit(id, show){
              var box = document.getElementById('cmt-'+id); if(!box) return;
              var body = box.querySelector('.cmt-body');
              var form = box.querySelector('.cmt-edit-form');
              if(!body||!form) return;
              if(show){ body.classList.add('d-none'); form.classList.remove('d-none'); form.querySelector('input[name=isi]').focus(); }
              else { body.classList.remove('d-none'); form.classList.add('d-none'); }
            }
            </script>
          </div>
          <?php endif; ?>
          <?php if($u): ?>
          <form method="post" class="d-flex gap-2 mt-2" data-ajax data-ajax-label="Mengirim komentar...">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="comment"><input type="hidden" name="post_id" value="<?= $p['id'] ?>">
            <input class="form-control form-control-sm" name="isi" placeholder="Tulis komentar..." maxlength="300" required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; if(!$feed): ?><p class="text-muted small text-center mb-0">Belum ada postingan.</p><?php endif; ?>
      <?php if ($feedTotal > $feedPerPage):
        $fpQS = function($n){ $q = $_GET; $q['fp'] = $n; return '/index.php?'.http_build_query($q).'#feed'; };
      ?>
      <nav class="d-flex justify-content-between align-items-center pt-2 border-top mt-2" aria-label="Navigasi feed">
        <?php if ($feedPage > 1): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($fpQS($feedPage-1)) ?>"><i class="bi bi-chevron-left"></i> Sebelumnya</a>
        <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled"><i class="bi bi-chevron-left"></i> Sebelumnya</span><?php endif; ?>
        <span class="small text-muted">Halaman <?= $feedPage ?> / <?= $feedPages ?></span>
        <?php if ($feedPage < $feedPages): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($fpQS($feedPage+1)) ?>">Berikutnya <i class="bi bi-chevron-right"></i></a>
        <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Berikutnya <i class="bi bi-chevron-right"></i></span><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div></div>
<?php else: ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted small py-4">
      <i class="bi bi-lock fs-3 d-block mb-2"></i>
      <strong>Social Feed</strong> hanya bisa ditampilkan untuk <strong>member / admin terdaftar</strong>.
      <div class="mt-2"><a href="/login.php" class="btn btn-sm btn-primary">Login</a> <a href="/register.php" class="btn btn-sm btn-outline-primary">Daftar</a></div>
    </div></div>
<?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm mb-3" id="sec-online"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-broadcast text-success me-1"></i> Online (<?= count($onlineMembers) ?>)</span><button class="btn btn-sm btn-link p-0" data-soft-refresh title="Muat data terbaru"><i class="bi bi-arrow-clockwise"></i></button></div>
      <ul class="list-group list-group-flush" data-live="online">
        <?php foreach($onlineMembers as $om): ?>
          <li class="list-group-item d-flex align-items-center justify-content-between">
            <a href="/user.php?id=<?= (int)$om['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($om['foto_url'] ?? null, $om['nama'], true, 28) ?></a>
            <span class="d-flex align-items-center gap-2">
              <?php if($u && (int)$om['id'] !== (int)$u['id']): ?>
                <a href="/dm.php?u=<?= (int)$om['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Chat"><i class="bi bi-chat-dots"></i></a>
              <?php endif; ?>
              <small class="text-muted"><?= date('H:i', strtotime($om['last_seen'])) ?></small>
            </span>
          </li>
        <?php endforeach; if(!$onlineMembers): ?><li class="list-group-item text-muted small">Belum ada yang online.</li><?php endif; ?>
      </ul></div>

    <div class="card shadow-sm mb-3" id="forum"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-chat-square-text text-primary me-1"></i> Forum Komunitas</span><button class="btn btn-sm btn-link p-0" data-soft-refresh title="Muat data terbaru"><i class="bi bi-arrow-clockwise"></i></button></div>
    <div class="card-body" data-live="forum">
      <?php if($u): ?>
      <form method="post" class="d-flex gap-2 mb-3" data-ajax data-ajax-label="Mengirim pesan...">
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
          <?php if(!empty($c['updated_at'])): ?><small class="chat-meta fst-italic">(diedit)</small><?php endif; ?>
          <div class="d-flex gap-2 mt-1 small">
            <?php if($u): ?>
            <form method="post" class="d-inline" data-ajax><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $c['id'] ?>"><input type="hidden" name="val" value="1">
              <button class="btn btn-sm btn-link text-success p-0 me-2"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$c['likes'] ?></button></form>
            <form method="post" class="d-inline" data-ajax><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $c['id'] ?>"><input type="hidden" name="val" value="-1">
              <button class="btn btn-sm btn-link text-danger p-0 me-2"><i class="bi bi-hand-thumbs-down"></i> <?= (int)$c['dislikes'] ?></button></form>
            <button type="button" class="btn btn-sm btn-link p-0" onclick="document.getElementById('reply<?= $c['id'] ?>').classList.toggle('d-none')"><i class="bi bi-reply"></i> Reply</button>
            <?php if((int)$c['user_id']===(int)$u['id'] || $u['role']==='admin'): ?>
              <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-primary" onclick="document.getElementById('editChat<?= $c['id'] ?>').classList.toggle('d-none')" title="Edit pesan"><i class="bi bi-pencil"></i> Edit</button>
            <?php endif; ?>
            <?php if($u['role']==='admin'): ?>
            <form method="post" class="d-inline" data-ajax onsubmit="return confirm('Hapus pesan ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-link text-danger p-0 ms-2" title="Hapus (admin)"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
            <?php else: ?>
              <span class="text-muted"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$c['likes'] ?> · <i class="bi bi-hand-thumbs-down"></i> <?= (int)$c['dislikes'] ?></span>
            <?php endif; ?>
          </div>
          <?php if($u): ?>
          <form method="post" id="reply<?= $c['id'] ?>" class="d-flex gap-2 mt-2 d-none" data-ajax data-ajax-label="Mengirim balasan...">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_post"><input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
            <input class="form-control form-control-sm" name="pesan" placeholder="Balas pesan..." maxlength="500" required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button>
          </form>
          <?php if((int)$c['user_id']===(int)$u['id'] || $u['role']==='admin'): ?>
          <form method="post" id="editChat<?= $c['id'] ?>" class="d-flex gap-2 mt-2 d-none" data-ajax data-ajax-label="Menyimpan pesan...">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_edit"><input type="hidden" name="id" value="<?= $c['id'] ?>">
            <input class="form-control form-control-sm" name="pesan" value="<?= htmlspecialchars(strip_tags($c['pesan'])) ?>" maxlength="500" required>
            <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
          <?php foreach($rs as $rep): ?>
            <div class="chat-bubble chat-reply mt-2">
              <div class="d-flex align-items-center gap-2 mb-1">
                <?= user_avatar($rep['foto_url'] ?? null, $rep['nama'] ?? '?', 20) ?>
                <span class="fw-semibold small"><?= htmlspecialchars($rep['nama'] ?? 'Anon') ?></span>
                <small class="chat-meta"><?= date('d M H:i', strtotime($rep['created_at'])) ?></small>
                <?php if(!empty($rep['updated_at'])): ?><small class="chat-meta fst-italic">(diedit)</small><?php endif; ?>
              </div>
              <div class="small"><?= sanitize_html($rep['pesan']) ?></div>
              <?php if($u): ?>
              <div class="d-flex gap-2 mt-1 small">
                <form method="post" class="d-inline" data-ajax><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $rep['id'] ?>"><input type="hidden" name="val" value="1">
                  <button class="btn btn-sm btn-link text-success p-0 me-2"><i class="bi bi-hand-thumbs-up"></i> <?= (int)$rep['likes'] ?></button></form>
                <form method="post" class="d-inline" data-ajax><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_react"><input type="hidden" name="chat_id" value="<?= $rep['id'] ?>"><input type="hidden" name="val" value="-1">
                  <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-hand-thumbs-down"></i> <?= (int)$rep['dislikes'] ?></button></form>
                <?php if((int)$rep['user_id']===(int)$u['id'] || $u['role']==='admin'): ?>
                  <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-primary" onclick="document.getElementById('editChat<?= $rep['id'] ?>').classList.toggle('d-none')" title="Edit balasan"><i class="bi bi-pencil"></i> Edit</button>
                <?php endif; ?>
                <?php if($u['role']==='admin'): ?>
                  <form method="post" class="d-inline" data-ajax onsubmit="return confirm('Hapus balasan ini?')">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_delete"><input type="hidden" name="id" value="<?= (int)$rep['id'] ?>">
                    <button class="btn btn-sm btn-link text-danger p-0 ms-2" title="Hapus (admin)"><i class="bi bi-trash"></i></button>
                  </form>
                <?php endif; ?>
              </div>
              <?php if((int)$rep['user_id']===(int)$u['id'] || $u['role']==='admin'): ?>
              <form method="post" id="editChat<?= $rep['id'] ?>" class="d-flex gap-2 mt-2 d-none" data-ajax data-ajax-label="Menyimpan balasan...">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="chat_edit"><input type="hidden" name="id" value="<?= (int)$rep['id'] ?>">
                <input class="form-control form-control-sm" name="pesan" value="<?= htmlspecialchars(strip_tags($rep['pesan'])) ?>" maxlength="500" required>
                <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
              </form>
              <?php endif; ?>
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
  <form class="modal-content" method="post" enctype="multipart/form-data" id="postNewForm" data-ajax data-ajax-label="Mengunggah & mengoptimasi foto...">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="post_new">
    <div class="modal-header"><h5 class="modal-title">Posting baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <label class="form-label small">Tipe</label>
      <select name="jenis" class="form-select mb-2"><option value="post">Post (feed)</option><option value="story">Story (24 jam)</option></select>
      <!-- Revisi 21 Juni 2026 R4 — Multi-image post (mengganti posting video) -->
      <label class="form-label small">Foto (boleh pilih beberapa, maks 10)</label>
      <input type="file" name="fotos[]" accept="image/*" multiple class="form-control mb-1" id="postFotosInput">
      <div class="form-text small">Pilih satu atau beberapa gambar sekaligus. Akan ditampilkan sebagai slider di feed bila lebih dari satu.</div>
      <div id="postFotosPreview" class="d-flex flex-wrap gap-2 mb-2"></div>
      <div id="postFotosInfo" class="small text-muted"></div>
      <label class="form-label small">Caption</label>
      <textarea name="caption" class="form-control" rows="3" maxlength="500" placeholder="Tulis caption..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" id="postSubmitBtn"><i class="bi bi-send"></i> Posting</button>
    </div>
  </form>
</div></div>
<script>
/* Revisi 21 Juni 2026 R4 — Preview multi-image (mengganti preview video). */
document.addEventListener('DOMContentLoaded', function(){
  var fi = document.getElementById('postFotosInput');
  var pv = document.getElementById('postFotosPreview');
  var info = document.getElementById('postFotosInfo');
  if (!fi) return;
  fi.addEventListener('change', function(){
    pv.innerHTML = ''; info.textContent = '';
    var files = Array.from(this.files || []);
    if (files.length > 10) {
      alert('Maksimum 10 gambar per posting. Hanya 10 pertama yang akan diunggah.');
    }
    files = files.slice(0, 10);
    var totalKB = 0;
    files.forEach(function(f){
      if (!/^image\//.test(f.type)) return;
      totalKB += f.size/1024;
      var img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.style.cssText = 'width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--bs-border-color,#dee2e6)';
      img.alt = f.name;
      pv.appendChild(img);
    });
    info.textContent = files.length+' gambar dipilih · total '+(totalKB/1024).toFixed(2)+' MB';
  });
});
</script>
<?php endif; ?>

<?php /* Revisi 6 Juni 2026: Modal QR Check-in dihapus. */ ?>

<!-- Revisi 19 Juni 2026 Part Q — Modal Story Hari Ini (lihat foto/video + like + komentar) -->
<div class="modal fade" id="storyModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg">
  <div class="modal-content">
    <div class="modal-header py-2">
      <h6 class="modal-title"><i class="bi bi-collection-play text-primary"></i> Story · <span id="storyName"></span></h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="text-center mb-2" id="storyMediaBox">
        <img id="storyImg" src="" alt="" style="max-width:100%;max-height:55vh;border-radius:8px;display:none">
      </div>
      <div id="storyCaption" class="small mb-1"></div>
      <div class="small text-muted mb-2"><i class="bi bi-clock"></i> <span id="storyTime"></span></div>

      <?php if($u): ?>
      <!-- Aksi like + jumlah komentar -->
      <div class="d-flex gap-2 align-items-center border-top border-bottom py-2 mb-2">
        <form method="post" class="d-inline" data-ajax id="storyLikeForm">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="like">
          <input type="hidden" name="post_id" id="storyLikePostId" value="">
          <button class="btn btn-sm btn-outline-danger" id="storyLikeBtn" type="submit"><i class="bi bi-heart" id="storyLikeIcon"></i> <span id="storyLikeCount">0</span></button>
        </form>
        <span class="text-muted small"><i class="bi bi-chat"></i> <span id="storyCommentCount">0</span> komentar</span>
        <span class="ms-auto small text-muted"><i class="bi bi-eye"></i> <span id="storyViewCount">0</span></span>
      </div>

      <!-- Daftar komentar -->
      <div id="storyComments" class="mb-2" style="max-height:30vh;overflow:auto"></div>

      <!-- Tambah komentar -->
      <form method="post" class="d-flex gap-2" data-ajax id="storyCommentForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="comment">
        <input type="hidden" name="post_id" id="storyCommentPostId" value="">
        <input class="form-control form-control-sm" name="isi" maxlength="300" placeholder="Tulis komentar untuk story…" required>
        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-send"></i></button>
      </form>
      <?php endif; ?>

      <div class="mt-3 border-top pt-2">
        <div class="small fw-semibold mb-1"><i class="bi bi-people"></i> Dilihat oleh</div>
        <div id="storyViewers" class="small"><span class="text-muted">—</span></div>
      </div>
    </div>
  </div>
</div></div>



<script>
let _qrM=null,_stM=null;
function showQR(token, jenis, tanggal, tempat){
  if(!_qrM) _qrM=new bootstrap.Modal(document.getElementById('qrModal'));
  const src='https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='+encodeURIComponent(token);
  document.getElementById('qrTitle').textContent=jenis+' · '+tanggal;
  document.getElementById('qrMeta').textContent=tempat;
  document.getElementById('qrImg').src=src;
  document.getElementById('qrToken').textContent=token;
  // Unduh: ambil sebagai blob agar nama file rapi
  const a=document.getElementById('qrDownload');
  a.href=src; a.download='qr-'+(jenis||'sesi')+'-'+(tanggal||'')+'.png';
  a.onclick=async function(ev){
    ev.preventDefault();
    try{
      const r=await fetch(src); const b=await r.blob();
      const url=URL.createObjectURL(b);
      const dl=document.createElement('a'); dl.href=url; dl.download=a.download; dl.click();
      setTimeout(()=>URL.revokeObjectURL(url),1500);
    }catch(e){ window.open(src,'_blank'); }
  };
  _qrM.show();
}
function showStory(d){
  if(!_stM) _stM=new bootstrap.Modal(document.getElementById('storyModal'));
  document.getElementById('storyName').textContent=d.nama||'';
  const img=document.getElementById('storyImg');
  /* Revisi 19 Juni 2026 Part O #6 — dukung story video */
  var box = img && img.parentElement;
  var oldVid = box ? box.querySelector('video.story-video') : null;
  if (oldVid) oldVid.remove();
  if(d.foto && d.is_video){
    img.style.display='none';
    if (box){
      var v=document.createElement('video'); v.className='story-video'; v.controls=true; v.autoplay=true;
      v.src=d.foto; v.style.cssText='max-width:100%;max-height:70vh;background:#000;border-radius:8px';
      box.insertBefore(v, img);
    }
  } else if(d.foto){
    img.src=d.foto; img.style.display='block';
  } else { img.style.display='none'; }
  document.getElementById('storyCaption').textContent=d.caption||'';
  document.getElementById('storyTime').textContent=d.created_at||'';
  document.getElementById('storyViewCount').textContent='0';
  document.getElementById('storyViewers').innerHTML='<span class="text-muted">memuat…</span>';
  // Revisi 19 Juni 2026 Part Q — populasi like + komentar story
  var lpId = document.getElementById('storyLikePostId');
  var cpId = document.getElementById('storyCommentPostId');
  var lcEl = document.getElementById('storyLikeCount');
  var liEl = document.getElementById('storyLikeIcon');
  var ccEl = document.getElementById('storyCommentCount');
  var clEl = document.getElementById('storyComments');
  if (lpId) lpId.value = d.id || '';
  if (cpId) cpId.value = d.id || '';
  if (lcEl) lcEl.textContent = d.likes || 0;
  if (liEl) liEl.className = 'bi bi-heart' + ((d.liked_by_me>0)?'-fill':'');
  if (ccEl) ccEl.textContent = d.comments_count || 0;
  if (clEl) {
    var arr = d.comments || [];
    if (!arr.length) { clEl.innerHTML = '<div class="text-muted small text-center py-2">Belum ada komentar.</div>'; }
    else {
      clEl.innerHTML = arr.map(function(c){
        var ava = c.foto ? '<img src="'+c.foto+'" style="width:24px;height:24px;border-radius:50%;object-fit:cover;margin-right:6px">' :
                   '<span class="d-inline-block rounded-circle bg-secondary text-white text-center" style="width:24px;height:24px;line-height:24px;font-size:.7rem;margin-right:6px">'+((c.nama||'?').substr(0,1).toUpperCase())+'</span>';
        var t = c.created_at ? new Date(c.created_at.replace(' ','T')) : null;
        var ts = t ? t.toLocaleString('id-ID',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) : '';
        var esc = function(s){return String(s||'').replace(/[&<>"']/g,function(m){return ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[m];});};
        return '<div class="d-flex align-items-start mb-2">'+ava+'<div class="small flex-grow-1"><strong>'+esc(c.nama)+'</strong> <span class="text-muted" style="font-size:.7rem">'+ts+'</span><br>'+esc(c.isi)+'</div></div>';
      }).join('');
    }
  }
  _stM.show();
  if (d.id){
    var fd=new FormData(); fd.append('post_id', d.id);
    fetch('/api_story_view.php',{method:'POST',body:fd,credentials:'same-origin'})
      .then(r=>r.json()).then(function(j){
        if(!j||!j.ok){ document.getElementById('storyViewers').innerHTML='<span class="text-muted">—</span>'; return; }
        document.getElementById('storyViewCount').textContent = j.total||0;
        var html = (j.viewers||[]).map(function(v){
          var ava = v.foto_url ? '<img src="'+v.foto_url+'" style="width:24px;height:24px;border-radius:50%;object-fit:cover;margin-right:6px">' : '';
          var t = new Date(v.viewed_at);
          var hari=['Min','Sen','Sel','Rab','Kam','Jum','Sab'][t.getDay()];
          return '<div class="d-flex align-items-center justify-content-between border-bottom py-1">'+
                 '<span>'+ava+'<strong>'+(v.nama||'')+'</strong></span>'+
                 '<small class="text-muted">'+hari+', '+t.toLocaleDateString('id-ID')+' '+t.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+'</small>'+
                 '</div>';
        }).join('');
        document.getElementById('storyViewers').innerHTML = html || '<span class="text-muted">Belum ada yang melihat.</span>';
      }).catch(()=>{ document.getElementById('storyViewers').innerHTML='<span class="text-muted">—</span>'; });
  }
}
</script>

<?php if($u): ?>
<script>
// === PWA Push sederhana (tanpa pihak ke-3) ===
// Pakai Notification API + polling /api_notif_poll.php tiap 60 detik.
(function(){
  const btn = document.getElementById('btnEnableNotif');
  function updateLabel(){
    if (!btn) return;
    if (!('Notification' in window)) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-bell-slash"></i> Notifikasi tidak didukung'; return; }
    if (Notification.permission === 'granted') btn.innerHTML = '<i class="bi bi-bell-fill text-success"></i> Notifikasi aktif';
    else if (Notification.permission === 'denied') { btn.disabled = true; btn.innerHTML = '<i class="bi bi-bell-slash"></i> Notifikasi diblokir browser'; }
    else btn.innerHTML = '<i class="bi bi-bell"></i> Aktifkan Notifikasi';
  }
  updateLabel();
  if (btn) btn.addEventListener('click', async () => {
    try { await Notification.requestPermission(); } catch(e){}
    updateLabel();
    if (Notification.permission === 'granted') startPoll();
  });

  async function poll(){
    if (Notification.permission !== 'granted') return;
    try {
      const r = await fetch('/api_notif_poll.php', { credentials: 'same-origin' });
      if (!r.ok) return;
      const data = await r.json();
      (data.items || []).forEach(n => {
        const opt = { body: n.isi || '', icon: '/assets/icon-192.png', badge: '/assets/icon-192.png', tag: 'hapfam-'+n.id, data: { url: n.url || '/' } };
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
          navigator.serviceWorker.ready.then(reg => reg.showNotification(n.judul || 'HapFam', opt));
        } else {
          try { new Notification(n.judul || 'HapFam', opt); } catch(e){}
        }
      });
    } catch(e){}
  }
  let _t = null;
  function startPoll(){ if (_t) return; poll(); _t = setInterval(poll, 15000); }
  document.addEventListener('DOMContentLoaded', () => {
    if ('Notification' in window && Notification.permission === 'granted') startPoll();
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
  });
})();
</script>
<?php endif; ?>


<!-- ============ Revisi 4 Jun 2026: Reorder layout via JS ============ -->
<script>
(function(){
  function $(id){ return document.getElementById(id); }
  document.addEventListener('DOMContentLoaded', function(){
    try {
      var dash    = $('sec-dashboard-stats');
      var story   = $('feed');             // Story Hari Ini
      var social  = $('sec-social-feed');  // Social Feed
      var forum   = $('forum');            // Forum Komunitas
      var online  = $('sec-online');
      var event_  = $('sec-event-terdekat');
      var jadwal  = $('sec-jadwal-terdekat');
      var kabari  = $('sec-kabari');
      function moveAfter(node, ref){ if(node && ref && ref.parentNode){ ref.parentNode.insertBefore(node, ref.nextSibling); } }
      var anchor = dash;
      // Revisi 12 Juni 2026: Online & Jadwal Terdekat naik ke atas, di atas Kabari Member.
      // Urutan baru: dashboard -> online -> jadwal -> kabari -> story -> social feed -> forum -> event
      [online, jadwal, kabari, story, social, forum, event_].forEach(function(el){
        if (el && anchor) { moveAfter(el, anchor); anchor = el; }
      });
    } catch(e){ console.warn('reorder failed', e); }
  });
})();
</script>

<?php render_index_blok('bottom'); include __DIR__.'/includes/footer.php'; ?>

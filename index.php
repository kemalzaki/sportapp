<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/migrations_v7.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/scope.php'; // Revisi Juli 2026 R10 — helper superduper
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
    // Revisi Juli 2026 R11 — hanya kembalikan JSON bila benar-benar XHR/fetch
    // (header X-Requested-With). Hidden field '_ajax' saja tidak cukup: bila
    // interceptor JS gagal berjalan, browser akan submit form biasa & JSON
    // response terbuka sebagai halaman/tab baru. Sekarang default: redirect.
    $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $isAjaxPost  = in_array($xrw, ['xmlhttprequest','fetch'], true);
    // Revisi Juli 2026 R9 — naikkan batas juga untuk multi-image (fotos[])
    // supaya upload beberapa foto ke ImageKit tidak putus (penyebab "Failed to fetch").
    if ($a === 'post_new') {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        @ignore_user_abort(true);
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
            $imageKit = null;
            try {
                require_once __DIR__.'/config/imagekit.php';
                global $imageKit;
            } catch (Throwable $e) {
                $postNewErr = 'Konfigurasi upload gambar (ImageKit) belum lengkap: '.$e->getMessage();
            }
            if ($imageKit) {
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
                        } else {
                            $errMsg = is_object($uploadFile->error ?? null) ? json_encode($uploadFile->error) : 'unknown';
                            $postNewErr = $postNewErr ?: ('Gambar #'.($idx+1).' gagal upload: '.$errMsg);
                        }
                    } catch (Throwable $e) {
                        $postNewErr = $postNewErr ?: ('Gambar #'.($idx+1).' error: '.$e->getMessage());
                    }
                }
                if (!empty($imagesUrls)) {
                    $fotoUrl = $imagesUrls[0]; // first image jadi cover (kompatibilitas mundur)
                    $mediaType = 'image';
                    $postNewErr = ''; // sukses paling tidak satu gambar terupload
                }
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
            // Revisi 24 Juni 2026 — kirim notifikasi HP (in-app + FCM) ke semua member lain
            // setiap ada Story / Social Feed baru, sama seperti notifikasi input absensi.
            try {
                $judulNotif = $jenis === 'story'
                    ? '📸 '.$u['nama'].' membagikan Story baru'
                    : '📝 '.$u['nama'].' membuat postingan baru';
                $isiNotif = $caption !== '' ? mb_substr($caption, 0, 120) : 'Buka aplikasi untuk melihatnya.';
                $urlNotif = $jenis === 'story' ? '/index.php#feed' : '/index.php#social';
                // Revisi Juli 2026 R9 — notifikasi HANYA ke anggota komunitas yang sama dengan pembuat post.
                require_once __DIR__.'/includes/scope.php';
                $senderKomIds = scope_current_user_kom_ids();
                if ($senderKomIds) {
                    $kArr = '{'.implode(',', array_map('intval', $senderKomIds)).'}';
                    $targets = db_all(
                        "SELECT DISTINCT u.id FROM users u
                         LEFT JOIN user_komunitas uk ON uk.user_id = u.id
                         WHERE u.role IN ('member','admin')
                           AND (u.komunitas_id = ANY($1::int[]) OR uk.komunitas_id = ANY($1::int[]))
                           AND u.id <> $2",
                        [$kArr, (int)$u['id']]);
                } else {
                    // pembuat post tidak punya komunitas → kirim hanya ke dirinya (skip)
                    $targets = [];
                }
                foreach ($targets as $t) {
                    notify((int)$t['id'], $jenis === 'story' ? 'story' : 'post', $judulNotif, $isiNotif, $urlNotif);
                }
            } catch (Throwable $e) { /* silent */ }
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

// Revisi Juli 2026 R8 #2 — semua statistik & daftar DIFILTER per komunitas user login.
require_once __DIR__ . '/includes/scope.php';
$__vids  = scope_user_ids_sql_array();
$__vkids = scope_kom_ids_sql_array();
$__isSuper = scope_is_super();
$totalSesi    = (int) db_val(
    "SELECT COUNT(*) FROM jadwal j WHERE (\$1=1 OR j.komunitas_id = ANY(\$2::int[]))",
    [$__isSuper?1:0, $__vkids]);
// Revisi R9 Juli 2026 — Total Hadir dihitung dari absensi user scope PADA jadwal komunitas.
$totalHadir   = (int) db_val(
    "SELECT COUNT(*) FROM absensi a
       JOIN jadwal j ON j.id=a.jadwal_id
      WHERE a.hadir=1
        AND a.user_id = ANY(\$1::int[])
        AND (\$2=1 OR j.komunitas_id = ANY(\$3::int[]))",
    [$__vids, $__isSuper?1:0, $__vkids]);
$totalMember  = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND id = ANY($1::int[])", [$__vids]);
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
$memberAktif    = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND id = ANY($1::int[]) AND $aktifExpr", [$__vids]);
$memberNonaktif = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND id = ANY($1::int[]) AND NOT $aktifExpr", [$__vids]);

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

// Revisi R7 #5 — batasi Jadwal Terdekat sesuai komunitas user (superadmin lihat semua)
require_once __DIR__ . '/includes/scope.php';
$__jadwalKomFilter = scope_is_super() ? '' : ' AND (j.komunitas_id = ANY($1::int[]))';
$__jadwalKomParams = scope_is_super() ? [] : [scope_kom_ids_sql_array()];
$jadwalTerdekat = db_all("SELECT j.*, u.nama AS koordinator, u.foto_url AS koord_foto, t.nama AS tim_nama,
                          tp.lat AS tp_lat, tp.lng AS tp_lng, tp.nama AS tp_nama,
                          tp.gpx_path AS tp_gpx_path, tp.run_route_id AS tp_run_route_id,
                          jj.nama AS jj_nama, jj.warna_bg AS jj_bg, jj.warna_text AS jj_text,
                          k.nama AS kom_nama, k.warna AS kom_warna, k.kota AS kom_kota
                          FROM jadwal j
                          LEFT JOIN users u ON u.id=j.koordinator_id
                          LEFT JOIN tim t ON t.id=j.tim_id
                          LEFT JOIN tempat tp ON tp.id=j.tempat_id
                          LEFT JOIN jenis_jadwal jj ON jj.id=j.jenis_jadwal_id
                          LEFT JOIN komunitas k ON k.id=j.komunitas_id
                          WHERE tanggal >= CURRENT_DATE $__jadwalKomFilter ORDER BY tanggal ASC LIMIT 5", $__jadwalKomParams);
// Revisi 22 Juni 2026 — pra-resolve GeoJSON rute Hiking (dari run_routes) untuk ditampilkan di Jadwal Terdekat
$jadwalRouteGeo = [];
foreach ($jadwalTerdekat as $__j) {
  if (mb_strtolower(trim((string)$__j['jenis'])) !== 'hiking') continue;
  if (empty($__j['tp_run_route_id'])) continue;
  try {
    $__sr = db_one("SELECT geojson FROM run_routes WHERE id=$1", [(int)$__j['tp_run_route_id']]);
    if ($__sr && !empty($__sr['geojson'])) $jadwalRouteGeo[(int)$__j['id']] = $__sr['geojson'];
  } catch (Throwable $e) {}
}
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
// Member baru 7 hari terakhir (sapa) — DIFILTER per komunitas (Revisi Juli 2026 #2)
// Hanya menampilkan member baru dari komunitas yang sama dengan user login.
$newMembers = [];
$hideSapaForMe = 0;
if ($u) {
  $_p = islami_pref((int)$u['id']);
  $hideSapaForMe = (int)($_p['hide_sapa'] ?? 0);
  if (!$hideSapaForMe) {
    $__sapaVids = scope_user_ids_sql_array(); // int[] literal komunitas scope
    $newMembers = db_all(
      "SELECT id, nama, foto_url, created_at FROM users
       WHERE created_at >= NOW() - INTERVAL '7 days'
         AND role IN ('member','admin')
         AND id <> $1
         AND id = ANY($2::int[])
         AND id NOT IN (SELECT target_user_id FROM sapa_log WHERE sender_user_id=$1)
       ORDER BY created_at DESC LIMIT 10",
      [(int)$u['id'], $__sapaVids]
    );
  }
} else {
  // Guest: tidak ada scope komunitas → tampilkan kosong (privasi antar-komunitas).
  $newMembers = [];
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
     FROM jadwal j
     WHERE j.tanggal >= CURRENT_DATE
       AND (\$1=1 OR j.komunitas_id = ANY(\$2::int[]))
     ORDER BY j.tanggal ASC, j.jam_mulai ASC NULLS LAST LIMIT 1",
    [$__isSuper?1:0, $__vkids]
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
      // Revisi Juli 2026 R8 #2 — fallback list Kabari Member DIFILTER per komunitas.
      $kabariKawan = db_all(
        "SELECT id, nama, foto_url,
                COALESCE(NULLIF(wa,''), NULLIF(nomor_wa,'')) AS nomor_wa
         FROM users
         WHERE role = 'member' AND id = ANY(\$1::int[])
         ORDER BY nama LIMIT 200",
        [$__vids]
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

// Revisi 24 Juni 2026 — Anggota hadir EKSTERNAL (member_eksternal) per jadwal terdekat,
// supaya ikut tampil di kartu "Jadwal Terdekat" (bukan hanya tamu internal).
$tamuByJadwal = [];
if ($_jids) {
    try {
        $_tRows = db_all(
          "SELECT me.jadwal_id, me.nama_tamu, me.dibawa_oleh_id, u.nama AS pembawa, u.foto_url AS pembawa_foto
           FROM member_eksternal me
           LEFT JOIN users u ON u.id = me.dibawa_oleh_id
           WHERE me.jadwal_id = ANY($1::int[])
           ORDER BY me.jadwal_id, me.nama_tamu",
          ['{'.implode(',', $_jids).'}']);
        foreach ($_tRows as $tr) $tamuByJadwal[(int)$tr['jadwal_id']][] = $tr;
    } catch (Throwable $e) {}
}
$onlineMembers = db_all("SELECT id, nama, foto_url, last_seen FROM users
                         WHERE last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL '2 minutes'
                           AND id = ANY($1::int[]) ORDER BY nama", [$__vids]);

// Forum: ambil top-level + replies, plus aggregate like/dislike
// Revisi Juli 2026 R8 #2 — Forum Komunitas hanya menampilkan chat dari user dalam scope komunitas.
$chats = db_all("SELECT c.*, u.nama, u.foto_url,
                   COALESCE((SELECT SUM(CASE WHEN val=1 THEN 1 ELSE 0 END) FROM chat_reactions r WHERE r.chat_id=c.id),0) AS likes,
                   COALESCE((SELECT SUM(CASE WHEN val=-1 THEN 1 ELSE 0 END) FROM chat_reactions r WHERE r.chat_id=c.id),0) AS dislikes
                 FROM chat_forum c LEFT JOIN users u ON u.id=c.user_id
                 WHERE c.user_id IS NULL OR c.user_id = ANY(\$1::int[])
                 ORDER BY c.created_at DESC LIMIT 60", [$__vids]);
// kelompokkan reply per parent
$top = []; $replies = [];
foreach ($chats as $c) {
    if (empty($c['parent_id'])) $top[] = $c;
    else $replies[(int)$c['parent_id']][] = $c;
}

// Social feed
$uidMe = (int)($u['id'] ?? 0);
// Revisi Juli 2026 R8 #2 — Story Hari Ini per komunitas.
$stories = db_all("SELECT p.*, u.nama, u.foto_url AS user_foto,
                          (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS likes,
                          (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=\$1) AS liked_by_me,
                          (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comments
                   FROM posts p JOIN users u ON u.id=p.user_id
                   WHERE p.jenis='story' AND (p.expired_at IS NULL OR p.expired_at > now())
                     AND p.user_id = ANY(\$2::int[])
                   ORDER BY p.created_at DESC LIMIT 20", [$uidMe, $__vids]);
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
$feedTotal = (int) db_val("SELECT COUNT(*) FROM posts WHERE jenis='post' AND user_id = ANY($1::int[])", [$__vids]);
$feedPages = max(1, (int)ceil($feedTotal / $feedPerPage));
if ($feedPage > $feedPages) $feedPage = $feedPages;
$feedOffset = ($feedPage - 1) * $feedPerPage;
// Revisi 21 Juni 2026 R4 — pastikan kolom images_json ada lalu sertakan dalam SELECT
try { db_exec("ALTER TABLE posts ADD COLUMN IF NOT EXISTS images_json TEXT"); } catch (Throwable $e) {}
// Revisi Juli 2026 R8 #2 — Social Feed per komunitas.
$feed = db_all("SELECT p.id, p.user_id, p.caption, p.foto_url AS post_foto, p.jenis,
                  COALESCE(p.media_type,'image') AS media_type, p.images_json, p.created_at,
                  u.nama, u.foto_url AS user_foto,
                  (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS likes,
                  (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comments,
                  (SELECT COUNT(*) FROM post_likes pl2 WHERE pl2.post_id=p.id AND pl2.user_id=\$1) AS liked_by_me
                FROM posts p JOIN users u ON u.id=p.user_id
                WHERE p.jenis='post' AND p.user_id = ANY(\$4::int[])
                ORDER BY p.created_at DESC LIMIT \$2 OFFSET \$3",
                [$uidMe, $feedPerPage, $feedOffset, $__vids]);

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

<?php
/* Revisi Juli 2026 R10 — flag anggota komunitas SuperDuperAdmin (non-superadmin)
   yang WAJIB disembunyikan dari: Status Online, Story, Social Feed, Forum. */
$__hideSuper = scope_is_superduper_kom_member();
?>



<section class="hero mb-3 p-3 p-md-4 rounded-3 text-white position-relative overflow-hidden" style="background:var(--primary-gradient, linear-gradient(135deg,#0ea5e9,#6366f1));box-shadow:0 6px 18px rgba(var(--primary-rgb,14,165,233),.25);">
  <!-- Revisi Nov 2026 R11 — Animasi/visualisasi runner berlari di kotak sapaan. -->
  <div class="hero-run-track" aria-hidden="true">
    <div class="hero-run-runner"><i class="bi bi-person-walking"></i></div>
    <div class="hero-run-ground"></div>
    <div class="hero-run-cloud c1"><i class="bi bi-cloud-fill"></i></div>
    <div class="hero-run-cloud c2"><i class="bi bi-cloud-fill"></i></div>
    <div class="hero-run-cloud c3"><i class="bi bi-cloud-fill"></i></div>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-3 position-relative" style="z-index:2;">
    <div class="flex-grow-1" style="min-width:240px;">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="badge-soft" style="display:inline-flex !important;align-items:center;gap:.35rem;background:#ffffff !important;color:#0f172a !important;border:1px solid #ffffff !important;padding:.35rem .8rem;border-radius:999px;font-size:.78rem;font-weight:800;letter-spacing:.02em;box-shadow:0 2px 10px rgba(15,23,42,.18);"><i class="bi bi-stars" style="color:#f59e0b"></i> <span style="color:#0f172a">KawanKeringat AppSport</span></span>
      </div>
      <h1 class="h3 mb-1 text-white" style="line-height:1.25;word-break:break-word;">Halo, <?= htmlspecialchars($u['nama'] ?? 'Sobat') ?>! 👋</h1>
      <p class="mb-1 text-white" style="line-height:1.45;font-weight:600;">Selamat datang Mahasiswa &amp; Pecinta Olahraga.</p>
      <p class="mb-2 text-white-50" style="line-height:1.5;">Check-in, kompetisi, dan komunitas dalam satu tempat — yuk kumpulkan keringat hari ini.</p>
      <div class="d-flex flex-wrap gap-2">
        <button id="installBtn" class="btn btn-sm btn-light fw-semibold"><i class="bi bi-phone"></i> Tambahkan Pintasan ke HP kamu</button>
      </div>
    </div>
</div>
</section>

<style>
/* Revisi Nov 2026 R11 — animasi runner di hero */
.hero-run-track{position:absolute;left:0;right:0;bottom:0;height:64px;pointer-events:none;overflow:hidden;z-index:1;}
.hero-run-ground{position:absolute;left:0;right:0;bottom:0;height:6px;background:repeating-linear-gradient(90deg,rgba(255,255,255,.35) 0 14px,transparent 14px 28px);animation:heroGround 1.2s linear infinite;}
.hero-run-runner{position:absolute;bottom:8px;left:12px;font-size:2rem;color:#fff;text-shadow:0 2px 6px rgba(0,0,0,.25);animation:heroBounce .5s ease-in-out infinite;}
.hero-run-cloud{position:absolute;color:rgba(255,255,255,.35);font-size:1.4rem;top:8px;animation:heroCloud 14s linear infinite;}
.hero-run-cloud.c1{left:100%;animation-delay:0s}
.hero-run-cloud.c2{left:100%;top:20px;font-size:1.1rem;animation-duration:18s;animation-delay:-6s;}
.hero-run-cloud.c3{left:100%;top:2px;font-size:1.7rem;animation-duration:22s;animation-delay:-12s;}
@keyframes heroGround{from{background-position:0 0}to{background-position:-28px 0}}
@keyframes heroBounce{0%,100%{transform:translateY(0) scaleX(1)}50%{transform:translateY(-6px) scaleX(1.02)}}
@keyframes heroCloud{from{transform:translateX(0)}to{transform:translateX(-140vw)}}
@media (prefers-reduced-motion: reduce){
  .hero-run-ground,.hero-run-runner,.hero-run-cloud{animation:none !important}
}
</style>


<script>
let _deferredInstall = null;
const _installBtn = document.getElementById('installBtn');
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); _deferredInstall = e; });
document.addEventListener('DOMContentLoaded', () => {
  if (!_installBtn) return;
  _installBtn.addEventListener('click', async () => {
    if (_deferredInstall) { _deferredInstall.prompt(); _deferredInstall = null; }
    else { const m = document.getElementById('pwaInstallModal'); if (m) new bootstrap.Modal(m).show(); }
  });
});
</script>

<!-- Revisi R8 Juli 2026 — Popup "Tambahkan Pintasan ke HP" (Bootstrap modal, URL tidak ditampilkan) -->
<div class="modal fade" id="pwaInstallModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:22px;overflow:hidden;border:0;box-shadow:0 20px 40px -18px rgba(15,23,42,.4)">
      <div class="modal-header border-0 pb-1" style="background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;">
        <div class="d-flex align-items-center gap-2">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:1.4rem;"><i class="bi bi-phone-fill"></i></div>
          <div>
            <h5 class="modal-title mb-0" style="font-weight:800;letter-spacing:-.01em;">Pasang ke Layar Utama</h5>
            <div style="font-size:.8rem;opacity:.88;">KawanKeringat siap dipakai seperti aplikasi</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pt-3">
        <p class="small text-muted mb-3">Ikuti langkah di bawah agar ikon aplikasi muncul di layar utama HP kamu.</p>

        <div class="mb-3 p-3" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:14px;">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-android2 text-success fs-4"></i>
            <strong>Android · Chrome / Edge</strong>
          </div>
          <ol class="small mb-0 ps-3">
            <li>Ketuk tombol menu <i class="bi bi-three-dots-vertical"></i> di pojok kanan atas browser.</li>
            <li>Pilih <strong>“Tambahkan ke Layar Utama”</strong> atau <strong>“Install app”</strong>.</li>
            <li>Konfirmasi. Ikon KawanKeringat akan muncul di home screen.</li>
          </ol>
        </div>

        <div class="mb-1 p-3" style="background:#fdf4ff;border:1px solid #f5d0fe;border-radius:14px;">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-apple text-dark fs-4"></i>
            <strong>iPhone / iPad · Safari</strong>
          </div>
          <ol class="small mb-0 ps-3">
            <li>Ketuk tombol <strong>Bagikan</strong> <i class="bi bi-box-arrow-up"></i> di bagian bawah Safari.</li>
            <li>Gulir dan pilih <strong>“Tambahkan ke Layar Utama”</strong>.</li>
            <li>Ketuk <strong>Tambah</strong>. Ikon aplikasi akan muncul.</li>
          </ol>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal" style="border-radius:12px;padding:.7rem 1rem;font-weight:700;background:linear-gradient(135deg,#0ea5e9,#6366f1);border:0;">
          <i class="bi bi-check2-circle me-1"></i> Mengerti
        </button>
      </div>
    </div>
  </div>
</div>

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
<?php
  // Revisi R22 — link Grup WhatsApp komunitas (dari app_settings)
  $waGrupLink = '';
  try { $waGrupLink = (string) db_val("SELECT sval FROM app_settings WHERE skey='wa_grup_link' LIMIT 1"); } catch (Throwable $e) {}
  $waGrupLink = trim($waGrupLink);
  $waGrupUrl  = ($waGrupLink && preg_match('~^https?://~i',$waGrupLink))
      ? $waGrupLink
      : '';
  $waGrupShareUrl = $waGrupUrl
      ? $waGrupUrl
      : ('https://api.whatsapp.com/send?text=' . rawurlencode($msgTpl));
?>
<div class="card shadow-sm mb-3" id="sec-kabari">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-megaphone text-warning"></i> Kabari Member (PIC &amp; Grup WhatsApp)</span>
    <?= $headerJadwal ?>
  </div>
  <div class="card-body">
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
      <i class="bi bi-whatsapp fs-4"></i>
      <div class="flex-grow-1 small">
        <strong>Kabari semua member sekaligus lewat Grup WhatsApp komunitas.</strong>
        <?php if (!$waGrupUrl): ?>
          <div class="text-muted small">Link grup belum diset — admin dapat mengatur di tabel <code>app_settings</code> (skey=<code>wa_grup_link</code>) atau menu Admin → Pengaturan.</div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($waGrupUrl): ?>
          <a href="<?= htmlspecialchars($waGrupUrl) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
            <i class="bi bi-people-fill"></i> Buka Grup WA
          </a>
        <?php endif; ?>
        <button type="button" id="btnSalinPesanGrup" class="btn btn-outline-success btn-sm" data-pesan="<?= htmlspecialchars($msgTpl) ?>">
          <i class="bi bi-clipboard-check"></i> Salin Pesan Grup
        </button>
        <a href="https://api.whatsapp.com/send?text=<?= rawurlencode($msgTpl) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
          <i class="bi bi-whatsapp"></i> Bagikan ke WA
        </a>
      </div>
    </div>
    <script>
    (function(){
      var b = document.getElementById('btnSalinPesanGrup');
      if (!b) return;
      b.addEventListener('click', function(){
        var t = b.dataset.pesan || '';
        if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function(){ b.innerHTML='<i class="bi bi-check2"></i> Tersalin'; setTimeout(function(){b.innerHTML='<i class=\"bi bi-clipboard-check\"></i> Salin Pesan Grup';},2000); }); }
        else { var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); b.innerHTML='<i class="bi bi-check2"></i> Tersalin'; }
      });
    })();
    </script>
    <p class="small text-muted mb-2">Atau klik WhatsApp di bawah untuk mengabari setiap member di bawah koordinasi kamu. Daftar diambil dari pengaturan PIC di halaman <em>Admin → Members</em>.</p>
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
    <div class="card shadow-sm border-0 text-white" style="background:var(--primary-gradient, linear-gradient(135deg,#0ea5e9,#6366f1)) !important;color:#fff !important;border-radius:20px;overflow:hidden;box-shadow:0 6px 18px rgba(var(--primary-rgb,14,165,233),.25);">
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
    <div class="stat-label">Member Aktif</div><div class="stat-value"><?= $memberAktif ?></div></div></div></div>
  <div class="col-6"><div class="card card-stat shadow-sm border-danger-subtle"><div class="card-body">
    <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-person-x-fill"></i></div>
    <div class="stat-label">Member Tidak Aktif</div><div class="stat-value"><?= $memberNonaktif ?></div></div></div></div>
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



<?php if($u && !$__hideSuper): /* Revisi R10 — sembunyikan Story dari komunitas SuperDuperAdmin (non-superadmin) */ ?>
<!-- Revisi Nov 2026 R11 — Tombol Posting standalone (dipindahkan dari header Story Hari Ini), berada TEPAT DI ATAS Story Hari Ini. -->
<div class="card shadow-sm mb-3" id="postingTopCard">
  <div class="card-body d-flex justify-content-between align-items-center gap-2 py-2 flex-wrap">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-plus-square-dotted text-primary fs-4"></i>
      <div>
        <div class="fw-semibold">Posting Baru</div>
        <div class="small text-muted">Bagikan story, foto atau update ke komunitas.</div>
      </div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#postModal"><i class="bi bi-plus-lg"></i> Posting</button>
  </div>
</div>

<div class="card shadow-sm mb-3" id="feed"><div class="card-header d-flex justify-content-between">
  <span><i class="bi bi-collection-play text-primary"></i> Story Hari Ini</span>
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
    <?php /* Revisi Juli 2026 R10 — Sapa Member Baru sudah dipindah ke PALING ATAS. */ ?>



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

    <?php /* Revisi R4 (Juli 2026) — Jadwal Terdekat: tambahkan kolom Komunitas + rapikan tampilan */ ?>
    <style>
      #sec-jadwal-terdekat .card-header{background:linear-gradient(90deg,#e0f2fe,#f0f9ff);font-weight:600}
      #sec-jadwal-terdekat table thead th{background:#f8fafc;font-size:.78rem;text-transform:uppercase;letter-spacing:.02em;color:#475569;border-bottom:2px solid #e2e8f0}
      #sec-jadwal-terdekat table tbody td{vertical-align:middle;padding:.75rem .5rem}
      #sec-jadwal-terdekat table tbody tr:hover{background:#f8fafc}
      #sec-jadwal-terdekat .kom-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .55rem;border-radius:999px;font-size:.72rem;font-weight:600;color:#fff;background:#0ea5e9}
      #sec-jadwal-terdekat .kom-chip.no{background:#e5e7eb;color:#64748b}
    </style>
    <div class="card shadow-sm mb-3" id="sec-jadwal-terdekat"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-calendar3 me-1 text-primary"></i> Jadwal Terdekat</span><a href="/calendar.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-week"></i> Semua Jadwal</a></div>
      <div data-live="jadwal">
      <div class="table-responsive"><table class="table table-hover align-middle table-stack mb-0" data-paginate="5">
        <thead><tr><th style="width:32px"></th><th>Tanggal</th><th>Jenis</th><th>Komunitas</th><th>Tempat</th><th>Lokasi</th><th>Koordinator</th><th>Absensi Saya</th><th class="text-end">Absen</th></tr></thead><tbody>
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
            <td data-label="Jenis">
              <span class="pill"><?= htmlspecialchars($j['jenis']) ?></span>
              <?php if(!empty($j['jj_nama'])): ?>
                <!-- Revisi R18 — Badge Jenis Jadwal (Tim Kantor KK / Tim Public KK) dgn warna BG -->
                <div class="mt-1"><span class="badge" style="background:<?= htmlspecialchars($j['jj_bg']) ?>;color:<?= htmlspecialchars($j['jj_text']) ?>"><?= htmlspecialchars($j['jj_nama']) ?></span></div>
              <?php endif; ?>
            </td>
            <?php /* Revisi R10 Juli 2026 #1 — kolom "Komunitas" pada Jadwal Terdekat dimunculkan kembali. */ ?>
            <td data-label="Komunitas">
              <?php if(!empty($j['kom_nama'])): ?>
                <span class="kom-chip" style="background:<?= htmlspecialchars($j['kom_warna'] ?: '#0ea5e9') ?>">
                  <i class="bi bi-people-fill"></i> <?= htmlspecialchars($j['kom_nama']) ?>
                </span>
                <?php if(!empty($j['kom_kota'])): ?><div class="small text-muted"><i class="bi bi-geo"></i> <?= htmlspecialchars($j['kom_kota']) ?></div><?php endif; ?>
              <?php else: ?>
                <span class="kom-chip no"><i class="bi bi-dash-circle"></i> Tanpa Komunitas</span>
              <?php endif; ?>
            </td>
            <td data-label="Tempat"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($j['tempat']) ?></td>
             <td data-label="Lokasi">
               <?php
                 $maps = ($j['tp_lat'] && $j['tp_lng'])
                   ? 'https://www.google.com/maps?q='.$j['tp_lat'].','.$j['tp_lng']
                   : 'https://www.google.com/maps/search/'.urlencode($j['tempat']);
               ?>
               <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?= htmlspecialchars($maps) ?>" title="Lihat di Google Maps"><i class="bi bi-google"></i> Lokasi</a>
                <?php
                  // Revisi 22 Juni 2026 R15 — tombol "Peta Rute" dihapus.
                  // Untuk rute perjalanan (Hiking/Camping), lihat di Daftar Tempat → popup Detail.
                  if (mb_strtolower(trim((string)$j['jenis'])) === 'hiking') {
                    echo '<div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> Rute perjalanan dapat dilihat di <a href="/tempat_list.php" class="text-decoration-none">Daftar Tempat</a>.</div>';
                  }
                ?>
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
              <?php $eksCnt = count($tamuByJadwal[$jid] ?? []); if($eksCnt): ?><span class="badge bg-success-subtle text-success-emphasis border" title="Hadir Eksternal">E <?= $eksCnt ?></span><?php endif; ?>
            </td>
          </tr>
          <tr class="collapse" id="jdetail<?= $jid ?>">
            <td colspan="10" class="bg-light">
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
                // Revisi 24 Juni 2026 — Tampilkan anggota hadir EKSTERNAL di Jadwal Terdekat.
                $tamuList = $tamuByJadwal[$jid] ?? [];
              ?>
              <div class="mt-2">
                <strong class="small"><i class="bi bi-person-plus text-success"></i> Anggota Hadir Eksternal (<?= count($tamuList) ?>)</strong>
                <?php if(!$tamuList): ?>
                  <span class="small text-muted">— belum ada tamu eksternal —</span>
                <?php else: ?>
                  <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Nama Tamu</th><th>Dibawa oleh</th></tr></thead>
                    <tbody>
                    <?php foreach($tamuList as $tr): ?>
                      <tr>
                        <td><span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-person"></i> <?= htmlspecialchars($tr['nama_tamu']) ?></span></td>
                        <td class="small">
                          <?php if(!empty($tr['pembawa'])): ?>
                            <a class="text-decoration-none" href="/user.php?id=<?= (int)$tr['dibawa_oleh_id'] ?>"><?= user_name_with_avatar($tr['pembawa_foto'] ?? null, $tr['pembawa'], false, 20) ?></a>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                <?php endif; ?>
              </div>
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

        <?php /* Revisi 22 Juni 2026 R15 — modal peta rute Hiking dihapus. Lihat di /tempat_list.php */ ?>
      </div>
    </div>

<?php if ($u && in_array($u['role'] ?? '', ['member','admin','superadmin'], true) && !$__hideSuper): /* R10 — sembunyikan dari komunitas SuperDuperAdmin */ ?>
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
<?php elseif (!$__hideSuper): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted small py-4">
      <i class="bi bi-lock fs-3 d-block mb-2"></i>
      <strong>Social Feed</strong> hanya bisa ditampilkan untuk <strong>member / admin terdaftar</strong>.
      <div class="mt-2"><a href="/login.php" class="btn btn-sm btn-primary">Login</a> <a href="/register.php" class="btn btn-sm btn-outline-primary">Daftar</a></div>
    </div></div>
<?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php if(!$__hideSuper): /* R10 — sembunyikan Online & Forum dari komunitas SuperDuperAdmin */ ?>
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

    <?php /* Revisi Juli 2026 R11 — Sapa Member Baru dipindah ke BAWAH Status Online. */ ?>
<?php /* Revisi Juli 2026 R10 — Sapa Member Baru dipindah ke PALING ATAS. */ ?>
<?php if(!empty($newMembers)): ?>
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
      <div class="col-md-6 col-lg-4">
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


    <div class="card shadow-sm mb-3" id="forum"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-chat-square-text text-primary me-1"></i> Diskusi / Informasi</span><button class="btn btn-sm btn-link p-0" data-soft-refresh title="Muat data terbaru"><i class="bi bi-arrow-clockwise"></i></button></div>
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
    <?php endif; /* R10 hide superduper */ ?>
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
      <input type="file" name="fotos[]" accept="image/*" multiple class="form-control mb-1" id="postFotosInput" data-compress-multi>
      <input type="hidden" name="_ajax" value="1">
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
      <!-- Revisi 24 Juni 2026 — fitur Like & Komentar pada Story DIHAPUS. Hanya jumlah dilihat. -->
      <div class="d-flex gap-2 align-items-center border-top border-bottom py-2 mb-2">
        <span class="ms-auto small text-muted"><i class="bi bi-eye"></i> <span id="storyViewCount">0</span> dilihat</span>
      </div>
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
        const opt = { body: n.isi || '', icon: '/assets/icon-192.png', badge: '/assets/icon-192.png', tag: 'kawankeringat-'+n.id, data: { url: n.url || '/' } };
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
          navigator.serviceWorker.ready.then(reg => reg.showNotification(n.judul || 'KawanKeringat', opt));
        } else {
          try { new Notification(n.judul || 'KawanKeringat', opt); } catch(e){}
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
      var posting = $('postingTopCard'); // Revisi Nov 2026 R12 — Widget Posting Baru DI ATAS Story Hari Ini
      function moveAfter(node, ref){ if(node && ref && ref.parentNode){ ref.parentNode.insertBefore(node, ref.nextSibling); } }
      var anchor = dash;
      // Urutan baru: dashboard -> online -> jadwal -> kabari -> posting -> story -> social feed -> forum -> event
      [online, jadwal, kabari, posting, story, social, forum, event_].forEach(function(el){
        if (el && anchor) { moveAfter(el, anchor); anchor = el; }
      });
    } catch(e){ console.warn('reorder failed', e); }
  });
})();
</script>

<script>
/* Revisi 22 Juni 2026 R12 — AJAX pagination Social Feed.
   Intercept tombol Sebelumnya/Berikutnya pada #sec-social-feed dan muat
   konten via fetch tanpa reload halaman. */
(function(){
  var section = document.getElementById('sec-social-feed');
  if (!section) return;
  function bind(){
    var nav = section.querySelector('nav[aria-label="Navigasi feed"]');
    if (!nav) return;
    nav.querySelectorAll('a[href]').forEach(function(a){
      if (a.dataset._ajaxBound) return;
      a.dataset._ajaxBound = '1';
      a.addEventListener('click', function(e){
        e.preventDefault();
        var url = a.getAttribute('href');
        if (!url) return;
        var body = section.querySelector('[data-live="feed"]');
        if (!body) { location.href = url; return; }
        body.style.opacity = '0.5';
        fetch(url, {headers:{'X-Requested-With':'fetch'}, credentials:'same-origin'})
          .then(function(r){ return r.text(); })
          .then(function(html){
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var nb = doc.querySelector('#sec-social-feed [data-live="feed"]');
            if (nb) {
              body.innerHTML = nb.innerHTML;
              try { history.replaceState(null,'', url.replace(/#.*$/,'') + '#feed'); } catch(e){}
              // re-init carousels & inline scripts inside new content
              body.querySelectorAll('script').forEach(function(s){
                var ns = document.createElement('script');
                if (s.src) ns.src = s.src; else ns.textContent = s.textContent;
                s.parentNode.replaceChild(ns, s);
              });
              bind(); // re-bind new pagination
              section.scrollIntoView({behavior:'smooth', block:'start'});
            } else {
              location.href = url;
            }
          })
          .catch(function(){ location.href = url; })
          .finally(function(){ body.style.opacity = '1'; });
      });
    });
  }
  bind();
  // observe future replacements
  new MutationObserver(bind).observe(section, {childList:true, subtree:true});
})();
</script>

<!-- Revisi Nov 2026 R11 — Spoiler otomatis (tertutup by default) untuk kartu: Kabari Member, Story Hari Ini, Social Feed, Forum Komunitas. -->
<style>
.idx-spoiler-caret{transition:transform .15s ease;display:inline-block;margin-left:.35rem;color:#94a3b8}
.idx-spoiler-open .idx-spoiler-caret{transform:rotate(180deg)}
</style>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var TARGETS = ["Kabari Member","Story Hari Ini","Social Feed","Forum Komunitas"];
  function norm(t){ return (t||'').replace(/\s+/g,' ').trim(); }
  function matches(title){
    title = norm(title);
    for (var i=0;i<TARGETS.length;i++){
      if (title.indexOf(TARGETS[i])===0 || title.indexOf(TARGETS[i]) !== -1) {
        return TARGETS[i];
      }
    }
    return null;
  }
  document.querySelectorAll('.card').forEach(function(card){
    if (card.tagName.toLowerCase() === 'details') return;
    if (card.dataset.idxSpoilerDone === '1') return;
    var header = card.querySelector(':scope > .card-header');
    if (!header) return;
    if (!matches(header.textContent)) return;

    // Kumpulkan semua sibling setelah header sebagai konten yang bisa disembunyikan.
    var kids = Array.from(card.children);
    var idx  = kids.indexOf(header);
    var contentEls = kids.slice(idx+1);
    if (!contentEls.length) return;

    card.dataset.idxSpoilerDone = '1';
    header.style.cursor = 'pointer';
    if (!header.querySelector('.idx-spoiler-caret')) {
      var c = document.createElement('span');
      c.className = 'idx-spoiler-caret ms-2';
      c.innerHTML = '<i class="bi bi-chevron-down"></i>';
      header.appendChild(c);
    }
    function setOpen(open){
      contentEls.forEach(function(el){ el.style.display = open ? '' : 'none'; });
      header.classList.toggle('idx-spoiler-open', open);
    }
    setOpen(false); // default tertutup

    header.addEventListener('click', function(e){
      if (e.target.closest('a,button,input,select,textarea,label,form')) return;
      var isOpen = contentEls[0] && contentEls[0].style.display !== 'none';
      setOpen(!isOpen);
    });
  });
});
</script>
<?php render_index_blok('bottom'); include __DIR__.'/includes/footer.php'; ?>


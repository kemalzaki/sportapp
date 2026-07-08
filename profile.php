<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/badges.php';
require __DIR__.'/includes/notifications.php';
require __DIR__.'/includes/migrations_v7.php';
require __DIR__.'/includes/scope.php';
require __DIR__.'/includes/paket_helpers.php'; // Revisi — badge Paket Member (gratis/pro/komunitas)
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Profil Saya';
$pageSkeleton = 'profile'; // Skeleton sesuai data: kartu profil

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
    // Revisi 19 Juni 2026 Part Q — kolom Strava & Nickname
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS strava_account VARCHAR(120)");
    db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname VARCHAR(80)");
} catch (Throwable $e) {}

// Pastikan tabel guest_messages ada (untuk fitur Titip Pesan di profile)
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
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    // Revisi R7 #4 — Ubah password pribadi (aku sendiri).
    if ($a==='update_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password']     ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = db_one("SELECT password_hash FROM users WHERE id=$1", [(int)$u['id']]);
        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            $_SESSION['flash_err'] = 'Password lama salah.';
        } elseif (strlen($new) < 6) {
            $_SESSION['flash_err'] = 'Password baru minimal 6 karakter.';
        } elseif ($new !== $confirm) {
            $_SESSION['flash_err'] = 'Konfirmasi password baru tidak cocok.';
        } else {
            db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",
                    [password_hash($new, PASSWORD_BCRYPT), (int)$u['id']]);
            $_SESSION['flash_ok'] = 'Password berhasil diperbarui. Silakan gunakan password baru pada login berikutnya.';
        }
        header('Location: /profile.php#ubahPassword'); exit;
    }
    // Revisi Nov 2026 — edit inline Nama Lengkap.
    if ($a==='update_nama') {
        $nm = trim(substr($_POST['nama'] ?? '', 0, 80));
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
        if (mb_strlen($nm) < 2) {
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>'Nama minimal 2 karakter.']); exit; }
            $_SESSION['flash_err'] = 'Nama minimal 2 karakter.';
        } else {
            db_exec("UPDATE users SET nama=$1 WHERE id=$2", [$nm, (int)$u['id']]);
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'nama'=>$nm]); exit; }
        }
        header('Location: /profile.php'); exit;
    }
    // Revisi Nov 2026 — edit inline Username.
    if ($a==='update_username') {
        $un = strtolower(trim($_POST['username'] ?? ''));
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
        if (!preg_match('/^[a-z0-9._]{3,40}$/', $un)) {
            $msg = 'Username hanya boleh huruf kecil/angka/titik/underscore (3-40 karakter).';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>$msg]); exit; }
            $_SESSION['flash_err'] = $msg;
        } elseif (db_one("SELECT id FROM users WHERE LOWER(username)=$1 AND id<>$2", [$un, (int)$u['id']])) {
            $msg = 'Username sudah dipakai. Pilih yang lain.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>$msg]); exit; }
            $_SESSION['flash_err'] = $msg;
        } else {
            db_exec("UPDATE users SET username=$1 WHERE id=$2", [$un, (int)$u['id']]);
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'username'=>$un]); exit; }
        }
        header('Location: /profile.php'); exit;
    }
    if ($a==='update_bio') {
        $bio = substr(trim($_POST['bio'] ?? ''), 0, 300);
        db_exec("UPDATE users SET bio=$1 WHERE id=$2", [$bio, (int)$u['id']]);
    } elseif ($a==='update_wa') {
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
    } elseif ($a==='update_tema') {
        $valid = ['sky','indigo','emerald','rose','amber','violet','slate'];
        $t = $_POST['tema_warna'] ?? 'sky';
        if (!in_array($t, $valid, true)) $t = 'sky';
        db_exec("UPDATE users SET tema_warna=$1 WHERE id=$2", [$t, (int)$u['id']]);
        // Revisi Juli 2026 — auto refresh setelah simpan tema (tidak perlu manual).
        // Untuk request AJAX (data-ajax) balas JSON berisi flag reload; untuk POST
        // biasa langsung redirect ke #temaWarna supaya halaman refresh sendiri.
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
                || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>true, 'reload'=>true, 'tema'=>$t]);
            exit;
        }
        header('Location: /profile.php?tema_ok=1#temaWarna'); exit;
    } elseif ($a==='delete_wa') {
        db_exec("UPDATE users SET nomor_wa=NULL WHERE id=$1", [(int)$u['id']]);
    } elseif ($a==='update_strava') {
        // Revisi 19 Juni 2026 Part Q — CRUD akun/ID Strava
        $sv = trim(substr($_POST['strava_account'] ?? '', 0, 120));
        db_exec("UPDATE users SET strava_account=NULLIF($1,'') WHERE id=$2", [$sv, (int)$u['id']]);
    } elseif ($a==='delete_strava') {
        db_exec("UPDATE users SET strava_account=NULL WHERE id=$1", [(int)$u['id']]);
    } elseif ($a==='update_nickname') {
        // Revisi 19 Juni 2026 Part Q — CRUD nickname / nama samaran
        $nk = trim(substr($_POST['nickname'] ?? '', 0, 80));
        db_exec("UPDATE users SET nickname=NULLIF($1,'') WHERE id=$2", [$nk, (int)$u['id']]);
    } elseif ($a==='delete_nickname') {
        db_exec("UPDATE users SET nickname=NULL WHERE id=$1", [(int)$u['id']]);
    } elseif ($a==='pertemanan_add') {
        // Revisi Juli 2026 — Fitur Pertemananku
        // Revisi R8 (Juli 2026) — tambah kolom tanggal_terakhir_ketemu
        try { db_exec("ALTER TABLE pertemanan ADD COLUMN IF NOT EXISTS tanggal_terakhir_ketemu DATE"); } catch (Throwable $e) {}
        $nm  = mb_substr(trim($_POST['nama'] ?? ''), 0, 120);
        $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal_kenalan'] ?? '') ? $_POST['tanggal_kenalan'] : null;
        $tkt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal_terakhir_ketemu'] ?? '') ? $_POST['tanggal_terakhir_ketemu'] : null;
        $kd  = max(0, min(5, (int)($_POST['kedekatan'] ?? 0)));
        $ct  = mb_substr(trim($_POST['catatan'] ?? ''), 0, 500);
        if ($nm !== '') {
            try {
                db_exec("INSERT INTO pertemanan(user_id,nama,tanggal_kenalan,tanggal_terakhir_ketemu,kedekatan,catatan)
                         VALUES($1,$2,$3,$4,NULLIF($5,0),NULLIF($6,''))",
                    [(int)$u['id'], $nm, $tgl, $tkt, $kd, $ct]);
            } catch (Throwable $e) {}
        }
    } elseif ($a==='pertemanan_update') {
        try { db_exec("ALTER TABLE pertemanan ADD COLUMN IF NOT EXISTS tanggal_terakhir_ketemu DATE"); } catch (Throwable $e) {}
        $id  = (int)($_POST['id'] ?? 0);
        $nm  = mb_substr(trim($_POST['nama'] ?? ''), 0, 120);
        $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal_kenalan'] ?? '') ? $_POST['tanggal_kenalan'] : null;
        $tkt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal_terakhir_ketemu'] ?? '') ? $_POST['tanggal_terakhir_ketemu'] : null;
        $kd  = max(0, min(5, (int)($_POST['kedekatan'] ?? 0)));
        $ct  = mb_substr(trim($_POST['catatan'] ?? ''), 0, 500);
        if ($id && $nm !== '') {
            try {
                db_exec("UPDATE pertemanan SET nama=$1, tanggal_kenalan=$2, tanggal_terakhir_ketemu=$3, kedekatan=NULLIF($4,0), catatan=NULLIF($5,'')
                         WHERE id=$6 AND user_id=$7",
                    [$nm, $tgl, $tkt, $kd, $ct, $id, (int)$u['id']]);
            } catch (Throwable $e) {}
        }
    } elseif ($a==='pertemanan_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { try { db_exec("DELETE FROM pertemanan WHERE id=$1 AND user_id=$2", [$id, (int)$u['id']]); } catch (Throwable $e) {} }
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
    // ===== v7: Kondisi Terkini =====
    elseif ($a==='kondisi_set') {
        $st = ($_POST['status'] ?? 'sehat')==='sakit' ? 'sakit' : 'sehat';
        $ket = substr(trim($_POST['keterangan'] ?? ''),0,500);
        db_exec("INSERT INTO user_kondisi(user_id,status,keterangan,updated_at) VALUES($1,$2,$3,now())
                 ON CONFLICT (user_id) DO UPDATE SET status=EXCLUDED.status, keterangan=EXCLUDED.keterangan, updated_at=now()",
                [(int)$u['id'], $st, $ket ?: null]);
        apply_kondisi_to_absensi((int)$u['id'], $st, $ket);
    }
    // ===== v7: Pengalaman Hiking / Camping =====
    elseif ($a==='peng_add' || $a==='peng_edit') {
        $jenis  = in_array($_POST['jenis'] ?? '', ['hiking','camping'], true) ? $_POST['jenis'] : 'hiking';
        $judul  = trim(substr($_POST['judul'] ?? '',0,160));
        $lokasi = trim(substr($_POST['lokasi'] ?? '',0,200));
        $tgl    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : null;
        $desk   = substr(trim($_POST['deskripsi'] ?? ''),0,2000);
        $foto   = trim(substr($_POST['foto_url'] ?? '', 0, 500));
        if ($judul !== '') {
          if ($a==='peng_add') {
            db_exec("INSERT INTO user_pengalaman(user_id,jenis,judul,lokasi,tanggal,deskripsi,foto_url) VALUES($1,$2,$3,$4,$5,$6,$7)",
              [(int)$u['id'],$jenis,$judul,$lokasi ?: null,$tgl,$desk ?: null,$foto ?: null]);
          } else {
            $pid = (int)($_POST['id'] ?? 0);
            if ($pid) db_exec("UPDATE user_pengalaman SET jenis=$1,judul=$2,lokasi=$3,tanggal=$4,deskripsi=$5,foto_url=$6 WHERE id=$7 AND user_id=$8",
              [$jenis,$judul,$lokasi ?: null,$tgl,$desk ?: null,$foto ?: null,$pid,(int)$u['id']]);
          }
        }
    } elseif ($a==='peng_del') {
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid) db_exec("DELETE FROM user_pengalaman WHERE id=$1 AND user_id=$2", [$pid,(int)$u['id']]);
    }
    // ===== v7: Perlengkapan Olahraga =====
    elseif ($a==='perl_add' || $a==='perl_edit') {
        $jId    = (int)($_POST['jenis_olahraga_id'] ?? 0) ?: null;
        $jNama  = trim(substr($_POST['jenis_nama'] ?? '',0,80));
        if ($jId) {
          $jRow = db_one("SELECT nama FROM jenis_olahraga WHERE id=$1", [$jId]);
          if ($jRow) $jNama = $jRow['nama'];
        }
        $nama   = trim(substr($_POST['nama'] ?? '',0,120));
        $jumlah = max(1, (int)($_POST['jumlah'] ?? 1));
        $cat    = substr(trim($_POST['catatan'] ?? ''),0,200);
        if ($nama !== '' && $jNama !== '') {
          if ($a==='perl_add') {
            db_exec("INSERT INTO user_perlengkapan(user_id,jenis_olahraga_id,jenis_nama,nama,jumlah,catatan) VALUES($1,$2,$3,$4,$5,$6)",
              [(int)$u['id'],$jId,$jNama,$nama,$jumlah,$cat ?: null]);
          } else {
            $pid=(int)($_POST['id']??0);
            if ($pid) db_exec("UPDATE user_perlengkapan SET jenis_olahraga_id=$1,jenis_nama=$2,nama=$3,jumlah=$4,catatan=$5 WHERE id=$6 AND user_id=$7",
              [$jId,$jNama,$nama,$jumlah,$cat ?: null,$pid,(int)$u['id']]);
          }
        }
    } elseif ($a==='perl_del') {
        $pid=(int)($_POST['id']??0);
        if ($pid) db_exec("DELETE FROM user_perlengkapan WHERE id=$1 AND user_id=$2", [$pid,(int)$u['id']]);
    }
    // ===== Titip Pesan (guestbook di profil saya) =====
    elseif ($a==='gm_add') {
        $pesan = trim(substr($_POST['pesan'] ?? '', 0, 1000));
        $pid = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($pesan !== '') {
            db_exec("INSERT INTO guest_messages(owner_user_id,sender_user_id,parent_id,pesan) VALUES($1,$2,$3,$4)",
                [(int)$u['id'], (int)$u['id'], $pid, $pesan]);
        }
    } elseif ($a==='gm_edit') {
        $mid = (int)($_POST['id'] ?? 0);
        $pesan = trim(substr($_POST['pesan'] ?? '', 0, 1000));
        if ($mid && $pesan !== '') {
            db_exec("UPDATE guest_messages SET pesan=$1, updated_at=now() WHERE id=$2 AND sender_user_id=$3",
                [$pesan, $mid, (int)$u['id']]);
        }
    } elseif ($a==='gm_del') {
        $mid = (int)($_POST['id'] ?? 0);
        if ($mid) {
            db_exec("DELETE FROM guest_messages WHERE id=$1 AND (sender_user_id=$2 OR owner_user_id=$2)",
                [$mid, (int)$u['id']]);
        }
    }
    header('Location: profile.php'); exit;
}

$favList = db_all("SELECT id, nama FROM user_olahraga_favorit WHERE user_id=$1 ORDER BY nama ASC", [(int)$u['id']]);
$kondisi = db_one("SELECT status,keterangan,updated_at FROM user_kondisi WHERE user_id=$1", [(int)$u['id']]) ?: ['status'=>'sehat','keterangan'=>null,'updated_at'=>null];
$pengList = db_all("SELECT * FROM user_pengalaman WHERE user_id=$1 ORDER BY tanggal DESC NULLS LAST, id DESC", [(int)$u['id']]);
// Revisi Nov 2026 — daftar member SATU KOMUNITAS saja (member di luar komunitas tidak muncul).
$__kids = function_exists('scope_current_user_kom_ids') ? scope_current_user_kom_ids() : [];
if ($__kids) {
    $__arrK = '{'.implode(',', array_map('intval', $__kids)).'}';
    try {
        $lihatMemberLain = db_all(
            "SELECT DISTINCT u.id, u.nama
               FROM users u
               LEFT JOIN user_komunitas uk ON uk.user_id = u.id
              WHERE u.id <> $1
                AND u.role IN ('member','admin')
                AND (u.komunitas_id = ANY($2::int[]) OR uk.komunitas_id = ANY($2::int[]))
              ORDER BY u.nama",
            [(int)$u['id'], $__arrK]
        );
    } catch (Throwable $e) { $lihatMemberLain = []; }
} else {
    // Belum terikat komunitas manapun — tidak ada member lain yang boleh dilihat.
    $lihatMemberLain = [];
}
$perlList = db_all("SELECT p.*, jo.nama AS jenis_resmi FROM user_perlengkapan p LEFT JOIN jenis_olahraga jo ON jo.id=p.jenis_olahraga_id WHERE p.user_id=$1 ORDER BY p.jenis_nama, p.nama", [(int)$u['id']]);
$jenisOR = db_all("SELECT id,nama FROM jenis_olahraga ORDER BY nama");

recompute_badges((int)$u['id']);
$me = db_one("SELECT * FROM users WHERE id=$1", [(int)$u['id']]);
$allBadges = db_all("SELECT * FROM badges ORDER BY xp DESC");
$ownBadgeIds = array_column(db_all("SELECT DISTINCT badge_id FROM user_badges WHERE user_id=$1", [(int)$u['id']]), 'badge_id');
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

// Ambil daftar titip pesan untuk profil saya (root + replies)
$gmRoots = db_all("SELECT g.*, u.nama AS sender_nama, u.foto_url AS sender_foto
                   FROM guest_messages g JOIN users u ON u.id=g.sender_user_id
                   WHERE g.owner_user_id=$1 AND g.parent_id IS NULL
                   ORDER BY g.created_at DESC LIMIT 200", [(int)$u['id']]);
$gmReplies = db_all("SELECT g.*, u.nama AS sender_nama, u.foto_url AS sender_foto
                     FROM guest_messages g JOIN users u ON u.id=g.sender_user_id
                     WHERE g.owner_user_id=$1 AND g.parent_id IS NOT NULL
                     ORDER BY g.created_at ASC", [(int)$u['id']]);
$gmByParent = [];
foreach ($gmReplies as $rep) { $gmByParent[(int)$rep['parent_id']][] = $rep; }

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
<?php /* Tema Warna Aplikasi dipindahkan ke bagian bawah halaman sesuai revisi. */ ?>
<h2 class="mb-3"><i class="bi bi-person-circle text-primary"></i> Profil Saya</h2>
<?php /* Revisi Nov 2026 — Rapikan tampilan kartu profil (spacing, badge, tombol edit). */ ?>
<style>
  .profile-card .user-avatar,
  .profile-card .avatar-fallback{
    box-shadow: 0 4px 14px rgba(15,23,42,.08);
    border: 3px solid var(--bs-body-bg,#fff);
    outline: 1px solid rgba(15,23,42,.08);
  }
  .profile-card h4{ font-weight:700; }
  .profile-card .prof-edit-btn{
    width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:50%; border:1px solid var(--bs-border-color,#e5e7eb);
    background:var(--bs-body-bg,#fff); color:var(--bs-primary,#0ea5e9);
    transition:background .15s ease, color .15s ease, transform .15s ease;
  }
  .profile-card .prof-edit-btn:hover{
    background:var(--bs-primary,#0ea5e9); color:#fff; transform:scale(1.05);
  }
  .profile-card .prof-badges{
    display:flex; flex-wrap:wrap; gap:.4rem; justify-content:center; margin-top:.5rem;
  }
  .profile-card .prof-badges .pill{ margin:0 !important; }
  .profile-card .prof-komunitas-wrap{
    display:flex; flex-wrap:wrap; gap:.35rem; justify-content:center;
  }
  .profile-card .prof-actions .btn{ font-weight:600; }
  .profile-card .prof-section{ padding-top:.75rem; margin-top:.75rem; border-top:1px dashed var(--bs-border-color,#e5e7eb); }
</style>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm profile-card" data-live="profile-card-main"><div class="card-body text-center">
      <?php if(!empty($me['foto_url'])): ?>
        <img src="<?= htmlspecialchars($me['foto_url']) ?>" alt="" class="user-avatar zoomable" style="width:104px;height:104px;border-radius:50%;object-fit:cover;">
      <?php else: ?>
        <?= user_avatar(null, $me['nama'], 104) ?>
      <?php endif; ?>
      <?php /* Revisi Juli 2026 — Nama & Username dirapikan di bawah Foto Profil, dengan tombol edit inline. */ ?>
      <div class="prof-identity mt-3 d-flex flex-column align-items-center gap-1">
        <div class="d-inline-flex align-items-center gap-2 justify-content-center flex-wrap">
          <h4 class="mb-0" style="line-height:1.2">
            <span id="profNamaText"><?= htmlspecialchars($me['nama']) ?></span>
          </h4>
        </div>
        <div class="d-inline-flex align-items-center gap-1 small text-muted justify-content-center flex-wrap">
          <i class="bi bi-at"></i>
          <span id="profUsernameText"><?= htmlspecialchars($me['username'] ?? '(belum diatur)') ?></span>
        </div>
        <div class="small text-muted text-center"><?= htmlspecialchars($me['email']) ?></div>
      </div>
      <?php /* Revisi Juli 2026 R3 — Inline edit Nama & Username (prompt native + fetch) */ ?>
      <script>
      (function(){
        var csrf = <?= json_encode(csrf_token()) ?>;
        function postField(action, field, value){
          var fd = new FormData();
          fd.append('csrf', csrf); fd.append('_action', action); fd.append(field, value);
          return fetch('/profile.php', {method:'POST', body: fd, credentials:'same-origin',
                       headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}})
            .then(function(r){ return r.json().catch(function(){ return {ok:false, err:'Respon server tidak valid.'}; }); });
        }
        function bindEdit(btnId, txtId, action, field, label, validator){
          var btn = document.getElementById(btnId);
          if (!btn) { console.warn('[profile-edit] tombol tidak ditemukan:', btnId); return; }
          btn.style.cursor = 'pointer';
          btn.addEventListener('click', function(ev){
            ev.preventDefault(); ev.stopPropagation();
            var el = document.getElementById(txtId);
            var cur = (el ? el.textContent : '').trim();
            if (cur === '(belum diatur)') cur = '';
            var val = window.prompt(label, cur);
            if (val === null) return;
            val = String(val).trim();
            var vErr = validator ? validator(val) : null;
            if (vErr) { alert(vErr); return; }
            btn.disabled = true;
            postField(action, field, val).then(function(j){
              if (j && j.ok) {
                if (el) el.textContent = j[field] || val;
                if (typeof Swal !== 'undefined') { try { Swal.fire({icon:'success',title:'Tersimpan',timer:1100,showConfirmButton:false}); } catch(e){} }
              } else {
                alert((j && j.err) || 'Gagal menyimpan.');
              }
            }).catch(function(e){ alert('Gagal jaringan: ' + (e && e.message ? e.message : e)); })
              .finally(function(){ btn.disabled = false; });
          });
        }
        function init(){
          bindEdit('btnEditNama','profNamaText','update_nama','nama','Nama lengkap baru:',
            function(v){ if (v.length<2 || v.length>80) return 'Nama 2-80 karakter.'; return null; });
          bindEdit('btnEditUsername','profUsernameText','update_username','username','Username baru (huruf kecil/angka/titik/underscore, 3-40):',
            function(v){ if (!/^[a-z0-9._]{3,40}$/.test(v)) return 'Format username tidak valid.'; return null; });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
      })();
      </script>
      <div class="prof-badges">
        <span class="pill">Level <?= $level ?></span>
        <span class="pill" data-bs-toggle="tooltip" title="Streak (mgg) = jumlah minggu berturut-turut Anda upload aktivitas atau hadir di sesi. Reset jika 1 minggu kosong.">🔥 <?= (int)$me['streak_minggu'] ?> minggu</span>
        <span class="pill">⭐ <?= $xp ?> XP</span>
      </div>
      <?php /* Revisi R4 (Juli 2026) — Paket Member + Masa Aktif (auto downgrade jika expired) */ ?>
      <div class="prof-section">
        <div><span class="small text-muted">Paket Member:</span> <?= paket_badge(paket_user($me)) ?></div>
        <div class="mt-1 small"><span class="text-muted">Masa Expire:</span> <?= paket_expiry_label($me) ?></div>
      </div>
      <?php /* Revisi R2 (Juli 2026) — Tampilkan komunitas member (mendukung multi-komunitas). */ ?>
      <?php
        $__mkl = [];
        try {
          $__mkl = db_all("SELECT k.nama FROM user_komunitas uk JOIN komunitas k ON k.id=uk.komunitas_id WHERE uk.user_id=$1 ORDER BY k.nama", [(int)$me['id']]);
        } catch (Throwable $e) {}
        if (!$__mkl && !empty($me['komunitas_id'])) {
          try { $__mkl = db_all("SELECT nama FROM komunitas WHERE id=$1", [(int)$me['komunitas_id']]); } catch (Throwable $e) {}
        }
      ?>
      <?php if ($__mkl): ?>
        <div class="prof-section">
          <span class="small text-muted d-block mb-1"><i class="bi bi-people-fill text-success"></i> Komunitas Saya:</span>
          <div class="prof-komunitas-wrap">
            <?php foreach($__mkl as $__k): ?>
              <span class="badge bg-success-subtle text-success border"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($__k['nama']) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="prof-actions mt-3">
        <?php if (paket_user($me) !== 'komunitas'): ?>
          <a class="btn btn-sm btn-outline-primary" href="/paket_upgrade.php"><i class="bi bi-stars"></i> Upgrade / Perpanjang</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-success" href="/paket_upgrade.php"><i class="bi bi-arrow-repeat"></i> Perpanjang Paket</a>
        <?php endif; ?>
      </div>
      <div class="xp-bar mt-3"><div style="width:<?= min(100,$xpInLevel/2) ?>%"></div></div>
      <small class="text-muted d-block mt-1">Butuh <?= $xpToNext ?> XP lagi ke Level <?= $level+1 ?></small>

      <div class="mt-3">
        <a href="/logout.php" class="btn btn-outline-danger w-100" onclick="return confirm('Keluar dari akun?')">
          <i class="bi bi-box-arrow-right"></i> Keluar / Logout
        </a>
        <!-- Revisi 6 Juni 2026: tombol lihat tampilan sebagai member lain -->
        <button type="button" class="btn btn-outline-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#lihatMemberLainModal">
          <i class="bi bi-eye"></i> Lihat tampilan sebagai Member lain
        </button>
        <div class="modal fade" id="lihatMemberLainModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header"><h5 class="modal-title"><i class="bi bi-people"></i> Pilih Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body">
                <p class="small text-muted">Anda akan diarahkan ke halaman publik profil member terpilih.</p>
                <input type="text" id="lmlSearch" class="form-control mb-2" placeholder="🔍 Cari nama member…">
                <div style="max-height:300px;overflow:auto" class="border rounded">
                  <ul class="list-group list-group-flush" id="lmlList">
                    <!-- Revisi: opsi lihat profil sendiri -->
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light"
                        data-name="<?= htmlspecialchars(strtolower($me['nama'])) ?> (saya)">
                      <span><i class="bi bi-person-check text-primary"></i> <strong><?= htmlspecialchars($me['nama']) ?></strong> <span class="text-muted small">(profil saya)</span></span>
                      <a href="/user.php?id=<?= (int)$me['id'] ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right"></i> Lihat
                      </a>
                    </li>
                    <?php foreach($lihatMemberLain as $lm): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center"
                          data-name="<?= htmlspecialchars(strtolower($lm['nama'])) ?>">
                        <span><?= htmlspecialchars($lm['nama']) ?></span>
                        <a href="/user.php?id=<?= (int)$lm['id'] ?>" class="btn btn-sm btn-outline-primary">
                          <i class="bi bi-arrow-right"></i> Lihat
                        </a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        <script>
          document.getElementById('lmlSearch')?.addEventListener('input', function(){
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('#lmlList li').forEach(li=>{
              li.style.display = (!q || li.dataset.name.includes(q)) ? '' : 'none';
            });
          });
        </script>
      </div>

      <?php /* Revisi 23 Juni 2026 — Ganti foto profil sekarang juga AJAX.
              Setelah upload sukses, softRefresh akan mengganti region
              [data-live="profile-card-main"] sehingga foto baru langsung tampil
              tanpa reload halaman. */ ?>
      <form data-ajax data-ajax-label="Mengunggah foto..." method="post" enctype="multipart/form-data" class="mt-3 text-start">
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

    <?php /* Revisi Juli 2026 — Ubah Password Pribadi DIPINDAH ke paling atas (di atas Pertemananku) */ ?>
    <div class="card shadow-sm mt-3" id="ubahPassword">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shield-lock text-primary"></i> Ubah Password Pribadi</span>
        <span class="small text-muted">Akun: <?= htmlspecialchars($u['email'] ?? '') ?></span>
      </div>
      <div class="card-body">
        <?php if(!empty($_SESSION['flash_ok'])): ?>
          <div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
          <?php unset($_SESSION['flash_ok']); ?>
        <?php endif; ?>
        <?php if(!empty($_SESSION['flash_err'])): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div>
          <?php unset($_SESSION['flash_err']); ?>
        <?php endif; ?>
        <form method="post" class="row g-2" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_password">
          <div class="col-md-4">
            <label class="form-label small mb-1">Password Sekarang</label>
            <input type="password" name="current_password" class="form-control form-control-sm" required autocomplete="current-password">
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Password Baru (min. 6 karakter)</label>
            <input type="password" name="new_password" class="form-control form-control-sm" minlength="6" required autocomplete="new-password">
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Konfirmasi Password Baru</label>
            <input type="password" name="confirm_password" class="form-control form-control-sm" minlength="6" required autocomplete="new-password">
          </div>
          <div class="col-12">
            <button class="btn btn-primary btn-sm"><i class="bi bi-shield-check"></i> Simpan Password Baru</button>
            <div class="form-text small">Password Anda tersimpan dalam bentuk hash (bcrypt). Admin tidak dapat melihat password Anda.</div>
          </div>
        </form>
      </div>
    </div>

    <?php /* Revisi Nov 2026 — Edit Nama Lengkap & Username (menggantikan tombol pensil di header profil). */ ?>
    <div class="card shadow-sm mt-3" id="editIdentitas">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-badge text-primary"></i> Edit Nama &amp; Username</span>
        <span class="small text-muted">Akun: <?= htmlspecialchars($u['email'] ?? '') ?></span>
      </div>
      <div class="card-body">
        <?php if(!empty($_SESSION['flash_ident_ok'])): ?>
          <div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash_ident_ok']) ?></div>
          <?php unset($_SESSION['flash_ident_ok']); ?>
        <?php endif; ?>
        <?php if(!empty($_SESSION['flash_ident_err'])): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_ident_err']) ?></div>
          <?php unset($_SESSION['flash_ident_err']); ?>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-6">
            <form method="post" class="row g-2" autocomplete="off">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="update_nama">
              <div class="col-12">
                <label class="form-label small mb-1"><i class="bi bi-person"></i> Nama Lengkap</label>
                <input type="text" name="nama" class="form-control form-control-sm"
                       minlength="2" maxlength="80" required
                       value="<?= htmlspecialchars($me['nama'] ?? '') ?>">
                <div class="form-text small">2–80 karakter.</div>
              </div>
              <div class="col-12">
                <button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan Nama</button>
              </div>
            </form>
          </div>
          <div class="col-md-6">
            <form method="post" class="row g-2" autocomplete="off">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="update_username">
              <div class="col-12">
                <label class="form-label small mb-1"><i class="bi bi-at"></i> Username</label>
                <input type="text" name="username" class="form-control form-control-sm"
                       pattern="[a-z0-9._]{3,40}" minlength="3" maxlength="40" required
                       value="<?= htmlspecialchars($me['username'] ?? '') ?>">
                <div class="form-text small">Huruf kecil / angka / titik / underscore, 3–40 karakter.</div>
              </div>
              <div class="col-12">
                <button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Simpan Username</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>



    <!-- Revisi Juli 2026 — Fitur "Pertemananku" (CRUD), diletakkan DI ATAS Akun Strava.
         SQL tambahan:
           CREATE TABLE IF NOT EXISTS pertemanan (
             id SERIAL PRIMARY KEY,
             user_id INT NOT NULL,
             nama VARCHAR(120) NOT NULL,
             tanggal_kenalan DATE,
             kedekatan SMALLINT,
             catatan TEXT,
             created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
           );
           CREATE INDEX IF NOT EXISTS ix_pertemanan_user ON pertemanan(user_id);
    -->
    <?php
    $ptRows = [];
    try {
      $ptRows = db_all("SELECT * FROM pertemanan WHERE user_id=$1 ORDER BY COALESCE(kedekatan,0) DESC, nama ASC",
                       [(int)$u['id']]);
    } catch (Throwable $e) { $ptRows = []; }
    ?>
    <div class="card shadow-sm mt-3" data-live="profile-pertemanan"><div class="card-body">
      <h6 class="fw-semibold mb-2"><i class="bi bi-people-fill text-info"></i> Pertemananku
        <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= count($ptRows) ?></span>
      </h6>
      <p class="small text-muted mb-2">Catat teman-teman Anda: nama, tanggal kenalan, tanggal terakhir ketemu, dan level kedekatan (1 = sekilas, 5 = sahabat karib).</p>

      <form method="post" class="row g-1 mb-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="pertemanan_add">
        <div class="col-md-3"><input class="form-control form-control-sm" name="nama" maxlength="120" placeholder="Nama teman *" required></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="tanggal_kenalan" title="Kenal sejak"></div>
        <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="tanggal_terakhir_ketemu" title="Tanggal terakhir ketemu"></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="kedekatan">
            <option value="0">Level –</option>
            <option value="1">1 · Kenal sekilas</option>
            <option value="2">2 · Sesekali ngobrol</option>
            <option value="3">3 · Teman biasa</option>
            <option value="4">4 · Teman dekat</option>
            <option value="5">5 · Sahabat karib</option>
          </select>
        </div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="catatan" maxlength="500" placeholder="Catatan (opsional)"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-info text-white w-100" title="Tambah"><i class="bi bi-plus-lg"></i></button></div>
      </form>

      <div class="table-responsive" style="max-height:360px; overflow:auto;">
        <table class="table table-sm mb-0 align-middle" style="min-width:860px;">
          <thead class="table-light" style="position:sticky;top:0;z-index:2;">
            <tr>
              <th style="min-width:160px">Nama</th>
              <th style="min-width:110px">Kenal Sejak</th>
              <th style="min-width:130px">Terakhir Ketemu</th>
              <th style="min-width:110px">Kedekatan</th>
              <th style="min-width:200px">Catatan</th>
              <th style="width:110px" class="text-end"></th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$ptRows): ?>
            <tr><td colspan="6" class="text-center text-muted small py-3">Belum ada teman tercatat.</td></tr>
          <?php else: foreach ($ptRows as $p): 
            $tkt = $p['tanggal_terakhir_ketemu'] ?? null;
            $tktLabel = '-'; $tktCls = 'text-muted';
            if ($tkt) {
              $days = (int) floor((time() - strtotime($tkt)) / 86400);
              $tktLabel = htmlspecialchars(date('d M Y', strtotime($tkt))) . ' <span class="text-muted">(' . ($days<=0?'hari ini':($days.' hari lalu')) . ')</span>';
              $tktCls = $days > 180 ? 'text-danger' : ($days > 60 ? 'text-warning' : 'text-success');
            }
          ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($p['nama']) ?></td>
              <td class="small"><?= htmlspecialchars($p['tanggal_kenalan'] ?? '-') ?></td>
              <td class="small <?= $tktCls ?>"><?= $tktLabel ?></td>
              <td class="small"><?= $p['kedekatan'] ? str_repeat('★', (int)$p['kedekatan']) : '-' ?></td>
              <td class="small text-muted"><?= htmlspecialchars($p['catatan'] ?? '') ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#ptEdit<?= (int)$p['id'] ?>"><i class="bi bi-pencil"></i></button>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus teman ini?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="pertemanan_delete">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <tr class="collapse" id="ptEdit<?= (int)$p['id'] ?>">
              <td colspan="6">
                <form method="post" class="row g-1">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="pertemanan_update">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <div class="col-md-3"><input class="form-control form-control-sm" name="nama" maxlength="120" value="<?= htmlspecialchars($p['nama']) ?>" required></div>
                  <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="tanggal_kenalan" value="<?= htmlspecialchars($p['tanggal_kenalan'] ?? '') ?>" title="Kenal sejak"></div>
                  <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="tanggal_terakhir_ketemu" value="<?= htmlspecialchars($p['tanggal_terakhir_ketemu'] ?? '') ?>" title="Terakhir ketemu"></div>
                  <div class="col-md-2">
                    <select class="form-select form-select-sm" name="kedekatan">
                      <?php for($k=0;$k<=5;$k++): ?>
                        <option value="<?= $k ?>" <?= ((int)($p['kedekatan']??0))===$k?'selected':'' ?>><?= $k===0?'Level –':$k ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>
                  <div class="col-md-2"><input class="form-control form-control-sm" name="catatan" maxlength="500" value="<?= htmlspecialchars($p['catatan'] ?? '') ?>"></div>
                  <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-save"></i></button></div>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div></div>

    <!-- Revisi 19 Juni 2026 Part Q — Akun Strava & Nickname -->
    <div class="card shadow-sm mt-3" data-live="profile-strava"><div class="card-body">
      <!-- Revisi 24 Juni 2026 — Akun Strava dibungkus <details> (spoiler) -->
      <details class="spoiler-card">
      <summary class="form-label small fw-semibold mb-0" style="cursor:pointer;list-style:revert"><i class="bi bi-bicycle text-warning"></i> Akun Strava / ID Strava <span class="text-muted">(klik untuk buka/tutup)</span></summary>
      <form data-ajax method="post" class="text-start mt-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_strava">
        <div class="input-group input-group-sm">
          <input class="form-control" name="strava_account" maxlength="120" placeholder="cth: username atau ID Strava Anda" value="<?= htmlspecialchars($me['strava_account'] ?? '') ?>">
          <button class="btn btn-outline-primary" title="Simpan"><i class="bi bi-save"></i></button>
          <?php if(!empty($me['strava_account'])): ?>
          <button class="btn btn-outline-danger" formaction="" type="submit" name="_action" value="delete_strava" onclick="return confirm('Hapus akun Strava?')"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        </div>
        <?php if(!empty($me['strava_account'])):
          $sv = trim($me['strava_account']);
          // Revisi 19 Juni 2026 (R2) — selalu link ke strava.com (jangan pernah Google).
          $svClean = ltrim($sv, '@');
          if (preg_match('~^https?://~i', $sv)) {
              $sUrl = $sv;
          } elseif (preg_match('~^\d{3,}$~', $svClean)) {
              $sUrl = 'https://www.strava.com/athletes/'.$svClean;
          } elseif (preg_match('~^[a-zA-Z0-9._-]{3,40}$~', $svClean)) {
              $sUrl = 'https://www.strava.com/athletes/'.rawurlencode($svClean);
          } else {
              $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i','-', $svClean), '-'));
              $sUrl = $slug !== ''
                  ? 'https://www.strava.com/athletes/'.rawurlencode($slug)
                  : 'https://www.strava.com/';
          }
        ?>
          <div class="mt-2">
            <a class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener" href="<?= htmlspecialchars($sUrl) ?>"><i class="bi bi-box-arrow-up-right"></i> Buka profil Strava</a>
            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Untuk hasil terbaik isi <b>ID numerik Strava</b> (cth: <code>12345678</code>) atau URL lengkap profil Anda (cth: <code>https://www.strava.com/athletes/12345678</code>).</div>
          </div>
        <?php endif; ?>

      </form>
      </details>

      <form data-ajax method="post" class="text-start mt-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_nickname">
        <label class="form-label small fw-semibold"><i class="bi bi-person-badge text-info"></i> Nickname / Nama Samaran</label>
        <div class="input-group input-group-sm">
          <input class="form-control" name="nickname" maxlength="80" placeholder="cth: SiCepat, RunnerKuy, dsb" value="<?= htmlspecialchars($me['nickname'] ?? '') ?>">
          <button class="btn btn-outline-primary" title="Simpan"><i class="bi bi-save"></i></button>
          <?php if(!empty($me['nickname'])): ?>
          <button class="btn btn-outline-danger" formaction="" type="submit" name="_action" value="delete_nickname" onclick="return confirm('Hapus nickname?')"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        </div>
        <div class="form-text">Nickname tampil di profil publik Anda di samping nama asli.</div>
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
    <div class="card shadow-sm mb-3" data-live="profile-kesehatan"><div class="card-header"><i class="bi bi-heart-pulse text-danger"></i> Data Kesehatan (Publik) <a href="/kalkulator.php" class="btn btn-sm btn-outline-primary float-end"><i class="bi bi-calculator"></i> Kalkulator Sehat</a></div>
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

    <!-- Revisi 24 Juni 2026 — Badge & Achievement dibungkus <details> (spoiler) agar tidak memanjang ke bawah -->
    <details class="card shadow-sm spoiler-card"><summary class="card-header" style="cursor:pointer;list-style:revert"><i class="bi bi-award-fill text-warning"></i> Badge &amp; Achievement <span class="text-muted small">(klik untuk buka/tutup)</span></summary>
    <div class="card-body">
      <?php /* Revisi 20 Juni 2026 — Penjelasan cara mendapatkan Badge & Achievement */ ?>
      <div class="alert alert-info small mb-3">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle-fill"></i> Apa itu Badge & Achievement?</div>
        <p class="mb-2">Badge adalah lencana digital yang Anda dapatkan otomatis setelah memenuhi syarat aktivitas tertentu di SportApp.
        Setiap badge memberi bonus <strong>XP</strong> yang menambah level akun Anda.</p>
        <div class="fw-semibold mb-1">Cara mendapatkan:</div>
        <ul class="mb-2 ps-3">
          <li><i class="bi bi-check2-circle text-primary"></i> <strong>First Check-in</strong> (+30 XP) — Tercatat hadir pertama kali di sesi olahraga (absensi diinput admin, tanpa perlu scan barcode).</li>
          <li><i class="bi bi-person-running text-success"></i> <strong>Jogging 10x</strong> (+100 XP) — Hadir di sesi <em>Jogging</em> sebanyak 10 kali.</li>
          <li><i class="bi bi-shield-fill-check text-info"></i> <strong>Badminton Warrior</strong> (+120 XP) — Hadir di sesi <em>Badminton</em> sebanyak 10 kali.</li>
          <li><i class="bi bi-stars text-warning"></i> <strong>All Rounder</strong> (+150 XP) — Hadir di minimal 3 jenis olahraga berbeda.</li>
          <li><i class="bi bi-moon-stars text-dark"></i> <strong>Night Runner</strong> (+80 XP) — Hadir 5x di sesi olahraga malam (jam mulai ≥ 18:00).</li>
          <li><i class="bi bi-fire text-danger"></i> <strong>Rajin 4 Minggu</strong> (+150 XP) — Hadir minimal 1x setiap minggu selama 4 minggu berturut-turut.</li>
          <li><i class="bi bi-trophy-fill text-warning"></i> <strong>Top Attendance</strong> (+200 XP) — Masuk Top 3 kehadiran bulanan komunitas.</li>
          <li><i class="bi bi-graph-up text-success"></i> <strong>Consistency King</strong> (+180 XP) — Skor konsistensi kehadiran &gt; 85%.</li>
          <li><i class="bi bi-sun text-warning"></i> <strong>Early Bird</strong> (+60 XP) — 5x check-in lebih cepat dari 10 menit sebelum sesi dimulai.</li>
          <li><i class="bi bi-chat-heart-fill text-danger"></i> <strong>Forum Star</strong> (+70 XP) — Mengirim 50 post di forum komunitas.</li>
        </ul>
        <div class="small text-muted mb-0">
          <i class="bi bi-lightbulb"></i> Tips: tiap <strong>200 XP</strong> = naik 1 Level. Badge yang masih terkunci (abu-abu) berarti syaratnya belum terpenuhi —
          terus aktif berolahraga &amp; check-in untuk membuka semuanya!
        </div>
      </div>
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
    </div></details>

    <!-- ===== v7: Kondisi Terkini ===== -->
    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-activity text-danger"></i> Kondisi Terkini</div>
    <div class="card-body">
      <form data-ajax method="post" class="row g-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="kondisi_set">
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Status</label>
          <select name="status" class="form-select form-select-sm" onchange="document.getElementById('ketWrap').style.display = this.value==='sakit'?'block':'none';">
            <option value="sehat" <?= $kondisi['status']==='sehat'?'selected':'' ?>>🟢 Sehat</option>
            <option value="sakit" <?= $kondisi['status']==='sakit'?'selected':'' ?>>🔴 Sakit</option>
          </select>
        </div>
        <div class="col-md-7" id="ketWrap" style="display:<?= $kondisi['status']==='sakit'?'block':'none' ?>">
          <label class="form-label small fw-semibold">Keterangan sakit</label>
          <input class="form-control form-control-sm" name="keterangan" maxlength="500" placeholder="cth: demam, flu berat" value="<?= htmlspecialchars($kondisi['keterangan'] ?? '') ?>">
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
      </form>
      <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Jika <strong>Sakit</strong>, sesi-sesi mendatang otomatis terisi <em>sakit</em> di absen. Ubah ke <strong>Sehat</strong> dulu untuk bisa hadir.</div>
      <?php if(!empty($kondisi['updated_at'])): ?><small class="text-muted">Diperbarui: <?= date('d M Y H:i', strtotime($kondisi['updated_at'])) ?></small><?php endif; ?>
    </div></div>

    <!-- ===== v7: Pengalaman Hiking & Camping ===== -->
    <details class="card shadow-sm mt-3 spoiler-card" data-live="profile-pengalaman"><summary class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
      <span><i class="bi bi-mountain text-success"></i> Pengalaman Hiking &amp; Camping <span class="text-muted small">(klik buka/tutup)</span></span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#pengModal" onclick="event.stopPropagation();pengReset()"><i class="bi bi-plus-lg"></i> Tambah</button>
    </summary>
    <div class="card-body">
      <?php if(!$pengList): ?><p class="text-muted small mb-0 text-center">Belum ada pengalaman tercatat.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle" data-paginate="6">
        <thead><tr><th>Jenis</th><th>Judul</th><th>Lokasi</th><th>Tanggal</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($pengList as $p): ?>
          <tr>
            <td><span class="badge bg-<?= $p['jenis']==='hiking'?'success':'warning' ?>-subtle text-<?= $p['jenis']==='hiking'?'success':'warning' ?>"><i class="bi bi-<?= $p['jenis']==='hiking'?'signpost-split':'fire' ?>"></i> <?= htmlspecialchars($p['jenis']) ?></span></td>
            <td><strong><?= htmlspecialchars($p['judul']) ?></strong><?php if($p['deskripsi']): ?><div class="small text-muted"><?= htmlspecialchars(mb_substr($p['deskripsi'],0,80)) ?></div><?php endif; ?></td>
            <td class="small"><?= htmlspecialchars($p['lokasi'] ?? '—') ?></td>
            <td class="small"><?= $p['tanggal'] ? htmlspecialchars($p['tanggal']) : '—' ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" onclick='pengEdit(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
              <form data-ajax method="post" class="d-inline" onsubmit="return confirm('Hapus pengalaman ini?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="peng_del">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div></details>

    <!-- ===== v7: Perlengkapan Olahraga ===== -->
    <div data-live="perlengkapan-profile">
    <details class="card shadow-sm mt-3 spoiler-card"><summary class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;list-style:revert">
      <span><i class="bi bi-bag-check text-primary"></i> Perlengkapan Olahraga <span class="text-muted small">(klik buka/tutup)</span></span>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#perlModal" onclick="event.stopPropagation();perlReset()"><i class="bi bi-plus-lg"></i> Tambah</button>
    </summary>
    <div class="card-body">
      <?php if(!$perlList): ?><p class="text-muted small mb-0 text-center">Belum ada perlengkapan. Tambahkan agar terintegrasi otomatis dengan jadwal olahraga.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle" data-paginate="8">
        <thead><tr><th>Jenis Olahraga</th><th>Perlengkapan</th><th class="text-end">Jumlah</th><th>Catatan</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($perlList as $p): ?>
          <tr>
            <td><span class="pill"><?= htmlspecialchars($p['jenis_nama']) ?></span></td>
            <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
            <td class="text-end"><?= (int)$p['jumlah'] ?></td>
            <td class="small text-muted"><?= htmlspecialchars($p['catatan'] ?? '—') ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" onclick='perlEdit(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
              <form data-ajax method="post" class="d-inline" onsubmit="return confirm('Hapus perlengkapan ini?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="perl_del">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Perlengkapan otomatis muncul di "Jadwal Terdekat" sesuai jenis olahraga.</div>
    </div></details>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  if(window.bootstrap){document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>new bootstrap.Tooltip(el));}
});
</script>

<!-- ===== Titip Pesan (Guestbook) untuk Profil Saya ===== -->
<div class="card shadow-sm mt-3" data-live="guestbook-profile">
  <div class="card-header"><i class="bi bi-envelope-heart text-primary"></i> Titip Pesan untuk Saya
    <span class="badge bg-secondary"><?= count($gmRoots) ?></span>
  </div>
  <div class="card-body">
    <form data-ajax method="post" class="mb-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="gm_add">
      <textarea name="pesan" class="form-control" rows="2" maxlength="1000" placeholder="Tulis catatan / pengingat untuk diri sendiri, atau balasan ke pesan member..." required></textarea>
      <div class="text-end mt-2"><button class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Kirim</button></div>
    </form>
    <?php if(!$gmRoots): ?>
      <p class="text-muted small text-center mb-0">Belum ada titip pesan masuk.</p>
    <?php else: ?>
      <div class="list-group list-group-flush">
      <?php foreach($gmRoots as $g):
          $isMine  = (int)$u['id']===(int)$g['sender_user_id'];
          $isOwner = true; // di profile.php, saya selalu owner
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
                  <button class="btn btn-link btn-sm p-0 me-2" type="button" onclick="document.getElementById('gmReplyP<?= (int)$g['id'] ?>').classList.toggle('d-none')"><i class="bi bi-reply"></i> Reply</button>
                  <?php if($isMine): ?><button class="btn btn-link btn-sm p-0 me-2 text-primary" type="button" onclick="document.getElementById('gmEditP<?= (int)$g['id'] ?>').classList.toggle('d-none')"><i class="bi bi-pencil"></i></button><?php endif; ?>
                  <form data-ajax method="post" onsubmit="return confirm('Hapus pesan ini?')" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="_action" value="gm_del">
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </div>
              <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($g['pesan']) ?></div>
              <?php if($isMine): ?>
              <form data-ajax method="post" id="gmEditP<?= (int)$g['id'] ?>" class="d-none mt-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="gm_edit">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <textarea name="pesan" rows="2" maxlength="1000" class="form-control form-control-sm" required><?= htmlspecialchars($g['pesan']) ?></textarea>
                <div class="text-end mt-1"><button class="btn btn-sm btn-primary">Simpan</button></div>
              </form>
              <?php endif; ?>
              <form data-ajax method="post" id="gmReplyP<?= (int)$g['id'] ?>" class="d-none mt-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="gm_add">
                <input type="hidden" name="parent_id" value="<?= (int)$g['id'] ?>">
                <textarea name="pesan" rows="2" maxlength="1000" class="form-control form-control-sm" placeholder="Balas pesan..." required></textarea>
                <div class="text-end mt-1"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-reply"></i> Balas</button></div>
              </form>
              <?php $reps = $gmByParent[(int)$g['id']] ?? []; if($reps): ?>
                <div class="mt-2 ps-3 border-start">
                <?php foreach($reps as $rp): $isMineRp = (int)$u['id']===(int)$rp['sender_user_id']; ?>
                  <div class="d-flex gap-2 mt-2">
                    <?php if($rp['sender_foto']): ?>
                      <img src="<?= htmlspecialchars($rp['sender_foto']) ?>" class="rounded-circle zoomable" style="width:28px;height:28px;object-fit:cover">
                    <?php else: ?>
                      <?= user_avatar(null, $rp['sender_nama'], 28) ?>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center">
                        <div><a href="/user.php?id=<?= (int)$rp['sender_user_id'] ?>" class="fw-semibold text-decoration-none small"><?= htmlspecialchars($rp['sender_nama']) ?></a>
                          <small class="text-muted ms-2"><?= date('d M H:i', strtotime($rp['created_at'])) ?></small></div>
                        <form data-ajax method="post" onsubmit="return confirm('Hapus balasan?')" class="d-inline">
                          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                          <input type="hidden" name="_action" value="gm_del">
                          <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                          <button class="btn btn-link btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                      <div class="small" style="white-space:pre-wrap"><?= htmlspecialchars($rp['pesan']) ?></div>
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
  </div>
</div>

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


<!-- ===== v7: Modal Pengalaman ===== -->
<div class="modal fade" id="pengModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
  <form class="modal-content" data-ajax method="post" id="pengForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" id="pengAction" value="peng_add">
    <input type="hidden" name="id" id="pengId" value="">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-mountain text-success"></i> Pengalaman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-semibold">Jenis</label>
          <select name="jenis" id="pengJenis" class="form-select form-select-sm">
            <option value="hiking">Hiking</option><option value="camping">Camping</option>
          </select></div>
        <div class="col-md-8"><label class="form-label small fw-semibold">Judul</label>
          <input name="judul" id="pengJudul" class="form-control form-control-sm" maxlength="160" required></div>
        <div class="col-md-7"><label class="form-label small fw-semibold">Lokasi</label>
          <input name="lokasi" id="pengLokasi" class="form-control form-control-sm" maxlength="200"></div>
        <div class="col-md-5"><label class="form-label small fw-semibold">Tanggal</label>
          <input type="date" name="tanggal" id="pengTanggal" class="form-control form-control-sm"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Deskripsi</label>
          <textarea name="deskripsi" id="pengDeskripsi" rows="3" maxlength="2000" class="form-control form-control-sm"></textarea></div>
        <div class="col-12"><label class="form-label small fw-semibold">Foto URL (opsional)</label>
          <input name="foto_url" id="pengFoto" class="form-control form-control-sm" maxlength="500" placeholder="https://..."></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
  </form>
</div></div>

<!-- ===== v7: Modal Perlengkapan ===== -->
<div class="modal fade" id="perlModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
  <form class="modal-content" data-ajax method="post" id="perlForm">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" id="perlAction" value="perl_add">
    <input type="hidden" name="id" id="perlId" value="">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-bag-check text-primary"></i> Perlengkapan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label small fw-semibold">Jenis Olahraga</label>
          <select name="jenis_olahraga_id" id="perlJenisId" class="form-select form-select-sm">
            <option value="0">— pilih atau isi manual —</option>
            <?php foreach($jenisOR as $jn): ?><option value="<?= (int)$jn['id'] ?>"><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">atau Nama jenis (manual)</label>
          <input name="jenis_nama" id="perlJenisNama" class="form-control form-control-sm" maxlength="80" placeholder="cth: Badminton"></div>
        <div class="col-md-8"><label class="form-label small fw-semibold">Nama Perlengkapan</label>
          <input name="nama" id="perlNama" class="form-control form-control-sm" maxlength="120" required placeholder="cth: Raket"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Jumlah</label>
          <input type="number" min="1" name="jumlah" id="perlJumlah" class="form-control form-control-sm" value="1" required></div>
        <div class="col-12"><label class="form-label small fw-semibold">Catatan</label>
          <input name="catatan" id="perlCatatan" class="form-control form-control-sm" maxlength="200"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
  </form>
</div></div>

<script>
function pengReset(){document.getElementById('pengAction').value='peng_add';document.getElementById('pengId').value='';document.getElementById('pengForm').reset();}
function pengEdit(p){document.getElementById('pengAction').value='peng_edit';document.getElementById('pengId').value=p.id;document.getElementById('pengJenis').value=p.jenis||'hiking';document.getElementById('pengJudul').value=p.judul||'';document.getElementById('pengLokasi').value=p.lokasi||'';document.getElementById('pengTanggal').value=p.tanggal||'';document.getElementById('pengDeskripsi').value=p.deskripsi||'';document.getElementById('pengFoto').value=p.foto_url||'';new bootstrap.Modal(document.getElementById('pengModal')).show();}
function perlReset(){document.getElementById('perlAction').value='perl_add';document.getElementById('perlId').value='';document.getElementById('perlForm').reset();document.getElementById('perlJumlah').value=1;}
function perlEdit(p){document.getElementById('perlAction').value='perl_edit';document.getElementById('perlId').value=p.id;document.getElementById('perlJenisId').value=p.jenis_olahraga_id||0;document.getElementById('perlJenisNama').value=p.jenis_nama||'';document.getElementById('perlNama').value=p.nama||'';document.getElementById('perlJumlah').value=p.jumlah||1;document.getElementById('perlCatatan').value=p.catatan||'';new bootstrap.Modal(document.getElementById('perlModal')).show();}
</script>

<!-- ===== Revisi: WA Reminder untuk lengkapi Pengalaman Hiking/Camping & Perlengkapan ===== -->
<?php
try {
  $cntPeng = (int)db_val("SELECT COUNT(*) FROM user_pengalaman WHERE user_id=$1", [(int)$u['id']]);
  $cntPerl = (int)db_val("SELECT COUNT(*) FROM user_perlengkapan WHERE user_id=$1", [(int)$u['id']]);
} catch (Throwable $e) { $cntPeng = 0; $cntPerl = 0; }
$waSelf = preg_replace('/\D+/','', (string)($me['nomor_wa'] ?? ''));
if ($waSelf && str_starts_with($waSelf,'0')) $waSelf = '62'.substr($waSelf,1);
if ($waSelf && !$cntPeng) {
  $msg = rawurlencode("Halo ".$me['nama'].", lengkapi pengalaman hiking/camping kamu di profil KawanKeringat ya! Buka: https://".($_SERVER['HTTP_HOST']??'kawankeringat.app')."/profile.php");
  echo '<div class="container my-3"><div class="alert alert-warning d-flex justify-content-between align-items-center"><div><i class="bi bi-mountain"></i> Belum ada pengalaman hiking/camping. Yuk lengkapi!</div>'
     . '<a class="btn btn-sm btn-success" target="_blank" href="https://wa.me/'.$waSelf.'?text='.$msg.'"><i class="bi bi-whatsapp"></i> Ingatkan via WA</a></div></div>';
}
if ($waSelf && !$cntPerl) {
  $msg = rawurlencode("Halo ".$me['nama'].", lengkapi data perlengkapan olahraga kamu di profil KawanKeringat ya! Buka: https://".($_SERVER['HTTP_HOST']??'kawankeringat.app')."/profile.php");
  echo '<div class="container my-3"><div class="alert alert-info d-flex justify-content-between align-items-center"><div><i class="bi bi-bag-check"></i> Belum ada data perlengkapan olahraga. Yuk lengkapi!</div>'
     . '<a class="btn btn-sm btn-success" target="_blank" href="https://wa.me/'.$waSelf.'?text='.$msg.'"><i class="bi bi-whatsapp"></i> Ingatkan via WA</a></div></div>';
}
?>

<!-- ===== Revisi: Tema Warna Aplikasi dipindahkan ke BAGIAN BAWAH halaman ===== -->
<section class="container my-3" id="temaWarna" data-live="profile-tema">
  <div class="card shadow-sm">
    <div class="card-header"><i class="bi bi-palette-fill text-primary"></i> Tema Warna Aplikasi</div>
    <form data-ajax method="post" class="card-body">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="update_tema">
      <p class="small text-muted mb-2">Pilih warna utama tampilan aplikasi. Perubahan akan terlihat setelah halaman dimuat ulang.</p>
      <?php
        $temaSekarang = 'sky';
        try { $rr = db_one("SELECT COALESCE(tema_warna,'sky') AS t FROM users WHERE id=$1",[(int)$u['id']]); if ($rr) $temaSekarang = $rr['t']; } catch (Throwable $e) {}
        $opsi = [
          'sky'=>['Langit','#0ea5e9'], 'indigo'=>['Indigo','#6366f1'], 'emerald'=>['Emerald','#10b981'],
          'rose'=>['Mawar','#f43f5e'], 'amber'=>['Amber','#f59e0b'], 'violet'=>['Violet','#8b5cf6'], 'slate'=>['Slate','#475569'],
        ];
      ?>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($opsi as $k=>$v): ?>
          <label class="border rounded p-2 d-flex align-items-center gap-2" style="cursor:pointer;<?= $temaSekarang===$k?'border-color:#0ea5e9;border-width:2px;':'' ?>">
            <input type="radio" name="tema_warna" value="<?= $k ?>" <?= $temaSekarang===$k?'checked':'' ?> class="form-check-input m-0">
            <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= $v[1] ?>;"></span>
            <span class="small"><?= $v[0] ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary btn-sm mt-3"><i class="bi bi-save"></i> Simpan Tema</button>
    </form>
  </div>
</section>

<?php /* Revisi Juli 2026 — Auto reload setelah menyimpan Tema Warna.
       - Jika form disubmit via AJAX (data-ajax), server balas JSON {reload:true} dan
         script di bawah akan reload halaman.
       - Jika data-ajax handler global mendahului (dan reload gagal terpicu), fallback
         intercept submit → fetch → reload. */ ?>
<script>
(function(){
  var form = document.querySelector('#temaWarna form');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(form);
    fetch(location.pathname, {
      method:'POST', body: fd, credentials:'same-origin',
      headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).then(function(){ location.reload(); })
      .catch(function(){ location.reload(); });
  }, true);
  // Notifikasi kecil bila datang dari redirect ?tema_ok=1
  if (/[?&]tema_ok=1/.test(location.search)) {
    try {
      var wrap = document.getElementById('temaWarna');
      if (wrap) {
        var a = document.createElement('div');
        a.className = 'alert alert-success mt-2 py-2 small';
        a.textContent = 'Tema warna disimpan dan diterapkan.';
        wrap.querySelector('.card-body').prepend(a);
        setTimeout(function(){ a.remove(); }, 3500);
      }
    } catch(e) {}
  }
})();
</script>

<?php /* Revisi Juli 2026 — Blok Ubah Password Pribadi telah DIPINDAH ke bagian atas (di atas Pertemananku). */ ?>


<?php include __DIR__.'/includes/footer.php'; ?>

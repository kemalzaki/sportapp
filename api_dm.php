<?php
/**
 * API DM — Revisi 22 Juni 2026 R9.
 *
 * Perubahan R9:
 *  - SEMUA jalur mengembalikan JSON, TIDAK PERNAH redirect (sebelumnya
 *    require_login() melempar 302 ke /login.php sehingga fetch() di dm.php
 *    mengikuti redirect, menerima HTML, gagal JSON.parse → user melihat
 *    pesan "tidak terkirim" padahal sebenarnya session habis).
 *  - Wrapper try/catch global → exception apapun keluar sebagai JSON,
 *    bukan halaman HTML dari set_exception_handler di config/db.php.
 *  - rate_limit dibungkus try/catch; bila tabel rate_limit belum ada,
 *    request TIDAK diblokir (failsafe).
 *  - Notifications insert kompatibel dengan dua skema (kolom `isi` di
 *    schema lama, kolom `body` di skema v8). Tidak menggagalkan kirim DM.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require_once __DIR__.'/includes/scope.php';

header('Content-Type: application/json; charset=utf-8');

function dm_json_die(int $code, string $err, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['ok'=>false,'err'=>$err], $extra));
    exit;
}

// Pastikan respons error tetap JSON (bukan HTML dari set_exception_handler).
set_exception_handler(function(Throwable $e){
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok'=>false,'err'=>'server: '.$e->getMessage()]);
    exit;
});

// === Auth — JSON 401, JANGAN redirect ===
$u = current_user();
if (!$u) dm_json_die(401, 'belum login (session habis). Muat ulang halaman & login lagi.');
$uid = (int)$u['id'];
// Revisi Juli 2026 — DM hanya boleh ke sesama anggota komunitas.
$__dmScopeIds = scope_visible_user_ids();
if (!$__dmScopeIds) $__dmScopeIds = [$uid];
$__dmScopeArr = '{'.implode(',', array_map('intval', $__dmScopeIds)).'}';
function dm_scope_allowed(int $peer): bool { global $__dmScopeIds; return in_array($peer, $__dmScopeIds, true); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        dm_json_die(400, 'csrf tidak cocok (muat ulang halaman).');
    }
    try {
        if (!rate_limit('dm:'.$uid, 60, 60)) {
            dm_json_die(429, 'Terlalu banyak permintaan, coba lagi sebentar.');
        }
    } catch (Throwable $e) { /* abaikan: jangan blokir kirim pesan */ }

    $to    = (int)($_POST['to'] ?? 0);
    $pesan = trim($_POST['pesan'] ?? '');
    if ($to <= 0 || $to === $uid)  dm_json_die(400, 'penerima tidak valid');
    if (!dm_scope_allowed($to))    dm_json_die(403, 'Anda hanya dapat mengirim pesan ke sesama anggota komunitas.');
    if ($pesan === '')             dm_json_die(400, 'pesan kosong');
    if (mb_strlen($pesan) > 2000) $pesan = mb_substr($pesan, 0, 2000);

    try {
        if (!db_one("SELECT id FROM users WHERE id=$1", [$to])) dm_json_die(404, 'penerima tidak ditemukan');
    } catch (Throwable $e) { dm_json_die(500, 'cek user gagal: '.$e->getMessage()); }

    try {
        db_exec("INSERT INTO dm_messages(sender_id,receiver_id,pesan) VALUES($1,$2,$3)", [$uid,$to,$pesan]);
    } catch (Throwable $e) {
        dm_json_die(500, 'gagal menyimpan pesan: '.$e->getMessage());
    }

    // Push notification (opsional — JANGAN blokir kirim pesan bila gagal).
    // Tabel notifications bisa pakai kolom `isi` (lama) atau `body` (v8). Coba dua-duanya.
    try {
        $judul = '💬 Pesan baru dari ' . ($u['nama'] ?? 'member');
        $isi   = mb_substr($pesan, 0, 120);
        $url   = '/dm.php?u='.$uid;
        $okIns = @pg_query_params(db(),
          "INSERT INTO notifications(user_id, jenis, judul, isi, url) VALUES($1,'dm',$2,$3,$4)",
          [$to, $judul, $isi, $url]);
        if ($okIns === false) {
            @pg_query_params(db(),
              "INSERT INTO notifications(user_id, judul, body, url) VALUES($1,$2,$3,$4)",
              [$to, $judul, $isi, $url]);
        }
    } catch (Throwable $e) { /* silently */ }

    echo json_encode(['ok'=>true]); exit;
}

// Mark semua pesan masuk ke saya sebagai "delivered" (ceklis 2 abu-abu)
if (isset($_GET['delivered'])) {
    try { db_exec("UPDATE dm_messages SET delivered_at=now() WHERE receiver_id=$1 AND delivered_at IS NULL", [$uid]); }
    catch (Throwable $e) {}
    echo json_encode(['ok'=>true]); exit;
}

if (isset($_GET['find'])) {
    $q = '%'.strtolower(trim($_GET['find'])).'%';
    try {
        $rows = db_all("SELECT id,nama,username,foto_url FROM users
                        WHERE id<>$1 AND id = ANY($3::int[])
                          AND (LOWER(nama) LIKE $2 OR LOWER(COALESCE(username,'')) LIKE $2)
                        ORDER BY nama LIMIT 15", [$uid, $q, $__dmScopeArr]);
    } catch (Throwable $e) { $rows = []; }
    echo json_encode($rows); exit;
}

if (isset($_GET['unread'])) {
    try {
        $row = db_one("SELECT COUNT(*) AS c FROM dm_messages WHERE receiver_id=$1 AND read_at IS NULL", [$uid]);
    } catch (Throwable $e) { $row = ['c'=>0]; }
    echo json_encode(['unread' => (int)($row['c'] ?? 0)]); exit;
}

if (isset($_GET['status'])) {
    $pid = (int)$_GET['status'];
    if ($pid <= 0) { echo json_encode(['online'=>false]); exit; }
    try {
        $row = db_one("SELECT last_seen, EXTRACT(EPOCH FROM last_seen)::bigint AS ts,
                              (last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL '2 minutes') AS online
                       FROM users WHERE id=$1", [$pid]);
    } catch (Throwable $e) { $row = null; }
    echo json_encode([
      'online'        => !empty($row['online']) && $row['online'] !== 'f' && $row['online'] !== false ? true : false,
      'last_seen'     => $row['last_seen'] ?? null,
      'last_seen_ts'  => isset($row['ts']) ? (int)$row['ts'] : null,
    ]); exit;
}

if (isset($_GET['threads'])) {
    try {
        $rows = db_all("
          SELECT u.id, u.nama, u.foto_url,
            (SELECT pesan FROM dm_messages m
               WHERE (m.sender_id=u.id AND m.receiver_id=\$1) OR (m.sender_id=\$1 AND m.receiver_id=u.id)
               ORDER BY m.id DESC LIMIT 1) AS last_msg,
            (SELECT created_at FROM dm_messages m
               WHERE (m.sender_id=u.id AND m.receiver_id=\$1) OR (m.sender_id=\$1 AND m.receiver_id=u.id)
               ORDER BY m.id DESC LIMIT 1) AS last_at,
            (SELECT COUNT(*) FROM dm_messages m
               WHERE m.sender_id=u.id AND m.receiver_id=\$1 AND m.read_at IS NULL) AS unread
          FROM users u
          WHERE u.id IN (
            SELECT DISTINCT CASE WHEN sender_id=\$1 THEN receiver_id ELSE sender_id END
              FROM dm_messages WHERE sender_id=\$1 OR receiver_id=\$1)
            AND u.id = ANY(\$2::int[])
          ORDER BY last_at DESC NULLS LAST
          LIMIT 30
        ", [$uid, $__dmScopeArr]);
    } catch (Throwable $e) { $rows = []; }
    echo json_encode(['threads' => $rows]); exit;
}

$peer  = (int)($_GET['peer']  ?? 0);
$since = (int)($_GET['since'] ?? 0);
if ($peer <= 0) { echo json_encode(['messages'=>[]]); exit; }
if (!dm_scope_allowed($peer)) { echo json_encode(['messages'=>[], 'statuses'=>[], 'err'=>'out_of_scope']); exit; }

// Tandai pesan masuk dari peer ini: delivered + read.
try {
    db_exec("UPDATE dm_messages SET delivered_at=COALESCE(delivered_at, now()), read_at=now()
             WHERE receiver_id=$1 AND sender_id=$2 AND read_at IS NULL",
        [$uid, $peer]);
} catch (Throwable $e) { /* jangan jatuhkan polling */ }

try {
    $rows = db_all("SELECT id, sender_id, receiver_id, pesan, created_at, delivered_at, read_at
                    FROM dm_messages
                    WHERE id > $1
                      AND ((sender_id=$2 AND receiver_id=$3) OR (sender_id=$3 AND receiver_id=$2))
                    ORDER BY id ASC LIMIT 200", [$since, $uid, $peer]);
} catch (Throwable $e) {
    $rows = db_all("SELECT id, sender_id, receiver_id, pesan, created_at
                    FROM dm_messages
                    WHERE id > $1
                      AND ((sender_id=$2 AND receiver_id=$3) OR (sender_id=$3 AND receiver_id=$2))
                    ORDER BY id ASC LIMIT 200", [$since, $uid, $peer]);
    foreach ($rows as &$r) { $r['delivered_at']=null; $r['read_at']=null; }
    unset($r);
}

$statuses = [];
try {
    $statuses = db_all("SELECT id, delivered_at, read_at FROM dm_messages
                        WHERE sender_id=$1 AND receiver_id=$2
                        ORDER BY id DESC LIMIT 80", [$uid, $peer]);
} catch (Throwable $e) { $statuses = []; }

echo json_encode(['messages'=>$rows, 'statuses'=>$statuses]);

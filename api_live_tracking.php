<?php
/**
 * Revisi 15 Juni 2026 — API Live Tracking (Beacon)
 *
 * Endpoint (semua via query/POST ke file ini):
 *   POST  ?action=start        { judul, pesan, olahraga, durasi_jam } -> {token, url}
 *   POST  ?action=push         { token, lat, lng, accuracy?, speed?, heading? }
 *   POST  ?action=stop         { token }
 *   GET   ?action=view&token=  -> { session, last_point, points[] }   (PUBLIK, tanpa login)
 *   GET   ?action=mine         -> daftar sesi milik user (LOGIN)
 *   POST  ?action=contact_add  { nama, nomor_wa, email, relasi }
 *   POST  ?action=contact_del  { id }
 *
 * Catatan keamanan:
 *   - "push" hanya butuh token (karena dipanggil dari device pelaku olahraga;
 *     token diperlakukan sebagai kredensial sesi singkat).
 *   - "view" PUBLIK by design: penerima tautan tidak perlu login.
 *   - Sesi otomatis kedaluwarsa via kolom expires_at; view hanya menampilkan
 *     data sampai expires_at + 30 menit untuk grace period.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jout($a, int $code = 200) { http_response_code($code); echo json_encode($a); exit; }

// Auto-migrasi (idempotent) — aman dipanggil setiap request.
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_sessions (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    token VARCHAR(48) NOT NULL UNIQUE, judul TEXT NOT NULL DEFAULT 'Live Tracking',
    pesan TEXT, olahraga TEXT NOT NULL DEFAULT 'lari',
    started_at TIMESTAMP NOT NULL DEFAULT now(), ended_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT (now() + INTERVAL '12 hours'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_lat DOUBLE PRECISION, last_lng DOUBLE PRECISION, last_seen_at TIMESTAMP
)");
@db_exec("CREATE INDEX IF NOT EXISTS lts_token_idx ON live_tracking_sessions(token)");
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_points (
    id BIGSERIAL PRIMARY KEY,
    session_id BIGINT NOT NULL REFERENCES live_tracking_sessions(id) ON DELETE CASCADE,
    lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL,
    accuracy_m DOUBLE PRECISION, speed_mps DOUBLE PRECISION, heading_deg DOUBLE PRECISION,
    ts TIMESTAMP NOT NULL DEFAULT now()
)");
@db_exec("CREATE INDEX IF NOT EXISTS ltp_session_idx ON live_tracking_points(session_id, id)");
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_contacts (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    nama TEXT NOT NULL, nomor_wa TEXT, email TEXT, relasi TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* ---------- VIEW (publik) ---------- */
if ($action === 'view' && $method === 'GET') {
    $tok = trim((string)($_GET['token'] ?? ''));
    if ($tok === '') jout(['ok'=>false, 'err'=>'token kosong'], 400);
    $s = db_one("SELECT s.id, s.judul, s.pesan, s.olahraga, s.started_at, s.ended_at,
                        s.expires_at, s.is_active, s.last_lat, s.last_lng, s.last_seen_at,
                        u.nama AS user_nama
                 FROM live_tracking_sessions s
                 JOIN users u ON u.id = s.user_id
                 WHERE s.token=$1", [$tok]);
    if (!$s) jout(['ok'=>false, 'err'=>'sesi tidak ditemukan'], 404);
    // Grace period 30 menit setelah expires_at agar penerima masih bisa lihat ringkasan.
    $expired = strtotime($s['expires_at']) < (time() - 30*60);
    $pts = db_all("SELECT lat, lng, ts, speed_mps FROM live_tracking_points
                   WHERE session_id=$1 ORDER BY id ASC", [(int)$s['id']]);
    jout([
        'ok'        => true,
        'session'   => $s,
        'expired'   => $expired,
        'points'    => array_map(function($p){
            return [
                'lat'=>(float)$p['lat'], 'lng'=>(float)$p['lng'],
                'ts'=>$p['ts'], 'speed'=>$p['speed_mps']!==null ? (float)$p['speed_mps'] : null,
            ];
        }, $pts),
    ]);
}

/* ---------- PUSH titik (butuh token saja) ---------- */
if ($action === 'push' && $method === 'POST') {
    $tok = trim((string)($_POST['token'] ?? ''));
    $lat = (float)($_POST['lat'] ?? 0);
    $lng = (float)($_POST['lng'] ?? 0);
    if ($tok === '' || ($lat == 0 && $lng == 0)) jout(['ok'=>false, 'err'=>'param kurang'], 400);
    $s = db_one("SELECT id, is_active, expires_at FROM live_tracking_sessions WHERE token=$1", [$tok]);
    if (!$s) jout(['ok'=>false, 'err'=>'sesi tidak ditemukan'], 404);
    if (!$s['is_active'] || strtotime($s['expires_at']) < time()) {
        jout(['ok'=>false, 'err'=>'sesi tidak aktif / kadaluarsa'], 410);
    }
    $sid = (int)$s['id'];
    $acc = isset($_POST['accuracy']) ? (float)$_POST['accuracy'] : null;
    $spd = isset($_POST['speed'])    ? (float)$_POST['speed']    : null;
    $hdg = isset($_POST['heading'])  ? (float)$_POST['heading']  : null;
    db_exec("INSERT INTO live_tracking_points(session_id,lat,lng,accuracy_m,speed_mps,heading_deg)
             VALUES($1,$2,$3,$4,$5,$6)", [$sid,$lat,$lng,$acc,$spd,$hdg]);
    db_exec("UPDATE live_tracking_sessions SET last_lat=$1,last_lng=$2,last_seen_at=now() WHERE id=$3",
        [$lat,$lng,$sid]);
    jout(['ok'=>true]);
}

/* ---------- Semua sisanya WAJIB login ---------- */
require_login();
$u = current_user(); $uid = (int)$u['id'];

/* ---------- START ---------- */
if ($action === 'start' && $method === 'POST') {
    $judul    = trim((string)($_POST['judul']    ?? 'Live Tracking'));
    $pesan    = trim((string)($_POST['pesan']    ?? ''));
    $olahraga = trim((string)($_POST['olahraga'] ?? 'lari'));
    $jam      = max(1, min(24, (int)($_POST['durasi_jam'] ?? 6)));
    // Token kuat tapi cukup pendek utk URL.
    $tok = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    db_exec("INSERT INTO live_tracking_sessions
             (user_id, token, judul, pesan, olahraga, expires_at)
             VALUES($1,$2,$3,$4,$5, now() + ($6 || ' hours')::interval)",
        [$uid, $tok, $judul, $pesan ?: null, $olahraga, (string)$jam]);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url    = $scheme.'://'.$host.'/track_view.php?token='.$tok;
    jout(['ok'=>true, 'token'=>$tok, 'url'=>$url]);
}

/* ---------- STOP ---------- */
if ($action === 'stop' && $method === 'POST') {
    $tok = trim((string)($_POST['token'] ?? ''));
    $row = db_one("SELECT id FROM live_tracking_sessions WHERE token=$1 AND user_id=$2", [$tok, $uid]);
    if (!$row) jout(['ok'=>false, 'err'=>'tidak ditemukan'], 404);
    db_exec("UPDATE live_tracking_sessions SET is_active=FALSE, ended_at=now() WHERE id=$1", [(int)$row['id']]);
    jout(['ok'=>true]);
}

/* ---------- MINE: daftar sesi user ---------- */
if ($action === 'mine' && $method === 'GET') {
    $rows = db_all("SELECT id, token, judul, olahraga, started_at, ended_at, expires_at,
                           is_active, last_lat, last_lng, last_seen_at
                    FROM live_tracking_sessions WHERE user_id=$1
                    ORDER BY id DESC LIMIT 30", [$uid]);
    jout(['ok'=>true, 'rows'=>$rows]);
}

/* ---------- KONTAK DARURAT ---------- */
if ($action === 'contact_add' && $method === 'POST') {
    $nama = trim((string)($_POST['nama'] ?? ''));
    if ($nama === '') jout(['ok'=>false, 'err'=>'nama wajib'], 400);
    db_exec("INSERT INTO live_tracking_contacts(user_id,nama,nomor_wa,email,relasi)
             VALUES($1,$2,$3,$4,$5)",
        [$uid, $nama, $_POST['nomor_wa'] ?? null, $_POST['email'] ?? null, $_POST['relasi'] ?? null]);
    jout(['ok'=>true]);
}
if ($action === 'contact_del' && $method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    db_exec("DELETE FROM live_tracking_contacts WHERE id=$1 AND user_id=$2", [$id, $uid]);
    jout(['ok'=>true]);
}
if ($action === 'contact_list' && $method === 'GET') {
    $rows = db_all("SELECT id, nama, nomor_wa, email, relasi FROM live_tracking_contacts
                    WHERE user_id=$1 ORDER BY id DESC", [$uid]);
    jout(['ok'=>true, 'rows'=>$rows]);
}

jout(['ok'=>false, 'err'=>'action tidak dikenal'], 400);

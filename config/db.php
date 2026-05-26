<?php
/**
 * Koneksi PostgreSQL native (pg_*) — TANPA PDO.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$DATABASE_URL = getenv('DATABASE_URL');
if ($DATABASE_URL) {
    $u = parse_url($DATABASE_URL);
    $DB_HOST = $u['host'] ?? 'localhost';
    $DB_PORT = $u['port'] ?? '5432';
    $DB_NAME = ltrim($u['path'] ?? '/postgres', '/');
    $DB_USER = $u['user'] ?? 'postgres';
    $DB_PASS = $u['pass'] ?? '';
} else {
    $DB_HOST = getenv('DB_HOST') ?: 'pg-c8c7f-adamsasmita534-4b4d.e.aivencloud.com';
    $DB_PORT = getenv('DB_PORT') ?: '18028';
    $DB_NAME = getenv('DB_NAME') ?: 'defaultdb';
    $DB_USER = getenv('DB_USER') ?: 'avnadmin';
    $DB_PASS = getenv('DB_PASS') ?: 'AVNS_y5gOzXZcbIzr4ENiNug';
}
$DB_SSL = getenv('DB_SSLMODE') ?: 'require';

$conninfo = sprintf("host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
    $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $DB_SSL);

$dbconn = @pg_connect($conninfo);
if (!$dbconn) {
    http_response_code(500);
    die("Koneksi database gagal. Pastikan ekstensi php-pgsql aktif & kredensial benar.");
}

// === Timezone Asia/Jakarta (GMT+7) untuk konsistensi tampilan waktu ===
date_default_timezone_set('Asia/Jakarta');
@pg_query($dbconn, "SET TIME ZONE 'Asia/Jakarta'");

function db() { global $dbconn; return $dbconn; }

function db_query(string $sql, array $params = []) {
    $res = @pg_query_params(db(), $sql, $params);
    if ($res === false) {
        $err = pg_last_error(db());
        error_log('DB ERROR: ' . $err . " | SQL: $sql");
        // Tampilkan popup error ke user (non-fatal jika ada try/catch)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['error_popup'] = "SQL: $sql\n\n" . $err;
        }
        throw new RuntimeException('Query gagal: ' . $err);
    }
    return $res;
}
function db_all(string $sql, array $params = []): array {
    $r = db_query($sql, $params); $rows = [];
    while ($row = pg_fetch_assoc($r)) $rows[] = $row;
    return $rows;
}
function db_one(string $sql, array $params = []): ?array {
    $r = db_query($sql, $params); $row = pg_fetch_assoc($r);
    return $row === false ? null : $row;
}
function db_val(string $sql, array $params = []) {
    $r = db_query($sql, $params); $row = pg_fetch_row($r);
    return $row[0] ?? null;
}
function db_exec(string $sql, array $params = []): int {
    $r = db_query($sql, $params); return pg_affected_rows($r);
}

/* ---------- Global handler: tangkap query error fatal ---------- */
set_exception_handler(function(Throwable $e) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error_popup'] = $e->getMessage();
    }
    error_log('UNCAUGHT: '.$e->getMessage());
    if (!headers_sent()) header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php'));
    echo "<pre style='padding:20px;font-family:monospace;background:#fee;color:#900'>Terjadi error: ".htmlspecialchars($e->getMessage())."</pre>";
});

/* ---------- Auto-migration: tambah kolom jika belum ada ---------- */
try {
    @pg_query(db(), "ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS jam_mulai TIME");
    @pg_query(db(), "ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS jam_selesai TIME");
    @pg_query(db(), "ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS durasi_menit INTEGER");
    // === Tambah kolom WhatsApp & jenis kelamin user ===
    @pg_query(db(), "ALTER TABLE users ADD COLUMN IF NOT EXISTS nomor_wa VARCHAR(25)");
    @pg_query(db(), "ALTER TABLE users ADD COLUMN IF NOT EXISTS jenis_kelamin VARCHAR(10)");
    // === Lat / Lng untuk tempat (idempotent) ===
    @pg_query(db(), "ALTER TABLE tempat ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION");
    @pg_query(db(), "ALTER TABLE tempat ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION");
    // === Forum chat: kolom updated_at untuk fitur edit pesan ===
    @pg_query(db(), "ALTER TABLE chat_forum ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP");
} catch (Throwable $e) { /* ignore */ }

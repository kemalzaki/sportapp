<?php
/**
 * Koneksi PostgreSQL native (pg_*) — TANPA PDO.
 */

// Muat env lokal jika tersedia
if (is_file(__DIR__ . '/env.local.php')) {
    require_once __DIR__ . '/env.local.php';
}

if (session_status() === PHP_SESSION_NONE) {
    $_https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $_isSecure = (!empty($_SERVER['HTTPS']) && $_https !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    @ini_set('session.gc_maxlifetime', 60*60*24*30);
    @ini_set('session.cookie_lifetime', 60*60*24*30);
    session_set_cookie_params([
        'lifetime' => 60*60*24*30,
        'path'     => '/',
        'secure'   => $_isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$DATABASE_URL = getenv('DATABASE_URL');
if ($DATABASE_URL) {
    $u = parse_url($DATABASE_URL);
    $DB_HOST = $u['host'] ?? 'localhost';
    $DB_PORT = $u['port'] ?? '5432';
    $DB_NAME = ltrim($u['path'] ?? '/postgres', '/');
    $DB_USER = $u['user'] ?? 'postgres';
    $DB_PASS = $u['pass'] ?? '';
} else {
    // Memprioritaskan getenv dari env.local.php, jika kosong gunakan default internal Alwaysdata
    $DB_HOST = getenv('DB_HOST') ?: 'postgresql-hapfam.alwaysdata.net';
    $DB_PORT = getenv('DB_PORT') ?: '5432';
    $DB_NAME = getenv('DB_NAME') ?: 'hapfam_sportapp';
    $DB_USER = getenv('DB_USER') ?: 'hapfam';
    $DB_PASS = getenv('DB_PASS') ?: 'kmzwa8awaa@@@';
}
$DB_SSL = getenv('DB_SSLMODE') ?: 'prefer';

$conninfo = sprintf("host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
    $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $DB_SSL);

// Nyalakan error reporting sementara jika koneksi gagal total untuk melihat alasan erornya
$dbconn = @pg_connect($conninfo);
if (!$dbconn) {
    http_response_code(500);
    die("Koneksi database gagal. Detail Error: " . pg_last_error());
}

date_default_timezone_set('Asia/Jakarta');
@pg_query($dbconn, "SET TIME ZONE 'Asia/Jakarta'");

function db() { global $dbconn; return $dbconn; }

function db_query(string $sql, array $params = []) {
    $res = @pg_query_params(db(), $sql, $params);
    if ($res === false) {
        $err = pg_last_error(db());
        error_log('DB ERROR: ' . $err . " | SQL: $sql");
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

/* Global handler error */
set_exception_handler(function(Throwable $e) {
    $SHOW_DETAIL = true; 
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error_popup'] = $e->getMessage();
    }
    error_log('UNCAUGHT: '.$e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = $SHOW_DETAIL ? htmlspecialchars($e->getMessage()) : 'Terjadi kesalahan server.';
    $trace = $SHOW_DETAIL ? htmlspecialchars($e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString()) : '';
    echo "<!doctype html><meta charset='utf-8'><title>Error</title>";
    echo "<div style='max-width:880px;margin:40px auto;font-family:system-ui,sans-serif;padding:20px;'>";
    echo "<h2 style='color:#b91c1c'>Terjadi error pada server</h2>";
    echo "<pre style='padding:16px;background:#fef2f2;color:#7f1d1d;border:1px solid #fecaca;border-radius:8px;white-space:pre-wrap;'>$msg</pre>";
    if ($trace) echo "<details style='margin-top:10px'><summary>Detail (file & trace)</summary><pre style='padding:12px;background:#f8fafc;color:#334155;border:1px solid #e2e8f0;border-radius:8px;white-space:pre-wrap;'>$trace</pre></details>";
    echo "<p style='margin-top:16px'><a href='/index.php' style='color:#0ea5e9'>&larr; Ke beranda</a> &middot; <a href='/login.php' style='color:#0ea5e9'>Login ulang</a></p>";
    echo "</div>";
});

// BLOK AUTO-MIGRATION LAMBAT TELAH DIHAPUS TOTAL DARI SINI AGAR TIDAK MEMBUAT SERVER TIMEOUT
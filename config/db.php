<?php
/**
 * Koneksi PostgreSQL native (pg_*) — TANPA PDO.
 * Cocok untuk hosting yang tidak menyediakan ekstensi pdo_pgsql,
 * misal Render.com / shared hosting yang hanya menyediakan php-pgsql.
 *
 * Mendukung DATABASE_URL (gaya Render/Heroku) maupun variabel terpisah.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$DATABASE_URL = getenv('DATABASE_URL');

if ($DATABASE_URL) {
    // postgres://user:pass@host:port/dbname
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

$conninfo = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
    $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS, $DB_SSL
);

$dbconn = @pg_connect($conninfo);
if (!$dbconn) {
    http_response_code(500);
    die("Koneksi database gagal. Pastikan ekstensi php-pgsql aktif & kredensial benar.");
}

/* ---------- Helper functions ---------- */

function db() {
    global $dbconn; return $dbconn;
}

/**
 * Jalankan parametrized query.
 * Pakai placeholder $1, $2, ... gaya pg_query_params.
 */
function db_query(string $sql, array $params = []) {
    $res = pg_query_params(db(), $sql, $params);
    if ($res === false) {
        error_log('DB ERROR: ' . pg_last_error(db()) . " | SQL: $sql");
        throw new RuntimeException('Query gagal: ' . pg_last_error(db()));
    }
    return $res;
}

function db_all(string $sql, array $params = []): array {
    $r = db_query($sql, $params);
    $rows = [];
    while ($row = pg_fetch_assoc($r)) $rows[] = $row;
    return $rows;
}

function db_one(string $sql, array $params = []): ?array {
    $r = db_query($sql, $params);
    $row = pg_fetch_assoc($r);
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = []) {
    $r = db_query($sql, $params);
    $row = pg_fetch_row($r);
    return $row[0] ?? null;
}

function db_exec(string $sql, array $params = []): int {
    $r = db_query($sql, $params);
    return pg_affected_rows($r);
}

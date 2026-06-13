<?php
/**
 * Koneksi PostgreSQL native (pg_*) — TANPA PDO.
 */

// Muat env lokal (Midtrans dsb) bila tersedia — hanya men-set variabel
// yang BELUM didefinisikan di environment, jadi aman untuk production.
if (is_file(__DIR__ . '/env.local.php')) {
    require_once __DIR__ . '/env.local.php';
}
if (session_status() === PHP_SESSION_NONE) {
    // === Member tetap login (cookie persistent 30 hari) ===
    $_cp = session_get_cookie_params();
    @ini_set('session.gc_maxlifetime', 60*60*24*30);
    @ini_set('session.cookie_lifetime', 60*60*24*30);
    session_set_cookie_params([
        'lifetime' => 60*60*24*30,
        'path'     => '/',
        'domain'   => $_cp['domain'] ?? '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Revisi 13 Juni 2026: cookie session sudah persistent via session_set_cookie_params di atas.
    // Manual setcookie() di sini menyebabkan dua header Set-Cookie ketika login memanggil
    // session_regenerate_id(true) -> browser memakai session ID lama -> user balik ke /login.php.
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
    // === Quick absen event ===
    @pg_query(db(), "ALTER TABLE event_peserta ADD COLUMN IF NOT EXISTS status VARCHAR(12)");
    @pg_query(db(), "ALTER TABLE event_peserta ADD COLUMN IF NOT EXISTS keterangan TEXT");
    // === Kontrol tempat yang tampil di halaman Booking Lapangan ===
    $cekTampil = @pg_query(db(), "SELECT 1 FROM information_schema.columns WHERE table_name='tempat' AND column_name='tampil_booking'");
    if ($cekTampil !== false && pg_num_rows($cekTampil) === 0) {
        @pg_query(db(), "ALTER TABLE tempat ADD COLUMN tampil_booking BOOLEAN NOT NULL DEFAULT false");
        // Default: tampilkan hanya jenis Badminton, Futsal, Biliar/Biliard
        @pg_query(db(), "UPDATE tempat SET tampil_booking = true
                         WHERE jenis_id IN (SELECT id FROM jenis_olahraga WHERE nama IN ('Badminton','Futsal','Biliar','Biliard'))");
    }
} catch (Throwable $e) { /* ignore */ }

/* ---------- Revisi 30 Mei 2026: tabel baru ---------- */
try {
    // Donasi Kegiatan: rekening dikelola admin via CRUD
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS donasi_rekening (
        id SERIAL PRIMARY KEY,
        bank VARCHAR(60) NOT NULL,
        nomor VARCHAR(60) NOT NULL,
        atas_nama VARCHAR(120) NOT NULL,
        keterangan VARCHAR(200),
        aktif BOOLEAN NOT NULL DEFAULT true,
        urutan INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");

    // Rekap pengeluaran kegiatan olahraga (relasi ke jadwal)
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS pengeluaran_kegiatan (
        id SERIAL PRIMARY KEY,
        jadwal_id INT REFERENCES jadwal(id) ON DELETE SET NULL,
        tanggal DATE NOT NULL DEFAULT CURRENT_DATE,
        kategori VARCHAR(60),
        judul VARCHAR(200) NOT NULL,
        jumlah BIGINT NOT NULL DEFAULT 0,
        catatan TEXT,
        bukti_url TEXT,
        created_by INT REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");

    // === Jajanan (Gojek-style) ===
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS jajanan (
        id SERIAL PRIMARY KEY,
        nama VARCHAR(160) NOT NULL,
        deskripsi TEXT,
        harga INT NOT NULL DEFAULT 0,
        stok INT NOT NULL DEFAULT 0,
        foto_url TEXT,
        kategori VARCHAR(60),
        aktif BOOLEAN NOT NULL DEFAULT true,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS jajanan_pesanan (
        id SERIAL PRIMARY KEY,
        kode VARCHAR(20) UNIQUE NOT NULL,
        nama_pemesan VARCHAR(120) NOT NULL,
        no_wa VARCHAR(25) NOT NULL,
        alamat TEXT NOT NULL,
        catatan TEXT,
        subtotal BIGINT NOT NULL DEFAULT 0,
        ongkir BIGINT NOT NULL DEFAULT 0,
        total BIGINT NOT NULL DEFAULT 0,
        metode VARCHAR(20) DEFAULT 'cod',
        status VARCHAR(20) NOT NULL DEFAULT 'baru',
        kurir_user_id INT REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        updated_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS jajanan_pesanan_item (
        id SERIAL PRIMARY KEY,
        pesanan_id INT NOT NULL REFERENCES jajanan_pesanan(id) ON DELETE CASCADE,
        jajanan_id INT REFERENCES jajanan(id) ON DELETE SET NULL,
        nama VARCHAR(160) NOT NULL,
        harga INT NOT NULL DEFAULT 0,
        qty INT NOT NULL DEFAULT 1
    )");

    // === Revisi 31 Mei 2026 v2: kolom & tabel tambahan ===
    @pg_query(db(), "ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS foto_file_id VARCHAR(120)");
    // === Revisi 1 Jun 2026: lat/lng lokasi jajanan ===
    @pg_query(db(), "ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lat NUMERIC(10,6)");
    @pg_query(db(), "ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lng NUMERIC(10,6)");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS pickup_lat NUMERIC(10,6)");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS pickup_lng NUMERIC(10,6)");

    // === Revisi 2 Jun 2026 (lanjutan): Toko / Pedagang ===
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS toko (
        id SERIAL PRIMARY KEY,
        nama VARCHAR(160) NOT NULL,
        deskripsi TEXT,
        alamat TEXT,
        no_wa VARCHAR(25),
        lat NUMERIC(10,6),
        lng NUMERIC(10,6),
        aktif BOOLEAN NOT NULL DEFAULT true,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    @pg_query(db(), "ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS toko_id INT REFERENCES toko(id) ON DELETE SET NULL");
    @pg_query(db(), "CREATE INDEX IF NOT EXISTS jajanan_toko_idx ON jajanan(toko_id)");

    // === Revisi 2 Jun 2026: Midtrans columns ===
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending'");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS midtrans_order_id VARCHAR(40)");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS snap_token VARCHAR(120)");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS snap_redirect TEXT");
    @pg_query(db(), "ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS stok_dipotong BOOLEAN NOT NULL DEFAULT false");

    // === Lacak HP oleh admin (heartbeat lokasi tiap user) ===
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS device_locations (
        user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        lat NUMERIC(10,6) NOT NULL,
        lng NUMERIC(10,6) NOT NULL,
        accuracy_m NUMERIC(8,2),
        device_label VARCHAR(120),
        updated_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    @pg_query(db(), "CREATE TABLE IF NOT EXISTS device_location_history (
        id BIGSERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        lat NUMERIC(10,6) NOT NULL,
        lng NUMERIC(10,6) NOT NULL,
        accuracy_m NUMERIC(8,2),
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    @pg_query(db(), "CREATE INDEX IF NOT EXISTS device_loc_hist_user_idx ON device_location_history(user_id, created_at DESC)");
    $cnt = @pg_fetch_row(@pg_query(db(), "SELECT COUNT(*) FROM donasi_rekening"));
    if ($cnt && (int)$cnt[0] === 0) {
        @pg_query(db(), "INSERT INTO donasi_rekening(bank,nomor,atas_nama,urutan,aktif) VALUES
            ('BCA','1234567890','Bendahara Kegiatan',1,true),
            ('Mandiri','9876543210','Bendahara Kegiatan',2,true),
            ('DANA','081234567890','Bendahara Kegiatan',3,true)");
    }
} catch (Throwable $e) { /* ignore */ }

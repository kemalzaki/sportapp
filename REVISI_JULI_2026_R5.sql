-- REVISI_JULI_2026_R5.sql
-- Jalankan sekali di PostgreSQL. Idempotent (aman di-run berulang).

-- 1) Kolom komunitas_id di users (untuk relasi Member <-> Komunitas)
ALTER TABLE users ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL;

-- 2) Pastikan kolom username ada (untuk login pakai username)
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_username_lower
    ON users (LOWER(username)) WHERE username IS NOT NULL;

-- 3) Tabel komunitas (jika belum ada)
CREATE TABLE IF NOT EXISTS komunitas (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    deskripsi TEXT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- 4) Tabel paket_pesanan (jika belum ada)
CREATE TABLE IF NOT EXISTS paket_pesanan (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    paket VARCHAR(20) NOT NULL,
    harga INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW()
);

-- 5) OPSIONAL: isi username default dari email untuk akun lama (jangan overwrite yang sudah ada).
UPDATE users
SET username = LOWER(SPLIT_PART(email, '@', 1))
WHERE (username IS NULL OR username = '')
  AND email IS NOT NULL AND email <> '';

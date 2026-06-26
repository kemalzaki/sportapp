-- migrations_r21_1jul2026.sql
-- Revisi R21 (1 Juli 2026) — Upgrade paket member via Midtrans.
--
-- Tambahan SATU tabel baru: paket_pesanan.
-- Halaman /paket_upgrade.php juga akan membuat tabel ini secara idempotent
-- (CREATE TABLE IF NOT EXISTS) saat pertama kali diakses, jadi menjalankan
-- file ini bersifat OPSIONAL — disertakan agar struktur database tetap
-- terdokumentasi dan bisa di-restore dari sportapp.sql.
--
-- Cara jalankan (PostgreSQL lokal):
--   psql -U <user> -d <db> -f migrations_r21_1jul2026.sql
--
-- TIDAK ADA data lain yang dihapus / diubah.

CREATE TABLE IF NOT EXISTS paket_pesanan (
    id              BIGSERIAL PRIMARY KEY,
    kode            VARCHAR(40) UNIQUE NOT NULL,
    user_id         BIGINT NOT NULL,
    paket           VARCHAR(20) NOT NULL,            -- 'pro' | 'komunitas'
    harga           INTEGER NOT NULL,                -- Rupiah
    status          VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending | paid | failed
    snap_token      TEXT,
    snap_redirect   TEXT,
    midtrans_status VARCHAR(40),
    midtrans_raw    TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    paid_at         TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS paket_pesanan_user_idx
    ON paket_pesanan(user_id, created_at DESC);

-- Pastikan kolom paket pada users tetap ada (idempotent — sudah ada sejak R14).
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis';
UPDATE users SET paket = 'gratis' WHERE paket IS NULL OR paket = '';

-- (Opsional) override harga paket tanpa edit kode:
--   INSERT INTO app_settings(skey,sval,keterangan,updated_at)
--   VALUES ('paket_price_pro','25000','Harga paket PRO / bulan', now())
--   ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, updated_at=now();
--   INSERT INTO app_settings(skey,sval,keterangan,updated_at)
--   VALUES ('paket_price_komunitas','50000','Harga paket KOMUNITAS / bulan', now())
--   ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, updated_at=now();

-- migrations_r22_27jun2026.sql
-- Revisi R22 (27 Juni 2026)
-- 1) Monitoring Tahajud & Duha Bulanan (islami.php)
-- 2) Pengaturan link Grup WhatsApp (index.php)
-- 3) Opini Viral / sentimen publik

BEGIN;

-- (1) Log harian shalat sunnah Tahajud & Duha
CREATE TABLE IF NOT EXISTS shalat_sunnah_log (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    jenis VARCHAR(16) NOT NULL CHECK (jenis IN ('tahajud','duha')),
    tanggal DATE NOT NULL,
    rakaat INT DEFAULT 2,
    catatan TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (user_id, jenis, tanggal)
);
CREATE INDEX IF NOT EXISTS idx_ssl_user_tgl ON shalat_sunnah_log(user_id, tanggal);

-- (2) Pengaturan link Grup WhatsApp komunitas (per-admin / global)
-- Disimpan via tabel app_settings yang sudah ada. Tambahkan default jika kosong.
INSERT INTO app_settings(skey, sval)
SELECT 'wa_grup_link', 'https://chat.whatsapp.com/'
WHERE NOT EXISTS (SELECT 1 FROM app_settings WHERE skey='wa_grup_link');

-- (3) Cache topik opini viral + sentimen (publik)
CREATE TABLE IF NOT EXISTS opini_viral (
    id BIGSERIAL PRIMARY KEY,
    judul TEXT NOT NULL,
    sumber TEXT,
    url TEXT,
    ringkasan TEXT,
    sentimen VARCHAR(10) NOT NULL DEFAULT 'netral'
        CHECK (sentimen IN ('rendah','netral','tinggi')),
    skor NUMERIC(5,2) DEFAULT 0,
    kategori TEXT,
    fetched_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_opini_fetched ON opini_viral(fetched_at DESC);

COMMIT;

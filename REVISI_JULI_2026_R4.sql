-- ============================================================
-- REVISI JULI 2026 R4 — Paket Expiry + Komunitas CRUD
-- Jalankan sekali di PostgreSQL. Semua idempotent (aman diulang).
-- ============================================================

-- 1) Kolom masa aktif paket (auto-downgrade ke 'gratis' jika lewat)
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis';
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_started_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_expires_at TIMESTAMP NULL;

-- Backfill: bila user sudah punya paket berbayar tapi belum ada expires,
-- default 30 hari sejak sekarang supaya tidak langsung downgrade.
UPDATE users
   SET paket_expires_at = now() + INTERVAL '30 days'
 WHERE paket IN ('pro','komunitas')
   AND paket_expires_at IS NULL;

-- 2) Tabel Komunitas (master) — dikelola di Admin → Komunitas Organize → Komunitas
CREATE TABLE IF NOT EXISTS komunitas (
    id           SERIAL PRIMARY KEY,
    nama         VARCHAR(120) NOT NULL,
    slug         VARCHAR(140) UNIQUE,
    deskripsi    TEXT,
    kota         VARCHAR(120),
    kontak_wa    VARCHAR(30),
    logo_url     TEXT,
    warna        VARCHAR(20) DEFAULT '#0ea5e9',
    aktif        SMALLINT NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT now(),
    updated_at   TIMESTAMP
);
CREATE INDEX IF NOT EXISTS komunitas_aktif_idx ON komunitas(aktif);

-- 3) Data Komunitas (detail/anggota/kegiatan) — berelasi ke komunitas.id
CREATE TABLE IF NOT EXISTS komunitas_data (
    id            SERIAL PRIMARY KEY,
    komunitas_id  INTEGER NOT NULL REFERENCES komunitas(id) ON DELETE CASCADE,
    judul         VARCHAR(180) NOT NULL,
    kategori      VARCHAR(60),                -- mis. 'kegiatan','pengurus','pengumuman','fasilitas'
    isi           TEXT,
    tanggal       DATE,
    created_at    TIMESTAMP NOT NULL DEFAULT now(),
    updated_at    TIMESTAMP
);
CREATE INDEX IF NOT EXISTS komunitas_data_kom_idx ON komunitas_data(komunitas_id);

-- 4) Relasi Jadwal → Komunitas (agar bisa tampil di "Jadwal Terdekat" index.php)
ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL
    REFERENCES komunitas(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS jadwal_kom_idx ON jadwal(komunitas_id);

-- 5) Seed contoh (opsional, hanya jika tabel kosong)
INSERT INTO komunitas (nama, slug, deskripsi, kota, kontak_wa)
SELECT 'KawanKeringat Pusat', 'kawankeringat-pusat',
       'Komunitas utama KawanKeringat.', 'Tangerang Selatan', '6281386369207'
WHERE NOT EXISTS (SELECT 1 FROM komunitas);

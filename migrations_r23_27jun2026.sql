-- migrations_r23_27jun2026.sql
-- Revisi R23 (27 Juni 2026)
-- 1) Tabel toko_olahraga — daftar toko perlengkapan olahraga terdekat
--    Dipakai oleh halaman user `toko_olahraga.php` dan CRUD admin
--    `admin/toko_olahraga.php`.
-- Catatan: tidak menghapus data lain. Aman dijalankan berulang
--          (semua statement IF NOT EXISTS).

BEGIN;

CREATE TABLE IF NOT EXISTS toko_olahraga (
    id            BIGSERIAL PRIMARY KEY,
    nama          VARCHAR(180) NOT NULL,
    alamat        TEXT,
    kota          VARCHAR(120),
    kategori      VARCHAR(80),                -- contoh: "Sepatu", "Bola", "Pakaian", "Umum"
    deskripsi     TEXT,
    foto_url      TEXT,
    wa_nomor      VARCHAR(20),                -- nomor WhatsApp untuk pesan, format internasional tanpa +
    telp          VARCHAR(40),
    jam_buka      VARCHAR(80),
    lat           DOUBLE PRECISION,
    lng           DOUBLE PRECISION,
    map_url       TEXT,
    rating        NUMERIC(3,2) DEFAULT 0,
    aktif         BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT now(),
    updated_at    TIMESTAMP NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_toko_olahraga_aktif ON toko_olahraga(aktif);
CREATE INDEX IF NOT EXISTS idx_toko_olahraga_kota  ON toko_olahraga(kota);

-- Seed contoh (hanya jika tabel masih kosong)
INSERT INTO toko_olahraga (nama, alamat, kota, kategori, deskripsi, wa_nomor, jam_buka, aktif, sort_order)
SELECT 'Sport Station', 'Mall Central Park Lt. 2', 'Jakarta', 'Umum',
       'Toko perlengkapan olahraga lengkap: sepatu, jersey, bola, aksesoris.', '6281234567890',
       '10.00–22.00', TRUE, 1
WHERE NOT EXISTS (SELECT 1 FROM toko_olahraga);

INSERT INTO toko_olahraga (nama, alamat, kota, kategori, deskripsi, wa_nomor, jam_buka, aktif, sort_order)
SELECT 'Planet Sports', 'Jl. Gatot Subroto Kav. 25', 'Jakarta', 'Sepatu & Pakaian',
       'Spesialis sepatu lari & pakaian olahraga merek internasional.', '6281200000001',
       '09.00–21.00', TRUE, 2
WHERE (SELECT COUNT(*) FROM toko_olahraga) < 2;

COMMIT;

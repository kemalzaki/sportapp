-- ============================================================
-- Migrasi 2 Jun 2026 (lanjutan) — Toko / Pedagang + relasi ke jajanan
-- Idempotent: aman dijalankan berkali-kali. TIDAK menghapus data apapun.
-- Jalankan:  psql -U <user> -d <db> -f migrations_2jun2026_toko.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS toko (
    id         SERIAL PRIMARY KEY,
    nama       VARCHAR(160) NOT NULL,
    deskripsi  TEXT,
    alamat     TEXT,
    no_wa      VARCHAR(25),
    lat        NUMERIC(10,6),
    lng        NUMERIC(10,6),
    aktif      BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS toko_id INT REFERENCES toko(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS jajanan_toko_idx ON jajanan(toko_id);

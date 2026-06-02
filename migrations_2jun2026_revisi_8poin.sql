-- =============================================================
-- Migrasi 2 Juni 2026 — Revisi 8 Poin (lanjutan)
-- Jalankan: psql "$DATABASE_URL" -f migrations_2jun2026_revisi_8poin.sql
-- Aman dijalankan berulang (idempotent).
-- Hanya menambah kolom; data lama TIDAK dihapus.
-- =============================================================

-- (5) Sistem jam & hari buka untuk Toko (sebelumnya hanya ada di jajanan)
ALTER TABLE toko ADD COLUMN IF NOT EXISTS jam_buka  TIME;
ALTER TABLE toko ADD COLUMN IF NOT EXISTS jam_tutup TIME;
-- hari_buka sudah ada (migrations_2jun2026_revisi10poin.sql),
-- baris berikut hanya untuk berjaga-jaga bila migrasi tsb belum dijalankan:
ALTER TABLE toko    ADD COLUMN IF NOT EXISTS hari_buka VARCHAR(20) DEFAULT '0,1,2,3,4,5,6';
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS hari_buka VARCHAR(20) DEFAULT '0,1,2,3,4,5,6';

-- Indeks bantu untuk filter "buka sekarang" di jajanan.php
CREATE INDEX IF NOT EXISTS jajanan_aktif_idx ON jajanan(aktif);
CREATE INDEX IF NOT EXISTS toko_aktif_idx    ON toko(aktif);

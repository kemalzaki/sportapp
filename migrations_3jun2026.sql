-- =====================================================================
-- Revisi 3 Juni 2026
-- Menambahkan kolom lokasi driver pada tabel jajanan_pesanan supaya
-- pemesan dapat melacak posisi kurir secara realtime (polling) seperti
-- Gojek.
-- Jalankan migrasi ini sekali di PostgreSQL Anda:
--   psql -d sportapp -f migrations_3jun2026.sql
-- =====================================================================

ALTER TABLE jajanan_pesanan
  ADD COLUMN IF NOT EXISTS driver_lat            NUMERIC(10,6),
  ADD COLUMN IF NOT EXISTS driver_lng            NUMERIC(10,6),
  ADD COLUMN IF NOT EXISTS driver_loc_updated_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_jjn_pesanan_driver_upd
  ON jajanan_pesanan (driver_loc_updated_at DESC);

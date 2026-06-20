-- =====================================================================
-- Revisi 20 Juni 2026 R3 — Migration tambahan untuk PostgreSQL
-- Jalankan SEKALI di database 'sportapp':
--   psql -d sportapp -U <user> -f migrations_r3.sql
--
-- Aman dijalankan berulang (idempotent). TIDAK menghapus data apapun.
-- =====================================================================

-- Kolom baru untuk tabel 'tempat' (dipakai oleh admin/tempat.php &
-- tempat_detail.php khusus jenis olahraga Hiking / Camping):
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS gpx_path     TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS parkir_info  TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS run_route_id BIGINT;

-- Foreign key opsional ke run_routes (boleh dilewati jika run_routes belum dibuat)
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='run_routes') THEN
    BEGIN
      ALTER TABLE tempat
        ADD CONSTRAINT tempat_run_route_id_fkey
        FOREIGN KEY (run_route_id) REFERENCES run_routes(id) ON DELETE SET NULL;
    EXCEPTION WHEN duplicate_object THEN NULL;
    END;
  END IF;
END $$;

-- Catatan:
--  - File .GPX disimpan di folder /uploads/gpx/ (perlu writable oleh PHP).
--  - Tidak ada perubahan untuk tabel lain.

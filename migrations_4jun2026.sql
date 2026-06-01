-- =====================================================================
-- Revisi 4 Juni 2026
-- Menambahkan fitur rating bintang per pesanan jajanan.
-- Idempotent (IF NOT EXISTS) — aman dijalankan berkali-kali, TIDAK
-- menghapus data apapun.
--
-- Jalankan sekali:
--   psql -U <user> -d <db> -f migrations_4jun2026.sql
-- =====================================================================

ALTER TABLE jajanan_pesanan
  ADD COLUMN IF NOT EXISTS rating          SMALLINT,
  ADD COLUMN IF NOT EXISTS rating_komentar TEXT,
  ADD COLUMN IF NOT EXISTS rating_at       TIMESTAMPTZ;

-- Validasi range bintang 1..5 (tanpa menghapus data lama yang mungkin NULL)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='jajanan_pesanan' AND constraint_name='jjn_rating_range_chk'
  ) THEN
    ALTER TABLE jajanan_pesanan
      ADD CONSTRAINT jjn_rating_range_chk
      CHECK (rating IS NULL OR (rating BETWEEN 1 AND 5));
  END IF;
END$$;

CREATE INDEX IF NOT EXISTS idx_jjn_pesanan_rating
  ON jajanan_pesanan (rating);

-- REVISI_JULI_2026_R7.sql
-- Revisi R7 (Juli 2026) — PostgreSQL migration untuk paket revisi berikut:
--   1) Menambah role baru 'superadmin' pada enum users.role.
--   2) Menambah kolom opsional komunitas_id pada tempat_survei (bila
--      di masa depan ingin memfilter usulan tempat langsung berdasarkan
--      komunitas — saat ini fitur R7 memakai relasi user_komunitas).
--
-- Jalankan idempotent — aman diulang.

-- 1) Tambah nilai 'superadmin' ke enum role.
--    PostgreSQL: ALTER TYPE ... ADD VALUE tidak mendukung IF NOT EXISTS
--    di semua versi, jadi kita bungkus dengan DO block.
DO $$
DECLARE
  _enumname text;
BEGIN
  -- Cari nama enum tipe kolom users.role
  SELECT t.typname INTO _enumname
  FROM pg_type t
  JOIN pg_attribute a ON a.atttypid = t.oid
  JOIN pg_class c ON c.oid = a.attrelid
  WHERE c.relname = 'users' AND a.attname = 'role' AND t.typtype = 'e';

  IF _enumname IS NOT NULL THEN
    -- Cek apakah label 'superadmin' sudah ada
    IF NOT EXISTS (
      SELECT 1 FROM pg_enum e
      JOIN pg_type t ON t.oid = e.enumtypid
      WHERE t.typname = _enumname AND e.enumlabel = 'superadmin'
    ) THEN
      EXECUTE format('ALTER TYPE %I ADD VALUE %L', _enumname, 'superadmin');
    END IF;
  END IF;
END $$;

-- 2) Kolom opsional komunitas_id di tempat_survei (aman jika sudah ada).
ALTER TABLE tempat_survei ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL;

-- 3) Pastikan tabel pivot user_komunitas ada (harus sudah dari revisi R2, tapi
--    kita jamin lagi untuk halaman-halaman baru yang bergantung padanya).
CREATE TABLE IF NOT EXISTS user_komunitas (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    komunitas_id INTEGER NOT NULL REFERENCES komunitas(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, komunitas_id)
);

-- Selesai.

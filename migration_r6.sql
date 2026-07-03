-- =====================================================================
-- Migration R6 (Juli 2026) — Revisi Member, Komunitas, Paket Halaman
-- Jalankan setelah schema utama sudah ada. Idempotent (aman diulang).
-- Data lama TIDAK dihapus.
-- =====================================================================

-- 1) Kolom username, komunitas_id, dan paket pada tabel users
ALTER TABLE users ADD COLUMN IF NOT EXISTS username     VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS komunitas_id INTEGER;
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket        VARCHAR(20) DEFAULT 'gratis';

-- Unique username (case-insensitive), tetapi NULL diperbolehkan berulang
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_username_lower
  ON users (LOWER(username)) WHERE username IS NOT NULL;

-- Foreign key ke komunitas (SET NULL jika komunitas dihapus)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'fk_users_komunitas'
  ) THEN
    ALTER TABLE users
      ADD CONSTRAINT fk_users_komunitas
      FOREIGN KEY (komunitas_id) REFERENCES komunitas(id) ON DELETE SET NULL;
  END IF;
END $$;

-- Batasi nilai paket yang valid
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_name = 'chk_users_paket'
  ) THEN
    ALTER TABLE users
      ADD CONSTRAINT chk_users_paket
      CHECK (paket IN ('gratis','pro','komunitas'));
  END IF;
END $$;

-- 2) Pastikan tiap user memiliki nilai paket default
UPDATE users SET paket = 'gratis' WHERE paket IS NULL;

-- Selesai.

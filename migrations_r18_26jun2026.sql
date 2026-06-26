-- ============================================================
-- Revisi R18 (26 Juni 2026) — Migrasi PostgreSQL
-- Jalankan SEKALI di database PostgreSQL Anda (lokal).
-- Tidak menghapus/mengubah data yg sudah ada.
-- ============================================================

-- 1) Tabel master Jenis Jadwal (Tim Kantor KK / Tim Public KK / dst.)
CREATE TABLE IF NOT EXISTS jenis_jadwal (
  id          SERIAL PRIMARY KEY,
  nama        VARCHAR(80) NOT NULL UNIQUE,
  warna_bg    VARCHAR(20) NOT NULL DEFAULT '#0ea5e9',
  warna_text  VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  created_at  TIMESTAMP DEFAULT now()
);

-- Seed default (idempotent)
INSERT INTO jenis_jadwal(nama, warna_bg, warna_text) VALUES
  ('Tim Kantor KK', '#0ea5e9', '#ffffff'),
  ('Tim Public KK', '#22c55e', '#ffffff')
ON CONFLICT (nama) DO NOTHING;

-- 2) Kolom jenis_jadwal_id pada tabel jadwal
ALTER TABLE jadwal
  ADD COLUMN IF NOT EXISTS jenis_jadwal_id INTEGER NULL REFERENCES jenis_jadwal(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS jadwal_jenis_jadwal_idx ON jadwal(jenis_jadwal_id);

-- Selesai. Tidak perlu mengubah tabel tempat / run_routes — filter kiloan
-- di tempat_list.php memakai JOIN ke run_routes.jarak_m yang sudah ada.

-- ============================================================
-- Revisi R19 — Migrasi PostgreSQL (lokal)
-- Jalankan SEKALI. Idempotent; data tidak dihapus.
-- ============================================================

-- 1) Kolom kiloan trek pada tabel tempat (khusus Hiking).
--    Memungkinkan admin mengisi kiloan langsung dari admin/tempat.php
--    tanpa harus menautkan run_route_id. tempat_list.php memakai
--    COALESCE(tempat.jarak_km, run_routes.jarak_m/1000) sebagai sumber.
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS jarak_km NUMERIC(6,2) NULL;

-- 2) (Sudah dijalankan di R18, ditulis ulang aman/idempotent)
--    Pastikan tabel jenis_jadwal & kolom jadwal.jenis_jadwal_id ada.
CREATE TABLE IF NOT EXISTS jenis_jadwal (
  id          SERIAL PRIMARY KEY,
  nama        VARCHAR(80) NOT NULL UNIQUE,
  warna_bg    VARCHAR(20) NOT NULL DEFAULT '#0ea5e9',
  warna_text  VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  created_at  TIMESTAMP DEFAULT now()
);
INSERT INTO jenis_jadwal(nama, warna_bg, warna_text) VALUES
  ('Tim Kantor KK', '#0ea5e9', '#ffffff'),
  ('Tim Public KK', '#22c55e', '#ffffff')
ON CONFLICT (nama) DO NOTHING;

ALTER TABLE jadwal
  ADD COLUMN IF NOT EXISTS jenis_jadwal_id INTEGER NULL REFERENCES jenis_jadwal(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS jadwal_jenis_jadwal_idx ON jadwal(jenis_jadwal_id);

-- Selesai.

-- ============================================================
-- Migration R14 (25 Juni 2026) — Revisi Islami & Member
-- Jalankan: psql -d sportapp -f migrations_r14.sql
-- (Idempotent, aman dijalankan berulang)
-- ============================================================

-- (#2,#6) Kolom paket fitur pada users: gratis / pro / komunitas
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis';
UPDATE users SET paket='gratis' WHERE paket IS NULL OR paket='';

-- Pastikan kolom aktif sudah ada (sudah dibuat di revisi 14 Juni 2026)
ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN DEFAULT TRUE;

-- (#8) CRUD Kategori untuk Kajian Literatur Buku
CREATE TABLE IF NOT EXISTS kajian_kategori (
  id BIGSERIAL PRIMARY KEY,
  nama VARCHAR(80) NOT NULL UNIQUE,
  slug VARCHAR(80) NOT NULL,
  warna VARCHAR(20) DEFAULT 'secondary',
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
INSERT INTO kajian_kategori(nama,slug,warna) VALUES
 ('Umum','umum','secondary'),
 ('Aqidah','aqidah','primary'),
 ('Fiqih','fiqih','success'),
 ('Tafsir','tafsir','info'),
 ('Hadist','hadist','warning'),
 ('Sirah','sirah','danger'),
 ('Akhlak','akhlak','primary'),
 ('Tazkiyah','tazkiyah','info'),
 ('Sains Islam','sains-islam','success'),
 ('Sejarah Islam','sejarah-islam','warning'),
 ('Parenting','parenting','danger'),
 ('Ekonomi Syariah','ekonomi-syariah','primary'),
 ('Lainnya','lainnya','secondary')
ON CONFLICT (nama) DO NOTHING;

-- Pastikan kolom kategori ada di islami_kajian
ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS kategori VARCHAR(60) DEFAULT 'Umum';

-- (#7) Pemilik literatur (bisa 1 atau lebih, dari member atau eksternal)
CREATE TABLE IF NOT EXISTS kajian_pemilik (
  id BIGSERIAL PRIMARY KEY,
  kajian_id BIGINT NOT NULL REFERENCES islami_kajian(id) ON DELETE CASCADE,
  user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  nama_eksternal VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS kajian_pemilik_kajian_idx ON kajian_pemilik(kajian_id);
CREATE INDEX IF NOT EXISTS kajian_pemilik_user_idx ON kajian_pemilik(user_id);

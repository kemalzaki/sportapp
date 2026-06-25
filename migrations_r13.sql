-- Revisi R13 (25 Juni 2026) - Kolom tambahan
-- Jalankan di PostgreSQL: psql -d sportapp -f migrations_r13.sql

-- (#4) Kategori untuk Kajian Literatur Buku
ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS kategori VARCHAR(60) DEFAULT 'Umum';
CREATE INDEX IF NOT EXISTS islami_kajian_kategori_idx ON islami_kajian(kategori);

-- (#2) Index pembantu pencarian catatan hafalan (AJAX)
CREATE INDEX IF NOT EXISTS catatan_hafalan_judul_idx ON catatan_hafalan USING gin (to_tsvector('simple', coalesce(judul,'')||' '||coalesce(referensi,'')));

-- (#8) Tabel progres belajar tajwid (opsional, dipakai tajwid.php)
CREATE TABLE IF NOT EXISTS tajwid_progress (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  hukum VARCHAR(60) NOT NULL,
  dipelajari BOOLEAN NOT NULL DEFAULT false,
  catatan TEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE(user_id, hukum)
);

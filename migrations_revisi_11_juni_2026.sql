-- migrations_revisi_11_juni_2026.sql
-- Jalankan sekali di PostgreSQL lokal Anda. Skrip aman dijalankan ulang (IF NOT EXISTS).
-- File PHP juga melakukan auto-migration saat halaman pertama kali dibuka,
-- jadi ini hanya untuk pre-provisioning manual jika diperlukan.

-- (riwayat.php) Like & Comment untuk upload_harian
CREATE TABLE IF NOT EXISTS upload_harian_likes (
  upload_id  INTEGER NOT NULL,
  user_id    INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (upload_id, user_id)
);

CREATE TABLE IF NOT EXISTS upload_harian_comments (
  id         SERIAL PRIMARY KEY,
  upload_id  INTEGER NOT NULL,
  user_id    INTEGER NOT NULL,
  isi        TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS uhc_upload_idx ON upload_harian_comments(upload_id);

-- (catatan_hafalan.php) CRUD Catatan Hafalan
CREATE TABLE IF NOT EXISTS catatan_hafalan (
  id           SERIAL PRIMARY KEY,
  user_id      INTEGER NOT NULL,
  jenis        VARCHAR(40) NOT NULL DEFAULT 'Quran',
  judul        VARCHAR(200) NOT NULL,
  referensi    VARCHAR(200),
  target_ayat  INTEGER DEFAULT 0,
  sudah_ayat   INTEGER DEFAULT 0,
  status       VARCHAR(20) NOT NULL DEFAULT 'progress',
  catatan      TEXT,
  last_review  DATE,
  created_at   TIMESTAMP NOT NULL DEFAULT now(),
  updated_at   TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS catatan_hafalan_user_idx ON catatan_hafalan(user_id);

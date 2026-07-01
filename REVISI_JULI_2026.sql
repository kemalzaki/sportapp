-- =============================================================
-- REVISI Juli 2026 — Tabel PostgreSQL tambahan
-- Jalankan hanya bagian yang belum ada di database Anda.
-- Tidak menghapus data lama. Semua CREATE memakai IF NOT EXISTS.
-- =============================================================

-- (#1) Evaluasi harian di Monitoring Tahajud & Duha
CREATE TABLE IF NOT EXISTS shalat_evaluasi_harian (
  user_id    INT  NOT NULL,
  tanggal    DATE NOT NULL,
  evaluasi   TEXT,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (user_id, tanggal)
);

-- (#2) Monitoring latihan Paket Bugar Kalistenik
CREATE TABLE IF NOT EXISTS kalistenik_log (
  user_id    INT  NOT NULL,
  tanggal    DATE NOT NULL,
  level      VARCHAR(20) NOT NULL,       -- pemula / menengah / lanjutan
  catatan    TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  PRIMARY KEY (user_id, tanggal, level)
);

-- (#3) Monitoring Tilawah Harian (diri / keluarga)
CREATE TABLE IF NOT EXISTS tilawah_harian (
  id           SERIAL PRIMARY KEY,
  user_id      INT  NOT NULL,
  tanggal      DATE NOT NULL,
  sasaran      VARCHAR(20) NOT NULL DEFAULT 'diri', -- 'diri' | 'keluarga'
  surah        VARCHAR(80),
  ayat_dari    INT,
  ayat_sampai  INT,
  durasi_menit INT,
  catatan      TEXT,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_tilawah_user_tgl ON tilawah_harian(user_id, tanggal DESC);

-- (#4) Monitoring Silat Lidah (skill komunikasi ke teman sebaya)
CREATE TABLE IF NOT EXISTS silat_lidah (
  id           SERIAL PRIMARY KEY,
  user_id      INT  NOT NULL,
  tanggal      DATE NOT NULL,
  teman        VARCHAR(160) NOT NULL,
  topik        VARCHAR(200) NOT NULL,
  durasi_menit INT,
  kualitas     SMALLINT,               -- 1..5
  catatan      TEXT,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_silat_user_tgl ON silat_lidah(user_id, tanggal DESC);

-- (#5) Fitur Pertemananku di profile.php
CREATE TABLE IF NOT EXISTS pertemanan (
  id              SERIAL PRIMARY KEY,
  user_id         INT NOT NULL,
  nama            VARCHAR(120) NOT NULL,
  tanggal_kenalan DATE,
  kedekatan       SMALLINT,             -- 1..5
  catatan         TEXT,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_pertemanan_user ON pertemanan(user_id);

-- (#6) sejarah_nabi.php  → tidak butuh perubahan DB (checklist "Paham?"
--     disimpan di localStorage browser).
-- (#7) panduan_shalat_jama.php → tidak butuh perubahan DB (spoiler UI saja).
-- (#8) pantau_progress_member.php → hanya membaca tabel yang sudah ada
--      (shalat_sunnah_log, doa_user, catatan_hafalan, catatan_baca_buku).
--      Jika salah satu tabel belum ada di DB Anda, query terkait akan
--      di-skip (dibungkus try/catch) dan kolom di UI tampil 0.

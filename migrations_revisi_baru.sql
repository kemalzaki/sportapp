-- ============================================================
-- Migrasi revisi: Gaya Hidup, Kalori Mingguan, IPTV CRUD
-- Aman dijalankan berulang (IF NOT EXISTS / ON CONFLICT).
-- Tidak menghapus data apapun.
-- ============================================================

-- ---------- IPTV Channels (CRUD) ----------
CREATE TABLE IF NOT EXISTS iptv_channels (
  id           SERIAL PRIMARY KEY,
  nama         VARCHAR(200) NOT NULL,
  url          TEXT NOT NULL UNIQUE,
  logo_url     TEXT,
  group_name   VARCHAR(120),
  aktif        BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order   INT DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT now(),
  updated_at   TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_iptv_aktif ON iptv_channels(aktif);
CREATE INDEX IF NOT EXISTS idx_iptv_group ON iptv_channels(group_name);

-- ---------- Gaya Hidup (Garmin-style) ----------
CREATE TABLE IF NOT EXISTS gaya_hidup_log (
  id            SERIAL PRIMARY KEY,
  user_id       INT NOT NULL,
  tanggal       DATE NOT NULL,
  langkah       INT,
  tidur_menit   INT,
  hidrasi_ml    INT,
  stres_skor    INT,              -- 0..100
  body_battery  INT,              -- 0..100
  berat_kg      NUMERIC(5,2),
  mood          VARCHAR(30),
  catatan       TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT now(),
  updated_at    TIMESTAMP NOT NULL DEFAULT now(),
  CONSTRAINT uniq_gh_user_tgl UNIQUE (user_id, tanggal)
);
CREATE INDEX IF NOT EXISTS idx_gh_user_tgl ON gaya_hidup_log(user_id, tanggal DESC);

-- ---------- Kalori Mingguan ----------
CREATE TABLE IF NOT EXISTS kalori_target (
  user_id        INT PRIMARY KEY,
  target_harian  INT NOT NULL DEFAULT 2000,
  updated_at     TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS kalori_log (
  id            SERIAL PRIMARY KEY,
  user_id       INT NOT NULL,
  tanggal       DATE NOT NULL DEFAULT CURRENT_DATE,
  waktu         TIME NOT NULL DEFAULT CURRENT_TIME,
  nama_makanan  VARCHAR(200) NOT NULL,
  kalori        INT NOT NULL DEFAULT 0,
  foto_url      TEXT,
  ai_estimasi   BOOLEAN NOT NULL DEFAULT FALSE,
  catatan       TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_kalori_user_tgl ON kalori_log(user_id, tanggal DESC);

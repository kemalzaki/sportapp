-- Revisi 12 Juni 2026
-- Tambahan kolom untuk fitur baru "Kalkulator Gaya Hidup".
-- Aman dijalankan berulang (IF NOT EXISTS). Tidak menghapus data.

ALTER TABLE gaya_hidup_log
  ADD COLUMN IF NOT EXISTS pola_makan        VARCHAR(20),
  ADD COLUMN IF NOT EXISTS porsi_makan       SMALLINT,
  ADD COLUMN IF NOT EXISTS minum_air_gelas   SMALLINT,
  ADD COLUMN IF NOT EXISTS pola_tidur        VARCHAR(20),
  ADD COLUMN IF NOT EXISTS kualitas_tidur    SMALLINT,
  ADD COLUMN IF NOT EXISTS mood_skor         SMALLINT,
  ADD COLUMN IF NOT EXISTS kecemasan         SMALLINT,
  ADD COLUMN IF NOT EXISTS motivasi          SMALLINT,
  ADD COLUMN IF NOT EXISTS fokus             SMALLINT,
  ADD COLUMN IF NOT EXISTS catatan_psikologi TEXT;

-- Indeks bantu (sudah ada UNIQUE user_id,tanggal dari migrasi sebelumnya).
CREATE INDEX IF NOT EXISTS idx_gaya_hidup_user_tgl
  ON gaya_hidup_log(user_id, tanggal DESC);
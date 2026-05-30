-- Revisi 31 Mei 2026 v2 — jalankan sekali via psql (atau biarkan auto via config/db.php).
ALTER TABLE jajanan          ADD COLUMN IF NOT EXISTS foto_file_id VARCHAR(120);
ALTER TABLE jajanan_pesanan  ADD COLUMN IF NOT EXISTS pickup_lat NUMERIC(10,6);
ALTER TABLE jajanan_pesanan  ADD COLUMN IF NOT EXISTS pickup_lng NUMERIC(10,6);

CREATE TABLE IF NOT EXISTS device_locations (
  user_id      INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  lat          NUMERIC(10,6) NOT NULL,
  lng          NUMERIC(10,6) NOT NULL,
  accuracy_m   NUMERIC(8,2),
  device_label VARCHAR(120),
  updated_at   TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS device_location_history (
  id         BIGSERIAL PRIMARY KEY,
  user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lat        NUMERIC(10,6) NOT NULL,
  lng        NUMERIC(10,6) NOT NULL,
  accuracy_m NUMERIC(8,2),
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS device_loc_hist_user_idx
  ON device_location_history(user_id, created_at DESC);

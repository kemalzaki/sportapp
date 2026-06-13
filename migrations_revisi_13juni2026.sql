-- Revisi 13 Juni 2026 — migrasi tambahan (idempotent, tidak menghapus data)
ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS nonaktif_catatan TEXT;

ALTER TABLE pengeluaran_kegiatan ADD COLUMN IF NOT EXISTS dana_dari VARCHAR(150);

CREATE TABLE IF NOT EXISTS login_logs (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(64),
  user_agent VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_login_logs_user ON login_logs(user_id, created_at DESC);

-- Hapus 5 user perempuan placeholder (Alya, Devi, Medew, Yuni, Umy)
DELETE FROM users WHERE LOWER(nama) IN ('alya','devi','medew','yuni','umy') AND role='member';

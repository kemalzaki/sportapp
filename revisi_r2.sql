-- =====================================================================
-- Revisi R2 (Juli 2026) — Migrasi PostgreSQL
-- Semua statement idempotent. Aman dijalankan ulang.
-- =====================================================================

-- (1) Masa expire paket akun per member (untuk admin/members.php)
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_expires_at TIMESTAMP;

-- (2) Tabel pivot user ↔ komunitas (mendukung multi-komunitas per member)
CREATE TABLE IF NOT EXISTS user_komunitas (
    user_id      INTEGER NOT NULL REFERENCES users(id)      ON DELETE CASCADE,
    komunitas_id INTEGER NOT NULL REFERENCES komunitas(id)  ON DELETE CASCADE,
    created_at   TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, komunitas_id)
);

CREATE INDEX IF NOT EXISTS idx_user_komunitas_user ON user_komunitas(user_id);
CREATE INDEX IF NOT EXISTS idx_user_komunitas_kom  ON user_komunitas(komunitas_id);

-- (3) Backfill: pindahkan users.komunitas_id lama ke pivot.
-- Kolom users.komunitas_id TIDAK dihapus (kompatibilitas mundur untuk kode lain).
INSERT INTO user_komunitas(user_id, komunitas_id)
SELECT id, komunitas_id FROM users
WHERE komunitas_id IS NOT NULL
ON CONFLICT DO NOTHING;

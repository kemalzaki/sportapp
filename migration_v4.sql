-- ============================================================
-- SportApp v4 ADDITIVE migration (PostgreSQL)
-- Jalankan SETELAH migration_v3.sql. Tidak menghapus data.
-- ============================================================

-- 1. Sistem perizinan / RSVP
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'hadir';
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS keterangan TEXT;

-- Drop check constraint lama bila ada (tanpa DO block agar kompatibel semua client)
ALTER TABLE absensi DROP CONSTRAINT IF EXISTS absensi_status_check;
ALTER TABLE absensi ADD CONSTRAINT absensi_status_check
  CHECK (status IN ('hadir','izin','sakit','telat','absen'));

-- backfill status dari kolom hadir lama
UPDATE absensi SET status='hadir' WHERE hadir=1 AND status IS NULL;
UPDATE absensi SET status='absen' WHERE hadir=0 AND status IS NULL;

-- 12. Forum: reply + like/dislike
ALTER TABLE chat_forum ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES chat_forum(id) ON DELETE CASCADE;
CREATE TABLE IF NOT EXISTS chat_reactions (
  chat_id INTEGER NOT NULL REFERENCES chat_forum(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  val SMALLINT NOT NULL CHECK (val IN (-1,1)),
  PRIMARY KEY(chat_id, user_id)
);

-- 6. Preferensi dark mode user
ALTER TABLE users ADD COLUMN IF NOT EXISTS dark_mode SMALLINT NOT NULL DEFAULT 0;

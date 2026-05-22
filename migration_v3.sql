-- ============================================================
-- SportApp v3 ADDITIVE migration (PostgreSQL)
-- Jalankan SETELAH import sportapp.sql. Tidak menghapus data.
-- ============================================================

-- ---- 1. QR Check-in & GPS ----
CREATE TABLE IF NOT EXISTS qr_tokens (
  id SERIAL PRIMARY KEY,
  jadwal_id INTEGER NOT NULL REFERENCES jadwal(id) ON DELETE CASCADE,
  token TEXT UNIQUE NOT NULL,
  valid_from TIMESTAMP NOT NULL DEFAULT now(),
  valid_until TIMESTAMP NOT NULL DEFAULT (now() + interval '3 hours'),
  lat DOUBLE PRECISION,
  lng DOUBLE PRECISION,
  radius_meter INTEGER DEFAULT 150,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);

ALTER TABLE absensi ADD COLUMN IF NOT EXISTS metode VARCHAR(20) DEFAULT 'manual';
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS checkin_at TIMESTAMP;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION;

ALTER TABLE tempat ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION;

-- ---- 2-3. Badges, Achievements, XP, Streak ----
CREATE TABLE IF NOT EXISTS badges (
  id SERIAL PRIMARY KEY,
  kode VARCHAR(50) UNIQUE NOT NULL,
  nama VARCHAR(100) NOT NULL,
  deskripsi TEXT,
  icon VARCHAR(50) DEFAULT 'bi-award',
  warna VARCHAR(20) DEFAULT 'primary',
  xp INTEGER DEFAULT 50
);

CREATE TABLE IF NOT EXISTS user_badges (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  badge_id INTEGER NOT NULL REFERENCES badges(id) ON DELETE CASCADE,
  earned_at TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE(user_id, badge_id)
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS xp INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS level INTEGER NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS streak_minggu INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT;

INSERT INTO badges(kode,nama,deskripsi,icon,warna,xp) VALUES
 ('JOGGING_10','Jogging 10x','Hadir jogging 10 kali','bi-person-running','success',100),
 ('RAJIN_4W','Rajin 4 Minggu','Hadir 4 minggu berturut-turut','bi-fire','danger',150),
 ('TOP_ATTEND','Top Attendance','Top 3 kehadiran bulanan','bi-trophy-fill','warning',200),
 ('NIGHT_RUNNER','Night Runner','5x olahraga malam','bi-moon-stars','dark',80),
 ('BADMINTON_WARRIOR','Badminton Warrior','Hadir 10x badminton','bi-shield-fill-check','info',120),
 ('FIRST_CHECKIN','First Check-in','Check-in pertama via QR','bi-qr-code-scan','primary',30),
 ('ALL_ROUNDER','All Rounder','Hadir di 3 jenis olahraga berbeda','bi-stars','warning',150),
 ('CONSISTENCY_KING','Consistency King','Score konsistensi >85%','bi-graph-up','success',180),
 ('EARLY_BIRD','Early Bird','5x check-in <10 menit sebelum mulai','bi-sun','warning',60),
 ('FORUM_STAR','Forum Star','50 post di forum','bi-chat-heart-fill','danger',70)
ON CONFLICT (kode) DO NOTHING;

-- ---- 4. Notifications + FCM ----
CREATE TABLE IF NOT EXISTS fcm_tokens (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token TEXT NOT NULL,
  device VARCHAR(100),
  created_at TIMESTAMP NOT NULL DEFAULT now(),
  UNIQUE(user_id, token)
);

CREATE TABLE IF NOT EXISTS notifications (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis VARCHAR(30) NOT NULL,
  judul VARCHAR(200) NOT NULL,
  isi TEXT,
  url VARCHAR(255),
  dibaca SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS notif_user_idx ON notifications(user_id, dibaca, created_at DESC);

-- ---- 5. Monitoring (catatan aktivitas lebih detail) ----
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS jarak_km NUMERIC(6,2);
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS durasi_menit INTEGER;
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS pace_detik INTEGER;
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS kalori INTEGER;
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS heart_rate INTEGER;
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS rpe SMALLINT;

-- ---- 7. Event & Tournament ----
CREATE TABLE IF NOT EXISTS event (
  id SERIAL PRIMARY KEY,
  nama VARCHAR(200) NOT NULL,
  jenis VARCHAR(50) NOT NULL,
  tipe VARCHAR(30) NOT NULL DEFAULT 'challenge',
  deskripsi TEXT,
  tanggal_mulai DATE NOT NULL,
  tanggal_selesai DATE,
  hadiah TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  banner_url TEXT,
  created_by INTEGER REFERENCES users(id),
  created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS event_peserta (
  id SERIAL PRIMARY KEY,
  event_id INTEGER NOT NULL REFERENCES event(id) ON DELETE CASCADE,
  tim_id INTEGER REFERENCES tim(id) ON DELETE CASCADE,
  user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  score NUMERIC(10,2) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS event_match (
  id SERIAL PRIMARY KEY,
  event_id INTEGER NOT NULL REFERENCES event(id) ON DELETE CASCADE,
  round INTEGER NOT NULL DEFAULT 1,
  tim_a INTEGER REFERENCES tim(id),
  tim_b INTEGER REFERENCES tim(id),
  score_a INTEGER DEFAULT 0,
  score_b INTEGER DEFAULT 0,
  pemenang INTEGER REFERENCES tim(id),
  jadwal_at TIMESTAMP
);

-- integrasikan jadwal ke event/tim
ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS tim_id INTEGER REFERENCES tim(id) ON DELETE SET NULL;
ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES event(id) ON DELETE SET NULL;
ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS jam_mulai TIME;

-- ---- 12. Social Feed & Story ----
CREATE TABLE IF NOT EXISTS posts (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  caption TEXT,
  foto_url TEXT,
  jenis VARCHAR(30) DEFAULT 'post',  -- post | story
  expired_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS posts_created_idx ON posts(created_at DESC);

CREATE TABLE IF NOT EXISTS post_likes (
  post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY(post_id,user_id)
);

CREATE TABLE IF NOT EXISTS post_comments (
  id SERIAL PRIMARY KEY,
  post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  isi TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);

-- ---- 13. Booking Lapangan ----
CREATE TABLE IF NOT EXISTS booking (
  id SERIAL PRIMARY KEY,
  tempat_id INTEGER NOT NULL REFERENCES tempat(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tanggal DATE NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|booked|canceled|done
  dp_status VARCHAR(20) DEFAULT 'unpaid',
  recurring VARCHAR(20),  -- weekly|monthly
  recurring_until DATE,
  catatan TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS booking_idx ON booking(tempat_id, tanggal);

-- ---- 10. Security: rate limit & login attempts ----
CREATE TABLE IF NOT EXISTS rate_limit (
  bucket VARCHAR(120) NOT NULL,
  ts TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS rl_idx ON rate_limit(bucket, ts);

CREATE TABLE IF NOT EXISTS login_attempts (
  id SERIAL PRIMARY KEY,
  email VARCHAR(150),
  ip VARCHAR(64),
  success SMALLINT DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT now()
);

-- ---- 11. Smart attendance stats (logging telat) ----
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS telat_menit INTEGER DEFAULT 0;

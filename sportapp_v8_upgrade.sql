-- =====================================================================
-- HapFam SportApp v8 — Upgrade Schema (PostgreSQL)
-- Jalankan SEKALI di database existing. Idempotent (IF NOT EXISTS).
-- Tidak menghapus data lama.
-- =====================================================================

-- -------- 1. Direct Message / Chat antar member ----------------------
CREATE TABLE IF NOT EXISTS dm_messages (
  id           BIGSERIAL PRIMARY KEY,
  sender_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  receiver_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  pesan        TEXT    NOT NULL,
  read_at      TIMESTAMP NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS dm_pair_idx     ON dm_messages(sender_id, receiver_id, id DESC);
CREATE INDEX IF NOT EXISTS dm_receiver_idx ON dm_messages(receiver_id, read_at);

-- -------- 2. Hashtag + Mention --------------------------------------
CREATE TABLE IF NOT EXISTS post_hashtags (
  post_id  INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  tag      VARCHAR(64) NOT NULL,
  PRIMARY KEY (post_id, tag)
);
CREATE INDEX IF NOT EXISTS post_hashtags_tag_idx ON post_hashtags(tag);

CREATE TABLE IF NOT EXISTS post_mentions (
  post_id  INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (post_id, user_id)
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40);
CREATE UNIQUE INDEX IF NOT EXISTS users_username_uidx ON users(LOWER(username)) WHERE username IS NOT NULL;

-- -------- 3. Bookmark post ------------------------------------------
CREATE TABLE IF NOT EXISTS post_bookmarks (
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (user_id, post_id)
);

-- -------- 4. Follow / Followers -------------------------------------
CREATE TABLE IF NOT EXISTS user_follows (
  follower_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  following_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at   TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (follower_id, following_id),
  CHECK (follower_id <> following_id)
);

-- -------- 5. Report postingan ---------------------------------------
CREATE TABLE IF NOT EXISTS post_reports (
  id          BIGSERIAL PRIMARY KEY,
  post_id     INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  alasan      VARCHAR(60) NOT NULL,
  catatan     TEXT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP NOT NULL DEFAULT now(),
  resolved_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS post_reports_post_idx   ON post_reports(post_id);
CREATE INDEX IF NOT EXISTS post_reports_status_idx ON post_reports(status);

-- -------- 6. Repost / Share -----------------------------------------
ALTER TABLE posts ADD COLUMN IF NOT EXISTS repost_of INTEGER NULL REFERENCES posts(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS posts_repost_idx ON posts(repost_of);

-- -------- 7. Tracking lari realtime ---------------------------------
CREATE TABLE IF NOT EXISTS run_sessions (
  id          BIGSERIAL PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  mulai_at    TIMESTAMP NOT NULL DEFAULT now(),
  selesai_at  TIMESTAMP NULL,
  jarak_m     DOUBLE PRECISION NOT NULL DEFAULT 0,
  durasi_dtk  INTEGER NOT NULL DEFAULT 0,
  kalori      INTEGER NOT NULL DEFAULT 0,
  catatan     TEXT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'aktif'
);
CREATE INDEX IF NOT EXISTS run_sessions_user_idx ON run_sessions(user_id, mulai_at DESC);

CREATE TABLE IF NOT EXISTS run_points (
  id         BIGSERIAL PRIMARY KEY,
  session_id BIGINT NOT NULL REFERENCES run_sessions(id) ON DELETE CASCADE,
  lat        DOUBLE PRECISION NOT NULL,
  lng        DOUBLE PRECISION NOT NULL,
  ts         TIMESTAMP NOT NULL DEFAULT now(),
  speed_mps  DOUBLE PRECISION NULL,
  accuracy_m DOUBLE PRECISION NULL
);
CREATE INDEX IF NOT EXISTS run_points_sess_idx ON run_points(session_id, ts);

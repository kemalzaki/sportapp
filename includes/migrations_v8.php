<?php
/**
 * v8 migrations – idempotent. Auto-loaded oleh header.
 * Mirror dari sportapp_v8_upgrade.sql.
 */
require_once __DIR__ . '/../config/db.php';

try {
  $stmts = [
    // DM
    "CREATE TABLE IF NOT EXISTS dm_messages (
       id BIGSERIAL PRIMARY KEY,
       sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
       receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
       pesan TEXT NOT NULL,
       read_at TIMESTAMP NULL,
       created_at TIMESTAMP NOT NULL DEFAULT now())",
    "CREATE INDEX IF NOT EXISTS dm_pair_idx ON dm_messages(sender_id, receiver_id, id DESC)",
    "CREATE INDEX IF NOT EXISTS dm_receiver_idx ON dm_messages(receiver_id, read_at)",
    // Hashtag + mention
    "CREATE TABLE IF NOT EXISTS post_hashtags (post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, tag VARCHAR(64) NOT NULL, PRIMARY KEY(post_id,tag))",
    "CREATE INDEX IF NOT EXISTS post_hashtags_tag_idx ON post_hashtags(tag)",
    "CREATE TABLE IF NOT EXISTS post_mentions (post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, PRIMARY KEY(post_id,user_id))",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40)",
    "CREATE UNIQUE INDEX IF NOT EXISTS users_username_uidx ON users(LOWER(username)) WHERE username IS NOT NULL",
    // Bookmark
    "CREATE TABLE IF NOT EXISTS post_bookmarks (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, created_at TIMESTAMP NOT NULL DEFAULT now(), PRIMARY KEY(user_id,post_id))",
    // Follow
    "CREATE TABLE IF NOT EXISTS user_follows (follower_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, following_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, created_at TIMESTAMP NOT NULL DEFAULT now(), PRIMARY KEY(follower_id,following_id), CHECK(follower_id <> following_id))",
    // Report
    "CREATE TABLE IF NOT EXISTS post_reports (id BIGSERIAL PRIMARY KEY, post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE, reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, alasan VARCHAR(60) NOT NULL, catatan TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'open', created_at TIMESTAMP NOT NULL DEFAULT now(), resolved_at TIMESTAMP NULL)",
    "CREATE INDEX IF NOT EXISTS post_reports_post_idx ON post_reports(post_id)",
    "CREATE INDEX IF NOT EXISTS post_reports_status_idx ON post_reports(status)",
    // Repost
    "ALTER TABLE posts ADD COLUMN IF NOT EXISTS repost_of INTEGER NULL REFERENCES posts(id) ON DELETE SET NULL",
    "CREATE INDEX IF NOT EXISTS posts_repost_idx ON posts(repost_of)",
    // Run tracking
    "CREATE TABLE IF NOT EXISTS run_sessions (id BIGSERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, mulai_at TIMESTAMP NOT NULL DEFAULT now(), selesai_at TIMESTAMP NULL, jarak_m DOUBLE PRECISION NOT NULL DEFAULT 0, durasi_dtk INTEGER NOT NULL DEFAULT 0, kalori INTEGER NOT NULL DEFAULT 0, catatan TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'aktif')",
    "CREATE INDEX IF NOT EXISTS run_sessions_user_idx ON run_sessions(user_id, mulai_at DESC)",
    "CREATE TABLE IF NOT EXISTS run_points (id BIGSERIAL PRIMARY KEY, session_id BIGINT NOT NULL REFERENCES run_sessions(id) ON DELETE CASCADE, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, ts TIMESTAMP NOT NULL DEFAULT now(), speed_mps DOUBLE PRECISION NULL, accuracy_m DOUBLE PRECISION NULL)",
    "CREATE INDEX IF NOT EXISTS run_points_sess_idx ON run_points(session_id, ts)",
  ];
  foreach ($stmts as $sql) { @pg_query(db(), $sql); }
} catch (Throwable $e) { /* ignore */ }

/* ------- Helper hashtag + mention parser ------- */
function extract_hashtags(string $text): array {
  preg_match_all('/(?:^|\s)#([a-zA-Z0-9_]{2,40})/u', $text, $m);
  return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
}
function extract_mentions(string $text): array {
  preg_match_all('/(?:^|\s)@([a-zA-Z0-9_]{2,40})/u', $text, $m);
  return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
}
function render_tags_and_mentions(string $html): string {
  // Linkify #tag and @user di luar tag HTML (Quill text content)
  $html = preg_replace_callback('/(^|[\s>])#([a-zA-Z0-9_]{2,40})/u', function($m){
    return $m[1].'<a href="/hashtag.php?t='.urlencode(strtolower($m[2])).'" class="text-primary">#'.htmlspecialchars($m[2]).'</a>';
  }, $html);
  $html = preg_replace_callback('/(^|[\s>])@([a-zA-Z0-9_]{2,40})/u', function($m){
    return $m[1].'<a href="/user.php?u='.urlencode(strtolower($m[2])).'" class="text-info">@'.htmlspecialchars($m[2]).'</a>';
  }, $html);
  return $html;
}
function sync_post_tags(int $postId, string $text): void {
  db_exec("DELETE FROM post_hashtags WHERE post_id=$1", [$postId]);
  foreach (extract_hashtags($text) as $tag) {
    @pg_query_params(db(), "INSERT INTO post_hashtags(post_id,tag) VALUES($1,$2) ON CONFLICT DO NOTHING", [$postId, $tag]);
  }
  db_exec("DELETE FROM post_mentions WHERE post_id=$1", [$postId]);
  foreach (extract_mentions($text) as $uname) {
    $u = db_one("SELECT id FROM users WHERE LOWER(username)=$1 OR LOWER(nama)=$1 LIMIT 1", [$uname]);
    if ($u) {
      @pg_query_params(db(), "INSERT INTO post_mentions(post_id,user_id) VALUES($1,$2) ON CONFLICT DO NOTHING", [$postId, (int)$u['id']]);
      // Notif sederhana
      @pg_query_params(db(), "INSERT INTO notifications(user_id,judul,body,url) VALUES($1,$2,$3,$4)",
        [(int)$u['id'], 'Anda di-mention', 'Seseorang menyebut Anda di sebuah post', '/index.php#p'.$postId]);
    }
  }
}

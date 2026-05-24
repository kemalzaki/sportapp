<?php
/**
 * v9 migrations – idempotent. Auto-loaded oleh header.
 * - post_views (story view tracking)
 * - dm_messages.delivered_at (untuk ceklis 2 / blue)
 */
require_once __DIR__ . '/../config/db.php';

try {
  $stmts = [
    "CREATE TABLE IF NOT EXISTS post_views (
       post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
       user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
       viewed_at TIMESTAMP NOT NULL DEFAULT now(),
       PRIMARY KEY (post_id, user_id))",
    "CREATE INDEX IF NOT EXISTS post_views_post_idx ON post_views(post_id, viewed_at DESC)",
    "ALTER TABLE dm_messages ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL",
    "CREATE INDEX IF NOT EXISTS dm_delivered_idx ON dm_messages(receiver_id, delivered_at)",
  ];
  foreach ($stmts as $sql) { @pg_query(db(), $sql); }
} catch (Throwable $e) { /* ignore */ }

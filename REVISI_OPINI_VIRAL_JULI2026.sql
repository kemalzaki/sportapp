-- REVISI_OPINI_VIRAL_JULI2026.sql
-- Migrasi untuk redesign total opini_viral.php (dashboard analisis sentimen YouTube).
-- Idempotent: aman dijalankan berulang. Tidak menghapus data lama.
-- Catatan: tabel LAMA `opini_viral` (berbasis RSS) TIDAK dihapus — biarkan sebagai arsip.

CREATE TABLE IF NOT EXISTS opini_viral_search (
    id BIGSERIAL PRIMARY KEY,
    keyword TEXT NOT NULL,
    periode VARCHAR(20) NOT NULL DEFAULT '7d',
    date_from TIMESTAMP NULL,
    date_to   TIMESTAMP NULL,
    total_videos INT NOT NULL DEFAULT 0,
    total_comments INT NOT NULL DEFAULT 0,
    summary TEXT,
    topics_json TEXT,
    fetched_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ovs_key ON opini_viral_search(lower(keyword), periode, fetched_at DESC);

CREATE TABLE IF NOT EXISTS opini_viral_comments (
    id BIGSERIAL PRIMARY KEY,
    search_id BIGINT NOT NULL REFERENCES opini_viral_search(id) ON DELETE CASCADE,
    comment_id TEXT NOT NULL,
    video_id TEXT NOT NULL,
    video_title TEXT,
    channel_name TEXT,
    author_name TEXT,
    comment_text TEXT,
    like_count INT DEFAULT 0,
    published_at TIMESTAMP NULL,
    comment_url TEXT,
    sentimen VARCHAR(10) DEFAULT 'netral',
    confidence NUMERIC(5,2) DEFAULT 0,
    alasan TEXT,
    UNIQUE(search_id, comment_id)
);
CREATE INDEX IF NOT EXISTS idx_ovc_search ON opini_viral_comments(search_id);
CREATE INDEX IF NOT EXISTS idx_ovc_sent   ON opini_viral_comments(search_id, sentimen);

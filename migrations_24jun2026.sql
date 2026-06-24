-- Revisi 24 Juni 2026 — tambahan kolom pada tabel event.
-- Aman dijalankan berulang.
ALTER TABLE event ADD COLUMN IF NOT EXISTS kategori_pelaksanaan VARCHAR(20) NOT NULL DEFAULT 'internal';
ALTER TABLE event ADD COLUMN IF NOT EXISTS sumber_eksternal TEXT;

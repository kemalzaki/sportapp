-- Revisi 21 Juni 2026 R4 — Skema tambahan
-- Idempotent. Aman dijalankan berulang.

-- 1. Multi-image post (mengganti video posting).
ALTER TABLE posts ADD COLUMN IF NOT EXISTS images_json TEXT;

-- 2. Hiking/Camping (sudah dari R3, dicantumkan ulang utk reproducibility).
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS gpx_path TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS parkir_info TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS run_route_id BIGINT;

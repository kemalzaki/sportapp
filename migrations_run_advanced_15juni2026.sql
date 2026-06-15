-- ============================================================
-- Revisi 15 Juni 2026: Eksplorasi Rute & Peta Canggih (run.php)
-- ------------------------------------------------------------
-- CATATAN:
-- Tabel berikut JUGA dibuat otomatis (idempotent) saat halaman
-- run.php dibuka pertama kali, jadi sebenarnya Anda tidak wajib
-- mengeksekusi file ini secara manual. File ini disediakan untuk
-- referensi / audit DBA dan untuk lingkungan yang tidak mengizinkan
-- DDL on-the-fly.
-- Tidak ada data lama yang dihapus atau diubah.
-- ============================================================

CREATE TABLE IF NOT EXISTS run_routes (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nama          TEXT NOT NULL DEFAULT 'Rute',
    jarak_m       DOUBLE PRECISION NOT NULL DEFAULT 0,
    elevasi_pref  TEXT NOT NULL DEFAULT 'apa-saja',   -- apa-saja|datar|berbukit
    surface_pref  TEXT NOT NULL DEFAULT 'apa-saja',   -- apa-saja|aspal|tanah|campuran
    geojson       JSONB NOT NULL,                     -- { "coords": [[lat,lng], ...] }
    is_public     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at    TIMESTAMP NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS run_routes_user_idx
    ON run_routes(user_id, created_at DESC);

-- Tidak perlu tabel khusus untuk Heatmap maupun Peta Offline:
-- * Heatmap diturunkan dari run_points yang sudah ada.
-- * Peta Offline disimpan di CacheStorage browser (sisi klien).

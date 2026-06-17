-- =====================================================================
-- Revisi 17 Juni 2026 — Part I
-- Tambahan tabel & catatan untuk revisi #1..#6.
-- Idempotent: aman dijalankan berulang kali di PostgreSQL lokal.
-- =====================================================================

-- (#1) Penyimpanan Tanya-Jawab AI Islami (islami.php)
-- NOTE: tabel ini juga dibuat otomatis oleh islami.php pada akses pertama,
-- migration di sini hanya pengaman jika auto-create gagal karena hak akses.
CREATE TABLE IF NOT EXISTS islami_qa_saved (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL,
    pertanyaan  TEXT   NOT NULL,
    jawaban     TEXT   NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS islami_qa_user_idx ON islami_qa_saved(user_id, created_at DESC);

-- (#4) run_routes — sudah dibuat run.php; pastikan ada kolom-kolom yang
-- dibutuhkan endpoint route_update (nama, elevasi_pref, surface_pref, is_public).
-- Tidak ada perubahan skema baru pada Part I; kolom-kolom yang dipakai sudah ada
-- sejak Revisi 15 Juni 2026 (migrations_run_advanced_15juni2026.sql).
-- Block berikut hanya self-check; tidak akan error jika kolom sudah ada.
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='run_routes') THEN
    CREATE TABLE run_routes (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        nama TEXT NOT NULL DEFAULT 'Rute',
        jarak_m DOUBLE PRECISION NOT NULL DEFAULT 0,
        elevasi_pref TEXT NOT NULL DEFAULT 'apa-saja',
        surface_pref TEXT NOT NULL DEFAULT 'apa-saja',
        geojson JSONB NOT NULL,
        is_public BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    );
    CREATE INDEX run_routes_user_idx ON run_routes(user_id, created_at DESC);
  END IF;
END $$;

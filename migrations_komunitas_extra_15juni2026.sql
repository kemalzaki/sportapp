-- =====================================================================
-- Revisi 15 Juni 2026 — Keamanan & Fitur Ekstra Komunitas
-- Fitur:
--   1) Berbagi Lokasi Real-Time (Live Tracking / Beacon)
--   2) Video Animasi Rute 3D Flyover (sebagian besar client-side, tabel
--      flyover_renders opsional untuk menyimpan metadata hasil render)
--
-- Catatan: SEMUA statement idempotent (CREATE TABLE IF NOT EXISTS).
-- Tidak ada DROP / DELETE / ALTER yang destruktif.
-- Halaman PHP terkait juga sudah memanggil DDL ini saat dibuka (auto),
-- jadi menjalankan file ini secara manual sifatnya OPSIONAL.
-- =====================================================================

-- ---------- Live tracking: sesi berbagi lokasi ----------
CREATE TABLE IF NOT EXISTS live_tracking_sessions (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT       NOT NULL,
    token       VARCHAR(48)  NOT NULL UNIQUE,
    judul       TEXT         NOT NULL DEFAULT 'Live Tracking',
    pesan       TEXT,                                    -- pesan tambahan utk penerima
    olahraga    TEXT         NOT NULL DEFAULT 'lari',
    started_at  TIMESTAMP    NOT NULL DEFAULT now(),
    ended_at    TIMESTAMP,
    expires_at  TIMESTAMP    NOT NULL DEFAULT (now() + INTERVAL '12 hours'),
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    last_lat    DOUBLE PRECISION,
    last_lng    DOUBLE PRECISION,
    last_seen_at TIMESTAMP
);
CREATE INDEX IF NOT EXISTS lts_user_idx   ON live_tracking_sessions(user_id, started_at DESC);
CREATE INDEX IF NOT EXISTS lts_token_idx  ON live_tracking_sessions(token);
CREATE INDEX IF NOT EXISTS lts_active_idx ON live_tracking_sessions(is_active, expires_at);

-- ---------- Live tracking: titik koordinat per sesi ----------
CREATE TABLE IF NOT EXISTS live_tracking_points (
    id          BIGSERIAL PRIMARY KEY,
    session_id  BIGINT      NOT NULL REFERENCES live_tracking_sessions(id) ON DELETE CASCADE,
    lat         DOUBLE PRECISION NOT NULL,
    lng         DOUBLE PRECISION NOT NULL,
    accuracy_m  DOUBLE PRECISION,
    speed_mps   DOUBLE PRECISION,
    heading_deg DOUBLE PRECISION,
    ts          TIMESTAMP   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ltp_session_idx ON live_tracking_points(session_id, id);

-- ---------- Live tracking: kontak darurat (opsional, utk WA blast) ----------
CREATE TABLE IF NOT EXISTS live_tracking_contacts (
    id        BIGSERIAL PRIMARY KEY,
    user_id   BIGINT NOT NULL,
    nama      TEXT   NOT NULL,
    nomor_wa  TEXT,
    email     TEXT,
    relasi    TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ltc_user_idx ON live_tracking_contacts(user_id);

-- ---------- Flyover renders (opsional, hanya metadata) ----------
CREATE TABLE IF NOT EXISTS flyover_renders (
    id           BIGSERIAL PRIMARY KEY,
    user_id      BIGINT NOT NULL,
    run_session_id BIGINT,
    judul        TEXT NOT NULL DEFAULT 'Flyover Route',
    durasi_detik INTEGER NOT NULL DEFAULT 20,
    style_preset TEXT NOT NULL DEFAULT 'satellite',
    file_url     TEXT,
    created_at   TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS flyr_user_idx ON flyover_renders(user_id, created_at DESC);

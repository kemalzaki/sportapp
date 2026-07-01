-- Revisi 2 Juli 2026 — skema tambahan (idempotent)

-- #1 Riwayat Pesanan Paket (kolom tambahan utk catatan admin & waktu update)
ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS admin_catatan TEXT;
ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS updated_at    TIMESTAMP;

-- #3 Usulan Tempat dari member (Coming Soon — Survei Tempat)
CREATE TABLE IF NOT EXISTS tempat_survei (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL,
    nama       VARCHAR(180) NOT NULL,
    alamat     TEXT,
    jenis      VARCHAR(80),
    lat        DOUBLE PRECISION,
    lng        DOUBLE PRECISION,
    catatan    TEXT,
    status     VARCHAR(20) NOT NULL DEFAULT 'baru',
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP
);
CREATE INDEX IF NOT EXISTS tempat_survei_user_idx ON tempat_survei(user_id, created_at DESC);

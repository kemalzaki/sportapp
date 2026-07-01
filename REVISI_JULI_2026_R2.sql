-- ============================================================
-- REVISI_JULI_2026_R2.sql  (PostgreSQL)
--
-- Migrasi tambahan untuk paket revisi Juli 2026 (bagian ke-2).
-- Semua idempotent — aman dijalankan berulang.
-- ============================================================

-- 1) Tabel usulan tempat baru dari member (Coming Soon: Survei Tempat)
CREATE TABLE IF NOT EXISTS tempat_survei (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT NOT NULL,
    nama          VARCHAR(180) NOT NULL,
    alamat        TEXT,
    jenis         VARCHAR(80),
    lat           DOUBLE PRECISION,
    lng           DOUBLE PRECISION,
    catatan       TEXT,
    status        VARCHAR(20) NOT NULL DEFAULT 'baru',
    created_at    TIMESTAMP NOT NULL DEFAULT now(),
    updated_at    TIMESTAMP
);
CREATE INDEX IF NOT EXISTS tempat_survei_user_idx
    ON tempat_survei(user_id, created_at DESC);

-- 2) Tabel paket_pesanan sudah ada; hanya menambah nilai status baru 'menunggu_wa'.
--    Kolomnya VARCHAR jadi tidak butuh ALTER TYPE. Sanity check kolom:
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name='paket_pesanan' AND column_name='status'
    ) THEN
        RAISE NOTICE 'Tabel paket_pesanan belum ada — jalankan migrasi awal terlebih dulu.';
    END IF;
END $$;

-- 3) (Opsional) Bila ingin membersihkan sisa kolom Midtrans yang tak dipakai lagi,
--    aktifkan baris di bawah. Direkomendasikan DIBIARKAN (untuk riwayat lama).
-- ALTER TABLE paket_pesanan DROP COLUMN IF EXISTS snap_token;
-- ALTER TABLE paket_pesanan DROP COLUMN IF EXISTS snap_redirect;
-- ALTER TABLE paket_pesanan DROP COLUMN IF EXISTS midtrans_status;
-- ALTER TABLE paket_pesanan DROP COLUMN IF EXISTS midtrans_raw;

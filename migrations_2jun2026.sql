-- ============================================================
-- Migrasi 2 Jun 2026 (idempotent — aman dijalankan berkali-kali)
-- Tidak ada data yang dihapus.
-- ============================================================

-- Kolom Midtrans untuk integrasi pembayaran transfer
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS payment_status   VARCHAR(20) DEFAULT 'pending';
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS midtrans_order_id VARCHAR(40);
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS snap_token       VARCHAR(120);
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS snap_redirect    TEXT;
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS stok_dipotong    BOOLEAN NOT NULL DEFAULT false;

-- (Opsional) index untuk pencarian status & lookup midtrans
CREATE INDEX IF NOT EXISTS jjn_pesanan_payment_idx ON jajanan_pesanan(payment_status);
CREATE INDEX IF NOT EXISTS jjn_pesanan_midtrans_idx ON jajanan_pesanan(midtrans_order_id);

-- =========================================================================
-- REVISI_JULI_2026_R8.sql
-- Migrasi PostgreSQL untuk revisi R8 (Juli 2026) — KawanKeringat / sportapp.
--
-- Aman dijalankan berulang kali (idempotent). Tidak menghapus data.
-- Jalankan di database `sportapp` (atau nama DB kamu) menggunakan psql:
--     psql -U <user> -d sportapp -f REVISI_JULI_2026_R8.sql
--
-- Ringkasan perubahan:
--   #3  pertemanan.tanggal_terakhir_ketemu (DATE) — untuk fitur "Pertemananku".
--   #5  notifications.komunitas_id (INTEGER, ter-index) — notifikasi per komunitas.
-- =========================================================================

BEGIN;

-- ---------- (#3) Pertemananku — tanggal terakhir ketemu ---------------------
ALTER TABLE IF EXISTS pertemanan
    ADD COLUMN IF NOT EXISTS tanggal_terakhir_ketemu DATE NULL;

-- Bila tabel pertemanan belum ada sama sekali (fresh install), buat sekaligus.
CREATE TABLE IF NOT EXISTS pertemanan (
    id                        SERIAL PRIMARY KEY,
    user_id                   INTEGER NOT NULL,
    nama                      VARCHAR(120) NOT NULL,
    tanggal_kenalan           DATE,
    tanggal_terakhir_ketemu   DATE,
    kedekatan                 SMALLINT,
    catatan                   TEXT,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_pertemanan_user
    ON pertemanan(user_id);

-- ---------- (#5) Notifications per-komunitas --------------------------------
ALTER TABLE IF EXISTS notifications
    ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS notif_kom_idx
    ON notifications(komunitas_id, user_id, dibaca);

-- Isi otomatis komunitas_id untuk notifikasi lama berdasarkan komunitas user
-- (best effort — tidak wajib). Baris yang tidak ketemu tetap NULL (broadcast).
UPDATE notifications n
   SET komunitas_id = u.komunitas_id
  FROM users u
 WHERE n.user_id = u.id
   AND n.komunitas_id IS NULL
   AND u.komunitas_id IS NOT NULL;

COMMIT;

-- Selesai. Cek hasil:
--   \d pertemanan
--   \d notifications

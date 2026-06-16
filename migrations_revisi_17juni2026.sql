-- Migrasi tambahan Revisi 17 Juni 2026
-- Jalankan: psql -d sportapp -f migrations_revisi_17juni2026.sql
-- Catatan: tim.php juga membuat tabel ini secara idempotent saat dibuka pertama kali.

CREATE TABLE IF NOT EXISTS tim_external (
    id          SERIAL PRIMARY KEY,
    tim_id      INTEGER NOT NULL REFERENCES tim(id) ON DELETE CASCADE,
    nama        VARCHAR(120) NOT NULL,
    nomor_wa    VARCHAR(30),
    catatan     VARCHAR(200),
    invited_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_tim_external_tim ON tim_external(tim_id);

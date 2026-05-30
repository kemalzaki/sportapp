-- Revisi 1 Juni 2026 — jalankan sekali via psql, atau biarkan auto via config/db.php yang sudah dipatch.
-- Idempotent: aman dijalankan berulang, TIDAK menghapus data apapun.

-- #6: lat/lng lokasi jajanan
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lat NUMERIC(10,6);
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lng NUMERIC(10,6);

-- (Catatan) Tabel jajanan_pesanan SUDAH punya kolom pickup_lat & pickup_lng
-- (dari migrations_31mei_v2.sql) yang dipakai untuk fitur #5 (link Google Maps di Kurir).
-- Tidak ada perubahan skema lain untuk revisi ini.

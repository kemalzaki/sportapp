-- ============================================================
-- Migrasi REVISI 31 Mei 2026 (versi terbaru)
-- Menambahkan kolom jam buka & jam tutup pedagang pada tabel jajanan.
-- Jalankan di PostgreSQL:
--   psql -U <user> -d <db> -f migrations_31mei2026_revisi.sql
-- Aman dijalankan berulang (pakai IF NOT EXISTS).
-- ============================================================

ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS jam_buka  TIME;
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS jam_tutup TIME;

-- Opsional: contoh default jam operasional (07:00 - 21:00) untuk data lama
-- agar tombol "Pesan" tidak otomatis tertutup. Hapus baris ini kalau
-- ingin diisi manual lewat halaman admin.
UPDATE jajanan
   SET jam_buka  = COALESCE(jam_buka,  TIME '07:00'),
       jam_tutup = COALESCE(jam_tutup, TIME '21:00')
 WHERE jam_buka IS NULL OR jam_tutup IS NULL;

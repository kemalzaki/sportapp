-- ============================================================
-- Migration R16 (25 Juni 2026) — Revisi halaman Islami (parsial)
-- Jalankan: psql -d sportapp -f migrations_r16.sql
-- (Idempotent, aman dijalankan berulang. TIDAK menghapus data apa pun.)
-- ============================================================

-- (#4 islami.php) Kunci Hub Islami -> hanya paket "komunitas".
-- Pastikan kolom paket ada di tabel users: gratis / pro / komunitas.
-- (Sama seperti migrations_r14.sql; diulang di sini agar self-contained.)
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis';
UPDATE users SET paket = 'gratis' WHERE paket IS NULL OR paket = '';

-- Contoh menjadikan seorang user sebagai paket Komunitas (silakan sesuaikan id/email):
-- UPDATE users SET paket = 'komunitas' WHERE email = 'admin@example.com';
-- Catatan: user dengan role = 'admin' otomatis dianggap "komunitas" (akses penuh).

-- (#1 catatan_hafalan.php) Tabel catatan_hafalan dibuat otomatis oleh aplikasi
--   (CREATE TABLE IF NOT EXISTS) saat halaman dibuka. Tidak ada perubahan skema
--   untuk revisi pagination (hanya tampil 5 baris / halaman via AJAX).

-- (#2 kajian.php, #3 sejarah_nabi.php, #5 doa.php, #6 tata cara) tidak memerlukan
--   perubahan skema database tambahan.

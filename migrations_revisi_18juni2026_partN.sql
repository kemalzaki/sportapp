-- migrations_revisi_18juni2026_partN.sql
-- Revisi 18 Juni 2026 (Lanjutan) — Sumber defisit kalori (manual / jogging Riwayat).
-- Dipakai oleh: kalori_mingguan.php
-- Catatan: tabel ini juga otomatis dibuat (CREATE TABLE IF NOT EXISTS) saat halaman dibuka,
-- jadi migrasi ini hanya diperlukan jika Anda ingin pre-provision di PostgreSQL.

CREATE TABLE IF NOT EXISTS kalori_defisit_setting (
    user_id        INT PRIMARY KEY,
    sumber         VARCHAR(20) NOT NULL DEFAULT 'auto',
    manual_harian  INT NOT NULL DEFAULT 0,
    updated_at     TIMESTAMP NOT NULL DEFAULT now()
);

-- sumber yang valid: 'auto' | 'jogging' | 'manual' | 'gabungan'
-- 'jogging' membaca tabel upload_harian (riwayat.php) baris jenis ILIKE '%jog%'/'%lari%'/'%run%'.

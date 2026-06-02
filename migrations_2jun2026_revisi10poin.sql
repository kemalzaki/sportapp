-- =============================================================
-- Migrasi 2 Juni 2026 — Revisi 10 Poin
-- Jalankan: psql "$DATABASE_URL" -f migrations_2jun2026_revisi10poin.sql
-- Aman dijalankan berulang (idempotent).
-- =============================================================

-- (1) Pengaturan biaya admin Midtrans + biaya aplikasi (key/value)
CREATE TABLE IF NOT EXISTS app_settings (
    skey      VARCHAR(80) PRIMARY KEY,
    sval      TEXT        NOT NULL DEFAULT '',
    keterangan TEXT,
    updated_at TIMESTAMP  NOT NULL DEFAULT now()
);
INSERT INTO app_settings(skey,sval,keterangan) VALUES
  ('biaya_admin_fixed','4000','Biaya admin Midtrans fixed (Rp) per transaksi'),
  ('biaya_admin_pct','0.007','Biaya admin Midtrans persen (0.007 = 0.7%)'),
  ('biaya_aplikasi_fixed','1000','Biaya aplikasi fixed (Rp) per transaksi'),
  ('biaya_aplikasi_pct','0','Biaya aplikasi persen'),
  ('invoice_email_from','no-reply@hapfam.local','Alamat email pengirim invoice'),
  ('invoice_email_nama','HapFam SportApp','Nama pengirim invoice')
ON CONFLICT (skey) DO NOTHING;

-- (2) Email pembeli untuk invoice
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS email_pemesan VARCHAR(160);
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS biaya_aplikasi BIGINT NOT NULL DEFAULT 0;
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS biaya_admin BIGINT NOT NULL DEFAULT 0;
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS invoice_sent_at TIMESTAMP;

-- (3) Hari buka toko/jajanan. Format: csv 0..6 (0=Min, 6=Sab). Kosong = setiap hari.
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS hari_buka VARCHAR(20) DEFAULT '0,1,2,3,4,5,6';
ALTER TABLE toko    ADD COLUMN IF NOT EXISTS hari_buka VARCHAR(20) DEFAULT '0,1,2,3,4,5,6';

-- (6) CRUD Navigasi Menu (gaya CMS WordPress)
CREATE TABLE IF NOT EXISTS nav_menu (
    id        SERIAL PRIMARY KEY,
    label     VARCHAR(80)  NOT NULL,
    url       VARCHAR(255) NOT NULL DEFAULT '#',
    icon      VARCHAR(60),              -- contoh: bi-house-door
    parent_id INT REFERENCES nav_menu(id) ON DELETE CASCADE,
    urutan    INT NOT NULL DEFAULT 0,
    aktif     BOOLEAN NOT NULL DEFAULT true,
    target    VARCHAR(10) DEFAULT '_self',
    posisi    VARCHAR(20) NOT NULL DEFAULT 'drawer', -- drawer/top/bottom
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS nav_menu_pos_urut_idx ON nav_menu(posisi, urutan);

-- (7) CRUD komponen / blok di index.php
CREATE TABLE IF NOT EXISTS index_blok (
    id        SERIAL PRIMARY KEY,
    judul     VARCHAR(120) NOT NULL,
    konten    TEXT         NOT NULL DEFAULT '',     -- HTML (sanitize via Quill di admin)
    posisi    VARCHAR(20)  NOT NULL DEFAULT 'top',  -- top / middle / bottom
    urutan    INT          NOT NULL DEFAULT 0,
    aktif     BOOLEAN      NOT NULL DEFAULT true,
    created_at TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at TIMESTAMP   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS index_blok_pos_urut_idx ON index_blok(posisi, urutan);

-- (8) Tema warna pilihan member
ALTER TABLE users ADD COLUMN IF NOT EXISTS tema_warna VARCHAR(20) DEFAULT 'sky';
-- Pilihan valid: sky, indigo, emerald, rose, amber, violet, slate

-- (9 & 10) Kebijakan Privasi (UU PDP)
CREATE TABLE IF NOT EXISTS kebijakan_privasi (
    id        SERIAL PRIMARY KEY,
    versi     VARCHAR(20)  NOT NULL DEFAULT '1.0',
    judul     VARCHAR(160) NOT NULL DEFAULT 'Kebijakan Privasi',
    konten    TEXT         NOT NULL DEFAULT '',
    aktif     BOOLEAN      NOT NULL DEFAULT true,
    created_at TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at TIMESTAMP   NOT NULL DEFAULT now()
);
INSERT INTO kebijakan_privasi(versi,judul,konten,aktif)
SELECT '1.0','Kebijakan Privasi (UU PDP No. 27 Tahun 2022)',
'<h3>Pendahuluan</h3><p>HapFam SportApp menghormati privasi Anda dan mematuhi UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
<h3>1. Data yang Kami Kumpulkan</h3><ul><li>Data identitas: nama, email, jenis kelamin, nomor WhatsApp</li><li>Data lokasi (saat memesan jajanan/booking lapangan)</li><li>Data aktivitas olahraga, foto profil, postingan</li></ul>
<h3>2. Dasar Pemrosesan</h3><p>Persetujuan Anda saat mendaftar, pelaksanaan kontrak (pemesanan), dan kepentingan sah.</p>
<h3>3. Hak Subjek Data</h3><ul><li>Hak mendapatkan informasi</li><li>Hak akses, koreksi, dan penghapusan</li><li>Hak menarik persetujuan</li><li>Hak menolak pemrosesan otomatis</li></ul>
<h3>4. Keamanan</h3><p>Kami menerapkan enkripsi password (bcrypt), HTTPS, dan kontrol akses berbasis peran.</p>
<h3>5. Pengiriman ke Pihak Ketiga</h3><p>Hanya untuk pemrosesan pembayaran (Midtrans) dan penyimpanan media (ImageKit) sesuai standar industri.</p>
<h3>6. Kontak DPO</h3><p>Hubungi: admin@hapfam.local</p>',
true
WHERE NOT EXISTS (SELECT 1 FROM kebijakan_privasi);

-- Persetujuan member terhadap privasi (audit trail)
ALTER TABLE users ADD COLUMN IF NOT EXISTS privasi_disetujui_at TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS privasi_versi_disetujui VARCHAR(20);

-- =============================================================
-- Selesai. Setelah migrasi:
--   1) Buka /admin/biaya.php untuk atur biaya admin & aplikasi
--   2) Buka /admin/menu.php untuk atur navigasi
--   3) Buka /admin/blok_index.php untuk atur blok beranda
--   4) Buka /admin/privasi.php untuk edit kebijakan privasi
-- =============================================================

# Revisi 2 Juni 2026 — 10 Poin

## Instalasi
1. Jalankan migrasi PostgreSQL:
   ```
   psql "$DATABASE_URL" -f migrations_2jun2026_revisi10poin.sql
   ```
2. Salin / overwrite file dalam arsip ini ke direktori project sesuai struktur folder.
3. Pastikan PHP punya `mail()` aktif (atau ganti `includes/invoice_email.php` dengan SMTP).

## Daftar file yang direvisi
| # | File | Poin |
|---|------|------|
| 1 | `admin/biaya.php` (BARU) | (1) CRUD Biaya Admin Midtrans + Biaya Aplikasi |
| 2 | `includes/app_settings.php` (BARU) | Helper key/value untuk biaya |
| 3 | `includes/invoice_email.php` (BARU) | (2) Kirim invoice HTML rapi ke email pembeli |
| 4 | `jajanan.php` | (1)(2)(3) baca biaya dari settings, kirim invoice, input email, cek hari buka |
| 5 | `includes/bottom_nav.php` | (4) Tab "Event" → "Berita" (`/berita.php`) |
| 6 | `includes/header.php` | (5) hilangkan foto user di top mobile · (8) inject tema warna user |
| 7 | `includes/theme_user.php` (BARU) | (8) generator CSS tema warna |
| 8 | `profile.php` | (8) form pilih tema warna member |
| 9 | `admin/menu.php` (BARU) | (6) CRUD Navigasi Menu CMS-style |
| 10 | `includes/menu_render.php` (BARU) | (6) helper render `<nav_menu>` |
| 11 | `admin/blok_index.php` (BARU) | (7) CRUD blok komponen `index.php` |
| 12 | `index.php` | (7) render blok dari tabel `index_blok` |
| 13 | `login.php` | (9) checkbox & link Kebijakan Privasi |
| 14 | `register.php` | (9) checkbox wajib + simpan persetujuan |
| 15 | `privasi.php` (BARU) | Halaman publik kebijakan privasi |
| 16 | `admin/privasi.php` (BARU) | (10) CRUD Kebijakan Privasi versioned |
| 17 | `migrations_2jun2026_revisi10poin.sql` (BARU) | semua skema baru |

## Catatan tabel PostgreSQL baru / kolom baru
- **Baru**: `app_settings`, `nav_menu`, `index_blok`, `kebijakan_privasi`
- **Kolom baru**:
  - `jajanan_pesanan`: `email_pemesan`, `biaya_admin`, `biaya_aplikasi`, `invoice_sent_at`
  - `jajanan`, `toko`: `hari_buka` (csv `0..6`, kosong=tiap hari)
  - `users`: `tema_warna`, `privasi_disetujui_at`, `privasi_versi_disetujui`

## Yang BELUM (dan saran lanjutan)
- **CRUD `hari_buka` di `admin/jajanan.php` & `admin/toko.php`**: kolom sudah ada di DB & ter-validasi di pembeli. Form input di admin belum disuntik (cukup tambah `<input name="hari_buka" placeholder="0,1,2,3,4,5,6">`).
- **SMTP**: `invoice_email.php` pakai `mail()` default PHP. Untuk production, ganti ke PHPMailer/SwiftMailer.
- **Editor WYSIWYG**: `admin/blok_index.php` & `admin/privasi.php` pakai textarea biasa. Tinggal aktifkan Quill (sudah dimuat di header.php) dengan `new Quill('#konten',...)`.

## Cara invoice tetap terlihat saat Midtrans ditutup
URL `/jajanan.php?invoice=KODE` (atau `?berhasil=KODE` yang sudah ada) menampilkan rincian pesanan dari DB — tidak bergantung popup Snap Midtrans.

# Revisi 4 Juni 2026

## File yang diubah
- `index.php`
  - **Info Beasiswa** dihapus dari grid Info & Wawasan.
  - **Total Visitor** dipindah ke paling atas (tepat sebelum dashboard stats).
  - Urutan blok dikunci lewat JS reorder: Dashboard → **Story Hari Ini** → **Social Feed** → **Forum Komunitas** → Online → Event → Jadwal → Info.
- `includes/skeleton.php`
  - Halaman tetap muncul lebih dulu, lalu skeleton tampil di `#skel-host` sebentar (mengikuti `data-skeleton` body).
  - **Baru:** overlay skeleton navigasi (`#hf-nav-skel`) langsung tampil saat user klik link menu / submit form, sebagai feedback "halaman sedang dibuka", lalu hilang otomatis pada `pageshow`. Berlaku untuk semua halaman yang me-include `includes/header.php`.

## Database / PostgreSQL
Tidak ada migrasi baru. Tabel `site_visitors` sudah dibuat otomatis oleh `index.php` (lihat blok `CREATE TABLE IF NOT EXISTS site_visitors`). Skema `sportapp.sql` yang sudah ada cukup.

## Cara pakai
Cukup overwrite file `index.php` dan `includes/skeleton.php` di project lokal Anda. Bersihkan cache browser (Ctrl+Shift+R) agar skeleton baru terambil.

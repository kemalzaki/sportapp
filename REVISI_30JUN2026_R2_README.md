# Revisi 30 Juni 2026 (R2)

File yang direvisi (timpa file lama di folder yang sama):

1. `opini_viral.php`
   - **Komentar kosong diperbaiki.** Untuk item dari Google News / YouTube
     yang tidak punya komentar, sistem otomatis mencari thread Reddit yang
     paling relevan (search Reddit JSON) lalu mengambil 5 komentar teratas.
   - Fallback terakhir: bila Reddit juga tidak menghasilkan, ringkasan
     berita dipotong menjadi 1-2 kalimat agar kartu komentar tidak kosong.
   - Tidak ada perubahan skema database (kolom `komentar` sudah ada sejak R25).

2. `includes/bottom_nav.php`
   - **FAB +Upload dirapikan.** Lingkaran 56px, gradient lembut, ring putih
     tipis di dalam, baseline label sejajar dengan tab lain, glow biru halus
     yang tidak berlebihan. Tidak lagi "menggantung" terlalu tinggi.

3. `paket_upgrade.php`
   - **Snap.js dimuat jauh lebih cepat.** Sekarang menggunakan
     `<link rel=preconnect>`, `<link rel=preload>`, dan `<script>` sinkron
     non-async di awal halaman sehingga Snap sudah siap saat user klik
     "Bayar Midtrans". Fallback otomatis ke URL alternatif (sandbox/prod).
     Polling Snap.js dipercepat ke 100 ms dengan timeout 15 detik.

## PostgreSQL
Tidak ada migrasi yang perlu dijalankan. Skema `opini_viral` sudah
memiliki kolom `komentar` (ditambahkan idempotent oleh `opini_viral.php`).

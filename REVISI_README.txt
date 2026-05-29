REVISI sportapp_core — ringkasan perubahan
==========================================

File yang direvisi (timpa file lama dengan isi zip ini):
  1. berita.php
  2. buku.php
  3. kalistenik.php
  4. assets/css/sport-islami.css   (BARU — tema gambar olahraga + islami)

Perubahan
---------

1) berita.php
   - Berita dibuka sebagai POPUP (modal Bootstrap) berisi judul, gambar,
     tanggal, kategori, dan ringkasan dari RSS.
   - Tidak lagi redirect ke antaranews.com saat klik judul/tombol "Baca".
   - Tetap menyediakan tombol "Buka Sumber Asli" di footer modal bagi
     yang ingin baca artikel penuh di situs asal.

2) buku.php
   - Sumber data diganti dari Google Books API ke OPEN LIBRARY API
     (https://openlibrary.org), gratis & tanpa API key, stabil dari server.
   - Tetap pakai cache 30 menit lewat ip_fetch_json().
   - Cover buku otomatis di https://covers.openlibrary.org.
   - Fallback berlapis (subject + tahun → subject saja → search.json)
     sehingga pesan "Gagal mengambil koleksi buku" praktis tidak muncul lagi
     selama server punya akses internet.

3) kalistenik.php
   - Setiap gerakan dapat tombol "Lihat Gerakan" (di tabel paket DAN di
     kartu panduan).
   - Modal popup menampilkan: gambar gerakan, target otot, langkah detail
     (4 langkah), tips form, dan tombol "Cari Video Tutorial" ke YouTube.
   - Gambar gerakan diambil dari Wikimedia Commons (gratis & stabil).

4) Desain nuansa olahraga + islami
   - File baru: assets/css/sport-islami.css
   - Setiap halaman direvisi dapat hero banner gradient (hijau-teal
     bernuansa islami) + foto olahraga, hover card lebih halus, dan
     ornamen radial kecil.

PostgreSQL
----------
TIDAK ada tambahan tabel / kolom baru yang diperlukan. Skema di
sportapp.sql lama tetap kompatibel — revisi ini murni di sisi tampilan
+ ganti sumber data eksternal (Open Library) dan tambahan modal popup.
Jadi cukup pakai database PostgreSQL yang sudah ada, tidak perlu migrasi.

Cara pasang
-----------
1. Backup folder lama (opsional).
2. Ekstrak zip ini, lalu salin/timpa ke folder project:
     - berita.php
     - buku.php
     - kalistenik.php
     - assets/css/sport-islami.css   (buat folder jika belum ada)
3. Pastikan ekstensi PHP cURL aktif (untuk fetch RSS / Open Library).
4. Akses halaman:
     /berita.php, /buku.php, /kalistenik.php

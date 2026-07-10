# Revisi Opini Viral — R31 (10 Juli 2026)

Revisi kecil setelah R30. Hanya file `opini_viral.php` yang berubah.
Skema database (tabel `opini_viral_search` & `opini_viral_comments`) TIDAK berubah,
data lama tetap terpakai.

## Yang diperbaiki

### 1. Proses "Cari Opini" terlalu lama
- Dulu mengambil **20 video × 100 komentar = ±2000 komentar** per pencarian,
  lalu semuanya dianalisis AI batch → sering timeout / lama sekali.
- Sekarang **total maksimum 100 komentar** per pencarian (sesuai permintaan).
  - Diambil dari sampai 15 video, maksimal ±20 komentar top-relevance per video.
  - Berhenti begitu total mencapai 100.
- Cache diperpanjang **30 menit → 24 jam** supaya keyword yang sama tidak
  memanggil YouTube API lagi.

### 2. Hasil sudah ada di DB tapi tidak muncul di UI
Kalau proses awal sempat timeout, kadang data sudah masuk ke DB tetapi
frontend tidak menerima `search_id`. Sekarang:

- Ada panel **"🕘 Riwayat Pencarian"** di atas hasil — menampilkan 20 pencarian
  terakhir dari tabel `opini_viral_search`. Klik **Lihat** untuk memuat semua
  komentar + statistik + word cloud tanpa memanggil YouTube lagi (langsung
  baca dari `opini_viral_comments`).
- Kalau tombol **Cari Opini** gagal/timeout, otomatis fallback: cek
  `opini_viral_search` untuk keyword itu, kalau ada → langsung tampilkan
  hasil terakhir dari DB.
- Endpoint baru (internal): `?action=history` dan `?action=find_latest&keyword=…`.

## Instalasi

1. **Replace** `opini_viral.php` di project dengan file di zip ini.
2. **Tidak perlu jalankan SQL migrasi lagi** — skema R30 sudah cukup.
   (File `REVISI_OPINI_VIRAL_JULI2026.sql` dari zip sebelumnya tetap valid;
   dilampirkan lagi untuk referensi kalau ada instalasi bersih.)
3. Pastikan `YOUTUBE_API_KEY` sudah ada di `config/env.local.php`
   (`putenv('YOUTUBE_API_KEY=AIza…');`).
4. Opsional: `OPENROUTER_API_KEY` / `GROQ_API_KEY` / `GEMINI_API_KEY`
   untuk klasifikasi sentimen AI. Kalau tidak ada, fallback ke lexicon.

## Tidak ada penambahan tabel / kolom PostgreSQL

Skema sudah lengkap dari R30. Data lama yang sudah tersimpan di
`opini_viral_search` & `opini_viral_comments` akan otomatis muncul di panel
"Riwayat Pencarian" setelah file di-replace.

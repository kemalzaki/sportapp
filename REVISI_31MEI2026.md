# Revisi 31 Mei 2026

Arsip ini HANYA berisi file yang direvisi. Timpa di folder project Anda dengan struktur folder yang sama.

## Daftar file
- `islami.php`                       — kompas kiblat baru (bergerak sesuai gerakan HP) + hero banner eye-catching.
- `berita.php`                       — gabung sumber CNN Indonesia + Detik + Kompas + Antara, filter HANYA berita 2026, urut terbaru.
- `run.php`                          — tombol baru **"Posisikan Posisi Sekarang"** untuk memusatkan peta ke lokasi GPS Anda.
- `index.php`                        — hero "Info & Wawasan" eye-catching + kartu baru **"Artikel Olahraga & Teknik"**. Video Terbaru tetap **IPTV** (bukan YouTube).
- `artikel_olahraga.php`             — BARU. Bacaan macam-macam olahraga & teknik yang benar (sumber: Wikipedia REST API).
- `assets/css/sport-islami.css`      — gaya kompas kiblat, hero variants, info-card hover, dll.

## Perubahan per item permintaan
1. **Kompas islami.php** — sebelumnya gambar kompas tidak bergerak. Perbaikan:
   - iOS: minta `DeviceOrientationEvent.requestPermission()` lewat tombol "Aktifkan Sensor".
   - Android Chrome: pakai event `deviceorientationabsolute` + konversi `alpha` ke heading kompas.
   - Hitung bearing lokasi user → Ka'bah (21.4225, 39.8262) dengan formula great-circle, lalu putar jarum
     ke `(bearingKiblat - headingHP)` sehingga jarum selalu menunjuk kiblat saat HP diputar.
   - Fallback: kalau GPS ditolak, dipakai Jakarta (-6.2, 106.81) sebagai posisi default.

2. **Berita 2026** — pakai 4 sumber RSS Indonesia, filter `date('Y', pubDate) === 2026`,
   dedup berdasarkan judul, urut paling baru. Kartu menampilkan badge sumber + badge "2026".

3. **Video Terbaru = IPTV** — sudah tetap IPTV (iptv-org/iptv channel Indonesia, player HLS.js).
   Tidak ada lagi embed YouTube di section ini.

4. **Lari** — tombol `Posisikan Posisi Sekarang` di `run.php` membaca GPS sekali
   (`getCurrentPosition`), memusatkan peta ke posisi user, dan menaruh marker biru "Anda di sini".
   Tidak memulai/menghentikan sesi lari.

5. **Artikel Olahraga & Teknik** — halaman baru `artikel_olahraga.php` mengambil ringkasan
   artikel dari **Wikipedia REST API** (`https://id.wikipedia.org/api/rest_v1/page/summary/...`)
   untuk 16 cabang olahraga + 10 teknik & latihan (sepak bola, bulu tangkis, lari, renang,
   yoga, push-up, plank, peregangan, sprint, kalistenik, dst.). Klik kartu → tampilan detail.

6. **Desain eye-catching** — `index.php` & `islami.php` dapat hero banner gradient + foto
   sport/islami, ornamen radial halus, dan `info-card` dengan hover lift.

## PostgreSQL — perlu tambahan?

**TIDAK.** Tidak ada tabel/kolom baru. Semua data baru murni di sisi PHP / API publik.
`sportapp.sql` Anda tetap dipakai apa adanya, tidak perlu migrasi.

## Cara apply
1. Backup folder lama.
2. Ekstrak zip ini, timpa ke folder project pada path yang sama persis.
3. Pastikan ekstensi PHP cURL aktif & server bisa mengakses:
   - `id.wikipedia.org` (untuk artikel olahraga)
   - `cnnindonesia.com`, `detik.com`, `kompas.com`, `antaranews.com` (RSS berita)
   - `iptv-org.github.io` (daftar channel IPTV)
4. **Penting untuk kompas kiblat di HP:**
   - Buka via **HTTPS** (sensor orientasi tidak aktif di HTTP non-localhost).
   - Di iPhone, tap tombol "Aktifkan Sensor" yang muncul di header kartu Kompas Kiblat.
5. Refresh browser (Ctrl+F5).

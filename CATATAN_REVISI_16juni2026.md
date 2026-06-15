# Catatan Revisi 16 Juni 2026

Zip ini **hanya berisi file yang direvisi** (parsial). Letakkan tiap file
pada path yang sama di project Anda (timpa file lama).

## Daftar file
- `flyover.php`
- `live_tracking.php`
- `run.php`
- `includes/header.php`

## Ringkasan perubahan

### 1. `includes/header.php` — Navigasi Menu Mobile
- Grup **Jogging Progress** kini berisi: Monitoring · Upload · Riwayat ·
  Tracking Jalur · **Live Tracking / Beacon** (baru) · **Video Flyover 3D** (baru).

### 2. `live_tracking.php`
- **Mulai Sesi Berbagi** sekarang hanya untuk **Lari** (dropdown olahraga
  diganti label terkunci + hidden input `olahraga=lari`).
- Ditambah card **Cara Penggunaan Live Tracking / Beacon** (6 langkah +
  tips Wake Lock).

### 3. `flyover.php`
- Fix error PostgreSQL: kolom `nama_rute` **tidak ada** di tabel
  `run_sessions`. Query diubah memakai `COALESCE(NULLIF(catatan,''), 'Sesi #'||id)`.
  Tidak perlu menambah kolom ke database.

### 4. `run.php`
- Ditambah panduan **Cara Penggunaan Tracking Jalur / Rute Realtime**.
- Ditambah panduan **Cara Penggunaan Eksplorasi Rute & Peta Canggih**.
- **Route Builder — Auto Generate** sekarang:
  - Membuat **4 kandidat rute** dengan bearing acak berbeda.
  - **Iterative scaling**: tiap kandidat di-scale ulang sampai jaraknya ≤7%
    dari target km (memperbaiki rute generated yang tidak sesuai target).
  - Menilai preferensi nyata:
    - **Elevasi** via Open-Elevation API (12 sampel sepanjang rute) → skor
      datar vs berbukit.
    - **Permukaan jalan** via Overpass API (tag `surface` OSM dalam bbox) →
      skor aspal / tanah / campuran.
  - Memilih kandidat terbaik dengan bobot Jarak 55% · Elevasi 25% · Permukaan 20%.
  - Info hasil sekarang menampilkan selisih jarak, ascent (m), dan %
    permukaan yang cocok.
- **Route Builder — Buat Sendiri (Manual)** (baru):
  - Toggle **Auto Generate / Buat Sendiri** di atas form.
  - Klik peta untuk menambahkan waypoint (urut dengan nomor),
    tombol **Hapus titik terakhir**, **Reset**, dan **Snap ke jalan**
    (OSRM merangkai semua waypoint Anda menjadi rute jalan asli, lalu bisa
    disimpan/di-export GPX seperti rute auto).

## Catatan PostgreSQL
**Tidak ada migrasi tambahan yang diperlukan** untuk revisi ini.
Tabel `run_routes`, `live_tracking_sessions`, `live_tracking_contacts`,
`flyover_renders` dibuat otomatis (idempotent `CREATE TABLE IF NOT EXISTS`)
saat halaman pertama kali dibuka.

Jika Anda ingin nanti menyimpan **nama rute kustom** per-sesi lari (sehingga
flyover menampilkan nama yang Anda set sendiri, bukan "Sesi #ID" atau catatan),
opsional jalankan:

```sql
ALTER TABLE run_sessions ADD COLUMN IF NOT EXISTS nama_rute TEXT;
```

(opsional saja — kode saat ini sudah aman tanpa kolom ini).

## Layanan eksternal yang dipakai (gratis, tanpa API key)
- `https://router.project-osrm.org` — snap/route foot
- `https://api.open-elevation.com` — sampel elevasi
- `https://overpass-api.de/api/interpreter` — query tag `surface` OSM

Semua bersifat best-effort & rate-limited. Bila gagal, ulangi generate.

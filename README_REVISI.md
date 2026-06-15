# Revisi 15 Juni 2026 — Patch Files

Salin file-file ini menimpa file dengan nama yang sama di proyek `sportapp_core`.

## Daftar file (relatif ke root proyek)

| File baru / direvisi | Untuk revisi nomor |
|---|---|
| `run.php` | #1 AI route, #2 Popup melayang (PiP), #3 Anti garis lurus saat layar mati |
| `live_tracking.php` | #2 Popup melayang (PiP) |
| `api_run.php` | #1 Endpoint AI route (`_action=ai_route_from_image`) + perbaikan endpoint titik untuk flyover |
| `flyover.php` | #4 Perbaikan "Sesi tidak memiliki titik GPS" (panggil endpoint yang benar) |
| `monitoring.php` | #5 Statistik tren jogging (pace · durasi · jarak) + cara baca pace trend |
| `admin/sistem.php` | #6 Halaman "Cek Sistem" baru (disk, DB, ImageKit, PHP) |
| `includes/header.php` | #6 Tambah menu Cek Sistem · #7 Hapus menu Riwayat Login Member |
| `kalori_mingguan.php` | #8 Navigasi riwayat minggu sebelumnya |
| `islami.php` | #9 Panel besar diubah jadi icon-card |
| `shalat_tatacara.php` (baru) | #9 Halaman terpisah Tata Cara Shalat |
| `shalat_rawatib.php` (baru)  | #9 Halaman terpisah Shalat Sunnah Rawatib |
| `shalat_sunnah.php` (baru)   | #9 Halaman terpisah Duha & Tahajud |
| `rukun_islam.php` (baru)     | #9 Halaman terpisah Rukun Islam |

## Catatan teknis (penting)

### #1 AI Import Rute dari Gambar
- Membutuhkan env var **`OPENAI_API_KEY`** di server.
- Geocoding memakai Nominatim (OpenStreetMap) — gratis, tanpa API key.
- Tidak perlu tabel/kolom baru.

### #2 Popup Melayang (PiP)
- Memakai API **Document Picture-in-Picture** (Chrome/Edge 116+).
- Harus diakses lewat **HTTPS** (atau `http://localhost` saat dev local).
- Saat popup aktif, tab utama tidak di-throttle browser sehingga `setInterval`,
  `watchPosition`, dan WakeLock tetap jalan → GPS tidak putus.

### #3 Anti garis lurus saat layar mati
- Polyline dipecah jadi **multi-segment**. Saat gap > 25 detik ATAU lompatan > 150 m,
  segmen baru dimulai dan jarak TIDAK ditambahkan.
- Tidak perlu perubahan database.

### #4 Flyover
- Bug: dulu memanggil `/api_run.php?session_id=…` (tidak ada).
- Sekarang panggil `/api_run.php?route=…` (sudah ada di server).

### #5 Monitoring
- Memakai kolom `durasi_menit`, `jarak_km`, `pace_detik` yang sudah ada di `upload_harian`.
- Tidak perlu perubahan database.

### #6 Cek Sistem
- Tidak perlu tabel baru.
- ImageKit usage diambil via `GET https://api.imagekit.io/v1/accounts/usage` (Basic auth privateKey).
- Render tidak punya API publik bandwidth tanpa OAuth — halaman menampilkan link ke dashboard
  dan estimasi log akses (bila ada).

### #7 Hapus menu Riwayat Login Member
- Hanya menghapus link UI; file `admin/login_logs.php` tidak dihapus agar tabel
  `login_logs` tetap aman. Bisa Anda hapus manual bila tidak diperlukan.

### #8 Kalori Mingguan
- Tambah parameter URL `?week=-1`, `?week=-2`, dst untuk melihat minggu lalu.
- Tidak perlu perubahan database.

### #9 Halaman Islami baru
- 4 halaman PHP statis baru, hanya membaca `includes/shalat_data.php` (sudah ada).
- Tidak perlu perubahan database.

## Yang TIDAK perlu ditambahkan ke PostgreSQL

Semua revisi di paket ini **tidak membutuhkan migrasi DB baru**. Skema yang sudah ada
(`run_sessions`, `run_points`, `run_routes`, `upload_harian`, `kalori_makanan_log`,
`kalori_target`, `flyover_renders`) sudah cukup.

## Yang belum termasuk

Tidak ada — semua 9 item revisi sudah ditangani dalam paket ini.

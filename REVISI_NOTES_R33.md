# Revisi R33 — Tracking Strava-style (KawanKeringat)

## File yang direvisi
1. **run.php** — Section Tracking direvisi total ala Strava.
   Section "Eksplorasi Rute & Peta Canggih" TIDAK diubah (dibiarkan apa adanya).
2. **api_run.php** — Ditambah dukungan export **GeoJSON** (`?export=<id>&fmt=geojson`).

## Ringkasan Fitur Baru pada `run.php`
- UI modern ala Strava: card `border-radius:18px`, shadow lembut, tombol besar,
  angka Distance / Time / Pace besar.
- Map di dalam wrapper `border-radius:18px`, tinggi `min(58vh, 560px)`.
- **Floating overlay** di atas map: Distance, Time, Pace, Speed, Avg Pace, Elevation.
- **GPS Chip** melayang: 🟢 Sangat Akurat / 🟢 Baik / 🟡 Sedang / 🔴 Buruk /
  🔴 GPS Hilang / 🟡 Menunggu GPS.
- **Marker realtime** — bergerak otomatis via `navigator.geolocation.watchPosition`
  dengan `enableHighAccuracy:true, maximumAge:0, timeout:10000`.
- Map auto-follow user. Jika user geser peta manual, muncul tombol
  **"Kembali ke Posisi Saya"** (auto-follow di-nonaktifkan).
- **Filter GPS**: acc>30 m diabaikan, perpindahan <3 m diabaikan (drift),
  kecepatan >12 m/s (≈43 km/h) diabaikan, jarak >150 m / gap >25 s → segmen baru
  (anti garis lurus setelah layar mati).
- **Smoothing** kecepatan (EMA 0.7/0.3) + smoothing elevasi.
- **Pace**: Moving Average dari ~30 titik terakhir + Avg Pace total sesi
  (format `menit'detik"`, per km).
- **Split KM** otomatis dengan bar visual.
- **Current Speed** dan **Avg Speed** (km/h).
- **Elevation** dari `pos.coords.altitude` (kalau tersedia).
- **Kalori MET**: `MET × berat_kg × jam`. MET otomatis dari jenis olahraga
  (Lari 9.8 / Jogging 7 / Jalan 3.5 / Sepeda 8) dan disesuaikan dengan kecepatan.
  User bisa mengubah berat & jenis olahraga di panel Setting.
- **Auto-Pause** kalau diam >10 detik, **Auto-Resume** saat mulai bergerak lagi.
- **Auto-Reconnect GPS**: waktu GPS hilang → status "🔴 GPS Hilang", saat kembali
  polyline lanjut sebagai segmen baru (tidak menyambung garis lurus).
- **Battery-friendly adaptive interval**: throttle titik disimpan berdasarkan
  kecepatan (diam 5 dtk, jalan 2 dtk, lari 1 dtk).
- **Background tracking** untuk APK Capacitor via
  `@capacitor-community/background-geolocation` (dipanggil kalau
  `window.Capacitor.isNativePlatform()` true). Jika browser biasa → banner
  peringatan tampil dan pakai Wake Lock + `visibilitychange` refresh.
- **Auto-resume sesi** aktif setelah reload / balik dari background.
- **Export**: GPX, KML, dan **GeoJSON** (baru).
- Simpan data lengkap: koordinat + accuracy + speed di server (via
  `/api_run.php` action `point`), plus split & durasi di client localStorage.

## Perubahan Database (opsional, tidak wajib)
Tracking tetap berjalan pada schema `run_sessions` / `run_points` yang sudah ada.
Kolom tambahan ini **opsional** — dipakai kalau ingin menyimpan elevasi ke DB:

```sql
-- Opsional: simpan elevasi per titik
ALTER TABLE run_points ADD COLUMN IF NOT EXISTS elev_m DOUBLE PRECISION;
```

Kalau kolom ini ditambahkan, silakan sesuaikan juga `api_run.php` action `point`
untuk menerima parameter `elev` (belum diaktifkan di R33 supaya kompatibel
mundur). Tanpa migrasi ini, elevasi tetap tampil realtime di UI (dari GPS)
namun tidak tersimpan di DB.

## Tambahan APK (Capacitor)
Agar background tracking berjalan saat layar HP mati, install plugin di project APK:

```bash
npm i @capacitor-community/background-geolocation
npx cap sync
```

Tambahkan permission `ACCESS_BACKGROUND_LOCATION` dan `FOREGROUND_SERVICE` di
`AndroidManifest.xml`. Plugin akan otomatis membuat Foreground Notification.
Kode di `run.php` sudah memanggil `BackgroundGeolocation.addWatcher(...)` kalau
plugin tersedia.

## Yang TIDAK diubah
- Section "Eksplorasi Rute & Peta Canggih" (Route Builder, AI Route, Heatmap,
  Flyover, dsb.) — persis seperti aslinya.
- Skema database (kecuali kolom opsional di atas).
- Halaman lain di luar `run.php` & `api_run.php`.

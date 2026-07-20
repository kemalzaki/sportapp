# CATATAN REVISI R51 — Activity Detail (Strava-style)

## File yang berubah
- `activity_detail.php` — **redesign TOTAL** menjadi halaman analisis aktivitas.

## Yang TIDAK diubah
- `tracking.js`, `gps.js`, `save.js` (Local First storage)
- `api_run.php`, `run.php`
- Skema database
- ID/CLASS utama, endpoint lain (riwayat, upload, dsb.)

## Fitur baru
1. Header dengan tombol Back, Share, Download GPX.
2. Peta Leaflet:
   - Start (hijau), Finish (merah), auto-fit bounds
   - Fullscreen map
   - Download GPX (`api_run.php?action=export_gpx&session_id=...`)
3. Statistik ringkas: Jarak, Durasi Total & Bergerak, Pace Rata-rata & Terbaik, Kecepatan Rata-rata & Maks, Kalori, Elevasi (naik/turun/maks), Total titik GPS.
4. **Split per Kilometer** ala Strava (progress bar proporsional; km tercepat = hijau, terlambat = oranye).
5. Grafik Pace (Chart.js) dengan garis pace rata-rata.
6. Grafik Elevasi (area chart) — kalau altitude tidak tersedia → tampil "Tidak tersedia" (tidak membuat angka palsu).
7. Grafik Kecepatan vs Waktu.
8. Grafik Akurasi GPS vs Waktu.
9. Insight otomatis: KM tercepat, KM terlambat, Pace paling stabil, kecepatan maks, total waktu berhenti, Estimasi VO₂ Max (opsional), Zona intensitas (opsional).
10. Tema KawanKeringat: dark navy + electric blue + glass card, rounded 18px, responsive Android.

## Kompatibilitas
- Query masih memakai kolom yang sudah ada: `run_points.lat, lng, ts, speed_mps, accuracy_m`.
- Cek `information_schema` otomatis: **jika** kolom `altitude_m` sudah ditambahkan di `run_points`, halaman langsung memakainya. Kalau belum, elevasi tampil "Tidak tersedia".

## PostgreSQL — OPSIONAL (tidak wajib)
Kalau nanti mau menyimpan elevasi dari GPS, tambahkan:

```sql
ALTER TABLE run_points ADD COLUMN IF NOT EXISTS altitude_m DOUBLE PRECISION NULL;
```

Tanpa perintah ini, semua fitur lain tetap berjalan normal — hanya bagian
elevasi yang menampilkan "Tidak tersedia" (sesuai permintaan).

## Cache-buster
Tidak ada — `activity_detail.php` dimuat langsung sebagai halaman, dan
resource eksternal (Leaflet, Chart.js) di-CDN dengan versi yang dipin.

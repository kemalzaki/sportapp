# Patch Revisi 16 Juni 2026 — SportApp Core

Salin semua file di arsip ini menimpa file dengan nama yang sama di proyek
`sportapp_core/`. Tidak ada migrasi PostgreSQL baru yang diperlukan — semua
revisi memakai tabel yang sudah ada.

## File dalam arsip

| File | Untuk revisi nomor |
|---|---|
| `includes/ai_gemini.php` (BARU) | helper Gemini 2.5 Flash dipakai semua revisi AI |
| `api_ai.php` (BARU) | endpoint AI umum (coach, tanya islami, safety, prompt rute) |
| `includes/security.php` | CSP ditambah api.mapbox.com & generativelanguage.googleapis.com |
| `live_tracking.php` | #1 detail popup melayang + musik + ikon + #3 peta Mapbox + #9 AI Safety Monitoring |
| `monitoring.php` | #2 perbaikan sinkronisasi pace + #3 peta (tidak ada peta di file ini — lihat catatan) + #6 AI Running Coach |
| `run.php` | #3 peta Mapbox + #7 AI Route dari prompt teks (`Buatkan rute lari 5 km…`) |
| `api_run.php` | #3 (tidak ada peta) + #4 implementasi AI foto diganti Gemini Vision (Screenshot peta → Gemini → landmark → Geocoding OSM → koordinat rute) |
| `flyover.php` | #3 peta Mapbox (style baru: Mapbox Outdoors & Satellite Streets) |
| `kalori_mingguan.php` | #5 `ai_estimate_kalori()` diganti Gemini Vision (Foto makanan → AI mengenali → estimasi kalori → masuk DB) |
| `islami.php` | #8 Tanya Jawab Islami dengan Gemini |

## Catatan revisi

- **#2 Pace sinkron**: `pacePoints` di `monitoring.php` sekarang hanya
  menghitung aktivitas berjenis "lari/jogging/run" dengan jarak ≥ 1 km dan
  membuang outlier pace di luar 2'–15'/km. Hasilnya konsisten dengan kartu
  "Statistik Tren Performa Jogging" dan rumus VO2.
- **#3 Mapbox**: Token publik sudah di-hardcode (`pk.eyJ1IjoiYWRhbXNhc21pdGE...`).
  Bisa di-override lewat env `MAPBOX_TOKEN`. File yang punya peta:
  `run.php`, `live_tracking.php`, `flyover.php`. `api_run.php` dan
  `monitoring.php` **tidak punya komponen peta** — fitur "Rute paling sering /
  Area pace turun / Area tanjakan" yang Anda sebut belum saya tambahkan
  sebagai komponen baru di sini (lihat bagian "Belum termasuk" di bawah).
- **#4 / #5 / #6 / #7 / #8 / #9 AI**: Semua memanggil Gemini 2.5 Flash via
  helper `includes/ai_gemini.php`. Key Gemini default sudah di-hardcode
  (`AQ.Ab8RN6Ih3agk9Ci-i4ChaLerRnJOK-OpOFF4qgL8y99ZZEC5kQ`) — bisa
  di-override lewat env `GEMINI_API_KEY`.
- **AI Safety Monitoring** (#9): browser mengirim ringkasan kondisi (kecepatan
  terakhir, durasi idle, jarak dari titik awal, jumlah penurunan kecepatan
  drastis 10 menit terakhir) setiap 60 detik. Gemini menentukan level
  `aman` / `waspada` / `darurat` lalu menampilkan banner & getaran di HP.

## Apa yang TIDAK perlu ditambahkan ke PostgreSQL

Tidak ada migrasi baru. Tabel yang dipakai semuanya sudah ada:
`run_sessions`, `run_points`, `run_routes`, `upload_harian`, `kalori_target`,
`kalori_makanan_log`, `live_tracking_sessions`, `live_tracking_contacts`,
`flyover_renders`.

## Belum termasuk dalam arsip ini

- Halaman analitik baru "Rute paling sering digunakan / Area membuat pace
  turun / Area tanjakan" sebagai komponen peta tersendiri di `monitoring.php`.
  Saya fokus dulu pada sinkronisasi pace + AI Coach sesuai item #2 & #6.
  Komponen analitik peta tsb bisa dibuat menyusul sebagai halaman baru
  (mis. `monitoring_peta.php`) yang membaca `run_points` lalu mengelompokkan
  per cell geohash. Beritahu jika ingin saya lanjutkan.


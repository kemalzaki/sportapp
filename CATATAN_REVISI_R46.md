# REVISI R46 (UI Polish) — KawanKeringat

Ruang lingkup: hanya UI/UX. Tidak menyentuh business logic
tracking, GPS, upload, save, riwayat, screenshot, fullscreen,
pause, stop, review, maupun schema database.

## File yang diubah
1. `assets/css/safe-area.css`
   - Tambah blok "REVISI R46" (di akhir file) berupa fallback
     `html.is-native` untuk `.offcanvas.offcanvas-start/-end/-top`
     dan `.gt-drawer` supaya drawer TIDAK menabrak status bar
     Android WebView (yang mengembalikan env(safe-area-inset-top)=0).
   - Sama pola dengan fallback header (.gt-top). Tidak ada margin manual.

2. `riwayat.php`
   - Tombol "Lihat Rute" (mini-map) diubah dari `<button class="kk-route-expand">`
     menjadi `<a href="/activity_detail.php?id=…">` sehingga membuka halaman
     detail READ-ONLY, bukan modal & bukan live_tracking.php.
   - Mini-map (Leaflet interaktif read-only) TIDAK diubah — sudah berjalan
     dari R43/R45.

3. `run.php`
   - Rename label tampilan "Tracking Jalur" → "Rekam Jogging" pada:
     `$pageTitle`, judul lock-page, dan heading dashboard.
   - Route, nama file, dan endpoint TIDAK diubah.

4. `explore.php`
   - Tombol "Ke Tracking Jalur" → "Ke Rekam Jogging".

5. `activity_detail.php` (BARU)
   - Halaman READ-ONLY untuk detail aktivitas (seperti detail Strava).
   - Query hanya SELECT dari `upload_harian`, `users`, `run_points`
     (tabel yang SUDAH ADA — tidak ada CREATE/ALTER baru).
   - Tidak memakai token tracking, tidak membutuhkan sesi live tracking.
   - Leaflet interaktif (drag/pinch/scroll/double-tap zoom) tetapi
     tidak dapat mengedit rute.

## Constraint yang DIHORMATI (tidak diubah)
- `assets/js/tracking.js`, `gps.js`, `save.js`, `background.js`, `voice.js`
- `api_run.php`, `api_live_tracking.php`
- Struktur database (tidak ada migration baru)
- Logika upload, screenshot, fullscreen, pause/stop, review

## PostgreSQL
Tidak perlu tabel/kolom baru. Halaman `activity_detail.php` memakai
`upload_harian.gpx_session_id` dan `run_points(session_id, lat, lng)`
yang sudah dipakai oleh mini-map di riwayat.php (R43).

## Cara pasang
Ekstrak isi ZIP di root project (menimpa file lama, dan menambah
`activity_detail.php`). Tidak perlu perintah migrasi apapun.

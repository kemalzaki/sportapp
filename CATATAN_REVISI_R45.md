# Revisi R45 — Juli 2026

Melanjutkan R43/R44. **Tidak** mengubah tracking.js, gps.js, save.js,
background.js, voice.js, struktur DB, endpoint, ID/class utama, atau
business logic.

## Isi arsip
- `includes/header.php` — hanya label menu: "Tracking Jalur" → "Rekam Jogging"
  di 3 titik (chip navbar atas, drawer kiri, top navbar desktop).
  Link `/run.php` TIDAK diubah.
- `assets/css/safe-area.css` — tambahan aturan `.offcanvas.*` &
  `.gt-drawer` supaya header drawer kiri tidak menabrak status bar
  Android (memakai `env(safe-area-inset-top)`).
- `assets/js/kk-mini-map.js` — mini map inline jadi **interaktif**
  (drag / pinch / zoom control) seperti Strava, tetap READ-ONLY.
  Klik area peta **tidak lagi** redirect ke `/track_view.php`.
  Tombol "Lihat Rute" sekarang membuka modal Leaflet besar
  (tidak butuh token, tidak error "Token tidak valid").
- `riwayat.php` — hanya tombol "Lihat Rute" diganti dari
  `<a href="/track_view.php?sid=…">` menjadi
  `<button class="kk-route-expand" data-sid="…">`. Tidak menyentuh
  query PostgreSQL, RLS, `run_points`, atau data lain.

## Cara pasang
Timpa file dengan struktur folder yang sama pada project asli.
Tidak ada migrasi SQL, tidak ada perubahan skema/data PostgreSQL.

## Regresi
Tracking, GPS, Pause, Resume, Stop, Save, Upload, Screenshot,
Fullscreen, Riwayat, Split, GPX, KML, GeoJSON, Bottom Nav global,
Safe-area lain — tidak disentuh.

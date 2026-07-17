# Revisi R48 — Juli 2026

Hanya berisi file yang direvisi. Copy/timpa ke folder project sesuai path.

## Perubahan

1. **includes/header.php** — Safe area untuk drawer navigasi.
   - Menu paling atas ("Jogging Progress") tidak lagi tenggelam di balik
     status bar Android/iOS (Capacitor/WebView).
   - Menambah `padding-top: calc(env(safe-area-inset-top,0) + 12px)`
     pada `.gt-drawer .offcanvas-header` dan `padding-bottom` safe-area
     pada `.gt-drawer .offcanvas-body`.
   - Tidak mengubah ID / class utama / event / struktur menu.

2. **assets/js/kk-mini-map.js** + **riwayat.php** — Mini map di riwayat
   dibuat NON-INTERAKTIF (static preview).
   - Peta tidak bisa diklik / didrag / zoom, sehingga tidak ada risiko
     ter-redirect ke `live_tracking.php` / `track_view.php`.
   - Leaflet options: `dragging/touchZoom/doubleClickZoom/zoomControl/tap = false`.
   - Container mini map diberi `pointer-events: none`.
   - Tombol **"Lihat Rute"** tetap berfungsi (buka modal Leaflet interaktif).
   - riwayat.php hanya dinaikkan cache-buster JS ke `?v=r48`.

## Catatan Database

Tidak ada perubahan skema PostgreSQL. Tidak perlu menjalankan SQL
tambahan. `sportapp.sql` di zip induk tidak berubah dan datanya tetap.

## Regresi

Tracking, GPS, upload, save, riwayat (list + kalender + modal bukti),
screenshot, fullscreen, pause, stop, review — tidak disentuh, tetap
bekerja seperti semula.

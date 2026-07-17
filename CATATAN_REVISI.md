# Revisi run.php — Fix Stop + UI Flat Premium

## File yang direvisi
- `run.php` — hanya CSS/tokens (shadow tipis, tombol proporsional, fab peta 48px).
- `assets/js/run/ui.js` — fix bug **Stop tidak berfungsi** di Dashboard Mode.

## Yang TIDAK berubah
- Semua ID, class, selector, dan nama fungsi.
- `gps.js`, `tracking.js`, `map.js`, `save.js`, `background.js`, `voice.js`.
- Endpoint `upload.php`, `api_run.php`, format POST, proses save, screenshot, GPX/KML/GeoJSON.
- Urutan panel: Statistik → Map → Tombol → Split → Riwayat.
- Focus Mode tetap ada; Dashboard tetap default; Focus aktif hanya via tombol ⛶.

## Root cause bug Stop
Tombol `#kk-dash-btn-stop` (Dashboard) memanggil `#kk-btn-stop.click()` yang
menjalankan `KKUI.showSwipeFinish(stopSession)`. Swipe UI berada di `.kk-ctrl`
yang `display:none` selama Dashboard Mode, jadi user tidak pernah melihat
swipe → `stopSession()` tidak pernah dipanggil → save & upload tidak jalan.

## Fix
Di `ui.js`, `showSwipeFinish` sekarang mendeteksi mode:
- **Focus Mode**: perilaku lama (swipe-to-finish).
- **Dashboard Mode**: `confirm()` "Selesaikan sesi tracking…" → langsung
  memanggil `stopSession` → `KKSave.flush` + `KKSave.stopSession` → summary
  → tombol Review & Upload ke `/upload.php`.

Perubahan minimal, tidak menyentuh logic tracking/GPS/timer/polyline.

## Catatan PostgreSQL
Tidak ada tabel baru. `run_sessions` dan `run_routes` sudah ada di
`sportapp.sql` / dibuat otomatis oleh `run.php`. Tidak perlu migrasi tambahan.

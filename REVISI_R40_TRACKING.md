# REVISI R40 — Refactor Bersih Halaman Tracking Jalur (run.php)

Arsip ini berisi HANYA file yang direvisi (bukan seluruh project).
Timpa ke root project KawanKeringat.

## File yang direvisi
```
run.php                        ← refactor total (HTML/CSS/JS bersih)
assets/js/run/ui.js            ← UI helper + Focus/Dashboard toggle
assets/js/run/tracking.js      ← orkestrator + wiring tombol SATU set
assets/js/run/map.js           ← Leaflet + safety-net anti Leaflet-tap
```

## File yang TIDAK diubah
- `assets/js/run/gps.js`
- `assets/js/run/save.js`
- `assets/js/run/background.js`
- `assets/js/run/voice.js`
- `api_run.php`, `upload.php`, `riwayat.php`
- `service-worker.js`, `includes/*`

## PostgreSQL
Tidak ada perubahan schema. Tabel `run_sessions`, `run_points`,
`run_routes` tetap dipakai apa adanya. Endpoint `api_run.php`
(`_action=start/point/stop/delete`) tidak berubah. Tidak ada
migrasi baru yang perlu dijalankan. Data `sportapp.sql` yang sudah
ada aman — tidak ada yang dihapus.

## Prinsip refactor (bukan patch)
1. **SATU source of truth** untuk tombol tracking.
   Hanya ada `#kk-btn-start`, `#kk-btn-pause`, `#kk-btn-resume`,
   `#kk-btn-stop`, `#kk-btn-mylocation`. Kedua mode (Dashboard/
   Focus) memakai tombol yang SAMA — Focus Mode hanya menggeser
   card kontrol ke posisi mengambang di bawah lewat CSS.
   Tidak ada hidden button, tidak ada `dispatchEvent()`, tidak
   ada `safeClickHidden()`, tidak ada `t.click()` berantai.
2. **Tidak ada override function existing.**
   `window.KKUI.enterFullscreen = function(){}` dan sejenisnya
   sudah dihapus. Perilaku diubah dengan tidak memanggil fungsi
   fullscreen otomatis dari `tracking.js`, bukan dengan me-no-op
   fungsinya.
3. **Leaflet tidak pernah di-destroy / recreate.**
   Perpindahan Dashboard ↔ Focus hanya toggle class di `<body>`.
   `KKMap.invalidate()` dipanggil setelah CSS transisi selesai
   agar Leaflet re-measure size — polyline, marker, GPS, compass,
   dan segmen tetap pakai instance yang sama.
4. **HTML valid.**
   Body ganda (`<body>...</body>` di tengah halaman) dihilangkan.
   Tidak ada duplicate id. Semua tombol memakai
   `type="button"` supaya tidak submit form induk.
5. **Fix akar masalah "tombol map tidak bisa diklik".**
   - Floating fabs (`#kk-fab-location`, `-compass`, `-fullscreen`,
     `-settings`) berada di dalam `.kk-mapwrap` sebagai SIBLING
     `#kk-map`, bukan child dari `.leaflet-container`. Artinya
     Leaflet secara arsitektur tidak menangkap eventnya.
   - `.kk-mapfabs`, `.kk-chips`, `.kk-settings-pop`, `.kk-recenter`
     memiliki `z-index` di atas Leaflet controls (1000) dan
     memakai `pointer-events:auto`.
   - `map.js` memasang `L.DomEvent.disableClickPropagation()` +
     `disableScrollPropagation()` pada elemen-elemen di atas
     sebagai SAFETY NET (bukan andalan utama) untuk kasus browser
     tertentu yang men-bubble touch ke `.leaflet-container`.
   - Zoom control default Leaflet disembunyikan lewat CSS
     (`display:none`) supaya tidak menabrak fab kanan-atas.
6. **Focus Mode: statistik = floating glass card kecil di atas.**
   Card berukuran seperlunya (`max-width:520px`, tinggi otomatis),
   `pointer-events:none` di container, `pointer-events:auto` hanya
   di kartu — tidak menutupi tombol map, tidak menghalangi klik,
   tidak memenuhi seluruh layar.
7. **Stop tracking selalu jalan.**
   Klik `#kk-btn-stop` → `KKUI.confirmStop()` (confirm sederhana,
   bukan swipe) → `stopSession()` di `tracking.js`:
   `KKSave.flush()` → `KKSave.stopSession()` (POST `/api_run.php
   ?_action=stop`) → `KKSave.clear()` → `KKFinish.open()`
   → user klik "Review & Upload" → `/upload.php`.
   Setelah Stop, `state.sessionId` di-null-kan sehingga tombol
   Start otomatis muncul kembali (via `refreshButtons()`).
8. **CSS bersih, gaya KawanKeringat.**
   - Border-radius 20px pada card
   - Shadow ringan (`0 2px 8px` / `0 6px 18px`)
   - Glass effect (backdrop-filter) hanya pada chip peta, popover
     floating focus stats, dan tombol exit — bukan di seluruh UI.

## Dashboard Mode — urutan layout (tidak berubah)
```
Statistik  →  Map  →  Control Tracking  →  Split per KM  →  Riwayat
```

## Cara pasang
1. Ekstrak zip.
2. Timpa ke root project KawanKeringat (path relatif sudah benar).
3. Reload `run.php` dengan Ctrl+F5 supaya cache CSS/JS lama bersih.

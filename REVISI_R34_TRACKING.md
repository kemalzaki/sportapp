# Revisi R34 — Tracking Jalur Strava/Garmin-Style

Isi arsip ini adalah **file yang direvisi saja** (bukan seluruh project):

```
run.php                       ← halaman Tracking Jalur (ditulis ulang total)
assets/js/run/tracking.js     ← orkestrator utama
assets/js/run/gps.js          ← Geolocation + heading + filter GPS
assets/js/run/map.js          ← Leaflet + rotasi map + auto-follow
assets/js/run/ui.js           ← fullscreen mode, floating metrics, lock, swipe, finish
assets/js/run/background.js   ← WakeLock, Capacitor BG geoloc, notif, bubble
assets/js/run/voice.js        ← Text-to-Speech (Web Speech API)
assets/js/run/save.js         ← persistence + sync ke /api_run.php
```

## Cara pasang

1. Ekstrak zip ini dan **timpa** ke root project (folder tempat `run.php` lama berada).
   Folder `assets/js/run/` akan dibuat otomatis.
2. `api_run.php`, `upload.php`, dan tabel `run_sessions` / `run_points` **tidak
   berubah** — endpoint `_action=start / point / stop / delete` tetap dipakai
   apa adanya. **Tidak ada migrasi SQL yang perlu dijalankan.**
3. Buka `/run.php` di browser atau di APK Capacitor.

## Fitur yang tercakup (nomor 1 – 20 dari brief)

| # | Fitur                          | Implementasi                                       |
|---|--------------------------------|----------------------------------------------------|
| 1 | Mode Tracking Fullscreen       | `body.kk-tracking-fullscreen` menyembunyikan header, nav bottom, sidebar, search, footer, dan shell pra-tracking. Status bar Android tetap tampil (tidak masuk immersive) |
| 2 | Peta fullscreen + auto-follow  | `#kk-map` mengisi seluruh viewport, `map.panTo()` mengikuti setiap fix |
| 3 | Rotasi map                     | `.leaflet-map-pane` di-`transform: rotate(-heading)`; heading diambil dari `pos.coords.heading` atau fallback `bearing(prev,now)`, dan `deviceorientation` saat kecepatan rendah |
| 4 | Auto follow + tombol Recenter  | `map.on('dragstart')` mematikan follow, tombol `#kk-recenter` muncul |
| 5 | Floating metrics blur          | `.kk-metric-primary` + `.kk-metric-cell` dgn `backdrop-filter: blur()` |
| 6 | Floating control               | FAB bulat `.kk-fab` (pause / resume / stop / lock / mute) |
| 7 | Swipe-to-Stop / Hold 2 dtk     | `KKUI.showSwipeFinish()` + `touchstart`+`setTimeout(2000)` |
| 8 | Lock Screen                    | Overlay `.kk-lock` + slide-to-unlock |
| 9 | Auto Dim                       | `.kk-dim` overlay 38% opacity setelah 45 dtk tak ada sentuhan; GPS tetap jalan |
| 10 | Keep Screen On                | `navigator.wakeLock.request('screen')` + `KeepAwake.keepAwake()` Capacitor |
| 11 | Background tracking           | `@capacitor-community/background-geolocation` (di APK) |
| 12 | Floating tracking bubble      | Plugin `FloatingOverlay` / `SystemAlertWindow` (kalau tersedia di APK). Silent no-op di browser |
| 13 | Notification tracking         | `@capacitor/local-notifications` dengan `ongoing:true`, update tiap 3 dtk |
| 14 | Voice feedback                | Web Speech API `SpeechSynthesisUtterance` bahasa `id-ID`, interval 500 m / 1 km / off |
| 15 | Auto Pause                    | Kalau diam >20 detik → `state.autoPaused` on; resume otomatis saat gerak |
| 16 | Fullscreen finish             | `#kk-finish` menampilkan peta besar + summary + split + grafik pace/speed/elev + tombol **Review & Upload** → `/upload.php` |
| 17 | Animasi smooth                | Semua transisi CSS 60 fps, tidak ada reload page selama sesi |
| 18 | Optimasi APK Capacitor        | KeepAwake, foreground service, background geoloc, offline cache via existing service worker |
| 19 | Kode modular JS               | Sudah dipecah menjadi 7 modul di `assets/js/run/` |
| 20 | Target UX premium             | Peta fullscreen + floating card + rotation + haptic-like swipe → mirip Strava/Garmin |

## PostgreSQL — apakah perlu tambahan?

**Tidak.** Skema `run_sessions` dan `run_points` yang sudah ada di
`sportapp.sql` sudah cukup. Endpoint `api_run.php` tetap dipakai apa adanya:

- `_action=start` → buat sesi baru
- `_action=point` (dipanggil buffered tiap 5 dtk) → simpan koordinat
- `_action=stop`  → tandai selesai + isi otomatis `upload_harian`
- `_action=delete` → hapus riwayat

Semua field opsional (`accuracy_m`, `speed_mps`) yang dikirim modul baru
sudah didukung skema lama.

## Catatan untuk build APK Capacitor

Agar fitur 11–13 aktif, install plugin berikut lalu sync:

```bash
npm i @capacitor-community/background-geolocation
npm i @capacitor/local-notifications
npm i @capacitor-community/keep-awake
# (opsional) floating bubble style Google Maps:
npm i capacitor-floating-overlay
npx cap sync android
```

Tambahkan permission di `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION"/>
<uses-permission android:name="android.permission.FOREGROUND_SERVICE"/>
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION"/>
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
<uses-permission android:name="android.permission.WAKE_LOCK"/>
<uses-permission android:name="android.permission.SYSTEM_ALERT_WINDOW"/>
```

Kalau di web browser, fitur background & bubble akan mati otomatis
(warning kuning tampil di shell pra-tracking).

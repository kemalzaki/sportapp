# Revisi R50 — Fix GPX terpotong (histori titik GPS hilang)

## Masalah
`tracking.js` membuang mayoritas titik GPS ke `state.points` karena filter render
Leaflet dipakai sebagai filter histori. Akibatnya jogging 41 menit hanya
menghasilkan ~70 titik.

## Perbaikan (hanya `assets/js/run/tracking.js`)
- Hapus `return` di cabang throttle `adaptiveMinInterval + d<30`. Titik tetap
  di-push ke `state.points`; throttle hanya menentukan apakah polyline di
  Leaflet di-redraw penuh atau cukup update marker.
- Hapus `return` di cabang `d < 3`. Titik tetap disimpan; hanya map yang tidak
  redraw polyline. Auto-pause tetap terdeteksi.
- Filter akurasi dilonggarkan `>30` → `>50` m (jogging outdoor sering 30–40 m).
- Filter glitch kecepatan `>12 m/s` dipindah ke atas (dicek lebih dulu).
- Penambahan `state.totalM += d` dipindah agar dijalankan untuk semua titik
  valid (bukan hanya cabang "bergerak").
- Log verifikasi di `stopSession()`:
  `console.log('[KK] Total state.points =', state.points.length, ...)`.

## Tidak diubah
- Background Geolocation, Wake Lock, Local-first storage (IndexedDB), upload
  sekali saat Stop, GPX, UI, statistik live, pause/resume/stop, screenshot,
  fullscreen, tombol, ID HTML, class, event, endpoint, skema DB.

## Cache-buster
- `run.php`: `tracking.js?v=r49` → `v=r50`. Skrip lain tetap `v=r49`.

## PostgreSQL
Tidak ada perubahan skema / data.

## Verifikasi
Jogging 40 menit → `state.points.length` di console harus ~800–1500,
`run_points` server juga terisi lengkap, GPX tidak terpotong.

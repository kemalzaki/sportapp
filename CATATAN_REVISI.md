# Revisi run.php — Fullscreen jadi tombol

## Perubahan
- Peta tracking **tidak lagi otomatis fullscreen** saat menekan "MULAI TRACKING".
- `#kk-track-root` sekarang tampil **inline** (tinggi 70vh, rounded, di dalam halaman) begitu rekaman dimulai.
- Ditambahkan tombol **Fullscreen** (`#kk-btn-fullscreen`, ikon `bi-arrows-fullscreen`) di pojok kanan atas peta. Klik untuk masuk / keluar mode layar penuh (menyembunyikan header/nav sesuai CSS `body.kk-tracking-fullscreen`).
- Override `KKUI.enterFullscreen()` / `KKUI.exitFullscreen()` di dalam `run.php` sehingga TIDAK perlu mengubah `assets/js/run/ui.js` maupun `tracking.js`.
- Saat toggle fullscreen, `KKMap.invalidateSize()` dipanggil agar Leaflet menyesuaikan ukuran peta.

## Yang TIDAK diubah
- Semua logika tracking, GPS, timer, polyline, auto-pause, voice, wake-lock, background, save, upload — **tetap sama**.
- ID/kelas yang dipakai modul JS (`kk-btn-start`, `kk-btn-pause`, `kk-btn-stop`, `kk-map`, metrik `m-*`, dll) tetap.
- Struktur countdown, lock screen, finish screen tetap.

## Cara pakai
Ganti file `run.php` di root project. Tidak ada perubahan schema PostgreSQL yang diperlukan.

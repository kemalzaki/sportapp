# Revisi run.php — R37

Zip ini berisi HANYA file yang direvisi. File lain di project asli
tidak perlu diubah — cukup timpa `run.php` di root.

## Perubahan (3 poin)

### 1. Tombol "Selesai" hanya muncul setelah "Mulai" ditekan
- Tombol `#kk-dash-btn-stop` sekarang **default `display:none`**.
- Fungsi `sync()` di run.php ikut mengatur tombol Selesai:
  muncul hanya bila `KKTracking.state.sessionId` sudah aktif
  (sama seperti tombol Jeda/Lanjut).

### 2. Tombol di peta (Fullscreen, Compass, Lokasi, Settings) tidak lagi
   tertimpa peta / kontrol Leaflet saat "Mulai" ditekan
- Kontrol default Leaflet (zoom control top-right, dsb.) punya
  z-index 1000 dan menabrak `.kk-mapfabs` (z-index 7) sehingga
  tombol Fullscreen dll. terlihat "hilang" saat map di-reset di awal sesi.
- Perbaikan:
  - `.kk-chips` z-index 1200, `.kk-mapfabs` 1201,
    `.kk-settings-pop` 1202, `.kk-recenter` 1200 (dengan `!important`).
  - `#kk-map .leaflet-control-zoom` dan `.leaflet-top.leaflet-right`
    di-`display:none` supaya tidak menabrak FAB kanan atas.
- Semua tombol tetap dapat diklik & memicu handler existing (tidak
  mengubah `map.js` / `ui.js` / `tracking.js`).

### 3. Peringatan error yang ramah (bukan halaman error mentah browser)
- Ditambahkan overlay modal `#kk-err-overlay` bergaya KawanKeringat
  (ikon segitiga oranye-merah, judul, pesan, tombol **Tutup**
  dan **Coba Lagi**).
- Global handler `window.KKError.show(pesan, onRetry)` tersedia untuk
  modul lain.
- Otomatis muncul saat:
  - Perangkat `offline` (event `offline`).
  - `fetch()` gagal jaringan atau server balas ≥500.
  - Klik link internal penting (`upload.php`, `riwayat.php`,
    `api_run.php`) tapi endpoint tidak reachable — mencegah munculnya
    layar bawaan browser seperti `net::ERR_CACHE_MISS` /
    "Halaman web tidak tersedia".
- Fetch asli disimpan sebagai `_origFetch` — probe HEAD ke link
  internal TIDAK memicu overlay ganda.

## Yang TIDAK diubah
- Logika tracking / GPS / timer / polyline / voice / wake-lock /
  background / save / upload — tetap.
- Modul JS (`assets/js/run/*.js`) TIDAK diubah.
- ID/kelas yang dipakai modul JS (`kk-btn-start`, `kk-btn-pause`,
  `kk-btn-stop`, `kk-map`, metrik `m-*`, dll) tetap.

## PostgreSQL
Tidak ada perubahan schema. **Tidak perlu** menambah tabel / kolom /
migrasi baru. Data existing pada `run_sessions`, `run_routes`, dsb.
tetap kompatibel. File `sportapp.sql` yang sudah ada di project asli
sudah cukup.

## Cara pakai
1. Backup `run.php` lama (opsional).
2. Timpa `run.php` di root project dengan file dari zip ini.
3. Reload halaman `run.php` di browser (Ctrl+F5 supaya cache CSS/JS bersih).

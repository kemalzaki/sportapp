# Revisi R52 — Save Reliability (Chunked Upload + Pending Queue)

## Ringkasan Masalah
Aktivitas 5 km / 30–40 menit (≈800–1500 titik GPS) sering **gagal tersimpan**
saat tombol Save ditekan.

## Akar Masalah
1. Seluruh titik GPS dikirim dalam **SATU request** raksasa (`upload_activity`)
   → rentan terhadap `post_max_size`, `max_execution_time`, timeout jaringan,
   proxy/CDN yang memutus koneksi panjang.
2. Setelah `stopSession()`, `tracking.js` memanggil `KKSave.clear()` **tanpa
   syarat** — sehingga saat upload gagal, backup lokal ikut terhapus dan
   pengguna kehilangan datanya.
3. Tidak ada progress UI, retry, maupun antrian "pending".

## Yang Diperbaiki (R52)
1. **Chunked upload** — `save.js` memecah upload menjadi:
   - `upload_init`       → server membuat `run_sessions` (status='aktif'),
     kembalikan `session_id`
   - `upload_chunk` × N  → bulk INSERT 300 titik / request
   - `upload_finalize`   → update jarak/durasi/kalori/status='selesai' +
     baris `upload_harian`, verifikasi jumlah titik tersimpan
2. **Retry otomatis** — tiap request di-retry hingga 3× dengan backoff.
3. **Progress overlay** di halaman Finish (0–100%, jumlah titik terunggah).
4. **Backup dipertahankan bila upload gagal.**
   `KKSave.clear()` sekarang **NO-OP untuk backup** ketika `_lastUploadOk=false`,
   sehingga panggilan `KKSave.clear()` dari `tracking.js` tetap aman dan
   TIDAK menghapus data. *(tracking.js sengaja tidak diubah — sesuai aturan.)*
5. **Dialog gagal** — muncul otomatis:
   > *"Aktivitas berhasil direkam, tetapi gagal disinkronkan ke server.
   > Data tetap aman di perangkat dan dapat diunggah kembali."*
   dengan tombol **Upload Ulang** menuju `aktivitas_pending.php`.
6. **Halaman `aktivitas_pending.php`** — daftar aktivitas yang gagal
   tersinkron (baca IndexedDB `kk_run_db` prefix `pending:*`) + tombol
   Upload Ulang / Hapus.
7. **Logging** — client (`console` `[KKSave]`) & server (`error_log`
   `[api_run]`) mencatat: jumlah titik, ukuran payload, waktu tiap
   request, `memory_get_usage`, jumlah titik akhir yang benar-benar
   tersimpan (verifikasi).
8. **Backward compat** — aksi lama `upload_activity`, `start`, `point`,
   `stop`, `delete` tetap ada. `upload_activity` juga di-boost:
   `set_time_limit(120)` + `memory_limit=256M`.

## Alur Baru
```
Stop → snapshot ke IDB → upload_init → chunk × N (progress 5–90%)
     → finalize (92–100%) → HAPUS backup lokal
Gagal di titik manapun → simpan sebagai pending:<ts> di IDB
                     → tampilkan dialog "Belum Tersinkron"
                     → user buka aktivitas_pending.php → Upload Ulang
```

## Yang TIDAK Diubah
- `tracking.js`, `gps.js`, `map.js`, `background.js`, `voice.js`
- Skema database, endpoint lama, ID HTML, class utama, event JS
- Logika tracking, wake lock, background geolocation, perhitungan
  jarak/pace/kalori, fullscreen, pause, stop, review, screenshot

## PostgreSQL
**Tidak ada perubahan skema.** Semua kolom yang dipakai
(`run_sessions.*`, `run_points.*`, `upload_harian.gpx_session_id`)
sudah tersedia dari revisi sebelumnya.

## File dalam ZIP
- `assets/js/run/save.js`       — chunked upload, pending queue, progress hook
- `assets/js/run/ui.js`         — progress overlay + failure dialog
- `api_run.php`                 — action `upload_init` / `upload_chunk` /
                                  `upload_finalize` + logging + boost
                                  `upload_activity`
- `run.php`                     — cache-buster `save.js?v=r52`, `ui.js?v=r52`
- `aktivitas_pending.php`       — halaman antrian belum tersinkron

## Regresi
- Tracking, GPS, background, pause, stop, screenshot, fullscreen,
  review, riwayat, delete, export GPX/KML/GeoJSON, discard flow:
  **tidak tersentuh**.
- Klien lama yang masih memakai `upload_activity` tetap jalan.

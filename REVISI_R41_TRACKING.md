# REVISI R41 — Focus Mode Regression Fix (run.php)

Zip ini berisi HANYA file yang direvisi. Timpa ke root project.

## File yang direvisi
```
run.php                        ← hapus HTML .kk-focus-stats, tambah #kk-stats-card, CSS Focus Mode reposition
assets/js/run/ui.js            ← renderMetrics hanya update id d-*
```

## File yang TIDAK diubah
- assets/js/run/tracking.js
- assets/js/run/map.js
- assets/js/run/gps.js
- assets/js/run/save.js
- assets/js/run/background.js
- assets/js/run/voice.js
- api_run.php, upload.php, riwayat.php, service-worker.js, includes/*

## Ringkasan Perbaikan
1. **Regression statistik kosong di Focus Mode** — hilang karena sebelumnya
   ada HTML duplikat `.kk-focus-stats` dengan id `f-*-live`. Sekarang
   dihapus total. Focus Mode memakai card yang SAMA dengan Dashboard
   (`#kk-stats-card`) — dipindahkan hanya via CSS `position:fixed`.
   Semua id tetap `d-dist / d-time / d-pace / d-speed / d-cal / d-elev
   / d-avgpace / d-mode-chip`, sehingga tracking.js & ui.js tidak
   perlu tahu mode apa yang aktif.
2. **Card tidak lagi menutupi peta** — Focus Mode: card `width:90%`
   `max-width:520px`, tinggi otomatis (`height:auto`), posisi
   `top: safe-area + 12px`.
3. **Layout ala Strava** — urutan Focus Mode:
   ```
   Chips GPS + REC (kiri-atas) ─┐
   Floating Statistik           │  di atas
   Peta fullscreen              │  ↓
   Floating Tombol Jeda/Selesai │  di bawah (z-index 1200)
   ```
4. **Tidak ada duplicate HTML** — 1 card statistik, 1 map, 1 set tombol.
5. **Binding JS tetap** — `tracking.js`, `gps.js`, `ui.js`, `save.js`
   memakai element yang sama. Yang berubah hanya CSS class `body.kk-focus-mode`.

## PostgreSQL
**Tidak ada perubahan schema.** Tabel `run_sessions`, `run_points`,
`run_routes` tetap. Tidak perlu menambah tabel/kolom/migrasi baru.
Data `sportapp.sql` yang sudah ada aman — tidak ada yang dihapus.

## Cara pasang
1. Ekstrak zip.
2. Timpa ke root project (path relatif sudah benar):
   - `run.php`
   - `assets/js/run/ui.js`
3. Hard reload `run.php` (Ctrl+F5) supaya cache CSS/JS lama bersih.

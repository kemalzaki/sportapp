# REVISI R42 — Focus Mode & Tombol Buang

Cakupan revisi (kecil, tidak menyentuh business-logic GPS/tracking):

## 1. Focus Mode — Layer & Ukuran Panel Statistik
File: `run.php` (blok `<style>` bagian `body.kk-focus-mode #kk-stats-card`, `.kk-mapfabs`, `.kk-controls-card`).

- Tinggi panel statistik dikecilkan ± 35–40% (padding 6/12 px, primary
  value 1.4rem, cell font 0.78rem, gap 4px).
- Panel di-inset dari kanan (`right:64px`) supaya tidak menutupi kolom
  Floating Controls (Lokasi / Compass / Fullscreen / Settings).
- Panel `pointer-events:none` → seandainya overlap, klik tetap tembus.
- Urutan z-index Focus Mode:
  - Map           : 900
  - Stats card    : 1100
  - Chips + Fabs  : 1300
  - Settings pop  : 1450
  - Pause/Selesai : 1400  (kk-controls-card, paling atas)

## 2. Tombol "Buang" — Benar-benar Membuang
Files: `assets/js/run/ui.js`, `api_run.php`.

- `ui.js` memasang listener pada `#f-btn-discard` di fase **capture**
  sehingga jalan lebih dulu dari `tracking.js` (yang cuma reload).
  `stopImmediatePropagation()` mematikan handler bawaan.
- Dialog konfirmasi: **"Buang aktivitas ini? Data tidak akan disimpan."**
  - Batal → tetap di halaman Finish (tidak ada aksi).
  - Buang → POST `/api_run.php` `_action=delete&session_id=<sid>` lalu
    `location.replace('/run.php')` (Dashboard bersih, tidak bisa
    kembali ke Finish via tombol Back).
- `KKSave.stopSession` di-monkey-patch di `ui.js` untuk menangkap
  `sessionId` terakhir (tracking.js meng-null-kan state sebelum
  `KKFinish.open` dipanggil). **tracking.js & save.js tidak diubah.**
- `api_run.php` `_action=delete` sekarang juga menghapus baris
  `upload_harian` yang di-auto-insert oleh `_action=stop`
  (kolom `gpx_session_id`). Kepemilikan sesi divalidasi ulang
  sebelum kaskade hapus.

## 3. Alur setelah revisi
```
Mulai Tracking → Selesai → Halaman Ringkasan (Finish)
                                 │
              ┌──────────────────┴──────────────────┐
              │                                     │
           Buang                              Review & Upload
   (DELETE run_sessions +                (upload_harian sudah
    run_points +                          ter-insert saat stop;
    upload_harian)                        halaman upload.php
              │                            menampilkannya)
        /run.php bersih
```

## PostgreSQL — apakah butuh migrasi?

**Tidak perlu migrasi baru.** Kolom `upload_harian.gpx_session_id`
sudah dibuat otomatis oleh `api_run.php` (baris
`ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS gpx_session_id BIGINT`
pada `_action=stop`, R14). Skema `run_sessions` / `run_points` tidak
berubah.

Catatan opsional (jika ingin lebih rapi):
```sql
-- opsional: index untuk pencarian upload_harian by session
CREATE INDEX IF NOT EXISTS upload_harian_gpx_idx
  ON upload_harian(gpx_session_id);
```

## Constraint terpenuhi
File yang **TIDAK** diubah: `gps.js`, `tracking.js`, `save.js`,
`background.js`, `voice.js`. Struktur modul tetap.

File yang diubah: `run.php`, `assets/js/run/ui.js`, `api_run.php`.

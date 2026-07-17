# REVISI R42 — Focus Mode layering + tombol Buang

Perbaikan hanya menyentuh `run.php`, `assets/js/run/ui.js`, dan CSS di dalam `run.php`.
File `gps.js`, `tracking.js`, `save.js`, `background.js`, `voice.js`, dan skema
PostgreSQL TIDAK diubah.

## 1. Focus Mode — panel statistik tidak lagi menutupi floating controls

- Panel statistik `#kk-stats-card` diberi ruang di kanan (`right:66px`) supaya
  tidak menabrak kolom tombol Lokasi / Compass / Fullscreen / Settings.
- Tinggi panel dikecilkan ±35–40%:
  - padding `6px 10px 8px`
  - primary value `1.5rem` (dari `2rem`)
  - stat cell padding `4px 2px`, value `0.78rem`
  - grid gap `4px`
- Urutan `z-index` (bawah → atas):
  1. `#kk-map` / `.kk-mapwrap` — dasar
  2. `#kk-stats-card` — `z-index:900`
  3. `.kk-mapfabs`, `.kk-chips`, `.kk-recenter` — `z-index:1000`
  4. `.kk-controls-card` (Pause / Selesai) — `z-index:1100`
  5. `.kk-settings-pop` — `z-index:1200`
- Chips GPS/REC dipindah ke kiri-bawah saat focus supaya tidak berebut ruang
  dengan panel statistik.

Panel statistik tetap `#kk-stats-card` (id yang sama) sehingga `ui.js`
meng-update dashboard & focus dari satu source of truth (`d-*`) tanpa
mengubah tracking.js.

## 2. Tombol "Buang" benar-benar membatalkan sesi

Sebelumnya `f-btn-discard` di `tracking.js` hanya `location.reload()` tanpa
menghapus data. Karena `tracking.js` tidak boleh diubah, `ui.js` sekarang:

- Membungkus `KKSave.stopSession(sid, ...)` (via monkey-patch, tanpa menulis
  ulang `save.js`) untuk merekam `sid` ke `window._kkLastSessionId`.
- Mendaftarkan listener `click` pada `#f-btn-discard` di **fase capture**
  dengan `stopImmediatePropagation()` sehingga handler default tracking.js
  tidak lagi jalan.
- Flow:
  ```
  Klik Buang → confirm("Buang aktivitas ini? Data tidak akan disimpan.")
    Batal → tetap di halaman finish
    Buang → POST /api_run.php?action=delete { session_id }
          → KKSave.clear()  (bersihkan localStorage)
          → KKFinish.close()
          → location.replace('/run.php')  → dashboard bersih
  ```

Endpoint `api_run.php?action=delete` (sudah ada) menghapus `run_points` dan
`run_sessions` untuk session_id milik user. Karena penghapusan terjadi
sebelum reload, sesi tidak muncul di Riwayat Tracking maupun di
`upload.php`.

Hanya tombol **Review & Upload** yang tetap membawa data ke `upload.php`.

## PostgreSQL

Tidak ada perubahan schema. Tabel `run_sessions`, `run_points`, `run_routes`
tetap. Data existing di `sportapp.sql` aman.

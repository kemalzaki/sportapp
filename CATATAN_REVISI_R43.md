# Revisi R43 — KawanKeringat (UI/UX)

Zip ini berisi HANYA file yang direvisi. Timpa file dengan path yang sama
di project asli.

## Isi Zip

```
assets/css/safe-area.css        (BARU) — utility Safe Area global
assets/js/kk-mini-map.js        (BARU) — inisialisasi mini map Leaflet
includes/header.php             (EDIT) — tambah <link> safe-area.css
run.php                         (EDIT) — dropdown Jenis Olahraga = Jogging
riwayat.php                     (EDIT) — mini map preview rute aktivitas publik
```

## Ringkasan Perubahan

### 1. Safe Area Global (WAJIB)
- `assets/css/safe-area.css` mengurus:
  - `env(safe-area-inset-top/right/bottom/left)` untuk `body`, top-bar
    (`.gt-top`, `nav.navbar.fixed-top`), chips bar (`.gt-chips`), bottom
    nav (`.gj-nav`), modal (termasuk popup screenshot `#buktiModal`),
    toast, dan focus-mode di `run.php` (`.kk-chips`, `.kk-mapfabs`,
    `#kk-stats-card`).
- Cukup di-include SEKALI di `includes/header.php` (sudah ditambahkan).
  Halaman tanpa header dapat menambahkan class `kk-safe-page` pada body.
- Tidak ada margin manual per halaman — semua diatur global.

### 2. Jenis Olahraga → hanya Jogging
- `run.php` baris 456–462: `<select id="sportSel">` sekarang hanya
  memiliki 1 opsi `<option value="jog" selected>Jogging</option>`.
- Value `jog` dipertahankan agar `tracking.js` / `save.js` / API tetap
  menerima jenis olahraga yang sama seperti sebelumnya (tidak ada
  perubahan business logic, tabel, atau endpoint).
- Nilai tersimpan ke session tracking & ke `upload_harian.jenis` seperti
  alur lama.

### 3. Riwayat Aktivitas Publik — Preview Rute (Strava-like)
- `riwayat.php`:
  - Query `$publicActs` ditambah kolom `uh.gpx_session_id`.
  - Loader batch: satu query `SELECT session_id, lat, lng FROM run_points
    WHERE session_id = ANY($1::bigint[])` untuk semua aktivitas publik
    yang punya sesi GPS. Titik disederhanakan ke ~120 titik/sesi.
  - Setiap card publik yang memiliki titik ≥ 2 menampilkan
    `<div class="kk-mini-map" data-points="…">` (tinggi 200px) + tombol
    "Lihat Rute" → `/track_view.php?sid=…` (halaman detail dengan peta
    penuh yang sudah ada).
- `assets/js/kk-mini-map.js` (BARU):
  - Read-only Leaflet map, semua interaksi dinonaktifkan (drag, zoom,
    scroll, tap) sesuai permintaan.
  - Polyline jingga (`#fc5200`), marker start hijau, marker finish
    merah, auto `fitBounds`.
  - Klik peta atau tombol → membuka `/track_view.php?sid=…`.
  - TIDAK mengambil screenshot statis; menggunakan koordinat asli dari
    `run_points`.

### 4. Bottom Navigation
- Bottom nav (`includes/bottom_nav.php`) TIDAK diubah karena implementasi
  saat ini sudah persisten:
  - `position: fixed` + `view-transition-name: gj-nav` (View Transitions
    API) → tidak berkedip antar halaman.
  - Sudah menghormati `env(safe-area-inset-bottom)` via `gojek-nav.css`,
    diperkuat oleh `safe-area.css`.

## Yang TIDAK Diubah
- `assets/js/run/gps.js`, `tracking.js`, `map.js`, `save.js`,
  `background.js`, `voice.js` — semua modul tracking dibiarkan apa adanya.
- `upload.php`, `api_run.php`, endpoint & struktur JSON — tetap.
- Semua ID/kelas eksisting yang dipakai JS (`kk-btn-start`, `kk-btn-stop`,
  `sportSel`, `#kk-map`, `showBukti`, `toggleLike`, dsb).
- Struktur database, RLS, atau tabel apa pun.

## PostgreSQL
Tidak ada perubahan schema. Tidak perlu menambah tabel / kolom /
migrasi baru. Fitur mini map hanya membaca dua tabel yang SUDAH ada:
- `upload_harian.gpx_session_id`
- `run_points(session_id, lat, lng, id)`

Kedua kolom/tabel tersebut sudah di-buat oleh `api_run.php` dan
`upload.php` di project asli. `sportapp.sql` yang sudah ada tidak perlu
diubah.

Jika aktivitas publik lama tidak memiliki `gpx_session_id` (misal
di-upload manual dari `upload.php` tanpa Tracking Jalur), mini map
tidak akan muncul untuk aktivitas tersebut — card tetap tampil normal
seperti sebelumnya. Ini sesuai permintaan (jangan screenshot statis).

## Cara Pakai
1. Backup file yang akan ditimpa (opsional).
2. Extract zip di root project (timpa file dengan path yang sama).
3. Reload di browser dengan Ctrl+F5 (bersihkan cache CSS/JS).
4. Cek:
   - Header/top-bar tidak lagi menabrak status bar Android.
   - `run.php` → panel Settings peta hanya menampilkan "Jogging".
   - `riwayat.php` → card Riwayat Aktivitas Publik yang berasal dari
     Tracking Jalur memiliki mini peta + tombol "Lihat Rute".
   - Bottom nav tetap di bawah saat navigasi antar halaman.

## Regresi
Fungsi berikut TETAP jalan (tidak ada perubahan pada file terkait):
tracking, GPS, upload, save, riwayat, screenshot popup, fullscreen,
pause, stop, review, like/comment/share pada Riwayat Publik.

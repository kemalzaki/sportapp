# Revisi R35 — Tracking Jalur: Dashboard Mode + Focus Mode

Zip ini berisi **hanya file yang direvisi**, bukan seluruh project:

```
run.php                       ← halaman Tracking Jalur (ditulis ulang)
assets/js/run/ui.js           ← modul UI baru (Dashboard + Focus + KKFinish)
```

## Cara pasang
1. Ekstrak zip ini dan **timpa** ke root project KawanKeringat.
2. Refresh cache browser / bump `?v=` di URL script kalau perlu (URL sudah `?v=r35`).

Modul lain (`gps.js`, `tracking.js`, `map.js`, `save.js`,
`background.js`, `voice.js`) **TIDAK diubah**.

## PostgreSQL — perlu tambahan?
**Tidak.** Tabel `run_sessions` + `run_points` dan endpoint `api_run.php`
tetap dipakai apa adanya. Tidak ada migrasi tambahan, tidak ada kolom baru.
Data eksisting di `sportapp.sql` tidak disentuh.

Kalau nanti kamu mau menambah kolom (mis. `favorite_mode`), cukup:
```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS favorite_view_mode TEXT DEFAULT 'dashboard';
```
Tapi untuk R35 ini **tidak perlu** — preferensi mode disimpan di
`localStorage['kk_run_mode_v1']` pada sisi klien.

## Ringkasan Perubahan

### 1. Dashboard Mode (default)
- Halaman `run.php` dibuka langsung di Dashboard Mode.
- Panel yang tampil: **Statistik → Mini Map (~38 vh) → Tombol
  Mulai/Pause/Stop → Split per KM → Riwayat**.
- Header & Bottom Navigation **tetap tampil**.
- Bukan fullscreen. Tidak ada auto-fullscreen saat Start.

### 2. Focus Mode (fullscreen, opsional)
- Aktif hanya lewat tombol floating **⛶** di kanan atas peta
  (atau dipulihkan otomatis kalau mode terakhir = focus).
- Peta jadi `position:fixed; inset:0` (100% viewport).
- Header, bottom nav, sidebar, search, footer disembunyikan via CSS
  `body.kk-focus-mode`.
- Statistik = glass overlay floating (blur 18px + saturate 140%).
- Tombol Pause / Stop / Lock / Voice jadi floating bottom bar.
- Tombol **Exit Fullscreen** (kiri kanan atas) untuk kembali ke Dashboard.

### 3. Perpindahan Mode
- **Hanya toggle CSS class**. Fungsi di `ui.js`:
  `enterFocusMode()`, `exitFocusMode()`, `toggleFocusMode()`.
- Leaflet TIDAK di-destroy / recreate. `KKMap.invalidate()` dipanggil
  agar peta menyesuaikan ukuran.
- Timer, GPS watcher, background service, voice, marker, polyline
  semua tetap jalan. Tidak ada reload.

### 4. Floating Map Controls
Empat tombol bulat di kanan atas peta (hadir di dua mode):

| Ikon | Fungsi                               |
|------|--------------------------------------|
| 📍   | Follow My Location (`KKMap.recenter`) |
| 🧭   | Toggle Compass (rotasi peta)          |
| ⛶    | Toggle Fullscreen (Focus Mode)        |
| ⚙️   | Settings (sport, voice, rotasi, berat)|

Style: 44px bulat, shadow ringan, hover lift, ripple, glow biru saat aktif.

### 5. Preferensi Pengguna
- Mode disimpan ke `localStorage['kk_run_mode_v1']` (`dashboard` |
  `focus`).
- Saat halaman dibuka lagi, mode terakhir dipulihkan otomatis.

### 6. Fix Bug Stop (dari CATATAN_REVISI)
Tombol Selesai di Dashboard tidak jalan di versi R34 karena swipe
UI berada di `.kk-ctrl` yang `display:none` di Dashboard. Di R35,
`KKUI.showSwipeFinish` mendeteksi mode:
- **Focus Mode** → swipe-to-finish (anti salah pencet).
- **Dashboard Mode** → `confirm()` → langsung `stopSession()` →
  `KKSave.flush` + `stopSession` → summary + tombol Review & Upload.

### 7. Identitas Visual
Palet KK dipakai: navy `#081223`, electric blue `#1E90FF`, light blue
`#BFE0FF`. Polyline & marker pelari berwarna Electric Blue.
Border radius 20–24 px, shadow biru lembut, transisi 300 ms.

## Verifikasi Cepat
1. Buka `/run.php` → Dashboard (stat + mini map + tombol Mulai + riwayat).
2. Klik ⛶ → seluruh viewport jadi peta, statistik jadi overlay glass.
3. Klik Exit Fullscreen → balik ke Dashboard. Timer/GPS/polyline
   tetap seperti sebelumnya.
4. Reload halaman saat mode terakhir Focus → otomatis Focus lagi.

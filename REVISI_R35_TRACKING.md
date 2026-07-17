# Revisi R35 — Tracking Jalur: Dashboard Mode + Focus Mode (KawanKeringat)

Zip ini berisi **hanya file yang direvisi** (bukan seluruh project):

```
run.php                       ← halaman Tracking Jalur (ditulis ulang)
assets/js/run/ui.js           ← ditambah Dashboard/Focus mode + floating map controls
```

Modul JS lain (`gps.js`, `tracking.js`, `map.js`, `save.js`,
`background.js`, `voice.js`) **TIDAK diubah**, sesuai konstrain.

## Cara pasang

1. Ekstrak zip ini dan **timpa** ke root project KawanKeringat (folder
   tempat `run.php` lama berada).
2. Refresh cache browser / bump ?v= di URL script kalau perlu
   (URL sudah `?v=r35`).

## PostgreSQL — apakah perlu tambahan?

**Tidak.** Skema `run_sessions`, `run_points`, dan endpoint
`api_run.php` (start / point / stop / delete) **tetap dipakai apa
adanya**. Tidak ada migrasi SQL, tidak ada tabel baru, tidak ada
kolom baru. Data eksisting di dump `sportapp.sql` tidak disentuh.

## Apa yang berubah (ringkas)

### 1. Dashboard Mode (default)
- Saat halaman `run.php` dibuka, langsung muncul mode Dashboard.
- Panel yang tampil: Statistik, Mini Map (±38 vh), tombol
  Mulai/Pause/Stop, Split per KM, Riwayat.
- Header aplikasi & Bottom Navigation **tetap tampil**.
- Bukan fullscreen.
- Peta Leaflet memakai tile Mapbox Outdoors normal (tanpa overlay gelap).

### 2. Focus Mode (fullscreen)
- Tombol floating **⛶ Fullscreen** di kanan atas peta.
- Saat aktif:
  - Peta jadi fullscreen (`position:fixed; inset:0`).
  - Header & Bottom Nav disembunyikan via CSS class `body.kk-focus-mode`.
  - Statistik = floating glass overlay (glassmorphism, blur 18px).
  - Tombol Pause/Stop/Lock/Voice jadi floating (bottom control bar).
  - Ada tombol **Exit Fullscreen** di kiri bawah peta.
- Perpindahan mode **hanya toggle CSS class** (`enterFocusMode()` /
  `exitFocusMode()` di `ui.js`). **Leaflet tidak di-destroy / recreate**.
  `KKMap.invalidate()` dipanggil supaya peta menyesuaikan ukuran.
- Selama perpindahan mode:
  - Timer tidak reset (semua state tetap di `tracking.js`).
  - GPS tidak restart (watcher `gps.js` tetap jalan).
  - Marker & polyline tetap.
  - Background tracking & voice tetap.
- Mode terakhir disimpan ke `localStorage['kk_run_mode_v1']`
  (nilai `dashboard` | `focus`). Saat sesi tracking aktif dan halaman
  dibuka ulang, mode terakhir dipulihkan otomatis.

### 3. Floating Map Controls
Empat tombol bulat di kanan atas peta, hadir di dua mode:
- 📍 Follow My Location — memanggil `KKMap.recenter()`.
- 🧭 Compass — toggle rotasi (`heading` ↔ `north`), memakai
  `KKMap.setRotationEnabled()`.
- ⛶ Fullscreen — toggle Focus Mode.
- ⚙️ Settings — popover untuk Jenis Olahraga, Voice Feedback,
  Rotasi Map.

Efek visual: hover lift, ripple JS, dan glow biru saat aktif
(`--kk-glow-blue`).

### 4. Identitas Visual KawanKeringat
Token warna baru di `run.php`:

| Token             | Nilai      | Pakai untuk                 |
|-------------------|------------|-----------------------------|
| `--kk-navy`       | `#081223`  | Background Focus Mode       |
| `--kk-navy-2`     | `#0d1a33`  | Gradasi                     |
| `--kk-blue`       | `#1E90FF`  | Aksen, polyline GPS, glow   |
| `--kk-blue-2`     | `#4FB0FF`  | Gradasi tombol              |
| `--kk-light`      | `#BFE0FF`  | Aksen label glass           |
| `--kk-white`      | `#ffffff`  | Panel Dashboard             |

- Dashboard: `body.kk-run-page` memakai
  `linear-gradient(160deg, #081223 → #0d2547 → #1E90FF → #BFE0FF)`
  fixed. Panel Dashboard putih dengan blur ringan.
- **Tile Leaflet tetap normal** (tidak diberi overlay gelap) supaya
  peta mudah dibaca.
- Focus Mode: body background `--kk-navy`. Metric card = glass
  (blur 18px + saturate 140% + border light-blue).
- Polyline GPS = Electric Blue via `KK_RUN.polylineColor` + CSS
  fallback `.leaflet-overlay-pane path.leaflet-interactive`.
- Marker pelari = bulatan Electric Blue dengan halo.
- Status GPS: hijau (ok), kuning (sedang), merah (buruk).
- REC: chip merah dengan `@keyframes kkRecBlink` (halus, 1.4s).
- Tombol aktif: `.kk-btn-start`, `.kk-btn-resume`, `.kk-mapfab.active`
  memakai `--kk-glow-blue`.
- Border radius 20–24 px, shadow biru lembut, transisi 300 ms.

### 5. Konsistensi UI
Finish screen (`#kk-finish`) memakai palet & radius KK yang sama
(kartu putih, canvas grafik dengan warna Electric Blue), sehingga
transisi dari tracking ke halaman hasil terasa mulus dan konsisten
dengan halaman Activity Summary.

### 6. Konstrain yang dipatuhi
- `gps.js`, `tracking.js`, `map.js`, `save.js`, `background.js`,
  `voice.js` **tidak diubah** — semua fungsi tracking, GPS, Leaflet,
  penyimpanan berjalan seperti sebelumnya.
- Fungsi baru **hanya di `ui.js`**:
  `enterFocusMode()`, `exitFocusMode()`, `toggleFocusMode()`,
  `currentMode()`, `initDashboardMode()`.
- `enterFullscreen()` / `exitFullscreen()` yang dipanggil
  `tracking.js` R34 tetap ada (back-compat), tetapi kini merutekan
  ke mode tersimpan (default Dashboard).

## Cara verifikasi cepat
1. Buka `/run.php` → seharusnya melihat Dashboard (stat + mini map +
   tombol Mulai + riwayat) dengan background gradasi navy → biru.
2. Klik ⛶ pada peta → seluruh viewport jadi peta, statistik jadi
   overlay glass. Timer/GPS tidak reset.
3. Klik Exit Fullscreen (kiri bawah) atau ⛶ lagi → kembali ke
   Dashboard. Peta tetap menampilkan posisi & polyline yang sama.
4. Refresh halaman saat mode Focus tersimpan → mode Focus otomatis
   dipulihkan (bila ada sesi aktif).

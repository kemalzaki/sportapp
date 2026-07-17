# Revisi R47 — KawanKeringat (Juli 2026)

Perbaikan minor lanjutan dari R46. Tidak ada perubahan business logic
(tracking, GPS, upload, DB, screenshot, pause/stop, review tetap sama).

## 1. Safe Area Drawer diperkuat
**File:** `assets/css/safe-area.css` (append block R47)

Menu paling atas di drawer (grup **Jogging Progress**) masih tertutup
status bar pada beberapa Android WebView yang mengembalikan
`env(safe-area-inset-top) = 0px`. R47 menambahkan fallback minimum
`24px` (dan `32px` untuk mode `.is-native`) pada:
- `.gt-drawer`
- `.offcanvas.offcanvas-start / -end / -top`
- `.offcanvas-header` yang menyertainya

Tidak mengubah ID/kelas apa pun. Aman untuk desktop (env > fallback →
env yang dipakai).

## 2. Mini map di `riwayat.php` dibuat READ-ONLY total
**File:** `riwayat.php` (blok `<style>` di sekitar baris 452)

Menambahkan CSS `pointer-events:none` untuk `.kk-route-preview
.kk-mini-map`. Efeknya:
- Peta preview **tidak bisa diklik / drag / zoom**.
- Tidak ada peluang redirect ke `live_tracking.php` atau
  `track_view.php`.
- Kontrol Leaflet (zoom, attribution) disembunyikan pada preview.
- Interaksi penuh tetap tersedia lewat tombol **Lihat Rute** yang sudah
  mengarah ke `activity_detail.php` (read-only, dari R46).

Struktur DOM, ID, class utama (`.kk-mini-map`, `.kk-route-preview`,
`.kk-route-expand`), dan JS `kk-mini-map.js` **tidak diubah**.

## Database / PostgreSQL
Tidak ada perubahan skema atau data. Tidak perlu tambahan tabel/kolom
baru untuk revisi ini.

## Daftar file di ZIP
- `assets/css/safe-area.css`
- `riwayat.php`
- `CATATAN_REVISI_R47.md`

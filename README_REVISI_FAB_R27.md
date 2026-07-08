# Revisi FAB Upload R27 — Strava-style + Bottom Sheet

## File yang diubah (UI/UX & CSS saja, tanpa perubahan PHP/JS/API/DB/route)
- `includes/bottom_nav.php`
- `assets/css/gojek-nav.css`

## Perubahan
- FAB sekarang satu lingkaran biru tunggal (58px), TANPA background putih / glow / label di dalamnya.
- Ikon "+" putih besar tepat di tengah, shadow lembut.
- Naik hanya 8px dari bottom nav — menyatu visual dengan bar.
- Label **"Upload"** kini menjadi bagian dari bottom navigation, sejajar dengan Beranda / Aktivitas / Kalori / Saya.
- Animasi tekan `scale(.94)`.
- Tap FAB membuka **Bottom Sheet** berisi 4 pilihan (mengikuti pola Strava/Google Fit/Instagram):
  1. **Upload Aktivitas** → `/upload.php`
  2. **Upload Foto** → `/upload.php?type=foto`
  3. **Story** → `/upload.php?type=story`
  4. **Check-in** → `/checkin.php`
- Bottom sheet: backdrop gelap, swipe-down untuk tutup, ESC untuk tutup, klik luar untuk tutup, aksesibel (`role=dialog`, `aria-modal`, `aria-expanded` di FAB).
- Query `?type=foto` / `?type=story` diabaikan oleh `upload.php` yang sudah ada (tidak mengubah logic). Bisa dipakai nanti kalau ingin menampilkan tab awal berbeda tanpa mengubah handler.
- Padding-bottom body disinkronkan agar konten tidak tertutup FAB di semua ukuran layar (Android/iPhone termasuk safe-area home indicator).

## PostgreSQL
Tidak ada perubahan skema atau data. **Tidak ada SQL yang perlu dijalankan** untuk revisi ini.

## Cara pasang
Ekstrak zip di root project — timpa 2 file di atas. Cache CSS otomatis di-bust via `?v=r27-fab`.

# Revisi 28 Juni 2026 — Tampilan desktop disamakan persis dengan tampilan handphone

## Masalah
Saat dibuka di laptop/desktop (lebar ≥ 992px), tampilan tidak rapih dan tidak
benar-benar mirip tampilan handphone. Penyebabnya: ADA DUA blok CSS
"paksa frame mobile" yang saling bertabrakan:

1. `assets/css/gojek-top.css` (@media min-width:992px)
   - Memakai `position:fixed; left:50%; transform:translateX(-50%)`
     untuk `.gt-top`, `.gt-chips`, `.gj-nav`.
   - Membatasi `body > .container` ke `max-width: 480px`.

2. `includes/header.php` (inline `<style>` @media min-width:992px)
   - Menimpa dengan `left:0; right:0; margin:0 auto` **tanpa membatalkan
     `transform: translateX(-50%)`** dari gojek-top.css.
   - Membuat `.container` jadi `max-width:100%`.

Akibatnya top-bar, chips, dan bottom-nav tergeser 240px ke kiri (karena
`translateX(-50%)` tetap aktif), lebar container tidak konsisten, dan
frame ponsel di desktop berantakan.

## Perbaikan
- `includes/header.php`: blok @media (min-width:992px) ditulis ulang
  menjadi SATU sumber kebenaran (canonical). Bar fixed dipusatkan dengan
  `left:50% + translateX(-50%)` saja, body dijadikan frame 480px terpusat
  dengan padding atas/bawah yang sama persis dengan versi mobile
  (`var(--gt-h, 56px) + 56px` untuk chips, 76px untuk bottom nav).
- `assets/css/gojek-top.css`: blok @media (min-width:992px) lama dikosongkan
  (komentar) karena sudah dipindahkan ke header.php.
- Modal/offcanvas/DM floating juga ikut diselaraskan agar tetap berada
  di dalam frame 480px.

## File yang berubah
- `includes/header.php`
- `assets/css/gojek-top.css`
- `README_REVISI_28JUN2026.md` (file ini)

## PostgreSQL
Tidak ada perubahan skema. **Tidak perlu menjalankan migrasi SQL apapun**
untuk revisi ini — murni perbaikan CSS frontend.

## Cara test di local
1. Replace `includes/header.php` dan `assets/css/gojek-top.css` dengan
   file dari zip ini.
2. Hard-reload (Ctrl+F5) supaya CSS cache browser tidak menahan versi lama.
3. Buka halaman apa pun (mis. `/index.php`, `/islami.php`, `/run.php`)
   di browser desktop dengan lebar > 992px — seharusnya tampil sebagai
   "ponsel di tengah layar" dengan top-bar biru, chips, dan bottom-nav
   sejajar tepat di atas frame.

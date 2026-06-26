# Revisi Tampilan Desktop — Frame Ponsel (KawanKeringat)

Tanggal: 26 Juni 2026
Berkas direvisi:
- `includes/header.php`

## Masalah
Pada layar desktop (≥992px), drawer/sidebar `#gtDrawer` ditampilkan
permanen sebagai sidebar di sisi kiri. Akibatnya tampilan tidak
menyerupai ponsel (lampiran "REALITAS"). Yang diharapkan: tampilan
sama persis dengan mobile — frame 480px terpusat, top bar + chips +
bottom nav, dan drawer hanya muncul saat tombol burger ditekan
(lampiran "HARAPAN").

## Perubahan
Menambahkan override CSS di blok `@media (min-width: 992px)` pada
`includes/header.php`:

- `.gt-drawer` dipaksa `transform: translateX(-100%)` + `visibility: hidden`
  sehingga TIDAK pernah terlihat sebagai sidebar permanen.
- Saat user menekan tombol burger, Bootstrap menambahkan class `.show`
  pada offcanvas; rule `.gt-drawer.show` mengembalikan
  `transform: translateX(0)` dan `visibility: visible` (animasi 250ms).
- `body` dikunci `margin: auto` agar tetap terpusat sebagai frame 480px.
- `desktop-fix.css` (di `/assets/css/`) TIDAK perlu diubah; cukup
  override di header karena `<style>` inline ada SETELAH stylesheet
  tersebut dan menggunakan `!important`.

## Cara Pasang
1. Replace file `includes/header.php` lama dengan file yang ada di
   arsip ini.
2. Hard refresh browser (Ctrl+F5) untuk membuang cache CSS.
3. Buka di desktop — tampilan kini sama dengan mobile.

## PostgreSQL
Tidak ada perubahan skema. **TIDAK perlu** menambah migration baru.
File `.sql` & data eksisting tidak diutak-atik.

## Catatan
Revisi ini murni front-end (CSS + struktur DOM tidak berubah).
Halaman lain otomatis ikut benar karena semua include `header.php`.

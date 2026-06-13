# Catatan Revisi Login (loginfix-2) — 13 Juni 2026

ZIP ini hanya berisi file yang direvisi, bukan seluruh aplikasi.

## Penyebab loop login → index → kembali ke login

Login sebenarnya **berhasil** (session & cookie `hf_auth` sudah diset).
Tapi pada `config/db.php` ada *global exception handler* lama yang
mem-redirect ke `HTTP_REFERER` setiap kali ada exception yang tidak
ditangkap:

```php
header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php'));
```

Setelah login, browser GET `/index.php` dengan header
`Referer: /login.php`. Bila **ada satu saja query di index.php yang
gagal** (mis. kolom belum dimigrasi, tabel hilang di DB lokal,
auto-migration di `db.php` belum bisa ALTER karena permission, dll),
handler tersebut akan melempar user kembali ke `/login.php`. User
login lagi → exception lagi → loop tak putus, tanpa pesan error
karena `Location:` keburu dikirim sebelum body error tampil.

## File yang direvisi

1. **`config/db.php`** — *fix utama untuk loop login*
   - Global exception handler tidak lagi redirect ke `HTTP_REFERER`.
   - Sekarang menampilkan halaman error HTTP 500 yang berisi pesan
     error + file & line + stack trace (bisa di-collapse), sehingga
     penyebab sebenarnya kelihatan.
   - Setelah aplikasi stabil di production, ubah variabel
     `$SHOW_DETAIL` pada handler menjadi `false` agar stack trace
     tidak ditampilkan ke user.

## Cara pakai

1. Ekstrak ZIP ini ke root project, timpa file lama
   (`config/db.php`).
2. Hard refresh browser (Ctrl+Shift+R) atau clear site data sekali
   agar service worker tidak menahan response lama.
3. Coba login lagi.
   - Jika berhasil masuk ke beranda → loop sudah hilang.
   - Jika muncul halaman error merah pada `/index.php`, **itu adalah
     penyebab sebenarnya** (misalnya nama kolom yang hilang). Salin
     pesan errornya dan kirim ke saya; nanti saya buatkan migrasi
     SQL-nya. Jangan kembalikan handler ke versi lama, karena akan
     menyembunyikan error lagi.

## PostgreSQL

Tidak ada tabel/kolom baru yang perlu ditambahkan untuk revisi
**loginfix-2** ini. Data SQL yang sudah ada tetap dipakai dan tidak
dihapus.

Catatan: bila pesan error yang muncul setelah revisi ini menyebut
kolom/tabel tertentu (mis. `column "xxx" does not exist`), itu artinya
DB lokal kamu belum sinkron dengan auto-migration. Beritahu saya pesan
error yang muncul, dan saya buatkan file `.sql` tambahan.

# Catatan Revisi Login - 13 Juni 2026 Fix 2

## Masalah
Setelah login benar, aplikasi tetap diarahkan kembali dari `/index.php` ke `/login.php`.

## Penyebab tambahan yang diperbaiki
Pada beberapa server lokal, `$_SERVER['HTTPS']` bisa bernilai string `off`.
Kode lama memakai `!empty($_SERVER['HTTPS'])`, sehingga `off` tetap dianggap HTTPS.
Akibatnya cookie `PHPSESSID` dibuat dengan flag `Secure` saat aplikasi dibuka lewat HTTP lokal.
Browser tidak mengirim cookie Secure pada HTTP, sehingga session tidak terbaca di `/index.php`.

## File yang direvisi
- `config/db.php`
  - Deteksi HTTPS dibuat benar: `off` tidak lagi dianggap HTTPS.
  - Blok manual `setcookie(session_name(), session_id(), ...)` tetap dihapus agar tidak mengirim session ID lama.
- `login.php`
  - Alur login dirapikan.
  - `session_write_close()` tetap dipanggil sebelum redirect ke `/index.php`.

## PostgreSQL
Tidak ada tabel atau kolom baru yang perlu ditambahkan untuk revisi ini.

## Cara pasang
Replace file berikut di project lokal:
1. `login.php`
2. `config/db.php`

Setelah replace, tutup browser atau hapus cookie `PHPSESSID` untuk domain lokal aplikasi, lalu login ulang.

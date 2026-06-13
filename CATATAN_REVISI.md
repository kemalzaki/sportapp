# Revisi 13 Juni 2026 — Perbaikan Login

## Masalah
Setelah submit form login dengan data benar, user kembali ke halaman `/login.php`
(tidak masuk ke `/index.php`).

## Penyebab
File `config/db.php` memanggil `setcookie(session_name(), session_id(), ...)`
di setiap awal request untuk memperpanjang cookie session 30 hari.
Pada request POST login, `login.php` memanggil `session_regenerate_id(true)`
sehingga PHP mengirim header `Set-Cookie: PHPSESSID=<ID_BARU>`.
Akibatnya response berisi DUA header `Set-Cookie` (ID lama dari db.php
+ ID baru dari regenerate). Browser sering memakai ID lama, sehingga
pada redirect ke `/index.php` session kosong → user dipantulkan balik ke
`/login.php`.

## Perbaikan
1. **`config/db.php`** — hilangkan blok manual `setcookie(session_name()...)`.
   Lifetime 30 hari tetap aktif via `session_set_cookie_params([...])` di atasnya.
2. **`login.php`** —
   - Set `$_SESSION['user']` DULU baru `session_regenerate_id(true)`
     agar data session pasti ikut termigrasi.
   - Tambah `session_write_close()` sebelum `header('Location: /index.php')`
     supaya session benar-benar tersimpan sebelum redirect.

## File yang diubah dalam zip ini
- `login.php`
- `config/db.php`

Cukup timpa kedua file tersebut di project lokal.

## PostgreSQL
Tidak ada migrasi baru yang perlu dijalankan untuk perbaikan ini.
(Migrasi `migrations_revisi_13juni2026.sql` dari paket sebelumnya tetap
berlaku jika belum dijalankan, tapi tidak terkait bug ini.)

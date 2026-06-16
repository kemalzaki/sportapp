# Revisi 16 Juni 2026 — Part D

Isi zip ini hanya file yang direvisi sebagian, bukan seluruh aplikasi.

## Penyebab error `GEMINI_API_KEY belum di-set`

Di revisi sebelumnya, `includes/ai_gemini.php` membaca `GEMINI_API_KEY` dari environment, `$_ENV`, atau `$_SERVER`.

Masalah yang sering terjadi di local PHP/XAMPP/Laragon:

1. Key ditulis di `config/env.local.php`, tetapi halaman seperti `monitoring.php`, `islami.php`, atau `live_tracking.php` tidak meng-include file config itu sebelum memanggil Gemini.
2. Key hanya ditulis sebagai komentar/contoh, bukan benar-benar dipasang ke environment.
3. Setelah key ditambahkan, server PHP/Apache belum direstart.
4. Key yang dipasang bukan API key AI Studio yang diawali `AIza...`.

## Perbaikan di part D

File `includes/ai_gemini.php` sekarang:

- Otomatis mencoba memuat:
  - `config/env.local.php`
  - `config/env.php`
  - `.env.local.php`
- Menambahkan kompatibilitas fungsi `hf_env_set()` bila belum ada.
- Membaca key dari `getenv()`, `$_ENV`, dan `$_SERVER`.
- Menyediakan fungsi aman `gemini_config_status()` untuk cek sumber key tanpa membocorkan key penuh.

## Cara pasang key yang disarankan

Buat/ubah file:

```php
config/env.local.php
```

Isi:

```php
<?php
hf_env_set('GEMINI_API_KEY', 'AIzaISI_API_KEY_ANDA');
hf_env_set('GEMINI_MODEL', 'gemini-2.5-flash');
```

Lalu restart Apache/PHP local server.

## Cara cek cepat

Buat file sementara `cek_gemini.php` di root project local:

```php
<?php
require_once __DIR__ . '/includes/ai_gemini.php';
header('Content-Type: text/plain');
print_r(gemini_config_status());
```

Buka di browser. Jika benar, `has_key` harus bernilai `1` dan `key_masked` harus diawali `AIza...`.

Setelah selesai cek, hapus `cek_gemini.php` agar key status tidak tampil publik.

## PostgreSQL

Tidak ada perubahan PostgreSQL untuk revisi ini.

# Revisi 16 Juni 2026 — Part F (perbaikan AI Gemini)

Arsip ini **hanya berisi sebagian file yang direvisi**, bukan seluruh
aplikasi. File `.sql` dan datanya dari zip awal **tidak diubah dan
tidak dihapus**. Cukup timpa file dengan nama yang sama di project
local Anda.

## Apa yang diperbaiki

1. **`includes/ai_gemini.php` (BARU — sebelumnya HILANG)**
   Penyebab utama semua error `Failed to open stream: includes/ai_gemini.php`,
   `Call to undefined function gemini_text()`, dan response JSON kosong
   di `api_ai.php`, `api_run.php`, `kalori_mingguan.php` adalah file
   helper-nya **tidak ada di zip sebelumnya**. File ini sekarang dibuat
   ulang lengkap dengan fungsi:
   - `gemini_text($prompt, $opts)`
   - `gemini_vision($prompt, $imagePath, $opts)`
   - `gemini_extract_json($text)` — toleran terhadap blok ```json``` dan
     teks campuran (tidak akan throw / return non-array)
   - `gemini_config_status()`

2. **Key Gemini di-render langsung di kode** (sesuai permintaan)
   Konstanta `GEMINI_API_KEY_DEFAULT` di-hardcode di
   `includes/ai_gemini.php` baris ~28. Jadi Anda **tidak perlu**
   set environment variable apa pun untuk menjalankan AI di local.

   Helper otomatis memilih cara autentikasi:
   - Jika key diawali `AIza…` (API key AI Studio) → dikirim via `?key=`.
   - Jika key diawali `AQ.` / `ya29.` / lainnya (OAuth access token) →
     dikirim via header `Authorization: Bearer …`.

3. **`config/env.local.php`** dibersihkan
   Baris lama `hf_env_set('GEMINI_API_KEY', 'AQ.Ab8RN6...')` dihapus
   supaya tidak menimpa default yang sudah di-hardcode. Bila Anda
   ingin pakai API key `AIza...` sendiri, uncomment baris contoh di
   file tersebut.

## Yang perlu diubah di PostgreSQL

**Tidak ada migrasi baru.** Tabel yang dipakai semuanya sudah ada di
`sportapp.sql` dan migrasi sebelumnya. Jika belum pernah dijalankan,
pastikan ketiganya sudah di-apply sekali:

```bash
psql -U postgres -d sportapp -f migrations_revisi_13juni2026.sql
psql -U postgres -d sportapp -f migrations_run_advanced_15juni2026.sql
psql -U postgres -d sportapp -f migrations_komunitas_extra_15juni2026.sql
```

## Cara cek cepat AI sudah jalan

Buat file sementara `cek_gemini.php` di root project:

```php
<?php
require_once __DIR__ . '/includes/ai_gemini.php';
header('Content-Type: text/plain');
print_r(gemini_config_status());
print_r(gemini_text('Tes singkat: balas kata "ok" saja.'));
```

Buka di browser. Harus muncul `has_key => 1` dan teks balasan dari
Gemini. Hapus file ini setelah selesai cek.

## Catatan tambahan

- Tetap **PHP + PostgreSQL** murni (tidak diubah ke React/Node).
- CSP di `includes/security.php` sudah meng-allow
  `generativelanguage.googleapis.com` (tidak perlu diubah).
- Jika token OAuth `AQ...` default sudah expired di sisi Google,
  pesan error akan jelas (HTTP 401 dari Google). Solusinya tinggal
  buka https://aistudio.google.com/apikey untuk generate API key
  `AIza...` gratis, lalu uncomment baris di `config/env.local.php`.

## Isi arsip Part F

```
README_REVISI_16JUNI2026_partF.md   (file ini)
includes/ai_gemini.php              (BARU — file inti yang hilang)
config/env.local.php                (REVISI — hapus baris key salah)
```

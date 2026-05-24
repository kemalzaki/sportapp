# Revisi Fitur Hub Islami — Catatan

## Ringkasan perubahan

| # | Fitur                                          | File                                   | Status |
|---|------------------------------------------------|----------------------------------------|--------|
| 1 | Pencarian ayat (Arab)                          | `quran_search.php?mode=ayat`           | BARU   |
| 2 | Jumlah total kata Bahasa Arab (Rabb, Malik, …) | `quran_kata.php`                       | BARU   |
| 3 | Makna per ayat                                 | `quran_surah.php` (tombol *Makna*)     | BARU   |
| 4 | Tafsir per-kata (Bahasa Indonesia)             | `quran_surah.php`                      | DIUBAH |
| 5 | Penanda ayat ber-Asbabun Nuzul                 | `includes/asbab_nuzul.php` + UI badge  | BARU   |
| 6 | Tafsir Ibnu Katsir + Fi Zhilalil Qur'an        | `quran_surah.php` (tombol *Tafsir*)    | DIUBAH |
| 7 | Audio per ayat dihapus                         | `quran_surah.php`                      | DIHAPUS|
| 8 | Pencarian terjemah                             | `quran_search.php?mode=terjemah`       | BARU   |

## Database

**Penting:** Aplikasi ini memakai **PostgreSQL**, bukan MySQL.
File `sportapp.sql` adalah dump PostgreSQL, dan semua query memakai
`pg_*` + placeholder `$1, $2`. Untuk menjalankan di lokal, install **PostgreSQL 14+** (bukan MySQL).

### Yang perlu Anda lakukan di PostgreSQL lokal

1. Install PostgreSQL + ekstensi `php-pgsql`:
   ```bash
   # Ubuntu/Debian
   sudo apt install postgresql php-pgsql
   # macOS
   brew install postgresql php
   ```
2. Buat database & import:
   ```bash
   createdb sportapp
   psql sportapp < sportapp.sql
   ```
3. Set env var koneksi sebelum menjalankan PHP:
   ```bash
   export DB_HOST=127.0.0.1
   export DB_PORT=5432
   export DB_NAME=sportapp
   export DB_USER=postgres
   export DB_PASS=postgres
   export DB_SSLMODE=disable
   php -S localhost:8080
   ```
4. **Tidak ada tabel baru yang perlu Anda buat.** Semua fitur revisi:
   - Asbabun Nuzul → data statis di `includes/asbab_nuzul.php`
   - Jumlah kata populer → data statis di `quran_kata.php`
   - Tafsir Ibnu Katsir / Fi Zhilal / pencarian → diambil dari API publik
     (equran.id, quran.com, jsDelivr/spa5k tafsir_api) — butuh koneksi internet.

Data lama Anda **tidak diubah maupun dihapus**.

## Catatan tafsir

* **Ibnu Katsir (Bahasa Indonesia)** diambil dari
  `https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir/id-tafisr-ibn-kathir/{surah}/{ayat}.json`.
  Jika untuk ayat tertentu terjemahan Indonesia belum tersedia, sistem
  otomatis fallback ke versi **English** dari sumber yang sama.
* **Fi Zhilalil Qur'an (Sayyid Quthb)** diambil dari
  `https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir/ar-tafsir-fi-zilal-quran/{surah}/{ayat}.json`
  (teks asli Bahasa Arab — karena belum ada API publik gratis yang menyediakan
  terjemah Bahasa Indonesia lengkap untuk tafsir ini).
* **Tafsir per-kata** memakai endpoint `word_translation_language=id` Quran.com (Bahasa Indonesia).
* **Pencarian** memakai endpoint search Quran.com (Arab & Indonesia).

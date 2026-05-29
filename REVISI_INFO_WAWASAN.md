# REVISI SportApp — Info & Wawasan (29 Mei 2026)

Arsip ini berisi **HANYA file yang ditambahkan / direvisi**. Timpa ke folder project Anda dengan struktur folder yang sama.

## Daftar file di zip
- `index.php`                 — ditambahkan section **"Info & Wawasan"** (4 kartu shortcut) tepat setelah hero. Bagian lain TIDAK diubah.
- `berita.php`                — **BARU.** Berita Politik / Ekonomi / Olahraga / Teknologi (API publik).
- `beasiswa.php`              — **BARU.** Info beasiswa S1 / S2 / S3 (kurasi + berita beasiswa via API).
- `kesehatan.php`             — **BARU.** Penyakit umum di masyarakat + rekomendasi obat herbal.
- `sejarah_nabi.php`          — **BARU.** Sejarah singkat 25 Nabi & Rasul.
- `includes/info_publik.php`  — **BARU.** Helper kecil: fetch API publik + cache file (10 menit).

## API Publik yang dipakai

| Halaman          | Sumber                                                                                                        | API Key |
|------------------|---------------------------------------------------------------------------------------------------------------|---------|
| `berita.php`     | `https://api-berita-indonesia.vercel.app/cnn/{kategori}/` (CNN Indonesia)                                     | tidak   |
| `beasiswa.php`   | Endpoint berita yang sama, di-filter kata kunci "beasiswa/scholarship" + daftar kurasi (LPDP, Kemdikbud, dll) | tidak   |
| `kesehatan.php`  | Tidak ada API publik gratis terstandar untuk obat herbal Indonesia → **data kurasi** (Kemenkes / Badan POM).  | —       |
| `sejarah_nabi.php` | Tidak ada API tunggal gratis untuk Qashashul Anbiya → **data kurasi 25 rasul** (Al-Qur'an + Tafsir Ibnu Katsir). | —    |

Response API di-cache di `sys_get_temp_dir().'/sportapp_publik_cache/'` selama 10 menit
supaya halaman tetap cepat dan tidak boros request.

## PostgreSQL — PERLU DITAMBAHKAN?

**TIDAK.** Tidak ada tabel/kolom baru, tidak ada perubahan schema.
Semua fitur baru hanya membaca data dari API publik atau array statis di file PHP.
File `sportapp.sql` Anda tetap aman dan **tidak perlu diubah / diimport ulang**.

## Cara apply

1. Backup folder project Anda.
2. Extract zip ini, timpa file pada path yang sama persis.
3. Pastikan server PHP punya ekstensi **cURL** aktif (umumnya sudah aktif di XAMPP / Laragon / php-fpm default).
4. Pastikan folder temporer (`sys_get_temp_dir()`) bisa ditulis — biasanya `/tmp` di Linux atau `C:\Windows\Temp` di Windows; ini default, tidak perlu konfigurasi.
5. Buka `index.php` di browser → akan muncul section **"Info & Wawasan"** dengan 4 kartu shortcut.

## Catatan

- Tidak ada data lama yang dihapus.
- Tidak ada perubahan pada file lain di luar daftar di atas.
- Bila API berita sedang down, halaman tetap tampil dengan pesan "Gagal mengambil berita saat ini".

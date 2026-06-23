# Revisi R12 — 22 Juni 2026

Zip ini berisi **11 file yang direvisi** dari paket `sportapp_core.zip`.
Tinggal **copy-replace** ke folder project di lokal (overwrite file lama). Tidak ada perubahan skema PostgreSQL — semua revisi cukup PHP/JS dan dibuat **idempotent** (aman dijalankan berulang).

## Yang masuk
| # | File | Inti revisi |
|---|------|-------------|
| 1 | `admin/tempat.php` | CRUD dirapikan: validasi nama wajib, enum status divalidasi, semua operasi dibungkus `try/catch`, flash success/error ditampilkan di atas tabel. |
| 2 | `tempat_list.php` | Pagination 9 kartu/halaman (3×3 grid). |
| 3 | `tempat_list.php` | Filter pencarian & jenis pakai **AJAX** (`?ajax_list=1`) — tidak reload halaman, URL tetap di-update via `history.replaceState`. |
| 4 | `riwayat.php` | Filter **Kategori** & **Periode** pada leaderboard pakai **AJAX** (`?ajax_lb=1`). |
| 5 | `index.php` | Pagination Social Feed pakai **AJAX** (intercept tombol Sebelumnya/Berikutnya, fetch + swap `#sec-social-feed [data-live="feed"]`). |
| 6 | `monitoring.php` | Card "Penjelasan Metrik" dibungkus `<details>` (spoiler). |
| 7 | `run.php` | Dua card "Cara Penggunaan" dibungkus `<details>` (spoiler). |
| 8 | `islami.php` | Card "Countdown Hari Raya & Peristiwa" diduplikasi sebagai versi mobile-only (`d-md-none`) tepat di bawah "Tanya Jawab Islami". Versi desktop tetap di kolom kanan (`d-none d-md-block`). JS `islamiCountdown()` dipanggil 2x (untuk ID asli & ID `_m`). |
| 9 | `admin/absensi.php` | Daftar member internal diberi **pencarian + pagination client-side** (10 per halaman). |
| 10 | `admin/pengeluaran.php` | Filter, add, edit, delete, dan pagination dijalankan via **AJAX** (`?ajax_table=1`). Tidak reload halaman. |
| 11 | `admin/tim.php` | Pemain Eksternal **tidak lagi input manual**. Pilih dari dropdown nama tamu yang sudah diinput di `admin/absensi.php` (`member_eksternal.nama_tamu`). Validasi server-side memastikan nama benar-benar ada di sana, plus pencegahan duplikasi. |
| 12 | `admin/keywords.php` | Ditambah **panel keterangan** menjelaskan arti angka **0** dan **1** untuk kolom *Aktif* & *Urut*. |

## Hal yang perlu ditambahkan di PostgreSQL
**Tidak ada.** Semua tabel yang dibutuhkan (`member_eksternal`, `tim_external`, `search_keywords`, `pengeluaran_kegiatan`, dll.) sudah ada di `sportapp.sql` versi lama. File yang menyentuh skema (`admin/tempat.php`, `admin/tim.php`, `admin/keywords.php`) sudah memakai `CREATE TABLE/ALTER TABLE … IF NOT EXISTS` sehingga aman dijalankan otomatis saat halaman pertama dibuka.

## Catatan teknis
- AJAX semua memakai endpoint yang sama (halaman itu sendiri) dengan query param khusus (`ajax_list`, `ajax_lb`, `ajax_table`) → tidak perlu file baru.
- Pagination AJAX tetap meng-update URL (`history.replaceState`) supaya bisa di-bookmark / share.
- Spoiler `<details><summary>` adalah HTML native — tidak butuh JS / Bootstrap collapse tambahan.
- File-file lain di paket tidak diubah.

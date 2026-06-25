# REVISI R13 — 25 Juni 2026

Zip ini berisi **hanya file yang direvisi** untuk paket sportapp_core. Letakkan file-file di bawah ke root project (timpa file lama). Data lama (`sportapp.sql`) tidak diubah.

## Daftar file dalam zip

| File | Status | Untuk poin |
|---|---|---|
| `catatan_hafalan.php` | revisi | #1 Total Ayat dari target_ayat · #2 Filter pencarian + surat (AJAX) |
| `kajian.php` | revisi | #4 Kategori literatur · #5 Popup YouTube & Web iframe |
| `sejarah_nabi.php` | revisi | #6 Popup ayat rujukan · #7 Tabel kaum/azab/pemimpin zalim/peninggalan |
| `tajwid.php` | **baru** | #8 Fitur belajar tajwid |
| `islami.php` | revisi | #3 Urutan menu (Kajian di bawah Catatan Hafalan) · link Tajwid |
| `migrations_r13.sql` | **baru** | Kolom `kategori` di `islami_kajian` + tabel `tajwid_progress` |

## Langkah pemasangan (lokal)

1. Backup folder project Anda.
2. Ekstrak zip ini, lalu salin/timpa semua file PHP ke root project.
3. Jalankan migrasi PostgreSQL:
   ```bash
   psql -U <user> -d <database> -f migrations_r13.sql
   ```
   Migrasi bersifat **idempotent** (`IF NOT EXISTS`), aman dijalankan berulang dan **tidak menghapus data** lama.

## Apa yang perlu ditambahkan di PostgreSQL

Hanya file `migrations_r13.sql`. Isinya:
- `islami_kajian.kategori VARCHAR(60) DEFAULT 'Umum'`
- Index pencarian catatan hafalan.
- Tabel baru `tajwid_progress` (opsional — dipakai halaman Tajwid untuk centang materi).

Tabel `catatan_hafalan` dan `catatan_baca_buku` sudah auto-migrate di dalam PHP-nya, jadi tidak perlu langkah manual untuk keduanya.

## Catatan implementasi per poin

1. **Total Ayat** — stat card "Total Ayat (target)" mengambil nilai `SUM(target_ayat)` langsung. Card "Total Catatan" memakai `COUNT(*)`.
2. **Filter pencarian** — endpoint AJAX: `GET /catatan_hafalan.php?ajax=list&q=...&surat=...&page=...` mengembalikan partial HTML tabel + pagination. JS pada halaman me-fetch dengan debounce 300ms.
3. **Reorder menu** — Kajian Literatur Buku diletakkan tepat setelah Catatan Hafalan di `islami.php`.
4. **Kategori kajian** — dropdown kategori (Umum, Aqidah, Fiqih, Tafsir, Hadist, Sirah, Akhlak, Tazkiyah, Sains Islam, Sejarah Islam, Parenting, Ekonomi Syariah, Lainnya) pada form & filter pencarian.
5. **Popup Video / Web** — tombol `Lihat Video` membuka modal dengan iframe `youtube.com/embed/<id>` (auto-detect dari `youtu.be`, `watch?v=`, `/embed/`, `/shorts/`). Tombol `Buka Web` membuka modal iframe situs (catatan: sebagian situs memblokir iframe via `X-Frame-Options` — tersedia tombol "Buka di Tab Baru" sebagai fallback).
6. **Popup ayat sejarah nabi** — tombol "Lihat Ayat" pada detail nabi memunculkan modal yang memanggil `https://equran.id/api/v2/surat/{n}` (perlu koneksi internet). Parser mengenali format `QS. Nama:awal-akhir` atau `Q.S. <nomor>:ayat`.
7. **Tabel kaum & azab** — tab baru "Tabel Kaum & Azab" di `sejarah_nabi.php?tab=kaum` berisi 10 entri (Nuh, 'Aad, Tsamud, Luth, Madyan, Bani Israil/Fir'aun, Ashabus Sabt, Saba', Ninawa, Quraisy) dengan kolom: kaum, nabi, kondisi sosial, jenis azab, pemimpin zalim, peninggalan azab (termasuk jasad Fir'aun di Museum Kairo).
8. **Belajar Tajwid** — halaman `/tajwid.php` berisi 15 materi (Nun Sukun & Tanwin, Mim Sukun, Mad, Qalqalah/Lam Jalalah/Ra/Waqaf). Pengguna login bisa menandai "sudah dipelajari" untuk progres.

## Yang TIDAK termasuk

- Migrasi data sensitif/penghapusan baris apapun.
- File yang tidak terkait (artikel, kalkulator, dll) — tidak disertakan agar zip ringkas.

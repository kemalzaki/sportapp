# Revisi 25 Juni 2026

File yang diubah/ditambah (timpa file lama dengan path yang sama):

- includes/islami_helpers.php  → #1 koreksi kalender Hijriyah (+1 hari): 25/06/2026 = 10 Muharram
- doa.php                      → #2 editor terjemah doa kini WYSIWYG (bold/italic/underline/list)
- islami.php                   → #3 kartu Catatan Hafalan dipindah ke bawah Doa Harian
                                 #6 kartu "Catatan Baca Buku" baru di bawah Kajian Literatur Buku
- catatan_hafalan.php          → #4 Judul/Ref (jenis Quran) bisa diklik → popup ayat & surat (data dari Al-Qur'an Digital / equran.id)
                                 #5 Pagination daftar catatan (10/halaman)
                                 #7 tombol "Bersihkan" field di form Tambah
                                 #8 form otomatis kosong setelah edit + tombol "Batal / Form Baru"
- catatan_baca_buku.php (BARU) → #6 CRUD progress baca buku, data buku diambil dari Kajian Literatur (tabel islami_kajian)
- sportapp.sql                 → ditambah definisi tabel catatan_baca_buku (data lama tidak dihapus)

## Catatan PostgreSQL
Hanya ada SATU tabel baru: `catatan_baca_buku`.
Tabel ini dibuat OTOMATIS oleh catatan_baca_buku.php saat pertama dibuka (CREATE TABLE IF NOT EXISTS),
jadi tidak wajib menjalankan SQL manual. DDL juga sudah disertakan di sportapp.sql bila ingin import manual.

Popup ayat (#4) memerlukan koneksi internet (memanggil API equran.id) — sama seperti halaman quran_surah.php.

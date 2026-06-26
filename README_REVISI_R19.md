# Revisi R19 — Ringkasan Perubahan

Arsip ini **hanya berisi file yang direvisi**, bukan seluruh project.
Salin/timpa file-file berikut ke struktur project Anda:

```
includes/header.php       (Revisi 1 — tampilan desktop)
tempat_list.php           (Revisi 2 & 3 — kiloan + filter hiking)
admin/tempat.php          (Revisi 2 — input kiloan hiking di form admin)
admin/jadwal.php          (Revisi 4 — sudah ada di R18, tidak berubah file ini.
                           Tetap disertakan untuk referensi.)
migrations_r19.sql        (Migrasi PostgreSQL baru — wajib dijalankan)
```

> Catatan: `admin/jadwal.php` dan `index.php` sudah memuat fitur pilihan
> *Tim Kantor KK / Tim Public KK* sejak R18 (badge tampil otomatis di
> "Jadwal Terdekat" di `index.php`). Tidak ada perubahan baru di file
> tersebut pada revisi ini selain memastikan migrasi terpasang.

## 1. Tampilan Desktop Rapi (mirip HP)

Perbaikan di `includes/header.php` (blok `<style>` paling akhir):
- Drawer offcanvas (menu burger) sekarang muncul **di dalam frame ponsel
  480px**, bukan menempel ke tepi kiri layar Windows.
- `transform` saat tertutup juga digeser relatif terhadap frame, jadi
  animasi slide-in/out terasa konsisten dengan tampilan HP.
- Backdrop sedikit lebih gelap supaya area di luar frame tampak redup.

## 2 & 3. Kiloan Hiking + Filter Rentang Trek

- `tempat_list.php`:
  - Sumber kiloan sekarang `COALESCE(tempat.jarak_km, run_routes.jarak_m/1000)`,
    jadi admin tidak wajib menautkan `run_route_id` agar kiloan muncul.
  - Badge kiloan **hanya** tampil untuk jenis "Hiking" (sebelumnya
    juga Camping; sekarang dipersempit sesuai permintaan).
  - Untuk Hiking yang belum punya kiloan, ditampilkan placeholder
    `kiloan: —` agar admin tahu perlu diisi.
  - Filter rentang kiloan **hanya muncul** ketika dropdown jenis di
    pilihan "Hiking". Bila tidak, blok filter disembunyikan dan nilainya
    direset supaya tidak ikut terkirim.
- `admin/tempat.php`:
  - Tambahan input "Kiloan Trek (km)" di form create & edit (muncul di
    bagian khusus trail / Hiking).
  - Data disimpan ke kolom baru `tempat.jarak_km`.

## 4. Jenis Jadwal (Tim Kantor KK / Tim Public KK)

Sudah tersedia sejak R18 — kode `admin/jadwal.php` & `index.php` saat ini
sudah:
- Menyediakan CRUD master "Jenis Jadwal" di `admin/jadwal.php`.
- Menyediakan dropdown "Jenis Jadwal" pada form tambah & edit jadwal.
- Memuat dan menampilkan badge "Tim Kantor KK / Tim Public KK" di
  "Jadwal Terdekat" di `index.php`.

Pastikan migrasi `migrations_r18_26jun2026.sql` (atau migrasi R19
di arsip ini yang sudah memuatnya kembali) sudah dijalankan agar tabel
`jenis_jadwal` dan kolom `jadwal.jenis_jadwal_id` ada.

## Migrasi PostgreSQL yang perlu dijalankan

Jalankan **satu** file ini di PostgreSQL lokal:

```bash
psql -U <user> -d <db> -f migrations_r19.sql
```

File migrasi sepenuhnya **idempotent** (memakai `IF NOT EXISTS` dan
`ON CONFLICT DO NOTHING`) sehingga aman dijalankan berulang dan
**tidak menghapus data yang sudah ada**.

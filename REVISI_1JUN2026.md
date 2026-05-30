# Revisi 1 Juni 2026 — SportApp (PHP + PostgreSQL)

Arsip ini **hanya berisi file yang berubah**. Ekstrak ke root project dan
overwrite file dengan nama yang sama. Tidak ada data yang dihapus.

## Daftar file dalam arsip
- `jajanan.php` — Pesan Jajan (front-end publik)
- `kurir.php` — Kurir Jajanan (member)
- `admin/jajanan.php` — CRUD Jajanan (admin)
- `admin/pengeluaran.php` — Rekap Pengeluaran (admin)
- `config/db.php` — koneksi DB + auto-migration (ditambahi kolom `jajanan.lat`, `jajanan.lng`)
- `migrations_1jun2026.sql` — migrasi PostgreSQL idempotent (opsional, sudah otomatis jika `config/db.php` ini dipakai)

## Pemetaan ke poin revisi

| # | Poin                                                                  | File yang berubah                          |
|---|-----------------------------------------------------------------------|--------------------------------------------|
| 1 | Tombol "Pesan Sekarang" otomatis kirim notifikasi WA admin Firdam     | `jajanan.php`                              |
| 2 | Form pengecekan status pesanan via nama pemesan                       | `jajanan.php`                              |
| 3 | Kategori jajanan (filter)                                             | `jajanan.php`                              |
| 4 | Pagination 5 produk per halaman                                       | `jajanan.php`                              |
| 5 | Link Google Maps dari lat/lng pemesan di menu Kurir                   | `kurir.php`                                |
| 6 | Input lat/lng lokasi jajanan di CRUD Jajanan                          | `admin/jajanan.php`, `config/db.php`, SQL  |
| 7 | Badge kategori per item di list Jajanan halaman depan                 | `jajanan.php`                              |
| 8 | URL Bukti pengeluaran diarahkan ke ImageKit (upload file)             | `admin/pengeluaran.php`                    |

## PostgreSQL yang perlu ditambahkan

Hanya **2 kolom baru** di tabel `jajanan` (idempotent — aman bila dijalankan ulang):

```sql
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lat NUMERIC(10,6);
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lng NUMERIC(10,6);
```

Dua cara menerapkan:
1. Otomatis — cukup overwrite `config/db.php` dari arsip ini. Saat halaman
   pertama dibuka, auto-migration di `config/db.php` akan menambahkan kedua
   kolom tersebut bila belum ada.
2. Manual — jalankan `migrations_1jun2026.sql` lewat `psql`:
   ```bash
   psql "$DATABASE_URL" -f migrations_1jun2026.sql
   ```

Tidak ada perubahan skema lain. Kolom `pickup_lat` & `pickup_lng` di
`jajanan_pesanan` (yang dipakai poin #5) sudah ada sejak `migrations_31mei_v2.sql`.

## Catatan operasional

- **Nomor WA admin Firdam** dibaca dari env `ADMIN_WA_FIRDAM`
  (default `6281386369207`), sama seperti di `register.php` — tidak perlu
  konfigurasi baru.
- **Auto-open WhatsApp** sesudah pesanan sukses memakai `window.open`.
  Browser modern dapat memblokir popup; bila terblokir, pengguna tinggal
  menekan tombol *"Kirim Notifikasi WA ke Admin Firdam"* yang muncul di
  kartu pesanan sukses.
- **Upload bukti pengeluaran** memakai konfigurasi ImageKit yang sudah
  ada di `config/imagekit.php`. File disimpan di folder
  `/sportapp/pengeluaran/<tahun>/<bulan>/`. Format yang diterima:
  JPG/PNG/WEBP/GIF/PDF, maks 8 MB.
- **Lat/Lng jajanan** opsional — kolom kosong tidak menampilkan link maps
  pada CRUD admin.

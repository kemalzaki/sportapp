# REVISI 31 MEI 2026 (lanjutan) — sportapp_core

Arsip ini **hanya berisi file yang direvisi**. Salin/menimpa ke folder
project `sportapp_core` Anda dengan struktur yang sama.

## Daftar file

| File | Letak di project | Keterangan |
|---|---|---|
| `migrations_31mei2026_revisi.sql` | root project | Migrasi PostgreSQL: tambah kolom `jam_buka`, `jam_tutup` di tabel `jajanan`. |
| `admin/jajanan.php` | `admin/jajanan.php` | CRUD admin Jajanan + field jam buka/tutup pedagang. |
| `feed_islami.php` | `feed_islami.php` | Social feed dengan pagination **2 data per halaman**. |
| `jajanan.php` | `jajanan.php` | Halaman pembeli: PPN 11%, mobile 2/baris, foto klik = zoom, wajib deteksi lokasi sebelum Midtrans, auto-disable "Pesan" jika toko tutup. |

## Langkah deploy (lokal)

1. **Backup** dulu folder project lama (`sportapp_core`).
2. **Ekstrak** zip ini, lalu **timpa** ke folder project (file lama yang sama akan diganti).
3. **Jalankan migrasi PostgreSQL** (aman dijalankan berulang):
   ```bash
   psql -U <user> -d <database> -f migrations_31mei2026_revisi.sql
   ```
   Migrasi menambahkan dua kolom baru pada tabel `jajanan`:
   - `jam_buka  TIME`
   - `jam_tutup TIME`
   
   Untuk data lama, otomatis di-set default `07:00`–`21:00` agar tombol
   "Pesan" tidak otomatis disable. Bisa diubah lewat halaman admin
   `/admin/jajanan.php` (Edit per produk).
4. Buka halaman:
   - `/admin/jajanan.php` → atur jam buka/tutup per produk.
   - `/jajanan.php` → cek tampilan 2 kolom di mobile, ikon "Tutup", lightbox foto, PPN di ringkasan, dan tombol "Bayar via Midtrans" yang baru aktif setelah klik "Deteksi Lokasi Saya".
   - `/feed_islami.php` → cek pagination 2 quote per halaman.

## Catatan rinci

### 1. CRUD jam buka/tutup di admin
- Form tambah & modal edit di `admin/jajanan.php` punya 2 input baru
  (`jam_buka`, `jam_tutup`) bertipe `<input type="time">`.
- Validasi server-side: format `HH:MM` / `HH:MM:SS`, value invalid jadi `NULL`.
- Mendukung **jadwal lewat tengah malam** (mis. `22:00–02:00`).
- Kosongkan kedua jam = pedagang **dianggap selalu buka**.

### 2. Pagination social feed 2/hal
- `feed_islami.php` query `LIMIT 2 OFFSET ...` + UI pagination dengan
  jendela halaman (maks 7 angka) dan tombol prev/next.

### 3. PPN 11% di `jajanan.php`
- Konstanta `$PPN_RATE = 0.11` (UU HPP).
- Ditampilkan sebagai baris terpisah di ringkasan checkout:
  `Subtotal + PPN 11% + Ongkir = Total`.
- Dikirim ke Midtrans sebagai `item_details` terpisah (id `PPN11`)
  agar `gross_amount` cocok dengan jumlah `item_details`.

### 4. Mobile 2/baris
- Class kolom diubah dari `col-md-4 col-sm-6 col-12` → `col-md-4 col-6`,
  sehingga di lebar < 768 px (handphone) tampil 2 produk per baris.

### 5. Foto bisa di-klik untuk zoom
- Tiap `<img>` produk punya class `jjn-zoomable` + cursor `zoom-in`.
- Event delegation membuka modal Bootstrap `#zoomModal` dengan foto
  full-size (max 80vh, contain), klik gambar atau tombol X untuk tutup.
- Foto kecil di modal pemesanan juga ikut zoomable (kecuali yang ada
  di dalam tombol pesan).

### 6. Wajib deteksi lokasi sebelum Midtrans
- Tombol **Bayar via Midtrans** awalnya `disabled` dengan tooltip.
- Tombol aktif **hanya setelah** `navigator.geolocation` berhasil
  mengembalikan koordinat dan jarak ≤ batas layanan.
- Server-side juga menolak request `create_snap` jika `pickup_lat`/
  `pickup_lng` kosong dengan pesan jelas dalam bahasa Indonesia.

### 7. Tombol "Pesan" disable jika toko tutup
- Fungsi PHP `jjn_is_open($jam_buka, $jam_tutup)` membandingkan jam
  Asia/Jakarta sekarang dengan jam toko (mendukung lewat tengah malam).
- Kartu produk yang sedang tutup menampilkan badge merah
  *"Tutup"* dan tombol `Toko Tutup • 07:00–21:00` (disabled).
- Counter qty (+/-) juga ikut disable untuk produk yang tutup.
- Server-side guard di endpoint `create_snap` menolak pesanan jika
  toko tutup, menyebut jam operasional pada pesan error.

## Yang perlu Anda lakukan di PostgreSQL

Hanya **satu migrasi** baru:

```sql
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS jam_buka  TIME;
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS jam_tutup TIME;
```

(file lengkap: `migrations_31mei2026_revisi.sql`). Data lama tidak dihapus.

## Tidak diubah

Tidak ada perubahan ke React/JS framework. Stack tetap **PHP + PostgreSQL** seperti project asli. File lain (jadwal, kurir, kalender, dst.) tidak ada di zip ini supaya tidak menimpa hal yang tidak perlu.

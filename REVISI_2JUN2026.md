# Revisi 2 Jun 2026

File yang diubah / ditambah (timpa langsung di project lokal):

```
jajanan.php
admin/jajanan.php
includes/header.php
config/db.php
migrations_2jun2026.sql   <-- baru
```

## Ringkasan perubahan

1. **`jajanan.php` — teks stok dirapikan**: stok dipindah ke badge overlay
   kanan-atas kartu sehingga tidak menabrak tombol −/+.
2. **`jajanan.php` — Gojek-style**: tiap produk punya tombol **Pesan Sekarang**
   yang membuka modal berisi data pengantaran (nama, WA, alamat, catatan,
   deteksi lokasi) **dan** ringkasan pembayaran. Pembayaran hanya menerima
   **transfer/VA/QRIS/e-wallet via Midtrans**. Pesanan baru *masuk ke daftar
   admin* (`status='baru'`, `payment_status='paid'`, stok dikurangi) **setelah
   Snap Midtrans melaporkan `settlement`/`capture`**. Selama menunggu
   pembayaran, baris di `jajanan_pesanan` berstatus `pending_payment`.
3. **`jajanan.php` — tombol "Tanyakan Ketersediaan"** per produk yang membuka
   WhatsApp ke Admin Firdam dengan teks otomatis.
4. **`jajanan.php` — nomor telepon `+62`**: input WA pakai prefix `+62`
   (input-group), JS otomatis menghapus `0` / `62` / `+62` di awal yang
   diketik user. Di server dinormalisasi ke `62xxxx`.
5. **`includes/header.php` — navbar mobile**: ditambah CSS yang memaksa
   `position: fixed` di breakpoint mobile (≤ 991.98px) dengan
   `body { padding-top: 64px }` agar konten tidak ketutupan.
6. **`admin/jajanan.php` — upload foto**: fungsi `jjn_upload_imagekit_strict`
   sekarang melempar exception yang dipasang ke `$_SESSION['flash_err']`
   sehingga **alasan kegagalan upload terlihat** (mis. error PHP, format,
   ukuran, autoload composer, dll). Tambah & edit foto kembali berfungsi.
7. **`admin/jajanan.php` — tabel + pagination**: daftar produk pindah ke
   tabel responsif dengan **pagination 10/halaman**. Edit lewat modal.
8. **`jajanan.php` — kolom pencarian**: form `?q=` di atas grid yang
   mencari di `nama` & `deskripsi` (kombinasi dengan filter kategori).

## Yang perlu disiapkan di PostgreSQL

Jalankan `migrations_2jun2026.sql` (idempotent, tidak menghapus data).
Atau cukup buka halaman mana saja — `config/db.php` sudah memanggil
`ALTER TABLE ... ADD COLUMN IF NOT EXISTS` saat boot, sehingga kolom baru
otomatis tersedia:

- `jajanan_pesanan.payment_status` VARCHAR(20) DEFAULT 'pending'
- `jajanan_pesanan.midtrans_order_id` VARCHAR(40)
- `jajanan_pesanan.snap_token` VARCHAR(120)
- `jajanan_pesanan.snap_redirect` TEXT
- `jajanan_pesanan.stok_dipotong` BOOLEAN DEFAULT false

## Yang perlu disiapkan di environment (Midtrans)

Set variabel environment sebelum menjalankan PHP:

```bash
export MIDTRANS_SERVER_KEY="SB-Mid-server-xxxxxxxx"   # dari dashboard sandbox/prod
export MIDTRANS_CLIENT_KEY="SB-Mid-client-xxxxxxxx"
export MIDTRANS_PROD=""                                # kosong = sandbox, "1" = production
```

Kalau key belum di-set:
- Tombol "Bayar via Midtrans" akan menampilkan pesan *"MIDTRANS_SERVER_KEY
  belum disetel"*.
- Pesanan tidak akan masuk sampai pembayaran berhasil — sesuai requirement.

> Status pembayaran diverifikasi server-side via panggilan ke
> `https://api.sandbox.midtrans.com/v2/{order_id}/status` setelah Snap popup
> selesai, jadi tidak rentan dipalsukan dari sisi browser.

## Catatan

- Pesanan dengan status `pending_payment` **disembunyikan** dari hasil "Cek
  Status Pesanan" supaya pengguna tidak bingung.
- Status `baru` di admin = sudah dibayar dan siap diambil kurir, sesuai
  alur yang sebelumnya sudah ada.

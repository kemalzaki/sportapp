# Revisi 4 Juni 2026 — `jajanan.php`

Arsip ini **HANYA berisi file yang direvisi**. Salin ke folder
`sportapp_core/` yang sudah ada, **timpa file lama**. Data lama
tidak terhapus.

## File dalam arsip
| File | Perubahan |
|------|-----------|
| `jajanan.php` | (1) Tombol **Detail Pesanan** pada tabel hasil "Cek Pesanan Saya" → modal berisi rincian item, alamat, ongkir, total, **plus nama & nomor telpon kurir** (tombol Chat WhatsApp + Telepon). (2) Tombol **Lacak Driver** tetap (Leaflet realtime, ikon pemesan hijau & kurir merah berdenyut — sudah ada sejak 3 Jun 2026, di-retain). (3) **Preloader** fullscreen good-looking (cangkir + uap + progress bar) supaya pengguna HP tidak bosan menunggu. (4) **Rating bintang 1–5** per pesanan untuk status `selesai`, lengkap komentar opsional. (5) Tombol **Pesan Sekarang** sekarang membuka **Modal Toko** yang me-load semua produk milik toko tersebut via AJAX, lengkap input jumlah di samping tiap produk → satu transaksi bisa berisi banyak produk. (6) Efek tambahan: shimmer skeleton, hover-lift kartu, animasi fade-in/pop, chip live berdenyut, gradient halus pada header modal toko. |
| `migrations_4jun2026.sql` | **Migrasi PostgreSQL baru** — tambah kolom `rating`, `rating_komentar`, `rating_at` pada `jajanan_pesanan` + CHECK constraint 1..5 + index. Idempotent, tidak menghapus data. |

## Migrasi PostgreSQL (WAJIB)

Hanya satu migrasi baru yang perlu dijalankan:

```bash
psql -U <user> -d <db> -f migrations_4jun2026.sql
```

Pastikan migrasi-migrasi sebelumnya **sudah** dijalankan (semua idempotent):
`migrations_2jun2026.sql`, `migrations_31mei2026_revisi.sql`,
`migrations_31mei_v2.sql`, `migrations_2jun2026_toko.sql`,
`migrations_3jun2026.sql`. Jika sudah, tidak perlu dijalankan ulang.

## Endpoint AJAX baru di `jajanan.php`

| Endpoint | Method | Fungsi |
|---|---|---|
| `?ajax=detail_pesanan&kode=...` | GET | Detail pesanan + items + kontak kurir |
| `?ajax=toko_produk&toko_id=...` | GET | List produk satu toko (untuk modal Pesan Sekarang) |
| `?ajax=submit_rating` | POST | Simpan rating 1–5 + komentar |
| `?ajax=create_snap` | POST | **Sekarang menerima multi-item** via field `items` (JSON array `[{id,qty}, …]`). Tetap kompatibel dengan parameter lama `jajanan_id` + `qty`. |

## Alur baru "Pesan Sekarang" (multi-item per toko)
1. User klik **Pesan Sekarang** di kartu produk.
2. Modal Toko terbuka → fetch `?ajax=toko_produk&toko_id=<id>`.
3. Semua produk toko muncul dengan input jumlah (±). Produk yang dipilih awal otomatis ter-preselect dari qty pada kartu.
4. User isi nama/WA/alamat + **Deteksi Lokasi Saya** (wajib).
5. Submit → `?ajax=create_snap` (multi-item) → Snap Midtrans.

## Stack
Tetap **PHP + PostgreSQL** murni — tidak ada React, tidak ada
dependency npm baru. Leaflet & Bootstrap tetap di-load via CDN
seperti sebelumnya. Aman dijalankan di local.

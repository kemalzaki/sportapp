# Revisi 1 Juni 2026 — Tampilkan Toko + Tracking Driver

Arsip ini **HANYA berisi file yang direvisi** (tidak menggantikan semua file).
Salin ke folder `sportapp_core/` yang sudah ada, **timpa file lama**. Data lama tidak terhapus.

## Daftar file revisi

| File | Perubahan |
|------|-----------|
| `admin/jajanan.php` | **#1** CRUD Jajanan kini menampilkan kolom **Toko** (badge). Form Tambah & modal Edit mendapat dropdown **Toko / Pedagang** untuk meng-assign produk ke toko. |
| `admin/jajanan_pesanan.php` | **#3** Kolom *Item* diganti **Toko & Produk** — item pesanan dikelompokkan per toko/pedagang sehingga admin langsung tahu pesanan datang dari toko mana. |
| `jajanan.php` | **#2** Nama toko muncul di kartu produk pembeli. **#4a** Tabel hasil "Cek Status Pesanan Saya" dirapikan (kolom tidak tabrakan, responsif mobile, kolom Tgl auto-hide di layar kecil, scroll horizontal aman). **#4b** Tombol **Lacak Driver** kini membuka peta Leaflet realtime gaya Gojek dengan **ikon desain khusus** untuk Pemesan (hijau 👤), Kurir (merah 🛵 dengan animasi pulse), dan kampus UIN (biru 🎓), plus garis rute putus-putus antara kurir → pemesan. Polling lokasi tetap 5 detik + tombol Refresh manual. |

## Migrasi PostgreSQL

Tidak ada perubahan skema baru — file ini memanfaatkan kolom yang sudah dibuat oleh migrasi sebelumnya. **Pastikan migrasi-migrasi ini sudah dijalankan** (semuanya idempotent / `IF NOT EXISTS`, tidak menghapus data):

```bash
psql -U <user> -d <db> -f migrations_2jun2026_toko.sql      # tabel `toko` + kolom jajanan.toko_id
psql -U <user> -d <db> -f migrations_31mei2026_revisi.sql   # jam_buka, jam_tutup
psql -U <user> -d <db> -f migrations_31mei_v2.sql           # pickup_lat, pickup_lng
psql -U <user> -d <db> -f migrations_3jun2026.sql           # driver_lat, driver_lng, driver_loc_updated_at
```

Jika semua migrasi di atas sudah pernah dijalankan sebelumnya, **tidak perlu menjalankan apa-apa lagi** untuk revisi ini.

## Cara pakai

1. Buka **Admin → CRUD Toko/Pedagang**, tambahkan toko (mis. "Warung Bu Yati").
2. Buka **Admin → CRUD Jajanan**, pilih toko di dropdown saat menambah/edit produk.
3. Di halaman pembeli (`jajanan.php`), nama toko otomatis tampil di kartu produk.
4. Setelah pembeli checkout & kurir mengaktifkan "Mulai Berbagi Lokasi" di `kurir.php`, pembeli klik **Cek Status Pesanan Saya → Lacak Driver** → modal peta terbuka dengan ikon desain dan update realtime tiap 5 detik.

Stack tetap **PHP + PostgreSQL** (tidak diubah ke React). Leaflet & tile OSM via CDN, tanpa dependency tambahan.

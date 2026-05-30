# REVISI 1 Jun 2026 — Lanjutan (Batch 2)

Berisi 6 revisi tambahan. Hanya halaman yang berubah yang disertakan dalam zip.

## File yang berubah

1. **`admin/pengeluaran.php`** — Tambah tombol **Edit** (modal) di tiap baris + **pagination 5 entri** per halaman. Total Rp tetap menjumlah seluruh data (bukan hanya halaman aktif).
2. **`index.php`** — Pemutar **IPTV** sekarang **hanya aktif di tampilan desktop**. Pada handphone, tablet kecil, atau aplikasi Android (Capacitor WebView), player & daftar channel disembunyikan dan diganti notice "buka di desktop". Deteksi: UA mobile/Android/iOS/Capacitor + viewport ≤ 991px + `window.Capacitor`.
3. **`jajanan.php`** — Ongkir kini **dihitung otomatis dari jarak** UIN SGD Bandung ↔ titik lokasi pemesan (Haversine). Rumus: `Rp 3.000 + (km × Rp 2.000)`. Bila pemesan belum share lokasi, fallback Rp 5.000.
4. **`jajanan.php`** — **Pagination** produk **tidak lagi me-reset** Total Bayar (COD). Pilihan qty disimpan ke `sessionStorage`; saat berpindah halaman, item dari halaman lain otomatis di-render sebagai hidden input + dijumlahkan ke Subtotal/Total.
5. **`jajanan.php`** — Ditambahkan **keterangan jarak rekomendasi** pengantaran: maksimal **±1.5 km** dari pusat kampus UIN SGD Bandung; layanan maksimal sampai **3 km** dengan tambahan ongkir per km.
6. **`kurir.php`** — Tiap card pesanan kini menampilkan dua tombol Maps berlabel jelas: **Maps Pemesan** (dari `pickup_lat`/`pickup_lng`) dan **Maps Pedagang** (diambil dari `jajanan.lat`/`jajanan.lng` item pertama pesanan).

## Catatan PostgreSQL

Tidak ada tabel/kolom **baru** yang wajib ditambahkan untuk batch ini.

Prasyarat (sudah ada dari `migrations_1jun2026.sql` batch sebelumnya — pastikan sudah dijalankan):

```sql
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lat NUMERIC(10,6);
ALTER TABLE jajanan ADD COLUMN IF NOT EXISTS lng NUMERIC(10,6);
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS pickup_lat NUMERIC(10,6);
ALTER TABLE jajanan_pesanan ADD COLUMN IF NOT EXISTS pickup_lng NUMERIC(10,6);
```

Agar **Maps Pedagang** muncul di `kurir.php`, isi kolom `lat`/`lng` pada produk via halaman **admin/jajanan.php** (CRUD Jajanan). Bila belum diisi, kurir tetap melihat label "Maps Pedagang: -".

## Cara apply

Timpa file lama di environment lokal Anda dengan file dalam zip ini. Data tidak dihapus, hanya logika & UI yang diperbarui.

— Selesai —

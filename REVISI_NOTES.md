# Revisi sportapp_core — paket revisi

Berisi 8 file yang direvisi (dari 9 permintaan). File lain di zip induk
tidak berubah — cukup tumpuk/replace file berikut ke folder proyek:

```
index.php
paket_upgrade.php
profile.php
hidup_sehat.php
tempat_list.php
kalistenik.php
kalori_mingguan.php
includes/header.php
```

## Daftar Revisi

1. **kalistenik.php** — Tombol "Selesai hari ini" pada Monitoring untuk paket
   **Menengah** terkunci sampai Paket Pemula ≥ 3 sesi. Paket **Lanjutan**
   terkunci sampai Paket Menengah ≥ 4 sesi. Server juga menolak POST
   log untuk level yang masih terkunci.
2. **kalori_mingguan.php** — Kartu **Meal Recommendation (Pagi/Siang/Malam)**
   ditambahkan di atas ringkasan mingguan. Data diambil dari:
   - `users.berat_kg` & `users.tinggi_cm` (dari `profile.php` / `kesehatan.php`)
     → dihitung BMI & kategori (Kurus / Normal / Berlebih / Obesitas).
   - `upload_harian` 7 hari terakhir (dari `riwayat.php`) untuk menit &
     kalori lari → menyesuaikan rekomendasi karbohidrat pemulihan.
3. **includes/header.php** — Menu **Paket Bugar Kalistenik** dipindah dari
   grup "Info dan Wawasan" menjadi menu top-level tepat di atas grup
   "Paket Anak" pada Navigation Drawer.
4. **hidup_sehat.php** — Kartu panduan dibungkus dalam accordion (spoiler)
   Bootstrap agar halaman lebih ringkas.
5. **profile.php** — Ditambahkan tautan **"Cara ukur"** di sebelah kolom
   Tinggi Badan pada "Data Kesehatan (Publik)". Klik untuk membuka
   panduan langkah-langkah pengukuran tinggi badan yang benar.
6. **index.php** — Widget **Sapa Member Baru** dinonaktifkan (dibungkus
   `if (false)`), sesuai permintaan. Blok kode tetap ada agar mudah
   dihidupkan kembali bila dibutuhkan.
7. **paket_upgrade.php** — Harga paket **PRO (AI)** dan **KOMUNITAS**
   ditukar (baik pada tampilan kartu maupun pada array `$PLANS`
   yang dipakai untuk generate pesan WA / pesanan).
   - Komunitas: Rp 49.900 / Rp 79.900 (bulan), Rp 399.000 / Rp 699.000 (tahun).
   - PRO (AI):  Rp 19.900 / Rp 39.900 (bulan), Rp 149.000 / Rp 299.000 (tahun).
8. **includes/header.php** — Menu **Eksplorasi Rute & Peta Canggih** kini
   memiliki badge biru **"Paket Komunitas"** di navigasi drawer.
9. **tempat_list.php** — Modal detail Tempat diberi `margin-bottom` +
   padding tambahan agar tombol *Google Maps / Hubungi PIC / Halaman
   Detail* tidak tertutup navigasi bawah (bottom-nav), termasuk di
   layar mobile & perangkat dengan safe-area (iPhone dsb).

## PostgreSQL — perubahan skema

**Tidak ada tabel baru** yang diperlukan. Semua fitur di atas memakai
tabel yang sudah ada di `sportapp.sql`:

- `kalistenik_log(user_id, tanggal, level, catatan, created_at)` — sudah ada,
  dipakai untuk menghitung syarat unlock. Bila belum ada, gunakan DDL yang
  memang sudah dituliskan di header `kalistenik.php`:
  ```sql
  CREATE TABLE IF NOT EXISTS kalistenik_log (
    user_id  INT  NOT NULL,
    tanggal  DATE NOT NULL,
    level    VARCHAR(20) NOT NULL,
    catatan  TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, tanggal, level)
  );
  ```
- `users.berat_kg`, `users.tinggi_cm` — sudah ada (untuk BMI di Meal Recommendation).
- `upload_harian(user_id, tanggal, jenis, durasi_menit, kalori)` — sudah ada
  (untuk statistik lari 7 hari terakhir di Meal Recommendation).

**Data lama TIDAK dihapus.** Semua revisi hanya menambah UI/logic; skema
tidak ada breaking change.


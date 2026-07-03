# REVISI R5 — Juli 2026

Isi ZIP ini hanya file yang direvisi (bukan seluruh proyek). Timpa file
dengan path relatif yang sama di instalasi lokal Anda.

## Perubahan
1. **admin/members.php** — Daftar Member kini punya kolom **Username** (CRUD inline) dan
   kolom **Komunitas** (dropdown dari `admin/komunitas.php`). Tombol *Tambah Member*
   menggunakan **spoiler** (collapse) berisi field: Nama, Username, Email, Password,
   **Paket Member** (Gratis/Pro/Komunitas), dan **Komunitas**.
2. **includes/header.php** — Menu drawer *Data Komunitas* dihapus. Mapping
   `nav_feature_paket_map()` disesuaikan: label paket **Komunitas** di **Riwayat**,
   dan label paket di **Kalori Badminton/Renang/PingPong/Futsal**, **Toko Perlengkapan
   Olahraga**, **Lacak Faskes**, **Paket Anak (2-4, 4-6, 7-9, 10-12)**, serta
   **Paket Lansia (55-69, 70+)** dihapus (halaman jadi bebas akses).
3. **admin/komunitas.php** — Kolom *Data* diganti menjadi **Total Member**
   (COUNT users.komunitas_id).
4. **admin/paket_pesanan.php** — Ditambah tombol **Hapus** per baris + **Hapus Semua**.
5. **login.php** — Mekanisme login diganti menggunakan **username** (input teks),
   bukan dropdown nama. Fallback ke email tetap ada agar akun lama masih bisa masuk.
6. **REVISI_JULI_2026_R5.sql** — Migrasi PostgreSQL (idempotent).

## Cara pakai
1. Ekstrak isi ZIP ke root proyek (timpa file).
2. Jalankan SQL: `psql -d sportapp -f REVISI_JULI_2026_R5.sql`
3. Buka `/admin/komunitas.php` untuk membuat komunitas, lalu `/admin/members.php`
   untuk assign komunitas & paket ke member.
4. Coba login di `/login.php` menggunakan **username** akun.

## Catatan
- Tidak ada data yang dihapus oleh SQL di atas.
- Migrasi hanya menambah kolom/tabel jika belum ada.
- Akun tanpa `username` masih bisa login memakai email (fallback).

# Revisi R4 — Juli 2026 (KawanKeringat)

Isi arsip ini **hanya file yang direvisi** — silakan overwrite ke project Anda.

## Daftar file dalam ZIP

```
REVISI_JULI_2026_R4.sql       <-- WAJIB dijalankan sekali di PostgreSQL
assets/img/paket_hero.jpg     <-- gambar hero untuk paket_upgrade.php
includes/paket_helpers.php    <-- + logika expire otomatis + label sisa hari
includes/header.php           <-- + dropdown "Komunitas Organize"
includes/bottom_nav.php       <-- FAB Upload tidak terpotong, selalu di atas
admin/komunitas.php           <-- CRUD master Komunitas (baru)
admin/komunitas_data.php      <-- CRUD Data Komunitas berelasi (baru)
profile.php                   <-- keterangan paket + expire
user.php                      <-- rapikan tampilan + info paket
index.php                     <-- Jadwal Terdekat: kolom Komunitas + polish
paket_upgrade.php             <-- tema teal/cyan + hero image elegan
```

## Langkah instalasi

1. Salin seluruh isi ZIP ke root project (overwrite).
2. Jalankan migrasi PostgreSQL sekali:
   ```bash
   psql "$DATABASE_URL" -f REVISI_JULI_2026_R4.sql
   ```
   Migrasi bersifat **idempotent** (aman diulang) dan tidak menghapus data:
   - Menambah kolom `users.paket_started_at`, `users.paket_expires_at`.
   - Membuat tabel `komunitas` & `komunitas_data`.
   - Menambah kolom `jadwal.komunitas_id` (nullable) → dipakai di "Jadwal Terdekat".
3. Buka **Admin → Komunitas Organize → Komunitas** untuk mulai membuat komunitas,
   lalu **Data Komunitas** untuk mengisi detailnya.
4. Untuk menampilkan chip komunitas di "Jadwal Terdekat", isi kolom
   `jadwal.komunitas_id` (bisa lewat SQL manual, atau tambahkan di form jadwal Anda).

## Poin revisi

| # | Permintaan | File |
|---|---|---|
| 1 | Paket expire di profile + auto-downgrade | `profile.php`, `user.php`, `includes/paket_helpers.php`, SQL |
| 2 | Dropdown Komunitas Organize → Komunitas (CRUD) | `includes/header.php`, `admin/komunitas.php` |
| 3 | CRUD Data Komunitas (relasi ke komunitas) | `admin/komunitas_data.php` |
| 4 | Chip komunitas di Jadwal Terdekat | `index.php` |
| 5 | Rapikan Jadwal Terdekat | `index.php` |
| 6 | Tombol Upload selalu di atas, tidak terpotong | `includes/bottom_nav.php` |
| 7 | Rapikan `user.php` | `user.php` |
| 8 | Tema warna + hero elegan `paket_upgrade.php` | `paket_upgrade.php`, `assets/img/paket_hero.jpg` |

## Catatan auto-downgrade paket

`includes/paket_helpers.php` sekarang mengecek `paket_expires_at` setiap kali
paket user dibaca. Jika sudah lewat masa aktif → otomatis di-update jadi
`gratis` di database, tanpa perlu cron.

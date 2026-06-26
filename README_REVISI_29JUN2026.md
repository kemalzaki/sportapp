# Revisi 29 Juni 2026 — Patch Parsial

Arsip ini **HANYA berisi file yang direvisi** pada putaran ini. Salin
(overwrite) ke folder project Anda yang sudah ada — file/data lain tidak
diubah.

## File yang direvisi

| File | Perubahan |
| --- | --- |
| `includes/header.php` | (#1) Peringatan layar penuh saat dibuka di **desktop/tablet besar (≥ 900px)**: aplikasi hanya bisa dibuka di handphone, dengan instruksi install. Konten halaman di-block selama overlay aktif. |
| `riwayat.php`         | (#2) Kolom baru **Jenis Kegiatan** pada tabel *Riwayat Sesi* — menampilkan badge `Tim Kantor KK` / `Tim Public KK` (warna BG dari tabel `jenis_jadwal`). |
| `tempat_list.php`     | (#3) **Rentang Kiloan (Hiking)** sekarang berfungsi: ditambahkan listener `input` (debounce 450ms) untuk `min km` / `max km`, sehingga filter ter-trigger di mobile tanpa harus blur input. Enter & tombol *Reset KM* juga tetap berfungsi. |
| `user.php`            | (#4) Menampilkan **status Paket Member** (gratis / pro / komunitas) pada header profil, sinkron dengan `admin/members.php` via `includes/paket_helpers.php`. |
| `profile.php`         | (#4) Sama seperti di atas: badge **Paket Member** ditampilkan di kartu profil saya. |

## Database / PostgreSQL

**Tidak ada migrasi baru** untuk patch ini. Semua kolom & tabel yang
dipakai sudah ada di migrasi sebelumnya:

- `jenis_jadwal` + `jadwal.jenis_jadwal_id` → sudah dibuat di
  `migrations_r18_26jun2026.sql` (atau `migrations_r19.sql`).
  - Jika belum dijalankan, jalankan **sekali**:
    `psql ... -f migrations_r18_26jun2026.sql`
  - Pastikan tabel `jenis_jadwal` minimal berisi:
    - `Tim Kantor KK` (warna #0ea5e9)
    - `Tim Public KK` (warna #22c55e)
  - Lalu setiap baris di tabel `jadwal` perlu di-set `jenis_jadwal_id`-nya
    via halaman admin (`admin/jadwal.php`) supaya badge muncul di
    *Riwayat Sesi*. Sesi yang belum di-set akan menampilkan `—`.
- `users.paket` (VARCHAR default `'gratis'`) → sudah di-`ALTER` idempotent
  di `admin/members.php`. Patch ini juga aman tanpa migrasi terpisah karena
  `paket_user()` di `includes/paket_helpers.php` melakukan fallback ke
  `'gratis'` jika kolom belum ada.

Tidak ada data yang dihapus / diubah.

## Cara apply

Ekstrak `sportapp_patch_29jun2026.zip` lalu **timpa** ke folder project
Anda. Tidak perlu restart web server (PHP) — perubahan langsung aktif
di request berikutnya.

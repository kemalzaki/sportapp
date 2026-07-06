# Revisi Juli 2026 R11 — Ringkasan

File yang direvisi (letakkan sesuai path di zip):

- paket_perokok_jogging.php          — perbaikan link YouTube (5 video)
                                        & tabel Monitoring Jogging bisa
                                        di-scroll horizontal untuk mobile
- includes/header.php                 — user PRO tidak lagi melihat badge
                                        "Komunitas" untuk fitur yang juga
                                        tersedia untuk paket Pro
- index.php                           — submit story/feed tidak lagi membuka
                                        JSON di tab baru; jika JS interceptor
                                        gagal, POST akan di-redirect balik
                                        ke /index.php#feed
- leaderboard_islami.php              — cast enum (role::text) agar tidak
                                        error "invalid input value for enum
                                        user_role: koordinator"
- dm.php + api_dm.php                 — Direct Message dibatasi hanya ke
                                        sesama anggota komunitas user login
- upload.php                          — tambah field "Gear Sepatu Jogging"
                                        (create + edit + tampilan tabel)
                                        dan Upload via AI otomatis submit
                                        beserta screenshot sebagai bukti
                                        (tidak perlu klik Simpan lagi)

## PostgreSQL — SQL Tambahan (jalankan sekali)

Kolom baru untuk gear sepatu (idempotent, aman diulang):

```sql
ALTER TABLE upload_harian
  ADD COLUMN IF NOT EXISTS gear_sepatu VARCHAR(120);
```

(File upload.php juga sudah memiliki auto-migration `CREATE COLUMN IF NOT
EXISTS` sehingga otomatis dijalankan saat halaman pertama kali dibuka.)

Tidak ada perubahan schema lain. Tidak perlu drop / re-seed data apapun.

## Catatan Enum user_role

Kalau di database Anda BENAR ada role 'koordinator' / 'pic' tetapi enum
`user_role` belum memuatnya, silakan tambahkan:

```sql
ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'koordinator';
ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'pic';
```

Namun perubahan `role::text IN (...)` di leaderboard_islami.php sudah
membuat query tetap jalan tanpa perlu enum tambahan.

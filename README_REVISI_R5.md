# Revisi R5 — 22 Juni 2026

ZIP ini berisi **revisi parsial** untuk SportApp (PHP + PostgreSQL).
Salin file di dalamnya menimpa file lama dengan path yang sama (struktur folder dipertahankan).

## File yang direvisi

| File | Issue tersangkut |
|------|------------------|
| `migrations_r5.sql` | **WAJIB DIJALANKAN** — fix akar masalah ON CONFLICT (#6, #9, #10), dedupe badge (#12), dedupe like (#4) |
| `index.php` | #4 — like dipastikan hanya 1×/user (cek dulu sebelum INSERT, tidak rely ON CONFLICT) |
| `riwayat.php` | #7 — Riwayat Sesi: nama tidak double lagi (DISTINCT ON jadwal_id+user_id) |
| `kalori_mingguan.php` | #8 — Sisa Kalori: tampilan tanda diperbaiki (tidak lagi muncul "+-200"), formula ditampilkan di bawah panel<br>#11 — AI catatan: 'rincian' yang berisi raw JSON dibersihkan |
| `tempat.php` | #1 — tombol "Lihat Peta" + modal Leaflet (popup detail tempat: alamat, harga, parkir, tombol Rute/Street View/WA) |
| `dm.php` | #5 — kirim chat lebih tahan error: tampilkan alasan gagal dari server, log ke console, tidak diam saat response bukan JSON |
| `profile.php` | #12 — query badge: DISTINCT badge_id (tidak double) |
| `includes/badges.php` | #12 — `user_badges()` pakai DISTINCT ON, jadi tampilan badge user.php juga rapi |

## Yang HARUS dilakukan (PostgreSQL)

```bash
psql -d sportapp -U <user> -f migrations_r5.sql
```

Migrasi ini **idempotent** dan **tidak menghapus data** — hanya:
1. Menghapus baris-baris **duplikat** (jika ada) sebelum menambah UNIQUE/PK,
2. Menambahkan UNIQUE/PK constraint yang hilang pada tabel berikut:
   - `post_likes(post_id,user_id)` ← fix issue #4 (like server-side dedup)
   - `kalori_target(user_id)` PK ← fix issue #9 (target kalori submit)
   - `kalori_defisit_setting(user_id)` PK ← fix issue #9 (pengaturan defisit submit)
   - `gaya_hidup(user_id,tanggal)` ← fix error ON CONFLICT serupa
   - `user_olahraga_favorit(user_id,nama)` ← fix profile.php
   - `user_status_kesehatan(user_id)` PK ← fix profile.php sehat/sakit
   - `upload_harian_likes(upload_id,user_id)` ← fix issue #6 like aktivitas
   - `user_badges(user_id,badge_id)` ← fix issue #12 badge double
   - `notif_state`, `device_loc`, `fcm_tokens`, `strava_tokens`, `quran_bookmark`, `quran_catatan`, `tim_member`, `iptv_channels`, `checkin`, `doa_aamiin`, `post_bookmarks`, `post_views` (semua tabel yang punya ON CONFLICT di kode)
3. Menambah index `idx_dm_pair` agar polling dm.php lebih cepat,
4. Memastikan kolom `users.tema_warna` ada (dipakai profile.php untuk pengaturan tema).

## Issue yang BELUM dapat diselesaikan tuntas (perlu klarifikasi / data live)

| # | Issue | Status | Catatan |
|---|-------|--------|---------|
| 2 | Jadwal terdekat (`index.php`) tidak sesuai dengan `admin/jadwal.php` | **belum** | Kueri di `index.php` (line ~302) sudah `WHERE tanggal >= CURRENT_DATE ORDER BY tanggal ASC LIMIT 5`. Sama dengan admin/jadwal.php. Mohon kirim contoh data yang muncul vs yang seharusnya. Dugaan: filter `aktif`/`arsip` berbeda antara dua halaman — kirim screenshot, akan saya samakan. |
| 3 | Posting foto error (`index.php`) | **partially** | R4 sudah ganti video → multi-image. Pesan errornya apa persisnya? Dengan migrasi R5 (kolom `images_json` sudah dibuat di R4), upload jalan. Jika muncul "Gambar #1: ...", periksa konfigurasi `config/imagekit.php` (API key). |
| 13 | Tema warna & pengalaman tidak otomatis update di profile.php | **partial** | Sebenarnya POST `tema_warna` sudah `UPDATE users SET tema_warna=...` lalu `header('Location: profile.php')`. Cek browser cache; bila perlu hard-reload (Ctrl+Shift+R). Bila masih tidak update, kemungkinan `users.tema_warna` belum kepilih dari include/header. Akan dilanjutkan di R6. |

## Cara apply

1. Backup database & folder lama.
2. Ekstrak ZIP, salin file ke posisi yang sama (timpa).
3. `psql -d sportapp -U <user> -f migrations_r5.sql`
4. Hard-reload browser (Ctrl+Shift+R).
5. Tes:
   - **Like di feed** — like ulang harus tidak menambah angka.
   - **Riwayat Sesi** — buka detail sesi, tidak ada nama double.
   - **Pilih kategori riwayat** (`?cat=jarak`/`pace`/`kalori`/`konsisten`/`all`) — tidak ada error ON CONFLICT.
   - **Submit Target di kalori_mingguan** — sukses tanpa error DB.
   - **Tempat / Booking** — pilih tempat, klik **Lihat Peta** → modal muncul dengan peta + detail.
   - **Send chat dm.php** — bila masih gagal, lihat console (alert akan menampilkan alasan dari server).

## Catatan

- Tidak menyentuh struktur `sportapps_insert.sql` (data tetap utuh).
- Tidak ada perubahan ke React. Murni PHP + PostgreSQL.

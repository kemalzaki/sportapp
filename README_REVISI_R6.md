# Revisi 22 Juni 2026 R6 — Patch Halaman

Arsip ini **hanya berisi file yang berubah** pada R6. Salin / overwrite ke
folder project Anda yang sudah berisi versi sebelumnya. Tidak ada file yang
dihapus.

## Daftar file di zip ini

```
migrations_r6.sql      ← jalankan di PostgreSQL (idempotent, aman diulang)
index.php              ← perbaikan #1 (like Story dapat di-toggle)
riwayat.php            ← perbaikan #3 (jumlah Hadir/Total pakai DISTINCT user_id)
kalori_mingguan.php    ← perbaikan #4 (sisa kalori berkurang) & #5 (modal Detail Gizi)
api_dm.php             ← perbaikan #2 (kirim DM merespon JSON pada semua jalur error)
README_REVISI_R6.md    ← file ini
```

## Yang harus dijalankan di PostgreSQL

Hanya satu file SQL:

```bash
psql -h <host> -U <user> -d <db> -f migrations_r6.sql
```

`migrations_r6.sql` bersifat **idempotent** — boleh dijalankan berulang kali,
tidak akan menghapus data Anda. Skrip ini menambahkan:

| Tabel                        | Constraint                              | Untuk perbaikan       |
|------------------------------|-----------------------------------------|-----------------------|
| `absensi`                    | `UNIQUE (jadwal_id, user_id)`           | error ON CONFLICT di admin/absensi.php, admin/jadwal.php |
| `post_likes`                 | `UNIQUE (post_id, user_id)` (re-cek)   | like story / post     |
| `kalori_target`              | `PRIMARY KEY (user_id)` (re-cek)        | error ON CONFLICT di kalori_mingguan.php |
| `kalori_defisit_setting`     | `PRIMARY KEY (user_id)` (re-cek)        | sda                   |
| `badges`                     | `UNIQUE (kode)`                         | badge dobel di profile.php |
| `user_badges`                | `UNIQUE (user_id, badge_id)` (re-cek)  | badge dobel di user.php   |
| `absensi (jadwal_id,user_id)`| INDEX bantu                              | mempercepat riwayat.php  |

Jika sebelumnya Anda menjalankan `migrations_r5.sql` tetapi error
"there is no unique or exclusion constraint" tetap muncul, itu karena blok
`DO $$ ... EXCEPTION WHEN OTHERS THEN NULL` di R5 menelan kegagalan saat data
sudah berisi baris ganda. **R6 menjalankan dedup terlebih dulu**, jadi
`ALTER TABLE ADD CONSTRAINT` pasti berhasil.

## Detail tiap perbaikan

### 1) Like Story (di `index.php`) tidak berfungsi → **toggle**
Sebelumnya aksi `like` hanya INSERT (sekali like, tidak bisa dibatalkan).
Sekarang menjadi toggle: klik kedua kalinya akan menghapus like (unlike).
Tetap defensif terhadap DB tanpa UNIQUE.

### 2) Kirim chat DM (`dm.php` & floating chat) tidak berfungsi
`api_dm.php` sekarang **selalu mengembalikan JSON** (`{ok:false,err:...}`)
walau untuk rate-limit (429), CSRF gagal, atau DB gagal — sebelumnya 429
mengirim plaintext sehingga UI menampilkan error tidak jelas. INSERT pesan
dibungkus try/catch dan notifikasi push dipisah agar pesan tetap terkirim
meski tabel `notifications` belum lengkap.

### 3) Jumlah Hadir di Riwayat Sesi keliru
`riwayat.php` menghitung `hadir`, `telat`, `total`, dan `tamu` dengan
`COUNT(DISTINCT user_id)` (untuk tamu: `DISTINCT nama_tamu`). Jadi walau
tabel `absensi` masih punya baris ganda untuk satu jadwal+user, angkanya
tidak ikut menggelembung.

### 4) Sisa Kalori Hari Ini di `kalori_mingguan.php`
Rumus diubah:
- **Sebelum:** `sisa = target − (konsumsi − terbakar)` → bisa NAIK saat user
  mencatat pembakaran baru. Tidak intuitif.
- **Sekarang:** `sisa = target − konsumsi` (hanya makanan).
  Olahraga & pembakaran lain tetap ditampilkan di teks rincian sebagai
  informasi, tapi **tidak menambah** angka sisa.

### 5) Modal "Detail Gizi & Catatan" (`kalori_mingguan.php`)
Tata letak modal diperbarui: makro nutrien (Protein, Karbohidrat, Lemak,
Serat, Gula, Sodium) ditampilkan sebagai **grid kartu berwarna 2–3 kolom**.
Field yang nilainya kosong tetap muncul dengan tanda "—" supaya user tahu
apa saja yang dianalisis AI. Catatan AI yang berbentuk JSON ditampilkan
sebagai blok teks yang dapat di-scroll (tidak nabrak).

### 6) Badge dobel & badge lain hilang di `user.php` / `profile.php`
Sumber duplikat: tabel `badges` (master) dan/atau `user_badges` (per user)
pernah punya baris ganda. R6 melakukan dedup + menambah
`UNIQUE (badges.kode)` dan `UNIQUE (user_badges.user_id, badge_id)`. Fungsi
`user_badges()` di `includes/badges.php` sudah memakai `DISTINCT ON`
(sejak R5) — kombinasi keduanya menghilangkan tampilan dobel. Badge yang
"hilang" akan ter-recompute otomatis saat user membuka `profile.php`
(memanggil `recompute_badges`).

### 7) Error "no unique or exclusion constraint" pada
- `admin/absensi.php` → fix oleh constraint baru `absensi_unique_ju`.
- `admin/jadwal.php`  → sda (admin/jadwal trigger `apply_kondisi_to_absensi`).
- `kalori_mingguan.php` → fix oleh `kalori_target_pk` &
  `kalori_defisit_setting_pk` yang dipasang ulang oleh R6.

## Catatan
- Tidak ada perubahan stack: tetap **PHP + PostgreSQL**, tidak ada migrasi
  ke React/TS apapun.
- Halaman lain (dm_floating.php, profile.php, user.php, includes/badges.php,
  dst.) **tidak perlu di-overwrite** — perbaikannya sudah cukup dilakukan
  via SQL migration + JS handler yang sudah ada di file R5.

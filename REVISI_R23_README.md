# Revisi R23 — 27 Juni 2026

ZIP ini hanya berisi file yang **direvisi / ditambahkan**. Salin/ekstrak
ke root project `sportapp_core/` Anda (overwrite). Struktur folder di
dalam zip sudah sesuai dengan project (root, `includes/`, `admin/`).

## Daftar Perubahan

### 1. Menu Navigasi Baru — Paket Anak & Paket Lansia
File **DIUBAH**: `includes/header.php`

- Drawer mobile & navbar desktop: ditambahkan dropdown **Paket Anak**
  (di atas menu Tempat) dengan 4 submenu:
  - Usia 2–4 Tahun → `paket_anak_2_4.php`
  - Usia 4–6 Tahun → `paket_anak_4_6.php`
  - Usia 7–9 Tahun → `paket_anak_7_9.php`
  - Usia 10–12 Tahun → `paket_anak_10_12.php`
- Ditambahkan dropdown **Paket Lansia** (di bawah Paket Anak) dengan 2
  submenu:
  - Usia 55–69 Tahun → `paket_lansia_55_69.php`
  - Usia 70+ Tahun → `paket_lansia_70.php`

> Catatan: di soal disebut "3 Submenu" untuk Paket Anak, namun rincian
> daftar usia ada 4 (2–4, 4–6, 7–9, 10–12). Kami buatkan 4 submenu
> sesuai daftar usia. Jika hanya ingin 3, hapus satu link submenu di
> `includes/header.php` dan file `paket_anak_*.php`-nya.

File **BARU**:
- `includes/paket_age_render.php` — helper render halaman paket usia
- `paket_anak_2_4.php`, `paket_anak_4_6.php`, `paket_anak_7_9.php`,
  `paket_anak_10_12.php`
- `paket_lansia_55_69.php`, `paket_lansia_70.php`

### 2. CRUD Toko Perlengkapan Olahraga Terdekat
- `admin/toko_olahraga.php` (**BARU**) — CRUD lengkap (tambah / edit /
  hapus / toggle aktif) untuk daftar toko perlengkapan olahraga.
- `toko_olahraga.php` (**BARU**) — halaman user yang tampil di menu
  **Info dan Wawasan** (di bawah IPTV). Setiap toko punya tombol
  **Tanyakan & Pesan via WhatsApp**, tombol Telepon, dan tombol Lihat
  di Peta.
- `includes/header.php` (**DIUBAH**) — link "Toko Perlengkapan Olahraga
  Terdekat" ditambahkan di drawer (Info dan Wawasan, di bawah IPTV)
  dan di dropdown Admin (Pengaturan Lainnya & navbar desktop).

### 3. Tambahan Artikel Olahraga — Basket + Tips Perawatan
File **DIUBAH**: `artikel_olahraga.php`

- Olahraga **Basket** ditambahkan tepat di bawah Futsal, dengan struktur
  konten yang sama persis: Definisi, Cara Main, Pembagian Tim, Sistem
  Skoring, Sistem Pemenang & Kalah, Manfaat, Khasiat Penyembuhan,
  Hormon, Mental, Peralatan, AI Problem Solver, dan pencarian video
  YouTube. Palette warna & ilustrasi SVG animasi Basket juga sudah
  ditambahkan (`ao_anim_svg('basket', ...)`).
- **Tips Merawat Alat Olahraga** ditambahkan untuk setiap jenis
  olahraga (Lari, Badminton, Renang, Hiking, Ping Pong, Futsal,
  Basket, Biliar) dalam bentuk daftar bullet di dalam alert info.

## Migrasi PostgreSQL — WAJIB DIJALANKAN

File **BARU**: `migrations_r23_27jun2026.sql`

Jalankan sekali di PostgreSQL Anda:

```bash
psql "$DATABASE_URL" -f migrations_r23_27jun2026.sql
# atau di Alwaysdata / phpPgAdmin: tempel isi file ke SQL editor & run.
```

Yang dibuat:
- Tabel **`toko_olahraga`** (kolom: nama, alamat, kota, kategori,
  deskripsi, foto_url, wa_nomor, telp, jam_buka, lat, lng, map_url,
  rating, aktif, sort_order, created_at, updated_at).
- 2 indeks (`idx_toko_olahraga_aktif`, `idx_toko_olahraga_kota`).
- 2 baris contoh ("Sport Station" & "Planet Sports") hanya bila tabel
  masih kosong.

**Tidak ada DROP/DELETE** apa pun — semua data lama Anda tetap aman.

## Cara Pakai (Lokal)

1. Ekstrak zip ke root project (overwrite file yang sudah ada).
2. Jalankan `migrations_r23_27jun2026.sql` di PostgreSQL.
3. Akses lokal Anda — login sebagai admin, buka:
   - `/admin/toko_olahraga.php` → kelola toko
   - `/toko_olahraga.php` → tampilan user
   - `/paket_anak_2_4.php` … `/paket_lansia_70.php`
   - `/artikel_olahraga.php#basket` → cek Basket & Tips Perawatan.

Stack tetap **PHP + PostgreSQL native (`pg_*`)** — tidak ada perubahan
ke React / framework lain.

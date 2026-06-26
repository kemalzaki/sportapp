# Revisi R18 — 26 Juni 2026

Zip ini berisi **hanya file yang direvisi**, bukan seluruh project.
Tindih file dengan path yang sama di project lokal Anda.

## Daftar file
- `includes/header.php` — penguatan CSS agar tampilan desktop benar-benar
  mirip tampilan handphone (frame ~480px di tengah). Override paling akhir
  agar selalu menang specificity terhadap `desktop-fix.css` / bootstrap.
- `tempat_list.php` —
  - Menampilkan **kiloan (km)** pada kartu Hiking/Camping (sumber:
    `run_routes.jarak_m` via `tempat.run_route_id`).
  - Filter baru **Rentang Kiloan** (min/max) yang otomatis tersaring
    via AJAX, sinkron dgn URL.
- `admin/jadwal.php` —
  - CRUD baru untuk **Jenis Jadwal** (Tim Kantor KK / Tim Public KK / dst.)
    dengan warna BG + warna tulisan yg bisa diatur per jenis.
  - Select **Jenis Jadwal** di form Tambah & Edit Jadwal.
- `index.php` — menampilkan badge **Jenis Jadwal** ber-background warna
  pada bagian *Jadwal Terdekat*.
- `migrations_r18_26jun2026.sql` — **wajib dijalankan SEKALI** di PostgreSQL.

## Yang perlu ditambahkan di PostgreSQL
Jalankan:
```bash
psql -d sportapp -f migrations_r18_26jun2026.sql
```
Migrasi membuat:
- Tabel `jenis_jadwal (id, nama UNIQUE, warna_bg, warna_text, created_at)`
- Seed default: `Tim Kantor KK` (#0ea5e9/#ffffff) dan `Tim Public KK` (#22c55e/#ffffff)
- Kolom `jadwal.jenis_jadwal_id` (FK ke `jenis_jadwal`, NULL diperbolehkan)
- Index `jadwal_jenis_jadwal_idx`

Tidak ada data yg dihapus / diubah.

## Catatan filter kiloan
- Sumber jarak adalah `run_routes.jarak_m` (meter) yang ditautkan ke
  `tempat.run_route_id`. Tempat hiking yg belum punya rute terhubung
  akan **tidak muncul** ketika filter rentang kiloan diisi.
- Untuk menampilkan kembali semua, kosongkan kolom min/max atau klik
  tombol **Reset KM**.

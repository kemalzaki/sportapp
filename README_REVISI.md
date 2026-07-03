# Revisi R6 (Juli 2026) — SportApp Core

Arsip ini berisi HANYA file yang direvisi. Timpa file dengan struktur yang sama pada proyek utama.

## Daftar Perubahan

1. **admin/members.php** — Tambah kolom Username & Komunitas, CRUD komunitas untuk member,
   spoiler "Tambah Member" dengan field Username, Komunitas, dan Paket Member.
2. **admin/komunitas.php** — Kolom "Data" diganti "Total Member".
3. **admin/paket_pesanan.php** — Sudah memiliki fitur hapus + hapus semua (dipastikan aktif).
4. **includes/header.php** — Menghapus label "Paket Komunitas" pada menu Riwayat;
   menu "Data Komunitas" (child dari Daftar Komunitas) dihapus.
5. **includes/paket_helpers.php** — Helper `paket_require_or_lock()` untuk mengunci halaman.
6. **Halaman terkunci untuk Pro & Komunitas** (guard `paket_require_or_lock('pro', ...)`):
   - `kalori_badminton.php`, `kalori_renang.php`, `kalori_pingpong.php`, `kalori_futsal.php`
   - `toko_olahraga.php`, `lacak_faskes.php`
   - `paket_anak_2_4.php`, `paket_anak_4_6.php`, `paket_anak_7_9.php`, `paket_anak_10_12.php`
   - `paket_lansia_55_69.php`, `paket_lansia_70.php`

## Migrasi PostgreSQL

Jalankan `migration_r6.sql` pada database yang sudah ada:

```bash
psql -U <user> -d <db_name> -f migration_r6.sql
```

Migrasi menambahkan (idempotent, TIDAK menghapus data lama):
- `users.username` (VARCHAR 64, unique case-insensitive, nullable)
- `users.komunitas_id` (INTEGER, FK → `komunitas(id)` ON DELETE SET NULL)
- `users.paket` (VARCHAR 20, default `'gratis'`, CHECK ∈ {gratis, pro, komunitas})

Tabel `komunitas` diasumsikan sudah ada dari schema utama.

# Revisi R10 (Juli 2026) — sportapp

Berkas yang direvisi (hanya ini yang perlu di-replace di folder proyek lokal):

- `admin/jadwal.php`
- `index.php`

## Ringkasan perubahan

### 1. `admin/jadwal.php` — CRUD Komunitas pada Kegiatan
- **Tambah Jadwal**: dropdown **Komunitas** baru (hanya tampil untuk superadmin / komunitas SuperDuperAdmin). Admin biasa otomatis memakai komunitas primer akunnya (perilaku R9 tetap dipertahankan).
- **Edit Jadwal (modal)**: dropdown Komunitas dengan nilai terpilih sesuai `jadwal.komunitas_id`.
- **List tabel**: kolom **Komunitas** baru (badge berwarna sesuai `komunitas.warna`).
- **UPDATE query** kini menyimpan `komunitas_id` (super boleh pindah komunitas; non-super dipaksa ke `scope_primary_kom_id()` sehingga tidak bisa "keluar" dari komunitas mereka).
- **INSERT query** memakai `komunitas_id` dari form bila super; jika kosong / non-super fallback ke `scope_primary_kom_id()`.

### 2. `admin/jadwal.php` — "Tambah Data" jadi Spoiler
- Kartu **Tambah Jadwal** kini dibungkus Bootstrap `collapse` (`#tambahJadwalPanel`). Tertutup default; ada tombol **Buka / Tutup** di header kartu.

### 3. `index.php` — Kolom Komunitas di kartu "Jadwal Terdekat"
- Ditambahkan kolom **Komunitas** (chip warna komunitas + kota bila ada). Rows tanpa komunitas menampilkan chip abu-abu "Tanpa Komunitas".
- `colspan` baris detail absensi disesuaikan (9 → 10) agar tidak melompati kolom.
- Query loader `$jadwalTerdekat` sudah menyertakan `k.nama, k.warna, k.kota` sejak R4, jadi tidak ada perubahan SQL.

## PostgreSQL
Tidak ada perubahan skema baru. Semua kolom yang dipakai (`jadwal.komunitas_id`, `komunitas.id/nama/warna/kota`) sudah ada dari revisi sebelumnya (R7/R9).

Bila database lokal Anda belum punya kolom `jadwal.komunitas_id`, jalankan (idempotent):

```sql
ALTER TABLE jadwal ADD COLUMN IF NOT EXISTS komunitas_id INT REFERENCES komunitas(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_jadwal_komunitas_id ON jadwal(komunitas_id);
```

Data existing **tidak dihapus**. Baris `jadwal` lama yang `komunitas_id` masih NULL akan tampil sebagai "Tanpa Komunitas" di kartu Jadwal Terdekat dan tidak terlihat oleh non-super (sesuai scoping R9). Untuk backfill opsional (contoh: semua ke komunitas id=1):

```sql
UPDATE jadwal SET komunitas_id = 1 WHERE komunitas_id IS NULL;
```

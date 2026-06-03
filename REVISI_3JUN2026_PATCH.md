# Revisi Patch — 3 Juni 2026

Arsip ini berisi **hanya file yang berubah/baru**. Letakkan file-file
ini di lokasi yang sama pada project `sportapp_core` Anda (timpa file lama).

## Daftar file

| File | Status | Keterangan |
|---|---|---|
| `kalori_badminton.php` | revisi | Sekarang full **CRUD** (tambah, edit, hapus) + riwayat berpaginasi |
| `kalori_renang.php` | **baru** | Kalkulator kalori renang, CRUD + riwayat (5 gaya/intensitas) |
| `admin/tim.php` | revisi | Bisa menambah **wasit** selain pemain; toggle peran & badge terpisah |
| `admin/event_absensi.php` | **baru** | Input absensi peserta event mirip `admin/absensi.php` |
| `includes/header.php` | revisi | Menambah link menu "Kalori Renang" dan "Input Absensi Event" (admin) |

## Perubahan PostgreSQL (otomatis — tidak perlu jalan manual)

Halaman menjalankan statement berikut dengan `IF NOT EXISTS`, jadi **aman
dan tidak menghapus data**:

1. Tabel baru untuk riwayat kalkulator kalori (dipakai badminton & renang):

```sql
CREATE TABLE IF NOT EXISTS kalori_log (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    jenis        VARCHAR(40) NOT NULL,      -- 'badminton' | 'renang'
    intensitas   VARCHAR(40) NOT NULL,
    berat_kg     NUMERIC(5,1) NOT NULL,
    menit        INTEGER     NOT NULL,
    met          NUMERIC(4,2) NOT NULL,
    kalori       NUMERIC(7,2) NOT NULL,
    dibuat_pada  TIMESTAMP   NOT NULL DEFAULT now()
);
```

2. Kolom peran untuk membedakan pemain vs wasit di tim:

```sql
ALTER TABLE tim_member
    ADD COLUMN IF NOT EXISTS peran VARCHAR(20) NOT NULL DEFAULT 'pemain';
-- nilai valid: 'pemain' | 'wasit'
```

3. Absensi event memakai kolom `status` dan `keterangan` yang **sudah ada** di
   tabel `event_peserta` — tidak perlu perubahan skema.

> Jika Anda ingin menambah kolom-kolom di atas secara manual via psql/DBeaver
> dulu (mis. di server produksi), silakan jalankan SQL di atas — aman karena
> menggunakan `IF NOT EXISTS`.

## Catatan ringkas

- `kalori_badminton.php` & `kalori_renang.php` menggunakan tabel sama
  (`kalori_log`) dengan kolom `jenis` sebagai pembeda.
- `admin/tim.php`: kuota tim hanya menghitung pemain. Wasit tidak terhitung
  kuota; ada tombol toggle ⇄ untuk berubah peran.
- `admin/event_absensi.php`: muncul di sidebar admin di bawah "Input Absensi".
  Tombol status (Hadir/Telat/Izin/Sakit/Absen) + kolom keterangan, mirip
  absensi jadwal latihan.

# Revisi 3 Juni 2026 — Event & Absensi

File yang direvisi (timpa file lama dengan path yang sama):

- `index.php`
- `event.php`
- `admin/event.php`
- `admin/event_absensi.php`

## Ringkasan perubahan

1. **index.php → Event Terdekat** kini menampilkan ringkasan absensi (badge Hadir/Telat/Izin/Sakit/Absen/Belum) + tombol expand untuk melihat daftar peserta lengkap dengan status & catatan, persis pola "Jadwal Terdekat".

2. **event.php** — di halaman detail event sekarang tampil kartu ringkasan absensi dan tabel peserta dengan kolom Status + Catatan (mirip `admin/event_absensi.php`).

3. **admin/event_absensi.php** — blok **"Tambah Peserta Cepat"** diganti jadi **"Tambah Tamu Eksternal"** (sama seperti `admin/absensi.php`). Untuk menambah member peserta, gunakan `admin/event.php`. Tamu yang ditambahkan bisa dihapus.

4. **admin/event_absensi.php** — query peserta sekarang **dedup** (`DISTINCT ON (user_id, tim_id)` dengan prioritas baris yang sudah ber-status). Data lama yang sebelumnya dobel hanya muncul satu kali tanpa menghapus data di database. Query peserta di `event.php` dan `admin/event.php` juga ikut di-dedup.

5. **admin/event.php** — CRUD absensi sekarang langsung tersedia di tiap kartu event (collapsible "Input / Kelola Absensi Kehadiran Peserta"), tersedia juga link cepat ke `event_absensi.php` versi halaman penuh.

6. **admin/event.php** — CRUD **gambar event** via ImageKit: upload (max 5MB, JPG/PNG/WEBP), ganti, dan hapus banner. Disimpan ke folder `/sportapp/event` di ImageKit dan URL-nya ke kolom `event.banner_url` (sudah ada di skema).

## Catatan PostgreSQL yang perlu ditambahkan

Hanya **1 tabel baru** untuk fitur Tamu Eksternal Event. Tabel ini **dibuat otomatis** (auto-migrate `CREATE TABLE IF NOT EXISTS`) saat halaman `admin/event_absensi.php` pertama kali dibuka, jadi tidak ada langkah manual yang wajib. Jika ingin membuat manual:

```sql
CREATE TABLE IF NOT EXISTS event_tamu (
    id BIGSERIAL PRIMARY KEY,
    event_id INTEGER NOT NULL REFERENCES event(id) ON DELETE CASCADE,
    nama_tamu VARCHAR(120) NOT NULL,
    dibawa_oleh_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_event_tamu_event ON event_tamu(event_id);
```

Tidak ada perubahan skema lain. Kolom `event.banner_url`, `event_peserta.status`, `event_peserta.keterangan` sudah ada di `sportapp.sql` yang dikirim sebelumnya.

## Tentang "data double" di event_absensi.php

Penyebab: ada baris `event_peserta` yang tertambah dua kali untuk user yang sama (mis. user_id=3 punya baris `id=16` ber-status 'hadir' dan `id=19` tanpa status). Solusi yang dipakai: query pakai `DISTINCT ON (user_id, tim_id)` dan memprioritaskan baris yang sudah punya status. Data lama **tidak dihapus**. Jika ingin membersihkan duplikat secara permanen, jalankan manual:

```sql
DELETE FROM event_peserta a
USING event_peserta b
WHERE a.event_id = b.event_id
  AND COALESCE(a.user_id,0) = COALESCE(b.user_id,0)
  AND COALESCE(a.tim_id,0)  = COALESCE(b.tim_id,0)
  AND a.id < b.id
  AND (a.status IS NULL OR a.status = 'absen');
```
(opsional — tidak wajib karena tampilan sudah dedup)

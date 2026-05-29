# Catatan Revisi (hanya file yang diubah ada di zip ini)

File yang direvisi (timpa ke project lokal Anda):

- `config/db.php` — session/cookie 30 hari + auto-migrasi kolom baru
- `includes/security.php` — session timeout dinaikkan ke 30 hari (member tetap login)
- `includes/footer.php` — fix syntax error (WYSIWYG & soft-refresh real-time kembali aktif)
- `admin/jadwal.php` — dropdown Tempat ter-filter sesuai Jenis olahraga
- `tempat_list.php` — tombol "Lihat di Google Maps" pada detail tempat
- `riwayat.php` — Riwayat Sesi / Publik / Saya: pagination per 5 data
- `index.php` — kartu **Event Terdekat** + quick absen (Hadir/Izin/Sakit) untuk Jadwal & Event, real-time tanpa reload

## PostgreSQL — perlu dijalankan?

**Tidak perlu jalankan SQL manual.** `config/db.php` sudah berisi auto-migrasi
yang akan menambahkan kolom berikut secara otomatis pada saat aplikasi
pertama kali diakses (jika belum ada):

```sql
ALTER TABLE event_peserta ADD COLUMN IF NOT EXISTS status   TEXT NOT NULL DEFAULT 'hadir';
ALTER TABLE event_peserta ADD COLUMN IF NOT EXISTS keterangan TEXT;
```

Kalau Anda ingin menjalankan manual (mis. server tidak mau auto-migrate),
cukup eksekusi dua baris ALTER di atas pada database PostgreSQL Anda.
Tidak ada data yang dihapus.

## Cara apply

1. Backup folder project Anda.
2. Extract zip ini, replace file dengan path yang sama persis.
3. Refresh aplikasi di browser — auto-migrasi akan jalan sekali.

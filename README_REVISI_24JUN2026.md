# Revisi 24 Juni 2026 — sportapp_core

Arsip ini berisi **hanya file yang direvisi** (bukan seluruh project). Salin/timpa ke project lama Anda.

## Daftar file yang berubah

| File                          | Perubahan                                                                                  |
|-------------------------------|--------------------------------------------------------------------------------------------|
| `riwayat.php`                 | (1) Tambah kategori leaderboard **"Penggaet Teman Eksternal Terbanyak"**. (8) Pindahan **Tren Kehadiran Mingguan semua anggota** dari `monitoring.php` ke sini. |
| `monitoring.php`              | (8) Tren kehadiran mingguan kini **personal** (per user yang login).                       |
| `index.php`                   | (2) Tampilkan **Anggota Hadir Eksternal** (member_eksternal) di **Jadwal Terdekat** + badge **E**. |
| `event.php`                   | (3) Tampilkan badge **Kategori Pelaksanaan** (internal / eksternal + penyelenggara).        |
| `admin/event.php`             | (3) CRUD field **Kategori Pelaksanaan** + **Penyelenggara Eksternal** (mis. "UNPAD"). Auto-migration kolom. |
| `admin/pengeluaran.php`       | (4) Filter Jadwal diubah jadi **Per Bulan** (YYYY-MM). Tetap ada opsi pilih jadwal spesifik. |
| `flyover.php`                 | (5) Hasil pencarian YouTube kini punya tombol **"Ekstrak MP3 & pakai untuk Rekam Video"**.  |
| `api_yt_mp3.php` *(BARU)*     | (5) Endpoint ekstraksi MP3 dari YouTube (butuh `yt-dlp` + `ffmpeg`).                        |
| `tempat_list.php`             | (6) Peta detail kini **MapBox** (tile sama dengan `run.php`).                              |
| `includes/header.php`         | (7) Navbar desktop ditampilkan ulang ≥992px, warna **disamakan dengan top header mobile** (`#0f172a → #1e293b → #243049`). Logo brand pakai **gambar `hapfam-logo.png`**, bukan ikon petir. |

## Penambahan PostgreSQL yang diperlukan

Hanya **satu** perubahan skema yang baru (sudah auto-migrate juga saat `admin/event.php` pertama kali dibuka oleh admin, tapi disarankan jalankan manual untuk jelas):

```sql
ALTER TABLE event ADD COLUMN IF NOT EXISTS kategori_pelaksanaan VARCHAR(20) NOT NULL DEFAULT 'internal';
ALTER TABLE event ADD COLUMN IF NOT EXISTS sumber_eksternal TEXT;
```

> Tidak ada data yang dihapus. Field bersifat aditif dengan default `'internal'`, jadi semua event lama tetap valid.

## Catatan menjalankan `api_yt_mp3.php` (revisi #5)

Endpoint ini butuh **yt-dlp** dan **ffmpeg** ada di `PATH` user yang menjalankan PHP-FPM / php -S. Karena dijalankan lokal:

```bash
# Linux/Mac
pip install -U yt-dlp
sudo apt install ffmpeg     # atau: brew install ffmpeg

# Windows (PowerShell, scoop)
scoop install yt-dlp ffmpeg
```

File hasil ekstraksi disimpan ke `uploads/yt_mp3/<videoId>.mp3` (folder dibuat otomatis). Jika tool belum terpasang, endpoint akan mengembalikan JSON dengan pesan jelas dan UI flyover akan menampilkan alert.

## Catatan lain

- File `.sql` di project lama tidak perlu di-load ulang. Cukup jalankan `ALTER TABLE` di atas. Data Anda **tidak disentuh**.
- Tidak ada perubahan stack (tetap PHP + PostgreSQL, dijalankan lokal).

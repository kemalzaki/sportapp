# Revisi R22 (27 Juni 2026)

Zip ini berisi **file-file yang direvisi saja**, bukan seluruh proyek.
Salin (overwrite) ke folder proyek lokal Anda lalu jalankan migrasi.

## File yang diubah / ditambah

| File | Status | Keterangan |
|------|--------|------------|
| `migrations_r22_27jun2026.sql` | **baru** | jalankan dulu di PostgreSQL |
| `includes/paket_helpers.php`   | revisi  | + `paket_require_or_lock()` & gate komunitas |
| `includes/header.php`          | revisi  | + menu "Informasi Opini Terkini/Viral" |
| `islami.php`                   | revisi  | + Monitoring Tahajud & Duha Bulanan |
| `index.php`                    | revisi  | + Kabari Grup WhatsApp |
| `iptv.php`                     | revisi  | gate **PRO** |
| `kalistenik.php`               | revisi  | gate **PRO** (Paket Bugar) |
| `tempat.php`                   | revisi  | gate **KOMUNITAS** |
| `tempat_list.php`              | revisi  | gate **KOMUNITAS** |
| `artikel_olahraga.php`         | revisi  | gate **KOMUNITAS** |
| `cedera_olahraga.php`          | revisi  | gate **KOMUNITAS** + Map Puskesmas/RS Terdekat |
| `survival.php`                 | revisi  | gate **KOMUNITAS** + Spoiler + Forest Finder |
| `cuaca.php`                    | revisi  | hapus label sumber + rekomendasi jogging |
| `opini_viral.php`              | **baru** | halaman opini viral + analisis sentimen |

## Cara memasang

1. Backup folder proyek lama.
2. Ekstrak `sportapp_revisi_r22.zip`, overwrite ke folder proyek.
3. Jalankan migrasi PostgreSQL:
   ```bash
   psql -U <user> -d <db_name> -f migrations_r22_27jun2026.sql
   ```
4. Opsional: set link grup WhatsApp komunitas via menu Admin → Pengaturan,
   atau langsung di tabel `app_settings`:
   ```sql
   UPDATE app_settings SET sval='https://chat.whatsapp.com/XXXXXX'
   WHERE skey='wa_grup_link';
   ```
5. Refresh aplikasi. Tidak ada data lama yang dihapus.

## Catatan teknis

- Map & rute menggunakan **Leaflet + OpenStreetMap + Overpass API + OSRM**
  (gratis, tanpa API key). Aktifkan akses internet di server lokal.
- Pencarian hutan per-provinsi memakai daftar kurasi (lihat
  `survival.php` → `$PROVINCE_FORESTS`); titik dapat ditambah manual.
- Halaman opini viral menarik data dari Google News RSS publik. Hasilnya
  di-cache ke tabel `opini_viral` (TTL ±30 menit). Sentimen ditentukan oleh
  kamus kata-kunci sederhana di `opini_viral.php`.

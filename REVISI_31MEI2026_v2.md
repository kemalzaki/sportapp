# Revisi 31 Mei 2026 v2

## Daftar perubahan
1. **run.php** — tambah tombol **Jeda / Lanjutkan / Stop (Selesai)**, Wake Lock + audio silent agar tracking jalan terus saat HP / layar mati, filter akurasi & lompatan agar rute tidak kacau, tombol export GPX/KML per riwayat.
2. **api_run.php** — endpoint export `?export=ID&fmt=gpx|kml` (bisa diimpor ke Google My Maps / Strava / Google Earth).
3. **includes/shalat_data.php + islami.php** — perbaikan rawatib Maghrib & Isya (sekarang ba‘diyah muakkad), tambah panduan **Shalat Duha** & **Shalat Tahajud** (waktu, rakaat, tata cara, doa, fadhilah).
4. **api_device_loc.php + admin/lacak.php + includes/header.php** — fitur **Lacak HP Member** untuk admin (heartbeat lokasi tiap 2 menit selama user login & buka aplikasi).
5. **index.php** — Social Feed di-gate hanya untuk role `member` / `admin` (guest melihat ajakan login).
6. **admin/jajanan.php** — foto otomatis diarahkan & disimpan ke **ImageKit** (folder `/sportapp/jajanan/YYYY/MM/`), file lama dihapus saat diganti / record dihapus.
7. **jajanan.php** — tombol **Deteksi Lokasi Saya**, tampilkan Lat/Lng, validasi radius **1,5 km** pusat UIN SGD Bandung (−6.926263, 107.717553). Diluar radius muncul peringatan `"Lokasi diluar jangkauan kampus UIN SGD Bandung"` dan submit diblokir (validasi juga di server).
8. **config/db.php** — auto-migrasi kolom & tabel baru saat boot.

## PostgreSQL — yang ditambahkan
Sudah otomatis dibuat oleh `config/db.php` saat aplikasi dijalankan, tapi jika ingin di-run manual via psql, isi `migrations_31mei_v2.sql` di repo:

```sql
ALTER TABLE jajanan          ADD COLUMN IF NOT EXISTS foto_file_id VARCHAR(120);
ALTER TABLE jajanan_pesanan  ADD COLUMN IF NOT EXISTS pickup_lat NUMERIC(10,6);
ALTER TABLE jajanan_pesanan  ADD COLUMN IF NOT EXISTS pickup_lng NUMERIC(10,6);

CREATE TABLE IF NOT EXISTS device_locations (
  user_id     INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  lat         NUMERIC(10,6) NOT NULL,
  lng         NUMERIC(10,6) NOT NULL,
  accuracy_m  NUMERIC(8,2),
  device_label VARCHAR(120),
  updated_at  TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS device_location_history (
  id         BIGSERIAL PRIMARY KEY,
  user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lat        NUMERIC(10,6) NOT NULL,
  lng        NUMERIC(10,6) NOT NULL,
  accuracy_m NUMERIC(8,2),
  created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS device_loc_hist_user_idx
  ON device_location_history(user_id, created_at DESC);
```

**Tidak ada data yang dihapus.** Hanya menambah kolom & tabel baru.

## Catatan operasional
- Wake Lock & background recording hanya bisa bekerja optimal selama tab/aplikasi masih hidup. Untuk pengalaman terbaik, install sebagai PWA di HP (Add to Home Screen) dan jangan force-close. Browser modern (Chrome Android, Safari iOS 16.4+) mendukung Wake Lock.
- Heartbeat lacak HP butuh izin Geolocation diberikan user. Jika user menolak, baris di `admin/lacak.php` tampil "Belum ada data lokasi".
- Koordinat pusat UIN SGD Bandung (Kampus 1 Cipadung) & radius bisa diubah di dua tempat: blok JS dan blok PHP di `jajanan.php` (variabel `UIN`).
- ImageKit credentials sudah ada di `config/imagekit.php`.

# Revisi R12 (fix upload.php AI auto-submit)

Perubahan hanya di **upload.php**:
- Tombol "Ekstrak dengan AI" sekarang otomatis mengisi form manual (Tanggal, Durasi, Jarak, Pace, Kalori, Deskripsi) DAN memasukkan file screenshot ke input `bukti` via DataTransfer, lalu memanggil `form.submit()` native.
- Alur upload jadi identik dengan submit manual (ImageKit + INSERT `upload_harian`), sehingga data pasti tampil di tabel "Aktivitas Saya".
- Tanggal dari AI dinormalisasi ke format `YYYY-MM-DD` agar tidak gagal simpan.
- Melewati interceptor `fetch` global / preloader kustom yang sebelumnya membuat data tidak tersimpan.

## Catatan DB
Tidak ada perubahan schema. Kolom `gear_sepatu` sudah ditambahkan otomatis pada R11:
```sql
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS gear_sepatu VARCHAR(120);
```

## File dalam zip
- upload.php  (diperbaiki di R12)
- (file R11 lain disertakan agar konsisten: paket_perokok_jogging.php, includes/header.php, index.php, leaderboard_islami.php, dm.php, api_dm.php)

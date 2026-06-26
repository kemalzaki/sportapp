# Revisi R20 (lanjutan R19)

Memperbaiki 2 bug dari R19:

1. **Tampilan acak-acakan** — `includes/header.php`: override CSS R19 yang memindahkan offcanvas drawer ke `left: calc(50% - 240px)` membuat panel ikut "mengintip" dari sisi kiri frame karena kombinasi `transform: translateX(calc(-100% - 8px))` tidak cukup menyembunyikannya di viewport lebar. Kembalikan ke perilaku core (drawer di `left:0` viewport, tersembunyi dgn `translateX(-100%)`) — sama persis seperti tampilan HP.

2. **Kiloan tidak muncul di `tempat_list.php`** padahal muncul di `tempat_detail.php`. Penyebab: detail menghitung dari GPX via JavaScript, sementara list mengandalkan `tempat.jarak_km` / `run_routes.jarak_m` yang kosong untuk semua row Hiking.
   - Tambah parser GPX server-side (`_r20_gpx_to_km`) yang menghitung total jarak dari `<trkpt>`/`<rtept>` dengan formula Haversine.
   - Saat halaman list dibuka, semua tempat Hiking yang punya `gpx_path` tapi `jarak_km IS NULL` di-update sekali (cache). Berikutnya badge "X.XX km" otomatis tampil di kartu.
   - Untuk tempat Hiking tanpa GPX & tanpa kiloan manual, admin bisa mengisi field "Kiloan Trek (km)" di `admin/tempat.php`.

## File diperbarui
- `includes/header.php`
- `tempat_list.php`
- `admin/tempat.php` (sama dgn R19 — input kiloan manual)
- `admin/jadwal.php` (sama dgn R19)
- `migrations_r19.sql` (tetap berlaku, idempotent)

## Database
Tidak ada migrasi baru. R19 sudah menambah kolom `tempat.jarak_km` (idempotent ALTER tetap dipanggil di runtime). Jalankan jika belum:
```sql
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS jarak_km NUMERIC(6,2) NULL;
```

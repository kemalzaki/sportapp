# Revisi 21 Juni 2026 — R4 (sebagian)

Arsip ini berisi HANYA file yang DIREVISI di iterasi R4. File lain pakai versi sebelumnya.

## File yang berubah
- `flyover.php`
- `index.php`
- `admin/tempat.php`
- `migrations_r4.sql` (perubahan database — opsional, kolom dibuat otomatis runtime juga)

## Ringkasan revisi

### 1. flyover.php — Audio player ke-2 tidak bisa play
Penyebabnya bukan iTunes API, tapi player audio: setelah `audioCtx.createMediaElementSource()` dipanggil sekali pada elemen `<audio>`, elemen tersebut tidak bisa lagi diputar normal di pemutaran berikutnya. Fix:
- Setiap kali pilih lagu (`pickMusic`) dan setiap upload sendiri (`setupMusicSrc`), elemen `<audio>` di-clone & di-replace dgn instance baru ⇒ lagu ke-2/3/dst pasti bisa diputar.
- Tombol **Refresh Preview iTunes** tetap ada sbg tombol cadangan.
- `MediaElementSource` sekarang di-cache per-elemen sehingga record berulang tidak melempar `InvalidStateError`.

### 2. flyover.php — CORS saat upload audio sendiri
- File self-upload (blob: URL) tidak butuh CORS. `crossOrigin` di-reset ke null + `removeAttribute('crossorigin')`, dan semua `fetch(..., {mode:'cors'})` jadi tidak menambahkan flag CORS untuk URL berskema `blob:`.

### 3. flyover.php — Pengaturan posisi subtitle
Tambahan dropdown **Posisi Subtitle** dengan opsi: bawah-tengah (default), bawah-kiri, bawah-kanan, atas-tengah, atas-kiri, atas-kanan, tengah-tengah. Diterapkan ke overlay HTML (preview) dan ke kanvas rekaman video.

### 4. flyover.php — Lyric translate tidak muncul di video rekaman
- Render terjemahan EN→ID juga digambar ke kanvas rekaman (sebelumnya hanya overlay HTML preview yang mendapat terjemahan).

### 5. index.php — Posting video DIGANTI multiple gambar + slider di feed
- Form posting: input `video` dihapus, diganti `fotos[]` (multiple, maks 10).
- Server-side: setiap gambar diupload ke ImageKit, daftar URL disimpan sebagai JSON di kolom baru `posts.images_json`. URL pertama disalin ke `foto_url` (kompatibilitas mundur).
- Rendering feed: kalau `> 1` gambar, dirender Bootstrap Carousel (slider). Posting video LAMA tetap ditampilkan supaya data tidak hilang.

### 6. tempat_detail.php — Rute hiking/camping
Sudah ada di R3. Tidak dimodifikasi di R4 karena fungsionalitas sudah lengkap (peta GPX, jarak, Google Maps directions, Street View titik awal, info parkir).

### 7. admin/tempat.php — Tombol "Lihat Peta" + rute tersimpan run.php
- Kolom Aksi: tombol **Lihat Peta** untuk hiking/camping (membuka modal Leaflet menggambar GPX/GeoJSON), atau tombol **Lihat Peta** ke OSM untuk tempat non-trail dengan lat/lng.
- Dropdown "Rute Tersimpan (run.php)" sekarang menampilkan SEMUA rute dari `run_routes` (lintas user) lengkap dgn nama pemilik.

## Perubahan database (PostgreSQL)
Migrasi idempotent — sudah dipanggil otomatis di runtime, namun bisa dijalankan manual dari `migrations_r4.sql`:

```sql
ALTER TABLE posts ADD COLUMN IF NOT EXISTS images_json TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS gpx_path TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS parkir_info TEXT;
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS run_route_id BIGINT;
```

Tidak ada data yang dihapus. Tabel `run_routes` (dari run.php) sudah ada sejak revisi sebelumnya — tidak perlu perubahan.

## Cara apply
1. Backup file lama yg akan di-overwrite.
2. Salin file dari arsip ini ke struktur project yang sama.
3. (Opsional) jalankan `migrations_r4.sql` di PostgreSQL — atau biarkan kode jalankan otomatis lewat `ALTER TABLE ... IF NOT EXISTS`.
4. Refresh browser (hard reload Ctrl+Shift+R) supaya cache JS lama tidak ikut.

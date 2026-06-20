# Revisi 20 Juni 2026 R3 — Catatan Perubahan

Isi zip hanya berisi file yang **direvisi** (bukan semua file projek). Salin & timpa ke
project lokal Anda. Tidak ada data yang dihapus.

## Daftar file yang diubah / ditambah

| File | Status | Keterangan |
|---|---|---|
| `flyover.php` | revisi | (1) Tombol "Refresh Preview iTunes" + reset audio saat ganti lagu. (2) Subtitle terjemahan EN→ID di bawah lirik EN (toggle di panel Lirik, sumber MyMemory free API, tanpa key). |
| `admin/tempat.php` | revisi | (3,6) Saat memilih jenis **Hiking** / **Camping**: form upload `.gpx` (CRUD), pilih rute dari `run_routes` (run.php), input parkir, dan lat/lng disembunyikan + tidak disimpan. Jenis lain tetap pakai lat/lng seperti semula. |
| `tempat_detail.php` | revisi | (4,5) Khusus hiking/camping: render jalur GPX/GeoJSON di peta (Leaflet) + tombol **Unduh Rute Perjalanan**, **Lihat Jalur di Google Maps** (origin/finish + waypoint walking), **Street View titik awal**, plus blok info **Tempat Parkir yang Disarankan**. |
| `run.php` | revisi | (7) Anchor `#eksplorasi` pada section "Eksplorasi Rute & Peta Canggih" + tombol cepat (mobile only) di atas. |
| `includes/header.php` | revisi | (7) Menu "**Eksplorasi Rute & Peta Canggih**" ditambahkan di grup **Jogging Progress**. |
| `artikel_olahraga.php` | revisi | (8) Setiap kartu jenis olahraga jadi **spoiler / accordion** — body collapse, klik header untuk membuka. |
| `uploads/gpx/.gitkeep` | baru | Folder penampungan file GPX (pastikan writable PHP). |
| `migrations_r3.sql` | baru | **WAJIB dijalankan** sekali untuk menambah kolom baru pada tabel `tempat`. |

## Langkah pemasangan (lokal)

1. **Backup** folder project & database (sekedar berjaga).
2. Ekstrak isi zip ini ke root project (timpa file).
3. Pastikan folder `uploads/gpx/` ada & dapat ditulis oleh PHP/web server.
4. Jalankan migrasi PostgreSQL:

   ```bash
   psql -h localhost -U <user> -d sportapp -f migrations_r3.sql
   ```

   Migrasi menambahkan kolom: `tempat.gpx_path`, `tempat.parkir_info`,
   `tempat.run_route_id` (semuanya nullable, `IF NOT EXISTS`, idempotent).

5. (Otomatis) `admin/tempat.php` juga menjalankan `ALTER ... IF NOT EXISTS`
   di setiap request admin, sehingga seandainya migration belum dijalankan
   manual pun kolomnya tetap dibuat saat halaman admin diakses pertama kali.

6. Tidak ada perubahan dependency / composer.

## Catatan teknis

- **Item 1** — tombol refresh memanggil `pause → removeAttribute('src') → load → src = original → play`.
  Cara ini mengatasi bug saat pilih lagu kedua kali yang sering stuck di Safari/Chrome
  karena CORS preview iTunes diiringi `crossOrigin='anonymous'`.
- **Item 2** — terjemahan baris EN→ID dicache di `LYRICS.trans[line]` agar tidak request berulang.
- **Item 3** — file `.gpx` divalidasi (ext, magic `<gpx`, ukuran ≤8 MB). Hapus file lama saat ganti / hapus record.
- **Item 4** — peta menggunakan Leaflet + tile OSM (sudah dipakai di tempat_detail). GPX di-parse di
  browser via `DOMParser`. GeoJSON dari `run_routes` juga didukung.
- **Item 5** — "Lihat Jalur di Google Maps" memakai Directions URL API
  (`maps/dir/?api=1`) dengan origin = titik pertama, destination = titik terakhir, dan
  satu waypoint tengah (cukup untuk visualisasi belokan jalan di Google Maps; jika ingin
  jalur GPX persis, gunakan tombol "Unduh Rute Perjalanan" lalu import ke Google My Maps).
- **Item 7** — section Eksplorasi tetap berada di `run.php` (script & state berbagi
  banyak kode). Menu terpisah hanya men-`#anchor` ke section tsb agar **tidak duplikasi 2.000+
  baris JS** yang akan rawan bug. Pengguna tetap merasa seperti menu sendiri.
- **Item 8** — spoiler memakai Bootstrap 5 collapse (sudah ada di project). Tidak butuh JS tambahan.

## Tidak ada data sensitif yang diubah

Tidak ada `INSERT/UPDATE/DELETE` data di migration. Kolom baru semua **nullable**.

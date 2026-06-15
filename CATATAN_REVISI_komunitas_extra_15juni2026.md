# Revisi 15 Juni 2026 — Keamanan & Fitur Ekstra Komunitas

Dua fitur baru, semua client + PHP murni + PostgreSQL (tanpa React/Node):

1. **Berbagi Lokasi Real-Time (Live Tracking / Beacon)**
   - Pemilik akun membuat sesi → mendapat tautan publik unik.
   - Tautan dapat dikirim ke keluarga / kontak darurat via WhatsApp,
     Telegram, SMS, atau email.
   - Selama tab pemilik terbuka, browser mengirim koordinat GPS tiap
     ~5 detik. Penerima yang membuka tautan akan melihat posisi
     berdenyut hijau di peta yang otomatis ter-update.
   - Tautan otomatis kedaluwarsa setelah durasi yang dipilih (1–24 jam).
   - Kontak darurat dapat disimpan agar mudah dipanggil ulang.

2. **Video Animasi Rute 3D (Flyover)**
   - Mengubah sesi `run_sessions` + `run_points` menjadi video sinematik
     dari udara, lengkap dengan kamera mengikuti rute & lintasan yang
     digambar progresif (mirip Relive).
   - Rekam langsung di browser via `MediaRecorder` → file `.webm`
     otomatis ter-download. **Tidak perlu encoding di server.**
   - Pakai MapLibre GL JS (gratis, tanpa API key). Style dapat
     diganti: OSM raster (default), MapLibre Demotiles, atau Carto Dark.

## File yang ada di zip

| File | Status | Keterangan |
|---|---|---|
| `live_tracking.php`                          | BARU | Halaman pemilik untuk start/stop sesi & kelola kontak. |
| `track_view.php`                             | BARU | Halaman PUBLIK utk penerima tautan (tanpa login). |
| `api_live_tracking.php`                      | BARU | Endpoint: start, push, stop, view (publik), mine, contact_*. |
| `flyover.php`                                | BARU | Render & rekam video flyover 3D di sisi browser. |
| `migrations_komunitas_extra_15juni2026.sql`  | BARU | DDL idempotent (opsional dijalankan manual). |
| `CATATAN_REVISI_komunitas_extra_15juni2026.md` | BARU | Dokumen ini. |

> Karena instruksi: "buat zip hanya yang direvisi", file lain (mis.
> `run.php`, `api_run.php`, dsb) **tidak** disertakan di zip ini —
> semua fitur baru di sini hidup sebagai file mandiri dan
> meng-konsumsi `api_run.php` yang sudah ada.

## PostgreSQL yang perlu ditambahkan

**Opsional** — halaman PHP terkait sudah memanggil DDL ini secara
idempotent saat pertama kali dibuka. Bila lebih nyaman dijalankan
manual:

```bash
psql "$DATABASE_URL" -f migrations_komunitas_extra_15juni2026.sql
```

Tabel yang dibuat (semua `CREATE TABLE IF NOT EXISTS`, tidak ada
`ALTER` / `DROP` / `DELETE`):

- `live_tracking_sessions`     — daftar sesi berbagi lokasi.
- `live_tracking_points`       — titik GPS per sesi.
- `live_tracking_contacts`     — kontak darurat per user.
- `flyover_renders`            — metadata video flyover (opsional, belum
  digunakan untuk upload otomatis; disediakan untuk pengembangan ke depan).

Data eksisting (`users`, `run_sessions`, `run_points`, dll) **tidak
disentuh sama sekali**.

## Cara uji cepat (local)

1. Salin semua file ke root project lama (sejajar dengan `run.php`).
2. Buka di browser:
   - `http://localhost/live_tracking.php` → klik "Mulai & buat tautan".
     Izinkan GPS. Salin tautan, buka di tab incognito → akan terlihat
     marker berjalan otomatis.
   - `http://localhost/flyover.php` → pilih salah satu sesi `run_sessions`
     yang memiliki minimal 5 titik → klik "Rekam Video".
3. (Opsional) Tambahkan menu ke `includes/bottom_nav.php` /
   `includes/menu_render.php` agar mudah diakses. Tidak diubah di
   revisi ini agar zip tetap minimal dan tidak menyentuh file lain.

## Dependensi eksternal (CDN, sudah otomatis)

- Leaflet 1.9.4                  (peta 2D)
- MapLibre GL JS 4.7.1           (peta 3D + WebGL canvas)
- Bootstrap 5.3.3 + Icons 1.11.3 (dipakai di seluruh proyek)
- OSM / Carto tiles, OSRM publik (opsional)

Semua bekerja di local; butuh koneksi internet untuk fetch awal tiles.

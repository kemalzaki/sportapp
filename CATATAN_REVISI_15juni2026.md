# Revisi 15 Juni 2026 — Eksplorasi Rute & Peta Canggih (run.php)

## File yang berubah
- `run.php`                — penambahan section "Eksplorasi Rute & Peta Canggih" (3 tab) + auto-create tabel `run_routes`.
- `api_run.php`            — endpoint baru: `?route_load=`, `?heatmap=`, `_action=route_save`, `_action=route_delete`.
- `migrations_run_advanced_15juni2026.sql` — referensi DDL (opsional, sudah idempotent di run.php).

## Fitur baru di /run.php

### 1. Pembuat Rute Kustom (Route Builder)
- Input: titik mulai (manual lat,lng atau lokasi GPS), target jarak (km),
  preferensi elevasi (apa-saja / datar / berbukit), jenis jalan
  (apa-saja / aspal / tanah / campuran), bentuk (loop atau out-&-back).
- Algoritme: men-generate waypoint sintetis (segitiga loop atau pulang-pergi),
  lalu di-snap ke jalan via OSRM publik
  (`https://router.project-osrm.org/route/v1/foot/...`).
- Hasil ditampilkan di peta Leaflet, bisa disimpan ke DB (`run_routes`) atau
  di-export sebagai GPX.
- Catatan kejujuran: OSRM publik tidak membedakan permukaan aspal/tanah,
  jadi preferensi tetap dijalankan namun disimpan sebagai metadata —
  rute akan tetap mengikuti jaringan jalan terdekat.

### 2. Heatmap Pribadi / Publik / Night
- Dihitung dari tabel `run_points` (tidak ada data baru yang perlu di-seed).
- Mode "Pribadi" memfilter berdasarkan `run_sessions.user_id = current_user`.
- Mode "Publik" mengambil semua titik (komunitas).
- Mode "Night" hanya titik dengan jam 18:00–05:00 lokal —
  warna gradient diubah ke biru→kuning sehingga jalur populer di malam hari
  langsung terlihat.
- Pakai plugin `leaflet.heat` (di-load dari unpkg).

### 3. Peta Offline
- Pilih rute tersimpan atau riwayat sesi → bbox dihitung otomatis.
- Tile OSM dalam bbox + zoom yang dipilih (13–16) diunduh ke
  `CacheStorage` browser (`hf-tiles-v1`).
- Tombol "Hapus Cache Offline" tersedia untuk membebaskan ruang.
- Saat sinyal hilang, tile yang sudah ter-cache tetap muncul.
  Untuk pengalaman benar-benar offline, pastikan `service-worker.js`
  juga mem-serve tile dari cache (revisi ini hanya menggunakan
  CacheStorage langsung; jika ingin tile otomatis dilayani saat offline,
  tambahkan handler `fetch` di `service-worker.js` untuk pola URL
  `tile.openstreetmap.org`).

## PostgreSQL yang perlu ditambahkan

Tidak ada yang wajib dijalankan manual. Saat halaman `run.php` dibuka,
blok berikut sudah otomatis dieksekusi (idempotent):

```sql
CREATE TABLE IF NOT EXISTS run_routes ( ... );
CREATE INDEX IF NOT EXISTS run_routes_user_idx ON run_routes(user_id, created_at DESC);
```

Jika lebih nyaman menjalankan manual, lihat file
`migrations_run_advanced_15juni2026.sql`.

Tidak ada `ALTER`, tidak ada `DROP`, tidak ada `DELETE` —
data lama (run_sessions, run_points, dll) tidak disentuh sama sekali.

## Cara uji cepat
1. Replace `run.php` & `api_run.php`.
2. Buka `/run.php` → scroll ke bawah → tab **Route Builder** →
   klik "Generate Rute" (browser akan minta izin lokasi).
3. Cek tab **Heatmaps** — pilih mode Pribadi / Publik / Night.
4. Cek tab **Peta Offline** — pilih rute / riwayat → "Unduh Peta Offline".

## Dependensi eksternal
- `https://unpkg.com/leaflet@1.9.4/dist/leaflet.{css,js}` (sudah dipakai sebelumnya)
- `https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js` (baru)
- `https://router.project-osrm.org` (OSRM publik — gratis, throttled)
- `https://{a,b,c}.tile.openstreetmap.org` (tile peta)

Semua dependensi tersebut bekerja saat dijalankan di local
(membutuhkan koneksi internet untuk request awal — setelah tile
ter-cache, peta tetap berfungsi tanpa internet).

# Revisi 27 Juni 2026 — Ringkasan & Catatan SQL

## Daftar File yang Direvisi (timpa file lama dengan path yang sama)

```
islami.php                       → menu inline Monitoring dihapus, diganti 1 kartu menu
monitoring_tahajud.php           → BARU: halaman tersendiri Monitoring Tahajud & Duha
cedera_olahraga.php              → tombol "Rute Google Maps" per item Puskesmas/RS
survival.php                     → tombol "Cari dari Lokasi Saya" + radius (Overpass)
opini_viral.php                  → tambah kategori Politik & Bisnis
flyover.php                      → fallback otomatis beat-sync saat Gemini di-block region
manifest.php                     → PWA installability: split icon any/maskable, id, display_override
admin/pengeluaran.php            → fix Edit (intercept submit + notifikasi + reset state)
admin/menu.php                   → CRUD kolom `paket` per item menu (label badge di navigasi)
includes/menu_render.php         → render badge paket di samping label menu
includes/ai_gemini.php           → deteksi error "User location is not supported" → rotasi key + pesan jelas
```

## SQL yang Perlu Ditambahkan (PostgreSQL)

Hanya **satu** kolom baru. Sudah otomatis di-`ADD COLUMN IF NOT EXISTS` saat
`admin/menu.php` atau `includes/menu_render.php` di-load pertama kali,
jadi **tidak perlu intervensi manual**. Tetapi bila ingin dijalankan manual:

```sql
ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket VARCHAR(20);
-- nilai: NULL (tanpa label), 'gratis', 'pro', 'komunitas'
```

Tabel `shalat_sunnah_log` yang dipakai `monitoring_tahajud.php` sudah ada
(tidak berubah). Tabel `pengeluaran_kegiatan` juga tidak berubah skema-nya.

## Catatan Item

1. **Monitoring Tahajud (islami.php)** — panel inline dihapus; tambah kartu menu
   "Monitoring Tahajud & Duha" tepat setelah kartu "Shalat Duha & Tahajud"
   yang membuka `/monitoring_tahajud.php`.

2. **Puskesmas/RS (cedera_olahraga.php)** — tiap item di list sekarang punya
   tombol hijau "Rute Google Maps" yang membuka tab baru ke
   `google.com/maps/dir/?api=1&origin=...&destination=...`.
   Klik baris (di luar tombol) tetap menampilkan rute OSRM seperti semula.

3. **Survival (survival.php)** — tambah tombol "Cari dari Lokasi Saya" + dropdown
   radius (10/25/50/100 km). Memakai Overpass API untuk natural=wood,
   landuse=forest, boundary=protected_area, boundary=national_park.
   Hasil disortir terdekat & dapat dibuka rute di Google Maps.

4. **Opini Viral (opini_viral.php)** — tambah `$sources` "Politik" & "Bisnis"
   (RSS Google News).

5. **PWA (manifest.php)** — perbaikan installability paling umum yang men-trigger
   warning Chrome: split icon any/maskable, tambah `id`, `display_override`,
   header `Cache-Control: no-cache`. Bila masih ada warning spesifik di Lighthouse,
   kirim screenshot konsol agar dapat di-tune lebih lanjut.

6. **Rekap Pengeluaran (admin/pengeluaran.php)** — bug "Edit tidak berfungsi"
   diperbaiki: tombol Save kini bertipe `submit` eksplisit; intercept submit
   menambah `stopPropagation()`, await response sebelum reload, restore innerHTML
   tombol, dan menampilkan alert error bila HTTP gagal.

7. **Sinkron Musik via AI (flyover.php + includes/ai_gemini.php)** — error
   "User location is not supported for the API use" sekarang:
   - Terdeteksi di `_gemini_call()` → coba rotasi ke `GEMINI_API_KEY_2/3/...`
     bila tersedia (key dari project region berbeda mungkin tidak di-block).
   - Bila semua key tetap di-block, frontend `flyover.php` otomatis fallback ke
     fungsi `syncLyricsToBeats()` (beat-detection lokal, tanpa AI).
   - Pesan error jadi ramah dengan saran setup proxy/VPN.

8. **CRUD Paket Menu (admin/menu.php + includes/menu_render.php)** —
   tambah kolom `paket` (gratis/pro/komunitas/NULL). UI:
   - Form Tambah menu: dropdown Paket.
   - Tabel daftar menu: dropdown inline (auto-submit `onchange`) untuk
     ubah paket setiap menu tanpa membuka form edit.
   - Render `nav_menu_html()` menampilkan badge berwarna (🆓/⭐/👥) di samping
     label menu sesuai paket yang dipilih.

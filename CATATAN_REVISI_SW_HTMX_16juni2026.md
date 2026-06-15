# Catatan Revisi — Skema SW Baru (NetworkFirst + SWR HTMX) + Layout Splitter

Tanggal: 16 Juni 2026 (revisi tambahan)

Zip ini **parsial** — hanya berisi file yang berubah. Timpa file lama
pada path yang sama di project Anda (`htdocs/sportapp/...` atau sesuai
lokasi instalasi lokal Anda).

## Tujuan

1. Semua halaman pakai **helper `is_htmx()`** + **layout splitter**
   (`htmx_layout_start()` / `htmx_layout_end()`).
2. Service Worker memakai skema baru:
   - **CacheFirst** untuk shell (CSS/JS/icon/font CDN)
   - **StaleWhileRevalidate** untuk fragment HTMX (`HX-Request: true`)
   - **NetworkFirst** untuk navigasi penuh `.php` (fallback `/offline.html`)
   - **NetworkFirst** untuk API JSON (`api_*.php`)
   - Halaman sensitif (`/login`, `/logout`, `/register`, `/admin`) tidak
     pernah di-cache.

## Daftar file dalam zip

### Inti (wajib)
- `service-worker.js`  — v5 HTMX-aware (CACHE_VERSION otomatis baru)
- `includes/htmx.php`  — helper `is_htmx()`, `htmx_layout_start()`,
  `htmx_layout_end()`, `htmx_trigger()`, `htmx_redirect()`, `htmx_push_url()`
- `includes/header.php` — kini memuat:
  - `<meta name="csrf-token">`
  - HTMX core (`unpkg.com/htmx.org@1.9.12`)
  - `assets/js/htmx-boot.js` (boot + service-worker register + CSRF auto-attach + view transitions)
  - `<body hx-boost="true" hx-target="#app-content" hx-select="#app-content" hx-swap="innerHTML transition:true">`
- `offline.html` — fallback offline (sudah ada di zip Anda sebelumnya;
  disertakan ulang agar konsisten)

### Halaman yang dimigrasikan (60 file)
Semua halaman berikut: pola lama
`include 'includes/header.php'; ... include 'includes/bottom_nav.php'; include 'includes/footer.php';`
diganti menjadi:
`require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Judul'); ... htmx_layout_end();`

```
artikel_olahraga.php       hadist.php                kalori_renang.php
artikel_sunnah.php         hashtag.php               kesehatan.php
beasiswa.php               hidup_sehat.php           leaderboard_islami.php
berita.php                 index.php                 live_tracking.php
bookmark.php               iptv.php                  monitoring.php
buku.php                   islami.php                privasi.php
calendar.php               jadwal_sholat.php         profile.php
catatan_hafalan.php        jajanan.php               quran.php
cedera_olahraga.php        kajian.php                quran_kata.php
challenge.php              kalender_hijriyah.php     quran_search.php
checkin.php                kalistenik.php            quran_surah.php
dm.php                     kalkulator.php            report.php
doa.php                    kalkulator_jantung.php    riwayat.php
doa_antar_member.php       kalkulator_kesehatan.php  run.php
donasi.php                 kalori_badminton.php      search.php
dzikir.php                 kalori_futsal.php         sejarah_nabi.php
event.php                  kalori_mingguan.php       statistik_islami.php
feed_islami.php            kalori_pingpong.php       tempat.php
flyover.php                                          tempat_detail.php
gaya_hidup.php                                       tempat_list.php
                                                     upload.php
                                                     user.php
```

### Halaman yang **sengaja TIDAK** dimigrasi
Auth/utility/tidak punya layout halaman penuh:
`login.php`, `register.php`, `logout.php`, `splash.php`, `onboarding.php`,
`manifest.php`, `export.php`, `repost.php`, `strava_connect.php`,
`strava_webhook.php`, semua `api_*.php`, `admin/*`, `offline.html`.

## Cara kerja layout splitter

`htmx_layout_start($title)`:
- Jika `HX-Request: true` (request datang dari HTMX): hanya kirim
  `<title hx-swap-oob="true">…</title>` + buka `<div id="app-content">`
  → **header/nav/footer tidak ikut dikirim** (hemat bandwidth + SWR cache
  ramah).
- Jika request normal: include `includes/header.php` lengkap.

`htmx_layout_end()`:
- Tutup `</div>` `#app-content`.
- Jika HTMX: stop. Jika normal: include `bottom_nav.php` + `footer.php`.

Body shell di `header.php` sudah pasang `hx-boost="true"` dengan
`hx-target="#app-content"` & `hx-select="#app-content"` sehingga **semua
`<a>` dan `<form>`** otomatis berperilaku HTMX (swap fragment, tanpa
reload, dengan View Transitions bila browser mendukung).

## Migrasi PostgreSQL

**TIDAK ADA** migrasi database baru yang diperlukan untuk revisi ini.
Berkas `.sql` di zip (`sportapp.sql`, `migrations_*.sql`) **tidak diubah**
dan data tetap utuh. Cukup pastikan migrasi-migrasi sebelumnya sudah
dijalankan:

- `sportapp.sql`                                  (schema dasar)
- `migrations_revisi_13juni2026.sql`
- `migrations_komunitas_extra_15juni2026.sql`
- `migrations_run_advanced_15juni2026.sql`

> Opsional (untuk fitur "Nama Rute Kustom" pada Flyover) — bila belum
> pernah dijalankan:
> ```sql
> ALTER TABLE run_sessions ADD COLUMN IF NOT EXISTS nama_rute TEXT;
> ```

## Setelah deploy

1. Bersihkan cache browser sekali (Ctrl+Shift+R), atau buka DevTools →
   Application → Service Workers → **Unregister** lalu reload.
   Versi SW naik ke `v5-htmx-2026-06-15`; client lama otomatis update
   karena `skipWaiting()` + `clients.claim()`.
2. Verifikasi di DevTools → Network: klik link dalam app → request
   memiliki header `HX-Request: true` dan response **tanpa** `<html>` /
   `<head>` (hanya fragment `#app-content`).
3. Putuskan jaringan (offline) → buka halaman yang pernah dikunjungi →
   harus tampil dari cache `sportapp-page-v5-…`. Halaman baru →
   `/offline.html`.

## Catatan tambahan
- Halaman `login.php` / `register.php` / `logout.php` / `admin/*`
  **sengaja di-skip** oleh service-worker (lihat `service-worker.js`
  baris `if (/^\/(login|logout|register|admin)/.test(...))`). Ini
  mencegah cache token lama atau form CSRF yang sudah expired.
- Bila Anda menambah halaman baru, cukup pakai pola:
  ```php
  <?php
  require_once __DIR__ . '/includes/htmx.php';
  htmx_layout_start('Judul Halaman');
  ?>
  <!-- isi halaman -->
  <?php htmx_layout_end(); ?>
  ```

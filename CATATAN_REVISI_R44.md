# Revisi R44 (Juli 2026) — Safe Area, Bottom Nav, Bug Riwayat

Zip ini HANYA berisi file yang direvisi. Timpa file berikut di root project:

- `riwayat.php`
- `includes/header.php`
- `includes/bottom_nav.php`
- `assets/css/safe-area.css`

## 1. Safe Area — implementasi global (bukan hanya run.php)
- `includes/header.php`
  - Bump cache-busting `safe-area.css?v=r44`.
  - Ditambahkan snippet JS kecil (inline, tanpa file baru) yang mendeteksi
    Capacitor / Android WebView lalu menambahkan class `is-native` ke `<html>`.
    Ini diperlukan karena sebagian Android WebView **tidak** mengembalikan
    nilai `env(safe-area-inset-top)` walau status bar overlay aktif → header
    (`.gt-top`) menabrak status bar.
- `assets/css/safe-area.css`
  - Blok R43 lama TIDAK diubah (tetap kompatibel).
  - Ditambahkan blok R44: fallback `max(env(safe-area-inset-top,0px), 28px)`
    yang HANYA aktif saat `html.is-native` (Android / Capacitor). Di browser
    desktop / iOS Safari, `env()` normal tetap dipakai (tidak berubah).
  - Berlaku otomatis untuk: header top-bar, chips bar, navbar bootstrap,
    body `.kk-safe-page`, `.kk-full-viewport`, `.kk-focus-shell`, modal
    fullscreen, chips & FAB pada focus mode (run.php), dan bottom nav
    `.gj-nav` (padding-bottom minimum 10px untuk gesture bar).
  - Semua diaplikasikan di layout global — tidak ada margin-top manual per
    halaman. Halaman Beranda / Aktivitas / Tracking Jalur / Upload / Kalori /
    Saya / Modal / Dialog / Fullscreen otomatis ikut.

## 2. Bug Database Riwayat — `malformed array literal: "Array"`
- `riwayat.php`
  - Query `SELECT ... FROM run_points WHERE session_id = ANY($1::bigint[])`
    sebelumnya menerima parameter `[$__sessIds]` — `pg_query_params` meng-cast
    array PHP menjadi string `"Array"` → PostgreSQL menolaknya.
  - Diperbaiki: parameter dikirim sebagai **literal array PostgreSQL** yang
    valid, mengikuti pola yang sudah dipakai di `includes/scope.php`
    (`scope_user_ids_sql_array()`):
    ```php
    ['{'.implode(',', array_map('intval', $__sessIds)).'}']
    ```
  - Struktur tabel, data, dan tidak ada migrasi baru. Query & alur mini-map
    rute pada Aktivitas tetap sama.

## 3. Bottom Navigation — tidak lagi hilang / berkedip
- `includes/bottom_nav.php`
  - Blok `<style id="gj-nav-vt">` yang mengaktifkan
    `@view-transition { navigation: auto; }` **dihapus**. Fitur eksperimental
    ini menyebabkan bottom nav berkedip atau hilang saat pindah/refresh
    halaman pada sebagian Chromium/WebView versi tertentu.
  - Bottom nav sudah persistent secara struktural:
    - `position: fixed` di `assets/css/gojek-nav.css`.
    - Guard `GJ_BOTTOM_NAV_RENDERED` memastikan hanya di-render 1×.
    - Di-include otomatis di `includes/footer.php` → semua halaman yang
      pakai footer global otomatis mendapat bottom nav yang sama, tanpa
      re-mount komponen per halaman.
  - CSS safe-area R44 juga memastikan padding-bottom aman di Android WebView.

## 4. Regression check (manual)
File JS bisnis TIDAK disentuh sama sekali:
- `assets/js/run/tracking.js`, `gps.js`, `save.js`, `background.js`, `voice.js`
- ID/kelas UI: `kk-btn-start`, `kk-btn-pause`, `kk-btn-stop`, `kk-map`,
  metrik `m-*`, `gj-nav`, `gj-item`, `gj-fab`, `gt-top`, `gt-chips`,
  `kk-focus-shell`, `kk-mapfabs`, `kk-chips` — semuanya tetap.
- Endpoint (`api_run.php`, `upload.php`, dll) tidak disentuh.
- Business logic (tracking, GPS, pause, resume, stop, save, upload,
  screenshot, fullscreen, riwayat, split, GPX, KML, GeoJSON) tetap.

## PostgreSQL
Tidak ada perubahan schema, tidak ada migrasi baru, tidak ada perubahan data.
Data pada `sportapp.sql` tetap dipertahankan.


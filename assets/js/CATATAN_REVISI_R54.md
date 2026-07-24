# Revisi R54 — Hybrid Shell (menggantikan SPA Router R53)

## Ringkasan
Router R53 menyebabkan halaman **berhenti di skeleton** karena mengintercept
seluruh link dan mem-fetch seluruh halaman. R54 mengubah pendekatan menjadi
**Hybrid Shell**: PHP multi-page tetap berjalan seperti biasa, hanya link
di bottom navigation / drawer / chip menu yang di-swap secara ringan.

## File yang berubah / ditambahkan
Semuanya berada di `assets/js/`. **Tidak ada perubahan pada file PHP,
database, endpoint, session, atau business logic.**

| File | Status | Fungsi |
|------|--------|--------|
| `assets/js/router.js`     | **REPLACE** | Intercept HANYA link opt-in (bottom nav, drawer, chip menu). Fallback keras ke `window.location.href` bila gagal. |
| `assets/js/loader.js`     | **NEW**     | Skeleton bar tipis dengan **hard cap 500ms**. |
| `assets/js/shell.js`      | **NEW**     | Swap `#app-content` saja (tidak mereplace node, hanya innerHTML). |
| `assets/js/navigation.js` | **NEW**     | Sinkronkan status aktif `.gj-nav .gj-item` setelah swap. |
| `assets/js/drawer.js`     | **NEW**     | Tutup `#gtDrawer` otomatis setelah navigasi berhasil. |

`footer.php` sudah memuat `router.js` (`<script src="/assets/js/router.js?v=r53" defer></script>`),
dan `router.js` sekarang otomatis memuat 4 modul lainnya secara idempotent —
jadi **tidak perlu mengubah footer.php**. Kalau ingin cache-busting, cukup ganti
query string versinya jadi `?v=r54` (opsional).

## Prinsip perbaikan
1. **Bukan SPA penuh.** Yang di-intercept hanyalah link di dalam selector:
   - `.gj-nav`  (bottom navigation)
   - `#gtDrawer` (menu drawer)
   - `.gt-chips` (chip menu di header)
   - `[data-spa="1"]` (opt-in eksplisit)

   Link lain (kartu artikel, tombol "Detail", link internal di konten) tetap
   melakukan navigasi normal browser — tidak akan pernah macet.

2. **Skeleton tidak pernah tanpa batas.** Loader disembunyikan paksa
   setelah 500ms. Request itu sendiri punya timeout 4 detik; bila melewati
   itu, otomatis fallback ke `window.location.href`.

3. **Fallback keras.** Semua jalur kegagalan (network error, response
   non-HTML, response tanpa `#app-content`, redirect ke halaman lain,
   status non-2xx, timeout, abort) berakhir di:

   ```js
   window.location.href = url;
   ```

   Sehingga halaman tetap terbuka via mekanisme normal browser.

4. **Halaman krusial di-SKIP** (selalu full navigation):
   `run.php`, `live_tracking.php`, `activity_detail.php`, `upload.php`,
   `login.php`, `logout.php`, `register.php`, `splash.php`,
   `onboarding.php`, `manifest.php`, semua rute di bawah `admin/`, serta
   file binary (zip, pdf, gambar, video, mp3, gpx, csv, xlsx).

   Ini melindungi fitur **tracking, GPS, upload, save, riwayat,
   screenshot, fullscreen, pause, stop, review** — semuanya tetap
   berjalan lewat lifecycle PHP asli, tanpa diintercept.

5. **DOM stabil.** Yang berubah hanya `innerHTML` dari `#app-content`.
   Header, bottom nav, drawer, floating action, dan `<script>` global di
   `footer.php` **tidak pernah dibongkar**. Script inline di halaman baru
   tetap dijalankan (via `KKShell.runInlineScripts`) sehingga interaksi
   spesifik halaman tetap hidup.

## Cara pasang di lokal
1. Ekstrak zip.
2. Copy folder `assets/js/` ke root aplikasi (menimpa `router.js` lama,
   menambah 4 file baru).
3. **Tidak perlu perubahan PostgreSQL / SQL.** Struktur tabel, seed data,
   dan `migration_r6.sql` tidak disentuh.
4. Hard-refresh browser (Ctrl+Shift+R) sekali untuk memuat versi baru.

## Regresi yang dites secara logis
- ✅ Tracking (`run.php`) → di-SKIP → full page load, GPS/pause/stop utuh.
- ✅ Detail aktivitas (`activity_detail.php`) → di-SKIP → screenshot,
  fullscreen, review tetap jalan seperti sebelumnya.
- ✅ Upload (`upload.php`) → di-SKIP → form + progress tidak diganggu.
- ✅ Login/logout/register → di-SKIP → redirect server tetap normal.
- ✅ Bottom nav Beranda ↔ Aktivitas ↔ Kalori ↔ Profil → SPA swap dengan
  skeleton ≤500ms; kalau gagal → fallback ke navigasi biasa.
- ✅ Drawer link → SPA swap + drawer auto-close.
- ✅ Back/forward browser → dihandle hanya untuk state milik router;
  history native lain tetap seperti biasa.

## Catatan untuk arsitektur ke depan (opsional, tidak dieksekusi di R54)
Sesuai saran Anda, `header.php`/`footer.php` kelak bisa dipecah menjadi
file-file HTML tipis + `router.js`/`shell.js`/`loader.js`/`navigation.js`/`drawer.js`.
Empat file JS terakhir sudah ada di paket ini dan dirancang siap dipakai
tanpa header/footer PHP dimodifikasi — pemecahan `header.php`/`footer.php`
bisa dilakukan bertahap di revisi berikutnya tanpa merusak R54.

## PostgreSQL
**Tidak ada tabel/kolom/index baru yang perlu ditambahkan** untuk revisi ini.

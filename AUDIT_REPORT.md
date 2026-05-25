# Audit & Refactor Report — Happy Family SportApp

Tanggal: 25 Mei 2026
Source: `sportapp_core.zip` (519 file, 355 PHP, 23 JSON, 4 JS, 3 CSS).

## 1. Ringkasan struktur yang ditemukan

| Layer | File kunci |
| --- | --- |
| Layout shell | `includes/header.php`, `includes/footer.php`, `includes/bottom_nav.php`, `includes/dm_floating.php` |
| Styling | `assets/css/app.css`, `assets/css/app-v3.css`, `assets/css/preloader.css`, Bootstrap 5.3.3 CDN, Bootstrap Icons CDN, Plus Jakarta Sans (Google Fonts) |
| JS | `assets/js/preloader.js`, `assets/js/fcm.js`, `assets/js/islami.js`, Chart.js, Quill, Bootstrap bundle |
| PWA | `manifest.php`, `service-worker.js` (versi minimal) |
| Backend | PHP + PostgreSQL (`config/db.php`), ImageKit, Composer (guzzle, beberlei/assert) |
| Routing | Banyak file root `*.php`, plus folder `admin/` |
| Hosting | Dockerfile, `app.yaml`, `vercel.json` — saat ini di Render |

## 2. Temuan utama (sebelum refactor)

### A. Layout / Mobile UX
1. **Navbar Bootstrap desktop dipakai juga di mobile** (collapse `hamburger`), berisi 14+ item — tidak ramah satu tangan. Sudah ada `bottom_nav.php` tetapi navbar lama tetap memakan ruang viewport ~58px termasuk safe-area.
2. **Tidak ada `viewport-fit=cover`** secara konsisten → tidak menghormati notch / gesture-bar Android & iOS.
3. **Input font-size < 16px** di beberapa form → iOS auto-zoom saat fokus.
4. **Banyak gradient/inline-style** di `header.php` mempersulit dark-mode konsisten.
5. **Belum ada page transition / ripple / skeleton / pull-to-refresh** — terasa “website biasa”, bukan app.
6. Tidak ada `theme-color` dinamis untuk dark mode (status bar Android tetap biru saat dark).

### B. PWA
1. `manifest.php`: bagus (sudah ada shortcuts, maskable hint) tapi `background_color` = biru terang → splash putih-biru flash sebelum app shell muncul. Tidak ada `id`, tidak ada `display_override`, ikon maskable & any digabung.
2. `service-worker.js`: strategi **cache-first untuk SEMUA GET** termasuk HTML → user lihat versi lama setelah deploy & POST/api ikut tercache jika dipanggil dengan GET. Tidak ada offline-page yang user-friendly. Tidak ada versioning di nama cache (kecuali `v3`).
3. SW **tidak terdaftar** di mana pun di footer/header — `manifest.php` ada tapi `navigator.serviceWorker.register()` tidak pernah dipanggil dalam HTML.

### C. Performance
1. CDN Bootstrap + Bootstrap-Icons + Quill + Chart.js + Google Fonts dimuat di **semua halaman** (termasuk login). Total payload >700 KB sebelum konten.
2. Tidak ada `loading="lazy"` di gambar generik (uploads).
3. `preloader.js` 290 baris berjalan di setiap navigasi — sudah baik, tapi double dengan `mobile-shell.js` perlu didampingi (tidak konflik, sudah dicek).

### D. Keamanan (note ringan, di luar scope refactor)
- `.htaccess` sudah memblokir `config/` & `includes/` ✓
- `manifest.php` dapat dipanggil semua orang ✓ (aman)
- Cookie session PHP harus `SameSite=None; Secure` agar Capacitor WebView mempertahankan login → cek di `includes/security.php` (bila perlu ditambahkan).

## 3. Yang diubah / ditambahkan oleh refactor ini

| File | Aksi | Catatan |
| --- | --- | --- |
| `assets/css/mobile-shell.css` | **BARU** | Mobile-first overlay: safe-area, sembunyikan navbar desktop, bottom-nav glassmorphism, ripple, skeleton, page transition, PTR, toast |
| `assets/js/mobile-shell.js`  | **BARU** | Capacitor detect, StatusBar/Keyboard/App plugin hook, ripple, page transitions, pull-to-refresh, active bottom-nav, helper `MSToast`/`MSSkeleton` |
| `service-worker.js`          | **DIGANTI** | Network-first HTML, stale-while-revalidate static, bypass API + POST, offline fallback HTML, cache versioning |
| `manifest.php`               | **DIGANTI** | `id`, `display_override`, `background_color` gelap (anti flash), maskable + any terpisah, 3 shortcuts |
| `includes/header.php`        | **PATCH** | Tambah meta `mobile-web-app-capable`, `apple-mobile-web-app-*`, `format-detection`, viewport `maximum-scale=1`, theme-color gelap; muat `mobile-shell.css` |
| `includes/footer.php`        | **PATCH** | Muat `mobile-shell.js`; daftarkan service worker (`navigator.serviceWorker.register`) |
| `capacitor/`                 | **BARU** | Folder wrapper terpisah: `package.json`, `capacitor.config.ts`, `www/index.html`, `README.md`, `resources/` |
| `BUILD_APK.md`               | **BARU** | Panduan ringkas APK |

**Yang TIDAK diubah:** semua file PHP backend (controller, query, auth, admin, API), database, asset gambar, vendor/. Refactor murni di layer presentasi & wrapper.

## 4. Bagaimana cara deploy

1. **Upload hasil refactor ke hosting Render** seperti biasa (commit & push, atau drag & drop di dashboard). Tidak ada migrasi DB.
2. Test di browser HP: buka `https://sportapp-rumd.onrender.com/` → navbar besar hilang di mobile, bottom-nav tetap, ada PTR, transisi halaman halus, splash gelap saat install PWA.
3. **Capacitor APK**: ikuti `capacitor/README.md` di laptop lokal Anda (Node + Android Studio). Tidak menyentuh hosting.

## 5. Roadmap fitur native (siap dipasang)

Struktur sudah memungkinkan tinggal `npm install` plugin Capacitor:

- **Push Notification** (`@capacitor/push-notifications`) — gabungkan dengan FCM yang sudah ada di `fcm.js`.
- **Biometric Login** (`capacitor-native-biometric`) — gate `login.php` dengan sidik jari setelah login pertama.
- **Camera native upload** (`@capacitor/camera`) — ganti input file di `upload.php`.
- **Geolocation** (`@capacitor/geolocation`) — `run.php` bisa pakai GPS native, jauh lebih akurat dari `navigator.geolocation` di WebView.
- **Barcode/QR native** (`@capacitor-community/barcode-scanner`) — `checkin.php` lebih cepat dari pustaka JS.
- **Offline storage** (`@capacitor/preferences`) — caching profil & draft post.
- **Deep link** (`@capacitor/app` `appUrlOpen` event) — sudah aktif di `mobile-shell.js`, tinggal handle URL.

## 6. Catatan penting

- **Cookie session PHP**: di `includes/security.php` (atau `auth.php`), pastikan saat set session cookie ditambahkan opsi `'samesite' => 'None', 'secure' => true`. Tanpa ini, login bisa hilang di Capacitor WebView (karena origin app `https://` tapi cookie default `Lax`).
- Untuk verifikasi semua benar termuat, buka `view-source:` halaman di HP — harus ada `<link href="/assets/css/mobile-shell.css">` dan `<script src="/assets/js/mobile-shell.js" defer>`.

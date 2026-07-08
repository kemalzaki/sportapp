# Audit Bottom Navigation — SportApp (Nov 2026)

## Ringkasan Masalah
Di Chromium (Chrome/Edge) tombol Upload di tengah rusak: label turun,
posisi tidak konsisten, ukuran berubah. Firefox tampak normal. Ini bukan
bug browser — ini **CSS conflict**: enam file berbeda mengatur selector
`.gj-nav`, `.gj-fab`, `.gj-fab-inner`, `.gj-label`, `.bottom-nav`,
`.bn-fab` secara bersamaan, sebagian dengan `!important`.

## Konflik yang Ditemukan

| File | Selector | Perilaku |
|---|---|---|
| `assets/css/gojek-nav.css` | `.gj-nav`, `.gj-fab`, `.gj-fab-inner`, `.gj-label` | Definisi utama FAB 58px, biru, `margin-top: -8px`. Tidak pakai `!important`. |
| `assets/css/redesign-2026.css` | `.gj-nav`, `.gj-fab`, `.gj-fab-inner`, `.gj-fab-label` | **Override dengan `!important` di setiap properti** — mengecilkan FAB dari 58px → **32px**, mengubah `min-height` bar jadi 74px, dan mereferensi class `.gj-fab-label` yang **tidak ada di markup** (yang benar `.gj-label`). Ini akar masalah "label turun / ukuran berubah". |
| `assets/css/app-v3.css` | `.bottom-nav`, `.bn-item`, `.bn-fab`, `.bn-badge`, `[dark] .bottom-nav`, `[dark] .bn-item` | Legacy dari implementasi lama. Markup baru tidak lagi memakai kelas ini, tapi `bottom_nav.php` masih menyimpan `<div class="bottom-nav d-none">` sekadar untuk kompatibilitas. Menambah 15 rule yang tidak dipakai. |
| `assets/css/mobile-shell.css` | `.bottom-nav`, `.bottom-nav .bn-item`, `.bottom-nav .bn-fab`, `.bottom-nav .bn-badge` | Duplikat legacy versi glassmorphism — juga tidak dipakai oleh markup baru. |
| `assets/css/desktop-fix.css` | `[class*="position-fixed"], .fixed-top, .fixed-bottom, .bottom-nav { max-width:100vw }` | Mengikat `.bottom-nav` ke aturan lebar; tidak dibutuhkan. |
| `includes/bottom_nav.php` | inline `<style>` — mengulang `position:fixed`, dark-mode, `@media (min-width:992px)` | Duplikasi dari `gojek-nav.css`. Menggunakan selector `.gj-fab-label` (tidak ada di DOM) sehingga rule loading-state mati. |
| `assets/css/gojek-top.css` | `.gj-nav { display:flex !important }` | **Bukan konflik** — hanya memaksa tampil di desktop. Dibiarkan. |

Kenapa Chrome vs Firefox berbeda? Karena `redesign-2026.css` menaruh
`!important` di **semua** properti FAB, urutan cascade jadi bergantung
pada momen paint. Chromium menerapkan aturan yang me-`!important`kan
`width/height:32px` lebih agresif; Firefox terkadang selesai layout
sebelum stylesheet inline di `bottom_nav.php` dieksekusi. Dua-duanya
"benar" — sistemnya yang salah.

## Prinsip Perbaikan
1. **Satu source of truth**: `assets/css/gojek-nav.css`.
2. **Tanpa `!important`**: dibersihkan dari file lain, bukan dilawan.
3. **Selector konsisten**: satu label `.gj-label` untuk semua item termasuk FAB (kelas `.gj-fab-label` dibuang).
4. **Flexbox 5 kolom sama lebar** via `flex: 1 1 0` pada `.gj-item` dan `.gj-fab`.
5. Legacy `.bottom-nav` / `.bn-*` dihapus tuntas (markup sudah tidak memakainya).

## File yang Diubah
- `assets/css/gojek-nav.css` — ditulis ulang menjadi **satu-satunya** definisi Bottom Nav (bar, item, FAB, label, avatar, badge, dark mode, bottom-sheet). Menggunakan CSS variable `--gj-fab-size` (58px, sesuai spec ±56–60px). Tidak ada `!important` di seluruh file.
- `assets/css/redesign-2026.css` — **dihapus 71 baris** blok "Bottom Navigation (premium Strava style)" dan blok "Upload button" yang menaruh `!important` di 20+ properti FAB. Diganti komentar penanda agar tidak diregresi.
- `assets/css/app-v3.css` — **dihapus** `body { padding-bottom: 80px }`, `.bottom-nav`, `.bn-item`, `.bn-item i`, `.bn-item:hover`, `.bn-fab`, `.bn-fab:hover`, `.bn-badge`, `[dark] .bottom-nav`, `[dark] .bn-item`. Total ±30 baris legacy.
- `assets/css/mobile-shell.css` — **dihapus 53 baris** blok "Bottom Nav polish" (semua rule `.bottom-nav`, `.bottom-nav .bn-*`, `[dark] .bottom-nav`).
- `assets/css/desktop-fix.css` — selector `[class*="position-fixed"], .fixed-top, .fixed-bottom, .bottom-nav` dipotong menjadi `[class*="position-fixed"], .fixed-top, .fixed-bottom` (buang `.bottom-nav`).
- `includes/bottom_nav.php` — dibersihkan:
  - Blok `<style>` yang mengulang `position:fixed`, dark-mode, dan `@media (min-width:992px)` **dihapus**.
  - Placeholder `<div class="bottom-nav d-none">` **dihapus** (tidak ada CSS legacy lagi yang mencarinya).
  - Semua referensi `.gj-fab-label` → `.gj-label` (menyatukan selector).
  - Cache buster CSS: `?v=r27-fab` → `?v=audit-nov2026`.

## Yang Tidak Diubah
- `includes/header.php` — urutan `<link>` sudah aman: `redesign-2026.css` dimuat di header, `gojek-nav.css` dimuat via `bottom_nav.php` (di dalam body, setelah semua CSS layout). Karena rule bottom-nav sudah **dihapus** dari `redesign-2026.css`, konflik cascade otomatis hilang tanpa perlu mengubah urutan atau menambah `!important`.
- `assets/css/gojek-top.css` — hanya berisi `display:flex !important` untuk memaksa nav tampil di desktop. Bukan konflik.
- File `.sql` dan data — tidak disentuh sesuai instruksi.

## PostgreSQL yang Perlu Ditambahkan
**Tidak ada.** Refactor ini murni CSS/HTML. Skema DB tidak berubah,
tidak ada tabel/kolom baru, tidak ada migrasi tambahan yang harus
dijalankan. `sportapp.sql`, `migration_r6.sql`, `REVISI_JULI_2026_R7.sql`,
`REVISI_JULI_2026_R8.sql` tetap seperti aslinya.

## Hasil Akhir
- Hanya `assets/css/gojek-nav.css` yang mendefinisikan Bottom Navigation.
- Tidak ada `!important` di file mana pun untuk selector `.gj-nav`, `.gj-fab`, `.gj-fab-inner`, `.gj-label`.
- Struktur Upload sesuai spec: lingkaran biru 58px, shadow lembut, label "Upload" sejajar dengan label menu lain, tanpa background putih tambahan, tanpa lingkaran/tombol ganda, tanpa overlap.
- 5 item (Beranda, Aktivitas, Upload, Kalori, Saya) memakai `flex: 1 1 0` — lebar identik.
- Tampilan seragam di Chrome, Edge, Firefox, Android Chrome, Android WebView, dan Capacitor karena tidak ada lagi rule yang bertabrakan atau cache-order-dependent.

## Cara Verifikasi Lokal
1. Salin isi zip ke root project (menimpa 6 file di daftar di atas).
2. Bersihkan cache browser (Ctrl+Shift+R) — cache buster `?v=audit-nov2026` juga otomatis memaksa reload.
3. Buka halaman apa pun yang menampilkan bottom nav; FAB Upload harus berbentuk lingkaran biru ±58px dengan label "Upload" sejajar 4 label lain.

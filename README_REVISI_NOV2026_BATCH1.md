# Revisi Nov 2026 — KawanKeringat UI Redesign (Batch 1)

Paket ini adalah **revisi PARSIAL** sesuai izin: "Jika belum semua direvisi, tetap dicompile jadi zip saja sebagiannya."

## Apa yang dikerjakan di batch ini (SIAP PAKAI)

### ✅ #2 Global Theme Engine + Navigation Redesign (SELESAI PENUH)
Ini adalah pondasi yang di-request paling tegas. Setelah batch ini di-install, **semua halaman** (profile.php, index.php, riwayat.php, kalori_mingguan.php, dan halaman lainnya) otomatis mendapatkan:

- **CSS Variable global**: `--primary`, `--primary-light`, `--primary-dark`, `--primary-soft`, `--surface`, `--surface-alt`, `--text-primary`, `--text-secondary`, `--primary-gradient`, dst. — di-inject dari `includes/theme_user.php` berdasarkan tema yang dipilih user di `profile.php` (kolom `users.tema_warna` yang sudah ada, tanpa perubahan schema).
- **Bottom Navigation premium** — tinggi 74px, sudut membulat 20px di atas, shadow tipis, icon aktif berwarna tema + animasi lift, label aktif semi-bold, transisi 200ms.
- **Floating Upload Button ala Strava** — lingkaran 64px, gradient tema, border putih 4px yang membuatnya "menyatu" dengan bottom nav, shadow halus, animasi scale saat ditekan. Selector fleksibel (`.gj-fab`, `.fab-upload`, `.floating-upload`, `.btn-fab`, `[data-fab="upload"]`) sehingga menangkap FAB apapun namanya.
- **Drawer** — header gradient primary-dark → primary, icon berwarna tema, item aktif dengan background `--primary-soft` + indikator vertikal 4px di sisi kiri.
- **Header / hero** — gradient tema otomatis.
- **Button, Card, Badge, Progress bar, Form input, Accordion, Tabs, Chip filter, Link, icon aktif** — semua mengikat ke CSS Variable, tidak ada hardcode biru lagi.
- **Card modern** — radius 20px, shadow halus, border ringan.
- **Tanpa horizontal scroll**, tipografi Plus Jakarta Sans, animasi fade 220ms.

Semua ini **tanpa mengubah logika PHP, session, DB, API, atau nama field**.

### ⚠️ #1 profile.php, #3 kalori_mingguan.php, #4 riwayat.php, #5 index.php — restrukturisasi HTML BELUM di batch ini
Keempat file tersebut berukuran total ~5.500 baris PHP dengan HTML/JS yang saling terkait erat. Restrukturisasi layout (hero card, accordion, feed style, podium leaderboard, timeline, dsb.) memerlukan rewrite bertahap yang **aman diverifikasi per-file**. Yang sudah didapat gratis dari batch ini:
- Warna semua tombol/card/nav/FAB/link/badge/progress di keempat halaman ini sudah otomatis konsisten dengan tema.
- Card, form, accordion, tab, chip yang sudah ada langsung terlihat modern.
- Header/hero/drawer/bottom-nav sudah premium.

Untuk restrukturisasi layout penuh (hero profile card, activity feed ala Strava, podium leaderboard, timeline notifikasi, dsb.) akan menyusul di batch berikutnya — file per file agar setiap perubahan bisa diuji.

## File yang berubah

| File | Aksi |
|---|---|
| `includes/theme_user.php` | **REPLACE** — sekarang menyediakan CSS Variable global lengkap + 2 palette tambahan (`orange`, `teal`). |
| `includes/header.php` | **REPLACE** — menambahkan 1 baris `<link>` ke `redesign-2026.css` (setelah `gojek-top.css`). |
| `includes/bottom_nav.php` | **REPLACE** — menambahkan 1 baris `<link>` ke `redesign-2026.css` sebagai safety net. |
| `assets/css/redesign-2026.css` | **NEW** — overlay UI modernization (~280 baris CSS). |

## Cara install

1. Backup folder aplikasi Anda dulu.
2. Ekstrak zip ini di root project, timpa file yang ada:
   ```
   unzip -o sportapp_revisi_nov2026_batch1.zip -d /path/to/sportapp_core/
   ```
3. Refresh browser dengan **hard reload** (Ctrl+Shift+R) agar CSS baru terambil.

## PostgreSQL — apakah perlu perubahan?

**TIDAK ADA** migrasi SQL yang perlu ditambahkan untuk batch ini.

Tema tetap disimpan di kolom `users.tema_warna` (VARCHAR) yang sudah ada — kode hanya membaca kolom yang sama. Palette baru `orange` dan `teal` bekerja otomatis begitu user memilihnya di `profile.php` (jika opsinya ditambahkan di dropdown), atau lewat query manual:
```sql
UPDATE users SET tema_warna='orange' WHERE id=1;
```

## Uji cepat

Setelah install, buka salah satu halaman berikut dan perhatikan:
- Bottom nav: sudut membulat + FAB Upload lingkaran dengan border putih menyatu.
- Drawer (buka menu kiri): header bergradasi warna tema + indikator kiri pada item aktif.
- Ubah `tema_warna` user di DB (misalnya jadi `emerald` atau `rose`), reload — **semua** komponen otomatis berubah warna.

— Lovable

# KawanKeringat — Redesign Package (Revisi Nov 2026, Partial)

Zip ini berisi **subset revisi** yang sudah selesai. Tidak semua 5 halaman
di-redesign ulang HTML-nya karena file-file tersebut sangat besar (1000–1900
baris PHP+HTML tercampur). Yang dikirim di sini adalah *fondasi* — Global
Theme Engine + Design System + Navigation Redesign — yang **otomatis
memperbarui tampilan seluruh halaman** (termasuk profile.php, index.php,
riwayat.php, kalori_mingguan.php, upload.php, dst.) tanpa mengubah PHP,
session, database, API, query SQL, maupun nama field.

## Isi Zip

| File | Status | Keterangan |
|---|---|---|
| `includes/theme_user.php` | **Rewrite** | Emit CSS Variable global: `--primary`, `--primary-light`, `--primary-dark`, `--primary-soft`, `--surface`, `--surface-2`, `--text-primary`, `--text-secondary`, `--border`, `--radius`, `--shadow-*`, `--gradient-primary`. Juga override `--bs-primary` agar seluruh komponen Bootstrap ikut tema aktif otomatis. |
| `includes/header.php` | **Patch** | Menambah `<link href="/assets/css/kk-redesign.css">` setelah `app-v3.css`. Sisanya tidak diubah. |
| `includes/bottom_nav.php` | **Rewrite (UI only)** | Bottom Nav premium Strava-style: bar tinggi 76px, rounded top, indicator garis di atas item aktif, ikon+label mengikuti tema, animasi press 200ms. **Floating Upload Button** kini bulat sempurna 64px, naik 22px dari bar, di-halo dengan ring `--surface` sehingga terlihat "notched" menyatu dengan navigation (bukan menempel). Warna FAB memakai `--gradient-primary`. |
| `assets/css/kk-redesign.css` | **Baru** | Design system global: card premium (radius 18px, shadow halus), buttons, forms, hero profile, kk-stat grid, feed card ala Strava, filter chips, badge carousel, podium leaderboard, timeline notifikasi, heatmap card, table→card responsif (`.kk-table-cards`), accordion menu (`.kk-accordion`). Semua warna 100% pakai CSS Variable — ganti tema di `profile.php` = seluruh UI ikut berubah. |

## Cara Deploy (Local, PHP + PostgreSQL)

1. Extract zip ini ke root project KawanKeringat, **overwrite** file yang ada:
   ```bash
   unzip kk-redesign-partial.zip -d /path/ke/sportapp_core/
   ```
2. Tidak ada perubahan schema PostgreSQL yang diperlukan. Tabel `users`
   sudah punya kolom `tema_warna` dan `dark_mode` (dipakai
   `includes/theme_user.php`). **Tidak perlu jalankan .sql tambahan.**
3. Reload halaman apa pun di aplikasi — Global Theme Engine langsung aktif.

## Yang Sudah Otomatis Terpenuhi via Design System Baru

Dari 5 poin permintaan:

- **Poin 2 (Navigation System)** — **SELESAI penuh.** Drawer, Bottom Nav,
  FAB, Header, Button, Card, Link, Badge, Progress, Switch, Icon aktif
  semuanya sekarang memakai `--primary` / `--primary-light` / `--primary-dark`
  yang berasal dari pilihan tema user. FAB Upload sudah notched ala Strava.
- **Warna tema konsisten di semua halaman** (bagian akhir dari poin 1, 3, 4, 5)
  — otomatis melalui `theme_user.php` + `kk-redesign.css`.
- **Radius 18–20px, shadow halus, whitespace lega, tipografi modern,
  Plus Jakarta Sans, progress bar bertema, animasi 200ms** — otomatis
  di semua card/button/form seluruh aplikasi.
- **Table → card list responsif** — cukup tambah class `.kk-table-cards`
  pada wrapper `<div>` di sekitar `<table>` (dan `data-label="..."` pada
  setiap `<td>`) untuk mengubahnya jadi card di mobile.

## Yang MASIH Perlu Dikerjakan (Belum Termasuk di Zip Ini)

Restrukturisasi HTML per-halaman (memindahkan section ke accordion/tab,
menyusun ulang urutan hero → stats → feed, mengubah `<table>` jadi feed
card Strava, dsb.) untuk:

- `profile.php` — perlu wrap semua form (Edit Profil, Ganti Password, Data
  Kesehatan, WhatsApp, Tema Aplikasi, Kondisi Terkini, Strava, Hiking,
  Perlengkapan, Pertemanan, Titip Pesan) ke dalam Bootstrap Accordion
  dengan class `.kk-accordion`, dan mengganti hero atas dengan `.kk-hero`.
- `index.php` — susun ulang: Hero Greeting → Quick Stats horizontal scroll
  → Jadwal Terdekat → Social Feed (pindah ke atas, pakai `.kk-feed-card`)
  → Story → Leaderboard Top 5 → Statistik Mingguan → sisanya accordion.
- `riwayat.php` — hero stats → filter chips `.kk-chips` → activity feed
  Strava-style → sisanya (monitoring, leaderboard, kalender, sesi) jadi
  section collapsible. Leaderboard pakai `.kk-podium`.
- `kalori_mingguan.php` (dipakai sebagai "kalori.php" — **catatan: tidak
  ada file `kalori.php` di zip asli**, hanya `kalori_mingguan.php`,
  `kalori_badminton.php`, `kalori_futsal.php`, `kalori_pingpong.php`,
  `kalori_renang.php` — mohon konfirmasi mana yang dimaksud). Hero card
  sisa kalori + `.kk-stat-grid` untuk ringkasan + grafik mingguan
  di `.card` besar + input makanan collapsible.

Semua class CSS untuk pekerjaan di atas sudah tersedia di
`assets/css/kk-redesign.css` (lihat komentar section-per-section di file
tersebut). Restrukturisasi HTML-nya bisa dilanjutkan di iterasi berikut
tanpa menyentuh logika PHP.

## Catatan PostgreSQL

**Tidak ada** migrasi tambahan yang diperlukan untuk perubahan di zip ini.
Kolom `users.tema_warna` (VARCHAR) dan `users.dark_mode` (SMALLINT/INT)
sudah dipakai kode existing dan sudah tersedia di `sportapp.sql`.

# Revisi 1 Jun 2026 — Splash, Onboarding, Skeleton, FAB Chat, Ilustrasi Auth

File yang berubah / baru (cukup timpa pada instalasi yang sudah ada):

## Baru
- `splash.php` — Splash Screen (auto 1.8 dtk → onboarding).
- `onboarding.php` — 3 layar onboarding (swipe / tombol Lanjut / Lewati). Setelah selesai set cookie `hf_onboarded` (1 tahun) → ke `login.php`.
- `includes/skeleton.php` — CSS + JS skeleton loading global (shimmer). Otomatis di-include dari `includes/header.php`.
- `assets/img/auth-illustration.svg` — Ilustrasi olahraga untuk halaman Login & Daftar.

## Dimodifikasi
- `includes/header.php` — include `skeleton.php` setelah `<main>` agar semua halaman dapat efek skeleton dan transisi.
- `includes/dm_floating.php` — FAB chat punya 3 status: **visible / pill (tulisan "Pesan") / hidden total**. Tombol × di FAB menyembunyikan ke pill, × di pill menyembunyikan total. Untuk memunculkan kembali tersedia tombol kecil bulat di kanan-bawah. Status disimpan di `localStorage` (`hf_dm_state`).
- `login.php` — Tambah ilustrasi di hero + redirect ke `splash.php` saat kunjungan pertama (tidak ada cookie `hf_onboarded`).
- `register.php` — Tambah ilustrasi di hero.

## Alur Pertama Buka Aplikasi
```
/index.php (belum login)
  → /login.php
     (cookie hf_onboarded kosong)
     → /splash.php  (1.8 detik)
       → /onboarding.php  (3 slide)
         → set cookie hf_onboarded=1
         → /login.php?skip_intro=1
```
Untuk uji ulang alur splash+onboarding: hapus cookie `hf_onboarded` di browser.

## Skeleton Loading
- Semua link internal & submit form otomatis memunculkan overlay skeleton fullscreen sampai halaman baru tampil (event `pageshow`).
- Elemen dengan teks "Memuat…/Loading…/Mencari…/Menghitung…/Mendeteksi…" yang berisi `.spinner-border` akan diganti otomatis dengan shimmer skeleton — tidak perlu edit halaman lain.
- Helper untuk dipakai manual di halaman lain (revisi lanjutan):
  ```html
  <div data-hfskel="cards" data-hfskel-n="5"></div>
  <div data-hfskel="grid"  data-hfskel-n="8"></div>
  ```
  atau via JS:
  ```js
  HFSkel.inject('#list', HFSkel.cardList(4));
  HFSkel.inject('#grid', HFSkel.grid(8));
  ```

## PostgreSQL
**Tidak ada perubahan skema database.** Tidak perlu menjalankan SQL baru. Semua data dari `sportapp.sql` & migrations lama tetap dipakai apa adanya.

## Belum direvisi (sengaja, sesuai permintaan partial)
- Halaman per-modul (jajanan.php, tempat.php, dll) belum di-rewrite memakai skeleton native pada blok kontennya — tapi tetap dapat skeleton fullscreen saat navigasi & otomatis penggantian spinner "Memuat…" via JS.

# Revisi 1 Juni 2026 — Partial Update (login, register, jajanan)

Arsip ini **HANYA berisi file yang sudah direvisi pada iterasi ini**.
Salin ke folder `sportapp_core/` yang sudah ada, **timpa file lama**.
Data lama (di PostgreSQL & folder `uploads/`) **tidak terpengaruh**.

## File dalam arsip

| File | Perubahan |
|------|-----------|
| `login.php` | (1) Gradient hero & tombol diubah dari navy gelap ke **sky→indigo (`#0ea5e9 → #6366f1`)** menyamai gradient dashboard aplikasi (`index.php`, `includes/header.php`, dll). (2) Tombol **"Daftar Akun Baru"** dan **"Lanjut ke Dashboard tanpa Login"** kini menampilkan **ikon spinner + tulisan "Memproses…"** saat diklik, sama seperti tombol Masuk. (3) Theme color & captcha box ikut diselaraskan ke palette sky/indigo. |
| `register.php` | (1) Gradient hero & tombol diselaraskan ke **sky→indigo** (sama seperti login & dashboard). (2) Tombol submit sudah punya spinner + "Mendaftarkan…" (sejak revisi sebelumnya — tetap dipertahankan). (3) Theme color diselaraskan. |
| `jajanan.php` | **Scroll popup pesanan diperbaiki.** Akar masalah: pada `#tokoModal` ada `<form>` di antara `.modal-content` dan `.modal-body`, sehingga layout flex bawaan Bootstrap `modal-dialog-scrollable` **putus** → `.modal-body` tidak punya tinggi terbatas → tidak bisa di-scroll & footer "Bayar via Midtrans" terdorong keluar layar. Diperbaiki dengan menambahkan rule CSS scoped `#tokoModal …` yang memulihkan flex chain `modal-content → form → modal-body` + sticky footer (lihat blok komentar di file). Aman untuk mobile (full-height) maupun desktop. |

## Belum dikerjakan di iterasi ini (catatan untuk revisi berikut)

Sesuai instruksi *"Jika belum semua direvisi, tetap dicompile jadi zip
saja sebagiannya"* — item berikut **belum** masuk arsip ini:

- **Skeleton screen global** pada semua halaman saat loading data
  (`index.php`, `berita.php`, `kalkulator.php`, `profile.php`,
  `riwayat.php`, `donasi.php`, dst.). Perlu dibuat
  `includes/skeleton.php` (CSS shimmer + helper PHP partial), lalu
  di-`include` di tiap halaman yang me-load data via PHP loop atau AJAX,
  diganti dengan blok `.skl-card` saat kondisi loading.
- **Skeleton "pindah halaman dulu, baru muncul skeleton"** —
  intercept klik link/tombol nav di `includes/bottom_nav.php` &
  `includes/header.php` untuk show overlay skeleton sebelum browser
  benar-benar berpindah, lalu di halaman tujuan tampilkan skeleton
  sampai data render. Butuh perubahan koordinasi di banyak halaman,
  paling baik dikerjakan di iterasi terpisah.

## PostgreSQL

**Tidak ada migration baru** untuk arsip ini. Semua migration lama
(`migrations_*jun2026*.sql`, `migrations_31mei*.sql`,
`migrations_2jun2026_toko.sql`, `migrations_4jun2026.sql`) tetap berlaku
dan **tidak perlu dijalankan ulang** jika sudah pernah dijalankan
(semua idempotent).

## Cara test lokal

```bash
php -S 0.0.0.0:8080 -t .
```

1. Buka `/login.php` → gradient harus sky→indigo (biru muda ke ungu).
   Klik **Daftar Akun Baru** → tombol berubah jadi spinner "Memproses…".
   Klik **Lanjut ke Dashboard tanpa Login** → idem.
2. Buka `/register.php` → gradient sama dengan login & dashboard.
3. Buka `/jajanan.php` → klik **Pesan Sekarang** pada salah satu produk
   → modal toko terbuka → **scroll ke bawah** harus berfungsi mulus
   (form alamat, deteksi lokasi, ringkasan total terlihat). Tombol
   **Bayar via Midtrans** tetap menempel di bawah (sticky) walaupun
   keyboard mobile muncul.

## Stack

Tetap **PHP + PostgreSQL** murni — tanpa React, tanpa npm.
Aman di-running di local (Apache/Nginx/`php -S`).

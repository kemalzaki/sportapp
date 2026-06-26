# Revisi 26 Juni 2026 — sportapp_core (partial)

Arsip ini berisi **hanya file yang direvisi** pada permintaan tanggal 26 Juni 2026.
Ekstrak ke folder project `sportapp_core` Anda (overwrite file yang sama).
Tidak ada perubahan skema database — `.sql` lama tidak perlu diubah dan
data lama tidak dihapus.

## Daftar file dalam zip

```
includes/header.php
riwayat.php
run.php
kalori_mingguan.php
live_tracking.php
```

## Revisi yang dikerjakan

1. **Tampilan desktop tidak lagi muncul versi mobile**
   - File: `includes/header.php`
   - Media query `@media (min-width: 992px)` diperluas: `html, body` dan semua
     wrapper kontainer (`.container`, `body > div`, `.kk-shell`, dst.)
     dipaksa `max-width: 1140px`, lebar penuh, tanpa frame mobile 480px.

2. **Tombol "Eksternal" di leaderboard `penggaet_eksternal` (riwayat.php)**
   - Ada tombol berlabel **Eksternal** di samping skor "N teman" tiap baris
     leaderboard. Klik → modal menampilkan daftar member eksternal yang
     dibawa user tersebut (nama tamu, jumlah kali ikut, tanggal terakhir).
   - Endpoint AJAX baru: `riwayat.php?action=ext_list&user_id=...&period=...`
     (sudah mengikuti pilihan periode mingguan/bulanan/all-time).

4. **Tren Kehadiran Mingguan dipindah ke ATAS Riwayat Sesi**
   - File: `riwayat.php`. Card "Tren Kehadiran Mingguan — Semua Anggota"
     sekarang berada di kolom kanan, persis di atas card "Riwayat Sesi".

5. **Hub Islami (islami.php) sudah dikunci hanya untuk paket KOMUNITAS**
   - File `islami.php` versi sebelumnya (R15 #5) sudah memuat gate ini.
     Tidak diubah lagi di zip ini, **tidak perlu ditambahkan**.

6. **Tombol "Popup Melayang" di `run.php`**
   - Pesan error "Browser Anda belum mendukung Document Picture-in-Picture"
     dihilangkan. Sekarang:
     - Jika browser MENDUKUNG Document PiP (Chromium 116+/HTTPS), tetap pakai
       PiP asli (perilaku lama).
     - Jika TIDAK mendukung, otomatis fallback ke **mini-window melayang
       di dalam halaman** — kotak peta kecil yang bisa di-drag, di-minimize
       ("Restore Down"), dan ditutup. Mirip "balon" mini browser.

7. **Kunci `kalori_mingguan.php` untuk paket Gratis**
   - Hanya paket **Pro** & **Komunitas** yang bisa akses.
   - Paket **Gratis** → tampilan banner kunci PRO + tombol Pesan via WhatsApp
     (memakai `paket_pro_lock_banner()` dari `includes/paket_helpers.php`).

8. **Kunci `live_tracking.php` untuk paket Gratis**
   - Sama seperti #7, hanya Pro & Komunitas yang bisa akses.

## Catatan PostgreSQL

Tidak ada migrasi/tabel baru yang perlu ditambahkan untuk revisi 26 Juni 2026
ini. Semua endpoint baru hanya membaca tabel yang sudah ada:

- `member_eksternal(jadwal_id, dibawa_oleh_id, nama_tamu, ...)`
- `jadwal(id, tanggal, ...)`
- `users(id, nama, paket, role, ...)`

Pastikan kolom `users.paket` ada (nilai: `gratis` / `pro` / `komunitas`).
Kalau belum, jalankan sekali di psql:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) NOT NULL DEFAULT 'gratis';
```

(Kolom ini sudah dipakai oleh revisi R14/R15 sebelumnya, biasanya sudah ada.)

## Cara pakai

1. Backup folder `sportapp_core` Anda.
2. Ekstrak zip ini ke folder `sportapp_core` (overwrite file yang sama).
3. Refresh browser (clear cache untuk file `header.php`/CSS bila perlu).
4. Selesai — tidak perlu menyentuh database.

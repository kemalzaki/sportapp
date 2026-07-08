# Revisi SportApp — Juli 2026 (patch parsial)

Zip ini HANYA berisi file yang direvisi. Copy-paste (overwrite) ke root
project di lokal, dengan struktur folder yang sama:

    profile.php                          → <root>/profile.php
    islami.php                           → <root>/islami.php
    pantau_progress_member.php           → <root>/pantau_progress_member.php
    kalori_mingguan.php                  → <root>/kalori_mingguan.php
    riwayat.php                          → <root>/riwayat.php
    includes/header.php                  → <root>/includes/header.php
    includes/bottom_nav.php              → <root>/includes/bottom_nav.php

## Ringkasan perubahan

1. **Menu "Pantau Progress Islami" hanya untuk superadmin**
   - `includes/header.php` : link di drawer "Member Organize" dibungkus
     `if ($__isSuperNav)`.
   - `islami.php`           : kartu "Khusus Admin > Pantau Progress
     Islami Member" pindah dari `$isAdmin` → `$isSuper`.
   - `pantau_progress_member.php` : guard akses diperketat menjadi
     `role === 'superadmin'`. Role admin/koordinator/pic sekarang akan
     mendapat pesan "khusus admin/koordinator" — jika perlu diakses
     kembali, longgarkan cek di baris `if ($role !== 'superadmin')`.

2. **profile.php — Nama & Username dirapikan di bawah Foto Profil**
   - Nama & username sekarang dibungkus `.prof-identity` (flex column,
     center), dengan tombol edit inline (`#btnEditNama`,
     `#btnEditUsername`) yang memang sudah dipanggil oleh script
     inline lama tapi belum ada tombolnya. Sekarang klik tombol pensil
     memanggil prompt edit yang sudah ada.

3. **Bottom navigation tidak "hilang" saat pindah halaman**
   - `includes/bottom_nav.php` menambahkan CSS View Transitions API
     (`@view-transition { navigation: auto; }` + `view-transition-name:
     gj-nav`) sehingga bottom nav terlihat tetap di tempat, konten
     halaman yang cross-fade. Berfungsi otomatis di browser berbasis
     Chromium ≥ 126 (termasuk Capacitor Android WebView modern).
     Browser lain fallback ke perilaku lama (tetap berfungsi, hanya
     tanpa transisi halus).

4. **kalori_mingguan.php — spoiler default TERTUTUP**
   - Script pembungkus spoiler diubah: `wrap.className = 'collapse'`
     (tanpa `.show`) dan `aria-expanded='false'`. Saat pertama kali
     mengunjungi halaman semua section spoiler tertutup.

5. **riwayat.php — Riwayat Aktivitas Publik dipindah paling atas + spoiler untuk section lain**
   - Script post-processor di akhir file:
     * Memindahkan card "Riwayat Aktivitas Publik" tepat di bawah H2
       (tanpa spoiler).
     * Membungkus card berikut sebagai spoiler default TERTUTUP:
       Monitoring Upload Harian, Kalender Aktivitas Publik, Kalender
       Aktivitas Saya, Leaderboard, Tren Kehadiran Mingguan, Riwayat
       Sesi, Riwayat Aktivitas Saya.

## PostgreSQL

Tidak ada perubahan skema untuk revisi ini — SEMUA perubahan bersifat
tampilan / logika PHP + JS. Tidak perlu menambahkan tabel/kolom baru
dan tidak perlu menjalankan migration tambahan. File .sql yang sudah
ada di project bisa dipakai apa adanya.

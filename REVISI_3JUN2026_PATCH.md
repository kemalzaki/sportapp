# Revisi 3 Juni 2026 — Patch SportApp

Berisi file yang DIREVISI saja. Salin & timpa ke folder project asli kamu (struktur sama).

## Daftar perubahan

1. **run.php** — Lari tetap merekam saat halaman pindah / HP layar mati (mirip Strava).
   - State sesi disimpan ke `localStorage` (lokasi, jarak, durasi, jeda).
   - Saat user kembali ke `/run.php`, sesi aktif otomatis dilanjutkan (auto-resume) — tidak akan ke-stop sendiri.
   - Notifikasi persistent "🏃 Tracking lari aktif" muncul agar user tahu sesi masih berjalan, tap untuk kembali.
   - Wake Lock + audio diam tetap aktif (sudah ada sebelumnya) supaya OS tidak men-suspend tab.
   - Service Worker didaftarkan untuk dukungan notifikasi background.

2. **includes/skeleton.php** — Halaman tampil dulu, baru skeleton muncul sebentar (tidak lagi menutup konten penuh). Web jadi terasa lincah & tidak lemot.

3. **includes/wa_notify.php** — Helper notifikasi WhatsApp (sudah ada di build sebelumnya, tetap dipertahankan):
   - `wa_notify_user($uid, $judul, $pesan, $jenis)` — kirim ke satu member.
   - `wa_notify_event($jadwalId, $judul, $pesan)` — kirim ke semua peserta event/jadwal (untuk pemberitahuan absensi olahraga).
   - `wa_notify_pic_admins($judul, $pesan)` — kirim ke admin PIC supaya mereka mengabari member di bawah koordinasinya.
   - Mode default: gratis pakai link `wa.me`. Bisa otomatis lewat WA Cloud API jika env `WA_CLOUD_TOKEN` + `WA_PHONE_ID` di-set.
   - Hook event/absensi sudah aktif di `admin/absensi.php` (tidak ada perubahan baru).

4. **profile.php** — Reminder WA untuk:
   - Pengalaman hiking/camping (jika belum diisi).
   - Perlengkapan olahraga yang dimiliki (jika belum diisi).
   - Section "Integrasi Strava" dihapus (lihat #7).

5. **index.php** — Pengaturan urutan tampilan:
   - Card **Donasi Kegiatan** di hero dihapus.
   - Urutan baru via JS reorder:
     1. Hero "Dashboard Olahraga Komunitas"
     2. Statistik (Total Sesi / Hadir / Member / Online)
     3. **Status yang Online** (pindah dari sidebar)
     4. **Event Terdekat** (pindah dari sidebar)
     5. **Jadwal Terdekat** (pindah dari sidebar)
     6. **Social Feed** (pindah ke atas, sebelumnya di sidebar)
     7. **Info & Wawasan** (pindah ke bawah Social Feed)

6. **includes/header.php** — Menu navigasi dibersihkan:
   - "Biaya Admin & Aplikasi" — dihapus dari offcanvas & dropdown admin.
   - "Donasi & Jajanan" + Rekening Donasi + Toko & Produk + Jajanan + Pesanan Jajanan — semuanya dihapus.
   - **Lacak HP Member** TETAP ADA (sesuai permintaan).

7. **Strava dihapus** — file berikut **harus dihapus** dari folder project asli:
   - `strava_connect.php`
   - `strava_webhook.php`
   - Section Strava di `profile.php` sudah dihapus dalam patch ini.

## PostgreSQL — yang perlu ditambah

Semua tabel yang dipakai (`run_sessions`, `user_pengalaman`, `user_perlengkapan`, `notifications`, `users.pic_user_id`, dll.) **sudah ada** di `sportapp.sql` build sebelumnya. **Tidak ada migrasi baru wajib** untuk patch ini.

Opsional (jika ingin aktifkan WA Cloud API otomatis tanpa interaksi user):
```sh
# .env / env.local.php
WA_CLOUD_TOKEN=...
WA_PHONE_ID=...
```

## Cara pasang

1. Extract `sportapp_patch_3jun2026.zip`.
2. Timpa file dengan path yang sama ke project asli kamu.
3. **Hapus** `strava_connect.php` & `strava_webhook.php` dari project asli.
4. Refresh browser (Ctrl+Shift+R) supaya cache JS/CSS skeleton kepakai versi baru.

## Catatan kinerja ("supaya web tidak berat")

- Skeleton tidak lagi blok layar → first paint langsung tampil.
- Reorder section pakai JS ringan, tanpa fetch tambahan.
- Tidak ada library baru ditambahkan.
- Asset CSS Bootstrap/Leaflet tetap dari CDN (cache browser).


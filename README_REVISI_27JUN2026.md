# Revisi 27 Juni 2026 — Islami pack (kajian, doa, dzikir, breadcrumb, mobile-only view)

## Daftar perubahan
1. **kajian.php**
   - Tombol *Bagikan ke WhatsApp* sekarang berisi info lengkap literatur:
     Judul, Penulis, Tipe, Kategori, Pemilik, Dibagikan oleh, Ringkasan (≤400 char),
     Link Web, Link Video YouTube, Link PDF (URL absolut), dan tagline aplikasi.
   - Tombol **Buka Web** dan **Lihat Video (YouTube)** kini langsung
     `target="_blank"` (buka tab baru) — modal iframe lama tetap ada tetapi
     tidak dipakai dari tombol utama.
   - Ditambahkan **breadcrumb** *Beranda › Islami › Kajian Literatur Buku*.

2. **doa.php**
   - **Breadcrumb** *Beranda › Islami › Doa Harian*.
   - Kolom **Judul** dan **Teks Arab** di form Tambah/Edit Doa kini berbentuk
     **WYSIWYG** (bold/italic/underline/list), area Arab otomatis RTL +
     font Amiri.
   - **Pagination 2 data per halaman** untuk:
     - *Doa Saya* — query string `?p_my=`
     - *Doa Aplikasi (Doa Harian Anak)* — query string `?p_app=`
   - **Suara Dewasa & Suara Anak-anak (TTS) dihapus**, beserta seluruh
     skrip SpeechSynthesis dan alert info-nya.

3. **dzikir.php**
   - Ditambahkan **breadcrumb** *Beranda › Islami › Dzikir Pagi/Petang*.

4. **Breadcrumb di semua halaman Islami sub-page lainnya**
   File berikut juga mendapat breadcrumb seragam
   *Beranda › Islami › <judul halaman>*:
   - hadist.php, sejarah_nabi.php, jadwal_sholat.php, kalender_hijriyah.php,
     challenge.php, leaderboard_islami.php, statistik_islami.php,
     artikel_sunnah.php, feed_islami.php, doa_antar_member.php,
     quran.php, quran_surah.php
   - Halaman yang sudah memiliki breadcrumb sebelumnya (tajwid, wudhu_tatacara,
     shalat_tatacara, shalat_rawatib, shalat_sunnah, rukun_islam,
     catatan_baca_buku) tidak diubah.

5. **catatan_hafalan.php** — perbaikan pagination
   - Tombol pagination kini langsung berfungsi pada render server-side pertama
     **tanpa harus klik Reset dulu**.
   - Logika lama (`bindRowActions` dipanggil setelah `loadList`) diganti dengan
     **event delegation** pada `#listBox`, sehingga element pagination yang
     dirender oleh PHP saat first paint juga ter-handle.

6. **islami.php**
   - Card **Kalender Hijriyah** dipindahkan ke posisi paling atas grid menu,
     **di atas card Al-Qur'an Digital**.

7. **includes/header.php** — Mobile-only view (#9)
   - Blok `@media (min-width: 992px)` yang dulunya memaksa layout desktop
     diganti **memaksa tampilan MOBILE** di semua ukuran layar:
     `body { max-width: 480px; margin: 0 auto; box-shadow; }`, top bar &
     bottom nav versi mobile tetap tampil.
   - Akibatnya membuka aplikasi di laptop / desktop pun memperlihatkan
     tampilan persis seperti di HP (frame mobile di tengah layar).

## PostgreSQL — perubahan skema
**Tidak ada migrasi tambahan diperlukan.**
- Kolom `doa_user.judul`, `arab`, `terjemah` tetap dipakai apa adanya
  (HTML hasil WYSIWYG disanitasi oleh `doa_sanitize_html()` sebelum disimpan).
- Catatan: jika sebelumnya `judul` adalah `VARCHAR(180)`, sekarang aplikasi
  memotong hingga 500 karakter (HTML). Bila ingin aman, jalankan:
  ```sql
  ALTER TABLE doa_user ALTER COLUMN judul TYPE VARCHAR(800);
  ```
  (opsional — tidak wajib bila konten judul tetap pendek).

## File yang ada di zip ini
- kajian.php
- doa.php
- dzikir.php
- islami.php
- catatan_hafalan.php
- includes/header.php
- hadist.php, sejarah_nabi.php, jadwal_sholat.php, kalender_hijriyah.php,
  challenge.php, leaderboard_islami.php, statistik_islami.php,
  artikel_sunnah.php, feed_islami.php, doa_antar_member.php,
  quran.php, quran_surah.php
- README_REVISI_27JUN2026.md (ini)

Tinggal **timpa file dengan nama yang sama** di project Anda.

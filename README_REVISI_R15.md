# Revisi R15 — 25 Juni 2026

Arsip ini **hanya berisi file yang direvisi** (bukan seluruh project).
Salin/timpa ke direktori project lokal Anda sesuai struktur folder.

## Daftar Perubahan

1. **catatan_hafalan.php** — Daftar Catatan Hafalan tidak muncul / loading
   terus menerus → daftar sekarang **dirender langsung di server** pada
   load pertama (tidak lagi menunggu AJAX). Filter pencarian tetap pakai
   AJAX, tapi URL relatif (`catatan_hafalan.php?...`) supaya tidak bermasalah
   di subfolder lokal, dan setiap error AJAX ditampilkan ke pengguna.
2. **catatan_hafalan.php** — Klik ayat di daftar tidak memunculkan
   surat/ayat → popup ayat sekarang mengambil data lewat **proxy server
   `api_quran_ayat.php`** (baru), tidak lagi langsung dari browser ke
   `equran.id`. Bebas dari masalah CORS/CSP browser saat run lokal.
3. **kajian.php** — Error PostgreSQL
   `COALESCE types smallint and boolean cannot be matched` → kolom
   `users.aktif` bertipe `SMALLINT`, query diperbaiki menjadi
   `COALESCE(aktif, 1) <> 0`.
4. **sejarah_nabi.php** — Klik "Lihat Ayat" tidak memunculkan surat/ayat →
   sama seperti #2, popup ayat dialihkan ke proxy `api_quran_ayat.php`.
5. **islami.php** — Menu Hub Islami dikunci untuk paket **Gratis & Pro**.
   Sekarang **hanya paket KOMUNITAS** yang bisa mengakses. Banner berisi
   tombol "Pesan Paket Komunitas via WhatsApp" ke **0813-8636-9207**.
6. **doa.php** — Tombol "Suara Dewasa / Anak-Anak" untuk Doa Bawaan
   Aplikasi tidak berbunyi → modul TTS ditulis ulang:
   - Menunggu daftar voice browser siap sebelum berbicara (Chrome
     memuat voice secara async).
   - Antrian utterance manual (judul → arab → terjemah) — bicara
     berikutnya hanya setelah `onend` agar tidak terpotong.
   - Workaround `synth.resume()` untuk bug queue stuck di Chrome.
   - Penekanan mode anak: pitch 1.6 + voice female; mode dewasa: pitch
     0.95 + voice male.
7. **shalat_tatacara.php** & **wudhu_tatacara.php** — Gambar
   `pollinations.ai` sering gagal/blank → gambar AI sekarang
   **digenerate oleh Lovable** dan disimpan lokal:
   - `assets/img/shalat/0.jpg` … `11.jpg` (12 langkah shalat)
   - `assets/img/wudhu/0.jpg` … `8.jpg` (9 langkah wudhu)

## File baru

- `api_quran_ayat.php` — proxy server-side untuk ambil ayat
  (sumber utama: equran.id v2, fallback: alquran.cloud).

## File yang direvisi

- `catatan_hafalan.php`
- `kajian.php`
- `sejarah_nabi.php`
- `islami.php`
- `doa.php`
- `shalat_tatacara.php`
- `wudhu_tatacara.php`

## File asset baru (gambar AI Lovable)

- `assets/img/shalat/0.jpg` … `assets/img/shalat/11.jpg`
- `assets/img/wudhu/0.jpg`  … `assets/img/wudhu/8.jpg`

## Catatan PostgreSQL

**Tidak ada migrasi SQL baru** yang perlu dijalankan untuk revisi R15.
Semua perbaikan murni di kode PHP / asset gambar.
(Catatan: jika nantinya ingin meng-upgrade member ke paket `komunitas`,
cukup `UPDATE users SET paket='komunitas' WHERE id=...;` — kolom `paket`
sudah ada sejak R14.)

## Cara pakai

1. Ekstrak `revisi_R15.zip`.
2. Timpa file-file di project lokal Anda dengan file yang sama di arsip
   ini (struktur folder dipertahankan).
3. Tidak perlu rebuild composer / dependencies.
4. Akses ulang halaman; cache browser sebaiknya di-hard-reload
   (Ctrl+F5) agar JS terbaru terpakai.
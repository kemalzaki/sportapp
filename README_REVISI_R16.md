# Revisi R16 — 25 Juni 2026 (paket halaman parsial)

Arsip ini berisi HANYA halaman yang direvisi pada batch ini, bukan seluruh
aplikasi. Salin/timpa file berikut ke folder aplikasi yang sudah ada (struktur
folder dipertahankan). Tidak ada data yang dihapus.

## Ringkasan perubahan

1. catatan_hafalan.php — Daftar catatan kini AJAX penuh (pencarian, filter
   surat, dan tombol pagination « 1 2 3 »). Dibatasi 5 catatan per halaman.

2. kajian.php — Tombol "Lihat Video" membuka popup (modal) pemutar YouTube
   (iframe), dan "Buka Web" membuka popup berisi iframe halaman web.

3. sejarah_nabi.php — Perbaikan tombol "Lihat Ayat" pada 25 Nabi & Rasul.
   URL pengambil ayat diubah jadi relatif (api_quran_ayat.php) agar tetap
   bekerja saat aplikasi dijalankan dari subfolder di lokal. Surat & ayat kini
   tampil di popup. Disertakan api_quran_ayat.php (proxy pengambil ayat).

4. islami.php — Halaman Hub Islami dikunci dan hanya bisa diakses paket
   Komunitas. Paket Gratis dan Pro melihat pesan penawaran + tombol pesan via
   WhatsApp 0813-8636-9207. (includes/paket_helpers.php disertakan.)

5. doa.php — Tombol "Suara Dewasa" & "Suara Anak-anak" pada Doa Bawaan
   diperkuat: ada priming audio, status terlihat (Memutar/Selesai/tidak
   didukung), perbedaan nada dewasa vs anak diperjelas, dan fallback suara
   bawaan. Fitur ini memakai Text-To-Speech bawaan browser (paling baik di
   Chrome/Edge terbaru). Pembuatan berkas audio terpisah belum dilakukan karena
   konektor audio (mis. ElevenLabs) belum tersedia di lingkungan ini.

6. shalat_tatacara.php & wudhu_tatacara.php — Tulisan "Ilustrasi AI (Lovable)" /
   "Gambar di-generate oleh AI" diganti menjadi "Hanya Gambar Ilustrasi".

## Yang perlu disiapkan di PostgreSQL

Hanya #4 (islami.php) yang butuh kolom database. Jalankan:

    psql -d sportapp -f migrations_r16.sql

Migrasi ini idempotent dan hanya menambahkan kolom users.paket
(gratis/pro/komunitas) bila belum ada — tidak menghapus data.
Untuk menguji akses Hub Islami, set salah satu user jadi komunitas:

    UPDATE users SET paket = 'komunitas' WHERE email = 'EMAIL_ANDA';

(User dengan role = 'admin' otomatis dianggap komunitas.)

Halaman lain (#1, #2, #3, #5, #6) tidak memerlukan perubahan skema; tabel yang
dipakai dibuat otomatis oleh aplikasi (CREATE TABLE IF NOT EXISTS).

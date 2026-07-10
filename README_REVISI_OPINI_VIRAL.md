# Revisi Opini Viral — Juli 2026 (R30)

## Ringkasan
Redesign total `opini_viral.php` menjadi **Dashboard Analisis Sentimen Opini Netizen Indonesia berbasis YouTube**. Google News RSS / BBC / CNN / Kompas / judul berita **tidak lagi dipakai**. Semua analisis dihitung dari komentar publik YouTube via YouTube Data API v3.

## Isi ZIP
- `opini_viral.php` — versi baru (drop-in replacement, taruh di root project menimpa file lama).
- `REVISI_OPINI_VIRAL_JULI2026.sql` — migrasi tabel baru (idempotent, aman dijalankan berulang).

## Langkah instalasi
1. Ekstrak isi ZIP → timpa `opini_viral.php` di root project.
2. Jalankan SQL:
   ```
   psql "$DATABASE_URL" -f REVISI_OPINI_VIRAL_JULI2026.sql
   ```
   Tabel baru: `opini_viral_search`, `opini_viral_comments`. Tabel lama `opini_viral` tidak diubah/dihapus (data tetap aman).
3. **Tambah environment variable** `YOUTUBE_API_KEY` di `config/env.local.php`:
   ```php
   putenv('YOUTUBE_API_KEY=AIza...ISI_KEY_ANDA');
   ```
   Dapatkan key gratis di Google Cloud Console → APIs & Services → enable **YouTube Data API v3**.
4. Pastikan salah satu env AI berikut sudah terisi (untuk klasifikasi sentimen + ringkasan):
   `OPENROUTER_API_KEY` / `GROQ_API_KEY` / `GEMINI_API_KEY`. Jika semua kosong, sistem otomatis fallback ke lexicon lokal (tetap jalan, akurasi lebih rendah).
5. Buka `/opini_viral.php`, coba keyword misal `PLN`, pilih periode `7 hari terakhir`, klik **Cari Opini**.

## Fitur baru
- Input keyword + selector **Periode Pencarian** (24 jam / 7 hari / 30 hari / custom tanggal).
- Ambil ≤20 video YouTube (bahasa Indonesia, region ID) + ≤100 komentar/video.
- Klasifikasi tiap komentar → Positif / Netral / Negatif + confidence + alasan (AI batch, fallback lexicon).
- Statistik total + Pie Chart + Bar Chart (Chart.js).
- Word Cloud (wordcloud2.js) dengan stopwords Bahasa Indonesia.
- Ringkasan AI + daftar topik yang sering dibahas.
- Kartu komentar (username, channel, judul video, isi, sentimen, confidence, like, waktu, link ke YouTube).
- Filter: Semua / Positif / Netral / Negatif.
- Export CSV / Excel (.xls) / PDF (HTML print-friendly).
- Cache hasil pencarian 30 menit di `opini_viral_search`.
- Pesan khusus jika komentar kosong: *"Belum ditemukan komentar publik untuk kata kunci tersebut."*

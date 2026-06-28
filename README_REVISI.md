# Revisi SportApp — 28 Juni 2026 (R25)

Arsip ini berisi HANYA file yang berubah. Salin/menimpa ke struktur project asli.

## Daftar perubahan
1. `survival.php` — Rekomendasi Hutan diubah dari array provinsi statis menjadi **generate via AI (Gemini)**.
   Input: string daftar kota/kabupaten dipisah koma (cth: `Bandung, Bogor, Sukabumi`).
   Tambahan endpoint POST `_action=forest_ai` (pakai `gemini_text()` dari `includes/ai_gemini.php`).
   **Tidak perlu migrasi DB.**
2. `admin/pengeluaran.php` — Tombol pensil **Edit** diperbaiki. Sebelumnya ada dua IIFE yang
   sama-sama bind handler ke `.btn-edit-peng` sehingga modal "berkedip" lalu tertutup.
   Sekarang pakai **event delegation tunggal** di `document` + `Modal.getOrCreateInstance`.
3. `includes/bottom_nav.php` — Layout PWA bottom nav dirapikan: tinggi seragam, label
   tidak terpotong, FAB Upload tidak menutup item lain, padding aman + safe-area iOS.
4. `admin/paket_member.php` —
   - FIX form bersarang (`<form method=post>` di dalam `<form method=get>`) yang memicu
     warning/error HTML invalid.
   - **Auto-seed** `nav_menu` saat tabel kosong, sesuai struktur PWA bottom nav
     (Beranda/Aktivitas/Upload/Kalori/Saya) + item drawer & top umum.
     Cukup buka halamannya satu kali → data terisi otomatis.
   - Pastikan kolom `paket` ada (idempotent `ALTER TABLE … IF NOT EXISTS`).
5. `opini_viral.php` —
   - **Menampilkan komentar netizen** (sampai 5 komentar teratas per post Reddit) di kartu.
   - Lexicon sentimen diperbaiki: kata "politik / viral / demo / heboh / protes"
     **dihapus dari daftar NEG** (itu sebab feed Politik selalu jadi "rendah").
   - Skor pakai frekuensi kata + memperhitungkan komentar, bukan hanya judul.
   - Nitter (Twitter/X) pakai **multi-mirror retry** (privacydev → poast → nitter.net).
   - Migrasi otomatis: `ALTER TABLE opini_viral ADD COLUMN IF NOT EXISTS komentar TEXT`.
6. `artikel_olahraga.php` — Peralatan basket sekarang punya **gambar masing-masing**
   (bola, sepatu, jersey, ring). Gambar baru ada di `assets/basket_*.jpg`.

## File baru (assets) — taruh di `assets/`
- `basket_bola.jpg`
- `basket_sepatu.jpg`
- `basket_jersey.jpg`
- `basket_ring.jpg`

## Migrasi PostgreSQL yang perlu dijalankan (opsional — script juga melakukan otomatis)
```sql
ALTER TABLE opini_viral ADD COLUMN IF NOT EXISTS komentar TEXT;
ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket VARCHAR(20);
```
Tidak ada data yang dihapus. Data lama tetap utuh.

## #7 — Cara "ganti region" Gemini agar flyover.php (sinkronisasi lirik) jalan lagi di Indonesia
Pesan error `"User location is not supported for the API use"` muncul karena
**akun/API Key Google AI Studio** yang Anda pakai terdaftar di region yang TIDAK
didukung untuk model Gemini tertentu (Indonesia memang termasuk yang sering kena).
Region "diikat" ke akun Google + lokasi billing, **bukan ke server PHP Anda**. Jadi
mengubah lokasi server tidak menyelesaikan masalah.

Pilihan solusi (urut dari paling sederhana):

1. **Pakai API Key dari Google Cloud (Vertex AI), bukan AI Studio.**
   - Buat project di Google Cloud Console → Enable *Vertex AI API*.
   - Set region project: `us-central1` (atau `asia-southeast1` yang sudah support Gemini).
   - Buat Service Account, download JSON, generate Access Token via OAuth2.
   - Ganti endpoint `generativelanguage.googleapis.com` ke
     `https://us-central1-aiplatform.googleapis.com/v1/projects/<PROJECT>/locations/us-central1/publishers/google/models/<MODEL>:generateContent`.
   - Vertex AI menerima request dari Indonesia tanpa block region.

2. **Gunakan API Key Google AI Studio dari akun Google yang lokasi billing-nya US/SG.**
   Jika punya akun Google Workspace/billing di luar Indonesia, key tersebut otomatis tidak terblok.

3. **Routing via proxy/Cloudflare Worker di region yang didukung (US/SG/EU).**
   - Deploy worker sederhana yang meneruskan request ke `generativelanguage.googleapis.com`.
   - Di `includes/ai_gemini.php` ganti base URL ke `https://<worker>.workers.dev/v1beta/...`.
   - Header `Authorization: Bearer <KEY>` tetap dipakai. Ini paling cepat tanpa migrasi ke Vertex.

4. **Pakai model `gemini-1.5-flash` atau `gemini-2.0-flash`** (region support lebih luas
   dibanding `gemini-2.5-pro`). Coba dulu sebelum migrasi ke Vertex.

> Catatan: jangan lupa di `includes/ai_gemini.php` sudah ada deteksi pesan
> "User location is not supported"; setelah pindah ke Vertex/proxy, error tersebut
> hilang dan sinkronisasi lirik di `flyover.php` jalan normal.

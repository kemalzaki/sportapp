# Revisi R14 — 25 Juni 2026

File-file di dalam zip ini adalah **revisi parsial** (bukan seluruh aplikasi). Letakkan langsung di root project menimpa file lama (struktur sudah disesuaikan).

## Daftar file

| File | Item revisi |
|---|---|
| `islami.php` | #1 Lock fitur PRO + tombol WA, #10 Card Tata Cara Wudhu |
| `wudhu_tatacara.php` (BARU) | #10 Halaman Tata Cara Wudhu + gambar AI |
| `shalat_tatacara.php` | #4 Gambar AI tiap gerakan shalat |
| `shalat_sunnah.php` | #5 Terjemah Indonesia doa Duha & Tahajud |
| `doa.php` | #3 Tombol Play TTS Dewasa & Anak untuk doa bawaan |
| `kajian.php` | #7 Pemilik literatur, #8 CRUD kategori + filter, #9 Pagination |
| `login.php` | #6 Member non-aktif tidak bisa login |
| `admin/members.php` | #2 Kolom Paket fitur (gratis/pro/komunitas) |
| `includes/paket_helpers.php` (BARU) | Helper fitur PRO |
| `migrations_r14.sql` | Migrasi PostgreSQL (idempotent) |

## Migrasi PostgreSQL yang perlu dijalankan

```bash
psql -d sportapp -f migrations_r14.sql
```

Migrasi membuat / mengubah:
- `users.paket` (VARCHAR — gratis/pro/komunitas) — untuk fitur PRO & filter.
- `users.aktif` (BOOLEAN) — pastikan kolom ini ada (sudah dari revisi 14 Juni 2026).
- Tabel baru **`kajian_kategori`** (id, nama, slug, warna) + seed 13 kategori default.
- Tabel baru **`kajian_pemilik`** (kajian_id, user_id, nama_eksternal) — relasi M:N pemilik literatur.
- `islami_kajian.kategori` — pastikan kolom ada.

Tidak ada data yang dihapus. Semua perubahan tabel pakai `IF NOT EXISTS` / `ON CONFLICT DO NOTHING` sehingga aman dijalankan berulang.

## Catatan teknis per item

### #1 Fitur PRO di `islami.php`
- Helper baru `includes/paket_helpers.php` (fungsi `paket_user()`, `paket_is_pro()`, `paket_pro_lock_banner()`, dst.).
- Jika user paket = `gratis` → seluruh halaman Hub Islami dikunci, hanya menampilkan banner "Pesan PRO via WA" ke **0813-8636-9207** dengan pesan otomatis.
- Admin otomatis dianggap paket **komunitas** (akses penuh).

### #2 Paket fitur di `admin/members.php`
- Tambahan kolom tabel **Paket Fitur** dengan dropdown (Gratis / PRO / Komunitas), simpan via AJAX form submit.

### #3 Play TTS di `doa.php` (Doa Bawaan Anak-Anak)
- Pakai **Web Speech API** (`speechSynthesis`) → built-in browser, tidak perlu file audio.
- Tombol **🧑 Suara Dewasa** (pitch normal, rate normal) dan **👶 Suara Anak-Anak** (pitch tinggi 1.6, rate sedikit lebih cepat, voice perempuan bila tersedia).
- Otomatis baca: Judul (ID) → Teks Arab (AR) → Terjemah (ID).

### #4 & #10 Gambar AI Shalat & Wudhu
- Gambar di-generate **on-demand** via [pollinations.ai](https://image.pollinations.ai) — gratis, tanpa API key, tanpa biaya.
- Setiap gerakan punya prompt deskriptif sendiri (12 gerakan shalat + 9 langkah wudhu).
- `<img loading="lazy">` agar tidak memberatkan halaman.

### #5 Terjemah Duha & Tahajud
- Terjemah Indonesia ditampilkan di blok hijau di bawah teks Arab doa.

### #6 Login block member non-aktif
- Setelah password OK, cek `users.aktif`. Jika `false` dan role bukan admin → tolak login dengan pesan + catatan dari admin.

### #7 Pemilik literatur
- Form tambah/edit literatur kini punya 2 input: dropdown `<select multiple>` daftar member aktif + textarea "Pemilik Eksternal" (pisah dengan koma/baris baru).
- Pemilik ditampilkan sebagai badge di tiap kartu literatur (👤 untuk member, 🌐 untuk eksternal).

### #8 CRUD Kategori
- Tabel `kajian_kategori` (admin bisa tambah, edit, hapus, beri warna badge).
- Form filter & form tambah literatur kini ambil kategori dinamis dari DB.

### #9 Pagination Kajian
- Server-side, 10 item per halaman, dengan navigasi ‹ 1 2 3 ... ›. Filter `?q=` dan `?kat=` tetap dipertahankan di URL pagination.

## Tidak ada perubahan data sensitif
Migrasi hanya `ADD COLUMN` / `CREATE TABLE IF NOT EXISTS` / `INSERT ... ON CONFLICT DO NOTHING`. Data lama (users, islami_kajian, dll.) **tidak disentuh**.

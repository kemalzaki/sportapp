# Catatan Revisi — 11 Juni 2026 (v2)

File yang direvisi pada arsip ini:

1. `includes/header.php`
   - Menambahkan menu navigasi mobile (drawer) & dropdown desktop:
     - **Gaya Hidup** → `/gaya_hidup.php`
     - **Kalori Mingguan (Makanan)** → `/kalori_mingguan.php`
     - **Kalori Ping Pong** → `/kalori_pingpong.php` (baru)
     - **Kalori Futsal** → `/kalori_futsal.php` (baru)

2. `kalori_mingguan.php` — FIX error PostgreSQL
   - Penyebab: tabel `kalori_log` sudah dibuat oleh `kalori_badminton.php`/`kalori_renang.php`
     dengan skema workout (kolom: jenis, intensitas, berat_kg, menit, met, kalori,
     dibuat_pada) — TIDAK memiliki kolom `tanggal`. Halaman `kalori_mingguan.php`
     mencoba pakai tabel yang sama untuk catatan MAKANAN sehingga error:
     `column "tanggal" does not exist`.
   - Solusi: pencatatan makanan dipindah ke tabel terpisah `kalori_makanan_log`.
     Halaman ini juga melakukan `CREATE TABLE IF NOT EXISTS` di awal sehingga
     aman dijalankan tanpa migrasi manual.

3. `migrations_revisi_baru.sql`
   - `kalori_log` (versi makanan) **dihapus** dari migrasi (bentrok dengan tabel
     workout yang sudah ada).
   - Diganti dengan `kalori_makanan_log` (+ index `idx_kalori_mkn_user_tgl`).
   - `iptv_channels` dan `gaya_hidup_log` & `kalori_target` tetap.

4. `index.php` — IPTV admin sekarang dipakai
   - Sebelumnya: index.php selalu mengunduh playlist M3U dari GitHub
     (`mgi24/tvdigital`), jadi perubahan di `/admin/iptv.php` (tabel
     `iptv_channels`) tidak terlihat.
   - Sekarang: index.php prioritas baca dari tabel `iptv_channels` (kolom
     `aktif` menentukan status tampil/disabled). Bila tabel kosong/belum ada,
     fallback ke playlist M3U eksternal (perilaku lama).
   - Blocklist hardcoded di index.php hanya berlaku untuk sumber M3U
     eksternal — saat memakai data DB admin, semua channel dari admin
     ditampilkan apa adanya.

5. `kalori_pingpong.php` (BARU) dan `kalori_futsal.php` (BARU)
   - Mengikuti pola `kalori_badminton.php`/`kalori_renang.php` (tabel
     `kalori_log` workout, kolom `jenis`).
   - Nilai MET (Compendium of Physical Activities):
     - Ping Pong: 3.5 / 4.0 / 5.0 / 7.0
     - Futsal:    6.0 / 8.0 / 9.0 / 10.0
   - Tabel `kalori_log` auto-dibuat (IF NOT EXISTS) bila belum ada.

---

## Yang perlu dijalankan di PostgreSQL lokal

Jalankan `migrations_revisi_baru.sql` (sudah diperbarui di arsip).
Aman dijalankan berulang — semua statement pakai `IF NOT EXISTS` / `ON CONFLICT`,
TIDAK menghapus data apa pun.

```bash
psql -U <user> -d <db> -f migrations_revisi_baru.sql
```

Atau cukup buka `kalori_mingguan.php`, `kalori_pingpong.php`,
`kalori_futsal.php` sekali — tabel akan dibuat otomatis oleh PHP.

Tidak ada perubahan kolom pada tabel yang sudah ada, jadi data lama
(`kalori_log` workout, `gaya_hidup_log`, `iptv_channels`, `kalori_target`)
tetap utuh.

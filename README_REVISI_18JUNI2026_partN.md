# Revisi 18 Juni 2026 — Part N (Lanjutan E)

## Ringkasan
Menambahkan opsi **sumber Defisit Kalori** di `kalori_mingguan.php` agar pengguna dapat:
1. **Input manual** nilai defisit (kkal/hari), atau
2. **Mengambil otomatis** dari aktivitas Jogging di `riwayat.php` (tabel `upload_harian`),
3. **Gabungan** keduanya, atau
4. Tetap memakai mode lama (**auto** = semua workout di tabel `kalori_log`).

## File yang berubah
- `kalori_mingguan.php` — tambah tabel `kalori_defisit_setting`, handler POST
  `_action=defisit_setting`, logika perhitungan `$burnMap`/`$burnDetail` yang
  mengikuti pilihan sumber, dan kartu UI "Sumber Defisit Kalori" di atas kartu
  ringkasan defisit/surplus.
- `migrations_revisi_18juni2026_partN.sql` — DDL `kalori_defisit_setting`
  (opsional, sudah idempotent `CREATE TABLE IF NOT EXISTS` di PHP).

## PostgreSQL yang perlu ditambahkan
Hanya **satu tabel baru**: `kalori_defisit_setting` (lihat file SQL).
Tidak ada perubahan pada tabel lain. Data lama (kalori_makanan_log,
kalori_target, kalori_log, upload_harian) tidak disentuh.

Jalankan manual bila ingin pre-provision:
```bash
psql "$DATABASE_URL" -f migrations_revisi_18juni2026_partN.sql
```
Jika tidak dijalankan manual, tabel akan dibuat otomatis saat user pertama
kali membuka `kalori_mingguan.php`.

## Cara pakai
1. Buka `/kalori_mingguan.php`.
2. Di kartu **"Sumber Defisit Kalori"** pilih salah satu:
   - *Otomatis* — perilaku lama (semua workout dari `kalori_log`).
   - *Riwayat Jogging saya* — membaca `upload_harian` dengan `jenis ILIKE '%jog%'`
     atau `'%lari%'` atau `'%run%'`.
   - *Input manual* — isi field "Manual defisit (kkal/hari)".
   - *Gabungan: Jogging + Manual* — keduanya dijumlahkan.
3. Klik **Simpan**. Kartu ringkasan defisit/surplus dan grafik otomatis ikut.

## Catatan teknis
- Validasi sumber dilakukan di server (whitelist 4 nilai).
- Nilai manual dibatasi 0–5000 kkal/hari.
- Nilai manual ditambahkan ke tiap hari pada minggu berjalan **sampai hari ini**
  saja, agar hari yang belum lewat tidak overshoot.
- CSRF token tetap digunakan (`csrf_check()`).

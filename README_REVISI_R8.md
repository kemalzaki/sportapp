# Revisi R8 — 22 Juni 2026

ZIP ini berisi **revisi parsial** untuk SportApp (PHP + PostgreSQL).
Salin / overwrite ke folder project Anda. Tidak ada file yang dihapus.

## File yang berubah

```
migrations_r8.sql           ← WAJIB dijalankan (idempotent)
index.php                   ← #1 sapa_log tanpa ON CONFLICT + #6 filter AUTO-absen
kalori_mingguan.php         ← #3 sisa = target − konsumsi + terbakar
                              #4 target & defisit pakai check-then-update/insert
api_dm.php                  ← #2 polling DM tahan kolom delivered_at/read_at hilang
dm.php                      ← (tidak diubah, hanya disertakan untuk acuan)
admin/jadwal.php            ← (tidak diubah; error berasal dari trigger
                              apply_kondisi_to_absensi yg sudah diperbaiki)
includes/migrations_v7.php  ← #5 apply_kondisi_to_absensi tanpa ON CONFLICT
README_REVISI_R8.md         ← file ini
```

## Yang HARUS dijalankan di PostgreSQL

```bash
psql -h <host> -U <user> -d <db> -f migrations_r8.sql
```

Migrasi **idempotent**. Tidak menghapus data Anda selain *baris ganda*
yang menghalangi pembuatan UNIQUE constraint. Menambahkan:

| Tabel                       | Constraint / Kolom                          | Memperbaiki                       |
|-----------------------------|---------------------------------------------|-----------------------------------|
| `absensi`                   | UNIQUE (jadwal_id, user_id)                 | #5 admin/jadwal.php ON CONFLICT   |
| `sapa_log`                  | UNIQUE (sender_user_id, target_user_id)     | #1 login → index.php ON CONFLICT  |
| `kalori_target`             | PRIMARY KEY (user_id)                       | #4 submit target ON CONFLICT      |
| `kalori_defisit_setting`    | PRIMARY KEY (user_id)                       | #4 submit defisit ON CONFLICT     |
| `dm_messages`               | kolom `delivered_at`, `read_at` (jika hilang) | #2 polling DM tidak 500        |
| `absensi`, `dm_messages`    | index bantu                                 | performa                          |

Selain SQL di atas, **tidak ada migrasi tambahan** yang perlu Anda
jalankan untuk perbaikan R8 ini.

## Detail tiap perbaikan

### 1) `index.php` saat login — "Query gagal: ON CONFLICT"
`sapa_log` tidak punya UNIQUE constraint di DB lama, padahal kode lama
memakai `INSERT … ON CONFLICT DO NOTHING`. Sekarang pakai pola
**check-then-insert** (SELECT 1 dulu, baru INSERT) sehingga tidak
bergantung pada constraint. Migrasi R8 juga memasangkan UNIQUE supaya
kalau dijalankan paralel di tab lain tetap aman.

### 2) `dm.php` — pesan tidak terkirim
Saat send (`api_dm.php` POST) berhasil, tapi polling berikutnya
(`api_dm.php?peer=…`) **gagal 500** karena kolom `delivered_at`/`read_at`
tidak ada di DB lama → pesan baru tidak muncul di chat dan user
mengira "tidak terkirim". Sekarang query polling dibungkus try/catch
dan dimigrasi (R8) menambahkan kolom yg hilang.

### 3) Sisa Kalori Hari Ini bertambah dengan olahraga
Rumus baru: `sisa = target − konsumsi + terbakar`.
Olahraga / pembakaran kalori sekarang **menambah ruang makan**
(diet defisit-friendly). Sebelumnya (R6) olahraga tidak berpengaruh
pada angka sisa, sehingga tidak intuitif.

### 4) Submit target & defisit di `kalori_mingguan.php`
Form Target Harian dan Pengaturan Defisit Kalori sekarang pakai
**check-then-update/insert**, bukan `ON CONFLICT (user_id)`. Jadi tetap
jalan walau DB belum punya PRIMARY KEY (user_id) di tabel
`kalori_target` / `kalori_defisit_setting`.

Catatan: pesan **"This model is currently experiencing high demand"**
berasal dari layanan AI (Gemini) eksternal — bukan dari DB. Itu hanya
muncul kalau scan foto AI dipakai dan quota sedang penuh. Tidak
memblokir penyimpanan kalori (kode sudah memvalidasi dan
menampilkannya sebagai warning, makanan tetap tersimpan).

### 5) `admin/jadwal.php` — "Query gagal: ON CONFLICT"
Error sebenarnya berasal dari fungsi `apply_kondisi_to_absensi()` di
`includes/migrations_v7.php` yang melakukan `INSERT … ON CONFLICT
(jadwal_id,user_id) DO UPDATE` tanpa constraint di DB lama. Fungsi
tersebut dipanggil saat admin menyimpan jadwal/absensi. R8 mengubahnya
menjadi pola check-then-update/insert + skip baris `hadir`.

### 6) Jadwal Terdekat menunjukkan "sudah absen" padahal belum
Penyebab: `apply_kondisi_to_absensi()` membuat baris `absensi` otomatis
berstatus 'sakit' (keterangan `[AUTO-SAKIT] …`) ketika user mengubah
kondisi di profile menjadi sakit. Baris itu ikut terhitung di
Jadwal Terdekat sehingga user / member lain terlihat sudah absen.

Fix: `index.php` sekarang **mengabaikan baris dengan keterangan
`[AUTO-*]`** baik untuk badge hitungan H/I/S/A maupun untuk highlight
"Status saya" — sehingga tombol Hadir/Izin/Sakit tetap bisa ditekan
secara eksplisit oleh user.

## Cara apply

1. Backup database & folder lama.
2. Ekstrak ZIP, salin file ke posisi yang sama (timpa).
3. `psql -d sportapp -U <user> -f migrations_r8.sql`
4. Hard-reload browser (Ctrl+Shift+R).
5. Tes:
   - **Login** → tidak muncul lagi "Query gagal: … ON CONFLICT".
   - **Kirim DM** → pesan langsung muncul di chat.
   - **Sisa Kalori Hari Ini** → catat olahraga, angka sisa naik.
   - **Submit Target Kalori** → tidak ada error ON CONFLICT.
   - **admin/jadwal.php** → tambah/edit jadwal tidak ada error ON CONFLICT.
   - **Jadwal Terdekat di index.php** → user yang belum benar-benar
     mengisi absen tidak muncul di hitungan H/I/S/A.

## Catatan

- Tidak ada perubahan stack: tetap **PHP + PostgreSQL**, tidak ada
  migrasi ke React/TypeScript.
- Tidak menghapus data apapun selain baris ganda yang menghalangi
  pembuatan UNIQUE constraint.
- File `dm.php` & `admin/jadwal.php` disertakan **tidak berubah**
  (cukup untuk referensi versi terakhir).

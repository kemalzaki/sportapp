# Revisi Juli 2026 R3

File yang direvisi (letakkan menimpa file lama dengan struktur folder yg sama):

- `monitoring_tahajud.php`
  Kolom **Evaluasi** diperlebar (min-width 380px, textarea 2 baris) dan
  tabel bulan sekarang bisa scroll horizontal + vertikal
  (`overflow:auto`, `min-width:900px`).
- `tilawah_harian.php`
  Tabel *Riwayat Tilawah* dirapihkan: `min-width` per kolom + total
  `min-width:880px` dengan scroll horizontal, sehingga kolom Surah/Catatan
  tidak sempit.
- `silat_lidah.php`
  Tabel *Riwayat Silat Lidah* dirapihkan (`min-width:960px`,
  kolom Topik/Catatan diperlebar, scroll horizontal).
- `profile.php`
  Section **Pertemananku** — tabel dirapihkan (`min-width:760px`, kolom
  Nama/Kenal Sejak/Kedekatan/Catatan tidak lagi berhimpitan, scroll horizontal).
- `pantau_progress_member.php`
  Fix SQL error `COALESCE types smallint and boolean cannot be matched`.
  `COALESCE(aktif,true)=true` → `COALESCE(aktif::int,1)<>0` (aman baik
  jika kolom `aktif` bertipe `smallint`, `integer`, maupun `boolean`).
- `includes/header.php`
  Tambah menu **Pantau Progress Islami** di drawer navigasi
  Admin → Member Organize (link ke `/pantau_progress_member.php`).

## PostgreSQL

Tidak ada tabel baru yang perlu dibuat pada revisi ini. Tabel-tabel yang
dipakai (`shalat_evaluasi_harian`, `tilawah_harian`, `silat_lidah`,
`pertemanan`) sudah dibuat pada revisi sebelumnya
(`REVISI_JULI_2026.sql` / `REVISI_JULI_2026_R2.sql`) — jalankan dulu file
tersebut jika belum pernah dijalankan.

Kolom `users.aktif` boleh tetap `smallint` (0/1) atau `boolean` — query
sudah kompatibel.

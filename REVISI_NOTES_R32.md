# Revisi (Juli 2026 R32)

Zip ini HANYA berisi file yang direvisi. Extract ke root project (timpa file lama).

## 1) paket_upgrade.php
- Harga paket Komunitas & PRO ditukar pada array `$PLANS`.
- Komunitas kini lebih terjangkau (Mhs Rp 19.900/bln, Umum Rp 39.900/bln, dsb.),
  PRO menjadi tier premium (Mhs Rp 49.900/bln, Umum Rp 79.900/bln, dsb.).
- Tampilan kartu tidak berubah (sudah sesuai harga baru).

## 2) opini_viral.php
- Ditambahkan `paket_require_or_lock('pro', ...)` seperti `iptv.php`.
- Paket Gratis / Komunitas → melihat banner "Upgrade ke PRO".
- Ditambahkan mapping `'opini_viral.php' => ['pro']` di `includes/header.php`
  sehingga label PRO tampil di drawer.

## 3-5) cuaca.php
- Rekomendasi Jogging / Outdoor (Auto) dipindah ke atas — tepat di bawah
  form "Cari Kota / Daerah".
- "Prakiraan 7 Hari" & "Per Jam (24 jam ke depan)" dibungkus `<details>`
  (spoiler bisa dibuka/tutup). 7 Hari default terbuka, Per Jam default tertutup.
- Tabel dirapikan: `table-striped table-hover align-middle text-center`,
  header lebih rapi, kolom Tanggal/Kondisi/Jam align kiri.

## 6) includes/header.php (drawer)
- Item "Eksplorasi Rute & Peta Canggih" kini memakai `nav_lock_badge_for('run.php')`
  agar labelnya persis sama dengan item "Monitoring" (Komunitas).

## 7) monitoring.php, run.php, live_tracking.php, flyover.php
- Kuncian halaman diseragamkan mengikuti `tempat_list.php`, memakai
  `paket_require_or_lock('komunitas', $u, ...)`.
- Paket Gratis dikunci. Paket Komunitas & PRO dapat mengakses.

## 8) Hirarki paket: PRO > KOMUNITAS > GRATIS
### includes/paket_helpers.php
- `paket_is_pro($u)` → HANYA true untuk paket 'pro'.
- Tambahan helper `paket_is_komunitas_or_higher($u)`.
- `paket_require_or_lock('komunitas', ...)` → boleh diakses oleh 'komunitas' & 'pro'.
- `paket_require_or_lock('pro', ...)`       → HANYA 'pro'.

### includes/header.php (`nav_lock_badge_for`)
- GRATIS    → semua label muncul (Pro & Komunitas).
- KOMUNITAS → hanya label PRO yang muncul (fitur PRO belum bisa diakses).
- PRO       → tidak ada label apa pun.

## PostgreSQL
Tidak ada perubahan skema database untuk revisi ini. Kolom `paket` di tabel
`users` tetap dipakai apa adanya (nilai: 'gratis' | 'komunitas' | 'pro').
Jika tabel `paket_pesanan` belum ada, `paket_upgrade.php` akan membuatnya
otomatis (idempotent) saat pertama dibuka.

## File yang termasuk dalam zip
- paket_upgrade.php
- opini_viral.php
- cuaca.php
- monitoring.php
- run.php
- live_tracking.php
- flyover.php
- includes/header.php
- includes/paket_helpers.php
- REVISI_NOTES_R32.md

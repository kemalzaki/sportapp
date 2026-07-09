# Revisi R11 (Juli 2026) — Nav Drawer Badge: Pro Only

## Ringkasan
Menghapus badge **Komunitas** pada 9 menu berikut di drawer navigasi, sehingga hanya menampilkan badge **Pro** untuk user paket GRATIS:

1. Paket Bugar Kalistenik (`kalistenik.php`)
2. Perhitungan Kalori — semua (Badminton, Renang, Ping Pong, Futsal, Mingguan/Makanan)
3. Kalkulator — semua (`kalkulator.php`, `kalkulator_jantung.php`, `kalkulator_kesehatan.php`)
4. IPTV (`iptv.php`)
5. Toko Perlengkapan Olahraga Terdekat (`toko_olahraga.php`)
6. Penyakit Umum dan Obat Herbal (`kesehatan.php`)
7. Cedera Olahraga dan Penanganan (`cedera_olahraga.php`)
8. Lacak Puskesmas / RS Terdekat (`lacak_faskes.php`)
9. Survival Mode (`survival.php`)

## File yang diubah
- `includes/header.php` — fungsi `nav_feature_paket_map()`:
  - 11 entry lama diubah dari `['pro','komunitas']` menjadi `['pro']`.
  - 2 entry baru ditambahkan: `kalistenik.php` dan `kesehatan.php` = `['pro']`.

## PostgreSQL
**Tidak ada perubahan skema database.** Semua data pada `sportapp.sql`, `REVISI_JULI_2026_R7.sql`, `REVISI_JULI_2026_R8.sql`, dan `migration_r6.sql` tetap dipakai apa adanya. Tidak perlu SQL tambahan untuk revisi ini.

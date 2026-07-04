# Revisi R8 — Juli 2026 (Scoping per Komunitas)

File yang diubah (sebagian revisi, sisanya menyusul):

1. **riwayat.php**
   - Kalender Aktivitas Publik (`$publicDays`) & Tren Kehadiran Mingguan (`$wkRows`)
     kini difilter dengan `scope_user_ids_sql_array()`.
   - Riwayat Sesi & detail anggota sudah discope dari revisi sebelumnya.

2. **index.php**
   - Total Sesi, Total Hadir, Total Member, Member Aktif, Member Tidak Aktif,
     Jadwal Terdekat (single row), Status Online, Story Hari Ini, Social Feed
     (total + list), Forum Komunitas, Kabari Member (fallback) — semuanya
     kini difilter per komunitas via `scope_user_ids_sql_array()` /
     `scope_kom_ids_sql_array()`.
   - Catatan: **Total Visitor** tetap global karena `site_visitors` tidak
     memiliki relasi user/komunitas. Jika perlu per-komunitas, tambahkan
     kolom `komunitas_id` ke tabel `site_visitors` dan isi saat insert.

3. **admin/jadwal.php**
   - Panel “Jenis Jadwal”, dropdown Jenis Jadwal di form Tambah/Edit, serta
     handler POST `jj_create / jj_edit / jj_delete` **hanya untuk superadmin
     atau anggota komunitas SuperDuperAdmin** (`scope_is_super()`).
   - Data Jadwal di tabel sudah discope per komunitas (revisi sebelumnya).

4. **admin/absensi.php**
   - Dropdown Filter Bulan dan Pilih Jadwal difilter per komunitas.

5. **admin/pengeluaran.php**
   - Filter Jadwal (per bulan), Jadwal Spesifik, dan Filter Dana Dari
     difilter per komunitas admin.

6. **includes/menu_render.php**
   - Menu drawer berikut disembunyikan bagi non-super:
     Jenis Olahraga, Kode Referal Pendaftaran, Lacak HP Member,
     Pesanan Paket Member, Komunitas Organize, Pengaturan Lainnya.
   - Halaman-halaman itu sendiri sudah di-guard `require_role`.

## PostgreSQL

Tidak ada perubahan skema tambahan. Semua query memanfaatkan kolom yang
sudah ada (`komunitas_id`, `user_id`, `pic_admin_id`, dsb.) dan tabel
`user_komunitas` / `komunitas` yang sudah diseed di `sportapp.sql`.

Jika kolom `nav_menu.paket` belum ada, `menu_render.php` sudah auto-migrasi.

## Belum direvisi (di luar cakupan zip ini)
- Item #7 (admin/tim.php), #8 (admin/tempat.php), #9 (admin/event.php)
  sudah menerapkan scope dari revisi R7 sebelumnya — tidak ada perubahan
  pada zip ini karena sudah sesuai.

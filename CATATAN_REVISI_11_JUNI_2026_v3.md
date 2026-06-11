# Revisi 11 Juni 2026 — v3

Hanya 1 file yang diubah: `includes/header.php` (menu navigasi mobile / offcanvas drawer).

## Perubahan
1. Menu **IPTV** (`/admin/iptv.php`) ditambahkan di drawer mobile (hanya tampil untuk admin).
2. Drawer mobile dirapikan dengan grup collapsible (Bootstrap 5 `data-bs-toggle="collapse"`):
   - **Jogging Progress** → Monitoring, Upload, Riwayat, Tracking Jalur
   - **Perhitungan Kalori Olahraga** → Kalori Badminton, Renang, Ping Pong, Futsal, Mingguan (Makanan)
   - **Agenda Kita** → Kalender, Event, Booking
   - **Admin → Giat Olahraga** → Manajemen Jadwal, Input Absensi, Rekap Pengeluaran Kegiatan, Pengaturan Tim, CRUD Tempat, Jenis Olahraga
   - **Admin → Event Organize** → Input Absensi Event, Pengaturan Event
   - **Admin → Member Organize** → Member, Kode Referal, Statistik, Lacak HP Member
   - **Admin → Pengaturan Lainnya** → Laporan Postingan, Kebijakan Privasi (UU PDP)
3. Header "CMS & Pengaturan" di menu admin **dihapus** (sesuai permintaan).

## Catatan
- Tidak ada perubahan PHP backend / database. **Tidak perlu menjalankan migrasi SQL apa pun** untuk revisi ini.
- Hanya menggantikan `includes/header.php` di project lokal Anda.
- Navigasi desktop (navbar atas) tidak diubah agar tidak mempengaruhi tata letak desktop.

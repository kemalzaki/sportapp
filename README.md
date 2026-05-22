# HapFam SportApp v4 (PHP + PostgreSQL)

## Cara Pakai (local)
1. Import `sportapp.sql` (skema awal + data).
2. Jalankan `migration_v3.sql`.
3. Jalankan `migration_v4.sql` (revisi terbaru).
4. Buka via PHP built-in: `php -S localhost:8000` di folder ini.

## Revisi v4
1. **Sistem RSVP** di `admin/absensi.php` — hadir / izin / sakit / telat / absen + keterangan.
2. **Achievement Profile** di `profile.php` — total hadir, total sesi, jenis olahraga, streak, badge, olahraga favorit, total kalori, jarak, ranking komunitas.
3. **Calendar View** baru `calendar.php` — monthly, drag & drop (admin), highlight upcoming.
4. **Attendance Heatmap** GitHub-style di `profile.php`.
5. **Export Data (admin)** ke Excel/CSV & PDF via `export.php?type=...&format=csv|pdf` (link di dropdown Admin + tombol di absensi).
6. **Dark Mode** toggle di navbar (icon bulan/matahari), tersinkron ke DB.
7. **Search Global** di `search.php` (member, jadwal, aktivitas, tempat) + input search di navbar.
8. **Fitur CRUD berita dihapus** (admin/berita.php dihapus, slider berita di index dihapus).
9. **Bukti popup** di `riwayat.php` — klik thumbnail foto bukti → modal.
10. **WYSIWYG fix** di `admin/jadwal.php` (tambah & edit) — editor terpisah, tidak nabrak.
11. **Forum reply + like/dislike** di `index.php`.

## Catatan
- Tetap memakai PostgreSQL (sama seperti v3); semua migrasi additive, tidak menghapus data.
- Export PDF memakai HTML printable + `window.print()` (Save as PDF di browser).

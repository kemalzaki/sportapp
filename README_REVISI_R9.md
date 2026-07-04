# Revisi Juli 2026 R9 — Filter Data per Komunitas

Berkas yang direvisi (hanya sebagian file, bukan seluruh proyek):

1. `includes/scope.php`
   - Tambah helper `scope_primary_kom_id()` untuk memberi tag `komunitas_id`
     pada baris baru (mis. jadwal).

2. `admin/jadwal.php`
   - INSERT `jadwal` sekarang otomatis menyimpan `komunitas_id` dari komunitas
     admin yang login.
   - Query daftar jadwal ketat per komunitas (tidak lagi bocor via
     `komunitas_id IS NULL`).

3. `admin/absensi.php`
   - Dropdown bulan & jadwal ketat per komunitas.

4. `admin/pengeluaran.php`
   - Filter Bulan / Jadwal / Dana Dari ketat per komunitas.
   - Tabel rekap: pengeluaran hanya yang jadwal-nya milik komunitas admin.

5. `admin/tim.php`
   - Dropdown "Pilih Jadwal Kegiatan" ketat per komunitas.

6. `admin/tempat.php`
   - Daftar tempat ketat per komunitas (via PIC).

7. `admin/event.php`
   - Sudah scope by `created_by` / `event_peserta.user_id` (tidak diubah).

8. `index.php`
   - Total Sesi, Total Hadir, Jadwal Terdekat, dan Kabari Kawan ketat per komunitas.

9. `riwayat.php`
   - Riwayat Sesi ketat per komunitas.

10. `includes/menu_render.php` — tidak berubah pada rilis ini; item drawer
    (Jenis Olahraga, Kode Referal, Lacak HP, Pesanan Paket, Komunitas Organize,
    Pengaturan Lainnya) tetap disembunyikan untuk non-super via
    `nav_menu_super_only_urls()`.

---

## Catatan PostgreSQL (jalankan sekali di DB lokal)

**Tidak ada perubahan skema wajib** untuk rilis ini — semua tabel yang
dipakai sudah punya kolom `komunitas_id` atau relasi ke user komunitas.
Namun, karena filter kini **strict** (tidak lagi menampilkan baris ber-
`komunitas_id = NULL`), data lama yang belum di-tag akan otomatis
tersembunyi bagi non-super. Backfill dengan SQL berikut agar data lama
tetap muncul di komunitas yang benar (ganti `1` dengan id komunitas
tujuan):

```sql
-- Backfill jadwal lama ke komunitas tertentu:
UPDATE jadwal
   SET komunitas_id = 1
 WHERE komunitas_id IS NULL;

-- Pastikan user admin/member juga punya komunitas_id:
UPDATE users
   SET komunitas_id = 1
 WHERE komunitas_id IS NULL
   AND role IN ('member','admin');

-- Atau via pivot user_komunitas:
INSERT INTO user_komunitas(user_id, komunitas_id)
SELECT id, 1 FROM users
 WHERE id NOT IN (SELECT user_id FROM user_komunitas)
   AND role IN ('member','admin')
ON CONFLICT DO NOTHING;
```

Tidak ada data yang dihapus. Superadmin (`role='superadmin'`) dan anggota
komunitas `superduperadmin` tetap melihat SEMUA data.

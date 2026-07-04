# Revisi Juli 2026 — Ringkasan Perubahan

Zip ini berisi **hanya halaman yang direvisi** pada iterasi ini.
Salin/timpa file di dalam project sesuai path relatifnya (root project + `admin/`).

## Perubahan per file

1. **profile.php** — Blok "Ubah Password Pribadi" dipindah ke bagian **paling atas** di kolom kanan, tepat **di atas kartu Pertemananku**. Blok lama di bagian bawah halaman dihapus.

2. **index.php** — Bagian "Sapa Member Baru" kini **difilter per komunitas** menggunakan `scope_visible_user_ids()`. Pengunjung tanpa login tidak lagi melihat daftar (privasi antar-komunitas).

3. **riwayat.php** — Difilter per komunitas:
   - **Monitoring Upload Harian** (belum olahraga 1×/minggu): `u.id = ANY($__vids::int[])`.
   - **Riwayat Sesi** (daftar jadwal `$riwayat`) & **Detail Sesi (anggota)** (`$absRows`): difilter berdasarkan `jadwal.komunitas_id` dan `users.id` yang berada di scope komunitas.
   - **Riwayat Aktivitas Publik** (`$publicActs`): hanya menampilkan upload_harian dari user di scope komunitas.

4. **admin/absensi.php** — Sudah mengaplikasikan scope (list member per komunitas via `scope_user_ids_sql_array()`). Disertakan agar konsisten dengan paket revisi ini (tidak ada perubahan tambahan).

5. **admin/pengeluaran.php** — Sudah mengaplikasikan scope. Disertakan tanpa perubahan tambahan.

6. **admin/tim.php** — Ditambahkan `require includes/scope.php`. Dropdown **jadwal**, **user komunitas** (dropdown anggota internal), dan **pemain eksternal (tamu)** kini difilter per komunitas admin login (via `jadwal.komunitas_id` untuk jadwal, `users.id ∈ scope` untuk user, dan gabungan keduanya untuk tamu eksternal).

7. **admin/tempat.php** — Ditambahkan `require includes/scope.php`. List tempat difilter: `pic_user_id IS NULL OR pic_user_id ∈ scope`. Dropdown PIC juga dibatasi ke admin komunitas yang sama. Super-scope melihat semua.

8. **admin/event.php** — Ditambahkan `require includes/scope.php`. Daftar event difilter agar hanya event yang `created_by` ATAU salah satu `event_peserta.user_id` berada di scope komunitas. Dropdown peserta (`$allMembers`) juga dibatasi ke scope.

9. **admin/stats.php** — Sudah mengaplikasikan scope. Disertakan tanpa perubahan tambahan.

10. **pantau_progress_member.php** — Sudah mengaplikasikan scope. Disertakan tanpa perubahan tambahan.

## Catatan PostgreSQL

**Tidak ada migration schema baru yang wajib** untuk paket revisi ini —
semua filter memanfaatkan kolom yang sudah ada:

- `users.komunitas_id` dan pivot `user_komunitas(user_id, komunitas_id)`
- `jadwal.komunitas_id`
- `event.created_by`, `event_peserta(event_id, user_id)`
- `tempat.pic_user_id`

Prasyarat (sudah ada pada `sportapp.sql` versi terkini + `REVISI_JULI_2026_R7.sql`):
- Tabel `komunitas`, `user_komunitas`.
- Kolom `users.komunitas_id`, `jadwal.komunitas_id`.
- Fungsi helper di `includes/scope.php` (harus tersedia — bagian dari paket R7).

### Opsional (rekomendasi ke depan)

Untuk penegasan scope pada tabel `tempat`, `event`, dan `tim`, Anda **boleh**
menambahkan kolom `komunitas_id` (nullable) supaya filter lebih akurat
tanpa bergantung pada `pic_user_id` / `created_by`:

```sql
ALTER TABLE tempat ADD COLUMN IF NOT EXISTS komunitas_id INTEGER
    REFERENCES komunitas(id) ON DELETE SET NULL;
ALTER TABLE event  ADD COLUMN IF NOT EXISTS komunitas_id INTEGER
    REFERENCES komunitas(id) ON DELETE SET NULL;
ALTER TABLE tim    ADD COLUMN IF NOT EXISTS komunitas_id INTEGER
    REFERENCES komunitas(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_tempat_kom ON tempat(komunitas_id);
CREATE INDEX IF NOT EXISTS ix_event_kom  ON event(komunitas_id);
CREATE INDEX IF NOT EXISTS ix_tim_kom    ON tim(komunitas_id);
```

Migration di atas **opsional** — halaman-halaman dalam zip ini sudah bekerja
tanpa perlu perubahan struktur DB. Jalankan hanya bila Anda ingin
mengasosiasikan `tempat` / `event` / `tim` langsung ke komunitas tertentu
(mis. untuk keperluan laporan lintas admin).

## Cara pakai

1. Backup file lama Anda.
2. Ekstrak zip ke root project (akan menimpa file di root & `admin/`).
3. Refresh browser. Tidak perlu clear cache DB.

Selamat mencoba!

# Revisi R7 (Juli 2026) — Ringkasan Perubahan

Paket revisi ini menyentuh manajemen role (superadmin), akses lintas
komunitas (anti IDOR), edit password mandiri, dan beberapa perbaikan UI
kecil. **Isi ZIP ini hanya berisi file yang direvisi**, sisanya tetap
mengikuti versi sebelumnya.

## Ringkas per-poin permintaan

1. **Role `superadmin` di `admin/members.php`** — Ditambahkan pada semua
   selectbox role, validasi POST (`update_role` & `create`) juga menerima
   role baru ini.

2. **Opsi `superadmin` hanya tampil untuk role superadmin** — Selectbox
   role sekarang di-render dari `$__roleOpts` yang di-branch berdasarkan
   `scope_is_super()`. Untuk admin biasa, opsi `superadmin` tidak muncul
   dan validasi backend menolaknya.

3. *(Tidak ada #3 pada daftar permintaan.)*

4. **Ubah password pribadi di `profile.php`** — Section baru "Ubah
   Password Pribadi" dengan verifikasi password lama, konfirmasi password
   baru, minimum 6 karakter, dan pesan sukses/gagal.

5. **Cegah Broken Access Control / IDOR antar-komunitas** — Ditambahkan
   helper `includes/scope.php` yang menyediakan
   `scope_visible_user_ids()`, `scope_visible_komunitas_ids()`,
   `scope_is_super()`, `scope_require_user()`, `scope_require_kom()`.

   Halaman yang telah dipagari dengan helper ini:
   - `index.php` — Jadwal Terdekat difilter komunitas.
   - `calendar.php` — Kalender & upcoming jadwal difilter komunitas +
     guard IDOR pada drag & drop jadwal (`_action=move`).
   - `event.php` — Daftar event difilter berdasarkan `created_by` yang
     berada dalam scope + guard IDOR pada detail (`?id=`).
   - `tempat.php` — Daftar booking difilter berdasarkan `user_id`
     pembooking dalam scope.
   - `riwayat.php` — Semua leaderboard (konsisten, jarak, pace, kalori,
     penggaet_eksternal) difilter komunitas melalui fragment SQL yang
     disisipkan ke `$periodSql/$uPeriodSql`.
   - `search.php` — Hasil member/jadwal/aktivitas difilter komunitas.
   - `pantau_progress_member.php` — Daftar member difilter komunitas.
   - `admin/jadwal.php` — Listing jadwal difilter komunitas.
   - `admin/absensi.php` — Guard IDOR untuk jadwal komunitas lain + daftar
     member difilter.
   - `admin/pengeluaran.php` — Filter berdasarkan jadwal & pencatat
     komunitas.
   - `admin/stats.php` — Semua chart/leaderboard difilter komunitas.
   - `admin/tempat_survei.php` — Usulan difilter berdasarkan pengusul
     dalam scope.
   - `admin/members.php` — Lihat #6.

   > Catatan: `superadmin` (role) dan anggota komunitas ber-slug
   > `superduperadmin` selalu bypass filter (dapat melihat semua data).

6. **Admin biasa hanya melihat member komunitasnya sendiri di
   `admin/members.php`** — Query utama, dropdown filter komunitas, dan
   kartu statistik semuanya dibatasi ke `scope_visible_komunitas_ids()`.
   Super-scope (role `superadmin` atau anggota `SuperDuperAdmin`) tetap
   melihat semua komunitas / semua member.

7. **Label "Komunitas" dihapus dari Jadwal Terdekat (`index.php`)** —
   Kolom `<th>Komunitas</th>` dan seluruh `<td data-label="Komunitas">`
   pada tabel Jadwal Terdekat dihapus.

8. **Kolom "Komunitas Pengusul" pada usulan tempat baru** — Tambah kolom
   baru di `tempat_list.php` (section survei) dan di
   `admin/tempat_survei.php`. Nilainya di-agregasi dari
   `user_komunitas` pivot.

## Perubahan Database (PostgreSQL)

File: **`REVISI_JULI_2026_R7.sql`**

Jalankan sekali (idempotent). Isinya:
1. `ALTER TYPE ... ADD VALUE 'superadmin'` pada enum `users.role` (via
   DO block agar aman bila sudah ada).
2. `ALTER TABLE tempat_survei ADD COLUMN IF NOT EXISTS komunitas_id INTEGER`
   (kolom cadangan; saat ini fitur R7 memakai relasi `user_komunitas`).
3. `CREATE TABLE IF NOT EXISTS user_komunitas` (harus sudah ada dari
   revisi R2, tetap dijamin di sini).

### Data komunitas yang dibutuhkan
Komunitas `SuperDuperAdmin` (id=5, slug `superduperadmin`) dan `Publik People`
(id=6) sudah ada di dump `sportapp.sql`. Tidak ada seed baru yang harus
ditambahkan; cukup pindahkan admin/koordinator "SuperDuperAdmin" ke
komunitas id=5 lewat `admin/members.php` bila belum.

## Cara Uji Cepat (Local)

1. Import `sportapp.sql` seperti biasa.
2. Jalankan `psql -f REVISI_JULI_2026_R7.sql` (atau paste ke pgAdmin).
3. Login sebagai:
   - `admin` biasa → hanya melihat member/jadwal/absensi komunitasnya.
   - `superadmin` (atau anggota komunitas SuperDuperAdmin) → melihat
     seluruh komunitas.
4. Buka `profile.php` → section "Ubah Password Pribadi" tampil di paling
   bawah halaman.

## Daftar File dalam Zip

- `includes/scope.php` **(baru)**
- `admin/members.php`
- `admin/tempat_survei.php`
- `admin/jadwal.php`
- `admin/absensi.php`
- `admin/pengeluaran.php`
- `admin/stats.php`
- `admin/event.php`, `admin/event_absensi.php`, `admin/tim.php`,
  `admin/tempat.php` *(hanya ekspansi require_role & include superadmin)*
- `profile.php`
- `index.php`
- `calendar.php`
- `event.php`
- `tempat.php`
- `riwayat.php`
- `tempat_list.php`
- `search.php`
- `pantau_progress_member.php`
- `REVISI_JULI_2026_R7.sql`

## Batasan / Catatan Sisa

Karena halaman `riwayat.php`, `admin/tim.php`, `admin/event.php`, dan
`admin/tempat.php` memiliki puluhan query internal, cakupan scope pada
file-file tersebut sengaja **difokuskan pada listing utama** (leaderboard,
daftar member, listing utama). Bila di kemudian hari ditemukan query lain
yang perlu di-scope, cukup sisipkan pola berikut:

```php
require_once __DIR__ . '/includes/scope.php';
$vids = scope_user_ids_sql_array();
// ... WHERE u.id = ANY($1::int[]) ...
db_all($sql, [$vids]);
```

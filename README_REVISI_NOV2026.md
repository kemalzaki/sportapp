# Revisi Nov 2026 — Batasi Islami, Hapus Lacak HP, Edit Nama/Username

## File yang direvisi
1. `includes/scope.php` — tambah helper `scope_can_access_islami()`.
2. `includes/header.php` — sembunyikan menu Islami (chip mobile, drawer, navbar desktop) untuk member di luar komunitas yang diizinkan; hapus menu "Lacak HP Member" dari drawer SuperAdmin.
3. `islami.php` — guard akses: user yang tidak berhak diarahkan ke `/index.php` dengan flash.
4. `profile.php` — hapus ikon pensil di samping Nama & Username; tambah kartu "Edit Nama & Username" di bawah "Ubah Password Pribadi".

## Ketentuan akses Islami
Fitur Islami hanya tampil untuk:
- Member yang tergabung di komunitas dengan slug: `kawankeringat-kantor`, `ladies-grup`, atau `superduperadmin`.
- Role `superadmin` (tetap boleh, untuk keperluan operasional).

## PostgreSQL — TIDAK ada perubahan schema
- **Tidak perlu menjalankan SQL baru.** Helper baru hanya membaca kolom yang sudah ada di tabel `komunitas` (`id`, `slug`) dan pivot `user_komunitas` (atau fallback `users.komunitas_id`).
- Pastikan tabel `komunitas` sudah berisi baris slug berikut (sudah ada di `sportapp.sql` yang lama):
  - `kawankeringat-kantor` (id 1)
  - `ladies-grup` (id 4)
  - `superduperadmin` (id 5)
- Jika di database lokal Anda slug berbeda (misal `ladiesgrup` tanpa strip), sesuaikan salah satu:
  a. Ubah data: `UPDATE komunitas SET slug='ladies-grup' WHERE id=4;`
  b. Atau ubah daftar slug di `includes/scope.php` fungsi `scope_can_access_islami()`.

## Cara terapkan
Timpa 4 file berikut ke folder proyek lokal Anda (backup dulu bila perlu):
- `includes/scope.php`
- `includes/header.php`
- `islami.php`
- `profile.php`

Tidak ada perubahan pada data, tidak ada file yang dihapus.

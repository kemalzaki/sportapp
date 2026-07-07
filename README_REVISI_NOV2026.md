# Revisi Nov 2026 — Ringkasan Perubahan

File yang direvisi (tinggal timpa di project lokal Anda):

1. `includes/bottom_nav.php`
   - Tombol **Upload** di bottom nav dibuat FLAT/sejajar dengan menu Beranda-Aktivitas-Kalori-Saya (tidak lagi "menjurus ke atas").
   - Warna semua ikon bottom nav (termasuk tombol Upload) mengikuti tema aktif (`--bs-primary`).
   - CSS tambahan meng-override warna ikon di drawer/side menu supaya seragam tema (tidak warna-warni).

2. `upload.php`
   - Ditambahkan **pagination 5 baris per halaman** untuk tabel "Aktivitas Saya".

3. `run.php`
   - Widget banner **Video Flyover 3D** dihapus.

4. `monitoring_tahajud.php`
   - Ditambahkan kolom **Tgl Hijriyah** di tabel monitoring Tahajud & Duha.

5. `islami.php`
   - Kartu **"Tanya Jawab Islami"** dan **"Countdown Hari Raya & Peristiwa"** (mobile & desktop) dibungkus jadi **spoiler** (`<details>/<summary>`) — bisa klik buka/tutup.

6. `profile.php`
   - Tampilan kartu profil dirapikan (avatar dengan ring halus, badges rata dengan gap konsisten, section dipisah garis putus-putus, tombol edit lebih menarik berbentuk lingkaran).
   - Fungsi **CRUD edit Nama & Username** (klik ikon pensil) sudah tersedia — sekarang menggunakan **SweetAlert** (jatuh kembali ke `prompt()` bila SweetAlert tidak ada). Endpoint AJAX `_action=update_nama` dan `_action=update_username` sudah ada pada file yang sama.

## PostgreSQL

**Tidak ada perubahan schema / SQL baru** yang diperlukan untuk paket revisi ini.
Semua perubahan murni tampilan/UI dan JavaScript client-side. Data lama tetap
aman dan langsung terpakai.

Catatan: kolom-kolom yang sudah dipakai (`users.username`, `users.nama`,
`shalat_sunnah_log`, `shalat_evaluasi_harian`) sudah ada dari revisi
sebelumnya — tidak perlu di-migrate ulang.

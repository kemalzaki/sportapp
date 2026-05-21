# HapFam SportApp — Aplikasi Web Olahraga (PHP + Bootstrap 5 + PostgreSQL)

Implementasi penuh dari rancangan **Transformasi Sistem Absensi & Monitoring Performa Olahraga** menggunakan PHP 8, Bootstrap 5, dan PostgreSQL. **Versi 1**.

> Catatan teknis: koneksi database **tidak lagi memakai PDO**. Aplikasi memakai
> ekstensi native `pgsql` (`pg_connect` / `pg_query_params`) sehingga bisa
> berjalan di hosting gratis seperti **Render**, **InfinityFree (PHP+PostgreSQL)**,
> atau VPS shared hosting yang hanya menyediakan `php-pgsql` tanpa `pdo_pgsql`.

## Fitur

| Modul | Hak Akses | Status |
|---|---|---|
| Dashboard publik (statistik + member eksternal) | Publik | ✅ |
| Riwayat olahraga + filter (Bulan, Jenis, Koordinator) + daftar tamu eksternal | Publik | ✅ |
| Login (dengan **captcha math**) / Register | Publik | ✅ |
| Upload Aktivitas Harian (khusus **Jogging**, popup bukti, edit & delete) | Member/Admin | ✅ |
| Monitoring Performa (leaderboard + tren mingguan, Chart.js) | Member/Admin | ✅ |
| Manajemen Jadwal (CRUD + **Edit**) | Admin | ✅ |
| Input Absensi (1/0) + Tamu Eksternal | Admin | ✅ |
| Manajemen Member (CRUD + **Reset password**) | Admin | ✅ |
| **CRUD Jenis Olahraga** | Admin | ✅ |
| Integrasi Google Drive API | Admin/Member | ⚙️ (hook di `upload.php`) |

## Struktur

```
sportapp/
├── config/db.php              # koneksi pg_* (TANPA PDO)
├── includes/                  # header, footer, auth, csrf, captcha
├── admin/                     # jadwal, absensi, members, jenis
├── assets/css/app.css
├── sportapp.sql               # DDL + seed PostgreSQL
├── uploads/                   # file upload (per bulan)
├── index.php                  # dashboard
├── riwayat.php
├── upload.php
├── monitoring.php
├── login.php / register.php / logout.php
└── .htaccess
```

## Instalasi Lokal

### 1. Persyaratan
- PHP 8.1+ dengan ekstensi `pgsql` (bukan `pdo_pgsql`)
- PostgreSQL 13+
- Web server (Apache/Nginx) atau built-in: `php -S localhost:8000`

### 2. Setup database
```bash
createdb sportapp
psql -d sportapp -f sportapp.sql
```

### 3. Konfigurasi koneksi
Gunakan **`DATABASE_URL`** (gaya Render/Heroku) atau variabel terpisah:

```bash
# salah satu:
export DATABASE_URL="postgres://user:pass@host:5432/sportapp"

# atau:
export DB_HOST=localhost DB_PORT=5432 DB_NAME=sportapp \
       DB_USER=postgres DB_PASS=postgres DB_SSLMODE=disable
```

### 4. Jalankan
```bash
php -S localhost:8000 -t .
```
Buka <http://localhost:8000>.

## Deploy ke Render.com (PHP + PostgreSQL)

1. Buat **PostgreSQL** di Render, salin **Internal Database URL**.
2. Buat **Web Service → Use existing repo** atau upload zip ini, runtime **PHP**.
3. Build command kosong, start command:
   ```
   php -S 0.0.0.0:$PORT -t .
   ```
4. Tambahkan env var **`DATABASE_URL`** = isi internal URL PostgreSQL Render.
5. (Sekali saja) import `sportapp.sql` ke database Render via psql atau Render Shell:
   ```bash
   psql "$DATABASE_URL" -f sportapp.sql
   ```

## Login Awal

Setelah import seed:

| Role | Email |
|------|-------|
| Admin | `admin@sport.local`, `firdam@sport.local` |
| Member | `rifat@sport.local`, `dani@sport.local`, dll. |

Password awal akun seed = `admin123`.
**Admin dapat me-reset password member kapan saja** melalui halaman **Admin → Member → tombol kunci 🔑**.

## Google Cloud / Google Drive (opsional)

Hook tersedia di `upload.php` (lihat komentar `--- Google Drive integration ---`).
Langkah singkat:

1. Buka <https://console.cloud.google.com>, buat project baru *HapFam SportApp*.
2. **APIs & Services → Library**: aktifkan **Google Drive API**.
3. **IAM & Admin → Service Accounts → Create**, beri nama `sportapp-uploader`.
4. Pada service account → **Keys → Add Key → JSON** → simpan sebagai `config/gdrive-credentials.json`.
5. Di Google Drive, buat folder `Aktivitas_Olahraga`, klik **Share**, masukkan
   email service account (format `xxx@xxx.iam.gserviceaccount.com`) sebagai **Editor**.
6. Install client library: `composer require google/apiclient:^2.15`.
7. Aktifkan blok kode pada `upload.php` (sudah disiapkan sebagai TODO).
8. URL pratinjau hasil upload akan disimpan ke kolom `upload_harian.gdrive_url`.

## Keamanan

- Password di-hash dengan `password_hash()` (bcrypt).
- **Captcha** matematika di halaman login.
- CSRF token di setiap form POST.
- Query parametrized (`pg_query_params`) — bebas SQL injection.
- Role-based access via `require_role()`.
- Admin dapat **reset password** member.

## Changelog Versi 1

1. Riwayat: kolom *Hadir Internal* menampilkan **`x dari y`**.
2. Riwayat: kolom *Tamu Eksternal* menampilkan **nama** + dibawa oleh siapa.
3. Dashboard: tambah kartu *Member Eksternal*.
4. Halaman Member memiliki kolom **Nama** yang jelas.
5. README mencantumkan langkah Google Cloud lengkap.
6. Upload Harian: keterangan **“Minimal 1 minggu 1x”**.
7. Klik **Bukti** → tampil sebagai **popup modal**, bukan tab baru.
8. Aktivitas Saya: **Edit & Delete** per baris.
9. Footer **© 2026 HapFam SportApp · Versi 1**.
10. Demo credential di login.php **dihapus**.
11. Tombol **Login SSO dihapus** dari halaman login.
12. **Admin dapat mengubah password member** dari halaman Manajemen Member.
13. **Captcha** matematika dipasang di login.
14. Monitoring: keterangan **Jogging** ditambahkan.
15. Upload harian dikunci ke **Jogging** saja.
16. Branding **HapFam SportApp** di seluruh aplikasi.
17. Aktivitas Saya diberi **kolom Nomor**.
18. Menu **CRUD Jenis Olahraga** (`/admin/jenis.php`).
19. Halaman **Jadwal** mendapatkan fitur **Edit** (modal).
20. Koneksi DB pakai **pg_\*** (non-PDO) → siap deploy di Render dll.
21. Tampilan lebih elegan, responsif, terasa nyaman di mobile (mobile-first).

## Lisensi
MIT

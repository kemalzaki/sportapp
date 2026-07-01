# REVISI JULI 2026 — Sportapp Core

Arsip ini berisi HANYA file-file yang direvisi / ditambahkan.
Timpakan ke folder proyek `sportapp_core/` Anda (backup dulu untuk berjaga-jaga).

## Isi

| # | Item | File |
|---|------|------|
| 1 | Kolom **Evaluasi** + tabel bulan **scrollable** di Monitoring Tahajud & Duha | `monitoring_tahajud.php` |
| 2 | **Monitoring Paket Bugar Kalistenik** (log latihan per level, rekap 30 hari) | `kalistenik.php` |
| 3 | **Monitoring Tilawah Harian** (diri / keluarga) — file baru | `tilawah_harian.php` |
| 4 | **Monitoring Silat Lidah** (teman + topik) — file baru | `silat_lidah.php` |
| 5 | **Pertemananku** (CRUD, di atas Akun Strava) | `profile.php` |
| 6 | Kolom **No** + **Paham?** (checklist) di Sejarah Nabi tab "Tabel Kaum & Azab" | `sejarah_nabi.php` |
| 7 | **Spoiler individual** per section Panduan Shalat Jama' | `panduan_shalat_jama.php` |
| 8 | **Pantau Progress Islami Member** (admin/koordinator) — file baru | `pantau_progress_member.php` |
| ➕ | Menu grid Hub Islami ditambah 3 kartu (Tilawah, Silat Lidah, Pantau Progress) | `islami.php` |

## Migrasi PostgreSQL

Jalankan `REVISI_JULI_2026.sql` di database `sportapp` Anda (semua `CREATE` memakai
`IF NOT EXISTS`, aman diulang, tidak menghapus data).

Tabel baru yang ditambahkan:
- `shalat_evaluasi_harian`
- `kalistenik_log`
- `tilawah_harian`
- `silat_lidah`
- `pertemanan`

Fitur (#6), (#7), (#8) tidak memerlukan tabel baru — (#8) hanya membaca tabel
yang sudah ada: `shalat_sunnah_log`, `doa_user`, `catatan_hafalan`,
`catatan_baca_buku`. Jika salah satu tabel belum ada, kolomnya akan tampil `0`
tanpa error (query dibungkus `try/catch`).

## Catatan

- Semua tetap **PHP + PostgreSQL** murni (tidak ada React).
- Checklist "Paham?" di Sejarah Nabi disimpan di `localStorage` browser
  (per-perangkat, tidak sync antar-device).
- Akses `pantau_progress_member.php` dibatasi role `admin`, `koordinator`,
  atau `pic`. Sesuaikan daftar role jika berbeda di sistem Anda.
- File `islami.php` hanya ditambahi 3 kartu menu baru dan tombol admin
  "Pantau Progress Islami Member" — konten lain tidak diubah.

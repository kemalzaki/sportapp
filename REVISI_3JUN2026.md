# REVISI 3 Juni 2026 — Catatan Perubahan

Zip ini berisi **hanya file yang berubah/baru** dari paket `sportapp_core.zip`.
Salin semua isi zip ini menimpa folder project lama. Data SQL **tidak diubah**.

## Ringkasan revisi yang sudah diterapkan

| # | Item | Status | File terkait |
|---|------|--------|--------------|
| 1 | Hapus menu Jajanan / Buku / Video IPTV / Sentuhan Islami + kata-kata di `index.php` + hapus file-nya | ✅ | `index.php` (diedit); file dihapus: `jajanan.php`, `buku.php`, `iptv_proxy.php`, `quran_kata.php`, `includes/islami_widget.php` |
| 2 | Hilangkan menu Jajan / Kurir Jajan dari navigasi (chip + drawer + navbar) | ✅ | `includes/header.php` |
| 3 | Tombol "Tambahkan Pintasan ke HP kamu" di halaman login (PWA install prompt sama dgn `index.php`) | ✅ | `login.php` |
| 4a | Notifikasi WA untuk pemberitahuan event & absensi olahraga | ✅ | `includes/wa_notify.php` (baru) + hook di `admin/absensi.php` |
| 4b | Notifikasi WA antar admin (PIC) untuk mengabari member-nya | ✅ | dipanggil dari `admin/absensi.php` lewat `wa_notify_pic_admins()` |
| 4c | Reminder WA mengisi pengalaman hiking/camping + perlengkapan di `profile.php` | ✅ | `profile.php` (banner reminder + tombol "Ingatkan via WA") |
| 5 | Tema Warna Aplikasi dipindahkan ke **bagian bawah** `profile.php` | ✅ | `profile.php` |
| 6 | Skeleton loading semua halaman | ✅ | Sudah ada `includes/skeleton.php` (per-page via `$pageSkeleton`) — di-include otomatis dari `includes/header.php`. Tidak ada perubahan struktur, hanya verifikasi. |
| 7 | Connect ke Strava — setiap posting di Strava masuk ke aplikasi | ✅ scaffold | `strava_connect.php` (OAuth flow) + `strava_webhook.php` (penerima event) + tombol di `profile.php` |
| 8 | Anti cold-start di Render | ✅ | `api_ping.php` (baru) + auto-ping setiap 4 menit dari `includes/footer.php`. Pasang juga **Uptime Robot / Cron-Job.org** ke URL ini setiap 5 menit. |

## Yang perlu kamu lakukan setelah extract

### A. Tambahan PostgreSQL (opsional — hanya kalau mau pakai Strava)

Jalankan SQL berikut **sekali** di database (tidak menghapus data yang sudah ada):

```sql
-- Untuk integrasi Strava (poin 7)
CREATE TABLE IF NOT EXISTS user_strava (
  user_id        INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  athlete_id     BIGINT,
  access_token   TEXT NOT NULL,
  refresh_token  TEXT NOT NULL,
  expires_at     TIMESTAMP NOT NULL,
  connected_at   TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS strava_activities (
  id           BIGINT PRIMARY KEY,
  user_id      INTEGER REFERENCES users(id) ON DELETE CASCADE,
  name         TEXT,
  type         VARCHAR(40),
  distance     NUMERIC(10,2),
  moving_time  INTEGER,
  start_date   TIMESTAMP,
  raw          JSONB,
  imported_at  TIMESTAMP NOT NULL DEFAULT now()
);

-- Untuk fitur PIC (poin 4b) — kalau kolom ini belum ada di tabel users
ALTER TABLE users ADD COLUMN IF NOT EXISTS pic_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
```

> Tabel `notifications`, `fcm_tokens`, `users.nomor_wa`, `absensi`, `jadwal`
> **sudah ada** dari migrasi sebelumnya — tidak perlu dibuat ulang.

### B. Environment variable

Hanya kalau kamu mau aktifkan Strava / WA Cloud API (opsional):

```
# Strava (poin 7)
STRAVA_CLIENT_ID=xxxxxx
STRAVA_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxx
STRAVA_REDIRECT_URI=https://domain-kamu.com/strava_connect.php
STRAVA_VERIFY_TOKEN=tulis-string-acak-bebas

# WhatsApp Cloud API (opsional — kalau kosong, WA pakai mode wa.me click-to-chat
# yang gratis & tidak perlu setup apa-apa)
WA_CLOUD_TOKEN=
WA_PHONE_ID=
```

### C. Uptime ping (poin 8)

Daftar gratis di salah satu:
- https://uptimerobot.com  (5 menit interval)
- https://cron-job.org      (1 menit interval)

Set monitor tipe HTTP(s) ke:
```
https://<domain-kamu-di-render>/api_ping.php
```
Interval **5 menit**. Selesai — service tidak akan tidur lagi.

---

## Halaman yang TIDAK diubah

Semua halaman lain (event, jadwal, run, dm, dst.) tidak disentuh — silakan
pakai dari `sportapp_core.zip` aslinya.

# Revisi 26 Juni 2026 — Gating Paket PRO/KOMUNITAS + Fix Ekstraksi YouTube

Paket file ini berisi **hanya halaman yang direvisi**, untuk ditimpa ke
folder `sportapp_core/` yang sudah ada.

## Daftar file

| File | Perubahan |
| --- | --- |
| `flyover.php` | Dikunci untuk paket **Gratis** (hanya PRO & KOMUNITAS). |
| `kalkulator.php` | Dikunci untuk paket **Gratis**. |
| `kalkulator_jantung.php` | Dikunci untuk paket **Gratis**. |
| `kalkulator_kesehatan.php` | Dikunci untuk paket **Gratis**. |
| `gaya_hidup.php` | Dikunci untuk paket **Gratis**. |
| `run.php` | Dikunci untuk paket **Gratis**. |
| `monitoring.php` | Dikunci untuk paket **Gratis**. |
| `api_yt_mp3.php` | Perbaikan deteksi `yt-dlp` & `ffmpeg` lintas platform + env override + pesan error jelas. |

Pola gating mengikuti `kalori_mingguan.php` (Revisi 26 Juni 2026 #7):
banner kunci + tombol pesan via WhatsApp ke `0813-8636-9207`. Halaman tetap
memanggil `require_login()` lebih dulu, lalu memeriksa `paket_user($u)` via
`includes/paket_helpers.php` (sudah ada di project, tidak ada perubahan).

## PostgreSQL — TIDAK ada migrasi baru

Tidak ada tabel/kolom baru yang perlu ditambahkan. Kolom `users.paket`
(`gratis|pro|komunitas`) sudah ada sejak Revisi R14. Pastikan saja nilai
`paket` pada akun uji sudah benar:

```sql
-- contoh: jadikan user id=1 sebagai PRO
UPDATE users SET paket = 'pro' WHERE id = 1;
-- atau KOMUNITAS / GRATIS
UPDATE users SET paket = 'komunitas' WHERE id = 2;
UPDATE users SET paket = 'gratis'    WHERE id = 3;
```

Jika kolom `paket` belum ada di database lokal Anda (mis. instalasi lama),
jalankan ini sekali saja:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) NOT NULL DEFAULT 'gratis';
```

`role = 'admin'` otomatis dianggap `komunitas` (akses penuh) — sudah
ditangani di `includes/paket_helpers.php`, tidak perlu perubahan SQL.

## Fix #5 — Ekstraksi Musik via YouTube error `yt-dlp / ffmpeg belum dipasang`

`api_yt_mp3.php` sekarang:

1. Mendeteksi binary lintas platform (Linux/macOS/Windows) dengan benar.
   Sebelumnya `command -v ... 2>NUL` di Linux malah membuat file `NUL`
   dan deteksi salah.
2. Mendukung **path absolut via env**: set `YT_DLP_BIN` dan/atau
   `FFMPEG_BIN` di environment PHP-FPM/Apache/CLI jika binary tidak ada
   di PATH global.
3. Memeriksa lokasi umum: `/usr/local/bin`, `/usr/bin`, `/opt/homebrew/bin`,
   `/snap/bin`, `~/.local/bin` (POSIX) dan `C:\Program Files\yt-dlp\`,
   `C:\ProgramData\chocolatey\bin\` (Windows).
4. Memberi pesan error + instruksi instalasi per OS pada response JSON.

### Cara install (lokal)

**Linux (Debian/Ubuntu):**

```bash
sudo apt update
sudo apt install -y ffmpeg python3-pip
pip install -U yt-dlp
# verifikasi
yt-dlp --version && ffmpeg -version
```

**macOS (Homebrew):**

```bash
brew install yt-dlp ffmpeg
```

**Windows (PowerShell, admin):**

```powershell
winget install yt-dlp
winget install Gyan.FFmpeg
# atau via Chocolatey:
# choco install yt-dlp ffmpeg
```

Setelah instalasi, **restart server PHP** (PHP-FPM / Apache / `php -S`)
agar PATH terbaca ulang. Untuk verifikasi cepat:

```bash
curl 'http://localhost/api_yt_mp3.php?v=dQw4w9WgXcQ' --cookie 'PHPSESSID=...'
```

Jika masih `belum terpasang`, set env override lalu restart:

```bash
# contoh untuk php -S:
YT_DLP_BIN=/usr/local/bin/yt-dlp FFMPEG_BIN=/usr/bin/ffmpeg php -S 0.0.0.0:8000
```

## Cara pasang revisi

1. Backup folder lama Anda.
2. Ekstrak zip ini, **timpa** file dengan nama yang sama di folder project.
3. Tidak perlu menjalankan SQL kecuali kolom `paket` belum ada (lihat di atas).
4. Tes login dengan akun bertipe `gratis`, `pro`, dan `komunitas` —
   akun `gratis` harus melihat banner kunci pada 7 halaman di atas.

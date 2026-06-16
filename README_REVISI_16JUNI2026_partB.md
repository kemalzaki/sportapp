# Revisi Tambahan 16 Juni 2026 — Bagian B

Arsip ini HANYA berisi file yang direvisi pada putaran ini. Salin/replace ke
project lama Anda (struktur folder sama).

## File yang diubah

| File | Masalah | Perbaikan |
|------|---------|-----------|
| `admin/sistem.php` | Query gagal: `column reference "relname" is ambiguous` | Kolom `relname` ada di `pg_class` & `pg_stat_user_tables`. Diqualifikasi jadi `c.relname`, dan `n_live_tup` jadi `s.n_live_tup` + `COALESCE(...,0)`. |
| `includes/ai_gemini.php` | Semua fitur AI gagal: *"Request had invalid authentication credentials. Expected OAuth 2 access token, login cookie or other valid authentication credential."* (mempengaruhi monitoring.php, islami.php, live_tracking.php / AI Safety, kalori_mingguan.php, run.php AI prompt) | Key default lama `AQ.Ab8…` adalah OAuth token, **bukan** Google API key, sehingga ditolak. Helper sekarang otomatis: <br>• kirim via `?key=` jika key diawali `AIza…` (API key biasa)<br>• kirim via `Authorization: Bearer …` jika token OAuth<br>Default key kosong → user wajib set `GEMINI_API_KEY`. Pesan error juga lebih ramah & memberi instruksi. |
| `run.php` | `Error: csrf is not defined` saat menekan tombol AI Route dari prompt Gemini | Di IIFE kedua (script line 907+) variabel CSRF dideklarasikan `var CSRF`, tetapi handler AI memakai lowercase `csrf` (yang hanya ada di IIFE pertama). Diganti ke `CSRF`. Juga handler AI Route from Image (potensi bug yang sama). |
| `run.php` | (Permintaan #5) Tampilan video animasi dibuat lebih menarik | Ditambah banner CTA mencolok di paling atas yang mempromosikan halaman `flyover.php` dengan fitur-fitur baru (HUD, musik, ikon). |
| `flyover.php` | (Permintaan #6) Video flyover lebih menarik | Ditambah:<br>• **HUD popup** kiri-atas: live distance, waktu, kecepatan, % progres (muncul saat playback dgn animasi fade-slide).<br>• **Badge REC** kanan-atas (animasi pulse) saat merekam.<br>• **Popup notifikasi** bawah saat tiap KM tercapai & saat Start/Finish.<br>• **Ikon**: Start (flag hijau), Finish (checkered), marker tiap-km (oranye bernomor), serta marker runner (pejalan) yang mengikuti kamera.<br>• **Musik latar** opsional: upload file sendiri atau pakai default instrumental Pixabay. Saat merekam, audio dimix ke MediaStream sehingga **ikut terekam** ke video `.webm`. |

## Tidak ada perubahan skema database (PostgreSQL)

Tidak ada tabel/kolom baru yang ditambahkan di putaran ini. File `.sql` lama
tetap berlaku. Tabel `flyover_renders` masih dibuat otomatis idempotent di
`flyover.php` seperti sebelumnya.

## Yang HARUS Anda lakukan setelah deploy

1. **Set environment variable `GEMINI_API_KEY`** dengan key valid dari
   <https://aistudio.google.com/apikey> (gratis, diawali `AIza...`). Tanpa
   ini SEMUA fitur AI (Coach, Tanya Islami, Safety Monitor, Kalori dari
   foto, AI Route prompt) tetap akan gagal dengan pesan yang sama dari
   server Google.
   - Di Apache/Nginx + PHP-FPM: tambahkan ke pool config
     `env[GEMINI_API_KEY] = AIzaXXXXXXXXXXXXXXXXX`
   - Atau di shell sebelum start: `export GEMINI_API_KEY=AIza...`
   - Atau hardcode di `config/env.local.php`:
     `putenv('GEMINI_API_KEY=AIza...');`
2. Restart PHP-FPM / web server agar env var ter-load.
3. Buka kembali halaman2 yang error → harusnya sudah jalan.

## Tidak diubah ke React JS

Tetap PHP + PostgreSQL murni, siap dijalankan local (XAMPP / `php -S`).

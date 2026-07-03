# Revisi R6 (Juli 2026) — Sportapp Core

Arsip ini HANYA berisi file yang direvisi. Extract dan **timpa** ke folder project Anda (backup dulu).

## Daftar file
- `profile.php` — tambah label "Masa Expire:" sebelum badge tanggal expire paket.
- `user.php` — tambah kolom `paket_expires_at` di SELECT + label "Masa Expire:" pada tampilan profil user lain.
- `admin/members.php`
  - Modal edit member sekarang scrollable (`modal-dialog-scrollable`) — tombol Simpan/Batal tidak lagi tertutup pada layar pendek.
  - Query statistik **Total Member Aktif per Komunitas** dirombak: sekarang menggabungkan pivot `user_komunitas` **dan** kolom lama `users.komunitas_id` lewat CTE `pairs`, lalu memakai `FILTER (WHERE ...)` agar hitungan aktif akurat termasuk data lama sebelum migrasi pivot.
- `includes/ai_gemini.php`
  - `GEMINI_API_BASE` sekarang bisa di-override lewat **environment variable** (bukan lagi konstanta hardcode) — pakai reverse-proxy Cloudflare Worker/Vercel Edge di region yang didukung Google untuk bypass "User location is not supported".
  - Tambah env `GEMINI_FALLBACK_BASE` — bila error geo-block terjadi, helper otomatis retry sekali ke base fallback tersebut sebelum menyerah.
  - `gemini_config_status()` sekarang juga menampilkan `proxy` dan `fallback_base` untuk memudahkan debugging.

## PostgreSQL — perubahan skema
**Tidak ada perubahan skema baru** untuk revisi R6 ini. Semua tabel/kolom yang dipakai (`users.paket`, `users.paket_expires_at`, `users.aktif`, `user_komunitas`) sudah ada dari revisi sebelumnya (R2/R4). Pastikan migrasi berikut sudah pernah dijalankan (idempotent, aman diulang):

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis';
ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_expires_at TIMESTAMP;
CREATE TABLE IF NOT EXISTS user_komunitas (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  komunitas_id INTEGER NOT NULL REFERENCES komunitas(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (user_id, komunitas_id)
);
```
(File `admin/members.php` juga menjalankan `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` secara idempotent saat halaman dibuka, jadi biasanya otomatis.)

## Cara mengaktifkan bypass Gemini geo-block
Pilih salah satu (server lokal Anda tinggal set env lalu reload):

1. **Proxy HTTP** — set env: `GEMINI_HTTP_PROXY=http://user:pass@host:port` (atau `socks5h://...`).
2. **Reverse-proxy sendiri** (paling stabil) — deploy Cloudflare Worker sederhana yang mem-forward ke `generativelanguage.googleapis.com/v1beta`, lalu set `GEMINI_API_BASE=https://<sub>.workers.dev/v1beta`.
3. **Auto-fallback** — set `GEMINI_FALLBACK_BASE=https://<sub>.workers.dev/v1beta`. Helper otomatis coba fallback bila permintaan pertama kena geo-block.
4. **Key baru dari akun di region didukung** (US/SG/JP) — set `GEMINI_API_KEY` baru; multi-key (`GEMINI_API_KEY_1..20` atau `GEMINI_API_KEYS=csv`) tetap didukung.

Semua env di atas dibaca dari `getenv() / $_ENV / $_SERVER`, jadi cocok untuk `.env`, Apache/Nginx `SetEnv`, atau systemd unit.

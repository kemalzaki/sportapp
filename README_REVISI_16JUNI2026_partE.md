# Revisi 16 Juni 2026 — Part E

Isi zip ini **hanya file yang direvisi sebagian**, bukan seluruh aplikasi.
Data `.sql` & datanya dari zip awal **tidak diubah dan tidak dihapus**.

---

## 1) Kenapa AI Gemini belum berfungsi?

Penyebab utamanya: di `config/env.local.php` yang Anda kirim **tidak ada
baris `GEMINI_API_KEY` sama sekali**. Yang terisi hanya Midtrans dan
`ADMIN_WA_FIRDAM`. Jadi meskipun Anda merasa "sudah set key", PHP tidak
pernah benar-benar menerima key tersebut → semua fitur AI (api_run,
monitoring, islami, live_tracking, kalori_mingguan) balik dengan pesan
`GEMINI_API_KEY belum di-set`.

### Perbaikan di Part E

File `config/env.local.php` di zip ini sudah ditambah blok:

```php
hf_env_set('GEMINI_API_KEY', 'GANTI_DENGAN_AIza_KEY_ANDA');
hf_env_set('GEMINI_MODEL',   'gemini-2.5-flash');
```

### Yang harus Anda lakukan di local

1. Buka https://aistudio.google.com/apikey, login Google, klik
   **Create API key**.
2. Copy key-nya — formatnya **wajib diawali `AIza...`**.
3. Buka `config/env.local.php`, ganti `GANTI_DENGAN_AIza_KEY_ANDA`
   dengan key Anda.
4. **Restart Apache / PHP** (XAMPP: Stop → Start Apache; Laragon:
   Reload; PHP built-in: Ctrl+C lalu jalankan lagi `php -S`).
5. Cek cepat — buat file sementara `cek_gemini.php` di root project:

   ```php
   <?php
   require_once __DIR__ . '/includes/ai_gemini.php';
   header('Content-Type: text/plain');
   print_r(gemini_config_status());
   ```

   Buka di browser. Harus muncul `has_key => 1` dan
   `key_masked => AIzaSy...xxxx`. Hapus file ini setelah cek.

### Kesalahan umum yang bikin AI tetap mati

| Gejala | Penyebab | Solusi |
|---|---|---|
| `GEMINI_API_KEY belum di-set` | Baris key belum ada / masih placeholder | Isi key asli di `env.local.php` lalu restart server |
| `Format GEMINI_API_KEY tidak valid` / `AQ...` | Anda paste OAuth token dari Google Sign-In | Bukan API key. Pakai key `AIza...` dari AI Studio |
| `API key not valid` (HTTP 400) | Key dibatasi region / project disabled | Buat ulang key di AI Studio, jangan restrict dulu |
| AI jalan di CLI tapi tidak di browser | Apache dijalankan **sebelum** key dimasukkan | Restart Apache wajib setiap kali env diubah |
| Key sudah benar tapi tetap gagal | Antivirus/firewall blok `generativelanguage.googleapis.com` | Whitelist domain itu di firewall |

---

## 2) File yang direvisi (isi zip ini)

```
README_REVISI.md
README_REVISI_16JUNI2026.md
README_REVISI_16JUNI2026_partB.md
README_REVISI_16JUNI2026_partC.md
README_REVISI_16JUNI2026_partD.md
README_REVISI_16JUNI2026_partE.md       (baru)
config/env.local.php                    (REVISI: + blok GEMINI_API_KEY)
includes/ai_gemini.php
includes/header.php
includes/security.php
admin/sistem.php
api_ai.php
api_run.php
flyover.php
islami.php
kalori_mingguan.php
live_tracking.php
monitoring.php
rukun_islam.php
run.php
shalat_rawatib.php
shalat_sunnah.php
shalat_tatacara.php
```

Cara apply: **timpa file dengan nama yang sama** di project local Anda.
File `.sql` dan data lama tidak perlu diubah.

---

## 3) Perubahan PostgreSQL yang diperlukan

**Tidak ada** migrasi PostgreSQL baru untuk Part E. Semua tabel sudah
ada di `sportapp.sql` dari zip awal, dan migrasi tambahan dari Part
sebelumnya (`migrations_revisi_13juni2026.sql`,
`migrations_run_advanced_15juni2026.sql`,
`migrations_komunitas_extra_15juni2026.sql`) **tetap berlaku** — pastikan
sudah dijalankan sekali di PostgreSQL Anda jika belum:

```bash
psql -U postgres -d sportapp -f migrations_revisi_13juni2026.sql
psql -U postgres -d sportapp -f migrations_run_advanced_15juni2026.sql
psql -U postgres -d sportapp -f migrations_komunitas_extra_15juni2026.sql
```

Stack tetap **PHP + PostgreSQL** (tidak diubah ke React/Node).

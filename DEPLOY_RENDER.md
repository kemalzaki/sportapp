# Cara Menjalankan di Server Render (https://sportapp-rumd.onrender.com)

`config/env.local.php` masuk `.gitignore`, jadi **tidak akan terikut push ke GitHub
maupun ke Render**. Itu sudah benar untuk keamanan (apalagi setelah Google
mengirim peringatan kebocoran kunci). Konsekuensinya: di Render, variabel
seperti `GEMINI_API_KEY` harus diisi lewat **Environment Variables Render**,
bukan lewat file.

Helper `includes/ai_gemini.php` (Part D/E) sudah membaca key dari:

1. `getenv('GEMINI_API_KEY')`
2. `$_ENV['GEMINI_API_KEY']`
3. `$_SERVER['GEMINI_API_KEY']`
4. File `config/env.local.php` (hanya ada di local Anda)

Artinya: cukup set env var di Render → otomatis kebaca, tanpa commit key apa pun.

## Langkah di Render

1. Buka https://dashboard.render.com → pilih service `sportapp-rumd`.
2. Tab **Environment** → **Add Environment Variable**.
3. Tambahkan:

   | Key | Value |
   |---|---|
   | `GEMINI_API_KEY` | `AIza...` (key baru dari https://aistudio.google.com/apikey) |
   | `GEMINI_MODEL` | `gemini-2.5-flash` |
   | `MIDTRANS_MERCHANT_ID` | `G537554248` |
   | `MIDTRANS_CLIENT_KEY` | `Mid-client-a0Qdc090d4Z1OSXw` |
   | `MIDTRANS_SERVER_KEY` | `Mid-server-nQ40waJaQMihHi-DnUtxndLH` |
   | `MIDTRANS_PROD` | `1` |
   | `ADMIN_WA_FIRDAM` | `6281386369207` |
   | `MAPBOX_TOKEN` | (opsional, kalau mau override token Mapbox hardcoded) |

4. Klik **Save Changes** → Render akan auto-redeploy. Tunggu deploy selesai.
5. Test: buka `https://sportapp-rumd.onrender.com/monitoring.php` atau
   `islami.php` → fitur AI sudah harus jalan.

## PERINGATAN PENTING tentang GEMINI_API_KEY

Di file `config/env.local.php` yang Anda kirim masih ada baris:

```php
hf_env_set('GEMINI_API_KEY', 'AQ.Ab8RN6IL-6ERW08AYymhdutqv5VhxMYakUyRL17hVbzN5Lu0OQ');
```

**Ini bukan API key Gemini yang valid.** Token diawali `AQ.Ab8RN6...` itu adalah
**OAuth/service-account token** dari Google Sign-In, persis seperti yang dilaporkan
Google di email peringatan. Google sudah / akan menghapusnya, dan walaupun
masih hidup, server Gemini akan menolak dengan error:

> Request had invalid authentication credentials. Expected OAuth 2 access token...

**Solusi:**

1. Buka https://aistudio.google.com/apikey → **Create API key**.
2. Copy key baru — **wajib diawali `AIza...`** (contoh: `AIzaSyB7...`).
3. Local: ganti baris di `config/env.local.php` dengan key baru.
4. Server Render: paste key baru di Environment Variables (langkah di atas).
5. Jangan pernah commit key ini ke GitHub — `.gitignore` Anda sudah benar,
   biarkan begitu.

## Setelah leak GitHub

Karena key lama sempat ter-push ke commit publik
(`https://github.com/kemalzaki/sportapp/blob/ef64072.../includes/ai_gemini.php`),
walaupun sudah dihapus dari kode terbaru, **history Git masih menyimpannya**.
Ini hal terpisah dari deploy:

- Anggap key `AQ.Ab8RN6Ih3agk9...` sudah mati (Google akan menghapusnya).
- Pakai key baru `AIza...` dan jangan pernah hardcode di file yang
  ter-commit. Helper `ai_gemini.php` Part C ke atas sudah tidak lagi memakai
  fallback hardcoded.
- Kalau ingin bersih total dari history Git: rewrite history dengan
  `git filter-repo --invert-paths --path includes/ai_gemini.php` lalu
  force-push (opsional, untuk kebersihan).

## Database PostgreSQL — yang perlu ditambahkan

Tidak ada migrasi baru untuk Part E. Pastikan migrasi sebelumnya sudah
dijalankan di Postgres di Render (atau Postgres lain yang dipakai service):

```bash
psql "$DATABASE_URL" -f migrations_revisi_13juni2026.sql
psql "$DATABASE_URL" -f migrations_run_advanced_15juni2026.sql
psql "$DATABASE_URL" -f migrations_komunitas_extra_15juni2026.sql
```

(File `.sql` di-zip awal — tidak diubah, datanya tidak dihapus.)
Tabel `flyover_renders` dibuat idempotent otomatis oleh `flyover.php`,
tidak perlu intervensi manual.

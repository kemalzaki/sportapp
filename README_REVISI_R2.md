# Revisi R2 (Juli 2026) — Catatan Rilis

Arsip ini HANYA berisi file yang direvisi. Timpa file dengan nama & path
yang sama di project Anda (backup dulu). Tidak ada data yang dihapus.

## Perubahan
1. **admin/members.php** — CRUD masa expire paket akun per member
   (kolom baru `users.paket_expires_at`). Setelah lewat tanggal ini,
   paket otomatis turun ke *gratis* (logika sudah ada di
   `includes/paket_helpers.php`).
2. **admin/members.php** — Filter "Daftar Member" **by komunitas**
   (dropdown di header tabel + query string `?filter_komunitas=<id>`).
3. **admin/members.php** — Setiap member dapat memiliki **> 1 komunitas**
   melalui tabel pivot baru `user_komunitas`. Kolom lama
   `users.komunitas_id` tetap dipertahankan (kompatibilitas mundur) dan
   diisi otomatis dengan komunitas pertama tiap kali disimpan.
4. **includes/bottom_nav.php** — Menonaktifkan CSS `@view-transition`
   (MPA View Transitions). Ini penyebab flash halaman putih di mobile
   yang membuat bottom nav "hilang" saat pindah halaman (mis. Tracking
   Jalur). Bottom nav tetap tampil karena posisinya `fixed`.
5. **includes/header.php** — Item "CRUD Toko Perlengkapan Olahraga" pada
   grup drawer "Pengaturan Lainnya" DIHAPUS. Halaman/route
   `/admin/toko_olahraga.php` tidak dihapus, hanya tidak ditampilkan
   lagi di menu navigasi drawer.
6. **profile.php & user.php** — Menampilkan daftar nama komunitas member
   (mendukung multi-komunitas dari tabel pivot; fallback ke kolom lama).
7. **admin/members.php** — Panel "Statistik Member Aktif per Komunitas"
   di bagian atas halaman (klik kartu untuk auto-filter).
8. **api_ai.php & includes/ai_gemini.php** — Perbaikan agar bisa
   digunakan:
   - `api_ai.php` kini menyalakan `ob_start()`, lalu `ob_clean()`
     tepat sebelum meng-`echo` JSON. Dipasang juga
     `register_shutdown_function` untuk mengubah fatal error PHP
     menjadi JSON `{"ok":false,"err":"…"}` sehingga client tidak lagi
     menerima HTML `<br />` yang membuat `JSON.parse` gagal.
   - `includes/ai_gemini.php` — Dimatikan `error_log()` debug yang
     dieksekusi tiap request (mengurangi noise & risiko output tercemar).
     Rotasi `GEMINI_API_KEY_1..20` (format `AQ.` maupun `AIza…`) tidak
     diubah — sudah kompatibel dengan environment variable Anda.

## Instalasi (PostgreSQL)
Jalankan **satu file** di `migrations/revisi_r2.sql`:

```bash
psql "$DATABASE_URL" -f migrations/revisi_r2.sql
```

File tersebut idempotent (aman di-run berulang). Ringkasan:
- `ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_expires_at TIMESTAMP;`
- `CREATE TABLE IF NOT EXISTS user_komunitas (user_id, komunitas_id, ...);`
- Backfill `user_komunitas` dari `users.komunitas_id` lama.

Tidak ada tabel/kolom yang dihapus. Data existing aman.

## Environment Variable (AI)
Sesuai screenshot Anda, cukup pastikan variable berikut ada di server:
- `GEMINI_API_KEY_1` … `GEMINI_API_KEY_6` (format `AQ.…`)
- `GEMINI_MODEL=gemini-2.5-flash`

Tidak ada perubahan konfigurasi lain yang diperlukan.

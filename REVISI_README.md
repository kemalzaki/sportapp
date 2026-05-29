# REVISI SportApp — Catatan Penerapan

Arsip ini berisi HANYA file-file yang direvisi. Timpa file lama dengan struktur folder yang sama.

## Daftar File
- `admin/event.php`           — CRUD event diperluas: bisa untuk non-olahraga (Nyate Bersama, arisan, dll) + pilih member peserta (checklist).
- `admin/jadwal.php`          — Perbaikan: `include` header diganti `require_once` + `?>` di baris terpisah supaya tidak bocor sebagai literal text saat PHP tidak mem-parsing dengan benar.
- `admin/qr_show.php`         — Sudah ada tombol "Hapus QR Aktif" di samping "Buka link check-in" (sertakan ulang untuk memastikan versi terbaru).
- `index.php`                 — Untuk role `admin`: kartu "Kabari Member (Koordinator PIC)" selalu muncul; bila admin belum punya member ber-PIC, fallback menampilkan semua member yang punya nomor WA.
- `includes/dm_floating.php`  — Chat melayang sekarang menampilkan checklist ala WhatsApp: ✓ terkirim, ✓✓ sampai, ✓✓ hijau (dibaca). Memakai `statuses` dari `api_dm.php` yang sudah ada.
- `includes/header.php`       — Menambahkan link `assets/css/desktop-fix.css`.
- `assets/css/desktop-fix.css`— BARU. Memastikan tampilan desktop/laptop tidak ada scroll horizontal yang menembus ke kanan (overflow-x hidden, max-width, table-responsive wrapper, dll).

## PostgreSQL — Perlu DITAMBAHKAN?
TIDAK ada migrasi schema baru yang wajib. Semua revisi memakai tabel/kolom yang sudah ada:
- `event(jenis VARCHAR(50), tipe VARCHAR(30))` — cukup panjang untuk string seperti "Nyate Bersama" / "sosial".
- `event_peserta(event_id, user_id, tim_id)` — sudah mendukung peserta perorangan (user_id) tanpa tim.
- `users.pic_admin_id`, `users.nomor_wa` — sudah ada.
- `dm_messages.delivered_at, read_at` — sudah ada (dipakai untuk ceklis).

Jika `event.tipe` Anda set lebih ketat (mis. CHECK constraint) silakan kendurkan,
contoh:
```sql
ALTER TABLE event ALTER COLUMN tipe TYPE VARCHAR(40);
ALTER TABLE event ALTER COLUMN jenis TYPE VARCHAR(80);
```
Opsional saja — default 30/50 char sudah cukup untuk preset yang disediakan.

## Catatan
- Tidak ada data yang dihapus. Aman digabungkan dengan database lama.
- Jalankan lokal seperti biasa (PHP built-in / Apache / Nginx + PHP-FPM) dengan PostgreSQL.
- Bila item #4 (literal text `include __DIR__.'/../includes/header.php'; ?>`) masih muncul setelah file diganti, pastikan:
  1. File `jadwal.php` benar-benar di-serve oleh PHP (bukan sebagai static file).
  2. Tidak ada error PHP fatal sebelum `include` (cek error log).
  3. Jangan ada BOM / whitespace sebelum `<?php` di file include.

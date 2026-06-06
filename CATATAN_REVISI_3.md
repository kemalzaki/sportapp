# Catatan Revisi — 6 Juni 2026 (revisi-3)

Arsip ini **hanya** berisi file yang berubah pada revisi-3, bukan keseluruhan project.
Letakkan/timpa file di lokasi yang sama di project lokal kamu.

## File yang diubah

1. `index.php`
   - **Logo / ikon warna-warni** pada kartu di section *"Belajar & tetap update setiap hari"*.
     Tidak lagi seragam biru semua. Sekarang:
     - Berita Terkini 2026 → merah (danger)
     - IPTV Indonesia → hijau (success)
     - Kesehatan → merah (danger) — sudah dari sebelumnya
     - Paket Bugar Kalistenik → kuning (warning)
     - Artikel Olahraga & Teknik → cyan (info)
     - Panduan Olahraga → merah (danger, YouTube)
     - Paket Pemanasan → kuning (warning, fire)
     - Paket Pendinginan → cyan (info, snow)
   - **IPTV → popup modal** (bukan lagi pindah ke halaman `/iptv.php`).
     - PHP fetch + parse playlist M3U `iptv-org/id.m3u` dilakukan inline di `index.php`
       dengan cache 6 jam di `sys_get_temp_dir()/sportapp_iptv/id.m3u`.
     - Modal `#modalIPTV` berisi: search box, grid channel (logo + nama + group),
       dan player `<video>` + `hls.js` untuk stream `.m3u8`.
     - Player otomatis berhenti saat modal ditutup.
   - File `iptv.php` lama tetap ada (tidak dihapus), tapi tidak dipakai dari menu lagi.

2. `admin/members.php`
   - Kolom **Koordinator Penghubung** di tabel member sekarang **selalu** menampilkan
     5 opsi tetap di select box: **Alya, Umy, Devi, Yuni, Medew**.
   - Bila user dengan nama tersebut belum ada di database, kode akan otomatis
     membuatkannya (idempotent, INSERT … ON CONFLICT DO NOTHING).
     Email placeholder: `koor_<nama>@hapfam.local`, role `member`, password acak
     (tidak dipakai untuk login — user ini hanya jadi "tag" koordinator).

## Database / PostgreSQL

**Tidak ada** migrasi manual yang wajib kamu jalankan. Semua dilakukan otomatis
saat halaman `admin/members.php` dibuka:

- `ALTER TABLE users ADD COLUMN IF NOT EXISTS koordinator_id INTEGER REFERENCES users(id) ON DELETE SET NULL;`
- `INSERT INTO users(nama,email,password_hash,role) VALUES (...) ON CONFLICT (email) DO NOTHING;`
  untuk Alya, Umy, Devi, Yuni, Medew.

Data existing **tidak dihapus**. File `sportapp.sql` tidak perlu diubah.

## CSP

Tidak ada perubahan tambahan untuk revisi-3 — domain `raw.githubusercontent.com`,
`cdn.jsdelivr.net`, dan `media-src` untuk HLS sudah ditambahkan pada revisi-2
di `includes/security.php`. Jika kamu belum apply revisi-2, gunakan zip
revisi-2 sebelumnya atau pastikan CSP-mu mengizinkan:
- `connect-src ... https://raw.githubusercontent.com`
- `script-src ... https://cdn.jsdelivr.net`
- `media-src 'self' blob: data: https:`

## Cara apply

```bash
unzip sportapp_core_revisi3_6jun2026.zip -d /tmp/r3
cp /tmp/r3/index.php /path/ke/project/
cp /tmp/r3/admin/members.php /path/ke/project/admin/
```

Lalu refresh browser. Buka halaman utama → klik kartu **IPTV Indonesia** untuk
melihat popup. Buka **Admin → Manajemen Member** untuk melihat select box
Koordinator Penghubung berisi 5 nama tetap.

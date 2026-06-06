# Catatan Revisi SportApp Core — 6 Juni 2026 (Revisi-2)

Arsip ini **HANYA** berisi file yang direvisi pada putaran ini. Ekstrak dan
timpa file dengan path yang sama di project Anda (struktur folder
`admin/` dan `includes/` dipertahankan).

## Daftar file & isi revisi

| # | File | Perubahan |
|---|------|-----------|
| 1 | `includes/header.php` | **Logo HapFam SportApp dibuat berwarna** (tidak biru semua). Petir kuning + "Hap" merah, "Fam" oranye, "Sport" hijau, "App" indigo. Berlaku di navbar desktop dan drawer offcanvas. CSS ditambahkan di blok `<style>` (class `.brand-logo-colored`). |
| 2 | `admin/members.php` | **Koordinator Penghubung** kembali dibatasi 5 nama tetap: **Alya, Umy, Devi, Yuni, Medew** (case-insensitive). Query: `WHERE LOWER(nama) IN ('alya','umy','devi','yuni','medew')`. |
| 3 | `index.php` | Tambah **1 menu** di section "Belajar & tetap update setiap hari" → **IPTV Indonesia** (di samping "Berita Terkini 2026"). Mengarah ke `/iptv.php`. |
| 4 | `iptv.php` (BARU) | Halaman pemutar IPTV. Menarik playlist `https://raw.githubusercontent.com/iptv-org/iptv/master/streams/id.m3u` di sisi server (cache 6 jam di `sys_get_temp_dir()`), lalu menampilkan grid channel dengan logo + pencarian. Player pakai `<video>` + hls.js (dari jsdelivr) untuk stream `.m3u8`. |
| 5 | `includes/security.php` | **Fix CSP** (ada syntax error PHP sebelumnya: `;` ganda di akhir `form-action` lalu `. "worker-src ..."`). Sekalian ditambahkan: `media-src 'self' blob: data: https:;` untuk HLS, dan host `https://raw.githubusercontent.com` di `connect-src` agar fetch m3u tidak diblok bila kelak dipindah ke fetch sisi client. `worker-src 'self' blob:;` juga dipindah ke posisi yang benar. |

## Database PostgreSQL

**Tidak ada migrasi baru** yang perlu dijalankan untuk revisi ini.
Tidak ada tabel/kolom baru. File `sportapp.sql` di arsip awal **tidak diubah**
dan datanya tidak dihapus.

## Cek CSP (sesuai permintaan)

- Sebelumnya ada bug: `. "form-action 'self';";` ditutup dengan `;` lalu
  baris berikutnya `. "worker-src ..."` → fatal `ParseError`. Sudah diperbaiki.
- `frame-src` sudah mengizinkan YouTube (untuk modal Panduan/Pemanasan/Pendinginan).
- `media-src 'self' blob: data: https:;` ditambahkan agar HLS (`.m3u8`/segmen
  `.ts`) dari berbagai CDN channel bisa diputar `<video>` + hls.js.
- `script-src` sudah mengizinkan `https://cdn.jsdelivr.net` → hls.js bisa dimuat.
- `connect-src` ditambah `https://raw.githubusercontent.com` (selain wildcard
  `https:` yang sudah ada).

## Cara apply

1. Backup folder project lama.
2. Ekstrak `sportapp_core_revisi2_6jun2026.zip`.
3. Copy isinya menimpa file dengan nama yang sama (mempertahankan
   struktur `admin/`, `includes/`).
4. Reload halaman di browser. Tidak perlu restart PostgreSQL.

## Catatan untuk pemutaran IPTV

- Sumber playlist iptv-org bersifat publik dan banyak channel hanya bisa
  diputar bila server / klien berada di Indonesia (geo-blocked).
- Channel yang membutuhkan DRM (Widevine) tidak akan jalan di `<video>`.
- Cache playlist disimpan 6 jam di folder temp sistem (`sys_get_temp_dir()`).
  Hapus file `sportapp_iptv/id.m3u` di sana bila ingin force refresh.

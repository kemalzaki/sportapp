# HapFam SportApp — Revisi 4 Juni 2026 (Partial)

Zip ini berisi **hanya file yang direvisi** pada putaran ini. Ekstrak ke root project (overwrite file lama).

## Daftar perubahan

### 1. Warna navigasi atas & bawah → biru-kehitaman (BUKAN hijau)
- `assets/css/gojek-top.css` — header atas mobile sekarang gradient `#0f172a → #1e293b` (slate-900 → slate-800) dengan aksen sky/indigo, selaras dengan `index.php` & navbar desktop. Variabel `--gt-green` dibuang.
- `assets/css/gojek-nav.css` — bottom nav: ikon bulat semua memakai gradient biru-kehitaman; FAB tengah (Upload) memakai gradient `sky → indigo`; indikator aktif memakai biru. Tidak ada hijau lagi.
- Login & Daftar hero overlay diubah dari hijau/indigo terang ke `rgba(15,23,42,.35→.78)` agar **foto manusia** terlihat dan nuansa konsisten biru-kehitaman.

### 2. Lonceng → popup notifikasi (BUKAN redirect)
- `includes/header.php` — tombol lonceng (mobile **dan** desktop) kini membuka popup notifikasi (`.gt-notif-pop`) yang menampilkan 15 notifikasi terakhir + tombol "Tandai dibaca".
- File baru: `api_notif_list.php` — endpoint JSON yang memuat notifikasi user yang login. Dipanggil oleh popup secara AJAX.
- Klik di luar popup otomatis menutup. Ikon notifikasi tetap menampilkan badge unread.

### 3. Duplikasi "Profil Saya" di navigasi dirapikan
- `includes/header.php`:
  - Item "Profil Saya" di drawer mobile dibuang (sudah ada avatar di top bar + ikon "Saya" di bottom nav).
  - Navbar desktop: hanya 1 pintu ke profil (avatar). Tombol lonceng desktop dipisahkan jadi popup, sehingga tidak ada link ganda ke `/profile.php`.

### 4. Suara klik aktif di SEMUA halaman & SEMUA elemen interaktif
- `assets/js/sfx.js` — listener klik global pada selector lengkap (`a, button, [role=button], input[type=submit|button|reset], .btn, .nav-link, .dropdown-item, .list-group-item-action, .gt-chip, .gj-item, label[for]`, dsb).
- Submit form → `SFX.success()`. Perubahan checkbox/switch → `SFX.toggle()`. Halaman dengan `.alert-danger / .is-invalid` → otomatis bunyi error saat dimuat.
- Mute/unmute via console: `SFX.mute()` / `SFX.unmute()` (disimpan di `localStorage`).

### 5. Skeleton loading SESUAI data tiap halaman
- `includes/skeleton.php` — generic full-screen overlay yang dipakai semua halaman **dihapus**. Sekarang tersedia API yang spesifik:
  - `HFSkel.list(n)` — kartu list (riwayat, member)
  - `HFSkel.grid(n)` — grid thumbnail (tempat, produk, jajanan)
  - `HFSkel.feed(n)` — feed sosial (gambar + caption)
  - `HFSkel.chat(n)` — gelembung chat (`dm.php`)
  - `HFSkel.video()` — player + judul (modal IPTV / video)
  - `HFSkel.profile()` — header profil + 3 statistik
  - `HFSkel.table(rows, cols)` — tabel admin
  - `HFSkel.inject(selector, html)` — sisipkan ke wadah yang ditentukan
- Opt-in per halaman: tambahkan ke `<body>`-nya `data-skeleton="grid|list|chat|feed|video|profile|table"` dan beri `<div id="skel-host"></div>` di tempat data akan dirender. Skeleton akan hilang otomatis saat `window.load`.

### 6. IPTV bisa diputar di handphone (Render-friendly)
- `iptv_proxy.php` — **BUG FIX kritikal**: saat manifest `.m3u8` di-rewrite, header `Content-Length` / `Accept-Ranges` / `Content-Range` upstream **tidak boleh** diteruskan karena panjang body sudah berubah. Akibat sebelumnya: Android Chrome (hls.js) memotong playlist di tengah jalan → "MANIFEST_PARSING_ERROR" dan video tidak pernah play. Sekarang `Content-Length` dihitung ulang dari body yang sudah ditulis.
- Tambahan: User-Agent mobile yang realistik (Chrome Android), header CORS lengkap (termasuk `OPTIONS` preflight), dukungan `HEAD`, dan `Accept-Encoding: identity` agar tidak ada gzip yang merusak rewrite.
- Tidak ada perubahan logic pemain di `index.php` karena sudah benar (hls.js diaktifkan paksa di Android, native HLS di iOS).

### 7. Login & Daftar pakai FOTO manusia asli (bukan grafik)
- `assets/img/sport-auth-hero.jpg` — foto atlet lari di lintasan saat golden hour (manusia asli, realistic).
- `assets/img/sport-auth-hero-2.jpg` — foto komunitas main futsal (pria & perempuan berhijab) saat sunset (manusia asli, realistic, sopan untuk konteks user Muslim).
- `login.php` & `register.php` — overlay gradient dilembutkan ke biru-kehitaman tipis supaya foto terlihat jelas; lingkaran glow hijau diganti biru.

## File di dalam zip
```
api_notif_list.php                 (BARU)
iptv_proxy.php                     (FIX MOBILE)
login.php                          (overlay + foto)
register.php                       (overlay + foto)
includes/header.php                (lonceng popup, profil rapi, warna biru)
includes/skeleton.php              (per-halaman, bukan global)
assets/css/gojek-top.css           (biru-kehitaman)
assets/css/gojek-nav.css           (biru-kehitaman)
assets/js/sfx.js                   (SFX di semua klik)
assets/img/sport-auth-hero.jpg     (foto manusia asli)
assets/img/sport-auth-hero-2.jpg   (foto manusia asli)
```

## Catatan database (PostgreSQL)
**Tidak ada perubahan schema** pada putaran ini. Endpoint `api_notif_list.php`
memakai tabel `notifications(user_id, jenis, judul, isi, url, dibaca, dibuat_pada)`
yang **sudah ada** di `sportapp.sql`. Tidak perlu menambah migrasi.

Jika di instalasi lokal Anda tabel `notifications` belum punya kolom `dibuat_pada`
(beberapa versi lama tidak punya), jalankan:
```sql
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS dibuat_pada TIMESTAMP DEFAULT now();
```
Selain itu tidak ada perubahan data — data lama aman.

## Cara pakai
1. Backup folder project Anda dulu.
2. Ekstrak zip ini ke root project, overwrite file yang ada.
3. Hard-refresh browser (Ctrl+Shift+R) supaya CSS/JS versi baru terambil (query
   string `?v=4jun2026` sudah ditambahkan di header.php).
4. Tes:
   - Buka via HP → header & bottom nav harus biru-kehitaman.
   - Klik lonceng → popup notifikasi muncul, bukan ganti halaman.
   - Buka login & daftar → foto manusia terlihat jelas.
   - Klik tombol/nav/menu manapun → terdengar bunyi tap halus.
   - Buka modal Video Terbaru (IPTV) di HP → channel pertama auto-play.

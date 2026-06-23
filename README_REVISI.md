# Revisi KawanKeringat

## Perubahan (revisi terbaru, 23 Juni 2026)

### 1. Tampilan desktop = tampilan mobile (frame mobile)
File: `assets/css/gojek-top.css`
- Navbar bootstrap desktop (`nav.navbar.sticky-top`) disembunyikan di semua ukuran layar.
- Top bar mobile (`.gt-top`), chips (`.gt-chips`), dan bottom nav (`.gj-nav`) selalu aktif.
- Pada layar >= 992px, konten dibatasi maksimal 480px dan diposisikan di tengah, dengan background gelap di sisi kiri/kanan — sehingga tampak seperti emulator mobile.

### 2. Gambar pelari (komunitas jogging) dihapus
File: `index.php`
- Tag `<img src="assets/img/card-olahraga.jpg">` di hero "Halo, …" sudah dihapus.

### 3. Branding HapFam → KawanKeringat (dari revisi sebelumnya)
Semua teks merek di header, footer, invoice email, admin, dan halaman utama sudah diganti menjadi KawanKeringat.

## Cara pakai
1. Backup folder project lama (opsional).
2. Salin/timpa file-file dalam folder ini ke root project `sportapp_core/` (struktur path dipertahankan).
3. Hard refresh browser (Ctrl+F5) supaya CSS baru dimuat.

## PostgreSQL
Tidak ada perubahan skema. File `sportapp.sql` tetap dipakai apa adanya — tidak ada migrasi tambahan yang perlu dijalankan.

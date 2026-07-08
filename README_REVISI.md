# Revisi Bottom Navigation — R28 (Juli 2026)

## Masalah
Di Firefox tombol Upload tampil normal (sejajar dengan menu lain).
Di Chrome/Edge (Chromium) tombol Upload melayang & label turun / tidak konsisten.
Penyebab: `margin-top` negatif + shadow besar + `view-transition-name` di
`.gj-nav` menyebabkan Chromium menghitung tinggi flex item berbeda dari
Firefox.

## Solusi
Menyamakan tampilan di semua browser dengan FAB Upload INLINE
(tidak melayang, tidak margin-top negatif). Satu sumber CSS: `gojek-nav.css`.

## File yang direvisi (isi ZIP ini)
- `assets/css/gojek-nav.css`  — ditulis ulang bersih, tanpa `!important`,
  FAB berukuran 36px sejajar dengan ikon menu lain.
- `includes/bottom_nav.php`   — inline `<style>` konflik dihapus,
  `view-transition` dihapus, class `gj-fab-label` diganti `gj-label`,
  placeholder `.bottom-nav.d-none` dihapus.

## Cara pakai
1. Backup dua file lama di project Anda.
2. Timpa dengan file di ZIP ini (path sama persis).
3. Hard-refresh browser (Ctrl+Shift+R). Cache buster CSS: `?v=r28-inline-fab`.

## PostgreSQL
Tidak ada perubahan skema. Tidak ada migration yang perlu dijalankan.

## Verifikasi
- Buka `/index.php` di Chrome & Firefox — bottom nav & tombol Upload
  harus tampil identik: 5 item lebar sama, FAB biru bulat kecil di tengah,
  label "Upload" sejajar dengan label lainnya.

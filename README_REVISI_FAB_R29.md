# Revisi R29 — FAB Upload konsisten Chrome & Firefox

## Masalah
- Di **Firefox** tombol Upload tampil rapi (lingkaran biru + label "Upload" di bawah).
- Di **Chrome** label "Upload" menumpuk / tidak sejajar dengan lingkaran FAB, terlihat berantakan.

## Perbaikan
1. `includes/bottom_nav.php`
   - Menghapus `<span class="gj-label">Upload</span>` pada item FAB.
   - Menambahkan `title="Upload"` supaya tetap ada tooltip & aksesibilitas
     (aria-label sudah ada sebelumnya).
2. `assets/css/gojek-nav.css`
   - Menaikkan `--gj-fab-size` dari `36px` → `44px` supaya lingkaran FAB
     lebih dominan dan seimbang secara visual karena label dihapus.
   - Tidak ada perubahan warna / dark-mode / positioning lain.

## Hasil
Tombol Upload = **lingkaran biru dengan ikon `+` saja**, sama persis
di Chrome, Edge, Firefox, dan Safari. Tidak ada teks yang menumpuk.

## File yang berubah (isi zip revisi ini)
- includes/bottom_nav.php
- assets/css/gojek-nav.css
- README_REVISI_FAB_R29.md

## Database / PostgreSQL
**Tidak ada perubahan skema / data.** Semua file `.sql` (`sportapp.sql`,
`migration_r6.sql`, `REVISI_JULI_2026_R7.sql`, `REVISI_JULI_2026_R8.sql`)
tidak perlu di-run ulang. Cukup timpa 2 file di atas ke folder project
lokal Anda.

## Cara pakai
1. Backup 2 file lama:
   - `includes/bottom_nav.php`
   - `assets/css/gojek-nav.css`
2. Timpa dengan file dari zip ini.
3. Hard reload browser (Ctrl+F5) — cache CSS lama (`?v=r28-inline-fab`)
   akan tergantikan otomatis karena versi query string.

Tidak perlu perubahan PostgreSQL apapun untuk revisi ini.

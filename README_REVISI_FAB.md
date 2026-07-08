# Revisi FAB Upload — Premium Style (Strava/Google Fit/Instagram)

## File yang diubah (hanya UI/CSS, tanpa perubahan logic/PHP/API/DB/route)
- `includes/bottom_nav.php`  — hapus label "Upload" di dalam lingkaran (dipindah ke label bar), bump cache-buster ke `?v=r26-fab`, hapus padding-bottom lama yang kini disinkronkan di CSS.
- `assets/css/gojek-nav.css` — desain ulang FAB.

## Perubahan visual
- FAB berukuran **62px** (var `--gj-fab-size`), naik **12px** (`--gj-fab-lift`) dari bar.
- Bar bottom nav tinggi **64px** (`--gj-h`), proporsional dan simetris (semua item `flex:1`).
- Shadow lembut berlapis (soft drop shadow), **tanpa glow putih**.
- Ikon `+` (`bi-plus-lg`) diperbesar (1.6rem, bold) dan tepat di tengah.
- Label "Upload" hanya di baris bar (konsisten dengan menu lain).
- Animasi tekan `scale(.95)` transition `200ms ease`.
- `body { padding-bottom: calc(--gj-h + --gj-fab-lift + safe-area) }` — memastikan konten tidak tertutup FAB di semua ukuran layar Android/iPhone (termasuk perangkat dengan home indicator).
- Dark mode disesuaikan.

## PostgreSQL
Tidak ada perubahan database, migration, atau data. **Tidak ada SQL yang perlu dijalankan** untuk revisi ini.

## Cara pasang
Ekstrak zip ini ke root project, timpa dua file di atas. Hard-refresh browser (query `?v=r26-fab` sudah otomatis memaksa reload CSS).

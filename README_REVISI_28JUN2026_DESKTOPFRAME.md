# Revisi 28 Juni 2026 — Frame Ponsel di Desktop + Kiloan di Daftar Tempat

File yang diubah (timpa file lama di project lokal):

1. `tempat_list.php`
   - Badge "kiloan" untuk jenis **Hiking** sekarang **selalu menampilkan jarak nyata**
     bila tempat punya `gpx_path` — dihitung di client (haversine atas trkpt/rtept GPX),
     sama persis cara `tempat_detail.php` menghitung "9972 titik · 11.36 km".
   - Saat sedang menghitung, badge menampilkan "menghitung…". Bila GPX kosong/gagal
     dimuat, badge jatuh ke status warning "kiloan: —".
   - Hasil GPX di-cache per URL supaya tidak diunduh berulang.
   - Re-hitung otomatis ketika grid di-replace oleh AJAX filter/pagination
     (MutationObserver pada `#tempatListWrap`).

2. `includes/header.php`
   - Override CSS desktop diperkuat dengan selector lebih spesifik
     (`html body header.gt-top`, dst.) supaya **selalu menang** atas
     `assets/css/gojek-top.css`.
   - Top-bar / chips / bottom-nav dikunci tepat 480px (sama dengan lebar
     frame body), `box-sizing: border-box` + `overflow:hidden` agar isi
     (search input dll.) tidak mendorong lebar keluar frame.
   - Strip chips bisa scroll horizontal (sesuai 4.png) dgn `overflow-x:auto`
     dan `flex-wrap:nowrap`.
   - Gambar/iframe/tabel dipaksa `max-width:100%` agar tidak overflow.

## PostgreSQL — apakah perlu migrasi tambahan?

Tidak ada migrasi baru yang wajib. File `tempat_list.php` versi sebelumnya sudah
menambahkan kolom `tempat.jarak_km` secara idempotent (`ALTER TABLE … ADD COLUMN
IF NOT EXISTS jarak_km NUMERIC(6,2)`) dan auto-fill via GPX server-side. Bila di
beberapa lingkungan auto-fill server-side gagal (mis. `gpx_path` berupa URL yang
tidak bisa dibaca via filesystem), fallback client-side baru ini akan tetap
memunculkan badge km yang benar — tanpa mengubah data SQL apapun.

Tidak ada perubahan skema lain, tidak ada data yang dihapus.

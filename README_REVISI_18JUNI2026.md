# Revisi 18 Juni 2026 — sportapp_core (Partial Zip)

Berikut daftar file yang direvisi pada zip ini. Halaman lain tidak disertakan
(sesuai permintaan: hanya halaman yang berubah).

## File yang berubah
1. `artikel_olahraga.php`
   - Foto cover olahraga DIHAPUS (hanya video YouTube tetap tampil).
   - Tiap olahraga punya 3 gambar ilustrasi (diagram lapangan/pemain) di bagian
     Cara Main, Pembagian Tim, dan Sistem Skoring.
   - Tambahan blok "Peralatan" dengan foto kecil dan deskripsi.

2. `kalori_mingguan.php`
   - FIX: foto kamera/upload tidak terupload + kalori tidak terhitung.
     - Membaca `$_FILES['foto']['error']` (sebelumnya disembunyikan, ini sumber
       silent fail saat foto > upload_max_filesize).
     - Pesan error ImageKit ditampilkan secara detail.
     - Entri tetap tersimpan walau AI / ImageKit gagal (data tidak hilang).
     - Resize otomatis di client (max 1280 px sisi terpanjang) → tidak ditolak
       limit upload server (default 2 MB di banyak hosting).
   - Tambah `MAX_FILE_SIZE` hidden field & hint UI.
   - `$pageSkeleton = 'table'` ditambahkan.

3. `islami.php`
   - Sapaan "Assalāmu‘alaikum…" dipindah ke paling atas; blok Tanya Jawab AI
     turun ke bawah sapaan.

4. `run.php`
   - Route Builder: setiap belokan tajam pada lintasan track diberi marker
     oranye yang bisa diklik → popup berisi:
       * Thumbnail OpenStreetMap titik belokan
       * Tombol Google Street View (URL `?layer=c&cbll=lat,lng`)
       * Tombol "Buka di Google Maps" (panorama)
       * Tombol Mapillary (foto jalan crowdsourced)
     Marker otomatis muncul setelah Generate / Snap / Auto / OSRM dan
     ter-clear saat Reset.

5. `includes/header.php`
   - Auto-default `$pageSkeleton` per halaman (mapping di header) — semua
     halaman kini punya skeleton sesuai jenis data dominan.
   - Page transition interaktif: klik link internal → overlay skeleton muncul
     di area konten sementara top header + bottom nav PWA tetap terlihat,
     memberi kesan "halaman terbuka dulu, baru data di-load".

## PostgreSQL — yang perlu ditambahkan
Tidak ada migration baru. Semua perubahan kompatibel dengan skema yang sudah
ada (`kalori_makanan_log` sudah punya kolom `foto_url`, `foto_file_id`,
`ai_estimasi`, dll).

## Pengujian lokal
- Local PHP + PostgreSQL, replace 5 file di atas.
- `kalori_mingguan.php`: pastikan `config/imagekit.php` berisi kredensial
  ImageKit yang valid. Pastikan `GEMINI_API_KEY` di env untuk fitur AI.
- `run.php` Route Builder: generate rute, lalu klik marker oranye di peta.

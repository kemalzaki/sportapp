SportApp — Revisi 20 Juni 2026
==============================

Daftar file yang direvisi (REPLACE file dengan nama yang sama di project):

1. survival.php
   - Tombol "Pesan Tour Guide (Camping & Survival)" DIHAPUS dari hero.

2. admin/members.php  ← perbaikan bug
   - Fix error "invalid input syntax for type smallint: 'f'" saat
     mengubah status Aktif/Nonaktif. Sekarang tipe kolom `aktif`
     dideteksi dari information_schema lalu nilai dipilih otomatis
     (BOOLEAN -> 't'/'f', SMALLINT -> 1/0). Pesan error stale juga
     dibersihkan.
   - Fix "Mengganti foto profil tidak berfungsi": ditambah validasi
     $_FILES, try/catch ImageKit, dan flash success/error agar admin
     tahu hasilnya. (Lihat juga perbaikan includes/footer.php yang
     mengirim `_action` dari tombol yang diklik saat form AJAX.)

3. profile.php
   - Form "Ganti Foto Profil" sekarang `data-no-ajax`, jadi setelah
     upload sukses halaman ter-reload penuh dan foto baru langsung
     tampil (sebelumnya hanya AJAX, kadang foto belum terganti).
   - Ditambahkan panel "Apa itu Badge & Achievement?" di dalam kartu
     Badge & Achievement berisi 10 badge dan cara mendapatkannya.

4. includes/footer.php
   - Submit AJAX sekarang menyertakan tombol yang diklik
     (FormData(f, ev.submitter)). Ini memperbaiki form bertombol ganda
     dengan name=_action (mis. Upload vs Hapus Foto di
     admin/members.php) yang sebelumnya tidak mengirim _action.
   - Klik link di sidebar mobile (offcanvas #gtDrawer) atau navbar
     collapse #nav sekarang otomatis menutup menu mobile.

5. flyover.php
   - Subtitle lirik di video rekaman: TANPA KOTAK, gaya subtitle film
     (teks putih kecil dengan outline hitam). Default ukuran sekarang
     14px ("Subtitle Film") dan otomatis word-wrap multi-baris bila
     kalimat panjang, jadi tidak terpotong lagi.
   - Pilihan ukuran subtitle ditambah opsi 12/14/16/20/26/32 px.
   - Overlay subtitle di preview juga ikut gaya film.
   - Pencarian lirik kini memanggil endpoint server `/api_lyrics.php`
     (cepat, lihat #6).

6. api_lyrics.php  (BARU)
   - Endpoint server-side untuk mempercepat pencarian lirik.
   - Memanggil lrclib.net (lirik + sinkronisasi LRC bertimestamp) dan
     lyrics.ovh secara PARALEL via curl_multi, timeout 5 detik per
     sumber. Hasil terbaik (LRC > plain > search) dikembalikan
     sebagai JSON. Cache sesi 1 jam supaya pencarian berulang instan.

PostgreSQL
----------
Tidak ada migrasi tabel/skema baru yang wajib dijalankan. Semua
revisi memakai tabel & kolom yang sudah ada. Pastikan kolom
`users.aktif` ada (SMALLINT atau BOOLEAN — keduanya aman setelah
revisi ini).


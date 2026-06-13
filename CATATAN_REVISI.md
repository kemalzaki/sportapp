# Catatan Revisi Login - 13 Juni 2026

Isi ZIP ini hanya file yang direvisi, bukan seluruh aplikasi.

## File yang direvisi

1. `config/db.php`
   - Memperbaiki deteksi HTTPS agar cookie session tidak salah menjadi `Secure` saat dijalankan di local HTTP.
   - Menghapus pengiriman ulang cookie session manual yang bisa bentrok dengan `session_regenerate_id()`.

2. `includes/auth.php`
   - Menambahkan cookie login cadangan `hf_auth` yang ditandatangani HMAC.
   - Jika session PHP tidak terbaca di `index.php`, aplikasi otomatis memulihkan user dari cookie cadangan dan mengambil data user terbaru dari tabel `users`.

3. `login.php`
   - Setelah login berhasil, aplikasi menyimpan session PHP dan cookie cadangan, lalu memanggil `session_write_close()` sebelum redirect ke `/index.php`.

4. `logout.php`
   - Logout sekarang menghapus session dan cookie cadangan.

5. `includes/security.php`
   - Saat session expired, cookie cadangan juga ikut dihapus.

6. `service-worker.js`
   - Halaman navigasi dan file `.php` tidak lagi di-cache oleh service worker, supaya `/index.php` dan `/login.php` selalu mengambil response terbaru dari server.
   - Cache lama `sportapp-v3` akan dibersihkan otomatis.

## PostgreSQL

Tidak perlu menambahkan tabel/kolom PostgreSQL untuk revisi ini. Data SQL yang sudah ada tetap dipakai dan tidak dihapus.

## Catatan penggunaan

Ekstrak ZIP ini ke root project dan timpa file lama sesuai path masing-masing. Setelah mengganti `service-worker.js`, jika browser masih memakai cache lama, lakukan hard refresh atau clear site data sekali.
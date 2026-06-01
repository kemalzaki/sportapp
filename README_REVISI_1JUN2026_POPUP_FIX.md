# Revisi 1 Juni 2026 — Fix Popup & Preloader di `jajanan.php`

## Daftar perbaikan
1. **Tombol "Pesan Sekarang"** sekarang membuka popup (modal toko / modal pemesanan).
2. **Tombol "Lacak"** pada tabel "Cek Pesanan Saya" sekarang membuka modal lacak driver realtime.
3. **Tombol "Detail"** pada tabel "Cek Pesanan Saya" sekarang membuka modal detail pesanan berisi:
   - List semua produk yang dipesan (1 toko bisa banyak produk).
   - Di samping tiap produk: **jumlah pesanan** (qty) + subtotal harga baris.
   - Kartu **Kurir**: nama + nomor telpon/WA + tombol Chat WA & Telepon.
4. **Preloader fullscreen** sekarang muncul juga saat:
   - Klik tombol *Pesan Sekarang* (single & toko).
   - Klik tombol *Detail* dan *Lacak*.
   - Submit form pembayaran Midtrans (single & multi-item toko).
   - Submit form rating.

## Akar masalah (popup tidak muncul)
Script modal (`#lacakModal`, `#tokoModal`, `#detailModal`, `#ratingModal`)
diinisialisasi via `new bootstrap.Modal(el)` di dalam IIFE
(`(function(){...})();`) yang **dieksekusi langsung saat parser HTML
melewati script tersebut**. Padahal `bootstrap.bundle.min.js` baru dimuat
oleh `includes/footer.php` yang di-`include` setelah seluruh script
modal. Akibatnya `typeof bootstrap === 'undefined'` saat IIFE jalan,
sehingga variabel modal selalu `null` dan `modal.show()` tidak pernah
dipanggil.

## Solusi
Setiap modal sekarang menggunakan **lazy getter**:

```js
var tkModal = null;
function getTkModal(){
  if (!tkModal && typeof bootstrap !== 'undefined' && tkModalEl)
    tkModal = new bootstrap.Modal(tkModalEl);
  return tkModal;
}
// ...
var m = getTkModal(); if (m) m.show();
```

Saat tombol diklik, Bootstrap pasti sudah dimuat (footer sudah ter-parse),
sehingga `bootstrap.Modal` tersedia dan popup berhasil muncul.

Preloader (`#jjnPreloader`) sekarang juga meng-expose
`window.JJN_PRELOAD.show(msg)` & `window.JJN_PRELOAD.hide()` agar bisa
dipanggil dari handler tombol dan submit form.

## File yang berubah
- `jajanan.php` (satu-satunya file yang direvisi).

## PostgreSQL
**Tidak ada perubahan skema DB**. Tidak perlu menjalankan migration baru.
Fitur memakai tabel & kolom yang sudah ada dari revisi-revisi sebelumnya
(`jajanan`, `jajanan_pesanan`, `jajanan_pesanan_item`, `toko`, `users`).

Pastikan saja migration revisi sebelumnya sudah dijalankan (yang sudah
ada dalam `sportapp.sql` & file `migrations_*jun2026*.sql`).

## Cara test lokal
1. `php -S 0.0.0.0:8080 -t .` (atau pakai Apache/Nginx).
2. Buka `/jajanan.php`.
3. Isi nama di "Cek Status Pesanan Saya" → klik **Detail** / **Lacak** → popup harus muncul.
4. Klik **Pesan Sekarang** pada salah satu produk → modal toko / modal pesan harus muncul.
5. Saat submit form pembayaran/rating → preloader fullscreen muncul lalu hilang.

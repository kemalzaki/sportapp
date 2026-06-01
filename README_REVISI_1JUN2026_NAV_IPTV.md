# Revisi 1 Juni 2026 — Navigasi Gojek + Perbaikan IPTV Mobile

Arsip ini hanya berisi file-file yang berubah. **Tindih ke folder utama** `sportapp/`
(jangan hapus file lain, data `.sql` aman, tidak perlu migrasi DB baru).

## Isi arsip

```
includes/bottom_nav.php        ← navigasi bawah baru gaya Gojek
assets/css/gojek-nav.css       ← style untuk navigasi baru (warna-warni, FAB tengah)
iptv_proxy.php                 ← proxy server-side untuk stream IPTV (HLS)
index.php                      ← memutar IPTV lewat proxy + dukungan mobile
```

## 1. Menu navigasi gaya Gojek

- Ikon bulat berwarna (Home hijau, Aktivitas biru, Event oranye, Profil ungu-pink)
  meniru tile GoRide/GoFood/GoSend.
- Tombol **Upload** menjadi FAB hijau Gojek di tengah dengan cincin putih
  (efek "floating").
- Highlight halaman aktif: pill kecil di bawah label + ikon sedikit naik & glow.
- Avatar pengguna otomatis tampil di slot **Saya**, lengkap dengan badge notifikasi.
- Aman backward-compatible: kelas lama `.bottom-nav` di-hide otomatis sehingga
  tidak dobel dengan navigasi lain di halaman yang sudah punya custom CSS.

> Tampil di mobile (`<lg`). Di desktop tetap pakai navbar atas, navigasi ini
> otomatis disembunyikan.

## 2. IPTV bisa diputar di HP

Akar masalah sebelumnya:
1. Banyak stream `.m3u8` publik **tidak mengirim header CORS**, sehingga HLS.js di
   mobile browser ditolak oleh browser saat fetch manifest/segment.
2. Beberapa stream masih `http://` (mixed-content) — diblokir Chrome/Safari di
   halaman HTTPS.
3. APK Capacitor sebelumnya **sengaja diblok** sehingga tombol play hilang.

Perbaikan:
- File baru **`iptv_proxy.php`** — proxy HLS server-side:
  - Ambil manifest `.m3u8` dengan cURL (timeout 25 dtk, follow redirect, UA mobile).
  - **Re-write** semua URI segmen (`.ts`, `.aac`, key, sub-playlist) supaya
    juga lewat proxy → satu pintu, selalu HTTPS, selalu CORS-friendly
    (`Access-Control-Allow-Origin: *`).
  - Teruskan header `Range`, `Content-Length`, `Content-Range`, `Accept-Ranges`
    agar seek video tetap jalan.
  - Whitelist sederhana memblokir alamat private (`127.*`, `10.*`, `192.168.*`)
    untuk mencegah SSRF.
- `index.php`:
  - Helper `iptv_proxy_url($url)` membungkus tiap channel jadi
    `/iptv_proxy.php?u=<base64url>` sebelum dikirim ke tombol channel.
  - Filter playlist sekarang menerima **HTTP maupun HTTPS** karena proxy
    menetralkan mixed-content.
  - Blokir APK Capacitor **dihapus** — IPTV kini aktif di:
    Chrome Desktop, Android Chrome, iOS Safari, dan APK.
  - `<video>` dipastikan `playsinline` + `muted` agar autoplay tidak ditolak
    oleh kebijakan mobile.

## Catatan teknis pemasangan

- **PHP**: butuh ekstensi `curl` aktif (cek `phpinfo()`). Sudah default di
  hampir semua distribusi.
- **PostgreSQL**: **tidak ada migrasi tambahan**. Tidak ada perubahan skema
  atau data.
- **Routing**: pastikan `iptv_proxy.php` ada di **root project** (sejajar
  dengan `index.php`). URL yang dipakai di JS adalah `/iptv_proxy.php`.
- Jika web server kamu memakai Nginx dengan `try_files`, tidak perlu rule
  tambahan — file PHP biasa.

## Cara coba cepat

1. Tindih file dari zip ini ke folder project.
2. Buka halaman utama di HP (Chrome Android / Safari iOS).
3. Tap kartu **Video Terbaru → modal IPTV**. Channel pertama harus auto-play.
4. Cek tampilan menu bawah: warna-warni gaya Gojek, FAB hijau di tengah.

Selamat mencoba! 🚀

# REVISI 1 Juni 2026 — Paket Sebagian

Paket ini **hanya berisi file yang direvisi**, bukan seluruh project.
Cara pakai: ekstrak ke root project `sportapp_core/`, **timpa** file yang sama
(struktur folder sudah persis sama dengan project asal).

## Daftar file dalam zip

```
index.php                        (revisi: fix bug pemutar IPTV di HP)
login.php                        (revisi: hero gambar olahraga AI + SFX)
register.php                     (revisi: hero gambar olahraga AI + SFX)
includes/header.php              (revisi: navigasi atas gaya Gojek di mobile + load SFX)
assets/css/gojek-top.css         (BARU — style header atas gaya Gojek)
assets/js/sfx.js                 (BARU — efek suara WebAudio, tanpa file mp3)
assets/img/sport-auth-hero.jpg   (BARU — gambar AI untuk halaman login)
assets/img/sport-auth-hero-2.jpg (BARU — gambar AI untuk halaman daftar)
```

## Rincian perubahan

### 1) Navigasi atas gaya Gojek di mobile  *(includes/header.php + assets/css/gojek-top.css)*
- Di layar < 992px, navbar Bootstrap lama otomatis disembunyikan.
- Diganti header hijau Gojek-style: tombol burger (kiri) + search bar besar di tengah + ikon bell + avatar.
- Di bawah header ada pita pintasan horizontal (chip) seperti GoFood/GoRide:
  Beranda · Lari · Upload · Jajan · Kurir · Tempat · Event · Check-in · Pesan · Islami · Sehat.
- Tombol burger membuka **offcanvas drawer** berisi menu lengkap (termasuk menu admin).
- Desktop tidak berubah, tetap memakai navbar lama.

### 2) Efek suara (SFX)  *(assets/js/sfx.js)*
- Memakai WebAudio API → **tidak butuh file mp3** apapun, langsung jalan di lokal.
- Auto-hook: setiap `<form>` yang di-submit otomatis memutar bunyi sukses.
  Tombol/anchor dengan atribut `data-sfx="tap|success|error|notify|toggle"` juga otomatis.
  Bisa dimatikan per-browser: `SFX.mute()` / `SFX.unmute()` di console.
- Dipasang otomatis di setiap halaman lewat `includes/header.php`,
  plus halaman `login.php` & `register.php` yang punya script sendiri.

### 3) IPTV bisa diputar di HP  *(index.php)*
- **Penyebab sebelumnya:** ada tag `<script>` duplikat (`<script>(function(){<script>(function(){`)
  di blok pemutar IPTV → seluruh IIFE gagal di-parse (SyntaxError) sehingga
  tombol channel tidak pernah memuat stream apapun, baik di HP maupun desktop.
- **Fix #1:** tag `<script>` duplikat dihapus.
- **Fix #2:** logika pemilih engine pemutar diperbaiki — sekarang Android Chrome
  selalu memakai **hls.js**, dan native HLS hanya dipakai di iOS Safari/macOS Safari.
  Sebelumnya `video.canPlayType('application/vnd.apple.mpegurl')` mengembalikan
  `"maybe"` di Android Chrome (truthy) padahal Android Chrome tidak benar-benar
  mampu memutar HLS native — itulah kenapa IPTV blank di HP.
- Stream tetap lewat `/iptv_proxy.php` (sudah ada), aman dari mixed-content & CORS,
  cocok untuk hosting di Render.

### 4) Hero login & daftar pakai gambar olahraga (AI)
- `login.php` → background hero memakai `/assets/img/sport-auth-hero.jpg`
  (silhouette atlet lari + bola) hasil generate AI, dengan overlay gradient
  sky→indigo. Judul diubah jadi "Ayo Olahraga Bareng" + 3 chip fitur
  (Event 2026 · Run Tracker · Sehat).
- `register.php` → background hero memakai `/assets/img/sport-auth-hero-2.jpg`
  (atlet pull-up, basket, lari) hasil generate AI, dengan overlay emerald→indigo.
- Ilustrasi SVG lama tidak dihapus dari project (file `auth-illustration.svg`
  tetap di tempatnya), hanya tidak lagi ditampilkan.

## Database / PostgreSQL

**Tidak ada perubahan skema DB pada revisi ini.**
File `sportapp.sql` yang sudah ada di project lama tetap dipakai apa adanya.
Data tidak dihapus. Tidak ada migrasi baru yang perlu dijalankan untuk paket ini.

## Catatan deploy di Render

- IPTV proxy butuh `curl` extension PHP (sudah aktif default di image PHP Render).
- Pastikan halaman situs sudah HTTPS — proxy `/iptv_proxy.php` akan otomatis
  membungkus stream HTTP supaya tidak mixed-content di browser HP.
- Bila ada channel yang tetap gagal play, sistem **otomatis lompat ke channel
  berikutnya** (sudah ada handler ERROR di hls.js).

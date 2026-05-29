# Revisi 30 Mei 2026

## 1. Live Streaming (index.php)
- Section "Nonton Streaming TV Online" diganti jadi SATU channel live:
  `https://www.youtube.com/channel/UC4R8DWoMoI7CAwX8_LjQHig`
- Embed `youtube-nocookie.com/embed/live_stream?channel=UC4R8DWoMoI7CAwX8_LjQHig`
  dengan `autoplay=1&mute=1` agar lolos blokir autoplay browser.

## 2. Berita = IPTV (index.php)
- Tab "Berita" di modal Video Terbaru sekarang ambil daftar channel dari
  `https://iptv-org.github.io/iptv/categories/news.m3u` (repo iptv-org/iptv).
- Parser m3u sederhana di PHP, hasil di-cache 6 jam di
  `sys_get_temp_dir()/iptv_news_cache.json` agar tidak fetch tiap request.
- Player pakai `<video>` + HLS.js (CDN) untuk memutar stream `.m3u8`.
- Tab "Podcast" tetap pakai YouTube uploads playlist (tidak diubah).

## Catatan
- Tidak ada perubahan skema PostgreSQL. File `sportapp.sql` tidak perlu diubah.
- Server PHP perlu bisa `file_get_contents` ke `https://iptv-org.github.io`
  (allow_url_fopen=On, atau ganti ke cURL bila diblokir firewall lokal).
- Beberapa stream IPTV bisa offline / geo-block — ini wajar untuk konten publik.

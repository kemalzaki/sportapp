# Revisi 29 Mei 2026

File yang diubah (cukup timpa file lama):
- `index.php`
  - Section "Nonton Streaming TV Online": daftar channel diperbarui dengan ID resmi terverifikasi (CNN Indonesia, Metro TV, iNews, BeritaSatu kini benar), ditambah TVRI, JakTV, IDX Channel, NET., RTV, Inspira TV.
  - Embed pindah ke domain `youtube-nocookie.com` + parameter `autoplay=1&mute=1&playsinline=1` (fix utama: browser modern memblokir autoplay tanpa mute → tampak layar hitam).
  - Section "Info & Wawasan" kini punya kartu BARU: **Video Terbaru** (Berita & Podcast) — membuka modal popup berisi tab Berita / Podcast dengan player YouTube embed yang otomatis memutar uploads playlist terbaru dari channel pilihan (CNN, Kompas, Metro, tvOne, Liputan6, Narasi / Deddy Corbuzier, Endgame, Total Politik, dll). Tidak perlu API key, tidak membuka youtube.com.
- `kalistenik.php` — TIDAK DIUBAH KONTEN-NYA (markup sama). Semua asset gambar gerakan diganti.
- `assets/img/kalistenik/*.jpg` — 11 file gambar diganti dengan ilustrasi atlet laki-laki (push_up, pull_up, squat, lunge, plank, dip, burpee, mountain, jumping, leg_raise, hero).

## PostgreSQL
Tidak ada tabel / kolom baru yang dibutuhkan. Fitur Video Terbaru murni front-end (iframe ke uploads playlist YouTube). Tidak perlu migrasi SQL. File `sportapp.sql` di zip lama tetap dipakai apa adanya.

## Cara apply
1. Backup folder lama.
2. Timpa 2 file PHP (`index.php`, `kalistenik.php`).
3. Timpa folder `assets/img/kalistenik/` dengan isi baru.
4. Refresh browser (Ctrl+F5 untuk bypass cache gambar lama).

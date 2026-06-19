# Revisi 19 Juni 2026 — SportApp Core

Hanya berisi file yang direvisi. Salin/timpa langsung ke root project SportApp.

## Daftar Revisi

1. **survival.php**
   - Ditambahkan 4 video YouTube edukasi (Shelter, Api, Mencari Makanan, Navigasi Pohon).
   - Ditambahkan gambar ilustrasi makanan AMAN dan BERBAHAYA (AI-generated, di `assets/img/survival/`).
   - Ditambahkan tombol **Pesan Tour Guide (Camping & Survival)** ke WhatsApp.

2. **assets/img/survival/makanan_aman.jpg** dan **makanan_bahaya.jpg**
   - Gambar AI baru — letakkan di `<root>/assets/img/survival/`.

3. **includes/header.php**
   - Label hijau "AI" di menu **Survival Mode** dihapus (drawer mobile/desktop).
   - `bottomOffset()` kini menyertakan `.gj-nav` → fix **Issue #6**: navigasi PWA bawah tidak hilang lagi saat klik link nav.
   - Handler `visibilitychange` baru → fix **Issue #7**: layout tidak hancur (seperti tanpa CSS) saat user pindah tab lalu kembali ke aplikasi di mobile.

4. **artikel_olahraga.php**
   - Ditambahkan field & tampilan **Manfaat untuk Tubuh** di setiap olahraga (Lari, Badminton, Renang, Hiking, PingPong, Futsal, Biliar).
   - Tombol **Pesan Tour Guide Hiking** (WhatsApp) di kartu Hiking.

5. **kalori_mingguan.php**
   - Fix **Issue #5**: "Total Konsumsi" kini ditampilkan sebagai **net** (Makanan − Terbakar). Saat user input pembakaran kalori via AI Lain, total konsumsi sekarang **berkurang** sesuai ekspektasi. Detail gross & burn ditampilkan di sub-baris kartu.

6. **service-worker.js**
   - Bump `CACHE_VERSION` ke `v6-htmx-2026-06-19` agar PWA klien memuat ulang JS/CSS terbaru (terutama fix Issue #6 & #7).

## PostgreSQL — Tambahan Skema

**Tidak ada perubahan skema baru.** Tabel `kalori_burn_lain` (Issue #5) sudah dibuat otomatis idempotent oleh `kalori_mingguan.php` (CREATE TABLE IF NOT EXISTS). Tabel `survival_qa_saved` juga sudah idempotent di `survival.php`. Anda **tidak perlu** menjalankan SQL tambahan apa pun.

## Catatan

- Issue **#8** tidak ada di permintaan (loncat dari #7 ke #9).
- Nomor WhatsApp tour guide saat ini hard-coded ke `6281234567890` (placeholder). Silakan ganti di `survival.php` (var `$waGuide`) dan `artikel_olahraga.php` (var `$waGuideH`) ke nomor admin sebenarnya.
- Video YouTube di Survival Mode pakai domain `youtube-nocookie.com` untuk privasi pengguna.

# Revisi 18 Juni 2026 — Part M (Lanjutan)

Arsip ini berisi **hanya file yang direvisi** pada sesi ini. Salin/replace ke
folder project sportapp di lokal Anda, lalu refresh browser. **TIDAK** ada
perubahan skema database baru — semua perbaikan ini bekerja dengan tabel yang
sudah ada (`kalori_makanan_log`, `kalori_log`, `kalori_target`,
`flyover_renders`, `run_sessions`). Tidak ada migrasi SQL tambahan yang perlu
dijalankan.

## Daftar file
- `flyover.php`
- `kalori_mingguan.php`
- `includes/header.php`

## Ringkasan perubahan

### 1) flyover.php — sinkronisasi tempo musik & lirik
- Tambah fungsi **`syncLyricsToBeats()`**: setelah lirik di-parse, audio
  di-decode via Web Audio, dihitung envelope RMS per ~23 ms, lalu dideteksi
  onset (spectral-flux sederhana). BPM diestimasi dari inter-onset interval,
  dan **setiap baris lirik di-snap ke onset terdekat** (toleransi adaptif
  ±0.55× beat). Bila onset tidak tersedia, baris di-snap ke grid beat.
- Menjaga jarak minimum antar baris ≥ ½ beat sehingga subtitle tidak
  tumpang-tindih.
- Status di panel lirik kini menampilkan BPM hasil deteksi.

### 2) kalori_mingguan.php — Defisit/Surplus kalori (olahraga)
- Menarik data **kalori terbakar** dari `kalori_log` untuk minggu berjalan
  (badminton, futsal, pingpong, renang).
- Kartu baru **"Defisit / Surplus Kalori Minggu Ini"** dengan rumus
  `Defisit = Target − (Konsumsi − Terbakar)`. Estimasi perubahan berat badan
  ditampilkan (≈ kkal/7700).
- Kartu **Aktivitas Pembakaran Minggu Ini** menampilkan rekap per-jenis
  olahraga (sesi, menit, kkal).
- Chart mingguan ditambah dataset **Terbakar olahraga** dan garis
  **Defisit/Surplus harian** dengan sumbu kanan terpisah.

### 3) kalori_mingguan.php — Nama makanan hasil scan AI
- AI hasil Gemini Vision sekarang **selalu mengisi/menggabungkan** field
  `nama_makanan` (sebelumnya hanya bila kosong). Bila user mengetik nama dan
  AI mengenali nama berbeda, keduanya disimpan: "Nasi padang (Rendang)".
- Field `catatan` otomatis menerima `rincian` AI bila kosong, sehingga di
  tabel riwayat terlihat tafsir AI.

### 4) kalori_mingguan.php — Edit & Klik Foto tidak berfungsi
- Sebelumnya `new bootstrap.Modal(...)` dipanggil sinkron saat
  `bootstrap.bundle.js` (dimuat di `footer.php`) belum tersedia → handler
  Edit dan zoom foto tidak pernah terpasang.
- Inisialisasi modal kini di-defer dengan `window.load` + retry hingga
  `bootstrap` siap, sehingga tombol Edit & klik foto thumbnail kembali
  berfungsi.

### 5) includes/header.php — Skeleton loading mobile tidak nabrak nav atas
- Overlay `#hfPageTransOverlay` kini menghitung offset gabungan `.gt-top`
  + `.gt-chips` (atau `navbar.sticky-top` di desktop) dan menambahkan padding
  8 px supaya skeleton tidak menempel persis di bawah header.
- Padding mobile ditingkatkan & blok skeleton diberi `max-width: 980px` agar
  terbaca rapih di layar besar.

## Catatan database
**Tidak ada SQL baru.** Semua tabel yang dibutuhkan (`kalori_log`,
`kalori_makanan_log`, dst.) sudah dibuat secara idempotent oleh masing-masing
halaman `kalori_*`. Sesuaikan saja file PHP, data lama tetap utuh.

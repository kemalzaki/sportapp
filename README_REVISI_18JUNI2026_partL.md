# Revisi 18 Juni 2026 — Part L (Flyover Studio + Mobile Nav UX)

File yang diubah dalam zip ini:
- `flyover.php`            — UI & JS untuk fitur baru
- `api_run.php`            — endpoint baru `ai_song_lyrics`, dan handler `ai_route_from_image` ditingkatkan (2-tahap)
- `includes/bottom_nav.php` — spinner kecil + top progress bar saat tap menu

## Ringkasan Perubahan

### 1) Audio Trim di Flyover (#1)
Buka **Flyover → Konfigurasi → Musik latar**. Setelah memilih lagu (dari pustaka iTunes atau upload sendiri), bagian **"Potong Audio"** muncul:
- Atur **Mulai (detik)** dan **Akhir (detik)**.
- Klik **Terapkan Trim** → audio di-decode via Web Audio API, dipotong, lalu di-encode ulang sebagai WAV blob. Audio hasil trim langsung dipakai untuk preview/rekam.
- Tombol **reset** mengembalikan audio asli.
- Catatan: trim file remote (iTunes preview) butuh CORS. iTunes preview MP3 mendukung CORS, jadi aman.

### 2) Subtitle Lirik Otomatis pada Video (#2)
- Aktifkan switch **"Aktifkan subtitle lirik di video"**.
- Isi **Judul lagu** (terisi otomatis kalau pilih dari pustaka) + artis opsional.
- Klik **"Ambil Lirik dari Gemini AI"** → server (`api_run.php?_action=ai_song_lyrics`) memanggil Gemini, mengembalikan JSON `lines:[{t:detik, line}]`. Distribusi waktu diestimasi Gemini berdasarkan durasi audio (atau hasil trim).
- Alternatif **lirik manual**: tempel teks (1 baris = 1 subtitle) ATAU format LRC `[mm:ss.xx]baris`. Tanpa timestamp → akan dibagikan rata otomatis sepanjang durasi.
- Saat **preview** muncul subtitle HTML di atas peta; saat **rekam** subtitle digambar ke canvas sehingga ikut tertulis ke video `.webm`. Sinkron dengan `audio.currentTime` (jadi mengikuti vokal lagu).

### 3) Pembacaan Rute Strava Diperbaiki (#3)
Handler `ai_route_from_image` sekarang **2-tahap**:
1. **Tahap 1 — Estimasi koordinat langsung**: Gemini Vision diminta mengembalikan array `[lat,lng]` (15–40 titik) dengan membaca skala/orientasi peta. Validasi bounding-box Indonesia. Jika ≥10 titik valid → langsung dipakai (tidak perlu Nominatim).
2. **Tahap 2 — Fallback landmark + Nominatim** (mekanisme lama, tetap berfungsi).
Hasilnya respons mengandung field `mode: "direct_coords" | "landmark_geocode"` untuk transparansi.

### 4) Loading Spinner di Menu Navigasi Mobile (#4)
File `includes/bottom_nav.php` ditambahi:
- Spinner kecil (`.gj-spin`) di samping label item nav, muncul saat di-tap.
- Top progress bar tipis di atas layar (`#gjTopBar`) sebagai indikator transisi halaman.
- State otomatis di-reset via event `pageshow` (back/forward cache).

### 5) Pustaka Musik Realtime di Flyover (#5)
Bagian **"Cari Musik (Pustaka iTunes)"** di Flyover:
- Pencarian realtime via **iTunes Search API** (`https://itunes.apple.com/search`), **gratis tanpa API key**, mendukung CORS.
- Hasil menampilkan artwork, judul, artis, durasi.
- Klik salah satu lagu → preview 30 detik (mp3) langsung di-load ke pemutar audio, dan judul/artis terisi otomatis untuk pengambilan lirik.

## PostgreSQL — perlu migrasi?
**Tidak ada** kolom/tabel baru yang perlu ditambahkan. Endpoint baru `ai_song_lyrics` murni call AI, tidak menyimpan ke DB.

## Catatan Konfigurasi
- Pastikan env `GEMINI_API_KEY` (atau `GEMINI_API_KEYS`) sudah terisi di `config/env.local.php` (sudah ada dari revisi sebelumnya). Tidak ada secret baru.
- Tidak ada perubahan pada `.sql`/data — semua data tetap utuh sesuai catatan.

## Cara Mengganti File (running lokal)
1. Backup folder lama (opsional).
2. Extract zip ini, timpa: `flyover.php`, `api_run.php`, `includes/bottom_nav.php`.
3. Refresh browser (Ctrl+F5 untuk bypass cache).

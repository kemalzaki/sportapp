# Revisi 18 Juni 2026 (Lanjutan)

File yang direvisi (sebagian saja, sesuai permintaan):

1. **includes/header.php** — Loading spinner kecil ditambahkan pada item
   navigasi samping (offcanvas drawer `#gtDrawer`) di tampilan mobile.
   Spinner muncul sesaat di samping label saat item nav di-klik.

2. **flyover.php**
   - **Trim audio**: input angka detik diganti dengan dua **slider (range)**:
     “Mulai” dan “Akhir”, otomatis menyesuaikan durasi audio.
   - **Lirik tanpa AI**: tombol *“Ambil Lirik dari Gemini AI”* dihapus.
     Diganti dengan **pencarian lirik (mirip pencarian musik)** memakai
     iTunes Search API untuk daftar lagu + **lyrics.ovh** (gratis, tanpa key)
     untuk mengambil teks lirik. Ketika dipilih → lirik otomatis terisi pada
     textbox dan dapat diseleksi sebagai subtitle video.
   - **Auto-detection lirik**: ketika user memilih lagu dari pencarian
     musik (atau ketika audio mulai play), lirik otomatis di-fetch
     (switch *“Auto-ambil lirik tiap kali memilih musik (deteksi otomatis)”*,
     default aktif).
   - **Copyright "© HapFam 2026 • Sport"** di-overlay pada video flyover
     (pojok kiri bawah).
   - **Foto profil HapFam** (lingkaran logo) di-overlay pada video
     (pojok kanan bawah). Gambar di-load dari `/assets/img/hapfam-logo.png`.

3. **artikel_olahraga.php** — 28 URL gambar peralatan (Wikipedia/Wikimedia,
   sebagian 404 / placehold.co) diganti dengan **foto produk hasil generate
   AI** yang disimpan lokal di `assets/img/peralatan/eq00.jpg … eq27.jpg`.

## Aset baru di paket ini

```
assets/img/peralatan/eq00.jpg ... eq27.jpg   # 28 foto peralatan olahraga
assets/img/hapfam-logo.png                   # logo HapFam (watermark video)
```

## PostgreSQL

Tidak ada perubahan skema database untuk revisi ini. Tidak perlu menjalankan
migrasi tambahan. (File `flyover_renders` sudah dibuat otomatis oleh
`flyover.php` saat halaman diakses, sama seperti sebelumnya.)

## Catatan integrasi

- `lyrics.ovh` adalah layanan publik gratis tanpa API key. Jika lagu tidak
  ditemukan, fallback otomatis mencoba lewat hasil pertama iTunes Search.
- Endpoint server `ai_song_lyrics` di `api_run.php` boleh dibiarkan
  (sudah tidak dipanggil lagi dari `flyover.php`).
- Logo `assets/img/hapfam-logo.png` di-load dengan `crossOrigin='anonymous'`
  agar dapat di-draw ke canvas tanpa tainting MediaRecorder.

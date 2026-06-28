# Revisi R24 — 28 Juni 2026

## 1. survival.php — Rekomendasi hutan per kota / lokasi terkini
- Sudah ada tombol **Cari dari Lokasi Saya** (radius 10–100 km, Overpass API).
- BARU: input **"Cari di Kota Ini"** (geocoding via Nominatim) sehingga
  user bisa cari hutan/kawasan lindung per nama kota/kabupaten tanpa harus
  mengaktifkan GPS. Hasil otomatis dipetakan + tombol Rute Google Maps.

## 2. PWA bottom-nav — file CSS hilang
- File `assets/css/gojek-nav.css` sebelumnya **TIDAK ada** di project.
  Akibatnya tombol "+" Upload menumpuk di atas konten tabel (lihat lampiran).
- Dibuat ulang lengkap: fixed bottom, FAB melayang, padding-bottom body
  agar konten tidak tertutup.

## 3. admin/pengeluaran.php — Edit tidak berfungsi
- Penyebab: handler `submit` modal melakukan `fetch()` ke endpoint yang
  merespon 302 redirect (HTML penuh), digabung multipart upload bukti +
  modal.hide() membuat respons kadang tidak menyimpan / tabel tidak refresh.
- Solusi: submit form modal kembali ke **submit normal** (browser ikut
  redirect) sehingga halaman dimuat ulang dengan data terbaru.

## 4. flyover.php — Sinkron Musik AI "User location is not supported"
**Kenapa errornya?**
Google Gemini API memblok akses dari beberapa region (termasuk sebagian
IP Indonesia / hosting). Pesan resmi Google:
`User location is not supported for the API use` (FAILED_PRECONDITION).
Itu **bukan** masalah aplikasi, melainkan pembatasan geografis pada
endpoint generativelanguage.googleapis.com.

**Perbaikan:**
- `includes/ai_gemini.php` sekarang mengembalikan field `code='GEO_BLOCK'`
  pada respons error dan tetap menulis pesan friendly Bahasa Indonesia.
- `flyover.php` mendeteksi `code==='GEO_BLOCK'` (atau pesan friendly)
  lalu **otomatis fallback** ke *beat-sync lokal* (WebAudio onset+BPM)
  tanpa mengganggu user — lirik tetap sinkron meski Gemini tidak tersedia.
- Solusi permanen (opsional): jalankan PHP melalui proxy/VPN ke region yg
  didukung, atau pakai Vertex AI di Cloud Run region us-central1.

## 5. admin/paket_member.php — CRUD Pengaturan Paket Member (BARU)
- File baru di `admin/paket_member.php` dengan UI khusus untuk mengisi
  label paket (🆓 Gratis / ⭐ PRO / 👥 Komunitas) per item menu.
- Mendukung filter posisi (drawer/top/bottom), filter paket, statistik
  jumlah, simpan bulk, dan tombol "Hapus Semua Label".
- Menggunakan kolom `nav_menu.paket` yang sudah ada (auto-migrasi).
- Link ditambahkan di drawer **Pengaturan Lainnya** (`includes/header.php`).
- Label otomatis tampil di samping nama menu (sudah ditangani
  `includes/menu_render.php` sejak R23).

## 6. opini_viral.php — Sumber dari OPINI NETIZEN sosial media
- Sumber sebelumnya: Google News RSS (berita formal).
- Sekarang: agregator opini publik dari **Reddit JSON** (r/indonesia,
  r/indonesia_local, r/indonesians), **Nitter** (mirror X/Twitter
  untuk politik / bisnis / viral), dan **YouTube RSS** channel berita
  populer (Narasi, CNN Indonesia, Kompas TV).
- Twitter/Facebook/Instagram/TikTok TIDAK menyediakan RSS publik resmi
  (semua di-private setelah 2023). Solusi termudah tanpa API berbayar
  adalah mirror Nitter — bila instance di-block, ganti ke instance lain.
- Google News tetap dipakai sebagai **fallback** kategori Politik & Bisnis.

## 7. lacak_faskes.php — Halaman tersendiri (BARU)
- Modul "Puskesmas / Rumah Sakit Terdekat" yang dulu inline di
  `cedera_olahraga.php` dipindahkan ke `lacak_faskes.php`.
- Fitur lengkap: pilih radius 2/5/10/25 km, geolocation, reverse-geocode
  alamat user, OSRM rute, tombol **Rute Google Maps** per item.
- Link di drawer ditambahkan tepat di bawah "Cedera Olahraga & Penanganan".
- `cedera_olahraga.php` kini hanya menampilkan tombol "Buka Halaman
  Lacak Faskes" (kode lama dihapus).

## 8. artikel_olahraga.php — Gambar peralatan basket (Lovable)
- File: `assets/basket_peralatan.jpg` (1024×1024, di-generate Lovable AI).
- Disimpan di folder `assets/` (di root project).
- Direferensikan pada item peralatan **bola basket** di `artikel_olahraga.php`.

---

## File yang ada di ZIP revisi (R24)
- `admin/pengeluaran.php` (fix edit)
- `admin/paket_member.php` (BARU)
- `includes/header.php` (link Lacak Faskes + Paket Member)
- `includes/ai_gemini.php` (field code='GEO_BLOCK')
- `flyover.php` (deteksi GEO_BLOCK + fallback beat-sync)
- `survival.php` (input "Cari di Kota Ini")
- `opini_viral.php` (sumber sosmed)
- `cedera_olahraga.php` (link ke /lacak_faskes.php)
- `lacak_faskes.php` (BARU)
- `artikel_olahraga.php` (gambar basket)
- `assets/basket_peralatan.jpg` (BARU)
- `assets/css/gojek-nav.css` (BARU — sebelumnya hilang)
- `CATATAN_REVISI_R24.md` (file ini)

## PostgreSQL — TIDAK ada tabel baru
- Hanya kolom `nav_menu.paket` (sudah auto-migrasi sejak R23, sekarang
  juga di `admin/paket_member.php`).
- Tabel `opini_viral` tetap (kolom tidak berubah).
- Tidak ada DROP / DELETE data eksisting.

# Revisi 19 Juni 2026 (R2) — SportApp Core

Zip ini hanya berisi **file yang direvisi**. Salin/timpa langsung ke root project SportApp Anda. **Tidak ada perubahan skema PostgreSQL** — Anda **tidak perlu** menjalankan SQL tambahan apa pun.

## Daftar Revisi

### 1. `artikel_olahraga.php`
- **(Item 1)** Setiap olahraga (Lari, Badminton, Renang, Hiking, PingPong, Futsal, Biliar) kini mencantumkan blok baru **"Khasiat & Manfaat Penyembuhan Penyakit"** — daftar penyakit / kondisi spesifik yang terbantu oleh olahraga tersebut (mis. diabetes tipe-2, hipertensi, asma, depresi, demensia, dst).
- **(Item 1)** Setiap olahraga kini mencantumkan blok **"Hormon yang Dikeluarkan"** — daftar hormon/neurotransmitter (endorfin, dopamin, serotonin, BDNF, HGH, kortisol, dst).
- **(Item 2)** Setiap olahraga kini mencantumkan blok **"Mental yang Diasah"** — aspek mental spesifik (disiplin, fokus, sportivitas, resiliensi, ketenangan, dst).
- **(Item 4)** Fitur **"Cari Video <olahraga> di YouTube"** sekarang menampilkan **5 video teratas** dalam grid 2 kolom (sebelumnya hanya 1). Link **"Lihat semua hasil di YouTube"** dan tombol panah ↗ "Buka YouTube" telah **dihapus** sesuai permintaan.

### 2. `user.php`  *(perbaikan link Strava — Item 3)*
- Klik akun Strava pada profil user **tidak lagi** redirect ke pencarian Google. Sekarang **selalu** mengarah ke domain `strava.com` (slugify otomatis bila input berupa nama dengan spasi). Bila ID tidak ditemukan Strava menampilkan 404 yang informatif (jauh lebih baik daripada hasil Google yang kosong).

### 3. `profile.php`  *(Item 3)*
- Form input Strava memperoleh validasi URL yang sama (input apa pun selalu menghasilkan link ke `strava.com`). Ditambahkan teks bantuan: disarankan mengisi **ID numerik Strava** atau **URL lengkap** profil.

### 4. `kalori_mingguan.php`  *(Item 6 — popup "Detail Gizi & Catatan")*
- Layout popup diperbaiki total: CSS `word-break` + `overflow-wrap:anywhere` agar **teks tidak nabrak lagi**.
- Kolom **Catatan** di tabel utama kini dipotong 140 karakter pertama (`…`) dan diberi `max-width` agar tidak merusak baris.
- Bila isi catatan berupa **rincian AI JSON** (mis. `AI: {"nama":"Nasi Goreng","kalori":540,...}`), popup kini menampilkannya sebagai **blok JSON ter-format** (pretty-printed, dapat di-scroll) — bukan satu baris panjang yang nabrak ke luar modal.

## Item yang belum dimasukkan
- **Item 5 — "Tambahkan fitur AI dan pencarian video seperti pada artikel_olahraga.php"**: belum dimasukkan karena tidak jelas halaman target. Mohon konfirmasi halaman (`cedera_olahraga.php`, `kalistenik.php`, `gaya_hidup.php`, atau lainnya) — saya tambahkan di rilis berikutnya.

## PostgreSQL
Tidak ada perubahan skema. Tidak perlu menjalankan SQL apa pun.

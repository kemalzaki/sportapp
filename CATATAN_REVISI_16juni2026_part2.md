# Catatan Revisi — 16 Juni 2026 (Part 2)

File yang direvisi dalam arsip ini:

- `run.php`
- `live_tracking.php`
- `flyover.php`

> ZIP ini hanya berisi 3 file di atas. Letakkan menimpa file lama di root project (`sportapp_core/`). **Database / SQL tidak ada perubahan** — semua kolom & data yang sudah ada tetap dipakai. Tabel `run_routes`, `live_tracking_sessions`, `live_tracking_contacts`, dan `flyover_renders` sudah otomatis dibuat ulang oleh `CREATE TABLE IF NOT EXISTS` di awal setiap halaman (idempotent). Tidak perlu menambah kolom PostgreSQL apa pun.

---

## Daftar perubahan

### 1. `run.php` — Route Builder: lokasi sekarang menampilkan titik & simbol di peta
Tombol <kbd>📍 Lokasi sekarang</kbd> di Route Builder sekarang:
- mengambil GPS, mengisi field koordinat,
- **menambahkan marker hijau bertuliskan "Mulai (Anda)" pada peta**,
- memusatkan peta ke posisi tsb,
- menampilkan akurasi GPS di panel info.

### 2. `run.php` — Import rute dari screenshot (Strava, dll.)
Kartu baru **"Import Rute dari Gambar (screenshot Strava)"** di tab **Route Builder**.

Cara pakai:
1. Upload screenshot lari yang menampilkan garis rute berwarna.
2. Klik **2 titik kalibrasi pada gambar** (mis. start & finish).
3. Tekan tombol **"Mode pilih titik peta"** lalu klik **2 titik yang sama pada peta**.
4. Pilih warna garis rute (Strava oranye, biru, hijau, ungu, atau Auto).
5. Tekan **Ekstrak Rute** — pixel garis terdeteksi, diurutkan greedy nearest-neighbour, lalu dipetakan ke lat/lng via affine 2-titik. Hasil muncul sebagai polyline kuning di peta dan bisa langsung **Simpan Rute** / **Export GPX** memakai panel kiri yang sudah ada.

Catatan: ini deteksi heuristik (bukan OCR penuh) — hasil tergantung kualitas screenshot & warna garis. Naikkan toleransi warna bila piksel cocoknya sedikit.

### 3. `run.php` — Reset di mode "Buat Sendiri" benar-benar mengosongkan peta
Tombol **Reset** di mode Manual sekarang menghapus:
- semua titik manual + marker,
- garis rute aktif (auto/snap),
- marker "Mulai (Anda)" dari lokasi sekarang,
- state `bCurrentRoute` (sehingga tombol Simpan/Export jadi non-aktif kembali),
- isi info builder.

### 4. `run.php` — Heatmaps: live tracking + legend + ketebalan
- **Live tracking lokasi sekarang** (marker biru + lingkaran akurasi) muncul saat tab Heatmap dibuka — peta otomatis ikut posisi Anda agar bisa mengikuti heatmap secara real-time.
- **Legend di pojok kanan bawah** menjelaskan: gradien kepadatan, makna garis merah putus-putus (= jalur padat), titik biru (= Anda live), dan **sumber data: tabel `run_points`**.
- Heatmap dipertebal: `radius 18→28`, `blur 22→18`, `minOpacity 0.35`, gradien penuh biru→hijau→kuning→merah supaya garis kepadatan benar-benar terlihat.
- Tambahan polyline merah putus-putus (dashed) tipis sebagai penanda visual "garis heatmap".

### 5. `flyover.php` — Variasi gaya peta + hapus syarat ≥5 titik
- Pilihan gaya peta bertambah jadi 10 preset: OSM, Carto Voyager / Light / Dark, Esri Satellite, OpenTopoMap, Stamen Terrain & Watercolor, CyclOSM, MapLibre Demotiles.
- Pembatasan "Sesi tidak memiliki cukup titik (<5)" **dihapus**. Sekarang sesi dengan 1 titik pun bisa dibuat video (dengan catatan flyover akan jadi statis di titik tsb). Pesan informatif tetap ditampilkan bila jumlah titik sedikit.

### 6. `live_tracking.php` — Preloader tidak lagi menghalangi halaman saat "Mulai & buat tautan"
Penyebab: form global handler di `includes/footer.php` menampilkan preloader navigasi pada setiap `submit`, kecuali form punya atribut `data-ajax`. Form `frmStart` & `frmContact` sekarang menggunakan `data-ajax`, plus tombol disabled + spinner mini selama request agar feedback tetap jelas tanpa overlay yang menutup seluruh halaman.

### 7. `live_tracking.php` — "Sesi Sebelumnya" dihapus
Kartu tabel "Sesi Sebelumnya" beserta query/render-nya dihapus dari halaman (loop `$mine` tidak lagi dirender).

### 8. `run.php` & `live_tracking.php` — GPS tetap mengirim walau layar HP mati
- `run.php` sebelumnya sudah memiliki Wake Lock + silent audio loop + persistent Service Worker notification. Tidak diubah.
- `live_tracking.php` sekarang menambahkan: `navigator.wakeLock.request('screen')`, oscillator audio mute (~0.0001 gain) untuk mencegah OS men-suspend tab, re-acquire wake lock + restart `watchPosition` ketika halaman kembali `visible`, dan **buffer pengiriman titik GPS** (titik yang gagal dikirim disimpan & retry setiap 5 detik) sehingga tidak hilang saat sinyal goyang.

> **Penting untuk pengujian di local:** browser desktop biasa **akan tetap mem-pause JavaScript** saat tab benar-benar tidak terlihat (mis. minimize jendela atau lock screen di laptop). Wake Lock + silent audio hanya mengurangi throttling, tidak menghilangkan suspensi total. Untuk tracking yang 100% jalan walau layar mati, gunakan versi APK Capacitor + plugin `@capacitor-community/background-geolocation` (sudah dijelaskan di catatan onboarding `run.php`).

---

## Perubahan PostgreSQL yang dibutuhkan?

**Tidak ada.** Semua tabel yang dipakai (`run_sessions`, `run_points`, `run_routes`, `live_tracking_sessions`, `live_tracking_contacts`, `flyover_renders`) sudah ada/diciptakan otomatis. Tidak ada kolom baru yang ditambah, tidak ada data yang dihapus.

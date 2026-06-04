# Revisi 4 Juni 2026 v2

File yang diubah (sudah ada di zip ini):

## 1. `includes/footer.php` — Posting feed & story di `index.php` gagal submit
- AJAX submit kini menampilkan **alert error** bila respons non-OK (mis. CSRF salah, file terlalu besar/413, rate limit/429), bukan diam-diam.
- Submit otomatis **menunggu kompresi gambar** selesai (penyebab "gagal submit" saat foto besar dipilih lalu langsung klik Posting — form ter-submit dengan file lama/kosong).
- Modal **selalu ditutup** setelah respons sukses, walau `softRefresh` gagal.
- `bootstrap.Modal.getOrCreateInstance` dipakai supaya modal tetap ter-close meskipun belum pernah ada instance Bootstrap.

## 2. `run.php` — GPS tidak akurat & terhenti saat layar mati / pindah halaman
- Filter GPS baru (anti rute kacau / acak-acakan):
  1. Titik pertama wajib akurasi ≤ 100 m.
  2. Titik berikutnya wajib akurasi ≤ 35 m.
  3. Tolak lompatan > 150 m antar tick (glitch / pindah cell tower).
  4. Tolak kecepatan > 10 m/s (≈ 36 km/jam) untuk lari.
  5. Tolak gerakan < 5 m (drift GPS saat diam → distance tidak bertambah sendiri).
- Setiap titik kini punya `t` (timestamp) untuk validasi kecepatan.
- **Buffer + retry** pengiriman titik ke `/api_run.php` (jika offline, titik tidak hilang; otomatis di-flush tiap 5 detik).
- Saat tab kembali visible: re-acquire Wake Lock, paksa baca posisi sekali, **restart watchPosition** (stream yang ter-throttle di bg sering tidak fresh), lalu flush buffer.
- Tambah info bahwa untuk tracking **layar mati / pindah halaman** seperti Strava perlu APK Capacitor + plugin `@capacitor-community/background-geolocation`. Browser PWA biasa akan dihentikan OS saat layar mati — itu batasan platform, bukan bug.

## Catatan database
- Tidak ada perubahan skema PostgreSQL baru pada revisi ini.
- Tabel yang dipakai (`posts`, `post_likes`, `post_comments`, `run_sessions`, `rate_limit`) sudah ada.

## Catatan opsional (untuk background tracking penuh)
Bila ingin tracking benar-benar di background (layar mati, pindah halaman) tanpa Strava-style limitation:
```bash
npm install @capacitor-community/background-geolocation
npx cap sync
```
Lalu panggil `BackgroundGeolocation.addWatcher(...)` dari layer Capacitor; web view PHP tetap dipakai untuk UI.

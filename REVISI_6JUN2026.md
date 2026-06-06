# Revisi 6 Juni 2026 — sportapp

Zip ini berisi **hanya file yang direvisi**. Letakkan file pada path yang sama
(timpa file lama). Tidak ada data yang dihapus dari database.

## Daftar Perubahan

1. **upload.php** — Input *Durasi, Jarak, Pace, Kalori* sekarang memakai `<datalist>` (saran pilihan cepat) namun tetap boleh diketik manual.
2. **assets/icon-192.png + assets/icon-512.png + splash.php + manifest.php** — Logo & splash screen baru (gradient gelap + lingkaran cahaya + ring animasi, logo PNG modern bertema lari + hati).
3. **.htaccess** — Mengaktifkan gzip & cache 7-30 hari pada CSS/JS/gambar/font supaya load halaman lebih cepat (transisi antar halaman tidak re-download asset besar).
4. **admin/members.php** — Tambah kolom **Koordinator Penghubung** di samping kolom *PIC Admin*. CRUD via dropdown inline. Auto-migrasi kolom `users.koordinator_id`.
5. **profile.php** — Tombol baru **"Lihat tampilan sebagai Member lain"** (modal dengan search) yang mengarah ke `/user.php?id=…`.
6. **index.php** — Section baru **"Panduan Olahraga & Teknik (Video)"** berisi 4 video YouTube `iframe` (lazy-load) di bawah card Artikel Olahraga & Teknik.
7. **includes/header.php + run.php** — Menu **"Lari"** diganti **"Tracking Jalur"** (judul halaman jadi *Tracking Jalur / Rute Realtime*).
8. **admin/event.php** — Tambah tombol **Edit Event** + modal CRUD penuh (termasuk tanggal & jam mulai/selesai).
9. **monitoring.php** — Card baru **"Rekomendasi Kesehatan"** otomatis berdasarkan: pace trend, kalori per minggu, tren total kehadiran, tren performa jogging, dan VO₂.
10. **islami.php** — Section **Kompas Kiblat** dihapus seluruhnya (HTML + JS).

## SQL yang perlu dijalankan (PostgreSQL)

Sebagian besar migrasi sudah otomatis di kode (idempotent `IF NOT EXISTS`).
Jika ingin menjalankan manual:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS koordinator_id INTEGER
  REFERENCES users(id) ON DELETE SET NULL;
```

Tidak ada tabel/kolom lain yang ditambah pada revisi ini.

## Catatan

* Video YouTube di `index.php` dapat ditambah/ganti pada array `$panduanVideos`.
* Logo PNG di `assets/icon-192.png` & `assets/icon-512.png` di-generate ulang
  (siluet pelari + hati, gradient biru→ungu). Hapus cache PWA browser/Android
  agar logo baru langsung tampil.
* `.htaccess` hanya efektif jika server pakai Apache. Untuk nginx, tambahkan
  setting `gzip on;` + `expires 7d;` setara di konfigurasi server.

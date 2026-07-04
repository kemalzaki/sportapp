# Revisi R8 — Juli 2026 (KawanKeringat / sportapp)

Berisi HANYA file yang direvisi. Ekstrak ke atas project existing untuk overwrite. Data lama tidak dihapus.

## Daftar Revisi

### 1. Akses ditolak selain superadmin (6 halaman)
- `admin/jenis.php` — Jenis Olahraga
- `admin/referal.php` — Kode Referal Pendaftaran
- `admin/lacak.php` — Lacak HP Member
- `admin/paket_pesanan.php` — Pesanan Paket Member
- `admin/paket_member.php` — Pesanan Paket Member (varian pengaturan)
- `admin/komunitas.php` — Komunitas Organize
- `admin/komunitas_data.php` — Komunitas Organize (data)
- `admin/sistem.php` — Pengaturan Lainnya (Cek Sistem)

Semua kini `require_role(['superadmin'])`. Selain superadmin: HTTP 403 + "Akses ditolak."

### 2. Tafsir Ibnu Katsir di `quran_surah.php`
- Sumber utama: `https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir/id-tafsir-ibn-kathir/{surah}/{ayah}.json`
- Fallback: `https://api.quran.com/api/v4/tafsirs/169/by_ayah/{surah}:{ayah}` (id 169 = Ibnu Katsir - Indonesia)
- Label "Sumber: …" ditampilkan kecil di bawah teks tafsir.

### 3. `profile.php` — Pertemananku dapat tanggal terakhir ketemu
- Kolom baru `tanggal_terakhir_ketemu DATE` (di-migrate otomatis + di SQL R8).
- Form add/edit + kolom tabel baru "Terakhir Ketemu" dengan label relatif ("14 hari lalu") dan warna:
  - Hijau ≤ 60 hari, kuning 61–180 hari, merah > 180 hari.

### 4. Drawer navigation — hilangkan label Komunitas saat user PRO
- `includes/menu_render.php`: jika `paket_user() === 'pro'` dan item menu memiliki paket `pro,komunitas`, badge "👥 Komunitas" disembunyikan (badge ⭐ PRO tetap tampil).

### 5. Notifikasi per komunitas
- Kolom baru `notifications.komunitas_id` (nullable + index).
- `includes/notifications.php`:
  - `notify()` otomatis mengisi `komunitas_id` dari komunitas penerima.
  - `notify_all_komunitas($kid, …)` — helper baru untuk kirim satu komunitas saja.
  - `notify_all()` legacy tetap ada (untuk broadcast global), tapi tetap merekam komunitas asal.
- `api_notif_list.php` dan `api_notif_poll.php`: memfilter agar user hanya melihat notifikasi milik komunitasnya + broadcast tanpa komunitas.
- Backfill di SQL: notifikasi lama otomatis di-set komunitas_id-nya dari `users.komunitas_id`.

### 6. Hero slideshow 5 gambar + fade (login.php & register.php)
- 10 gambar disimpan di `assets/img/`:
  - Login: `sport-auth-hero1.jpg`, `sport-auth-hero2.jpg`, `sport-auth-hero3.jpg`, `sport-auth-hero4.jpg`, `sport-auth-hero5.jpg`
  - Register: `sport-auth-hero-2.jpg`, `sport-auth-hero-3.jpg`, `sport-auth-hero-4.jpg`, `sport-auth-hero-5.jpg`, `sport-auth-hero-6.jpg`
- CSS keyframes `lgFade` — total siklus 25 detik, 5 slide × 5 detik, opacity fade 1.6s.
- Semua foto dokumenter Indonesia, natural, ada wanita berkerudung (rapih & beretika), tidak terlihat AI.

### 7. Popup "Tambahkan Pintasan ke HP kamu"
- `index.php` dan `login.php`: `alert(...)` diganti Bootstrap modal `#pwaInstallModal`.
- Berisi langkah-langkah untuk Android (Chrome/Edge) dan iOS (Safari), header gradient, ikon, tombol "Mengerti".
- **URL website tidak ditampilkan.**

## Perubahan PostgreSQL yang perlu dijalankan

Hanya SATU file baru: **`REVISI_JULI_2026_R8.sql`** (idempotent, tidak menghapus data).

```bash
psql -U <user> -d sportapp -f REVISI_JULI_2026_R8.sql
```

Menambahkan:
- `pertemanan.tanggal_terakhir_ketemu DATE`
- `notifications.komunitas_id INTEGER` + index `notif_kom_idx`
- Backfill `notifications.komunitas_id` dari `users.komunitas_id`

Catatan: Kalau kamu lupa jalankan SQL, aplikasi tetap jalan — kolom di-ALTER otomatis idempotent di runtime (`ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...`). SQL formal disediakan untuk clean install/staging.

## Cara Menggabungkan ke Project

1. Ekstrak zip ini ke root project (overwrite).
2. Jalankan `REVISI_JULI_2026_R8.sql`.
3. Refresh browser (Ctrl+Shift+R) supaya CSS hero baru kebaca.

Tidak ada file/data yang dihapus.

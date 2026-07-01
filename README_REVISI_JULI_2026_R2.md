# REVISI JULI 2026 — R2 (partial revision package)

Arsip ini berisi HANYA file yang berubah. Ekstrak lalu **timpa** file dengan
nama & lokasi yang sama di project `sportapp_core` Anda.

## Isi Zip
```
paket_upgrade.php                 — item 2 (bayar via WhatsApp)
profile.php                       — item 4 (auto refresh setelah simpan tema)
tempat_list.php                   — item 5 (CRUD Survei Tempat)
includes/header.php               — item 1, 3, 7
includes/bottom_nav.php           — item 6 (bottom nav tetap tampil di mobile)
admin/sistem.php                  — item 8 (peringatan batas / limit)
REVISI_JULI_2026_R2.sql           — migrasi PostgreSQL tambahan
```

## Mapping Perubahan

| # | Poin                                                         | File yang berubah            |
|---|--------------------------------------------------------------|------------------------------|
| 1 | Label paket di menu bergantung paket user                    | `includes/header.php`        |
| 2 | Bayar Midtrans → redirect WhatsApp (kirim data pesanan)      | `paket_upgrade.php`          |
| 3 | "Tracking Jalur" (run.php) sekarang **Komunitas**            | `includes/header.php`        |
| 4 | Ganti Tema Warna → **otomatis refresh** (tidak manual)       | `profile.php`                |
| 5 | Fitur CRUD **"Coming Soon: Survei Tempat"** di tempat_list   | `tempat_list.php` + SQL      |
| 6 | Bottom nav mobile **tetap tampil** saat pindah halaman       | `includes/bottom_nav.php`    |
| 7 | Hapus link *Pengaturan Paket Member* & *Navigasi Menu (CMS)* | `includes/header.php`        |
| 8 | Peringatan batas Server / DB / ImageKit di Cek Sistem        | `admin/sistem.php`           |

## PostgreSQL

Jalankan `REVISI_JULI_2026_R2.sql` sekali. Isinya:
- `CREATE TABLE tempat_survei` (idempotent, `IF NOT EXISTS`) — untuk fitur no. 5.
- Sanity check tabel `paket_pesanan` (tetap dipakai; status baru `menunggu_wa`
  disimpan sebagai VARCHAR — tidak butuh ALTER TYPE).
- **Tidak ada data yang dihapus.** Kolom lama Midtrans (`snap_token`,
  `midtrans_status`, dst.) dibiarkan untuk riwayat.

## ENV yang perlu ditambahkan (opsional)

| Variable | Default | Fungsi |
|----------|---------|--------|
| `WA_ADMIN_NUMBER`        | `6281386369207` | Nomor WhatsApp tujuan bayar paket |
| `SYS_DISK_LIMIT_GB`      | `20`  | Batas disk server untuk halaman Cek Sistem |
| `SYS_DB_LIMIT_MB`        | `500` | Batas ukuran DB PostgreSQL |
| `SYS_IMAGEKIT_LIMIT_GB`  | `20`  | Batas storage/bandwidth ImageKit |
| `SYS_WARN_PERCENT`       | `80`  | Ambang warning (%) |
| `SYS_DANGER_PERCENT`     | `95`  | Ambang danger (%) |

Untuk membaca kuota ImageKit otomatis, pastikan `IMAGEKIT_PRIVATE_KEY` sudah
di-set (biasanya sudah ada di `config/imagekit.php` / `.env`).

## Catatan

- **Item 6 (bottom nav mobile)** menggunakan **MPA View Transitions API** —
  didukung Chrome/Edge/Safari terbaru. Di browser lama, navigasi tetap
  berjalan normal (tanpa smooth-transition), fungsi tidak terpengaruh.
- **Item 8**: bila `admin/sistem.php` versi lama Anda ingin dipertahankan,
  salin saja blok di antara komentar `BEGIN — Blok "Peringatan Batas / Limit"`
  hingga `END` ke file lama Anda.
- **Item 2**: seluruh alur callback Midtrans (`ajax=create_snap`, `confirm_payment`,
  Snap.js) sudah **dihapus**. Aktivasi paket sekarang **manual oleh admin**
  setelah pembayaran dikonfirmasi via WhatsApp.
- **Admin `paket_member.php` & `menu.php`**: hanya link di drawer yang dihapus
  (sesuai permintaan). File PHP-nya, bila ada di folder `admin/`, tidak
  disertakan di zip ini — bisa Anda hapus sendiri secara manual atau biarkan
  (tidak akan diakses karena link sudah hilang).

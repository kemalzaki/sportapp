# Catatan Revisi SportApp Core — 6 Juni 2026

Arsip ini hanya berisi **file yang direvisi**. Tempel ke folder project
sesuai struktur (replace file lama dengan path yang sama).

## Daftar file & isi revisi

| # | File | Perubahan |
|---|------|-----------|
| 1 | `admin/members.php` | Daftar dropdown **Koordinator Penghubung** sekarang mengambil **semua user** (tidak dibatasi role `admin`/`member`), sehingga pilihan tidak hanya Alya/Umy/Devi/Yuni/Medew. |
| 2 | `profile.php` | Modal "Lihat tampilan sebagai Member lain" kini menampilkan opsi **Lihat profil saya sendiri** di paling atas (highlight). |
| 3 | `includes/header.php`, `index.php` | **Check-in via barcode dihapus**: hilang dari chip nav atas, drawer, navbar desktop, dropdown Admin, dan kartu QR Check-in + modal QR di `index.php`. |
| 4 | `artikel_olahraga.php` | Hanya menampilkan **6 artikel**: Lari, Bulu_tangkis (Badminton), Renang, Hiking, Tenis_meja (PingPong), Futsal. |
| 5 | `index.php` | Tambah menu **Panduan Olahraga** (klik → popup berisi 7 video teknik: Lari, Badminton, Renang, Hiking, PingPong, Futsal, Biliard). |
| 6 | `index.php` | Kotak **Artikel Olahraga & Teknik** di section "Belajar & tetap update setiap hari" sekarang berwarna **putih** (ikon biru), seragam dengan kartu lain (sebelumnya kuning). |
| 7 | `kalistenik.php` | Tiap modal gerakan kini **langsung memutar video YouTube embed** (autoplay) dengan link spesifik per gerakan. Tombol **"Cari Video Tutorial" dihapus**. |
| 8 | `index.php` | Tambah dua menu lagi: **Paket Pemanasan Olahraga** & **Paket Pendinginan Olahraga**, masing-masing klik → popup video YouTube. |

## Database PostgreSQL

**Tidak ada migrasi baru** yang wajib dijalankan untuk revisi ini.
Kolom `users.koordinator_id` yang dipakai item 1 sudah otomatis dibuat
secara idempotent di awal `admin/members.php` (baris `ALTER TABLE users
ADD COLUMN IF NOT EXISTS koordinator_id ...`).

Data SQL di arsip awal tidak diubah / tidak dihapus.

## Cara apply

1. Backup folder project lama Anda.
2. Ekstrak `sportapp_core_revisi_6jun2026.zip`.
3. Copy seluruh isi (mempertahankan struktur folder `admin/`, `includes/`)
   menimpa file dengan nama yang sama di project lama.
4. Reload halaman — tidak perlu restart database.

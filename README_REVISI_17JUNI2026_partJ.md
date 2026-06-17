# Revisi Part J — 17 Juni 2026

Arsip ini berisi **hanya file yang direvisi**. Salin/timpa ke folder root project Anda dengan struktur path yang sama.

## File yang direvisi

```
kalori_mingguan.php
run.php
includes/footer.php
```

> Catatan: `api_run.php` **tidak diubah** — backend endpoint `route_update` sudah ada dan benar. Bug "tidak bisa disimpan" diperbaiki di sisi frontend `run.php` (handler submit yang lebih robust + fallback click).

---

## Ringkasan perubahan

### 1. `kalori_mingguan.php` — AI baca teks + gambar
- Prompt Gemini diubah agar AI **eksplisit membaca dua input**: (a) teks `nama_makanan` yang diketik user, dan (b) foto. Jika keduanya beda, foto diprioritaskan tapi keduanya disebut di rincian.

### 2. `kalori_mingguan.php` — Upload foto ke ImageKit
- Sebelumnya foto disimpan di `/uploads/kalori/`. Sekarang menggunakan **ImageKit** sama seperti `upload.php` (memakai `config/imagekit.php`).
- File lokal hanya sementara di `sys_get_temp_dir()` untuk dipakai AI, lalu **dihapus** setelah upload sukses.
- Folder ImageKit: `/sportapp/kalori/<Bulan_Tahun>/`.
- Kolom baru `foto_file_id` ditambah otomatis lewat `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (idempotent, jalan di runtime saat halaman dibuka). **Tidak ada migration manual yang wajib dijalankan** — tapi jika Anda ingin menjalankannya manual lewat psql, lihat bagian PostgreSQL di bawah.
- Saat entri dihapus, file foto juga otomatis dihapus dari ImageKit.

### 3. `kalori_mingguan.php` — Foto di riwayat bisa di-klik & zoom
- Thumbnail foto pada tabel "Riwayat Minggu Ini" punya kursor `zoom-in`. Klik → modal Bootstrap muncul menampilkan foto besar (max 80vh).

### 4. `kalori_mingguan.php` + `includes/footer.php` — Loading di tombol, bukan preloader
- Handler global di `footer.php` sudah menampilkan spinner kecil di tombol saat submit.
- Fix: **preloader fullscreen tidak lagi muncul** saat form submit memicu `beforeunload`. Sekarang hanya tombol yang berubah jadi "Menyimpan…" dengan spinner.

### 5. `run.php` — Edit rute tersimpan bisa disimpan
- Handler submit modal `routeEditForm` ditulis ulang lebih robust:
  - Try/catch + parse JSON manual (kalau server kirim error HTML, error dilaporkan).
  - Spinner di tombol "Simpan Perubahan" selama proses.
  - **Fallback click handler** pada tombol submit — kalau event `submit` terhalang script lain, tombol tetap merespons.
- Backend (`api_run.php` action `route_update`) tidak diubah.

---

## PostgreSQL — yang perlu ditambahkan

Cukup satu kolom baru (idempotent, akan otomatis dibuat saat `kalori_mingguan.php` pertama kali dimuat di server baru). Bila Anda ingin menjalankan manual:

```sql
ALTER TABLE kalori_makanan_log
  ADD COLUMN IF NOT EXISTS foto_file_id TEXT;
```

Tidak ada perubahan skema lain. Data lama **tidak dihapus** — kolom baru hanya berisi `NULL` untuk baris lama (foto lama tetap bisa ditampilkan via `foto_url`).

---

## Cara apply (local PHP + PostgreSQL)

1. Backup folder project Anda.
2. Ekstrak zip ini ke folder yang sama (timpa file lama).
   ```
   unzip -o sportapp_revisi_17juni2026_partJ.zip -d /path/ke/sportapp
   ```
3. (Opsional) jalankan SQL di atas lewat psql.
4. Refresh browser. Selesai.

## Yang TIDAK perlu disentuh

- `vendor/`, `assets/`, `config/` — tetap.
- `config/imagekit.php` — kredensial ImageKit yang sudah ada terus dipakai.
- `GEMINI_API_KEY` / `GEMINI_API_KEYS` — env var seperti biasa.

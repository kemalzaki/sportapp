# Revisi KawanKeringat (sebagian)

Zip ini berisi file-file yang **direvisi** dari `sportapp_core`. Salin file
di bawah ke folder project Anda (mempertahankan struktur folder).

## Daftar file direvisi

```
includes/header.php           # Logo brand "HapFam SportApp" → "KawanKeringat"
includes/footer.php           # tag notifikasi hapfam- → kawankeringat-
includes/bottom_nav.php       # hapus class d-lg-none agar muncul di desktop
includes/invoice_email.php    # default email no-reply@hapfam.local → kawankeringat.local
admin/biaya.php               # default email no-reply (admin) → kawankeringat.local
assets/css/gojek-top.css      # tampilan mobile dipakai di SEMUA ukuran layar
index.php                     # tag notifikasi PWA hapfam- → kawankeringat-
profile.php                   # fallback host hapfam.app → kawankeringat.app
flyover.php                   # identifier HAPFAM_LOGO + path /assets/img/kawankeringat-logo.png
```

## Ringkasan perubahan

1. **Desktop = Mobile**
   - `assets/css/gojek-top.css` diubah agar:
     - Navbar Bootstrap lama (`nav.navbar.sticky-top`) **disembunyikan
       di semua ukuran layar**.
     - Top bar mobile (`.gt-top`), chips (`.gt-chips`), dan drawer
       (`#gtDrawer`) **selalu tampil** (tidak lagi mobile-only).
   - `includes/bottom_nav.php` — class `d-lg-none` dihapus dan CSS
     `.gj-nav { display:flex !important }` ditambahkan, sehingga bottom
     nav juga muncul di desktop.

2. **Penggantian merek**
   - Semua tulisan "HapFam" / "HapFam SportApp" yang terlihat user
     diganti jadi **"KawanKeringat"** (judul navbar, drawer, badge merk
     berwarna, dsb).
   - Identifier internal yang berisi `hapfam`/`HAPFAM` (tag notifikasi
     PWA, host fallback, default email no-reply, variabel logo)
     juga diganti agar konsisten.

## Database / PostgreSQL

**Tidak ada perubahan skema database.** File `sportapp.sql` lama tetap
dipakai apa adanya (data tidak dihapus). Tidak ada tabel atau kolom baru
yang perlu Anda tambahkan untuk revisi ini.

> Catatan: nama database default (`hapfam_sportapp`) di
> `config/db.php` **tidak diubah** karena ini menyangkut kredensial PG
> aktual di server Anda. Kalau ingin mengganti, ubah manual di
> `config/db.php` atau via environment variable `DB_NAME`, `DB_USER`,
> `DB_HOST`.

## Cara pakai (lokal)

1. Backup folder project Anda.
2. Ekstrak zip ini, lalu **timpa** file dengan path yang sama di folder
   project (`includes/`, `assets/css/`, `admin/`, dan beberapa file
   root).
3. Refresh halaman di browser (Ctrl+F5) untuk memastikan CSS lama tidak
   ter-cache. File CSS sudah pakai parameter `?v=4jun2026` dari header,
   namun bersihkan service worker kalau perlu (`SportApp Service Worker`
   di DevTools → Application).
4. Tidak perlu menjalankan migrasi PostgreSQL apa pun.

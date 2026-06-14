# Catatan Revisi — 14 Juni 2026

File yang diubah / ditambahkan:

1. `index.php`
   - Kartu **Member Aktif** dan **Member Tidak Aktif** dipindah **ke atas** (di atas grid statistik Total Sesi / Total Hadir / Member / Online).

2. `includes/header.php`
   - Menu **Tempat, Pesan, Bookmark, Islami** dipindah dari bagian paling bawah drawer mobile, sekarang berada **tepat di bawah grup "Info dan Wawasan"** (tetap di luar grup, bukan submenu).
   - Item **Kesehatan** di dalam grup "Info dan Wawasan" diganti namanya menjadi **"Penyakit Umum dan Obat Herbal"** (target tetap `kesehatan.php`).
   - Ditambah item baru **"Hidup Sehat"** di dalam grup "Info dan Wawasan", **tepat di bawah IPTV** (target `hidup_sehat.php`).

3. `admin/members.php`
   - **CRUD status Aktif / Nonaktif** ditambahkan: kolom **Aktif** baru pada tabel member, klik tombol untuk toggle.
   - Auto-create akun dummy untuk **Alya, Devi, Medew, Yuni, Umy** dihapus.
   - Kolom **Koordinator Penghubung** dihapus dari tampilan & handler.
   - Migration idempotent: kolom `aktif BOOLEAN DEFAULT TRUE` dan `nonaktif_catatan TEXT` ditambahkan otomatis bila belum ada.

4. `riwayat.php`
   - Tombol **Share WA** sekarang membuka `https://wa.me/?text=...` dengan fallback `location.href` untuk browser mobile yang memblok `window.open`.

5. `hidup_sehat.php` *(baru)*
   - Halaman tips hidup sehat: makanan yang dihindari, saran pola makan, jadwal, dan tips praktis.

6. `includes/footer.php`
   - Ditambah skrip **instant navigation**: link di-prefetch otomatis saat hover/touch, dan progress-bar tipis muncul saat klik link. Memberi kesan aplikasi mobile native — perpindahan halaman terasa instan, tidak menunggu loading.

---

## Catatan PostgreSQL

Tidak ada migration SQL manual yang **wajib** dijalankan. Tabel `users` diperbarui otomatis (idempotent `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`) saat `admin/members.php` pertama kali dibuka oleh admin:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS nonaktif_catatan TEXT;
```

Jika di DB lokal Anda kolom `aktif` sudah bertipe **SMALLINT** (0/1), handler toggle sudah fallback otomatis ke nilai integer. Bila ingin disamakan jadi BOOLEAN, jalankan SEKALI:

```sql
ALTER TABLE users
  ALTER COLUMN aktif DROP DEFAULT,
  ALTER COLUMN aktif TYPE BOOLEAN
    USING (CASE WHEN aktif::text IN ('1','t','true','y','yes') THEN TRUE ELSE FALSE END),
  ALTER COLUMN aktif SET DEFAULT TRUE,
  ALTER COLUMN aktif SET NOT NULL;
```

Kolom lama `koordinator_id` **tidak dihapus** dari database (datanya disimpan, hanya tidak ditampilkan). Bila ingin dihapus permanen:

```sql
ALTER TABLE users DROP COLUMN IF EXISTS koordinator_id;
```

---

## Cara pasang

Replace file berikut di project lokal Anda (struktur direktori tetap sama):

```
index.php
includes/header.php
includes/footer.php
admin/members.php
riwayat.php
hidup_sehat.php            ← file baru, taruh di root
CATATAN_REVISI_14juni2026.md
```

Halaman lain di project tidak diubah.

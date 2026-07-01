# Revisi 2 Juli 2026 — Ringkasan

Zip ini berisi **hanya file yang direvisi** (tidak semua halaman aplikasi).
Ekstrak lalu **timpa** file yang bernama sama di project. Struktur folder sudah
mengikuti struktur project (`includes/`, `admin/`, root).

Stack tetap **PHP + PostgreSQL**, tanpa perubahan ke React.

---

## Isi zip

```
includes/auth.php              # #2 CSRF popup + helper csrf_valid()
includes/header.php            # nav baru: Pesanan Paket Member, Usulan Tempat
login.php                      # #2 #5 #6  (aktif filter, privasi popup)
privasi.php                    # #6  mode ?embed=1 utk modal
tempat_list.php                # #3  section Survei dipindah ke paling atas + kolom dilebarkan
paket_upgrade.php              # (unchanged; disertakan sbg referensi)
admin/paket_pesanan.php        # #1  BARU — riwayat pesanan paket + ubah status
admin/tempat_survei.php        # #3  BARU — admin melihat usulan tempat
admin/sistem.php               # #4  BARU — Cek Sistem + Detail size semua tabel
```

---

## Detail per revisi

### 1. Riwayat Pesanan Paket di Admin → Member Organize
- File baru: `admin/paket_pesanan.php`.
- Menu drawer: **Admin → Member Organize → Pesanan Paket Member** (sudah
  ditambahkan di `includes/header.php`).
- Admin bisa melihat semua pesanan dari `paket_pesanan` (dibuat oleh
  `paket_upgrade.php`) dan mengubah status:
  `menunggu_wa`, `pending`, `paid`, `ditolak`, `dibatalkan`.
- Bila diubah ke **paid**, otomatis:
  - `paid_at` diisi
  - kolom `users.paket` di-update ke paket yang dipesan.

### 2. Perbaikan error "CSRF token invalid" saat login
- Root cause: kalau halaman login dibuka lama / dua tab / cookie session
  expired, POST mengirim token lama sehingga `csrf_check()` mati (`die`) polos.
- Perbaikan di `includes/auth.php`:
  - `csrf_check()` sekarang menampilkan **popup HTML rapi** dengan pilihan:
    - **Muat Ulang Halaman** (`location.reload()`)
    - **Kembali ke Halaman Login**
  - Untuk request AJAX, mengembalikan `HTTP 419` + JSON
    `{ok:false, code:"csrf_expired"}`.
- Sesuai dengan desain popup di lampiran.

### 3. "Coming Soon — Survei Tempat" di `tempat_list.php`
- Section `#surveiTempat` dipindah ke **paling atas** (di atas card filter).
- Kolom tabel dilebarkan: `form-control-sm` → `form-control`,
  `table-sm` dilepas, `<colgroup>` menetapkan `min-width` per kolom, dan
  tabel diberi `min-width:900px` agar tidak sempit.
- Admin view: file baru `admin/tempat_survei.php` + link menu
  **Admin → Giat Olahraga → Usulan Tempat (Survei)**.

### 4. `admin/sistem.php` — detail size tabel database
- File baru berisi:
  - Info database (versi PG, nama DB, total size, jumlah tabel).
  - Info PHP runtime.
  - **Tabel "Detail Size Semua Tabel Database"**:
    kolom **Total / Data / Indeks / TOAST / Baris (estimasi)** untuk
    setiap tabel di schema `public`, diurutkan berdasarkan total size
    (dari `pg_class` + `pg_total_relation_size`).

### 5. Member non-aktif tidak muncul di login
- `login.php` — daftar user di dropdown difilter:
  ```sql
  WHERE COALESCE(aktif,1) <> 0 OR role='admin'
  ```
  Admin tetap muncul walau non-aktif (agar akses admin tidak terkunci).
- Guard POST tetap ada (kalau ada user coba akses lewat ID lama).

### 6. Popup Kebijakan Privasi & UU PDP di halaman login
- `login.php` — link "Kebijakan Privasi (UU PDP)" tidak lagi `target=_blank`.
  Sekarang membuka **modal Bootstrap** berisi `<iframe>` yang memuat
  `privasi.php?embed=1`.
- `privasi.php` — mendukung mode `?embed=1`: hanya me-render konten
  tanpa header/footer aplikasi, cocok untuk iframe di modal.

---

## Perubahan skema PostgreSQL yang perlu dijalankan

Sebagian besar sudah dibuat idempotent di dalam PHP (`CREATE TABLE IF NOT
EXISTS ...`). Yang perlu **eksplisit dijalankan** (opsional, biar tidak nunggu
hit halaman pertama) hanya ini — aman dijalankan berulang:

```sql
-- #1: kolom tambahan utk catatan admin di pesanan paket
ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS admin_catatan TEXT;
ALTER TABLE paket_pesanan ADD COLUMN IF NOT EXISTS updated_at    TIMESTAMP;

-- #3: tabel usulan tempat (biasanya sudah dibuat otomatis oleh tempat_list.php)
CREATE TABLE IF NOT EXISTS tempat_survei (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL,
    nama       VARCHAR(180) NOT NULL,
    alamat     TEXT,
    jenis      VARCHAR(80),
    lat        DOUBLE PRECISION,
    lng        DOUBLE PRECISION,
    catatan    TEXT,
    status     VARCHAR(20) NOT NULL DEFAULT 'baru',
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP
);
```

Tidak ada perubahan lain pada `sportapp.sql`; data yang sudah ada tetap.

---

## Catatan uji cepat
- Login sebagai member yang **aktif=0** → tidak lagi muncul di dropdown login.
- Klik link "Kebijakan Privasi & UU PDP" di halaman login → tampil popup modal.
- POST login dengan sengaja menghapus field CSRF → tampil popup
  "Sesi Anda Kedaluwarsa" dengan tombol Muat Ulang / Kembali ke Login.
- Buka `/tempat_list.php` → section "Coming Soon — Survei Tempat" sekarang di
  paling atas, kolom lebar.
- Login sebagai admin → menu **Member Organize → Pesanan Paket Member** dan
  **Giat Olahraga → Usulan Tempat (Survei)** tersedia.
- Buka `/admin/sistem.php` → tabel size semua relasi public tampil.

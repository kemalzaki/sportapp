# Revisi 1 Juli 2026 (R21) — Ringkasan

Zip ini HANYA berisi file yang direvisi, bukan seluruh project.
Salin/timpa file-file di bawah ke folder `sportapp_core/` Anda.

## File yang diubah / ditambah

| File | Status | Keterangan |
|------|--------|------------|
| `tempat_list.php` | revisi | Fix bug sort by kilometer untuk Hiking. |
| `includes/paket_helpers.php` | revisi | Banner kunci PRO/KOMUNITAS sekarang 1 tombol → `/paket_upgrade.php`. Helper baru `paket_lock_banner()`, `paket_komunitas_lock_banner()`, `paket_prices()`. Backward-compatible. |
| `islami.php` | revisi | Kartu pesan WA paket Komunitas diganti dengan banner kunci baru (tombol "Lihat Paket & Upgrade"). |
| `paket_upgrade.php` | BARU | Halaman pilih paket + checkout Midtrans Snap. Status `users.paket` otomatis di-update setelah lunas. |
| `migrations_r21_1jul2026.sql` | BARU | Tabel `paket_pesanan`. |

## Perubahan PostgreSQL yang perlu ditambahkan

Hanya **satu tabel baru**:

```sql
CREATE TABLE IF NOT EXISTS paket_pesanan (
    id              BIGSERIAL PRIMARY KEY,
    kode            VARCHAR(40) UNIQUE NOT NULL,
    user_id         BIGINT NOT NULL,
    paket           VARCHAR(20) NOT NULL,
    harga           INTEGER NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    snap_token      TEXT,
    snap_redirect   TEXT,
    midtrans_status VARCHAR(40),
    midtrans_raw    TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT now(),
    paid_at         TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS paket_pesanan_user_idx
    ON paket_pesanan(user_id, created_at DESC);
```

Jalankan:
```
psql -U <user> -d <db> -f migrations_r21_1jul2026.sql
```

**Catatan**: `paket_upgrade.php` juga menjalankan `CREATE TABLE IF NOT EXISTS`
saat pertama kali diakses, jadi sebenarnya migrasi manual ini opsional.
Tidak ada data lain yang dihapus / diubah. Kolom `users.paket` sudah ada
sejak R14, dan migrasi hanya memastikan idempotent saja.

## Cara mengubah harga paket (opsional)

Default: PRO Rp 25.000/bulan, KOMUNITAS Rp 50.000/bulan.
Untuk mengubah tanpa edit kode, jalankan di psql:

```sql
INSERT INTO app_settings(skey,sval,keterangan,updated_at)
VALUES ('paket_price_pro','30000','Harga paket PRO / bulan', now())
ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, updated_at=now();

INSERT INTO app_settings(skey,sval,keterangan,updated_at)
VALUES ('paket_price_komunitas','60000','Harga paket KOMUNITAS / bulan', now())
ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, updated_at=now();
```

## Penjelasan perbaikan

### 1) `tempat_list.php` — Sort by kilometer Hiking

**Bug:** Ekspresi `COALESCE(t.jarak_km, rr.jarak_m/1000.0, 0)` dipakai juga
sebagai ekspresi `ORDER BY`. Karena fallback `0`, semua tempat Hiking yang
**belum** memiliki kiloan dianggap 0 dan muncul paling atas pada `km_asc`,
sehingga sort terlihat "tidak berfungsi" — semua entri ber-km tertimbun di bawah
tumpukan entri 0 km.

**Fix:** Ekspresi sort khusus tanpa fallback 0, plus `NULLS LAST`:

```php
$distSort = "COALESCE(t.jarak_km, rr.jarak_m/1000.0)";
// km_asc  → "$distSort ASC NULLS LAST,  t.nama ASC"
// km_desc → "$distSort DESC NULLS LAST, t.nama ASC"
```

Hasil: entri Hiking yang punya kiloan diurutkan dengan benar; entri tanpa
kiloan didorong ke akhir daftar.

### 2) Banner kunci Pro / Komunitas — satu tombol terpadu

Banner di `flyover.php`, `gaya_hidup.php`, `kalkulator*.php`, `kalori_mingguan.php`,
`live_tracking.php`, `monitoring.php`, `run.php`, dan `islami.php` semuanya
me-render `paket_pro_lock_banner()` / kartu manual. Implementasi helper diganti:
sekarang menampilkan **satu tombol** "Lihat Paket & Upgrade" yang membuka
`/paket_upgrade.php?need=pro` (atau `?need=komunitas` untuk Hub Islami).

Karena `paket_pro_lock_banner()` dipakai langsung oleh halaman-halaman tersebut
dan tanda tangannya sama, **tidak perlu menyentuh file halaman lain**.

### 3) `paket_upgrade.php` — Pilih paket + Midtrans

- Menampilkan 2 kartu paket (PRO & KOMUNITAS) dengan tombol "Pilih Paket".
- Setelah memilih, muncul ringkasan + tombol **"Bayar via Midtrans"**.
- Tombol bayar memanggil `?ajax=create_snap` → insert ke `paket_pesanan`,
  request token Snap, lalu membuka popup Snap (`snap.pay(token, ...)`)
  memakai kredensial dari `config/env.local.php` yang sudah ada (tidak
  perlu diubah).
- `onSuccess` / `onClose` memanggil `?ajax=confirm_payment` yang
  memverifikasi status transaksi ke API Midtrans dan, bila `settlement`
  / `capture` + `fraud_status=accept`, **otomatis** menjalankan
  `UPDATE users SET paket=$1 WHERE id=$2` — tanpa intervensi admin.
- Redirect `finish_url` juga mem-verifikasi status (safety-net jika user
  menutup popup terlalu cepat).

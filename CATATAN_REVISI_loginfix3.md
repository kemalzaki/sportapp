# Revisi loginfix-3 — 13 Juni 2026

## Masalah
Setelah login berhasil, `index.php` menampilkan HTTP 500:

```
Query gagal: ERROR:  COALESCE types smallint and boolean cannot be matched
LINE 1: ...ERE role IN ('member','admin') AND COALESCE(aktif,TRUE)=TRUE
```

Penyebab: di database lokal, kolom `users.aktif` ter-create sebagai **SMALLINT**
(nilai 0/1), bukan BOOLEAN. `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` tidak
mengubah tipe kolom yang sudah ada, sehingga `COALESCE(aktif, TRUE)` gagal
karena tipe `smallint` vs `boolean` tidak kompatibel di PostgreSQL.

## Perbaikan (file: `index.php`)
Mengganti pembanding boolean dengan ekspresi tahan-tipe yang valid untuk
BOOLEAN maupun SMALLINT/INT:

```php
$aktifExpr = "(LOWER(COALESCE(aktif::text,'true')) IN ('1','t','true','y','yes'))";
$memberAktif    = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND $aktifExpr");
$memberNonaktif = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin') AND NOT $aktifExpr");
```

`aktif::text` aman untuk tipe apapun (boolean → 'true'/'false', smallint → '0'/'1').

## Opsional — normalisasi skema (tidak menghapus data)
Jika ingin menyamakan tipe ke BOOLEAN secara permanen:

```sql
ALTER TABLE users
  ALTER COLUMN aktif DROP DEFAULT,
  ALTER COLUMN aktif TYPE BOOLEAN
    USING (CASE WHEN aktif::text IN ('1','t','true','y','yes') THEN TRUE ELSE FALSE END),
  ALTER COLUMN aktif SET DEFAULT TRUE,
  ALTER COLUMN aktif SET NOT NULL;
```

Tidak wajib — kode di atas sudah jalan tanpa migrasi ini.

## File dalam zip
- `index.php` — fix query aktif/non-aktif
- `CATATAN_REVISI_loginfix3.md` — dokumen ini

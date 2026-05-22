-- ===========================================================
-- HapFam SportApp — Migrasi tambahan (REVISI 21 poin)
-- Jalankan SEKALI saja di database PostgreSQL yang sama.
-- Aman: hanya MENAMBAH kolom/tabel, tidak menghapus data apa pun.
-- ===========================================================

-- 1) Tempat: tambah PIC admin, kontak WA, jenis olahraga, harga tiket/parkir
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "pic_user_id"   INTEGER REFERENCES "users"("id") ON DELETE SET NULL;
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "kontak_wa"     VARCHAR(30);
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "jenis_id"      INTEGER REFERENCES "jenis_olahraga"("id") ON DELETE SET NULL;
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "harga_tiket"   NUMERIC(12,2) DEFAULT 0;
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "harga_parkir"  NUMERIC(12,2) DEFAULT 0;

-- 2) Users: tambah nomor WhatsApp, dan PIC admin yang membantu member tsb
ALTER TABLE "users" ADD COLUMN IF NOT EXISTS "wa"            VARCHAR(30);
ALTER TABLE "users" ADD COLUMN IF NOT EXISTS "pic_admin_id"  INTEGER REFERENCES "users"("id") ON DELETE SET NULL;

-- 3) Chat forum: catat waktu edit pesan
ALTER TABLE "chat_forum" ADD COLUMN IF NOT EXISTS "updated_at" TIMESTAMP;

-- 4) Set timezone default DB ke Asia/Jakarta (GMT+7)
-- (PHP juga di-set di config/db.php saat koneksi)
DO $$
BEGIN
  EXECUTE 'ALTER DATABASE ' || quote_ident(current_database()) || ' SET TIME ZONE ''Asia/Jakarta''';
EXCEPTION WHEN insufficient_privilege THEN NULL;
END$$;

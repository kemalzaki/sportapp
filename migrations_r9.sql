-- ==========================================================================
-- migrations_r9.sql — Revisi 22 Juni 2026 R9
-- Pastikan SEMUA UNIQUE / PRIMARY KEY constraint yang dipakai ON CONFLICT
-- di kode PHP benar-benar ada. Idempotent: aman dijalankan ulang.
-- Jalankan SEKALI di PostgreSQL:
--    psql "$DATABASE_URL" -f migrations_r9.sql
-- atau di pgAdmin / DBeaver, jalankan keseluruhan file.
-- ==========================================================================

-- 1. kalori_target(user_id) PRIMARY KEY
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_target') THEN
    -- buang baris ganda jika ada
    DELETE FROM kalori_target a USING kalori_target b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_target'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_target ADD CONSTRAINT kalori_target_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 2. kalori_defisit_setting(user_id) PRIMARY KEY
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_defisit_setting') THEN
    DELETE FROM kalori_defisit_setting a USING kalori_defisit_setting b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_defisit_setting'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_defisit_setting ADD CONSTRAINT kalori_defisit_setting_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 3. absensi(jadwal_id, user_id) UNIQUE — dipakai apply_kondisi_to_absensi.
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='absensi') THEN
    DELETE FROM absensi a USING absensi b
      WHERE a.ctid < b.ctid AND a.jadwal_id = b.jadwal_id AND a.user_id = b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='absensi'::regclass
                     AND contype IN ('p','u')
                     AND conkey @> ARRAY[
                       (SELECT attnum FROM pg_attribute WHERE attrelid='absensi'::regclass AND attname='jadwal_id'),
                       (SELECT attnum FROM pg_attribute WHERE attrelid='absensi'::regclass AND attname='user_id')
                     ]::smallint[]) THEN
      BEGIN
        ALTER TABLE absensi ADD CONSTRAINT absensi_unique_ju UNIQUE (jadwal_id, user_id);
      EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL; END;
    END IF;
  END IF;
END $$;

-- 4. app_settings(skey) UNIQUE — dipakai oleh includes/app_settings.php
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='app_settings') THEN
    DELETE FROM app_settings a USING app_settings b
      WHERE a.ctid < b.ctid AND a.skey = b.skey;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='app_settings'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE app_settings ADD CONSTRAINT app_settings_pk PRIMARY KEY (skey);
    END IF;
  END IF;
END $$;

-- 5. notifications — pastikan kolom `isi` ada (skema lama) ATAU `body` (v8).
-- Bila tabel sudah pakai `isi` saja, tambahkan alias `body` agar INSERT lama
-- (migrations_v8.php → notifications(user_id,judul,body,url)) tidak gagal.
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='notifications') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='notifications' AND column_name='body') THEN
      ALTER TABLE notifications ADD COLUMN body TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='notifications' AND column_name='isi') THEN
      ALTER TABLE notifications ADD COLUMN isi TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='notifications' AND column_name='jenis') THEN
      ALTER TABLE notifications ADD COLUMN jenis VARCHAR(30) NOT NULL DEFAULT 'umum';
    END IF;
  END IF;
END $$;

-- 6. dm_messages — kolom delivered_at + read_at (untuk ceklis WhatsApp-style).
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='dm_messages') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='dm_messages' AND column_name='delivered_at') THEN
      ALTER TABLE dm_messages ADD COLUMN delivered_at TIMESTAMP NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='dm_messages' AND column_name='read_at') THEN
      ALTER TABLE dm_messages ADD COLUMN read_at TIMESTAMP NULL;
    END IF;
  END IF;
END $$;

-- 7. rate_limit — tabel pendukung rate_limit() di includes/security.php.
CREATE TABLE IF NOT EXISTS rate_limit (
  bucket VARCHAR(120) NOT NULL,
  ts     TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS rate_limit_bucket_ts_idx ON rate_limit(bucket, ts);

-- ==========================================================================
-- Selesai. Verifikasi cepat:
--   \d kalori_target              -- harus ada PRIMARY KEY (user_id)
--   \d kalori_defisit_setting     -- harus ada PRIMARY KEY (user_id)
--   \d absensi                    -- harus ada UNIQUE (jadwal_id, user_id)
--   \d app_settings               -- harus ada PRIMARY KEY (skey)
-- ==========================================================================

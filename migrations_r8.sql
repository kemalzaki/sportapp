-- =====================================================================
-- Revisi R8 — 22 Juni 2026
-- Memperbaiki sisa error "no unique or exclusion constraint matching the
-- ON CONFLICT specification" yang masih muncul di:
--   • index.php  (login → sapa_log)
--   • kalori_mingguan.php (target & defisit setting)
--   • admin/jadwal.php  (apply_kondisi_to_absensi → absensi)
--
-- Strategi:
--   1. Pastikan constraint UNIQUE / PK benar-benar ada (dedup dulu pakai
--      ctid agar pasti jalan walau kolom id NULL).
--   2. Pastikan kolom dm_messages.delivered_at & read_at ada (kalau hilang
--      polling DM 500 → user mengira pesan tidak terkirim).
--
-- Aman dijalankan berulang (idempotent). Tidak menghapus data selain
-- baris ganda yang menghalangi pembuatan UNIQUE.
--
-- Jalankan:
--   psql -h <host> -U <user> -d <db> -f migrations_r8.sql
-- =====================================================================

-- 1. absensi(jadwal_id, user_id) UNIQUE -----------------------------------
DO $$
DECLARE n int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='absensi') THEN
    WITH d AS (
      DELETE FROM absensi a USING absensi b
      WHERE a.ctid < b.ctid
        AND a.jadwal_id = b.jadwal_id
        AND a.user_id   = b.user_id
      RETURNING 1
    ) SELECT count(*) INTO n FROM d;
    RAISE NOTICE 'absensi: % baris duplikat dihapus', n;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conrelid = 'absensi'::regclass
        AND contype IN ('p','u')
        AND conkey @> ARRAY[
          (SELECT attnum FROM pg_attribute WHERE attrelid='absensi'::regclass AND attname='jadwal_id'),
          (SELECT attnum FROM pg_attribute WHERE attrelid='absensi'::regclass AND attname='user_id')
        ]::smallint[]
    ) THEN
      ALTER TABLE absensi ADD CONSTRAINT absensi_unique_ju UNIQUE (jadwal_id, user_id);
    END IF;
  END IF;
END $$;

-- 2. sapa_log(sender_user_id, target_user_id) UNIQUE ----------------------
DO $$
DECLARE n int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='sapa_log') THEN
    WITH d AS (
      DELETE FROM sapa_log a USING sapa_log b
      WHERE a.ctid < b.ctid
        AND a.sender_user_id = b.sender_user_id
        AND a.target_user_id = b.target_user_id
      RETURNING 1
    ) SELECT count(*) INTO n FROM d;
    RAISE NOTICE 'sapa_log: % baris duplikat dihapus', n;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conrelid='sapa_log'::regclass AND contype IN ('p','u')
        AND conkey @> ARRAY[
          (SELECT attnum FROM pg_attribute WHERE attrelid='sapa_log'::regclass AND attname='sender_user_id'),
          (SELECT attnum FROM pg_attribute WHERE attrelid='sapa_log'::regclass AND attname='target_user_id')
        ]::smallint[]
    ) THEN
      ALTER TABLE sapa_log ADD CONSTRAINT sapa_log_unique_st UNIQUE (sender_user_id, target_user_id);
    END IF;
  END IF;
END $$;

-- 3. kalori_target(user_id) PK --------------------------------------------
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_target') THEN
    DELETE FROM kalori_target a USING kalori_target b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conrelid='kalori_target'::regclass AND contype IN ('p','u')
        AND conkey @> ARRAY[
          (SELECT attnum FROM pg_attribute WHERE attrelid='kalori_target'::regclass AND attname='user_id')
        ]::smallint[]
    ) THEN
      ALTER TABLE kalori_target ADD CONSTRAINT kalori_target_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 4. kalori_defisit_setting(user_id) PK -----------------------------------
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_defisit_setting') THEN
    DELETE FROM kalori_defisit_setting a USING kalori_defisit_setting b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint
      WHERE conrelid='kalori_defisit_setting'::regclass AND contype IN ('p','u')
        AND conkey @> ARRAY[
          (SELECT attnum FROM pg_attribute WHERE attrelid='kalori_defisit_setting'::regclass AND attname='user_id')
        ]::smallint[]
    ) THEN
      ALTER TABLE kalori_defisit_setting ADD CONSTRAINT kalori_defisit_setting_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 5. dm_messages: pastikan kolom delivered_at & read_at ada ---------------
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='dm_messages') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='dm_messages' AND column_name='delivered_at') THEN
      ALTER TABLE dm_messages ADD COLUMN delivered_at TIMESTAMP;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='dm_messages' AND column_name='read_at') THEN
      ALTER TABLE dm_messages ADD COLUMN read_at TIMESTAMP;
    END IF;
  END IF;
END $$;

-- 6. Index bantu (mengulangi r6/r7, aman) ---------------------------------
CREATE INDEX IF NOT EXISTS idx_absensi_jadwal_user ON absensi(jadwal_id, user_id);
CREATE INDEX IF NOT EXISTS idx_dm_pair ON dm_messages(sender_id, receiver_id, id DESC);

-- =====================================================================
-- Verifikasi:
--   \d absensi                  → ada UNIQUE (jadwal_id, user_id)
--   \d sapa_log                 → ada UNIQUE (sender_user_id, target_user_id)
--   \d kalori_target            → ada PRIMARY KEY (user_id)
--   \d kalori_defisit_setting   → ada PRIMARY KEY (user_id)
--   \d dm_messages              → ada kolom delivered_at, read_at
-- =====================================================================

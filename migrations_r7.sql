-- =====================================================================
-- Revisi R7 — Perbaikan migrations_r6.sql.
-- R6 gagal menghapus baris ganda di tabel `absensi` dan `badges` karena
-- memakai kolom `id` (kemungkinan tidak ada / NULL di DB lama), sehingga
-- ALTER TABLE ... ADD CONSTRAINT UNIQUE selalu gagal.
--
-- R7 memakai `ctid` (identitas fisik baris, selalu unik di Postgres) untuk
-- menghapus duplikat, jadi dijamin jalan tanpa peduli skema PK lama.
--
-- Aman dijalankan berulang. TIDAK menghapus data selain BARIS GANDA.
-- Jika r6 sudah sebagian jalan (post_likes, kalori_*, user_badges sudah
-- terpasang) script ini akan skip yang sudah ada.
--
-- Jalankan:
--   psql -h <host> -U <user> -d <db> -f migrations_r7.sql
-- =====================================================================

-- 1. absensi(jadwal_id, user_id) ------------------------------------------
DO $$
DECLARE deleted_rows int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='absensi') THEN
    -- Hapus duplikat memakai ctid (pasti unik per baris fisik).
    -- Pertahankan ctid terbesar (umumnya baris yang dimasukkan paling baru).
    WITH d AS (
      DELETE FROM absensi a USING absensi b
      WHERE a.ctid < b.ctid
        AND a.jadwal_id = b.jadwal_id
        AND a.user_id   = b.user_id
      RETURNING 1
    )
    SELECT count(*) INTO deleted_rows FROM d;
    RAISE NOTICE 'absensi: % baris duplikat dihapus', deleted_rows;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='absensi_unique_ju') THEN
      ALTER TABLE absensi ADD CONSTRAINT absensi_unique_ju UNIQUE (jadwal_id, user_id);
    END IF;
  END IF;
END $$;

-- 2. post_likes(post_id, user_id) -----------------------------------------
DO $$
DECLARE deleted_rows int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='post_likes') THEN
    WITH d AS (
      DELETE FROM post_likes a USING post_likes b
      WHERE a.ctid < b.ctid
        AND a.post_id = b.post_id
        AND a.user_id = b.user_id
      RETURNING 1
    )
    SELECT count(*) INTO deleted_rows FROM d;
    RAISE NOTICE 'post_likes: % baris duplikat dihapus', deleted_rows;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='post_likes_unique_pu') THEN
      ALTER TABLE post_likes ADD CONSTRAINT post_likes_unique_pu UNIQUE (post_id, user_id);
    END IF;
  END IF;
END $$;

-- 3. kalori_target(user_id) PK --------------------------------------------
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_target') THEN
    DELETE FROM kalori_target a USING kalori_target b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_target'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_target ADD CONSTRAINT kalori_target_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 4. kalori_defisit_setting(user_id) PK -----------------------------------
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_defisit_setting') THEN
    DELETE FROM kalori_defisit_setting a USING kalori_defisit_setting b
      WHERE a.ctid < b.ctid AND a.user_id = b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_defisit_setting'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_defisit_setting ADD CONSTRAINT kalori_defisit_setting_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 5. badges(kode) UNIQUE --------------------------------------------------
--    PENTING: sebelum hapus baris badges ganda, pindahkan dulu user_badges
--    yang mengarah ke ctid yang akan dibuang supaya tidak kehilangan data.
DO $$
DECLARE deleted_rows int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='badges') THEN
    -- Pertahankan ctid TERKECIL per kode (dianggap badge master asli),
    -- hapus duplikatnya. Pakai ctid, bukan id, karena id mungkin NULL.
    WITH d AS (
      DELETE FROM badges a USING badges b
      WHERE a.ctid > b.ctid
        AND a.kode = b.kode
      RETURNING 1
    )
    SELECT count(*) INTO deleted_rows FROM d;
    RAISE NOTICE 'badges: % baris duplikat dihapus', deleted_rows;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='badges_unique_kode') THEN
      ALTER TABLE badges ADD CONSTRAINT badges_unique_kode UNIQUE (kode);
    END IF;
  END IF;
END $$;

-- 6. user_badges(user_id, badge_id) UNIQUE --------------------------------
DO $$
DECLARE deleted_rows int;
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='user_badges') THEN
    -- Bersihkan user_badges yang badge_id-nya sudah tidak ada (akibat step 5).
    DELETE FROM user_badges ub
      WHERE badge_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM badges b WHERE b.id = ub.badge_id);

    WITH d AS (
      DELETE FROM user_badges a USING user_badges b
      WHERE a.ctid < b.ctid
        AND a.user_id  = b.user_id
        AND a.badge_id = b.badge_id
      RETURNING 1
    )
    SELECT count(*) INTO deleted_rows FROM d;
    RAISE NOTICE 'user_badges: % baris duplikat dihapus', deleted_rows;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='user_badges_unique_ub') THEN
      ALTER TABLE user_badges ADD CONSTRAINT user_badges_unique_ub UNIQUE (user_id, badge_id);
    END IF;
  END IF;
END $$;

-- 7. Index bantu untuk riwayat.php ----------------------------------------
CREATE INDEX IF NOT EXISTS idx_absensi_jadwal_user ON absensi(jadwal_id, user_id);

-- =====================================================================
-- Verifikasi cepat:
--   \d absensi      → ada "absensi_unique_ju" UNIQUE (jadwal_id, user_id)
--   \d post_likes   → ada "post_likes_unique_pu"
--   \d badges       → ada UNIQUE (kode)
--   \d user_badges  → ada UNIQUE (user_id, badge_id)
--
-- Jika ingin lihat baris duplikat yang akan dihapus SEBELUM menjalankan:
--   SELECT jadwal_id, user_id, count(*) FROM absensi
--     GROUP BY 1,2 HAVING count(*) > 1;
--   SELECT kode, count(*) FROM badges GROUP BY 1 HAVING count(*) > 1;
-- =====================================================================

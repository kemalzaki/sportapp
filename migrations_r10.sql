-- ==========================================================================
-- migrations_r10.sql — Revisi 22 Juni 2026 R10
--
-- Memastikan SEMUA UNIQUE / PRIMARY KEY yang dipakai oleh klausa ON CONFLICT
-- di kode PHP benar-benar tersedia di PostgreSQL. Aman dijalankan ulang
-- (idempotent). Jalankan SEKALI di HeidiSQL / pgAdmin / DBeaver / psql:
--
--   psql "$DATABASE_URL" -f migrations_r10.sql
--
-- Berisi semua isi migrations_r9.sql + tambahan untuk tabel-tabel berikut:
--   fcm_tokens, user_device_loc, user_notif_state, post_views, post_bookmarks,
--   doa_aamiin, gaya_hidup_log, user_olahraga_favorit, user_kondisi,
--   user_quran_catatan, user_quran_bookmark, upload_harian_likes,
--   strava_tokens, iptv_channels, tim_member, user_islami_pref,
--   islami_streak, islami_amal_jariyah, islami_badges, challenge_master,
--   post_hashtags, post_mentions, sapa_log, search_keywords.
--
-- Tidak ada data yang dihapus selain duplikat persis yang menghalangi
-- pembuatan UNIQUE constraint (baris ber-ctid terbesar dipertahankan).
-- ==========================================================================

-- Helper: tambahkan UNIQUE (col_list) ke table_name bila belum ada constraint
-- PRIMARY/UNIQUE yang persis cover kolom-kolom tsb. Idempotent.
CREATE OR REPLACE FUNCTION _r10_add_unique(p_table text, p_cols text[], p_cname text)
RETURNS void LANGUAGE plpgsql AS $fn$
DECLARE
  v_oid oid;
  v_attnums smallint[];
  v_exists boolean;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = p_table) THEN
    RETURN;
  END IF;
  v_oid := (quote_ident(p_table))::regclass;
  SELECT array_agg(a.attnum ORDER BY a.attnum)
    INTO v_attnums
    FROM unnest(p_cols) c
    JOIN pg_attribute a ON a.attrelid = v_oid AND a.attname = c;
  IF v_attnums IS NULL OR array_length(v_attnums,1) <> array_length(p_cols,1) THEN
    -- ada kolom yang belum ada — skip diam-diam
    RETURN;
  END IF;
  SELECT EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conrelid = v_oid
      AND contype IN ('p','u')
      AND (SELECT array_agg(x ORDER BY x) FROM unnest(conkey) x)
          = (SELECT array_agg(x ORDER BY x) FROM unnest(v_attnums) x)
  ) INTO v_exists;
  IF v_exists THEN RETURN; END IF;

  -- hapus duplikat sebelum membuat UNIQUE
  EXECUTE format(
    'DELETE FROM %1$I a USING %1$I b WHERE a.ctid < b.ctid AND %2$s',
    p_table,
    (SELECT string_agg(format('a.%I = b.%I', c, c), ' AND ') FROM unnest(p_cols) c)
  );

  EXECUTE format('ALTER TABLE %I ADD CONSTRAINT %I UNIQUE (%s)',
                 p_table, p_cname,
                 (SELECT string_agg(quote_ident(c), ',') FROM unnest(p_cols) c));
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $fn$;


-- ====== Bagian dari migrations_r9.sql (tetap diperlukan) ===================
SELECT _r10_add_unique('kalori_target',          ARRAY['user_id'],              'kalori_target_uq');
SELECT _r10_add_unique('kalori_defisit_setting', ARRAY['user_id'],              'kalori_defisit_setting_uq');
SELECT _r10_add_unique('absensi',                ARRAY['jadwal_id','user_id'],  'absensi_uq_ju');
SELECT _r10_add_unique('app_settings',           ARRAY['skey'],                 'app_settings_uq');

-- notifications & dm_messages (skema kompatibilitas — sama persis dgn r9)
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='notifications') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notifications' AND column_name='body') THEN
      ALTER TABLE notifications ADD COLUMN body TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notifications' AND column_name='isi') THEN
      ALTER TABLE notifications ADD COLUMN isi TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='notifications' AND column_name='jenis') THEN
      ALTER TABLE notifications ADD COLUMN jenis VARCHAR(30) NOT NULL DEFAULT 'umum';
    END IF;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='dm_messages') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='dm_messages' AND column_name='delivered_at') THEN
      ALTER TABLE dm_messages ADD COLUMN delivered_at TIMESTAMP NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='dm_messages' AND column_name='read_at') THEN
      ALTER TABLE dm_messages ADD COLUMN read_at TIMESTAMP NULL;
    END IF;
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS rate_limit (
  bucket VARCHAR(120) NOT NULL,
  ts     TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS rate_limit_bucket_ts_idx ON rate_limit(bucket, ts);


-- ====== Tambahan R10 — sumber-sumber ON CONFLICT yang tersisa ==============
SELECT _r10_add_unique('fcm_tokens',            ARRAY['user_id','token'],      'fcm_tokens_uq');
SELECT _r10_add_unique('user_device_loc',       ARRAY['user_id'],              'user_device_loc_uq');
SELECT _r10_add_unique('user_notif_state',      ARRAY['user_id'],              'user_notif_state_uq');
SELECT _r10_add_unique('post_views',            ARRAY['post_id','user_id'],    'post_views_uq');
SELECT _r10_add_unique('post_bookmarks',        ARRAY['user_id','post_id'],    'post_bookmarks_uq');
SELECT _r10_add_unique('doa_aamiin',            ARRAY['doa_id','user_id'],     'doa_aamiin_uq');
SELECT _r10_add_unique('gaya_hidup_log',        ARRAY['user_id','tanggal'],    'gaya_hidup_log_uq');
SELECT _r10_add_unique('user_olahraga_favorit', ARRAY['user_id','nama'],       'user_olahraga_favorit_uq');
SELECT _r10_add_unique('user_kondisi',          ARRAY['user_id'],              'user_kondisi_uq');
SELECT _r10_add_unique('user_quran_catatan',    ARRAY['user_id','surah','ayat'], 'user_quran_catatan_uq');
SELECT _r10_add_unique('user_quran_bookmark',   ARRAY['user_id'],              'user_quran_bookmark_uq');
SELECT _r10_add_unique('upload_harian_likes',   ARRAY['upload_id','user_id'],  'upload_harian_likes_uq');
SELECT _r10_add_unique('strava_tokens',         ARRAY['user_id'],              'strava_tokens_uq');
SELECT _r10_add_unique('iptv_channels',         ARRAY['url'],                  'iptv_channels_uq');
SELECT _r10_add_unique('tim_member',            ARRAY['tim_id','user_id'],     'tim_member_uq');
SELECT _r10_add_unique('user_islami_pref',      ARRAY['user_id'],              'user_islami_pref_uq');
SELECT _r10_add_unique('islami_streak',         ARRAY['user_id','tanggal'],    'islami_streak_uq');
SELECT _r10_add_unique('islami_badges',         ARRAY['user_id','badge_key'],  'islami_badges_uq');
SELECT _r10_add_unique('challenge_master',      ARRAY['kunci'],                'challenge_master_uq');
SELECT _r10_add_unique('post_hashtags',         ARRAY['post_id','tag'],        'post_hashtags_uq');
SELECT _r10_add_unique('post_mentions',         ARRAY['post_id','user_id'],    'post_mentions_uq');
SELECT _r10_add_unique('sapa_log',              ARRAY['sender_user_id','target_user_id'], 'sapa_log_uq');

-- search_keywords (revisi R7) — buat tabel bila belum ada (data sudah ada di SQL dump kalau pernah dibuka admin)
CREATE TABLE IF NOT EXISTS search_keywords (
    id BIGSERIAL PRIMARY KEY,
    kategori VARCHAR(20) NOT NULL,
    kata TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    urutan INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_search_keywords_kat ON search_keywords(kategori, aktif);

-- Bersihkan helper
DROP FUNCTION IF EXISTS _r10_add_unique(text, text[], text);

-- ==========================================================================
-- Verifikasi cepat di HeidiSQL (klik tab "Query") — jalankan satu per satu:
--   SELECT conname, contype FROM pg_constraint WHERE conrelid='absensi'::regclass;
--   SELECT conname, contype FROM pg_constraint WHERE conrelid='kalori_target'::regclass;
--   SELECT conname, contype FROM pg_constraint WHERE conrelid='gaya_hidup_log'::regclass;
-- Setelah migrasi ini berjalan, semua ON CONFLICT di kode PHP akan bekerja.
-- ==========================================================================

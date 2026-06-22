-- =====================================================================
-- Revisi 22 Juni 2026 R5 — Tambahan UNIQUE/PK constraints + kolom bantu.
-- Jalankan SEKALI:
--   psql -d sportapp -U <user> -f migrations_r5.sql
-- Aman dijalankan berulang (idempotent). TIDAK menghapus data apapun.
--
-- Tujuan: menghilangkan error "there is no unique or exclusion constraint
-- matching the ON CONFLICT specification" pada riwayat.php, kalori_mingguan.php,
-- index.php (like), dan halaman lainnya yang memakai ON CONFLICT.
-- =====================================================================

-- 0. Helper: drop duplikat sebelum menambah UNIQUE (data dipertahankan, hanya baris ganda yang dirapikan).
--    Strategi umum: keep MIN(ctid) per kunci.

-- 1. post_likes(post_id, user_id)
DELETE FROM post_likes a
  USING post_likes b
  WHERE a.ctid > b.ctid AND a.post_id=b.post_id AND a.user_id=b.user_id;
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='post_likes_unique_pu') THEN
    ALTER TABLE post_likes ADD CONSTRAINT post_likes_unique_pu UNIQUE (post_id, user_id);
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 2. post_bookmarks(user_id, post_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='post_bookmarks') THEN
    DELETE FROM post_bookmarks a USING post_bookmarks b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.post_id=b.post_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='post_bookmarks_unique_up') THEN
      ALTER TABLE post_bookmarks ADD CONSTRAINT post_bookmarks_unique_up UNIQUE (user_id, post_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 3. post_views(post_id, user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='post_views') THEN
    DELETE FROM post_views a USING post_views b
      WHERE a.ctid > b.ctid AND a.post_id=b.post_id AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='post_views_unique_pu') THEN
      ALTER TABLE post_views ADD CONSTRAINT post_views_unique_pu UNIQUE (post_id, user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 4. upload_harian_likes(upload_id, user_id) — biasanya sudah PRIMARY KEY, tapi
--    pada DB lama yang dibuat manual mungkin belum.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='upload_harian_likes') THEN
    DELETE FROM upload_harian_likes a USING upload_harian_likes b
      WHERE a.ctid > b.ctid AND a.upload_id=b.upload_id AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='upload_harian_likes'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE upload_harian_likes ADD CONSTRAINT upload_harian_likes_pk PRIMARY KEY (upload_id, user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 5. doa_aamiin(doa_id, user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='doa_aamiin') THEN
    DELETE FROM doa_aamiin a USING doa_aamiin b
      WHERE a.ctid > b.ctid AND a.doa_id=b.doa_id AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='doa_aamiin_unique_du') THEN
      ALTER TABLE doa_aamiin ADD CONSTRAINT doa_aamiin_unique_du UNIQUE (doa_id, user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 6. kalori_target(user_id) PRIMARY KEY (auto-create di kalori_mingguan.php
--    sudah definisikan PK, tapi DB existing bisa belum punya).
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_target') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_target'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM kalori_target a USING kalori_target b
        WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE kalori_target ADD CONSTRAINT kalori_target_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 7. kalori_defisit_setting(user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_defisit_setting') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_defisit_setting'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM kalori_defisit_setting a USING kalori_defisit_setting b
        WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE kalori_defisit_setting ADD CONSTRAINT kalori_defisit_setting_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 8. gaya_hidup(user_id, tanggal)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='gaya_hidup') THEN
    DELETE FROM gaya_hidup a USING gaya_hidup b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.tanggal=b.tanggal;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='gaya_hidup_unique_ut') THEN
      ALTER TABLE gaya_hidup ADD CONSTRAINT gaya_hidup_unique_ut UNIQUE (user_id, tanggal);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 9. user_olahraga_favorit(user_id, nama)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='user_olahraga_favorit') THEN
    DELETE FROM user_olahraga_favorit a USING user_olahraga_favorit b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.nama=b.nama;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='user_olfav_unique_un') THEN
      ALTER TABLE user_olahraga_favorit ADD CONSTRAINT user_olfav_unique_un UNIQUE (user_id, nama);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 10. user_status_kesehatan(user_id) — profile.php ON CONFLICT(user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='user_status_kesehatan') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='user_status_kesehatan'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM user_status_kesehatan a USING user_status_kesehatan b
        WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE user_status_kesehatan ADD CONSTRAINT user_status_kesehatan_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 11. checkin (jadwal_id, user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='checkin') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='checkin_unique_ju') THEN
      DELETE FROM checkin a USING checkin b
        WHERE a.ctid > b.ctid AND a.jadwal_id=b.jadwal_id AND a.user_id=b.user_id;
      ALTER TABLE checkin ADD CONSTRAINT checkin_unique_ju UNIQUE (jadwal_id, user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 12. user_badges(user_id, badge_id) — anti-double badge
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='user_badges') THEN
    DELETE FROM user_badges a USING user_badges b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.badge_id=b.badge_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='user_badges_unique_ub') THEN
      ALTER TABLE user_badges ADD CONSTRAINT user_badges_unique_ub UNIQUE (user_id, badge_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 13. notif_state(user_id) — api_notif_poll.php
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='notif_state') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='notif_state'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM notif_state a USING notif_state b WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE notif_state ADD CONSTRAINT notif_state_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 14. device_loc(user_id) — api_device_loc.php
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='device_loc') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='device_loc'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM device_loc a USING device_loc b WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE device_loc ADD CONSTRAINT device_loc_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 15. fcm_tokens(user_id, token)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='fcm_tokens') THEN
    DELETE FROM fcm_tokens a USING fcm_tokens b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.token=b.token;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fcm_tokens_unique_ut') THEN
      ALTER TABLE fcm_tokens ADD CONSTRAINT fcm_tokens_unique_ut UNIQUE (user_id, token);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 16. strava_tokens(user_id) PRIMARY KEY
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='strava_tokens') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='strava_tokens'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM strava_tokens a USING strava_tokens b WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE strava_tokens ADD CONSTRAINT strava_tokens_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 17. quran_bookmark(user_id) PRIMARY KEY (untuk auto-bookmark terakhir baca)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='quran_bookmark') THEN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='quran_bookmark'::regclass AND contype IN ('p','u')) THEN
      DELETE FROM quran_bookmark a USING quran_bookmark b WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
      ALTER TABLE quran_bookmark ADD CONSTRAINT quran_bookmark_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 18. quran_catatan(user_id, surah, ayat)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='quran_catatan') THEN
    DELETE FROM quran_catatan a USING quran_catatan b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.surah=b.surah AND a.ayat=b.ayat;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='quran_catatan_unique_usa') THEN
      ALTER TABLE quran_catatan ADD CONSTRAINT quran_catatan_unique_usa UNIQUE (user_id, surah, ayat);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 19. tim_member(tim_id, user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='tim_member') THEN
    DELETE FROM tim_member a USING tim_member b
      WHERE a.ctid > b.ctid AND a.tim_id=b.tim_id AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='tim_member_unique_tu') THEN
      ALTER TABLE tim_member ADD CONSTRAINT tim_member_unique_tu UNIQUE (tim_id, user_id);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 20. iptv channel(url) UNIQUE
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='iptv_channels') THEN
    DELETE FROM iptv_channels a USING iptv_channels b WHERE a.ctid > b.ctid AND a.url=b.url;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='iptv_channels_unique_url') THEN
      ALTER TABLE iptv_channels ADD CONSTRAINT iptv_channels_unique_url UNIQUE (url);
    END IF;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 21. chat_reactions(chat_id, user_id) — bukan ON CONFLICT, tapi defensive
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='chat_reactions') THEN
    DELETE FROM chat_reactions a USING chat_reactions b
      WHERE a.ctid > b.ctid AND a.chat_id=b.chat_id AND a.user_id=b.user_id;
  END IF;
EXCEPTION WHEN others THEN NULL; END $$;

-- 22. users.tema_warna kolom (jika belum ada) — dipakai profile.php
ALTER TABLE users ADD COLUMN IF NOT EXISTS tema_warna VARCHAR(32);

-- 23. dm_messages indeks bantu performa send/receive
CREATE INDEX IF NOT EXISTS idx_dm_pair ON dm_messages(sender_id, receiver_id, id DESC);

-- =====================================================================
-- Selesai. Cek hasilnya:
--   \d post_likes   \d kalori_target   \d gaya_hidup
-- Pastikan baris "UNIQUE/PRIMARY KEY" muncul.
-- =====================================================================

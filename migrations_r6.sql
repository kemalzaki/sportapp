-- =====================================================================
-- Revisi 22 Juni 2026 R6 — Lanjutan dari migrations_r5.sql.
-- Menambah constraint yang BELUM dicakup r5 dan memperbaiki kasus
-- dimana DO-block r5 swallow error ("EXCEPTION WHEN OTHERS THEN NULL")
-- sehingga ALTER TABLE-nya tidak benar-benar terpasang.
--
-- Aman dijalankan berulang (idempotent). TIDAK menghapus data apapun
-- selain BARIS GANDA yang menghalangi pembuatan UNIQUE constraint.
--
-- Jalankan:
--   psql -h <host> -U <user> -d <db> -f migrations_r6.sql
-- =====================================================================

-- 1. absensi(jadwal_id, user_id) — dipakai admin/absensi.php (apply_kondisi_to_absensi)
--    ON CONFLICT (jadwal_id,user_id) — r5 belum menambahkan, ini penyebab utama error
--    "no unique or exclusion constraint" yang muncul saat admin menyimpan absensi
--    atau jadwal (apply_kondisi dipanggil saat kondisi sehat/sakit berubah).
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='absensi') THEN
    -- Gabungkan baris ganda untuk (jadwal_id,user_id) sama:
    -- pertahankan id TERBESAR (data terbaru), hapus yang lebih lama.
    DELETE FROM absensi a USING absensi b
      WHERE a.jadwal_id = b.jadwal_id
        AND a.user_id   = b.user_id
        AND a.id        < b.id;
    -- Tambahkan UNIQUE constraint kalau belum ada (idempotent).
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='absensi_unique_ju') THEN
      ALTER TABLE absensi ADD CONSTRAINT absensi_unique_ju UNIQUE (jadwal_id, user_id);
    END IF;
  END IF;
END $$;

-- 2. post_likes(post_id, user_id) — pastikan benar-benar terpasang (r5 pakai EXCEPTION
--    WHEN OTHERS yang bisa menelan error saat data ganda). Kita lakukan lagi tanpa swallow.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='post_likes') THEN
    DELETE FROM post_likes a USING post_likes b
      WHERE a.ctid > b.ctid AND a.post_id=b.post_id AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='post_likes_unique_pu') THEN
      ALTER TABLE post_likes ADD CONSTRAINT post_likes_unique_pu UNIQUE (post_id, user_id);
    END IF;
  END IF;
END $$;

-- 3. kalori_target(user_id) — pastikan PK terpasang.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_target') THEN
    DELETE FROM kalori_target a USING kalori_target b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_target'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_target ADD CONSTRAINT kalori_target_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 4. kalori_defisit_setting(user_id)
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='kalori_defisit_setting') THEN
    DELETE FROM kalori_defisit_setting a USING kalori_defisit_setting b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conrelid='kalori_defisit_setting'::regclass AND contype IN ('p','u')) THEN
      ALTER TABLE kalori_defisit_setting ADD CONSTRAINT kalori_defisit_setting_pk PRIMARY KEY (user_id);
    END IF;
  END IF;
END $$;

-- 5. badges(kode) UNIQUE — supaya badge master tidak punya baris ganda yang membuat
--    "Daftar Semua Badge" di profile.php menampilkan badge dobel.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='badges') THEN
    -- Pertahankan id terkecil per kode; hapus duplikat lain.
    DELETE FROM badges a USING badges b
      WHERE a.id > b.id AND a.kode = b.kode;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='badges_unique_kode') THEN
      ALTER TABLE badges ADD CONSTRAINT badges_unique_kode UNIQUE (kode);
    END IF;
  END IF;
END $$;

-- 6. user_badges(user_id, badge_id) — pastikan UNIQUE benar-benar terpasang +
--    bersihkan user_badges yang merujuk ke badge_id yang baru saja dihapus di step 5.
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='user_badges') THEN
    -- Buang user_badges yang badge_id-nya tidak lagi ada di tabel badges.
    DELETE FROM user_badges ub
      WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.id = ub.badge_id);
    -- Buang baris ganda (data lama tanpa UNIQUE).
    DELETE FROM user_badges a USING user_badges b
      WHERE a.ctid > b.ctid AND a.user_id=b.user_id AND a.badge_id=b.badge_id;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='user_badges_unique_ub') THEN
      ALTER TABLE user_badges ADD CONSTRAINT user_badges_unique_ub UNIQUE (user_id, badge_id);
    END IF;
  END IF;
END $$;

-- 7. Index bantu untuk perhitungan hadir/total di riwayat.php (mempercepat
--    COUNT(DISTINCT user_id) ... WHERE jadwal_id=...).
CREATE INDEX IF NOT EXISTS idx_absensi_jadwal_user ON absensi(jadwal_id, user_id);

-- =====================================================================
-- Verifikasi:
--   \d absensi      → harus ada "absensi_unique_ju" UNIQUE (jadwal_id, user_id)
--   \d post_likes   → harus ada "post_likes_unique_pu"
--   \d kalori_target → harus ada PRIMARY KEY (user_id)
--   \d badges       → harus ada UNIQUE (kode)
--   \d user_badges  → harus ada UNIQUE (user_id, badge_id)
-- =====================================================================

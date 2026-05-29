-- --------------------------------------------------------
-- Host:                         pg-c8c7f-adamsasmita534-4b4d.e.aivencloud.com
-- Server version:               PostgreSQL 17.10 on x86_64-pc-linux-gnu, compiled by gcc (GCC) 15.2.1 20260123 (Red Hat 15.2.1-7), 64-bit
-- Server OS:                    
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES  */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table public.absensi
CREATE TABLE IF NOT EXISTS "absensi" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''absensi_id_seq''::regclass)',
	"jadwal_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"hadir" SMALLINT NOT NULL DEFAULT '0',
	"metode" VARCHAR(20) NULL DEFAULT 'manual',
	"checkin_at" TIMESTAMP NULL DEFAULT NULL,
	"lat" DOUBLE PRECISION NULL DEFAULT NULL,
	"lng" DOUBLE PRECISION NULL DEFAULT NULL,
	"telat_menit" INTEGER NULL DEFAULT '0',
	"status" VARCHAR(20) NOT NULL DEFAULT 'hadir',
	"keterangan" TEXT NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "absensi_jadwal_id_user_id_key" ("jadwal_id", "user_id"),
	CONSTRAINT "absensi_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "absensi_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "absensi_hadir_check" CHECK ((hadir = ANY (ARRAY[0, 1]))),
	CONSTRAINT "absensi_status_check" CHECK (((status)::text = ANY ((ARRAY['hadir'::character varying, 'izin'::character varying, 'sakit'::character varying, 'telat'::character varying, 'absen'::character varying])::text[])))
);

-- Dumping data for table public.absensi: 82 rows
/*!40000 ALTER TABLE "absensi" DISABLE KEYS */;
INSERT INTO "absensi" ("id", "jadwal_id", "user_id", "hadir", "metode", "checkin_at", "lat", "lng", "telat_menit", "status", "keterangan") VALUES
	(183, 1, 13, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(184, 1, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', 'Dani sama Rifat ada Kuliah Online'),
	(185, 1, 8, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', 'ada bimbingan skripsi, jadi pulang duluan'),
	(186, 1, 6, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(187, 1, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(188, 1, 14, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(189, 1, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(190, 1, 15, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(191, 1, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(192, 1, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(193, 1, 11, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(194, 1, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', 'Dani sama Rifat ada Kuliah Online'),
	(195, 1, 5, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(292, 6, 16, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(293, 6, 13, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(294, 6, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(295, 6, 8, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(296, 6, 6, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Tidak suka badminton'),
	(214, 5, 16, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(297, 6, 7, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(216, 5, 13, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', 'Ada acara grup ITB'),
	(217, 5, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(218, 5, 8, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(219, 5, 6, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Masih Tidur'),
	(220, 5, 7, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(221, 5, 14, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(222, 5, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(223, 5, 15, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(224, 5, 9, 0, 'manual', NULL, NULL, NULL, 0, 'sakit', 'Tidak Tahu'),
	(225, 5, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(226, 5, 11, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(227, 5, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(228, 5, 17, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(229, 5, 5, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Masih Tidur'),
	(298, 6, 14, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Vcat 2x acara intern'),
	(299, 6, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(300, 6, 15, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Muncak with CMD'),
	(301, 6, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Gak di read chat dani'),
	(302, 6, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(303, 6, 11, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Pulkam'),
	(304, 6, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(305, 6, 17, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Pulkam'),
	(306, 6, 5, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Lomba'),
	(123, 2, 13, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(124, 2, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(125, 2, 8, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(126, 2, 6, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(127, 2, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(128, 2, 14, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(129, 2, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(130, 2, 15, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(131, 2, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(132, 2, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(133, 2, 11, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(134, 2, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(135, 2, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(138, 3, 13, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(139, 3, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(140, 3, 8, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(141, 3, 6, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(142, 3, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(143, 3, 14, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(144, 3, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(145, 3, 15, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(146, 3, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(147, 3, 10, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(148, 3, 11, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(149, 3, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(150, 3, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(153, 4, 13, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(154, 4, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(155, 4, 8, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(156, 4, 6, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(157, 4, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(158, 4, 14, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(159, 4, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(160, 4, 15, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(161, 4, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(162, 4, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(163, 4, 11, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(164, 4, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(165, 4, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL);
/*!40000 ALTER TABLE "absensi" ENABLE KEYS */;

-- Dumping structure for table public.badges
CREATE TABLE IF NOT EXISTS "badges" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''badges_id_seq''::regclass)',
	"kode" VARCHAR(50) NOT NULL,
	"nama" VARCHAR(100) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"icon" VARCHAR(50) NULL DEFAULT 'bi-award',
	"warna" VARCHAR(20) NULL DEFAULT 'primary',
	"xp" INTEGER NULL DEFAULT '50',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "badges_kode_key" ("kode")
);

-- Dumping data for table public.badges: -1 rows
/*!40000 ALTER TABLE "badges" DISABLE KEYS */;
INSERT INTO "badges" ("id", "kode", "nama", "deskripsi", "icon", "warna", "xp") VALUES
	(1, 'JOGGING_10', 'Jogging 10x', 'Hadir jogging 10 kali', 'bi-person-running', 'success', 100),
	(2, 'RAJIN_4W', 'Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', 'bi-fire', 'danger', 150),
	(3, 'TOP_ATTEND', 'Top Attendance', 'Top 3 kehadiran bulanan', 'bi-trophy-fill', 'warning', 200),
	(4, 'NIGHT_RUNNER', 'Night Runner', '5x olahraga malam', 'bi-moon-stars', 'dark', 80),
	(5, 'BADMINTON_WARRIOR', 'Badminton Warrior', 'Hadir 10x badminton', 'bi-shield-fill-check', 'info', 120),
	(6, 'FIRST_CHECKIN', 'First Check-in', 'Check-in pertama via QR', 'bi-qr-code-scan', 'primary', 30),
	(7, 'ALL_ROUNDER', 'All Rounder', 'Hadir di 3 jenis olahraga berbeda', 'bi-stars', 'warning', 150),
	(8, 'CONSISTENCY_KING', 'Consistency King', 'Score konsistensi >85%', 'bi-graph-up', 'success', 180),
	(9, 'EARLY_BIRD', 'Early Bird', '5x check-in <10 menit sebelum mulai', 'bi-sun', 'warning', 60),
	(10, 'FORUM_STAR', 'Forum Star', '50 post di forum', 'bi-chat-heart-fill', 'danger', 70);
/*!40000 ALTER TABLE "badges" ENABLE KEYS */;

-- Dumping structure for table public.berita
CREATE TABLE IF NOT EXISTS "berita" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''berita_id_seq''::regclass)',
	"judul" VARCHAR(200) NOT NULL,
	"isi" TEXT NULL DEFAULT NULL,
	"gambar_url" VARCHAR(255) NULL DEFAULT NULL,
	"gambar_file_id" VARCHAR(120) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id")
);

-- Dumping data for table public.berita: -1 rows
/*!40000 ALTER TABLE "berita" DISABLE KEYS */;
INSERT INTO "berita" ("id", "judul", "isi", "gambar_url", "gambar_file_id", "created_at") VALUES
	(1, 'Putri KW dengan Senang Hati Terima Tongkat Estafet dari Gregoria Mercy Raya', '<p><span style="color: rgb(0, 0, 0);">Jakarta - Putri Kusuma Wardani dengan senang hati menerima tongkat estafet dari Gregoria Mariska Tunjung sebagai tulang punggung tunggal putri PBSI.</span></p><p><span style="color: rgb(0, 0, 0);">Gregoria memutuskan mundur dari Pelatnas PBSI pekan lalu. Dia mengundurkan diri karena kondisi kesehatannya yang belum pulih sepenuhnya dari vertigo.</span></p><p><br></p><p><span style="color: rgb(0, 0, 0);">Mundurnya Gregoria membuat Putri KW jadi andalan utama PBSI di nomor tunggal putri. Pebulutangkis berusia 23 tahun itu pun optimis bisa melanjutkan langkah seniornya tersebut.</span></p>', 'https://ik.imagekit.io/ahsansur/sportapp/berita/berita-1779362347_WO6ceqhnq.jpeg', '6a0eea2d5c7cd75eb8cb5ec7', '2026-05-21 10:44:13.957308');
/*!40000 ALTER TABLE "berita" ENABLE KEYS */;

-- Dumping structure for table public.booking
CREATE TABLE IF NOT EXISTS "booking" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''booking_id_seq''::regclass)',
	"tempat_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"tanggal" DATE NOT NULL,
	"jam_mulai" TIME NOT NULL,
	"jam_selesai" TIME NOT NULL,
	"status" VARCHAR(20) NOT NULL DEFAULT 'pending',
	"dp_status" VARCHAR(20) NULL DEFAULT 'unpaid',
	"recurring" VARCHAR(20) NULL DEFAULT NULL,
	"recurring_until" DATE NULL DEFAULT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "booking_idx" ("tempat_id", "tanggal"),
	CONSTRAINT "booking_tempat_id_fkey" FOREIGN KEY ("tempat_id") REFERENCES "tempat" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "booking_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.booking: -1 rows
/*!40000 ALTER TABLE "booking" DISABLE KEYS */;
INSERT INTO "booking" ("id", "tempat_id", "user_id", "tanggal", "jam_mulai", "jam_selesai", "status", "dp_status", "recurring", "recurring_until", "catatan", "created_at") VALUES
	(1, 3, 2, '2026-05-23', '16:00:00', '18:00:00', 'canceled', 'unpaid', NULL, NULL, 'DP', '2026-05-22 00:45:14.355745');
/*!40000 ALTER TABLE "booking" ENABLE KEYS */;

-- Dumping structure for table public.challenge_log
CREATE TABLE IF NOT EXISTS "challenge_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''challenge_log_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"challenge_key" VARCHAR(40) NOT NULL,
	"tanggal" DATE NOT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "challenge_log_user_id_challenge_key_tanggal_key" ("user_id", "challenge_key", "tanggal"),
	CONSTRAINT "challenge_log_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.challenge_log: -1 rows
/*!40000 ALTER TABLE "challenge_log" DISABLE KEYS */;
INSERT INTO "challenge_log" ("id", "user_id", "challenge_key", "tanggal", "catatan", "created_at") VALUES
	(1, 2, 'dzikir_pagi', '2026-05-24', NULL, '2026-05-24 06:27:37.380155'),
	(3, 2, 'subuh_walk', '2026-05-24', NULL, '2026-05-24 06:31:17.872226'),
	(5, 2, 'ayat_harian', '2026-05-24', NULL, '2026-05-24 06:31:30.814959'),
	(7, 4, 'puasa_tasua_asyura', '2026-05-24', NULL, '2026-05-24 13:59:49.864411'),
	(11, 14, 'ayat_harian', '2026-05-24', NULL, '2026-05-24 15:09:15.284908'),
	(12, 3, 'ayat_harian', '2026-05-26', NULL, '2026-05-26 11:31:32.277207'),
	(14, 16, 'ayat_harian', '2026-05-29', NULL, '2026-05-29 10:01:14.497521'),
	(15, 20, 'ayat_harian', '2026-05-29', NULL, '2026-05-29 13:03:20.644834');
/*!40000 ALTER TABLE "challenge_log" ENABLE KEYS */;

-- Dumping structure for table public.challenge_master
CREATE TABLE IF NOT EXISTS "challenge_master" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''challenge_master_id_seq''::regclass)',
	"kunci" VARCHAR(40) NOT NULL,
	"judul" VARCHAR(180) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"icon" VARCHAR(40) NOT NULL DEFAULT 'bi-trophy',
	"warna" VARCHAR(20) NOT NULL DEFAULT 'success',
	"aktif" SMALLINT NOT NULL DEFAULT '1',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "challenge_master_kunci_key" ("kunci")
);

-- Dumping data for table public.challenge_master: -1 rows
/*!40000 ALTER TABLE "challenge_master" DISABLE KEYS */;
INSERT INTO "challenge_master" ("id", "kunci", "judul", "deskripsi", "icon", "warna", "aktif", "created_at") VALUES
	(1, 'ayat_harian', '1 Hari 1 Ayat', 'Baca minimal 1 ayat Al-Qur''an setiap hari.', 'bi-book', 'success', 1, '2026-05-24 08:39:45.801252'),
	(2, 'subuh_walk', 'Subuh Walk Challenge', 'Jalan kaki ≥10 menit setelah sholat Subuh.', 'bi-sunrise', 'warning', 1, '2026-05-24 08:39:45.841177'),
	(3, 'puasa_seninkamis', 'Puasa Senin-Kamis', 'Catat puasa sunnah Senin/Kamis hari ini.', 'bi-droplet-half', 'info', 1, '2026-05-24 08:39:45.887727'),
	(4, 'dzikir_pagi', 'Dzikir Pagi', 'Selesaikan rangkaian dzikir pagi.', 'bi-brightness-high', 'primary', 1, '2026-05-24 08:39:45.927186'),
	(5, 'dzikir_petang', 'Dzikir Petang', 'Selesaikan rangkaian dzikir petang.', 'bi-moon-stars', 'dark', 1, '2026-05-24 08:39:45.966648'),
	(11, 'puasa_ayyamul_bidh', 'Puasa Ayyamul Bidh', 'Puasa 13, 14, 15 Hijriyah (hari putih).', 'bi-moon', 'info', 1, '2026-05-24 09:12:01.835257'),
	(12, 'puasa_daud', 'Puasa Daud', 'Puasa selang-seling: sehari puasa, sehari berbuka.', 'bi-droplet', 'primary', 1, '2026-05-24 09:12:01.875835'),
	(13, 'puasa_syawal', 'Puasa 6 Hari Syawal', 'Puasa 6 hari di bulan Syawal setelah Ramadhan.', 'bi-stars', 'success', 1, '2026-05-24 09:12:01.915993'),
	(14, 'puasa_arafah', 'Puasa Arafah', 'Puasa 9 Dzulhijjah, menghapus dosa 2 tahun.', 'bi-sun', 'warning', 1, '2026-05-24 09:12:01.956198'),
	(15, 'puasa_tasua_asyura', 'Puasa Tasu''a & Asyura', 'Puasa 9 & 10 Muharram.', 'bi-droplet-half', 'dark', 1, '2026-05-24 09:12:01.996228'),
	(16, 'puasa_nisfu_syaban', 'Puasa Nisfu Sya''ban', 'Puasa di pertengahan bulan Sya''ban.', 'bi-moon-stars', 'secondary', 1, '2026-05-24 09:12:02.036358'),
	(17, 'puasa_ramadhan', 'Puasa Ramadhan', 'Puasa wajib di bulan Ramadhan.', 'bi-moon', 'success', 1, '2026-05-24 09:12:02.076612');
/*!40000 ALTER TABLE "challenge_master" ENABLE KEYS */;

-- Dumping structure for table public.chat_forum
CREATE TABLE IF NOT EXISTS "chat_forum" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''chat_forum_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"pesan" TEXT NOT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"parent_id" INTEGER NULL DEFAULT NULL,
	"updated_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "chat_forum_parent_id_fkey" FOREIGN KEY ("parent_id") REFERENCES "chat_forum" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "chat_forum_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.chat_forum: -1 rows
/*!40000 ALTER TABLE "chat_forum" DISABLE KEYS */;
INSERT INTO "chat_forum" ("id", "user_id", "pesan", "created_at", "parent_id", "updated_at") VALUES
	(3, 2, 'Assalamualaikum lagi, ada yang online?', '2026-05-21 11:41:53.489748', NULL, NULL),
	(4, 3, 'wa''alikumussalam', '2026-05-21 15:47:46.367728', NULL, NULL),
	(6, 2, 'Semangat malam. Untuk absen, dilakukan di area sekitar lapang, karena radius absen 150 meter dari lokasi. Terimakasih.', '2026-05-22 16:36:35.671593', NULL, '2026-05-23 05:52:06.486691'),
	(8, 4, 'Semangat pagi. Siapp laksanakan', '2026-05-23 06:18:22.527654', 6, NULL),
	(5, 2, 'siap kawans', '2026-05-22 00:37:27.733498', 4, '2026-05-23 06:42:22.877495'),
	(9, 2, 'Pengumuman, sudah ada kalkulator sehat, bisa dicoba', '2026-05-23 07:05:27.882099', NULL, NULL),
	(10, 4, 'Mantappp', '2026-05-23 16:24:57.427956', 9, NULL),
	(12, 2, 'ada fitur Kalender Hijriyah & Puasa Sunnah , boleh dicek', '2026-05-24 08:48:22.594551', NULL, NULL);
/*!40000 ALTER TABLE "chat_forum" ENABLE KEYS */;

-- Dumping structure for table public.chat_reactions
CREATE TABLE IF NOT EXISTS "chat_reactions" (
	"chat_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"val" SMALLINT NOT NULL,
	PRIMARY KEY ("chat_id", "user_id"),
	CONSTRAINT "chat_reactions_chat_id_fkey" FOREIGN KEY ("chat_id") REFERENCES "chat_forum" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "chat_reactions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "chat_reactions_val_check" CHECK ((val = ANY (ARRAY['-1'::integer, 1])))
);

-- Dumping data for table public.chat_reactions: -1 rows
/*!40000 ALTER TABLE "chat_reactions" DISABLE KEYS */;
INSERT INTO "chat_reactions" ("chat_id", "user_id", "val") VALUES
	(4, 2, 1),
	(6, 2, 1),
	(8, 2, 1),
	(9, 14, 1);
/*!40000 ALTER TABLE "chat_reactions" ENABLE KEYS */;

-- Dumping structure for table public.dm_messages
CREATE TABLE IF NOT EXISTS "dm_messages" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''dm_messages_id_seq''::regclass)',
	"sender_id" INTEGER NOT NULL,
	"receiver_id" INTEGER NOT NULL,
	"pesan" TEXT NOT NULL,
	"read_at" TIMESTAMP NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"delivered_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	INDEX "dm_pair_idx" ("sender_id", "receiver_id", "id"),
	INDEX "dm_receiver_idx" ("receiver_id", "read_at"),
	INDEX "dm_delivered_idx" ("receiver_id", "delivered_at"),
	CONSTRAINT "dm_messages_receiver_id_fkey" FOREIGN KEY ("receiver_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "dm_messages_sender_id_fkey" FOREIGN KEY ("sender_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.dm_messages: 16 rows
/*!40000 ALTER TABLE "dm_messages" DISABLE KEYS */;
INSERT INTO "dm_messages" ("id", "sender_id", "receiver_id", "pesan", "read_at", "created_at", "delivered_at") VALUES
	(2, 2, 4, '💪', '2026-05-24 00:23:28.819379', '2026-05-24 00:22:18.066754', NULL),
	(1, 2, 4, 'Semangat Malam', '2026-05-24 00:23:28.819379', '2026-05-24 00:22:10.053792', NULL),
	(5, 2, 15, 'Nif', NULL, '2026-05-24 09:43:56.986858', NULL),
	(4, 2, 4, '⚽', '2026-05-24 09:44:44.72952', '2026-05-24 09:43:38.545424', NULL),
	(8, 2, 4, 'Oh iya dan, berapa lagi bayaran th?', '2026-05-24 13:55:26.780213', '2026-05-24 13:30:01.408808', NULL),
	(7, 2, 4, 'Lg dmn dan', '2026-05-24 13:55:26.780213', '2026-05-24 09:45:59.090019', NULL),
	(13, 2, 14, 'Kumaha kamari vcat han aman', '2026-05-24 15:08:24.63053', '2026-05-24 14:47:13.573034', NULL),
	(10, 2, 4, 'Sip', '2026-05-24 16:45:35.24327', '2026-05-24 14:05:24.801144', '2026-05-24 16:45:35.24327'),
	(3, 4, 2, 'Oh iya kng semangat malam', '2026-05-24 00:23:39.489397', '2026-05-24 00:23:37.356513', '2026-05-24 16:48:31.503473'),
	(6, 4, 2, 'Kuy', '2026-05-24 09:45:51.456394', '2026-05-24 09:44:50.284804', '2026-05-24 16:48:31.503473'),
	(9, 4, 2, 'Done kang', '2026-05-24 14:05:17.908667', '2026-05-24 13:55:49.348383', '2026-05-24 16:48:31.503473'),
	(14, 14, 2, 'Aman', '2026-05-24 15:28:25.822548', '2026-05-24 15:08:38.785014', '2026-05-24 16:48:31.503473'),
	(17, 4, 2, 'kang', '2026-05-24 16:48:33.141645', '2026-05-24 16:45:56.109485', '2026-05-24 16:48:31.503473'),
	(18, 4, 2, 'tes tes', '2026-05-24 16:48:33.141645', '2026-05-24 16:47:46.57744', '2026-05-24 16:48:31.503473'),
	(15, 2, 14, 'Sip', '2026-05-24 19:45:47.106604', '2026-05-24 15:28:37.998097', '2026-05-24 19:45:47.106604'),
	(16, 2, 14, 'Alhamdulilah', '2026-05-24 19:45:47.106604', '2026-05-24 15:29:03.95966', '2026-05-24 19:45:47.106604'),
	(11, 2, 3, 'Assalamualaikum', '2026-05-26 11:30:29.729809', '2026-05-24 14:40:34.244062', '2026-05-26 11:30:29.729809'),
	(12, 2, 3, '🏸', '2026-05-26 11:30:29.729809', '2026-05-24 14:40:41.349807', '2026-05-26 11:30:29.729809');
/*!40000 ALTER TABLE "dm_messages" ENABLE KEYS */;

-- Dumping structure for table public.doa_aamiin
CREATE TABLE IF NOT EXISTS "doa_aamiin" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''doa_aamiin_id_seq''::regclass)',
	"doa_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "doa_aamiin_doa_id_user_id_key" ("doa_id", "user_id"),
	CONSTRAINT "doa_aamiin_doa_id_fkey" FOREIGN KEY ("doa_id") REFERENCES "doa_request" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "doa_aamiin_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.doa_aamiin: -1 rows
/*!40000 ALTER TABLE "doa_aamiin" DISABLE KEYS */;
/*!40000 ALTER TABLE "doa_aamiin" ENABLE KEYS */;

-- Dumping structure for table public.doa_request
CREATE TABLE IF NOT EXISTS "doa_request" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''doa_request_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"isi" TEXT NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "doa_request_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.doa_request: -1 rows
/*!40000 ALTER TABLE "doa_request" DISABLE KEYS */;
/*!40000 ALTER TABLE "doa_request" ENABLE KEYS */;

-- Dumping structure for table public.doa_user
CREATE TABLE IF NOT EXISTS "doa_user" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''doa_user_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"judul" VARCHAR(180) NOT NULL,
	"arab" TEXT NOT NULL,
	"terjemah" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "doa_user_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.doa_user: -1 rows
/*!40000 ALTER TABLE "doa_user" DISABLE KEYS */;
/*!40000 ALTER TABLE "doa_user" ENABLE KEYS */;

-- Dumping structure for table public.donasi_krb
CREATE TABLE IF NOT EXISTS "donasi_krb" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''donasi_krb_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"nama" VARCHAR(120) NOT NULL,
	"jumlah" BIGINT NOT NULL,
	"metode" VARCHAR(30) NULL DEFAULT 'transfer',
	"bank" VARCHAR(40) NULL DEFAULT NULL,
	"no_ref" VARCHAR(60) NULL DEFAULT NULL,
	"bukti_path" VARCHAR(500) NULL DEFAULT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"status" VARCHAR(20) NULL DEFAULT 'pending',
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "donasi_krb_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.donasi_krb: -1 rows
/*!40000 ALTER TABLE "donasi_krb" DISABLE KEYS */;
/*!40000 ALTER TABLE "donasi_krb" ENABLE KEYS */;

-- Dumping structure for table public.event
CREATE TABLE IF NOT EXISTS "event" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''event_id_seq''::regclass)',
	"nama" VARCHAR(200) NOT NULL,
	"jenis" VARCHAR(50) NOT NULL,
	"tipe" VARCHAR(30) NOT NULL DEFAULT 'challenge',
	"deskripsi" TEXT NULL DEFAULT NULL,
	"tanggal_mulai" DATE NOT NULL,
	"tanggal_selesai" DATE NULL DEFAULT NULL,
	"hadiah" TEXT NULL DEFAULT NULL,
	"status" VARCHAR(20) NOT NULL DEFAULT 'open',
	"banner_url" TEXT NULL DEFAULT NULL,
	"created_by" INTEGER NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "event_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION
);

-- Dumping data for table public.event: -1 rows
/*!40000 ALTER TABLE "event" DISABLE KEYS */;
/*!40000 ALTER TABLE "event" ENABLE KEYS */;

-- Dumping structure for table public.event_match
CREATE TABLE IF NOT EXISTS "event_match" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''event_match_id_seq''::regclass)',
	"event_id" INTEGER NOT NULL,
	"round" INTEGER NOT NULL DEFAULT '1',
	"tim_a" INTEGER NULL DEFAULT NULL,
	"tim_b" INTEGER NULL DEFAULT NULL,
	"score_a" INTEGER NULL DEFAULT '0',
	"score_b" INTEGER NULL DEFAULT '0',
	"pemenang" INTEGER NULL DEFAULT NULL,
	"jadwal_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "event_match_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "event" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "event_match_pemenang_fkey" FOREIGN KEY ("pemenang") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT "event_match_tim_a_fkey" FOREIGN KEY ("tim_a") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT "event_match_tim_b_fkey" FOREIGN KEY ("tim_b") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION
);

-- Dumping data for table public.event_match: -1 rows
/*!40000 ALTER TABLE "event_match" DISABLE KEYS */;
/*!40000 ALTER TABLE "event_match" ENABLE KEYS */;

-- Dumping structure for table public.event_peserta
CREATE TABLE IF NOT EXISTS "event_peserta" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''event_peserta_id_seq''::regclass)',
	"event_id" INTEGER NOT NULL,
	"tim_id" INTEGER NULL DEFAULT NULL,
	"user_id" INTEGER NULL DEFAULT NULL,
	"score" NUMERIC(10,2) NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"status" VARCHAR(12) NULL DEFAULT NULL,
	"keterangan" TEXT NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "event_peserta_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "event" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "event_peserta_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "event_peserta_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.event_peserta: -1 rows
/*!40000 ALTER TABLE "event_peserta" DISABLE KEYS */;
/*!40000 ALTER TABLE "event_peserta" ENABLE KEYS */;

-- Dumping structure for table public.fcm_tokens
CREATE TABLE IF NOT EXISTS "fcm_tokens" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''fcm_tokens_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"token" TEXT NOT NULL,
	"device" VARCHAR(100) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "fcm_tokens_user_id_token_key" ("user_id", "token"),
	CONSTRAINT "fcm_tokens_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.fcm_tokens: -1 rows
/*!40000 ALTER TABLE "fcm_tokens" DISABLE KEYS */;
/*!40000 ALTER TABLE "fcm_tokens" ENABLE KEYS */;

-- Dumping structure for table public.guest_messages
CREATE TABLE IF NOT EXISTS "guest_messages" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''guest_messages_id_seq''::regclass)',
	"owner_user_id" INTEGER NOT NULL,
	"sender_user_id" INTEGER NOT NULL,
	"parent_id" INTEGER NULL DEFAULT NULL,
	"pesan" TEXT NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "guest_messages_parent_id_fkey" FOREIGN KEY ("parent_id") REFERENCES "guest_messages" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "guest_messages_owner_user_id_fkey" FOREIGN KEY ("owner_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "guest_messages_sender_user_id_fkey" FOREIGN KEY ("sender_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.guest_messages: -1 rows
/*!40000 ALTER TABLE "guest_messages" DISABLE KEYS */;
INSERT INTO "guest_messages" ("id", "owner_user_id", "sender_user_id", "parent_id", "pesan", "created_at", "updated_at") VALUES
	(1, 3, 2, NULL, 'Berqurban Yuks...👍', '2026-05-23 07:02:06.611451', NULL),
	(2, 14, 2, NULL, '👋 Semangat Pagi', '2026-05-23 07:18:03.654891', NULL),
	(3, 14, 2, NULL, '👋 Assalamualaikum', '2026-05-23 07:51:17.711997', NULL),
	(5, 2, 4, NULL, 'Kang, kumaha kabarna, sehat?', '2026-05-23 08:05:05.864711', NULL),
	(6, 17, 4, NULL, '👋 🤗', '2026-05-23 09:15:10.057807', NULL),
	(7, 2, 2, 5, 'Alhamdulilah dan', '2026-05-23 21:17:33.682802', NULL),
	(8, 4, 2, NULL, 'Sudah dikirim sisa dp modul 1 ya dan', '2026-05-24 13:44:59.631205', NULL),
	(9, 4, 4, 8, 'Siapp sudah masuk, makasih kang', '2026-05-24 13:55:11.727449', NULL),
	(10, 4, 2, 8, 'Okay', '2026-05-24 14:05:36.612789', NULL);
/*!40000 ALTER TABLE "guest_messages" ENABLE KEYS */;

-- Dumping structure for table public.islami_artikel
CREATE TABLE IF NOT EXISTS "islami_artikel" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''islami_artikel_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"judul" VARCHAR(180) NOT NULL,
	"isi" TEXT NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "islami_artikel_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.islami_artikel: -1 rows
/*!40000 ALTER TABLE "islami_artikel" DISABLE KEYS */;
INSERT INTO "islami_artikel" ("id", "user_id", "judul", "isi", "created_at", "updated_at") VALUES
	(1, NULL, 'Pola Tidur ala Rasulullah', 'Tidur lebih awal setelah Isya, bangun sebelum Subuh. Posisi tidur miring ke kanan, dengan dzikir sebelum tidur.', '2026-05-23 23:43:21.290195', NULL),
	(2, NULL, 'Makan Tidak Berlebihan', 'Sepertiga untuk makanan, sepertiga air, sepertiga udara. Pola makan yang menjaga kesehatan jangka panjang.', '2026-05-23 23:43:21.33081', NULL),
	(3, NULL, 'Berbekam (Hijamah)', 'Sunnah Nabi yang baik dilakukan di tanggal 17, 19, 21 bulan hijriyah untuk membantu sirkulasi darah.', '2026-05-23 23:43:21.370454', NULL),
	(4, NULL, 'Madu, Habbatussauda, Kurma', 'Tiga makanan sunnah yang memiliki manfaat kesehatan luar biasa.', '2026-05-23 23:43:21.410013', NULL),
	(5, NULL, 'Berjalan Kaki & Olahraga', 'Rasulullah menganjurkan memanah, berenang, dan menunggang kuda. Bergeraklah setiap hari.', '2026-05-23 23:43:21.449408', NULL);
/*!40000 ALTER TABLE "islami_artikel" ENABLE KEYS */;

-- Dumping structure for table public.islami_badges
CREATE TABLE IF NOT EXISTS "islami_badges" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''islami_badges_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"badge_key" VARCHAR(40) NOT NULL,
	"earned_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "islami_badges_user_id_badge_key_key" ("user_id", "badge_key"),
	CONSTRAINT "islami_badges_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.islami_badges: -1 rows
/*!40000 ALTER TABLE "islami_badges" DISABLE KEYS */;
/*!40000 ALTER TABLE "islami_badges" ENABLE KEYS */;

-- Dumping structure for table public.islami_kajian
CREATE TABLE IF NOT EXISTS "islami_kajian" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''islami_kajian_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"judul" VARCHAR(180) NOT NULL,
	"isi" TEXT NULL DEFAULT NULL,
	"link_video" VARCHAR(255) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"penulis" VARCHAR(120) NULL DEFAULT NULL,
	"tipe" VARCHAR(20) NULL DEFAULT 'buku',
	"link_web" VARCHAR(500) NULL DEFAULT NULL,
	"pdf_path" VARCHAR(500) NULL DEFAULT NULL,
	"updated_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "islami_kajian_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.islami_kajian: -1 rows
/*!40000 ALTER TABLE "islami_kajian" DISABLE KEYS */;
/*!40000 ALTER TABLE "islami_kajian" ENABLE KEYS */;

-- Dumping structure for table public.islami_quotes
CREATE TABLE IF NOT EXISTS "islami_quotes" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''islami_quotes_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"isi" TEXT NOT NULL,
	"sumber" VARCHAR(120) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "islami_quotes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.islami_quotes: -1 rows
/*!40000 ALTER TABLE "islami_quotes" DISABLE KEYS */;
/*!40000 ALTER TABLE "islami_quotes" ENABLE KEYS */;

-- Dumping structure for table public.islami_streak
CREATE TABLE IF NOT EXISTS "islami_streak" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''islami_streak_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"tanggal" DATE NOT NULL,
	"quran_done" SMALLINT NOT NULL DEFAULT '0',
	"dzikir_pagi" SMALLINT NOT NULL DEFAULT '0',
	"dzikir_petang" SMALLINT NOT NULL DEFAULT '0',
	"doa_done" SMALLINT NOT NULL DEFAULT '0',
	"sholat_count" SMALLINT NOT NULL DEFAULT '0',
	"subuh_walk" SMALLINT NOT NULL DEFAULT '0',
	"sedekah" SMALLINT NOT NULL DEFAULT '0',
	"poin" INTEGER NOT NULL DEFAULT '0',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "islami_streak_user_id_tanggal_key" ("user_id", "tanggal"),
	CONSTRAINT "islami_streak_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.islami_streak: -1 rows
/*!40000 ALTER TABLE "islami_streak" DISABLE KEYS */;
INSERT INTO "islami_streak" ("id", "user_id", "tanggal", "quran_done", "dzikir_pagi", "dzikir_petang", "doa_done", "sholat_count", "subuh_walk", "sedekah", "poin") VALUES
	(1, 2, '2026-05-24', 1, 1, 0, 0, 0, 1, 0, 90),
	(10, 14, '2026-05-24', 1, 0, 0, 0, 0, 0, 0, 10),
	(11, 3, '2026-05-26', 1, 0, 0, 0, 0, 0, 0, 20),
	(13, 16, '2026-05-29', 1, 0, 0, 0, 0, 0, 0, 10),
	(14, 20, '2026-05-29', 1, 0, 0, 0, 0, 0, 0, 10);
/*!40000 ALTER TABLE "islami_streak" ENABLE KEYS */;

-- Dumping structure for table public.jadwal
CREATE TABLE IF NOT EXISTS "jadwal" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''jadwal_id_seq''::regclass)',
	"tanggal" DATE NOT NULL,
	"bulan" VARCHAR(20) NOT NULL,
	"minggu_ke" VARCHAR(4) NOT NULL,
	"jenis" VARCHAR(60) NOT NULL,
	"tempat" VARCHAR(180) NOT NULL,
	"koordinator_id" INTEGER NULL DEFAULT NULL,
	"konten_obrolan" TEXT NULL DEFAULT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"tempat_id" INTEGER NULL DEFAULT NULL,
	"durasi_menit" INTEGER NULL DEFAULT NULL,
	"tim_id" INTEGER NULL DEFAULT NULL,
	"event_id" INTEGER NULL DEFAULT NULL,
	"jam_mulai" TIME NULL DEFAULT NULL,
	"jam_selesai" TIME NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "jadwal_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "event" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tempat_id_fkey" FOREIGN KEY ("tempat_id") REFERENCES "tempat" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.jadwal: -1 rows
/*!40000 ALTER TABLE "jadwal" DISABLE KEYS */;
INSERT INTO "jadwal" ("id", "tanggal", "bulan", "minggu_ke", "jenis", "tempat", "koordinator_id", "konten_obrolan", "catatan", "created_at", "tempat_id", "durasi_menit", "tim_id", "event_id", "jam_mulai", "jam_selesai") VALUES
	(1, '2026-04-16', 'April', 'W3', 'Jogging', 'SR-Panyileukan', 2, '<p>Struktur DK, Indk, Sjrh</p>', '<ol><li>Dedi ada bimbingan skripsi, jadi pulang </li><li>Dani sama Rifat ada Kuliah Online</li></ol>', '2026-05-19 07:50:23.02801', 4, 240, NULL, NULL, '06:10:00', '10:00:00'),
	(2, '2026-04-22', 'April', 'W4', 'Badminton', 'GOR Mayasari', 3, '<p>Tidak Ada</p>', '<p>Tidak Ada</p>', '2026-05-19 07:51:01.708229', 2, 120, NULL, NULL, '16:00:00', '18:00:00'),
	(3, '2026-05-03', 'May', 'W1', 'Jogging', 'Summarecon', 3, '<p>Sharing Hikmah Per Orang</p>', '<ol><li>Dedi Jalan dari Kosan ke Summarecon </li><li>Dedi Cedera kaki</li></ol>', '2026-05-19 07:51:58.579444', 5, 210, NULL, NULL, '07:30:00', '10:00:00'),
	(4, '2026-05-09', 'May', 'W2', 'Futsal', 'GOR Adiguna', 3, '<p>Tidak Ada</p>', '<p>Dedi Jalan dari Kosan ke Adiguna</p>', '2026-05-19 07:52:37.974739', 1, 60, NULL, NULL, '16:00:00', '17:00:00'),
	(5, '2026-05-17', 'May', 'W3', 'Badminton', 'GOR Purbaya', 4, '<p>Sharing Hikmah Per Orang</p>', '<ol><li>Rizal (Rihlah bersama adik Mentornya) </li><li>Fajar S (Part time)</li></ol>', '2026-05-19 07:53:14.399509', 3, 120, NULL, NULL, '08:00:00', '10:00:00'),
	(6, '2026-05-23', 'May', 'W4', 'Badminton', 'GOR Gaza', 3, '<p><br></p>', '<p><br></p>', '2026-05-21 15:45:32.456543', 14, 120, NULL, NULL, '16:00:00', '18:00:00'),
	(7, '2026-06-01', 'June', 'W1', 'Jogging', 'Parkiran Taman Sumringah', 2, '', '', '2026-05-26 11:30:41.092374', 5, 120, NULL, NULL, '06:30:00', '09:00:00');
/*!40000 ALTER TABLE "jadwal" ENABLE KEYS */;

-- Dumping structure for table public.jenis_olahraga
CREATE TABLE IF NOT EXISTS "jenis_olahraga" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''jenis_olahraga_id_seq''::regclass)',
	"nama" VARCHAR(60) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "jenis_olahraga_nama_key" ("nama")
);

-- Dumping data for table public.jenis_olahraga: -1 rows
/*!40000 ALTER TABLE "jenis_olahraga" DISABLE KEYS */;
INSERT INTO "jenis_olahraga" ("id", "nama", "deskripsi", "created_at") VALUES
	(1, 'Jogging', 'Lari santai outdoor, fokus pada durasi dan jarak.', '2026-05-21 00:40:55.617378'),
	(2, 'Badminton', 'Pertandingan ganda/tunggal di GOR.', '2026-05-21 00:40:55.617378'),
	(3, 'Futsal', 'Sepak bola dalam ruangan, 5 vs 5.', '2026-05-21 00:40:55.617378'),
	(7, 'Basket', 'Mari mainkan bersama', '2026-05-21 15:50:18.482263'),
	(8, 'Hiking', 'Ngalam bagian healing', '2026-05-22 16:19:49.34839'),
	(9, 'Camping', 'Bersama dalam malam', '2026-05-22 16:20:06.583601'),
	(10, 'Gerak Jalan', 'Menjaga intensitas kaki', '2026-05-22 16:20:27.018665'),
	(5, 'Renang', 'Renang gaya yang bagian dari edukasi', '2026-05-21 00:40:55.617378'),
	(11, 'Biliard', 'Kuy, maen bola kecil', '2026-05-23 04:33:57.391667'),
	(12, 'Ping Pong', 'Bola Bolaan Kecil', '2026-05-23 06:40:37.374439');
/*!40000 ALTER TABLE "jenis_olahraga" ENABLE KEYS */;

-- Dumping structure for table public.login_attempts
CREATE TABLE IF NOT EXISTS "login_attempts" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''login_attempts_id_seq''::regclass)',
	"email" VARCHAR(150) NULL DEFAULT NULL,
	"ip" VARCHAR(64) NULL DEFAULT NULL,
	"success" SMALLINT NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id")
);

-- Dumping data for table public.login_attempts: 172 rows
/*!40000 ALTER TABLE "login_attempts" DISABLE KEYS */;
INSERT INTO "login_attempts" ("id", "email", "ip", "success", "created_at") VALUES
	(1, 'firdam@sport.local', '::1', 1, '2026-05-22 00:12:26.427246'),
	(2, 'firdam@sport.local', '::1', 1, '2026-05-22 00:17:34.01573'),
	(3, 'firdam@sport.local', '::1', 1, '2026-05-22 01:29:24.816901'),
	(4, 'firdam@sport.local', '::1', 1, '2026-05-22 02:37:01.677462'),
	(5, 'firdam@sport.local', '::1', 1, '2026-05-22 02:38:41.215681'),
	(6, 'firdam@sport.local', '::1', 1, '2026-05-22 02:39:11.674584'),
	(7, 'firdam@sport.local', '::1', 1, '2026-05-22 02:43:33.266834'),
	(8, 'firdam@sport.local', '::1', 1, '2026-05-22 02:44:29.827481'),
	(9, 'firdam@sport.local', '::1', 1, '2026-05-22 03:00:43.311588'),
	(10, 'dedi@sport.local', '::1', 1, '2026-05-22 03:22:39.113084'),
	(11, 'firdam@sport.local', '::1', 1, '2026-05-22 03:48:44.556657'),
	(12, 'firdam@sport.local', '::1', 1, '2026-05-22 04:08:55.248041'),
	(13, 'dedi@sport.local', '::1', 1, '2026-05-22 04:15:04.752104'),
	(14, 'firdam@sport.local', '::1', 1, '2026-05-22 04:45:06.775472'),
	(15, 'firdam@sport.local', '::1', 0, '2026-05-22 04:47:57.45418'),
	(16, 'firdam@sport.local', '::1', 1, '2026-05-22 04:48:04.754668'),
	(17, 'firdam@local.sport', '::1', 0, '2026-05-22 04:52:05.704754'),
	(18, 'firdam@sport.local', '::1', 0, '2026-05-22 04:52:22.255597'),
	(19, 'firdam@sport.local', '::1', 1, '2026-05-22 04:52:50.653865'),
	(20, 'firdam@sport.local', '::1', 1, '2026-05-22 05:25:13.391183'),
	(21, 'firdam@sport.local', '::1', 1, '2026-05-22 05:26:07.704106'),
	(22, 'firdam@sport.local', '::1', 1, '2026-05-22 05:47:39.216339'),
	(23, 'aziz@sport.local', '::1', 1, '2026-05-22 05:47:40.208118'),
	(24, 'firdam@sport.local', '::1', 1, '2026-05-22 05:48:56.387582'),
	(25, 'firdam@sport.local', '::1', 1, '2026-05-22 05:53:55.788381'),
	(26, 'firdam@sport.local', '::1', 1, '2026-05-22 06:10:21.948608'),
	(27, 'firdam@sport.local', '::1', 1, '2026-05-22 06:15:10.512396'),
	(28, 'firdam@sport.local', '::1', 0, '2026-05-22 06:17:52.407287'),
	(29, 'firdam@sport.local', '::1', 1, '2026-05-22 06:18:02.606331'),
	(30, 'firdam@sport.local', '::1', 0, '2026-05-22 06:55:25.922506'),
	(31, 'firdam@sport.local', '::1', 1, '2026-05-22 06:55:39.323671'),
	(32, 'rizalsaad1405@gmail.com', '::1', 1, '2026-05-22 09:25:59.055948'),
	(33, 'rizalsaad1405@gmail.com', '::1', 1, '2026-05-22 09:26:01.152729'),
	(34, 'adith@sport.local', '::1', 0, '2026-05-22 09:26:12.665261'),
	(35, 'adithsetiawan62@gmail.com', '::1', 0, '2026-05-22 09:26:28.252302'),
	(36, 'farhan@sport.local', '::1', 1, '2026-05-22 09:26:42.758253'),
	(37, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:15.67298'),
	(38, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:17.662859'),
	(39, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:21.063629'),
	(40, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:22.960921'),
	(41, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:24.956696'),
	(42, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:26.959754'),
	(43, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:29.063756'),
	(44, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-22 09:27:31.577118'),
	(45, 'firdam@sport.local', '::1', 0, '2026-05-22 09:32:01.756689'),
	(46, 'firdam@sport.local', '::1', 1, '2026-05-22 09:32:11.957059'),
	(47, 'dani@sport.local', '::1', 1, '2026-05-22 10:33:17.332064'),
	(48, 'firdam@sport.local', '::1', 1, '2026-05-22 12:15:52.764111'),
	(49, 'firdam@sport.local', '::1', 1, '2026-05-22 15:22:45.07098'),
	(50, 'firdam@sport.local', '::1', 1, '2026-05-22 16:02:56.558954'),
	(51, 'firdam@sport.local', '::1', 1, '2026-05-22 16:50:07.36065'),
	(52, 'firdam@sport.local', '::1', 1, '2026-05-22 16:50:29.760719'),
	(53, 'farhan@sport.local', '::1', 1, '2026-05-22 20:32:57.610004'),
	(54, 'firdam@sport.local', '::1', 1, '2026-05-23 04:32:52.006381'),
	(55, 'firdam@sport.local', '::1', 1, '2026-05-23 05:11:39.051908'),
	(56, 'firdam@sport.local', '::1', 1, '2026-05-23 05:45:57.544728'),
	(57, 'firdam@sport.local', '::1', 1, '2026-05-23 05:57:10.94672'),
	(58, 'firdam@sport.local', '::1', 1, '2026-05-23 05:58:14.043684'),
	(59, 'firdam@sport.local', '::1', 1, '2026-05-23 06:01:13.646096'),
	(60, 'dani@sport.local', '::1', 1, '2026-05-23 06:16:18.049163'),
	(61, 'firdam@sport.local', '::1', 1, '2026-05-23 06:40:21.467383'),
	(62, 'firdam@sport.local', '::1', 1, '2026-05-23 07:00:19.265408'),
	(63, 'firdam@sport.local', '::1', 1, '2026-05-23 07:17:49.750471'),
	(64, 'firdam@sport.local', '::1', 1, '2026-05-23 07:51:09.666772'),
	(65, 'firdam@sport.local', '::1', 1, '2026-05-23 07:53:56.730811'),
	(66, 'hanif@sport.local', '::1', 1, '2026-05-23 07:55:42.030535'),
	(67, 'firdam@sport.local', '::1', 1, '2026-05-23 08:00:14.234138'),
	(68, 'firdam@sport.local', '::1', 1, '2026-05-23 08:00:50.925963'),
	(69, 'hanif@sport.local', '::1', 1, '2026-05-23 08:01:10.53084'),
	(70, 'firdam@sport.local', '::1', 1, '2026-05-23 08:01:54.533101'),
	(71, 'firdam@sport.local', '::1', 1, '2026-05-23 08:01:57.43081'),
	(72, 'dani@sport.local', '::1', 1, '2026-05-23 08:04:25.631009'),
	(73, 'firdam@sport.local', '::1', 1, '2026-05-23 08:17:17.036734'),
	(74, 'hanif@sport.local', '::1', 1, '2026-05-23 08:17:57.438327'),
	(75, 'firdam@sport.local', '::1', 1, '2026-05-23 08:18:23.336663'),
	(76, 'farhan@sport.local', '::1', 0, '2026-05-23 09:06:58.780826'),
	(77, 'farhan@sport.local', '::1', 1, '2026-05-23 09:07:15.78216'),
	(78, 'dani@sport.local', '::1', 1, '2026-05-23 09:13:09.993464'),
	(79, 'rifat@sport.local', '::1', 1, '2026-05-23 09:13:59.298529'),
	(80, 'rifat@sport.local', '::1', 1, '2026-05-23 09:14:02.285043'),
	(81, 'rifat@sport.local', '::1', 1, '2026-05-23 09:14:03.576288'),
	(82, 'firdam@sport.local', '::1', 1, '2026-05-23 09:14:49.881582'),
	(83, 'rifat@sport.local', '::1', 0, '2026-05-23 09:14:51.184753'),
	(84, 'rifat@sport.local', '::1', 1, '2026-05-23 09:15:25.480309'),
	(85, 'dani@sport.local', '::1', 1, '2026-05-23 09:16:56.881765'),
	(86, 'firdam@sport.local', '::1', 1, '2026-05-23 10:58:45.370556'),
	(87, 'firdam@sport.local', '::1', 1, '2026-05-23 10:58:48.364178'),
	(88, 'firdam@sport.local', '::1', 1, '2026-05-23 11:26:02.090968'),
	(89, 'firdam@sport.local', '::1', 1, '2026-05-23 14:11:11.46566'),
	(90, 'firdam@sport.local', '::1', 1, '2026-05-23 14:32:36.622198'),
	(91, 'dani@sport.local', '::1', 1, '2026-05-23 15:55:35.428255'),
	(92, 'adithsetiawan62@gmail.com', '::1', 0, '2026-05-23 15:56:11.031084'),
	(93, 'adithsetiawan62@gmail.com', '::1', 0, '2026-05-23 15:56:38.930822'),
	(94, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-23 15:56:58.428601'),
	(95, 'dani@sport.local', '::1', 1, '2026-05-23 16:24:12.85294'),
	(96, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-23 16:24:48.460409'),
	(97, 'firdam@sport.local', '::1', 1, '2026-05-23 16:24:55.46588'),
	(98, 'firdam@sport.local', '::1', 1, '2026-05-23 16:24:59.058688'),
	(99, 'faiz@sport.local', '::1', 1, '2026-05-23 16:26:33.246502'),
	(100, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-23 16:27:59.048286'),
	(101, 'firdam@sport.local', '::1', 1, '2026-05-23 17:01:18.09499'),
	(102, 'firdam@sport.local', '::1', 1, '2026-05-23 17:01:21.102968'),
	(103, 'dedi@sport.local', '::1', 1, '2026-05-23 17:10:11.295735'),
	(104, 'firdam@sport.local', '::1', 1, '2026-05-23 18:31:01.491479'),
	(105, 'firdam@sport.local', '::1', 1, '2026-05-23 18:31:04.477134'),
	(106, 'firdam@sport.local', '::1', 1, '2026-05-23 18:54:13.463847'),
	(107, 'firdam@sport.local', '::1', 1, '2026-05-23 19:05:44.174095'),
	(108, 'farhan@sport.local', '::1', 1, '2026-05-23 19:06:33.775824'),
	(109, 'farhan@sport.local', '::1', 1, '2026-05-23 19:06:36.865871'),
	(110, 'firdam@sport.local', '::1', 1, '2026-05-23 19:10:43.370673'),
	(111, 'firdam@sport.local', '::1', 1, '2026-05-23 21:15:16.789076'),
	(112, 'firdam@sport.local', '::1', 1, '2026-05-23 21:16:21.282745'),
	(113, 'firdam@sport.local', '::1', 1, '2026-05-23 21:16:24.694205'),
	(114, 'hanif@sport.local', '::1', 1, '2026-05-23 21:18:46.803703'),
	(115, 'hanif@sport.local', '::1', 1, '2026-05-23 21:18:47.990227'),
	(116, 'firdam@sport.local', '::1', 1, '2026-05-23 21:21:22.500638'),
	(117, 'firdam@sport.local', '::1', 1, '2026-05-23 21:21:23.688742'),
	(118, 'firdam@sport.local', '::1', 1, '2026-05-23 23:25:04.818341'),
	(119, 'firdam@sport.local', '::1', 1, '2026-05-23 23:39:33.740411'),
	(120, 'firdam@sport.local', '::1', 1, '2026-05-23 23:39:38.048722'),
	(121, 'firdam@sport.local', '::1', 1, '2026-05-23 23:51:18.536705'),
	(122, 'firdam@sport.local', '::1', 1, '2026-05-23 23:51:19.736515'),
	(123, 'firdamdamsasmita@upi.edu', '::1', 1, '2026-05-23 23:52:15.352602'),
	(124, 'firdamdamsasmita@upi.edu', '::1', 1, '2026-05-23 23:52:17.741655'),
	(125, 'firdam@sport.local', '::1', 0, '2026-05-24 00:10:00.031515'),
	(126, 'firdam@sport.local', '::1', 1, '2026-05-24 00:10:24.336'),
	(127, 'firdam@sport.local', '::1', 1, '2026-05-24 00:10:25.537046'),
	(128, 'firdam@sport.local', '::1', 1, '2026-05-24 00:13:55.436265'),
	(129, 'firdam@sport.local', '::1', 0, '2026-05-24 00:16:22.439664'),
	(130, 'firdam@sport.local', '::1', 1, '2026-05-24 00:16:39.039698'),
	(131, 'tes@sport.local', '::1', 1, '2026-05-24 00:18:11.538797'),
	(132, 'firdam@sport.local', '::1', 1, '2026-05-24 00:18:48.337171'),
	(133, 'dani@sport.local', '::1', 1, '2026-05-24 00:23:05.738692'),
	(134, 'firdam@sport.local', '::1', 1, '2026-05-24 00:34:56.950419'),
	(135, 'firdam@sport.local', '::1', 1, '2026-05-24 00:34:58.138956'),
	(136, 'firdam@sport.local', '::1', 1, '2026-05-24 06:00:22.471063'),
	(137, 'firdam@sport.local', '::1', 1, '2026-05-24 06:00:23.667252'),
	(138, 'firdam@sport.local', '::1', 1, '2026-05-24 07:19:08.653942'),
	(139, 'firdam@sport.local', '::1', 1, '2026-05-24 07:24:04.104817'),
	(140, 'firdam@sport.local', '::1', 0, '2026-05-24 07:56:40.571535'),
	(141, 'firdam@sport.local', '::1', 0, '2026-05-24 07:56:48.967109'),
	(142, 'firdam@sport.local', '::1', 0, '2026-05-24 07:56:59.978372'),
	(143, 'firdam@sport.local', '::1', 0, '2026-05-24 07:57:14.366125'),
	(144, 'dani@sport.local', '::1', 0, '2026-05-24 07:57:46.577606'),
	(145, 'firdam@sport.local', '::1', 0, '2026-05-24 08:00:06.864485'),
	(146, 'firdam@sport.local', '::1', 0, '2026-05-24 08:00:44.16464'),
	(147, 'firdam@sport.local', '::1', 1, '2026-05-24 08:10:46.414771'),
	(148, 'firdam@sport.local', '::1', 1, '2026-05-24 08:40:25.235493'),
	(149, 'firdam@sport.local', '::1', 0, '2026-05-24 08:54:52.534767'),
	(150, 'firdam@sport.local', '::1', 1, '2026-05-24 08:55:11.435494'),
	(151, 'firdam@sport.local', '::1', 1, '2026-05-24 08:55:38.835667'),
	(152, 'firdam@sport.local', '::1', 1, '2026-05-24 08:56:14.036775'),
	(153, 'firdam@sport.local', '::1', 1, '2026-05-24 08:56:15.137174'),
	(154, 'tes@sport.local', '::1', 1, '2026-05-24 09:05:12.035483'),
	(155, 'tes@sport.local', '::1', 1, '2026-05-24 09:05:14.240911'),
	(156, 'firdam@sport.local', '::1', 1, '2026-05-24 09:13:32.698431'),
	(157, 'firdam@sport.local', '::1', 1, '2026-05-24 09:39:09.735445'),
	(158, 'dani@sport.local', '::1', 1, '2026-05-24 09:44:32.433449'),
	(159, 'firdam@sport.local', '::1', 1, '2026-05-24 09:45:35.733956'),
	(160, 'firdam@sport.local', '::1', 1, '2026-05-24 09:45:36.93286'),
	(161, 'firdam@sport.local', '::1', 1, '2026-05-24 10:07:38.565108'),
	(162, 'firdam@sport.local', '::1', 1, '2026-05-24 10:19:10.760743'),
	(163, 'firdam@sport.local', '::1', 1, '2026-05-24 10:20:21.459961'),
	(164, 'firdam@sport.local', '::1', 1, '2026-05-24 13:06:19.185657'),
	(165, 'firdam@sport.local', '::1', 1, '2026-05-24 13:06:20.601317'),
	(166, 'firdam@sport.local', '::1', 1, '2026-05-24 13:25:50.18519'),
	(167, 'firdam@sport.local', '::1', 1, '2026-05-24 13:25:51.603675'),
	(168, 'dani@sport.local', '::1', 1, '2026-05-24 13:53:40.399612'),
	(169, 'dani@sport.local', '::1', 1, '2026-05-24 13:53:49.486295'),
	(170, 'dani@sport.local', '::1', 1, '2026-05-24 13:53:50.685468'),
	(171, 'dani@sport.local', '::1', 1, '2026-05-24 13:53:51.885335'),
	(172, 'dani@sport.local', '::1', 1, '2026-05-24 13:53:53.085512'),
	(173, 'dani@sport.local', '::1', 1, '2026-05-24 13:54:07.884485'),
	(174, 'firdam@sport.local', '::1', 1, '2026-05-24 14:38:00.177554'),
	(175, 'firdam@sport.local', '::1', 1, '2026-05-24 14:38:01.380972'),
	(176, 'dani@sport.local', '::1', 1, '2026-05-24 14:46:57.48718'),
	(177, 'dani@sport.local', '::1', 1, '2026-05-24 14:47:07.17703'),
	(178, 'dani@sport.local', '::1', 1, '2026-05-24 14:47:08.595317'),
	(179, 'dani@sport.local', '::1', 1, '2026-05-24 14:47:09.778603'),
	(180, 'dani@sport.local', '::1', 1, '2026-05-24 14:47:10.975287'),
	(181, 'farhan@sport.local', '::1', 1, '2026-05-24 15:08:13.5815'),
	(182, 'rifat@sport.local', '::1', 1, '2026-05-24 15:35:25.178991'),
	(183, 'firdam@sport.local', '::1', 1, '2026-05-24 15:43:07.98022'),
	(184, 'rifat@sport.local', '::1', 0, '2026-05-24 16:40:24.368752'),
	(185, 'rifat@sport.local', '::1', 0, '2026-05-24 16:40:38.870349'),
	(186, 'rifat@sport.local', '::1', 0, '2026-05-24 16:40:47.384306'),
	(187, 'dani@sport.local', '::1', 1, '2026-05-24 16:41:26.106283'),
	(188, 'rifat@sport.local', '::1', 1, '2026-05-24 16:42:36.271745'),
	(189, 'rifat@sport.local', '::1', 1, '2026-05-24 16:42:46.470877'),
	(190, 'rifat@sport.local', '::1', 1, '2026-05-24 16:44:41.270966'),
	(191, 'rifat@sport.local', '::1', 1, '2026-05-24 16:45:33.972304'),
	(192, 'firdam@sport.local', '::1', 1, '2026-05-24 16:47:21.873799'),
	(193, 'firdam@sport.local', '::1', 1, '2026-05-24 16:47:23.289217'),
	(194, 'firdam@sport.local', '::1', 1, '2026-05-24 17:17:38.258654'),
	(195, 'firdam@sport.local', '::1', 0, '2026-05-24 17:26:51.758859'),
	(196, 'firdam@sport.local', '::1', 1, '2026-05-24 17:27:08.058026'),
	(197, 'firdam@sport.local', '::1', 1, '2026-05-24 17:27:09.259693'),
	(198, 'farhan@sport.local', '::1', 1, '2026-05-24 17:45:43.570794'),
	(199, 'firdam@sport.local', '::1', 1, '2026-05-24 17:51:16.328044'),
	(200, 'firdam@sport.local', '::1', 1, '2026-05-24 17:52:17.210753'),
	(201, 'farhan@sport.local', '::1', 1, '2026-05-24 19:45:30.220705'),
	(202, 'firdam@sport.local', '::1', 1, '2026-05-24 21:27:09.619165'),
	(203, 'firdam@sport.local', '::1', 1, '2026-05-24 23:09:14.183005'),
	(204, 'firdam@sport.local', '::1', 1, '2026-05-25 06:12:53.320202'),
	(205, 'firdam@sport.local', '::1', 1, '2026-05-25 14:33:09.209759'),
	(206, 'firdam@sport.local', '::1', 1, '2026-05-25 17:40:51.835472'),
	(207, 'firdam@sport.local', '::1', 1, '2026-05-25 22:51:30.420108'),
	(208, 'firdam@sport.local', '::1', 1, '2026-05-25 23:09:56.046495'),
	(209, 'firdam@sport.local', '::1', 1, '2026-05-26 08:50:54.968428'),
	(210, 'firdam@sport.local', '::1', 1, '2026-05-26 10:06:01.136196'),
	(211, 'firdam@sport.local', '::1', 1, '2026-05-26 10:40:19.054152'),
	(212, 'firdam@sport.local', '::1', 1, '2026-05-26 10:49:20.016167'),
	(213, 'firdam@sport.local', '::1', 1, '2026-05-26 10:59:33.909343'),
	(214, 'rifat@sport.local', '::1', 1, '2026-05-26 11:28:02.69151'),
	(215, 'rifat@sport.local', '::1', 1, '2026-05-26 11:28:39.599941'),
	(216, 'rifat@sport.local', '::1', 1, '2026-05-26 11:28:47.313181'),
	(217, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-26 11:38:44.391285'),
	(218, 'farhan@sport.local', '::1', 1, '2026-05-26 11:39:08.998148'),
	(219, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-26 11:40:44.313752'),
	(220, 'firdam@sport.local', '::1', 1, '2026-05-26 15:40:55.097164'),
	(221, 'firdam@sport.local', '::1', 1, '2026-05-26 15:41:28.893999'),
	(222, 'firdam@sport.local', '::1', 1, '2026-05-27 06:46:43.916294'),
	(223, 'firdam@sport.local', '::1', 1, '2026-05-29 05:38:20.376448'),
	(224, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-29 10:00:59.005647'),
	(225, 'firdam@sport.local', '::1', 1, '2026-05-29 12:48:58.619054'),
	(226, 'firdam@sport.local', '::1', 1, '2026-05-29 12:56:49.119734'),
	(227, 'fajar@sport.local', '::1', 1, '2026-05-29 13:02:23.295608');
/*!40000 ALTER TABLE "login_attempts" ENABLE KEYS */;

-- Dumping structure for table public.member_eksternal
CREATE TABLE IF NOT EXISTS "member_eksternal" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''member_eksternal_id_seq''::regclass)',
	"jadwal_id" INTEGER NOT NULL,
	"nama_tamu" VARCHAR(120) NOT NULL,
	"dibawa_oleh_id" INTEGER NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "member_eksternal_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "member_eksternal_dibawa_oleh_id_fkey" FOREIGN KEY ("dibawa_oleh_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.member_eksternal: -1 rows
/*!40000 ALTER TABLE "member_eksternal" DISABLE KEYS */;
INSERT INTO "member_eksternal" ("id", "jadwal_id", "nama_tamu", "dibawa_oleh_id") VALUES
	(4, 4, 'Zacky Arido', 4),
	(5, 4, 'Kean', 4);
/*!40000 ALTER TABLE "member_eksternal" ENABLE KEYS */;

-- Dumping structure for table public.notifications
CREATE TABLE IF NOT EXISTS "notifications" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''notifications_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"jenis" VARCHAR(30) NOT NULL,
	"judul" VARCHAR(200) NOT NULL,
	"isi" TEXT NULL DEFAULT NULL,
	"url" VARCHAR(255) NULL DEFAULT NULL,
	"dibaca" SMALLINT NOT NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "notif_user_idx" ("user_id", "dibaca", "created_at"),
	CONSTRAINT "notifications_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.notifications: -1 rows
/*!40000 ALTER TABLE "notifications" DISABLE KEYS */;
INSERT INTO "notifications" ("id", "user_id", "jenis", "judul", "isi", "url", "dibaca", "created_at") VALUES
	(4, 13, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.805361'),
	(6, 8, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.885423'),
	(7, 6, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.92564'),
	(8, 7, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.965584'),
	(9, 14, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.005545'),
	(10, 3, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.0456'),
	(11, 15, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.085645'),
	(12, 10, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.125638'),
	(13, 9, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.165505'),
	(14, 11, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.205328'),
	(15, 5, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.245296'),
	(18, 8, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 03:22:47.970868'),
	(19, 14, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 09:29:51.433106'),
	(20, 4, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-22 10:34:05.881776'),
	(2, 4, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:56.72524'),
	(21, 3, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-23 09:18:05.260227'),
	(23, 4, 'titip_pesan', '💌 Titip pesan baru dari Firdam', 'Sudah dikirim sisa dp modul 1 ya dan', '/user.php?id=4#titip-pesan', 0, '2026-05-24 13:44:59.686455'),
	(24, 4, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 0, '2026-05-24 13:54:15.697312'),
	(26, 4, 'titip_pesan', '💌 Titip pesan baru dari Firdam', 'Okay', '/user.php?id=4#titip-pesan', 0, '2026-05-24 14:05:36.654331'),
	(27, 3, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 0, '2026-05-24 15:37:52.588805'),
	(29, 2, 'dm', '💬 Pesan baru dari Dani', 'tes tes', '/dm.php?u=4', 1, '2026-05-24 16:47:46.618028'),
	(28, 2, 'dm', '💬 Pesan baru dari Dani', 'kang', '/dm.php?u=4', 1, '2026-05-24 16:45:56.151978'),
	(25, 2, 'titip_pesan_reply', '↩️ Dani membalas pesanmu', 'Siapp sudah masuk, makasih kang', '/user.php?id=4#titip-pesan', 1, '2026-05-24 13:55:11.808257'),
	(22, 2, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 1, '2026-05-23 16:27:31.593629'),
	(17, 2, 'booking', 'Booking dibuat', 'Lapangan #3, 2026-05-23 16:00-18:00 (DP belum dibayar)', '/tempat.php', 1, '2026-05-22 00:45:14.401911'),
	(16, 2, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-22 00:37:28.326276'),
	(1, 2, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:56.680877');
/*!40000 ALTER TABLE "notifications" ENABLE KEYS */;

-- Dumping structure for table public.posts
CREATE TABLE IF NOT EXISTS "posts" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''posts_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"caption" TEXT NULL DEFAULT NULL,
	"foto_url" TEXT NULL DEFAULT NULL,
	"jenis" VARCHAR(30) NULL DEFAULT 'post',
	"expired_at" TIMESTAMP NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"repost_of" INTEGER NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	INDEX "posts_created_idx" ("created_at"),
	INDEX "posts_repost_idx" ("repost_of"),
	CONSTRAINT "posts_repost_of_fkey" FOREIGN KEY ("repost_of") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "posts_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.posts: 8 rows
/*!40000 ALTER TABLE "posts" DISABLE KEYS */;
INSERT INTO "posts" ("id", "user_id", "caption", "foto_url", "jenis", "expired_at", "created_at", "repost_of") VALUES
	(5, 2, 'tes', '/uploads/post_d2dce7a2a089a7d9.jpg', 'story', '2026-05-23 03:53:03.596651', '2026-05-22 03:53:03.596651', NULL),
	(8, 2, 'Qurban moal?', NULL, 'story', '2026-05-23 05:00:49.909346', '2026-05-22 05:00:49.909346', NULL),
	(10, 2, 'tes b', '/uploads/post_37cbf90924ba9b51.jpg', 'story', '2026-05-23 05:23:01.584754', '2026-05-22 05:23:01.584754', NULL),
	(12, 2, 'Dimana ini?', 'uploads/post_2098d5361db54699.jpg', 'story', '2026-05-23 05:28:20.343507', '2026-05-22 05:28:20.343507', NULL),
	(20, 2, 'Hiking Zaman doloe...', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-story-1779493421-2b6b42d2_AEpezqZSR.jpg', 'story', '2026-05-24 06:43:42.642329', '2026-05-23 06:43:42.642329', NULL),
	(21, 2, 'Hati2, jalanan macet penuh dengan persib', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-story-1779547426-900d5ed9_c4S84uqK_.jpg', 'story', '2026-05-24 21:43:48.245773', '2026-05-23 21:43:48.245773', NULL),
	(22, 2, 'Qurban moal', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-post-1779547507-7ed5637f_9n8arlV51.jpg', 'post', NULL, '2026-05-23 21:45:08.625407', NULL),
	(23, 2, 'Climate Funding', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-post-1779547596-1e76b6d1_9wtvJIxFg.jpg', 'post', NULL, '2026-05-23 21:46:38.411556', NULL),
	(25, 4, 'Ngingetan wee...(keur simkuring oge😁)', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Dani-story-1779605908-2204a5fa_kUSGEz6lb.jpg', 'story', '2026-05-25 13:58:30.336405', '2026-05-24 13:58:30.336405', NULL),
	(28, 2, 'Domba terkini', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-story-1779768993-21dc48d4_XPYhdHH33.jpg', 'story', '2026-05-27 11:16:35.636885', '2026-05-26 11:16:35.636885', NULL),
	(29, 3, '', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Rifat-story-1779770124-f0e41a1d_1a1BS0Fet.jpg', 'story', '2026-05-27 11:35:26.013084', '2026-05-26 11:35:26.013084', NULL);
/*!40000 ALTER TABLE "posts" ENABLE KEYS */;

-- Dumping structure for table public.post_bookmarks
CREATE TABLE IF NOT EXISTS "post_bookmarks" (
	"user_id" INTEGER NOT NULL,
	"post_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id", "post_id"),
	CONSTRAINT "post_bookmarks_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_bookmarks_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_bookmarks: -1 rows
/*!40000 ALTER TABLE "post_bookmarks" DISABLE KEYS */;
/*!40000 ALTER TABLE "post_bookmarks" ENABLE KEYS */;

-- Dumping structure for table public.post_comments
CREATE TABLE IF NOT EXISTS "post_comments" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''post_comments_id_seq''::regclass)',
	"post_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"isi" TEXT NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "post_comments_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_comments_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_comments: -1 rows
/*!40000 ALTER TABLE "post_comments" DISABLE KEYS */;
INSERT INTO "post_comments" ("id", "post_id", "user_id", "isi", "created_at") VALUES
	(7, 23, 2, 'tes', '2026-05-29 12:51:09.343132');
/*!40000 ALTER TABLE "post_comments" ENABLE KEYS */;

-- Dumping structure for table public.post_hashtags
CREATE TABLE IF NOT EXISTS "post_hashtags" (
	"post_id" INTEGER NOT NULL,
	"tag" VARCHAR(64) NOT NULL,
	PRIMARY KEY ("post_id", "tag"),
	INDEX "post_hashtags_tag_idx" ("tag"),
	CONSTRAINT "post_hashtags_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_hashtags: -1 rows
/*!40000 ALTER TABLE "post_hashtags" DISABLE KEYS */;
/*!40000 ALTER TABLE "post_hashtags" ENABLE KEYS */;

-- Dumping structure for table public.post_likes
CREATE TABLE IF NOT EXISTS "post_likes" (
	"post_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("post_id", "user_id"),
	CONSTRAINT "post_likes_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_likes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_likes: -1 rows
/*!40000 ALTER TABLE "post_likes" DISABLE KEYS */;
INSERT INTO "post_likes" ("post_id", "user_id", "created_at") VALUES
	(23, 2, '2026-05-24 17:51:28.688676'),
	(22, 2, '2026-05-24 21:27:42.950059');
/*!40000 ALTER TABLE "post_likes" ENABLE KEYS */;

-- Dumping structure for table public.post_mentions
CREATE TABLE IF NOT EXISTS "post_mentions" (
	"post_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	PRIMARY KEY ("post_id", "user_id"),
	CONSTRAINT "post_mentions_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_mentions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_mentions: -1 rows
/*!40000 ALTER TABLE "post_mentions" DISABLE KEYS */;
/*!40000 ALTER TABLE "post_mentions" ENABLE KEYS */;

-- Dumping structure for table public.post_reports
CREATE TABLE IF NOT EXISTS "post_reports" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''post_reports_id_seq''::regclass)',
	"post_id" INTEGER NOT NULL,
	"reporter_id" INTEGER NOT NULL,
	"alasan" VARCHAR(60) NOT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"status" VARCHAR(20) NOT NULL DEFAULT 'open',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"resolved_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	INDEX "post_reports_post_idx" ("post_id"),
	INDEX "post_reports_status_idx" ("status"),
	CONSTRAINT "post_reports_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_reports_reporter_id_fkey" FOREIGN KEY ("reporter_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_reports: -1 rows
/*!40000 ALTER TABLE "post_reports" DISABLE KEYS */;
/*!40000 ALTER TABLE "post_reports" ENABLE KEYS */;

-- Dumping structure for table public.post_views
CREATE TABLE IF NOT EXISTS "post_views" (
	"post_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"viewed_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("post_id", "user_id"),
	INDEX "post_views_post_idx" ("post_id", "viewed_at"),
	CONSTRAINT "post_views_post_id_fkey" FOREIGN KEY ("post_id") REFERENCES "posts" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "post_views_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.post_views: -1 rows
/*!40000 ALTER TABLE "post_views" DISABLE KEYS */;
INSERT INTO "post_views" ("post_id", "user_id", "viewed_at") VALUES
	(25, 3, '2026-05-24 16:44:50.2914'),
	(21, 3, '2026-05-24 16:45:06.41631'),
	(21, 2, '2026-05-24 16:53:01.202804'),
	(25, 2, '2026-05-24 16:53:15.55592'),
	(21, 14, '2026-05-24 19:46:01.157275'),
	(28, 2, '2026-05-26 11:17:00.071517'),
	(28, 3, '2026-05-26 11:36:11.94052'),
	(29, 16, '2026-05-26 11:40:20.585951'),
	(29, 14, '2026-05-26 11:40:33.125094'),
	(29, 2, '2026-05-26 15:41:05.828374');
/*!40000 ALTER TABLE "post_views" ENABLE KEYS */;

-- Dumping structure for table public.push_seen
CREATE TABLE IF NOT EXISTS "push_seen" (
	"user_id" INTEGER NOT NULL,
	"last_notif_id" INTEGER NOT NULL DEFAULT '0',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "push_seen_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.push_seen: -1 rows
/*!40000 ALTER TABLE "push_seen" DISABLE KEYS */;
INSERT INTO "push_seen" ("user_id", "last_notif_id", "updated_at") VALUES
	(15, 11, '2026-05-23 21:18:51.120847'),
	(14, 19, '2026-05-24 15:26:29.918678'),
	(4, 26, '2026-05-24 16:41:32.303962'),
	(3, 27, '2026-05-24 16:44:05.666011'),
	(2, 29, '2026-05-24 16:48:15.370992');
/*!40000 ALTER TABLE "push_seen" ENABLE KEYS */;

-- Dumping structure for table public.qr_tokens
CREATE TABLE IF NOT EXISTS "qr_tokens" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''qr_tokens_id_seq''::regclass)',
	"jadwal_id" INTEGER NOT NULL,
	"token" TEXT NOT NULL,
	"valid_from" TIMESTAMP NOT NULL DEFAULT 'now()',
	"valid_until" TIMESTAMP NOT NULL DEFAULT '(now() + ''03:00:00''::interval)',
	"lat" DOUBLE PRECISION NULL DEFAULT NULL,
	"lng" DOUBLE PRECISION NULL DEFAULT NULL,
	"radius_meter" INTEGER NULL DEFAULT '150',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "qr_tokens_token_key" ("token"),
	CONSTRAINT "qr_tokens_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.qr_tokens: -1 rows
/*!40000 ALTER TABLE "qr_tokens" DISABLE KEYS */;
INSERT INTO "qr_tokens" ("id", "jadwal_id", "token", "valid_from", "valid_until", "lat", "lng", "radius_meter", "created_at") VALUES
	(5, 6, 'cd37adec1cf2590065502b658ecf499d', '2026-05-23 05:49:08.58788', '2026-05-24 05:49:08.58788', -6.930473, 107.731552, 150, '2026-05-23 05:49:08.58788');
/*!40000 ALTER TABLE "qr_tokens" ENABLE KEYS */;

-- Dumping structure for table public.quran_bookmarks
CREATE TABLE IF NOT EXISTS "quran_bookmarks" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''quran_bookmarks_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"surah" INTEGER NOT NULL,
	"ayat" INTEGER NOT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "quran_bookmarks_user_id_surah_ayat_key" ("user_id", "surah", "ayat"),
	CONSTRAINT "quran_bookmarks_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.quran_bookmarks: -1 rows
/*!40000 ALTER TABLE "quran_bookmarks" DISABLE KEYS */;
INSERT INTO "quran_bookmarks" ("id", "user_id", "surah", "ayat", "catatan", "created_at") VALUES
	(1, 2, 2, 5, '', '2026-05-24 00:36:28.512328'),
	(7, 2, 60, 12, '', '2026-05-24 10:24:33.534955');
/*!40000 ALTER TABLE "quran_bookmarks" ENABLE KEYS */;

-- Dumping structure for table public.quran_last_read
CREATE TABLE IF NOT EXISTS "quran_last_read" (
	"user_id" INTEGER NOT NULL,
	"surah" INTEGER NOT NULL,
	"ayat" INTEGER NOT NULL,
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "quran_last_read_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.quran_last_read: -1 rows
/*!40000 ALTER TABLE "quran_last_read" DISABLE KEYS */;
INSERT INTO "quran_last_read" ("user_id", "surah", "ayat", "updated_at") VALUES
	(2, 60, 12, '2026-05-24 10:24:35.592169');
/*!40000 ALTER TABLE "quran_last_read" ENABLE KEYS */;

-- Dumping structure for table public.rate_limit
CREATE TABLE IF NOT EXISTS "rate_limit" (
	"bucket" VARCHAR(120) NOT NULL,
	"ts" TIMESTAMP NOT NULL DEFAULT 'now()',
	INDEX "rl_idx" ("bucket", "ts")
);

-- Dumping data for table public.rate_limit: 1 rows
/*!40000 ALTER TABLE "rate_limit" DISABLE KEYS */;
INSERT INTO "rate_limit" ("bucket", "ts") VALUES
	('login:::1', '2026-05-29 13:02:22.867174');
/*!40000 ALTER TABLE "rate_limit" ENABLE KEYS */;

-- Dumping structure for table public.referal_codes
CREATE TABLE IF NOT EXISTS "referal_codes" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''referal_codes_id_seq''::regclass)',
	"kode" VARCHAR(32) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"aktif" SMALLINT NOT NULL DEFAULT '1',
	"max_pakai" INTEGER NULL DEFAULT NULL,
	"jumlah_terpakai" INTEGER NOT NULL DEFAULT '0',
	"dibuat_oleh" INTEGER NULL DEFAULT NULL,
	"expired_at" DATE NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "referal_codes_kode_key" ("kode"),
	CONSTRAINT "referal_codes_dibuat_oleh_fkey" FOREIGN KEY ("dibuat_oleh") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.referal_codes: -1 rows
/*!40000 ALTER TABLE "referal_codes" DISABLE KEYS */;
INSERT INTO "referal_codes" ("id", "kode", "deskripsi", "aktif", "max_pakai", "jumlah_terpakai", "dibuat_oleh", "expired_at", "created_at") VALUES
	(1, 'HAPFAM2211', '', 1, 5, 1, 2, '2026-05-24', '2026-05-23 23:46:16.842891');
/*!40000 ALTER TABLE "referal_codes" ENABLE KEYS */;

-- Dumping structure for table public.run_points
CREATE TABLE IF NOT EXISTS "run_points" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''run_points_id_seq''::regclass)',
	"session_id" BIGINT NOT NULL,
	"lat" DOUBLE PRECISION NOT NULL,
	"lng" DOUBLE PRECISION NOT NULL,
	"ts" TIMESTAMP NOT NULL DEFAULT 'now()',
	"speed_mps" DOUBLE PRECISION NULL DEFAULT NULL,
	"accuracy_m" DOUBLE PRECISION NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	INDEX "run_points_sess_idx" ("session_id", "ts"),
	CONSTRAINT "run_points_session_id_fkey" FOREIGN KEY ("session_id") REFERENCES "run_sessions" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.run_points: 5 rows
/*!40000 ALTER TABLE "run_points" DISABLE KEYS */;
INSERT INTO "run_points" ("id", "session_id", "lat", "lng", "ts", "speed_mps", "accuracy_m") VALUES
	(122, 5, -6.9371956, 107.7295671, '2026-05-24 14:06:27.220108', 0.02862118370831, 11.479999542236),
	(123, 5, -6.9372365, 107.7295822, '2026-05-24 14:06:28.015353', 0.090525113046169, 11.154999732971),
	(124, 5, -6.9372532, 107.729589, '2026-05-24 14:06:29.186623', 0.026881486177444, 11.03600025177),
	(125, 5, -6.9255177, 107.7294744, '2026-05-24 14:19:43.366962', NULL, 64.099998474121),
	(126, 5, -6.9255201, 107.7294733, '2026-05-24 14:19:48.223482', NULL, 82.5);
/*!40000 ALTER TABLE "run_points" ENABLE KEYS */;

-- Dumping structure for table public.run_sessions
CREATE TABLE IF NOT EXISTS "run_sessions" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''run_sessions_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"mulai_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"selesai_at" TIMESTAMP NULL DEFAULT NULL,
	"jarak_m" DOUBLE PRECISION NOT NULL DEFAULT '0',
	"durasi_dtk" INTEGER NOT NULL DEFAULT '0',
	"kalori" INTEGER NOT NULL DEFAULT '0',
	"catatan" TEXT NULL DEFAULT NULL,
	"status" VARCHAR(20) NOT NULL DEFAULT 'aktif',
	PRIMARY KEY ("id"),
	INDEX "run_sessions_user_idx" ("user_id", "mulai_at"),
	CONSTRAINT "run_sessions_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.run_sessions: 1 rows
/*!40000 ALTER TABLE "run_sessions" DISABLE KEYS */;
INSERT INTO "run_sessions" ("id", "user_id", "mulai_at", "selesai_at", "jarak_m", "durasi_dtk", "kalori", "catatan", "status") VALUES
	(5, 2, '2026-05-24 14:06:19.121828', '2026-05-24 14:19:48.95468', 1312.1291569323, 808, 85, NULL, 'selesai');
/*!40000 ALTER TABLE "run_sessions" ENABLE KEYS */;

-- Dumping structure for table public.sapa_log
CREATE TABLE IF NOT EXISTS "sapa_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''sapa_log_id_seq''::regclass)',
	"sender_user_id" INTEGER NOT NULL,
	"target_user_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "sapa_log_sender_user_id_target_user_id_key" ("sender_user_id", "target_user_id"),
	CONSTRAINT "sapa_log_sender_user_id_fkey" FOREIGN KEY ("sender_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "sapa_log_target_user_id_fkey" FOREIGN KEY ("target_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.sapa_log: -1 rows
/*!40000 ALTER TABLE "sapa_log" DISABLE KEYS */;
INSERT INTO "sapa_log" ("id", "sender_user_id", "target_user_id", "created_at") VALUES
	(1, 2, 14, '2026-05-23 07:54:08.724011'),
	(2, 4, 17, '2026-05-23 09:15:10.100932');
/*!40000 ALTER TABLE "sapa_log" ENABLE KEYS */;

-- Dumping structure for table public.sedekah_log
CREATE TABLE IF NOT EXISTS "sedekah_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''sedekah_log_id_seq''::regclass)',
	"program_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"jumlah" BIGINT NOT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "sedekah_log_program_id_fkey" FOREIGN KEY ("program_id") REFERENCES "sedekah_program" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "sedekah_log_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.sedekah_log: -1 rows
/*!40000 ALTER TABLE "sedekah_log" DISABLE KEYS */;
/*!40000 ALTER TABLE "sedekah_log" ENABLE KEYS */;

-- Dumping structure for table public.sedekah_program
CREATE TABLE IF NOT EXISTS "sedekah_program" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''sedekah_program_id_seq''::regclass)',
	"judul" VARCHAR(180) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"jenis" VARCHAR(20) NOT NULL DEFAULT 'sedekah',
	"target_amount" BIGINT NOT NULL DEFAULT '0',
	"terkumpul" BIGINT NOT NULL DEFAULT '0',
	"deadline" DATE NULL DEFAULT NULL,
	"active" SMALLINT NOT NULL DEFAULT '1',
	"dibuat_oleh" INTEGER NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "sedekah_program_dibuat_oleh_fkey" FOREIGN KEY ("dibuat_oleh") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.sedekah_program: -1 rows
/*!40000 ALTER TABLE "sedekah_program" DISABLE KEYS */;
/*!40000 ALTER TABLE "sedekah_program" ENABLE KEYS */;

-- Dumping structure for table public.tempat
CREATE TABLE IF NOT EXISTS "tempat" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''tempat_id_seq''::regclass)',
	"nama" VARCHAR(180) NOT NULL,
	"alamat" TEXT NULL DEFAULT NULL,
	"harga_lapang" NUMERIC(12,2) NULL DEFAULT '0',
	"harga_per_jam" NUMERIC(12,2) NULL DEFAULT '0',
	"status_booking" VARCHAR(30) NULL DEFAULT 'tersedia',
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"lat" DOUBLE PRECISION NULL DEFAULT NULL,
	"lng" DOUBLE PRECISION NULL DEFAULT NULL,
	"pic_user_id" INTEGER NULL DEFAULT NULL,
	"kontak_wa" VARCHAR(30) NULL DEFAULT NULL,
	"jenis_id" INTEGER NULL DEFAULT NULL,
	"harga_tiket" NUMERIC(12,2) NULL DEFAULT '0',
	"harga_parkir" NUMERIC(12,2) NULL DEFAULT '0',
	PRIMARY KEY ("id"),
	CONSTRAINT "tempat_jenis_id_fkey" FOREIGN KEY ("jenis_id") REFERENCES "jenis_olahraga" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "tempat_pic_user_id_fkey" FOREIGN KEY ("pic_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.tempat: 27 rows
/*!40000 ALTER TABLE "tempat" DISABLE KEYS */;
INSERT INTO "tempat" ("id", "nama", "alamat", "harga_lapang", "harga_per_jam", "status_booking", "catatan", "created_at", "lat", "lng", "pic_user_id", "kontak_wa", "jenis_id", "harga_tiket", "harga_parkir") VALUES
	(5, 'Parkiran Taman Sumringah', 'Summarecon Bandung', 0.00, 0.00, 'tersedia', '', '2026-05-21 11:16:55.600715', -6.9537503, 107.6929201, 3, NULL, 1, 0.00, 0.00),
	(22, 'Lapang Pingpong', 'Pinggir Kampus UIN', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:15:28.522765', NULL, NULL, 4, NULL, 12, 0.00, 0.00),
	(11, 'Kolam Renang Lettu Pas Basonai', 'Lanud Sulaiman, Margahayu', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:38.344177', -6.9912435, 107.5759873, 2, NULL, 5, 0.00, 0.00),
	(9, 'Kolam Renang Panorama', 'Ujung Berung', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:36.928503', -6.898462, 107.7103046, 2, NULL, 5, 0.00, 0.00),
	(10, 'Kolam Renang UPI', 'UPI Setiabudi', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:05:55.865701', -6.8594515, 107.5855598, 4, NULL, 5, 0.00, 0.00),
	(19, 'Biliar Sinai', 'Baleendah, Rancamanyar', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:43.837103', NULL, NULL, 2, '089638726182', 11, 0.00, 0.00),
	(8, 'BSD Sport', 'Cipamokolan', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:04.47909', NULL, NULL, 3, '08872947080', 2, 0.00, 0.00),
	(1, 'GOR Adiguna', 'Jln. Pertamina, Soetta', 110000.00, 110000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 3, NULL, 3, 0.00, 0.00),
	(6, 'GOR Azaka', 'Pasirimpun Atas', 50000.00, 50000.00, 'tersedia', '0', '2026-05-21 11:29:20.544026', NULL, NULL, 2, '081320906764', 2, 0.00, 0.00),
	(16, 'GOR Cempaka Arum', 'Panyileukan, Al-Jabbar', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:10:20.815157', NULL, NULL, 3, NULL, 2, 0.00, 0.00),
	(12, 'Kolam Renang Yadika', 'Tanjungsari', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:56.515685', -6.8974891, 107.8055482, 3, NULL, 5, 0.00, 0.00),
	(7, 'Singgasana Sport', 'Cibaduyut', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:56:55.240167', -6.9612456, 107.5942425, 2, NULL, 12, 0.00, 0.00),
	(21, 'Biliar BS Pool and Cafe', 'Wastukencana, Kota Bandung', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:44.253902', -6.9081659, 107.6049949, 2, NULL, 11, 0.00, 0.00),
	(13, 'GOR Mayasari', 'Soekarno Hatta, Bunderan Cibiru', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:08:01.628889', NULL, NULL, 4, NULL, 2, 0.00, 0.00),
	(2, 'GOR Mayasari', 'Soekarno Hatta, Bunderan Cibiru', 125000.00, 125000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 4, NULL, 3, 0.00, 0.00),
	(17, 'GOR Pasanggrahan', 'Cilengkrang, Bandung', 45000.00, 45000.00, 'tersedia', '', '2026-05-22 16:12:36.197922', NULL, NULL, 2, '089655369495', 2, 0.00, 0.00),
	(18, 'GOR Pilar Biru', 'Pilar Biru, Cibiru Hilir', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:13.801444', NULL, NULL, 2, NULL, 2, 0.00, 0.00),
	(3, 'GOR Purbaya', 'Jln. Ciguruwik', 25000.00, 25000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 3, NULL, 2, 0.00, 0.00),
	(15, 'GOR Sindangreret', 'Sindangreret, Cibiru', 40000.00, 40000.00, 'tersedia', '', '2026-05-22 16:09:31.917895', NULL, NULL, 2, '089628188960', 2, 0.00, 0.00),
	(14, 'GOR Gaza', 'Cinunuk, Cibiru', 20000.00, 20000.00, 'tersedia', '', '2026-05-22 16:08:56.792858', -6.930473, 107.7315517, 3, '082215309779', 2, 0.00, 0.00),
	(20, 'GOR Gaza', 'Ciguruwik, Cibiru', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:16.040455', -6.930473, 107.731552, 2, '082215309779', 11, 0.00, 0.00),
	(26, 'BHD - Warung Yos', 'Dago Atas', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:27:23.261738', -6.846409, 107.650307, 2, NULL, 8, 0.00, 5000.00),
	(24, 'Gn.Pangradinan', 'Rancaekek', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:25:11.313536', -7.043889, 107.828311, 3, NULL, 8, 0.00, 5000.00),
	(27, 'Batukuda - Manglayang', 'Batu Kuda', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:28:03.059991', -6.8928621, 107.7429292, 4, NULL, 8, 15000.00, 2000.00),
	(25, 'Kina - Sanggara/Lembah Tengkorak/Pangparang', 'Bukit Kina, Cibodas', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:26:01.59066', -6.8370644, 107.7277736, 2, NULL, 8, 0.00, 5000.00),
	(23, 'Tangkuban Perahu - Cibarebeuy', 'Subang', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:24:00.396558', -6.7733931, 107.6359156, 2, NULL, 8, 0.00, 5000.00),
	(4, 'Sindangreret - Panyileukan', 'BnC Cookies', 0.00, 0.00, 'tersedia', '', '2026-05-21 11:16:55.600715', -6.9318895, 107.7216289, 2, NULL, 10, 0.00, 0.00);
/*!40000 ALTER TABLE "tempat" ENABLE KEYS */;

-- Dumping structure for table public.tim
CREATE TABLE IF NOT EXISTS "tim" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''tim_id_seq''::regclass)',
	"nama" VARCHAR(120) NOT NULL,
	"jenis" VARCHAR(60) NOT NULL,
	"koordinator_id" INTEGER NULL DEFAULT NULL,
	"kuota" INTEGER NOT NULL DEFAULT '2',
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "tim_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.tim: -1 rows
/*!40000 ALTER TABLE "tim" DISABLE KEYS */;
/*!40000 ALTER TABLE "tim" ENABLE KEYS */;

-- Dumping structure for table public.tim_member
CREATE TABLE IF NOT EXISTS "tim_member" (
	"tim_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	PRIMARY KEY ("tim_id", "user_id"),
	CONSTRAINT "tim_member_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "tim_member_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.tim_member: -1 rows
/*!40000 ALTER TABLE "tim_member" DISABLE KEYS */;
/*!40000 ALTER TABLE "tim_member" ENABLE KEYS */;

-- Dumping structure for table public.upload_harian
CREATE TABLE IF NOT EXISTS "upload_harian" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''upload_harian_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"tanggal" DATE NOT NULL,
	"jenis" VARCHAR(60) NOT NULL,
	"durasi_menit" INTEGER NULL DEFAULT NULL,
	"jarak_km" NUMERIC(6,2) NULL DEFAULT NULL,
	"kalori" INTEGER NULL DEFAULT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"file_path" VARCHAR(255) NULL DEFAULT NULL,
	"gdrive_url" VARCHAR(255) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"pace" VARCHAR(20) NULL DEFAULT NULL,
	"pace_detik" INTEGER NULL DEFAULT NULL,
	"heart_rate" INTEGER NULL DEFAULT NULL,
	"rpe" SMALLINT NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "upload_harian_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.upload_harian: -1 rows
/*!40000 ALTER TABLE "upload_harian" DISABLE KEYS */;
INSERT INTO "upload_harian" ("id", "user_id", "tanggal", "jenis", "durasi_menit", "jarak_km", "kalori", "deskripsi", "file_path", "gdrive_url", "created_at", "pace", "pace_detik", "heart_rate", "rpe") VALUES
	(9, 2, '2026-05-12', 'Jogging', 7, 1.06, 88, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-12-Jogging_OVC1nAqpR.jpg', '6a1089ca5c7cd75eb8772939', '2026-05-22 16:52:27.591246', '7''20" /km', NULL, NULL, NULL),
	(10, 2, '2026-05-10', 'Jogging', 7, 1.00, 84, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-22-Jogging_mohISwu4E.jpg', '6a108a415c7cd75eb8795b23', '2026-05-22 16:54:26.552623', '7''07" /km', NULL, NULL, NULL),
	(11, 2, '2026-05-01', 'Jogging', 22, 2.99, 244, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-01-Jogging_bcPHSrFit.jpg', '6a108a8d5c7cd75eb87aea13', '2026-05-22 16:55:42.365204', '7''35" /km', NULL, NULL, NULL),
	(12, 2, '2026-05-07', 'Jogging', 10, 1.34, 122, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-22-Jogging_F4ltKu6BJh.jpg', '6a108af65c7cd75eb87d6ace', '2026-05-22 16:57:27.262779', '8''04" /km', NULL, NULL, NULL),
	(15, 2, '2026-05-03', 'Jogging', 24, 3.00, 244, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-03-Jogging_SXZ58UsK4.jpg', '6a108b945c7cd75eb8814d5e', '2026-05-22 17:00:05.107689', '07''49" /km', NULL, NULL, NULL),
	(7, 2, '2026-05-18', 'Jogging', 15, 2.26, 198, 'Tidak ada', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-21-Jogging_PGVG98kLK.jpg', '6a0e92aa5c7cd75eb83a10c4', '2026-05-21 05:05:47.220034', '6''47" /km', NULL, NULL, NULL),
	(13, 2, '2026-05-05', 'Jogging', 35, 4.96, 441, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-05-Jogging_57vIUf1o-.jpg', '6a108b4d5c7cd75eb87f8312', '2026-05-22 16:58:54.086772', '7''01" /km', NULL, NULL, NULL),
	(14, 2, '2026-05-03', 'Jogging', 24, 3.00, 244, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-03-Jogging_CbA7iOtwa.jpg', '6a108b8e5c7cd75eb8811758', '2026-05-22 16:59:59.092363', '07''49" /km', NULL, NULL, NULL),
	(8, 2, '2026-05-15', 'Jogging', 13, 2.40, 187, 'Wow..', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg', '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34.100156', '6''14" /km', NULL, NULL, NULL);
/*!40000 ALTER TABLE "upload_harian" ENABLE KEYS */;

-- Dumping structure for table public.users
CREATE TABLE IF NOT EXISTS "users" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''users_id_seq''::regclass)',
	"nama" VARCHAR(120) NOT NULL,
	"email" VARCHAR(180) NOT NULL,
	"password_hash" VARCHAR(255) NOT NULL,
	"role" UNKNOWN NOT NULL DEFAULT 'member',
	"google_id" VARCHAR(120) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"foto_url" VARCHAR(255) NULL DEFAULT NULL,
	"foto_file_id" VARCHAR(120) NULL DEFAULT NULL,
	"last_seen" TIMESTAMP NULL DEFAULT NULL,
	"jenis_kelamin" VARCHAR(10) NULL DEFAULT NULL,
	"xp" INTEGER NOT NULL DEFAULT '0',
	"level" INTEGER NOT NULL DEFAULT '1',
	"streak_minggu" INTEGER NOT NULL DEFAULT '0',
	"bio" TEXT NULL DEFAULT NULL,
	"dark_mode" SMALLINT NOT NULL DEFAULT '0',
	"wa" VARCHAR(30) NULL DEFAULT NULL,
	"pic_admin_id" INTEGER NULL DEFAULT NULL,
	"nomor_wa" VARCHAR(25) NULL DEFAULT NULL,
	"berat_kg" NUMERIC(5,2) NULL DEFAULT NULL,
	"tinggi_cm" NUMERIC(5,2) NULL DEFAULT NULL,
	"tanggal_lahir" DATE NULL DEFAULT NULL,
	"riwayat_penyakit" TEXT NULL DEFAULT NULL,
	"kode_referal" VARCHAR(32) NULL DEFAULT NULL,
	"referred_by_code" VARCHAR(32) NULL DEFAULT NULL,
	"username" VARCHAR(40) NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "users_email_key" ("email"),
	UNIQUE INDEX "users_kode_referal_uidx" ("kode_referal"),
	CONSTRAINT "users_pic_admin_id_fkey" FOREIGN KEY ("pic_admin_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.users: 17 rows
/*!40000 ALTER TABLE "users" DISABLE KEYS */;
INSERT INTO "users" ("id", "nama", "email", "password_hash", "role", "google_id", "created_at", "foto_url", "foto_file_id", "last_seen", "jenis_kelamin", "xp", "level", "streak_minggu", "bio", "dark_mode", "wa", "pic_admin_id", "nomor_wa", "berat_kg", "tinggi_cm", "tanggal_lahir", "riwayat_penyakit", "kode_referal", "referred_by_code", "username") VALUES
	(19, 'Tes', 'tes@sport.local', '$argon2id$v=19$m=65536,t=4,p=1$ZkxJcXdLL0Y3bi8wZ1BaYQ$q4RoXNQ7Ad51ewEm0xGGmnvFplASCFqXr0HKOyk+o08', 'member', NULL, '2026-05-24 00:17:55.137935', NULL, NULL, '2026-05-24 09:08:41.985239', 'P', 0, 1, 0, NULL, 0, NULL, NULL, '081386369206', 59.00, 150.00, '1996-01-22', NULL, NULL, 'HAPFAM2211', NULL),
	(4, 'Dani', 'dani@sport.local', '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Dani-avatar-1779446202_D6MgJZEDkC.jpg', NULL, '2026-05-24 16:48:48.609004', 'L', 300, 2, 6, NULL, 0, '0895337148803', 4, NULL, 58.00, 163.00, '2004-10-09', 'Darah Tinggi', NULL, NULL, NULL),
	(17, 'RIZAL SAAD', 'rizalsaad1405@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$dWZVNkNuMDFRbUxEbTdUbQ$FymiSUHfBJnWIII+P5DJeMVHC7cH5YbosxTQNxhFqUw', 'member', NULL, '2026-05-22 09:25:26.79199', NULL, NULL, '2026-05-22 09:26:42.829588', 'L', 0, 1, 0, NULL, 0, '082218532348', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(16, 'ADITH SETIAWAN', 'adithsetiawan62@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$NzkuSWtnU0J1UjFTcGV4Ug$3kOfbqXaVv19r43a8KDxVPg33BbgV/AkqZ7Gt6oY9u8', 'member', NULL, '2026-05-22 09:25:05.526258', NULL, NULL, '2026-05-29 10:06:59.352043', 'L', 0, 1, 0, 'Enjoy the Proses', 0, '082118785024', 4, NULL, 66.00, 160.00, '2005-03-12', 'Sehat sentosa', NULL, NULL, NULL),
	(8, 'Dedi', 'dedi@sport.local', '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', NULL, '2026-05-19 07:55:00.498075', NULL, NULL, '2026-05-23 17:11:43.514279', 'L', 150, 1, 0, NULL, 0, '082184381823', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(9, 'Rafi', 'rafi@sport.local', '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', NULL, '2026-05-19 07:55:12.485671', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '089502639933', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'member', NULL, '2026-05-19 07:56:28.908609', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Farhan_Akmali-avatar-1779482008_KIqU_LMhc.jpg', NULL, '2026-05-26 11:40:27.215003', 'L', 150, 1, 0, NULL, 0, '087854972839', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(3, 'Rifat', 'rifat@sport.local', '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Rifat-avatar-1779378411_1K68zsR1h.jpg', '6a0f28ed5c7cd75eb84a1dad', '2026-05-26 11:38:50.425452', 'L', 300, 2, 0, '', 0, '081369248630', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(10, 'Reyhan', 'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', NULL, '2026-05-19 07:55:29.376846', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '082320781890', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(20, 'Fajar Suseno', 'fajar@sport.local', '$2y$10$PCnvpCyKEdEapN87UMqQHOh7edoaNTepREZPpBljj5sHgdp68uUbi', 'member', NULL, '2026-05-29 12:57:35.205148', NULL, NULL, '2026-05-29 13:08:39.252702', 'L', 0, 1, 0, NULL, 0, '087822615464', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(13, 'Aziz', 'aziz@sport.local', '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', NULL, '2026-05-19 07:56:12.862165', NULL, NULL, '2026-05-22 05:47:41.226206', 'L', 0, 1, 0, NULL, 0, '081223450704', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(15, 'Hanif', 'hanif@sport.local', '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', NULL, '2026-05-19 07:56:40.664031', NULL, NULL, '2026-05-23 21:20:58.269648', 'L', 0, 1, 0, NULL, 0, '082117100115', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(6, 'Dendra', 'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', NULL, '2026-05-19 07:54:35.123756', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '082316481216', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(2, 'Firdam', 'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Firdam-avatar-1779423163_TlgPp4MS-.jpg', '6a0ee0135c7cd75eb87edbaf', '2026-05-29 13:23:50.070886', 'L', 300, 2, 0, 'Mau yang mana?', 0, '081386369207', 2, '081386369207', 83.00, 170.00, '1996-03-11', 'Usus Buntu', NULL, NULL, NULL),
	(7, 'Faiz', 'faiz@sport.local', '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', NULL, '2026-05-19 07:54:49.054143', NULL, NULL, '2026-05-23 16:28:16.452662', 'L', 0, 1, 2, NULL, 0, '085814120846', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(11, 'Rian', 'rian@sport.local', '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', NULL, '2026-05-19 07:55:42.436033', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '085691767966', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(5, 'Usama', 'usama@sport.local', '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', NULL, '2026-05-19 07:54:22.015654', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '089525429272', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
/*!40000 ALTER TABLE "users" ENABLE KEYS */;

-- Dumping structure for table public.user_badges
CREATE TABLE IF NOT EXISTS "user_badges" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''user_badges_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"badge_id" INTEGER NOT NULL,
	"earned_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "user_badges_user_id_badge_id_key" ("user_id", "badge_id"),
	CONSTRAINT "user_badges_badge_id_fkey" FOREIGN KEY ("badge_id") REFERENCES "badges" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "user_badges_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_badges: -1 rows
/*!40000 ALTER TABLE "user_badges" DISABLE KEYS */;
INSERT INTO "user_badges" ("id", "user_id", "badge_id", "earned_at") VALUES
	(1, 2, 7, '2026-05-22 00:37:28.111196'),
	(2, 8, 7, '2026-05-22 03:22:47.755994'),
	(3, 14, 7, '2026-05-22 09:29:51.266041'),
	(4, 4, 7, '2026-05-22 10:34:05.720315'),
	(5, 3, 7, '2026-05-23 09:18:05.084045'),
	(6, 2, 2, '2026-05-23 16:27:31.433549'),
	(7, 4, 2, '2026-05-24 13:54:15.537989'),
	(8, 3, 2, '2026-05-24 15:37:52.431443');
/*!40000 ALTER TABLE "user_badges" ENABLE KEYS */;

-- Dumping structure for table public.user_follows
CREATE TABLE IF NOT EXISTS "user_follows" (
	"follower_id" INTEGER NOT NULL,
	"following_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("follower_id", "following_id"),
	CONSTRAINT "user_follows_follower_id_fkey" FOREIGN KEY ("follower_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "user_follows_following_id_fkey" FOREIGN KEY ("following_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "user_follows_check" CHECK ((follower_id <> following_id))
);

-- Dumping data for table public.user_follows: -1 rows
/*!40000 ALTER TABLE "user_follows" DISABLE KEYS */;
/*!40000 ALTER TABLE "user_follows" ENABLE KEYS */;

-- Dumping structure for table public.user_islami_pref
CREATE TABLE IF NOT EXISTS "user_islami_pref" (
	"user_id" INTEGER NOT NULL,
	"hide_sapa" SMALLINT NOT NULL DEFAULT '0',
	"mode_tenang" SMALLINT NOT NULL DEFAULT '1',
	"kota" VARCHAR(60) NOT NULL DEFAULT 'Jakarta',
	"negara" VARCHAR(40) NOT NULL DEFAULT 'Indonesia',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "user_islami_pref_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_islami_pref: -1 rows
/*!40000 ALTER TABLE "user_islami_pref" DISABLE KEYS */;
INSERT INTO "user_islami_pref" ("user_id", "hide_sapa", "mode_tenang", "kota", "negara", "updated_at") VALUES
	(19, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 00:18:13.569206'),
	(4, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 00:23:08.064265'),
	(14, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 15:08:16.25189'),
	(3, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 15:35:27.781673'),
	(2, 1, 0, 'Bandung', 'Indonesia', '2026-05-26 11:08:57.379774'),
	(16, 0, 1, 'Jakarta', 'Indonesia', '2026-05-26 11:38:46.965837'),
	(20, 0, 1, 'Jakarta', 'Indonesia', '2026-05-29 13:02:26.848779');
/*!40000 ALTER TABLE "user_islami_pref" ENABLE KEYS */;

-- Dumping structure for table public.user_kondisi
CREATE TABLE IF NOT EXISTS "user_kondisi" (
	"user_id" INTEGER NOT NULL,
	"status" VARCHAR(10) NOT NULL DEFAULT 'sehat',
	"keterangan" TEXT NULL DEFAULT NULL,
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "user_kondisi_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_kondisi: -1 rows
/*!40000 ALTER TABLE "user_kondisi" DISABLE KEYS */;
INSERT INTO "user_kondisi" ("user_id", "status", "keterangan", "updated_at") VALUES
	(2, 'sehat', 'Ga enak badan', '2026-05-23 07:55:15.791319');
/*!40000 ALTER TABLE "user_kondisi" ENABLE KEYS */;

-- Dumping structure for table public.user_olahraga_favorit
CREATE TABLE IF NOT EXISTS "user_olahraga_favorit" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''user_olahraga_favorit_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"nama" VARCHAR(80) NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "user_olahraga_favorit_user_id_nama_key" ("user_id", "nama"),
	CONSTRAINT "user_olahraga_favorit_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_olahraga_favorit: -1 rows
/*!40000 ALTER TABLE "user_olahraga_favorit" DISABLE KEYS */;
INSERT INTO "user_olahraga_favorit" ("id", "user_id", "nama", "created_at") VALUES
	(1, 2, 'Badminton, Renang, Futsal, Hiking', '2026-05-22 04:54:22.608755'),
	(2, 4, 'Badminton', '2026-05-22 10:37:33.838919');
/*!40000 ALTER TABLE "user_olahraga_favorit" ENABLE KEYS */;

-- Dumping structure for table public.user_pengalaman
CREATE TABLE IF NOT EXISTS "user_pengalaman" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''user_pengalaman_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"jenis" VARCHAR(20) NOT NULL,
	"judul" VARCHAR(160) NOT NULL,
	"lokasi" VARCHAR(200) NULL DEFAULT NULL,
	"tanggal" DATE NULL DEFAULT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"foto_url" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "user_pengalaman_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_pengalaman: -1 rows
/*!40000 ALTER TABLE "user_pengalaman" DISABLE KEYS */;
INSERT INTO "user_pengalaman" ("id", "user_id", "jenis", "judul", "lokasi", "tanggal", "deskripsi", "foto_url", "created_at") VALUES
	(1, 2, 'hiking', 'Muncak Bareng Barudak', 'Gunung Putri', '2026-05-17', 'Adem Ayem', NULL, '2026-05-23 07:20:18.626138');
/*!40000 ALTER TABLE "user_pengalaman" ENABLE KEYS */;

-- Dumping structure for table public.user_perlengkapan
CREATE TABLE IF NOT EXISTS "user_perlengkapan" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''user_perlengkapan_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"jenis_olahraga_id" INTEGER NULL DEFAULT NULL,
	"jenis_nama" VARCHAR(80) NULL DEFAULT NULL,
	"nama" VARCHAR(120) NOT NULL,
	"jumlah" INTEGER NOT NULL DEFAULT '1',
	"catatan" VARCHAR(200) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "user_perlengkapan_jenis_olahraga_id_fkey" FOREIGN KEY ("jenis_olahraga_id") REFERENCES "jenis_olahraga" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "user_perlengkapan_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_perlengkapan: -1 rows
/*!40000 ALTER TABLE "user_perlengkapan" DISABLE KEYS */;
INSERT INTO "user_perlengkapan" ("id", "user_id", "jenis_olahraga_id", "jenis_nama", "nama", "jumlah", "catatan", "created_at") VALUES
	(1, 2, 1, 'Jogging', 'Sepatu', 1, 'Merk Asics', '2026-05-23 07:22:07.385253'),
	(2, 2, 12, 'Ping Pong', 'Raket', 2, NULL, '2026-05-23 07:22:31.855522'),
	(3, 2, 12, 'Ping Pong', 'Bola', 2, 'Warna Kuninig', '2026-05-23 07:22:48.543813'),
	(4, 2, 5, 'Renang', 'Kacamata', 1, 'Pribadi', '2026-05-23 07:23:16.66464'),
	(5, 2, 2, 'Badminton', 'Sepatu', 1, 'Biasa dipakai futsal', '2026-05-23 07:23:55.738743'),
	(6, 4, 2, 'Badminton', 'Raket', 2, NULL, '2026-05-24 14:01:06.155221');
/*!40000 ALTER TABLE "user_perlengkapan" ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

-- =====================================================================
-- REVISI: Kontrol tempat yang tampil di halaman Booking Lapangan
-- Idempotent — aman dijalankan ulang, TIDAK menghapus data apa pun.
-- Default: hanya jenis Badminton, Futsal, dan Biliar/Biliard yang tampil.
-- =====================================================================
ALTER TABLE "tempat" ADD COLUMN IF NOT EXISTS "tampil_booking" BOOLEAN NOT NULL DEFAULT false;
UPDATE "tempat" SET "tampil_booking" = true
 WHERE "jenis_id" IN (SELECT "id" FROM "jenis_olahraga" WHERE "nama" IN ('Badminton','Futsal','Biliar','Biliard'));

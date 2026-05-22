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

-- Dumping data for table public.absensi: 72 rows
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
	(214, 5, 16, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
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

-- Dumping structure for table public.chat_forum
CREATE TABLE IF NOT EXISTS "chat_forum" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''chat_forum_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"pesan" TEXT NOT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	"parent_id" INTEGER NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "chat_forum_parent_id_fkey" FOREIGN KEY ("parent_id") REFERENCES "chat_forum" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "chat_forum_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.chat_forum: -1 rows
/*!40000 ALTER TABLE "chat_forum" DISABLE KEYS */;
INSERT INTO "chat_forum" ("id", "user_id", "pesan", "created_at", "parent_id") VALUES
	(3, 2, 'Assalamualaikum lagi, ada yang online?', '2026-05-21 11:41:53.489748', NULL),
	(4, 3, 'wa''alikumussalam', '2026-05-21 15:47:46.367728', NULL),
	(5, 2, 'siap kawan', '2026-05-22 00:37:27.733498', 4),
	(6, 2, 'Semangat malam. Untuk absen, dilakukan di area sekitar lapang. Terimakasih.', '2026-05-22 16:36:35.671593', NULL);
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
	(6, 2, 1);
/*!40000 ALTER TABLE "chat_reactions" ENABLE KEYS */;

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
	CONSTRAINT "jadwal_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tempat_id_fkey" FOREIGN KEY ("tempat_id") REFERENCES "tempat" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.jadwal: -1 rows
/*!40000 ALTER TABLE "jadwal" DISABLE KEYS */;
INSERT INTO "jadwal" ("id", "tanggal", "bulan", "minggu_ke", "jenis", "tempat", "koordinator_id", "konten_obrolan", "catatan", "created_at", "tempat_id", "durasi_menit", "tim_id", "event_id", "jam_mulai", "jam_selesai") VALUES
	(1, '2026-04-16', 'April', 'W3', 'Jogging', 'SR-Panyileukan', 2, '<p>Struktur DK, Indk, Sjrh</p>', '<ol><li>Dedi ada bimbingan skripsi, jadi pulang </li><li>Dani sama Rifat ada Kuliah Online</li></ol>', '2026-05-19 07:50:23.02801', 4, 240, NULL, NULL, '06:10:00', '10:00:00'),
	(2, '2026-04-22', 'April', 'W4', 'Badminton', 'GOR Mayasari', 3, '<p>Tidak Ada</p>', '<p>Tidak Ada</p>', '2026-05-19 07:51:01.708229', 2, 120, NULL, NULL, '16:00:00', '18:00:00'),
	(3, '2026-05-03', 'May', 'W1', 'Jogging', 'Summarecon', 3, '<p>Sharing Hikmah Per Orang</p>', '<ol><li>Dedi Jalan dari Kosan ke Summarecon </li><li>Dedi Cedera kaki</li></ol>', '2026-05-19 07:51:58.579444', 5, 210, NULL, NULL, '07:30:00', '10:00:00'),
	(4, '2026-05-09', 'May', 'W2', 'Futsal', 'GOR Adiguna', 3, '<p>Tidak Ada</p>', '<p>Dedi Jalan dari Kosan ke Adiguna</p>', '2026-05-19 07:52:37.974739', 1, 60, NULL, NULL, '16:00:00', '17:00:00'),
	(5, '2026-05-17', 'May', 'W3', 'Badminton', 'GOR Purbaya', 4, '<p>Sharing Hikmah Per Orang</p>', '<ol><li>Rizal (Rihlah bersama adik Mentornya) </li><li>Fajar S (Part time)</li></ol>', '2026-05-19 07:53:14.399509', 3, 120, NULL, NULL, '08:00:00', '10:00:00'),
	(6, '2026-05-23', 'May', 'W4', 'Badminton', 'GOR Gaza', 3, '<p><br></p>', '<p><br></p>', '2026-05-21 15:45:32.456543', 14, 120, NULL, NULL, '16:00:00', '18:00:00');
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
	(5, 'Renang', 'Renang gaya yang bagian dari edukasi', '2026-05-21 00:40:55.617378');
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

-- Dumping data for table public.login_attempts: 52 rows
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
	(53, 'farhan@sport.local', '::1', 1, '2026-05-22 20:32:57.610004');
/*!40000 ALTER TABLE "login_attempts" ENABLE KEYS */;

-- Dumping structure for table public.member_eksternal
CREATE TABLE IF NOT EXISTS "member_eksternal" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''member_eksternal_id_seq''::regclass)',
	"jadwal_id" INTEGER NOT NULL,
	"nama_tamu" VARCHAR(120) NOT NULL,
	"dibawa_oleh_id" INTEGER NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "member_eksternal_dibawa_oleh_id_fkey" FOREIGN KEY ("dibawa_oleh_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "member_eksternal_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
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
	(1, 2, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.680877'),
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
	(16, 2, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 00:37:28.326276'),
	(17, 2, 'booking', 'Booking dibuat', 'Lapangan #3, 2026-05-23 16:00-18:00 (DP belum dibayar)', '/tempat.php', 0, '2026-05-22 00:45:14.401911'),
	(18, 8, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 03:22:47.970868'),
	(19, 14, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 09:29:51.433106'),
	(20, 4, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-22 10:34:05.881776'),
	(2, 4, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:56.72524');
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
	PRIMARY KEY ("id"),
	INDEX "posts_created_idx" ("created_at"),
	CONSTRAINT "posts_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.posts: -1 rows
/*!40000 ALTER TABLE "posts" DISABLE KEYS */;
INSERT INTO "posts" ("id", "user_id", "caption", "foto_url", "jenis", "expired_at", "created_at") VALUES
	(5, 2, 'tes', '/uploads/post_d2dce7a2a089a7d9.jpg', 'story', '2026-05-23 03:53:03.596651', '2026-05-22 03:53:03.596651'),
	(8, 2, 'Qurban moal?', NULL, 'story', '2026-05-23 05:00:49.909346', '2026-05-22 05:00:49.909346'),
	(10, 2, 'tes b', '/uploads/post_37cbf90924ba9b51.jpg', 'story', '2026-05-23 05:23:01.584754', '2026-05-22 05:23:01.584754'),
	(12, 2, 'Dimana ini?', 'uploads/post_2098d5361db54699.jpg', 'story', '2026-05-23 05:28:20.343507', '2026-05-22 05:28:20.343507'),
	(14, 2, 'Hayu ath qurban dulu', NULL, 'story', '2026-05-23 12:16:57.55121', '2026-05-22 12:16:57.55121'),
	(16, 2, 'Tes', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-story-1779463780-0a9f6c49_qV6eavhdu.jpg', 'story', '2026-05-23 15:29:42.251876', '2026-05-22 15:29:42.251876');
/*!40000 ALTER TABLE "posts" ENABLE KEYS */;

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
/*!40000 ALTER TABLE "post_comments" ENABLE KEYS */;

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
/*!40000 ALTER TABLE "post_likes" ENABLE KEYS */;

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
	(3, 6, '07452096cfe8e90687cea8ce618a282f', '2026-05-22 16:34:18.088311', '2026-05-23 16:34:18.088311', -6.927241, 107.732561, 1500, '2026-05-22 16:34:18.088311');
/*!40000 ALTER TABLE "qr_tokens" ENABLE KEYS */;

-- Dumping structure for table public.rate_limit
CREATE TABLE IF NOT EXISTS "rate_limit" (
	"bucket" VARCHAR(120) NOT NULL,
	"ts" TIMESTAMP NOT NULL DEFAULT 'now()',
	INDEX "rl_idx" ("bucket", "ts")
);

-- Dumping data for table public.rate_limit: 2 rows
/*!40000 ALTER TABLE "rate_limit" DISABLE KEYS */;
INSERT INTO "rate_limit" ("bucket", "ts") VALUES
	('login:::1', '2026-05-22 20:32:57.22181');
/*!40000 ALTER TABLE "rate_limit" ENABLE KEYS */;

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
	PRIMARY KEY ("id")
);

-- Dumping data for table public.tempat: -1 rows
/*!40000 ALTER TABLE "tempat" DISABLE KEYS */;
INSERT INTO "tempat" ("id", "nama", "alamat", "harga_lapang", "harga_per_jam", "status_booking", "catatan", "created_at", "lat", "lng") VALUES
	(5, 'Summarecon', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715', NULL, NULL),
	(3, 'GOR Purbaya', 'Jln. Ciguruwik', 25000.00, 25000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL),
	(4, 'Jln.SR - Panyileukan', '-', 0.00, 0.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL),
	(2, 'GOR Mayasari (Futsal)', '-', 125000.00, 125000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL),
	(1, 'GOR Adiguna', 'Jln. Pertamina, Soetta', 110000.00, 110000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL),
	(8, 'BSD Sport', 'Cipamokolan', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:04.47909', NULL, NULL),
	(9, 'Kolam Renang Panorama', 'Ujung Berung', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:36.928503', NULL, NULL),
	(10, 'Kolam Renang UPI', 'UPI Setiabudi', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:05:55.865701', NULL, NULL),
	(11, 'Kolam Renang Lettu Pas Basonai', 'Lanud Sulaiman, Margahayu', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:38.344177', NULL, NULL),
	(12, 'Kolam Renang Yadika', 'Tanjungsari', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:56.515685', NULL, NULL),
	(13, 'GOR Mayasari (Badminton)', 'Soekarno Hatta, Bunderan Cibiru', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:08:01.628889', NULL, NULL),
	(14, 'GOR Gaza', 'Cinunuk, Cibiru', 20000.00, 20000.00, 'tersedia', '', '2026-05-22 16:08:56.792858', NULL, NULL),
	(15, 'GOR Sindangreret', 'Sindangreret, Cibiru', 40000.00, 40000.00, 'tersedia', '', '2026-05-22 16:09:31.917895', NULL, NULL),
	(16, 'GOR Cempaka Arum', 'Panyileukan, Al-Jabbar', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:10:20.815157', NULL, NULL),
	(6, 'GOR Azaka', 'Pasirimpun Atas', 50000.00, 50000.00, 'tersedia', '0', '2026-05-21 11:29:20.544026', NULL, NULL),
	(17, 'GOR Pasanggrahan', 'Cilengkrang, Bandung', 45000.00, 45000.00, 'tersedia', '', '2026-05-22 16:12:36.197922', NULL, NULL),
	(18, 'GOR Pilar Biru', 'Pilar Biru, Cibiru Hilir', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:13.801444', NULL, NULL),
	(19, 'Biliar Sinai', 'Baleendah, Rancamanyar', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:43.837103', NULL, NULL),
	(20, 'GOR Gaza (Biliar)', 'Ciguruwik, Cibiru', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:16.040455', NULL, NULL),
	(21, 'Biliar BS Pool and Cafe', 'Wastukencana, Kota Bandung', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:44.253902', NULL, NULL),
	(22, 'Lapang Pingpong', 'Pinggir Kampus UIN', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:15:28.522765', NULL, NULL),
	(7, 'Singgasana Sport (Ping Pong)', 'Cibaduyut', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:56:55.240167', NULL, NULL),
	(23, 'Hiking: Tangkuban Perahu-Cibarebeuy', 'Subang', 0.00, 0.00, 'tersedia', 'Parkir di warung. 5000/motor', '2026-05-22 16:24:00.396558', NULL, NULL),
	(24, 'Hiking: Gmn.Pangradinan', 'Rancaekek', 0.00, 0.00, 'tersedia', 'HTM+Parkir. 5000/motor', '2026-05-22 16:25:11.313536', NULL, NULL),
	(25, 'Hiking: Sanggara/Lembah Tengkorak/Pangparang', 'Bukit Kina, Cibodas', 0.00, 0.00, 'tersedia', 'Parkir di warga. 5000/motor', '2026-05-22 16:26:01.59066', NULL, NULL),
	(26, 'Hiking: BHD-Warung Yos', 'Dago Atas', 0.00, 0.00, 'tersedia', 'Parkir di BHD. 5000/motor', '2026-05-22 16:27:23.261738', NULL, NULL),
	(27, 'Hiking: Manglayang', 'Batu Kuda', 0.00, 0.00, 'tersedia', 'Parkir di tempat. 5000/motor', '2026-05-22 16:28:03.059991', NULL, NULL);
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
INSERT INTO "tim" ("id", "nama", "jenis", "koordinator_id", "kuota", "catatan", "created_at") VALUES
	(3, 'Tim A', 'Badminton', 4, 4, '', '2026-05-21 15:48:21.90474'),
	(4, 'Tim B Badminton', 'Badminton', 3, 4, '', '2026-05-21 15:51:05.754063');
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
INSERT INTO "tim_member" ("tim_id", "user_id") VALUES
	(3, 2),
	(3, 9),
	(3, 5),
	(3, 6),
	(4, 10),
	(4, 3),
	(4, 7),
	(4, 4);
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
	(8, 2, '2026-05-15', 'Jogging', 13, 2.40, 187, 'Wow..', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg', '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34.100156', '6''14" /km', NULL, NULL, NULL),
	(13, 2, '2026-05-05', 'Jogging', 35, 4.96, 441, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-05-Jogging_57vIUf1o-.jpg', '6a108b4d5c7cd75eb87f8312', '2026-05-22 16:58:54.086772', '7''01" /km', NULL, NULL, NULL),
	(14, 2, '2026-05-03', 'Jogging', 24, 3.00, 244, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-03-Jogging_CbA7iOtwa.jpg', '6a108b8e5c7cd75eb8811758', '2026-05-22 16:59:59.092363', '07''49" /km', NULL, NULL, NULL);
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
	PRIMARY KEY ("id"),
	UNIQUE INDEX "users_email_key" ("email")
);

-- Dumping data for table public.users: 15 rows
/*!40000 ALTER TABLE "users" DISABLE KEYS */;
INSERT INTO "users" ("id", "nama", "email", "password_hash", "role", "google_id", "created_at", "foto_url", "foto_file_id", "last_seen", "jenis_kelamin", "xp", "level", "streak_minggu", "bio", "dark_mode") VALUES
	(13, 'Aziz', 'aziz@sport.local', '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', NULL, '2026-05-19 07:56:12.862165', NULL, NULL, '2026-05-22 05:47:41.226206', 'L', 0, 1, 0, NULL, 0),
	(2, 'Firdam', 'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Firdam-avatar-1779423163_TlgPp4MS-.jpg', '6a0ee0135c7cd75eb87edbaf', '2026-05-22 17:17:32.666475', 'L', 150, 1, 0, 'Mau yang mana?', 0),
	(6, 'Dendra', 'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', NULL, '2026-05-19 07:54:35.123756', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(16, 'ADITH SETIAWAN', 'adithsetiawan62@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$NzkuSWtnU0J1UjFTcGV4Ug$3kOfbqXaVv19r43a8KDxVPg33BbgV/AkqZ7Gt6oY9u8', 'member', NULL, '2026-05-22 09:25:05.526258', NULL, NULL, '2026-05-22 09:29:29.816667', 'L', 0, 1, 0, NULL, 0),
	(7, 'Faiz', 'faiz@sport.local', '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', NULL, '2026-05-19 07:54:49.054143', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(4, 'Dani', 'dani@sport.local', '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Dani-avatar-1779446202_D6MgJZEDkC.jpg', NULL, '2026-05-22 10:39:52.639224', 'L', 150, 1, 0, NULL, 0),
	(3, 'Rifat', 'rifat@sport.local', '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Rifat-avatar-1779378411_1K68zsR1h.jpg', '6a0f28ed5c7cd75eb84a1dad', '2026-05-21 15:47:47.464521', 'L', 0, 1, 0, NULL, 0),
	(15, 'Hanif', 'hanif@sport.local', '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', NULL, '2026-05-19 07:56:40.664031', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(10, 'Reyhan', 'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', NULL, '2026-05-19 07:55:29.376846', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(9, 'Rafi', 'rafi@sport.local', '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', NULL, '2026-05-19 07:55:12.485671', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(11, 'Rian', 'rian@sport.local', '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', NULL, '2026-05-19 07:55:42.436033', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(5, 'Usama', 'usama@sport.local', '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', NULL, '2026-05-19 07:54:22.015654', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(17, 'RIZAL SAAD', 'rizalsaad1405@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$dWZVNkNuMDFRbUxEbTdUbQ$FymiSUHfBJnWIII+P5DJeMVHC7cH5YbosxTQNxhFqUw', 'member', NULL, '2026-05-22 09:25:26.79199', NULL, NULL, '2026-05-22 09:26:42.829588', 'L', 0, 1, 0, NULL, 0),
	(14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'member', NULL, '2026-05-19 07:56:28.908609', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Farhan_Akmali-avatar-1779482008_KIqU_LMhc.jpg', NULL, '2026-05-22 20:33:32.350349', 'L', 150, 1, 0, NULL, 0),
	(8, 'Dedi', 'dedi@sport.local', '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', NULL, '2026-05-19 07:55:00.498075', NULL, NULL, '2026-05-22 04:16:27.599527', 'L', 150, 1, 0, NULL, 0);
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
	(4, 4, 7, '2026-05-22 10:34:05.720315');
/*!40000 ALTER TABLE "user_badges" ENABLE KEYS */;

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

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

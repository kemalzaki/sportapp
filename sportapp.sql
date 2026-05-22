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

-- Dumping data for table public.absensi: 75 rows
/*!40000 ALTER TABLE "absensi" DISABLE KEYS */;
REPLACE INTO "absensi" ("id", "jadwal_id", "user_id", "hadir", "metode", "checkin_at", "lat", "lng", "telat_menit", "status", "keterangan") VALUES
	(106, 1, 12, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(107, 1, 1, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(108, 1, 13, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(109, 1, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(110, 1, 8, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'ada bimbingan skripsi, jadi pulang'),
	(111, 1, 6, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(112, 1, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(113, 1, 14, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(114, 1, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(115, 1, 15, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(116, 1, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(117, 1, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(118, 1, 11, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(119, 1, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(120, 1, 5, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(121, 2, 12, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(122, 2, 1, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
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
	(136, 3, 12, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(137, 3, 1, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
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
	(151, 4, 12, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(152, 4, 1, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
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
	(165, 4, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(166, 5, 12, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(167, 5, 1, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(168, 5, 13, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Ada acara grup ITB'),
	(169, 5, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(170, 5, 8, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(171, 5, 6, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Masih Tidur'),
	(172, 5, 7, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(173, 5, 14, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(174, 5, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(175, 5, 15, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(176, 5, 9, 0, 'manual', NULL, NULL, NULL, 0, 'sakit', 'Tidak Tahu'),
	(177, 5, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(178, 5, 11, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(179, 5, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(180, 5, 5, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Masih Tidur');
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
REPLACE INTO "badges" ("id", "kode", "nama", "deskripsi", "icon", "warna", "xp") VALUES
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
REPLACE INTO "berita" ("id", "judul", "isi", "gambar_url", "gambar_file_id", "created_at") VALUES
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
REPLACE INTO "booking" ("id", "tempat_id", "user_id", "tanggal", "jam_mulai", "jam_selesai", "status", "dp_status", "recurring", "recurring_until", "catatan", "created_at") VALUES
	(1, 3, 2, '2026-05-23', '16:00:00', '18:00:00', 'pending', 'unpaid', NULL, NULL, 'DP', '2026-05-22 00:45:14.355745');
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
REPLACE INTO "chat_forum" ("id", "user_id", "pesan", "created_at", "parent_id") VALUES
	(1, 2, 'Assalamualaikum', '2026-05-21 10:33:39.752133', NULL),
	(2, 2, 'Assalamualaikum', '2026-05-21 10:35:06.060555', NULL),
	(3, 2, 'Assalamualaikum lagi, ada yang online?', '2026-05-21 11:41:53.489748', NULL),
	(4, 3, 'wa''alikumussalam', '2026-05-21 15:47:46.367728', NULL),
	(5, 2, 'siap kawan', '2026-05-22 00:37:27.733498', 4);
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
REPLACE INTO "chat_reactions" ("chat_id", "user_id", "val") VALUES
	(4, 2, 1);
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
REPLACE INTO "event" ("id", "nama", "jenis", "tipe", "deskripsi", "tanggal_mulai", "tanggal_selesai", "hadiah", "status", "banner_url", "created_by", "created_at") VALUES
	(1, 'Lomba Badminton', 'Badminton', 'tournament', 'Ya', '2026-05-22', '2026-05-23', 'Juara 1 20.000.000', 'done', NULL, 2, '2026-05-22 00:27:56.595307');
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
REPLACE INTO "event_match" ("id", "event_id", "round", "tim_a", "tim_b", "score_a", "score_b", "pemenang", "jadwal_at") VALUES
	(1, 1, 1, 3, 3, 2, 0, 3, NULL),
	(2, 1, 1, 3, 3, 0, 0, NULL, NULL),
	(3, 1, 1, 4, 4, 2, 1, 4, NULL);
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
	PRIMARY KEY ("id"),
	CONSTRAINT "jadwal_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "event" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tempat_id_fkey" FOREIGN KEY ("tempat_id") REFERENCES "tempat" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.jadwal: -1 rows
/*!40000 ALTER TABLE "jadwal" DISABLE KEYS */;
REPLACE INTO "jadwal" ("id", "tanggal", "bulan", "minggu_ke", "jenis", "tempat", "koordinator_id", "konten_obrolan", "catatan", "created_at", "tempat_id", "durasi_menit", "tim_id", "event_id", "jam_mulai") VALUES
	(2, '2026-04-22', 'April', 'W4', 'Badminton', 'GOR Mayasari', 3, 'Tidak Ada', 'Tidak Ada', '2026-05-19 07:51:01.708229', NULL, NULL, NULL, NULL, NULL),
	(3, '2026-05-03', 'May', 'W1', 'Jogging', 'Summarecon', 3, 'Sharing Hikmah Per Orang', '1. Dedi Jalan dari Kosan ke Summarecon 2. Dedi Cedera kaki', '2026-05-19 07:51:58.579444', NULL, NULL, NULL, NULL, NULL),
	(4, '2026-05-09', 'May', 'W2', 'Futsal', 'GOR Adiguna', 3, 'Tidak Ada', '1. Dedi Jalan dari Kosan ke Summarecon', '2026-05-19 07:52:37.974739', NULL, NULL, NULL, NULL, NULL),
	(5, '2026-05-17', 'May', 'W3', 'Badminton', 'GOR Purbaya', 4, 'Sharing Hikmah Per Orang', '1. Rafi (sakit) 2. Rizal (Rihlah bersama adik Mentornya) 3. Fajar S (Part time)', '2026-05-19 07:53:14.399509', NULL, NULL, NULL, NULL, NULL),
	(1, '2026-04-16', 'April', 'W3', 'Jogging', 'SR-Panyileukan', 2, '-', '1. Dedi ada bimbingan skripsi, jadi pulang 2. Dani sama Rifat ada Kuliah Online', '2026-05-19 07:50:23.02801', NULL, NULL, NULL, NULL, NULL),
	(6, '2026-05-23', 'May', 'W4', 'Badminton', 'GOR Purbaya', 3, '<p><br></p>', '<p><br></p>', '2026-05-21 15:45:32.456543', 3, 120, NULL, NULL, NULL);
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
REPLACE INTO "jenis_olahraga" ("id", "nama", "deskripsi", "created_at") VALUES
	(1, 'Jogging', 'Lari santai outdoor, fokus pada durasi dan jarak.', '2026-05-21 00:40:55.617378'),
	(2, 'Badminton', 'Pertandingan ganda/tunggal di GOR.', '2026-05-21 00:40:55.617378'),
	(3, 'Futsal', 'Sepak bola dalam ruangan, 5 vs 5.', '2026-05-21 00:40:55.617378'),
	(5, 'Renang', 'Renang gaya bebas atau dada.', '2026-05-21 00:40:55.617378'),
	(7, 'Basket', '', '2026-05-21 15:50:18.482263');
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

-- Dumping data for table public.login_attempts: -1 rows
/*!40000 ALTER TABLE "login_attempts" DISABLE KEYS */;
REPLACE INTO "login_attempts" ("id", "email", "ip", "success", "created_at") VALUES
	(1, 'firdam@sport.local', '::1', 1, '2026-05-22 00:12:26.427246'),
	(2, 'firdam@sport.local', '::1', 1, '2026-05-22 00:17:34.01573');
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
REPLACE INTO "member_eksternal" ("id", "jadwal_id", "nama_tamu", "dibawa_oleh_id") VALUES
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
REPLACE INTO "notifications" ("id", "user_id", "jenis", "judul", "isi", "url", "dibaca", "created_at") VALUES
	(1, 2, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.680877'),
	(2, 4, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.72524'),
	(3, 12, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.765297'),
	(4, 13, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.805361'),
	(5, 1, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.845381'),
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
	(17, 2, 'booking', 'Booking dibuat', 'Lapangan #3, 2026-05-23 16:00-18:00 (DP belum dibayar)', '/tempat.php', 0, '2026-05-22 00:45:14.401911');
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
REPLACE INTO "posts" ("id", "user_id", "caption", "foto_url", "jenis", "expired_at", "created_at") VALUES
	(1, 2, 'Mau pilih yang mana?', '/uploads/post_de4789966e32a4ae.jpg', 'post', NULL, '2026-05-22 00:38:30.628757'),
	(2, 2, 'Sip', NULL, 'post', NULL, '2026-05-22 00:41:18.455765');
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
REPLACE INTO "post_comments" ("id", "post_id", "user_id", "isi", "created_at") VALUES
	(1, 2, 2, 'wiss', '2026-05-22 00:42:50.755615');
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
REPLACE INTO "post_likes" ("post_id", "user_id", "created_at") VALUES
	(2, 2, '2026-05-22 00:42:45.671769');
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
/*!40000 ALTER TABLE "qr_tokens" ENABLE KEYS */;

-- Dumping structure for table public.rate_limit
CREATE TABLE IF NOT EXISTS "rate_limit" (
	"bucket" VARCHAR(120) NOT NULL,
	"ts" TIMESTAMP NOT NULL DEFAULT 'now()',
	INDEX "rl_idx" ("bucket", "ts")
);

-- Dumping data for table public.rate_limit: -1 rows
/*!40000 ALTER TABLE "rate_limit" DISABLE KEYS */;
REPLACE INTO "rate_limit" ("bucket", "ts") VALUES
	('book:2', '2026-05-22 00:45:14.275368');
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
REPLACE INTO "tempat" ("id", "nama", "alamat", "harga_lapang", "harga_per_jam", "status_booking", "catatan", "created_at", "lat", "lng") VALUES
	(1, 'GOR Adiguna', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715', NULL, NULL),
	(2, 'GOR Mayasari', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715', NULL, NULL),
	(4, 'SR-Panyileukan', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715', NULL, NULL),
	(5, 'Summarecon', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715', NULL, NULL),
	(3, 'GOR Purbaya', 'Jln. Ciguruwik', 25000.00, 25000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL),
	(6, 'GOR Azaka', '-', 0.00, 0.00, 'tersedia', '0', '2026-05-21 11:29:20.544026', NULL, NULL);
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
REPLACE INTO "tim" ("id", "nama", "jenis", "koordinator_id", "kuota", "catatan", "created_at") VALUES
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
REPLACE INTO "tim_member" ("tim_id", "user_id") VALUES
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
REPLACE INTO "upload_harian" ("id", "user_id", "tanggal", "jenis", "durasi_menit", "jarak_km", "kalori", "deskripsi", "file_path", "gdrive_url", "created_at", "pace", "pace_detik", "heart_rate", "rpe") VALUES
	(1, 1, '2026-05-19', 'Jogging', 12, 12.00, 12, 'tes', '/uploads/May_2026/Administrator-2026-05-19-Jogging.png', NULL, '2026-05-19 08:21:41.021259', NULL, NULL, NULL, NULL),
	(5, 1, '2026-05-21', 'Jogging', 60, 2.00, 2, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Administrator-2026-05-21-Jogging_8PMuV8B1C.jpg', '6a0e899a5c7cd75eb803caee', '2026-05-21 04:27:07.596483', NULL, NULL, NULL, NULL),
	(8, 2, '2026-05-15', 'Jogging', 13, 2.40, 187, 'Wow..', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg', '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34.100156', NULL, NULL, NULL, NULL),
	(7, 2, '2026-05-18', 'Jogging', 15, 2.26, 198, 'Tidak ada', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-21-Jogging_PGVG98kLK.jpg', '6a0e92aa5c7cd75eb83a10c4', '2026-05-21 05:05:47.220034', '6', NULL, NULL, NULL);
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
REPLACE INTO "users" ("id", "nama", "email", "password_hash", "role", "google_id", "created_at", "foto_url", "foto_file_id", "last_seen", "jenis_kelamin", "xp", "level", "streak_minggu", "bio", "dark_mode") VALUES
	(4, 'Dani', 'dani@sport.local', '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'admin', NULL, '2026-05-19 07:09:24.276208', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(12, 'Adith', 'adith@sport.local', '$2y$10$lrFgpD0ArMaHOpbvma/B9ebuuHjL6QffUVMD.D1kUfBp3RX1O2Xse', 'member', NULL, '2026-05-19 07:55:54.185236', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(13, 'Aziz', 'aziz@sport.local', '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', NULL, '2026-05-19 07:56:12.862165', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(1, 'Administrator', 'admin@sport.local', '$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa', 'admin', NULL, '2026-05-19 07:09:24.276208', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(8, 'Dedi', 'dedi@sport.local', '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', NULL, '2026-05-19 07:55:00.498075', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(6, 'Dendra', 'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', NULL, '2026-05-19 07:54:35.123756', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(7, 'Faiz', 'faiz@sport.local', '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', NULL, '2026-05-19 07:54:49.054143', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'member', NULL, '2026-05-19 07:56:28.908609', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(3, 'Rifat', 'rifat@sport.local', '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Rifat-avatar-1779378411_1K68zsR1h.jpg', '6a0f28ed5c7cd75eb84a1dad', '2026-05-21 15:47:47.464521', 'L', 0, 1, 0, NULL, 0),
	(15, 'Hanif', 'hanif@sport.local', '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', NULL, '2026-05-19 07:56:40.664031', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(2, 'Firdam', 'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Firdam-avatar-1779359762_loijDH3Ed.png', '6a0ee0135c7cd75eb87edbaf', '2026-05-22 01:30:07.444611', 'L', 150, 1, 0, NULL, 0),
	(10, 'Reyhan', 'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', NULL, '2026-05-19 07:55:29.376846', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(9, 'Rafi', 'rafi@sport.local', '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', NULL, '2026-05-19 07:55:12.485671', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(11, 'Rian', 'rian@sport.local', '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', NULL, '2026-05-19 07:55:42.436033', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0),
	(5, 'Usama', 'usama@sport.local', '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', NULL, '2026-05-19 07:54:22.015654', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0);
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
REPLACE INTO "user_badges" ("id", "user_id", "badge_id", "earned_at") VALUES
	(1, 2, 7, '2026-05-22 00:37:28.111196');
/*!40000 ALTER TABLE "user_badges" ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

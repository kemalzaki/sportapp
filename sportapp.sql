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

-- Dumping data for table public.absensi: 138 rows
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
	(441, 8, 16, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Ada kuliah sore sampai magrib'),
	(442, 8, 13, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Nyusul'),
	(443, 8, 4, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(444, 8, 8, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Ga ada kabar'),
	(445, 8, 6, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(446, 8, 7, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(447, 8, 20, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', 'Cuman 1 jam rencana'),
	(448, 8, 14, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Kerja'),
	(449, 8, 21, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(450, 8, 2, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Ketiduran'),
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
	(451, 8, 15, 0, 'manual', NULL, NULL, NULL, 0, 'izin', 'Kerja'),
	(452, 8, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Tidak ada kabar'),
	(453, 8, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Tidak ada kabar'),
	(454, 8, 11, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
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
	(165, 4, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(552, 10, 16, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(554, 10, 13, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(555, 10, 4, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Jemput dendra'),
	(556, 10, 8, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(557, 10, 6, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'With dendra'),
	(559, 10, 7, 0, 'manual', NULL, NULL, NULL, 0, 'sakit', NULL),
	(560, 10, 20, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(561, 10, 14, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(562, 10, 21, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(563, 10, 2, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Pertama'),
	(564, 10, 15, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Kedua'),
	(566, 10, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(567, 10, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(568, 10, 11, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Ketiga'),
	(569, 10, 3, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(570, 10, 17, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Keempat'),
	(424, 9, 16, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Telat'),
	(425, 9, 13, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(426, 9, 4, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(427, 9, 8, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(428, 9, 6, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(429, 9, 7, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Lagi ada tugas kuliah'),
	(430, 9, 20, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(431, 9, 14, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(432, 9, 21, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(433, 9, 2, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(434, 9, 15, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Kerja'),
	(435, 9, 9, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(436, 9, 10, 0, 'manual', NULL, NULL, NULL, 0, 'absen', NULL),
	(437, 9, 11, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(438, 9, 3, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(439, 9, 17, 1, 'manual', NULL, NULL, NULL, 0, 'telat', NULL),
	(440, 9, 5, 0, 'manual', NULL, NULL, NULL, 0, 'absen', 'Lomba katanya'),
	(455, 8, 3, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(456, 8, 17, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Insya allah'),
	(457, 8, 5, 1, 'manual', NULL, NULL, NULL, 0, 'hadir', NULL),
	(572, 10, 5, 1, 'manual', NULL, NULL, NULL, 0, 'telat', 'Bersama rifat');
/*!40000 ALTER TABLE "absensi" ENABLE KEYS */;

-- Dumping structure for table public.app_settings
CREATE TABLE IF NOT EXISTS "app_settings" (
	"skey" VARCHAR(80) NOT NULL,
	"sval" TEXT NOT NULL DEFAULT '',
	"keterangan" TEXT NULL DEFAULT NULL,
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("skey")
);

-- Dumping data for table public.app_settings: -1 rows
/*!40000 ALTER TABLE "app_settings" DISABLE KEYS */;
INSERT INTO "app_settings" ("skey", "sval", "keterangan", "updated_at") VALUES
	('biaya_admin_fixed', '4000', 'Biaya admin Midtrans fixed (Rp) per transaksi', '2026-06-02 07:20:16.747266'),
	('biaya_admin_pct', '0.007', 'Biaya admin Midtrans persen (0.007 = 0.7%)', '2026-06-02 07:20:16.747266'),
	('biaya_aplikasi_fixed', '1000', 'Biaya aplikasi fixed (Rp) per transaksi', '2026-06-02 07:20:16.747266'),
	('biaya_aplikasi_pct', '0', 'Biaya aplikasi persen', '2026-06-02 07:20:16.747266'),
	('invoice_email_from', 'no-reply@hapfam.local', 'Alamat email pengirim invoice', '2026-06-02 07:20:16.747266'),
	('invoice_email_nama', 'HapFam SportApp', 'Nama pengirim invoice', '2026-06-02 07:20:16.747266');
/*!40000 ALTER TABLE "app_settings" ENABLE KEYS */;

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

-- Dumping structure for table public.catatan_hafalan
CREATE TABLE IF NOT EXISTS "catatan_hafalan" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''catatan_hafalan_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"jenis" VARCHAR(40) NOT NULL DEFAULT 'Quran',
	"judul" VARCHAR(200) NOT NULL,
	"referensi" VARCHAR(200) NULL DEFAULT NULL,
	"target_ayat" INTEGER NULL DEFAULT '0',
	"sudah_ayat" INTEGER NULL DEFAULT '0',
	"status" VARCHAR(20) NOT NULL DEFAULT 'progress',
	"catatan" TEXT NULL DEFAULT NULL,
	"last_review" DATE NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "catatan_hafalan_user_idx" ("user_id")
);

-- Dumping data for table public.catatan_hafalan: -1 rows
/*!40000 ALTER TABLE "catatan_hafalan" DISABLE KEYS */;
INSERT INTO "catatan_hafalan" ("id", "user_id", "jenis", "judul", "referensi", "target_ayat", "sudah_ayat", "status", "catatan", "last_review", "created_at", "updated_at") VALUES
	(1, 2, 'Quran', 'MI Pokok', '76:1-2', 1, 1, 'selesai', '', NULL, '2026-06-11 17:31:13.443017', '2026-06-11 17:32:01.99662');
/*!40000 ALTER TABLE "catatan_hafalan" ENABLE KEYS */;

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
	(15, 20, 'ayat_harian', '2026-05-29', NULL, '2026-05-29 13:03:20.644834'),
	(16, 14, 'ayat_harian', '2026-05-29', NULL, '2026-05-29 17:24:49.678454'),
	(17, 2, 'ayat_harian', '2026-05-30', NULL, '2026-05-30 10:21:32.107295'),
	(18, 3, 'ayat_harian', '2026-05-31', NULL, '2026-05-31 16:40:08.47246'),
	(51, 16, 'ayat_harian', '2026-06-01', NULL, '2026-06-01 15:08:27.24533'),
	(52, 6, 'ayat_harian', '2026-06-01', NULL, '2026-06-01 22:14:46.78882');
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
	(4, 3, 'wa''alikumussalam', '2026-05-21 15:47:46.367728', NULL, NULL),
	(6, 2, 'Semangat malam. Untuk absen, dilakukan di area sekitar lapang, karena radius absen 150 meter dari lokasi. Terimakasih.', '2026-05-22 16:36:35.671593', NULL, '2026-05-23 05:52:06.486691'),
	(8, 4, 'Semangat pagi. Siapp laksanakan', '2026-05-23 06:18:22.527654', 6, NULL),
	(5, 2, 'siap kawans', '2026-05-22 00:37:27.733498', 4, '2026-05-23 06:42:22.877495'),
	(9, 2, 'Pengumuman, sudah ada kalkulator sehat, bisa dicoba', '2026-05-23 07:05:27.882099', NULL, NULL),
	(10, 4, 'Mantappp', '2026-05-23 16:24:57.427956', 9, NULL),
	(12, 2, 'ada fitur Kalender Hijriyah & Puasa Sunnah , boleh dicek', '2026-05-24 08:48:22.594551', NULL, NULL),
	(13, 2, 'Jadwal olahraga jogging pagi ini di rescheduke ke selaaa 2 juni 2026 sorean..', '2026-06-01 13:11:04.649424', NULL, NULL),
	(14, 6, 'Assalamualaikum sadayana☺️', '2026-06-01 22:16:47.249183', NULL, NULL),
	(15, 3, 'wa''alikumussalam broo', '2026-06-01 22:18:32.284391', 14, NULL),
	(16, 2, 'Walaikumsalam', '2026-06-02 00:06:07.527515', 14, '2026-06-02 00:06:28.40659'),
	(17, 3, 'info yang sudah di gor gess', '2026-06-02 16:10:53.192261', NULL, NULL),
	(18, 2, 'Perlengkapan Olahraga dan Pengalaman Hiking Camping tidak lupa di isi ya guys', '2026-06-03 05:39:14.331787', NULL, NULL);
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
	(9, 14, 1),
	(12, 15, 1),
	(9, 15, 1),
	(14, 6, 1),
	(14, 3, 1),
	(14, 2, 1),
	(17, 2, 1),
	(15, 2, -1),
	(18, 3, 1),
	(18, 2, 1);
/*!40000 ALTER TABLE "chat_reactions" ENABLE KEYS */;

-- Dumping structure for table public.device_locations
CREATE TABLE IF NOT EXISTS "device_locations" (
	"user_id" INTEGER NOT NULL,
	"lat" NUMERIC(10,6) NOT NULL,
	"lng" NUMERIC(10,6) NOT NULL,
	"accuracy_m" NUMERIC(8,2) NULL DEFAULT NULL,
	"device_label" VARCHAR(120) NULL DEFAULT NULL,
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "device_locations_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.device_locations: 8 rows
/*!40000 ALTER TABLE "device_locations" DISABLE KEYS */;
INSERT INTO "device_locations" ("user_id", "lat", "lng", "accuracy_m", "device_label", "updated_at") VALUES
	(13, -6.890229, 107.608901, 11.38, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-08 14:21:27.484395'),
	(14, -6.292972, 107.302198, 20.00, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-01 09:16:06.165449'),
	(21, -6.246796, 107.071909, 28.10, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-10 21:24:09.895067'),
	(3, -6.928889, 107.714629, 100.00, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Sa', '2026-06-14 09:21:18.785333'),
	(6, -6.914244, 107.697175, 14.68, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-01 22:25:39.035786'),
	(4, -6.932078, 107.713653, 116.10, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Sa', '2026-06-15 07:47:36.343491'),
	(11, -6.938349, 107.715934, 87.60, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-13 05:34:16.727002'),
	(2, -6.925500, 107.729660, 182.00, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-15 12:23:40.432535');
/*!40000 ALTER TABLE "device_locations" ENABLE KEYS */;

-- Dumping structure for table public.device_location_history
CREATE TABLE IF NOT EXISTS "device_location_history" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''device_location_history_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"lat" NUMERIC(10,6) NOT NULL,
	"lng" NUMERIC(10,6) NOT NULL,
	"accuracy_m" NUMERIC(8,2) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "device_loc_hist_user_idx" ("user_id", "created_at"),
	CONSTRAINT "device_location_history_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.device_location_history: 540 rows
/*!40000 ALTER TABLE "device_location_history" DISABLE KEYS */;
INSERT INTO "device_location_history" ("id", "user_id", "lat", "lng", "accuracy_m", "created_at") VALUES
	(431, 6, -6.914244, 107.697168, 14.93, '2026-06-01 22:12:19.118774'),
	(434, 6, -6.914253, 107.697165, 17.07, '2026-06-01 22:13:09.16133'),
	(440, 6, -6.914249, 107.697175, 20.42, '2026-06-01 22:14:52.413539'),
	(443, 6, -6.914243, 107.697171, 13.85, '2026-06-01 22:17:15.132446'),
	(446, 6, -6.914240, 107.697168, 15.38, '2026-06-01 22:18:14.464798'),
	(449, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:20:14.64417'),
	(452, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:20:56.289452'),
	(455, 3, -6.914249, 107.697170, 100.00, '2026-06-01 22:21:17.057545'),
	(458, 3, -6.914249, 107.697170, 100.00, '2026-06-01 22:21:45.660929'),
	(461, 3, -6.914249, 107.697170, 100.00, '2026-06-01 22:22:30.112235'),
	(464, 6, -6.914244, 107.697171, 14.54, '2026-06-01 22:23:04.384555'),
	(467, 6, -6.914244, 107.697175, 14.68, '2026-06-01 22:24:19.201369'),
	(470, 6, -6.914244, 107.697175, 14.68, '2026-06-01 22:24:57.157541'),
	(473, 6, -6.914244, 107.697175, 14.68, '2026-06-01 22:25:39.076751'),
	(800, 3, -6.928900, 107.714215, 100.00, '2026-06-04 15:32:26.533605'),
	(804, 3, -6.928900, 107.714215, 100.00, '2026-06-04 15:35:23.841067'),
	(807, 3, -6.928564, 107.714585, 100.00, '2026-06-04 15:36:25.718356'),
	(810, 4, -6.914240, 107.697175, 100.00, '2026-06-04 19:14:33.814968'),
	(819, 4, -6.955226, 107.696282, 110.00, '2026-06-05 07:29:06.695733'),
	(822, 4, -6.955308, 107.696204, 100.00, '2026-06-05 07:32:50.920138'),
	(825, 11, -6.955146, 107.693491, 3.07, '2026-06-05 07:57:21.913639'),
	(834, 11, -6.939466, 107.707478, 18.77, '2026-06-05 15:12:25.612628'),
	(837, 11, -6.939403, 107.707492, 22.41, '2026-06-05 15:14:02.009853'),
	(938, 2, -6.925516, 107.729487, 42.50, '2026-06-06 12:16:48.229816'),
	(942, 2, -6.925565, 107.729447, 8.30, '2026-06-06 12:23:47.652283'),
	(946, 3, -6.928952, 107.714696, 98.40, '2026-06-06 13:21:44.167489'),
	(950, 3, -6.930757, 107.712949, 100.00, '2026-06-06 14:33:08.199913'),
	(954, 11, -6.940840, 107.714570, 14.22, '2026-06-06 14:46:15.4874'),
	(958, 11, -6.940838, 107.714572, 14.27, '2026-06-06 14:47:57.908546'),
	(962, 2, -6.914311, 107.697152, 21.65, '2026-06-06 15:07:16.189665'),
	(966, 2, -6.904650, 107.674180, 10.50, '2026-06-06 16:00:33.727254'),
	(970, 2, -6.980117, 107.561730, 10.20, '2026-06-07 10:32:16.354574'),
	(78, 3, -6.906189, 107.657360, 128.37, '2026-05-31 16:06:17.643089'),
	(79, 3, -6.907269, 107.657232, 100.00, '2026-05-31 16:08:14.281336'),
	(80, 3, -6.907269, 107.657232, 100.00, '2026-05-31 16:09:33.818083'),
	(81, 3, -6.907269, 107.657232, 100.00, '2026-05-31 16:10:24.264631'),
	(82, 3, -6.907269, 107.657232, 100.00, '2026-05-31 16:13:50.295841'),
	(83, 3, -6.907563, 107.657972, 100.00, '2026-05-31 16:14:08.359027'),
	(84, 3, -6.906640, 107.657430, 100.00, '2026-05-31 16:14:13.419149'),
	(85, 3, -6.906640, 107.657430, 100.00, '2026-05-31 16:15:06.698168'),
	(86, 3, -6.906640, 107.657430, 100.00, '2026-05-31 16:15:31.171919'),
	(87, 3, -6.901992, 107.655138, 8.73, '2026-05-31 16:30:01.967854'),
	(88, 3, -6.902028, 107.655140, 50.11, '2026-05-31 16:30:35.165213'),
	(89, 3, -6.902028, 107.655140, 92.12, '2026-05-31 16:31:07.331414'),
	(90, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:31:46.438103'),
	(91, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:32:19.164335'),
	(92, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:32:54.919811'),
	(93, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:33:27.736967'),
	(94, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:33:57.968374'),
	(95, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:34:56.778641'),
	(96, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:35:34.508509'),
	(97, 3, -6.902028, 107.655140, 100.00, '2026-05-31 16:36:27.437737'),
	(98, 3, -6.901970, 107.655123, 15.43, '2026-05-31 16:40:05.411649'),
	(99, 3, -6.901956, 107.655125, 15.92, '2026-05-31 16:40:22.824217'),
	(100, 3, -6.901970, 107.655115, 16.24, '2026-05-31 16:41:05.341275'),
	(101, 3, -6.901970, 107.655115, 100.00, '2026-05-31 16:43:15.470448'),
	(102, 3, -6.901970, 107.655115, 100.00, '2026-05-31 16:43:41.532509'),
	(103, 3, -6.901937, 107.655092, 15.85, '2026-05-31 16:45:26.699036'),
	(559, 4, -6.931886, 107.713719, 81.62, '2026-06-02 11:23:11.519965'),
	(560, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:24:44.999247'),
	(561, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:25:23.319291'),
	(562, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:25:51.495763'),
	(879, 2, -6.925516, 107.729512, 36.90, '2026-06-06 08:47:53.688746'),
	(882, 2, -6.925514, 107.729511, 64.10, '2026-06-06 08:49:14.795389'),
	(885, 2, -6.925510, 107.729548, 34.40, '2026-06-06 08:52:09.940325'),
	(888, 2, -6.925516, 107.729478, 30.00, '2026-06-06 09:42:46.195225'),
	(891, 2, -6.925513, 107.729509, 34.40, '2026-06-06 09:43:52.500097'),
	(894, 2, -6.925507, 107.729505, 17.64, '2026-06-06 09:45:39.19309'),
	(897, 2, -6.925513, 107.729531, 32.10, '2026-06-06 09:48:14.631857'),
	(900, 2, -6.925515, 107.729540, 9.10, '2026-06-06 09:49:53.298119'),
	(903, 2, -6.925516, 107.729549, 28.10, '2026-06-06 09:51:23.926171'),
	(906, 2, -6.925516, 107.729518, 30.00, '2026-06-06 09:54:09.039071'),
	(909, 2, -6.925492, 107.729432, 8.20, '2026-06-06 09:56:41.288157'),
	(912, 2, -6.925511, 107.729467, 17.63, '2026-06-06 10:02:15.402884'),
	(915, 2, -6.925375, 107.729555, 10.50, '2026-06-06 10:49:54.660523'),
	(918, 2, -6.925516, 107.729523, 28.10, '2026-06-06 10:50:50.103252'),
	(921, 2, -6.925360, 107.729630, 7.20, '2026-06-06 10:51:53.188877'),
	(924, 2, -6.925457, 107.729610, 6.40, '2026-06-06 10:53:38.498375'),
	(927, 2, -6.925516, 107.729482, 26.40, '2026-06-06 11:29:10.075538'),
	(930, 2, -6.925510, 107.729628, 9.80, '2026-06-06 11:52:28.769536'),
	(933, 2, -6.925517, 107.729447, 7.60, '2026-06-06 11:54:15.003539'),
	(936, 2, -6.925515, 107.729515, 36.90, '2026-06-06 12:12:48.117313'),
	(429, 6, -6.914253, 107.697169, 34.40, '2026-06-01 22:11:36.709962'),
	(432, 6, -6.914240, 107.697164, 26.40, '2026-06-01 22:12:31.127865'),
	(435, 6, -6.914244, 107.697145, 22.84, '2026-06-01 22:13:27.885783'),
	(438, 6, -6.914247, 107.697163, 48.90, '2026-06-01 22:14:21.683391'),
	(441, 6, -6.914248, 107.697158, 82.50, '2026-06-01 22:15:11.200288'),
	(444, 4, -6.914464, 107.697124, 84.38, '2026-06-01 22:17:57.638881'),
	(447, 6, -6.914247, 107.697168, 14.26, '2026-06-01 22:18:30.304242'),
	(117, 3, -6.914253, 107.697175, 100.00, '2026-05-31 23:50:56.179853'),
	(118, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:51:03.649937'),
	(450, 3, -6.914249, 107.697170, 100.00, '2026-06-01 22:20:17.540101'),
	(453, 6, -6.914248, 107.697172, 15.19, '2026-06-01 22:20:58.536196'),
	(121, 3, -6.914253, 107.697175, 100.00, '2026-05-31 23:51:36.47147'),
	(122, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:51:55.996465'),
	(123, 3, -6.914253, 107.697175, 100.00, '2026-05-31 23:52:05.567464'),
	(456, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:21:26.983415'),
	(125, 3, -6.914454, 107.697301, 49.14, '2026-05-31 23:52:58.320067'),
	(126, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:53:12.025083'),
	(127, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:53:44.840796'),
	(459, 6, -6.914244, 107.697171, 14.54, '2026-06-01 22:21:56.47828'),
	(129, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:54:13.52953'),
	(462, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:22:31.329956'),
	(465, 6, -6.914242, 107.697167, 14.49, '2026-06-01 22:23:37.300186'),
	(132, 3, -6.914454, 107.697301, 100.00, '2026-05-31 23:55:50.14471'),
	(133, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:56:13.409657'),
	(468, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:24:34.663227'),
	(135, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:56:45.836936'),
	(471, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:25:04.550666'),
	(138, 3, -6.914454, 107.697301, 100.00, '2026-05-31 23:57:22.670545'),
	(139, 4, -6.914251, 107.697157, 100.00, '2026-05-31 23:58:10.502113'),
	(140, 3, -6.914454, 107.697301, 100.00, '2026-05-31 23:58:31.611731'),
	(141, 4, -6.914162, 107.697178, 99.64, '2026-06-01 00:00:03.881515'),
	(143, 4, -6.914162, 107.697178, 100.00, '2026-06-01 00:02:10.510743'),
	(145, 4, -6.914162, 107.697178, 100.00, '2026-06-01 00:08:41.815307'),
	(147, 4, -6.914259, 107.697167, 14.51, '2026-06-01 00:09:03.732252'),
	(801, 3, -6.928900, 107.714215, 100.00, '2026-06-04 15:33:39.918187'),
	(149, 4, -6.914255, 107.697156, 14.29, '2026-06-01 00:10:05.120751'),
	(939, 2, -6.925516, 107.729514, 56.10, '2026-06-06 12:20:47.31373'),
	(151, 4, -6.914257, 107.697163, 72.22, '2026-06-01 00:11:10.530311'),
	(943, 2, -6.925510, 107.729450, 8.00, '2026-06-06 12:23:55.312889'),
	(947, 3, -6.928905, 107.714582, 53.08, '2026-06-06 13:23:26.373689'),
	(154, 4, -6.914255, 107.697171, 15.02, '2026-06-01 00:13:02.765293'),
	(951, 11, -6.940841, 107.714578, 14.02, '2026-06-06 14:45:02.774256'),
	(955, 11, -6.940840, 107.714576, 13.64, '2026-06-06 14:47:16.982329'),
	(959, 11, -6.940832, 107.714570, 16.62, '2026-06-06 14:48:06.001653'),
	(963, 2, -6.914340, 107.697139, 22.94, '2026-06-06 15:07:56.40269'),
	(635, 3, -6.928974, 107.714717, 104.10, '2026-06-03 05:56:26.695484'),
	(638, 3, -6.929037, 107.714701, 110.00, '2026-06-03 05:59:46.302498'),
	(641, 3, -6.928797, 107.714451, 128.90, '2026-06-03 06:04:13.075208'),
	(967, 3, -6.930734, 107.712935, 21.60, '2026-06-06 16:40:39.755043'),
	(971, 2, -6.913245, 107.659542, 12.76, '2026-06-07 13:37:24.351678'),
	(974, 2, -6.913232, 107.659539, 13.72, '2026-06-07 13:42:26.371814'),
	(977, 2, -6.912978, 107.659400, 10.20, '2026-06-07 14:26:49.570049'),
	(980, 13, -6.890229, 107.608901, 11.38, '2026-06-08 14:21:27.529916'),
	(983, 3, -6.928831, 107.714617, 130.78, '2026-06-09 11:24:30.901127'),
	(168, 14, -6.292970, 107.302204, 19.56, '2026-06-01 09:12:22.897825'),
	(169, 14, -6.292973, 107.302197, 20.00, '2026-06-01 09:13:31.3428'),
	(170, 14, -6.292966, 107.302206, 14.85, '2026-06-01 09:13:55.641584'),
	(171, 14, -6.292970, 107.302204, 24.93, '2026-06-01 09:14:01.722147'),
	(172, 14, -6.292970, 107.302202, 20.00, '2026-06-01 09:14:30.933659'),
	(173, 14, -6.292967, 107.302203, 20.00, '2026-06-01 09:15:05.062035'),
	(174, 14, -6.292968, 107.302201, 18.21, '2026-06-01 09:15:28.912681'),
	(175, 14, -6.292972, 107.302198, 20.00, '2026-06-01 09:16:06.20698'),
	(986, 3, -6.930860, 107.718375, 82.11, '2026-06-09 13:15:29.707789'),
	(989, 2, -6.925518, 107.729478, 64.10, '2026-06-10 05:15:24.835567'),
	(992, 2, -6.925516, 107.729470, 82.50, '2026-06-10 05:21:31.393331'),
	(995, 2, -6.925516, 107.729468, 92.90, '2026-06-10 05:25:34.496868'),
	(998, 2, -6.925516, 107.729467, 98.40, '2026-06-10 12:33:12.871068'),
	(1001, 2, -6.925516, 107.729470, 82.50, '2026-06-10 12:38:54.405851'),
	(1004, 3, -6.930682, 107.718302, 17.55, '2026-06-10 13:22:33.214044'),
	(1007, 3, -6.930682, 107.718302, 100.00, '2026-06-10 13:24:13.150525'),
	(1010, 2, -6.931954, 107.714722, 111.00, '2026-06-10 20:57:49.938848'),
	(1013, 21, -6.246797, 107.071908, 22.50, '2026-06-10 21:10:41.949912'),
	(1016, 21, -6.246797, 107.071909, 20.00, '2026-06-10 21:12:00.503517'),
	(1019, 21, -6.246797, 107.071905, 26.40, '2026-06-10 21:13:35.252857'),
	(1022, 21, -6.246793, 107.071907, 22.50, '2026-06-10 21:14:15.550127'),
	(1025, 21, -6.246797, 107.071908, 22.50, '2026-06-10 21:21:14.851175'),
	(734, 3, -6.930821, 107.718245, 100.00, '2026-06-03 13:37:17.858033'),
	(737, 11, -6.940841, 107.714567, 18.17, '2026-06-03 14:23:54.522728'),
	(230, 4, -6.925610, 107.729378, 500.00, '2026-06-01 12:38:13.507923'),
	(231, 4, -6.925610, 107.729378, 500.00, '2026-06-01 12:38:32.061847'),
	(760, 3, -6.928900, 107.714215, 100.00, '2026-06-03 19:13:00.259043'),
	(762, 3, -6.928900, 107.714215, 100.00, '2026-06-03 19:16:56.485958'),
	(1028, 21, -6.246796, 107.071909, 28.10, '2026-06-10 21:24:09.935823'),
	(1031, 3, -6.932093, 107.715132, 4.39, '2026-06-11 06:43:17.525237'),
	(1034, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:04:00.539286'),
	(1037, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:08:32.694918'),
	(1040, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:11:42.365642'),
	(1043, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:13:27.406817'),
	(1046, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:14:57.611951'),
	(1049, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:16:37.219233'),
	(1052, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:51:25.570613'),
	(636, 3, -6.928933, 107.714648, 98.40, '2026-06-03 05:57:04.823617'),
	(639, 3, -6.928797, 107.714451, 128.90, '2026-06-03 06:01:36.707764'),
	(270, 4, -6.955900, 107.649900, 20000.00, '2026-06-01 13:15:09.568561'),
	(271, 4, -6.955900, 107.649900, 20000.00, '2026-06-01 13:15:16.771537'),
	(272, 4, -6.955900, 107.649900, 20000.00, '2026-06-01 13:15:32.686506'),
	(273, 4, -6.955900, 107.649900, 20000.00, '2026-06-01 13:15:58.72261'),
	(277, 4, -6.925610, 107.729378, 500.00, '2026-06-01 13:17:53.586953'),
	(735, 3, -6.930821, 107.718245, 100.00, '2026-06-03 13:38:28.694685'),
	(281, 4, -6.925610, 107.729378, 500.00, '2026-06-01 13:25:54.696475'),
	(282, 4, -6.925610, 107.729378, 381.00, '2026-06-01 13:27:55.225595'),
	(283, 4, -6.925610, 107.729378, 381.00, '2026-06-01 13:29:57.442825'),
	(284, 4, -6.925610, 107.729378, 500.00, '2026-06-01 13:31:56.343209'),
	(285, 4, -6.925610, 107.729378, 381.00, '2026-06-01 13:33:59.28349'),
	(286, 4, -6.925610, 107.729378, 381.00, '2026-06-01 13:35:57.783616'),
	(287, 4, -6.925610, 107.729378, 381.00, '2026-06-01 13:37:57.750906'),
	(296, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:09:09.292807'),
	(297, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:09:28.764134'),
	(298, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:09:35.910165'),
	(299, 4, -6.925610, 107.729378, 500.00, '2026-06-01 14:11:32.10936'),
	(759, 3, -6.928886, 107.714585, 200.00, '2026-06-03 17:40:23.156695'),
	(301, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:12:49.638134'),
	(761, 3, -6.928900, 107.714215, 100.00, '2026-06-03 19:15:47.818704'),
	(305, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:14:41.398264'),
	(306, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:15:44.367443'),
	(312, 4, -6.925610, 107.729378, 500.00, '2026-06-01 14:17:36.962247'),
	(315, 4, -6.925610, 107.729378, 381.00, '2026-06-01 14:19:37.54986'),
	(316, 4, -6.925610, 107.729378, 500.00, '2026-06-01 14:21:36.964166'),
	(802, 3, -6.928900, 107.714215, 100.00, '2026-06-04 15:34:10.170921'),
	(805, 3, -6.928564, 107.714585, 100.00, '2026-06-04 15:35:34.102806'),
	(808, 3, -6.928564, 107.714585, 100.00, '2026-06-04 15:36:50.46907'),
	(811, 3, -6.914257, 107.697173, 100.00, '2026-06-04 21:25:17.026417'),
	(820, 4, -6.955308, 107.696204, 96.25, '2026-06-05 07:29:23.780802'),
	(823, 4, -6.955424, 107.696330, 104.10, '2026-06-05 07:34:49.503086'),
	(835, 11, -6.939366, 107.707300, 7.65, '2026-06-05 15:12:40.982845'),
	(838, 11, -6.939409, 107.707487, 6.43, '2026-06-05 15:14:10.64582'),
	(877, 2, -6.925500, 107.729592, 54.30, '2026-06-06 08:46:33.240734'),
	(880, 2, -6.925514, 107.729555, 34.40, '2026-06-06 08:48:21.49244'),
	(883, 2, -6.925407, 107.729603, 9.40, '2026-06-06 08:49:58.230044'),
	(886, 2, -6.925515, 107.729492, 39.60, '2026-06-06 09:34:28.375882'),
	(889, 2, -6.925513, 107.729497, 34.40, '2026-06-06 09:43:05.833'),
	(892, 2, -6.925510, 107.729532, 8.30, '2026-06-06 09:44:07.483185'),
	(895, 2, -6.925511, 107.729524, 17.10, '2026-06-06 09:45:47.234952'),
	(898, 2, -6.925507, 107.729542, 9.00, '2026-06-06 09:48:25.734508'),
	(901, 2, -6.925515, 107.729552, 32.10, '2026-06-06 09:50:19.720407'),
	(904, 21, -6.925515, 107.729540, 36.90, '2026-06-06 09:52:29.698239'),
	(907, 2, -6.925512, 107.729511, 28.10, '2026-06-06 09:55:16.319829'),
	(910, 2, -6.925515, 107.729492, 36.90, '2026-06-06 09:57:56.733195'),
	(913, 2, -6.925515, 107.729516, 39.60, '2026-06-06 10:07:25.341496'),
	(916, 2, -6.925417, 107.729475, 9.40, '2026-06-06 10:50:09.302732'),
	(919, 2, -6.925452, 107.729547, 7.80, '2026-06-06 10:51:07.537218'),
	(922, 2, -6.925443, 107.729627, 7.10, '2026-06-06 10:52:16.825109'),
	(925, 2, -6.925513, 107.729540, 6.50, '2026-06-06 10:53:45.827818'),
	(928, 2, -6.925516, 107.729482, 26.40, '2026-06-06 11:29:28.276366'),
	(931, 2, -6.925516, 107.729489, 26.40, '2026-06-06 11:53:43.38284'),
	(934, 2, -6.925514, 107.729555, 36.90, '2026-06-06 11:55:37.975551'),
	(937, 2, -6.925513, 107.729497, 34.40, '2026-06-06 12:14:49.496938'),
	(347, 4, -6.931890, 107.713711, 100.00, '2026-06-01 16:32:38.313528'),
	(348, 4, -6.931890, 107.713711, 100.00, '2026-06-01 16:34:50.787229'),
	(349, 4, -6.931832, 107.713737, 100.00, '2026-06-01 16:36:33.128683'),
	(350, 3, -6.914248, 107.697173, 19.89, '2026-06-01 19:14:02.594452'),
	(351, 3, -6.914248, 107.697173, 100.00, '2026-06-01 19:16:13.952241'),
	(352, 3, -6.914248, 107.697173, 100.00, '2026-06-01 19:16:41.625818'),
	(353, 3, -6.914248, 107.697173, 100.00, '2026-06-01 19:17:16.490991'),
	(803, 3, -6.928900, 107.714215, 100.00, '2026-06-04 15:34:40.748006'),
	(806, 3, -6.928564, 107.714585, 100.00, '2026-06-04 15:35:49.245549'),
	(809, 4, -6.914240, 107.697175, 100.00, '2026-06-04 19:12:35.462545'),
	(818, 4, -6.955226, 107.696282, 110.00, '2026-06-05 07:28:30.782166'),
	(821, 4, -6.955308, 107.696204, 100.00, '2026-06-05 07:31:06.925869'),
	(824, 11, -6.954683, 107.693382, 3.12, '2026-06-05 07:56:53.136995'),
	(833, 11, -6.939303, 107.707507, 22.34, '2026-06-05 15:11:45.227538'),
	(836, 11, -6.939409, 107.707474, 21.97, '2026-06-05 15:13:28.548131'),
	(839, 11, -6.939473, 107.707468, 18.73, '2026-06-05 15:14:34.901844'),
	(878, 2, -6.925509, 107.729530, 68.40, '2026-06-06 08:46:51.953511'),
	(881, 2, -6.925517, 107.729546, 52.40, '2026-06-06 08:49:09.348733'),
	(884, 2, -6.925513, 107.729493, 32.10, '2026-06-06 08:51:59.688403'),
	(887, 2, -6.925513, 107.729535, 34.40, '2026-06-06 09:36:56.870459'),
	(890, 2, -6.925515, 107.729521, 23.60, '2026-06-06 09:43:44.181613'),
	(893, 2, -6.925513, 107.729509, 36.90, '2026-06-06 09:45:27.277549'),
	(896, 2, -6.925515, 107.729523, 42.50, '2026-06-06 09:47:44.488927'),
	(899, 2, -6.925511, 107.729550, 34.40, '2026-06-06 09:49:18.715415'),
	(902, 2, -6.925515, 107.729553, 30.00, '2026-06-06 09:50:50.479762'),
	(905, 2, -6.925517, 107.729527, 32.10, '2026-06-06 09:53:59.50784'),
	(908, 2, -6.925525, 107.729462, 8.60, '2026-06-06 09:56:14.757386'),
	(911, 2, -6.925515, 107.729492, 36.90, '2026-06-06 09:58:07.256642'),
	(914, 2, -6.925515, 107.729515, 34.40, '2026-06-06 10:07:47.199748'),
	(917, 2, -6.923447, 107.728888, 12.30, '2026-06-06 10:50:29.013802'),
	(920, 2, -6.925497, 107.729602, 7.40, '2026-06-06 10:51:42.789205'),
	(923, 2, -6.925447, 107.729527, 6.70, '2026-06-06 10:52:47.659526'),
	(926, 2, -6.925517, 107.729501, 60.00, '2026-06-06 11:23:34.519659'),
	(929, 2, -6.925516, 107.729518, 30.00, '2026-06-06 11:52:20.110016'),
	(932, 2, -6.925530, 107.729500, 8.50, '2026-06-06 11:53:58.991559'),
	(935, 2, -6.925515, 107.729527, 45.60, '2026-06-06 12:10:51.040765'),
	(940, 2, -6.925516, 107.729514, 56.10, '2026-06-06 12:20:54.14191'),
	(944, 2, -6.925525, 107.729485, 7.00, '2026-06-06 12:24:19.367426'),
	(948, 2, -6.914508, 107.697180, 6.90, '2026-06-06 13:55:44.814329'),
	(952, 11, -6.940842, 107.714576, 13.65, '2026-06-06 14:45:49.625579'),
	(956, 11, -6.940836, 107.714573, 15.18, '2026-06-06 14:47:31.06138'),
	(960, 2, -6.914255, 107.697173, 18.69, '2026-06-06 15:06:52.759816'),
	(964, 3, -6.930735, 107.712931, 20.40, '2026-06-06 15:45:03.434862'),
	(968, 3, -6.930724, 107.712922, 21.60, '2026-06-06 16:41:15.009297'),
	(972, 2, -6.913237, 107.659538, 13.40, '2026-06-07 13:37:32.239455'),
	(975, 2, -6.913248, 107.659544, 13.14, '2026-06-07 13:42:35.577495'),
	(978, 2, -6.913224, 107.659540, 14.94, '2026-06-07 14:28:50.29609'),
	(981, 3, -6.928831, 107.714617, 130.78, '2026-06-09 11:23:21.191659'),
	(984, 3, -6.930860, 107.718375, 82.11, '2026-06-09 13:14:49.178085'),
	(987, 2, -6.925512, 107.729486, 56.10, '2026-06-10 05:11:30.275678'),
	(990, 2, -6.925516, 107.729471, 77.60, '2026-06-10 05:17:24.227257'),
	(993, 2, -6.925049, 107.729516, 24.97, '2026-06-10 05:22:00.734514'),
	(996, 2, -6.925516, 107.729469, 87.60, '2026-06-10 05:25:53.213146'),
	(999, 2, -6.925516, 107.729468, 92.90, '2026-06-10 12:34:54.579197'),
	(1002, 2, -6.925516, 107.729469, 87.60, '2026-06-10 12:40:59.090998'),
	(430, 6, -6.914254, 107.697171, 34.40, '2026-06-01 22:11:49.775814'),
	(433, 6, -6.914231, 107.697158, 18.01, '2026-06-01 22:12:43.562276'),
	(436, 6, -6.914251, 107.697172, 56.10, '2026-06-01 22:13:44.968173'),
	(1005, 3, -6.930682, 107.718302, 69.71, '2026-06-10 13:23:10.611135'),
	(442, 6, -6.914245, 107.697152, 20.59, '2026-06-01 22:15:18.349904'),
	(445, 3, -6.914261, 107.697175, 100.00, '2026-06-01 22:18:06.962233'),
	(448, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:19:30.920853'),
	(451, 6, -6.914250, 107.697172, 16.86, '2026-06-01 22:20:29.018057'),
	(454, 6, -6.914241, 107.697164, 14.04, '2026-06-01 22:21:11.169758'),
	(457, 6, -6.914245, 107.697171, 15.09, '2026-06-01 22:21:37.886727'),
	(460, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:21:58.029452'),
	(463, 4, -6.914464, 107.697124, 100.00, '2026-06-01 22:22:56.467802'),
	(466, 6, -6.914244, 107.697175, 14.68, '2026-06-01 22:24:07.658729'),
	(469, 6, -6.914244, 107.697175, 14.68, '2026-06-01 22:24:55.371336'),
	(472, 3, -6.914249, 107.697170, 100.00, '2026-06-01 22:25:08.00126'),
	(1008, 2, -6.932334, 107.715383, 15.05, '2026-06-10 15:35:17.077284'),
	(1011, 21, -6.246797, 107.071909, 20.10, '2026-06-10 21:10:18.136369'),
	(1014, 21, -6.246800, 107.071926, 64.10, '2026-06-10 21:10:47.214771'),
	(1017, 21, -6.246803, 107.071908, 24.90, '2026-06-10 21:12:14.200778'),
	(1020, 21, -6.246800, 107.071907, 19.80, '2026-06-10 21:13:47.676641'),
	(1023, 21, -6.246793, 107.071910, 30.00, '2026-06-10 21:14:54.112153'),
	(1026, 21, -6.246796, 107.071909, 30.00, '2026-06-10 21:22:07.375546'),
	(1029, 3, -6.927293, 107.713331, 3.64, '2026-06-11 06:05:21.847317'),
	(1032, 3, -6.932054, 107.714992, 3.00, '2026-06-11 06:43:24.84836'),
	(1035, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:04:32.6202'),
	(1038, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:10:33.377581'),
	(1041, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:12:28.838153'),
	(1044, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:13:56.719096'),
	(1047, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:15:27.09479'),
	(1050, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:18:34.131698'),
	(563, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:27:35.674075'),
	(564, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:29:41.293328'),
	(565, 4, -6.931886, 107.713719, 100.00, '2026-06-02 11:30:40.402417'),
	(566, 4, -6.931820, 107.713744, 32.10, '2026-06-02 11:30:52.686972'),
	(567, 4, -6.931820, 107.713744, 100.00, '2026-06-02 11:32:47.128974'),
	(568, 4, -6.931820, 107.713744, 100.00, '2026-06-02 11:33:57.242142'),
	(569, 4, -6.931820, 107.713744, 100.00, '2026-06-02 11:34:57.156876'),
	(941, 2, -6.925548, 107.729538, 88.60, '2026-06-06 12:22:32.084415'),
	(945, 2, -6.925518, 107.729533, 6.90, '2026-06-06 12:24:25.977428'),
	(949, 2, -6.914347, 107.697109, 28.30, '2026-06-06 14:24:27.170064'),
	(953, 11, -6.940836, 107.714576, 15.09, '2026-06-06 14:45:58.226162'),
	(957, 11, -6.940842, 107.714569, 15.70, '2026-06-06 14:47:45.301837'),
	(961, 2, -6.914258, 107.697178, 17.84, '2026-06-06 15:07:05.845746'),
	(965, 3, -6.930735, 107.712931, 62.42, '2026-06-06 15:45:30.800877'),
	(577, 4, -6.930704, 107.718277, 100.00, '2026-06-02 13:20:45.980563'),
	(578, 3, -6.945905, 107.710276, 24.48, '2026-06-02 13:21:12.754642'),
	(579, 3, -6.930934, 107.718429, 100.00, '2026-06-02 13:21:34.125498'),
	(580, 3, -6.945896, 107.710274, 18.47, '2026-06-02 13:23:34.749672'),
	(581, 4, -6.930704, 107.718277, 100.00, '2026-06-02 13:23:55.724581'),
	(582, 4, -6.930704, 107.718277, 100.00, '2026-06-02 13:25:09.969883'),
	(583, 4, -6.930715, 107.718345, 61.02, '2026-06-02 13:25:51.531969'),
	(969, 2, -6.939095, 107.716805, 100.00, '2026-06-07 00:26:31.14064'),
	(585, 4, -6.930715, 107.718345, 100.00, '2026-06-02 13:26:21.55526'),
	(586, 4, -6.930715, 107.718345, 100.00, '2026-06-02 13:26:51.313832'),
	(587, 4, -6.930715, 107.718345, 100.00, '2026-06-02 13:28:32.461775'),
	(588, 3, -6.931074, 107.714404, 16.09, '2026-06-02 16:10:28.268027'),
	(589, 11, -6.930698, 107.731549, 14.79, '2026-06-02 17:43:50.571489'),
	(590, 11, -6.930700, 107.731544, 17.16, '2026-06-02 17:44:10.507463'),
	(591, 11, -6.930698, 107.731551, 20.10, '2026-06-02 17:44:45.074222'),
	(592, 11, -6.930697, 107.731545, 16.32, '2026-06-02 17:47:54.207077'),
	(593, 11, -6.930699, 107.731548, 16.34, '2026-06-02 17:51:10.251202'),
	(594, 11, -6.930710, 107.731534, 19.85, '2026-06-02 17:51:40.483003'),
	(595, 11, -6.930690, 107.731531, 39.60, '2026-06-02 17:52:45.731506'),
	(596, 11, -6.930698, 107.731541, 17.94, '2026-06-02 17:53:20.94979'),
	(597, 11, -6.930699, 107.731542, 15.73, '2026-06-02 17:55:16.481722'),
	(598, 11, -6.930684, 107.731539, 16.29, '2026-06-02 17:56:04.106663'),
	(599, 11, -6.930697, 107.731551, 20.10, '2026-06-02 17:57:42.224484'),
	(600, 11, -6.930681, 107.731528, 14.96, '2026-06-02 17:59:33.938092'),
	(601, 11, -6.930692, 107.731548, 20.00, '2026-06-02 18:01:33.943936'),
	(602, 11, -6.930705, 107.731575, 11.17, '2026-06-02 18:11:46.280975'),
	(603, 11, -6.930694, 107.731552, 14.76, '2026-06-02 18:12:17.538173'),
	(604, 11, -6.930696, 107.731558, 18.42, '2026-06-02 18:12:43.973792'),
	(605, 11, -6.930687, 107.731545, 14.26, '2026-06-02 18:14:30.554412'),
	(606, 11, -6.930697, 107.731554, 17.05, '2026-06-02 18:14:59.062145'),
	(973, 2, -6.913237, 107.659538, 12.79, '2026-06-07 13:37:50.154831'),
	(608, 11, -6.931489, 107.730605, 18.33, '2026-06-02 20:09:24.978703'),
	(609, 11, -6.931489, 107.730605, 18.33, '2026-06-02 20:10:04.281214'),
	(976, 2, -6.913120, 107.659392, 11.60, '2026-06-07 14:24:57.095793'),
	(979, 2, -6.913224, 107.659540, 14.94, '2026-06-07 14:29:19.530307'),
	(982, 3, -6.928831, 107.714617, 130.78, '2026-06-09 11:24:00.854782'),
	(985, 3, -6.930860, 107.718375, 82.11, '2026-06-09 13:14:51.067702'),
	(988, 2, -6.925428, 107.729679, 9.54, '2026-06-10 05:13:27.894465'),
	(991, 2, -6.925516, 107.729470, 82.50, '2026-06-10 05:19:27.209049'),
	(994, 2, -6.925447, 107.729474, 7.27, '2026-06-10 05:24:03.0221'),
	(997, 2, -6.925516, 107.729468, 92.90, '2026-06-10 05:26:09.065221'),
	(1000, 2, -6.925541, 107.729452, 77.60, '2026-06-10 12:36:57.766462'),
	(1003, 2, -6.925518, 107.729500, 77.60, '2026-06-10 12:41:35.388869'),
	(1006, 3, -6.930682, 107.718302, 100.00, '2026-06-10 13:23:38.339851'),
	(1009, 2, -6.931954, 107.714722, 111.00, '2026-06-10 20:56:58.330875'),
	(1012, 21, -6.246794, 107.071910, 20.00, '2026-06-10 21:10:29.465178'),
	(1015, 21, -6.246797, 107.071908, 22.50, '2026-06-10 21:11:51.988576'),
	(1018, 21, -6.246797, 107.071909, 23.60, '2026-06-10 21:12:19.609458'),
	(1021, 21, -6.246798, 107.071907, 21.60, '2026-06-10 21:13:52.992535'),
	(1024, 2, -6.931952, 107.714722, 128.00, '2026-06-10 21:20:50.930546'),
	(1027, 21, -6.246803, 107.071907, 22.50, '2026-06-10 21:22:14.095271'),
	(1030, 3, -6.927369, 107.713240, 3.40, '2026-06-11 06:05:32.041275'),
	(1033, 3, -6.931958, 107.714703, 5.72, '2026-06-11 06:43:50.010165'),
	(1036, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:06:32.686761'),
	(1039, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:11:11.923498'),
	(634, 3, -6.928957, 107.714729, 98.40, '2026-06-03 05:55:19.013838'),
	(637, 3, -6.928926, 107.714782, 116.10, '2026-06-03 05:57:33.287862'),
	(640, 3, -6.928797, 107.714451, 128.90, '2026-06-03 06:03:26.320954'),
	(1042, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:12:57.254019'),
	(1045, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:14:26.681906'),
	(1048, 3, -6.928856, 107.714667, 100.00, '2026-06-11 07:15:58.392575'),
	(1051, 4, -6.931819, 107.718074, 53.59, '2026-06-11 15:50:49.303515'),
	(1053, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:52:14.643799'),
	(1054, 3, -6.928970, 107.714663, 100.00, '2026-06-11 15:53:58.775667'),
	(1055, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:54:18.932558'),
	(1056, 3, -6.928970, 107.714663, 100.00, '2026-06-11 15:54:42.621619'),
	(1057, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:54:56.793871'),
	(1058, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:55:33.559907'),
	(1059, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:56:00.648145'),
	(1060, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:56:30.523767'),
	(1061, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:57:54.191036'),
	(1062, 4, -6.931819, 107.718074, 100.00, '2026-06-11 15:58:24.675338'),
	(1063, 2, -6.925502, 107.729683, 183.00, '2026-06-11 17:01:14.095981'),
	(1064, 2, -6.925502, 107.729683, 183.00, '2026-06-11 17:01:25.82365'),
	(1065, 2, -6.925502, 107.729683, 183.00, '2026-06-11 17:01:37.678989'),
	(1066, 2, -6.925489, 107.729720, 187.00, '2026-06-11 17:03:36.691597'),
	(1067, 2, -6.925473, 107.729767, 212.00, '2026-06-11 17:05:37.29413'),
	(1068, 2, -6.925502, 107.729683, 183.00, '2026-06-11 17:07:36.822335'),
	(736, 11, -6.940841, 107.714569, 15.25, '2026-06-03 14:23:04.258386'),
	(1069, 2, -6.925473, 107.729767, 212.00, '2026-06-11 17:15:37.221772'),
	(1070, 2, -6.925453, 107.729472, 9.80, '2026-06-11 17:58:14.385779'),
	(1071, 2, -6.925491, 107.729497, 34.40, '2026-06-11 17:59:58.639892'),
	(1072, 2, -6.925370, 107.729600, 8.30, '2026-06-11 18:00:36.658667'),
	(1073, 2, -6.925492, 107.729462, 11.10, '2026-06-11 18:02:32.683076'),
	(1074, 2, -6.925602, 107.729393, 11.60, '2026-06-11 18:02:48.741975'),
	(1075, 2, -6.925445, 107.729407, 9.50, '2026-06-11 18:03:13.375244'),
	(1076, 2, -6.925491, 107.729494, 36.90, '2026-06-11 18:05:12.845421'),
	(1077, 2, -6.925494, 107.729497, 39.60, '2026-06-11 18:07:36.994853'),
	(1078, 2, -6.925494, 107.729494, 48.90, '2026-06-11 18:09:34.148272'),
	(1079, 2, -6.925491, 107.729501, 52.40, '2026-06-11 18:11:30.610133'),
	(1080, 2, -6.925493, 107.729500, 32.10, '2026-06-11 18:13:28.52278'),
	(1081, 2, -6.925506, 107.729494, 16.97, '2026-06-11 18:35:35.791814'),
	(1082, 2, -6.925489, 107.729502, 30.00, '2026-06-11 18:35:50.939012'),
	(1083, 2, -6.925442, 107.729518, 10.30, '2026-06-11 18:38:41.85403'),
	(1084, 2, -6.925492, 107.729495, 34.40, '2026-06-11 18:39:52.985806'),
	(1085, 2, -6.925438, 107.729510, 8.60, '2026-06-11 18:40:55.700215'),
	(1086, 2, -6.925493, 107.729496, 32.10, '2026-06-11 18:42:38.083663'),
	(1087, 2, -6.925497, 107.729506, 19.78, '2026-06-11 18:43:32.790162'),
	(1088, 2, -6.925481, 107.729497, 39.60, '2026-06-11 18:47:08.521421'),
	(1089, 2, -6.925498, 107.729493, 42.50, '2026-06-11 18:47:32.904184'),
	(1090, 2, -6.925490, 107.729499, 20.40, '2026-06-11 19:44:27.839985'),
	(1091, 2, -6.925402, 107.729483, 83.30, '2026-06-11 19:44:51.984868'),
	(1092, 2, -6.925497, 107.729513, 36.90, '2026-06-11 20:24:53.858912'),
	(1093, 2, -6.925531, 107.729513, 9.10, '2026-06-11 20:25:40.007831'),
	(1094, 2, -6.925488, 107.729690, 183.00, '2026-06-11 23:03:25.09141'),
	(1095, 2, -6.925488, 107.729690, 183.00, '2026-06-11 23:04:10.962937'),
	(1096, 2, -6.925489, 107.729720, 187.00, '2026-06-11 23:09:59.037692'),
	(1097, 2, -6.925489, 107.729720, 187.00, '2026-06-11 23:11:08.59168'),
	(1098, 3, -6.929321, 107.712450, 5.12, '2026-06-12 06:28:54.558794'),
	(1099, 3, -6.929371, 107.712390, 4.71, '2026-06-12 06:29:02.39009'),
	(1100, 3, -6.929441, 107.712385, 3.83, '2026-06-12 06:29:13.335453'),
	(1101, 3, -6.929841, 107.712188, 3.10, '2026-06-12 06:31:07.573842'),
	(1102, 3, -6.932975, 107.710503, 3.00, '2026-06-12 06:45:07.56124'),
	(1103, 3, -6.930771, 107.711268, 48.33, '2026-06-12 07:06:29.595937'),
	(1104, 3, -6.930558, 107.711144, 30.11, '2026-06-12 07:06:40.876182'),
	(1105, 3, -6.930548, 107.711186, 28.89, '2026-06-12 07:07:03.962901'),
	(1106, 3, -6.930578, 107.711278, 33.87, '2026-06-12 07:07:38.353511'),
	(1107, 3, -6.930229, 107.712191, 21.16, '2026-06-12 07:10:18.479791'),
	(1108, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:03:33.838125'),
	(1109, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:04:01.432824'),
	(1110, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:04:48.418288'),
	(1111, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:05:18.014591'),
	(1112, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:06:08.143926'),
	(1113, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:06:36.649634'),
	(1114, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:07:59.393075'),
	(1115, 3, -6.928888, 107.714635, 100.00, '2026-06-12 13:08:50.685593'),
	(1116, 4, -6.931971, 107.713720, 100.00, '2026-06-12 19:53:01.054953'),
	(1117, 4, -6.931954, 107.724295, 100.00, '2026-06-12 20:43:25.305579'),
	(1118, 4, -6.931954, 107.724295, 100.00, '2026-06-12 20:43:54.991697'),
	(1119, 4, -6.931954, 107.724295, 100.00, '2026-06-12 20:44:56.119823'),
	(1120, 3, -6.941327, 107.715492, 100.00, '2026-06-12 20:46:47.054684'),
	(1121, 4, -6.931954, 107.724295, 100.00, '2026-06-12 20:46:55.597903'),
	(1122, 3, -6.941327, 107.715492, 100.00, '2026-06-12 20:47:30.639366'),
	(1123, 3, -6.941327, 107.715492, 100.00, '2026-06-12 20:47:59.899903'),
	(1124, 4, -6.931954, 107.724295, 100.00, '2026-06-12 20:48:55.570295'),
	(1125, 11, -6.938397, 107.716003, 20.12, '2026-06-13 05:29:27.226645'),
	(1126, 11, -6.938342, 107.715854, 3.95, '2026-06-13 05:29:46.128405'),
	(1127, 11, -6.938327, 107.715954, 5.27, '2026-06-13 05:30:01.056571'),
	(1128, 11, -6.938417, 107.715908, 20.28, '2026-06-13 05:30:17.206456'),
	(1129, 11, -6.938225, 107.716036, 64.10, '2026-06-13 05:32:42.502396'),
	(1130, 11, -6.938343, 107.715982, 15.99, '2026-06-13 05:33:09.678377'),
	(1131, 11, -6.938349, 107.715934, 87.60, '2026-06-13 05:34:16.767293'),
	(1132, 2, -6.925488, 107.729690, 183.00, '2026-06-13 13:32:47.503616'),
	(1133, 2, -6.925488, 107.729690, 183.00, '2026-06-13 13:33:24.154306'),
	(1134, 2, -6.925489, 107.729720, 187.00, '2026-06-13 21:01:09.006442'),
	(1135, 2, -6.925489, 107.729720, 187.00, '2026-06-13 21:01:50.926237'),
	(1136, 2, -6.925489, 107.729720, 187.00, '2026-06-13 21:02:51.281007'),
	(1137, 2, -6.925489, 107.729720, 187.00, '2026-06-13 21:04:15.517495'),
	(1138, 2, -6.925489, 107.729720, 187.00, '2026-06-13 21:04:48.544378'),
	(1139, 2, -6.945100, 107.620100, 20000.00, '2026-06-13 21:06:47.95988'),
	(1140, 2, -6.925500, 107.729660, 182.00, '2026-06-13 21:08:48.043396'),
	(1141, 2, -6.925171, 107.729389, 148.00, '2026-06-13 21:10:48.221062'),
	(1142, 2, -6.910665, 107.650790, 16.98, '2026-06-13 23:57:45.523549'),
	(1143, 2, -6.910648, 107.650766, 15.56, '2026-06-13 23:58:24.292264'),
	(1144, 2, -6.910634, 107.650760, 17.98, '2026-06-13 23:59:59.631131'),
	(1145, 2, -6.910634, 107.650760, 17.98, '2026-06-14 00:02:10.971769'),
	(1146, 2, -6.910676, 107.650806, 16.31, '2026-06-14 00:02:38.385536'),
	(1147, 2, -6.910676, 107.650806, 16.31, '2026-06-14 00:04:58.967152'),
	(1148, 2, -6.910674, 107.650735, 17.78, '2026-06-14 00:05:18.340796'),
	(1149, 2, -7.452701, 107.824582, 7.17, '2026-06-14 03:55:00.183056'),
	(1150, 2, -7.452698, 107.824585, 8.10, '2026-06-14 03:55:13.768585'),
	(1151, 2, -7.645040, 107.728535, 23.50, '2026-06-14 06:21:23.065948'),
	(1152, 2, -7.645043, 107.728560, 23.42, '2026-06-14 06:21:34.420178'),
	(1153, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:14:30.370523'),
	(1154, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:15:11.612175'),
	(1155, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:15:51.352892'),
	(1156, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:17:19.799347'),
	(1157, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:17:43.332916'),
	(1158, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:17:57.330301'),
	(1159, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:18:24.693642'),
	(1160, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:18:54.051082'),
	(1161, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:19:35.756508'),
	(1162, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:20:01.776933'),
	(1163, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:20:47.834997'),
	(1164, 3, -6.928889, 107.714629, 100.00, '2026-06-14 09:21:18.828119'),
	(1165, 2, -6.933357, 107.723215, 14.43, '2026-06-14 21:53:13.287743'),
	(1166, 2, -6.933372, 107.723223, 10.50, '2026-06-14 21:54:15.937992'),
	(1167, 2, -6.933370, 107.723237, 16.17, '2026-06-14 21:56:10.618081'),
	(1168, 2, -6.933371, 107.723241, 24.29, '2026-06-14 21:57:23.009534'),
	(1169, 2, -6.925498, 107.729495, 32.10, '2026-06-14 22:15:25.86272'),
	(1170, 2, -6.925513, 107.729476, 82.50, '2026-06-15 04:42:42.780855'),
	(1171, 2, -6.925798, 107.729505, 13.80, '2026-06-15 04:44:29.801869'),
	(1172, 2, -6.925513, 107.729476, 87.60, '2026-06-15 04:46:05.258902'),
	(1173, 2, -6.925511, 107.729479, 77.60, '2026-06-15 04:47:05.359415'),
	(1174, 2, -6.925509, 107.729478, 77.60, '2026-06-15 04:48:16.153939'),
	(1175, 2, -6.925508, 107.729477, 18.47, '2026-06-15 05:38:13.138423'),
	(1176, 2, -6.925502, 107.729502, 15.97, '2026-06-15 05:38:45.315405'),
	(1177, 2, -6.925518, 107.729475, 7.90, '2026-06-15 05:38:58.587119'),
	(1178, 2, -6.925497, 107.729508, 34.40, '2026-06-15 05:39:20.59911'),
	(1179, 2, -6.925509, 107.729473, 18.57, '2026-06-15 05:39:33.234906'),
	(1180, 2, -6.925499, 107.729490, 16.52, '2026-06-15 05:39:46.993127'),
	(1181, 2, -6.925496, 107.729484, 18.30, '2026-06-15 05:39:59.400261'),
	(1182, 2, -6.925238, 107.729472, 11.50, '2026-06-15 05:40:22.808923'),
	(1183, 2, -6.925437, 107.729208, 8.40, '2026-06-15 05:40:38.679848'),
	(1184, 2, -6.925400, 107.729415, 7.70, '2026-06-15 05:40:54.837097'),
	(1185, 2, -6.925417, 107.729442, 7.40, '2026-06-15 05:41:11.719211'),
	(1186, 2, -6.925508, 107.729479, 39.60, '2026-06-15 05:41:46.265777'),
	(1187, 2, -6.925499, 107.729495, 39.60, '2026-06-15 05:42:03.62469'),
	(1188, 2, -6.925417, 107.729373, 6.80, '2026-06-15 05:42:43.393562'),
	(1189, 2, -6.925493, 107.729494, 34.40, '2026-06-15 05:43:33.274266'),
	(1190, 2, -6.925513, 107.729481, 32.65, '2026-06-15 06:24:09.916848'),
	(1191, 4, -6.932050, 107.713680, 100.00, '2026-06-15 07:40:51.268896'),
	(1192, 4, -6.932050, 107.713680, 100.00, '2026-06-15 07:42:07.45279'),
	(1193, 4, -6.932050, 107.713680, 100.00, '2026-06-15 07:43:24.317224'),
	(1194, 4, -6.932050, 107.713680, 100.00, '2026-06-15 07:44:14.051337'),
	(1195, 4, -6.932050, 107.713680, 100.00, '2026-06-15 07:45:04.938738'),
	(1196, 4, -6.932078, 107.713653, 116.10, '2026-06-15 07:46:57.054817'),
	(1197, 4, -6.932078, 107.713653, 116.10, '2026-06-15 07:47:36.383507'),
	(1230, 2, -6.925489, 107.729720, 187.00, '2026-06-15 11:18:10.685174'),
	(1231, 2, -6.925489, 107.729720, 187.00, '2026-06-15 11:19:07.917754'),
	(1232, 2, -6.925494, 107.729495, 42.50, '2026-06-15 11:48:32.048377'),
	(1233, 2, -6.925503, 107.729490, 34.40, '2026-06-15 11:50:46.119316'),
	(1234, 2, -6.925565, 107.729365, 7.90, '2026-06-15 11:51:10.282466'),
	(1235, 2, -6.925557, 107.729473, 7.30, '2026-06-15 11:51:28.135919'),
	(1236, 2, -6.925450, 107.729505, 6.50, '2026-06-15 11:52:35.224213'),
	(1237, 2, -6.925508, 107.729481, 32.10, '2026-06-15 11:54:42.78028'),
	(1238, 2, -6.925506, 107.729482, 32.10, '2026-06-15 12:21:10.120968'),
	(1239, 2, -6.925610, 107.729378, 381.00, '2026-06-15 12:22:03.668643'),
	(1240, 2, -6.925500, 107.729660, 182.00, '2026-06-15 12:23:40.479225');
/*!40000 ALTER TABLE "device_location_history" ENABLE KEYS */;

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

-- Dumping data for table public.dm_messages: 25 rows
/*!40000 ALTER TABLE "dm_messages" DISABLE KEYS */;
INSERT INTO "dm_messages" ("id", "sender_id", "receiver_id", "pesan", "read_at", "created_at", "delivered_at") VALUES
	(5, 2, 15, 'Nif', NULL, '2026-05-24 09:43:56.986858', NULL),
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
	(12, 2, 3, '🏸', '2026-05-26 11:30:29.729809', '2026-05-24 14:40:41.349807', '2026-05-26 11:30:29.729809'),
	(19, 2, 20, 'Assalamualaikum', NULL, '2026-05-29 16:11:03.974136', NULL),
	(20, 2, 4, 'Tes', '2026-06-01 00:13:05.94832', '2026-06-01 00:12:11.892282', '2026-06-01 00:13:05.94832'),
	(21, 2, 4, 'Tes', '2026-06-01 00:13:05.94832', '2026-06-01 00:12:20.613937', '2026-06-01 00:13:05.94832'),
	(22, 2, 4, 'Dan', '2026-06-01 00:13:05.94832', '2026-06-01 00:12:38.249808', '2026-06-01 00:13:05.94832'),
	(23, 2, 4, 'Dan', '2026-06-01 00:13:05.94832', '2026-06-01 00:12:43.875096', '2026-06-01 00:13:05.94832'),
	(24, 4, 2, 'Tes', '2026-06-01 00:13:12.821161', '2026-06-01 00:13:10.966645', '2026-06-01 00:13:12.821161'),
	(25, 4, 2, 'Euy', '2026-06-01 00:13:51.677483', '2026-06-01 00:13:30.400303', '2026-06-01 00:13:50.132446'),
	(2, 2, 4, '💪', '2026-05-24 00:23:28.819379', '2026-05-24 00:22:18.066754', '2026-06-01 22:20:51.03983'),
	(1, 2, 4, 'Semangat Malam', '2026-05-24 00:23:28.819379', '2026-05-24 00:22:10.053792', '2026-06-01 22:20:51.03983'),
	(4, 2, 4, '⚽', '2026-05-24 09:44:44.72952', '2026-05-24 09:43:38.545424', '2026-06-01 22:20:51.03983'),
	(8, 2, 4, 'Oh iya dan, berapa lagi bayaran th?', '2026-05-24 13:55:26.780213', '2026-05-24 13:30:01.408808', '2026-06-01 22:20:51.03983'),
	(7, 2, 4, 'Lg dmn dan', '2026-05-24 13:55:26.780213', '2026-05-24 09:45:59.090019', '2026-06-01 22:20:51.03983'),
	(53, 4, 2, 'Cara nambah absen buat ekstern gimana kang?', '2026-06-02 20:20:45.594856', '2026-06-02 13:28:25.212208', '2026-06-02 20:20:43.876446'),
	(54, 2, 4, 'Ada di menu input absensi', '2026-06-11 15:57:56.546115', '2026-06-02 20:21:10.024151', '2026-06-11 15:57:56.546115');
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

-- Dumping structure for table public.donasi_rekening
CREATE TABLE IF NOT EXISTS "donasi_rekening" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''donasi_rekening_id_seq''::regclass)',
	"bank" VARCHAR(60) NOT NULL,
	"nomor" VARCHAR(60) NOT NULL,
	"atas_nama" VARCHAR(120) NOT NULL,
	"keterangan" VARCHAR(200) NULL DEFAULT NULL,
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"urutan" INTEGER NOT NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id")
);

-- Dumping data for table public.donasi_rekening: -1 rows
/*!40000 ALTER TABLE "donasi_rekening" DISABLE KEYS */;
INSERT INTO "donasi_rekening" ("id", "bank", "nomor", "atas_nama", "keterangan", "aktif", "urutan", "created_at") VALUES
	(1, 'BCA', '1234567890', 'Bendahara Kegiatan', NULL, 'true', 1, '2026-05-30 11:18:09.797229'),
	(2, 'Mandiri', '9876543210', 'Bendahara Kegiatan', NULL, 'true', 2, '2026-05-30 11:18:09.797229'),
	(3, 'DANA', '081234567890', 'Bendahara Kegiatan', NULL, 'true', 3, '2026-05-30 11:18:09.797229');
/*!40000 ALTER TABLE "donasi_rekening" ENABLE KEYS */;

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
	"jam_mulai" TIME NULL DEFAULT NULL,
	"jam_selesai" TIME NULL DEFAULT NULL,
	"lokasi" VARCHAR(255) NULL DEFAULT NULL,
	"batas_daftar" DATE NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "event_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION
);

-- Dumping data for table public.event: -1 rows
/*!40000 ALTER TABLE "event" DISABLE KEYS */;
INSERT INTO "event" ("id", "nama", "jenis", "tipe", "deskripsi", "tanggal_mulai", "tanggal_selesai", "hadiah", "status", "banner_url", "created_by", "created_at", "jam_mulai", "jam_selesai", "lokasi", "batas_daftar") VALUES
	(2, 'Nyate Bersama Idul Adha 1447 H', 'Nyate Bersama', 'sosial', 'Gaskeun... Daging sudah tersedia', '2026-06-06', '2026-06-06', 'Konsumsi Gratis', 'open', 'https://ik.imagekit.io/ahsansur/sportapp/event/event-2-1780472669_w_8uqs1F6.jpg', 2, '2026-05-29 14:58:53.412649', '19:00:00', '22:00:00', 'Flamboyan FC', '2026-06-06');
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

-- Dumping data for table public.event_peserta: 19 rows
/*!40000 ALTER TABLE "event_peserta" DISABLE KEYS */;
INSERT INTO "event_peserta" ("id", "event_id", "tim_id", "user_id", "score", "created_at", "status", "keterangan") VALUES
	(54, 2, NULL, 11, 0.00, '2026-06-06 14:47:02.742934', 'hadir', NULL),
	(19, 2, NULL, 3, 0.00, '2026-05-31 16:33:14.240644', 'absen', NULL),
	(52, 2, NULL, 2, 0.00, '2026-06-03 14:34:00.934817', NULL, NULL),
	(3, 2, NULL, 16, 0.00, '2026-05-29 14:58:53.455615', 'hadir', NULL),
	(4, 2, NULL, 13, 0.00, '2026-05-29 14:58:53.496709', 'hadir', NULL),
	(5, 2, NULL, 4, 0.00, '2026-05-29 14:58:53.536049', 'hadir', NULL),
	(6, 2, NULL, 8, 0.00, '2026-05-29 14:58:53.575339', 'absen', NULL),
	(7, 2, NULL, 6, 0.00, '2026-05-29 14:58:53.61471', 'hadir', NULL),
	(8, 2, NULL, 7, 0.00, '2026-05-29 14:58:53.654143', 'hadir', NULL),
	(9, 2, NULL, 20, 0.00, '2026-05-29 14:58:53.693313', 'absen', NULL),
	(10, 2, NULL, 14, 0.00, '2026-05-29 14:58:53.732247', 'hadir', NULL),
	(53, 2, NULL, 21, 0.00, '2026-06-06 09:52:27.704232', 'hadir', NULL),
	(11, 2, NULL, 2, 0.00, '2026-05-29 14:58:53.771509', 'hadir', NULL),
	(12, 2, NULL, 15, 0.00, '2026-05-29 14:58:53.810588', 'absen', NULL),
	(13, 2, NULL, 9, 0.00, '2026-05-29 14:58:53.849744', 'absen', NULL),
	(14, 2, NULL, 10, 0.00, '2026-05-29 14:58:53.888845', 'absen', NULL),
	(15, 2, NULL, 11, 0.00, '2026-05-29 14:58:53.929695', 'hadir', NULL),
	(16, 2, NULL, 3, 0.00, '2026-05-29 14:58:53.969345', 'hadir', NULL),
	(17, 2, NULL, 17, 0.00, '2026-05-29 14:58:54.008886', 'absen', NULL),
	(18, 2, NULL, 5, 0.00, '2026-05-29 14:58:54.047887', 'hadir', NULL);
/*!40000 ALTER TABLE "event_peserta" ENABLE KEYS */;

-- Dumping structure for table public.event_tamu
CREATE TABLE IF NOT EXISTS "event_tamu" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''event_tamu_id_seq''::regclass)',
	"event_id" INTEGER NOT NULL,
	"nama_tamu" VARCHAR(120) NOT NULL,
	"dibawa_oleh_id" INTEGER NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "idx_event_tamu_event" ("event_id"),
	CONSTRAINT "event_tamu_event_id_fkey" FOREIGN KEY ("event_id") REFERENCES "event" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "event_tamu_dibawa_oleh_id_fkey" FOREIGN KEY ("dibawa_oleh_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.event_tamu: -1 rows
/*!40000 ALTER TABLE "event_tamu" DISABLE KEYS */;
/*!40000 ALTER TABLE "event_tamu" ENABLE KEYS */;

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

-- Dumping structure for table public.flyover_renders
CREATE TABLE IF NOT EXISTS "flyover_renders" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''flyover_renders_id_seq''::regclass)',
	"user_id" BIGINT NOT NULL,
	"run_session_id" BIGINT NULL DEFAULT NULL,
	"judul" TEXT NOT NULL DEFAULT 'Flyover Route',
	"durasi_detik" INTEGER NOT NULL DEFAULT '20',
	"style_preset" TEXT NOT NULL DEFAULT 'satellite',
	"file_url" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "flyr_user_idx" ("user_id", "created_at")
);

-- Dumping data for table public.flyover_renders: -1 rows
/*!40000 ALTER TABLE "flyover_renders" DISABLE KEYS */;
/*!40000 ALTER TABLE "flyover_renders" ENABLE KEYS */;

-- Dumping structure for table public.gaya_hidup_log
CREATE TABLE IF NOT EXISTS "gaya_hidup_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''gaya_hidup_log_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"tanggal" DATE NOT NULL,
	"langkah" INTEGER NULL DEFAULT NULL,
	"tidur_menit" INTEGER NULL DEFAULT NULL,
	"hidrasi_ml" INTEGER NULL DEFAULT NULL,
	"stres_skor" INTEGER NULL DEFAULT NULL,
	"body_battery" INTEGER NULL DEFAULT NULL,
	"berat_kg" NUMERIC(5,2) NULL DEFAULT NULL,
	"mood" VARCHAR(30) NULL DEFAULT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"pola_makan" VARCHAR(20) NULL DEFAULT NULL,
	"porsi_makan" SMALLINT NULL DEFAULT NULL,
	"minum_air_gelas" SMALLINT NULL DEFAULT NULL,
	"pola_tidur" VARCHAR(20) NULL DEFAULT NULL,
	"kualitas_tidur" SMALLINT NULL DEFAULT NULL,
	"mood_skor" SMALLINT NULL DEFAULT NULL,
	"kecemasan" SMALLINT NULL DEFAULT NULL,
	"motivasi" SMALLINT NULL DEFAULT NULL,
	"fokus" SMALLINT NULL DEFAULT NULL,
	"catatan_psikologi" TEXT NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "uniq_gh_user_tgl" ("user_id", "tanggal"),
	INDEX "idx_gh_user_tgl" ("user_id", "tanggal"),
	INDEX "idx_gaya_hidup_user_tgl" ("user_id", "tanggal")
);

-- Dumping data for table public.gaya_hidup_log: -1 rows
/*!40000 ALTER TABLE "gaya_hidup_log" DISABLE KEYS */;
/*!40000 ALTER TABLE "gaya_hidup_log" ENABLE KEYS */;

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
	(10, 4, 2, 8, 'Okay', '2026-05-24 14:05:36.612789', NULL),
	(11, 16, 2, NULL, '💪 Tetap Semangat', '2026-05-29 17:07:20.637099', NULL),
	(12, 21, 4, NULL, '👋 Halloo', '2026-06-05 07:30:59.302977', NULL);
/*!40000 ALTER TABLE "guest_messages" ENABLE KEYS */;

-- Dumping structure for table public.index_blok
CREATE TABLE IF NOT EXISTS "index_blok" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''index_blok_id_seq''::regclass)',
	"judul" VARCHAR(120) NOT NULL,
	"konten" TEXT NOT NULL DEFAULT '',
	"posisi" VARCHAR(20) NOT NULL DEFAULT 'top',
	"urutan" INTEGER NOT NULL DEFAULT '0',
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "index_blok_pos_urut_idx" ("posisi", "urutan")
);

-- Dumping data for table public.index_blok: -1 rows
/*!40000 ALTER TABLE "index_blok" DISABLE KEYS */;
/*!40000 ALTER TABLE "index_blok" ENABLE KEYS */;

-- Dumping structure for table public.iptv_channels
CREATE TABLE IF NOT EXISTS "iptv_channels" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''iptv_channels_id_seq''::regclass)',
	"nama" VARCHAR(200) NOT NULL,
	"url" TEXT NOT NULL,
	"logo_url" TEXT NULL DEFAULT NULL,
	"group_name" VARCHAR(120) NULL DEFAULT NULL,
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"sort_order" INTEGER NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	UNIQUE INDEX "iptv_channels_url_key" ("url"),
	INDEX "idx_iptv_aktif" ("aktif"),
	INDEX "idx_iptv_group" ("group_name")
);

-- Dumping data for table public.iptv_channels: 62 rows
/*!40000 ALTER TABLE "iptv_channels" DISABLE KEYS */;
INSERT INTO "iptv_channels" ("id", "nama", "url", "logo_url", "group_name", "aktif", "sort_order", "created_at", "updated_at") VALUES
	(4, 'BTV 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h210/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/btv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.031491', '2026-06-11 23:08:31.031491'),
	(7, 'DAAI TV', 'https://op-group1-swiftservesd-1.dens.tv/s/s182/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/daaitv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.15696', '2026-06-11 23:08:31.15696'),
	(12, 'IDTV 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h209/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/idtv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.355555', '2026-06-11 23:08:31.355555'),
	(17, 'Kompas TV', 'https://op-group1-swiftservehd-1.dens.tv/h/h234/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kompastv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.555547', '2026-06-11 23:08:31.555547'),
	(18, 'Kompas TV 1', 'https://op-group1-swiftservehd-1.dens.tv/s/s104/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kompastv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.595563', '2026-06-11 23:08:31.595563'),
	(20, 'Magna Channel', 'https://edge.medcom.id/live-edge/smil:magna.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/magna.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.675167', '2026-06-11 23:08:31.675167'),
	(21, 'Magna Channel 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h24/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/magna.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.714812', '2026-06-11 23:08:31.714812'),
	(22, 'Metro TV', 'https://edge.medcom.id/live-edge/smil:metro.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/metrotv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.754693', '2026-06-11 23:08:31.754693'),
	(23, 'Metro TV 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h12/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/metrotv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.794517', '2026-06-11 23:08:31.794517'),
	(26, 'NET.', 'https://op-group1-swiftservehd-1.dens.tv/h/h223/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/net.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.91438', '2026-06-11 23:08:31.91438'),
	(27, 'NET. 1', 'https://op-group1-swiftservesd-1.dens.tv/h/h06/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/net.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.953924', '2026-06-11 23:08:31.953924'),
	(28, 'Nusantara TV', 'https://nusantaratv.siar.us/nusantaratv/live/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/nusantaratv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:31.993513', '2026-06-11 23:08:31.993513'),
	(29, 'Rajawali TV', 'https://op-group1-swiftservehd-1.dens.tv/h/h10/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rtv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.033117', '2026-06-11 23:08:32.033117'),
	(31, 'Rodja TV', 'https://rodjatv.com/rodjatv/live.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rodjatv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.11234', '2026-06-11 23:08:32.11234'),
	(32, 'Rodja TV 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h233/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rodjatv.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.151959', '2026-06-11 23:08:32.151959'),
	(2, 'ANTV 1', 'http://203.77.246.2:443/udp/239.1.1.104:5000', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/antv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:30.952073', '2026-06-11 23:08:30.952073'),
	(3, 'BTV', 'https://b1news.beritasatumedia.com/Beritasatu/B1News_manifest.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/btv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:30.991647', '2026-06-11 23:08:30.991647'),
	(5, 'CNBC Indonesia', 'https://live.cnbcindonesia.com/livecnbc/smil:cnbctv.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/cnbcindonesia.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.07137', '2026-06-11 23:08:31.07137'),
	(6, 'CNN Indonesia', 'https://live.cnnindonesia.com/livecnn/smil:cnntv.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/cnnindonesia.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.116838', '2026-06-11 23:08:31.116838'),
	(9, 'Garuda TV (480p) [Geo-blocked]', 'http://vod.linknetott.swiftcontent.com/Content/HLS/Live/Channel(ch45)/index.m3u8', '', '', 'false', 0, '2026-06-11 23:08:31.236416', '2026-06-11 23:08:31.236416'),
	(8, 'Garuda TV (720p)', 'https://etv-cdn.kdb.co.id/GarudaTV-Stream/index.m3u8', '', '', 'false', 0, '2026-06-11 23:08:31.19675', '2026-06-11 23:08:31.19675'),
	(10, 'GTV', 'https://d1abp075u76pbq.cloudfront.net/live/eds/GTV-HD/sa_dash_vmx/GTV-HD.mpd|Referer=https://www.visionplus.id/', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/gtv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.27614', '2026-06-11 23:08:31.27614'),
	(11, 'IDTV', 'https://b1world.beritasatumedia.com/Beritasatu/B1World_manifest.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/idtv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.315825', '2026-06-11 23:08:31.315825'),
	(13, 'Indonesiana.TV', 'https://dgwubfppws111.cloudfront.net/out/v1/667a86e35ddd496c886fa11598dc184d/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/indonesianatv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.395165', '2026-06-11 23:08:31.395165'),
	(14, 'Indonesiana.TV 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h292/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/indonesianatv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.43635', '2026-06-11 23:08:31.43635'),
	(15, 'Indosiar', 'https://203.77.246.2:443/udp/239.1.1.110:5000', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/indosiar.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.475942', '2026-06-11 23:08:31.475942'),
	(16, 'iNews', 'https://d1abp075u76pbq.cloudfront.net/live/eds/iNewsTV-HDD/sa_dash_vmx/iNewsTV-HDD.mpd|Referer=https://www.visionplus.id/', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/inews.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.515623', '2026-06-11 23:08:31.515623'),
	(19, 'Kompas TV 2', 'https://ythls-v2.onrender.com/channel/UC5BMIWZe9isJXLZZWPWvBlg.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kompastv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.635341', '2026-06-11 23:08:31.635341'),
	(24, 'Metro TV 2', 'https://op-group1-swiftservehd-1.dens.tv/h/h211/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/metrotv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.834537', '2026-06-11 23:08:31.834537'),
	(25, 'MNCTV', 'https://d1abp075u76pbq.cloudfront.net/live/eds/MNCTV-HD/sa_dash_vmx/MNCTV-HD.mpd|Referer=https://www.visionplus.id/', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/mnctv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:31.874386', '2026-06-11 23:08:31.874386'),
	(30, 'RCTI', 'https://d1abp075u76pbq.cloudfront.net/live/eds/RCTI-DD/sa_dash_vmx/RCTI-DD.mpd|Referer=https://www.visionplus.id/', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rcti.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.072768', '2026-06-11 23:08:32.072768'),
	(33, 'RRI Net', 'https://public-streaming.rri.co.id/memfs/b3169f10-7846-496c-a186-698ea5ddd310.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rrinet.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.191556', '2026-06-11 23:08:32.191556'),
	(35, 'SCTV', 'https://203.77.246.2:443/udp/239.1.1.108:5000', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/sctv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.270889', '2026-06-11 23:08:32.270889'),
	(34, 'SEA Today', 'https://liveaneviadev.mncnow.id/live/eds/SEA-Channel/sa_dash_vmx/SEA-Channel.mpd', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/seatoday.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.23122', '2026-06-11 23:08:32.23122'),
	(36, 'Trans7', 'https://video.detik.com/trans7/smil:trans7.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/trans7.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.310743', '2026-06-11 23:08:32.310743'),
	(39, 'tvOne 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h40/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvone.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.43078', '2026-06-11 23:08:32.43078'),
	(41, 'TVRI Nasional 1', 'https://op-group1-swiftservesd-1.dens.tv/s/s11/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvri.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.510456', '2026-06-11 23:08:32.510456'),
	(42, 'TVRI Sport', 'https://ott-balancer.tvri.go.id/live/eds/SportHD/hls/SportHD.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvrisport.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.550392', '2026-06-11 23:08:32.550392'),
	(44, 'TVRI World', 'https://ott-balancer.tvri.go.id/live/eds/TVRIWorld/hls/TVRIWorld.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvriworld.png?raw=true', 'Indonesia', 'true', 0, '2026-06-11 23:08:32.63019', '2026-06-11 23:08:32.63019'),
	(48, 'CinemaWorld', 'https://op-group1-swiftservehd-1.dens.tv/h/h202/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/cinemaworld.png?raw=true', 'Premium', 'true', 0, '2026-06-11 23:08:32.788693', '2026-06-11 23:08:32.788693'),
	(1, 'ANTV', 'https://op-group1-swiftservehd-1.dens.tv/s/s07/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/antv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:30.910453', '2026-06-11 23:08:30.910453'),
	(46, 'Celestial Classic Movies', 'https://op-group1-swiftservehd-1.dens.tv/h/h239/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/ccm.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.709426', '2026-06-11 23:08:32.709426'),
	(47, 'Celestial Movies', 'https://op-group1-swiftservehd-1.dens.tv/h/h212/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/celestialmovies.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.749161', '2026-06-11 23:08:32.749161'),
	(49, 'HITS', 'https://op-group1-swiftservehd-1.dens.tv/h/h205/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/hits.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.828747', '2026-06-11 23:08:32.828747'),
	(50, 'HITS Movies', 'https://op-group1-swiftservehd-1.dens.tv/h/h206/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/hitsmovies.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.868332', '2026-06-11 23:08:32.868332'),
	(51, 'KIX', 'https://op-group1-swiftservehd-1.dens.tv/h/h220/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kix.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.908014', '2026-06-11 23:08:32.908014'),
	(52, 'K-Plus', 'https://op-group1-swiftservehd-1.dens.tv/h/h219/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kplus.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.947671', '2026-06-11 23:08:32.947671'),
	(53, 'K-Plus 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h08/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/kplus.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:32.987272', '2026-06-11 23:08:32.987272'),
	(54, 'My Cinema', 'https://op-group1-swiftservehd-1.dens.tv/h/h192/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/mycinema.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.026966', '2026-06-11 23:08:33.026966'),
	(55, 'My Cinema Asia', 'https://op-group1-swiftservehd-1.dens.tv/h/h193/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/mycinemaasia.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.066741', '2026-06-11 23:08:33.066741'),
	(56, 'My Family', 'https://op-group1-swiftservehd-1.dens.tv/h/h194/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/myfamilychannel.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.10641', '2026-06-11 23:08:33.10641'),
	(57, 'My Kidz', 'https://op-group1-swiftservehd-1.dens.tv/h/h191/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/mykidz.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.146004', '2026-06-11 23:08:33.146004'),
	(58, 'Rock Action', 'https://op-group1-swiftservehd-1.dens.tv/h/h218/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rockaction.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.185675', '2026-06-11 23:08:33.185675'),
	(59, 'Rock Entertaiment', 'https://op-group1-swiftservehd-1.dens.tv/h/h213/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/rockentertainment.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.225831', '2026-06-11 23:08:33.225831'),
	(61, 'tvN Asia', 'https://op-group1-swiftservehd-1.dens.tv/h/h20/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvn.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.305277', '2026-06-11 23:08:33.305277'),
	(62, 'tvN Movies Asia', 'https://op-group1-swiftservehd-1.dens.tv/h/h214/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvnmovies.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.344976', '2026-06-11 23:08:33.344976'),
	(38, 'tvOne', 'https://op-group1-swiftservehd-1.dens.tv/h/h224/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvone.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.39116', '2026-06-11 23:08:32.39116'),
	(40, 'TVRI Nasional', 'https://ott-balancer.tvri.go.id/live/eds/Nasional/hls/Nasional.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvri.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.470533', '2026-06-11 23:08:32.470533'),
	(43, 'TVRI Sport 1', 'https://op-group1-swiftservehd-1.dens.tv/h/h238/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/tvrisport.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.590543', '2026-06-11 23:08:32.590543'),
	(45, 'VTV', 'https://flv.intechmedia.net/live/ch107.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/vtv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.669775', '2026-06-11 23:08:32.669775'),
	(60, 'Thrill', 'https://op-group1-swiftservehd-1.dens.tv/h/h240/index.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/thrill.png?raw=true', 'Premium', 'false', 0, '2026-06-11 23:08:33.265507', '2026-06-11 23:08:33.265507'),
	(37, 'Trans TV', 'https://video.detik.com/transtv/smil:transtv.smil/playlist.m3u8', 'https://github.com/riotryulianto/iptv-playlists/blob/main/icons/transtv.png?raw=true', 'Indonesia', 'false', 0, '2026-06-11 23:08:32.350567', '2026-06-11 23:08:32.350567');
/*!40000 ALTER TABLE "iptv_channels" ENABLE KEYS */;

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
INSERT INTO "islami_kajian" ("id", "user_id", "judul", "isi", "link_video", "created_at", "penulis", "tipe", "link_web", "pdf_path", "updated_at") VALUES
	(2, 2, 'Al Syura 42:52', 'Petunjuk dan al iman', '', '2026-06-08 23:33:23.465479', '', 'buku', '', NULL, NULL);
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
	(14, 20, '2026-05-29', 1, 0, 0, 0, 0, 0, 0, 10),
	(15, 14, '2026-05-29', 1, 0, 0, 0, 0, 0, 0, 10),
	(16, 2, '2026-05-30', 1, 0, 0, 0, 0, 0, 0, 10),
	(17, 3, '2026-05-31', 1, 0, 0, 0, 0, 0, 0, 10),
	(50, 16, '2026-06-01', 1, 0, 0, 0, 0, 0, 0, 10),
	(51, 6, '2026-06-01', 1, 0, 0, 0, 0, 0, 0, 10),
	(52, 2, '2026-06-13', 1, 0, 0, 0, 0, 0, 0, 10);
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
	(9, '2026-06-05', 'June', 'W1', 'Jogging', 'Parkiran Taman Sumringah', 2, '<p>Siapakah ''Dia'' menurut kalian?</p>', '<p>Pada telat kunjungannya, namun tetap semangat kompak</p>', '2026-06-02 20:30:34.263032', 5, 150, NULL, NULL, '06:30:00', '09:00:00'),
	(8, '2026-06-02', 'June', 'W1', 'Badminton', 'GOR Gaza', 2, '<p>Shalat bkn hanya aspek ritual, tp 24 jam</p>', '<p>Kompak.. Gaskeun, Makan bersama mie ayam guys</p>', '2026-06-01 13:57:16.375314', 14, 120, NULL, NULL, '16:00:00', '18:00:00'),
	(6, '2026-05-23', 'May', 'W4', 'Badminton', 'GOR Gaza', 3, '<p><br></p>', '<p>Sepi.. dikitan kita main ini</p>', '2026-05-21 15:45:32.456543', 14, 120, NULL, NULL, '16:00:00', '18:00:00'),
	(10, '2026-06-13', 'June', 'W2', 'Jogging', 'Flamboyan Jogging', 14, '<p>Ada Dia nya gk disetiap aktifitas kita</p>', '<p>Kesiangan, kemudian mulai jam 6, 2.4km, sisanya makan dan ngobrol konten</p>', '2026-06-10 21:42:46.741554', 29, 120, NULL, NULL, '06:00:00', '09:00:00');
/*!40000 ALTER TABLE "jadwal" ENABLE KEYS */;

-- Dumping structure for table public.jajanan
CREATE TABLE IF NOT EXISTS "jajanan" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''jajanan_id_seq''::regclass)',
	"nama" VARCHAR(160) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"harga" INTEGER NOT NULL DEFAULT '0',
	"stok" INTEGER NOT NULL DEFAULT '0',
	"foto_url" TEXT NULL DEFAULT NULL,
	"kategori" VARCHAR(60) NULL DEFAULT NULL,
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"foto_file_id" VARCHAR(120) NULL DEFAULT NULL,
	"lat" NUMERIC(10,6) NULL DEFAULT NULL,
	"lng" NUMERIC(10,6) NULL DEFAULT NULL,
	"jam_buka" TIME NULL DEFAULT NULL,
	"jam_tutup" TIME NULL DEFAULT NULL,
	"toko_id" INTEGER NULL DEFAULT NULL,
	"hari_buka" VARCHAR(20) NULL DEFAULT '0,1,2,3,4,5,6',
	PRIMARY KEY ("id"),
	INDEX "jajanan_toko_idx" ("toko_id"),
	INDEX "jajanan_aktif_idx" ("aktif"),
	CONSTRAINT "jajanan_toko_id_fkey" FOREIGN KEY ("toko_id") REFERENCES "toko" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.jajanan: 7 rows
/*!40000 ALTER TABLE "jajanan" DISABLE KEYS */;
INSERT INTO "jajanan" ("id", "nama", "deskripsi", "harga", "stok", "foto_url", "kategori", "aktif", "created_at", "foto_file_id", "lat", "lng", "jam_buka", "jam_tutup", "toko_id", "hari_buka") VALUES
	(4, 'Nasi Telor Dadar', NULL, 12000, 10, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Nasi_Dadar_Abin-1780186504-3a1835_9soVpQNTe.jpg', 'Makanan', 'true', '2026-05-30 17:54:08.943507', NULL, -6.934213, 107.716876, '07:00:00', '21:00:00', 5, '0,1,2,3,4,5,6'),
	(5, 'Kopi Susu Caramel Ice', NULL, 20000, 10, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Kopi_Tekun_Cibiru___Kopi_Susu_Caramel_Ice-1780186465-fc78c6_hoErhR2bZ.jpg', 'Minuman', 'true', '2026-05-30 17:56:18.352873', NULL, -6.932213, 107.715419, '07:00:00', '21:00:00', 4, '0,1,2,3,4,5,6'),
	(2, 'Mie Bakso Urat', NULL, 15000, 9, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Bakso_Neng_Hajjah___Mie_Bakso_Urat-1780186577-a980f0_QV9JBgCqB.jpg', 'Makanan', 'true', '2026-05-30 17:43:16.278053', NULL, -6.940613, 107.714490, '07:00:00', '21:00:00', 6, '0,1,2,3,4,5,6'),
	(3, 'Tempe Mendoan', NULL, 12000, 10, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Ayam_penyet_esti___Tempe_Mendoan-1780186536-dcdd7b_q7hTvQ2mX.jpg', 'Snack', 'true', '2026-05-30 17:52:58.119717', NULL, -6.933805, 107.716582, '07:00:00', '21:00:00', 1, '0,1,2,3,4,5,6'),
	(7, 'Blooming Jasmine Milk Tea', NULL, 20000, 10, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Tianlala_Cibiru___Blooming_Jasmine_Milk_Tea-1780186336-fe1b3e_kFvedWrpy.jpg', 'Minuman', 'true', '2026-05-30 18:00:28.030693', NULL, -6.933354, 107.720471, '07:00:00', '21:00:00', 2, '0,1,2,3,4,5,6'),
	(6, 'Nasi Goreng Hongkong', NULL, 29000, 10, 'https://ik.imagekit.io/ahsansur/sportapp/jajanan/2026/05/Waroeng_Cafe_Yayang___Nasi_Goreng_Hongkong-1780186385-5a253e_IbrFiWzPMh.jpg', 'Makanan', 'true', '2026-05-30 17:58:44.024626', NULL, -6.933812, 107.716533, '07:00:00', '21:00:00', 3, '0,1,2,3,4,5,6'),
	(9, 'Nasi Telor Dadar', NULL, 12000, 10, NULL, 'Makanan', 'true', '2026-06-02 12:47:28.88952', NULL, NULL, NULL, '07:00:00', '21:00:00', 7, '0,1,2,3,4,5,6');
/*!40000 ALTER TABLE "jajanan" ENABLE KEYS */;

-- Dumping structure for table public.jajanan_pesanan
CREATE TABLE IF NOT EXISTS "jajanan_pesanan" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''jajanan_pesanan_id_seq''::regclass)',
	"kode" VARCHAR(20) NOT NULL,
	"nama_pemesan" VARCHAR(120) NOT NULL,
	"no_wa" VARCHAR(25) NOT NULL,
	"alamat" TEXT NOT NULL,
	"catatan" TEXT NULL DEFAULT NULL,
	"subtotal" BIGINT NOT NULL DEFAULT '0',
	"ongkir" BIGINT NOT NULL DEFAULT '0',
	"total" BIGINT NOT NULL DEFAULT '0',
	"metode" VARCHAR(20) NULL DEFAULT 'cod',
	"status" VARCHAR(20) NOT NULL DEFAULT 'baru',
	"kurir_user_id" INTEGER NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"pickup_lat" NUMERIC(10,6) NULL DEFAULT NULL,
	"pickup_lng" NUMERIC(10,6) NULL DEFAULT NULL,
	"payment_status" VARCHAR(20) NULL DEFAULT 'pending',
	"midtrans_order_id" VARCHAR(40) NULL DEFAULT NULL,
	"snap_token" VARCHAR(120) NULL DEFAULT NULL,
	"snap_redirect" TEXT NULL DEFAULT NULL,
	"stok_dipotong" BOOLEAN NOT NULL DEFAULT 'false',
	"driver_lat" NUMERIC(10,6) NULL DEFAULT NULL,
	"driver_lng" NUMERIC(10,6) NULL DEFAULT NULL,
	"driver_loc_updated_at" TIMESTAMPTZ NULL DEFAULT NULL,
	"rating" SMALLINT NULL DEFAULT NULL,
	"rating_komentar" TEXT NULL DEFAULT NULL,
	"rating_at" TIMESTAMPTZ NULL DEFAULT NULL,
	"email_pemesan" VARCHAR(160) NULL DEFAULT NULL,
	"biaya_aplikasi" BIGINT NOT NULL DEFAULT '0',
	"biaya_admin" BIGINT NOT NULL DEFAULT '0',
	"invoice_sent_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "jajanan_pesanan_kode_key" ("kode"),
	INDEX "jjn_pesanan_payment_idx" ("payment_status"),
	INDEX "jjn_pesanan_midtrans_idx" ("midtrans_order_id"),
	INDEX "idx_jjn_pesanan_driver_upd" ("driver_loc_updated_at"),
	CONSTRAINT "jajanan_pesanan_kurir_user_id_fkey" FOREIGN KEY ("kurir_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jjn_rating_range_chk" CHECK (((rating IS NULL) OR ((rating >= 1) AND (rating <= 5))))
);

-- Dumping data for table public.jajanan_pesanan: 4 rows
/*!40000 ALTER TABLE "jajanan_pesanan" DISABLE KEYS */;
INSERT INTO "jajanan_pesanan" ("id", "kode", "nama_pemesan", "no_wa", "alamat", "catatan", "subtotal", "ongkir", "total", "metode", "status", "kurir_user_id", "created_at", "updated_at", "pickup_lat", "pickup_lng", "payment_status", "midtrans_order_id", "snap_token", "snap_redirect", "stok_dipotong", "driver_lat", "driver_lng", "driver_loc_updated_at", "rating", "rating_komentar", "rating_at", "email_pemesan", "biaya_aplikasi", "biaya_admin", "invoice_sent_at") VALUES
	(2, 'JJN-260530-3974', 'Andin', '081386369207', 'Tes', 'Gerbang Biru', 15000, 5000, 20000, 'cod', 'selesai', 2, '2026-05-30 17:45:28.285732', '2026-05-30 18:28:35.688819', -6.925610, 107.729378, 'pending', NULL, NULL, NULL, 'false', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL),
	(38, 'JJN-260601-8E2D', 'Firdam', '6281386369207', 'Ngetes', 'Gerbang hijau', 30000, 5638, 43211, 'midtrans', 'pending_payment', NULL, '2026-06-01 13:13:28.980403', '2026-06-01 13:13:28.980403', -6.925368, 107.729468, 'pending', 'JJN-260601-8E2D', 'ba0c3cad-46b4-4194-ae20-bae0a288f6b8', 'https://app.sandbox.midtrans.com/snap/v4/redirection/ba0c3cad-46b4-4194-ae20-bae0a288f6b8', 'false', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL),
	(1, 'JJN-260530-3300', 'Anggi', '081386369207', 'Biru', 'Gerbang biru', 40000, 5000, 45000, 'cod', 'diantar', 4, '2026-05-30 12:24:40.776433', '2026-06-01 14:35:33.005843', NULL, NULL, 'pending', NULL, NULL, NULL, 'false', -6.925610, 107.729378, '2026-06-01 14:15:36.01085+07', NULL, NULL, NULL, NULL, 0, 0, NULL),
	(40, 'JJN-260602-877D', 'Firdam', '6281386369207', 'Cibiru', 'Gerbang', 10000, 5637, 20854, 'midtrans', 'pending_payment', NULL, '2026-06-02 05:34:05.70711', '2026-06-02 05:34:05.70711', -6.925379, 107.729466, 'pending', 'JJN-260602-877D', '99e4c775-9f1b-4206-92f1-aea28de015d3', 'https://app.midtrans.com/snap/v4/redirection/99e4c775-9f1b-4206-92f1-aea28de015d3', 'false', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL);
/*!40000 ALTER TABLE "jajanan_pesanan" ENABLE KEYS */;

-- Dumping structure for table public.jajanan_pesanan_item
CREATE TABLE IF NOT EXISTS "jajanan_pesanan_item" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''jajanan_pesanan_item_id_seq''::regclass)',
	"pesanan_id" INTEGER NOT NULL,
	"jajanan_id" INTEGER NULL DEFAULT NULL,
	"nama" VARCHAR(160) NOT NULL,
	"harga" INTEGER NOT NULL DEFAULT '0',
	"qty" INTEGER NOT NULL DEFAULT '1',
	PRIMARY KEY ("id"),
	CONSTRAINT "jajanan_pesanan_item_jajanan_id_fkey" FOREIGN KEY ("jajanan_id") REFERENCES "jajanan" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jajanan_pesanan_item_pesanan_id_fkey" FOREIGN KEY ("pesanan_id") REFERENCES "jajanan_pesanan" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.jajanan_pesanan_item: -1 rows
/*!40000 ALTER TABLE "jajanan_pesanan_item" DISABLE KEYS */;
INSERT INTO "jajanan_pesanan_item" ("id", "pesanan_id", "jajanan_id", "nama", "harga", "qty") VALUES
	(1, 1, NULL, '🍕 Pizza', 20000, 2),
	(2, 2, 2, 'Bakso Neng Hajjah - Mie Bakso Urat', 15000, 1),
	(38, 38, 2, 'Mie Bakso Urat', 15000, 2),
	(41, 40, NULL, 'Testes', 10000, 1);
/*!40000 ALTER TABLE "jajanan_pesanan_item" ENABLE KEYS */;

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
	(8, 'Hiking', 'Ngalam bagian healing', '2026-05-22 16:19:49.34839'),
	(9, 'Camping', 'Bersama dalam malam', '2026-05-22 16:20:06.583601'),
	(10, 'Gerak Jalan', 'Menjaga intensitas kaki', '2026-05-22 16:20:27.018665'),
	(5, 'Renang', 'Renang gaya yang bagian dari edukasi', '2026-05-21 00:40:55.617378'),
	(11, 'Biliard', 'Kuy, maen bola kecil', '2026-05-23 04:33:57.391667'),
	(12, 'Ping Pong', 'Bola Bolaan Kecil', '2026-05-23 06:40:37.374439'),
	(13, 'Olahraga Pribadi', 'Rumahan', '2026-06-03 05:36:56.762834');
/*!40000 ALTER TABLE "jenis_olahraga" ENABLE KEYS */;

-- Dumping structure for table public.kalori_log
CREATE TABLE IF NOT EXISTS "kalori_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''kalori_log_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"jenis" VARCHAR(40) NOT NULL,
	"intensitas" VARCHAR(20) NOT NULL,
	"berat_kg" NUMERIC(5,1) NOT NULL,
	"menit" INTEGER NOT NULL,
	"met" NUMERIC(4,2) NOT NULL,
	"kalori" NUMERIC(7,2) NOT NULL,
	"dibuat_pada" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "kalori_log_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.kalori_log: -1 rows
/*!40000 ALTER TABLE "kalori_log" DISABLE KEYS */;
/*!40000 ALTER TABLE "kalori_log" ENABLE KEYS */;

-- Dumping structure for table public.kalori_makanan_log
CREATE TABLE IF NOT EXISTS "kalori_makanan_log" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''kalori_makanan_log_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"tanggal" DATE NOT NULL DEFAULT 'CURRENT_DATE',
	"waktu" TIME NOT NULL DEFAULT 'CURRENT_TIME',
	"nama_makanan" VARCHAR(200) NOT NULL,
	"kalori" INTEGER NOT NULL DEFAULT '0',
	"foto_url" TEXT NULL DEFAULT NULL,
	"ai_estimasi" BOOLEAN NOT NULL DEFAULT 'false',
	"catatan" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "idx_kalori_mkn_user_tgl" ("user_id", "tanggal")
);

-- Dumping data for table public.kalori_makanan_log: -1 rows
/*!40000 ALTER TABLE "kalori_makanan_log" DISABLE KEYS */;
INSERT INTO "kalori_makanan_log" ("id", "user_id", "tanggal", "waktu", "nama_makanan", "kalori", "foto_url", "ai_estimasi", "catatan", "created_at") VALUES
	(1, 2, '2026-06-13', '07:40:00', 'Nasi Rames Wicipi', 550, NULL, 'false', 'Nasi Rames, Nasi, Telor Dadar, Tempe Oreg, dan Sayur Tahu 1 + kerupuk kecil 3', '2026-06-13 07:42:38.556169');
/*!40000 ALTER TABLE "kalori_makanan_log" ENABLE KEYS */;

-- Dumping structure for table public.kalori_target
CREATE TABLE IF NOT EXISTS "kalori_target" (
	"user_id" INTEGER NOT NULL,
	"target_harian" INTEGER NOT NULL DEFAULT '2000',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id")
);

-- Dumping data for table public.kalori_target: -1 rows
/*!40000 ALTER TABLE "kalori_target" DISABLE KEYS */;
INSERT INTO "kalori_target" ("user_id", "target_harian", "updated_at") VALUES
	(2, 1500, '2026-06-11 23:40:42.808845');
/*!40000 ALTER TABLE "kalori_target" ENABLE KEYS */;

-- Dumping structure for table public.kebijakan_privasi
CREATE TABLE IF NOT EXISTS "kebijakan_privasi" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''kebijakan_privasi_id_seq''::regclass)',
	"versi" VARCHAR(20) NOT NULL DEFAULT '1.0',
	"judul" VARCHAR(160) NOT NULL DEFAULT 'Kebijakan Privasi',
	"konten" TEXT NOT NULL DEFAULT '',
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"updated_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id")
);

-- Dumping data for table public.kebijakan_privasi: -1 rows
/*!40000 ALTER TABLE "kebijakan_privasi" DISABLE KEYS */;
INSERT INTO "kebijakan_privasi" ("id", "versi", "judul", "konten", "aktif", "created_at", "updated_at") VALUES
	(1, '1.0', 'Kebijakan Privasi (UU PDP No. 27 Tahun 2022)', '<h3>Pendahuluan</h3><p>HapFam SportApp menghormati privasi Anda dan mematuhi UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
<h3>1. Data yang Kami Kumpulkan</h3><ul><li>Data identitas: nama, email, jenis kelamin, nomor WhatsApp</li><li>Data lokasi (saat memesan jajanan/booking lapangan)</li><li>Data aktivitas olahraga, foto profil, postingan</li></ul>
<h3>2. Dasar Pemrosesan</h3><p>Persetujuan Anda saat mendaftar, pelaksanaan kontrak (pemesanan), dan kepentingan sah.</p>
<h3>3. Hak Subjek Data</h3><ul><li>Hak mendapatkan informasi</li><li>Hak akses, koreksi, dan penghapusan</li><li>Hak menarik persetujuan</li><li>Hak menolak pemrosesan otomatis</li></ul>
<h3>4. Keamanan</h3><p>Kami menerapkan enkripsi password (bcrypt), HTTPS, dan kontrol akses berbasis peran.</p>
<h3>5. Pengiriman ke Pihak Ketiga</h3><p>Hanya untuk pemrosesan pembayaran (Midtrans) dan penyimpanan media (ImageKit) sesuai standar industri.</p>
<h3>6. Kontak DPO</h3><p>Hubungi: admin@hapfam.local</p>', 'true', '2026-06-02 07:20:18.842615', '2026-06-02 07:20:18.842615');
/*!40000 ALTER TABLE "kebijakan_privasi" ENABLE KEYS */;

-- Dumping structure for table public.live_tracking_contacts
CREATE TABLE IF NOT EXISTS "live_tracking_contacts" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''live_tracking_contacts_id_seq''::regclass)',
	"user_id" BIGINT NOT NULL,
	"nama" TEXT NOT NULL,
	"nomor_wa" TEXT NULL DEFAULT NULL,
	"email" TEXT NULL DEFAULT NULL,
	"relasi" TEXT NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "ltc_user_idx" ("user_id")
);

-- Dumping data for table public.live_tracking_contacts: -1 rows
/*!40000 ALTER TABLE "live_tracking_contacts" DISABLE KEYS */;
/*!40000 ALTER TABLE "live_tracking_contacts" ENABLE KEYS */;

-- Dumping structure for table public.live_tracking_points
CREATE TABLE IF NOT EXISTS "live_tracking_points" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''live_tracking_points_id_seq''::regclass)',
	"session_id" BIGINT NOT NULL,
	"lat" DOUBLE PRECISION NOT NULL,
	"lng" DOUBLE PRECISION NOT NULL,
	"accuracy_m" DOUBLE PRECISION NULL DEFAULT NULL,
	"speed_mps" DOUBLE PRECISION NULL DEFAULT NULL,
	"heading_deg" DOUBLE PRECISION NULL DEFAULT NULL,
	"ts" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "ltp_session_idx" ("session_id", "id"),
	CONSTRAINT "live_tracking_points_session_id_fkey" FOREIGN KEY ("session_id") REFERENCES "live_tracking_sessions" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.live_tracking_points: -1 rows
/*!40000 ALTER TABLE "live_tracking_points" DISABLE KEYS */;
INSERT INTO "live_tracking_points" ("id", "session_id", "lat", "lng", "accuracy_m", "speed_mps", "heading_deg", "ts") VALUES
	(1, 1, -6.9254995900617, 107.72965966931, 182, NULL, NULL, '2026-06-15 12:22:28.118955'),
	(2, 1, -6.9254995900617, 107.72965966931, 182, NULL, NULL, '2026-06-15 12:22:38.760569'),
	(3, 1, -6.9254995900617, 107.72965966931, 182, NULL, NULL, '2026-06-15 12:22:46.523412'),
	(4, 1, -6.9254995900617, 107.72965966931, 182, NULL, NULL, '2026-06-15 12:23:00.985059'),
	(5, 1, -6.9254995900617, 107.72965966931, 182, NULL, NULL, '2026-06-15 12:23:27.848797');
/*!40000 ALTER TABLE "live_tracking_points" ENABLE KEYS */;

-- Dumping structure for table public.live_tracking_sessions
CREATE TABLE IF NOT EXISTS "live_tracking_sessions" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''live_tracking_sessions_id_seq''::regclass)',
	"user_id" BIGINT NOT NULL,
	"token" VARCHAR(48) NOT NULL,
	"judul" TEXT NOT NULL DEFAULT 'Live Tracking',
	"pesan" TEXT NULL DEFAULT NULL,
	"olahraga" TEXT NOT NULL DEFAULT 'lari',
	"started_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"ended_at" TIMESTAMP NULL DEFAULT NULL,
	"expires_at" TIMESTAMP NOT NULL DEFAULT '(now() + ''12:00:00''::interval)',
	"is_active" BOOLEAN NOT NULL DEFAULT 'true',
	"last_lat" DOUBLE PRECISION NULL DEFAULT NULL,
	"last_lng" DOUBLE PRECISION NULL DEFAULT NULL,
	"last_seen_at" TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "live_tracking_sessions_token_key" ("token"),
	INDEX "lts_user_idx" ("user_id", "started_at"),
	INDEX "lts_token_idx" ("token"),
	INDEX "lts_active_idx" ("is_active", "expires_at")
);

-- Dumping data for table public.live_tracking_sessions: -1 rows
/*!40000 ALTER TABLE "live_tracking_sessions" DISABLE KEYS */;
INSERT INTO "live_tracking_sessions" ("id", "user_id", "token", "judul", "pesan", "olahraga", "started_at", "ended_at", "expires_at", "is_active", "last_lat", "last_lng", "last_seen_at") VALUES
	(1, 2, '13X36YYkNJnx_VveTzxixoxx', 'Lari sore', NULL, 'lari', '2026-06-15 12:22:22.847447', NULL, '2026-06-15 18:22:22.847447', 'true', -6.9254995900617, 107.72965966931, '2026-06-15 12:23:27.9099');
/*!40000 ALTER TABLE "live_tracking_sessions" ENABLE KEYS */;

-- Dumping structure for table public.login_attempts
CREATE TABLE IF NOT EXISTS "login_attempts" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''login_attempts_id_seq''::regclass)',
	"email" VARCHAR(150) NULL DEFAULT NULL,
	"ip" VARCHAR(64) NULL DEFAULT NULL,
	"success" SMALLINT NULL DEFAULT '0',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id")
);

-- Dumping data for table public.login_attempts: 368 rows
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
	(227, 'fajar@sport.local', '::1', 1, '2026-05-29 13:02:23.295608'),
	(228, 'firdam@sport.local', '::1', 1, '2026-05-29 13:53:13.096611'),
	(229, 'dendra@sport.local', '::1', 1, '2026-05-29 13:58:31.086987'),
	(230, 'firdam@sport.local', '::1', 1, '2026-05-29 13:59:34.691233'),
	(231, 'firdam@sport.local', '::1', 1, '2026-05-29 14:21:28.382151'),
	(232, 'firdam@sport.local', '::1', 1, '2026-05-29 14:55:17.585663'),
	(233, 'dani@sport.local', '::1', 1, '2026-05-29 15:05:59.872701'),
	(234, 'fajar@sport.local', '::1', 1, '2026-05-29 15:55:41.274426'),
	(235, 'firdam@sport.local', '::1', 1, '2026-05-29 16:02:07.874481'),
	(236, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-29 16:05:43.674392'),
	(237, 'firdam@sport.local', '::1', 1, '2026-05-29 17:04:23.736548'),
	(238, 'adithsetiawan62@gmail.com', '::1', 1, '2026-05-29 17:08:11.536964'),
	(239, 'farhan@sport.local', '::1', 1, '2026-05-29 17:24:32.739622'),
	(240, 'firdam@sport.local', '::1', 1, '2026-05-29 20:28:45.605916'),
	(241, 'firdam@sport.local', '::1', 1, '2026-05-29 20:31:58.306581'),
	(242, 'firdam@sport.local', '::1', 1, '2026-05-29 21:11:08.51261'),
	(243, 'firdam@sport.local', '::1', 1, '2026-05-29 21:36:34.035689'),
	(244, 'firdam@sport.local', '::1', 1, '2026-05-29 21:58:15.920093'),
	(245, 'firdam@sport.local', '::1', 1, '2026-05-29 22:14:55.120676'),
	(246, 'firdam@sport.local', '::1', 1, '2026-05-29 22:41:27.803853'),
	(247, 'firdam@sport.local', '::1', 1, '2026-05-29 22:57:39.470241'),
	(248, 'firdam@sport.local', '::1', 1, '2026-05-29 23:09:33.339499'),
	(249, 'farhan@sport.local', '::1', 1, '2026-05-30 07:10:30.138981'),
	(250, 'firdam@sport.local', '::1', 1, '2026-05-30 08:07:07.448765'),
	(251, 'firdam@sport.local', '::1', 1, '2026-05-30 08:08:03.733944'),
	(252, 'firdam@sport.local', '::1', 1, '2026-05-30 08:09:22.239651'),
	(253, 'firdam@sport.local', '::1', 1, '2026-05-30 08:44:39.825668'),
	(254, 'firdam@sport.local', '::1', 1, '2026-05-30 08:45:49.219626'),
	(255, 'firdam@sport.local', '::1', 1, '2026-05-30 09:23:30.091893'),
	(256, 'firdam@sport.local', '::1', 1, '2026-05-30 09:52:59.506263'),
	(257, 'hanif@sport.local', '::1', 1, '2026-05-30 09:55:46.297174'),
	(258, 'firdam@sport.local', '::1', 1, '2026-05-30 11:19:24.327497'),
	(259, 'firdam@sport.local', '::1', 0, '2026-05-30 11:22:12.429735'),
	(260, 'firdam@sport.local', '::1', 1, '2026-05-30 11:22:20.427708'),
	(261, 'firdam@sport.local', '::1', 0, '2026-05-30 11:40:18.638941'),
	(262, 'firdam@sport.local', '::1', 0, '2026-05-30 11:40:27.926208'),
	(263, 'firdam@sport.local', '::1', 1, '2026-05-30 11:40:36.729913'),
	(264, 'firdam@sport.local', '::1', 1, '2026-05-30 12:26:35.428276'),
	(265, 'firdam@sport.local', '::1', 1, '2026-05-30 13:18:34.999448'),
	(266, 'firdam@sport.local', '::1', 1, '2026-05-30 14:58:03.483097'),
	(267, 'rifat@sport.local', '::1', 1, '2026-05-30 16:20:59.291477'),
	(268, 'rifat@sport.local', '::1', 1, '2026-05-30 16:25:40.491661'),
	(269, 'firdam@sport.local', '::1', 1, '2026-05-30 16:32:29.591067'),
	(270, 'firdam@sport.local', '::1', 1, '2026-05-30 16:45:59.986954'),
	(271, 'firdam@sport.local', '::1', 1, '2026-05-30 16:51:44.074789'),
	(272, 'firdam@sport.local', '::1', 1, '2026-05-30 17:39:53.473253'),
	(273, 'firdam@sport.local', '::1', 1, '2026-05-30 17:50:08.074344'),
	(274, 'firdam@sport.local', '::1', 1, '2026-05-30 18:03:41.373338'),
	(275, 'firdam@sport.local', '::1', 1, '2026-05-30 18:16:16.201811'),
	(276, 'firdam@sport.local', '::1', 1, '2026-05-30 21:30:33.93701'),
	(277, 'firdam@sport.local', '::1', 1, '2026-05-30 21:53:40.024054'),
	(278, 'firdam@sport.local', '::1', 1, '2026-05-30 22:52:09.57996'),
	(279, 'firdam@sport.local', '::1', 1, '2026-05-30 22:52:49.465588'),
	(280, 'firdam@sport.local', '::1', 0, '2026-05-31 06:15:20.669998'),
	(281, 'firdam@sport.local', '::1', 1, '2026-05-31 06:15:38.273792'),
	(282, 'firdam@sport.local', '::1', 1, '2026-05-31 06:31:26.958175'),
	(283, 'firdam@sport.local', '::1', 1, '2026-05-31 06:39:32.882247'),
	(284, 'firdam@sport.local', '::1', 1, '2026-05-31 07:11:36.813499'),
	(285, 'firdam@sport.local', '::1', 1, '2026-05-31 11:48:27.824279'),
	(286, 'rifat@sport.local', '::1', 1, '2026-05-31 16:06:02.889833'),
	(287, 'rifat@sport.local', '::1', 1, '2026-05-31 16:09:19.687038'),
	(288, 'firdam@sport.local', '::1', 1, '2026-05-31 19:27:10.512728'),
	(289, 'firdam@sport.local', '::1', 1, '2026-05-31 23:46:41.163239'),
	(290, 'rifat@sport.local', '::1', 1, '2026-05-31 23:50:41.278639'),
	(291, 'dani@sport.local', '::1', 1, '2026-05-31 23:50:46.363918'),
	(292, 'firdam@sport.local', '::1', 1, '2026-06-01 06:11:00.014366'),
	(293, 'farhan@sport.local', '::1', 1, '2026-06-01 09:12:01.863449'),
	(326, 'firdam@sport.local', '::1', 1, '2026-06-01 10:44:01.033806'),
	(327, 'firdam@sport.local', '::1', 1, '2026-06-01 12:31:10.010099'),
	(328, 'dani@sport.local', '::1', 1, '2026-06-01 12:37:47.918355'),
	(329, 'firdam@sport.local', '::1', 1, '2026-06-01 13:00:04.397033'),
	(330, 'dani@sport.local', '::1', 1, '2026-06-01 13:14:50.998276'),
	(331, 'firdam@sport.local', '::1', 1, '2026-06-01 13:56:25.041246'),
	(332, 'firdam@sport.local', '::1', 1, '2026-06-01 14:03:47.926665'),
	(333, 'dani@sport.local', '::1', 0, '2026-06-01 14:08:40.625924'),
	(334, 'dani@sport.local', '::1', 1, '2026-06-01 14:08:52.739123'),
	(335, 'firdam@sport.local', '::1', 1, '2026-06-01 14:32:47.087979'),
	(336, 'firdam@sport.local', '::1', 1, '2026-06-01 15:00:14.549108'),
	(337, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-01 15:05:56.857392'),
	(338, 'firdam@sport.local', '::1', 1, '2026-06-01 15:31:33.453482'),
	(339, 'firdam@sport.local', '::1', 1, '2026-06-01 15:48:48.690433'),
	(340, 'dani@sport.local', '::1', 1, '2026-06-01 16:32:25.12652'),
	(341, 'firdam@sport.local', '::1', 1, '2026-06-01 16:53:39.427242'),
	(342, 'rifat@sport.local', '::1', 1, '2026-06-01 19:13:46.607896'),
	(343, 'firdam@sport.local', '::1', 1, '2026-06-01 19:14:07.010733'),
	(344, 'rifat@sport.local', '::1', 1, '2026-06-01 19:15:57.078245'),
	(345, 'firdam@sport.local', '::1', 0, '2026-06-01 19:16:40.079613'),
	(346, 'firdam@sport.local', '::1', 1, '2026-06-01 19:16:50.078418'),
	(347, 'firdam@sport.local', '::1', 1, '2026-06-01 19:32:59.975439'),
	(348, 'firdam@sport.local', '::1', 1, '2026-06-01 19:55:24.01756'),
	(349, 'firdam@sport.local', '::1', 1, '2026-06-01 20:41:36.519655'),
	(350, 'firdam@sport.local', '::1', 1, '2026-06-01 20:42:39.420161'),
	(351, 'firdam@sport.local', '::1', 1, '2026-06-01 21:27:41.03711'),
	(352, 'firdam@sport.local', '::1', 1, '2026-06-01 22:05:50.64621'),
	(353, 'dendra@sport.local', '::1', 1, '2026-06-01 22:11:10.646938'),
	(354, 'firdam@sport.local', '::1', 1, '2026-06-01 22:14:35.248438'),
	(355, 'dani@sport.local', '::1', 1, '2026-06-01 22:17:42.850003'),
	(356, 'rifat@sport.local', '::1', 1, '2026-06-01 22:17:51.450672'),
	(357, 'firdam@sport.local', '::1', 1, '2026-06-01 22:28:07.06768'),
	(358, 'firdam@sport.local', '::1', 1, '2026-06-01 22:47:54.771537'),
	(359, 'firdam@sport.local', '::1', 1, '2026-06-02 00:03:54.851303'),
	(360, 'firdam@sport.local', '::1', 1, '2026-06-02 00:26:13.246726'),
	(361, 'firdam@sport.local', '::1', 1, '2026-06-02 05:30:48.355304'),
	(362, 'firdam@sport.local', '::1', 1, '2026-06-02 07:30:47.37703'),
	(363, 'firdam@sport.local', '::1', 1, '2026-06-02 07:33:55.467284'),
	(364, 'farhan@sport.local', '::1', 1, '2026-06-02 08:08:09.37426'),
	(365, 'firdam@sport.local', '::1', 1, '2026-06-02 08:34:27.731107'),
	(366, 'firdam@sport.local', '::1', 1, '2026-06-02 10:03:43.860296'),
	(367, 'dani@sport.local', '::1', 1, '2026-06-02 11:22:50.672741'),
	(368, 'firdam@sport.local', '::1', 1, '2026-06-02 12:27:50.84233'),
	(369, 'dendra@sport.local', '::1', 1, '2026-06-02 13:16:21.606401'),
	(370, 'dani@sport.local', '::1', 1, '2026-06-02 13:20:32.034977'),
	(371, 'rifat@sport.local', '::1', 1, '2026-06-02 13:20:56.713246'),
	(372, 'rifat@sport.local', '::1', 1, '2026-06-02 13:21:17.925249'),
	(373, 'firdam@sport.local', '::1', 1, '2026-06-02 13:25:58.107728'),
	(374, 'rifat@sport.local', '::1', 1, '2026-06-02 16:10:11.944114'),
	(375, 'rian@sport.local', '::1', 1, '2026-06-02 17:43:25.591088'),
	(376, 'rian@sport.local', '::1', 1, '2026-06-02 17:55:00.290807'),
	(377, 'rian@sport.local', '::1', 1, '2026-06-02 17:57:22.591216'),
	(378, 'rian@sport.local', '::1', 1, '2026-06-02 18:11:30.289524'),
	(379, 'firdam@sport.local', '::1', 1, '2026-06-02 20:03:21.214415'),
	(380, 'rian@sport.local', '::1', 1, '2026-06-02 20:09:08.811674'),
	(381, 'firdam@sport.local', '::1', 0, '2026-06-02 22:09:36.65991'),
	(382, 'firdam@sport.local', '::1', 1, '2026-06-02 22:09:49.648998'),
	(383, 'firdam@sport.local', '::1', 1, '2026-06-03 05:35:36.045188'),
	(384, 'rifat@sport.local', '::1', 1, '2026-06-03 05:55:02.45179'),
	(385, 'firdam@sport.local', '::1', 1, '2026-06-03 06:34:59.156733'),
	(386, 'firdam@sport.local', '::1', 1, '2026-06-03 08:44:54.354731'),
	(387, 'firdam@sport.local', '::1', 1, '2026-06-03 09:06:26.818364'),
	(388, 'firdam@sport.local', '::1', 1, '2026-06-03 09:08:06.920454'),
	(389, 'firdam@sport.local', '::1', 1, '2026-06-03 10:24:16.289324'),
	(390, 'firdam@sport.local', '::1', 1, '2026-06-03 10:44:20.354664'),
	(391, 'firdam@sport.local', '::1', 1, '2026-06-03 10:58:48.080541'),
	(392, 'firdam@sport.local', '::1', 1, '2026-06-03 11:21:09.328531'),
	(393, 'firdam@sport.local', '::1', 1, '2026-06-03 12:48:18.643155'),
	(394, 'firdam@sport.local', '::1', 1, '2026-06-03 12:55:46.775053'),
	(395, 'rifat@sport.local', '::1', 1, '2026-06-03 13:37:02.139757'),
	(396, 'rian@sport.local', '::1', 1, '2026-06-03 14:22:50.117221'),
	(397, 'firdam@sport.local', '::1', 1, '2026-06-03 14:29:39.216458'),
	(398, 'rifat@sport.local', '::1', 1, '2026-06-03 17:40:06.031505'),
	(399, 'firdam@sport.local', '::1', 1, '2026-06-04 11:02:10.174945'),
	(400, 'rifat@sport.local', '::1', 1, '2026-06-04 15:32:10.875892'),
	(401, 'dani@sport.local', '::1', 1, '2026-06-04 19:11:52.372798'),
	(402, 'adithsetiawan62@gmail.com', '::1', 0, '2026-06-05 05:35:53.769934'),
	(403, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-05 05:36:21.67985'),
	(404, 'farhan@sport.local', '::1', 1, '2026-06-05 07:28:42.179172'),
	(405, 'rian@sport.local', '::1', 1, '2026-06-05 07:56:37.294537'),
	(406, 'firdam@sport.local', '::1', 1, '2026-06-06 09:42:26.603209'),
	(407, 'fawaid@sport.local', '::1', 1, '2026-06-06 09:52:10.887817'),
	(408, 'firdam@sport.local', '::1', 1, '2026-06-06 09:53:39.588282'),
	(409, 'firdam@sport.local', '::1', 1, '2026-06-06 10:11:17.488874'),
	(410, 'firdam@sport.local', '::1', 1, '2026-06-06 10:49:03.387263'),
	(411, 'firdam@sport.local', '127.0.0.1', 1, '2026-06-06 11:04:31.234443'),
	(412, 'firdam@sport.local', '::1', 1, '2026-06-06 11:23:17.743555'),
	(413, 'firdam@sport.local', '::1', 1, '2026-06-06 11:51:59.547398'),
	(414, 'firdam@sport.local', '::1', 1, '2026-06-06 12:10:33.448319'),
	(415, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-06 13:02:23.441669'),
	(416, 'rifat@sport.local', '::1', 1, '2026-06-06 13:21:27.948649'),
	(417, 'rian@sport.local', '::1', 1, '2026-06-06 14:44:45.453667'),
	(418, 'farhan@sport.local', '::1', 1, '2026-06-07 00:53:15.747086'),
	(419, 'farhan@sport.local', '::1', 1, '2026-06-07 07:51:35.252556'),
	(420, 'firdam@sport.local', '::1', 1, '2026-06-07 14:17:38.473898'),
	(421, 'firdam@sport.local', '::1', 1, '2026-06-07 14:19:26.771157'),
	(422, 'firdam@sport.local', '::1', 1, '2026-06-07 14:24:34.712293'),
	(423, 'firdam@sport.local', '::1', 1, '2026-06-07 15:14:51.478802'),
	(424, 'farhan@sport.local', '::1', 1, '2026-06-07 22:25:03.073917'),
	(425, 'aziz@sport.local', '::1', 1, '2026-06-08 14:21:08.706674'),
	(426, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-09 09:29:49.084351'),
	(427, 'rifat@sport.local', '::1', 1, '2026-06-09 11:23:04.678453'),
	(428, 'firdam@sport.local', '::1', 1, '2026-06-10 05:11:14.675448'),
	(429, 'faiz@sport.local', '::1', 1, '2026-06-10 13:29:37.677378'),
	(430, 'firdam@sport.local', '::1', 1, '2026-06-10 20:56:34.775485'),
	(431, 'fawaid@sport.local', '::1', 1, '2026-06-10 21:08:34.475448'),
	(432, 'dani@sport.local', '::1', 1, '2026-06-11 15:50:30.076569'),
	(433, 'firdam@sport.local', '::1', 1, '2026-06-11 17:26:10.109861'),
	(434, 'firdam@sport.local', '::1', 1, '2026-06-11 17:39:27.417074'),
	(435, 'firdam@sport.local', '::1', 1, '2026-06-11 17:59:42.188244'),
	(436, 'firdam@sport.local', '::1', 1, '2026-06-11 18:34:29.562854'),
	(437, 'firdam@sport.local', '::1', 1, '2026-06-11 19:44:10.798698'),
	(438, 'firdam@sport.local', '::1', 1, '2026-06-11 23:00:24.687116'),
	(439, 'firdam@sport.local', '::1', 1, '2026-06-11 23:03:08.183983'),
	(440, 'firdam@sport.local', '::1', 1, '2026-06-11 23:38:24.547807'),
	(441, 'farhan@sport.local', '::1', 1, '2026-06-12 05:16:01.346344'),
	(442, 'rifat@sport.local', '::1', 1, '2026-06-12 06:28:33.831178'),
	(443, 'firdam@sport.local', '::1', 1, '2026-06-12 07:17:39.128447'),
	(444, 'firdam@sport.local', '::1', 1, '2026-06-12 15:46:53.026051'),
	(445, 'firdam@sport.local', '::1', 1, '2026-06-12 16:22:42.764106'),
	(446, 'adithsetiawan62@gmail.com', '::1', 0, '2026-06-12 16:37:42.781002'),
	(447, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-12 16:37:59.666862'),
	(448, 'dani@sport.local', '::1', 1, '2026-06-12 19:52:41.46707'),
	(449, 'rifat@sport.local', '::1', 1, '2026-06-12 20:46:31.065758'),
	(450, 'firdam@sport.local', '::1', 1, '2026-06-13 04:26:15.035736'),
	(451, 'rian@sport.local', '::1', 1, '2026-06-13 05:29:11.63445'),
	(452, 'firdam@sport.local', '::1', 1, '2026-06-13 13:32:30.63993'),
	(453, 'firdam@sport.local', '::1', 1, '2026-06-13 14:41:23.708523'),
	(454, 'firdam@sport.local', '::1', 1, '2026-06-13 14:41:47.588459'),
	(455, 'rifat@sport.local', '::1', 1, '2026-06-13 14:45:10.481327'),
	(456, 'rifat@sport.local', '::1', 1, '2026-06-13 14:45:30.487395'),
	(457, 'rifat@sport.local', '::1', 1, '2026-06-13 14:46:00.58091'),
	(458, 'firdam@sport.local', '::1', 1, '2026-06-13 14:58:18.555044'),
	(459, 'firdam@sport.local', '::1', 1, '2026-06-13 14:59:02.660784'),
	(460, 'firdam@sport.local', '::1', 1, '2026-06-13 15:00:30.554749'),
	(461, 'firdam@sport.local', '::1', 1, '2026-06-13 15:30:50.443264'),
	(462, 'firdam@sport.local', '::1', 1, '2026-06-13 15:31:10.226253'),
	(463, 'rifat@sport.local', '::1', 1, '2026-06-13 16:08:08.621027'),
	(464, 'rifat@sport.local', '::1', 1, '2026-06-13 16:08:34.933414'),
	(465, 'rifat@sport.local', '::1', 1, '2026-06-13 16:09:04.029372'),
	(466, 'rifat@sport.local', '::1', 1, '2026-06-13 16:09:28.125685'),
	(467, 'firdam@sport.local', '::1', 1, '2026-06-13 17:57:49.774858'),
	(468, 'firdam@sport.local', '::1', 1, '2026-06-13 18:45:29.854953'),
	(469, 'firdam@sport.local', '::1', 0, '2026-06-13 19:24:01.219366'),
	(470, 'firdam@sport.local', '::1', 1, '2026-06-13 19:24:16.398993'),
	(471, 'faiz@sport.local', '::1', 1, '2026-06-13 20:09:29.804477'),
	(472, 'firdam@sport.local', '::1', 1, '2026-06-13 20:55:12.70395'),
	(473, 'firdam@sport.local', '::1', 1, '2026-06-13 21:00:52.177554'),
	(474, 'adithsetiawan62@gmail.com', '::1', 1, '2026-06-14 09:12:58.676539'),
	(475, 'rifat@sport.local', '::1', 1, '2026-06-14 09:14:14.477216'),
	(476, 'dani@sport.local', '::1', 1, '2026-06-15 07:40:35.761342'),
	(509, 'firdam@sport.local', '::1', 1, '2026-06-15 12:31:08.54824');
/*!40000 ALTER TABLE "login_attempts" ENABLE KEYS */;

-- Dumping structure for table public.login_logs
CREATE TABLE IF NOT EXISTS "login_logs" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''login_logs_id_seq''::regclass)',
	"user_id" INTEGER NOT NULL,
	"ip" VARCHAR(64) NULL DEFAULT NULL,
	"user_agent" VARCHAR(255) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "idx_login_logs_user" ("user_id", "created_at")
);

-- Dumping data for table public.login_logs: -1 rows
/*!40000 ALTER TABLE "login_logs" DISABLE KEYS */;
INSERT INTO "login_logs" ("id", "user_id", "ip", "user_agent", "created_at") VALUES
	(1, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 14:41:23.77839'),
	(2, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 14:41:47.631848'),
	(3, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 14:45:10.52069'),
	(4, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 14:45:30.528078'),
	(5, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 14:46:00.62521'),
	(6, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 14:58:18.595039'),
	(7, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 14:59:02.706178'),
	(8, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '2026-06-13 15:00:30.595259'),
	(9, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 15:30:50.487872'),
	(10, 2, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-13 15:31:10.265919'),
	(11, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 16:08:08.660811'),
	(12, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 16:08:34.974108'),
	(13, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 16:09:04.069246'),
	(14, 3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-06-13 16:09:28.166187');
/*!40000 ALTER TABLE "login_logs" ENABLE KEYS */;

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

-- Dumping structure for table public.nav_menu
CREATE TABLE IF NOT EXISTS "nav_menu" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''nav_menu_id_seq''::regclass)',
	"label" VARCHAR(80) NOT NULL,
	"url" VARCHAR(255) NOT NULL DEFAULT '#',
	"icon" VARCHAR(60) NULL DEFAULT NULL,
	"parent_id" INTEGER NULL DEFAULT NULL,
	"urutan" INTEGER NOT NULL DEFAULT '0',
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"target" VARCHAR(10) NULL DEFAULT '_self',
	"posisi" VARCHAR(20) NOT NULL DEFAULT 'drawer',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "nav_menu_pos_urut_idx" ("posisi", "urutan"),
	CONSTRAINT "nav_menu_parent_id_fkey" FOREIGN KEY ("parent_id") REFERENCES "nav_menu" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.nav_menu: -1 rows
/*!40000 ALTER TABLE "nav_menu" DISABLE KEYS */;
/*!40000 ALTER TABLE "nav_menu" ENABLE KEYS */;

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
	"dibuat_pada" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "notif_user_idx" ("user_id", "dibaca", "created_at"),
	CONSTRAINT "notifications_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.notifications: 175 rows
/*!40000 ALTER TABLE "notifications" DISABLE KEYS */;
INSERT INTO "notifications" ("id", "user_id", "jenis", "judul", "isi", "url", "dibaca", "created_at", "dibuat_pada") VALUES
	(4, 13, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.805361', '2026-06-01 21:23:57.044901'),
	(6, 8, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.885423', '2026-06-01 21:23:57.044901'),
	(7, 6, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.92564', '2026-06-01 21:23:57.044901'),
	(8, 7, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:56.965584', '2026-06-01 21:23:57.044901'),
	(9, 14, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.005545', '2026-06-01 21:23:57.044901'),
	(11, 15, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.085645', '2026-06-01 21:23:57.044901'),
	(12, 10, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.125638', '2026-06-01 21:23:57.044901'),
	(13, 9, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.165505', '2026-06-01 21:23:57.044901'),
	(14, 11, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.205328', '2026-06-01 21:23:57.044901'),
	(15, 5, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 0, '2026-05-22 00:27:57.245296', '2026-06-01 21:23:57.044901'),
	(18, 8, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 03:22:47.970868', '2026-06-01 21:23:57.044901'),
	(19, 14, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 0, '2026-05-22 09:29:51.433106', '2026-06-01 21:23:57.044901'),
	(29, 2, 'dm', '💬 Pesan baru dari Dani', 'tes tes', '/dm.php?u=4', 1, '2026-05-24 16:47:46.618028', '2026-06-01 21:23:57.044901'),
	(28, 2, 'dm', '💬 Pesan baru dari Dani', 'kang', '/dm.php?u=4', 1, '2026-05-24 16:45:56.151978', '2026-06-01 21:23:57.044901'),
	(25, 2, 'titip_pesan_reply', '↩️ Dani membalas pesanmu', 'Siapp sudah masuk, makasih kang', '/user.php?id=4#titip-pesan', 1, '2026-05-24 13:55:11.808257', '2026-06-01 21:23:57.044901'),
	(22, 2, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 1, '2026-05-23 16:27:31.593629', '2026-06-01 21:23:57.044901'),
	(17, 2, 'booking', 'Booking dibuat', 'Lapangan #3, 2026-05-23 16:00-18:00 (DP belum dibayar)', '/tempat.php', 1, '2026-05-22 00:45:14.401911', '2026-06-01 21:23:57.044901'),
	(16, 2, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-22 00:37:28.326276', '2026-06-01 21:23:57.044901'),
	(1, 2, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:56.680877', '2026-06-01 21:23:57.044901'),
	(32, 17, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.206067', '2026-06-01 21:23:57.044901'),
	(33, 16, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.245251', '2026-06-01 21:23:57.044901'),
	(34, 8, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.284508', '2026-06-01 21:23:57.044901'),
	(35, 9, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.326161', '2026-06-01 21:23:57.044901'),
	(36, 14, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.365582', '2026-06-01 21:23:57.044901'),
	(38, 10, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.443708', '2026-06-01 21:23:57.044901'),
	(40, 13, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.52263', '2026-06-01 21:23:57.044901'),
	(41, 15, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.56162', '2026-06-01 21:23:57.044901'),
	(43, 6, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.640076', '2026-06-01 21:23:57.044901'),
	(44, 7, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.679285', '2026-06-01 21:23:57.044901'),
	(45, 11, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.718433', '2026-06-01 21:23:57.044901'),
	(46, 5, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 0, '2026-05-29 14:58:54.757476', '2026-06-01 21:23:57.044901'),
	(39, 20, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 1, '2026-05-29 14:58:54.48276', '2026-06-01 21:23:57.044901'),
	(47, 20, 'dm', '💬 Pesan baru dari Firdam', 'Assalamualaikum', '/dm.php?u=2', 0, '2026-05-29 16:11:04.015005', '2026-06-01 21:23:57.044901'),
	(48, 16, 'titip_pesan', '💌 Titip pesan baru dari Firdam', '💪 Tetap Semangat', '/user.php?id=16#titip-pesan', 0, '2026-05-29 17:07:20.677133', '2026-06-01 21:23:57.044901'),
	(42, 2, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 1, '2026-05-29 14:58:54.600808', '2026-06-01 21:23:57.044901'),
	(54, 2, 'dm', '💬 Pesan baru dari Dani', 'Tes', '/dm.php?u=4', 1, '2026-06-01 00:13:11.006352', '2026-06-01 21:23:57.044901'),
	(55, 2, 'dm', '💬 Pesan baru dari Dani', 'Euy', '/dm.php?u=4', 1, '2026-06-01 00:13:30.441056', '2026-06-01 21:23:57.044901'),
	(20, 4, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-22 10:34:05.881776', '2026-06-01 21:23:57.044901'),
	(2, 4, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:56.72524', '2026-06-01 21:23:57.044901'),
	(49, 3, 'event', 'Pendaftaran event berhasil', 'Anda terdaftar di event #2', '/event.php?id=2', 1, '2026-05-31 16:33:14.282903', '2026-06-01 21:23:57.044901'),
	(37, 3, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 1, '2026-05-29 14:58:54.404635', '2026-06-01 21:23:57.044901'),
	(27, 3, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 1, '2026-05-24 15:37:52.588805', '2026-06-01 21:23:57.044901'),
	(21, 3, 'badge', '🏅 Badge baru: All Rounder', 'Hadir di 3 jenis olahraga berbeda', '/profile.php', 1, '2026-05-23 09:18:05.260227', '2026-06-01 21:23:57.044901'),
	(10, 3, 'event', '🏆 Event baru: Lomba Badminton', 'Daftar sekarang di menu Event.', '/event.php?id=1', 1, '2026-05-22 00:27:57.0456', '2026-06-01 21:23:57.044901'),
	(23, 4, 'titip_pesan', '💌 Titip pesan baru dari Firdam', 'Sudah dikirim sisa dp modul 1 ya dan', '/user.php?id=4#titip-pesan', 1, '2026-05-24 13:44:59.686455', '2026-06-01 21:23:57.044901'),
	(24, 4, 'badge', '🏅 Badge baru: Rajin 4 Minggu', 'Hadir 4 minggu berturut-turut', '/profile.php', 1, '2026-05-24 13:54:15.697312', '2026-06-01 21:23:57.044901'),
	(26, 4, 'titip_pesan', '💌 Titip pesan baru dari Firdam', 'Okay', '/user.php?id=4#titip-pesan', 1, '2026-05-24 14:05:36.654331', '2026-06-01 21:23:57.044901'),
	(50, 4, 'dm', '💬 Pesan baru dari Firdam', 'Tes', '/dm.php?u=2', 1, '2026-06-01 00:12:11.93225', '2026-06-01 21:23:57.044901'),
	(83, 2, 'dm', '💬 Pesan baru dari Dani', 'Cara nambah absen buat ekstern gimana kang?', '/dm.php?u=4', 1, '2026-06-02 13:28:25.269817', '2026-06-02 13:28:25.269817'),
	(85, 2, 'event', 'Pendaftaran event berhasil', 'Anda terdaftar di event #2', '/event.php?id=2', 1, '2026-06-03 14:34:00.990057', '2026-06-03 14:34:00.990057'),
	(87, 3, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 11:08:50.717128', '2026-06-05 11:08:50.717128'),
	(89, 5, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:50.888649', '2026-06-05 11:08:50.888649'),
	(90, 6, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:50.973956', '2026-06-05 11:08:50.973956'),
	(91, 7, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.056553', '2026-06-05 11:08:51.056553'),
	(92, 8, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.139435', '2026-06-05 11:08:51.139435'),
	(93, 9, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.222577', '2026-06-05 11:08:51.222577'),
	(94, 10, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.306901', '2026-06-05 11:08:51.306901'),
	(95, 11, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.389553', '2026-06-05 11:08:51.389553'),
	(96, 13, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.471926', '2026-06-05 11:08:51.471926'),
	(97, 14, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.554643', '2026-06-05 11:08:51.554643'),
	(98, 15, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.636638', '2026-06-05 11:08:51.636638'),
	(99, 16, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.718888', '2026-06-05 11:08:51.718888'),
	(100, 17, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.802235', '2026-06-05 11:08:51.802235'),
	(101, 20, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 11:08:51.884856', '2026-06-05 11:08:51.884856'),
	(86, 2, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 11:08:50.633609', '2026-06-05 11:08:50.633609'),
	(31, 4, 'event', '🎉 Event baru: Nyate Bersama Idul Adha 1447 H', 'Detail di menu Event.', '/event.php?id=2', 1, '2026-05-29 14:58:54.166954', '2026-06-01 21:23:57.044901'),
	(51, 4, 'dm', '💬 Pesan baru dari Firdam', 'Tes', '/dm.php?u=2', 1, '2026-06-01 00:12:20.653809', '2026-06-01 21:23:57.044901'),
	(52, 4, 'dm', '💬 Pesan baru dari Firdam', 'Dan', '/dm.php?u=2', 1, '2026-06-01 00:12:38.298878', '2026-06-01 21:23:57.044901'),
	(53, 4, 'dm', '💬 Pesan baru dari Firdam', 'Dan', '/dm.php?u=2', 1, '2026-06-01 00:12:43.915597', '2026-06-01 21:23:57.044901'),
	(84, 4, 'dm', '💬 Pesan baru dari Firdam', 'Ada di menu input absensi', '/dm.php?u=2', 1, '2026-06-02 20:21:10.06544', '2026-06-02 20:21:10.06544'),
	(102, 21, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-05 11:08:51.966982', '2026-06-05 11:08:51.966982'),
	(103, 16, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.171271', '2026-06-05 20:20:49.171271'),
	(104, 13, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.25329', '2026-06-05 20:20:49.25329'),
	(106, 8, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.412545', '2026-06-05 20:20:49.412545'),
	(107, 6, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.492124', '2026-06-05 20:20:49.492124'),
	(108, 7, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.571807', '2026-06-05 20:20:49.571807'),
	(109, 20, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.650943', '2026-06-05 20:20:49.650943'),
	(110, 14, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.732424', '2026-06-05 20:20:49.732424'),
	(111, 21, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-05 20:20:49.813133', '2026-06-05 20:20:49.813133'),
	(113, 15, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:49.972862', '2026-06-05 20:20:49.972862'),
	(114, 9, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:50.054761', '2026-06-05 20:20:50.054761'),
	(115, 10, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:50.133906', '2026-06-05 20:20:50.133906'),
	(116, 11, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:50.213828', '2026-06-05 20:20:50.213828'),
	(118, 17, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:50.372629', '2026-06-05 20:20:50.372629'),
	(119, 5, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-05 20:20:50.451785', '2026-06-05 20:20:50.451785'),
	(112, 2, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 20:20:49.892012', '2026-06-05 20:20:49.892012'),
	(120, 11, 'event', 'Pendaftaran event berhasil', 'Anda terdaftar di event #2', '/event.php?id=2', 0, '2026-06-06 14:47:02.784461', '2026-06-06 14:47:02.784461'),
	(88, 4, 'event', 'Absensi Jogging tanggal 05 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Parkiran Taman Sumringah. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Parkiran%20Taman%20Sumringah.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 11:08:50.802138', '2026-06-05 11:08:50.802138'),
	(105, 4, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 20:20:49.33298', '2026-06-05 20:20:49.33298'),
	(122, 3, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:36.805833', '2026-06-13 05:40:36.805833'),
	(123, 4, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:36.886955', '2026-06-13 05:40:36.886955'),
	(124, 5, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:36.970393', '2026-06-13 05:40:36.970393'),
	(125, 6, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.054695', '2026-06-13 05:40:37.054695'),
	(126, 7, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.135909', '2026-06-13 05:40:37.135909'),
	(127, 8, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.217424', '2026-06-13 05:40:37.217424'),
	(128, 9, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.299837', '2026-06-13 05:40:37.299837'),
	(129, 10, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.38182', '2026-06-13 05:40:37.38182'),
	(130, 11, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.46669', '2026-06-13 05:40:37.46669'),
	(131, 13, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.550653', '2026-06-13 05:40:37.550653'),
	(132, 14, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.63327', '2026-06-13 05:40:37.63327'),
	(133, 15, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.714624', '2026-06-13 05:40:37.714624'),
	(134, 16, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.795582', '2026-06-13 05:40:37.795582'),
	(135, 17, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.87649', '2026-06-13 05:40:37.87649'),
	(136, 20, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:37.957369', '2026-06-13 05:40:37.957369'),
	(137, 21, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-13 05:40:38.039415', '2026-06-13 05:40:38.039415'),
	(146, 5, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:40:59.991942', '2026-06-13 05:40:59.991942'),
	(147, 6, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.075228', '2026-06-13 05:41:00.075228'),
	(144, 3, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:59.829215', '2026-06-13 05:40:59.829215'),
	(145, 4, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:59.910441', '2026-06-13 05:40:59.910441'),
	(148, 7, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.157088', '2026-06-13 05:41:00.157088'),
	(149, 8, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.242036', '2026-06-13 05:41:00.242036'),
	(150, 9, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.325067', '2026-06-13 05:41:00.325067'),
	(151, 10, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.406363', '2026-06-13 05:41:00.406363'),
	(152, 11, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.487417', '2026-06-13 05:41:00.487417'),
	(153, 13, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.568996', '2026-06-13 05:41:00.568996'),
	(154, 14, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.650496', '2026-06-13 05:41:00.650496'),
	(155, 15, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.731354', '2026-06-13 05:41:00.731354'),
	(156, 16, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.812478', '2026-06-13 05:41:00.812478'),
	(157, 17, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.893032', '2026-06-13 05:41:00.893032'),
	(158, 20, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 05:41:00.974036', '2026-06-13 05:41:00.974036'),
	(159, 21, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-13 05:41:01.055474', '2026-06-13 05:41:01.055474'),
	(168, 5, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:03.913913', '2026-06-13 06:04:03.913913'),
	(169, 6, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:03.995249', '2026-06-13 06:04:03.995249'),
	(170, 7, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.077969', '2026-06-13 06:04:04.077969'),
	(171, 8, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.159494', '2026-06-13 06:04:04.159494'),
	(166, 3, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 06:04:03.751293', '2026-06-13 06:04:03.751293'),
	(167, 4, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 06:04:03.832426', '2026-06-13 06:04:03.832426'),
	(172, 9, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.24201', '2026-06-13 06:04:04.24201'),
	(173, 10, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.325534', '2026-06-13 06:04:04.325534'),
	(174, 11, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.406505', '2026-06-13 06:04:04.406505'),
	(175, 13, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.487325', '2026-06-13 06:04:04.487325'),
	(176, 14, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.568505', '2026-06-13 06:04:04.568505'),
	(177, 15, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.651243', '2026-06-13 06:04:04.651243'),
	(178, 16, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.732811', '2026-06-13 06:04:04.732811'),
	(179, 17, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.814347', '2026-06-13 06:04:04.814347'),
	(180, 20, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 06:04:04.895272', '2026-06-13 06:04:04.895272'),
	(181, 21, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-13 06:04:04.977535', '2026-06-13 06:04:04.977535'),
	(165, 2, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 06:04:03.669499', '2026-06-13 06:04:03.669499'),
	(143, 2, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:59.746836', '2026-06-13 05:40:59.746836'),
	(121, 2, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 05:40:36.723484', '2026-06-13 05:40:36.723484'),
	(190, 5, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:15.851442', '2026-06-13 07:56:15.851442'),
	(191, 6, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:15.932051', '2026-06-13 07:56:15.932051'),
	(192, 7, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.012704', '2026-06-13 07:56:16.012704'),
	(188, 3, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 07:56:15.688483', '2026-06-13 07:56:15.688483'),
	(189, 4, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 07:56:15.769615', '2026-06-13 07:56:15.769615'),
	(193, 8, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.094229', '2026-06-13 07:56:16.094229'),
	(194, 9, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.174628', '2026-06-13 07:56:16.174628'),
	(195, 10, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.254495', '2026-06-13 07:56:16.254495'),
	(196, 11, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.335243', '2026-06-13 07:56:16.335243'),
	(197, 13, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.414817', '2026-06-13 07:56:16.414817'),
	(198, 14, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.495934', '2026-06-13 07:56:16.495934'),
	(199, 15, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.575905', '2026-06-13 07:56:16.575905'),
	(200, 16, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.656396', '2026-06-13 07:56:16.656396'),
	(201, 17, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.736883', '2026-06-13 07:56:16.736883'),
	(202, 20, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 07:56:16.817098', '2026-06-13 07:56:16.817098'),
	(203, 21, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-13 07:56:16.897417', '2026-06-13 07:56:16.897417'),
	(187, 2, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 07:56:15.606066', '2026-06-13 07:56:15.606066'),
	(212, 5, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289525429272?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.424897', '2026-06-13 12:57:15.424897'),
	(213, 6, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282316481216?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.508554', '2026-06-13 12:57:15.508554'),
	(214, 7, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285814120846?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.590087', '2026-06-13 12:57:15.590087'),
	(215, 8, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282184381823?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.671607', '2026-06-13 12:57:15.671607'),
	(209, 2, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281386369207?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 12:57:15.179975', '2026-06-13 12:57:15.179975'),
	(210, 3, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 12:57:15.262031', '2026-06-13 12:57:15.262031'),
	(211, 4, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/62895337148803?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-13 12:57:15.343624', '2026-06-13 12:57:15.343624'),
	(216, 9, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6289502639933?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.752859', '2026-06-13 12:57:15.752859'),
	(217, 10, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282320781890?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.835095', '2026-06-13 12:57:15.835095'),
	(218, 11, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6285691767966?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.916292', '2026-06-13 12:57:15.916292'),
	(219, 13, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281223450704?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:15.997465', '2026-06-13 12:57:15.997465'),
	(220, 14, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287854972839?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:16.078923', '2026-06-13 12:57:16.078923'),
	(221, 15, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282117100115?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:16.160159', '2026-06-13 12:57:16.160159'),
	(222, 16, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282118785024?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:16.241707', '2026-06-13 12:57:16.241707'),
	(223, 17, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6282218532348?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:16.323548', '2026-06-13 12:57:16.323548'),
	(224, 20, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', 'https://wa.me/6287822615464?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Jogging%22%20di%20Flamboyan%20Jogging.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 0, '2026-06-13 12:57:16.404557', '2026-06-13 12:57:16.404557'),
	(225, 21, 'event', 'Absensi Jogging tanggal 13 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Jogging" di Flamboyan Jogging. Cek riwayat kamu di aplikasi.', '/', 0, '2026-06-13 12:57:16.485763', '2026-06-13 12:57:16.485763'),
	(117, 3, 'event', 'Absensi Badminton tanggal 02 Jun 2026', 'Absensi telah diinput admin untuk kegiatan "Badminton" di GOR Gaza. Cek riwayat kamu di aplikasi.', 'https://wa.me/6281369248630?text=Absensi%20telah%20diinput%20admin%20untuk%20kegiatan%20%22Badminton%22%20di%20GOR%20Gaza.%20Cek%20riwayat%20kamu%20di%20aplikasi.', 1, '2026-06-05 20:20:50.293472', '2026-06-05 20:20:50.293472');
/*!40000 ALTER TABLE "notifications" ENABLE KEYS */;

-- Dumping structure for table public.pengeluaran_kegiatan
CREATE TABLE IF NOT EXISTS "pengeluaran_kegiatan" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''pengeluaran_kegiatan_id_seq''::regclass)',
	"jadwal_id" INTEGER NULL DEFAULT NULL,
	"tanggal" DATE NOT NULL DEFAULT 'CURRENT_DATE',
	"kategori" VARCHAR(60) NULL DEFAULT NULL,
	"judul" VARCHAR(200) NOT NULL,
	"jumlah" BIGINT NOT NULL DEFAULT '0',
	"catatan" TEXT NULL DEFAULT NULL,
	"bukti_url" TEXT NULL DEFAULT NULL,
	"created_by" INTEGER NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"dana_dari" VARCHAR(150) NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "pengeluaran_kegiatan_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "pengeluaran_kegiatan_created_by_fkey" FOREIGN KEY ("created_by") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.pengeluaran_kegiatan: -1 rows
/*!40000 ALTER TABLE "pengeluaran_kegiatan" DISABLE KEYS */;
INSERT INTO "pengeluaran_kegiatan" ("id", "jadwal_id", "tanggal", "kategori", "judul", "jumlah", "catatan", "bukti_url", "created_by", "created_at", "dana_dari") VALUES
	(1, 6, '2026-05-23', NULL, 'Sewa Lapang 2 Jam dan Konsumsi', 76000, 'Tidak ada', NULL, 2, '2026-05-30 15:06:08.76388', NULL),
	(3, 1, '2026-04-16', 'Konsumsi', 'Konsumsi', 32000, 'Dani Rifat', NULL, 2, '2026-05-30 15:12:35.797556', NULL),
	(4, 2, '2026-04-22', NULL, 'Sewa Lapang 2 Jam dan Konsumsi', 70000, 'Tidak ada', NULL, 2, '2026-05-30 15:13:20.984815', NULL),
	(5, 5, '2026-05-17', '-', 'Sewa Lapang 2 Jam dan Konsumsi', 55000, 'Tidak ada', NULL, 2, '2026-06-01 22:50:44.855311', NULL),
	(6, 8, '2026-06-02', 'Sewa Lapang', 'Sewa Lapang 2 Jam', 50000, 'Dr Firdam', NULL, 2, '2026-06-02 20:24:50.167984', NULL),
	(7, 8, '2026-06-02', 'Shuttlecock', 'Kok Badminton 1', 10000, 'Dari rifat', NULL, 2, '2026-06-02 20:25:38.08549', NULL),
	(8, 8, '2026-06-02', 'Konsumsi Mie Ayam', 'Mie Ayam Pujas Gor Gaza', 96000, 'Dari Firdam 50 + Aziz 46 (12.000 satu porsi)', NULL, 2, '2026-06-02 20:26:51.364726', NULL),
	(9, 3, '2026-06-02', 'Konsumsi', 'Snack Snack', 35000, 'Dari Rifat', NULL, 2, '2026-06-02 20:29:08.646703', NULL),
	(10, 10, '2026-06-13', 'Konsumsi', 'Makan Wicipi 7 orang', 126000, NULL, NULL, 2, '2026-06-13 07:24:58.09904', NULL);
/*!40000 ALTER TABLE "pengeluaran_kegiatan" ENABLE KEYS */;

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

-- Dumping data for table public.posts: 13 rows
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
	(29, 3, '', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Rifat-story-1779770124-f0e41a1d_1a1BS0Fet.jpg', 'story', '2026-05-27 11:35:26.013084', '2026-05-26 11:35:26.013084', NULL),
	(32, 2, 'Antara fakta dan opini', 'https://ik.imagekit.io/ahsansur/sportapp/social/May_2026/Firdam-post-1780049475-cedee336_wPxDCJpb-.jpg', 'post', NULL, '2026-05-29 17:11:17.421259', NULL),
	(33, 4, 'Konspirasi or Fakta?', 'https://ik.imagekit.io/ahsansur/sportapp/social/June_2026/Dani-post-1780327470-21d51df0_gVR0VWdgw.jpg', 'post', NULL, '2026-06-01 22:24:32.328472', NULL),
	(35, 2, 'Menjelang beberapa hari lagi', 'https://ik.imagekit.io/ahsansur/sportapp/social/June_2026/Firdam-story-1780545790-0a5bd0da_icwT3m8YF.jpg', 'story', '2026-06-05 11:03:12.251466', '2026-06-04 11:03:12.251466', NULL),
	(36, 2, 'Dapet dr temen', 'https://ik.imagekit.io/ahsansur/sportapp/social/June_2026/Firdam-post-1780557389-7755bfed_hv-LogMMN.jpg', 'post', NULL, '2026-06-04 14:16:31.435009', NULL),
	(37, 2, 'Antara yang terlihat dan kenyataan', 'https://ik.imagekit.io/ahsansur/sportapp/social/June_2026/Firdam-post-1780557518-7a537cdf_ye7Y5bm13.jpg', 'post', NULL, '2026-06-04 14:18:39.890176', NULL),
	(38, 2, 'Naon ieu?', 'https://ik.imagekit.io/ahsansur/sportapp/social/June_2026/Firdam-post-1781313030-a4f5b1e7_WM6nPUhbe.jpg', 'post', NULL, '2026-06-13 08:10:31.505273', NULL);
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
INSERT INTO "post_bookmarks" ("user_id", "post_id", "created_at") VALUES
	(4, 33, '2026-06-02 11:30:17.935851');
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
	(10, 32, 15, 'Sumber penghasilan zionis', '2026-05-30 09:58:40.957355'),
	(11, 32, 2, 'Tru', '2026-05-30 10:31:45.242195'),
	(12, 32, 2, '🍺', '2026-05-30 10:32:06.204291'),
	(13, 33, 2, 'Dunia terasa luas', '2026-06-01 22:29:29.316883');
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
	(22, 2, '2026-05-24 21:27:42.950059'),
	(32, 2, '2026-05-29 17:11:38.556404'),
	(22, 15, '2026-05-30 09:59:06.795263'),
	(33, 2, '2026-06-01 22:29:52.872995'),
	(33, 4, '2026-06-02 11:30:23.159956'),
	(38, 2, '2026-06-14 13:42:00.164657'),
	(37, 2, '2026-06-14 13:42:18.882106');
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
	(29, 2, '2026-05-26 15:41:05.828374'),
	(35, 2, '2026-06-04 11:03:30.23482'),
	(35, 16, '2026-06-05 05:36:36.635217');
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
	(20, 39, '2026-05-29 15:55:47.827124'),
	(6, 43, '2026-06-01 22:12:15.742673'),
	(21, 111, '2026-06-06 09:52:20.626092'),
	(11, 120, '2026-06-06 14:47:09.90958'),
	(14, 110, '2026-06-07 00:53:26.163985'),
	(2, 209, '2026-06-13 12:57:18.962833'),
	(16, 222, '2026-06-14 09:13:09.278581'),
	(3, 210, '2026-06-14 09:14:26.310671'),
	(4, 211, '2026-06-15 07:40:47.867884');
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
	(7, 2, 60, 12, '', '2026-05-24 10:24:33.534955'),
	(10, 2, 24, 55, '', '2026-06-13 08:29:00.282281');
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
	(2, 7, 179, '2026-06-13 08:08:51.572806');
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
	('login:::1', '2026-06-15 12:30:42.209073'),
	('login:::1', '2026-06-15 12:30:52.449987'),
	('login:::1', '2026-06-15 12:31:08.066771');
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

-- Dumping data for table public.run_points: 75 rows
/*!40000 ALTER TABLE "run_points" DISABLE KEYS */;
INSERT INTO "run_points" ("id", "session_id", "lat", "lng", "ts", "speed_mps", "accuracy_m") VALUES
	(1629, 19, -6.9295148, 107.7123639, '2026-06-12 06:29:17.943336', 1.0881296396255, 5.451000213623),
	(1643, 19, -6.9298064, 107.7122274, '2026-06-12 06:29:52.993455', 1.6439760923386, 4.308000087738),
	(1556, 17, -6.926339, 107.7273922, '2026-06-06 06:24:19.092457', 1.2816851139069, 17.844999313354),
	(1570, 17, -6.9265965, 107.7277133, '2026-06-06 06:24:55.083633', 0.92555165290833, 1.7159999608994),
	(1584, 17, -6.9267589, 107.7281921, '2026-06-06 06:25:35.857966', 1.0858000516891, 1.6849999427795),
	(1598, 17, -6.9262116, 107.728748, '2026-06-06 06:27:01.502265', 1.2710144519806, 2),
	(1612, 17, -6.9255302, 107.7291628, '2026-06-06 06:29:12.580555', 0.56771332025528, 9.0920000076294),
	(1626, 18, -6.9273999, 107.713202, '2026-06-11 06:05:33.973114', 0.86307621002197, 3.4549999237061),
	(1630, 19, -6.9295148, 107.7123639, '2026-06-12 06:29:19.757036', 1.0881296396255, 5.451000213623),
	(1644, 19, -6.9297159, 107.712249, '2026-06-12 06:29:54.773102', 1.0246359109879, 4.710000038147),
	(1557, 17, -6.926339, 107.7273922, '2026-06-06 06:24:20.838367', 1.2816851139069, 17.844999313354),
	(1571, 17, -6.9266137, 107.7277577, '2026-06-06 06:24:59.117016', 1.167513012886, 1.8159999847412),
	(1585, 17, -6.9267589, 107.7281921, '2026-06-06 06:25:37.618943', 1.0858000516891, 1.6849999427795),
	(1599, 17, -6.9262116, 107.728748, '2026-06-06 06:27:03.28581', 1.2710144519806, 2),
	(1613, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:14.370832', 1.0051058530807, 7.4809999465942),
	(1627, 18, -6.9273999, 107.713202, '2026-06-11 06:05:35.762836', 0.86307621002197, 3.4549999237061),
	(1631, 19, -6.9295586, 107.7123285, '2026-06-12 06:29:22.897194', 1.0467429161072, 5.5980000495911),
	(1645, 19, -6.9298258, 107.7121827, '2026-06-12 06:29:56.560376', 1.2770917415619, 3.2409999370575),
	(1558, 17, -6.9263884, 107.7273828, '2026-06-06 06:24:24.53814', 1.3161661624908, 11.772000312805),
	(1572, 17, -6.9266163, 107.7278036, '2026-06-06 06:25:00.881979', 1.1164000034332, 1.7000000476837),
	(1586, 17, -6.9267719, 107.7282432, '2026-06-06 06:25:42.947856', 1.3574486970901, 1.7159999608994),
	(1600, 17, -6.9261675, 107.7287654, '2026-06-06 06:27:08.633302', 1.081723690033, 1.7829999923706),
	(1614, 17, -6.9254216, 107.7292342, '2026-06-06 06:29:16.179742', 1.0373097658157, 3.0139999389648),
	(1628, 18, -6.9273999, 107.713202, '2026-06-11 06:05:38.285341', 0.86307621002197, 3.4549999237061),
	(1632, 19, -6.9295586, 107.7123285, '2026-06-12 06:29:24.727289', 1.0467429161072, 5.5980000495911),
	(1646, 19, -6.9297608, 107.7122237, '2026-06-12 06:29:58.933733', 1.5747481584549, 4.7220001220703),
	(1559, 17, -6.9263884, 107.7273828, '2026-06-06 06:24:26.317482', 1.3161661624908, 11.772000312805),
	(1573, 17, -6.9266163, 107.7278036, '2026-06-06 06:25:04.380525', 1.1164000034332, 1.7000000476837),
	(1587, 17, -6.9267804, 107.7282886, '2026-06-06 06:25:44.735639', 1.1792486906052, 1.75),
	(1601, 17, -6.9253913, 107.7292299, '2026-06-06 06:28:20.869316', NULL, 19.95299911499),
	(1615, 17, -6.925392, 107.7292737, '2026-06-06 06:29:17.972738', 1.0260392427444, 2),
	(1633, 19, -6.9295944, 107.7122983, '2026-06-12 06:29:33.177181', 0.736512362957, 4.8509998321533),
	(1647, 19, -6.9297608, 107.7122237, '2026-06-12 06:30:00.802724', 1.5747481584549, 4.7220001220703),
	(1560, 17, -6.9264385, 107.7274655, '2026-06-06 06:24:28.074596', 0.72685295343399, 1.8500000238419),
	(1574, 17, -6.9266404, 107.7278494, '2026-06-06 06:25:06.164937', 1.1761541366577, 1.75),
	(1588, 17, -6.9267804, 107.7282886, '2026-06-06 06:25:46.46601', 1.1792486906052, 1.75),
	(1602, 17, -6.9253913, 107.7292299, '2026-06-06 06:28:41.919032', NULL, 19.95299911499),
	(1616, 17, -6.925392, 107.7292737, '2026-06-06 06:29:19.803423', 1.0260392427444, 2),
	(1634, 19, -6.9295944, 107.7122983, '2026-06-12 06:29:34.973432', 0.736512362957, 4.8509998321533),
	(1648, 19, -6.9297159, 107.712249, '2026-06-12 06:30:06.394818', 1.0246359109879, 4.710000038147),
	(1561, 17, -6.9264385, 107.7274655, '2026-06-06 06:24:30.403668', 0.72685295343399, 1.8500000238419),
	(1575, 17, -6.9266404, 107.7278494, '2026-06-06 06:25:12.054662', 1.1761541366577, 1.75),
	(1589, 17, -6.9268044, 107.7283321, '2026-06-06 06:25:50.479108', 1.1153500080109, 1.7330000400543),
	(1603, 17, -6.9261675, 107.7287654, '2026-06-06 06:28:43.735239', 1.081723690033, 1.7829999923706),
	(1617, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:21.550589', 1.0051058530807, 7.4809999465942),
	(1635, 19, -6.9295944, 107.7122983, '2026-06-12 06:29:36.782834', 0.736512362957, 4.8509998321533),
	(1649, 19, -6.9298258, 107.7121827, '2026-06-12 06:30:08.232326', 1.2770917415619, 3.2409999370575),
	(1562, 17, -6.9263884, 107.7273828, '2026-06-06 06:24:34.451968', 1.3161661624908, 11.772000312805),
	(1576, 17, -6.9266592, 107.7278978, '2026-06-06 06:25:14.0721', 1.2814434766769, 1.7660000324249),
	(1590, 17, -6.9268173, 107.7283796, '2026-06-06 06:25:56.39778', 0.61391752958298, 1.7000000476837),
	(1604, 17, -6.9255302, 107.7291628, '2026-06-06 06:28:47.769074', 0.56771332025528, 9.0920000076294),
	(1618, 17, -6.9254123, 107.7293244, '2026-06-06 06:29:23.879313', 0.75620067119598, 1.7599999904633),
	(1636, 19, -6.9295944, 107.7122983, '2026-06-12 06:29:38.580692', 0.736512362957, 4.8509998321533),
	(1650, 19, -6.9297608, 107.7122237, '2026-06-12 06:30:10.052912', 1.5747481584549, 4.7220001220703),
	(1563, 17, -6.9264581, 107.7275063, '2026-06-06 06:24:36.294796', 1.4890049695969, 1.7400000095367),
	(1577, 17, -6.9266592, 107.7278978, '2026-06-06 06:25:16.435477', 1.2814434766769, 1.7660000324249),
	(1591, 17, -6.9268173, 107.7283796, '2026-06-06 06:26:44.272864', 0.61391752958298, 1.7000000476837),
	(1605, 17, -6.9255302, 107.7291628, '2026-06-06 06:28:53.815448', 0.56771332025528, 9.0920000076294),
	(1619, 17, -6.9254123, 107.7293244, '2026-06-06 06:29:25.644763', 0.75620067119598, 1.7599999904633),
	(1637, 19, -6.9296645, 107.7122686, '2026-06-12 06:29:40.357825', 0.96938896179199, 4.6350002288818),
	(1651, 19, -6.9298064, 107.7122274, '2026-06-12 06:30:11.848989', 1.6439760923386, 4.308000087738),
	(1564, 17, -6.9264308, 107.7274144, '2026-06-06 06:24:38.580261', 1.0967756509781, 6.039999961853),
	(1578, 17, -6.9266769, 107.7279508, '2026-06-06 06:25:18.778533', 0.99880218505859, 1.8660000562668),
	(1592, 17, -6.9268173, 107.7283796, '2026-06-06 06:26:46.004014', 0.61391752958298, 1.7000000476837),
	(1606, 17, -6.9255302, 107.7291628, '2026-06-06 06:28:55.828555', 0.56771332025528, 9.0920000076294),
	(1620, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:27.375978', 1.0051058530807, 7.4809999465942),
	(1638, 19, -6.9296645, 107.7122686, '2026-06-12 06:29:42.159145', 0.96938896179199, 4.6350002288818),
	(1652, 19, -6.9298064, 107.7122274, '2026-06-12 06:30:13.693481', 1.6439760923386, 4.308000087738),
	(1565, 17, -6.9264833, 107.7275447, '2026-06-06 06:24:40.908544', 0.94812023639679, 1.7330000400543),
	(1579, 17, -6.9266769, 107.7279508, '2026-06-06 06:25:21.11453', 0.99880218505859, 1.8660000562668),
	(1593, 17, -6.9263369, 107.7286524, '2026-06-06 06:26:48.336847', NULL, 15.571000099182),
	(1607, 17, -6.9255302, 107.7291628, '2026-06-06 06:28:57.588427', 0.56771332025528, 9.0920000076294),
	(1621, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:29.118875', 1.0051058530807, 7.4809999465942),
	(1639, 19, -6.9297159, 107.712249, '2026-06-12 06:29:43.938706', 1.0246359109879, 4.710000038147),
	(1653, 19, -6.9305583, 107.7111442, '2026-06-12 07:06:35.512409', NULL, 30.114000320435),
	(1566, 17, -6.9265138, 107.7275918, '2026-06-06 06:24:44.568296', 1.0484145879745, 1.8500000238419),
	(1580, 17, -6.9266933, 107.7280019, '2026-06-06 06:25:24.563471', 0.76596242189407, 1.8999999761581),
	(1594, 17, -6.9263369, 107.7286524, '2026-06-06 06:26:52.021206', NULL, 15.571000099182),
	(1608, 17, -6.9255302, 107.7291628, '2026-06-06 06:28:59.367783', 0.56771332025528, 9.0920000076294),
	(1622, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:32.665468', 1.0051058530807, 7.4809999465942),
	(1640, 19, -6.9297159, 107.712249, '2026-06-12 06:29:45.721809', 1.0246359109879, 4.710000038147),
	(1567, 17, -6.9264308, 107.7274144, '2026-06-06 06:24:47.970845', 1.0967756509781, 6.039999961853),
	(1581, 17, -6.9267227, 107.7280511, '2026-06-06 06:25:28.85951', 1.3329480886459, 1.7999999523163),
	(1595, 17, -6.9264938, 107.7285552, '2026-06-06 06:26:53.803645', 1.6513434648514, 5.8239998817444),
	(1609, 17, -6.9255302, 107.7291628, '2026-06-06 06:29:01.183973', 0.56771332025528, 9.0920000076294),
	(1623, 17, -6.9254654, 107.7294633, '2026-06-06 06:29:34.458785', 0.99465620517731, 1.6499999761581),
	(1641, 19, -6.9296645, 107.7122686, '2026-06-12 06:29:49.446426', 0.96938896179199, 4.6350002288818),
	(1554, 17, -6.926339, 107.7273922, '2026-06-06 06:24:15.311586', 1.2816851139069, 17.844999313354),
	(1568, 17, -6.9265305, 107.7276387, '2026-06-06 06:24:49.705007', 1.0060453414917, 1.8329999446869),
	(1582, 17, -6.9267317, 107.7280988, '2026-06-06 06:25:30.66246', 1.2950731515884, 1.7660000324249),
	(1596, 17, -6.9262531, 107.7287239, '2026-06-06 06:26:55.586017', 1.212863445282, 3.7000000476837),
	(1610, 17, -6.9255302, 107.7291628, '2026-06-06 06:29:02.944302', 0.56771332025528, 9.0920000076294),
	(1624, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:39.935247', 1.0051058530807, 7.4809999465942),
	(1642, 19, -6.9296645, 107.7122686, '2026-06-12 06:29:51.225696', 0.96938896179199, 4.6350002288818),
	(1555, 17, -6.926339, 107.7273922, '2026-06-06 06:24:17.071326', 1.2816851139069, 17.844999313354),
	(1569, 17, -6.9265628, 107.7276748, '2026-06-06 06:24:51.473274', 0.9789742231369, 1.6829999685287),
	(1583, 17, -6.9267469, 107.7281483, '2026-06-06 06:25:34.09183', 1.3859300613403, 1.7159999608994),
	(1597, 17, -6.9268173, 107.7283796, '2026-06-06 06:26:57.997086', 0.61391752958298, 1.7000000476837),
	(1611, 17, -6.9255302, 107.7291628, '2026-06-06 06:29:09.162622', 0.56771332025528, 9.0920000076294),
	(1625, 17, -6.9254634, 107.7292011, '2026-06-06 06:29:41.671674', 1.0051058530807, 7.4809999465942);
/*!40000 ALTER TABLE "run_points" ENABLE KEYS */;

-- Dumping structure for table public.run_routes
CREATE TABLE IF NOT EXISTS "run_routes" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''run_routes_id_seq''::regclass)',
	"user_id" BIGINT NOT NULL,
	"nama" TEXT NOT NULL DEFAULT 'Rute',
	"jarak_m" DOUBLE PRECISION NOT NULL DEFAULT '0',
	"elevasi_pref" TEXT NOT NULL DEFAULT 'apa-saja',
	"surface_pref" TEXT NOT NULL DEFAULT 'apa-saja',
	"geojson" JSONB NOT NULL,
	"is_public" BOOLEAN NOT NULL DEFAULT 'false',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "run_routes_user_idx" ("user_id", "created_at"),
	CONSTRAINT "run_routes_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.run_routes: -1 rows
/*!40000 ALTER TABLE "run_routes" DISABLE KEYS */;
/*!40000 ALTER TABLE "run_routes" ENABLE KEYS */;

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

-- Dumping data for table public.run_sessions: 6 rows
/*!40000 ALTER TABLE "run_sessions" DISABLE KEYS */;
INSERT INTO "run_sessions" ("id", "user_id", "mulai_at", "selesai_at", "jarak_m", "durasi_dtk", "kalori", "catatan", "status") VALUES
	(17, 2, '2026-06-06 06:24:09.584582', '2026-06-06 06:30:37.52453', 413.53812654343, 358, 27, NULL, 'selesai'),
	(18, 3, '2026-06-11 06:05:28.031646', '2026-06-11 06:43:37.208928', 0, 2287, 0, NULL, 'selesai'),
	(19, 3, '2026-06-12 06:29:15.242368', '2026-06-12 07:06:44.382308', 182.81882175394, 2245, 12, NULL, 'selesai');
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
	(2, 4, 17, '2026-05-23 09:15:10.100932'),
	(3, 4, 21, '2026-06-05 07:30:59.34643');
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

-- Dumping structure for table public.site_visitors
CREATE TABLE IF NOT EXISTS "site_visitors" (
	"id" BIGINT NOT NULL DEFAULT 'nextval(''site_visitors_id_seq''::regclass)',
	"ip" VARCHAR(64) NULL DEFAULT NULL,
	"user_agent" TEXT NULL DEFAULT NULL,
	"path" VARCHAR(255) NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "idx_site_visitors_created_at" ("created_at")
);

-- Dumping data for table public.site_visitors: 72 rows
/*!40000 ALTER TABLE "site_visitors" DISABLE KEYS */;
INSERT INTO "site_visitors" ("id", "ip", "user_agent", "path", "created_at") VALUES
	(1, '::1', 'Go-http-client/1.1', '/', '2026-05-30 09:51:58.763107'),
	(2, '::1', 'Go-http-client/1.1', '/', '2026-05-30 11:18:12.290922'),
	(3, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 12:23:32.706099'),
	(4, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 13:24:02.711213'),
	(5, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 14:48:37.232089'),
	(6, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-05-30 16:19:21.008588'),
	(7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-05-30 17:19:27.355654'),
	(8, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 18:29:25.417065'),
	(9, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 21:30:38.252002'),
	(10, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-30 22:51:20.666957'),
	(11, '::1', 'Go-http-client/1.1', '/', '2026-05-31 06:06:26.491776'),
	(12, '::1', 'Go-http-client/1.1', '/', '2026-05-31 07:07:55.92647'),
	(13, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-05-31 11:38:48.100049'),
	(14, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-05-31 16:04:50.596434'),
	(15, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-05-31 17:46:59.054147'),
	(16, '::1', 'Go-http-client/1.1', '/', '2026-05-31 19:25:48.121207'),
	(17, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-05-31 23:41:38.797271'),
	(18, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 00:49:36.469156'),
	(19, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 06:09:03.121185'),
	(20, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-01 08:43:44.230158'),
	(21, '::1', 'Go-http-client/1.1', '/', '2026-06-01 09:51:49.915651'),
	(54, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 12:31:13.667795'),
	(55, '::1', 'Go-http-client/1.1', '/', '2026-06-01 13:42:32.899087'),
	(56, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?guest=1', '2026-06-01 14:55:54.514449'),
	(57, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 16:32:28.849651'),
	(58, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 19:13:50.264309'),
	(59, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 20:41:40.096343'),
	(60, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-01 22:05:54.205797'),
	(61, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 00:03:58.623199'),
	(62, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 05:30:51.97562'),
	(63, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 07:30:51.039574'),
	(64, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 08:34:31.415341'),
	(65, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 10:03:47.541683'),
	(66, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 11:22:55.541184'),
	(67, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 12:27:54.536161'),
	(68, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 16:10:15.606372'),
	(69, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 17:43:29.177474'),
	(70, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 20:03:25.945858'),
	(71, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-02 22:09:53.260067'),
	(72, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 05:35:39.629032'),
	(73, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-06-03 08:44:57.908496'),
	(74, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 10:24:19.918977'),
	(75, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 11:57:59.044813'),
	(76, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 13:37:05.820208'),
	(77, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 14:37:07.736446'),
	(78, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 17:40:10.73799'),
	(79, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-03 19:12:47.307654'),
	(80, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-03 22:28:08.185764'),
	(81, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-04 05:42:31.03429'),
	(82, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-04 06:54:34.012198'),
	(83, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-04 11:02:13.765683'),
	(84, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-04 13:33:44.801762'),
	(85, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-04 15:32:14.536281'),
	(86, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-04 18:36:59.52357'),
	(87, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-04 21:25:24.334397'),
	(88, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-04 22:50:44.591772'),
	(89, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-05 05:07:28.195192'),
	(90, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-05 07:28:15.790515'),
	(91, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-05 11:00:34.996463'),
	(92, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-05 15:13:45.168272'),
	(93, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-05 20:10:10.352491'),
	(94, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 05:00:08.830909'),
	(95, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-06 06:12:29.982445'),
	(96, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/', '2026-06-06 08:26:08.074865'),
	(97, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 09:34:21.165887'),
	(98, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 10:49:06.998224'),
	(99, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/sportapp_core/index.php', '2026-06-06 11:04:49.019506'),
	(100, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 11:52:03.775777'),
	(101, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 13:02:27.055308'),
	(102, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-06 14:24:21.403241'),
	(103, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-06 15:44:55.193445'),
	(104, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 00:25:49.3597'),
	(105, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-07 03:50:59.943791'),
	(106, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 07:51:38.920833'),
	(107, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 08:51:47.546157'),
	(108, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 10:17:12.674827'),
	(109, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-07 13:37:09.119587'),
	(110, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 14:37:16.942627'),
	(111, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-07 16:02:06.444033'),
	(112, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-07 22:25:06.857681'),
	(113, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-08 07:03:14.542502'),
	(114, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-08 10:57:38.263239'),
	(115, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-08 14:21:12.416984'),
	(116, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-08 23:28:05.47191'),
	(117, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-09 04:34:01.76681'),
	(118, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-09 09:29:52.785577'),
	(119, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-09 10:47:31.310798'),
	(120, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-09 15:45:06.97007'),
	(121, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-09 17:31:17.667907'),
	(122, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-09 22:31:59.111301'),
	(123, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-10 05:11:18.271832'),
	(124, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-10 12:32:43.96901'),
	(125, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-10 14:29:07.889728'),
	(126, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-10 15:35:03.59704'),
	(127, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-10 20:54:46.321418'),
	(128, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-10 21:54:53.467071'),
	(129, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 04:34:49.467445'),
	(130, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 05:43:03.381756'),
	(131, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 06:43:55.278795'),
	(132, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 08:37:33.950702'),
	(133, '::1', 'Mozilla/5.0 (Linux; Android 12; Infinix X6515) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 11:39:46.157901'),
	(134, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 15:45:52.328931'),
	(135, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/', '2026-06-11 16:47:04.938863'),
	(136, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-11 17:59:45.819563'),
	(137, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-11 19:44:14.580833'),
	(138, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-11 22:01:52.961764'),
	(139, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-06-11 23:03:11.910729'),
	(140, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-12 04:51:00.723023'),
	(141, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-12 06:28:37.606284'),
	(142, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-12 07:29:56.872767'),
	(143, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-12 09:00:19.674229'),
	(144, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-12 11:44:19.491771'),
	(145, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-12 13:03:21.370629'),
	(146, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-12 15:35:00.407904'),
	(147, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-12 16:38:03.346853'),
	(148, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-12 18:24:36.303894'),
	(149, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-12 19:52:45.299652'),
	(150, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-13 04:26:19.920369'),
	(151, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-13 05:29:15.369993'),
	(152, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-13 07:38:31.722054'),
	(153, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-13 12:51:49.689749'),
	(154, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-06-13 21:00:56.094804'),
	(155, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-13 23:57:32.894315'),
	(156, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-14 09:13:02.487848'),
	(157, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-14 13:41:44.84644'),
	(158, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-14 21:52:59.788902'),
	(159, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php?source=pwa', '2026-06-15 04:38:43.663266'),
	(160, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-15 05:38:53.289527'),
	(161, '::1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '/index.php', '2026-06-15 07:40:39.65092'),
	(194, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-06-15 11:17:32.519148'),
	(195, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', '/index.php', '2026-06-15 12:21:27.202588');
/*!40000 ALTER TABLE "site_visitors" ENABLE KEYS */;

-- Dumping structure for table public.strava_activities
CREATE TABLE IF NOT EXISTS "strava_activities" (
	"id" BIGINT NOT NULL,
	"user_id" INTEGER NULL DEFAULT NULL,
	"name" TEXT NULL DEFAULT NULL,
	"type" VARCHAR(40) NULL DEFAULT NULL,
	"distance" NUMERIC(10,2) NULL DEFAULT NULL,
	"moving_time" INTEGER NULL DEFAULT NULL,
	"start_date" TIMESTAMP NULL DEFAULT NULL,
	"raw" JSONB NULL DEFAULT NULL,
	"imported_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "strava_activities_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.strava_activities: -1 rows
/*!40000 ALTER TABLE "strava_activities" DISABLE KEYS */;
/*!40000 ALTER TABLE "strava_activities" ENABLE KEYS */;

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
	"tampil_booking" BOOLEAN NOT NULL DEFAULT 'false',
	PRIMARY KEY ("id"),
	CONSTRAINT "tempat_jenis_id_fkey" FOREIGN KEY ("jenis_id") REFERENCES "jenis_olahraga" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "tempat_pic_user_id_fkey" FOREIGN KEY ("pic_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.tempat: 27 rows
/*!40000 ALTER TABLE "tempat" DISABLE KEYS */;
INSERT INTO "tempat" ("id", "nama", "alamat", "harga_lapang", "harga_per_jam", "status_booking", "catatan", "created_at", "lat", "lng", "pic_user_id", "kontak_wa", "jenis_id", "harga_tiket", "harga_parkir", "tampil_booking") VALUES
	(28, 'Flamboyan Pingpong', 'Jalan Flamboyan Utara No.6, Panyileukan, Bandung', 0.00, 0.00, 'tersedia', '', '2026-06-10 21:40:59.070103', -6.9383769, 107.715939, 2, NULL, 12, 0.00, 0.00, 'false'),
	(5, 'Parkiran Taman Sumringah', 'Summarecon Bandung', 0.00, 0.00, 'tersedia', '', '2026-05-21 11:16:55.600715', -6.9537503, 107.6929201, 3, NULL, 1, 0.00, 0.00, 'false'),
	(22, 'Lapang Pingpong', 'Pinggir Kampus UIN', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:15:28.522765', NULL, NULL, 4, NULL, 12, 0.00, 0.00, 'false'),
	(29, 'Flamboyan Jogging', 'Jln. Flamboyan Utara No.6', 0.00, 0.00, 'tersedia', '', '2026-06-12 15:40:02.616251', -6.9383769, 107.715939, 14, NULL, 1, 0.00, 0.00, 'false'),
	(11, 'Kolam Renang Lettu Pas Basonai', 'Lanud Sulaiman, Margahayu', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:38.344177', -6.9912435, 107.5759873, 2, NULL, 5, 0.00, 0.00, 'false'),
	(9, 'Kolam Renang Panorama', 'Ujung Berung', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:36.928503', -6.898462, 107.7103046, 2, NULL, 5, 0.00, 0.00, 'false'),
	(10, 'Kolam Renang UPI', 'UPI Setiabudi', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:05:55.865701', -6.8594515, 107.5855598, 4, NULL, 5, 0.00, 0.00, 'false'),
	(12, 'Kolam Renang Yadika', 'Tanjungsari', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:06:56.515685', -6.8974891, 107.8055482, 3, NULL, 5, 0.00, 0.00, 'false'),
	(7, 'Singgasana Sport', 'Cibaduyut', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:56:55.240167', -6.9612456, 107.5942425, 2, NULL, 12, 0.00, 0.00, 'false'),
	(19, 'Biliar Sinai', 'Baleendah, Rancamanyar', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:43.837103', NULL, NULL, 2, '089638726182', 11, 0.00, 0.00, 'true'),
	(8, 'BSD Sport', 'Cipamokolan', 0.00, 0.00, 'tersedia', '', '2026-05-22 06:59:04.47909', NULL, NULL, 3, '08872947080', 2, 0.00, 0.00, 'true'),
	(26, 'BHD - Warung Yos', 'Dago Atas', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:27:23.261738', -6.846409, 107.650307, 2, NULL, 8, 0.00, 5000.00, 'false'),
	(1, 'GOR Adiguna', 'Jln. Pertamina, Soetta', 110000.00, 110000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 3, NULL, 3, 0.00, 0.00, 'true'),
	(24, 'Gn.Pangradinan', 'Rancaekek', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:25:11.313536', -7.043889, 107.828311, 3, NULL, 8, 0.00, 5000.00, 'false'),
	(27, 'Batukuda - Manglayang', 'Batu Kuda', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:28:03.059991', -6.8928621, 107.7429292, 4, NULL, 8, 15000.00, 2000.00, 'false'),
	(23, 'Tangkuban Perahu - Cibarebeuy', 'Subang', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:24:00.396558', -6.7733931, 107.6359156, 2, NULL, 8, 0.00, 5000.00, 'false'),
	(4, 'Sindangreret - Panyileukan', 'BnC Cookies', 0.00, 0.00, 'tersedia', '', '2026-05-21 11:16:55.600715', -6.9318895, 107.7216289, 2, NULL, 10, 0.00, 0.00, 'false'),
	(6, 'GOR Azaka', 'Pasirimpun Atas', 50000.00, 50000.00, 'tersedia', '0', '2026-05-21 11:29:20.544026', NULL, NULL, 2, '081320906764', 2, 0.00, 0.00, 'true'),
	(16, 'GOR Cempaka Arum', 'Panyileukan, Al-Jabbar', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:10:20.815157', NULL, NULL, 3, NULL, 2, 0.00, 0.00, 'true'),
	(21, 'Biliar BS Pool and Cafe', 'Wastukencana, Kota Bandung', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:44.253902', -6.9081659, 107.6049949, 2, NULL, 11, 0.00, 0.00, 'true'),
	(13, 'GOR Mayasari', 'Soekarno Hatta, Bunderan Cibiru', 35000.00, 35000.00, 'tersedia', '', '2026-05-22 16:08:01.628889', NULL, NULL, 4, NULL, 2, 0.00, 0.00, 'true'),
	(2, 'GOR Mayasari', 'Soekarno Hatta, Bunderan Cibiru', 125000.00, 125000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 4, NULL, 3, 0.00, 0.00, 'true'),
	(17, 'GOR Pasanggrahan', 'Cilengkrang, Bandung', 45000.00, 45000.00, 'tersedia', '', '2026-05-22 16:12:36.197922', NULL, NULL, 2, '089655369495', 2, 0.00, 0.00, 'true'),
	(18, 'GOR Pilar Biru', 'Pilar Biru, Cibiru Hilir', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:13:13.801444', NULL, NULL, 2, NULL, 2, 0.00, 0.00, 'true'),
	(3, 'GOR Purbaya', 'Jln. Ciguruwik', 25000.00, 25000.00, 'tersedia', '', '2026-05-21 11:16:55.600715', NULL, NULL, 3, NULL, 2, 0.00, 0.00, 'true'),
	(15, 'GOR Sindangreret', 'Sindangreret, Cibiru', 40000.00, 40000.00, 'tersedia', '', '2026-05-22 16:09:31.917895', NULL, NULL, 2, '089628188960', 2, 0.00, 0.00, 'true'),
	(14, 'GOR Gaza', 'Cinunuk, Cibiru', 20000.00, 20000.00, 'tersedia', '', '2026-05-22 16:08:56.792858', -6.930473, 107.7315517, 3, '082215309779', 2, 0.00, 0.00, 'true'),
	(20, 'GOR Gaza', 'Ciguruwik, Cibiru', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:14:16.040455', -6.930473, 107.731552, 2, '082215309779', 11, 0.00, 0.00, 'true'),
	(25, 'Kina - Sanggara/Lembah Tengkorak/Pangparang', 'Bukit Kina, Cibodas', 0.00, 0.00, 'tersedia', '', '2026-05-22 16:26:01.59066', -6.8370644, 107.7277736, 2, NULL, 8, 0.00, 5000.00, 'true');
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
	(5, 'Tim A', 'Badminton', 2, 4, '', '2026-06-03 12:50:13.603977');
/*!40000 ALTER TABLE "tim" ENABLE KEYS */;

-- Dumping structure for table public.tim_member
CREATE TABLE IF NOT EXISTS "tim_member" (
	"tim_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"peran" VARCHAR(20) NOT NULL DEFAULT 'pemain',
	PRIMARY KEY ("tim_id", "user_id"),
	CONSTRAINT "tim_member_tim_id_fkey" FOREIGN KEY ("tim_id") REFERENCES "tim" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "tim_member_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.tim_member: -1 rows
/*!40000 ALTER TABLE "tim_member" DISABLE KEYS */;
/*!40000 ALTER TABLE "tim_member" ENABLE KEYS */;

-- Dumping structure for table public.toko
CREATE TABLE IF NOT EXISTS "toko" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''toko_id_seq''::regclass)',
	"nama" VARCHAR(160) NOT NULL,
	"deskripsi" TEXT NULL DEFAULT NULL,
	"alamat" TEXT NULL DEFAULT NULL,
	"no_wa" VARCHAR(25) NULL DEFAULT NULL,
	"lat" NUMERIC(10,6) NULL DEFAULT NULL,
	"lng" NUMERIC(10,6) NULL DEFAULT NULL,
	"aktif" BOOLEAN NOT NULL DEFAULT 'true',
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	"hari_buka" VARCHAR(20) NULL DEFAULT '0,1,2,3,4,5,6',
	"jam_buka" TIME NULL DEFAULT NULL,
	"jam_tutup" TIME NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	INDEX "toko_aktif_idx" ("aktif")
);

-- Dumping data for table public.toko: 6 rows
/*!40000 ALTER TABLE "toko" DISABLE KEYS */;
INSERT INTO "toko" ("id", "nama", "deskripsi", "alamat", "no_wa", "lat", "lng", "aktif", "created_at", "hari_buka", "jam_buka", "jam_tutup") VALUES
	(1, 'Ayam Penyet Esti', 'Murah...', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 10:47:53.154299', '0,1,2,3,4,5,6', NULL, NULL),
	(2, 'Tianlala Cibiru', 'Seger...', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 13:01:44.587998', '0,1,2,3,4,5,6', NULL, NULL),
	(3, 'Waroeng Cafe Yayang', 'Yummy..', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 13:03:16.861832', '0,1,2,3,4,5,6', NULL, NULL),
	(4, 'Kopi Tekun Cibiru', 'Plong..', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 13:05:01.564054', '0,1,2,3,4,5,6', NULL, NULL),
	(5, 'Nasi Dadar Abin', 'Kenyang..', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 13:06:50.459866', '0,1,2,3,4,5,6', NULL, NULL),
	(6, 'Bakso Neng Hajjah', 'Muantap..', 'Kampus UIN SGD 1', NULL, NULL, NULL, 'true', '2026-06-01 13:08:52.835693', '0,1,2,3,4,5,6', NULL, NULL),
	(7, 'WiCiPi', 'Temoat Makan murah dan Berkualitas', 'Panyileukan', NULL, NULL, NULL, 'true', '2026-06-02 12:45:31.535585', '0,1,2,3,4,5,6', '08:00:00', '17:00:00');
/*!40000 ALTER TABLE "toko" ENABLE KEYS */;

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
	(8, 2, '2026-05-15', 'Jogging', 13, 2.40, 187, 'Wow..', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg', '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34.100156', '6''14" /km', NULL, NULL, NULL),
	(18, 16, '2026-06-09', 'Jogging', 31, 5.00, 0, 'Happy Alhamdulillah, soalnya sambil dengerin podcast tentang cara meminimalisir Doom scrolling', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/ADITH_SETIAWAN-2026-06-09-Jogging_RbXWe1-Ju.png', '6a277b235c7cd75eb87e2389', '2026-06-09 09:32:04.585194', '6''15"/km', NULL, NULL, NULL),
	(19, 2, '2026-06-10', 'Jogging', 32, 5.00, 422, 'Muantap', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Firdam-2026-06-10-Jogging_iXQIuv2J5.jpg', '6a2970325c7cd75eb83ef9f5', '2026-06-10 21:09:55.502543', '6''30"/km', NULL, NULL, NULL),
	(17, 2, '2026-06-06', 'Jogging', 30, 5.00, 426, 'Muantap', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Firdam-2026-06-06-Jogging_PGSFjn1ka.jpg', '6a2358b25c7cd75eb8c49d00', '2026-06-06 06:16:02.70941', '6''00"/km', NULL, NULL, NULL),
	(20, 16, '2026-06-11', 'Jogging', 41, 6.45, 0, 'Menuju HM', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/ADITH_SETIAWAN-2026-06-11-Jogging_aT0bOtfU0.png', '6a29f8715c7cd75eb8a4767c', '2026-06-11 06:51:14.247209', '6''27/KM', NULL, NULL, NULL),
	(21, 3, '2026-06-11', 'Jogging', 40, 5.00, 0, '', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Rifat-2026-06-11-Jogging_v7f4N49IO.jpg', '6a29fed65c7cd75eb8d5391f', '2026-06-11 07:18:31.010826', '7''00"/km', NULL, NULL, NULL),
	(22, 4, '2026-06-11', 'Jogging', 20, 3.00, 0, '', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Dani-2026-06-11-Jogging_Y4UGyMu79.jpg', '6a2a77d55c7cd75eb8aadf08', '2026-06-11 15:54:45.773464', '7''30"/km', NULL, NULL, NULL),
	(23, 3, '2026-06-12', 'Jogging', 30, 5.00, 0, 'rute tritan poin', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Rifat-2026-06-12-Jogging_k67Rb8kUB.jpg', '6a2b4e5d5c7cd75eb81e2394', '2026-06-12 07:10:06.234402', '6''30"/km', NULL, NULL, NULL),
	(24, 2, '2026-06-13', 'Jogging', 17, 2.54, 206, 'Asa te karaos', 'https://ik.imagekit.io/ahsansur/sportapp/June_2026/Firdam-2026-06-13-Jogging_ZlJs3FaF5.jpg', '6a2c9faa5c7cd75eb85ea342', '2026-06-13 07:09:14.779208', '7''00"/km', NULL, NULL, NULL);
/*!40000 ALTER TABLE "upload_harian" ENABLE KEYS */;

-- Dumping structure for table public.upload_harian_comments
CREATE TABLE IF NOT EXISTS "upload_harian_comments" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''upload_harian_comments_id_seq''::regclass)',
	"upload_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"isi" TEXT NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	INDEX "uhc_upload_idx" ("upload_id")
);

-- Dumping data for table public.upload_harian_comments: -1 rows
/*!40000 ALTER TABLE "upload_harian_comments" DISABLE KEYS */;
INSERT INTO "upload_harian_comments" ("id", "upload_id", "user_id", "isi", "created_at") VALUES
	(1, 22, 2, 'Muantap', '2026-06-11 18:05:21.780775'),
	(2, 24, 2, 'oke', '2026-06-13 21:01:59.054374');
/*!40000 ALTER TABLE "upload_harian_comments" ENABLE KEYS */;

-- Dumping structure for table public.upload_harian_likes
CREATE TABLE IF NOT EXISTS "upload_harian_likes" (
	"upload_id" INTEGER NOT NULL,
	"user_id" INTEGER NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("upload_id", "user_id")
);

-- Dumping data for table public.upload_harian_likes: -1 rows
/*!40000 ALTER TABLE "upload_harian_likes" DISABLE KEYS */;
INSERT INTO "upload_harian_likes" ("upload_id", "user_id", "created_at") VALUES
	(22, 2, '2026-06-11 18:04:52.486608'),
	(21, 2, '2026-06-11 18:05:02.193622'),
	(20, 2, '2026-06-11 18:05:10.459371'),
	(18, 2, '2026-06-12 05:00:21.837678'),
	(23, 2, '2026-06-13 07:11:14.285927'),
	(24, 2, '2026-06-13 21:06:54.460959');
/*!40000 ALTER TABLE "upload_harian_likes" ENABLE KEYS */;

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
	"tema_warna" VARCHAR(20) NULL DEFAULT 'sky',
	"privasi_disetujui_at" TIMESTAMP NULL DEFAULT NULL,
	"privasi_versi_disetujui" VARCHAR(20) NULL DEFAULT NULL,
	"pic_user_id" INTEGER NULL DEFAULT NULL,
	"koordinator_id" INTEGER NULL DEFAULT NULL,
	"aktif" SMALLINT NOT NULL DEFAULT '1',
	"nonaktif_catatan" TEXT NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "users_email_key" ("email"),
	UNIQUE INDEX "users_kode_referal_uidx" ("kode_referal"),
	CONSTRAINT "users_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "users_pic_admin_id_fkey" FOREIGN KEY ("pic_admin_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "users_pic_user_id_fkey" FOREIGN KEY ("pic_user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.users: 17 rows
/*!40000 ALTER TABLE "users" DISABLE KEYS */;
INSERT INTO "users" ("id", "nama", "email", "password_hash", "role", "google_id", "created_at", "foto_url", "foto_file_id", "last_seen", "jenis_kelamin", "xp", "level", "streak_minggu", "bio", "dark_mode", "wa", "pic_admin_id", "nomor_wa", "berat_kg", "tinggi_cm", "tanggal_lahir", "riwayat_penyakit", "kode_referal", "referred_by_code", "username", "tema_warna", "privasi_disetujui_at", "privasi_versi_disetujui", "pic_user_id", "koordinator_id", "aktif", "nonaktif_catatan") VALUES
	(13, 'Aziz', 'aziz@sport.local', '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', NULL, '2026-05-19 07:56:12.862165', NULL, NULL, '2026-06-08 14:21:47.13626', 'L', 0, 1, 0, NULL, 0, '081223450704', 2, '081223450704', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(2, 'Firdam', 'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Firdam-avatar-1779423163_TlgPp4MS-.jpg', '6a0ee0135c7cd75eb87edbaf', '2026-06-15 12:39:47.879755', 'L', 300, 2, 0, 'Mau yang mana?', 0, '081386369207', 2, '081386369207', 83.00, 170.00, '1996-03-11', 'Usus Buntu', NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(15, 'Hanif', 'hanif@sport.local', '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', NULL, '2026-05-19 07:56:40.664031', NULL, NULL, '2026-05-30 09:59:44.711128', 'L', 0, 1, 0, NULL, 0, '082117100115', 2, '082117100115', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(16, 'ADITH SETIAWAN', 'adithsetiawan62@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$NzkuSWtnU0J1UjFTcGV4Ug$3kOfbqXaVv19r43a8KDxVPg33BbgV/AkqZ7Gt6oY9u8', 'member', NULL, '2026-05-22 09:25:05.526258', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/ADITH_SETIAWAN-avatar-1780045646_vUCRcMgf9.jpg', NULL, '2026-06-14 09:15:36.733988', 'L', 0, 1, 2, 'Enjoy the Proses', 0, '082118785024', NULL, '082118785024', 66.00, 160.00, '2006-03-12', 'Sehat sentosa', NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(17, 'RIZAL SAAD', 'rizalsaad1405@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$dWZVNkNuMDFRbUxEbTdUbQ$FymiSUHfBJnWIII+P5DJeMVHC7cH5YbosxTQNxhFqUw', 'member', NULL, '2026-05-22 09:25:26.79199', NULL, NULL, '2026-05-22 09:26:42.829588', 'L', 0, 1, 0, NULL, 0, '082218532348', 4, '082218532348', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(20, 'Fajar Suseno', 'fajar@sport.local', '$2y$10$PCnvpCyKEdEapN87UMqQHOh7edoaNTepREZPpBljj5sHgdp68uUbi', 'member', NULL, '2026-05-29 12:57:35.205148', NULL, NULL, '2026-05-29 16:08:52.600659', 'L', 0, 1, 0, NULL, 0, '087822615464', NULL, '087822615464', 67.00, 168.00, '2005-06-19', 'Tidak ada.', NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(21, 'Fawaid', 'fawaid@sport.local', '$2y$10$WRVlvftweEcCk1Qv4W4r0.swunLUfBb3NdRc0Y.kwlx7i6MkNUH7q', 'member', NULL, '2026-06-03 22:39:30.317356', NULL, NULL, '2026-06-10 21:38:35.98882', 'L', 0, 1, 0, NULL, 0, '085177010166', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(10, 'Reyhan', 'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', NULL, '2026-05-19 07:55:29.376846', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '082320781890', NULL, '082320781890', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 0, NULL),
	(14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'admin', NULL, '2026-05-19 07:56:28.908609', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Farhan_Akmali-avatar-1779482008_KIqU_LMhc.jpg', NULL, '2026-06-12 05:24:38.294415', 'L', 150, 1, 1, NULL, 0, '087854972839', 2, '087854972839', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(9, 'Rafi', 'rafi@sport.local', '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', NULL, '2026-05-19 07:55:12.485671', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '089502639933', NULL, '089502639933', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 0, NULL),
	(11, 'Rian', 'rian@sport.local', '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', NULL, '2026-05-19 07:55:42.436033', NULL, NULL, '2026-06-13 05:34:02.63025', 'L', 0, 1, 0, NULL, 0, '085691767966', NULL, '085691767966', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(8, 'Dedi', 'dedi@sport.local', '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', NULL, '2026-05-19 07:55:00.498075', NULL, NULL, '2026-05-23 17:11:43.514279', 'L', 150, 1, 0, NULL, 0, '082184381823', NULL, '082184381823', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(7, 'Faiz', 'faiz@sport.local', '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', NULL, '2026-05-19 07:54:49.054143', NULL, NULL, '2026-06-11 11:39:47.840675', 'L', 0, 1, 2, NULL, 0, '085814120846', NULL, '085814120846', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(5, 'Usama', 'usama@sport.local', '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', NULL, '2026-05-19 07:54:22.015654', NULL, NULL, NULL, 'L', 0, 1, 0, NULL, 0, '089525429272', NULL, '089525429272', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(6, 'Dendra', 'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', NULL, '2026-05-19 07:54:35.123756', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Dendra-avatar-1780327141_1LN6n1f2i.jpg', NULL, '2026-06-01 22:26:59.76998', 'L', 0, 1, 1, NULL, 0, '082316481216', NULL, '082316481216', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(3, 'Rifat', 'rifat@sport.local', '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'member', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Rifat-avatar-1779378411_1K68zsR1h.jpg', '6a0f28ed5c7cd75eb84a1dad', '2026-06-14 09:21:44.353376', 'L', 300, 2, 2, '', 0, '081369248630', NULL, '081369248630', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL),
	(4, 'Dani', 'dani@sport.local', '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'member', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Dani-avatar-1779446202_D6MgJZEDkC.jpg', NULL, '2026-06-15 07:47:55.275328', 'L', 300, 2, 0, NULL, 0, '0895337148803', NULL, '0895337148803', 58.00, 163.00, '2004-10-09', 'Darah Tinggi', NULL, NULL, NULL, 'sky', NULL, NULL, NULL, NULL, 1, NULL);
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
	(4, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 00:23:08.064265'),
	(3, 0, 1, 'Jakarta', 'Indonesia', '2026-05-24 15:35:27.781673'),
	(2, 1, 0, 'Bandung', 'Indonesia', '2026-05-26 11:08:57.379774'),
	(16, 0, 1, 'Jakarta', 'Indonesia', '2026-05-26 11:38:46.965837'),
	(20, 0, 1, 'Jakarta', 'Indonesia', '2026-05-29 13:02:26.848779'),
	(6, 0, 1, 'Jakarta', 'Indonesia', '2026-05-29 13:58:33.835718'),
	(14, 0, 1, 'Bandung', 'Indonesia', '2026-05-30 07:15:07.73775'),
	(15, 0, 1, 'Jakarta', 'Indonesia', '2026-05-30 09:55:49.225629'),
	(11, 0, 1, 'Jakarta', 'Indonesia', '2026-06-02 17:43:29.415'),
	(21, 0, 1, 'Jakarta', 'Indonesia', '2026-06-06 09:52:14.563982'),
	(13, 0, 1, 'Jakarta', 'Indonesia', '2026-06-08 14:21:12.625834'),
	(7, 0, 1, 'Jakarta', 'Indonesia', '2026-06-10 13:29:41.510151');
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
	(5, 2, 2, 'Badminton', 'Sepatu', 1, 'Biasa dipakai futsal', '2026-05-23 07:23:55.738743'),
	(6, 4, 2, 'Badminton', 'Raket', 2, NULL, '2026-05-24 14:01:06.155221'),
	(7, 2, 8, 'Hiking', 'Sepatu', 1, NULL, '2026-05-30 10:35:04.124359'),
	(8, 2, 8, 'Hiking', 'Sepatu', 1, 'Merk Treksta', '2026-05-30 10:36:35.730992'),
	(10, 2, 13, 'Olahraga Pribadi', 'Hand Gripper', 2, 'Buat Latihan Jari', '2026-06-03 05:37:34.080804'),
	(11, 2, 13, 'Olahraga Pribadi', 'Skipping', 1, 'Loncat Loncat', '2026-06-03 05:38:06.063242'),
	(13, 2, 8, 'Hiking', 'Tas Camping/Hiking Hijau', 1, 'Merk Ospray', '2026-06-03 05:46:17.051677'),
	(12, 2, 3, 'Futsal', 'Sepatu', 1, 'Merk Ortus Eight', '2026-06-03 05:45:49.553352'),
	(4, 2, 5, 'Renang', 'Kacamata', 2, 'Pribadi dan anak', '2026-05-23 07:23:16.66464'),
	(14, 3, 2, 'Badminton', 'Raket', 3, '1 dalam perbaikan', '2026-06-03 05:59:04.018762'),
	(15, 3, 3, 'Futsal', 'sepatu aicec', 1, NULL, '2026-06-03 06:00:32.477591'),
	(16, 3, NULL, 'jogging', 'sepatu', 2, 'bisa dipake untuk olahraga yang lain', '2026-06-03 06:01:06.830129'),
	(17, 3, 13, 'Olahraga Pribadi', 'hand grip', 1, NULL, '2026-06-03 06:02:38.324866'),
	(18, 3, 13, 'Olahraga Pribadi', 'pull up bar', 1, NULL, '2026-06-03 06:03:11.12582'),
	(19, 3, 13, 'Olahraga Pribadi', 'calisthenics bar', 1, NULL, '2026-06-03 06:03:39.157228');
/*!40000 ALTER TABLE "user_perlengkapan" ENABLE KEYS */;

-- Dumping structure for table public.user_strava
CREATE TABLE IF NOT EXISTS "user_strava" (
	"user_id" INTEGER NOT NULL,
	"athlete_id" BIGINT NULL DEFAULT NULL,
	"access_token" TEXT NOT NULL,
	"refresh_token" TEXT NOT NULL,
	"expires_at" TIMESTAMP NOT NULL,
	"connected_at" TIMESTAMP NOT NULL DEFAULT 'now()',
	PRIMARY KEY ("user_id"),
	CONSTRAINT "user_strava_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.user_strava: -1 rows
/*!40000 ALTER TABLE "user_strava" DISABLE KEYS */;
/*!40000 ALTER TABLE "user_strava" ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

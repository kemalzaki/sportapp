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
	PRIMARY KEY ("id"),
	UNIQUE INDEX "absensi_jadwal_id_user_id_key" ("jadwal_id", "user_id"),
	CONSTRAINT "absensi_jadwal_id_fkey" FOREIGN KEY ("jadwal_id") REFERENCES "jadwal" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "absensi_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT "absensi_hadir_check" CHECK ((hadir = ANY (ARRAY[0, 1])))
);

-- Dumping data for table public.absensi: 75 rows
/*!40000 ALTER TABLE "absensi" DISABLE KEYS */;
REPLACE INTO "absensi" ("id", "jadwal_id", "user_id", "hadir") VALUES
	(1, 1, 12, 0),
	(2, 1, 1, 0),
	(3, 1, 13, 0),
	(4, 1, 4, 1),
	(5, 1, 8, 1),
	(6, 1, 6, 0),
	(7, 1, 7, 0),
	(8, 1, 14, 0),
	(9, 1, 2, 1),
	(10, 1, 15, 0),
	(11, 1, 9, 0),
	(12, 1, 10, 0),
	(13, 1, 11, 0),
	(14, 1, 3, 1),
	(15, 1, 5, 0),
	(16, 2, 12, 0),
	(17, 2, 1, 0),
	(18, 2, 13, 0),
	(19, 2, 4, 1),
	(20, 2, 8, 0),
	(21, 2, 6, 1),
	(22, 2, 7, 0),
	(23, 2, 14, 0),
	(24, 2, 2, 1),
	(25, 2, 15, 0),
	(26, 2, 9, 0),
	(27, 2, 10, 0),
	(28, 2, 11, 0),
	(29, 2, 3, 1),
	(30, 2, 5, 1),
	(31, 3, 12, 0),
	(32, 3, 1, 0),
	(33, 3, 13, 0),
	(34, 3, 4, 1),
	(35, 3, 8, 1),
	(36, 3, 6, 0),
	(37, 3, 7, 0),
	(38, 3, 14, 1),
	(39, 3, 2, 1),
	(40, 3, 15, 1),
	(41, 3, 9, 0),
	(42, 3, 10, 1),
	(43, 3, 11, 1),
	(44, 3, 3, 1),
	(45, 3, 5, 1),
	(61, 5, 12, 1),
	(62, 5, 1, 0),
	(63, 5, 13, 1),
	(64, 5, 4, 1),
	(65, 5, 8, 0),
	(66, 5, 6, 0),
	(67, 5, 7, 1),
	(68, 5, 14, 1),
	(69, 5, 2, 1),
	(70, 5, 15, 1),
	(71, 5, 9, 0),
	(72, 5, 10, 0),
	(73, 5, 11, 1),
	(74, 5, 3, 1),
	(75, 5, 5, 0),
	(91, 4, 12, 0),
	(92, 4, 1, 0),
	(93, 4, 13, 1),
	(94, 4, 4, 1),
	(95, 4, 8, 1),
	(96, 4, 6, 0),
	(97, 4, 7, 0),
	(98, 4, 14, 1),
	(99, 4, 2, 1),
	(100, 4, 15, 0),
	(101, 4, 9, 0),
	(102, 4, 10, 0),
	(103, 4, 11, 0),
	(104, 4, 3, 1),
	(105, 4, 5, 1);
/*!40000 ALTER TABLE "absensi" ENABLE KEYS */;

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

-- Dumping structure for table public.chat_forum
CREATE TABLE IF NOT EXISTS "chat_forum" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''chat_forum_id_seq''::regclass)',
	"user_id" INTEGER NULL DEFAULT NULL,
	"pesan" TEXT NOT NULL,
	"created_at" TIMESTAMP NULL DEFAULT 'now()',
	PRIMARY KEY ("id"),
	CONSTRAINT "chat_forum_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.chat_forum: -1 rows
/*!40000 ALTER TABLE "chat_forum" DISABLE KEYS */;
REPLACE INTO "chat_forum" ("id", "user_id", "pesan", "created_at") VALUES
	(1, 2, 'Assalamualaikum', '2026-05-21 10:33:39.752133'),
	(2, 2, 'Assalamualaikum', '2026-05-21 10:35:06.060555'),
	(3, 2, 'Assalamualaikum lagi, ada yang online?', '2026-05-21 11:41:53.489748'),
	(4, 3, 'wa''alikumussalam', '2026-05-21 15:47:46.367728');
/*!40000 ALTER TABLE "chat_forum" ENABLE KEYS */;

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
	PRIMARY KEY ("id"),
	CONSTRAINT "jadwal_koordinator_id_fkey" FOREIGN KEY ("koordinator_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE SET NULL,
	CONSTRAINT "jadwal_tempat_id_fkey" FOREIGN KEY ("tempat_id") REFERENCES "tempat" ("id") ON UPDATE NO ACTION ON DELETE SET NULL
);

-- Dumping data for table public.jadwal: -1 rows
/*!40000 ALTER TABLE "jadwal" DISABLE KEYS */;
REPLACE INTO "jadwal" ("id", "tanggal", "bulan", "minggu_ke", "jenis", "tempat", "koordinator_id", "konten_obrolan", "catatan", "created_at", "tempat_id", "durasi_menit") VALUES
	(2, '2026-04-22', 'April', 'W4', 'Badminton', 'GOR Mayasari', 3, 'Tidak Ada', 'Tidak Ada', '2026-05-19 07:51:01.708229', NULL, NULL),
	(3, '2026-05-03', 'May', 'W1', 'Jogging', 'Summarecon', 3, 'Sharing Hikmah Per Orang', '1. Dedi Jalan dari Kosan ke Summarecon 2. Dedi Cedera kaki', '2026-05-19 07:51:58.579444', NULL, NULL),
	(4, '2026-05-09', 'May', 'W2', 'Futsal', 'GOR Adiguna', 3, 'Tidak Ada', '1. Dedi Jalan dari Kosan ke Summarecon', '2026-05-19 07:52:37.974739', NULL, NULL),
	(5, '2026-05-17', 'May', 'W3', 'Badminton', 'GOR Purbaya', 4, 'Sharing Hikmah Per Orang', '1. Rafi (sakit) 2. Rizal (Rihlah bersama adik Mentornya) 3. Fajar S (Part time)', '2026-05-19 07:53:14.399509', NULL, NULL),
	(1, '2026-04-16', 'April', 'W3', 'Jogging', 'SR-Panyileukan', 2, '-', '1. Dedi ada bimbingan skripsi, jadi pulang 2. Dani sama Rifat ada Kuliah Online', '2026-05-19 07:50:23.02801', NULL, NULL),
	(6, '2026-05-23', 'May', 'W4', 'Badminton', 'GOR Purbaya', 3, '<p><br></p>', '<p><br></p>', '2026-05-21 15:45:32.456543', 3, 120);
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
	(2, 4, 'Zacky Arido', 4),
	(3, 4, 'Kean', 4);
/*!40000 ALTER TABLE "member_eksternal" ENABLE KEYS */;

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
	PRIMARY KEY ("id")
);

-- Dumping data for table public.tempat: -1 rows
/*!40000 ALTER TABLE "tempat" DISABLE KEYS */;
REPLACE INTO "tempat" ("id", "nama", "alamat", "harga_lapang", "harga_per_jam", "status_booking", "catatan", "created_at") VALUES
	(1, 'GOR Adiguna', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715'),
	(2, 'GOR Mayasari', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715'),
	(4, 'SR-Panyileukan', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715'),
	(5, 'Summarecon', '-', 0.00, 0.00, 'tersedia', NULL, '2026-05-21 11:16:55.600715'),
	(3, 'GOR Purbaya', 'Jln. Ciguruwik', 25000.00, 25000.00, 'tersedia', '', '2026-05-21 11:16:55.600715'),
	(6, 'GOR Azaka', '-', 0.00, 0.00, 'tersedia', '0', '2026-05-21 11:29:20.544026');
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
	PRIMARY KEY ("id"),
	CONSTRAINT "upload_harian_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Dumping data for table public.upload_harian: -1 rows
/*!40000 ALTER TABLE "upload_harian" DISABLE KEYS */;
REPLACE INTO "upload_harian" ("id", "user_id", "tanggal", "jenis", "durasi_menit", "jarak_km", "kalori", "deskripsi", "file_path", "gdrive_url", "created_at", "pace") VALUES
	(1, 1, '2026-05-19', 'Jogging', 12, 12.00, 12, 'tes', '/uploads/May_2026/Administrator-2026-05-19-Jogging.png', NULL, '2026-05-19 08:21:41.021259', NULL),
	(5, 1, '2026-05-21', 'Jogging', 60, 2.00, 2, '', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Administrator-2026-05-21-Jogging_8PMuV8B1C.jpg', '6a0e899a5c7cd75eb803caee', '2026-05-21 04:27:07.596483', NULL),
	(8, 2, '2026-05-15', 'Jogging', 13, 2.40, 187, 'Wow..', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg', '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34.100156', NULL),
	(7, 2, '2026-05-18', 'Jogging', 15, 2.26, 198, 'Tidak ada', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-21-Jogging_PGVG98kLK.jpg', '6a0e92aa5c7cd75eb83a10c4', '2026-05-21 05:05:47.220034', '6');
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
	PRIMARY KEY ("id"),
	UNIQUE INDEX "users_email_key" ("email")
);

-- Dumping data for table public.users: 15 rows
/*!40000 ALTER TABLE "users" DISABLE KEYS */;
REPLACE INTO "users" ("id", "nama", "email", "password_hash", "role", "google_id", "created_at", "foto_url", "foto_file_id", "last_seen", "jenis_kelamin") VALUES
	(4, 'Dani', 'dani@sport.local', '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'admin', NULL, '2026-05-19 07:09:24.276208', NULL, NULL, NULL, 'L'),
	(12, 'Adith', 'adith@sport.local', '$2y$10$lrFgpD0ArMaHOpbvma/B9ebuuHjL6QffUVMD.D1kUfBp3RX1O2Xse', 'member', NULL, '2026-05-19 07:55:54.185236', NULL, NULL, NULL, 'L'),
	(13, 'Aziz', 'aziz@sport.local', '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', NULL, '2026-05-19 07:56:12.862165', NULL, NULL, NULL, 'L'),
	(1, 'Administrator', 'admin@sport.local', '$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa', 'admin', NULL, '2026-05-19 07:09:24.276208', NULL, NULL, NULL, 'L'),
	(8, 'Dedi', 'dedi@sport.local', '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', NULL, '2026-05-19 07:55:00.498075', NULL, NULL, NULL, 'L'),
	(6, 'Dendra', 'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', NULL, '2026-05-19 07:54:35.123756', NULL, NULL, NULL, 'L'),
	(7, 'Faiz', 'faiz@sport.local', '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', NULL, '2026-05-19 07:54:49.054143', NULL, NULL, NULL, 'L'),
	(14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'member', NULL, '2026-05-19 07:56:28.908609', NULL, NULL, NULL, 'L'),
	(3, 'Rifat', 'rifat@sport.local', '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Rifat-avatar-1779378411_1K68zsR1h.jpg', '6a0f28ed5c7cd75eb84a1dad', '2026-05-21 15:47:47.464521', 'L'),
	(15, 'Hanif', 'hanif@sport.local', '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', NULL, '2026-05-19 07:56:40.664031', NULL, NULL, NULL, 'L'),
	(2, 'Firdam', 'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin', NULL, '2026-05-19 07:09:24.276208', 'https://ik.imagekit.io/ahsansur/sportapp/avatar/Firdam-avatar-1779359762_loijDH3Ed.png', '6a0ee0135c7cd75eb87edbaf', '2026-05-21 22:39:10.440434', 'L'),
	(10, 'Reyhan', 'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', NULL, '2026-05-19 07:55:29.376846', NULL, NULL, NULL, 'L'),
	(9, 'Rafi', 'rafi@sport.local', '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', NULL, '2026-05-19 07:55:12.485671', NULL, NULL, NULL, 'L'),
	(11, 'Rian', 'rian@sport.local', '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', NULL, '2026-05-19 07:55:42.436033', NULL, NULL, NULL, 'L'),
	(5, 'Usama', 'usama@sport.local', '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', NULL, '2026-05-19 07:54:22.015654', NULL, NULL, NULL, 'L');
/*!40000 ALTER TABLE "users" ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

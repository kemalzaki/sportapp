-- ============================================================
-- SportApp v2 — PostgreSQL schema + seed data
-- Kompatibel PostgreSQL 13+ (idempotent, aman dijalankan ulang)
-- ============================================================

SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;

-- ------------------------------------------------------------
-- TABLE: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    nama          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'member',
    google_id     VARCHAR(120),
    foto_url      VARCHAR(255),
    foto_file_id  VARCHAR(120),
    last_seen     TIMESTAMP,
    created_at    TIMESTAMP DEFAULT now()
);

-- migrasi kolom (kalau tabel sudah ada dari versi lama)
ALTER TABLE users ADD COLUMN IF NOT EXISTS foto_url      VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS foto_file_id  VARCHAR(120);
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen     TIMESTAMP;

-- ------------------------------------------------------------
-- TABLE: jenis_olahraga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jenis_olahraga (
    id         SERIAL PRIMARY KEY,
    nama       VARCHAR(60) NOT NULL UNIQUE,
    deskripsi  TEXT,
    created_at TIMESTAMP DEFAULT now()
);

-- ------------------------------------------------------------
-- TABLE: jadwal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jadwal (
    id              SERIAL PRIMARY KEY,
    tanggal         DATE NOT NULL,
    bulan           VARCHAR(20) NOT NULL,
    minggu_ke       VARCHAR(4)  NOT NULL,
    jenis           VARCHAR(60) NOT NULL,
    tempat          VARCHAR(180) NOT NULL,
    koordinator_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    konten_obrolan  TEXT,
    catatan         TEXT,
    created_at      TIMESTAMP DEFAULT now()
);

-- ------------------------------------------------------------
-- TABLE: absensi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS absensi (
    id        SERIAL PRIMARY KEY,
    jadwal_id INTEGER NOT NULL REFERENCES jadwal(id) ON DELETE CASCADE,
    user_id   INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    hadir     SMALLINT NOT NULL DEFAULT 0 CHECK (hadir IN (0,1)),
    CONSTRAINT absensi_jadwal_user_unique UNIQUE (jadwal_id, user_id)
);

-- ------------------------------------------------------------
-- TABLE: member_eksternal
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS member_eksternal (
    id              SERIAL PRIMARY KEY,
    jadwal_id       INTEGER NOT NULL REFERENCES jadwal(id) ON DELETE CASCADE,
    nama_tamu       VARCHAR(120) NOT NULL,
    dibawa_oleh_id  INTEGER REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- TABLE: upload_harian
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS upload_harian (
    id            SERIAL PRIMARY KEY,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tanggal       DATE NOT NULL,
    jenis         VARCHAR(60) NOT NULL,
    durasi_menit  INTEGER,
    jarak_km      NUMERIC(6,2),
    kalori        INTEGER,
    pace          VARCHAR(20),
    deskripsi     TEXT,
    file_path     VARCHAR(255),
    gdrive_url    VARCHAR(255),
    created_at    TIMESTAMP DEFAULT now()
);
ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS pace VARCHAR(20);

-- ------------------------------------------------------------
-- TABLE: chat_forum (fitur forum di Beranda)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_forum (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER REFERENCES users(id) ON DELETE CASCADE,
    pesan      TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT now()
);

-- ------------------------------------------------------------
-- TABLE: berita (slider berita di Beranda, CRUD admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS berita (
    id             SERIAL PRIMARY KEY,
    judul          VARCHAR(200) NOT NULL,
    isi            TEXT,
    gambar_url     VARCHAR(255),
    gambar_file_id VARCHAR(120),
    created_at     TIMESTAMP DEFAULT now()
);

-- ============================================================
-- SEED DATA (idempotent: pakai ON CONFLICT DO NOTHING)
-- ============================================================

-- users
INSERT INTO users (id, nama, email, password_hash, role, created_at) VALUES
 (1,  'Administrator', 'admin@sport.local',  '$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa', 'admin',  '2026-05-19 07:09:24'),
 (2,  'Firdam',        'firdam@sport.local', '$2y$10$J219qLjtcMqVaSla3vEmsuaOMwxaL7XVJ4Xpnc7VQl8TJKBNMDv0m', 'admin',  '2026-05-19 07:09:24'),
 (3,  'Rifat',         'rifat@sport.local',  '$2y$10$2nAaw2Qjru8mkOrZMA5Bcu2nX7ulxiqPObQk1Ekp0VxBPTjowBrNW', 'admin',  '2026-05-19 07:09:24'),
 (4,  'Dani',          'dani@sport.local',   '$2y$10$VgQ6RZkSly9XqDDlNH0B8e/VTM.GB.3nDyxY6O4nyA2HtTOD8MOi2', 'admin',  '2026-05-19 07:09:24'),
 (5,  'Usama',         'usama@sport.local',  '$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2', 'member', '2026-05-19 07:54:22'),
 (6,  'Dendra',        'dendra@sport.local', '$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y', 'member', '2026-05-19 07:54:35'),
 (7,  'Faiz',          'faiz@sport.local',   '$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2', 'member', '2026-05-19 07:54:49'),
 (8,  'Dedi',          'dedi@sport.local',   '$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q', 'member', '2026-05-19 07:55:00'),
 (9,  'Rafi',          'rafi@sport.local',   '$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y', 'member', '2026-05-19 07:55:12'),
 (10, 'Reyhan',        'reyhan@sport.local', '$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni', 'member', '2026-05-19 07:55:29'),
 (11, 'Rian',          'rian@sport.local',   '$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS', 'member', '2026-05-19 07:55:42'),
 (12, 'Adith',         'adith@sport.local',  '$2y$10$lrFgpD0ArMaHOpbvma/B9ebuuHjL6QffUVMD.D1kUfBp3RX1O2Xse', 'member', '2026-05-19 07:55:54'),
 (13, 'Aziz',          'aziz@sport.local',   '$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO', 'member', '2026-05-19 07:56:12'),
 (14, 'Farhan Akmali', 'farhan@sport.local', '$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W', 'member', '2026-05-19 07:56:28'),
 (15, 'Hanif',         'hanif@sport.local',  '$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK', 'member', '2026-05-19 07:56:40')
ON CONFLICT (id) DO NOTHING;

-- jenis_olahraga
INSERT INTO jenis_olahraga (id, nama, deskripsi) VALUES
 (1, 'Jogging',   'Lari santai outdoor, fokus pada durasi dan jarak.'),
 (2, 'Badminton', 'Pertandingan ganda/tunggal di GOR.'),
 (3, 'Futsal',    'Sepak bola dalam ruangan, 5 vs 5.'),
 (4, 'Senam',     'Senam kebugaran bersama.'),
 (5, 'Renang',    'Renang gaya bebas atau dada.'),
 (6, 'Lainnya',   'Jenis olahraga lain yang belum terdaftar.')
ON CONFLICT (id) DO NOTHING;

-- jadwal
INSERT INTO jadwal (id, tanggal, bulan, minggu_ke, jenis, tempat, koordinator_id, konten_obrolan, catatan, created_at) VALUES
 (1, '2026-04-16', 'April', 'W3', 'Jogging',   'SR-Panyileukan', 2, '-',                          '1. Dedi ada bimbingan skripsi, jadi pulang 2. Dani sama Rifat ada Kuliah Online', '2026-05-19 07:50:23'),
 (2, '2026-04-22', 'April', 'W4', 'Badminton', 'GOR Mayasari',   3, 'Tidak Ada',                  'Tidak Ada',                                                                       '2026-05-19 07:51:01'),
 (3, '2026-05-03', 'May',   'W1', 'Jogging',   'Summarecon',     3, 'Sharing Hikmah Per Orang',   '1. Dedi Jalan dari Kosan ke Summarecon 2. Dedi Cedera kaki',                      '2026-05-19 07:51:58'),
 (4, '2026-05-09', 'May',   'W2', 'Futsal',    'GOR Adiguna',    3, 'Tidak Ada',                  '1. Dedi Jalan dari Kosan ke Summarecon',                                          '2026-05-19 07:52:37'),
 (5, '2026-05-17', 'May',   'W3', 'Badminton', 'GOR Purbaya',    4, 'Sharing Hikmah Per Orang',   '1. Rafi (sakit) 2. Rizal (Rihlah bersama adik Mentornya) 3. Fajar S (Part time)', '2026-05-19 07:53:14')
ON CONFLICT (id) DO NOTHING;

-- absensi
INSERT INTO absensi (id, jadwal_id, user_id, hadir) VALUES
 (1,1,12,0),(2,1,1,0),(3,1,13,0),(4,1,4,1),(5,1,8,1),(6,1,6,0),(7,1,7,0),(8,1,14,0),(9,1,2,1),(10,1,15,0),
 (11,1,9,0),(12,1,10,0),(13,1,11,0),(14,1,3,1),(15,1,5,0),
 (16,2,12,0),(17,2,1,0),(18,2,13,0),(19,2,4,1),(20,2,8,0),(21,2,6,1),(22,2,7,0),(23,2,14,0),(24,2,2,1),(25,2,15,0),
 (26,2,9,0),(27,2,10,0),(28,2,11,0),(29,2,3,1),(30,2,5,1),
 (31,3,12,0),(32,3,1,0),(33,3,13,0),(34,3,4,1),(35,3,8,1),(36,3,6,0),(37,3,7,0),(38,3,14,1),(39,3,2,1),(40,3,15,1),
 (41,3,9,0),(42,3,10,1),(43,3,11,1),(44,3,3,1),(45,3,5,1),
 (61,5,12,1),(62,5,1,0),(63,5,13,1),(64,5,4,1),(65,5,8,0),(66,5,6,0),(67,5,7,1),(68,5,14,1),(69,5,2,1),(70,5,15,1),
 (71,5,9,0),(72,5,10,0),(73,5,11,1),(74,5,3,1),(75,5,5,0),
 (91,4,12,0),(92,4,1,0),(93,4,13,1),(94,4,4,1),(95,4,8,1),(96,4,6,0),(97,4,7,0),(98,4,14,1),(99,4,2,1),(100,4,15,0),
 (101,4,9,0),(102,4,10,0),(103,4,11,0),(104,4,3,1),(105,4,5,1)
ON CONFLICT (id) DO NOTHING;

-- member_eksternal
INSERT INTO member_eksternal (id, jadwal_id, nama_tamu, dibawa_oleh_id) VALUES
 (2, 4, 'Zacky Arido', 4),
 (3, 4, 'Kean',        4)
ON CONFLICT (id) DO NOTHING;

-- upload_harian
INSERT INTO upload_harian (id, user_id, tanggal, jenis, durasi_menit, jarak_km, kalori, deskripsi, file_path, gdrive_url, created_at) VALUES
 (1, 1, '2026-05-19', 'Jogging', 12, 12.00,  12,  'tes',       '/uploads/May_2026/Administrator-2026-05-19-Jogging.png',                                  NULL,                       '2026-05-19 08:21:41'),
 (5, 1, '2026-05-21', 'Jogging', 60,  2.00,   2,  '',          'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Administrator-2026-05-21-Jogging_8PMuV8B1C.jpg', '6a0e899a5c7cd75eb803caee', '2026-05-21 04:27:07'),
 (7, 2, '2026-05-18', 'Jogging', 15,  2.26, 198,  'Tidak ada', 'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-21-Jogging_PGVG98kLK.jpg',        '6a0e92aa5c7cd75eb83a10c4', '2026-05-21 05:05:47'),
 (8, 2, '2026-05-15', 'Jogging', 13,  2.40, 187,  'Wow..',     'https://ik.imagekit.io/ahsansur/sportapp/May_2026/Firdam-2026-05-15-Jogging_qFDGBnfHn.jpg',        '6a0e93155c7cd75eb83d74d0', '2026-05-21 05:07:34')
ON CONFLICT (id) DO NOTHING;

-- ------------------------------------------------------------
-- Sinkronkan sequence agar INSERT berikutnya tidak tabrakan id
-- ------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('users',            'id'), COALESCE((SELECT MAX(id) FROM users),            1), true);
SELECT setval(pg_get_serial_sequence('jenis_olahraga',   'id'), COALESCE((SELECT MAX(id) FROM jenis_olahraga),   1), true);
SELECT setval(pg_get_serial_sequence('jadwal',           'id'), COALESCE((SELECT MAX(id) FROM jadwal),           1), true);
SELECT setval(pg_get_serial_sequence('absensi',          'id'), COALESCE((SELECT MAX(id) FROM absensi),          1), true);
SELECT setval(pg_get_serial_sequence('member_eksternal', 'id'), COALESCE((SELECT MAX(id) FROM member_eksternal), 1), true);
SELECT setval(pg_get_serial_sequence('upload_harian',    'id'), COALESCE((SELECT MAX(id) FROM upload_harian),    1), true);

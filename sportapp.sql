-- =============================================================
-- HapFam SportApp — PostgreSQL schema + seed data
-- =============================================================
-- Cara import:
--   createdb sportapp
--   psql -d sportapp -f sportapp.sql
-- =============================================================

SET client_min_messages = WARNING;

DROP TABLE IF EXISTS absensi          CASCADE;
DROP TABLE IF EXISTS member_eksternal CASCADE;
DROP TABLE IF EXISTS upload_harian    CASCADE;
DROP TABLE IF EXISTS jadwal           CASCADE;
DROP TABLE IF EXISTS users            CASCADE;
DROP TABLE IF EXISTS jenis_olahraga   CASCADE;
DROP TYPE  IF EXISTS user_role        CASCADE;

CREATE TYPE user_role AS ENUM ('publik','member','admin');

-- -------- users --------
CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    nama          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          user_role    NOT NULL DEFAULT 'member',
    google_id     VARCHAR(120),
    created_at    TIMESTAMP DEFAULT now()
);

-- -------- jenis_olahraga (CRUD baru) --------
CREATE TABLE jenis_olahraga (
    id         SERIAL PRIMARY KEY,
    nama       VARCHAR(60) NOT NULL UNIQUE,
    deskripsi  TEXT,
    created_at TIMESTAMP DEFAULT now()
);

-- -------- jadwal --------
CREATE TABLE jadwal (
    id              SERIAL PRIMARY KEY,
    tanggal         DATE         NOT NULL,
    bulan           VARCHAR(20)  NOT NULL,
    minggu_ke       VARCHAR(4)   NOT NULL,
    jenis           VARCHAR(60)  NOT NULL,
    tempat          VARCHAR(180) NOT NULL,
    koordinator_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    konten_obrolan  TEXT,
    catatan         TEXT,
    created_at      TIMESTAMP DEFAULT now()
);

-- -------- absensi --------
CREATE TABLE absensi (
    id        SERIAL PRIMARY KEY,
    jadwal_id INTEGER NOT NULL REFERENCES jadwal(id) ON DELETE CASCADE,
    user_id   INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
    hadir     SMALLINT NOT NULL DEFAULT 0 CHECK (hadir IN (0,1)),
    UNIQUE (jadwal_id, user_id)
);

-- -------- member_eksternal --------
CREATE TABLE member_eksternal (
    id             SERIAL PRIMARY KEY,
    jadwal_id      INTEGER NOT NULL REFERENCES jadwal(id) ON DELETE CASCADE,
    nama_tamu      VARCHAR(120) NOT NULL,
    dibawa_oleh_id INTEGER REFERENCES users(id) ON DELETE SET NULL
);

-- -------- upload_harian --------
CREATE TABLE upload_harian (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tanggal      DATE NOT NULL,
    jenis        VARCHAR(60) NOT NULL,
    durasi_menit INTEGER,
    jarak_km     NUMERIC(6,2),
    kalori       INTEGER,
    deskripsi    TEXT,
    file_path    VARCHAR(255),
    gdrive_url   VARCHAR(255),
    created_at   TIMESTAMP DEFAULT now()
);

-- =============================================================
-- SEED DATA
-- =============================================================

INSERT INTO users (id, nama, email, password_hash, role, google_id, created_at) VALUES
 (1,'Administrator','admin@sport.local','$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa','admin',NULL,'2026-05-19 07:09:24.276208'),
 (2,'Firdam','firdam@sport.local','$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa','admin',NULL,'2026-05-19 07:09:24.276208'),
 (3,'Rifat','rifat@sport.local','$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa','member',NULL,'2026-05-19 07:09:24.276208'),
 (4,'Dani','dani@sport.local','$2b$10$S./KuLCK3WQWRfSaj5GA2.sjzuETYbywguoOZZuPr4M8bMU90ksEa','member',NULL,'2026-05-19 07:09:24.276208'),
 (5,'Usama','usama@sport.local','$2y$10$.t7NxThSxmHvK3Bst9NmguSIlu9zz2QjlaTxOnB6PvcSv71OsdWm2','member',NULL,'2026-05-19 07:54:22.015654'),
 (6,'Dendra','dendra@sport.local','$2y$10$6Xt5Sj9rKVSr9fqdXcF14.y/DP5240ULEtf/lie738rt1H5frLo/y','member',NULL,'2026-05-19 07:54:35.123756'),
 (7,'Faiz','faiz@sport.local','$2y$10$IU70GA7RajjzT1JaITB/0Oo3D7xTWI1OfuNs.U61Zh0q7GCGPs.o2','member',NULL,'2026-05-19 07:54:49.054143'),
 (8,'Dedi','dedi@sport.local','$2y$10$nuKddv8x8SvUhueELQwWv.F/F8YzaEOLA52T438WdLXMeLhZlee8q','member',NULL,'2026-05-19 07:55:00.498075'),
 (9,'Rafi','rafi@sport.local','$2y$10$WXVJ/JHsAzNkfEEz/ZAyOuioNuZj4iM5TVN4xRd1qkqqEanljth8y','member',NULL,'2026-05-19 07:55:12.485671'),
 (10,'Reyhan','reyhan@sport.local','$2y$10$84RpoOaWh9iDdj4eVoNgnuy3ycDWsYTpJnhKoCW3rd74cPepinhni','member',NULL,'2026-05-19 07:55:29.376846'),
 (11,'Rian','rian@sport.local','$2y$10$1i9pPdfgTNmnk.znbNW/O.RqmElHfaA0l/cnj3Lc98BUZto6kIVhS','member',NULL,'2026-05-19 07:55:42.436033'),
 (12,'Adith','adith@sport.local','$2y$10$lrFgpD0ArMaHOpbvma/B9ebuuHjL6QffUVMD.D1kUfBp3RX1O2Xse','member',NULL,'2026-05-19 07:55:54.185236'),
 (13,'Aziz','aziz@sport.local','$2y$10$hscxGGWZSkrUVdUi9GPuleeSCgD6HfEktM/SU4TzVT85LVuRsfcwO','member',NULL,'2026-05-19 07:56:12.862165'),
 (14,'Farhan Akmali','farhan@sport.local','$2y$10$FJBGlMFxj85cDACsi1G/BuyLCGZQQO1vq6j.RpXLGudAFayjKm76W','member',NULL,'2026-05-19 07:56:28.908609'),
 (15,'Hanif','hanif@sport.local','$2y$10$GnFSPJJ7.9X2BsmQ2ScrTOza76tmuZt1y8RFiX9QptHnZEFr4u8WK','member',NULL,'2026-05-19 07:56:40.664031');
SELECT setval(pg_get_serial_sequence('users','id'), (SELECT MAX(id) FROM users));

INSERT INTO jenis_olahraga (nama, deskripsi) VALUES
 ('Jogging','Lari santai outdoor, fokus pada durasi dan jarak.'),
 ('Badminton','Pertandingan ganda/tunggal di GOR.'),
 ('Futsal','Sepak bola dalam ruangan, 5 vs 5.'),
 ('Senam','Senam kebugaran bersama.'),
 ('Renang','Renang gaya bebas atau dada.'),
 ('Lainnya','Jenis olahraga lain yang belum terdaftar.');

INSERT INTO jadwal (id, tanggal, bulan, minggu_ke, jenis, tempat, koordinator_id, konten_obrolan, catatan, created_at) VALUES
 (1,'2026-04-16','April','W3','Jogging','SR-Panyileukan',2,'-','1. Dedi ada bimbingan skripsi, jadi pulang 2. Dani sama Rifat ada Kuliah Online','2026-05-19 07:50:23.02801'),
 (2,'2026-04-22','April','W4','Badminton','GOR Mayasari',3,'Tidak Ada','Tidak Ada','2026-05-19 07:51:01.708229'),
 (3,'2026-05-03','May','W1','Jogging','Summarecon',3,'Sharing Hikmah Per Orang','1. Dedi Jalan dari Kosan ke Summarecon 2. Dedi Cedera kaki','2026-05-19 07:51:58.579444'),
 (4,'2026-05-09','May','W2','Futsal','GOR Adiguna',3,'Tidak Ada','1. Dedi Jalan dari Kosan ke Summarecon','2026-05-19 07:52:37.974739'),
 (5,'2026-05-17','May','W3','Badminton','GOR Purbaya',4,'Sharing Hikmah Per Orang','1. Rafi (sakit) 2. Rizal (Rihlah bersama adik Mentornya) 3. Fajar S (Part time)','2026-05-19 07:53:14.399509');
SELECT setval(pg_get_serial_sequence('jadwal','id'), (SELECT MAX(id) FROM jadwal));

INSERT INTO absensi (id, jadwal_id, user_id, hadir) VALUES
 (1,1,12,0),(2,1,1,0),(3,1,13,0),(4,1,4,1),(5,1,8,1),(6,1,6,0),(7,1,7,0),(8,1,14,0),(9,1,2,1),(10,1,15,0),(11,1,9,0),(12,1,10,0),(13,1,11,0),(14,1,3,1),(15,1,5,0),
 (16,2,12,0),(17,2,1,0),(18,2,13,0),(19,2,4,1),(20,2,8,0),(21,2,6,1),(22,2,7,0),(23,2,14,0),(24,2,2,1),(25,2,15,0),(26,2,9,0),(27,2,10,0),(28,2,11,0),(29,2,3,1),(30,2,5,1),
 (31,3,12,0),(32,3,1,0),(33,3,13,0),(34,3,4,1),(35,3,8,1),(36,3,6,0),(37,3,7,0),(38,3,14,1),(39,3,2,1),(40,3,15,1),(41,3,9,0),(42,3,10,1),(43,3,11,1),(44,3,3,1),(45,3,5,1),
 (61,5,12,1),(62,5,1,0),(63,5,13,1),(64,5,4,1),(65,5,8,0),(66,5,6,0),(67,5,7,1),(68,5,14,1),(69,5,2,1),(70,5,15,1),(71,5,9,0),(72,5,10,0),(73,5,11,1),(74,5,3,1),(75,5,5,0),
 (91,4,12,0),(92,4,1,0),(93,4,13,1),(94,4,4,1),(95,4,8,1),(96,4,6,0),(97,4,7,0),(98,4,14,1),(99,4,2,1),(100,4,15,0),(101,4,9,0),(102,4,10,0),(103,4,11,0),(104,4,3,1),(105,4,5,1);
SELECT setval(pg_get_serial_sequence('absensi','id'), (SELECT MAX(id) FROM absensi));

INSERT INTO member_eksternal (id, jadwal_id, nama_tamu, dibawa_oleh_id) VALUES
 (2,4,'Zacky Arido',4),
 (3,4,'Kean',4);
SELECT setval(pg_get_serial_sequence('member_eksternal','id'), (SELECT MAX(id) FROM member_eksternal));

INSERT INTO upload_harian (id, user_id, tanggal, jenis, durasi_menit, jarak_km, kalori, deskripsi, file_path, gdrive_url, created_at) VALUES
 (1, 1, '2026-05-19', 'Jogging', 12, 12.00, 12, 'tes', '/uploads/May_2026/Administrator-2026-05-19-Jogging.png', NULL, '2026-05-19 08:21:41.021259');
SELECT setval(pg_get_serial_sequence('upload_harian','id'), (SELECT MAX(id) FROM upload_harian));

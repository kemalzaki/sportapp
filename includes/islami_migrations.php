<?php
/**
 * Migrasi tabel-tabel fitur Islami (idempotent, aman dijalankan berkali-kali).
 * Dipanggil otomatis saat halaman Islami / index dibuka.
 */
require_once __DIR__ . '/../config/db.php';

function islami_run_migrations(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sqls = [
        // CRUD kode referal (admin)
        "CREATE TABLE IF NOT EXISTS referal_codes (
            id SERIAL PRIMARY KEY,
            kode VARCHAR(32) NOT NULL UNIQUE,
            deskripsi TEXT,
            aktif SMALLINT NOT NULL DEFAULT 1,
            max_pakai INTEGER DEFAULT NULL,
            jumlah_terpakai INTEGER NOT NULL DEFAULT 0,
            dibuat_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL,
            expired_at DATE,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Preferensi widget per-user (close sapa per akun, kota sholat, mode tenang)
        "CREATE TABLE IF NOT EXISTS user_islami_pref (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            hide_sapa SMALLINT NOT NULL DEFAULT 0,
            mode_tenang SMALLINT NOT NULL DEFAULT 1,
            kota VARCHAR(60) NOT NULL DEFAULT 'Jakarta',
            negara VARCHAR(40) NOT NULL DEFAULT 'Indonesia',
            updated_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Bookmark ayat Qur'an
        "CREATE TABLE IF NOT EXISTS quran_bookmarks (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            surah INTEGER NOT NULL,
            ayat INTEGER NOT NULL,
            catatan TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT now(),
            UNIQUE (user_id, surah, ayat)
        )",
        // Last read Qur'an
        "CREATE TABLE IF NOT EXISTS quran_last_read (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            surah INTEGER NOT NULL,
            ayat INTEGER NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Daily streak ibadah (per tanggal)
        "CREATE TABLE IF NOT EXISTS islami_streak (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tanggal DATE NOT NULL,
            quran_done SMALLINT NOT NULL DEFAULT 0,
            dzikir_pagi SMALLINT NOT NULL DEFAULT 0,
            dzikir_petang SMALLINT NOT NULL DEFAULT 0,
            doa_done SMALLINT NOT NULL DEFAULT 0,
            sholat_count SMALLINT NOT NULL DEFAULT 0,
            subuh_walk SMALLINT NOT NULL DEFAULT 0,
            sedekah SMALLINT NOT NULL DEFAULT 0,
            poin INTEGER NOT NULL DEFAULT 0,
            UNIQUE(user_id, tanggal)
        )",
        // Badge islami
        "CREATE TABLE IF NOT EXISTS islami_badges (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            badge_key VARCHAR(40) NOT NULL,
            earned_at TIMESTAMP NOT NULL DEFAULT now(),
            UNIQUE(user_id, badge_key)
        )",
        // Feed quote islami komunitas
        "CREATE TABLE IF NOT EXISTS islami_quotes (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            isi TEXT NOT NULL,
            sumber VARCHAR(120),
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Kajian (kesehatan islami)
        "CREATE TABLE IF NOT EXISTS islami_kajian (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            judul VARCHAR(180) NOT NULL,
            isi TEXT,
            link_video VARCHAR(255),
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Artikel sunnah kesehatan
        "CREATE TABLE IF NOT EXISTS islami_artikel (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            judul VARCHAR(180) NOT NULL,
            isi TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Saling mendoakan antar member
        "CREATE TABLE IF NOT EXISTS doa_request (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            isi TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        "CREATE TABLE IF NOT EXISTS doa_aamiin (
            id SERIAL PRIMARY KEY,
            doa_id INTEGER NOT NULL REFERENCES doa_request(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_at TIMESTAMP NOT NULL DEFAULT now(),
            UNIQUE(doa_id, user_id)
        )",
        // Sedekah challenge komunitas + donasi masjid/event
        "CREATE TABLE IF NOT EXISTS sedekah_program (
            id SERIAL PRIMARY KEY,
            judul VARCHAR(180) NOT NULL,
            deskripsi TEXT,
            jenis VARCHAR(20) NOT NULL DEFAULT 'sedekah', -- sedekah | donasi
            target_amount BIGINT NOT NULL DEFAULT 0,
            terkumpul BIGINT NOT NULL DEFAULT 0,
            deadline DATE,
            active SMALLINT NOT NULL DEFAULT 1,
            dibuat_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        "CREATE TABLE IF NOT EXISTS sedekah_log (
            id SERIAL PRIMARY KEY,
            program_id INTEGER NOT NULL REFERENCES sedekah_program(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            jumlah BIGINT NOT NULL,
            catatan TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
        // Log challenge harian (1 ayat / subuh walk / puasa Sen-Kam)
        "CREATE TABLE IF NOT EXISTS challenge_log (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            challenge_key VARCHAR(40) NOT NULL, -- ayat_harian | subuh_walk | puasa_seninkamis | dzikir_pagi | dzikir_petang
            tanggal DATE NOT NULL,
            catatan TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT now(),
            UNIQUE(user_id, challenge_key, tanggal)
        )",
        // ====== TAMBAHAN: CRUD Doa Harian pribadi ======
        "CREATE TABLE IF NOT EXISTS doa_user (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            judul VARCHAR(180) NOT NULL,
            arab TEXT NOT NULL,
            terjemah TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT now(),
            updated_at TIMESTAMP
        )",
        // ====== TAMBAHAN: CRUD master Challenge Islami (admin) ======
        "CREATE TABLE IF NOT EXISTS challenge_master (
            id SERIAL PRIMARY KEY,
            kunci VARCHAR(40) NOT NULL UNIQUE,
            judul VARCHAR(180) NOT NULL,
            deskripsi TEXT,
            icon VARCHAR(40) NOT NULL DEFAULT 'bi-trophy',
            warna VARCHAR(20) NOT NULL DEFAULT 'success',
            aktif SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
    ];
    foreach ($sqls as $sql) {
        try { @pg_query(db(), $sql); } catch (Throwable $e) {}
    }
    // Seed challenge_master dari daftar bawaan, hanya jika kosong
    try {
        $n = (int) @pg_fetch_result(@pg_query(db(), "SELECT COUNT(*) FROM challenge_master"), 0, 0);
        if ($n === 0) {
            $seed = [
              ['ayat_harian','1 Hari 1 Ayat','Baca minimal 1 ayat Al-Qur\'an setiap hari.','bi-book','success'],
              ['subuh_walk','Subuh Walk Challenge','Jalan kaki ≥10 menit setelah sholat Subuh.','bi-sunrise','warning'],
              ['puasa_seninkamis','Puasa Senin-Kamis','Catat puasa sunnah Senin/Kamis hari ini.','bi-droplet-half','info'],
              ['dzikir_pagi','Dzikir Pagi','Selesaikan rangkaian dzikir pagi.','bi-brightness-high','primary'],
              ['dzikir_petang','Dzikir Petang','Selesaikan rangkaian dzikir petang.','bi-moon-stars','dark'],
            ];
            foreach ($seed as $s) {
                @pg_query_params(db(),
                  "INSERT INTO challenge_master(kunci,judul,deskripsi,icon,warna,aktif) VALUES($1,$2,$3,$4,$5,1) ON CONFLICT (kunci) DO NOTHING",
                  $s);
            }
        }
    } catch (Throwable $e) {}
}
islami_run_migrations();

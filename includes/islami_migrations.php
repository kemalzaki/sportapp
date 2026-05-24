<?php
/**
 * Migrasi tabel fitur Islami (idempotent).
 */
require_once __DIR__ . '/../config/db.php';

function islami_run_migrations(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sqls = [
        "CREATE TABLE IF NOT EXISTS referal_codes (id SERIAL PRIMARY KEY, kode VARCHAR(32) NOT NULL UNIQUE, deskripsi TEXT, aktif SMALLINT NOT NULL DEFAULT 1, max_pakai INTEGER, jumlah_terpakai INTEGER NOT NULL DEFAULT 0, dibuat_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL, expired_at DATE, created_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS user_islami_pref (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, hide_sapa SMALLINT NOT NULL DEFAULT 0, mode_tenang SMALLINT NOT NULL DEFAULT 1, kota VARCHAR(60) NOT NULL DEFAULT 'Jakarta', negara VARCHAR(40) NOT NULL DEFAULT 'Indonesia', updated_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS quran_bookmarks (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, surah INTEGER NOT NULL, ayat INTEGER NOT NULL, catatan TEXT, created_at TIMESTAMP NOT NULL DEFAULT now(), UNIQUE (user_id, surah, ayat))",
        "CREATE TABLE IF NOT EXISTS quran_last_read (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, surah INTEGER NOT NULL, ayat INTEGER NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS islami_streak (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, tanggal DATE NOT NULL, quran_done SMALLINT NOT NULL DEFAULT 0, dzikir_pagi SMALLINT NOT NULL DEFAULT 0, dzikir_petang SMALLINT NOT NULL DEFAULT 0, doa_done SMALLINT NOT NULL DEFAULT 0, sholat_count SMALLINT NOT NULL DEFAULT 0, subuh_walk SMALLINT NOT NULL DEFAULT 0, sedekah SMALLINT NOT NULL DEFAULT 0, poin INTEGER NOT NULL DEFAULT 0, UNIQUE(user_id, tanggal))",
        "CREATE TABLE IF NOT EXISTS islami_badges (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, badge_key VARCHAR(40) NOT NULL, earned_at TIMESTAMP NOT NULL DEFAULT now(), UNIQUE(user_id, badge_key))",
        "CREATE TABLE IF NOT EXISTS islami_quotes (id SERIAL PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, isi TEXT NOT NULL, sumber VARCHAR(120), created_at TIMESTAMP NOT NULL DEFAULT now())",
        // Kajian Literatur Buku (ditambah kolom penulis, tipe, link_web, pdf_path)
        "CREATE TABLE IF NOT EXISTS islami_kajian (id SERIAL PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, judul VARCHAR(180) NOT NULL, isi TEXT, link_video VARCHAR(255), created_at TIMESTAMP NOT NULL DEFAULT now())",
        "ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS penulis VARCHAR(120)",
        "ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS tipe VARCHAR(20) DEFAULT 'buku'",
        "ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS link_web VARCHAR(500)",
        "ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS pdf_path VARCHAR(500)",
        "ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP",
        // Artikel sunnah
        "CREATE TABLE IF NOT EXISTS islami_artikel (id SERIAL PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, judul VARCHAR(180) NOT NULL, isi TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT now())",
        "ALTER TABLE islami_artikel ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP",
        "CREATE TABLE IF NOT EXISTS doa_request (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, isi TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS doa_aamiin (id SERIAL PRIMARY KEY, doa_id INTEGER NOT NULL REFERENCES doa_request(id) ON DELETE CASCADE, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, created_at TIMESTAMP NOT NULL DEFAULT now(), UNIQUE(doa_id, user_id))",
        "CREATE TABLE IF NOT EXISTS sedekah_program (id SERIAL PRIMARY KEY, judul VARCHAR(180) NOT NULL, deskripsi TEXT, jenis VARCHAR(20) NOT NULL DEFAULT 'sedekah', target_amount BIGINT NOT NULL DEFAULT 0, terkumpul BIGINT NOT NULL DEFAULT 0, deadline DATE, active SMALLINT NOT NULL DEFAULT 1, dibuat_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS sedekah_log (id SERIAL PRIMARY KEY, program_id INTEGER NOT NULL REFERENCES sedekah_program(id) ON DELETE CASCADE, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, jumlah BIGINT NOT NULL, catatan TEXT, created_at TIMESTAMP NOT NULL DEFAULT now())",
        "CREATE TABLE IF NOT EXISTS challenge_log (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, challenge_key VARCHAR(40) NOT NULL, tanggal DATE NOT NULL, catatan TEXT, created_at TIMESTAMP NOT NULL DEFAULT now(), UNIQUE(user_id, challenge_key, tanggal))",
        "CREATE TABLE IF NOT EXISTS doa_user (id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, judul VARCHAR(180) NOT NULL, arab TEXT NOT NULL, terjemah TEXT, created_at TIMESTAMP NOT NULL DEFAULT now(), updated_at TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS challenge_master (id SERIAL PRIMARY KEY, kunci VARCHAR(40) NOT NULL UNIQUE, judul VARCHAR(180) NOT NULL, deskripsi TEXT, icon VARCHAR(40) NOT NULL DEFAULT 'bi-trophy', warna VARCHAR(20) NOT NULL DEFAULT 'success', aktif SMALLINT NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT now())",
        // ====== TAMBAHAN: Donasi Yayasan KRB ======
        "CREATE TABLE IF NOT EXISTS donasi_krb (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            nama VARCHAR(120) NOT NULL,
            jumlah BIGINT NOT NULL,
            metode VARCHAR(30) NOT NULL DEFAULT 'transfer',
            bank VARCHAR(40),
            no_ref VARCHAR(60),
            bukti_path VARCHAR(500),
            catatan TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT now()
        )",
    ];
    foreach ($sqls as $sql) { try { @pg_query(db(), $sql); } catch (Throwable $e) {} }

    // Seed challenge_master (termasuk jenis-jenis puasa sunnah)
    $seed = [
      ['ayat_harian','1 Hari 1 Ayat','Baca minimal 1 ayat Al-Qur\'an setiap hari.','bi-book','success'],
      ['subuh_walk','Subuh Walk Challenge','Jalan kaki ≥10 menit setelah sholat Subuh.','bi-sunrise','warning'],
      ['dzikir_pagi','Dzikir Pagi','Selesaikan rangkaian dzikir pagi.','bi-brightness-high','primary'],
      ['dzikir_petang','Dzikir Petang','Selesaikan rangkaian dzikir petang.','bi-moon-stars','dark'],
      ['puasa_seninkamis','Puasa Senin-Kamis','Catat puasa sunnah Senin/Kamis hari ini.','bi-droplet-half','info'],
      ['puasa_ayyamul_bidh','Puasa Ayyamul Bidh','Puasa 13, 14, 15 Hijriyah (hari putih).','bi-moon','info'],
      ['puasa_daud','Puasa Daud','Puasa selang-seling: sehari puasa, sehari berbuka.','bi-droplet','primary'],
      ['puasa_syawal','Puasa 6 Hari Syawal','Puasa 6 hari di bulan Syawal setelah Ramadhan.','bi-stars','success'],
      ['puasa_arafah','Puasa Arafah','Puasa 9 Dzulhijjah, menghapus dosa 2 tahun.','bi-sun','warning'],
      ['puasa_tasua_asyura','Puasa Tasu\'a & Asyura','Puasa 9 & 10 Muharram.','bi-droplet-half','dark'],
      ['puasa_nisfu_syaban','Puasa Nisfu Sya\'ban','Puasa di pertengahan bulan Sya\'ban.','bi-moon-stars','secondary'],
      ['puasa_ramadhan','Puasa Ramadhan','Puasa wajib di bulan Ramadhan.','bi-moon','success'],
    ];
    foreach ($seed as $s) {
        try {
            @pg_query_params(db(),
              "INSERT INTO challenge_master(kunci,judul,deskripsi,icon,warna,aktif) VALUES($1,$2,$3,$4,$5,1) ON CONFLICT (kunci) DO NOTHING",
              $s);
        } catch (Throwable $e) {}
    }
}
islami_run_migrations();

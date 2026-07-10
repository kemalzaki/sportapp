<?php
// kalori_mingguan.php — Pencatatan kalori mingguan + AI estimasi kalori dari foto
// AI: gunakan OPENAI_API_KEY (model gpt-4o-mini vision). Bila tidak ada, input manual saja.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/ai_router.php';
require __DIR__.'/includes/paket_helpers.php'; // Revisi 26 Juni 2026 #7 — gating PRO/KOMUNITAS
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Kalori Mingguan';
$pageSkeleton = 'table';
$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }
$uid = (int)$u['id'];

/* Revisi 26 Juni 2026 #7 — Kalori Mingguan hanya untuk paket PRO & KOMUNITAS.
   Paket Gratis: dikunci, ditampilkan banner PRO + tombol pesan via WA. */
$USER_PAKET = paket_user($u);
if (!in_array($USER_PAKET, ['pro','komunitas'], true)) {
    include __DIR__.'/includes/header.php';
    echo '<h2 class="mb-3"><i class="bi bi-lock-fill text-warning"></i> Kalori Mingguan</h2>';
    echo paket_pro_lock_banner('Kalori Mingguan',
        'Pencatatan kalori harian/mingguan, target, dan estimasi AI dari foto makanan hanya tersedia untuk paket PRO & KOMUNITAS. Paket Gratis tidak bisa mengakses fitur ini. Status paket Anda saat ini: '.strtoupper($USER_PAKET).'.');
    include __DIR__.'/includes/footer.php';
    exit;
}

// Auto-buat tabel (idempotent) — dipisah dari kalori_log (workout) agar tidak bentrok skema.
try {
    db_exec("CREATE TABLE IF NOT EXISTS kalori_target (
        user_id        INT PRIMARY KEY,
        target_harian  INT NOT NULL DEFAULT 2000,
        updated_at     TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE TABLE IF NOT EXISTS kalori_makanan_log (
        id            SERIAL PRIMARY KEY,
        user_id       INT NOT NULL,
        tanggal       DATE NOT NULL DEFAULT CURRENT_DATE,
        waktu         TIME NOT NULL DEFAULT CURRENT_TIME,
        nama_makanan  VARCHAR(200) NOT NULL,
        kalori        INT NOT NULL DEFAULT 0,
        foto_url      TEXT,
        ai_estimasi   BOOLEAN NOT NULL DEFAULT FALSE,
        catatan       TEXT,
        created_at    TIMESTAMP NOT NULL DEFAULT now()
    )");
    // Revisi 17 Juni 2026 Part J — kolom file_id untuk hapus dari ImageKit
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS foto_file_id TEXT");
    /* Revisi 19 Juni 2026 Part O #7 — kolom detail makro dari AI (gram) */
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS karbohidrat_g NUMERIC(7,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS protein_g     NUMERIC(7,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS lemak_g       NUMERIC(7,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS serat_g       NUMERIC(7,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS gula_g        NUMERIC(7,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS sodium_mg     NUMERIC(8,2)");
    db_exec("ALTER TABLE kalori_makanan_log ADD COLUMN IF NOT EXISTS ai_detail     TEXT");
    db_exec("CREATE INDEX IF NOT EXISTS idx_kalori_mkn_user_tgl ON kalori_makanan_log(user_id, tanggal DESC)");
    // Revisi 18 Juni 2026 (Lanjutan) — Setting sumber defisit kalori per user.
    // sumber: 'auto' (semua workout di kalori_log, default lama),
    //         'jogging' (hanya jogging dari upload_harian / Riwayat),
    //         'manual' (input nilai harian kkal/hari oleh user),
    //         'gabungan' (jogging dari Riwayat + manual harian).
    db_exec("CREATE TABLE IF NOT EXISTS kalori_defisit_setting (
        user_id        INT PRIMARY KEY,
        sumber         VARCHAR(20) NOT NULL DEFAULT 'auto',
        manual_harian  INT NOT NULL DEFAULT 0,
        updated_at     TIMESTAMP NOT NULL DEFAULT now()
    )");
    // Revisi 19 Juni 2026 — Pembakaran kalori AKTIVITAS LAIN (selain makanan & olahraga utama).
    // User mendeskripsikan aktivitas via teks → AI memperkirakan kalori → disimpan di sini.
    db_exec("CREATE TABLE IF NOT EXISTS kalori_burn_lain (
        id           SERIAL PRIMARY KEY,
        user_id      INT NOT NULL,
        tanggal      DATE NOT NULL DEFAULT CURRENT_DATE,
        deskripsi    TEXT NOT NULL,
        durasi_menit INT NOT NULL DEFAULT 0,
        kalori       INT NOT NULL DEFAULT 0,
        rincian      TEXT,
        created_at   TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_kalori_lain_user_tgl ON kalori_burn_lain(user_id, tanggal DESC)");
} catch (Throwable $e) {}

// === Helper AI (Revisi 17 Juni 2026 Part J) ===
// Pipeline: Foto + nama teks → Gemini 2.5 Flash mengenali → Estimasi kalori → DB.
// AI membaca KEDUA input (nama makanan yang diketik user + gambar) untuk akurasi maksimal.
function ai_estimate_kalori($imagePath, $namaTeks=''){
    @set_time_limit(90);
    if (!is_file($imagePath)) return ['err'=>'file gambar tidak ditemukan'];
    $hintTxt = trim((string)$namaTeks);
    $prompt = "Anda adalah ahli gizi. Anda menerima DUA input: "
            . "(1) FOTO MAKANAN, dan "
            . "(2) TEKS NAMA MAKANAN yang diketik pengguna: \""
            . ($hintTxt !== '' ? $hintTxt : '(tidak diisi)') . "\". "
            . "Tugas: BACA KEDUANYA. Jika teks nama diisi, gunakan sebagai petunjuk utama dan verifikasi dengan foto. "
            . "Jika teks nama kosong, tebak dari foto saja. Jika foto dan teks berbeda, prioritaskan foto namun sebut keduanya di 'rincian'. "
            . "Hitung PERKIRAAN TOTAL KALORI (kcal, INTEGER) DAN PERKIRAAN MAKRO NUTRIEN untuk porsi yang terlihat. "
            . "Jumlahkan bila ada beberapa item. Estimasi konservatif berbasis tabel komposisi pangan Indonesia (TKPI/USDA). "
            . "Balas HANYA JSON murni TANPA fence ```json``` dan TANPA kalimat pengantar: "
            . "{\"nama\":\"...\",\"kalori\":<int>,"
            . "\"karbohidrat_g\":<number>,\"protein_g\":<number>,\"lemak_g\":<number>,"
            . "\"serat_g\":<number>,\"gula_g\":<number>,\"sodium_mg\":<number>,"
            . "\"rincian\":\"<1 kalimat: komposisi & catatan gizi>\"}.";
    $g = ai_vision($prompt, $imagePath,
            ['json'=>true,'temperature'=>0.2,'max_tokens'=>1024]);
    if (!$g['ok']) return ['err'=>'Gemini: '.$g['err']];
    $obj = ai_extract_json($g['text']);
    if (is_array($obj) && isset($obj['kalori'])) {
        // Revisi 22 Juni 2026 R5 — bersihkan 'rincian' agar tidak menampilkan raw JSON.
        $rincianRaw = (string)($obj['rincian'] ?? '');
        $rincianRaw = trim($rincianRaw);
        if ($rincianRaw !== '' && ($rincianRaw[0] === '{' || $rincianRaw[0] === '[')) {
            $rincianRaw = ''; // buang JSON yang nyasar
        }
        // Strip karakter kontrol & potong panjang
        $rincianRaw = preg_replace('/\s+/', ' ', $rincianRaw);
        if (mb_strlen($rincianRaw) > 220) $rincianRaw = mb_substr($rincianRaw, 0, 217).'…';
        return [
            'nama'    => $obj['nama'] ?? '',
            'kalori'  => (int)$obj['kalori'],
            'karbohidrat_g' => isset($obj['karbohidrat_g']) ? (float)$obj['karbohidrat_g'] : null,
            'protein_g'     => isset($obj['protein_g'])     ? (float)$obj['protein_g']     : null,
            'lemak_g'       => isset($obj['lemak_g'])       ? (float)$obj['lemak_g']       : null,
            'serat_g'       => isset($obj['serat_g'])       ? (float)$obj['serat_g']       : null,
            'gula_g'        => isset($obj['gula_g'])        ? (float)$obj['gula_g']        : null,
            'sodium_mg'     => isset($obj['sodium_mg'])     ? (float)$obj['sodium_mg']     : null,
            'rincian' => $rincianRaw,
        ];
    }
    // Fallback regex bila JSON gagal
    $raw = (string)$g['text'];
    $nama = ''; $kal = 0;
    if (preg_match('/"nama"\s*:\s*"([^"]{2,80})"/i', $raw, $nn)) $nama = $nn[1];
    if (preg_match('/"kalori"\s*:\s*"?(\d{2,5})/i', $raw, $kk)) $kal = (int)$kk[1];
    elseif (preg_match('/(\d{2,4})\s*(?:kkal|kcal|kal)/i', $raw, $mm)) $kal = (int)$mm[1];
    if ($kal > 0) return ['nama'=>$nama, 'kalori'=>$kal, 'rincian'=>trim(substr($raw,0,120))];
    return ['err'=>'AI gagal mengurai JSON. Raw: '.substr($raw,0,200)];
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    // Revisi 22 Juni 2026 R9 — Bungkus seluruh CRUD dalam try/catch sehingga
    // error (ON CONFLICT dari trigger/migrasi, kolom hilang, kuota AI, dll)
    // tidak ditampilkan sebagai halaman HTML dari set_exception_handler,
    // melainkan masuk ke flash_err dan user tetap diarahkan kembali.
    try {
    if ($a==='target') {
        $t = max(500, (int)$_POST['target_harian']);
        // Revisi R8 — pakai check-then-update/insert agar tidak bergantung pada
        // PRIMARY KEY (user_id) di tabel kalori_target. Pernah ada DB lama yang
        // belum punya PK sehingga ON CONFLICT melempar error.
        try {
            $exists = db_val("SELECT 1 FROM kalori_target WHERE user_id=$1", [$uid]);
            if ($exists) {
                db_exec("UPDATE kalori_target SET target_harian=$2 WHERE user_id=$1", [$uid, $t]);
            } else {
                db_exec("INSERT INTO kalori_target(user_id,target_harian) VALUES($1,$2)", [$uid, $t]);
            }
            $_SESSION['flash_ok'] = "Target diperbarui: $t kkal/hari.";
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = "Gagal menyimpan target: ".$e->getMessage();
        }
    } elseif ($a==='defisit_setting') {
        // Revisi 18 Juni 2026 (Lanjutan) — Simpan pilihan sumber defisit kalori.
        $src = $_POST['sumber'] ?? 'auto';
        if (!in_array($src, ['auto','jogging','manual','gabungan'], true)) $src = 'auto';
        $mh  = max(0, (int)($_POST['manual_harian'] ?? 0));
        // Revisi R8 — check-then-update/insert (alasan sama seperti di atas).
        try {
            $exists = db_val("SELECT 1 FROM kalori_defisit_setting WHERE user_id=$1", [$uid]);
            if ($exists) {
                db_exec("UPDATE kalori_defisit_setting SET sumber=$2, manual_harian=$3, updated_at=now() WHERE user_id=$1",
                    [$uid, $src, $mh]);
            } else {
                db_exec("INSERT INTO kalori_defisit_setting(user_id,sumber,manual_harian,updated_at) VALUES($1,$2,$3, now())",
                    [$uid, $src, $mh]);
            }
            $_SESSION['flash_ok'] = "Pengaturan defisit kalori disimpan (sumber: $src, manual: $mh kkal/hari).";
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = "Gagal menyimpan pengaturan defisit: ".$e->getMessage();
        }
    } elseif ($a==='add') {
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $jam = $_POST['waktu'] ?: date('H:i');
        $nama = trim($_POST['nama_makanan'] ?? '');
        $kal  = (int)($_POST['kalori'] ?? 0);
        $cat  = trim($_POST['catatan'] ?? '');
        $foto = null; $fotoFileId = null; $aiUsed = false;
        $aiMacro = ['karbohidrat_g'=>null,'protein_g'=>null,'lemak_g'=>null,'serat_g'=>null,'gula_g'=>null,'sodium_mg'=>null];
        $aiDetail = null;
        $errs = []; $warns = [];

        // === Revisi 18 Juni 2026 — perbaiki upload kamera + AI kalori ===
        // Bug sebelumnya:
        //  (a) Foto dari kamera HP sering >5 MB, melebihi php upload_max_filesize default → tmp_name kosong,
        //      tapi error UPLOAD_ERR_INI_SIZE tidak ditampilkan ke user.
        //  (b) Jika AI gagal (quota habis) dan user mengandalkan AI, kalori tetap 0 dan user bingung.
        //  (c) Pesan error ImageKit tidak rinci → user tidak tahu kenapa foto tidak masuk.
        $hasFile = isset($_FILES['foto']) && (!empty($_FILES['foto']['name']) || !empty($_FILES['foto']['tmp_name']));
        if ($hasFile) {
            $upErr = (int)($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($upErr !== UPLOAD_ERR_OK) {
                $upErrMap = [
                    UPLOAD_ERR_INI_SIZE=>'Ukuran foto melebihi limit server (upload_max_filesize). Coba foto resolusi lebih kecil.',
                    UPLOAD_ERR_FORM_SIZE=>'Ukuran foto melebihi limit form (MAX_FILE_SIZE).',
                    UPLOAD_ERR_PARTIAL=>'Foto hanya terupload sebagian. Coba lagi.',
                    UPLOAD_ERR_NO_FILE=>'Tidak ada file foto.',
                    UPLOAD_ERR_NO_TMP_DIR=>'Server: tmp dir tidak ada.',
                    UPLOAD_ERR_CANT_WRITE=>'Server: gagal menulis ke disk.',
                    UPLOAD_ERR_EXTENSION=>'Upload diblokir ekstensi PHP.',
                ];
                $errs[] = 'Upload gagal: '.($upErrMap[$upErr] ?? ('kode '.$upErr));
            } elseif (!is_uploaded_file($_FILES['foto']['tmp_name'])) {
                $errs[] = 'Upload tidak valid (is_uploaded_file=false).';
            } else {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION) ?: 'jpg');
                if (!in_array($ext, ['jpg','jpeg','png','webp','heic','heif'])) $ext='jpg';
                $tmpDst = sys_get_temp_dir().'/k_'.$uid.'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $tmpDst)) {
                    $errs[] = 'Gagal memindahkan file upload ke folder tmp server.';
                } else {
                    // === AI dulu (file lokal masih ada) ===
                    if (!empty($_POST['use_ai']) || $kal <= 0) {
                        $ai = ai_estimate_kalori($tmpDst, $nama);
                        if (is_array($ai) && isset($ai['kalori'])) {
                            if ($kal <= 0) $kal = (int)$ai['kalori'];
                            // Revisi 18 Juni 2026 — SELALU prioritaskan nama hasil scan AI bila tersedia,
                            // supaya user benar-benar tahu "ini makanan apa" dari foto.
                            if (!empty($ai['nama'])) {
                                $nama = ($nama === '' || strtolower($nama)==='tanpa nama')
                                        ? $ai['nama']
                                        : $nama.' ('.$ai['nama'].')';
                            }
                            // Simpan rincian AI ke catatan bila kosong supaya tetap terlihat di tabel.
                            if ($cat === '' && !empty($ai['rincian'])) $cat = 'AI: '.$ai['rincian'];
                            // Revisi 19 Juni 2026 Part O #7 — simpan makro nutrien dari AI
                            foreach ($aiMacro as $k => $_) if (array_key_exists($k,$ai)) $aiMacro[$k] = $ai[$k];
                            $detailParts = [];
                            foreach ([
                                'karbohidrat_g'=>'Karbohidrat (g)',
                                'protein_g'    =>'Protein (g)',
                                'lemak_g'      =>'Lemak (g)',
                                'serat_g'      =>'Serat (g)',
                                'gula_g'       =>'Gula (g)',
                                'sodium_mg'    =>'Sodium (mg)',
                            ] as $k=>$lab) {
                                if ($aiMacro[$k] !== null) $detailParts[] = $lab.': '.rtrim(rtrim(number_format($aiMacro[$k],2,'.',''),'0'),'.');
                            }
                            if ($detailParts) $aiDetail = implode(' · ', $detailParts);
                            $aiUsed = true;
                        } else {
                            $errMsg = (is_array($ai) && !empty($ai['err'])) ? $ai['err'] : 'AI tidak menjawab.';
                            if (stripos($errMsg,'quota')!==false || stripos($errMsg,'exceeded')!==false || stripos($errMsg,'rate limit')!==false) {
                                $errMsg = 'Kuota AI Gemini habis (free tier). Coba lagi 1 menit, atau set GEMINI_API_KEYS=key1,key2,key3. Detail: '.mb_substr($errMsg,0,180);
                            }
                            $warns[] = 'AI gagal menebak kalori — '.$errMsg.' Foto tetap diupload, silakan edit angka kalori manual.';
                        }
                    }
                    // === Upload ke ImageKit (terpisah dari status AI) ===
                    try {
                        require_once __DIR__.'/config/imagekit.php';
                        global $imageKit;
                        $bin = @file_get_contents($tmpDst);
                        if ($bin === false || strlen($bin) === 0) {
                            $errs[] = 'File foto kosong setelah dipindah.';
                        } else {
                            $safeNama = preg_replace('/[^a-z0-9]/i','_', $nama ?: 'makanan');
                            $fileName = 'kalori-'.$uid.'-'.$tgl.'-'.$safeNama.'-'.time().'.'.$ext;
                            $up = $imageKit->uploadFile([
                                'file'     => base64_encode($bin),
                                'fileName' => $fileName,
                                'folder'   => '/sportapp/kalori/'.date('F_Y', strtotime($tgl))
                            ]);
                            if (!empty($up->error)) {
                                $emsg = is_object($up->error) ? json_encode($up->error) : (string)$up->error;
                                $errs[] = 'ImageKit menolak upload: '.mb_substr($emsg, 0, 250);
                            } elseif (!empty($up->result) && !empty($up->result->url)) {
                                $foto       = $up->result->url;
                                $fotoFileId = $up->result->fileId ?? null;
                            } else {
                                $errs[] = 'ImageKit tidak mengembalikan URL. Cek API key di config/imagekit.php.';
                            }
                        }
                    } catch (Throwable $e) {
                        $errs[] = 'ImageKit exception: '.$e->getMessage();
                    }
                    @unlink($tmpDst);
                }
            }
        }
        if ($nama === '') $nama = 'Tanpa nama';
        if ($kal < 0) $kal = 0;
        // SELALU simpan entri agar data tidak hilang — meski foto gagal / AI gagal.
        db_exec(
            "INSERT INTO kalori_makanan_log(user_id,tanggal,waktu,nama_makanan,kalori,foto_url,foto_file_id,ai_estimasi,catatan,
                karbohidrat_g,protein_g,lemak_g,serat_g,gula_g,sodium_mg,ai_detail)
             VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16)",
            [$uid,$tgl,$jam,$nama,$kal,$foto,$fotoFileId,$aiUsed?'t':'f',$cat,
             $aiMacro['karbohidrat_g'],$aiMacro['protein_g'],$aiMacro['lemak_g'],
             $aiMacro['serat_g'],$aiMacro['gula_g'],$aiMacro['sodium_mg'],$aiDetail]
        );
        if ($errs)  $_SESSION['flash_err'] = implode(' • ', $errs);
        if ($warns) $_SESSION['flash_err'] = ($_SESSION['flash_err'] ?? '') . ' ' . implode(' • ', $warns);
        $okMsg = "Makanan ditambahkan ($kal kkal)".($aiUsed?' [AI]':'').($foto?' · foto OK':'');
        if ($aiDetail) $okMsg .= ' · '.$aiDetail;
        $_SESSION['flash_ok'] = $okMsg;
    } elseif ($a==='delete') {
        // Hapus juga dari ImageKit jika ada file_id
        $row = db_one("SELECT foto_file_id FROM kalori_makanan_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
        if ($row && !empty($row['foto_file_id'])) {
            try { require_once __DIR__.'/config/imagekit.php'; global $imageKit; $imageKit->deleteFile($row['foto_file_id']); } catch (Throwable $e) {}
        }
        db_exec("DELETE FROM kalori_makanan_log WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
    } elseif ($a==='edit') {
        // Revisi 13 Juni 2026 — CRUD Riwayat Minggu ini
        $id  = (int)($_POST['id'] ?? 0);
        $tgl = $_POST['tanggal'] ?: date('Y-m-d');
        $jam = $_POST['waktu'] ?: date('H:i');
        $nama= trim($_POST['nama_makanan'] ?? '') ?: 'Tanpa nama';
        $kal = max(0,(int)($_POST['kalori'] ?? 0));
        $cat = trim($_POST['catatan'] ?? '');
        if ($id>0) {
          db_exec("UPDATE kalori_makanan_log
                      SET tanggal=$1, waktu=$2, nama_makanan=$3, kalori=$4, catatan=$5
                    WHERE id=$6 AND user_id=$7",
                  [$tgl,$jam,$nama,$kal,$cat?:null,$id,$uid]);
          $_SESSION['flash_ok'] = "Entri diperbarui.";
        }
    } elseif ($a==='lain_add') {
        // Revisi 19 Juni 2026 — Tambah entri pembakaran kalori aktivitas LAIN
        $tgl  = $_POST['tanggal'] ?: date('Y-m-d');
        $desk = trim((string)($_POST['deskripsi'] ?? ''));
        $dur  = max(0, (int)($_POST['durasi_menit'] ?? 0));
        $kal  = max(0, (int)($_POST['kalori'] ?? 0));
        $rinc = trim((string)($_POST['rincian'] ?? ''));
        if ($desk === '') {
            $_SESSION['flash_err'] = 'Deskripsi aktivitas wajib diisi.';
        } else {
            db_exec("INSERT INTO kalori_burn_lain(user_id,tanggal,deskripsi,durasi_menit,kalori,rincian)
                     VALUES($1,$2,$3,$4,$5,$6)", [$uid,$tgl,$desk,$dur,$kal,$rinc?:null]);
            $_SESSION['flash_ok'] = "Aktivitas lain ditambahkan ($kal kkal).";
        }
    } elseif ($a==='lain_delete') {
        db_exec("DELETE FROM kalori_burn_lain WHERE id=$1 AND user_id=$2", [(int)$_POST['id'],$uid]);
    }
    } catch (Throwable $e) {
        unset($_SESSION['error_popup']);
        $msg = $e->getMessage();
        if (stripos($msg, 'ON CONFLICT') !== false) {
            $msg .= ' — jalankan migrations_r9.sql untuk menambah UNIQUE/PRIMARY KEY yang hilang.';
        }
        $_SESSION['flash_err'] = 'Operasi gagal: ' . $msg;
    }
    header('Location: kalori_mingguan.php'); exit;
}

// Data
$target = (int)(db_one("SELECT target_harian FROM kalori_target WHERE user_id=$1",[$uid])['target_harian'] ?? 2000);

// Revisi 15 Juni 2026 — dukung riwayat minggu sebelumnya via ?week=offset (0=ini, -1=mgg lalu, dst)
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
if ($weekOffset > 0) $weekOffset = 0; // tidak boleh ke depan
$weekStart = date('Y-m-d', strtotime('monday this week '.($weekOffset).' week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week '.($weekOffset).' week'));

$logs = db_all("SELECT * FROM kalori_makanan_log WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3 ORDER BY tanggal DESC, waktu DESC",
    [$uid,$weekStart,$weekEnd]);
$byDay = db_all("SELECT tanggal::text AS tgl, SUM(kalori)::int AS total
                 FROM kalori_makanan_log WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                 GROUP BY tanggal ORDER BY tanggal",[$uid,$weekStart,$weekEnd]);
$map = [];
foreach($byDay as $r) $map[$r['tgl']] = (int)$r['total'];

// Revisi 18 Juni 2026 (Lanjutan) — Sumber kalori TERBAKAR dapat dipilih user:
//   'auto'     → tabel kalori_log (semua workout, default lama)
//   'jogging'  → tabel upload_harian dari Riwayat, hanya entri jenis Jogging
//   'manual'   → angka manual_harian (kkal/hari) yang diisi user
//   'gabungan' → jogging (upload_harian) + manual_harian
$defSet = db_one("SELECT sumber, manual_harian FROM kalori_defisit_setting WHERE user_id=$1", [$uid]);
$defSumber = $defSet['sumber'] ?? 'auto';
$defManual = (int)($defSet['manual_harian'] ?? 0);

$burnMap = []; $burnDetail = [];
try {
    if ($defSumber === 'auto') {
        $burnRows = db_all(
            "SELECT (dibuat_pada::date)::text AS tgl, SUM(kalori)::int AS total
               FROM kalori_log
              WHERE user_id=$1 AND dibuat_pada::date BETWEEN $2 AND $3
           GROUP BY dibuat_pada::date ORDER BY 1",
            [$uid,$weekStart,$weekEnd]
        );
        foreach ($burnRows as $r) $burnMap[$r['tgl']] = (int)$r['total'];
        $burnDetail = db_all(
            "SELECT jenis, SUM(menit)::int AS menit, SUM(kalori)::int AS kalori, COUNT(*)::int AS sesi
               FROM kalori_log
              WHERE user_id=$1 AND dibuat_pada::date BETWEEN $2 AND $3
           GROUP BY jenis ORDER BY kalori DESC",
            [$uid,$weekStart,$weekEnd]
        );
    }
    if ($defSumber === 'jogging' || $defSumber === 'gabungan') {
        // Ambil kalori jogging dari riwayat.php (tabel upload_harian) — jenis mengandung 'jog'/'lari'/'run'.
        $jogRows = db_all(
            "SELECT tanggal::text AS tgl, SUM(kalori)::int AS total
               FROM upload_harian
              WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                AND (jenis ILIKE '%jog%' OR jenis ILIKE '%lari%' OR jenis ILIKE '%run%')
           GROUP BY tanggal ORDER BY 1",
            [$uid,$weekStart,$weekEnd]
        );
        foreach ($jogRows as $r) $burnMap[$r['tgl']] = ($burnMap[$r['tgl']] ?? 0) + (int)$r['total'];
        $jogDetail = db_all(
            "SELECT jenis, SUM(durasi_menit)::int AS menit, SUM(kalori)::int AS kalori, COUNT(*)::int AS sesi
               FROM upload_harian
              WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                AND (jenis ILIKE '%jog%' OR jenis ILIKE '%lari%' OR jenis ILIKE '%run%')
           GROUP BY jenis ORDER BY kalori DESC",
            [$uid,$weekStart,$weekEnd]
        );
        foreach ($jogDetail as $d) $burnDetail[] = $d;
    }
    if ($defSumber === 'manual' || $defSumber === 'gabungan') {
        // Tambahkan nilai manual_harian ke setiap hari minggu ini (sampai hari ini saja agar tidak overshoot ke depan).
        $today = date('Y-m-d');
        for ($i=0; $i<7; $i++) {
            $d = date('Y-m-d', strtotime($weekStart." +$i day"));
            if ($d <= $today) $burnMap[$d] = ($burnMap[$d] ?? 0) + $defManual;
        }
        if ($defManual > 0) {
            $burnDetail[] = ['jenis'=>'Defisit Manual','sesi'=>1,'menit'=>0,'kalori'=>$defManual*7];
        }
    }
    // Revisi 19 Juni 2026 — Pembakaran kalori AKTIVITAS LAIN selalu dijumlahkan
    // ke burnMap, tidak tergantung pilihan sumber defisit.
    $lainRows = db_all(
        "SELECT tanggal::text AS tgl, SUM(kalori)::int AS total
           FROM kalori_burn_lain
          WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
       GROUP BY tanggal", [$uid,$weekStart,$weekEnd]);
    foreach ($lainRows as $r) $burnMap[$r['tgl']] = ($burnMap[$r['tgl']] ?? 0) + (int)$r['total'];
    $lainTotalWk = (int)(db_one(
        "SELECT COALESCE(SUM(kalori),0)::int AS t FROM kalori_burn_lain
          WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3",[$uid,$weekStart,$weekEnd])['t'] ?? 0);
    if ($lainTotalWk > 0) {
        $burnDetail[] = ['jenis'=>'Aktivitas Lain (AI)','sesi'=>count($lainRows),
                         'menit'=>0,'kalori'=>$lainTotalWk];
    }
} catch (Throwable $e) { /* tabel terkait belum ada → diabaikan */ }

// Daftar entri aktivitas lain hari ini (untuk panel input)
$lainToday = db_all(
    "SELECT id, tanggal::text AS tgl, deskripsi, durasi_menit, kalori, rincian, created_at
       FROM kalori_burn_lain WHERE user_id=$1 AND tanggal=$2 ORDER BY id DESC",
    [$uid, date('Y-m-d')]);

$labels=[]; $data=[]; $sisa=[]; $burnData=[]; $netData=[]; $deficitData=[];
for($i=0;$i<7;$i++){
    $d = date('Y-m-d', strtotime($weekStart." +$i day"));
    $labels[] = date('D d/m', strtotime($d));
    $cons = $map[$d] ?? 0;
    $burn = $burnMap[$d] ?? 0;
    $data[] = $cons;
    $burnData[] = $burn;
    $net  = $cons - $burn;          // Net asupan setelah dikurangi olahraga
    $netData[] = $net;
    $sisa[] = max(0, $target - $cons);
    // Defisit harian = target - net. Positif = defisit (penurunan BB), Negatif = surplus.
    $deficitData[] = $target - $net;
}
$totalWeek    = array_sum($data);
$totalBurn    = array_sum($burnData);
$totalNet     = $totalWeek - $totalBurn;
$totalTarget  = $target * 7;
$weekDeficit  = $totalTarget - $totalNet;   // >0 = defisit (bagus utk diet); <0 = surplus
$avgDay = round($totalWeek/7);

// Revisi R8 (#3) — "Sisa Kalori Hari Ini" sekarang BERTAMBAH ketika user
// mencatat olahraga / pembakaran kalori. Rumus: sisa = target − konsumsi + terbakar.
// Sebelumnya (R6) sisa hanya dipengaruhi makanan, sehingga olahraga tidak
// memberi "ruang makan tambahan" — tidak intuitif untuk user diet defisit.
$todayKey      = date('Y-m-d');
$todayCons     = (int)($map[$todayKey]     ?? 0);
$todayBurn     = (int)($burnMap[$todayKey] ?? 0);
$todayNet      = $todayCons - $todayBurn;            // konsumsi bersih (info)
$todayRemain   = $target - $todayCons + $todayBurn;  // sisa = target − konsumsi + terbakar
$todayPct      = $target>0 ? min(100, max(0, ($todayNet/$target)*100)) : 0;
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$err= $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$aiEnabled = (bool) (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: ''));

// Daftar 8 minggu terakhir utk dropdown navigasi
$weekChoices = [];
for ($i=0; $i<=8; $i++){
    $s = date('Y-m-d', strtotime('monday this week -'.$i.' week'));
    $e = date('Y-m-d', strtotime('sunday this week -'.$i.' week'));
    $weekChoices[-$i] = ($i===0?'Minggu Ini':($i===1?'Minggu Lalu':$i.' minggu lalu')).' ('.date('d M', strtotime($s)).' – '.date('d M', strtotime($e)).')';
}
/* =========================================================================
 * Revisi: Meal Recommendation (pagi / siang / malam).
 * Sumber data:
 *   - Statistik lari: tabel upload_harian (7 hari terakhir, jenis mengandung
 *     jog/lari/run) — total menit & kalori terbakar mingguan.
 *   - Profil BMI: users.berat_kg & users.tinggi_cm (juga dipakai di
 *     profile.php / kesehatan.php).
 * Rekomendasi bersifat heuristik sederhana, bukan resep medis.
 * ========================================================================= */
$mrProfile = db_one("SELECT berat_kg, tinggi_cm FROM users WHERE id=$1", [$uid]) ?: [];
$mrBerat = (float)($mrProfile['berat_kg'] ?? 0);
$mrTinggi = (float)($mrProfile['tinggi_cm'] ?? 0);
$mrBmi = ($mrBerat > 0 && $mrTinggi > 0) ? round($mrBerat / pow($mrTinggi/100, 2), 1) : null;
$mrBmiCat = null;
if ($mrBmi !== null) {
    if      ($mrBmi < 18.5) $mrBmiCat = 'Kurus';
    elseif  ($mrBmi < 25)   $mrBmiCat = 'Normal';
    elseif  ($mrBmi < 30)   $mrBmiCat = 'Berlebih';
    else                    $mrBmiCat = 'Obesitas';
}
$mrRun = db_one(
    "SELECT COALESCE(SUM(kalori),0)::int AS kal,
            COALESCE(SUM(durasi_menit),0)::int AS menit,
            COUNT(*)::int AS sesi
       FROM upload_harian
      WHERE user_id=$1
        AND tanggal >= (CURRENT_DATE - INTERVAL '6 days')
        AND (jenis ILIKE '%jog%' OR jenis ILIKE '%lari%' OR jenis ILIKE '%run%')",
    [$uid]
) ?: ['kal'=>0,'menit'=>0,'sesi'=>0];
$mrRunKal   = (int)$mrRun['kal'];
$mrRunMenit = (int)$mrRun['menit'];
$mrRunSesi  = (int)$mrRun['sesi'];
$mrAktif    = $mrRunMenit >= 90; // >=90 menit lari / minggu -> aktif

/* Bangun menu pagi/siang/malam berdasarkan kategori BMI + level aktivitas. */
$mrMenus = [
  'Kurus' => [
    'pagi'  => ['Nasi merah 1 centong + telur dadar 2 butir + alpukat 1/2', 'Susu full-cream 1 gelas', 'Pisang 1 buah'],
    'siang' => ['Nasi putih 1,5 centong + ayam bakar dada+paha', 'Tumis sayur + tempe goreng 2 potong', 'Buah pepaya + air putih'],
    'malam' => ['Nasi 1 centong + ikan salmon/tongkol panggang', 'Sup ayam kentang wortel', 'Yogurt + madu 1 sdm'],
    'catatan' => 'Fokus surplus 300–500 kkal/hari untuk menaikkan berat sehat. Sertakan protein 1.6–2 g/kg BB.',
  ],
  'Normal' => [
    'pagi'  => ['Oat 40 g + susu + pisang + kacang almond', 'Telur rebus 2 butir', 'Teh hijau tanpa gula'],
    'siang' => ['Nasi merah 1 centong + ayam panggang (tanpa kulit)', 'Sayur bening bayam / capcay', 'Buah jeruk / apel'],
    'malam' => ['Nasi 3/4 centong + ikan bakar', 'Tumis brokoli + tahu', 'Buah potong + air putih'],
    'catatan' => 'Jaga defisit/isokalori sesuai target. Protein ±1.2 g/kg BB, minum 2 L/hari.',
  ],
  'Berlebih' => [
    'pagi'  => ['Oat 30 g + putih telur 3 butir + buah beri', 'Kopi hitam tanpa gula', 'Air lemon hangat'],
    'siang' => ['Nasi merah 1/2 centong + dada ayam kukus/pepes', 'Sayur rebus (bayam/kangkung) + tempe kukus', 'Buah apel / pir'],
    'malam' => ['Ikan panggang + salad sayur + minyak zaitun 1 sdt', 'Sup miso / bening', 'Air putih 2 gelas'],
    'catatan' => 'Target defisit ±500 kkal/hari. Hindari gula tambahan, gorengan, minuman manis, mie instan.',
  ],
  'Obesitas' => [
    'pagi'  => ['Putih telur 3 + sayur tumis minyak minimal', 'Kopi/teh tanpa gula', 'Air putih 2 gelas'],
    'siang' => ['Ayam/ikan panggang 100 g + sayur rebus banyak', 'Karbohidrat kompleks kecil (kentang rebus 1 kepal)', 'Buah rendah kalori (semangka/melon)'],
    'malam' => ['Sup ayam + sayuran (tanpa nasi)', 'Tahu kukus + tempe kukus', 'Air putih + teh hijau'],
    'catatan' => 'Konsultasikan ke ahli gizi. Target defisit 500–750 kkal/hari + jalan/lari 150 menit/minggu.',
  ],
];
$mrCat = $mrBmiCat ?: 'Normal';
$mrPlan = $mrMenus[$mrCat];
if ($mrAktif) {
    // pelari aktif butuh tambahan karbohidrat pemulihan
    array_unshift($mrPlan['pagi'],  'Tambahan: roti gandum + selai kacang (recovery karbohidrat pelari aktif)');
    array_unshift($mrPlan['malam'], 'Tambahan: kentang rebus / ubi 1 kepal (glikogen recovery)');
    $mrPlan['catatan'] .= ' Anda tercatat berlari '.$mrRunMenit.' menit ('.$mrRunKal.' kkal) minggu ini — pertahankan asupan karbohidrat kompleks pasca-lari.';
} else {
    $mrPlan['catatan'] .= ' Aktivitas lari mingguan Anda '.$mrRunMenit.' menit — targetkan minimal 90 menit/minggu.';
}

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Kalori Mingguan</li>
</ol></nav>

<h2 class="mb-1"><i class="bi bi-egg-fried text-warning"></i> Pencatatan Kalori Mingguan</h2>
<p class="text-muted small mb-2">Minggu <?= htmlspecialchars($weekStart) ?> – <?= htmlspecialchars($weekEnd) ?>. Target harian: <strong><?= $target ?></strong> kkal.
<?= $aiEnabled ? '<span class="badge bg-success ms-2">AI Foto Aktif</span>' : '<span class="badge bg-secondary ms-2">AI Foto Nonaktif</span>' ?></p>

<!-- Revisi: Meal Recommendation (pagi / siang / malam) — berdasarkan BMI & statistik lari mingguan. -->
<div class="card shadow-sm mb-3 border-success" id="mealReco">
  <div class="card-header bg-success-subtle text-success-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-egg-fried"></i> <strong>Meal Recommendation</strong> (Pagi / Siang / Malam)</span>
    <small class="text-muted">
      BMI:
      <?php if($mrBmi!==null): ?>
        <strong><?= $mrBmi ?></strong>
        <span class="badge bg-<?= $mrBmiCat==='Normal'?'success':($mrBmiCat==='Kurus'?'warning':'danger') ?>"><?= htmlspecialchars($mrBmiCat) ?></span>
      <?php else: ?>
        <em>belum diisi</em> — lengkapi di <a href="/profile.php">Profil</a>
      <?php endif; ?>
      &middot; Lari 7 hari: <strong><?= $mrRunMenit ?></strong> menit / <strong><?= $mrRunKal ?></strong> kkal
      (<?= $mrRunSesi ?> sesi, sumber: <a href="/riwayat.php">Riwayat</a>)
    </small>
  </div>
  <div class="card-body">
    <?php if ($mrBmi === null): ?>
      <div class="alert alert-warning small mb-3">
        <i class="bi bi-info-circle"></i> Data BMI belum tersedia. Rekomendasi di bawah memakai profil <em>Normal</em> sebagai default.
        Isi berat &amp; tinggi badan di <a href="/profile.php">Profil</a> untuk hasil yang lebih akurat.
      </div>
    <?php endif; ?>
    <div class="row g-2">
      <?php
        $mrIcons = ['pagi'=>'bi-sunrise text-warning','siang'=>'bi-sun-fill text-danger','malam'=>'bi-moon-stars-fill text-primary'];
        $mrJudul = ['pagi'=>'Sarapan (Pagi)','siang'=>'Makan Siang','malam'=>'Makan Malam'];
      ?>
      <?php foreach (['pagi','siang','malam'] as $slot): ?>
        <div class="col-12 col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2"><i class="bi <?= $mrIcons[$slot] ?>"></i> <?= $mrJudul[$slot] ?></div>
            <ul class="small mb-0 ps-3">
              <?php foreach ($mrPlan[$slot] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="alert alert-info small mt-3 mb-0">
      <i class="bi bi-lightbulb"></i>
      <strong>Catatan (kategori <?= htmlspecialchars($mrCat) ?><?= $mrAktif?' &middot; Pelari aktif':'' ?>):</strong>
      <?= $mrPlan['catatan'] ?>
    </div>
  </div>
</div>


<!-- Revisi 15 Juni 2026 — Navigasi minggu (riwayat minggu lalu, dsb) -->
<form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="?week=<?= $weekOffset-1 ?>"><i class="bi bi-chevron-left"></i> Minggu sebelumnya</a>
  <select name="week" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
    <?php foreach($weekChoices as $off=>$lab): ?>
      <option value="<?= $off ?>" <?= $off===$weekOffset?'selected':'' ?>><?= htmlspecialchars($lab) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($weekOffset < 0): ?>
    <a class="btn btn-outline-secondary btn-sm" href="?week=<?= $weekOffset+1 ?>">Minggu berikutnya <i class="bi bi-chevron-right"></i></a>
    <a class="btn btn-primary btn-sm" href="?week=0"><i class="bi bi-arrow-counterclockwise"></i> Kembali ke Minggu Ini</a>
  <?php endif; ?>
</form>

<?php if($ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-warning py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-bullseye fs-4 text-primary"></i>
    <div class="fw-bold"><?= $target ?> kkal</div><div class="small text-muted">Target Harian</div></div></div></div>
  <?php /* Revisi 19 Juni 2026 — "Total Konsumsi" sekarang dihitung NET (sudah dikurangi
       pembakaran). Sebelumnya hanya menampilkan makanan masuk, sehingga ketika user
       menambah entri "Input Pembakaran Kalori Lain (AI)" angka ini terlihat tidak turun.
       Sekarang Total Konsumsi = Makanan − Terbakar (gross & net ditampilkan di tooltip). */ ?>
  <?php $totalKonsumsiNet = max(0, $totalWeek - $totalBurn); ?>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center"
       title="Makanan masuk <?= number_format($totalWeek) ?> kkal − Terbakar <?= number_format($totalBurn) ?> kkal">
    <i class="bi bi-fire fs-4 text-danger"></i>
    <div class="fw-bold"><?= number_format($totalKonsumsiNet) ?> kkal</div>
    <div class="small text-muted">Total Konsumsi (net)</div>
    <div class="small text-muted" style="font-size:.72rem">
      Makanan <?= number_format($totalWeek) ?> − Bakar <?= number_format($totalBurn) ?>
    </div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-lightning-charge-fill fs-4 text-warning"></i>
    <div class="fw-bold"><?= $totalBurn ?> kkal</div><div class="small text-muted">Terbakar (semua)</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-3 text-center">
    <i class="bi bi-calendar3 fs-4 text-info"></i>
    <div class="fw-bold"><?= round($totalKonsumsiNet/7) ?> kkal</div><div class="small text-muted">Rata-rata net / hari</div></div></div></div>
</div>

<!-- ============================================================
     Revisi 19 Juni 2026 — Sisa Kalori HARI INI + Pembakaran "Lain" (AI)
     ============================================================ -->
<div class="row g-3 mb-3">
  <div class="col-md-5">
    <div class="card shadow-sm h-100 <?= $todayRemain>=0 ? 'border-success' : 'border-danger' ?>">
      <div class="card-header py-2 <?= $todayRemain>=0 ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
        <i class="bi bi-pie-chart-fill"></i> <strong>Sisa Kalori Hari Ini</strong>
        <span class="small ms-2"><?= htmlspecialchars(date('D, d M Y')) ?></span>
      </div>
      <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3">
          <div class="flex-fill">
            <div class="display-6 fw-bold <?= $todayRemain>=0 ? 'text-success' : 'text-danger' ?>">
              <?php /* Revisi 22 Juni 2026 R5 — perbaiki tampilan tanda. Sisa = target - net.
                       Nilai negatif berarti melebihi target (sudah ditampilkan number_format
                       dengan tanda minus). */ ?>
              <?= number_format($todayRemain) ?>
              <small class="fs-6 text-muted">kkal</small>
            </div>
            <div class="small text-muted">
              <?php if ($todayRemain>=0): ?>
                masih bisa dikonsumsi hari ini
              <?php else: ?>
                <strong>melebihi target</strong> sebanyak <?= number_format(abs($todayRemain)) ?> kkal
              <?php endif; ?>
            </div>
            <div class="progress mt-2" style="height:8px">
              <div class="progress-bar <?= $todayPct>=100 ? 'bg-danger' : ($todayPct>=80?'bg-warning':'bg-success') ?>"
                   role="progressbar" style="width:<?= number_format($todayPct,1) ?>%"></div>
            </div>
            <div class="small text-muted mt-1">
              Konsumsi makanan <b><?= number_format($todayCons) ?></b>
              / Target <b><?= number_format($target) ?></b> kkal
              · Terbakar olahraga <b><?= number_format($todayBurn) ?></b> kkal
              · <b>Sisa = Target − Konsumsi + Terbakar = <?= number_format($todayRemain) ?></b>
              (<?= number_format($todayPct,1) ?>% terpakai).
              <br>Olahraga menambah "ruang makan" sebanyak kalori yang dibakar.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm h-100 border-info">
      <div class="card-header py-2 bg-info-subtle text-info-emphasis">
        <i class="bi bi-robot"></i> <strong>Input Pembakaran Kalori Lain (AI)</strong>
        <small class="ms-2 text-muted">Selain makanan & selain olahraga utama</small>
      </div>
      <div class="card-body py-2">
        <p class="small text-muted mb-2">
          Tulis aktivitas pembakaran kalori yang tidak masuk di olahraga utama
          (cth: <em>"jalan kaki ke pasar 25 menit"</em>, <em>"naik turun tangga kantor selama 15 menit"</em>,
          <em>"mengasuh anak balita aktif selama 2 jam"</em>). AI akan memperkirakan kalori-nya
          lalu disimpan ke akumulasi pembakaran harian.
        </p>
        <form id="lainForm" class="row g-2 align-items-end">
          <div class="col-12">
            <textarea id="lainPrompt" class="form-control form-control-sm" rows="2"
                      placeholder="cth: jalan kaki ke pasar 25 menit, beban belanjaan ~3 kg"></textarea>
          </div>
          <div class="col-md-9 d-flex gap-2 flex-wrap">
            <button type="button" id="btnLainAI" class="btn btn-info btn-sm">
              <i class="bi bi-stars"></i> Estimasi dengan AI
            </button>
            <span id="lainStat" class="small text-muted align-self-center"></span>
          </div>
        </form>

        <!-- Hasil AI (dapat diedit) → Simpan -->
        <form method="post" id="lainSaveForm" class="row g-2 mt-2" style="display:none">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="lain_add">
          <input type="hidden" name="rincian" id="lainRincian">
          <div class="col-4"><label class="form-label small mb-0">Tanggal</label>
            <input type="date" name="tanggal" id="lainTgl" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-8"><label class="form-label small mb-0">Aktivitas</label>
            <input type="text" name="deskripsi" id="lainDesk" class="form-control form-control-sm" required></div>
          <div class="col-4"><label class="form-label small mb-0">Durasi (menit)</label>
            <input type="number" name="durasi_menit" id="lainDur" class="form-control form-control-sm" min="0" value="0"></div>
          <div class="col-4"><label class="form-label small mb-0">Kalori (kkal)</label>
            <input type="number" name="kalori" id="lainKal" class="form-control form-control-sm" min="0" value="0" required></div>
          <div class="col-4 d-flex align-items-end">
            <button class="btn btn-success btn-sm w-100"><i class="bi bi-save"></i> Simpan</button>
          </div>
        </form>

        <?php if (!empty($lainToday)): ?>
        <hr class="my-2">
        <div class="small fw-semibold mb-1">Hari ini (<?= count($lainToday) ?>):</div>
        <ul class="list-group list-group-flush small">
          <?php foreach ($lainToday as $L): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
              <div>
                <span class="fw-semibold"><?= htmlspecialchars($L['deskripsi']) ?></span>
                <span class="text-muted">— <?= (int)$L['durasi_menit'] ?> mnt, <b class="text-warning"><?= (int)$L['kalori'] ?> kkal</b></span>
                <?php if (!empty($L['rincian'])): ?>
                  <div class="text-muted small"><?= htmlspecialchars($L['rincian']) ?></div>
                <?php endif; ?>
              </div>
              <form method="post" onsubmit="return confirm('Hapus aktivitas ini?')" class="m-0">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="lain_delete">
                <input type="hidden" name="id" value="<?= (int)$L['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';
  var btn = document.getElementById('btnLainAI');
  var inp = document.getElementById('lainPrompt');
  var stat = document.getElementById('lainStat');
  var saveForm = document.getElementById('lainSaveForm');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    var q = (inp.value||'').trim();
    if (!q){ stat.textContent='Tulis aktivitas dulu.'; return; }
    btn.disabled = true;
    var oh = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menanya AI…';
    stat.textContent = '';
    try {
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('task', 'kalori_lain');
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok){ stat.innerHTML = '<span class="text-danger">Gagal: '+(j.err||'?')+'</span>'; }
      else {
        document.getElementById('lainDesk').value = j.aktivitas || q.substring(0,80);
        document.getElementById('lainDur').value  = j.durasi_menit || 0;
        document.getElementById('lainKal').value  = j.kalori || 0;
        document.getElementById('lainRincian').value = j.rincian || '';
        saveForm.style.display = 'flex';
        stat.innerHTML = '<i class="bi bi-check-circle text-success"></i> AI: <b>'+(j.kalori||0)+' kkal</b> · '
                       + (j.rincian||'').replace(/[<>]/g,'') + ' — silakan edit lalu Simpan.';
      }
    } catch(e){ stat.innerHTML = '<span class="text-danger">Error: '+e.message+'</span>'; }
    btn.disabled = false; btn.innerHTML = oh;
  });
})();
</script>

<!-- Revisi 18 Juni 2026 (Lanjutan) — Pengaturan sumber Defisit Kalori -->
<div class="card shadow-sm mb-3">
  <div class="card-header py-2"><i class="bi bi-sliders"></i> Sumber Defisit Kalori</div>
  <div class="card-body py-2">
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="defisit_setting">
      <div class="col-md-6">
        <label class="form-label small mb-1">Ambil kalori terbakar dari:</label>
        <select name="sumber" class="form-select form-select-sm">
          <option value="auto"     <?= $defSumber==='auto'?'selected':'' ?>>Otomatis — semua workout (kalori_log)</option>
          <option value="jogging"  <?= $defSumber==='jogging'?'selected':'' ?>>Riwayat Jogging saya (riwayat.php)</option>
          <option value="manual"   <?= $defSumber==='manual'?'selected':'' ?>>Input manual (kkal/hari)</option>
          <option value="gabungan" <?= $defSumber==='gabungan'?'selected':'' ?>>Gabungan: Jogging + Manual</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Manual defisit (kkal / hari)</label>
        <input type="number" min="0" max="5000" name="manual_harian" value="<?= (int)$defManual ?>"
               class="form-control form-control-sm" placeholder="cth: 300">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Simpan</button>
      </div>
      <div class="col-12 small text-muted mt-1">
        Mode aktif: <strong class="text-capitalize"><?= htmlspecialchars($defSumber) ?></strong>.
        <?php if ($defSumber==='jogging' || $defSumber==='gabungan'): ?>
          Kalori dijumlahkan dari entri <em>upload_harian</em> dengan jenis mengandung "jog"/"lari"/"run" di halaman <a href="/riwayat.php">Riwayat</a>.
        <?php endif; ?>
        <?php if ($defSumber==='manual' || $defSumber==='gabungan'): ?>
          Nilai manual ditambahkan ke setiap hari pada minggu berjalan (sampai hari ini).
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Revisi 18 Juni 2026 — Defisit / Surplus mingguan (memperhitungkan olahraga) -->
<div class="row g-2 mb-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100 <?= $weekDeficit>=0 ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
      <div class="card-body p-3">
        <div class="d-flex align-items-center gap-3">
          <i class="bi <?= $weekDeficit>=0 ? 'bi-arrow-down-circle-fill text-success' : 'bi-arrow-up-circle-fill text-danger' ?> fs-1"></i>
          <div class="flex-fill">
            <div class="small text-muted"><?= $weekDeficit>=0 ? 'Defisit Kalori Minggu Ini' : 'Surplus Kalori Minggu Ini' ?></div>
            <div class="fs-3 fw-bold <?= $weekDeficit>=0 ? 'text-success' : 'text-danger' ?>">
              <?= ($weekDeficit>=0?'−':'+') ?><?= number_format(abs($weekDeficit)) ?> kkal
            </div>
            <div class="small text-muted">
              Konsumsi <strong><?= number_format($totalWeek) ?></strong>
              − Terbakar <strong><?= number_format($totalBurn) ?></strong>
              = Net <strong><?= number_format($totalNet) ?></strong> kkal
              <br>Target mingguan: <strong><?= number_format($totalTarget) ?></strong> kkal
              <?php if ($weekDeficit>=0): ?>
                · <span class="text-success">cocok untuk penurunan berat badan (~<?= number_format($weekDeficit/7700, 2) ?> kg)</span>
              <?php else: ?>
                · <span class="text-danger">cenderung penambahan berat badan</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2"><i class="bi bi-activity"></i> Aktivitas Pembakaran Minggu Ini</div>
      <div class="card-body p-2">
        <?php if (empty($burnDetail)): ?>
          <div class="small text-muted py-2">Belum ada catatan olahraga minggu ini. Catat di
            <a href="/kalori_badminton.php">Badminton</a>, <a href="/kalori_futsal.php">Futsal</a>,
            <a href="/kalori_pingpong.php">Pingpong</a>, <a href="/kalori_renang.php">Renang</a>.</div>
        <?php else: ?>
          <table class="table table-sm mb-0 small">
            <thead class="table-light"><tr><th>Jenis</th><th class="text-end">Sesi</th><th class="text-end">Menit</th><th class="text-end">Kkal</th></tr></thead>
            <tbody>
            <?php foreach($burnDetail as $b): ?>
              <tr><td class="text-capitalize"><?= htmlspecialchars($b['jenis']) ?></td>
                  <td class="text-end"><?= (int)$b['sesi'] ?></td>
                  <td class="text-end"><?= (int)$b['menit'] ?></td>
                  <td class="text-end fw-semibold text-warning"><?= (int)$b['kalori'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-bar-chart-line"></i> Statistik Konsumsi vs Sisa Target</div>
      <div class="card-body"><canvas id="weekChart" height="160"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-plus-circle"></i> Catat Makanan</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="add">
          <div class="col-7"><label class="form-label small">Tanggal</label><input class="form-control form-control-sm" type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-5"><label class="form-label small">Waktu</label><input class="form-control form-control-sm" type="time" name="waktu" value="<?= date('H:i') ?>"></div>
          <div class="col-8"><label class="form-label small">Nama makanan</label><input class="form-control form-control-sm" name="nama_makanan" placeholder="cth: Nasi padang"></div>
          <div class="col-4"><label class="form-label small">Kalori (kkal)</label><input class="form-control form-control-sm" type="number" min="0" name="kalori" placeholder="0"></div>
          <div class="col-12"><label class="form-label small">Foto makanan (opsional, AI dapat menebak kalori)</label>
            <!-- Revisi 18 Juni 2026: capture="environment" → di mobile langsung buka kamera belakang.
                 Tombol "Pilih dari Galeri" sebagai fallback agar tetap fleksibel.
                 Auto-resize sisi terpanjang → 1280 px sebelum upload supaya tidak ditolak server
                 (upload_max_filesize default 2 MB di banyak hosting). -->
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
            <div class="d-flex gap-2 flex-wrap align-items-start">
              <label class="btn btn-primary btn-sm mb-0 flex-fill">
                <i class="bi bi-camera-fill"></i> Buka Kamera Langsung
                <input class="d-none" type="file" name="foto" accept="image/*" capture="environment" id="kmFoto" onchange="kmFotoPreview(this)">
              </label>
              <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="kmPickGallery()">
                <i class="bi bi-image"></i> Pilih dari Galeri
              </button>
            </div>
            <div id="kmFotoPrev" class="small text-muted mt-2">Belum ada foto dipilih.</div>
            <div class="form-text small">Foto akan otomatis dikecilkan ke max 1280 px agar tidak ditolak limit upload server.</div>
          </div>
          <script>
          // Tombol Galeri: hapus atribut capture sementara → buka picker file biasa.
          function kmPickGallery(){
            var inp = document.getElementById('kmFoto');
            if (!inp) return;
            inp.removeAttribute('capture');
            inp.click();
            // Pasang kembali capture untuk klik berikut (kamera).
            setTimeout(function(){ inp.setAttribute('capture','environment'); }, 1000);
          }
          function kmFotoPreview(inp){
            var box = document.getElementById('kmFotoPrev');
            if (!(inp.files && inp.files[0])){ box.textContent = 'Belum ada foto dipilih.'; return; }
            var f = inp.files[0];
            // Revisi 18 Juni 2026: auto-resize sisi terpanjang → 1280 px sebelum upload
            // supaya tidak ditolak upload_max_filesize / post_max_size.
            var MAX = 1280;
            var img = new Image();
            img.onload = function(){
              var w=img.width, h=img.height, scale=Math.min(1, MAX/Math.max(w,h));
              if (scale >= 1 && f.size < 4*1024*1024){
                box.innerHTML = '<img src="'+img.src+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+f.name+' ('+(Math.round(f.size/1024))+' KB)</span>';
                return;
              }
              var c=document.createElement('canvas'); c.width=Math.round(w*scale); c.height=Math.round(h*scale);
              c.getContext('2d').drawImage(img,0,0,c.width,c.height);
              c.toBlob(function(blob){
                if(!blob){ return; }
                var nf = new File([blob], (f.name||'foto.jpg').replace(/\.(heic|heif|png|webp)$/i,'.jpg'), {type:'image/jpeg'});
                var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
                var url = URL.createObjectURL(nf);
                box.innerHTML = '<img src="'+url+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+nf.name+' ('+(Math.round(nf.size/1024))+' KB · dikompres dari '+(Math.round(f.size/1024))+' KB)</span>';
              }, 'image/jpeg', 0.85);
            };
            img.onerror = function(){
              box.innerHTML = '<img src="'+URL.createObjectURL(f)+'" style="max-height:120px;border-radius:8px;border:1px solid #ddd"> <span class="ms-2">'+f.name+'</span>';
            };
            img.src = URL.createObjectURL(f);
          }
          </script>
          <?php if($aiEnabled): ?>
          <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="use_ai" id="uai" value="1" checked>
            <label class="form-check-label small" for="uai">Gunakan AI untuk estimasi kalori dari foto</label></div>
          <?php endif; ?>
          <?php /* Revisi 22 Juni 2026 R7 — catatan diubah ke textarea dengan scrolling agar teks panjang tidak terpotong. */ ?>
          <div class="col-12"><label class="form-label small">Catatan</label>
            <textarea class="form-control form-control-sm" name="catatan" rows="3"
              style="max-height:160px;overflow-y:auto;resize:vertical;white-space:pre-wrap;"
              placeholder="Tambahkan catatan (porsi, lokasi, dll). Bisa banyak baris — scroll bila panjang."></textarea>
          </div>
          <div class="col-12"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Simpan</button></div>
        </form>
        <hr>
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="target">
          <div class="col-8"><input class="form-control form-control-sm" type="number" min="500" name="target_harian" value="<?= $target ?>"></div>
          <div class="col-4"><button class="btn btn-outline-secondary btn-sm w-100">Set Target</button></div>
        </form>
        <?php if(!$aiEnabled): ?>
          <div class="alert alert-info small mt-2 mb-0"><i class="bi bi-info-circle"></i> Untuk mengaktifkan AI estimasi kalori dari foto, set environment variable <code>GEMINI_API_KEY</code> di server.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-list-ul"></i> Riwayat <?= $weekOffset===0?'Minggu Ini':($weekOffset===-1?'Minggu Lalu':abs($weekOffset).' Minggu Lalu') ?> (<?= count($logs) ?> entri)</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Tanggal</th><th>Waktu</th><th>Foto</th><th>Makanan</th><th class="text-end">Kalori</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
      <?php foreach($logs as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['tanggal']) ?></td>
          <td><?= htmlspecialchars(substr($r['waktu'],0,5)) ?></td>
          <td><?php if(!empty($r['foto_url'])): ?>
            <img src="<?= htmlspecialchars($r['foto_url']) ?>"
                 class="km-foto-thumb"
                 data-full="<?= htmlspecialchars($r['foto_url']) ?>"
                 data-nama="<?= htmlspecialchars($r['nama_makanan']) ?>"
                 alt="Foto <?= htmlspecialchars($r['nama_makanan']) ?>"
                 style="width:50px;height:50px;object-fit:cover;border-radius:6px;cursor:zoom-in"
                 title="Klik untuk perbesar">
          <?php endif; ?></td>
          <td><?= htmlspecialchars($r['nama_makanan']) ?>
            <?php if($r['ai_estimasi']==='t'||$r['ai_estimasi']===true||$r['ai_estimasi']==='1'): ?><span class="badge bg-success-subtle text-success ms-1">AI</span><?php endif; ?>
            <?php
              /* Revisi 19 Juni 2026 Part R — detail makro & rincian AI dipindah ke popup. */
              $macroMap = [
                'karbohidrat_g'=>['Karbohidrat','g'],
                'protein_g'    =>['Protein','g'],
                'lemak_g'      =>['Lemak','g'],
                'serat_g'      =>['Serat','g'],
                'gula_g'       =>['Gula','g'],
                'sodium_mg'    =>['Sodium','mg'],
              ];
              $hasMacro = false;
              foreach ($macroMap as $k=>$_) { if (isset($r[$k]) && $r[$k]!==null && $r[$k]!=='') { $hasMacro = true; break; } }
              $hasDetail = $hasMacro || !empty($r['ai_detail']) || !empty($r['catatan']);
              $detailPayload = [
                'nama'    => $r['nama_makanan'],
                'tanggal' => $r['tanggal'],
                'waktu'   => substr($r['waktu'],0,5),
                'kalori'  => (int)$r['kalori'],
                'catatan' => $r['catatan'] ?? '',
                'detail'  => $r['ai_detail'] ?? '',
                'foto'    => $r['foto_url'] ?? '',
                'makro'   => [],
              ];
              foreach ($macroMap as $k=>$meta) {
                if (isset($r[$k]) && $r[$k]!==null && $r[$k]!=='') {
                  $val = rtrim(rtrim(number_format((float)$r[$k],2,'.',''),'0'),'.');
                  $detailPayload['makro'][] = ['label'=>$meta[0],'value'=>$val,'unit'=>$meta[1]];
                }
              }
              if ($hasDetail): ?>
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 km-detail-btn"
                        data-detail='<?= htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
                        title="Lihat detail gizi"><i class="bi bi-info-circle"></i> Detail</button>
              <?php endif; ?>
          </td>
          <td class="text-end fw-semibold text-danger"><?= (int)$r['kalori'] ?></td>
          <td class="small text-muted" style="max-width:220px;word-break:break-word;overflow-wrap:anywhere;"><?= htmlspecialchars(mb_strimwidth((string)($r['catatan']??''),0,140,'…')) ?></td>

          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-kal"
                    data-id="<?= (int)$r['id'] ?>"
                    data-tanggal="<?= htmlspecialchars($r['tanggal']) ?>"
                    data-waktu="<?= htmlspecialchars(substr($r['waktu'],0,5)) ?>"
                    data-nama="<?= htmlspecialchars($r['nama_makanan']) ?>"
                    data-kalori="<?= (int)$r['kalori'] ?>"
                    data-catatan="<?= htmlspecialchars($r['catatan'] ?? '') ?>"
                    title="Edit"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$logs): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada catatan minggu ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Modal Edit Riwayat Kalori (Revisi 13 Juni 2026) -->
<div class="modal fade" id="editKalModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="edit">
  <input type="hidden" name="id" id="ek_id">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Entri Kalori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-7"><label class="form-label small">Tanggal</label><input type="date" name="tanggal" id="ek_tgl" class="form-control form-control-sm" required></div>
    <div class="col-5"><label class="form-label small">Waktu</label><input type="time" name="waktu" id="ek_jam" class="form-control form-control-sm" required></div>
    <div class="col-8"><label class="form-label small">Nama makanan</label><input name="nama_makanan" id="ek_nama" class="form-control form-control-sm" required></div>
    <div class="col-4"><label class="form-label small">Kalori (kkal)</label><input type="number" min="0" name="kalori" id="ek_kal" class="form-control form-control-sm" required></div>
    <?php /* Revisi 22 Juni 2026 R7 — textarea scrolling. */ ?>
    <div class="col-12"><label class="form-label small">Catatan</label>
      <textarea name="catatan" id="ek_cat" class="form-control form-control-sm" rows="3"
        style="max-height:180px;overflow-y:auto;resize:vertical;white-space:pre-wrap;"></textarea>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<script>
// Revisi 18 Juni 2026 — Wrap dgn window.load supaya bootstrap.bundle.js (di footer.php)
// SUDAH siap saat new bootstrap.Modal() dipanggil. Sebelumnya ReferenceError → tombol Edit & klik foto tidak bereaksi.
function kmInitEditAndZoom(){
  if (typeof bootstrap === 'undefined' || !bootstrap.Modal){ setTimeout(kmInitEditAndZoom, 120); return; }
  var editEl = document.getElementById('editKalModal');
  if (editEl){
    var m = new bootstrap.Modal(editEl);
    document.querySelectorAll('.btn-edit-kal').forEach(function(b){
      b.addEventListener('click', function(){
        document.getElementById('ek_id').value   = this.dataset.id;
        document.getElementById('ek_tgl').value  = this.dataset.tanggal;
        document.getElementById('ek_jam').value  = this.dataset.waktu;
        document.getElementById('ek_nama').value = this.dataset.nama;
        document.getElementById('ek_kal').value  = this.dataset.kalori;
        document.getElementById('ek_cat').value  = this.dataset.catatan || '';
        m.show();
      });
    });
  }
  var zEl = document.getElementById('zoomFotoModal');
  if (zEl){
    var zm = new bootstrap.Modal(zEl);
    var imgEl = document.getElementById('zoomFotoImg');
    var ttlEl = document.getElementById('zoomFotoTitle');
    document.querySelectorAll('.km-foto-thumb').forEach(function(t){
      t.addEventListener('click', function(){
        imgEl.src = this.dataset.full || this.src;
        ttlEl.textContent = this.dataset.nama || 'Foto Makanan';
        zm.show();
      });
    });
  }
}
if (document.readyState === 'complete') kmInitEditAndZoom();
else window.addEventListener('load', kmInitEditAndZoom);
</script>

<!-- Revisi 19 Juni 2026 Part R — Modal Detail Gizi (popup detail riwayat makanan) -->
<div class="modal fade" id="detailGiziModal" tabindex="-1">
  <?php /* Revisi 22 Juni 2026 R7 — modal scrollable supaya catatan & makro panjang tidak terpotong. */ ?>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title"><i class="bi bi-clipboard-data text-success"></i> Detail Gizi & Catatan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="dgBody">
        <div class="text-muted small">Memuat…</div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<style>
/* Revisi 19 Juni 2026 (R2) — fix layout Detail Gizi: cegah teks nabrak. */
#detailGiziModal .modal-body{word-break:break-word;overflow-wrap:anywhere;}
#detailGiziModal .dg-head{display:flex;gap:.6rem;align-items:flex-start;flex-wrap:wrap;}
#detailGiziModal .dg-head img{width:72px;height:72px;object-fit:cover;border-radius:8px;flex:0 0 72px;}
#detailGiziModal .dg-head .dg-meta{min-width:0;flex:1;}
#detailGiziModal .dg-head .dg-nama{font-weight:700;line-height:1.25;word-break:break-word;}
#detailGiziModal table.dg-makro{margin-bottom:.75rem;}
#detailGiziModal table.dg-makro td{padding:.3rem .5rem;vertical-align:top;}
#detailGiziModal .dg-detail{white-space:pre-wrap;word-break:break-word;}
#detailGiziModal .dg-catatan-box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .65rem;}
[data-bs-theme=dark] #detailGiziModal .dg-catatan-box{background:#0b1220;border-color:#1f2937;}
#detailGiziModal .dg-catatan-box .dg-catatan-text{white-space:pre-wrap;word-break:break-word;line-height:1.4;margin-top:.25rem;}
#detailGiziModal .dg-ai-json{margin:0;padding:.5rem;background:#0f172a;color:#e2e8f0;border-radius:6px;font-size:.72rem;max-height:220px;overflow:auto;}
/* Revisi 22 Juni 2026 R6 — kartu makro nutrien yang rapih dan compact. */
#detailGiziModal .dg-macro-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem;}
@media (min-width:520px){#detailGiziModal .dg-macro-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
#detailGiziModal .dg-macro-cell{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:.45rem .55rem;}
[data-bs-theme=dark] #detailGiziModal .dg-macro-cell{background:#0b1220;border-color:#1f2937;}
#detailGiziModal .dg-macro-label{font-size:.72rem;color:#475569;font-weight:600;line-height:1.1;margin-bottom:.15rem;}
[data-bs-theme=dark] #detailGiziModal .dg-macro-label{color:#cbd5e1;}
#detailGiziModal .dg-macro-val{font-size:.95rem;font-weight:700;color:#0f172a;}
[data-bs-theme=dark] #detailGiziModal .dg-macro-val{color:#e2e8f0;}
#detailGiziModal .dg-macro-val .dg-unit{font-size:.7rem;font-weight:500;color:#64748b;margin-left:2px;}

</style>
<script>
function kmInitDetailGizi(){
  if (typeof bootstrap === 'undefined' || !bootstrap.Modal){ setTimeout(kmInitDetailGizi, 120); return; }
  var el = document.getElementById('detailGiziModal'); if (!el) return;
  var modal = new bootstrap.Modal(el);
  var body  = document.getElementById('dgBody');
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){
    return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
  });}
  // Revisi 19 Juni 2026 (R2) — kalau catatan berupa "AI: {...json...}" tampilkan
  // sebagai blok JSON yg dapat di-scroll, bukan satu baris panjang yang nabrak.
  function renderCatatan(catRaw){
    if (!catRaw) return '';
    var raw = String(catRaw).trim();
    var jsonMatch = raw.match(/^AI\s*:\s*(\{[\s\S]*\})\s*$/i);
    if (jsonMatch) {
      var pretty = jsonMatch[1];
      try { pretty = JSON.stringify(JSON.parse(jsonMatch[1]), null, 2); } catch(e){}
      return '<div class="dg-catatan-box mt-2">'+
        '<div class="small"><b><i class="bi bi-robot"></i> Catatan AI (rincian):</b></div>'+
        '<pre class="dg-ai-json mt-1">'+esc(pretty)+'</pre>'+
      '</div>';
    }
    return '<div class="dg-catatan-box mt-2">'+
      '<div class="small"><b>Catatan:</b></div>'+
      '<div class="small dg-catatan-text">'+esc(raw)+'</div>'+
    '</div>';
  }
  // Revisi 22 Juni 2026 R6 — tampilkan SEMUA makronutrien (protein/karbo/lemak/serat/gula/sodium)
  // walaupun nilainya kosong, supaya user tahu apa saja yang dianalisis AI. Nilai yang
  // tidak tersedia diberi tanda "—". Tata letak diubah ke kartu ringkas berwarna agar lebih rapih.
  var MACRO_FIELDS = [
    {key:'protein_g',     label:'Protein',     unit:'g',  icon:'bi-egg-fried',     color:'#f97316'},
    {key:'karbohidrat_g', label:'Karbohidrat', unit:'g',  icon:'bi-basket',        color:'#0ea5e9'},
    {key:'lemak_g',       label:'Lemak',       unit:'g',  icon:'bi-droplet-half',  color:'#eab308'},
    {key:'serat_g',       label:'Serat',       unit:'g',  icon:'bi-flower2',       color:'#16a34a'},
    {key:'gula_g',        label:'Gula',        unit:'g',  icon:'bi-cup-straw',     color:'#db2777'},
    {key:'sodium_mg',     label:'Sodium',      unit:'mg', icon:'bi-shaker',        color:'#64748b'}
  ];
  function render(d){
    var html = '<div class="dg-head mb-2">';
    if (d.foto) html += '<img src="'+esc(d.foto)+'" alt="">';
    html += '<div class="dg-meta">'+
      '<div class="dg-nama">'+esc(d.nama)+'</div>'+
      '<div class="small text-muted">'+esc(d.tanggal)+' · '+esc(d.waktu)+'</div>'+
      '<div class="mt-1"><span class="badge bg-danger">'+(d.kalori|0)+' kkal</span></div>'+
    '</div></div>';

    // Index makro dari payload (array of {label,value,unit}) menjadi map by label.
    var byLabel = {};
    (d.makro||[]).forEach(function(m){ byLabel[m.label] = m; });
    var hasAny = (d.makro||[]).length > 0;

    html += '<div class="small fw-semibold mb-1"><i class="bi bi-clipboard-pulse text-success"></i> Makro Nutrien</div>';
    html += '<div class="dg-macro-grid mb-2">';
    MACRO_FIELDS.forEach(function(f){
      var m = byLabel[f.label];
      var val = m ? (esc(m.value)+' <span class="dg-unit">'+esc(m.unit)+'</span>') : '<span class="text-muted">—</span>';
      html += '<div class="dg-macro-cell" style="border-left:3px solid '+f.color+'">'+
                '<div class="dg-macro-label"><i class="bi '+f.icon+'" style="color:'+f.color+'"></i> '+f.label+'</div>'+
                '<div class="dg-macro-val">'+val+'</div>'+
              '</div>';
    });
    html += '</div>';
    if (!hasAny) {
      html += '<div class="small text-muted mb-2"><i class="bi bi-info-circle"></i> Entri ini belum punya data makro '+
              '(entri lama atau tanpa AI). Edit ulang dengan foto + AI untuk mendapatkan rincian gizi.</div>';
    }

    if (d.detail) html += '<div class="alert alert-success py-2 small mb-2 dg-detail"><i class="bi bi-stars"></i> '+esc(d.detail)+'</div>';
    html += renderCatatan(d.catatan);
    body.innerHTML = html;
  }
  document.querySelectorAll('.km-detail-btn').forEach(function(b){
    b.addEventListener('click', function(){
      try { render(JSON.parse(this.dataset.detail||'{}')); }
      catch(e){ body.innerHTML = '<div class="text-danger small">Gagal membaca detail.</div>'; }
      modal.show();
    });
  });
}
if (document.readyState === 'complete') kmInitDetailGizi();
else window.addEventListener('load', kmInitDetailGizi);
</script>


<script>
// Revisi 18 Juni 2026 — Chart kini menampilkan Konsumsi vs Terbakar vs Defisit harian.
window.addEventListener('load', function(){
  if (typeof Chart === 'undefined') return;
  var ctx = document.getElementById('weekChart'); if (!ctx) return;
  new Chart(ctx, {
    type:'bar',
    data:{ labels: <?= json_encode($labels) ?>,
      datasets:[
        {label:'Konsumsi (kkal)', data: <?= json_encode($data) ?>, backgroundColor:'#dc3545', stack:'a'},
        {label:'Terbakar olahraga (kkal)', data: <?= json_encode($burnData) ?>, backgroundColor:'#f59e0b', stack:'b'},
        {label:'Defisit (+) / Surplus (−) harian', data: <?= json_encode($deficitData) ?>, type:'line',
          borderColor:'#16a34a', backgroundColor:'#16a34a', tension:0.3, yAxisID:'y1'}
      ]
    },
    options:{ responsive:true,
      scales:{
        x:{stacked:false},
        y:{beginAtZero:true, title:{display:true,text:'kkal'}},
        y1:{beginAtZero:false, position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'Defisit / Surplus'}}
      },
      plugins:{ tooltip:{ callbacks:{ label:function(c){
        if (c.dataset.label.indexOf('Defisit')>=0){
          var v=c.parsed.y; return c.dataset.label+': '+(v>=0?'−':'+')+Math.abs(v)+' kkal';
        }
        return c.dataset.label+': '+c.parsed.y+' kkal';
      }}}}
    }
  });
});
</script>

<!-- Revisi 17 Juni 2026 Part J — Modal Zoom Foto Makanan -->
<div class="modal fade" id="zoomFotoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-zoom-in"></i> <span id="zoomFotoTitle">Foto Makanan</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-2" style="background:#0f172a">
        <img id="zoomFotoImg" src="" alt="" style="max-width:100%;max-height:80vh;border-radius:8px">
      </div>
    </div>
  </div>
</div>
<!-- Inisialisasi modal zoom dilakukan oleh kmInitEditAndZoom() di atas (load-safe). -->

<?php /* ================= Revisi Juli 2026 R3 =================
   - Spoiler (collapsible) untuk section-section tertentu
   - Reorder: "Catat Makanan" pindah tepat di bawah "Sisa Kalori Hari Ini"
   - Hapus card "Defisit Kalori Minggu Ini"
 ==========================================================*/ ?>
<style>
  .kk-spoiler-head{ cursor:pointer; user-select:none; }
  .kk-spoiler-head .kk-chev{ transition: transform .2s ease; margin-left:.5rem; }
  .kk-spoiler-head[aria-expanded="false"] .kk-chev{ transform: rotate(-90deg); }
</style>
<script>
(function(){
  var LABELS = [
    'Sisa Kalori Hari Ini',
    'Input Pembakaran Kalori Lain (AI)',
    'Sumber Defisit Kalori',
    'Aktivitas Pembakaran Minggu Ini',
    'Statistik Konsumsi',
    'Catat Makanan',
    'Riwayat '
  ];
  function norm(s){ return (s||'').replace(/\s+/g,' ').trim(); }
  function matchLabel(t){
    t = norm(t);
    return LABELS.some(function(l){ return t.indexOf(l) === 0 || t.indexOf(l) !== -1; });
  }

  function run(){
    // 1) Hapus card "Defisit/Surplus Kalori Minggu Ini"
    document.querySelectorAll('.card').forEach(function(card){
      var t = norm(card.textContent).slice(0, 80);
      if (/^(Defisit|Surplus) Kalori Minggu Ini/i.test(t) ||
          t.indexOf('Defisit Kalori Minggu Ini') !== -1 && card.querySelector('.fs-3')) {
        // Hapus wrapper col-* terdekat kalau ada
        var col = card.closest('[class*="col-"]');
        (col || card).remove();
      }
    });

    // 2) Pindahkan "Catat Makanan" ke bawah "Sisa Kalori Hari Ini"
    var catatCard = null;
    document.querySelectorAll('.card').forEach(function(c){
      var h = c.querySelector(':scope > .card-header');
      if (h && norm(h.textContent).indexOf('Catat Makanan') !== -1) catatCard = c;
    });
    var sisaCard = null;
    document.querySelectorAll('.card').forEach(function(c){
      var h = c.querySelector(':scope > .card-header');
      if (h && norm(h.textContent).indexOf('Sisa Kalori Hari Ini') !== -1) sisaCard = c;
    });
    if (catatCard && sisaCard) {
      var catatCol = catatCard.closest('[class*="col-"]') || catatCard;
      var sisaRow  = sisaCard.closest('.row') || sisaCard.parentElement;
      // Bungkus dalam row baru supaya lebar penuh
      var newRow = document.createElement('div');
      newRow.className = 'row g-3 mb-3';
      var newCol = document.createElement('div');
      newCol.className = 'col-12';
      newCol.appendChild(catatCard);
      newRow.appendChild(newCol);
      // sisipkan setelah row Sisa Kalori
      sisaRow.parentNode.insertBefore(newRow, sisaRow.nextSibling);
      // Hapus col-lg-5 lama kalau sekarang kosong
      if (catatCol && catatCol !== catatCard && !catatCol.children.length) catatCol.remove();
    }

    // 3) Jadikan section berlabel sebagai spoiler
    document.querySelectorAll('.card').forEach(function(card){
      var h = card.querySelector(':scope > .card-header');
      if (!h) return;
      if (h.dataset.kkSpoiler === '1') return;
      var t = norm(h.textContent);
      if (!matchLabel(t)) return;
      // Kumpulkan semua anak setelah header → bungkus dalam collapse
      var kids = Array.from(card.children).filter(function(x){ return x !== h; });
      if (!kids.length) return;
      var id = 'kkspoil_' + Math.random().toString(36).slice(2,9);
      var wrap = document.createElement('div');
      wrap.className = 'collapse';
      wrap.id = id;
      kids.forEach(function(k){ wrap.appendChild(k); });
      card.appendChild(wrap);
      h.classList.add('kk-spoiler-head');
      h.setAttribute('role','button');
      h.setAttribute('aria-expanded','false');
      h.setAttribute('aria-controls', id);
      var chev = document.createElement('i');
      chev.className = 'bi bi-chevron-down kk-chev float-end';
      h.appendChild(chev);
      h.dataset.kkSpoiler = '1';
      h.addEventListener('click', function(ev){
        if (ev.target.closest('button, a, input, select, textarea, form')) return;
        var open = wrap.classList.toggle('show');
        h.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

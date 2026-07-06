<?php
// Revisi R2 (Juli 2026) — buffer output supaya warning/notice PHP tidak
// mencemari body JSON (penyebab '<br />' di dalam JSON parse client-side).
if (!ob_get_level()) ob_start();
/**
 * Revisi 16 Juni 2026 — Endpoint AI umum (Google Gemini 2.5 Flash).
 * Task yang didukung:
 *  - coach        : AI Running Coach (analisa statistik lari → saran latihan)
 *  - tanya_islami : Tanya jawab keislaman (referensi Qur'an/Hadist umum)
 *  - safety       : AI Safety monitoring untuk live tracking
 *  - chat         : free-form prompt (fallback)
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/ai_gemini.php';
require_login();
header('Content-Type: application/json');
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'] ?? 0, [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
        if (ob_get_level()) ob_end_clean();
        echo json_encode(['ok'=>false,'err'=>'PHP fatal: '.$e['message']]);
    } elseif (ob_get_level()) {
        // buang sisa buffer jika belum di-flush
        @ob_end_flush();
    }
});
// bersihkan buffer sekali sebelum output JSON utama (buang notice/warning).
if (ob_get_level()) { ob_clean(); }

$u = current_user(); $uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'err'=>'method']); exit; }
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { echo json_encode(['ok'=>false,'err'=>'csrf']); exit; }

rate_limit_or_die('api_ai:'.$uid, 30, 300);

$task   = $_POST['task']   ?? 'chat';
$prompt = trim((string)($_POST['prompt'] ?? ''));
$ctx    = $_POST['ctx']    ?? '';

switch ($task) {

    /* ---------- AI Running Coach ---------- */
    case 'coach': {
        $stats = $ctx ?: '(tidak ada statistik dikirim)';
        $sys = "Anda 'AI Running Coach' berpengalaman. Balas dalam Bahasa Indonesia, singkat (maks 6 poin), praktis, ".
               "fokus pada: rekomendasi pace, durasi, frekuensi latihan minggu depan, peringatan over-training, ".
               "dan 1 saran nutrisi/recovery. Gunakan format markdown poin.";
        $p = "Statistik pelari (30 hari terakhir):\n$stats\n\nBeri rekomendasi latihan & evaluasi.";
        $r = gemini_text($p, ['system'=>$sys,'temperature'=>0.5,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- Tanya Jawab Islami ---------- */
    case 'tanya_islami': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda asisten Tanya Jawab Islami berbasis Al-Qur'an dan Hadist shahih (Bukhari/Muslim/4 sunan). ".
               "Jawab dalam Bahasa Indonesia, sopan, ringkas (maks 6 paragraf pendek). ".
               "Selalu sebutkan referensi (surah:ayat, atau perawi+nomor hadist) bila relevan. ".
               "Jika pertanyaan termasuk khilafiyah, jelaskan pendapat utama tanpa menyalahkan. ".
               "Akhiri dengan kalimat 'Wallahu a'lam.'";
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- Survival Mode (Revisi 19 Juni 2026 Part O #3) ---------- */
    case 'tanya_survival': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda 'AI Survival Coach' — ahli bertahan hidup di alam liar / hutan tropis Indonesia. ".
               "Rujukan: SAR Nasional (Basarnas), Bushcraft USA, manual TNI AD/Marinir tentang survival hutan, ".
               "serta praktik PPGD outdoor. Jawab dalam Bahasa Indonesia, sopan, ringkas (maks 6 paragraf pendek ".
               "atau poin), praktis, dan jujur soal risiko. Fokus pada: prinsip STOP (Stop-Think-Observe-Plan), ".
               "prioritas survival 3-3-3 (3 menit tanpa udara, 3 jam tanpa naungan di cuaca ekstrem, 3 hari tanpa ".
               "air, 3 minggu tanpa makanan), api/air/shelter/sinyal, makanan boleh & beracun di hutan Indonesia, ".
               "navigasi & mitigasi tersesat, serta langkah jika SUDAH tersesat. Jika ada potensi cedera berat / ".
               "kondisi mengancam nyawa, ingatkan untuk segera memanggil 115 (Basarnas) / 112. ".
               "Akhiri dengan: 'Tetap tenang dan utamakan keselamatan.'";
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- AI Konsultasi Olahraga (artikel_olahraga.php) — Revisi 19 Juni 2026 ---------- */
    case 'tanya_olahraga': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $jenis = trim((string)($_POST['jenis'] ?? ''));
        $sys = "Anda 'AI Problem Solver Olahraga' — pelatih & terapis olahraga. ".
               "Konteks jenis olahraga yang ditanyakan: '".$jenis."'. ".
               "Jawab dalam Bahasa Indonesia, sopan, ringkas (maks 6 paragraf pendek atau poin), praktis. ".
               "Fokus: identifikasi masalah, solusi teknik/peralatan/latihan, langkah bertahap, dan tanda harus konsultasi dokter. ".
               "Jika user bertanya di luar konteks olahraga '".$jenis."', tetap jawab tapi sebutkan relevansinya. ".
               "Akhiri dengan: 'Selamat berolahraga dengan aman!'";
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.5,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- Estimasi kalori aktivitas LAIN (kalori_mingguan.php) — Revisi 19 Juni 2026 ---------- */
    case 'kalori_lain': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        // Asumsi berat badan dewasa rata-rata Indonesia 65 kg agar estimasi MET realistis.
        $sys = "Anda ahli sport science. Pengguna mendeskripsikan aktivitas pembakaran kalori SELAIN makanan dan SELAIN ".
               "olahraga utama (cth: jalan kaki ke pasar, naik-turun tangga di kantor, mencuci mobil, berkebun, momong anak, dll). ".
               "Gunakan rumus MET: kalori = MET × berat(kg) × jam. Asumsi berat 65 kg jika tidak disebut. ".
               "Tentukan MET berdasarkan tabel Compendium of Physical Activities (cth: jalan santai 3.0, jalan cepat 4.3, naik-turun tangga 8.0, momong balita aktif 4.0, mencuci mobil 3.5, berkebun ringan 3.8). ".
               "WAJIB: estimasi durasi (menit) WAJIB > 0 dan kalori WAJIB > 0 (integer, minimal 5). Jangan pernah balas 0. ".
               "Balas HANYA JSON tanpa fence: {\"aktivitas\":\"<ringkas>\",\"durasi_menit\":<int>,\"kalori\":<int>,\"rincian\":\"<MET × kg × jam = kkal>\"}";
        $r = gemini_text($prompt, ['system'=>$sys,'json'=>true,'temperature'=>0.2,'max_tokens'=>512]);
        if (!$r['ok']) { echo json_encode($r); exit; }
        $obj = gemini_extract_json($r['text']);
        $kal = (int)($obj['kalori'] ?? 0);
        $dur = (int)($obj['durasi_menit'] ?? 0);
        if ($kal <= 0) {
            if (preg_match('/(\d{2,5})\s*(?:kkal|kcal|kal)/i', $r['text'], $mm)) $kal = (int)$mm[1];
        }
        if ($dur <= 0) {
            if (preg_match('/(\d{1,4})\s*(?:menit|min)/i', $prompt.' '.$r['text'], $mm2)) $dur = (int)$mm2[1];
            if ($dur <= 0 && preg_match('/(\d{1,3})\s*jam/i', $prompt.' '.$r['text'], $mm3)) $dur = ((int)$mm3[1]) * 60;
        }
        // Fallback hitung manual jika AI tetap balas 0: pakai MET rata-rata 3.5 × 65kg.
        if ($kal <= 0 && $dur > 0) {
            $kal = (int) round(3.5 * 65.0 * ($dur / 60.0));
        }
        if ($kal <= 0) { $kal = 25; } // minimal supaya tidak 0
        if ($dur <= 0) { $dur = 15; }
        echo json_encode(['ok'=>true,
            'aktivitas'=>(string)($obj['aktivitas'] ?? trim(mb_substr($prompt,0,80))),
            'durasi_menit'=>$dur,
            'kalori'=>$kal,
            'rincian'=>(string)($obj['rincian'] ?? ''),
            'raw'=>$r['text']
        ]); exit;
    }

    /* ---------- Lyric → LRC (audio + lirik → format timestamp) — Revisi 19 Juni 2026 ---------- */
    case 'lyric_to_lrc': {
        @set_time_limit(180);
        $lirik = trim((string)($_POST['lirik'] ?? ''));
        if ($lirik === '') { echo json_encode(['ok'=>false,'err'=>'lirik kosong']); exit; }
        if (empty($_FILES['audio']['tmp_name']) || !is_uploaded_file($_FILES['audio']['tmp_name'])) {
            echo json_encode(['ok'=>false,'err'=>'audio belum diupload']); exit;
        }
        rate_limit_or_die('lrc:'.$uid, 6, 600);
        $sys = "Anda 'Synchroniser Lirik'. Anda menerima sebuah file AUDIO musik DAN teks LIRIK lagu tersebut. ".
               "Tugas: DENGARKAN audio, lalu kembalikan lirik dalam FORMAT LRC dengan timestamp menit:detik.centisecond ".
               "(contoh: [00:12.34]baris satu). Setiap baris lirik diberi SATU timestamp pada saat baris itu mulai dinyanyikan. ".
               "JANGAN tambahkan baris yang tidak ada di lirik input. JANGAN ubah kata-katanya. ".
               "Boleh menghilangkan baris hiasan (\"(chorus)\", \"verse 2:\"). ".
               "Balas HANYA isi file LRC murni, tanpa fence, tanpa komentar.";
        $prompt = "Lirik input (urutan harus dipertahankan):\n----\n".$lirik."\n----\n".
                  "Outputkan file LRC lengkap. Bila durasi audio < panjang lirik, tetap selaraskan semua baris se-realistis mungkin.";
        $r = gemini_audio($prompt, $_FILES['audio']['tmp_name'],
                ['system'=>$sys,'temperature'=>0.2,'max_tokens'=>8192]);
        if (!$r['ok']) { echo json_encode($r); exit; }
        $txt = trim((string)$r['text']);
        // strip fences kalau ada
        if (preg_match('/```(?:lrc|text)?\s*(.+?)\s*```/is', $txt, $m)) $txt = trim($m[1]);
        echo json_encode(['ok'=>true,'lrc'=>$txt]); exit;
    }

    /* ---------- AI Health (cedera olahraga & penanganan) — Revisi 18 Juni 2026 ---------- */
    case 'ai_health': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda 'AI Health' — asisten edukasi kesehatan olahraga berbasis bukti (sport medicine). ".
               "Jawab dalam Bahasa Indonesia, sopan, ringkas (maks 6 paragraf pendek atau poin), praktis. ".
               "Fokus pada: identifikasi cedera, prinsip RICE/POLICE, langkah penanganan, tanda merah (red flags) ".
               "yang wajib ke dokter/IGD, serta mitigasi/pencegahan sebelum cedera. ".
               "JANGAN memberikan diagnosis pasti. Akhiri dengan: 'Konten edukatif, bukan pengganti pemeriksaan tenaga medis.'";
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- AI Doctor (penyakit umum & obat herbal) — Revisi 18 Juni 2026 ---------- */
    case 'ai_doctor': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda 'AI Doctor' — asisten edukasi kesehatan umum & herbal Indonesia (rujukan: Kemenkes RI, BPOM, jurnal herbal). ".
               "Jawab dalam Bahasa Indonesia, sopan, ringkas (maks 6 paragraf pendek atau poin), praktis. ".
               "Sertakan: kemungkinan penyebab umum, gejala, langkah awal di rumah, rekomendasi herbal yang aman ".
               "(sebut nama tanaman & cara pakai bila relevan), serta tanda bahaya yang wajib ke dokter. ".
               "JANGAN memberikan dosis obat keras / resep dokter. Akhiri dengan: 'Konten edukatif, bukan pengganti konsultasi dokter.'";
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- AI Safety Monitoring (live tracking) ---------- */
    case 'safety': {
        $sys = "Anda sistem 'AI Safety Monitor' untuk pelari yang sedang aktif live-tracking. ".
               "Diberikan ringkasan kondisi GPS terakhir (kecepatan, idle, jarak dari rute biasa). ".
               "Tentukan tingkat risiko ('aman'|'waspada'|'darurat') dan beri pesan singkat (≤ 25 kata) ".
               "yang bisa dikirim ke kontak darurat jika perlu. Balas HANYA JSON: ".
               "{\"level\":\"aman|waspada|darurat\",\"alasan\":\"...\",\"pesan\":\"...\"}";
        $r = gemini_text($ctx ?: $prompt, ['system'=>$sys,'json'=>true,'temperature'=>0.2,'max_tokens'=>250]);
        $obj = gemini_extract_json($r['text'] ?? '');
        echo json_encode(['ok'=>$r['ok'], 'err'=>$r['err'] ?? null, 'data'=>$obj, 'raw'=>$r['text'] ?? null]); exit;
    }

    /* ---------- AI Route dari prompt teks (run.php) ---------- */
    case 'ai_route_prompt': {
        @set_time_limit(120);
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda asisten perencana rute lari di INDONESIA. Berdasarkan prompt pengguna (jarak, kota, preferensi), ".
               "kembalikan urutan 6–10 nama tempat / landmark / nama jalan yang dapat dirangkai jadi rute lari sirkular. ".
               "PENTING: SEMUA tempat WAJIB berada di Indonesia. Jika pengguna tidak menyebut kota, asumsikan kota di Indonesia ".
               "(default: Jakarta). Selalu sertakan nama kota + ', Indonesia' di setiap entri agar tidak salah negara. ".
               "Balas HANYA JSON: {\"places\":[\"Nama tempat 1, Nama Kota, Indonesia\", ...], \"note\":\"<1 kalimat ringkas>\"}";
        $r = gemini_text($prompt, ['system'=>$sys,'json'=>true,'temperature'=>0.4,'max_tokens'=>2048]);
        if (!$r['ok']) { echo json_encode($r); exit; }
        $obj = gemini_extract_json($r['text']);
        $places = is_array($obj['places'] ?? null) ? $obj['places'] : [];
        // Fallback A: ekstrak semua string ber-kutip dari raw text (handles JSON terpotong)
        if (count($places) < 2) {
            if (preg_match_all('/"([^"\\\\]{4,160})"/u', (string)$r['text'], $mm)) {
                foreach ($mm[1] as $cand) {
                    $cand = trim($cand);
                    // saring key-key JSON umum
                    if (in_array(strtolower($cand), ['places','note','name','nama','kota'], true)) continue;
                    if (strlen($cand) > 4) $places[] = $cand;
                }
            }
        }
        // Fallback B: pisah per baris polos (markdown list / numbered)
        if (count($places) < 2) {
            foreach (preg_split('/\r?\n/', (string)$r['text']) as $ln) {
                $ln = trim($ln);
                if ($ln === '') continue;
                if (strpbrk($ln, '{}[]') !== false) continue;
                $ln = trim($ln, " \t\"',");
                $ln = trim(preg_replace('/^[\-\*\d\.\)]+\s*/','', $ln));
                if (strlen($ln) > 4 && strlen($ln) < 160 && strpos($ln, ':') === false) $places[] = $ln;
            }
        }
        // pastikan tiap entry punya ", Indonesia" untuk geocoding bias
        $places = array_map(function($p){
            $p = trim((string)$p);
            if ($p !== '' && stripos($p, 'indonesia') === false) $p .= ', Indonesia';
            return $p;
        }, $places);
        $places = array_slice(array_values(array_filter(array_unique($places))), 0, 8);
        if (count($places) < 2) { echo json_encode(['ok'=>false,'err'=>'AI tidak mengembalikan tempat. Raw: '.substr($r['text'],0,200)]); exit; }
        // Geocode via Nominatim (Revisi 17 Juni 2026 Part I — fallback multi-variant)
        $coords = []; $failures = [];
        foreach ($places as $place) {
            $q = trim((string)$place); if ($q==='') continue;
            // Buat beberapa variasi query: full → tanpa countrycode → potong jadi 2 segmen terakhir → kota saja
            $parts = array_map('trim', explode(',', $q));
            $tail2 = count($parts)>=2 ? implode(', ', array_slice($parts,-2)) : $q;
            $kota  = count($parts)>=2 ? $parts[count($parts)-2] : $parts[0];
            $variants = [
                ['q'=>$q,     'cc'=>'id'],
                ['q'=>$q,     'cc'=>''],
                ['q'=>$tail2, 'cc'=>'id'],
                ['q'=>$kota.', Indonesia', 'cc'=>'id'],
            ];
            $found = null;
            foreach ($variants as $v) {
                if ($v['q']==='') continue;
                $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1'
                     . ($v['cc']?'&countrycodes='.$v['cc']:'') . '&q='.urlencode($v['q']);
                $ch = curl_init($url);
                $copt = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
                    CURLOPT_USERAGENT=>'SportAppBot/1.0 (admin@local)',
                    CURLOPT_HTTPHEADER=>['Accept-Language: id,en']];
                if (getenv('GEMINI_INSECURE_SSL') === '1') { $copt[CURLOPT_SSL_VERIFYPEER]=false; $copt[CURLOPT_SSL_VERIFYHOST]=0; }
                curl_setopt_array($ch, $copt);
                $r2 = curl_exec($ch); curl_close($ch);
                $arr = json_decode($r2 ?: '[]', true);
                if (is_array($arr) && !empty($arr[0]['lat'])) {
                    $found = [(float)$arr[0]['lat'], (float)$arr[0]['lon']];
                    break;
                }
                usleep(550*1000); // hormati rate-limit Nominatim
            }
            if ($found) $coords[] = $found; else $failures[] = $q;
        }
        if (count($coords) < 2) { echo json_encode(['ok'=>false,'err'=>'Geocoding gagal untuk: '.implode(' | ',$failures).'. Coba prompt yang lebih spesifik (sebut kota besar/landmark terkenal).']); exit; }
        echo json_encode(['ok'=>true,'coords'=>$coords,'places'=>$places,'note'=>$obj['note'] ?? '','gagal_geocode'=>$failures]); exit;
    }

    /* ---------- Generate Lirik (Revisi 20 Juni 2026 R4) ---------- */
    case 'lyrics_gen': {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'judul/artis kosong']); exit; }
        $sys = "Anda asisten penulis lirik. Tugas: cari/tuliskan ULANG lirik lengkap dari lagu yang diminta user. ".
               "Aturan output WAJIB:\n".
               "- HANYA teks lirik mentah, satu baris per baris (tanpa nomor, tanpa pengantar, tanpa penjelasan).\n".
               "- Pertahankan bahasa asli lagu (jangan diterjemahkan).\n".
               "- Jangan tulis 'Verse 1', 'Chorus', dll — cukup baris kosong sebagai pemisah bagian.\n".
               "- Bila lagu tidak Anda kenali atau lirik tidak pasti, balas TEPAT: 'LIRIK_TIDAK_DITEMUKAN'.";
        $r = gemini_text("Judul / artis: ".$prompt, ['system'=>$sys,'temperature'=>0.2,'max_tokens'=>2048]);
        if (!empty($r['ok'])) {
            $txt = trim((string)($r['text'] ?? ''));
            if ($txt === '' || stripos($txt, 'LIRIK_TIDAK_DITEMUKAN') !== false) {
                echo json_encode(['ok'=>false,'err'=>'AI tidak menemukan lirik untuk "'.$prompt.'". Coba tombol "Cari di Google".']); exit;
            }
            echo json_encode(['ok'=>true,'text'=>$txt,'lyrics'=>$txt]); exit;
        }
        echo json_encode($r); exit;
    }

    /* ---------- Makna Ayat (Al-Qur'an) — Revisi Juli 2026 ---------- */
    case 'makna_ayat': {
        $surah = trim((string)($_POST['surah'] ?? ''));
        $ayat  = (int)($_POST['ayat'] ?? 0);
        $arab  = trim((string)($_POST['arab'] ?? ''));
        $terj  = trim((string)($_POST['terjemah'] ?? ''));
        if ($surah === '' || $ayat < 1) { echo json_encode(['ok'=>false,'err'=>'surah/ayat kosong']); exit; }
        $sys = "Anda ahli tafsir Al-Qur'an. Jelaskan MAKNA singkat ayat berikut dalam Bahasa Indonesia yang mudah ".
               "dipahami umat awam. Fokus: pesan utama ayat, kata kunci penting, dan pelajaran praktis. ".
               "Maksimal 3 paragraf pendek (total ± 120–180 kata). Sopan, netral madzhab, tidak polemis. ".
               "Tanpa fence markdown, tanpa judul. Langsung isi penjelasan.";
        $p = "Surah: $surah\nAyat ke-$ayat\n".
             ($arab!=='' ? "Teks Arab: $arab\n" : '').
             ($terj!=='' ? "Terjemahan: $terj\n" : '').
             "\nJelaskan makna ayat ini.";
        $r = gemini_text($p, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>1024]);
        echo json_encode($r); exit;
    }

    /* ---------- Tafsir Kontemporer (Al-Qur'an) — Revisi Juli 2026 ---------- */
    case 'tafsir_kontemporer': {
        $surah = trim((string)($_POST['surah'] ?? ''));
        $ayat  = (int)($_POST['ayat'] ?? 0);
        $arab  = trim((string)($_POST['arab'] ?? ''));
        $terj  = trim((string)($_POST['terjemah'] ?? ''));
        if ($surah === '' || $ayat < 1) { echo json_encode(['ok'=>false,'err'=>'surah/ayat kosong']); exit; }
        $sys = "Anda 'Mufassir Kontemporer' — ahli tafsir Al-Qur'an dengan pendekatan kontekstual modern ".
               "(rujukan gaya: M. Quraish Shihab 'Al-Mishbah', Wahbah Az-Zuhaili 'Al-Munir', Sayyid Qutb 'Fi Zhilalil Qur'an', ".
               "dan tafsir maudhu'i modern). Tugas: berikan TAFSIR KONTEMPORER ayat berikut yang menghubungkan pesan Qur'an ".
               "dengan realitas kehidupan hari ini (sosial, teknologi, ekonomi, keluarga, lingkungan, etika digital, dsb). ".
               "Struktur (gunakan sub-judul markdown ###):\n".
               "### Konteks Ayat\n(1–2 kalimat latar belakang)\n".
               "### Pesan Inti\n(inti pesan ayat)\n".
               "### Relevansi Kontemporer\n(3–5 contoh aplikatif di kehidupan modern)\n".
               "### Renungan\n(1 paragraf pendek ajakan aksi)\n".
               "Bahasa Indonesia, sopan, netral madzhab, hindari polemik. Total ± 250–400 kata. Akhiri dengan 'Wallahu a'lam.'";
        $p = "Surah: $surah\nAyat ke-$ayat\n".
             ($arab!=='' ? "Teks Arab: $arab\n" : '').
             ($terj!=='' ? "Terjemahan: $terj\n" : '').
             "\nBerikan tafsir kontemporer ayat ini.";
        $r = gemini_text($p, ['system'=>$sys,'temperature'=>0.5,'max_tokens'=>2048]);
        echo json_encode($r); exit;
    }

    /* ---------- Tafsir Lughawi (analisis bahasa / linguistik ayat) — Revisi Nov 2026 ---------- */
    case 'tafsir_lughawi': {
        $surah = trim((string)($_POST['surah'] ?? ''));
        $ayat  = (int)($_POST['ayat'] ?? 0);
        $arab  = trim((string)($_POST['arab'] ?? ''));
        $terj  = trim((string)($_POST['terjemah'] ?? ''));
        if ($surah === '' || $ayat < 1) { echo json_encode(['ok'=>false,'err'=>'surah/ayat kosong']); exit; }
        $sys = "Anda 'Ahli Tafsir Lughawi' — pakar linguistik Al-Qur'an (balaghah, nahwu, sharaf, semantik). ".
               "Tugas: berikan TAFSIR LUGHAWI (analisis bahasa Arab) untuk ayat berikut. Bahasa jawaban: Bahasa Indonesia, ".
               "sopan, jelas, netral madzhab. Gunakan struktur markdown ### sebagai berikut:\n".
               "### Analisis Kata Kunci\n(Ambil 3–6 kata Arab penting di ayat. Tulis: kata Arab — akar kata (root) — makna dasar — makna kontekstual.)\n".
               "### Struktur Kalimat (Nahwu)\n(Jelaskan pola i'rab / kedudukan kata inti: mubtada, khabar, fi'il, fa'il, maf'ul, dsb.)\n".
               "### Balaghah / Gaya Bahasa\n(Sebutkan majaz, tasybih, kinayah, taqdim-ta'khir, iltifat, penekanan, dsb. bila ada.)\n".
               "### Nuansa Makna\n(Perbedaan halus makna antar sinonim, konotasi, mengapa Al-Qur'an memilih kata tsb.)\n".
               "### Kesimpulan Bahasa\n(1 paragraf ringkas pesan yang muncul dari sisi bahasa.)\n".
               "Total ± 300–500 kata. Sajikan kata Arab dalam huruf Arab dan transliterasi Latin. Akhiri dengan 'Wallahu a'lam.'";
        $p = "Surah: $surah\nAyat ke-$ayat\n".
             ($arab!=='' ? "Teks Arab: $arab\n" : '').
             ($terj!=='' ? "Terjemahan Indonesia: $terj\n" : '').
             "\nBerikan tafsir lughawi (analisis bahasa) ayat ini.";
        $r = gemini_text($p, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- OCR Strava Screenshot → data upload (Revisi Nov 2026) ---------- */
    case 'strava_ocr': {
        @set_time_limit(120);
        if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            echo json_encode(['ok'=>false,'err'=>'gambar belum diupload']); exit;
        }
        rate_limit_or_die('strava_ocr:'.$uid, 15, 600);
        $sys = "Anda 'AI OCR Strava'. Anda menerima screenshot ringkasan aktivitas lari/jogging dari aplikasi ".
               "Strava (atau smartwatch serupa: Garmin, Apple Watch, Nike Run). Ekstrak data numerik utamanya. ".
               "Balas HANYA JSON tanpa fence, tanpa penjelasan tambahan, dalam format berikut:\n".
               "{\"tanggal\":\"YYYY-MM-DD\",\"durasi_menit\":<int>,\"jarak_km\":<float>,\"kalori\":<int>,".
               "\"pace\":\"m'ss\\\"/km\",\"deskripsi\":\"<judul aktivitas / catatan singkat, opsional>\"}\n".
               "Aturan:\n".
               "- Jika tanggal tidak terbaca, gunakan tanggal hari ini.\n".
               "- Konversi jam ke menit (contoh 1:23:45 → 83 menit).\n".
               "- pace dalam format menit'detik\"/km (contoh 5'42\"/km). Jika tidak ada, hitung dari durasi/jarak.\n".
               "- Nilai wajib > 0. Jika field tidak terbaca sama sekali, isi 0 (untuk numeric) atau string kosong.\n".
               "- Jika gambar BUKAN screenshot aktivitas lari, balas: {\"error\":\"bukan_strava\"}.";
        $r = gemini_vision('Ekstrak data aktivitas jogging dari screenshot ini.', $_FILES['image']['tmp_name'],
                ['system'=>$sys,'json'=>true,'temperature'=>0.1,'max_tokens'=>1024]);
        if (!$r['ok']) { echo json_encode($r); exit; }
        $obj = gemini_extract_json($r['text']);
        if (!is_array($obj) || isset($obj['error'])) {
            echo json_encode(['ok'=>false,'err'=>'Gambar tidak dikenali sebagai screenshot aktivitas Strava. Coba upload screenshot yang jelas.','raw'=>$r['text']]);
            exit;
        }
        // Normalisasi
        $today = date('Y-m-d');
        $tgl = (string)($obj['tanggal'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) $tgl = $today;
        echo json_encode(['ok'=>true,'data'=>[
            'tanggal'      => $tgl,
            'durasi_menit' => max(0, (int)($obj['durasi_menit'] ?? 0)),
            'jarak_km'     => max(0, (float)($obj['jarak_km'] ?? 0)),
            'kalori'       => max(0, (int)($obj['kalori'] ?? 0)),
            'pace'         => (string)($obj['pace'] ?? ''),
            'deskripsi'    => (string)($obj['deskripsi'] ?? ''),
        ],'raw'=>$r['text']]);
        exit;
    }

    /* ---------- Terjemah teks ke Bahasa Indonesia — Revisi Juli 2026 R9 ----------
     * Dipakai quran_surah.php untuk memastikan Tafsir Ibnu Katsir selalu tampil
     * dalam Bahasa Indonesia (jika API sumber mengembalikan versi bahasa lain). */
    case 'translate_id': {
        $text = trim((string)($_POST['text'] ?? $prompt));
        if ($text === '') { echo json_encode(['ok'=>false,'err'=>'text kosong']); exit; }
        if (mb_strlen($text) > 12000) $text = mb_substr($text, 0, 12000);
        $sys = "Anda penerjemah profesional teks tafsir Al-Qur'an. Terjemahkan teks berikut ke Bahasa Indonesia ".
               "yang baik, lugas, dan mudah dipahami umat awam. Pertahankan kutipan ayat Arab, kata Arab ".
               "(seperti Allah, Rasulullah, taqwa, dsb) apa adanya. Jangan menambahkan pengantar, jangan ".
               "menyebut proses terjemah — langsung sajikan hasil terjemahan saja. Pertahankan pembagian ".
               "paragraf. Jika teks sudah berbahasa Indonesia, kembalikan apa adanya.";
        $r = gemini_text($text, ['system'=>$sys,'temperature'=>0.2,'max_tokens'=>4096]);
        echo json_encode($r); exit;
    }

    /* ---------- Chat free-form ---------- */
    default: {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $r = gemini_text($prompt, ['temperature'=>0.5,'max_tokens'=>800]);
        echo json_encode($r); exit;
    }
}

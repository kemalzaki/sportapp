<?php
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
        // fallback: pisah baris — hanya yang BUKAN bagian JSON
        if (count($places) < 2) {
            foreach (preg_split('/\r?\n/', (string)$r['text']) as $ln) {
                $ln = trim($ln);
                // skip line yang masih mengandung token JSON
                if ($ln === '' || strpbrk($ln, '{}[]":') !== false) continue;
                $ln = trim(preg_replace('/^[\-\*\d\.\)]+\s*/','', $ln));
                if (strlen($ln) > 4 && strlen($ln) < 120) $places[] = $ln;
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

    /* ---------- Chat free-form ---------- */
    default: {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $r = gemini_text($prompt, ['temperature'=>0.5,'max_tokens'=>800]);
        echo json_encode($r); exit;
    }
}

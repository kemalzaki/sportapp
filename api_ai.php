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
        $r = gemini_text($p, ['system'=>$sys,'temperature'=>0.5,'max_tokens'=>700]);
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
        $r = gemini_text($prompt, ['system'=>$sys,'temperature'=>0.4,'max_tokens'=>900]);
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
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $sys = "Anda asisten perencana rute lari. Berdasarkan prompt pengguna (jarak, kota, preferensi), ".
               "kembalikan urutan 6–14 nama tempat / landmark / nama jalan yang dapat dirangkai jadi rute lari sirkular. ".
               "Balas HANYA JSON: {\"places\":[\"Nama tempat 1, Kota\", ...], \"note\":\"<1 kalimat ringkas>\"} ".
               "Sertakan nama kota di tiap entri agar bisa di-geocode.";
        $r = gemini_text($prompt, ['system'=>$sys,'json'=>true,'temperature'=>0.5,'max_tokens'=>500]);
        if (!$r['ok']) { echo json_encode($r); exit; }
        $obj = gemini_extract_json($r['text']);
        $places = is_array($obj['places'] ?? null) ? $obj['places'] : [];
        if (count($places) < 2) { echo json_encode(['ok'=>false,'err'=>'AI tidak mengembalikan tempat']); exit; }
        // Geocode via Nominatim
        $coords = [];
        foreach ($places as $place) {
            $q = trim((string)$place); if ($q==='') continue;
            $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='.urlencode($q);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
                CURLOPT_USERAGENT=>'SportAppBot/1.0 (admin@local)']);
            $r2 = curl_exec($ch); curl_close($ch);
            $arr = json_decode($r2 ?: '[]', true);
            if (is_array($arr) && !empty($arr[0]['lat'])) {
                $coords[] = [(float)$arr[0]['lat'], (float)$arr[0]['lon']];
            }
            usleep(1100*1000);
        }
        if (count($coords) < 2) { echo json_encode(['ok'=>false,'err'=>'Geocoding < 2 titik']); exit; }
        echo json_encode(['ok'=>true,'coords'=>$coords,'places'=>$places,'note'=>$obj['note'] ?? '']); exit;
    }

    /* ---------- Chat free-form ---------- */
    default: {
        if ($prompt === '') { echo json_encode(['ok'=>false,'err'=>'prompt kosong']); exit; }
        $r = gemini_text($prompt, ['temperature'=>0.5,'max_tokens'=>800]);
        echo json_encode($r); exit;
    }
}
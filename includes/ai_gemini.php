<?php
/**
 * Helper Google Gemini — Revisi 17 Juni 2026 (Part G).
 *
 * PERUBAHAN PART G:
 *  - Dukungan penuh untuk **Auth Key format baru** Google AI Studio
 *    (diawali "AQ.") yang menggantikan Standard API key sebelum
 *    September 2026 (lihat changelog Gemini API).
 *  - Auth dikirim via header **x-goog-api-key: <KEY>** (cara baru yang
 *    direkomendasikan Google), bukan lagi "Authorization: Bearer ..."
 *    yang menyebabkan error
 *      "Request had invalid authentication credentials.
 *       Expected OAuth 2 access token, login cookie or other valid
 *       authentication credential."
 *  - Format key lama (AIza...) tetap didukung; header yang sama juga
 *    valid untuk standard key, jadi tidak perlu lagi cabang ?key= URL.
 *  - Default model dinaikkan ke "gemini-2.5-flash" (tetap), endpoint
 *    tetap v1beta (compatible dengan auth key baru).
 *  - Tambah retry sederhana 1x bila gateway 5xx / curl error transient.
 *
 * Fungsi publik (tidak berubah, backward-compatible):
 *   gemini_text($prompt, $opts=[])
 *   gemini_vision($prompt, $imagePath, $opts=[])
 *   gemini_extract_json($text)
 *   gemini_config_status()
 *
 * Opsi yang dikenali: system, temperature, max_tokens, json, model.
 * Return shape: ['ok'=>bool, 'text'=>string, 'err'=>string, 'raw'=>mixed]
 */

// ============================================================
//  KEY GEMINI default (boleh di-override via env GEMINI_API_KEY).
//  Isi langsung di sini kalau mau hardcode.
// ============================================================
if (!defined('GEMINI_API_KEY_DEFAULT')) {
    define('GEMINI_API_KEY_DEFAULT', '');
}
if (!defined('GEMINI_MODEL_DEFAULT')) {
    define('GEMINI_MODEL_DEFAULT', 'gemini-2.5-flash');
}
if (!defined('GEMINI_API_BASE')) {
    // v1beta sudah support auth key baru (AQ.*) per pengumuman Google.
    define('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta');
}

// kompatibilitas helper dari env.local.php
if (!function_exists('hf_env_set')) {
    function hf_env_set($key, $value) {
        if (getenv($key) === false || getenv($key) === '') {
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// auto-load env files bila ada (tidak fatal kalau tidak ada)
foreach ([
    __DIR__ . '/../config/env.local.php',
    __DIR__ . '/../config/env.php',
    __DIR__ . '/../.env.local.php',
] as $__envFile) {
    if (is_file($__envFile)) { @include_once $__envFile; }
}

function _gemini_key() {
    $list = _gemini_keys();
    return $list[0] ?? '';
}

/**
 * Revisi 17 Juni 2026 (Part I) — Dukungan multi-key rotation.
 * Set GEMINI_API_KEYS=key1,key2,key3 di env.local.php untuk fallback
 * otomatis saat satu key kena quota (429 / RESOURCE_EXHAUSTED).
 * GEMINI_API_KEY tunggal tetap didukung (akan digabung ke list).
 */
function _gemini_keys() {
    $keys = [];
    $single = getenv('GEMINI_API_KEY');
    if (!$single && isset($_ENV['GEMINI_API_KEY']))    $single = $_ENV['GEMINI_API_KEY'];
    if (!$single && isset($_SERVER['GEMINI_API_KEY'])) $single = $_SERVER['GEMINI_API_KEY'];
    if ($single && stripos($single,'GANTI')===false) $keys[] = trim($single);

    $multi = getenv('GEMINI_API_KEYS') ?: ($_ENV['GEMINI_API_KEYS'] ?? '');
    if ($multi) {
        foreach (preg_split('/[,\s;]+/', $multi) as $kk) {
            $kk = trim($kk);
            if ($kk !== '' && stripos($kk,'GANTI')===false) $keys[] = $kk;
        }
    }
    if (!$keys && GEMINI_API_KEY_DEFAULT !== '') $keys[] = GEMINI_API_KEY_DEFAULT;
    if (!$keys) {
        $tok = getenv('GEMINI_ACCESS_TOKEN') ?: ($_ENV['GEMINI_ACCESS_TOKEN'] ?? '');
        if ($tok) $keys[] = trim($tok);
    }
    return array_values(array_unique(array_filter($keys)));
}

function _gemini_model() {
    $m = getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? '') ?: GEMINI_MODEL_DEFAULT;
    return trim((string)$m) ?: GEMINI_MODEL_DEFAULT;
}

/**
 * Identifikasi tipe key untuk diagnostik.
 *   - "auth_key"    : format baru AI Studio (AQ.*)
 *   - "api_key"     : standard key lama (AIza*)
 *   - "oauth_bearer": OAuth access token (ya29.*) — masih dikirim via Bearer
 *   - "unknown"     : format tidak dikenali; coba header x-goog-api-key
 */
function _gemini_key_kind($k) {
    if ($k === '')                          return 'missing';
    if (strpos($k, 'AIza') === 0)           return 'api_key';
    if (strpos($k, 'AQ.')  === 0)           return 'auth_key';
    if (strpos($k, 'ya29.') === 0)          return 'oauth_bearer';
    // beberapa OAuth token lama juga panjang & tanpa prefix jelas
    return 'unknown';
}

function gemini_config_status() {
    $k = _gemini_key();
    return [
        'has_key'    => $k !== '' ? 1 : 0,
        'mode'       => _gemini_key_kind($k),
        'key_masked' => $k === '' ? '' : (substr($k, 0, 6) . '…' . substr($k, -4)),
        'model'      => _gemini_model(),
        'endpoint'   => GEMINI_API_BASE,
    ];
}

/**
 * Inti pemanggilan REST Gemini.
 * $parts = [ ['text'=>...], ['inline_data'=>['mime_type'=>...,'data'=>base64]] , ... ]
 */
function _gemini_call(array $parts, array $opts = []) {
    $keys  = _gemini_keys();
    $model = $opts['model'] ?? _gemini_model();
    if (!$keys) {
        return ['ok'=>false,'text'=>'','err'=>'GEMINI_API_KEY belum di-set (cek includes/ai_gemini.php).'];
    }

    $body = [
        'contents' => [[ 'role'=>'user', 'parts'=>$parts ]],
        'generationConfig' => [
            'temperature'     => isset($opts['temperature']) ? (float)$opts['temperature'] : 0.5,
            'maxOutputTokens' => isset($opts['max_tokens'])  ? (int)$opts['max_tokens']    : 800,
        ],
    ];
    if (!empty($opts['json'])) {
        $body['generationConfig']['responseMimeType'] = 'application/json';
    }
    if (!empty($opts['system'])) {
        $body['systemInstruction'] = [
            'role'  => 'system',
            'parts' => [['text' => (string)$opts['system']]],
        ];
    }

    $url = rtrim(GEMINI_API_BASE, '/')
         . '/models/' . rawurlencode($model) . ':generateContent';
    $postBody = json_encode($body, JSON_UNESCAPED_UNICODE);

    $lastErr = ''; $lastJson = null; $lastKind = '';
    foreach ($keys as $keyIdx => $key) {
        $kind = _gemini_key_kind($key);
        $lastKind = $kind;
        $headers = ['Content-Type: application/json'];
        if ($kind === 'oauth_bearer') {
            $headers[] = 'Authorization: Bearer ' . $key;
        } else {
            $headers[] = 'x-goog-api-key: ' . $key;
        }

        // request + retry transient 1x
        $attempt = 0; $maxAttempt = 2;
        $resp=false; $code=0; $cerr='';
        while ($attempt < $maxAttempt) {
            $attempt++;
            $ch = curl_init($url);
            $curlOpts = [
                CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>15,
                CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$postBody,
            ];
            if (getenv('GEMINI_INSECURE_SSL') === '1') {
                $curlOpts[CURLOPT_SSL_VERIFYPEER]=false; $curlOpts[CURLOPT_SSL_VERIFYHOST]=0;
            }
            curl_setopt_array($ch, $curlOpts);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            $transient = ($resp===false) || ($code>=500 && $code<600);
            if ($resp===false && stripos($cerr,'SSL')!==false && $attempt===1) {
                $curlOpts[CURLOPT_SSL_VERIFYPEER]=false; $curlOpts[CURLOPT_SSL_VERIFYHOST]=0;
                $ch=curl_init($url); curl_setopt_array($ch,$curlOpts);
                $resp=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
                $cerr=curl_error($ch); curl_close($ch);
                $transient = ($resp===false) || ($code>=500 && $code<600);
            }
            if (!$transient) break;
            if ($attempt < $maxAttempt) usleep(400000);
        }

        if ($resp === false) { $lastErr = 'curl: '.$cerr; continue; }
        $json = json_decode($resp, true);
        if (!is_array($json)) { $lastErr='Respons bukan JSON (HTTP '.$code.'): '.substr((string)$resp,0,200); continue; }
        $lastJson = $json;
        if ($code < 200 || $code >= 300 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? ('HTTP '.$code);
            $isQuota = ($code===429) || stripos($msg,'quota')!==false
                       || stripos($msg,'exceeded')!==false || stripos($msg,'RESOURCE_EXHAUSTED')!==false
                       || stripos($msg,'rate limit')!==false;
            // rotasi key bila quota / 401 / 403 dan masih ada key cadangan
            if (($isQuota || $code===401 || $code===403) && $keyIdx < count($keys)-1) {
                $lastErr = '[key#'.($keyIdx+1).' '.$kind.' gagal: '.substr($msg,0,140).'] — coba key berikutnya';
                continue; // try next key
            }
            if (stripos($msg,'OAuth 2 access token')!==false || stripos($msg,'API key not valid')!==false || $code===401 || $code===403) {
                $msg .= ' [hint: cek format key. Mode: '.$kind.']';
            }
            if ($isQuota) {
                $msg .= ' [Tip: set GEMINI_API_KEYS=key1,key2,... untuk rotasi otomatis saat quota habis.]';
            }
            return ['ok'=>false,'text'=>'','err'=>$msg,'raw'=>$json];
        }
        $text = '';
        if (isset($json['candidates'][0]['content']['parts']) && is_array($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $p) {
                if (isset($p['text'])) $text .= $p['text'];
            }
        }
        return ['ok'=>true,'text'=>$text,'err'=>'','raw'=>$json];
    }
    return ['ok'=>false,'text'=>'','err'=>$lastErr ?: 'semua key gagal','raw'=>$lastJson];
}

function gemini_text($prompt, array $opts = []) {
    return _gemini_call([['text' => (string)$prompt]], $opts);
}

function gemini_vision($prompt, $imagePath, array $opts = []) {
    if (!is_file($imagePath)) {
        return ['ok'=>false,'text'=>'','err'=>'file gambar tidak ada'];
    }
    $bytes = @file_get_contents($imagePath);
    if ($bytes === false || $bytes === '') {
        return ['ok'=>false,'text'=>'','err'=>'gagal baca gambar'];
    }
    $mime = 'image/jpeg';
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($imagePath);
        if (is_string($m) && $m !== '') $mime = $m;
    } else {
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $map = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'];
        if (isset($map[$ext])) $mime = $map[$ext];
    }
    $parts = [
        ['text' => (string)$prompt],
        ['inline_data' => ['mime_type'=>$mime,'data'=>base64_encode($bytes)]],
    ];
    return _gemini_call($parts, $opts);
}

/**
 * Revisi 19 Juni 2026 — Multimodal AUDIO untuk Gemini.
 * Kirim file audio (mp3/wav/m4a/ogg/webm/aac/flac) + prompt teks ke Gemini.
 * Dipakai oleh task lyric_to_lrc di api_ai.php (sinkronisasi lirik karaoke).
 */
function gemini_audio($prompt, $audioPath, array $opts = []) {
    if (!is_file($audioPath)) {
        return ['ok'=>false,'text'=>'','err'=>'file audio tidak ada'];
    }
    $bytes = @file_get_contents($audioPath);
    if ($bytes === false || $bytes === '') {
        return ['ok'=>false,'text'=>'','err'=>'gagal baca audio'];
    }
    $mime = 'audio/mpeg';
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($audioPath);
        if (is_string($m) && stripos($m, 'audio/') === 0) $mime = $m;
    }
    $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
    $map = ['mp3'=>'audio/mpeg','wav'=>'audio/wav','m4a'=>'audio/mp4',
            'mp4'=>'audio/mp4','ogg'=>'audio/ogg','webm'=>'audio/webm',
            'aac'=>'audio/aac','flac'=>'audio/flac'];
    if (isset($map[$ext])) $mime = $map[$ext];
    $parts = [
        ['text' => (string)$prompt],
        ['inline_data' => ['mime_type'=>$mime,'data'=>base64_encode($bytes)]],
    ];
    return _gemini_call($parts, $opts);
}

/**
 * Ekstrak object JSON pertama dari teks (mendukung ```json fences).
 * Selalu kembalikan array (kosong bila gagal) supaya pemanggil tidak crash.
 */
function gemini_extract_json($text) {
    $s = trim((string)$text);
    if ($s === '') return [];
    // 1) Fence ```json ... ``` (lengkap)
    if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $s, $m)) {
        $s = $m[1];
    }
    // 1b) Fence ```json TANPA penutup (Revisi 17 Juni 2026 — output kepotong)
    elseif (preg_match('/```(?:json)?\s*(.+)$/is', $s, $m2)) {
        $s = $m2[1];
    }
    $j = json_decode($s, true);
    if (is_array($j)) return $j;
    // 2) Cari pasangan { } atau [ ] terluar
    foreach ([['{','}'], ['[',']']] as $pair) {
        $start = strpos($s, $pair[0]);
        $end   = strrpos($s, $pair[1]);
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($s, $start, $end - $start + 1);
            $j = json_decode($sub, true);
            if (is_array($j)) return $j;
            // 2b) Coba bersihkan trailing koma & control char (Revisi 17 Juni 2026)
            $sub2 = preg_replace('/,\s*([}\]])/', '$1', $sub);
            $sub2 = preg_replace('/[\x00-\x1F\x7F]/', ' ', $sub2);
            $j = json_decode($sub2, true);
            if (is_array($j)) return $j;
        }
    }
    return [];
}

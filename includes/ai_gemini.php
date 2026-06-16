<?php
/**
 * Helper Google Gemini 2.5 Flash — Revisi 16 Juni 2026 (Part F).
 *
 * Key Gemini di-HARDCODE di file ini supaya tidak ada lagi error
 * "GEMINI_API_KEY belum di-set" di local. Bila ingin override, isi
 * environment variable GEMINI_API_KEY (atau GEMINI_ACCESS_TOKEN) — itu
 * akan menggantikan nilai default di bawah.
 *
 * Mendukung dua format kredensial Google:
 *   - API key  AI Studio  (diawali "AIza...") → dikirim via ?key=...
 *   - OAuth access token  (diawali "AQ." / "ya29." / dll) → dikirim via
 *     header Authorization: Bearer ...
 *
 * Fungsi publik:
 *   gemini_text($prompt, $opts=[])
 *   gemini_vision($prompt, $imagePath, $opts=[])
 *   gemini_extract_json($text)
 *   gemini_config_status()
 *
 * Opsi yang dikenali: system, temperature, max_tokens, json, model.
 * Return shape: ['ok'=>bool, 'text'=>string, 'err'=>string, 'raw'=>mixed]
 */

// ============================================================
//  KEY GEMINI (di-render langsung di kode, sesuai permintaan).
//  Ganti baris di bawah jika Anda punya API key AI Studio sendiri.
// ============================================================
if (!defined('GEMINI_API_KEY_DEFAULT')) {
    define('GEMINI_API_KEY_DEFAULT',
        '');
}
if (!defined('GEMINI_MODEL_DEFAULT')) {
    define('GEMINI_MODEL_DEFAULT', 'gemini-2.5-flash');
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
    $k = getenv('GEMINI_API_KEY');
    if (!$k && isset($_ENV['GEMINI_API_KEY']))    $k = $_ENV['GEMINI_API_KEY'];
    if (!$k && isset($_SERVER['GEMINI_API_KEY'])) $k = $_SERVER['GEMINI_API_KEY'];
    if (!$k || stripos($k, 'GANTI') !== false)    $k = GEMINI_API_KEY_DEFAULT;
    // dukung GEMINI_ACCESS_TOKEN sebagai alternatif
    if (!$k) {
        $k = getenv('GEMINI_ACCESS_TOKEN') ?: '';
    }
    return trim((string)$k);
}

function _gemini_model() {
    $m = getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? '') ?: GEMINI_MODEL_DEFAULT;
    return trim((string)$m) ?: GEMINI_MODEL_DEFAULT;
}

function gemini_config_status() {
    $k = _gemini_key();
    $mode = '';
    if ($k === '')                              $mode = 'missing';
    elseif (strpos($k, 'AIza') === 0)           $mode = 'api_key';
    else                                        $mode = 'oauth_bearer';
    return [
        'has_key'    => $k !== '' ? 1 : 0,
        'mode'       => $mode,
        'key_masked' => $k === '' ? '' : (substr($k, 0, 6) . '…' . substr($k, -4)),
        'model'      => _gemini_model(),
    ];
}

/**
 * Inti pemanggilan REST Gemini.
 * $parts = [ ['text'=>...], ['inline_data'=>['mime_type'=>...,'data'=>base64]] , ... ]
 */
function _gemini_call(array $parts, array $opts = []) {
    $key   = _gemini_key();
    $model = $opts['model'] ?? _gemini_model();
    if ($key === '') {
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

    $base = "https://generativelanguage.googleapis.com/v1beta/models/"
          . rawurlencode($model) . ":generateContent";
    $headers = ['Content-Type: application/json'];

    // pilih auth method berdasarkan format key
    if (strpos($key, 'AIza') === 0) {
        $url = $base . '?key=' . rawurlencode($key);
    } else {
        $url = $base;
        $headers[] = 'Authorization: Bearer ' . $key;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok'=>false,'text'=>'','err'=>'curl: '.$cerr];
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['ok'=>false,'text'=>'','err'=>'Respons bukan JSON (HTTP '.$code.'): '.substr((string)$resp,0,300)];
    }
    if ($code < 200 || $code >= 300 || isset($json['error'])) {
        $msg = $json['error']['message'] ?? ('HTTP '.$code);
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
 * Ekstrak object JSON pertama dari teks (mendukung ```json fences).
 * Selalu kembalikan array (kosong bila gagal) supaya pemanggil tidak crash.
 */
function gemini_extract_json($text) {
    $s = trim((string)$text);
    if ($s === '') return [];
    // buang code fence ```json ... ```
    if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $s, $m)) {
        $s = $m[1];
    }
    // coba langsung
    $j = json_decode($s, true);
    if (is_array($j)) return $j;
    // cari blok { ... } atau [ ... ] terpanjang yang valid
    foreach ([['{','}'], ['[',']']] as $pair) {
        $start = strpos($s, $pair[0]);
        $end   = strrpos($s, $pair[1]);
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($s, $start, $end - $start + 1);
            $j = json_decode($sub, true);
            if (is_array($j)) return $j;
        }
    }
    return [];
}

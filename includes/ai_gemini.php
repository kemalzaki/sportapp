<?php
/**
 * AI Provider Router — Revisi Nov 2026 (R13).
 *
 * PERUBAHAN UTAMA:
 *  - Gemini TIDAK LAGI menjadi provider utama. Urutan baru (priority list):
 *        1) OpenRouter — model dari env OPENROUTER_MODEL
 *           (rekomendasi: deepseek/deepseek-chat-v3)
 *        2) OpenRouter — model dari env OPENROUTER_MODEL_2
 *           (rekomendasi: qwen/qwen3-235b-a22b)
 *        3) Groq       — model dari env GROQ_MODEL
 *           (rekomendasi: llama-3.3-70b-versatile)
 *        4) Gemini     — model dari env GEMINI_MODEL (LAST RESORT)
 *  - Tidak ada model hardcode di source: semua model & API key wajib dari
 *    environment variables. Jika env kosong provider tsb otomatis dilewati.
 *  - Retry maksimal 1x per provider sebelum berpindah ke provider berikutnya
 *    (dipicu oleh timeout, network error, 429, 500, 502, 503, 504,
 *    quota / provider unavailable / overloaded).
 *  - Logging tiap percobaan ke error_log:
 *        [AI] Provider: OpenRouter  Model: deepseek/deepseek-chat-v3
 *  - Nama fungsi publik LAMA (gemini_text/gemini_vision/gemini_audio/
 *    gemini_extract_json/gemini_config_status) dipertahankan agar seluruh
 *    halaman lain tidak perlu diubah. gemini_text() sekarang memakai router
 *    baru; vision/audio masih memakai Gemini karena provider lain di router
 *    ini belum mendukung modalitas tsb.
 *
 * Env var yang dikenali:
 *   OPENROUTER_API_KEY     (wajib untuk provider 1 & 2)
 *   OPENROUTER_MODEL       (model provider #1, contoh: deepseek/deepseek-chat-v3)
 *   OPENROUTER_MODEL_2     (model provider #2, contoh: qwen/qwen3-235b-a22b)
 *   GROQ_API_KEY           (wajib untuk provider #3)
 *   GROQ_MODEL             (contoh: llama-3.3-70b-versatile)
 *   GEMINI_API_KEY / GEMINI_API_KEY_1..20 / GEMINI_API_KEYS
 *   GEMINI_MODEL           (contoh: gemini-2.5-flash)
 *   AI_ROUTER_DISABLE=1    (opsional; matikan router, langsung Gemini)
 */

define('AI_ROUTER_VERSION', 'R13-'.date('H:i:s'));

// ============================================================
// Konstanta endpoint (bukan model — model tetap dari env)
// ============================================================
if (!defined('AI_OPENROUTER_URL')) define('AI_OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions');
if (!defined('AI_GROQ_URL'))       define('AI_GROQ_URL',       'https://api.groq.com/openai/v1/chat/completions');

// Gemini base — bisa di-override via env (reverse proxy dsb).
if (!defined('GEMINI_API_BASE')) {
    $__base = getenv('GEMINI_API_BASE');
    if (!$__base) $__base = $_ENV['GEMINI_API_BASE']    ?? '';
    if (!$__base) $__base = $_SERVER['GEMINI_API_BASE'] ?? '';
    if (!$__base) $__base = 'https://generativelanguage.googleapis.com/v1beta';
    define('GEMINI_API_BASE', rtrim((string)$__base, '/'));
}

// Kompatibilitas: konstanta lama masih dirujuk oleh beberapa halaman.
if (!defined('GEMINI_HELPER_VERSION'))    define('GEMINI_HELPER_VERSION', AI_ROUTER_VERSION);
if (!defined('GEMINI_API_KEY_DEFAULT'))   define('GEMINI_API_KEY_DEFAULT', '');
if (!defined('GEMINI_MODEL_DEFAULT'))     define('GEMINI_MODEL_DEFAULT', ''); // tidak dipakai lagi; model dari env

// helper env.local.php
if (!function_exists('hf_env_set')) {
    function hf_env_set($key, $value) {
        if (getenv($key) === false || getenv($key) === '') {
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Auto-load env files bila ada.
foreach ([
    __DIR__ . '/../config/env.local.php',
    __DIR__ . '/../config/env.php',
    __DIR__ . '/../.env.local.php',
] as $__envFile) {
    if (is_file($__envFile)) { @include_once $__envFile; }
}

// ============================================================
// Util env & logging
// ============================================================
function _ai_env($name) {
    $v = getenv($name);
    if ($v === false || $v === '') $v = $_ENV[$name]    ?? '';
    if ($v === '')                 $v = $_SERVER[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

function _ai_log($provider, $model, $extra = '') {
    $line = '[AI] Provider: '.$provider.'  Model: '.$model;
    if ($extra !== '') $line .= '  '.$extra;
    error_log($line);
}

// ============================================================
// Definisi urutan provider (priority list) — tanpa model hardcode.
// Provider yang env-nya tidak lengkap otomatis dilewati.
// ============================================================
function _ai_router_providers() {
    if (_ai_env('AI_ROUTER_DISABLE') === '1') return [];

    $orKey     = _ai_env('OPENROUTER_API_KEY');
    $orModel1  = _ai_env('OPENROUTER_MODEL');
    $orModel2  = _ai_env('OPENROUTER_MODEL_2');
    $grKey     = _ai_env('GROQ_API_KEY');
    $grModel   = _ai_env('GROQ_MODEL');
    $gmKey     = _gemini_key();     // ambil dari daftar key gemini
    $gmModel   = _ai_env('GEMINI_MODEL');

    $orHeaders = [
        'HTTP-Referer: '.(_ai_env('APP_URL') ?: 'https://sportapp.local'),
        'X-Title: SportApp',
    ];

    $list = [];

    // 1) OpenRouter — PRIMARY
    if ($orKey !== '' && $orModel1 !== '') {
        $list[] = [
            'name'    => 'OpenRouter',
            'kind'    => 'openai_compat',
            'url'     => AI_OPENROUTER_URL,
            'key'     => $orKey,
            'model'   => $orModel1,
            'headers' => $orHeaders,
        ];
    }
    // 2) OpenRouter — model kedua (fallback dalam OpenRouter)
    if ($orKey !== '' && $orModel2 !== '') {
        $list[] = [
            'name'    => 'OpenRouter',
            'kind'    => 'openai_compat',
            'url'     => AI_OPENROUTER_URL,
            'key'     => $orKey,
            'model'   => $orModel2,
            'headers' => $orHeaders,
        ];
    }
    // 3) Groq — SECONDARY
    if ($grKey !== '' && $grModel !== '') {
        $list[] = [
            'name'    => 'Groq',
            'kind'    => 'openai_compat',
            'url'     => AI_GROQ_URL,
            'key'     => $grKey,
            'model'   => $grModel,
            'headers' => [],
        ];
    }
    // 4) Gemini — LAST RESORT
    if ($gmKey !== '' && $gmModel !== '') {
        $list[] = [
            'name'    => 'Gemini',
            'kind'    => 'gemini',
            'url'     => rtrim(GEMINI_API_BASE, '/').'/models/'.rawurlencode($gmModel).':generateContent',
            'key'     => $gmKey,
            'model'   => $gmModel,
            'headers' => [],
        ];
    }

    return $list;
}

// ============================================================
// Multi-key Gemini (backward-compat) — tetap dipakai oleh
// gemini_vision / gemini_audio karena hanya Gemini yang mendukung
// modalitas tsb pada router ini.
// ============================================================
function _gemini_keys() {
    $keys = [];
    $push = function($v) use (&$keys) {
        if ($v === '' || stripos($v,'GANTI')!==false) return;
        $keys[] = $v;
    };
    $push(_ai_env('GEMINI_API_KEY'));
    for ($i = 1; $i <= 20; $i++) $push(_ai_env('GEMINI_API_KEY_' . $i));
    $multi = _ai_env('GEMINI_API_KEYS');
    if ($multi) {
        foreach (preg_split('/[,\s;]+/', $multi) as $kk) $push(trim($kk));
    }
    if (!$keys && GEMINI_API_KEY_DEFAULT !== '') $keys[] = GEMINI_API_KEY_DEFAULT;
    $tok = _ai_env('GEMINI_ACCESS_TOKEN');
    if (!$keys && $tok) $keys[] = $tok;
    return array_values(array_unique(array_filter($keys)));
}
function _gemini_key() {
    $l = _gemini_keys();
    return $l[0] ?? '';
}
function _gemini_model() {
    // Model Gemini WAJIB dari env (tidak ada hardcode default).
    return _ai_env('GEMINI_MODEL');
}
function _gemini_key_kind($k) {
    if ($k === '')                    return 'missing';
    if (strpos($k, 'AIza') === 0)     return 'api_key';
    if (strpos($k, 'AQ.')  === 0)     return 'auth_key';
    if (strpos($k, 'ya29.') === 0)    return 'oauth_bearer';
    return 'unknown';
}

function gemini_config_status() {
    $providers = _ai_router_providers();
    return [
        'router_version'     => AI_ROUTER_VERSION,
        'providers_active'   => array_map(function($p){ return $p['name'].' ('.$p['model'].')'; }, $providers),
        'gemini_key_count'   => count(_gemini_keys()),
        'gemini_model'       => _gemini_model(),
        'openrouter_model'   => _ai_env('OPENROUTER_MODEL'),
        'openrouter_model_2' => _ai_env('OPENROUTER_MODEL_2'),
        'groq_model'         => _ai_env('GROQ_MODEL'),
        'endpoint_gemini'    => GEMINI_API_BASE,
        'endpoint_openrouter'=> AI_OPENROUTER_URL,
        'endpoint_groq'      => AI_GROQ_URL,
    ];
}

// ============================================================
// HTTP helper (curl) — dipakai semua provider.
// ============================================================
function _ai_http_post($url, array $headers, $bodyStr, $timeout = 60) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>15,
        CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$bodyStr,
    ];
    if (getenv('GEMINI_INSECURE_SSL') === '1') {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    $proxy = getenv('GEMINI_HTTP_PROXY') ?: getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '';
    if ($proxy) {
        $opts[CURLOPT_PROXY] = $proxy;
        if (stripos($proxy,'socks5h://')===0)    $opts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5_HOSTNAME;
        elseif (stripos($proxy,'socks5://')===0) $opts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$resp, 'code'=>$code, 'cerr'=>$cerr];
}

// Deteksi apakah kegagalan bersifat transient / layak dilanjut ke provider
// berikutnya (timeout, 429, 5xx, quota, provider unavailable, network error).
function _ai_is_transient($code, $cerr, $msg = '') {
    if ($cerr !== '') return true; // network / timeout / SSL
    if ($code === 0)  return true;
    if ($code === 408 || $code === 425 || $code === 429) return true;
    if ($code >= 500 && $code < 600) return true;
    $m = strtolower($msg);
    foreach (['quota','exceeded','rate limit','resource_exhausted','overloaded',
              'unavailable','timeout','try again','high demand','no provider',
              'provider returned error','upstream'] as $needle) {
        if ($m !== '' && strpos($m, $needle) !== false) return true;
    }
    return false;
}

// ============================================================
// Panggilan per provider — OpenAI-compatible (OpenRouter/Groq).
// ============================================================
function _ai_call_openai_compat(array $prov, $prompt, array $opts) {
    $messages = [];
    if (!empty($opts['system'])) $messages[] = ['role'=>'system','content'=>(string)$opts['system']];
    $messages[] = ['role'=>'user','content'=>(string)$prompt];

    $body = [
        'model'       => $prov['model'],
        'messages'    => $messages,
        'temperature' => isset($opts['temperature']) ? (float)$opts['temperature'] : 0.5,
        'max_tokens'  => isset($opts['max_tokens'])  ? (int)$opts['max_tokens']    : 800,
    ];
    if (!empty($opts['json'])) $body['response_format'] = ['type'=>'json_object'];

    $headers = array_merge(
        ['Content-Type: application/json', 'Authorization: Bearer '.$prov['key']],
        $prov['headers'] ?? []
    );
    $bodyStr = json_encode($body, JSON_UNESCAPED_UNICODE);

    // Retry maksimal 1x per provider (total 2 attempt).
    $lastErr = ''; $lastCode = 0; $lastRaw = null;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        _ai_log($prov['name'], $prov['model'], 'attempt='.$attempt);
        $r = _ai_http_post($prov['url'], $headers, $bodyStr, 60);
        $resp = $r['resp']; $code = $r['code']; $cerr = $r['cerr'];

        if ($resp === false || $resp === '') {
            $lastErr = 'network: '.$cerr;
            $lastCode = $code;
            if (_ai_is_transient($code, $cerr) && $attempt < 2) { usleep(400000); continue; }
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].' '.$lastErr,'code'=>$code,'transient'=>true];
        }
        $json = json_decode($resp, true);
        $lastRaw = $json;
        if (!is_array($json)) {
            $lastErr = 'respons bukan JSON (HTTP '.$code.')';
            if (_ai_is_transient($code, '', $lastErr) && $attempt < 2) { usleep(400000); continue; }
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].' '.$lastErr,'code'=>$code,'transient'=>_ai_is_transient($code,'',$lastErr)];
        }
        if ($code < 200 || $code >= 300 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? ($json['error'] ?? ('HTTP '.$code));
            if (is_array($msg)) $msg = json_encode($msg);
            $msg = (string)$msg;
            $lastErr = substr($msg, 0, 240);
            $transient = _ai_is_transient($code, '', $msg);
            if ($transient && $attempt < 2) { usleep(400000); continue; }
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].': '.$lastErr,'code'=>$code,'transient'=>$transient,'raw'=>$json];
        }
        // sukses
        $text = $json['choices'][0]['message']['content'] ?? '';
        if (is_array($text)) {
            $buf=''; foreach ($text as $pp) { $buf .= is_array($pp) ? ($pp['text'] ?? '') : (string)$pp; } $text=$buf;
        }
        return ['ok'=>true,'text'=>(string)$text,'err'=>'','raw'=>$json,'via'=>$prov['name'],'model'=>$prov['model']];
    }
    return ['ok'=>false,'text'=>'','err'=>$prov['name'].': '.$lastErr,'code'=>$lastCode,'transient'=>true,'raw'=>$lastRaw];
}

// ============================================================
// Panggilan Gemini (untuk teks via router, dan multimodal).
// ============================================================
function _ai_call_gemini_text(array $prov, $prompt, array $opts) {
    $parts = [['text' => (string)$prompt]];
    return _ai_call_gemini_parts($prov, $parts, $opts);
}

function _ai_call_gemini_parts(array $prov, array $parts, array $opts) {
    $body = [
        'contents' => [[ 'role'=>'user', 'parts'=>$parts ]],
        'generationConfig' => [
            'temperature'     => isset($opts['temperature']) ? (float)$opts['temperature'] : 0.5,
            'maxOutputTokens' => isset($opts['max_tokens'])  ? (int)$opts['max_tokens']    : 800,
        ],
    ];
    if (!empty($opts['json']))   $body['generationConfig']['responseMimeType'] = 'application/json';
    if (!empty($opts['system'])) $body['systemInstruction'] = ['role'=>'system','parts'=>[['text'=>(string)$opts['system']]]];

    $kind = _gemini_key_kind($prov['key']);
    $headers = ['Content-Type: application/json'];
    if ($kind === 'oauth_bearer') $headers[] = 'Authorization: Bearer '.$prov['key'];
    else                          $headers[] = 'x-goog-api-key: '.$prov['key'];

    $bodyStr = json_encode($body, JSON_UNESCAPED_UNICODE);

    // Retry 1x per key. Bila key pertama transient-fail, coba key berikutnya
    // (masih dianggap 1 provider = Gemini). Batasi total 2 attempt tiap key.
    $keys = _gemini_keys();
    if (!$keys) return ['ok'=>false,'text'=>'','err'=>'Gemini: tidak ada API key','transient'=>false];

    $lastErr = '';
    foreach ($keys as $ki => $key) {
        // update header untuk key ini
        $kk = _gemini_key_kind($key);
        $h  = ['Content-Type: application/json'];
        $h[] = ($kk === 'oauth_bearer') ? ('Authorization: Bearer '.$key) : ('x-goog-api-key: '.$key);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            _ai_log('Gemini', $prov['model'], 'key#'.($ki+1).' attempt='.$attempt);
            $r = _ai_http_post($prov['url'], $h, $bodyStr, 60);
            $resp = $r['resp']; $code = $r['code']; $cerr = $r['cerr'];

            if ($resp === false || $resp === '') {
                $lastErr = 'network: '.$cerr;
                if ($attempt < 2) { usleep(400000); continue; }
                break; // coba key berikutnya
            }
            $json = json_decode($resp, true);
            if (!is_array($json)) {
                $lastErr = 'respons bukan JSON (HTTP '.$code.')';
                if ($attempt < 2) { usleep(400000); continue; }
                break;
            }
            if ($code < 200 || $code >= 300 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? ('HTTP '.$code);
                $lastErr = substr((string)$msg, 0, 240);
                $transient = _ai_is_transient($code, '', (string)$msg);
                if ($transient && $attempt < 2) { usleep(400000); continue; }
                if ($transient) break; // rotasi key
                return ['ok'=>false,'text'=>'','err'=>'Gemini: '.$lastErr,'code'=>$code,'transient'=>false,'raw'=>$json];
            }
            // sukses
            $text = '';
            if (isset($json['candidates'][0]['content']['parts']) && is_array($json['candidates'][0]['content']['parts'])) {
                foreach ($json['candidates'][0]['content']['parts'] as $p) if (isset($p['text'])) $text .= $p['text'];
            }
            return ['ok'=>true,'text'=>$text,'err'=>'','raw'=>$json,'via'=>'Gemini','model'=>$prov['model']];
        }
    }
    return ['ok'=>false,'text'=>'','err'=>'Gemini: '.$lastErr,'transient'=>true];
}

// ============================================================
// PUBLIC — gemini_text() sekarang adalah AI Router.
// Nama fungsi dipertahankan agar seluruh halaman lama tetap jalan.
// ============================================================
function _ai_router_skipped_reasons() {
    $skipped = [];
    if (_ai_env('OPENROUTER_API_KEY') === '') $skipped[] = 'OpenRouter (OPENROUTER_API_KEY kosong)';
    elseif (_ai_env('OPENROUTER_MODEL') === '') $skipped[] = 'OpenRouter#1 (OPENROUTER_MODEL kosong)';
    if (_ai_env('OPENROUTER_API_KEY') !== '' && _ai_env('OPENROUTER_MODEL_2') === '') $skipped[] = 'OpenRouter#2 (OPENROUTER_MODEL_2 kosong)';
    if (_ai_env('GROQ_API_KEY') === '') $skipped[] = 'Groq (GROQ_API_KEY kosong)';
    elseif (_ai_env('GROQ_MODEL') === '') $skipped[] = 'Groq (GROQ_MODEL kosong)';
    return $skipped;
}

function gemini_text($prompt, array $opts = []) {
    $providers = _ai_router_providers();
    if (!$providers) {
        return ['ok'=>false,'text'=>'','err'=>
            'AI Router: tidak ada provider aktif. Set env OPENROUTER_API_KEY+OPENROUTER_MODEL, '.
            'GROQ_API_KEY+GROQ_MODEL, atau GEMINI_API_KEY+GEMINI_MODEL.'];
    }

    $errs = [];
    foreach ($providers as $idx => $prov) {
        if ($prov['kind'] === 'gemini') {
            $r = _ai_call_gemini_text($prov, $prompt, $opts);
        } else {
            $r = _ai_call_openai_compat($prov, $prompt, $opts);
        }
        if (!empty($r['ok'])) {
            _ai_log($prov['name'], $prov['model'], 'OK'.($idx>0?(' (fallback #'.$idx.')'):''));
            return $r;
        }
        $errs[] = ($r['err'] ?? '?');
        _ai_log($prov['name'], $prov['model'], 'FAIL → next provider: '.substr($r['err'] ?? '?', 0, 160));
        // Selalu lanjut ke provider berikutnya jika masih ada.
    }
    $skip = _ai_router_skipped_reasons();
    $msg = 'Semua provider AI gagal: '.implode(' | ', $errs);
    if ($skip) $msg .= ' || Provider dilewati (env kosong): '.implode(', ', $skip);
    return ['ok'=>false,'text'=>'','err'=>$msg];
}

// ============================================================
// Multimodal — Vision & Audio tetap via Gemini (satu-satunya
// provider di router ini yang mendukung modalitas tsb).
// ============================================================
function _ai_gemini_only_provider() {
    $model = _gemini_model();
    $key   = _gemini_key();
    if ($model === '' || $key === '') return null;
    return [
        'name'  => 'Gemini',
        'kind'  => 'gemini',
        'url'   => rtrim(GEMINI_API_BASE, '/').'/models/'.rawurlencode($model).':generateContent',
        'key'   => $key,
        'model' => $model,
    ];
}

function gemini_vision($prompt, $imagePath, array $opts = []) {
    $prov = _ai_gemini_only_provider();
    if (!$prov) return ['ok'=>false,'text'=>'','err'=>'Vision butuh GEMINI_API_KEY + GEMINI_MODEL di env.'];
    if (!is_file($imagePath)) return ['ok'=>false,'text'=>'','err'=>'file gambar tidak ada'];
    $bytes = @file_get_contents($imagePath);
    if ($bytes === false || $bytes === '') return ['ok'=>false,'text'=>'','err'=>'gagal baca gambar'];
    $mime = 'image/jpeg';
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($imagePath);
        if (is_string($m) && $m !== '') $mime = $m;
    }
    $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $map = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'];
    if (isset($map[$ext])) $mime = $map[$ext];
    $parts = [
        ['text' => (string)$prompt],
        ['inline_data' => ['mime_type'=>$mime,'data'=>base64_encode($bytes)]],
    ];
    return _ai_call_gemini_parts($prov, $parts, $opts);
}

function gemini_audio($prompt, $audioPath, array $opts = []) {
    $prov = _ai_gemini_only_provider();
    if (!$prov) return ['ok'=>false,'text'=>'','err'=>'Audio butuh GEMINI_API_KEY + GEMINI_MODEL di env.'];
    if (!is_file($audioPath)) return ['ok'=>false,'text'=>'','err'=>'file audio tidak ada'];
    $bytes = @file_get_contents($audioPath);
    if ($bytes === false || $bytes === '') return ['ok'=>false,'text'=>'','err'=>'gagal baca audio'];
    $mime = 'audio/mpeg';
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($audioPath);
        if (is_string($m) && stripos($m,'audio/')===0) $mime = $m;
    }
    $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
    $map = ['mp3'=>'audio/mpeg','wav'=>'audio/wav','m4a'=>'audio/mp4','mp4'=>'audio/mp4',
            'ogg'=>'audio/ogg','webm'=>'audio/webm','aac'=>'audio/aac','flac'=>'audio/flac'];
    if (isset($map[$ext])) $mime = $map[$ext];
    $parts = [
        ['text' => (string)$prompt],
        ['inline_data' => ['mime_type'=>$mime,'data'=>base64_encode($bytes)]],
    ];
    return _ai_call_gemini_parts($prov, $parts, $opts);
}

// ============================================================
// Ekstraksi JSON dari teks (dukung ```json fences).
// ============================================================
function gemini_extract_json($text) {
    $s = trim((string)$text);
    if ($s === '') return [];
    if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $s, $m))       $s = $m[1];
    elseif (preg_match('/```(?:json)?\s*(.+)$/is', $s, $m2))         $s = $m2[1];
    $j = json_decode($s, true);
    if (is_array($j)) return $j;
    foreach ([['{','}'], ['[',']']] as $pair) {
        $start = strpos($s, $pair[0]);
        $end   = strrpos($s, $pair[1]);
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($s, $start, $end - $start + 1);
            $j = json_decode($sub, true);
            if (is_array($j)) return $j;
            $sub2 = preg_replace('/,\s*([}\]])/', '$1', $sub);
            $sub2 = preg_replace('/[\x00-\x1F\x7F]/', ' ', $sub2);
            $j = json_decode($sub2, true);
            if (is_array($j)) return $j;
        }
    }
    return [];
}

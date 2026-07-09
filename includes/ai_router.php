<?php
/**
 * ============================================================
 *  UNIVERSAL AI ROUTER  (includes/ai_router.php)
 * ============================================================
 *  Satu pintu untuk SEMUA request AI: chat/teks, vision/OCR,
 *  screenshot Strava, parsing data olahraga, audio, dsb.
 *
 *  TIDAK ADA lagi pemanggilan langsung ke Gemini API di halaman
 *  manapun. Semua lewat fungsi publik:
 *
 *      ai_chat($prompt, $opts)            -> teks
 *      ai_vision($prompt, $imagePath, $o) -> gambar / screenshot
 *      ai_audio($prompt, $audioPath, $o)  -> audio
 *      ai_extract_json($text)             -> parse JSON dari output
 *      ai_config_status()                 -> status provider aktif
 *
 *  URUTAN PROVIDER (otomatis fallback bila gagal):
 *  ------------------------------------------------------------
 *  TEXT:
 *    1) OpenRouter  -> OPENROUTER_FREE_MODEL   (PRIMARY, gratis)
 *    2) OpenRouter  -> OPENROUTER_MODEL        (deepseek/deepseek-chat-v3)
 *    3) Groq        -> GROQ_MODEL              (llama-3.3-70b-versatile)
 *    4) Gemini      -> GEMINI_MODEL            (gemini-2.5-flash, LAST)
 *
 *  VISION:
 *    1) OpenRouter  -> OPENROUTER_FREE_MODEL   (vision gratis)
 *    2) Gemini      -> GEMINI_MODEL            (LAST)
 *
 *  AUDIO:
 *    1) Gemini      -> GEMINI_MODEL
 *       (provider lain di router ini belum mendukung audio)
 *
 *  ATURAN:
 *   - SEMUA model & API key WAJIB dari ENV. Tidak ada model
 *     hardcode di source. Provider dgn env kosong dilewati.
 *   - Fallback dipicu oleh: quota, timeout, rate limit,
 *     provider unavailable, 500/502/503/504, network error.
 *   - Error TIDAK ditampilkan ke user sebelum SEMUA provider
 *     dicoba.
 *   - Logging tiap percobaan ke error_log (lihat _air_log()).
 * ============================================================
 */

if (!defined('AI_ROUTER_VERSION')) define('AI_ROUTER_VERSION', 'UNIVERSAL-'.date('Ymd'));

// ---- Endpoint (bukan model — model tetap dari env) ----
if (!defined('AI_OPENROUTER_URL')) define('AI_OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions');
if (!defined('AI_GROQ_URL'))       define('AI_GROQ_URL',       'https://api.groq.com/openai/v1/chat/completions');

if (!defined('GEMINI_API_BASE')) {
    $__base = getenv('GEMINI_API_BASE');
    if (!$__base) $__base = $_ENV['GEMINI_API_BASE']    ?? '';
    if (!$__base) $__base = $_SERVER['GEMINI_API_BASE'] ?? '';
    if (!$__base) $__base = 'https://generativelanguage.googleapis.com/v1beta';
    define('GEMINI_API_BASE', rtrim((string)$__base, '/'));
}

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
function _air_env($name) {
    $v = getenv($name);
    if ($v === false || $v === '') $v = $_ENV[$name]    ?? '';
    if ($v === '')                 $v = $_SERVER[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

/**
 * Logging seragam:
 *   [AI] Provider: OpenRouter | Model: openrouter/free | Vision: true
 *        | Response Time: 1234ms | Fallback: #1 | Error: ...
 */
function _air_log(array $info) {
    $line = '[AI]';
    $line .= ' Provider: ' . ($info['provider'] ?? '-');
    $line .= ' | Model: '  . ($info['model']    ?? '-');
    $line .= ' | Vision: ' . (!empty($info['vision']) ? 'true' : 'false');
    if (isset($info['ms']))       $line .= ' | Response Time: ' . (int)$info['ms'] . 'ms';
    if (isset($info['fallback'])) $line .= ' | Fallback: #' . (int)$info['fallback'];
    if (!empty($info['status']))  $line .= ' | ' . $info['status'];
    if (!empty($info['error']))   $line .= ' | Error: ' . substr((string)$info['error'], 0, 200);
    error_log($line);
}

// ============================================================
// Multi-key Gemini (backward-compat, LAST RESORT)
// ============================================================
function _air_gemini_keys() {
    $keys = [];
    $push = function($v) use (&$keys) {
        if ($v === '' || stripos($v, 'GANTI') !== false) return;
        $keys[] = $v;
    };
    $push(_air_env('GEMINI_API_KEY'));
    for ($i = 1; $i <= 20; $i++) $push(_air_env('GEMINI_API_KEY_' . $i));
    $multi = _air_env('GEMINI_API_KEYS');
    if ($multi) foreach (preg_split('/[,\s;]+/', $multi) as $kk) $push(trim($kk));
    $tok = _air_env('GEMINI_ACCESS_TOKEN');
    if (!$keys && $tok) $keys[] = $tok;
    return array_values(array_unique(array_filter($keys)));
}
function _air_gemini_key() { $l = _air_gemini_keys(); return $l[0] ?? ''; }
function _air_gemini_key_kind($k) {
    if ($k === '')                  return 'missing';
    if (strpos($k, 'ya29.') === 0)  return 'oauth_bearer';
    return 'api_key';
}

// ============================================================
// Daftar provider berdasarkan modalitas (text / vision).
// Model 100% dari env — kalau kosong, provider dilewati.
// ============================================================
function _air_openrouter_headers() {
    return [
        'HTTP-Referer: ' . (_air_env('APP_URL') ?: 'https://sportapp.local'),
        'X-Title: SportApp',
    ];
}

function _air_providers_text() {
    if (_air_env('AI_ROUTER_DISABLE') === '1') {
        // paksa Gemini saja (debug)
        return _air_gemini_provider() ? [_air_gemini_provider()] : [];
    }
    $orKey    = _air_env('OPENROUTER_API_KEY');
    $orFree   = _air_env('OPENROUTER_FREE_MODEL');
    $orModel  = _air_env('OPENROUTER_MODEL');
    $grKey    = _air_env('GROQ_API_KEY');
    $grModel  = _air_env('GROQ_MODEL');

    $list = [];
    // 1) OpenRouter FREE (PRIMARY)
    if ($orKey !== '' && $orFree !== '') {
        $list[] = ['name'=>'OpenRouter','kind'=>'openai_compat','url'=>AI_OPENROUTER_URL,
                   'key'=>$orKey,'model'=>$orFree,'headers'=>_air_openrouter_headers()];
    }
    // 2) OpenRouter deepseek (SECONDARY, text-only)
    if ($orKey !== '' && $orModel !== '') {
        $list[] = ['name'=>'OpenRouter','kind'=>'openai_compat','url'=>AI_OPENROUTER_URL,
                   'key'=>$orKey,'model'=>$orModel,'headers'=>_air_openrouter_headers()];
    }
    // 3) Groq (THIRD, text-only)
    if ($grKey !== '' && $grModel !== '') {
        $list[] = ['name'=>'Groq','kind'=>'openai_compat','url'=>AI_GROQ_URL,
                   'key'=>$grKey,'model'=>$grModel,'headers'=>[]];
    }
    // 4) Gemini (LAST RESORT)
    if ($p = _air_gemini_provider()) $list[] = $p;
    return $list;
}

function _air_providers_vision() {
    if (_air_env('AI_ROUTER_DISABLE') === '1') {
        return _air_gemini_provider() ? [_air_gemini_provider()] : [];
    }
    $orKey  = _air_env('OPENROUTER_API_KEY');
    $orFree = _air_env('OPENROUTER_FREE_MODEL');

    $list = [];
    // 1) OpenRouter Vision FREE (PRIMARY)
    if ($orKey !== '' && $orFree !== '') {
        $list[] = ['name'=>'OpenRouter','kind'=>'openai_compat','url'=>AI_OPENROUTER_URL,
                   'key'=>$orKey,'model'=>$orFree,'headers'=>_air_openrouter_headers()];
    }
    // 2) Gemini Vision (LAST RESORT)
    if ($p = _air_gemini_provider()) $list[] = $p;
    return $list;
}

function _air_gemini_provider() {
    $key   = _air_gemini_key();
    $model = _air_env('GEMINI_MODEL');
    if ($key === '' || $model === '') return null;
    return ['name'=>'Gemini','kind'=>'gemini','model'=>$model,'key'=>$key,
            'url'=>rtrim(GEMINI_API_BASE,'/').'/models/'.rawurlencode($model).':generateContent'];
}

// ============================================================
// HTTP helper (curl)
// ============================================================
function _air_http_post($url, array $headers, $bodyStr, $timeout = 60) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>15,
        CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$bodyStr,
    ];
    if (getenv('GEMINI_INSECURE_SSL') === '1' || getenv('AI_INSECURE_SSL') === '1') {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    $proxy = getenv('AI_HTTP_PROXY') ?: getenv('GEMINI_HTTP_PROXY') ?: getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '';
    if ($proxy) {
        $opts[CURLOPT_PROXY] = $proxy;
        if (stripos($proxy,'socks5h://')===0)    $opts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5_HOSTNAME;
        elseif (stripos($proxy,'socks5://')===0) $opts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5;
    }
    curl_setopt_array($ch, $opts);
    $t0   = microtime(true);
    $resp = curl_exec($ch);
    $ms   = (int)round((microtime(true)-$t0)*1000);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$resp,'code'=>$code,'cerr'=>$cerr,'ms'=>$ms];
}

// Kegagalan transient => lanjut provider berikutnya.
function _air_is_transient($code, $cerr, $msg = '') {
    if ($cerr !== '') return true;      // network / timeout / SSL
    if ($code === 0)  return true;
    if (in_array($code, [408,425,429], true)) return true;
    if ($code >= 500 && $code < 600) return true;
    $m = strtolower($msg);
    foreach (['quota','exceeded','rate limit','resource_exhausted','overloaded',
              'unavailable','timeout','try again','high demand','no provider',
              'provider returned error','upstream','capacity','busy'] as $needle) {
        if ($m !== '' && strpos($m, $needle) !== false) return true;
    }
    return false;
}

// ============================================================
// Panggilan OpenAI-compatible (OpenRouter / Groq) — text & vision.
// $content bisa string (text) atau array parts (vision).
// ============================================================
function _air_call_openai_compat(array $prov, $content, array $opts, $isVision = false) {
    $messages = [];
    if (!empty($opts['system'])) $messages[] = ['role'=>'system','content'=>(string)$opts['system']];
    $messages[] = ['role'=>'user','content'=>$content];

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

    $lastErr = ''; $lastCode = 0; $lastMs = 0;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $r = _air_http_post($prov['url'], $headers, $bodyStr, 60);
        $resp=$r['resp']; $code=$r['code']; $cerr=$r['cerr']; $lastMs=$r['ms'];

        if ($resp === false || $resp === '') {
            $lastErr='network: '.$cerr; $lastCode=$code;
            if (_air_is_transient($code,$cerr) && $attempt<2){usleep(400000);continue;}
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].' '.$lastErr,'code'=>$code,'transient'=>true,'ms'=>$lastMs];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            $lastErr='respons bukan JSON (HTTP '.$code.')';
            if (_air_is_transient($code,'',$lastErr) && $attempt<2){usleep(400000);continue;}
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].' '.$lastErr,'code'=>$code,'transient'=>_air_is_transient($code,'',$lastErr),'ms'=>$lastMs];
        }
        if ($code<200 || $code>=300 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? ($json['error'] ?? ('HTTP '.$code));
            if (is_array($msg)) $msg = json_encode($msg);
            $lastErr = substr((string)$msg, 0, 240);
            $transient = _air_is_transient($code,'',(string)$msg);
            if ($transient && $attempt<2){usleep(400000);continue;}
            return ['ok'=>false,'text'=>'','err'=>$prov['name'].': '.$lastErr,'code'=>$code,'transient'=>$transient,'ms'=>$lastMs];
        }
        $text = $json['choices'][0]['message']['content'] ?? '';
        if (is_array($text)) { $buf=''; foreach($text as $pp){$buf.=is_array($pp)?($pp['text']??''):(string)$pp;} $text=$buf; }
        return ['ok'=>true,'text'=>(string)$text,'err'=>'','via'=>$prov['name'],'model'=>$prov['model'],'ms'=>$lastMs];
    }
    return ['ok'=>false,'text'=>'','err'=>$prov['name'].': '.$lastErr,'code'=>$lastCode,'transient'=>true,'ms'=>$lastMs];
}

// ============================================================
// Panggilan Gemini (text & multimodal via parts).
// ============================================================
function _air_call_gemini_parts(array $prov, array $parts, array $opts) {
    $body = [
        'contents' => [['role'=>'user','parts'=>$parts]],
        'generationConfig' => [
            'temperature'     => isset($opts['temperature']) ? (float)$opts['temperature'] : 0.5,
            'maxOutputTokens' => isset($opts['max_tokens'])  ? (int)$opts['max_tokens']    : 800,
        ],
    ];
    if (!empty($opts['json']))   $body['generationConfig']['responseMimeType'] = 'application/json';
    if (!empty($opts['system'])) $body['systemInstruction'] = ['role'=>'system','parts'=>[['text'=>(string)$opts['system']]]];
    $bodyStr = json_encode($body, JSON_UNESCAPED_UNICODE);

    $keys = _air_gemini_keys();
    if (!$keys) return ['ok'=>false,'text'=>'','err'=>'Gemini: tidak ada API key','transient'=>false,'ms'=>0];

    $lastErr=''; $lastMs=0;
    foreach ($keys as $ki => $key) {
        $kk = _air_gemini_key_kind($key);
        $h  = ['Content-Type: application/json'];
        $h[] = ($kk === 'oauth_bearer') ? ('Authorization: Bearer '.$key) : ('x-goog-api-key: '.$key);

        for ($attempt=1; $attempt<=2; $attempt++) {
            $r = _air_http_post($prov['url'], $h, $bodyStr, 60);
            $resp=$r['resp']; $code=$r['code']; $cerr=$r['cerr']; $lastMs=$r['ms'];

            if ($resp === false || $resp === '') { $lastErr='network: '.$cerr; if($attempt<2){usleep(400000);continue;} break; }
            $json = json_decode($resp, true);
            if (!is_array($json)) { $lastErr='respons bukan JSON (HTTP '.$code.')'; if($attempt<2){usleep(400000);continue;} break; }
            if ($code<200 || $code>=300 || isset($json['error'])) {
                $msg = $json['error']['message'] ?? ('HTTP '.$code);
                $lastErr = substr((string)$msg, 0, 240);
                $transient = _air_is_transient($code,'',(string)$msg);
                if ($transient && $attempt<2){usleep(400000);continue;}
                if ($transient) break; // rotasi key
                return ['ok'=>false,'text'=>'','err'=>'Gemini: '.$lastErr,'code'=>$code,'transient'=>false,'ms'=>$lastMs];
            }
            $text='';
            if (isset($json['candidates'][0]['content']['parts']) && is_array($json['candidates'][0]['content']['parts'])) {
                foreach ($json['candidates'][0]['content']['parts'] as $p) if (isset($p['text'])) $text .= $p['text'];
            }
            return ['ok'=>true,'text'=>$text,'err'=>'','via'=>'Gemini','model'=>$prov['model'],'ms'=>$lastMs];
        }
    }
    return ['ok'=>false,'text'=>'','err'=>'Gemini: '.$lastErr,'transient'=>true,'ms'=>$lastMs];
}

// ============================================================
// Bantu: baca file gambar/audio -> [mime, base64]
// ============================================================
function _air_read_media($path, $default = 'application/octet-stream', array $map = []) {
    if (!is_file($path)) return [null, null, 'file tidak ada'];
    $bytes = @file_get_contents($path);
    if ($bytes === false || $bytes === '') return [null, null, 'gagal baca file'];
    $mime = $default;
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') $mime = $m;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (isset($map[$ext])) $mime = $map[$ext];
    return [$mime, base64_encode($bytes), ''];
}

// ============================================================
// PUBLIC API — ai_chat / ai_vision / ai_audio
// ============================================================
function ai_chat($prompt, array $opts = []) {
    $providers = _air_providers_text();
    if (!$providers) {
        return ['ok'=>false,'text'=>'','err'=>
            'AI Router: tidak ada provider aktif. Isi OPENROUTER_API_KEY (+OPENROUTER_FREE_MODEL), '.
            'GROQ_API_KEY (+GROQ_MODEL), atau GEMINI_API_KEY (+GEMINI_MODEL) di config/env.local.php.'];
    }
    $errs = [];
    foreach ($providers as $idx => $prov) {
        $r = ($prov['kind']==='gemini')
            ? _air_call_gemini_parts($prov, [['text'=>(string)$prompt]], $opts)
            : _air_call_openai_compat($prov, (string)$prompt, $opts, false);

        _air_log(['provider'=>$prov['name'],'model'=>$prov['model'],'vision'=>false,
                  'ms'=>$r['ms']??null,'fallback'=>$idx,
                  'status'=>!empty($r['ok'])?'OK':'FAIL -> next provider','error'=>$r['ok']?'':($r['err']??'')]);

        if (!empty($r['ok'])) return $r;
        $errs[] = ($prov['name'].': '.($r['err'] ?? '?'));
    }
    return ['ok'=>false,'text'=>'','err'=>'Semua provider AI gagal: '.implode(' | ', $errs)];
}

function ai_vision($prompt, $imagePath, array $opts = []) {
    $providers = _air_providers_vision();
    if (!$providers) {
        return ['ok'=>false,'text'=>'','err'=>
            'AI Vision: tidak ada provider aktif. Isi OPENROUTER_API_KEY (+OPENROUTER_FREE_MODEL) '.
            'atau GEMINI_API_KEY (+GEMINI_MODEL) di config/env.local.php.'];
    }
    $imgMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'];
    [$mime, $b64, $rerr] = _air_read_media($imagePath, 'image/jpeg', $imgMap);
    if ($rerr !== '') return ['ok'=>false,'text'=>'','err'=>$rerr];

    $errs = [];
    foreach ($providers as $idx => $prov) {
        if ($prov['kind']==='gemini') {
            $parts = [['text'=>(string)$prompt], ['inline_data'=>['mime_type'=>$mime,'data'=>$b64]]];
            $r = _air_call_gemini_parts($prov, $parts, $opts);
        } else {
            // OpenRouter vision: content array (text + image_url data URI)
            $content = [
                ['type'=>'text','text'=>(string)$prompt],
                ['type'=>'image_url','image_url'=>['url'=>'data:'.$mime.';base64,'.$b64]],
            ];
            $r = _air_call_openai_compat($prov, $content, $opts, true);
        }
        _air_log(['provider'=>$prov['name'],'model'=>$prov['model'],'vision'=>true,
                  'ms'=>$r['ms']??null,'fallback'=>$idx,
                  'status'=>!empty($r['ok'])?'OK':'FAIL -> next provider','error'=>$r['ok']?'':($r['err']??'')]);

        if (!empty($r['ok'])) return $r;
        $errs[] = ($prov['name'].': '.($r['err'] ?? '?'));
    }
    return ['ok'=>false,'text'=>'','err'=>'Semua provider Vision gagal: '.implode(' | ', $errs)];
}

function ai_audio($prompt, $audioPath, array $opts = []) {
    // Hanya Gemini yang mendukung audio pada router ini.
    $prov = _air_gemini_provider();
    if (!$prov) return ['ok'=>false,'text'=>'','err'=>'Audio butuh GEMINI_API_KEY + GEMINI_MODEL di env.'];
    $audMap = ['mp3'=>'audio/mpeg','wav'=>'audio/wav','m4a'=>'audio/mp4','mp4'=>'audio/mp4',
               'ogg'=>'audio/ogg','webm'=>'audio/webm','aac'=>'audio/aac','flac'=>'audio/flac'];
    [$mime, $b64, $rerr] = _air_read_media($audioPath, 'audio/mpeg', $audMap);
    if ($rerr !== '') return ['ok'=>false,'text'=>'','err'=>$rerr];
    $parts = [['text'=>(string)$prompt], ['inline_data'=>['mime_type'=>$mime,'data'=>$b64]]];
    $r = _air_call_gemini_parts($prov, $parts, $opts);
    _air_log(['provider'=>'Gemini','model'=>$prov['model'],'vision'=>false,
              'ms'=>$r['ms']??null,'status'=>!empty($r['ok'])?'OK (audio)':'FAIL (audio)',
              'error'=>$r['ok']?'':($r['err']??'')]);
    return $r;
}

// ============================================================
// Ekstraksi JSON dari teks (dukung ```json fences).
// ============================================================
function ai_extract_json($text) {
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

// ============================================================
// Status provider aktif (untuk halaman diagnosa / tes.php).
// ============================================================
function ai_config_status() {
    $text   = _air_providers_text();
    $vision = _air_providers_vision();
    return [
        'router_version'      => AI_ROUTER_VERSION,
        'text_providers'      => array_map(fn($p)=>$p['name'].' ('.$p['model'].')', $text),
        'vision_providers'    => array_map(fn($p)=>$p['name'].' ('.$p['model'].')', $vision),
        'audio_provider'      => _air_gemini_provider() ? ('Gemini ('._air_env('GEMINI_MODEL').')') : '(nonaktif)',
        'openrouter_free'     => _air_env('OPENROUTER_FREE_MODEL'),
        'openrouter_model'    => _air_env('OPENROUTER_MODEL'),
        'groq_model'          => _air_env('GROQ_MODEL'),
        'gemini_model'        => _air_env('GEMINI_MODEL'),
        'gemini_key_count'    => count(_air_gemini_keys()),
        'endpoint_openrouter' => AI_OPENROUTER_URL,
        'endpoint_groq'       => AI_GROQ_URL,
        'endpoint_gemini'     => GEMINI_API_BASE,
    ];
}

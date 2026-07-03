<?php
define('GEMINI_HELPER_VERSION', 'R28-'.date('H:i:s'));
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
    // Revisi R6 (Juli 2026) — endpoint bisa di-override via env
    // GEMINI_API_BASE (mis. reverse proxy Cloudflare Worker di region yang
    // didukung Google untuk bypass error "User location is not supported").
    // Contoh: GEMINI_API_BASE=https://gemini.<user>.workers.dev/v1beta
    $__base = getenv('GEMINI_API_BASE');
    if (!$__base) $__base = $_ENV['GEMINI_API_BASE'] ?? '';
    if (!$__base) $__base = $_SERVER['GEMINI_API_BASE'] ?? '';
    if (!$__base) $__base = 'https://generativelanguage.googleapis.com/v1beta';
    define('GEMINI_API_BASE', rtrim((string)$__base, '/'));
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
 * Sumber key (urutan prioritas, semuanya digabung & deduplikasi):
 *   1. GEMINI_API_KEY           (single key, format lama)
 *   2. GEMINI_API_KEY_1, GEMINI_API_KEY_2, ... GEMINI_API_KEY_20
 *      (Revisi 19 Juni 2026 — format bernomor, paling direkomendasikan
 *       untuk antisipasi quota habis di AI Studio Google)
 *   3. GEMINI_API_KEYS=key1,key2,key3   (CSV/space/semicolon separated)
 * Saat satu key kena 429 / 401 / 403 / RESOURCE_EXHAUSTED, helper
 * otomatis mencoba key berikutnya tanpa mengganggu user.
 */
function _gemini_keys() {
    $keys = [];

    // helper ambil env var dari getenv / $_ENV / $_SERVER
    $readEnv = function($name) {
        $v = getenv($name);
        if ($v === false || $v === '') $v = $_ENV[$name]    ?? '';
        if ($v === '')                 $v = $_SERVER[$name] ?? '';
        return is_string($v) ? trim($v) : '';
    };
    $push = function($v) use (&$keys) {
        if ($v === '' || stripos($v,'GANTI')!==false) return;
        $keys[] = $v;
    };

    // 1) Single key (backward compatible)
    $push($readEnv('GEMINI_API_KEY'));

    // 2) GEMINI_API_KEY_1 .. GEMINI_API_KEY_20 (Revisi 19 Juni 2026)
    for ($i = 1; $i <= 20; $i++) {
        $push($readEnv('GEMINI_API_KEY_' . $i));
    }

    // 3) GEMINI_API_KEYS=key1,key2,... (CSV)
    $multi = $readEnv('GEMINI_API_KEYS');
    if ($multi) {
        foreach (preg_split('/[,\s;]+/', $multi) as $kk) {
            $push(trim($kk));
        }
    }

    if (!$keys && GEMINI_API_KEY_DEFAULT !== '') $keys[] = GEMINI_API_KEY_DEFAULT;
    if (!$keys) {
        $tok = $readEnv('GEMINI_ACCESS_TOKEN');
        if ($tok) $keys[] = $tok;
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
    $all = _gemini_keys();
    return [
        'has_key'    => $k !== '' ? 1 : 0,
        'key_count'  => count($all),
        'mode'       => _gemini_key_kind($k),
        'key_masked' => $k === '' ? '' : (substr($k, 0, 6) . '…' . substr($k, -4)),
        'model'      => _gemini_model(),
        'endpoint'   => GEMINI_API_BASE,
        'proxy'      => getenv('GEMINI_HTTP_PROXY') ?: (getenv('HTTPS_PROXY') ?: (getenv('HTTP_PROXY') ?: '')),
        'fallback_base' => getenv('GEMINI_FALLBACK_BASE') ?: '',
        // Revisi R7 — daftar provider fallback yang aktif (nama saja, tanpa key).
        'fallback_providers' => array_map(function($p){ return $p['name'].' ('.$p['model'].')'; }, _ai_fallback_providers()),
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
    // error_log("===== GEMINI KEYS ====="); // Revisi R2: dimatikan
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
            // Revisi 28 Juni 2026 — dukung proxy via env supaya bisa keluar dari
            // region yang diblok Gemini (error "User location is not supported").
            // Set GEMINI_HTTP_PROXY=http://user:pass@host:port (atau socks5h://...)
            $proxy = getenv('GEMINI_HTTP_PROXY') ?: getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '';
            if ($proxy) {
                $curlOpts[CURLOPT_PROXY] = $proxy;
                if (stripos($proxy, 'socks5h://') === 0) {
                    $curlOpts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
                } elseif (stripos($proxy, 'socks5://') === 0) {
                    $curlOpts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                }
            }
            curl_setopt_array($ch, $curlOpts);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);

            // DEBUG
            // error_log("===== GEMINI DEBUG =====");
            // error_log("URL      : ".$url);
            // error_log("HTTP     : ".$code);
            // error_log("MODEL    : ".$model);
            // error_log("KEY TYPE : ".$kind);
            // error_log("RESP     : ".$resp);

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
            // Revisi R10 — deteksi "model overloaded / high demand / 503 UNAVAILABLE"
            // sebagai transient → rotasi key bila ada cadangan, dan beri pesan ramah.
            $isOverloaded = ($code===503) || stripos($msg,'overloaded')!==false
                       || stripos($msg,'high demand')!==false || stripos($msg,'UNAVAILABLE')!==false
                       || stripos($msg,'try again')!==false;
            // Revisi 27 Juni 2026 — deteksi error region/lokasi tidak didukung
            // ("User location is not supported for the API use") → rotasi key
            // (key lain mungkin terdaftar di project region berbeda) dan
            // tampilkan pesan ramah + saran proxy.
            $isGeo = (stripos($msg,'User location is not supported')!==false)
                  || (stripos($msg,'location is not supported')!==false)
                  || (stripos($msg,'FAILED_PRECONDITION')!==false && stripos($msg,'location')!==false);
            // rotasi key bila quota / 401 / 403 / overloaded / geo-block dan masih ada key cadangan
            if (($isQuota || $isOverloaded || $isGeo || $code===401 || $code===403) && $keyIdx < count($keys)-1) {
                $lastErr = '[key#'.($keyIdx+1).' '.$kind.' gagal: '.substr($msg,0,140).'] — coba key berikutnya';
                continue; // try next key
            }
            if (stripos($msg,'OAuth 2 access token')!==false || stripos($msg,'API key not valid')!==false || $code===401 || $code===403) {
                $msg .= ' [hint: cek format key. Mode: '.$kind.']';
            }
            if ($isQuota) {
                $msg .= ' [Tip: tambahkan GEMINI_API_KEY_1, GEMINI_API_KEY_2, dst. (atau GEMINI_API_KEYS=key1,key2,...) untuk rotasi otomatis saat quota habis.]';
            }
            if ($isOverloaded) {
                $msg = 'Model AI sedang sibuk (high demand). Coba lagi sebentar, atau tambahkan GEMINI_API_KEY cadangan agar otomatis dirotasi.';
            }
            if ($isGeo) {
                error_log("GOOGLE GEO ERROR: ".($json['error']['message'] ?? ''));
                // Revisi R6 — otomatis retry via reverse-proxy fallback bila di-set.
                $fallback = getenv('GEMINI_FALLBACK_BASE') ?: ($_ENV['GEMINI_FALLBACK_BASE'] ?? '') ?: ($_SERVER['GEMINI_FALLBACK_BASE'] ?? '');
                if ($fallback && !isset($opts['__geo_retried'])) {
                    $optsRetry = $opts; $optsRetry['__geo_retried'] = 1;
                    // panggil ulang dengan endpoint alternatif
                    $prevBase = GEMINI_API_BASE;
                    if (!defined('GEMINI_API_BASE_RUNTIME')) {
                        define('GEMINI_API_BASE_RUNTIME', rtrim((string)$fallback,'/'));
                    }
                    $altUrl = rtrim((string)$fallback,'/') . '/models/' . rawurlencode($model) . ':generateContent';
                    $ch2 = curl_init($altUrl);
                    $co2 = [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
                        CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>15,
                        CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$postBody];
                    if (getenv('GEMINI_INSECURE_SSL')==='1') { $co2[CURLOPT_SSL_VERIFYPEER]=false; $co2[CURLOPT_SSL_VERIFYHOST]=0; }
                    curl_setopt_array($ch2, $co2);
                    $resp2 = curl_exec($ch2); $code2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
                    $j2 = json_decode((string)$resp2, true);
                    if (is_array($j2) && $code2>=200 && $code2<300 && !isset($j2['error'])) {
                        $t2='';
                        if (isset($j2['candidates'][0]['content']['parts']) && is_array($j2['candidates'][0]['content']['parts'])) {
                            foreach ($j2['candidates'][0]['content']['parts'] as $pp) if (isset($pp['text'])) $t2 .= $pp['text'];
                        }
                        return ['ok'=>true,'text'=>$t2,'err'=>'','raw'=>$j2,'via'=>'fallback_base'];
                    }
                }
                $msg = 'Layanan Gemini AI menolak permintaan karena lokasi server tidak didukung Google '
                     . '("User location is not supported for the API use"). '
                     . 'Solusi cepat: (1) set env GEMINI_HTTP_PROXY=http://user:pass@host:port (proxy/VPN ke US/SG/JP) lalu reload; '
                     . '(2) set env GEMINI_API_BASE=https://<reverse-proxy-anda>/v1beta (mis. Cloudflare Worker) atau GEMINI_FALLBACK_BASE untuk auto-retry; '
                     . '(3) buat API key baru dari akun Google di region didukung lalu set GEMINI_API_KEY; '
                     . '(4) deploy PHP ke Cloud Run region us-central1 / asia-southeast1.';
            }
            return ['ok'=>false,'text'=>'','err'=>$msg,'code'=>($isGeo?'GEO_BLOCK':($isQuota?'QUOTA':($isOverloaded?'OVERLOADED':'ERR'))),'raw'=>$json];

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

/* ============================================================
 * Revisi R7 (Juli 2026) #6 — FALLBACK provider AI.
 * Bila Gemini gagal (terutama geo-block "User location is not supported",
 * quota habis, model overloaded, atau error jaringan), otomatis switch ke
 * provider lain yang API-nya kompatibel dengan format OpenAI Chat Completions:
 *   1) OpenRouter  — env OPENROUTER_API_KEY (opsional OPENROUTER_MODEL)
 *   2) Groq        — env GROQ_API_KEY       (opsional GROQ_MODEL)
 * Aktif otomatis selama salah satu key tersedia. Bisa dimatikan dengan
 * env AI_FALLBACK_DISABLE=1.
 *
 * Hanya untuk pemanggilan TEKS (gemini_text). Vision/audio tetap Gemini-only
 * karena provider fallback belum tentu mendukung modalitas tsb.
 * ============================================================ */
function _ai_env($name) {
    $v = getenv($name);
    if ($v === false || $v === '') $v = $_ENV[$name]    ?? '';
    if ($v === '')                 $v = $_SERVER[$name] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Daftar provider fallback yang tersedia (berurutan sesuai prioritas). */
function _ai_fallback_providers() {
    if (_ai_env('AI_FALLBACK_DISABLE') === '1') return [];
    $list = [];
    $orKey = _ai_env('OPENROUTER_API_KEY');
    if ($orKey !== '') {
        $list[] = [
            'name'    => 'openrouter',
            'url'     => 'https://openrouter.ai/api/v1/chat/completions',
            'key'     => $orKey,
            'model'   => _ai_env('OPENROUTER_MODEL') ?: 'google/gemini-2.0-flash-001',
            'headers' => [
                'HTTP-Referer: '.(_ai_env('APP_URL') ?: 'https://sportapp.local'),
                'X-Title: SportApp',
            ],
        ];
    }
    $grKey = _ai_env('GROQ_API_KEY');
    if ($grKey !== '') {
        $list[] = [
            'name'    => 'groq',
            'url'     => 'https://api.groq.com/openai/v1/chat/completions',
            'key'     => $grKey,
            'model'   => _ai_env('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
            'headers' => [],
        ];
    }
    return $list;
}

/** Panggil satu provider OpenAI-compatible. Return shape sama dgn _gemini_call. */
function _ai_openai_compatible_call(array $prov, $prompt, array $opts = []) {
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

    $ch = curl_init($prov['url']);
    $curlOpts = [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>15,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>json_encode($body, JSON_UNESCAPED_UNICODE),
    ];
    if (getenv('GEMINI_INSECURE_SSL') === '1') {
        $curlOpts[CURLOPT_SSL_VERIFYPEER]=false; $curlOpts[CURLOPT_SSL_VERIFYHOST]=0;
    }
    // Reuse proxy yang sama bila di-set (berguna bila jaringan lokal butuh proxy).
    $proxy = getenv('GEMINI_HTTP_PROXY') ?: getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '';
    if ($proxy) {
        $curlOpts[CURLOPT_PROXY] = $proxy;
        if (stripos($proxy,'socks5h://')===0)      $curlOpts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5_HOSTNAME;
        elseif (stripos($proxy,'socks5://')===0)   $curlOpts[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5;
    }
    curl_setopt_array($ch, $curlOpts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return ['ok'=>false,'text'=>'','err'=>'curl('.$prov['name'].'): '.$cerr];
    $json = json_decode($resp, true);
    if (!is_array($json)) return ['ok'=>false,'text'=>'','err'=>$prov['name'].' respons bukan JSON (HTTP '.$code.')'];
    if ($code < 200 || $code >= 300 || isset($json['error'])) {
        $msg = $json['error']['message'] ?? ($json['error'] ?? ('HTTP '.$code));
        if (is_array($msg)) $msg = json_encode($msg);
        return ['ok'=>false,'text'=>'','err'=>$prov['name'].': '.substr((string)$msg,0,200),'raw'=>$json];
    }
    $text = $json['choices'][0]['message']['content'] ?? '';
    if (is_array($text)) { // beberapa model balas content sbg array of parts
        $buf=''; foreach ($text as $pp) { $buf .= is_array($pp) ? ($pp['text'] ?? '') : (string)$pp; } $text=$buf;
    }
    return ['ok'=>true,'text'=>(string)$text,'err'=>'','raw'=>$json,'via'=>$prov['name']];
}

/**
 * Cek apakah error Gemini layak di-fallback (geo-block, quota, overloaded,
 * atau error jaringan). Untuk error lain (mis. prompt invalid) tetap fallback
 * juga karena tujuan utama fitur ini: user tetap dapat jawaban.
 */
function _ai_should_fallback(array $r) {
    if (!empty($r['ok'])) return false;
    return true; // selalu coba fallback bila Gemini gagal & provider tersedia
}

function gemini_text($prompt, array $opts = []) {
    $r = _gemini_call([['text' => (string)$prompt]], $opts);
    if (!empty($r['ok'])) return $r;

    // Gemini gagal → coba provider fallback (OpenRouter/Groq) bila tersedia.
    if (_ai_should_fallback($r)) {
        $geminiErr = $r['err'] ?? '';
        foreach (_ai_fallback_providers() as $prov) {
            $fr = _ai_openai_compatible_call($prov, $prompt, $opts);
            if (!empty($fr['ok'])) {
                $fr['fallback_from'] = 'gemini';
                $fr['gemini_err']    = $geminiErr;
                return $fr;
            }
            $geminiErr .= ' | fallback '.$prov['name'].' gagal: '.($fr['err'] ?? '?');
        }
        // Semua fallback gagal → kembalikan error gabungan yang informatif.
        $r['err'] = ($r['err'] ?? 'Gemini gagal')
                  . (count(_ai_fallback_providers()) ? (' | '.$geminiErr)
                     : ' [Tip: set OPENROUTER_API_KEY atau GROQ_API_KEY sebagai fallback otomatis bila Gemini geo-blocked.]');
    }
    return $r;
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

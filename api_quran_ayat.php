<?php
/**
 * api_quran_ayat.php — Proxy server-side untuk mengambil ayat Al-Qur'an
 *  (R15 - perbaikan popup ayat di catatan_hafalan.php & sejarah_nabi.php).
 *
 * Tujuan: menghindari masalah CORS / CSP / jaringan yang membuat fetch
 * langsung dari JS ke equran.id kadang gagal di lingkungan lokal.
 *
 * Parameter (GET):
 *   s    = nomor surat (1..114)   wajib
 *   from = ayat awal (opsional, default 1)
 *   to   = ayat akhir (opsional, default min(jumlahAyat, from+9))
 *
 * Response: HTML siap-tempel ke modal (Content-Type: text/html).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers();
if (!current_user()) { http_response_code(401); echo 'Login diperlukan.'; exit; }

header('Content-Type: text/html; charset=utf-8');

$s    = max(1, min(114, (int)($_GET['s'] ?? 0)));
$from = max(1, (int)($_GET['from'] ?? 1));
$to   = (int)($_GET['to'] ?? 0);
if ($to < $from) $to = $from;
if (($to - $from) > 50) $to = $from + 50; // batas aman

if ($s < 1) { echo '<div class="alert alert-warning small mb-0">Nomor surat tidak dikenali.</div>'; exit; }

function fetch_url(string $url, int $timeout = 8): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'KawanKeringat/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 400) return (string)$body;
        return null;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'header'=>"User-Agent: KawanKeringat/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

// 1) Coba equran.id v2
$raw = fetch_url('https://equran.id/api/v2/surat/'.$s);
$ayatArr = []; $namaLatin = ''; $arti = '';
if ($raw) {
    $j = json_decode($raw, true);
    if (isset($j['data']['ayat']) && is_array($j['data']['ayat'])) {
        $namaLatin = (string)($j['data']['namaLatin'] ?? '');
        $arti      = (string)($j['data']['arti'] ?? '');
        foreach ($j['data']['ayat'] as $a) {
            $ayatArr[] = [
                'no'    => (int)($a['nomorAyat'] ?? 0),
                'arab'  => (string)($a['teksArab'] ?? ''),
                'latin' => (string)($a['teksLatin'] ?? ''),
                'arti'  => (string)($a['teksIndonesia'] ?? ''),
            ];
        }
    }
}

// 2) Fallback: alquran.cloud (Arab) — terjemah Indonesia opsional
if (!$ayatArr) {
    $raw2 = fetch_url('https://api.alquran.cloud/v1/surah/'.$s);
    if ($raw2) {
        $j = json_decode($raw2, true);
        if (isset($j['data']['ayahs']) && is_array($j['data']['ayahs'])) {
            $namaLatin = (string)($j['data']['englishName'] ?? '');
            $arti      = (string)($j['data']['englishNameTranslation'] ?? '');
            foreach ($j['data']['ayahs'] as $a) {
                $ayatArr[] = [
                    'no'    => (int)($a['numberInSurah'] ?? 0),
                    'arab'  => (string)($a['text'] ?? ''),
                    'latin' => '',
                    'arti'  => '',
                ];
            }
        }
        // Tambah terjemahan Indonesia
        $raw3 = fetch_url('https://api.alquran.cloud/v1/surah/'.$s.'/id.indonesian');
        if ($raw3) {
            $j3 = json_decode($raw3, true);
            if (isset($j3['data']['ayahs']) && is_array($j3['data']['ayahs'])) {
                foreach ($j3['data']['ayahs'] as $i => $a) {
                    if (isset($ayatArr[$i])) $ayatArr[$i]['arti'] = (string)($a['text'] ?? '');
                }
            }
        }
    }
}

if (!$ayatArr) {
    echo '<div class="alert alert-danger small mb-0">Gagal memuat ayat (server tidak dapat menghubungi sumber data). Pastikan PHP punya akses internet.</div>';
    exit;
}

$total = count($ayatArr);
if ($to > $total) $to = $total;
if ($from > $total) { $from = 1; $to = min($total, 10); }

echo '<div class="mb-2 small text-muted">Surah '.htmlspecialchars($namaLatin).' · '.htmlspecialchars($arti).' · Ayat '.$from.'–'.$to.' dari '.$total.'</div>';
foreach ($ayatArr as $a) {
    if ($a['no'] < $from || $a['no'] > $to) continue;
    echo '<div class="border-bottom py-2">';
    echo   '<div class="text-end" dir="rtl" style="font-family:\'Amiri\',serif;font-size:1.6rem;line-height:2.2">'.htmlspecialchars($a['arab']).' <span class="badge bg-success">'.$a['no'].'</span></div>';
    if ($a['latin'] !== '') echo '<div class="small text-success fst-italic mt-1">'.htmlspecialchars($a['latin']).'</div>';
    if ($a['arti']  !== '') echo '<div class="small mt-1">'.htmlspecialchars($a['arti']).'</div>';
    echo '</div>';
}
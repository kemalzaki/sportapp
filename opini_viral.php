<?php
/**
 * opini_viral.php — REDESIGN TOTAL (Juli 2026 R31)
 * Dashboard Analisis Sentimen Opini Netizen Indonesia berbasis KOMENTAR YOUTUBE.
 * Sumber tunggal: YouTube Data API v3 (butuh env YOUTUBE_API_KEY).
 * Analisis sentimen: ai_router (ai_chat) — Positif / Netral / Negatif + confidence + alasan.
 *
 * Revisi R31 (10 Juli 2026):
 *   1) Batasi total komentar per pencarian menjadi maksimum 100 (bukan 100 per video)
 *      supaya proses "Cari Opini" jauh lebih cepat (dulu bisa 20×100 = 2000 komentar).
 *   2) Tambah panel "Riwayat Pencarian" & auto-load hasil terakhir untuk keyword
 *      yang sama supaya data yang SUDAH ada di database (opini_viral_search /
 *      opini_viral_comments) langsung tampil, walau request awal sempat timeout.
 *   3) Cache diperpanjang jadi 24 jam (sebelumnya 30 menit) — pencarian keyword
 *      yang sama dalam 24 jam langsung memakai data DB, tidak memanggil YouTube lagi.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/ai_router.php';
send_security_headers(); require_login();
$pageTitle = 'Opini Viral · Analisis Sentimen YouTube';
$u = current_user();

/* ============================================================
 * MIGRASI TABEL (idempotent)
 * ============================================================ */
try {
    db_exec("CREATE TABLE IF NOT EXISTS opini_viral_search (
        id BIGSERIAL PRIMARY KEY,
        keyword TEXT NOT NULL,
        periode VARCHAR(20) NOT NULL DEFAULT '7d',
        date_from TIMESTAMP NULL,
        date_to   TIMESTAMP NULL,
        total_videos INT NOT NULL DEFAULT 0,
        total_comments INT NOT NULL DEFAULT 0,
        summary TEXT,
        topics_json TEXT,
        fetched_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_ovs_key ON opini_viral_search(lower(keyword), periode, fetched_at DESC)");
    db_exec("CREATE TABLE IF NOT EXISTS opini_viral_comments (
        id BIGSERIAL PRIMARY KEY,
        search_id BIGINT NOT NULL REFERENCES opini_viral_search(id) ON DELETE CASCADE,
        comment_id TEXT NOT NULL,
        video_id TEXT NOT NULL,
        video_title TEXT,
        channel_name TEXT,
        author_name TEXT,
        comment_text TEXT,
        like_count INT DEFAULT 0,
        published_at TIMESTAMP NULL,
        comment_url TEXT,
        sentimen VARCHAR(10) DEFAULT 'netral',
        confidence NUMERIC(5,2) DEFAULT 0,
        alasan TEXT,
        UNIQUE(search_id, comment_id)
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_ovc_search ON opini_viral_comments(search_id)");
    db_exec("CREATE INDEX IF NOT EXISTS idx_ovc_sent ON opini_viral_comments(search_id, sentimen)");
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 * HELPER
 * ============================================================ */
function ov_env(string $k): string {
    $v = getenv($k); if ($v) return (string)$v;
    $v = $_ENV[$k] ?? $_SERVER[$k] ?? ''; return (string)$v;
}
function ov_http_get(string $url, int $timeout=15): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_FOLLOWLOCATION=>true, CURLOPT_USERAGENT=>'KawanKeringat/OpiniViral',
    ]);
    if (ov_env('GEMINI_INSECURE_SSL')==='1') curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body ?: ''];
}
function ov_periode_range(string $periode, ?string $from=null, ?string $to=null): array {
    $now = time();
    switch ($periode) {
        case '24h': return [date('c', $now-86400), date('c', $now)];
        case '30d': return [date('c', $now-30*86400), date('c', $now)];
        case 'custom':
            $f = $from ? strtotime($from) : ($now-7*86400);
            $t = $to   ? strtotime($to.' 23:59:59') : $now;
            return [date('c',$f), date('c',$t)];
        case '7d': default: return [date('c', $now-7*86400), date('c', $now)];
    }
}
function ov_stopwords_id(): array {
    return array_flip(['yang','dan','di','ini','itu','dengan','untuk','pada','tidak','ada','sudah','saja','juga','atau','jadi','ke','dari','sih','kok','ya','lah','deh','aja','nya','kalo','kalau','biar','bikin','buat','gitu','gini','ga','gak','nggak','engga','enggak','tapi','tp','krn','karena','ke','oleh','dalam','akan','bisa','sama','kan','pun','se','pak','bu','mba','mas','loh','lho','kek','banget','bgt','tau','tahu','klo','yg','dgn','utk','pd','trs','terus','lagi','sekali','saya','aku','kamu','kita','kami','mereka','anda','dia','ia','pak','bpk','ibu','the','and','or','of','to','in','is','a','an']);
}

/* ============================================================
 * YOUTUBE API
 * ============================================================ */
function ov_yt_search(string $q, string $pubAfter, string $pubBefore, int $maxVideos=20): array {
    $key = ov_env('YOUTUBE_API_KEY');
    if (!$key) throw new RuntimeException('YOUTUBE_API_KEY belum diatur di environment. Tambahkan YOUTUBE_API_KEY=xxxx di config/env.local.php.');
    $url = 'https://www.googleapis.com/youtube/v3/search?'.http_build_query([
        'part'=>'snippet','type'=>'video','maxResults'=>min(50,$maxVideos),
        'q'=>$q,'relevanceLanguage'=>'id','regionCode'=>'ID','order'=>'relevance',
        'publishedAfter'=>$pubAfter,'publishedBefore'=>$pubBefore,'key'=>$key,
    ]);
    [$code,$body] = ov_http_get($url, 20);
    if ($code !== 200) {
        $j = json_decode($body, true);
        $msg = $j['error']['message'] ?? "HTTP $code";
        throw new RuntimeException("YouTube search gagal: $msg");
    }
    $j = json_decode($body, true) ?: [];
    $vids = [];
    foreach (($j['items'] ?? []) as $it) {
        $vid = $it['id']['videoId'] ?? null; if (!$vid) continue;
        $vids[] = [
            'video_id'=>$vid,
            'video_title'=>$it['snippet']['title'] ?? '',
            'channel_name'=>$it['snippet']['channelTitle'] ?? '',
            'published_at'=>$it['snippet']['publishedAt'] ?? null,
        ];
        if (count($vids) >= $maxVideos) break;
    }
    return $vids;
}
function ov_yt_comments(string $videoId, int $max=100): array {
    $key = ov_env('YOUTUBE_API_KEY'); if (!$key) return [];
    $out = []; $pageToken = '';
    while (count($out) < $max) {
        $url = 'https://www.googleapis.com/youtube/v3/commentThreads?'.http_build_query(array_filter([
            'part'=>'snippet','videoId'=>$videoId,'maxResults'=>min(100, $max-count($out)),
            'order'=>'relevance','textFormat'=>'plainText','key'=>$key,
            'pageToken'=>$pageToken ?: null,
        ]));
        [$code,$body] = ov_http_get($url, 15);
        if ($code !== 200) break; // komentar disabled / video private → skip
        $j = json_decode($body,true) ?: [];
        foreach (($j['items'] ?? []) as $it) {
            $s = $it['snippet']['topLevelComment']['snippet'] ?? null; if (!$s) continue;
            $cid = $it['snippet']['topLevelComment']['id'] ?? ($it['id'] ?? null); if (!$cid) continue;
            $out[] = [
                'comment_id'=>$cid,
                'author_name'=>$s['authorDisplayName'] ?? 'anonim',
                'comment_text'=>trim((string)($s['textDisplay'] ?? $s['textOriginal'] ?? '')),
                'like_count'=>(int)($s['likeCount'] ?? 0),
                'published_at'=>$s['publishedAt'] ?? null,
                'comment_url'=>'https://www.youtube.com/watch?v='.$videoId.'&lc='.$cid,
            ];
            if (count($out) >= $max) break 2;
        }
        $pageToken = $j['nextPageToken'] ?? '';
        if (!$pageToken) break;
    }
    return $out;
}

/* ============================================================
 * AI SENTIMEN (batch)
 * ============================================================ */
function ov_sentimen_batch(array $comments): array {
    // fallback lexicon jika AI tidak tersedia
    $useAI = function_exists('ai_chat');
    $result = [];
    $chunks = array_chunk($comments, 15, true);
    foreach ($chunks as $chunk) {
        $items = [];
        foreach ($chunk as $i => $c) {
            $items[] = ['i'=>$i, 'text'=>mb_substr($c['comment_text'], 0, 500)];
        }
        $ok = false;
        if ($useAI) {
            $prompt = "Klasifikasikan sentimen tiap komentar berbahasa Indonesia berikut sebagai Positif, Netral, atau Negatif. Balas JSON valid dengan struktur: {\"items\":[{\"i\":<id>,\"sentimen\":\"positif|netral|negatif\",\"confidence\":0-100,\"alasan\":\"<max 12 kata>\"}]}\n\nDATA:\n".json_encode($items, JSON_UNESCAPED_UNICODE);
            try {
                $raw = ai_chat($prompt, ['json'=>true, 'temperature'=>0.1, 'max_tokens'=>1200, 'system'=>'Anda adalah analis sentimen bahasa Indonesia. Selalu balas JSON valid.']);
                $j = ai_extract_json((string)$raw);
                if (is_array($j) && !empty($j['items'])) {
                    foreach ($j['items'] as $r) {
                        $idx = (int)($r['i'] ?? -1); if (!isset($chunk[$idx])) continue;
                        $s = strtolower(trim((string)($r['sentimen'] ?? 'netral')));
                        if (!in_array($s, ['positif','netral','negatif'], true)) $s='netral';
                        $result[$idx] = [
                            'sentimen'=>$s,
                            'confidence'=>max(0,min(100,(float)($r['confidence'] ?? 60))),
                            'alasan'=>mb_substr((string)($r['alasan'] ?? ''), 0, 240),
                        ];
                    }
                    $ok = true;
                }
            } catch (Throwable $e) { $ok=false; }
        }
        if (!$ok) {
            foreach ($chunk as $i=>$c) {
                $result[$i] = ov_sentimen_lexicon($c['comment_text']);
            }
        }
    }
    // isi yang belum kena
    foreach ($comments as $i=>$c) if (!isset($result[$i])) $result[$i] = ov_sentimen_lexicon($c['comment_text']);
    return $result;
}
function ov_sentimen_lexicon(string $t): array {
    $POS=['baik','bagus','mantap','keren','hebat','sukses','juara','menang','positif','bangga','apresiasi','terima kasih','makasih','love','suka','setuju','recommended','top','wow','hormat','salut','semangat'];
    $NEG=['buruk','jelek','gagal','kalah','rusak','krisis','korupsi','tewas','marah','kecewa','hoax','penipu','bohong','busuk','tolol','bodoh','sampah','anjing','bangsat','goblok','malu','payah','anjlok','tolak','benci'];
    $s = ' '.mb_strtolower($t).' '; $p=0;$n=0;
    foreach ($POS as $w) $p += substr_count($s,' '.$w);
    foreach ($NEG as $w) $n += substr_count($s,' '.$w);
    if ($p===0 && $n===0) return ['sentimen'=>'netral','confidence'=>50,'alasan'=>'Tidak ada kata kunci sentimen jelas'];
    if ($p>$n)  return ['sentimen'=>'positif','confidence'=>min(95,60+10*($p-$n)),'alasan'=>'Kata bermuatan positif'];
    if ($n>$p)  return ['sentimen'=>'negatif','confidence'=>min(95,60+10*($n-$p)),'alasan'=>'Kata bermuatan negatif'];
    return ['sentimen'=>'netral','confidence'=>55,'alasan'=>'Positif & negatif seimbang'];
}

function ov_ringkasan_ai(int $total, int $pos, int $net, int $neg, array $topWords): array {
    $topics = []; $summary = '';
    if (function_exists('ai_chat')) {
        $prompt = "Berdasarkan statistik berikut buat JSON: {\"summary\":\"...\",\"topics\":[\"...\",\"...\"]}.\n".
                  "Total komentar YouTube: $total. Positif: $pos, Netral: $net, Negatif: $neg. Kata paling sering: ".implode(', ', array_slice(array_keys($topWords),0,20)).".\n".
                  "Buat ringkasan 2-3 kalimat bahasa Indonesia natural, lalu 5 topik utama yang paling banyak dibahas.";
        try {
            $raw = ai_chat($prompt, ['json'=>true,'temperature'=>0.4,'max_tokens'=>400]);
            $j = ai_extract_json((string)$raw);
            if (is_array($j)) {
                $summary = (string)($j['summary'] ?? '');
                $topics  = array_values(array_filter((array)($j['topics'] ?? []), fn($x)=>is_string($x)&&$x!==''));
            }
        } catch (Throwable $e) { /* fallback */ }
    }
    if ($summary==='') {
        $pctPos = $total?round($pos*100/$total):0; $pctNet=$total?round($net*100/$total):0; $pctNeg=$total?round($neg*100/$total):0;
        $dom = $pctPos>=$pctNet && $pctPos>=$pctNeg ? "positif" : ($pctNeg>=$pctNet ? "negatif" : "netral");
        $summary = "Dari $total komentar YouTube, mayoritas netizen memberikan sentimen $dom ($pctPos% positif, $pctNet% netral, $pctNeg% negatif).";
    }
    if (empty($topics)) $topics = array_slice(array_keys($topWords), 0, 5);
    return ['summary'=>$summary, 'topics'=>$topics];
}

/* ============================================================
 * PIPELINE: cari + simpan (dengan cache 30 menit)
 * ============================================================ */
function ov_run_search(string $keyword, string $periode, ?string $from, ?string $to): array {
    [$pubAfter, $pubBefore] = ov_periode_range($periode, $from, $to);
    // Revisi R31: cache diperpanjang jadi 24 jam supaya keyword yang sama
    // tidak memanggil YouTube API lagi (juga menghindari proses yang lama).
    $cached = db_one("SELECT * FROM opini_viral_search
        WHERE lower(keyword)=lower($1) AND periode=$2
          AND COALESCE(date_from::text,'')=COALESCE($3::text,'') AND COALESCE(date_to::text,'')=COALESCE($4::text,'')
          AND fetched_at > now() - interval '24 hours'
        ORDER BY fetched_at DESC LIMIT 1",
        [$keyword, $periode, $periode==='custom'?$pubAfter:null, $periode==='custom'?$pubBefore:null]);
    if ($cached) return ['search_id'=>(int)$cached['id'], 'cached'=>true];

    // Revisi R31: BATAS TOTAL komentar per pencarian = 100 (bukan 100 per video).
    // Ambil video sedikit lebih banyak lalu berhenti begitu terkumpul 100 komentar.
    $MAX_TOTAL_COMMENTS = 100;
    $videos = ov_yt_search($keyword, $pubAfter, $pubBefore, 15);
    $allComments = []; // flat list dengan metadata video
    $videosUsed = 0;
    foreach ($videos as $v) {
        $sisa = $MAX_TOTAL_COMMENTS - count($allComments);
        if ($sisa <= 0) break;
        // per video maksimal ambil ~20 komentar top-relevance agar variasi video terjaga
        $perVideo = min($sisa, 20);
        $cs = ov_yt_comments($v['video_id'], $perVideo);
        if (!$cs) continue;
        foreach ($cs as $c) {
            $c['video_id']     = $v['video_id'];
            $c['video_title']  = $v['video_title'];
            $c['channel_name'] = $v['channel_name'];
            $allComments[] = $c;
            if (count($allComments) >= $MAX_TOTAL_COMMENTS) break;
        }
        $videosUsed++;
        if (count($allComments) >= $MAX_TOTAL_COMMENTS) break;
    }
    // klasifikasi (maks 100 komentar → cepat)
    $sent = ov_sentimen_batch($allComments);
    // word cloud
    $sw = ov_stopwords_id();
    $wc = [];
    foreach ($allComments as $c) {
        $tok = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($c['comment_text'])) ?: [];
        foreach ($tok as $w) { if (mb_strlen($w)<4) continue; if (isset($sw[$w])) continue; $wc[$w] = ($wc[$w] ?? 0)+1; }
    }
    arsort($wc); $topWords = array_slice($wc, 0, 100, true);

    $pos=$net=$neg=0;
    foreach ($sent as $s) { if ($s['sentimen']==='positif') $pos++; elseif ($s['sentimen']==='negatif') $neg++; else $net++; }
    $ai = ov_ringkasan_ai(count($allComments), $pos, $net, $neg, $topWords);

    db_exec("INSERT INTO opini_viral_search (keyword, periode, date_from, date_to, total_videos, total_comments, summary, topics_json) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)",
        [$keyword, $periode, $periode==='custom'?$pubAfter:null, $periode==='custom'?$pubBefore:null, $videosUsed, count($allComments), $ai['summary'], json_encode(['topics'=>$ai['topics'],'words'=>$topWords], JSON_UNESCAPED_UNICODE)]);
    $sid = (int)db_val("SELECT lastval()");

    foreach ($allComments as $i=>$c) {
        $s = $sent[$i];
        try {
            db_exec("INSERT INTO opini_viral_comments (search_id, comment_id, video_id, video_title, channel_name, author_name, comment_text, like_count, published_at, comment_url, sentimen, confidence, alasan)
                VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13) ON CONFLICT DO NOTHING",
                [$sid, $c['comment_id'], $c['video_id'], $c['video_title'], $c['channel_name'], $c['author_name'], $c['comment_text'], $c['like_count'], $c['published_at'], $c['comment_url'], $s['sentimen'], $s['confidence'], $s['alasan']]);
        } catch (Throwable $e) {}
    }
    return ['search_id'=>$sid, 'cached'=>false];
}

/* ============================================================
 * AJAX / EXPORT ENDPOINTS
 * ============================================================ */
$action = $_GET['action'] ?? '';
if ($action === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $kw = trim((string)($_POST['keyword'] ?? ''));
        if ($kw==='') throw new RuntimeException('Keyword wajib diisi');
        $periode = (string)($_POST['periode'] ?? '7d');
        $from = $_POST['from'] ?? null; $to = $_POST['to'] ?? null;
        $r = ov_run_search($kw, $periode, $from, $to);
        echo json_encode(['ok'=>true] + $r);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
    }
    exit;
}
/* Revisi R31: endpoint riwayat + fallback "cari pencarian terakhir untuk keyword ini".
   Digunakan UI untuk memuat kembali data yang SUDAH ada di DB tanpa memicu proses lagi. */
if ($action === 'history') {
    header('Content-Type: application/json; charset=utf-8');
    $rows = db_all("SELECT id, keyword, periode, total_videos, total_comments, fetched_at
                    FROM opini_viral_search ORDER BY fetched_at DESC LIMIT 20");
    echo json_encode(['ok'=>true, 'items'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'find_latest') {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim((string)($_GET['keyword'] ?? ''));
    if ($kw==='') { echo json_encode(['ok'=>false,'err'=>'keyword kosong']); exit; }
    $r = db_one("SELECT id FROM opini_viral_search WHERE lower(keyword)=lower($1) ORDER BY fetched_at DESC LIMIT 1", [$kw]);
    echo json_encode(['ok'=>(bool)$r, 'search_id'=>$r?(int)$r['id']:0]);
    exit;
}
if ($action === 'data') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_GET['sid'] ?? 0);
    $s = db_one("SELECT * FROM opini_viral_search WHERE id=$1", [$sid]);
    if (!$s) { echo json_encode(['ok'=>false,'err'=>'not found']); exit; }
    $rows = db_all("SELECT * FROM opini_viral_comments WHERE search_id=$1 ORDER BY like_count DESC, id DESC", [$sid]);
    $pos=$net=$neg=0;
    foreach ($rows as $r) { if ($r['sentimen']==='positif') $pos++; elseif ($r['sentimen']==='negatif') $neg++; else $net++; }
    $meta = json_decode($s['topics_json'] ?: '{}', true) ?: [];
    echo json_encode([
        'ok'=>true,
        'keyword'=>$s['keyword'], 'periode'=>$s['periode'],
        'total'=>count($rows), 'pos'=>$pos, 'net'=>$net, 'neg'=>$neg,
        'summary'=>$s['summary'], 'topics'=>$meta['topics'] ?? [], 'words'=>$meta['words'] ?? [],
        'comments'=>$rows,
        'fetched_at'=>$s['fetched_at'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'export') {
    $sid = (int)($_GET['sid'] ?? 0);
    $fmt = strtolower((string)($_GET['fmt'] ?? 'csv'));
    $rows = db_all("SELECT author_name, channel_name, video_title, comment_text, sentimen, confidence, like_count, published_at, comment_url FROM opini_viral_comments WHERE search_id=$1 ORDER BY id", [$sid]);
    $s = db_one("SELECT keyword FROM opini_viral_search WHERE id=$1",[$sid]);
    $slug = preg_replace('/[^a-z0-9_-]+/','_', strtolower($s['keyword'] ?? 'opini'));
    if ($fmt==='xls' || $fmt==='excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=opini_{$slug}.xls");
        echo "\xEF\xBB\xBF<table border='1'><tr><th>Author</th><th>Channel</th><th>Video</th><th>Komentar</th><th>Sentimen</th><th>Confidence</th><th>Like</th><th>Waktu</th><th>URL</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>"; foreach (['author_name','channel_name','video_title','comment_text','sentimen','confidence','like_count','published_at','comment_url'] as $k) echo "<td>".htmlspecialchars((string)$r[$k])."</td>"; echo "</tr>";
        }
        echo "</table>"; exit;
    }
    if ($fmt==='pdf') {
        // HTML print-friendly (browser Save as PDF). Simple + tanpa dependensi.
        header('Content-Type: text/html; charset=utf-8');
        echo "<!doctype html><meta charset='utf-8'><title>Opini {$slug}</title>";
        echo "<style>body{font-family:Arial,sans-serif;padding:20px;font-size:12px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px;vertical-align:top}.pos{color:#059669}.neg{color:#dc2626}.net{color:#475569}@media print{@page{size:A4 landscape;margin:1cm}}</style>";
        echo "<h2>Opini Viral: ".htmlspecialchars($s['keyword'])."</h2><button onclick='window.print()'>Cetak / Simpan PDF</button>";
        echo "<table><tr><th>#</th><th>Author</th><th>Channel</th><th>Komentar</th><th>Sentimen</th><th>Confidence</th><th>Like</th></tr>";
        foreach ($rows as $i=>$r) {
            $cls = $r['sentimen']==='positif'?'pos':($r['sentimen']==='negatif'?'neg':'net');
            echo "<tr><td>".($i+1)."</td><td>".htmlspecialchars($r['author_name'])."</td><td>".htmlspecialchars($r['channel_name'])."</td><td>".htmlspecialchars($r['comment_text'])."</td><td class='$cls'>".htmlspecialchars($r['sentimen'])."</td><td>".htmlspecialchars($r['confidence'])."%</td><td>".(int)$r['like_count']."</td></tr>";
        }
        echo "</table>"; exit;
    }
    // default CSV
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=opini_{$slug}.csv");
    $out = fopen('php://output','w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['author','channel','video_title','comment','sentimen','confidence','like','published_at','url']);
    foreach ($rows as $r) fputcsv($out, [$r['author_name'],$r['channel_name'],$r['video_title'],$r['comment_text'],$r['sentimen'],$r['confidence'],$r['like_count'],$r['published_at'],$r['comment_url']]);
    fclose($out); exit;
}

/* ============================================================
 * RENDER
 * ============================================================ */
require __DIR__.'/includes/header.php';
$hasYT = ov_env('YOUTUBE_API_KEY') !== '';
?>
<style>
.ov-wrap{max-width:1100px;margin:0 auto;padding:16px}
.ov-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.ov-title{font-size:22px;font-weight:800;margin:0 0 4px}
.ov-sub{color:#64748b;font-size:13px;margin-bottom:14px}
.ov-search{display:grid;grid-template-columns:1fr 180px 130px;gap:8px}
.ov-search input,.ov-search select,.ov-search button{padding:10px 12px;border-radius:10px;border:1px solid #cbd5e1;font-size:14px;background:#fff}
.ov-search button{background:#0ea5e9;color:#fff;border-color:#0ea5e9;font-weight:700;cursor:pointer}
.ov-search button:hover{background:#0284c7}
.ov-custom{display:none;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
.ov-custom.show{display:grid}
.ov-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.ov-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center}
.ov-stat b{font-size:22px;display:block}
.ov-stat.pos b{color:#059669}.ov-stat.neg b{color:#dc2626}.ov-stat.net b{color:#475569}
.ov-charts{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ov-filter{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0}
.ov-filter button{padding:6px 12px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font-size:13px}
.ov-filter button.active{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
.ov-cmt{border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:8px;background:#fff}
.ov-cmt .head{display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px;gap:8px;flex-wrap:wrap}
.ov-cmt .txt{font-size:14px;line-height:1.5;color:#0f172a}
.ov-cmt .foot{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:12px;color:#64748b;gap:8px;flex-wrap:wrap}
.ov-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
.ov-badge.positif{background:#d1fae5;color:#065f46}
.ov-badge.negatif{background:#fee2e2;color:#991b1b}
.ov-badge.netral{background:#e2e8f0;color:#334155}
.ov-topics{display:flex;flex-wrap:wrap;gap:6px}
.ov-topics span{background:#eff6ff;color:#1d4ed8;padding:4px 10px;border-radius:999px;font-size:12px;border:1px solid #bfdbfe}
.ov-loader{display:none;padding:20px;text-align:center;color:#0ea5e9;font-weight:600}
.ov-loader.show{display:block}
.ov-empty{padding:30px;text-align:center;color:#64748b}
.ov-warn{background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:10px 12px;border-radius:10px;margin-bottom:10px;font-size:13px}
.ov-export{display:flex;gap:6px;flex-wrap:wrap}
.ov-export a{padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#0f172a;font-size:13px;background:#f8fafc}
.ov-export a:hover{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
@media(max-width:720px){.ov-search{grid-template-columns:1fr}.ov-stats{grid-template-columns:repeat(2,1fr)}.ov-charts{grid-template-columns:1fr}}
</style>

<div class="ov-wrap">
  <div class="ov-card">
    <h1 class="ov-title">🎯 Opini Viral · Analisis Sentimen YouTube</h1>
    <p class="ov-sub">Analisis komentar publik YouTube untuk memahami opini netizen Indonesia terhadap sebuah topik.</p>
    <?php if (!$hasYT): ?>
      <div class="ov-warn">⚠️ Env <code>YOUTUBE_API_KEY</code> belum diatur. Tambahkan pada <code>config/env.local.php</code>: <code>putenv('YOUTUBE_API_KEY=xxxx');</code> lalu reload halaman.</div>
    <?php endif; ?>
    <form id="ovForm" onsubmit="return false" autocomplete="off">
      <div class="ov-search">
        <input type="text" id="ovKeyword" placeholder="Masukkan keyword (contoh: PLN, Timnas, BBM, Banjir Jakarta)" required>
        <select id="ovPeriode">
          <option value="24h">24 jam terakhir</option>
          <option value="7d" selected>7 hari terakhir</option>
          <option value="30d">30 hari terakhir</option>
          <option value="custom">Custom tanggal…</option>
        </select>
        <button type="submit">🔍 Cari Opini</button>
      </div>
      <div class="ov-custom" id="ovCustom">
        <input type="date" id="ovFrom"><input type="date" id="ovTo">
      </div>
    </form>
  </div>

  <div class="ov-loader" id="ovLoader">⏳ Mengambil video & komentar YouTube (maks 100 komentar), menganalisis sentimen dengan AI… (±10-20 detik)</div>

  <!-- Revisi R31: panel riwayat pencarian, memuat data dari DB tanpa memanggil YouTube lagi -->
  <div class="ov-card" id="ovHistoryCard">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0;font-size:16px">🕘 Riwayat Pencarian (dari database)</h3>
      <button type="button" id="ovHistoryReload" style="padding:6px 12px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;cursor:pointer;font-size:12px">🔄 Muat ulang</button>
    </div>
    <div id="ovHistoryList" style="margin-top:10px;font-size:13px;color:#64748b">Memuat riwayat…</div>
  </div>

  <div id="ovResult" style="display:none">
    <div class="ov-card">
      <div class="ov-stats">
        <div class="ov-stat"><b id="ovTotal">0</b>Total Komentar</div>
        <div class="ov-stat pos"><b id="ovPos">0</b>Positif <small id="ovPosP"></small></div>
        <div class="ov-stat net"><b id="ovNet">0</b>Netral <small id="ovNetP"></small></div>
        <div class="ov-stat neg"><b id="ovNeg">0</b>Negatif <small id="ovNegP"></small></div>
      </div>
    </div>

    <div class="ov-card">
      <div class="ov-charts">
        <div><canvas id="ovPie" height="220"></canvas></div>
        <div><canvas id="ovBar" height="220"></canvas></div>
      </div>
    </div>

    <div class="ov-card">
      <h3 style="margin:0 0 8px">🧠 Ringkasan AI</h3>
      <p id="ovSummary" style="margin:0;color:#0f172a;line-height:1.6"></p>
      <div style="margin-top:12px">
        <b style="font-size:13px;color:#475569">Topik yang sering dibahas:</b>
        <div class="ov-topics" id="ovTopics" style="margin-top:6px"></div>
      </div>
    </div>

    <div class="ov-card">
      <h3 style="margin:0 0 8px">☁️ Word Cloud</h3>
      <div id="ovCloud" style="width:100%;height:280px;background:#f8fafc;border-radius:10px"></div>
    </div>

    <div class="ov-card">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">💬 Daftar Komentar</h3>
        <div class="ov-export" id="ovExport"></div>
      </div>
      <div class="ov-filter" id="ovFilter">
        <button data-f="all" class="active">Semua</button>
        <button data-f="positif">Positif</button>
        <button data-f="netral">Netral</button>
        <button data-f="negatif">Negatif</button>
      </div>
      <div id="ovList"></div>
    </div>
  </div>

  <div id="ovEmpty" class="ov-card ov-empty" style="display:none">Belum ditemukan komentar publik untuk kata kunci tersebut.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/wordcloud@1.2.2/src/wordcloud2.min.js"></script>
<script>
(function(){
  const $ = id => document.getElementById(id);
  let pieChart, barChart, currentData = null, currentFilter = 'all', currentSid = 0;

  $('ovPeriode').addEventListener('change', e => {
    $('ovCustom').classList.toggle('show', e.target.value === 'custom');
  });

  $('ovForm').addEventListener('submit', async () => {
    const kw = $('ovKeyword').value.trim(); if (!kw) return;
    const periode = $('ovPeriode').value;
    const fd = new FormData(); fd.append('keyword', kw); fd.append('periode', periode);
    if (periode==='custom'){ fd.append('from', $('ovFrom').value); fd.append('to', $('ovTo').value); }
    $('ovLoader').classList.add('show'); $('ovResult').style.display='none'; $('ovEmpty').style.display='none';
    try {
      const r = await fetch('?action=search', {method:'POST', body:fd}).then(r=>r.json());
      if (!r.ok) throw new Error(r.err || 'Gagal');
      currentSid = r.search_id;
      await loadData(r.search_id);
      loadHistory();
    } catch (e) {
      // Revisi R31: kalau proses gagal/timeout, cek apakah pencarian keyword
      // ini sudah pernah tersimpan di DB. Kalau ada, langsung tampilkan hasil DB.
      try {
        const f = await fetch('?action=find_latest&keyword='+encodeURIComponent(kw)).then(r=>r.json());
        if (f.ok && f.search_id) {
          alert('Proses baru gagal ('+e.message+'), memuat hasil terakhir dari database.');
          currentSid = f.search_id; await loadData(f.search_id); return;
        }
      } catch(_) {}
      alert('Error: ' + e.message);
    }
    finally { $('ovLoader').classList.remove('show'); }
  });

  // Revisi R31: riwayat pencarian dari DB
  async function loadHistory(){
    try {
      const r = await fetch('?action=history').then(r=>r.json());
      const box = $('ovHistoryList');
      if (!r.ok || !r.items || !r.items.length) { box.innerHTML = '<em>Belum ada pencarian tersimpan.</em>'; return; }
      box.innerHTML = r.items.map(it => {
        const dt = it.fetched_at ? new Date(it.fetched_at).toLocaleString('id-ID') : '';
        return `<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:6px;background:#f8fafc">
          <div><b style="color:#0f172a">${escapeHtml(it.keyword)}</b>
            <span style="color:#64748b"> · ${escapeHtml(it.periode)} · ${it.total_comments} komentar / ${it.total_videos} video · ${dt}</span>
          </div>
          <button type="button" data-sid="${it.id}" class="ov-hist-load" style="padding:5px 12px;border-radius:6px;border:1px solid #0ea5e9;background:#0ea5e9;color:#fff;cursor:pointer;font-size:12px">Lihat</button>
        </div>`;
      }).join('');
      box.querySelectorAll('.ov-hist-load').forEach(b => b.addEventListener('click', async () => {
        currentSid = +b.dataset.sid;
        await loadData(currentSid);
        window.scrollTo({top: $('ovResult').offsetTop-20, behavior:'smooth'});
      }));
    } catch(e){ $('ovHistoryList').innerHTML = '<em>Gagal memuat riwayat.</em>'; }
  }
  $('ovHistoryReload').addEventListener('click', loadHistory);
  loadHistory();

  async function loadData(sid){
    const d = await fetch('?action=data&sid='+sid).then(r=>r.json());
    if (!d.ok) { alert(d.err||'error'); return; }
    currentData = d;
    if (!d.total) { $('ovEmpty').style.display='block'; return; }
    $('ovResult').style.display='block';
    $('ovTotal').textContent = d.total;
    $('ovPos').textContent = d.pos; $('ovNet').textContent = d.net; $('ovNeg').textContent = d.neg;
    const pct = n => d.total ? Math.round(n*100/d.total)+'%' : '0%';
    $('ovPosP').textContent = pct(d.pos); $('ovNetP').textContent = pct(d.net); $('ovNegP').textContent = pct(d.neg);
    $('ovSummary').textContent = d.summary || '';
    $('ovTopics').innerHTML = (d.topics||[]).map(t=>`<span>${escapeHtml(t)}</span>`).join('');
    renderCharts(d); renderCloud(d.words||{}); renderList();
    $('ovExport').innerHTML = ['csv','xls','pdf'].map(f=>`<a href="?action=export&sid=${sid}&fmt=${f}" target="_blank">⬇️ ${f.toUpperCase()}</a>`).join('');
  }

  function renderCharts(d){
    if (pieChart) pieChart.destroy(); if (barChart) barChart.destroy();
    pieChart = new Chart($('ovPie'), {type:'doughnut', data:{labels:['Positif','Netral','Negatif'],datasets:[{data:[d.pos,d.net,d.neg],backgroundColor:['#10b981','#94a3b8','#ef4444']}]}, options:{plugins:{legend:{position:'bottom'}, title:{display:true,text:'Distribusi Sentimen'}}}});
    barChart = new Chart($('ovBar'), {type:'bar', data:{labels:['Positif','Netral','Negatif'],datasets:[{label:'Jumlah',data:[d.pos,d.net,d.neg],backgroundColor:['#10b981','#94a3b8','#ef4444']}]}, options:{plugins:{legend:{display:false},title:{display:true,text:'Komentar per Sentimen'}}, scales:{y:{beginAtZero:true}}}});
  }
  function renderCloud(words){
    const list = Object.entries(words).slice(0,80);
    if (!list.length) { $('ovCloud').innerHTML = '<div style="padding:20px;color:#94a3b8;text-align:center">Tidak cukup kata untuk word cloud.</div>'; return; }
    WordCloud($('ovCloud'), {list, gridSize:8, weightFactor:6, color:'random-dark', backgroundColor:'#f8fafc', rotateRatio:0.2});
  }
  $('ovFilter').addEventListener('click', e=>{
    const b = e.target.closest('button'); if (!b) return;
    document.querySelectorAll('#ovFilter button').forEach(x=>x.classList.remove('active')); b.classList.add('active');
    currentFilter = b.dataset.f; renderList();
  });
  function renderList(){
    if (!currentData) return;
    const rows = currentData.comments.filter(c => currentFilter==='all' || c.sentimen===currentFilter);
    $('ovList').innerHTML = rows.length ? rows.map(c=>{
      const url = 'https://www.youtube.com/watch?v='+c.video_id+'&lc='+c.comment_id;
      return `<div class="ov-cmt">
        <div class="head"><span><b>${escapeHtml(c.author_name)}</b> · <i>${escapeHtml(c.channel_name)}</i></span><span>${fmtDate(c.published_at)}</span></div>
        <div style="font-size:12px;color:#64748b;margin-bottom:6px">📺 ${escapeHtml(c.video_title)}</div>
        <div class="txt">${escapeHtml(c.comment_text)}</div>
        <div class="foot">
          <span><span class="ov-badge ${c.sentimen}">${c.sentimen}</span> · ${Math.round(c.confidence)}% · 👍 ${c.like_count}</span>
          <a href="${url}" target="_blank" rel="noopener">Lihat di YouTube ↗</a>
        </div>
        ${c.alasan?`<div style="font-size:11px;color:#94a3b8;margin-top:4px">💡 ${escapeHtml(c.alasan)}</div>`:''}
      </div>`;
    }).join('') : '<div class="ov-empty">Tidak ada komentar untuk filter ini.</div>';
  }
  function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function fmtDate(s){ if(!s) return ''; try{ return new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}catch(e){return s;} }
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>

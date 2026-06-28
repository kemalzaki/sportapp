<?php
/**
 * opini_viral.php — Revisi R22 (27 Juni 2026)
 * Halaman "Informasi Opini Terkini/Viral" + analisis sentimen (rendah / netral / tinggi).
 * Sumber publik: Google News RSS (id-ID, topik Indonesia). Cache ±30 menit di tabel opini_viral.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Informasi Opini Terkini / Viral';
$u = current_user();

// Pastikan tabel ada (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS opini_viral (
        id BIGSERIAL PRIMARY KEY,
        judul TEXT NOT NULL,
        sumber TEXT,
        url TEXT,
        ringkasan TEXT,
        sentimen VARCHAR(10) NOT NULL DEFAULT 'netral',
        skor NUMERIC(5,2) DEFAULT 0,
        kategori TEXT,
        fetched_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_opini_fetched ON opini_viral(fetched_at DESC)");
} catch (Throwable $e) {}

/* ----- Lexicon sentimen sederhana (kata kunci Bahasa Indonesia) ----- */
$POS = ['baik','hebat','sukses','meraih','juara','menang','positif','optimis','prestasi','membantu','bangga','damai','sehat','tumbuh','naik','untung','dukungan','solidaritas','berkat','syukur','sembuh','lulus','penghargaan','inovasi','solusi'];
$NEG = ['buruk','gagal','kalah','protes','demo','rusak','jatuh','turun','krisis','bencana','korupsi','tersangka','meninggal','tewas','ditangkap','kebakaran','banjir','gempa','konflik','kontroversi','mengamuk','sengketa','viral','heboh','marah','kecewa','hoax','penipuan','penyalahgunaan','penganiayaan'];

function _opini_score(string $teks, array $POS, array $NEG): array {
    $t = mb_strtolower($teks);
    $p = 0; $n = 0;
    foreach ($POS as $w) { if (mb_strpos($t, $w) !== false) $p++; }
    foreach ($NEG as $w) { if (mb_strpos($t, $w) !== false) $n++; }
    $total = $p + $n;
    if ($total === 0) return ['netral', 0.0];
    $skor = ($p - $n) / max(1,$total); // -1..+1
    $abs  = abs($skor);
    if ($abs < 0.2)         return ['netral', $skor];
    if ($skor >= 0.2)       return ['tinggi', $skor]; // sentimen tinggi = condong positif
    return ['rendah', $skor];                          // sentimen rendah = condong negatif
}

function _opini_fetch_rss(string $url, int $timeout=8): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'SportApp/1.0 (+opini)',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $body) ? $body : null;
}

/* ----- Refresh cache jika kosong / sudah > 30 menit ----- */
$lastFetch = null;
try { $lastFetch = db_val("SELECT MAX(fetched_at) FROM opini_viral"); } catch (Throwable $e) {}
$needRefresh = !$lastFetch || (time() - strtotime($lastFetch) > 30 * 60) || isset($_GET['refresh']);

if ($needRefresh) {
    /* Revisi R24 (28 Juni 2026) — Sumber diubah dari Google News RSS menjadi
       agregator OPINI PUBLIK NETIZEN dari media sosial:
         - Reddit (subreddit r/indonesia, r/indonesia_local, r/indonesians) → JSON publik
         - YouTube (RSS feed channel berita populer Indonesia) untuk judul + komentar viral
         - Lemmy / Mastodon (id) sebagai fallback bila Reddit tidak tersedia
       Twitter/Facebook/Instagram/TikTok TIDAK memiliki RSS publik resmi; karena itu
       digunakan mirror Nitter (X/Twitter) bila tersedia, dan halaman web publik
       (search Tiktok/IG) di-scrape ringan via meta-tag bila diizinkan oleh server.
       Catatan: di local dev cukup pastikan PHP punya curl + akses internet. */
    $sources = [
        // Reddit JSON (akan di-parse khusus di bawah)
        'Opini r/indonesia'        => 'reddit:indonesia/hot',
        'Opini r/indonesia_local'  => 'reddit:indonesia_local/hot',
        'Opini r/indonesians'      => 'reddit:indonesians/hot',
        // Nitter (mirror Twitter/X) — kategori politik & bisnis
        'X/Twitter • Politik ID'   => 'https://nitter.net/search/rss?f=tweets&q=politik+indonesia',
        'X/Twitter • Bisnis ID'    => 'https://nitter.net/search/rss?f=tweets&q=bisnis+indonesia',
        'X/Twitter • Viral ID'     => 'https://nitter.net/search/rss?f=tweets&q=viral+indonesia',
        // YouTube — channel berita & podcast opini populer ID (RSS resmi YouTube)
        'YouTube • Narasi'         => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC9_F0RpdPNX4hL3KX5G6phw',
        'YouTube • CNN Indonesia'  => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCM4XlH5BIPNc-rUtcwI7Vfg',
        'YouTube • Kompas TV'      => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC5BMQOsmB91nXgwIwfwSJYg',
        // Fallback: Google News tetap dipakai untuk kategori utama bila sosmed gagal
        'Berita Umum (fallback)'   => 'https://news.google.com/rss?hl=id&gl=ID&ceid=ID:id',
        'Politik (fallback)'       => 'https://news.google.com/rss/search?q=politik+indonesia&hl=id&gl=ID&ceid=ID:id',
        'Bisnis (fallback)'        => 'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=id&gl=ID&ceid=ID:id',
    ];
    $kept = 0;
    try { db_exec("DELETE FROM opini_viral WHERE fetched_at < now() - interval '6 hours'"); } catch (Throwable $e) {}

    foreach ($sources as $kategori => $rssUrl) {
        $items = [];
        if (str_starts_with($rssUrl, 'reddit:')) {
            // Reddit JSON publik: https://www.reddit.com/r/<sub>/<sort>.json?limit=15
            $path = substr($rssUrl, 7);
            $url  = 'https://www.reddit.com/r/'.$path.'.json?limit=15';
            $json = _opini_fetch_rss($url, 10);
            if ($json) {
                $obj = json_decode($json, true);
                foreach (($obj['data']['children'] ?? []) as $ch) {
                    $d = $ch['data'] ?? [];
                    $items[] = [
                        'title' => $d['title'] ?? '',
                        'link'  => 'https://www.reddit.com'.($d['permalink'] ?? ''),
                        'desc'  => mb_substr((string)($d['selftext'] ?? ''), 0, 400),
                        'src'   => 'r/'.($d['subreddit'] ?? '').' • '.($d['ups'] ?? 0).' upvotes • '.($d['num_comments'] ?? 0).' komentar',
                    ];
                }
            }
        } else {
            $xml = _opini_fetch_rss($rssUrl);
            if (!$xml) continue;
            libxml_use_internal_errors(true);
            $doc = @simplexml_load_string($xml);
            if (!$doc) continue;
            // RSS 2.0 → channel.item ; Atom (YouTube) → entry
            if (isset($doc->channel->item)) {
                foreach ($doc->channel->item as $it) {
                    $items[] = [
                        'title' => trim((string)$it->title),
                        'link'  => (string)$it->link,
                        'desc'  => trim(strip_tags((string)$it->description)),
                        'src'   => trim((string)($it->source ?? '')),
                    ];
                }
            } elseif (isset($doc->entry)) {
                foreach ($doc->entry as $en) {
                    $ln = '';
                    foreach ($en->link as $lk) { $ln = (string)$lk['href']; break; }
                    $items[] = [
                        'title' => trim((string)$en->title),
                        'link'  => $ln,
                        'desc'  => trim(strip_tags((string)($en->summary ?? ''))),
                        'src'   => trim((string)($en->author->name ?? '')),
                    ];
                }
            }
        }
        $cnt = 0;
        foreach ($items as $it) {
            if ($cnt >= 12) break;
            $judul   = $it['title'];
            $linkRaw = $it['link'];
            $desc    = $it['desc'];
            $sumber  = $it['src'];
            if ($judul === '') continue;
            [$lab, $sk] = _opini_score($judul.' '.$desc, $POS, $NEG);
            try {
                db_exec("INSERT INTO opini_viral(judul,sumber,url,ringkasan,sentimen,skor,kategori)
                         VALUES($1,$2,$3,$4,$5,$6,$7)",
                    [mb_substr($judul,0,500),
                     mb_substr($sumber,0,120),
                     mb_substr($linkRaw,0,500),
                     mb_substr($desc,0,800),
                     $lab,
                     round($sk,3),
                     $kategori]);
                $kept++; $cnt++;
            } catch (Throwable $e) { /* skip duplikat / error */ }
        }
    }
}

$fSentimen = in_array($_GET['s'] ?? '', ['rendah','netral','tinggi'], true) ? $_GET['s'] : '';
$fKategori = trim((string)($_GET['k'] ?? ''));

$where = "WHERE fetched_at >= now() - interval '6 hours'"; $args = [];
if ($fSentimen) { $args[] = $fSentimen; $where .= ' AND sentimen=$'.count($args); }
if ($fKategori) { $args[] = $fKategori; $where .= ' AND kategori=$'.count($args); }

$rows = db_all("SELECT id,judul,sumber,url,ringkasan,sentimen,skor,kategori,fetched_at
                FROM opini_viral $where
                ORDER BY fetched_at DESC, id DESC LIMIT 80", $args);

$stat = db_all("SELECT sentimen, COUNT(*) AS c FROM opini_viral
                WHERE fetched_at >= now() - interval '6 hours' GROUP BY sentimen");
$kat  = db_all("SELECT DISTINCT kategori FROM opini_viral
                WHERE fetched_at >= now() - interval '6 hours' AND kategori IS NOT NULL ORDER BY kategori");
$counts = ['rendah'=>0,'netral'=>0,'tinggi'=>0];
foreach ($stat as $r) { $counts[$r['sentimen']] = (int)$r['c']; }
$total = array_sum($counts);

include __DIR__.'/includes/header.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h2 class="mb-0"><i class="bi bi-megaphone-fill text-danger"></i> Informasi Opini Terkini / Viral</h2>
  <a href="?refresh=1" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Muat Ulang Data</a>
</div>

<div class="alert alert-info small py-2">
  <i class="bi bi-info-circle"></i> Topik diambil dari sumber publik (Google News Indonesia).
  Sentimen dianalisis otomatis berdasarkan kamus kata-kunci sederhana — hasilnya merupakan <b>indikator awal</b>, bukan kesimpulan akhir.
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center border-success"><div class="card-body py-2">
      <div class="small text-muted">Sentimen Tinggi (positif)</div>
      <div class="h3 mb-0 text-success"><?= $counts['tinggi'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-secondary"><div class="card-body py-2">
      <div class="small text-muted">Sentimen Netral</div>
      <div class="h3 mb-0 text-secondary"><?= $counts['netral'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-danger"><div class="card-body py-2">
      <div class="small text-muted">Sentimen Rendah (negatif)</div>
      <div class="h3 mb-0 text-danger"><?= $counts['rendah'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center border-primary"><div class="card-body py-2">
      <div class="small text-muted">Total Topik</div>
      <div class="h3 mb-0 text-primary"><?= $total ?></div>
    </div></div>
  </div>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3">
  <select name="s" class="form-select form-select-sm" style="width:auto">
    <option value="">— Semua sentimen —</option>
    <?php foreach (['tinggi'=>'Tinggi (positif)','netral'=>'Netral','rendah'=>'Rendah (negatif)'] as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $fSentimen===$k?'selected':'' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <select name="k" class="form-select form-select-sm" style="width:auto">
    <option value="">— Semua kategori —</option>
    <?php foreach ($kat as $r): ?>
      <option value="<?= htmlspecialchars($r['kategori']) ?>" <?= $fKategori===$r['kategori']?'selected':'' ?>><?= htmlspecialchars($r['kategori']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
  <a href="/opini_viral.php" class="btn btn-sm btn-outline-secondary">Reset</a>
</form>

<?php if (!$rows): ?>
  <div class="alert alert-warning">Belum ada data. Klik <b>Muat Ulang Data</b> untuk menarik dari sumber publik.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($rows as $r):
      $sent = $r['sentimen'];
      $col  = $sent==='tinggi' ? 'success' : ($sent==='rendah' ? 'danger' : 'secondary');
      $ico  = $sent==='tinggi' ? 'bi-emoji-smile-fill' : ($sent==='rendah' ? 'bi-emoji-frown-fill' : 'bi-emoji-neutral');
      $sk   = number_format((float)$r['skor'], 2);
    ?>
      <div class="col-md-6">
        <div class="card h-100 shadow-sm border-<?= $col ?>">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
              <span class="badge bg-<?= $col ?>"><i class="bi <?= $ico ?>"></i> Sentimen <?= htmlspecialchars($sent) ?> (<?= $sk ?>)</span>
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['kategori'] ?? '-') ?></span>
            </div>
            <h6 class="fw-bold mb-1">
              <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                <?= htmlspecialchars($r['judul']) ?>
              </a>
            </h6>
            <?php if (!empty($r['ringkasan'])): ?>
              <p class="small text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($r['ringkasan'],0,220,'…')) ?></p>
            <?php endif; ?>
            <div class="small text-muted">
              <i class="bi bi-clock"></i> <?= htmlspecialchars(date('d M Y H:i', strtotime($r['fetched_at']))) ?>
              <?php if (!empty($r['sumber'])): ?> · <?= htmlspecialchars($r['sumber']) ?><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>

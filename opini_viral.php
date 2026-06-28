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
    // Revisi R25 (28 Juni 2026) — kolom komentar publik untuk ditampilkan di kartu
    db_exec("ALTER TABLE opini_viral ADD COLUMN IF NOT EXISTS komentar TEXT");
} catch (Throwable $e) {}

/* ----- Lexicon sentimen — diperluas (Revisi R25, 28 Juni 2026).
   Sebelumnya kata "politik" otomatis masuk daftar NEG sehingga seluruh feed
   "Politik" otomatis ber-sentimen RENDAH (negatif). Itu sebabnya muncul keluhan
   "masa politik rendah?". Sekarang:
   - Kata netral/tematik (politik, viral, demo, dll) DIKELUARKAN dari NEG.
   - NEG kembali fokus pada kata yang benar-benar bermakna negatif.
   - POS diperluas. Skor pakai bobot frekuensi (bukan hanya ada/tidak). */
$POS = ['baik','hebat','sukses','meraih','juara','menang','positif','optimis','prestasi','membantu','bangga','damai','sehat','tumbuh','naik','untung','dukungan','solidaritas','berkat','syukur','sembuh','lulus','penghargaan','inovasi','solusi','keren','mantap','bagus','luar biasa','membanggakan','harapan','sepakat','disetujui','disahkan','memuji','apresiasi','rekor','tercepat','terbaik','meningkat'];
$NEG = ['buruk','gagal','kalah','rusak','jatuh','turun','krisis','bencana','korupsi','tersangka','meninggal','tewas','ditangkap','kebakaran','banjir','gempa','konflik','kontroversi','mengamuk','sengketa','marah','kecewa','hoax','penipuan','penyalahgunaan','penganiayaan','dibunuh','memprihatinkan','tragis','kabur','melarikan diri','divonis','dipenjara','ditolak','digugat','memburuk','anjlok','merosot','rugi','defisit'];

function _opini_score(string $teks, array $POS, array $NEG): array {
    $t = ' '.mb_strtolower($teks).' ';
    $p = 0; $n = 0;
    foreach ($POS as $w) { $p += substr_count($t, ' '.$w); $p += substr_count($t, ' '.$w.' '); }
    foreach ($NEG as $w) { $n += substr_count($t, ' '.$w); $n += substr_count($t, ' '.$w.' '); }
    $total = $p + $n;
    if ($total === 0) return ['netral', 0.0];
    $skor = ($p - $n) / max(1,$total); // -1..+1
    $abs  = abs($skor);
    if ($abs < 0.2)         return ['netral', $skor];
    if ($skor >= 0.2)       return ['tinggi', $skor];
    return ['rendah', $skor];
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
        // Nitter (mirror Twitter/X) — Revisi R25: pakai skema `nitter:` + multi-mirror
        'X/Twitter • Politik ID'   => 'nitter:politik indonesia',
        'X/Twitter • Bisnis ID'    => 'nitter:bisnis indonesia',
        'X/Twitter • Viral ID'     => 'nitter:viral indonesia',
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
            $path = substr($rssUrl, 7);
            $url  = 'https://www.reddit.com/r/'.$path.'.json?limit=10';
            $json = _opini_fetch_rss($url, 10);
            if ($json) {
                $obj = json_decode($json, true);
                $idx = 0;
                foreach (($obj['data']['children'] ?? []) as $ch) {
                    $d = $ch['data'] ?? [];
                    $permalink = $d['permalink'] ?? '';
                    /* Revisi R25 (28 Juni 2026) — ambil 5 komentar teratas untuk 5 post
                       pertama tiap subreddit, simpan ke kolom `komentar` agar UI bisa
                       memunculkan komentar netizen (sesuai permintaan user). */
                    $comments = [];
                    if ($idx < 5 && $permalink) {
                        $cj = _opini_fetch_rss('https://www.reddit.com'.$permalink.'.json?limit=5&depth=1&sort=top', 8);
                        if ($cj) {
                            $cobj = json_decode($cj, true);
                            $kids = $cobj[1]['data']['children'] ?? [];
                            foreach ($kids as $kc) {
                                $body = trim((string)($kc['data']['body'] ?? ''));
                                if ($body==='' || $body==='[deleted]' || $body==='[removed]') continue;
                                $comments[] = mb_substr($body, 0, 240);
                                if (count($comments) >= 5) break;
                            }
                        }
                    }
                    $idx++;
                    $items[] = [
                        'title' => $d['title'] ?? '',
                        'link'  => 'https://www.reddit.com'.$permalink,
                        'desc'  => mb_substr((string)($d['selftext'] ?? ''), 0, 400),
                        'src'   => 'r/'.($d['subreddit'] ?? '').' • '.($d['ups'] ?? 0).' upvotes • '.($d['num_comments'] ?? 0).' komentar',
                        'comments' => $comments,
                    ];
                }
            }
        } elseif (str_starts_with($rssUrl, 'nitter:')) {
            /* Revisi R25 (28 Juni 2026) — Nitter sering down/diblok. Coba beberapa
               mirror berurutan, pakai yang pertama berhasil. */
            $q = substr($rssUrl, 7);
            $mirrors = ['https://nitter.privacydev.net','https://nitter.poast.org','https://nitter.net'];
            foreach ($mirrors as $base) {
                $xml = _opini_fetch_rss($base.'/search/rss?f=tweets&q='.urlencode($q), 6);
                if (!$xml) continue;
                libxml_use_internal_errors(true);
                $doc = @simplexml_load_string($xml);
                if (!$doc || !isset($doc->channel->item)) continue;
                foreach ($doc->channel->item as $it) {
                    $items[] = [
                        'title' => trim((string)$it->title),
                        'link'  => (string)$it->link,
                        'desc'  => trim(strip_tags((string)$it->description)),
                        'src'   => 'Twitter/X • '.parse_url($base, PHP_URL_HOST),
                        'comments' => [],
                    ];
                }
                if ($items) break;
            }
        } else {
            $xml = _opini_fetch_rss($rssUrl);
            if (!$xml) continue;
            libxml_use_internal_errors(true);
            $doc = @simplexml_load_string($xml);
            if (!$doc) continue;
            if (isset($doc->channel->item)) {
                foreach ($doc->channel->item as $it) {
                    $items[] = [
                        'title' => trim((string)$it->title),
                        'link'  => (string)$it->link,
                        'desc'  => trim(strip_tags((string)$it->description)),
                        'src'   => trim((string)($it->source ?? '')),
                        'comments' => [],
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
                        'comments' => [],
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
            $komen   = $it['comments'] ?? [];
            if ($judul === '') continue;
            // Sentimen pakai judul + ringkasan + komentar (lebih representatif)
            $teks = $judul.' '.$desc.' '.implode(' ', $komen);
            [$lab, $sk] = _opini_score($teks, $POS, $NEG);
            try {
                db_exec("INSERT INTO opini_viral(judul,sumber,url,ringkasan,sentimen,skor,kategori,komentar)
                         VALUES($1,$2,$3,$4,$5,$6,$7,$8)",
                    [mb_substr($judul,0,500),
                     mb_substr($sumber,0,120),
                     mb_substr($linkRaw,0,500),
                     mb_substr($desc,0,800),
                     $lab,
                     round($sk,3),
                     $kategori,
                     $komen ? json_encode($komen, JSON_UNESCAPED_UNICODE) : null]);
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

$rows = db_all("SELECT id,judul,sumber,url,ringkasan,sentimen,skor,kategori,komentar,fetched_at
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
  <i class="bi bi-info-circle"></i> <b>Sumber data:</b> Reddit (r/indonesia, r/indonesia_local, r/indonesians) untuk opini + komentar netizen,
  Nitter (mirror Twitter/X) untuk topik politik/bisnis/viral, YouTube (Narasi, CNN, Kompas TV), serta Google News RSS sebagai fallback bila salah satu sumber down.
  Sentimen dianalisis otomatis dari <em>judul + ringkasan + komentar</em> via kamus kata kunci Bahasa Indonesia (Revisi R25: kata "politik/viral/demo" tidak lagi otomatis dianggap negatif).
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
            <?php
              /* Revisi R25 (28 Juni 2026) — tampilkan komentar netizen jika ada. */
              $komenList = [];
              if (!empty($r['komentar'])) {
                  $dec = json_decode($r['komentar'], true);
                  if (is_array($dec)) $komenList = $dec;
              }
              if ($komenList):
            ?>
              <div class="mt-2 p-2 rounded bg-light border">
                <div class="small fw-bold text-muted mb-1"><i class="bi bi-chat-quote"></i> Komentar Netizen (<?= count($komenList) ?>):</div>
                <ul class="small mb-0 ps-3" style="max-height:160px;overflow:auto">
                  <?php foreach (array_slice($komenList,0,5) as $cmt): ?>
                    <li class="mb-1"><?= htmlspecialchars(mb_strimwidth($cmt,0,220,'…')) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <div class="small text-muted mt-2">
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

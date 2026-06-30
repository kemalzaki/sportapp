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
    /* Revisi 29 Juni 2026 — Google News RSS dipromosikan menjadi sumber UTAMA.
       Reddit/Nitter/YouTube sering diblokir/CORS/rate-limited di hosting Indonesia
       sehingga seluruh feed kosong (cards tidak muncul). Google News RSS jauh
       lebih stabil dan tetap menyediakan judul + sumber + ringkasan. */
    /* Revisi 30 Juni 2026 — Sumber dibatasi hanya media sosial sesuai permintaan:
       X (Twitter via Nitter mirror), TikTok (RSSHub), YouTube (RSS resmi),
       Instagram (RSSHub), Facebook (RSSHub). Google News dihapus.
       RSSHub instance default: https://rsshub.app  (boleh diganti via env RSSHUB_BASE) */
    $RSSHUB = rtrim(getenv('RSSHUB_BASE') ?: 'https://rsshub.app', '/');
    $sources = [
        // === X / Twitter (via Nitter mirror) ===
        'X • Indonesia'             => 'nitter:indonesia',
        'X • Politik'               => 'nitter:politik',
        'X • Viral'                 => 'nitter:viral',
        'X • Olahraga'              => 'nitter:olahraga',
        // === YouTube (RSS resmi) ===
        'YouTube • Narasi'          => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC9_F0RpdPNX4hL3KX5G6phw',
        'YouTube • CNN Indonesia'   => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCM4XlH5BIPNc-rUtcwI7Vfg',
        'YouTube • Kompas TV'       => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC5BMQOsmB91nXgwIwfwSJYg',
        'YouTube • tvOneNews'       => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCgM2lH5BIPNc-rUtcwI7Vfg',
        // === TikTok (via RSSHub) ===
        'TikTok • CNN Indonesia'    => $RSSHUB.'/tiktok/user/@cnnindonesia',
        'TikTok • Detikcom'         => $RSSHUB.'/tiktok/user/@detikcom',
        'TikTok • Kompascom'        => $RSSHUB.'/tiktok/user/@kompascom',
        // === Instagram (via RSSHub) ===
        'Instagram • CNN Indonesia' => $RSSHUB.'/instagram/user/cnnindonesia',
        'Instagram • Detikcom'      => $RSSHUB.'/instagram/user/detikcom',
        'Instagram • Kompascom'     => $RSSHUB.'/instagram/user/kompascom',
        // === Facebook (via RSSHub) ===
        'Facebook • CNN Indonesia'  => $RSSHUB.'/facebook/page/CNNIndonesia',
        'Facebook • Detikcom'       => $RSSHUB.'/facebook/page/detikcom',
        'Facebook • Kompascom'      => $RSSHUB.'/facebook/page/KOMPAScom',
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
  <i class="bi bi-info-circle"></i> <b>Sumber data:</b> X (via Nitter mirror), TikTok, YouTube, Instagram, dan Facebook (akun media populer Indonesia).
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

<?php
  /* ============================================================
     Revisi (29 Juni 2026) — Ubah tampilan menjadi list KOMENTAR
     (positif / netral / negatif) lengkap dengan statistik global.
     ============================================================ */
  $posKw = ['bagus','mantap','setuju','keren','hebat','top','suka','baik','dukung','salut','semangat','alhamdulillah','sukses','terima kasih','makasih','cinta','sayang','indah','luar biasa','bangga','optimis','jaya','sehat','damai'];
  $negKw = ['jelek','buruk','marah','benci','tolol','goblok','bodoh','anjing','bangsat','kecewa','gagal','korup','penipu','sampah','sedih','prihatin','jijik','muak','tipu','hoax','rusak','hancur','tragis','memprihatinkan'];

  function _opini_klasifikasi_komentar(string $teks, array $pos, array $neg): array {
      $low = ' '.mb_strtolower($teks).' ';
      $p=0; $n=0;
      foreach ($pos as $w) { if (strpos($low,' '.$w)!==false) $p++; }
      foreach ($neg as $w) { if (strpos($low,' '.$w)!==false) $n++; }
      if ($p===$n) return ['netral', 0];
      if ($p > $n) return ['positif', $p-$n];
      return ['negatif', $n-$p];
  }

  // Flatten semua komentar dari semua topik (dalam 6 jam terakhir) menjadi satu list.
  $allKomentar = [];
  foreach ($rows as $r) {
      if (empty($r['komentar'])) continue;
      $dec = json_decode($r['komentar'], true);
      if (!is_array($dec)) continue;
      foreach ($dec as $cmt) {
          $cmt = trim((string)$cmt);
          if ($cmt === '') continue;
          [$lab, $bobot] = _opini_klasifikasi_komentar($cmt, $posKw, $negKw);
          $allKomentar[] = [
              'teks'      => $cmt,
              'sentimen'  => $lab,
              'bobot'     => $bobot,
              'topik'     => $r['judul'],
              'url'       => $r['url'],
              'sumber'    => $r['sumber'],
              'kategori'  => $r['kategori'],
              'waktu'     => $r['fetched_at'],
          ];
      }
  }

  $kCounts = ['positif'=>0,'netral'=>0,'negatif'=>0];
  foreach ($allKomentar as $k) { $kCounts[$k['sentimen']]++; }
  $kTotal = max(1, count($allKomentar));
  $pPos = round(100*$kCounts['positif']/$kTotal);
  $pNet = round(100*$kCounts['netral']/$kTotal);
  $pNeg = max(0, 100 - $pPos - $pNet);

  // Filter komentar via query string ?ks=positif|netral|negatif (opsional).
  $fKsent = in_array($_GET['ks'] ?? '', ['positif','netral','negatif'], true) ? $_GET['ks'] : '';
  $komentarTampil = $fKsent
      ? array_values(array_filter($allKomentar, fn($k)=>$k['sentimen']===$fKsent))
      : $allKomentar;
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-light">
    <i class="bi bi-chat-quote-fill text-primary"></i>
    <strong>Statistik Komentar Netizen</strong>
    <span class="small text-muted ms-1">(<?= count($allKomentar) ?> komentar dari <?= count($rows) ?> topik, 6 jam terakhir)</span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3">
      <div class="col-4">
        <div class="border border-success rounded text-center p-2 bg-success-subtle">
          <div class="small text-success-emphasis"><i class="bi bi-emoji-smile-fill"></i> Positif</div>
          <div class="h4 mb-0 text-success"><?= $kCounts['positif'] ?></div>
          <div class="small text-muted"><?= $pPos ?>%</div>
        </div>
      </div>
      <div class="col-4">
        <div class="border border-secondary rounded text-center p-2 bg-light">
          <div class="small text-secondary"><i class="bi bi-emoji-neutral-fill"></i> Netral</div>
          <div class="h4 mb-0 text-secondary"><?= $kCounts['netral'] ?></div>
          <div class="small text-muted"><?= $pNet ?>%</div>
        </div>
      </div>
      <div class="col-4">
        <div class="border border-danger rounded text-center p-2 bg-danger-subtle">
          <div class="small text-danger-emphasis"><i class="bi bi-emoji-frown-fill"></i> Negatif</div>
          <div class="h4 mb-0 text-danger"><?= $kCounts['negatif'] ?></div>
          <div class="small text-muted"><?= $pNeg ?>%</div>
        </div>
      </div>
    </div>
    <div class="progress mb-2" style="height:10px" role="progressbar"
         aria-label="Distribusi sentimen komentar" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-success" style="width:<?= $pPos ?>%" title="Positif <?= $pPos ?>%"></div>
      <div class="progress-bar bg-secondary" style="width:<?= $pNet ?>%" title="Netral <?= $pNet ?>%"></div>
      <div class="progress-bar bg-danger" style="width:<?= $pNeg ?>%" title="Negatif <?= $pNeg ?>%"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <a class="btn btn-sm <?= $fKsent===''?'btn-primary':'btn-outline-primary' ?>" href="/opini_viral.php">Semua</a>
      <a class="btn btn-sm <?= $fKsent==='positif'?'btn-success':'btn-outline-success' ?>" href="?ks=positif"><i class="bi bi-emoji-smile"></i> Positif</a>
      <a class="btn btn-sm <?= $fKsent==='netral'?'btn-secondary':'btn-outline-secondary' ?>" href="?ks=netral"><i class="bi bi-emoji-neutral"></i> Netral</a>
      <a class="btn btn-sm <?= $fKsent==='negatif'?'btn-danger':'btn-outline-danger' ?>" href="?ks=negatif"><i class="bi bi-emoji-frown"></i> Negatif</a>
    </div>
  </div>
</div>

<?php if (!$komentarTampil): ?>
  <div class="alert alert-warning small">Belum ada komentar untuk ditampilkan. Klik <b>Muat Ulang Data</b> di atas untuk menarik komentar terbaru dari sumber publik.</div>
<?php else: ?>
  <h3 class="h6 mb-2"><i class="bi bi-list-ul"></i> Daftar Komentar
    <?php if ($fKsent): ?>
      <span class="badge bg-<?= $fKsent==='positif'?'success':($fKsent==='negatif'?'danger':'secondary') ?>"><?= htmlspecialchars($fKsent) ?></span>
    <?php endif; ?>
    <span class="small text-muted">(<?= count($komentarTampil) ?>)</span>
  </h3>
  <div class="row g-2">
  <?php foreach ($komentarTampil as $k):
      $col = $k['sentimen']==='positif'?'success':($k['sentimen']==='negatif'?'danger':'secondary');
      $ico = $k['sentimen']==='positif'?'bi-emoji-smile-fill':($k['sentimen']==='negatif'?'bi-emoji-frown-fill':'bi-emoji-neutral-fill');
  ?>
    <div class="col-md-6">
      <div class="card h-100 border-start border-<?= $col ?> border-3 shadow-sm">
        <div class="card-body py-2 px-3">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <span class="badge bg-<?= $col ?>"><i class="bi <?= $ico ?>"></i> <?= htmlspecialchars($k['sentimen']) ?></span>
            <span class="badge bg-light text-dark border small"><?= htmlspecialchars($k['kategori'] ?? '-') ?></span>
          </div>
          <p class="mb-2 small" style="white-space:pre-wrap"><?= htmlspecialchars(mb_strimwidth($k['teks'],0,400,'…')) ?></p>
          <div class="small text-muted">
            <i class="bi bi-newspaper"></i>
            <a href="<?= htmlspecialchars($k['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none text-muted">
              <?= htmlspecialchars(mb_strimwidth((string)$k['topik'],0,90,'…')) ?>
            </a>
            <?php if (!empty($k['sumber'])): ?> · <?= htmlspecialchars($k['sumber']) ?><?php endif; ?>
            · <i class="bi bi-clock"></i> <?= htmlspecialchars(date('d M H:i', strtotime($k['waktu']))) ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>

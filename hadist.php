<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/hadist_data.php';
send_security_headers(); require_login();
$pageTitle = 'Ensiklopedia Hadist';

// =========================================================
//  Sumber tambahan: API api.hadith.gading.dev (Bukhari & Muslim)
//  Daftar nomor di bawah dipilih agar relevan dengan tema
//  "Perjuangan" dan "Olahraga". Hasil di-cache 7 hari ke file.
// =========================================================
$HADITH_API_MAP = [
  'Sahih Bukhari' => [
    'slug' => 'bukhari',
    'perjuangan' => [2787,2790,2792,2810,2811,2823,2827,2831,2832,2834,2835,2836,2841,5641,6114,6116,6117,2945,2966,3026],
    'olahraga'   => [2868,2869,2870,2899,2900,2902,2906,2912,2913,5190,6037,6038,6039,2898,2901],
  ],
  'Sahih Muslim' => [
    'slug' => 'muslim',
    'perjuangan' => [1876,1877,1878,1880,1885,1886,1888,1903,1904,1905,1910,1913,2664,2865,1037,1051],
    'olahraga'   => [1917,1919,1920,1922,1918],
  ],
];

function hadist_cache_path(string $kitab, string $tema): string {
    $dir = __DIR__.'/uploads/cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir.'/hadist_'.preg_replace('/[^a-z0-9]/i','_', strtolower($kitab)).'_'.$tema.'.json';
}

function hadist_fetch_api(string $kitab, string $tema, array $map): array {
    if (!isset($map[$kitab])) return [];
    $cfg = $map[$kitab];
    $cache = hadist_cache_path($kitab, $tema);
    if (file_exists($cache) && (time() - filemtime($cache)) < 7*24*3600) {
        $j = json_decode((string)file_get_contents($cache), true);
        if (is_array($j)) return $j;
    }
    $nums = $cfg[$tema] ?? [];
    $out = [];
    foreach ($nums as $n) {
        $url = 'https://api.hadith.gading.dev/books/'.$cfg['slug'].'/'.$n;
        $ctx = stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true,'user_agent'=>'SportApp/1.0']]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) continue;
        $j = json_decode($raw, true);
        $d = $j['data']['contents'] ?? null;
        if (!$d || empty($d['arab']) || empty($d['id'])) continue;
        $out[] = [
            'kitab'  => $kitab,
            'no'     => (int)($d['number'] ?? $n),
            'tema'   => $tema,
            'arab'   => trim((string)$d['arab']),
            'id'     => trim((string)$d['id']),
            'perawi' => '—',
        ];
    }
    if ($out) @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE));
    return $out;
}

$tema   = $_GET['tema']  ?? 'semua';
$kitab  = $_GET['kitab'] ?? 'semua';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= 10;

// Gabungkan dataset lokal + API per kombinasi (kitab, tema)
$all = $HADITHS;
$kitabList = ($kitab === 'semua') ? ['Sahih Bukhari','Sahih Muslim'] : [$kitab];
$temaList  = ($tema  === 'semua') ? ['perjuangan','olahraga']        : [$tema];
foreach ($kitabList as $k) {
    foreach ($temaList as $t) {
        foreach (hadist_fetch_api($k, $t, $HADITH_API_MAP) as $h) $all[] = $h;
    }
}

// Dedup berdasarkan kitab+nomor
$seen = []; $dedup = [];
foreach ($all as $h) {
    $key = ($h['kitab'] ?? '').'#'.($h['no'] ?? '');
    if (isset($seen[$key])) continue;
    $seen[$key] = 1;
    $dedup[] = $h;
}

// Filter
$data = array_values(array_filter($dedup, function($h) use ($tema,$kitab,$q){
    if ($tema!=='semua' && ($h['tema']??'')!==$tema) return false;
    if ($kitab!=='semua' && ($h['kitab']??'')!==$kitab) return false;
    if ($q!=='') {
        $hay = mb_strtolower(($h['no']??'').' '.($h['id']??'').' '.($h['arab']??'').' '.($h['perawi']??'').' '.($h['kitab']??''));
        if (mb_strpos($hay, mb_strtolower($q)) === false) return false;
    }
    return true;
}));

// Urutkan: kitab, lalu nomor asc
usort($data, function($a,$b){
    $c = strcmp($a['kitab'] ?? '', $b['kitab'] ?? '');
    if ($c !== 0) return $c;
    return ((int)($a['no']??0)) <=> ((int)($b['no']??0));
});

$total = count($data);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$pageData = array_slice($data, ($page-1)*$perPage, $perPage);

include __DIR__.'/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-book-half text-success"></i> Ensiklopedia Hadist</h4>
<p class="text-muted small">Koleksi hadits dari <strong>Sahih Bukhari</strong> &amp; <strong>Sahih Muslim</strong> bertema <em>Perjuangan</em> dan <em>Olahraga</em>. Sumber: koleksi internal + <code>api.hadith.gading.dev</code>.</p>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="tema" class="form-select" onchange="this.form.submit()">
    <option value="semua" <?= $tema==='semua'?'selected':'' ?>>Semua tema</option>
    <option value="perjuangan" <?= $tema==='perjuangan'?'selected':'' ?>>Perjuangan</option>
    <option value="olahraga"   <?= $tema==='olahraga'?'selected':'' ?>>Olahraga</option>
  </select></div>
  <div class="col-md-3"><select name="kitab" class="form-select" onchange="this.form.submit()">
    <option value="semua" <?= $kitab==='semua'?'selected':'' ?>>Semua kitab</option>
    <option value="Sahih Bukhari" <?= $kitab==='Sahih Bukhari'?'selected':'' ?>>Sahih Bukhari</option>
    <option value="Sahih Muslim"  <?= $kitab==='Sahih Muslim'?'selected':'' ?>>Sahih Muslim</option>
  </select></div>
  <div class="col-md-5"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari kata kunci / nomor hadits..."></div>
  <div class="col-md-1"><button class="btn btn-success w-100"><i class="bi bi-search"></i></button></div>
</form>

<div class="small text-muted mb-2"><?= $total ?> hadits · halaman <?= $page ?> / <?= $totalPages ?></div>

<?php foreach ($pageData as $h):
  $temaColor = ($h['tema'] ?? '') === 'olahraga' ? 'primary' : 'danger';
  $no = (int)($h['no'] ?? 0); ?>
  <div class="card mb-3 shadow-sm border-start border-4 border-<?= $temaColor ?>">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
        <div>
          <span class="badge bg-<?= $temaColor ?> text-uppercase me-1"><?= htmlspecialchars($h['tema'] ?? '-') ?></span>
          <strong><?= htmlspecialchars($h['kitab'] ?? '-') ?></strong>
          <span class="badge bg-dark ms-1">No. <?= $no ?: '—' ?></span>
        </div>
        <small class="text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($h['perawi'] ?? '-') ?></small>
      </div>
      <p class="fs-5 text-end" style="font-family:'Scheherazade New','Amiri',serif;line-height:2.1" dir="rtl"><?= htmlspecialchars($h['arab'] ?? '') ?></p>
      <p class="mb-0 text-secondary"><i class="bi bi-translate"></i> <?= htmlspecialchars($h['id'] ?? '') ?></p>
    </div>
  </div>
<?php endforeach; if (!$pageData): ?>
  <div class="alert alert-warning">Tidak ada hadits yang cocok.</div>
<?php endif; ?>

<?php if ($totalPages > 1):
  $qs = function($p) use ($tema,$kitab,$q){
      return '?'.http_build_query(['tema'=>$tema,'kitab'=>$kitab,'q'=>$q,'page'=>$p]);
  };
  $start = max(1, $page-2); $end = min($totalPages, $page+2); ?>
<nav><ul class="pagination pagination-sm justify-content-center">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $qs(max(1,$page-1)) ?>">«</a></li>
  <?php if ($start > 1): ?>
    <li class="page-item"><a class="page-link" href="<?= $qs(1) ?>">1</a></li>
    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
  <?php endif; ?>
  <?php for ($p=$start; $p<=$end; $p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $qs($p) ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <?php if ($end < $totalPages): ?>
    <?php if ($end < $totalPages-1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
    <li class="page-item"><a class="page-link" href="<?= $qs($totalPages) ?>"><?= $totalPages ?></a></li>
  <?php endif; ?>
  <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= $qs(min($totalPages,$page+1)) ?>">»</a></li>
</ul></nav>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Leaderboard Amal & Aktivitas Sehat';
$pageSkeleton = 'table'; // Skeleton sesuai data: tabel leaderboard

// Sorting
$sortOpts = [
    'poin_islami'    => 'poin_islami',
    'hari_aktif'     => 'hari_aktif',
    'hadir_olahraga' => 'hadir_olahraga',
    'nama'           => 'nama',
];
$sort = $_GET['sort'] ?? 'poin_islami';
if (!isset($sortOpts[$sort])) $sort = 'poin_islami';
$dir  = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per'] ?? 10)));
$offset  = ($page - 1) * $perPage;

$total = (int) db_val("SELECT COUNT(*) FROM users WHERE role IN ('member','admin')");
$totalPages = max(1, (int)ceil($total / $perPage));

$rows = db_all("SELECT u.id, u.nama, u.foto_url,
                COALESCE(SUM(s.poin),0) AS poin_islami,
                COALESCE((SELECT COUNT(*) FROM islami_streak s2 WHERE s2.user_id=u.id),0) AS hari_aktif,
                COALESCE((SELECT COUNT(*) FROM absensi a WHERE a.user_id=u.id AND a.hadir=1),0) AS hadir_olahraga
                FROM users u
                LEFT JOIN islami_streak s ON s.user_id=u.id
                WHERE u.role IN ('member','admin')
                GROUP BY u.id, u.nama, u.foto_url
                ORDER BY {$sortOpts[$sort]} $dir, u.id ASC
                LIMIT $perPage OFFSET $offset");

function sort_link($key, $label, $curSort, $curDir) {
    $newDir = ($curSort === $key && $curDir === 'DESC') ? 'asc' : 'desc';
    $arrow = '';
    if ($curSort === $key) $arrow = $curDir === 'DESC' ? ' ▼' : ' ▲';
    $qs = $_GET; $qs['sort']=$key; $qs['dir']=$newDir; unset($qs['page']);
    return '<a class="text-decoration-none" href="?'.http_build_query($qs).'">'.htmlspecialchars($label).$arrow.'</a>';
}
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Leaderboard Islami');
?>
<h4 class="mb-3"><i class="bi bi-bar-chart-line text-danger"></i> Leaderboard Amal & Aktivitas Sehat</h4>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><label class="small">Per halaman</label>
    <select name="per" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ([10,25,50,100] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><label class="small">Urut</label>
    <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="poin_islami"    <?= $sort==='poin_islami'?'selected':'' ?>>Poin Islami</option>
      <option value="hari_aktif"     <?= $sort==='hari_aktif'?'selected':'' ?>>Hari Aktif</option>
      <option value="hadir_olahraga" <?= $sort==='hadir_olahraga'?'selected':'' ?>>Hadir Olahraga</option>
      <option value="nama"           <?= $sort==='nama'?'selected':'' ?>>Nama</option>
    </select>
  </div>
  <div class="col-auto"><label class="small">Arah</label>
    <select name="dir" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="desc" <?= $dir==='DESC'?'selected':'' ?>>Terbesar dulu</option>
      <option value="asc"  <?= $dir==='ASC' ?'selected':'' ?>>Terkecil dulu</option>
    </select>
  </div>
  <div class="col-auto align-self-end small text-muted">Total: <strong><?= $total ?></strong> member</div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table mb-0 align-middle">
  <thead><tr>
    <th>#</th>
    <th><?= sort_link('nama','Member',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('poin_islami','Poin Islami',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('hari_aktif','Hari Aktif',$sort,$dir) ?></th>
    <th class="text-end"><?= sort_link('hadir_olahraga','Hadir Olahraga',$sort,$dir) ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $i=>$r): $rank = $offset + $i + 1; ?>
    <tr>
      <td><?= $rank ?> <?php if($rank<=3): ?><i class="bi bi-trophy-fill text-warning"></i><?php endif; ?></td>
      <td><?= user_avatar($r['foto_url'] ?? null, $r['nama'], 28) ?> <?= htmlspecialchars($r['nama']) ?></td>
      <td class="text-end"><span class="badge bg-success"><?= (int)$r['poin_islami'] ?></span></td>
      <td class="text-end"><?= (int)$r['hari_aktif'] ?></td>
      <td class="text-end"><?= (int)$r['hadir_olahraga'] ?></td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php
    $mk = function($p,$label=null,$disabled=false,$active=false) use ($sort,$dir,$perPage){
        $qs = $_GET; $qs['page']=$p; $qs['sort']=$sort; $qs['dir']=strtolower($dir); $qs['per']=$perPage;
        $cls = 'page-item'.($disabled?' disabled':'').($active?' active':'');
        $lbl = $label ?? $p;
        return '<li class="'.$cls.'"><a class="page-link" href="?'.http_build_query($qs).'">'.$lbl.'</a></li>';
    };
    echo $mk(max(1,$page-1), '«', $page<=1);
    $start = max(1, $page-3); $end = min($totalPages, $page+3);
    if ($start>1) echo $mk(1).'<li class="page-item disabled"><span class="page-link">…</span></li>';
    for ($p=$start; $p<=$end; $p++) echo $mk($p, $p, false, $p===$page);
    if ($end<$totalPages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'.$mk($totalPages);
    echo $mk(min($totalPages,$page+1), '»', $page>=$totalPages);
  ?>
</ul></nav>
<?php endif; ?>

<?php htmx_layout_end(); ?>

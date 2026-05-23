<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Daftar Tempat';

$q = trim($_GET['q'] ?? '');
$fJenis = (int)($_GET['jenis'] ?? 0);
$where = []; $params = []; $i=1;
if ($q !== '') { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[]="%$q%"; $i++; }
if ($fJenis) { $where[] = "t.jenis_id = \$$i"; $params[]=$fJenis; $i++; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
$rows = db_all("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto
                FROM tempat t LEFT JOIN jenis_olahraga jo ON jo.id=t.jenis_id
                LEFT JOIN users u ON u.id=t.pic_user_id $wsql ORDER BY t.nama ASC", $params);
$jenisList = db_all("SELECT id,nama FROM jenis_olahraga ORDER BY nama");
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-geo-alt-fill text-primary"></i> Daftar Tempat Olahraga</h2>
<p class="text-muted small">Tempat-tempat olahraga yang dikelola admin komunitas. Klik untuk melihat detail & arah lokasi.</p>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-6"><input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="🔍 Cari nama / alamat..."></div>
    <div class="col-md-4"><select class="form-select form-select-sm" name="jenis">
      <option value="0">Semua Jenis</option>
      <?php foreach($jenisList as $jn): ?><option value="<?= (int)$jn['id'] ?>" <?= $fJenis===(int)$jn['id']?'selected':'' ?>><?= htmlspecialchars($jn['nama']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button></div>
  </form>
</div></div>

<div class="row g-3">
<?php foreach($rows as $r):
  $maps = ($r['lat'] && $r['lng']) ? ('https://www.google.com/maps?q='.$r['lat'].','.$r['lng']) : ('https://www.google.com/maps/search/'.urlencode($r['nama'].' '.($r['alamat']??''))); ?>
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <h5 class="card-title mb-1"><?= htmlspecialchars($r['nama']) ?></h5>
          <?php $st=$r['status_booking']; $cls=$st==='tersedia'?'success':($st==='booked'?'warning':'secondary'); ?>
          <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
        </div>
        <?php if($r['jenis_nama']): ?><div class="mb-2"><span class="pill"><i class="bi bi-tags"></i> <?= htmlspecialchars($r['jenis_nama']) ?></span></div><?php endif; ?>
        <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($r['alamat'] ?? '—') ?></p>
        <?php if($r['pic_nama']): ?><div class="small mb-2">PIC: <?= user_name_with_avatar($r['pic_foto']??null,$r['pic_nama'],false,22) ?></div><?php endif; ?>
        <div class="d-flex gap-2">
          <a href="/tempat_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-info-circle"></i> Detail</a>
          <a href="<?= htmlspecialchars($maps) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-google"></i> Lihat Lokasi</a>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; if(!$rows): ?><div class="col-12"><div class="alert alert-info">Tidak ada tempat.</div></div><?php endif; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>

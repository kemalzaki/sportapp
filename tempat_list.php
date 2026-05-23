<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Daftar Tempat';
$u = current_user();
$isAdmin = $u && $u['role']==='admin';

$q = trim($_GET['q'] ?? '');
$fJenis = (int)($_GET['jenis'] ?? 0);
$where = []; $params = []; $i=1;
if ($q !== '') { $where[] = "(t.nama ILIKE \$$i OR t.alamat ILIKE \$$i)"; $params[]="%$q%"; $i++; }
if ($fJenis) { $where[] = "t.jenis_id = \$$i"; $params[]=$fJenis; $i++; }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
$rows = db_all("SELECT t.*, jo.nama AS jenis_nama, u.nama AS pic_nama, u.foto_url AS pic_foto, u.nomor_wa AS pic_wa
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
  $maps = ($r['lat'] && $r['lng']) ? ('https://www.google.com/maps/search/?api=1&query='.$r['lat'].','.$r['lng']) : ('https://www.google.com/maps/search/?api=1&query='.urlencode($r['nama'].' '.($r['alamat']??'')));
  // Data untuk popup member
  $picWa = preg_replace('/^0/','62', preg_replace('/\D+/','', $r['kontak_wa'] ?: ($r['pic_wa'] ?? '')));
  $popup = [
    'nama' => $r['nama'],
    'alamat' => $r['alamat'] ?? '',
    'jenis' => $r['jenis_nama'] ?? '',
    'status' => $r['status_booking'],
    'harga_lapang' => (float)$r['harga_lapang'],
    'harga_jam' => (float)$r['harga_per_jam'],
    'harga_tiket' => (float)($r['harga_tiket'] ?? 0),
    'harga_parkir' => (float)($r['harga_parkir'] ?? 0),
    'catatan' => $r['catatan'] ?? '',
    'pic_nama' => $r['pic_nama'] ?? '',
    'pic_foto' => $r['pic_foto'] ?? '',
    // Nomor telepon TIDAK dikirim ke member; hanya admin
    'kontak_wa' => $isAdmin ? ($r['kontak_wa'] ?? '') : '',
    'pic_wa_admin' => $isAdmin ? ($r['pic_wa'] ?? '') : '',
    'wa_link' => $picWa ? ('https://wa.me/'.$picWa) : '', // tombol WA tetap, tanpa expose nomor
    'lat' => $r['lat'],
    'lng' => $r['lng'],
    'maps' => $maps,
    'is_admin' => $isAdmin,
  ];
?>
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
          <button type="button" class="btn btn-sm btn-outline-primary"
            onclick='showTempatDetail(<?= json_encode($popup, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
            <i class="bi bi-info-circle"></i> Detail
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; if(!$rows): ?><div class="col-12"><div class="alert alert-info">Tidak ada tempat.</div></div><?php endif; ?>
</div>

<!-- Popup detail Tempat (untuk member) -->
<div class="modal fade" id="tempatModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="bi bi-geo-alt-fill text-primary"></i> <span id="tmNama">Tempat</span></h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-2 small text-muted" id="tmAlamat"></div>
      <div class="mb-2" id="tmJenis"></div>
      <div class="row g-3">
        <div class="col-12">
          <table class="table table-sm mb-2">
            <tr><th>Status</th><td><span id="tmStatus" class="badge bg-info-subtle text-info"></span></td></tr>
            <tr><th>Harga Lapang</th><td id="tmHL"></td></tr>
            <tr><th>Harga / Jam</th><td id="tmHJ"></td></tr>
            <tr><th>Harga Tiket</th><td id="tmHT"></td></tr>
            <tr><th>Harga Parkir</th><td id="tmHP"></td></tr>
            <tr id="tmRowPIC" class="d-none"><th>PIC</th><td id="tmPIC"></td></tr>
          </table>
          <div id="tmCatatan" class="small text-muted" style="white-space:pre-wrap"></div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <a id="tmWa" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success d-none"><i class="bi bi-whatsapp"></i> Hubungi PIC</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div></div>

<script>
let _tmM = null;
function showTempatDetail(d){
  if(!_tmM) _tmM = new bootstrap.Modal(document.getElementById('tempatModal'));
  const fmt = v => 'Rp '+ Number(v||0).toLocaleString('id-ID');
  document.getElementById('tmNama').textContent = d.nama || '';
  document.getElementById('tmAlamat').innerHTML = '<i class="bi bi-geo-alt"></i> ' + (d.alamat || '—');
  document.getElementById('tmJenis').innerHTML = d.jenis ? ('<span class="pill">'+d.jenis+'</span>') : '';
  document.getElementById('tmStatus').textContent = d.status || '';
  document.getElementById('tmHL').textContent = fmt(d.harga_lapang);
  document.getElementById('tmHJ').textContent = fmt(d.harga_jam);
  document.getElementById('tmHT').textContent = fmt(d.harga_tiket);
  document.getElementById('tmHP').textContent = fmt(d.harga_parkir);
  if (d.pic_nama) {
    document.getElementById('tmRowPIC').classList.remove('d-none');
    document.getElementById('tmPIC').textContent = d.pic_nama;
  } else {
    document.getElementById('tmRowPIC').classList.add('d-none');
  }
  document.getElementById('tmCatatan').textContent = d.catatan || '';
  const wa = document.getElementById('tmWa');
  if (d.wa_link) { wa.href = d.wa_link; wa.classList.remove('d-none'); } else { wa.classList.add('d-none'); }
  _tmM.show();
}
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

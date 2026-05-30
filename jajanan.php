<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers();
$pageTitle = 'Pesan Jajan';
$u = current_user(); // boleh null (guest)

$ONGKIR_DEFAULT = 5000;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'order') {
        $nama   = substr(trim($_POST['nama'] ?? ''),0,120);
        $no_wa  = substr(preg_replace('/[^0-9+]/','', $_POST['no_wa'] ?? ''),0,25);
        $alamat = substr(trim($_POST['alamat'] ?? ''),0,500);
        $catat  = substr(trim($_POST['catatan'] ?? ''),0,500);
        $items  = $_POST['qty'] ?? []; // [jajanan_id => qty]
        if ($nama==='' || $no_wa==='' || $alamat==='') {
            $_SESSION['flash_err'] = 'Nama, nomor WA, dan alamat wajib diisi.';
            header('Location: /jajanan.php'); exit;
        }
        $sub = 0; $pickedItems = [];
        foreach ($items as $jid => $qty) {
            $jid=(int)$jid; $qty=max(0,(int)$qty);
            if ($jid<=0 || $qty<=0) continue;
            $j = db_one("SELECT id, nama, harga, stok, aktif FROM jajanan WHERE id=$1",[$jid]);
            if (!$j || !($j['aktif']==='t'||$j['aktif']===true)) continue;
            $qty = min($qty, max(0,(int)$j['stok']));
            if ($qty<=0) continue;
            $pickedItems[] = ['j'=>$j,'qty'=>$qty];
            $sub += $qty * (int)$j['harga'];
        }
        if (!$pickedItems) {
            $_SESSION['flash_err'] = 'Pilih minimal 1 jajanan dengan jumlah > 0.';
            header('Location: /jajanan.php'); exit;
        }
        $ongkir = $ONGKIR_DEFAULT;
        $total = $sub + $ongkir;
        $kode = 'JJN-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        $plat = isset($_POST['pickup_lat']) && $_POST['pickup_lat']!=='' ? (float)$_POST['pickup_lat'] : null;
        $plng = isset($_POST['pickup_lng']) && $_POST['pickup_lng']!=='' ? (float)$_POST['pickup_lng'] : null;
        // Validasi server-side radius UIN SGD (fallback bila JS dilewati)
        if ($plat !== null && $plng !== null) {
            $UIN_LAT = -6.926263; $UIN_LNG = 107.717553; $UIN_R = 1500.0;
            $R=6371000; $toRad = M_PI/180;
            $dLat = ($UIN_LAT-$plat)*$toRad; $dLng = ($UIN_LNG-$plng)*$toRad;
            $s = sin($dLat/2)**2 + cos($plat*$toRad)*cos($UIN_LAT*$toRad)*sin($dLng/2)**2;
            $dist = 2*$R*asin(sqrt($s));
            if ($dist > $UIN_R) {
                $_SESSION['flash_err'] = "Lokasi diluar jangkauan kampus UIN SGD Bandung (".round($dist/1000,2)." km dari pusat kampus). Pesanan dibatalkan.";
                header('Location: /jajanan.php'); exit;
            }
        }
        db_exec("INSERT INTO jajanan_pesanan(kode,nama_pemesan,no_wa,alamat,catatan,subtotal,ongkir,total,metode,status,pickup_lat,pickup_lng)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,'cod','baru',$9,$10)",
          [$kode,$nama,$no_wa,$alamat,$catat?:null,$sub,$ongkir,$total,$plat,$plng]);
        $pid = (int) db_val("SELECT id FROM jajanan_pesanan WHERE kode=$1",[$kode]);
        foreach ($pickedItems as $pi) {
            db_exec("INSERT INTO jajanan_pesanan_item(pesanan_id,jajanan_id,nama,harga,qty) VALUES($1,$2,$3,$4,$5)",
              [$pid,(int)$pi['j']['id'],$pi['j']['nama'],(int)$pi['j']['harga'],(int)$pi['qty']]);
            // kurangi stok
            db_exec("UPDATE jajanan SET stok = GREATEST(0, stok - $1) WHERE id=$2",[(int)$pi['qty'],(int)$pi['j']['id']]);
        }
        $_SESSION['flash'] = "Pesanan berhasil dibuat dengan kode $kode. Total Rp ".number_format($total,0,',','.').". Admin akan menghubungi Anda lewat WhatsApp.";
        header('Location: /jajanan.php?kode='.$kode); exit;
    }
}

$rows = db_all("SELECT * FROM jajanan WHERE aktif=true AND stok>0 ORDER BY kategori NULLS LAST, nama");
$kategoriList = [];
foreach($rows as $r){ $k = $r['kategori'] ?? 'Lainnya'; $kategoriList[$k][] = $r; }
$kode = $_GET['kode'] ?? '';
$myOrder = $kode ? db_one("SELECT * FROM jajanan_pesanan WHERE kode=$1",[$kode]) : null;
$myItems = $myOrder ? db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$myOrder['id']]) : [];

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="p-3 mb-3 rounded-3 text-white" style="background:linear-gradient(135deg,#22c55e,#0ea5e9);">
  <h1 class="h4 mb-1 text-white"><i class="bi bi-bag-heart"></i> Pesan Jajan — Antar ke Rumah</h1>
  <p class="mb-0 small opacity-90">Mirip Gojek: pesan jajanan favorit, kurir adalah member komunitas kami. Tanpa perlu login.</p>
</div>

<?php if ($myOrder): ?>
<div class="card border-success mb-3"><div class="card-header bg-success text-white">
  <i class="bi bi-check2-circle"></i> Pesanan Berhasil: <?= htmlspecialchars($myOrder['kode']) ?>
</div><div class="card-body">
  <div class="small">Status: <span class="badge bg-info"><?= htmlspecialchars($myOrder['status']) ?></span> · Total: <strong>Rp <?= number_format((int)$myOrder['total'],0,',','.') ?></strong></div>
  <ul class="small mb-0 mt-2">
    <?php foreach($myItems as $it): ?><li><?= (int)$it['qty'] ?>× <?= htmlspecialchars($it['nama']) ?> — Rp <?= number_format((int)$it['harga']*(int)$it['qty'],0,',','.') ?></li><?php endforeach; ?>
  </ul>
</div></div>
<?php endif; ?>

<form method="post" id="jjnForm">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="_action" value="order">

<?php foreach($kategoriList as $kat=>$items): ?>
<h5 class="mt-3"><i class="bi bi-tag"></i> <?= htmlspecialchars($kat) ?></h5>
<div class="row g-2">
<?php foreach($items as $r): ?>
  <div class="col-md-4 col-6">
    <div class="card h-100 shadow-sm">
      <?php if(!empty($r['foto_url'])): ?><img src="<?= htmlspecialchars($r['foto_url']) ?>" class="card-img-top" style="height:130px;object-fit:cover"><?php else: ?>
        <div class="bg-light text-center py-4"><i class="bi bi-bag fs-1 text-muted"></i></div><?php endif; ?>
      <div class="card-body p-2">
        <div class="fw-semibold small"><?= htmlspecialchars($r['nama']) ?></div>
        <div class="text-success small fw-bold">Rp <?= number_format((int)$r['harga'],0,',','.') ?></div>
        <?php if(!empty($r['deskripsi'])): ?><div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($r['deskripsi']) ?></div><?php endif; ?>
        <div class="d-flex align-items-center gap-1 mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-delta="-1" data-id="<?= (int)$r['id'] ?>">−</button>
          <input type="number" min="0" max="<?= (int)$r['stok'] ?>" value="0" name="qty[<?= (int)$r['id'] ?>]" data-harga="<?= (int)$r['harga'] ?>"
                 id="q<?= (int)$r['id'] ?>" class="form-control form-control-sm text-center qty-input" style="width:60px">
          <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-delta="1" data-id="<?= (int)$r['id'] ?>">+</button>
          <span class="small text-muted ms-auto">stok <?= (int)$r['stok'] ?></span>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if(!$rows): ?><p class="text-muted small">Belum ada jajanan tersedia. Silakan cek lagi nanti.</p><?php endif; ?>

<div class="card mt-3 shadow-sm" id="checkoutCard">
  <div class="card-header"><i class="bi bi-cart-check"></i> Data Pengantaran</div>
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-4"><label class="small">Nama</label>
        <input class="form-control form-control-sm" name="nama" required value="<?= htmlspecialchars($u['nama'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="small">Nomor WhatsApp</label>
        <input class="form-control form-control-sm" name="no_wa" required placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($u['nomor_wa'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="small">Catatan (opsional)</label>
        <input class="form-control form-control-sm" name="catatan" placeholder="cth: gerbang biru, pagar besi"></div>
      <div class="col-12"><label class="small">Alamat Lengkap Pengantaran</label>
        <textarea class="form-control form-control-sm" name="alamat" rows="2" required></textarea></div>

      <!-- ===== Deteksi Lokasi Saya (revisi 31 Mei 2026) ===== -->
      <div class="col-12">
        <div class="border rounded p-2 bg-light-subtle">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" id="btnDetectLoc" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-geo-alt-fill"></i> Deteksi Lokasi Saya
            </button>
            <span id="locCoords" class="small text-muted">Lat/Lng belum terdeteksi</span>
            <input type="hidden" name="pickup_lat" id="pickup_lat">
            <input type="hidden" name="pickup_lng" id="pickup_lng">
          </div>
          <div id="locWarn" class="alert alert-danger small mt-2 mb-0 d-none">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Lokasi diluar jangkauan kampus UIN SGD Bandung.</strong>
            Pengantaran hanya dilayani di sekitar kampus (radius ± 1,5 km dari pusat kampus).
          </div>
          <div id="locOk" class="alert alert-success small mt-2 mb-0 d-none">
            <i class="bi bi-check-circle-fill"></i> Lokasi berada di dalam radius kampus UIN SGD Bandung.
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3 p-2 bg-light rounded">
      <div class="d-flex justify-content-between small"><span>Subtotal</span><strong id="sumSub">Rp 0</strong></div>
      <div class="d-flex justify-content-between small"><span>Ongkir (flat)</span><strong>Rp <?= number_format($ONGKIR_DEFAULT,0,',','.') ?></strong></div>
      <hr class="my-1">
      <div class="d-flex justify-content-between"><span class="fw-semibold">Total Bayar (COD)</span><strong class="text-success" id="sumTot">Rp <?= number_format($ONGKIR_DEFAULT,0,',','.') ?></strong></div>
    </div>
    <button class="btn btn-success w-100 mt-3" id="btnSubmitOrder"><i class="bi bi-send-check"></i> Pesan Sekarang</button>
    <div class="small text-muted mt-2 text-center">Pembayaran COD (bayar saat barang diantar oleh kurir member kami).</div>
  </div>
</div>
</form>

<script>
(function(){
  const ONGKIR = <?= (int)$ONGKIR_DEFAULT ?>;
  function recalc(){
    let sub=0;
    document.querySelectorAll('.qty-input').forEach(i=>{
      const q = Math.max(0,parseInt(i.value||'0',10));
      const h = parseInt(i.dataset.harga||'0',10);
      sub += q*h;
    });
    document.getElementById('sumSub').textContent = 'Rp '+sub.toLocaleString('id-ID');
    document.getElementById('sumTot').textContent = 'Rp '+(sub+ONGKIR).toLocaleString('id-ID');
  }
  document.querySelectorAll('.qty-btn').forEach(b=>b.addEventListener('click',function(){
    const id=this.dataset.id, d=parseInt(this.dataset.delta,10);
    const el=document.getElementById('q'+id);
    let v = Math.max(0,(parseInt(el.value||'0',10))+d);
    const max = parseInt(el.max||'999',10); if(v>max) v=max;
    el.value=v; recalc();
  }));
  document.querySelectorAll('.qty-input').forEach(i=>i.addEventListener('input',recalc));

  // ===== Deteksi Lokasi & validasi radius kampus UIN SGD Bandung (revisi 31 Mei 2026) =====
  // Pusat kampus 1 UIN SGD Bandung (Cipadung): -6.926263, 107.717553. Radius 1500 m.
  var UIN = {lat: -6.926263, lng: 107.717553, radius_m: 1500};
  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2 + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  var locValid = null; // null=belum dicek, true=dalam radius, false=luar
  var btn = document.getElementById('btnDetectLoc');
  if (btn) btn.addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    btn.disabled = true; var orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mendeteksi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat = pos.coords.latitude, lng = pos.coords.longitude, acc = pos.coords.accuracy;
      document.getElementById('pickup_lat').value = lat.toFixed(6);
      document.getElementById('pickup_lng').value = lng.toFixed(6);
      var dist = haversine({lat:lat,lng:lng}, UIN);
      document.getElementById('locCoords').innerHTML = '<strong>Lat:</strong> '+lat.toFixed(6)+' · <strong>Lng:</strong> '+lng.toFixed(6)+' · akurasi '+Math.round(acc)+' m · jarak ke kampus: '+(dist/1000).toFixed(2)+' km';
      var warn = document.getElementById('locWarn'), ok = document.getElementById('locOk');
      if (dist > UIN.radius_m) {
        warn.classList.remove('d-none'); ok.classList.add('d-none'); locValid = false;
      } else {
        ok.classList.remove('d-none'); warn.classList.add('d-none'); locValid = true;
      }
      btn.disabled = false; btn.innerHTML = orig;
    }, function(err){
      alert('Gagal membaca lokasi: '+err.message);
      btn.disabled = false; btn.innerHTML = orig;
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  });
  // Cegah submit kalau sudah dideteksi & ternyata diluar radius
  var form = document.getElementById('jjnForm');
  if (form) form.addEventListener('submit', function(e){
    if (locValid === false) {
      e.preventDefault();
      alert('Lokasi diluar jangkauan kampus UIN SGD Bandung. Pesanan tidak bisa dilanjutkan.');
    }
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers();
$pageTitle = 'Pesan Jajan';
$u = current_user();

$ADMIN_WA_FIRDAM = getenv('ADMIN_WA_FIRDAM') ?: '6281386369207';

// ===== Konfigurasi ongkir berbasis jarak (revisi 1 Jun 2026 - Lanjutan #3) =====
// Patokan: pusat kampus UIN Sunan Gunung Djati Bandung
$UIN_LAT = -6.926263;
$UIN_LNG = 107.717553;
$UIN_R_REKOM_KM = 1.5;   // jarak rekomendasi pengantaran (revisi #5)
$UIN_R_MAX_KM   = 3.0;   // jarak maksimum yang masih bisa dilayani
$ONGKIR_BASE    = 3000;  // ongkir dasar (0 km)
$ONGKIR_PER_KM  = 2000;  // tambahan per km
$ONGKIR_FALLBACK = 5000; // kalau pemesan tidak share lokasi

$PER_PAGE       = 5;

/** Hitung jarak Haversine (meter) */
function jjn_haversine($lat1,$lng1,$lat2,$lng2){
    $R=6371000; $toRad=M_PI/180;
    $dLat=($lat2-$lat1)*$toRad; $dLng=($lng2-$lng1)*$toRad;
    $s=sin($dLat/2)**2 + cos($lat1*$toRad)*cos($lat2*$toRad)*sin($dLng/2)**2;
    return 2*$R*asin(sqrt($s));
}
/** Hitung ongkir dari jarak (meter) ke UIN */
function jjn_ongkir_from_dist_m($dist_m, $base, $perKm){
    $km = $dist_m / 1000.0;
    return (int) round($base + $km * $perKm);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';

    if ($a === 'cek_status') {
        $nm = trim($_POST['cek_nama'] ?? '');
        header('Location: /jajanan.php?cek_nama='.urlencode($nm).'#cek-status'); exit;
    }

    if ($a === 'order') {
        $nama   = substr(trim($_POST['nama'] ?? ''),0,120);
        $no_wa  = substr(preg_replace('/[^0-9+]/','', $_POST['no_wa'] ?? ''),0,25);
        $alamat = substr(trim($_POST['alamat'] ?? ''),0,500);
        $catat  = substr(trim($_POST['catatan'] ?? ''),0,500);
        $items  = $_POST['qty'] ?? [];
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

        $plat = isset($_POST['pickup_lat']) && $_POST['pickup_lat']!=='' ? (float)$_POST['pickup_lat'] : null;
        $plng = isset($_POST['pickup_lng']) && $_POST['pickup_lng']!=='' ? (float)$_POST['pickup_lng'] : null;

        // ===== Revisi #3: ongkir dihitung dari jarak UIN <-> pemesan =====
        if ($plat !== null && $plng !== null) {
            $dist = jjn_haversine($UIN_LAT,$UIN_LNG,$plat,$plng);
            if ($dist/1000 > $UIN_R_MAX_KM) {
                $_SESSION['flash_err'] = "Lokasi diluar jangkauan layanan (".round($dist/1000,2)." km > ".$UIN_R_MAX_KM." km dari UIN SGD Bandung). Pesanan dibatalkan.";
                header('Location: /jajanan.php'); exit;
            }
            $ongkir = jjn_ongkir_from_dist_m($dist, $ONGKIR_BASE, $ONGKIR_PER_KM);
        } else {
            $ongkir = $ONGKIR_FALLBACK;
        }
        $total = $sub + $ongkir;

        $kode = 'JJN-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        db_exec("INSERT INTO jajanan_pesanan(kode,nama_pemesan,no_wa,alamat,catatan,subtotal,ongkir,total,metode,status,pickup_lat,pickup_lng)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,'cod','baru',$9,$10)",
          [$kode,$nama,$no_wa,$alamat,$catat?:null,$sub,$ongkir,$total,$plat,$plng]);
        $pid = (int) db_val("SELECT id FROM jajanan_pesanan WHERE kode=$1",[$kode]);
        foreach ($pickedItems as $pi) {
            db_exec("INSERT INTO jajanan_pesanan_item(pesanan_id,jajanan_id,nama,harga,qty) VALUES($1,$2,$3,$4,$5)",
              [$pid,(int)$pi['j']['id'],$pi['j']['nama'],(int)$pi['j']['harga'],(int)$pi['qty']]);
            db_exec("UPDATE jajanan SET stok = GREATEST(0, stok - $1) WHERE id=$2",[(int)$pi['qty'],(int)$pi['j']['id']]);
        }
        $_SESSION['flash'] = "Pesanan berhasil dibuat dengan kode $kode. Total Rp ".number_format($total,0,',','.')." (ongkir Rp ".number_format($ongkir,0,',','.').").";
        header('Location: /jajanan.php?kode='.$kode.'&notify=1'); exit;
    }
}

$katAll = db_all("SELECT COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') AS kat, COUNT(*) AS n
                  FROM jajanan WHERE aktif=true AND stok>0
                  GROUP BY 1 ORDER BY 1");
$katPilih = trim($_GET['kat'] ?? '');

$page = max(1,(int)($_GET['page'] ?? 1));
$where = "WHERE aktif=true AND stok>0";
$params = [];
if ($katPilih !== '' && $katPilih !== 'Semua') {
    $where .= " AND COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') = $1";
    $params[] = $katPilih;
}
$totalProduk = (int) db_val("SELECT COUNT(*) FROM jajanan $where", $params);
$totalPage   = max(1,(int)ceil($totalProduk / $PER_PAGE));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page-1) * $PER_PAGE;
$rows = db_all("SELECT * FROM jajanan $where ORDER BY kategori NULLS LAST, nama
                LIMIT $PER_PAGE OFFSET $offset", $params);

$kode    = $_GET['kode'] ?? '';
$notify  = !empty($_GET['notify']);
$myOrder = $kode ? db_one("SELECT * FROM jajanan_pesanan WHERE kode=$1",[$kode]) : null;
$myItems = $myOrder ? db_all("SELECT * FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$myOrder['id']]) : [];

$cekNama = trim($_GET['cek_nama'] ?? '');
$cekHasil = [];
if ($cekNama !== '') {
    $cekHasil = db_all(
        "SELECT id,kode,nama_pemesan,no_wa,total,status,created_at,updated_at
         FROM jajanan_pesanan
         WHERE LOWER(nama_pemesan) LIKE LOWER($1)
         ORDER BY created_at DESC LIMIT 20",
        ['%'.$cekNama.'%']
    );
}

$waAdminLink = '';
if ($myOrder && $notify) {
    $lines = [];
    $lines[] = "🔔 *Pesanan Baru Pesan Jajan*";
    $lines[] = "Kode: *".$myOrder['kode']."*";
    $lines[] = "Nama: ".$myOrder['nama_pemesan'];
    $lines[] = "WA Pemesan: ".$myOrder['no_wa'];
    $lines[] = "Alamat: ".$myOrder['alamat'];
    if (!empty($myOrder['catatan'])) $lines[] = "Catatan: ".$myOrder['catatan'];
    $lines[] = "";
    foreach ($myItems as $it) {
        $lines[] = "• ".(int)$it['qty']."× ".$it['nama']." — Rp ".number_format((int)$it['harga']*(int)$it['qty'],0,',','.');
    }
    $lines[] = "";
    $lines[] = "Subtotal: Rp ".number_format((int)$myOrder['subtotal'],0,',','.');
    $lines[] = "Ongkir : Rp ".number_format((int)$myOrder['ongkir'],0,',','.');
    $lines[] = "*Total  : Rp ".number_format((int)$myOrder['total'],0,',','.')."* (COD)";
    if (!empty($myOrder['pickup_lat']) && !empty($myOrder['pickup_lng'])) {
        $lines[] = "Lokasi: https://www.google.com/maps?q=".$myOrder['pickup_lat'].",".$myOrder['pickup_lng'];
    }
    $waAdminLink = 'https://wa.me/'.preg_replace('/\D+/','',$ADMIN_WA_FIRDAM)
                  .'?text='.rawurlencode(implode("\n",$lines));
}

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="p-3 mb-3 rounded-3 text-white" style="background:linear-gradient(135deg,#22c55e,#0ea5e9);">
  <h1 class="h4 mb-1 text-white"><i class="bi bi-bag-heart"></i> Pesan Jajan — Antar ke Rumah</h1>
  <p class="mb-0 small opacity-90">Mirip Gojek: pesan jajanan favorit, kurir adalah member komunitas kami. Tanpa perlu login.</p>
</div>

<!-- ===== Revisi #5: keterangan jarak rekomendasi ===== -->
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle-fill"></i>
  <strong>Jarak yang direkomendasikan:</strong> maksimal ±<?= $UIN_R_REKOM_KM ?> km dari pusat kampus
  <strong>UIN Sunan Gunung Djati Bandung</strong> (titik patokan: <?= $UIN_LAT ?>, <?= $UIN_LNG ?>).
  Pengantaran masih bisa dilakukan hingga <?= $UIN_R_MAX_KM ?> km, namun di luar jangkauan rekomendasi
  ongkir bertambah sesuai jarak. <br>
  <strong>Ongkir:</strong> Rp <?= number_format($ONGKIR_BASE,0,',','.') ?> dasar + Rp <?= number_format($ONGKIR_PER_KM,0,',','.') ?>/km
  (dihitung otomatis dari titik lokasi yang kamu pilih).
</div>

<?php if ($myOrder): ?>
<div class="card border-success mb-3"><div class="card-header bg-success text-white">
  <i class="bi bi-check2-circle"></i> Pesanan Berhasil: <?= htmlspecialchars($myOrder['kode']) ?>
</div><div class="card-body">
  <div class="small">Status: <span class="badge bg-info"><?= htmlspecialchars($myOrder['status']) ?></span> · Total: <strong>Rp <?= number_format((int)$myOrder['total'],0,',','.') ?></strong> · Ongkir: Rp <?= number_format((int)$myOrder['ongkir'],0,',','.') ?></div>
  <ul class="small mb-0 mt-2">
    <?php foreach($myItems as $it): ?><li><?= (int)$it['qty'] ?>× <?= htmlspecialchars($it['nama']) ?> — Rp <?= number_format((int)$it['harga']*(int)$it['qty'],0,',','.') ?></li><?php endforeach; ?>
  </ul>
  <?php if ($waAdminLink): ?>
    <a id="waAdminBtn" href="<?= htmlspecialchars($waAdminLink) ?>" target="_blank" rel="noopener"
       class="btn btn-success btn-sm mt-2">
      <i class="bi bi-whatsapp"></i> Kirim Notifikasi WA ke Admin Firdam
    </a>
  <?php endif; ?>
</div></div>
<?php endif; ?>

<div class="card mb-3" id="cek-status">
  <div class="card-header bg-light"><i class="bi bi-search"></i> Cek Status Pesanan Saya</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="cek_status">
      <div class="col-md-8">
        <label class="small">Nama Pemesan</label>
        <input class="form-control form-control-sm" name="cek_nama" required
               value="<?= htmlspecialchars($cekNama) ?>"
               placeholder="Masukkan nama yang dipakai saat memesan">
      </div>
      <div class="col-md-4">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Cek Pesanan</button>
      </div>
    </form>
    <?php if ($cekNama !== ''): ?>
      <hr>
      <?php if (!$cekHasil): ?>
        <div class="small text-muted">Tidak ditemukan pesanan atas nama <strong><?= htmlspecialchars($cekNama) ?></strong>.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm small align-middle mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Total</th><th>Status</th><th>Tgl</th><th></th></tr></thead>
            <tbody>
            <?php foreach($cekHasil as $r): ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['kode']) ?></strong></td>
                <td><?= htmlspecialchars($r['nama_pemesan']) ?></td>
                <td>Rp <?= number_format((int)$r['total'],0,',','.') ?></td>
                <td><span class="badge bg-info"><?= htmlspecialchars($r['status']) ?></span></td>
                <td><?= htmlspecialchars(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
                <td><a class="btn btn-sm btn-outline-secondary" href="/jajanan.php?kode=<?= urlencode($r['kode']) ?>">Detail</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($katAll): ?>
<div class="mb-2 d-flex flex-wrap gap-1 align-items-center">
  <span class="small text-muted me-1"><i class="bi bi-tags"></i> Kategori:</span>
  <a href="/jajanan.php" class="btn btn-sm <?= $katPilih===''?'btn-success':'btn-outline-success' ?>">Semua</a>
  <?php foreach($katAll as $k): ?>
    <a href="/jajanan.php?kat=<?= urlencode($k['kat']) ?>"
       class="btn btn-sm <?= $katPilih===$k['kat']?'btn-success':'btn-outline-success' ?>">
       <?= htmlspecialchars($k['kat']) ?> <span class="badge bg-light text-success ms-1"><?= (int)$k['n'] ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" id="jjnForm">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="_action" value="order">

<div class="row g-2" id="jjnGrid">
<?php foreach($rows as $r): ?>
  <div class="col-md-4 col-6">
    <div class="card h-100 shadow-sm">
      <?php if(!empty($r['foto_url'])): ?><img src="<?= htmlspecialchars($r['foto_url']) ?>" class="card-img-top" style="height:130px;object-fit:cover"><?php else: ?>
        <div class="bg-light text-center py-4"><i class="bi bi-bag fs-1 text-muted"></i></div><?php endif; ?>
      <div class="card-body p-2">
        <?php if(!empty($r['kategori'])): ?>
          <span class="badge bg-success-subtle text-success mb-1"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($r['kategori']) ?></span>
        <?php endif; ?>
        <div class="fw-semibold small"><?= htmlspecialchars($r['nama']) ?></div>
        <div class="text-success small fw-bold">Rp <?= number_format((int)$r['harga'],0,',','.') ?></div>
        <?php if(!empty($r['deskripsi'])): ?><div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($r['deskripsi']) ?></div><?php endif; ?>
        <div class="d-flex align-items-center gap-1 mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-delta="-1" data-id="<?= (int)$r['id'] ?>">−</button>
          <input type="number" min="0" max="<?= (int)$r['stok'] ?>" value="0" name="qty[<?= (int)$r['id'] ?>]" data-harga="<?= (int)$r['harga'] ?>" data-nama="<?= htmlspecialchars($r['nama']) ?>"
                 id="q<?= (int)$r['id'] ?>" class="form-control form-control-sm text-center qty-input" style="width:60px">
          <button type="button" class="btn btn-sm btn-outline-secondary qty-btn" data-delta="1" data-id="<?= (int)$r['id'] ?>">+</button>
          <span class="small text-muted ms-auto">stok <?= (int)$r['stok'] ?></span>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if(!$rows): ?><p class="text-muted small mt-2">Belum ada jajanan tersedia di kategori ini.</p><?php endif; ?>

<?php if ($totalPage > 1):
    $qs = function($p) use ($katPilih) {
        $a = ['page'=>$p];
        if ($katPilih!=='') $a['kat']=$katPilih;
        return '?'.http_build_query($a);
    };
?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-1">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $qs(max(1,$page-1)) ?>">«</a></li>
  <?php for($p=1;$p<=$totalPage;$p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $qs($p) ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="<?= $qs(min($totalPage,$page+1)) ?>">»</a></li>
</ul></nav>
<div class="text-center small text-muted mb-2">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $totalProduk ?> produk · 5 per halaman</div>
<div class="text-center small text-muted mb-2" id="hiddenCartInfo"></div>
<?php endif; ?>

<!-- Container untuk hidden input qty produk yang dipilih di halaman lain (revisi #4) -->
<div id="hiddenCartHolder" style="display:none"></div>

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
            <strong>Lokasi diluar jangkauan layanan.</strong>
            Maksimum <?= $UIN_R_MAX_KM ?> km dari pusat kampus UIN SGD Bandung.
          </div>
          <div id="locOk" class="alert alert-success small mt-2 mb-0 d-none"></div>
        </div>
      </div>
    </div>
    <div class="mt-3 p-2 bg-light rounded">
      <div class="d-flex justify-content-between small"><span>Subtotal</span><strong id="sumSub">Rp 0</strong></div>
      <div class="d-flex justify-content-between small">
        <span>Ongkir <span id="sumOngkirNote" class="text-muted">(perkiraan flat)</span></span>
        <strong id="sumOngkir">Rp <?= number_format($ONGKIR_FALLBACK,0,',','.') ?></strong>
      </div>
      <hr class="my-1">
      <div class="d-flex justify-content-between"><span class="fw-semibold">Total Bayar (COD)</span><strong class="text-success" id="sumTot">Rp <?= number_format($ONGKIR_FALLBACK,0,',','.') ?></strong></div>
    </div>
    <button class="btn btn-success w-100 mt-3" id="btnSubmitOrder"><i class="bi bi-send-check"></i> Pesan Sekarang</button>
    <div class="small text-muted mt-2 text-center">Pembayaran COD. Setelah klik, WhatsApp ke admin Firdam akan otomatis terbuka untuk notifikasi pesanan.</div>
  </div>
</div>
</form>

<script>
(function(){
  // ===== Konfigurasi sinkron dengan PHP =====
  var UIN = {lat: <?= $UIN_LAT ?>, lng: <?= $UIN_LNG ?>, rekom_km: <?= $UIN_R_REKOM_KM ?>, max_km: <?= $UIN_R_MAX_KM ?>};
  var ONGKIR_BASE     = <?= (int)$ONGKIR_BASE ?>;
  var ONGKIR_PER_KM   = <?= (int)$ONGKIR_PER_KM ?>;
  var ONGKIR_FALLBACK = <?= (int)$ONGKIR_FALLBACK ?>;

  var STORE_KEY = 'jjn_cart_v1';
  function loadCart(){ try { return JSON.parse(sessionStorage.getItem(STORE_KEY)||'{}'); } catch(e){ return {}; } }
  function saveCart(c){ try { sessionStorage.setItem(STORE_KEY, JSON.stringify(c)); } catch(e){} }
  // Cart structure: { "<jid>": { qty: N, harga: N, nama: "..." } }
  var cart = loadCart();

  // ===== Restore qty yang sudah ada di cart pada produk yang tampil di halaman ini =====
  document.querySelectorAll('.qty-input').forEach(function(inp){
    var jid = inp.name.replace(/\D/g,'');
    if (cart[jid] && cart[jid].qty > 0) {
      inp.value = Math.min(cart[jid].qty, parseInt(inp.max||'999',10));
    }
  });

  // ===== Build hidden inputs untuk item di cart yang TIDAK tampil di halaman ini =====
  // (revisi #4: Total Bayar/COD tidak hilang saat pindah halaman pagination)
  function rebuildHiddenCart(){
    var holder = document.getElementById('hiddenCartHolder');
    holder.innerHTML = '';
    var info = [];
    Object.keys(cart).forEach(function(jid){
      var ent = cart[jid];
      if (!ent || ent.qty<=0) return;
      var visible = document.getElementById('q'+jid);
      if (visible) return; // sudah tampil sebagai input visible
      var h = document.createElement('input');
      h.type='hidden';
      h.name='qty['+jid+']';
      h.value=ent.qty;
      h.dataset.harga = ent.harga;
      h.dataset.nama  = ent.nama || '';
      h.className = 'qty-hidden';
      holder.appendChild(h);
      info.push(ent.qty+'× '+(ent.nama||('#'+jid)));
    });
    var el = document.getElementById('hiddenCartInfo');
    if (el) {
      el.textContent = info.length
        ? ('Di keranjang dari halaman lain: '+info.join(', '))
        : '';
    }
  }

  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2 + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));
  }
  var currentDistKm = null;
  var locValid = null;

  function fmtRp(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID'); }
  function calcOngkir(){
    if (currentDistKm !== null) {
      return Math.round(ONGKIR_BASE + currentDistKm*ONGKIR_PER_KM);
    }
    return ONGKIR_FALLBACK;
  }
  function recalc(){
    var sub=0;
    // input visible
    document.querySelectorAll('.qty-input').forEach(function(i){
      var jid = i.name.replace(/\D/g,'');
      var q   = Math.max(0,parseInt(i.value||'0',10));
      var h   = parseInt(i.dataset.harga||'0',10);
      var nm  = i.dataset.nama || '';
      if (q>0) { cart[jid] = {qty:q, harga:h, nama:nm}; }
      else if (cart[jid]) { delete cart[jid]; }
      sub += q*h;
    });
    saveCart(cart);
    rebuildHiddenCart();
    // tambah dari hidden (item halaman lain)
    document.querySelectorAll('.qty-hidden').forEach(function(i){
      var q = parseInt(i.value||'0',10);
      var h = parseInt(i.dataset.harga||'0',10);
      sub += q*h;
    });
    var ongkir = calcOngkir();
    document.getElementById('sumSub').textContent    = fmtRp(sub);
    document.getElementById('sumOngkir').textContent = fmtRp(ongkir);
    document.getElementById('sumTot').textContent    = fmtRp(sub+ongkir);
    var note = document.getElementById('sumOngkirNote');
    if (note) {
      note.textContent = (currentDistKm!==null)
        ? '('+currentDistKm.toFixed(2)+' km × Rp '+ONGKIR_PER_KM.toLocaleString('id-ID')+'/km + dasar)'
        : '(perkiraan flat — share lokasi untuk hitung akurat)';
    }
  }

  document.querySelectorAll('.qty-btn').forEach(function(b){
    b.addEventListener('click', function(){
      var id=this.dataset.id, d=parseInt(this.dataset.delta,10);
      var el=document.getElementById('q'+id);
      var v = Math.max(0,(parseInt(el.value||'0',10))+d);
      var max = parseInt(el.max||'999',10); if(v>max) v=max;
      el.value=v; recalc();
    });
  });
  document.querySelectorAll('.qty-input').forEach(function(i){ i.addEventListener('input',recalc); });

  // Inisialisasi
  rebuildHiddenCart();
  recalc();

  var btn = document.getElementById('btnDetectLoc');
  if (btn) btn.addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    btn.disabled = true; var orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mendeteksi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat = pos.coords.latitude, lng = pos.coords.longitude, acc = pos.coords.accuracy;
      document.getElementById('pickup_lat').value = lat.toFixed(6);
      document.getElementById('pickup_lng').value = lng.toFixed(6);
      var distM = haversine({lat:lat,lng:lng}, UIN);
      var distKm = distM/1000;
      currentDistKm = distKm;
      document.getElementById('locCoords').innerHTML =
        '<strong>Lat:</strong> '+lat.toFixed(6)+' · <strong>Lng:</strong> '+lng.toFixed(6)+
        ' · jarak ke UIN: <strong>'+distKm.toFixed(2)+' km</strong>';
      var warn = document.getElementById('locWarn'), ok = document.getElementById('locOk');
      warn.classList.add('d-none'); ok.classList.add('d-none');
      if (distKm > UIN.max_km) {
        warn.classList.remove('d-none'); locValid = false;
      } else if (distKm > UIN.rekom_km) {
        ok.classList.remove('d-none'); locValid = true;
        ok.classList.remove('alert-success'); ok.classList.add('alert-warning');
        ok.innerHTML = '<i class="bi bi-exclamation-circle"></i> Di luar jarak rekomendasi ('+UIN.rekom_km+' km) tapi masih dilayani. Ongkir disesuaikan jarak.';
      } else {
        ok.classList.remove('d-none'); locValid = true;
        ok.classList.remove('alert-warning'); ok.classList.add('alert-success');
        ok.innerHTML = '<i class="bi bi-check-circle-fill"></i> Lokasi berada di dalam jarak rekomendasi (≤ '+UIN.rekom_km+' km).';
      }
      recalc();
      btn.disabled = false; btn.innerHTML = orig;
    }, function(err){
      alert('Gagal membaca lokasi: '+err.message);
      btn.disabled = false; btn.innerHTML = orig;
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  });

  var form = document.getElementById('jjnForm');
  if (form) form.addEventListener('submit', function(e){
    if (locValid === false) {
      e.preventDefault();
      alert('Lokasi diluar jangkauan layanan (maks '+UIN.max_km+' km dari UIN SGD Bandung).');
      return;
    }
    // Kosongkan keranjang sessionStorage sesudah submit sukses (akan reload halaman)
    try { sessionStorage.removeItem(STORE_KEY); } catch(e){}
  });

  var waBtn = document.getElementById('waAdminBtn');
  if (waBtn) {
    setTimeout(function(){ try { window.open(waBtn.href, '_blank'); } catch(e){} }, 600);
  }
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

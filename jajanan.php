<?php
/**
 * Revisi 2 Jun 2026
 *   #1 Teks stok dirapikan (badge overlay, tidak tabrakan dengan tombol qty)
 *   #2 Gojek-style: tombol "Pesan" per produk → modal data pengantaran + Midtrans
 *   #3 Tombol "Tanyakan Ketersediaan" per produk → WA Firdam
 *   #4 Nomor telepon menggunakan prefix +62 (bukan 0)
 *   #5 Navbar tetap di atas saat scroll di mobile (lihat includes/header.php)
 *   #8 Kolom pencarian produk
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers();
$pageTitle = 'Pesan Jajan';
$u = current_user();

$ADMIN_WA_FIRDAM = getenv('ADMIN_WA_FIRDAM') ?: '6281386369207';

/* ---------- Midtrans ---------- */
$MT_SERVER_KEY = getenv('MIDTRANS_SERVER_KEY') ?: '';
$MT_CLIENT_KEY = getenv('MIDTRANS_CLIENT_KEY') ?: '';
$MT_PROD       = (bool) (getenv('MIDTRANS_PROD') ?: false);
$MT_BASE       = $MT_PROD ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
$MT_API_BASE   = $MT_PROD ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
$MT_SNAP_JS    = $MT_PROD
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';

/* ---------- Konfigurasi ongkir ---------- */
$UIN_LAT = -6.926263;
$UIN_LNG = 107.717553;
$UIN_R_REKOM_KM = 1.5;
$UIN_R_MAX_KM   = 3.0;
$ONGKIR_BASE    = 3000;
$ONGKIR_PER_KM  = 2000;
$ONGKIR_FALLBACK = 5000;
$PER_PAGE       = 5;

function jjn_haversine($lat1,$lng1,$lat2,$lng2){
    $R=6371000; $toRad=M_PI/180;
    $dLat=($lat2-$lat1)*$toRad; $dLng=($lng2-$lng1)*$toRad;
    $s=sin($dLat/2)**2 + cos($lat1*$toRad)*cos($lat2*$toRad)*sin($dLng/2)**2;
    return 2*$R*asin(sqrt($s));
}
function jjn_ongkir_from_dist_m($d, $base, $perKm){ return (int) round($base + ($d/1000.0) * $perKm); }

/** Normalisasi nomor telepon ke format internasional (62xxxxxx, tanpa +). */
function jjn_normalize_phone($raw){
    $s = preg_replace('/\D+/','', (string)$raw);
    if ($s === '') return '';
    if (strpos($s,'62') === 0) return $s;
    if (strpos($s,'0')  === 0) return '62' . substr($s,1);
    return '62' . $s;
}

/** Panggil endpoint Midtrans Snap. Return [token, redirect_url] atau lempar exception. */
function mt_snap_request(array $payload, $serverKey, $base) {
    $url = rtrim($base,'/') . '/snap/v1/transactions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($serverKey.':'),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException('Midtrans cURL: '.$err); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($resp, true);
    if ($code >= 400 || empty($j['token'])) {
        throw new RuntimeException('Midtrans error: '.($j['error_messages'][0] ?? $resp));
    }
    return $j;
}

function mt_status_request($orderId, $serverKey, $apiBase) {
    $url = rtrim($apiBase,'/') . '/v2/' . rawurlencode($orderId) . '/status';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($serverKey.':'),
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp, true) ?: [];
}

/* ============================================================
 * AJAX endpoints (JSON)
 * ============================================================ */
$ajax = $_GET['ajax'] ?? '';

if ($ajax === 'create_snap' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $jid    = (int)($_POST['jajanan_id'] ?? 0);
        $qty    = max(1,(int)($_POST['qty'] ?? 1));
        $nama   = substr(trim($_POST['nama'] ?? ''),0,120);
        $no_wa  = jjn_normalize_phone($_POST['no_wa'] ?? '');
        $alamat = substr(trim($_POST['alamat'] ?? ''),0,500);
        $catat  = substr(trim($_POST['catatan'] ?? ''),0,500);
        $plat   = ($_POST['pickup_lat'] ?? '') !== '' ? (float)$_POST['pickup_lat'] : null;
        $plng   = ($_POST['pickup_lng'] ?? '') !== '' ? (float)$_POST['pickup_lng'] : null;
        if (!$jid || $nama==='' || $no_wa==='' || $alamat==='') {
            throw new RuntimeException('Nama, nomor WA, dan alamat wajib diisi.');
        }
        $j = db_one("SELECT id,nama,harga,stok,aktif FROM jajanan WHERE id=$1",[$jid]);
        if (!$j || !($j['aktif']==='t'||$j['aktif']===true)) throw new RuntimeException('Produk tidak tersedia.');
        $qty = min($qty, max(0,(int)$j['stok']));
        if ($qty<=0) throw new RuntimeException('Stok habis.');

        // Ongkir
        if ($plat !== null && $plng !== null) {
            $dist = jjn_haversine($UIN_LAT,$UIN_LNG,$plat,$plng);
            if ($dist/1000 > $UIN_R_MAX_KM) throw new RuntimeException('Lokasi diluar jangkauan layanan (>'.$UIN_R_MAX_KM.' km).');
            $ongkir = jjn_ongkir_from_dist_m($dist, $ONGKIR_BASE, $ONGKIR_PER_KM);
        } else {
            $ongkir = $ONGKIR_FALLBACK;
        }
        $sub = $qty * (int)$j['harga'];
        $total = $sub + $ongkir;

        $kode = 'JJN-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        db_exec("INSERT INTO jajanan_pesanan(kode,nama_pemesan,no_wa,alamat,catatan,subtotal,ongkir,total,metode,status,pickup_lat,pickup_lng,midtrans_order_id,payment_status)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,'midtrans','pending_payment',$9,$10,$11,'pending')",
          [$kode,$nama,$no_wa,$alamat,$catat?:null,$sub,$ongkir,$total,$plat,$plng,$kode]);
        $pid = (int) db_val("SELECT id FROM jajanan_pesanan WHERE kode=$1",[$kode]);
        db_exec("INSERT INTO jajanan_pesanan_item(pesanan_id,jajanan_id,nama,harga,qty) VALUES($1,$2,$3,$4,$5)",
          [$pid,(int)$j['id'],$j['nama'],(int)$j['harga'],$qty]);

        if ($MT_SERVER_KEY === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum disetel di environment. Hubungi admin untuk mengaktifkan pembayaran.');
        }

        // URL absolut situs (untuk callback Midtrans → redirect setelah pembayaran)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $finish_url = $scheme.'://'.$host.'/jajanan.php?berhasil='.urlencode($kode);

        $payload = [
            'transaction_details' => ['order_id'=>$kode, 'gross_amount'=>$total],
            'item_details' => [
                ['id'=>'JJN-'.$j['id'], 'price'=>(int)$j['harga'], 'quantity'=>$qty, 'name'=>substr($j['nama'],0,50)],
                ['id'=>'ONGKIR',        'price'=>(int)$ongkir,     'quantity'=>1,    'name'=>'Ongkir'],
            ],
            'customer_details' => [
                'first_name'=>$nama, 'phone'=>$no_wa,
                'shipping_address'=>['address'=>$alamat],
            ],
            'enabled_payments' => ['bca_va','bni_va','bri_va','mandiri_va','permata_va','gopay','shopeepay','qris','other_va','bank_transfer'],
            'callbacks'        => ['finish' => $finish_url],
        ];
        $resp = mt_snap_request($payload, $MT_SERVER_KEY, $MT_BASE);
        db_exec("UPDATE jajanan_pesanan SET snap_token=$1, snap_redirect=$2 WHERE id=$3",
            [$resp['token'], $resp['redirect_url'] ?? null, $pid]);

        echo json_encode(['ok'=>true,'token'=>$resp['token'],'kode'=>$kode,'redirect'=>$resp['redirect_url'] ?? null]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($ajax === 'confirm_payment' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $kode = trim($_POST['kode'] ?? '');
        $order = $kode ? db_one("SELECT * FROM jajanan_pesanan WHERE kode=$1",[$kode]) : null;
        if (!$order) throw new RuntimeException('Pesanan tidak ditemukan.');
        $status = mt_status_request($kode, $MT_SERVER_KEY, $MT_API_BASE);
        $ts = $status['transaction_status'] ?? '';
        $fraud = $status['fraud_status'] ?? 'accept';
        if (in_array($ts, ['capture','settlement'], true) && $fraud === 'accept') {
            db_exec("UPDATE jajanan_pesanan SET payment_status='paid', status='baru', updated_at=now() WHERE id=$1",[(int)$order['id']]);
            // Kurangi stok hanya kalau belum dikurangi (cek flag stok_dipotong)
            if (empty($order['stok_dipotong']) || $order['stok_dipotong']==='f' || $order['stok_dipotong']===false) {
                $its = db_all("SELECT jajanan_id, qty FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$order['id']]);
                foreach ($its as $it) {
                    if ($it['jajanan_id']) db_exec("UPDATE jajanan SET stok=GREATEST(0,stok-$1) WHERE id=$2",[(int)$it['qty'],(int)$it['jajanan_id']]);
                }
                db_exec("UPDATE jajanan_pesanan SET stok_dipotong=true WHERE id=$1",[(int)$order['id']]);
            }
            echo json_encode(['ok'=>true,'status'=>'paid']);
        } elseif (in_array($ts, ['pending'], true)) {
            echo json_encode(['ok'=>true,'status'=>'pending']);
        } else {
            db_exec("UPDATE jajanan_pesanan SET payment_status=$1, status='dibatalkan', updated_at=now() WHERE id=$2",[$ts?:'failed',(int)$order['id']]);
            echo json_encode(['ok'=>true,'status'=>'failed','raw'=>$ts]);
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ============================================================
 * Non-AJAX POST: cek status
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'cek_status') {
        $nm = trim($_POST['cek_nama'] ?? '');
        header('Location: /jajanan.php?cek_nama='.urlencode($nm).'#cek-status'); exit;
    }
}

/* ============================================================
 * Listing produk: kategori + pencarian + pagination
 * ============================================================ */
$katAll = db_all("SELECT COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') AS kat, COUNT(*) AS n
                  FROM jajanan WHERE aktif=true AND stok>0
                  GROUP BY 1 ORDER BY 1");
$katPilih = trim($_GET['kat'] ?? '');
$qSearch  = trim($_GET['q'] ?? '');

$page = max(1,(int)($_GET['page'] ?? 1));
$where = "WHERE aktif=true AND stok>0";
$params = []; $i = 0;
if ($katPilih !== '' && $katPilih !== 'Semua') { $i++; $where .= " AND COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') = \$$i"; $params[] = $katPilih; }
if ($qSearch !== '')                          { $i++; $where .= " AND (LOWER(nama) LIKE LOWER(\$$i) OR LOWER(COALESCE(deskripsi,'')) LIKE LOWER(\$$i))"; $params[] = '%'.$qSearch.'%'; }

$totalProduk = (int) db_val("SELECT COUNT(*) FROM jajanan $where", $params);
$totalPage   = max(1,(int)ceil($totalProduk / $PER_PAGE));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page-1) * $PER_PAGE;
$rows = db_all("SELECT * FROM jajanan $where ORDER BY kategori NULLS LAST, nama
                LIMIT $PER_PAGE OFFSET $offset", $params);

$cekNama = trim($_GET['cek_nama'] ?? '');
$cekHasil = $cekNama !== ''
    ? db_all("SELECT id,kode,nama_pemesan,no_wa,total,status,payment_status,created_at,updated_at
              FROM jajanan_pesanan
              WHERE LOWER(nama_pemesan) LIKE LOWER($1) AND status<>'pending_payment'
              ORDER BY created_at DESC LIMIT 20", ['%'.$cekNama.'%'])
    : [];

include __DIR__.'/includes/header.php';
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<?php
/* ============================================================
 * Tampilan "Pemesanan Berhasil" — dipanggil dari callbacks.finish Midtrans
 * URL: /jajanan.php?berhasil=KODE
 * Halaman ini akan polling status ke server (confirm_payment) sampai paid.
 * ============================================================ */
$berhasilKode = trim($_GET['berhasil'] ?? '');
if ($berhasilKode !== ''):
    $bOrder = db_one("SELECT kode,nama_pemesan,total,payment_status,status FROM jajanan_pesanan WHERE kode=$1",[$berhasilKode]);
?>
<div class="card border-success mb-3" id="bayarBerhasil">
  <div class="card-header bg-success text-white">
    <i class="bi bi-check-circle-fill"></i> Pemesanan Berhasil
  </div>
  <div class="card-body">
    <?php if (!$bOrder): ?>
      <div class="alert alert-warning mb-0 small">Kode pesanan <strong><?= htmlspecialchars($berhasilKode) ?></strong> tidak ditemukan.</div>
    <?php else: ?>
      <div class="mb-2">
        <div class="small text-muted">Kode Pesanan</div>
        <div class="h5 mb-0"><strong><?= htmlspecialchars($bOrder['kode']) ?></strong></div>
      </div>
      <div class="row g-2 small">
        <div class="col-6">Nama: <strong><?= htmlspecialchars($bOrder['nama_pemesan']) ?></strong></div>
        <div class="col-6">Total: <strong>Rp <?= number_format((int)$bOrder['total'],0,',','.') ?></strong></div>
        <div class="col-6">Pembayaran: <span id="bp_pay" class="badge bg-<?= $bOrder['payment_status']==='paid'?'success':'warning text-dark' ?>"><?= htmlspecialchars($bOrder['payment_status']) ?></span></div>
        <div class="col-6">Status: <span id="bp_st" class="badge bg-info"><?= htmlspecialchars($bOrder['status']) ?></span></div>
      </div>
      <div class="alert alert-info small mt-3 mb-2" id="bp_msg">
        <span class="spinner-border spinner-border-sm me-1"></span>
        Mengkonfirmasi pembayaran ke Midtrans…
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="/jajanan.php?cek_nama=<?= urlencode($bOrder['nama_pemesan']) ?>#cek-status" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-check"></i> Lihat Pesanan Saya</a>
        <a href="/jajanan.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
      <script>
      (function(){
        var kode = <?= json_encode($bOrder['kode']) ?>;
        var tries = 0, MAX = 8;
        function poll(){
          tries++;
          var fd = new FormData();
          fd.append('csrf', <?= json_encode(csrf_token()) ?>);
          fd.append('kode', kode);
          fetch('/jajanan.php?ajax=confirm_payment',{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(j){
              if (!j || !j.ok) { return finish('failed'); }
              if (j.status==='paid')    return finish('paid');
              if (j.status==='failed')  return finish('failed');
              if (tries < MAX) setTimeout(poll, 2000); else finish('pending');
            }).catch(function(){ if (tries<MAX) setTimeout(poll,2500); else finish('pending'); });
        }
        function finish(s){
          var msg = document.getElementById('bp_msg');
          var pay = document.getElementById('bp_pay');
          var st  = document.getElementById('bp_st');
          if (s==='paid'){
            msg.className='alert alert-success small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-check-circle-fill"></i> Pembayaran berhasil dikonfirmasi. Pesanan kamu sedang diproses penjual.';
            pay.className='badge bg-success'; pay.textContent='paid';
            st.className='badge bg-info'; st.textContent='baru';
          } else if (s==='failed'){
            msg.className='alert alert-danger small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-x-circle-fill"></i> Pembayaran gagal / dibatalkan. Silakan pesan ulang.';
          } else {
            msg.className='alert alert-warning small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-hourglass-split"></i> Pembayaran masih tertunda. Selesaikan pembayaran di Midtrans, lalu cek "Pesanan Saya".';
          }
        }
        poll();
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="p-3 mb-3 rounded-3 text-white" style="background:linear-gradient(135deg,#22c55e,#0ea5e9);">
  <h1 class="h4 mb-1 text-white"><i class="bi bi-bag-heart"></i> Pesan Jajan — Antar ke Rumah</h1>
  <p class="mb-0 small opacity-90">Pesan per produk seperti Gojek. Pembayaran online via Midtrans (transfer/VA/QRIS/e-wallet).</p>
</div>

<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle-fill"></i>
  Jarak rekomendasi pengantaran maks ±<?= $UIN_R_REKOM_KM ?> km, batas layanan <?= $UIN_R_MAX_KM ?> km dari
  <strong>UIN SGD Bandung</strong>. Ongkir Rp <?= number_format($ONGKIR_BASE,0,',','.') ?> + Rp <?= number_format($ONGKIR_PER_KM,0,',','.') ?>/km.
</div>

<!-- Cek status pesanan -->
<div class="card mb-3" id="cek-status">
  <div class="card-header bg-light"><i class="bi bi-search"></i> Cek Status Pesanan Saya</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="cek_status">
      <div class="col-md-8"><label class="small">Nama Pemesan</label>
        <input class="form-control form-control-sm" name="cek_nama" required value="<?= htmlspecialchars($cekNama) ?>"></div>
      <div class="col-md-4"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Cek Pesanan</button></div>
    </form>
    <?php if ($cekNama !== ''): ?>
      <hr>
      <?php if (!$cekHasil): ?>
        <div class="small text-muted">Tidak ditemukan pesanan atas nama <strong><?= htmlspecialchars($cekNama) ?></strong>.</div>
      <?php else: ?>
        <div class="table-responsive"><table class="table table-sm small align-middle mb-0">
          <thead><tr><th>Kode</th><th>Nama</th><th>Total</th><th>Bayar</th><th>Status</th><th>Tgl</th></tr></thead>
          <tbody>
          <?php foreach($cekHasil as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['kode']) ?></strong></td>
              <td><?= htmlspecialchars($r['nama_pemesan']) ?></td>
              <td>Rp <?= number_format((int)$r['total'],0,',','.') ?></td>
              <td><span class="badge bg-<?= ($r['payment_status']??'')==='paid'?'success':'secondary' ?>"><?= htmlspecialchars($r['payment_status']??'-') ?></span></td>
              <td><span class="badge bg-info"><?= htmlspecialchars($r['status']) ?></span></td>
              <td><?= htmlspecialchars(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Pencarian + Kategori -->
<form method="get" class="row g-2 mb-2" id="filterForm">
  <div class="col-md-6">
    <div class="input-group input-group-sm">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" type="search" name="q" value="<?= htmlspecialchars($qSearch) ?>" placeholder="Cari makanan, minuman, snack...">
      <?php if ($katPilih!==''): ?><input type="hidden" name="kat" value="<?= htmlspecialchars($katPilih) ?>"><?php endif; ?>
      <button class="btn btn-success">Cari</button>
      <?php if ($qSearch!==''): ?><a class="btn btn-outline-secondary" href="/jajanan.php<?= $katPilih!==''?'?kat='.urlencode($katPilih):'' ?>">Reset</a><?php endif; ?>
    </div>
  </div>
</form>

<?php if ($katAll): ?>
<div class="mb-2 d-flex flex-wrap gap-1 align-items-center">
  <span class="small text-muted me-1"><i class="bi bi-tags"></i> Kategori:</span>
  <a href="/jajanan.php<?= $qSearch!==''?'?q='.urlencode($qSearch):'' ?>" class="btn btn-sm <?= $katPilih===''?'btn-success':'btn-outline-success' ?>">Semua</a>
  <?php foreach($katAll as $k):
      $qs = ['kat'=>$k['kat']]; if($qSearch!=='') $qs['q']=$qSearch;
  ?>
    <a href="/jajanan.php?<?= http_build_query($qs) ?>"
       class="btn btn-sm <?= $katPilih===$k['kat']?'btn-success':'btn-outline-success' ?>">
       <?= htmlspecialchars($k['kat']) ?> <span class="badge bg-light text-success ms-1"><?= (int)$k['n'] ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Grid produk -->
<div class="row g-2">
<?php foreach($rows as $r):
    $waText = "Halo Admin Firdam, saya mau tanya apakah pedagang buka untuk jajanan: *".$r['nama']."* (Rp ".number_format((int)$r['harga'],0,',','.').").";
    $waLink = 'https://wa.me/'.preg_replace('/\D+/','',$ADMIN_WA_FIRDAM).'?text='.rawurlencode($waText);
    $stokR = (int)$r['stok'];
?>
  <div class="col-md-4 col-sm-6 col-12">
    <div class="card h-100 shadow-sm position-relative">
      <?php if(!empty($r['foto_url'])): ?>
        <img src="<?= htmlspecialchars($r['foto_url']) ?>" class="card-img-top" style="height:140px;object-fit:cover" alt="">
      <?php else: ?>
        <div class="bg-light text-center py-4"><i class="bi bi-bag fs-1 text-muted"></i></div>
      <?php endif; ?>
      <div class="card-body p-2 d-flex flex-column">
        <?php if(!empty($r['kategori'])): ?>
          <span class="badge bg-success-subtle text-success mb-1 align-self-start"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($r['kategori']) ?></span>
        <?php endif; ?>
        <div class="fw-semibold small"><?= htmlspecialchars($r['nama']) ?></div>
        <div class="text-success small fw-bold mb-1">Rp <?= number_format((int)$r['harga'],0,',','.') ?></div>
        <?php if(!empty($r['deskripsi'])): ?><div class="text-muted mb-2" style="font-size:.72rem"><?= htmlspecialchars($r['deskripsi']) ?></div><?php endif; ?>

        <!-- Gojek-style counter qty per produk -->
        <div class="qty-counter d-flex align-items-center justify-content-between mb-2"
             data-id="<?= (int)$r['id'] ?>" data-stok="<?= $stokR ?>">
          <span class="small text-muted">Jumlah</span>
          <div class="input-group input-group-sm" style="max-width:130px">
            <button type="button" class="btn btn-outline-success qc-minus" aria-label="Kurangi">−</button>
            <input type="number" class="form-control text-center qc-input"
                   value="1" min="1" max="<?= $stokR ?>" inputmode="numeric">
            <button type="button" class="btn btn-outline-success qc-plus" aria-label="Tambah">+</button>
          </div>
        </div>

        <div class="mt-auto d-grid gap-1">
          <button type="button" class="btn btn-sm btn-success btn-pesan"
                  data-id="<?= (int)$r['id'] ?>"
                  data-nama="<?= htmlspecialchars($r['nama']) ?>"
                  data-harga="<?= (int)$r['harga'] ?>"
                  data-stok="<?= $stokR ?>"
                  data-foto="<?= htmlspecialchars($r['foto_url'] ?? '') ?>">
            <i class="bi bi-send-check"></i> Pesan Sekarang
          </button>
          <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?= htmlspecialchars($waLink) ?>">
            <i class="bi bi-whatsapp"></i> Tanyakan apakah pedagang buka?
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if(!$rows): ?><p class="text-muted small mt-2">Tidak ada produk yang cocok dengan filter ini.</p><?php endif; ?>

<?php if ($totalPage > 1):
    $qs = function($p) use ($katPilih,$qSearch) {
        $a = ['page'=>$p];
        if ($katPilih!=='') $a['kat']=$katPilih;
        if ($qSearch!=='')  $a['q']=$qSearch;
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
<?php endif; ?>

<!-- ===== Modal Pemesanan (Gojek-style) ===== -->
<style>
/* Pastikan modal body bisa scroll di HP agar tombol submit terlihat */
#pesanModal .modal-body {
  overflow-y: auto !important;
  max-height: calc(100vh - 150px);
}
@media (max-width: 576px) {
  #pesanModal .modal-body {
    max-height: calc(100vh - 130px);
  }
}
.modal-fullscreen-sm-down .modal-content {
  max-height: 100vh !important;
}
</style>
<div class="modal fade" id="pesanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-send-check"></i> Pemesanan Jajan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="pesanForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="jajanan_id" id="mod_jid">
        <div class="modal-body">
          <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded">
            <img id="mod_foto" src="" alt="" class="rounded" style="width:54px;height:54px;object-fit:cover;display:none">
            <div class="flex-grow-1">
              <div class="fw-semibold small" id="mod_nama">-</div>
              <div class="text-success small fw-bold" id="mod_harga">-</div>
              <input type="hidden" id="mod_stok" value="0">
            </div>
          </div>

          <label class="small">Jumlah</label>
          <div class="input-group input-group-sm mb-2" style="max-width:180px">
            <button type="button" class="btn btn-outline-secondary" id="qtyMinus">−</button>
            <input type="number" min="1" value="1" class="form-control text-center" name="qty" id="mod_qty">
            <button type="button" class="btn btn-outline-secondary" id="qtyPlus">+</button>
          </div>

          <label class="small">Nama Pemesan</label>
          <input class="form-control form-control-sm mb-2" name="nama" required value="<?= htmlspecialchars($u['nama'] ?? '') ?>">

          <label class="small">Nomor WhatsApp</label>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text">+62</span>
            <input class="form-control" name="no_wa" id="mod_wa" required inputmode="numeric"
                   placeholder="81234567890"
                   value="<?= htmlspecialchars(preg_replace('/^(\+?62|0)/','', $u['nomor_wa'] ?? '')) ?>">
          </div>
          <div class="form-text small mb-2">Tanpa angka 0 di depan. Contoh: <strong>81234567890</strong>.</div>

          <label class="small">Alamat Lengkap Pengantaran</label>
          <textarea class="form-control form-control-sm mb-2" name="alamat" rows="2" required></textarea>

          <label class="small">Catatan (opsional)</label>
          <input class="form-control form-control-sm mb-2" name="catatan" placeholder="cth: gerbang biru, pagar besi">

          <div class="border rounded p-2 bg-light-subtle mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <button type="button" id="btnDetectLoc" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-geo-alt-fill"></i> Deteksi Lokasi Saya
              </button>
              <span id="locCoords" class="small text-muted">Lat/Lng belum terdeteksi</span>
              <input type="hidden" name="pickup_lat" id="pickup_lat">
              <input type="hidden" name="pickup_lng" id="pickup_lng">
            </div>
            <div id="locWarn" class="alert alert-danger small mt-2 mb-0 d-none">
              Lokasi di luar jangkauan layanan (>&nbsp;<?= $UIN_R_MAX_KM ?>&nbsp;km).
            </div>
          </div>

          <div class="p-2 bg-light rounded">
            <div class="d-flex justify-content-between small"><span>Subtotal</span><strong id="sumSub">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Ongkir <span id="sumOngkirNote" class="text-muted">(flat)</span></span><strong id="sumOngkir">Rp <?= number_format($ONGKIR_FALLBACK,0,',','.') ?></strong></div>
            <hr class="my-1">
            <div class="d-flex justify-content-between"><span class="fw-semibold">Total Bayar</span><strong class="text-success" id="sumTot">Rp 0</strong></div>
          </div>

          <div class="alert alert-warning small mt-2 mb-0">
            <i class="bi bi-credit-card-2-front-fill"></i>
            Pembayaran <strong>Transfer/VA/QRIS/E-Wallet via Midtrans</strong>. Pesanan baru masuk setelah pembayaran berhasil.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm" id="btnBayar"><i class="bi bi-credit-card-2-front"></i> Bayar via Midtrans</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($MT_CLIENT_KEY !== ''): ?>
<script src="<?= $MT_SNAP_JS ?>" data-client-key="<?= htmlspecialchars($MT_CLIENT_KEY) ?>"></script>
<?php else: ?>
<script>console.warn('MIDTRANS_CLIENT_KEY belum diset — Snap popup tidak akan muncul.');</script>
<?php endif; ?>

<script>
/* Tunggu DOMContentLoaded supaya bootstrap.bundle.min.js (di-load belakangan oleh
   footer.php) sudah pasti tersedia. Sebelumnya `new bootstrap.Modal(...)` jalan
   inline -> ReferenceError -> seluruh IIFE crash -> tombol "Pesan Sekarang" &
   counter (+/-) tidak ter-bind. */
document.addEventListener('DOMContentLoaded', function(){
  var UIN = {lat: <?= $UIN_LAT ?>, lng: <?= $UIN_LNG ?>, rekom_km: <?= $UIN_R_REKOM_KM ?>, max_km: <?= $UIN_R_MAX_KM ?>};
  var ONGKIR_BASE = <?= (int)$ONGKIR_BASE ?>, ONGKIR_PER_KM = <?= (int)$ONGKIR_PER_KM ?>, ONGKIR_FALLBACK = <?= (int)$ONGKIR_FALLBACK ?>;
  var currentDistKm = null, locValid = null;
  var current = {harga:0, stok:0};

  function fmtRp(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID'); }
  function haversine(a,b){var R=6371000,toRad=Math.PI/180,dLat=(b.lat-a.lat)*toRad,dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2+Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));}
  function calcOngkir(){return currentDistKm!==null ? Math.round(ONGKIR_BASE+currentDistKm*ONGKIR_PER_KM) : ONGKIR_FALLBACK;}
  function recalc(){
    var q = Math.max(1, parseInt(document.getElementById('mod_qty').value||'1',10));
    if (current.stok && q>current.stok) { q=current.stok; document.getElementById('mod_qty').value=q; }
    var sub = q*current.harga, ong=calcOngkir();
    document.getElementById('sumSub').textContent=fmtRp(sub);
    document.getElementById('sumOngkir').textContent=fmtRp(ong);
    document.getElementById('sumTot').textContent=fmtRp(sub+ong);
    document.getElementById('sumOngkirNote').textContent = currentDistKm!==null
        ? '('+currentDistKm.toFixed(2)+' km)' : '(flat — share lokasi untuk akurat)';
  }

  var modalEl = document.getElementById('pesanModal');
  var modal   = (typeof bootstrap !== 'undefined' && modalEl) ? new bootstrap.Modal(modalEl) : null;
  function showModal(){
    if (!modal && typeof bootstrap !== 'undefined' && modalEl) modal = new bootstrap.Modal(modalEl);
    if (modal) modal.show();
    else if (modalEl) { modalEl.classList.add('show'); modalEl.style.display='block'; document.body.classList.add('modal-open'); }
  }

  // ====== Gojek-style: counter qty per produk di kartu ======
  document.querySelectorAll('.qty-counter').forEach(function(box){
    var stok  = parseInt(box.dataset.stok || '0', 10);
    var input = box.querySelector('.qc-input');
    var minus = box.querySelector('.qc-minus');
    var plus  = box.querySelector('.qc-plus');
    function clamp(){
      var v = parseInt(input.value || '1', 10); if (isNaN(v) || v<1) v=1;
      if (stok>0 && v>stok) v=stok;
      input.value = v;
      minus.disabled = (v<=1);
      plus.disabled  = (stok>0 && v>=stok);
    }
    minus.addEventListener('click', function(){ input.value = Math.max(1, (parseInt(input.value||'1',10)-1)); clamp(); });
    plus .addEventListener('click', function(){ input.value = (parseInt(input.value||'1',10)+1); clamp(); });
    input.addEventListener('input', clamp);
    clamp();
  });

  document.querySelectorAll('.btn-pesan').forEach(function(b){
    b.addEventListener('click', function(){
      current.harga = parseInt(b.dataset.harga||'0',10);
      current.stok  = parseInt(b.dataset.stok||'0',10);
      document.getElementById('mod_jid').value   = b.dataset.id;
      document.getElementById('mod_nama').textContent  = b.dataset.nama;
      document.getElementById('mod_harga').textContent = fmtRp(current.harga);
      document.getElementById('mod_stok').value = current.stok;
      var foto = b.dataset.foto, fimg = document.getElementById('mod_foto');
      if (foto) { fimg.src = foto; fimg.style.display=''; } else { fimg.style.display='none'; }
      // Ambil qty dari counter kartu (ala Gojek)
      var card = b.closest('.card');
      var qcInp = card ? card.querySelector('.qc-input') : null;
      var qtyFromCard = qcInp ? Math.max(1, parseInt(qcInp.value||'1',10)) : 1;
      if (current.stok && qtyFromCard > current.stok) qtyFromCard = current.stok;
      document.getElementById('mod_qty').value = qtyFromCard;
      document.getElementById('mod_qty').max = current.stok;
      currentDistKm=null; locValid=null;
      document.getElementById('pickup_lat').value=''; document.getElementById('pickup_lng').value='';
      document.getElementById('locCoords').textContent='Lat/Lng belum terdeteksi';
      document.getElementById('locWarn').classList.add('d-none');
      recalc(); showModal();
    });
  });

  document.getElementById('qtyMinus').addEventListener('click',function(){
    var i=document.getElementById('mod_qty'); i.value=Math.max(1,parseInt(i.value||'1',10)-1); recalc();
  });
  document.getElementById('qtyPlus').addEventListener('click',function(){
    var i=document.getElementById('mod_qty'); var v=parseInt(i.value||'1',10)+1;
    if (current.stok && v>current.stok) v=current.stok; i.value=v; recalc();
  });
  document.getElementById('mod_qty').addEventListener('input',recalc);

  // Normalisasi WA: hapus 0 / +62 / 62 di depan
  var waInp = document.getElementById('mod_wa');
  waInp.addEventListener('input', function(){
    var v = (this.value||'').replace(/\D+/g,'');
    v = v.replace(/^(62|0)+/, '');
    this.value = v;
  });

  document.getElementById('btnDetectLoc').addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var btn=this, orig=btn.innerHTML; btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude, lng=pos.coords.longitude;
      document.getElementById('pickup_lat').value=lat.toFixed(6);
      document.getElementById('pickup_lng').value=lng.toFixed(6);
      currentDistKm = haversine({lat:lat,lng:lng}, UIN)/1000;
      document.getElementById('locCoords').innerHTML =
        'Lat '+lat.toFixed(5)+' · Lng '+lng.toFixed(5)+' · <strong>'+currentDistKm.toFixed(2)+' km</strong> ke UIN';
      var warn=document.getElementById('locWarn');
      if (currentDistKm>UIN.max_km){warn.classList.remove('d-none'); locValid=false;}
      else {warn.classList.add('d-none'); locValid=true;}
      recalc(); btn.disabled=false; btn.innerHTML=orig;
    }, function(err){ alert('Gagal lokasi: '+err.message); btn.disabled=false; btn.innerHTML=orig; },
    {enableHighAccuracy:true, timeout:15000});
  });

  document.getElementById('pesanForm').addEventListener('submit', function(e){
    e.preventDefault();
    if (locValid===false) { alert('Lokasi di luar jangkauan.'); return; }
    var btn = document.getElementById('btnBayar');
    btn.disabled=true; var orig=btn.innerHTML;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Memproses…';

    var fd = new FormData(this);
    fetch('/jajanan.php?ajax=create_snap', {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){
        btn.disabled=false; btn.innerHTML=orig;
        if (!j.ok) { alert(j.error||'Gagal membuat transaksi'); return; }
        if (typeof window.snap === 'undefined') {
          if (j.redirect) { window.location.href = j.redirect; return; }
          alert('Snap.js belum dimuat. Set MIDTRANS_CLIENT_KEY.'); return;
        }
        window.snap.pay(j.token, {
          onSuccess: function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onPending: function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onError:   function(){ alert('Pembayaran gagal.'); window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onClose:   function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); }
        });
      })
      .catch(function(){ btn.disabled=false; btn.innerHTML=orig; alert('Koneksi gagal'); });
  });

  function confirmPayment(kode){
    var fd = new FormData(); fd.append('csrf','<?= csrf_token() ?>'); fd.append('kode', kode);
    fetch('/jajanan.php?ajax=confirm_payment', {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){
        if (j.ok && j.status==='paid') {
          window.location.href = '/jajanan.php?cek_nama='+encodeURIComponent(document.querySelector('#pesanForm [name=nama]').value);
        } else if (j.ok && j.status==='pending') {
          alert('Pembayaran tertunda. Selesaikan pembayaran lalu cek status pesanan.');
          window.location.href = '/jajanan.php?cek_nama='+encodeURIComponent(document.querySelector('#pesanForm [name=nama]').value);
        } else {
          alert('Pembayaran belum berhasil.');
        }
      });
  }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

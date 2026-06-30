<?php
/**
 * paket_upgrade.php — Revisi R21 (1 Juli 2026)
 *
 * Halaman pemilihan & pembayaran paket member.
 *
 * Alur:
 *   1. User datang dari banner "Lihat Paket & Upgrade" (?need=pro / komunitas).
 *   2. Memilih salah satu paket → muncul ringkasan + tombol "Bayar via Midtrans".
 *   3. Tombol bayar memanggil endpoint AJAX (ajax=create_snap) yang membuat
 *      Snap token Midtrans, lalu Snap.js dibuka inline (popup).
 *   4. Setelah pembayaran sukses (callback Snap onSuccess / settlement), endpoint
 *      AJAX (ajax=confirm_payment) memverifikasi status ke Midtrans, lalu
 *      meng-UPDATE users.paket otomatis — TANPA perlu admin mengubah manual.
 *
 * Catatan SQL:
 *   - Tabel baru: paket_pesanan (lihat migrations_r21_1jul2026.sql).
 *   - Migrasi idempotent juga dijalankan dari halaman ini sebagai safety net.
 */

require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/app_settings.php';
require __DIR__.'/includes/paket_helpers.php';
send_security_headers(); require_login();

$u = current_user();
if (!$u) { header('Location: /login.php'); exit; }

/* ---------- Idempotent: pastikan tabel paket_pesanan ada ---------- */
try {
    db_exec("CREATE TABLE IF NOT EXISTS paket_pesanan (
        id              BIGSERIAL PRIMARY KEY,
        kode            VARCHAR(40) UNIQUE NOT NULL,
        user_id         BIGINT NOT NULL,
        paket           VARCHAR(20) NOT NULL,
        harga           INTEGER NOT NULL,
        status          VARCHAR(20) NOT NULL DEFAULT 'pending',
        snap_token      TEXT,
        snap_redirect   TEXT,
        midtrans_status VARCHAR(40),
        midtrans_raw    TEXT,
        created_at      TIMESTAMP NOT NULL DEFAULT now(),
        paid_at         TIMESTAMP NULL
    )");
    db_exec("CREATE INDEX IF NOT EXISTS paket_pesanan_user_idx ON paket_pesanan(user_id, created_at DESC)");
} catch (Throwable $e) {}

/* ---------- Midtrans config ---------- */
$MT_SERVER_KEY = getenv('MIDTRANS_SERVER_KEY') ?: '';
$MT_CLIENT_KEY = getenv('MIDTRANS_CLIENT_KEY') ?: '';
$MT_PROD       = (bool) (getenv('MIDTRANS_PROD') ?: false);
$MT_BASE       = $MT_PROD ? 'https://app.midtrans.com'      : 'https://app.sandbox.midtrans.com';
$MT_API_BASE   = $MT_PROD ? 'https://api.midtrans.com'      : 'https://api.sandbox.midtrans.com';
$MT_SNAP_JS    = $MT_PROD ? 'https://app.midtrans.com/snap/snap.js'
                          : 'https://app.sandbox.midtrans.com/snap/snap.js';

$PRICES   = paket_prices();
$curPaket = paket_user($u);

/* ---------- Helpers ---------- */
function pu_snap_request(array $payload, string $serverKey, string $base): array {
    $ch = curl_init(rtrim($base,'/').'/snap/v1/transactions');
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode($resp, true);
    if ($code >= 400 || empty($j['token'])) {
        throw new RuntimeException('Midtrans error: '.($j['error_messages'][0] ?? $resp));
    }
    return $j;
}
function pu_status_request(string $orderId, string $serverKey, string $apiBase): array {
    $ch = curl_init(rtrim($apiBase,'/').'/v2/'.rawurlencode($orderId).'/status');
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

/* ---------- AJAX endpoints ---------- */
$ajax = $_GET['ajax'] ?? '';

if ($ajax === 'create_snap' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $pilih = strtolower(trim($_POST['paket'] ?? ''));
        if (!in_array($pilih, ['pro','komunitas'], true)) {
            throw new RuntimeException('Pilihan paket tidak valid.');
        }
        if ($curPaket === 'komunitas') {
            throw new RuntimeException('Akun Anda sudah berpaket Komunitas.');
        }
        if ($curPaket === 'pro' && $pilih === 'pro') {
            throw new RuntimeException('Akun Anda sudah berpaket PRO. Anda dapat upgrade ke Komunitas.');
        }
        if ($MT_SERVER_KEY === '') {
            throw new RuntimeException('Midtrans belum dikonfigurasi (MIDTRANS_SERVER_KEY kosong).');
        }
        $harga = (int) ($PRICES[$pilih] ?? 0);
        if ($harga <= 0) throw new RuntimeException('Harga paket tidak valid.');

        $kode = 'PKT-'.strtoupper($pilih[0]).'-'.date('ymdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        db_exec("INSERT INTO paket_pesanan(kode,user_id,paket,harga,status) VALUES($1,$2,$3,$4,'pending')",
            [$kode, (int)$u['id'], $pilih, $harga]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $finish = $scheme.'://'.$host.'/paket_upgrade.php?berhasil='.urlencode($kode);

        $payload = [
            'transaction_details' => ['order_id' => $kode, 'gross_amount' => $harga],
            'item_details'        => [[
                'id'       => 'PAKET-'.strtoupper($pilih),
                'price'    => $harga,
                'quantity' => 1,
                'name'     => 'Paket '.strtoupper($pilih).' KawanKeringat (1 bulan)',
            ]],
            'customer_details'    => [
                'first_name' => substr((string)($u['nama'] ?? 'User'), 0, 60),
                'email'      => (string)($u['email'] ?? ''),
                'phone'      => (string)($u['nomor_wa'] ?? ''),
            ],
            'enabled_payments'    => ['bca_va','bni_va','bri_va','mandiri_va','permata_va','gopay','shopeepay','qris','other_va','bank_transfer'],
            'callbacks'           => ['finish' => $finish],
        ];
        $resp = pu_snap_request($payload, $MT_SERVER_KEY, $MT_BASE);
        db_exec("UPDATE paket_pesanan SET snap_token=$1, snap_redirect=$2 WHERE kode=$3",
            [$resp['token'], $resp['redirect_url'] ?? null, $kode]);

        echo json_encode(['ok'=>true,'token'=>$resp['token'],'kode'=>$kode,'redirect'=>$resp['redirect_url'] ?? null]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($ajax === 'confirm_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $kode  = trim($_POST['kode'] ?? '');
        $order = $kode ? db_one("SELECT * FROM paket_pesanan WHERE kode=$1 AND user_id=$2",
                                [$kode, (int)$u['id']]) : null;
        if (!$order) throw new RuntimeException('Pesanan paket tidak ditemukan.');
        if ($MT_SERVER_KEY === '') throw new RuntimeException('Midtrans belum dikonfigurasi.');

        $status = pu_status_request($kode, $MT_SERVER_KEY, $MT_API_BASE);
        $ts     = $status['transaction_status'] ?? '';
        $fraud  = $status['fraud_status'] ?? 'accept';
        $paid   = in_array($ts, ['capture','settlement'], true) && $fraud === 'accept';

        db_exec("UPDATE paket_pesanan
                 SET midtrans_status=$1, midtrans_raw=$2,
                     status=CASE WHEN $3 THEN 'paid' ELSE status END,
                     paid_at=CASE WHEN $3 THEN now() ELSE paid_at END
                 WHERE id=$4",
            [$ts, json_encode($status, JSON_UNESCAPED_UNICODE), $paid, (int)$order['id']]);

        if ($paid) {
            /* Auto-upgrade paket user — TANPA perlu admin */
            db_exec("UPDATE users SET paket=$1 WHERE id=$2",
                [$order['paket'], (int)$order['user_id']]);
        }
        echo json_encode(['ok'=>true,'paid'=>$paid,'status'=>$ts]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---------- Auto-confirm bila datang dari finish redirect ---------- */
$justPaidKode = trim($_GET['berhasil'] ?? '');
if ($justPaidKode !== '' && $MT_SERVER_KEY !== '') {
    try {
        $order = db_one("SELECT * FROM paket_pesanan WHERE kode=$1 AND user_id=$2", [$justPaidKode, (int)$u['id']]);
        if ($order && $order['status'] !== 'paid') {
            $st = pu_status_request($justPaidKode, $MT_SERVER_KEY, $MT_API_BASE);
            $ts = $st['transaction_status'] ?? '';
            $fr = $st['fraud_status'] ?? 'accept';
            if (in_array($ts, ['capture','settlement'], true) && $fr === 'accept') {
                db_exec("UPDATE paket_pesanan SET status='paid', midtrans_status=$1,
                         midtrans_raw=$2, paid_at=now() WHERE id=$3",
                    [$ts, json_encode($st, JSON_UNESCAPED_UNICODE), (int)$order['id']]);
                db_exec("UPDATE users SET paket=$1 WHERE id=$2",
                    [$order['paket'], (int)$order['user_id']]);
                $curPaket = $order['paket'];
                $_SESSION['flash'] = 'Pembayaran berhasil. Paket Anda kini: '.strtoupper($curPaket).'.';
            }
        }
    } catch (Throwable $e) {}
}

$pageTitle = 'Upgrade Paket Member';
$pageSkeleton = 'feed';
include __DIR__.'/includes/header.php';

$need    = strtolower(trim($_GET['need'] ?? ''));
$default = in_array($need, ['pro','komunitas'], true) ? $need : 'pro';

/* History pesanan terbaru milik user */
$riwayat = db_all("SELECT kode,paket,harga,status,created_at,paid_at
                   FROM paket_pesanan WHERE user_id=$1
                   ORDER BY id DESC LIMIT 10", [(int)$u['id']]);
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Upgrade Paket</li>
</ol></nav>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h3 class="mb-0"><i class="bi bi-stars text-warning"></i> Upgrade Paket Member</h3>
      <div class="small">Paket saat ini: <?= paket_badge($curPaket) ?></div>
    </div>
    <p class="text-muted small mb-0">
      Pilih paket di bawah lalu klik <strong>Bayar via Midtrans</strong>. Setelah pembayaran
      lunas, status paket Anda akan <strong>otomatis aktif</strong> — tanpa perlu menunggu admin.
    </p>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-success mt-2 mb-0 small"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3" id="paketCards">
  <?php
  $opsiList = [
    'pro' => [
      'title'   => '⭐ Paket PRO',
      'color'   => 'warning',
      'harga'   => $PRICES['pro'],
      'desc'    => 'Akses fitur premium olahraga & kesehatan.',
      'fitur'   => [
        'Kalori Mingguan + estimasi AI dari foto makanan',
        'Live Tracking / Beacon (berbagi lokasi real-time)',
        'Flyover & Run Heatmap',
        'Kalkulator Kesehatan, Jantung, Gaya Hidup',
        'Monitoring lanjutan',
      ],
    ],
    'komunitas' => [
      'title'   => '👥 Paket KOMUNITAS',
      'color'   => 'success',
      'harga'   => $PRICES['komunitas'],
      'desc'    => 'Semua fitur PRO + Hub Islami lengkap.',
      'fitur'   => [
        'Semua yang ada di paket PRO',
        'Hub Islami lengkap (Qur’an, Sholat, Doa, Hadist)',
        'Tanya Jawab Islami berbasis AI',
        'Tata Cara Wudhu &amp; Shalat (ilustrasi)',
        'Kajian Literatur Buku Islami',
      ],
    ],
  ];
  foreach ($opsiList as $key => $opt):
    $disabled = ($curPaket === 'komunitas') || ($curPaket === 'pro' && $key === 'pro');
    $isDefault = ($key === $default && !$disabled);
  ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm border-<?= $opt['color'] ?> paket-card <?= $isDefault?'selected':'' ?>"
           data-paket="<?= $key ?>" data-harga="<?= (int)$opt['harga'] ?>" style="cursor:pointer">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <h4 class="card-title mb-1 text-<?= $opt['color'] ?>-emphasis"><?= $opt['title'] ?></h4>
            <span class="badge bg-<?= $opt['color'] ?> selected-badge" style="display:<?= $isDefault?'inline-block':'none' ?>">
              <i class="bi bi-check-circle"></i> Dipilih
            </span>
          </div>
          <div class="h3 fw-bold mb-1">Rp <?= number_format($opt['harga'], 0, ',', '.') ?>
            <small class="text-muted fs-6">/ bulan</small></div>
          <p class="text-muted small mb-2"><?= $opt['desc'] ?></p>
          <ul class="small mb-3">
            <?php foreach ($opt['fitur'] as $f): ?><li><?= $f ?></li><?php endforeach; ?>
          </ul>
          <?php if ($disabled): ?>
            <button class="btn btn-outline-secondary w-100" disabled>
              <i class="bi bi-check2-all"></i> Sudah aktif
            </button>
          <?php else: ?>
            <button type="button" class="btn btn-<?= $opt['color'] ?> w-100 btn-pilih"
                    data-paket="<?= $key ?>">
              <i class="bi bi-hand-index-thumb"></i> Pilih Paket <?= strtoupper($key) ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Ringkasan + tombol bayar (muncul setelah salah satu paket dipilih) -->
<div class="card shadow-sm mb-4 d-none" id="paySummary">
  <div class="card-body">
    <h5 class="mb-2"><i class="bi bi-credit-card-2-front text-primary"></i> Ringkasan Pembayaran</h5>
    <div class="d-flex justify-content-between border-bottom py-1">
      <span>Paket dipilih</span><strong id="sumPaket">—</strong>
    </div>
    <div class="d-flex justify-content-between border-bottom py-1">
      <span>Harga</span><strong id="sumHarga">Rp 0</strong>
    </div>
    <div class="d-flex justify-content-between py-1">
      <span>Metode</span><strong>Midtrans (VA / QRIS / GoPay / ShopeePay)</strong>
    </div>
    <button type="button" id="btnBayar" class="btn btn-primary btn-lg w-100 mt-2">
      <i class="bi bi-shield-check"></i> Bayar via Midtrans
    </button>
    <div id="payMsg" class="small mt-2 text-muted">
      Setelah pembayaran berhasil, status paket Anda otomatis aktif tanpa perlu menunggu admin.
    </div>
  </div>
</div>

<?php if ($riwayat): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header"><i class="bi bi-clock-history"></i> Riwayat Pesanan Paket</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Kode</th><th>Paket</th><th>Harga</th><th>Status</th><th>Dibuat</th><th>Lunas</th></tr></thead>
      <tbody>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td class="small font-monospace"><?= htmlspecialchars($r['kode']) ?></td>
          <td><?= paket_badge($r['paket']) ?></td>
          <td>Rp <?= number_format((int)$r['harga'], 0, ',', '.') ?></td>
          <td>
            <?php $st = $r['status']; $cls = $st==='paid'?'success':($st==='pending'?'secondary':'danger'); ?>
            <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
          <td class="small"><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td class="small"><?= htmlspecialchars((string)($r['paid_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>
  .paket-card{ transition:transform .15s ease, box-shadow .15s ease, border-width .15s ease; border-width:1px; }
  .paket-card:hover{ transform:translateY(-2px); box-shadow:0 .5rem 1rem rgba(0,0,0,.08); }
  .paket-card.selected{ border-width:3px; }
</style>

<?php /* Revisi 29 Juni 2026 — Snap.js loader tahan banting:
       - Skrip async + onerror handler agar bisa diagnosa kegagalan jaringan.
       - Fallback otomatis re-inject ke URL alternatif (sandbox <-> prod) bila gagal.
       - Tombol bayar MENUNGGU Snap.js siap (sampai 8 detik) sebelum menampilkan error. */ ?>
<?php /* Revisi 30 Juni 2026 — Snap.js dimuat via <script> statis dulu (paling reliable),
   baru dilengkapi loader dinamis sebagai fallback. */ ?>
<?php if ($MT_CLIENT_KEY): ?>
<script src="<?= htmlspecialchars($MT_SNAP_JS) ?>" data-client-key="<?= htmlspecialchars($MT_CLIENT_KEY) ?>" async></script>
<?php endif; ?>
<script>
window.__MT = {
  url: <?= json_encode($MT_SNAP_JS) ?>,
  alt: <?= json_encode($MT_PROD ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js') ?>,
  key: <?= json_encode($MT_CLIENT_KEY) ?>,
  loaded: false, loading: null, failed: false
};
window.__loadSnap = function(url){
  return new Promise(function(resolve, reject){
    if (window.snap) { window.__MT.loaded = true; return resolve(true); }
    if (!window.__MT.key) { return reject(new Error('MIDTRANS_CLIENT_KEY belum di-set di server (.env).')); }
    // Hindari double-inject
    var existing = document.querySelector('script[data-mt-loader="'+url+'"]');
    if (existing) {
      existing.addEventListener('load', function(){ resolve(!!window.snap); });
      existing.addEventListener('error', function(){ reject(new Error('Gagal load: '+url)); });
      return;
    }
    var s = document.createElement('script');
    s.src = url; s.async = true;
    s.setAttribute('data-client-key', window.__MT.key);
    s.setAttribute('data-mt-loader', url);
    s.onload  = function(){ window.__MT.loaded = !!window.snap; resolve(window.__MT.loaded); };
    s.onerror = function(){ reject(new Error('Gagal load: '+url)); };
    document.head.appendChild(s);
  });
};
// Mulai loading segera bila tag statis belum berhasil
window.__MT.loading = window.__loadSnap(window.__MT.url).catch(function(){
  return window.__loadSnap(window.__MT.alt).catch(function(e){
    window.__MT.failed = true; console.warn('Snap.js gagal dimuat:', e);
  });
});
</script>
<script>
(function(){
  var csrf = <?= json_encode(csrf_token()) ?>;
  var defaultKey = <?= json_encode($default) ?>;
  var prices = <?= json_encode($PRICES) ?>;
  var labels = { pro: '⭐ PRO', komunitas: '👥 KOMUNITAS' };
  var selected = null;

  function fmtRp(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }
  function setMsg(html, cls){
    var m = document.getElementById('payMsg');
    m.className = 'small mt-2 ' + (cls || 'text-muted');
    m.innerHTML = html;
  }
  function selectPaket(key){
    if (!prices[key]) return;
    selected = key;
    document.querySelectorAll('.paket-card').forEach(function(c){
      var b = c.querySelector('.selected-badge');
      if (c.dataset.paket === key){ c.classList.add('selected'); if(b) b.style.display='inline-block'; }
      else { c.classList.remove('selected'); if(b) b.style.display='none'; }
    });
    document.getElementById('sumPaket').textContent = labels[key] || key;
    document.getElementById('sumHarga').textContent = fmtRp(prices[key]);
    document.getElementById('paySummary').classList.remove('d-none');
    document.getElementById('paySummary').scrollIntoView({behavior:'smooth', block:'nearest'});
  }

  document.querySelectorAll('.btn-pilih').forEach(function(b){
    b.addEventListener('click', function(e){ e.stopPropagation(); selectPaket(b.dataset.paket); });
  });
  document.querySelectorAll('.paket-card').forEach(function(c){
    c.addEventListener('click', function(){
      if (c.querySelector('button[disabled]')) return;
      selectPaket(c.dataset.paket);
    });
  });

  // Auto-pilih default sesuai ?need=
  if (defaultKey && prices[defaultKey]) selectPaket(defaultKey);

  function waitSnap(){
    if (window.snap) return Promise.resolve(true);
    return new Promise(function(resolve){
      var t0 = Date.now();
      var triedAlt = false;
      (function tick(){
        if (window.snap) return resolve(true);
        var elapsed = Date.now() - t0;
        if (!triedAlt && elapsed > 4000) {
          triedAlt = true;
          try { window.__loadSnap(window.__MT.alt).catch(function(){}); } catch(_) {}
        }
        if (elapsed > 12000) return resolve(false);
        setTimeout(tick, 200);
      })();
    });
  }

  document.getElementById('btnBayar').addEventListener('click', async function(){
    if (!selected) { setMsg('Silakan pilih paket terlebih dahulu.', 'text-danger'); return; }
    setMsg('Menyiapkan Snap.js Midtrans…', 'text-muted');
    var ok = await waitSnap();
    if (!ok) {
      // Coba sekali lagi via loader (jaga-jaga URL pertama gagal)
      try { await window.__loadSnap(window.__MT.alt); } catch(e){}
      ok = !!window.snap;
    }
    if (!ok) {
      var keyMissing = !window.__MT.key;
      setMsg(
        (keyMissing ? '<b>MIDTRANS_CLIENT_KEY belum di-set</b> di server (.env).<br>' : '') +
        'Snap.js Midtrans tidak dapat dimuat. Periksa koneksi internet, lalu refresh halaman.',
        'text-danger'
      );
      return;
    }
    var btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses…';
    setMsg('Menyiapkan transaksi…', 'text-muted');

    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('paket', selected);

    fetch('/paket_upgrade.php?ajax=create_snap', { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json().then(function(j){ return { ok:r.ok, j:j }; }); })
      .then(function(res){
        if (!res.ok || !res.j.ok) throw new Error(res.j.error || 'Gagal membuat transaksi.');
        var kode = res.j.kode, token = res.j.token;
        setMsg('Membuka jendela pembayaran Midtrans…', 'text-muted');
        window.snap.pay(token, {
          onSuccess: function(){ confirm(kode, 'Pembayaran berhasil! Mengaktifkan paket…'); },
          onPending: function(){ confirm(kode, 'Pembayaran tertunda. Selesaikan pembayaran untuk mengaktifkan paket.'); },
          onError:   function(){ setMsg('Pembayaran gagal / dibatalkan.', 'text-danger'); resetBtn(); },
          onClose:   function(){ confirm(kode, 'Jendela ditutup. Memeriksa status…'); }
        });
      })
      .catch(function(err){
        setMsg('Gagal: ' + err.message, 'text-danger'); resetBtn();
      });

    function resetBtn(){ btn.disabled=false; btn.innerHTML='<i class="bi bi-shield-check"></i> Bayar via Midtrans'; }

    function confirm(kode, infoMsg){
      setMsg(infoMsg, 'text-muted');
      var cf = new FormData();
      cf.append('csrf', csrf);
      cf.append('kode', kode);
      fetch('/paket_upgrade.php?ajax=confirm_payment', { method:'POST', body: cf, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
          if (j && j.ok && j.paid) {
            setMsg('Paket Anda telah aktif. Halaman akan dimuat ulang…', 'text-success');
            setTimeout(function(){ location.href = '/paket_upgrade.php?berhasil='+encodeURIComponent(kode); }, 1200);
          } else {
            setMsg('Status terakhir: <strong>' + ((j && j.status) || 'unknown') + '</strong>. Jika sudah membayar, refresh dalam beberapa detik.', 'text-warning');
            resetBtn();
          }
        })
        .catch(function(){ setMsg('Tidak dapat memverifikasi status. Coba refresh halaman.', 'text-danger'); resetBtn(); });
    }
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

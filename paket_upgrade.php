<?php
/**
 * paket_upgrade.php — Revisi Juli 2026 (bayar via WhatsApp)
 *
 * Sebelumnya: pembayaran memakai Midtrans Snap (VA/QRIS/GoPay/ShopeePay) dan
 * status paket otomatis aktif setelah settlement.
 *
 * Revisi Juli 2026:
 *   - Mekanisme pembayaran Midtrans DIHAPUS dari halaman ini.
 *   - Tombol "Pilih Paket" me-redirect user ke WhatsApp admin dengan pesan
 *     berisi seluruh data pemesanan (nama, email, WA, paket, harga, kode order).
 *   - Kode order tetap disimpan di tabel paket_pesanan dengan status='menunggu_wa'
 *     supaya admin bisa melacak & meng-approve manual dari sisi admin.
 *
 * Konfigurasi nomor WA admin: set ENV WA_ADMIN_NUMBER (format 62xxxxxxxxxx).
 * Default: 6281386369207 (sama seperti tombol Pemandu Olahraga di menu).
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

$PRICES   = paket_prices();
$curPaket = paket_user($u);

$WA_ADMIN = getenv('WA_ADMIN_NUMBER') ?: '6281386369207';
$WA_ADMIN = preg_replace('/\D+/', '', $WA_ADMIN);

/* ---------- AJAX: buat kode pesanan (pending WA) & kembalikan URL WA ---------- */
$ajax = $_GET['ajax'] ?? '';
if ($ajax === 'create_wa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $harga = (int) ($PRICES[$pilih] ?? 0);
        if ($harga <= 0) throw new RuntimeException('Harga paket tidak valid.');

        $kode = 'PKT-'.strtoupper($pilih[0]).'-'.date('ymdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        db_exec("INSERT INTO paket_pesanan(kode,user_id,paket,harga,status) VALUES($1,$2,$3,$4,'menunggu_wa')",
            [$kode, (int)$u['id'], $pilih, $harga]);

        $nama  = trim((string)($u['nama']    ?? '-'));
        $email = trim((string)($u['email']   ?? '-'));
        $wa    = trim((string)($u['nomor_wa']?? '-'));
        $hargaRp = 'Rp ' . number_format($harga, 0, ',', '.');

        $pesan  = "Halo Admin KawanKeringat 👋\n";
        $pesan .= "Saya ingin membeli paket member berikut:\n\n";
        $pesan .= "• Kode Order  : {$kode}\n";
        $pesan .= "• Paket       : ".strtoupper($pilih)." (1 bulan)\n";
        $pesan .= "• Harga       : {$hargaRp}\n\n";
        $pesan .= "Data Saya:\n";
        $pesan .= "• Nama        : {$nama}\n";
        $pesan .= "• Email       : {$email}\n";
        $pesan .= "• Nomor WA    : {$wa}\n";
        $pesan .= "• User ID     : #".(int)$u['id']."\n\n";
        $pesan .= "Mohon informasi cara pembayaran & aktivasi paket. Terima kasih 🙏";

        $waUrl = 'https://wa.me/'.$WA_ADMIN.'?text='.rawurlencode($pesan);

        echo json_encode(['ok'=>true,'kode'=>$kode,'wa_url'=>$waUrl]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
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
      Pilih paket lalu klik <strong>Bayar via WhatsApp</strong>. Anda akan diarahkan ke chat
      admin dengan data pesanan sudah terisi otomatis — cukup kirim untuk mendapat instruksi
      pembayaran &amp; aktivasi paket.
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
        'Kalkulator Kesehatan, Jantung, Gaya Hidup',
        'Artikel &amp; Cedera Olahraga',
        'Toko Perlengkapan Olahraga',
        'Paket Anak &amp; Lansia',
      ],
    ],
    'komunitas' => [
      'title'   => '👥 Paket KOMUNITAS',
      'color'   => 'success',
      'harga'   => $PRICES['komunitas'],
      'desc'    => 'Semua fitur PRO + Jogging Progress + Hub Islami.',
      'fitur'   => [
        'Semua yang ada di paket PRO',
        'Tracking Jalur, Live Tracking &amp; Flyover',
        'Direktori Tempat Komunitas',
        'Hub Islami lengkap (Qur’an, Sholat, Doa, Hadist)',
        'Tanya Jawab Islami berbasis AI',
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

<!-- Ringkasan + tombol Bayar via WhatsApp -->
<div class="card shadow-sm mb-4 d-none" id="paySummary">
  <div class="card-body">
    <h5 class="mb-2"><i class="bi bi-whatsapp text-success"></i> Ringkasan Pembayaran</h5>
    <div class="d-flex justify-content-between border-bottom py-1">
      <span>Paket dipilih</span><strong id="sumPaket">—</strong>
    </div>
    <div class="d-flex justify-content-between border-bottom py-1">
      <span>Harga</span><strong id="sumHarga">Rp 0</strong>
    </div>
    <div class="d-flex justify-content-between py-1">
      <span>Metode</span><strong>WhatsApp Admin (Manual)</strong>
    </div>
    <button type="button" id="btnBayar" class="btn btn-success btn-lg w-100 mt-2">
      <i class="bi bi-whatsapp"></i> Bayar via WhatsApp
    </button>
    <div id="payMsg" class="small mt-2 text-muted">
      Setelah tombol diklik, Anda akan diarahkan ke chat WhatsApp admin dengan data
      pesanan sudah terisi. Admin akan meng-aktifkan paket setelah pembayaran diterima.
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
            <?php
              $st = $r['status'];
              $cls = $st==='paid' ? 'success'
                   : ($st==='menunggu_wa' ? 'info'
                   : ($st==='pending' ? 'secondary' : 'danger'));
            ?>
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
  if (defaultKey && prices[defaultKey]) selectPaket(defaultKey);

  document.getElementById('btnBayar').addEventListener('click', function(){
    if (!selected) { setMsg('Silakan pilih paket terlebih dahulu.', 'text-danger'); return; }
    var btn = this; btn.disabled = true;
    var oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyiapkan pesan WhatsApp…';
    setMsg('Membuat kode order & menyusun pesan WhatsApp…', 'text-muted');

    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('paket', selected);

    fetch('/paket_upgrade.php?ajax=create_wa', { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json().then(function(j){ return { ok:r.ok, j:j }; }); })
      .then(function(res){
        if (!res.ok || !res.j.ok) throw new Error(res.j.error || 'Gagal membuat pesanan.');
        setMsg('Membuka WhatsApp untuk kode <b>'+res.j.kode+'</b> …', 'text-success');
        // Redirect ke WhatsApp
        window.location.href = res.j.wa_url;
      })
      .catch(function(err){
        setMsg('Gagal: ' + err.message, 'text-danger');
        btn.disabled = false; btn.innerHTML = oldHtml;
      });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

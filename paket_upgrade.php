<?php
/**
 * paket_upgrade.php — Revisi (KawanKeringat: AI Sport & Healthy Lifestyle Super App)
 *
 * Halaman upgrade paket member dengan tier baru:
 *   - GRATIS         (Rp 0)
 *   - KOMUNITAS      (Mahasiswa / Umum, Bulanan / Tahunan)
 *   - PRO (AI)       (Mahasiswa / Umum, Bulanan / Tahunan)
 *   - ADMIN/ORGANIZER (Bulanan / Tahunan, s.d. 500 anggota)
 *   - ENTERPRISE     (kampus/sekolah/instansi/perusahaan) — kontak admin
 *
 * Pembayaran tetap manual via WhatsApp admin. Kode order disimpan di
 * tabel paket_pesanan (status='menunggu_wa'). Admin meng-aktifkan paket
 * secara manual di panel admin.
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

$curPaket = paket_user($u);

$WA_ADMIN = getenv('WA_ADMIN_NUMBER') ?: '6281386369207';
$WA_ADMIN = preg_replace('/\D+/', '', $WA_ADMIN);

/* ---------- Katalog paket baru ---------- */
$PLANS = [
    // key => [tier, label, harga, periode]
    // Revisi: harga Komunitas & PRO ditukar — Komunitas lebih terjangkau,
    // PRO (AI) menjadi tier premium yang lebih mahal.
    'kom_mhs_bln' => ['komunitas', 'Komunitas — Mahasiswa (Bulanan)',   19900,  'bulan'],
    'kom_mhs_thn' => ['komunitas', 'Komunitas — Mahasiswa (Tahunan)',   149000, 'tahun'],
    'kom_um_bln'  => ['komunitas', 'Komunitas — Umum (Bulanan)',         39900,  'bulan'],
    'kom_um_thn'  => ['komunitas', 'Komunitas — Umum (Tahunan)',        299000, 'tahun'],
    'pro_mhs_bln' => ['pro',       'PRO AI — Mahasiswa (Bulanan)',       49900,  'bulan'],
    'pro_mhs_thn' => ['pro',       'PRO AI — Mahasiswa (Tahunan)',      399000, 'tahun'],
    'pro_um_bln'  => ['pro',       'PRO AI — Umum (Bulanan)',            79900,  'bulan'],
    'pro_um_thn'  => ['pro',       'PRO AI — Umum (Tahunan)',           699000, 'tahun'],
    'org_bln'     => ['komunitas', 'Organizer (Bulanan)',               149000,  'bulan'],
    'org_thn'     => ['komunitas', 'Organizer (Tahunan)',              1299000,  'tahun'],
];

/* ---------- AJAX: buat kode pesanan (pending WA) & kembalikan URL WA ---------- */
$ajax = $_GET['ajax'] ?? '';
if ($ajax === 'create_wa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $pilih = trim($_POST['paket'] ?? '');
        if (!isset($PLANS[$pilih])) {
            throw new RuntimeException('Pilihan paket tidak valid.');
        }
        [$tier, $label, $harga, $periode] = $PLANS[$pilih];

        $kode = 'PKT-'.strtoupper(substr($pilih,0,3)).'-'.date('ymdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        db_exec("INSERT INTO paket_pesanan(kode,user_id,paket,harga,status) VALUES($1,$2,$3,$4,'menunggu_wa')",
            [$kode, (int)$u['id'], $pilih, $harga]);

        $nama  = trim((string)($u['nama']    ?? '-'));
        $email = trim((string)($u['email']   ?? '-'));
        $wa    = trim((string)($u['nomor_wa']?? '-'));
        $hargaRp = 'Rp ' . number_format($harga, 0, ',', '.');

        $pesan  = "Halo Admin KawanKeringat 👋\n";
        $pesan .= "Saya ingin membeli paket member berikut:\n\n";
        $pesan .= "• Kode Order  : {$kode}\n";
        $pesan .= "• Paket       : {$label}\n";
        $pesan .= "• Periode     : 1 {$periode}\n";
        $pesan .= "• Harga       : {$hargaRp}\n\n";
        $pesan .= "Data Saya:\n";
        $pesan .= "• Nama        : {$nama}\n";
        $pesan .= "• Email       : {$email}\n";
        $pesan .= "• Nomor WA    : {$wa}\n";
        $pesan .= "• User ID     : #".(int)$u['id']."\n\n";
        $pesan .= "Mohon informasi cara pembayaran & aktivasi paket. Terima kasih 🙏";

        $waUrl = 'https://wa.me/'.$WA_ADMIN.'?text='.rawurlencode($pesan);

        echo json_encode(['ok'=>true,'kode'=>$kode,'wa_url'=>$waUrl,'label'=>$label,'harga'=>$harga]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ---------- URL kontak Enterprise ---------- */
$entPesan = "Halo Admin KawanKeringat 👋\nSaya ingin bertanya tentang paket ENTERPRISE (kampus/sekolah/instansi/perusahaan). Mohon informasinya. Terima kasih.";
$entUrl = 'https://wa.me/'.$WA_ADMIN.'?text='.rawurlencode($entPesan);

$pageTitle = 'Upgrade Paket Member';
$pageSkeleton = 'feed';
include __DIR__.'/includes/header.php';

/* History pesanan terbaru milik user */
$riwayat = db_all("SELECT kode,paket,harga,status,created_at,paid_at
                   FROM paket_pesanan WHERE user_id=$1
                   ORDER BY id DESC LIMIT 10", [(int)$u['id']]);
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item active">Upgrade Paket</li>
</ol></nav>

<div class="paket-hero shadow-lg mb-3">
  <div class="paket-hero-overlay"></div>
  <div class="paket-hero-body">
    <span class="paket-hero-eyebrow"><i class="bi bi-stars"></i> KawanKeringat · AI Sport &amp; Healthy Lifestyle Super App</span>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-2 mb-2">
      <h3 class="mb-0 text-white fw-bold display-6">Pilih Paket Kamu</h3>
      <div class="small text-white-50">Paket saat ini: <?= paket_badge($curPaket) ?></div>
    </div>
    <p class="text-white-50 small mb-0" style="max-width:720px">
      Olahraga, komunitas, AI, kesehatan, dan aktivitas outdoor — semua dalam satu aplikasi.
      Pilih paket lalu klik <strong class="text-white">Bayar via WhatsApp</strong>. Admin akan
      mengaktifkan paket setelah pembayaran diterima.
    </p>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-light mt-3 mb-0 small"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
  </div>
</div>

<?php
/* Helper render fitur list */
function pk_ul(array $groups): string {
    $h='';
    foreach ($groups as $judul => $items) {
        $h .= '<div class="pk-group"><div class="pk-group-title">'.htmlspecialchars($judul).'</div><ul class="pk-list">';
        foreach ($items as $it) $h .= '<li>'.$it.'</li>';
        $h .= '</ul></div>';
    }
    return $h;
}
?>

<div class="row g-3 mb-3">

  <!-- ========== GRATIS ========== -->
  <div class="col-md-6 col-xl-4">
    <div class="card paket-card h-100 tier-gratis">
      <div class="card-body d-flex flex-column">
        <div class="pk-badge">🟢 GRATIS</div>
        <h4 class="pk-title">Kawan Keringat</h4>
        <div class="pk-price">Rp 0</div>
        <div class="pk-sub">Untuk semua orang yang ingin mulai hidup sehat.</div>
        <?= pk_ul([
          'Aktivitas Olahraga' => [
            'Upload jogging harian (AI Recognition)',
            'Riwayat aktivitas olahraga',
            'Statistik mingguan &amp; bulanan',
            'Attendance Heatmap',
            'Badge &amp; Achievement',
          ],
          'Profil' => [
            'Profil kesehatan',
            'Berat badan &amp; target',
            'Riwayat perkembangan',
          ],
          'Sosial' => [
            'Story olahraga',
            'Social Feed',
            'Diskusi komunitas',
            'Chat Personal',
          ],
          'Personalisasi' => [
            'Tema aplikasi',
            'Catatan perlengkapan olahraga',
            'Catatan pengalaman lapangan',
          ],
        ]) ?>
        <div class="mt-auto pt-2">
          <button class="btn btn-outline-secondary w-100" disabled>
            <i class="bi bi-check2-all"></i> Paket dasar (aktif otomatis)
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== KOMUNITAS ========== -->
  <div class="col-md-6 col-xl-4">
    <div class="card paket-card h-100 tier-komunitas">
      <div class="card-body d-flex flex-column">
        <div class="pk-badge">🔵 KOMUNITAS</div>
        <h4 class="pk-title">Untuk komunitas lari, hiking, badminton, gowes, dll.</h4>
        <div class="pk-price-grid">
          <div>
            <div class="pk-price-label">Mahasiswa</div>
            <div class="pk-price-num">Rp 19.900<span>/bulan</span></div>
            <div class="pk-price-alt">atau Rp 149.000/tahun</div>
          </div>
          <div>
            <div class="pk-price-label">Masyarakat Umum</div>
            <div class="pk-price-num">Rp 39.900<span>/bulan</span></div>
            <div class="pk-price-alt">atau Rp 299.000/tahun</div>
          </div>
        </div>
        <div class="pk-plus">Semua fitur Gratis +</div>
        <?= pk_ul([
          'Tracking' => [
            'Live GPS Tracking',
            'Real-Time Route',
            'Live Beacon (lokasi anggota)',
            'Video Flyover 3D',
            'Riwayat jalur',
          ],
          'Event' => [
            'Booking lapangan',
            'Kalender kegiatan',
            'Manajemen absensi',
            'Join Event',
          ],
          'Outdoor' => [
            'Tempat olahraga terbaru',
            'Jalur Hiking',
            'Jalur Camping',
          ],
          'Monitoring' => [
            'Paket Program Kalistenik',
            'Monitoring Progress Tim',
          ],
        ]) ?>
        <div class="mt-auto pt-2 d-grid gap-2">
          <div class="btn-group w-100" role="group" aria-label="Pilih paket Komunitas">
            <button class="btn btn-primary btn-pilih" data-paket="kom_mhs_bln">Mahasiswa · Bulanan</button>
            <button class="btn btn-outline-primary btn-pilih" data-paket="kom_mhs_thn">Mahasiswa · Tahunan</button>
          </div>
          <div class="btn-group w-100" role="group">
            <button class="btn btn-primary btn-pilih" data-paket="kom_um_bln">Umum · Bulanan</button>
            <button class="btn btn-outline-primary btn-pilih" data-paket="kom_um_thn">Umum · Tahunan</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== PRO (AI) ========== -->
  <div class="col-md-6 col-xl-4">
    <div class="card paket-card h-100 tier-pro">
      <div class="card-body d-flex flex-column">
        <div class="pk-badge">🟣 PRO (AI)</div>
        <h4 class="pk-title">Personal AI Sports Assistant</h4>
        <div class="pk-price-grid">
          <div>
            <div class="pk-price-label">Mahasiswa</div>
            <div class="pk-price-num">Rp 49.900<span>/bulan</span></div>
            <div class="pk-price-alt">atau Rp 399.000/tahun</div>
          </div>
          <div>
            <div class="pk-price-label">Masyarakat Umum</div>
            <div class="pk-price-num">Rp 79.900<span>/bulan</span></div>
            <div class="pk-price-alt">atau Rp 699.000/tahun</div>
          </div>
        </div>
        <div class="pk-plus">Semua fitur Komunitas +</div>
        <?= pk_ul([
          'AI Coach' => [
            'AI Perhitungan Kalori Olahraga',
            'AI Perhitungan Kalori Makanan',
            'AI Meal Recommendation',
          ],
          'AI Health' => [
            'AI Cedera Olahraga',
            'AI Pertolongan Pertama',
            'AI Penyakit Umum',
            'AI Herbal Recommendation',
          ],
          'AI Survival' => [
            'AI Survival Mode',
            'AI Survival Assistant',
          ],
          'AI Smart Search' => [
            'AI Toko Olahraga Terdekat',
            'AI Rumah Sakit Terdekat',
            'AI Puskesmas Terdekat',
          ],
          'Program Khusus' => [
            'Program Anak (2–12 Tahun)',
            'Program Remaja',
            'Program Dewasa',
            'Program Lansia',
            'Program Perokok',
          ],
          'Smart Calculator' => [
            'BMI', 'BMR', 'TDEE', 'Heart Rate Zone', 'Pace Calculator',
          ],
        ]) ?>
        <div class="mt-auto pt-2 d-grid gap-2">
          <div class="btn-group w-100" role="group">
            <button class="btn btn-purple btn-pilih" data-paket="pro_mhs_bln">Mahasiswa · Bulanan</button>
            <button class="btn btn-outline-purple btn-pilih" data-paket="pro_mhs_thn">Mahasiswa · Tahunan</button>
          </div>
          <div class="btn-group w-100" role="group">
            <button class="btn btn-purple btn-pilih" data-paket="pro_um_bln">Umum · Bulanan</button>
            <button class="btn btn-outline-purple btn-pilih" data-paket="pro_um_thn">Umum · Tahunan</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== ADMIN / ORGANIZER ========== -->
  <div class="col-md-6 col-xl-8">
    <div class="card paket-card h-100 tier-organizer">
      <div class="card-body d-flex flex-column">
        <div class="pk-badge">🔴 ADMIN / ORGANIZER</div>
        <h4 class="pk-title">Paket Organizer <small class="text-muted">— mendukung hingga 500 anggota</small></h4>
        <div class="pk-price-grid single">
          <div>
            <div class="pk-price-num">Rp 149.000<span>/bulan</span></div>
            <div class="pk-price-alt">atau Rp 1.299.000/tahun</div>
          </div>
        </div>
        <div class="pk-plus">Semua fitur PRO +</div>
        <div class="row g-2">
          <div class="col-md-6"><?= pk_ul([
            'Manajemen Komunitas' => ['Kelola Member','Kelola Event','Kelola Jadwal','Kelola Tim'],
            'Manajemen Aktivitas' => ['Absensi QR','Pengaturan Match','Statistik Kehadiran','Monitoring Aktivitas'],
            'Keuangan' => ['Rekap Pengeluaran','Iuran Anggota'],
          ]) ?></div>
          <div class="col-md-6"><?= pk_ul([
            'Survey' => ['Penambahan Tempat Olahraga','Usulan Jalur Baru'],
            'Dashboard' => ['Statistik Keaktifan','Dashboard Komunitas','Export Excel &amp; PDF'],
          ]) ?></div>
        </div>
        <div class="mt-auto pt-2 d-grid gap-2 d-md-flex">
          <button class="btn btn-danger btn-pilih flex-fill" data-paket="org_bln"><i class="bi bi-shield-lock"></i> Organizer · Bulanan</button>
          <button class="btn btn-outline-danger btn-pilih flex-fill" data-paket="org_thn"><i class="bi bi-shield-lock"></i> Organizer · Tahunan</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== ENTERPRISE ========== -->
  <div class="col-md-6 col-xl-4">
    <div class="card paket-card h-100 tier-enterprise">
      <div class="card-body d-flex flex-column">
        <div class="pk-badge">🏢 ENTERPRISE</div>
        <h4 class="pk-title">Kampus, sekolah, instansi pemerintah, &amp; perusahaan.</h4>
        <div class="pk-price-num">Mulai Rp 499.000<span>/bulan</span></div>
        <div class="pk-price-alt">Harga disesuaikan berdasarkan jumlah anggota &amp; kebutuhan fitur.</div>
        <ul class="pk-list mt-3">
          <li>Kuota anggota fleksibel</li>
          <li>Fitur custom &amp; integrasi</li>
          <li>Dukungan prioritas &amp; onboarding</li>
          <li>Kontrak &amp; invoice institusi</li>
        </ul>
        <div class="mt-auto pt-2">
          <a href="<?= htmlspecialchars($entUrl) ?>" target="_blank" rel="noopener" class="btn btn-dark w-100">
            <i class="bi bi-whatsapp"></i> Hubungi Admin
          </a>
        </div>
      </div>
    </div>
  </div>
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
      <?php foreach ($riwayat as $r):
        $lbl = isset($PLANS[$r['paket']]) ? $PLANS[$r['paket']][1] : $r['paket'];
      ?>
        <tr>
          <td class="small font-monospace"><?= htmlspecialchars($r['kode']) ?></td>
          <td class="small"><?= htmlspecialchars($lbl) ?></td>
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
  :root{
    --pk-ink:#0a1633; --pk-teal:#1d4ed8; --pk-teal-dark:#0a2472;
    --pk-purple:#7c3aed; --pk-purple-dark:#4c1d95;
    --pk-red:#dc2626; --pk-green:#16a34a; --pk-slate:#334155;
  }
  .paket-hero{ position:relative; border-radius:1.25rem; overflow:hidden;
    background:linear-gradient(135deg,#0a1633 0%, #1d4ed8 45%, #7c3aed 100%); min-height:200px; }
  .paket-hero-overlay{ position:absolute; inset:0;
    background:radial-gradient(120% 80% at 90% 10%, rgba(236,72,153,.28), transparent 60%),
               radial-gradient(120% 80% at 10% 90%, rgba(56,189,248,.35), transparent 60%); }
  .paket-hero-body{ position:relative; padding:1.75rem 1.5rem; z-index:2; }
  .paket-hero-eyebrow{ display:inline-block; background:rgba(255,255,255,.16); backdrop-filter:blur(4px); color:#dbeafe; font-size:.72rem; font-weight:700; letter-spacing:.10em; text-transform:uppercase; padding:.4rem .9rem; border-radius:999px; }
  .paket-hero h3{ text-shadow:0 2px 16px rgba(0,0,0,.35); }

  .paket-card{ border:1px solid #e2e8f0; border-radius:1rem; background:#fff;
    transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
  .paket-card:hover{ transform:translateY(-3px); box-shadow:0 .75rem 1.5rem rgba(14,165,233,.15); }
  .paket-card.selected{ box-shadow:0 0 0 3px rgba(14,165,233,.25); }
  .pk-badge{ display:inline-block; font-size:.72rem; font-weight:800; letter-spacing:.08em;
    padding:.35rem .7rem; border-radius:999px; margin-bottom:.6rem; background:#f1f5f9; color:#0f172a; }
  .tier-gratis    .pk-badge{ background:#dcfce7; color:#166534; }
  .tier-komunitas .pk-badge{ background:#dbeafe; color:#1e40af; }
  .tier-pro       .pk-badge{ background:#ede9fe; color:#5b21b6; }
  .tier-organizer .pk-badge{ background:#fee2e2; color:#991b1b; }
  .tier-enterprise .pk-badge{ background:#e2e8f0; color:#0f172a; }
  .pk-title{ font-size:1.05rem; font-weight:800; color:#0f172a; margin-bottom:.75rem; }
  .pk-price{ font-size:1.6rem; font-weight:800; color:#0f172a; }
  .pk-price-grid{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-bottom:.75rem; }
  .pk-price-grid.single{ grid-template-columns:1fr; }
  .pk-price-label{ font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.06em; }
  .pk-price-num{ font-size:1.25rem; font-weight:800; color:#0f172a; }
  .pk-price-num span{ font-size:.8rem; font-weight:600; color:#64748b; }
  .pk-price-alt{ font-size:.78rem; color:#64748b; }
  .pk-sub{ font-size:.85rem; color:#475569; margin-bottom:.6rem; }
  .pk-plus{ font-size:.85rem; font-weight:700; color:#1d4ed8; margin:.4rem 0; }
  .pk-group{ margin-bottom:.5rem; }
  .pk-group-title{ font-size:.72rem; font-weight:800; letter-spacing:.05em; color:#0f172a; margin-top:.35rem; }
  .pk-list{ padding-left:1.05rem; margin-bottom:.4rem; }
  .pk-list li{ font-size:.82rem; color:#334155; margin-bottom:.15rem; }

  .btn-purple{ background:linear-gradient(135deg,var(--pk-purple),var(--pk-purple-dark)); color:#fff; border:0; }
  .btn-purple:hover{ filter:brightness(1.05); color:#fff; }
  .btn-outline-purple{ color:var(--pk-purple-dark); border:1px solid var(--pk-purple); background:#fff; }
  .btn-outline-purple:hover{ background:var(--pk-purple); color:#fff; }
</style>

<script>
(function(){
  var csrf = <?= json_encode(csrf_token()) ?>;
  var PLANS = <?= json_encode($PLANS) ?>;
  var selected = null;

  function fmtRp(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }
  function setMsg(html, cls){
    var m = document.getElementById('payMsg');
    m.className = 'small mt-2 ' + (cls || 'text-muted');
    m.innerHTML = html;
  }
  function selectPaket(key){
    if (!PLANS[key]) return;
    selected = key;
    document.querySelectorAll('.btn-pilih').forEach(function(b){
      b.classList.toggle('active', b.dataset.paket === key);
    });
    document.getElementById('sumPaket').textContent = PLANS[key][1];
    document.getElementById('sumHarga').textContent = fmtRp(PLANS[key][2]);
    var s = document.getElementById('paySummary');
    s.classList.remove('d-none');
    s.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
  document.querySelectorAll('.btn-pilih').forEach(function(b){
    b.addEventListener('click', function(e){ e.preventDefault(); selectPaket(b.dataset.paket); });
  });

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

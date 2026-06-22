<?php
/**
 * admin/biaya.php
 * CRUD biaya admin Midtrans + biaya aplikasi (key/value di app_settings).
 * Dipakai jajanan.php sebagai sumber tunggal kebenaran.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/app_settings.php';
require_role('admin');
$pageTitle = 'Biaya Admin & Aplikasi';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    try {
        $map = [
            'biaya_admin_fixed'    => max(0,(int)($_POST['biaya_admin_fixed'] ?? 0)),
            'biaya_admin_pct'      => max(0,(float)($_POST['biaya_admin_pct'] ?? 0)),
            'biaya_aplikasi_fixed' => max(0,(int)($_POST['biaya_aplikasi_fixed'] ?? 0)),
            'biaya_aplikasi_pct'   => max(0,(float)($_POST['biaya_aplikasi_pct'] ?? 0)),
            'invoice_email_from'   => substr(trim($_POST['invoice_email_from'] ?? ''),0,160),
            'invoice_email_nama'   => substr(trim($_POST['invoice_email_nama'] ?? ''),0,80),
        ];
        foreach ($map as $k => $v) app_setting_set($k, (string)$v);
        $_SESSION['flash'] = 'Pengaturan biaya disimpan.';
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: biaya.php'); exit;
}

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-cash-coin text-success"></i> Biaya Admin &amp; Biaya Aplikasi</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<p class="text-muted small">Total biaya yang ditambahkan ke transaksi Midtrans dihitung sebagai:<br>
<code>biaya_admin = fixed_admin + (subtotal+ongkir+PPN) × pct_admin</code><br>
<code>biaya_aplikasi = fixed_aplikasi + (subtotal+ongkir+PPN) × pct_aplikasi</code></p>

<form method="post" class="card shadow-sm">
  <div class="card-body">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="row g-3">
      <div class="col-12"><h5 class="mb-0"><i class="bi bi-credit-card-2-front text-primary"></i> Biaya Admin (Midtrans)</h5></div>
      <div class="col-md-6">
        <label class="form-label small">Fixed (Rp / transaksi)</label>
        <input type="number" min="0" step="100" name="biaya_admin_fixed" class="form-control" value="<?= htmlspecialchars(app_setting('biaya_admin_fixed','4000')) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small">Persen (mis. 0.007 = 0.7%)</label>
        <input type="number" min="0" step="0.0001" name="biaya_admin_pct" class="form-control" value="<?= htmlspecialchars(app_setting('biaya_admin_pct','0.007')) ?>">
      </div>

      <div class="col-12 mt-3"><h5 class="mb-0"><i class="bi bi-app-indicator text-warning"></i> Biaya Aplikasi</h5></div>
      <div class="col-md-6">
        <label class="form-label small">Fixed (Rp / transaksi)</label>
        <input type="number" min="0" step="100" name="biaya_aplikasi_fixed" class="form-control" value="<?= htmlspecialchars(app_setting('biaya_aplikasi_fixed','1000')) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small">Persen</label>
        <input type="number" min="0" step="0.0001" name="biaya_aplikasi_pct" class="form-control" value="<?= htmlspecialchars(app_setting('biaya_aplikasi_pct','0')) ?>">
      </div>

      <div class="col-12 mt-3"><h5 class="mb-0"><i class="bi bi-envelope-paper text-info"></i> Pengirim Invoice Email</h5></div>
      <div class="col-md-6">
        <label class="form-label small">Email From</label>
        <input type="email" name="invoice_email_from" class="form-control" value="<?= htmlspecialchars(app_setting('invoice_email_from','no-reply@hapfam.local')) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small">Nama Pengirim</label>
        <input type="text" name="invoice_email_nama" class="form-control" value="<?= htmlspecialchars(app_setting('invoice_email_nama','KawanKeringat')) ?>">
      </div>
    </div>
  </div>
  <div class="card-footer">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Pengaturan</button>
  </div>
</form>

<?php include __DIR__.'/../includes/footer.php'; ?>

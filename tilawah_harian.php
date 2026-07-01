<?php
/**
 * tilawah_harian.php — Revisi Juli 2026.
 * Monitoring Tilawah Harian (kepada diri sendiri / keluarga).
 *
 * SQL tambahan (PostgreSQL):
 *   CREATE TABLE IF NOT EXISTS tilawah_harian (
 *     id          SERIAL PRIMARY KEY,
 *     user_id     INT NOT NULL,
 *     tanggal     DATE NOT NULL,
 *     sasaran     VARCHAR(20) NOT NULL DEFAULT 'diri', -- 'diri' atau 'keluarga'
 *     surah       VARCHAR(80),
 *     ayat_dari   INT,
 *     ayat_sampai INT,
 *     durasi_menit INT,
 *     catatan     TEXT,
 *     created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
 *   );
 *   CREATE INDEX IF NOT EXISTS ix_tilawah_user_tgl ON tilawah_harian(user_id, tanggal DESC);
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user();
$pageTitle = 'Monitoring Tilawah Harian';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='add') {
        $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
        $sas = ($_POST['sasaran'] ?? 'diri')==='keluarga' ? 'keluarga' : 'diri';
        $sur = mb_substr(trim($_POST['surah'] ?? ''), 0, 80);
        $af  = max(0, min(300, (int)($_POST['ayat_dari'] ?? 0)));
        $as  = max(0, min(300, (int)($_POST['ayat_sampai'] ?? 0)));
        $du  = max(0, min(600, (int)($_POST['durasi_menit'] ?? 0)));
        $cat = mb_substr(trim($_POST['catatan'] ?? ''), 0, 500);
        db_exec("INSERT INTO tilawah_harian(user_id,tanggal,sasaran,surah,ayat_dari,ayat_sampai,durasi_menit,catatan)
                 VALUES($1,$2,$3,NULLIF($4,''),NULLIF($5,0),NULLIF($6,0),NULLIF($7,0),NULLIF($8,''))",
            [(int)$u['id'],$tgl,$sas,$sur,$af,$as,$du,$cat]);
    } elseif ($a==='delete') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec("DELETE FROM tilawah_harian WHERE id=$1 AND user_id=$2", [$id, (int)$u['id']]);
    }
    header('Location: /tilawah_harian.php'); exit;
}

$rows = [];
try {
    $rows = db_all("SELECT * FROM tilawah_harian WHERE user_id=$1 ORDER BY tanggal DESC, id DESC LIMIT 200",
        [(int)$u['id']]);
} catch (Throwable $e) { $rows = []; }

$today = date('Y-m-d');
$countDiri     = 0; $countKel = 0;
foreach ($rows as $r) {
    if ($r['tanggal'] === $today) { $r['sasaran']==='keluarga' ? $countKel++ : $countDiri++; }
}

include __DIR__.'/includes/header.php'; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
    <li class="breadcrumb-item active">Monitoring Tilawah Harian</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-book-half text-success"></i> Monitoring Tilawah Harian</h2>
<p class="text-muted small">Catat tilawah Al-Qur'an harian Anda, baik untuk <b>diri sendiri</b> maupun <b>menyimak / mengajarkan keluarga</b>.</p>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Tilawah hari ini (diri)</div><div class="h4 mb-0 text-success"><?= $countDiri ?></div></div></div>
  <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Tilawah hari ini (keluarga)</div><div class="h4 mb-0 text-warning"><?= $countKel ?></div></div></div>
</div>

<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success-subtle text-success-emphasis"><i class="bi bi-plus-circle"></i> <strong>Tambah Catatan Tilawah</strong></div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="add">
      <div class="col-md-3"><label class="small">Tanggal</label>
        <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= $today ?>" required></div>
      <div class="col-md-3"><label class="small">Sasaran</label>
        <select class="form-select form-select-sm" name="sasaran">
          <option value="diri">Diri sendiri</option>
          <option value="keluarga">Keluarga (menyimak / mengajarkan)</option>
        </select></div>
      <div class="col-md-3"><label class="small">Surah</label>
        <input type="text" class="form-control form-control-sm" name="surah" maxlength="80" placeholder="mis. Al-Baqarah"></div>
      <div class="col-md-3"><label class="small">Durasi (menit)</label>
        <input type="number" class="form-control form-control-sm" name="durasi_menit" min="0" max="600" placeholder="mis. 20"></div>
      <div class="col-md-3"><label class="small">Ayat dari</label>
        <input type="number" class="form-control form-control-sm" name="ayat_dari" min="0" max="300"></div>
      <div class="col-md-3"><label class="small">Ayat sampai</label>
        <input type="number" class="form-control form-control-sm" name="ayat_sampai" min="0" max="300"></div>
      <div class="col-md-6"><label class="small">Catatan</label>
        <input type="text" class="form-control form-control-sm" name="catatan" maxlength="500" placeholder="siapa yg disimak, murojaah, hafalan baru, dsb"></div>
      <div class="col-12">
        <button class="btn btn-sm btn-success"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-list-ul text-primary"></i> <strong>Riwayat Tilawah</strong></div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:520px; overflow:auto;">
      <table class="table table-sm mb-0 align-middle" style="min-width:880px;">
        <thead class="table-light" style="position:sticky;top:0;z-index:2;">
          <tr>
            <th style="min-width:110px">Tanggal</th>
            <th style="min-width:110px">Sasaran</th>
            <th style="min-width:150px">Surah</th>
            <th style="min-width:90px">Ayat</th>
            <th style="min-width:90px">Durasi</th>
            <th style="min-width:220px">Catatan</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted small py-4">Belum ada catatan tilawah.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><span class="badge bg-<?= $r['sasaran']==='keluarga'?'warning text-dark':'success' ?>"><?= htmlspecialchars(ucfirst($r['sasaran'])) ?></span></td>
            <td><?= htmlspecialchars($r['surah'] ?? '-') ?></td>
            <td class="small"><?= $r['ayat_dari'] ? htmlspecialchars($r['ayat_dari'].' - '.($r['ayat_sampai'] ?: $r['ayat_dari'])) : '-' ?></td>
            <td class="small"><?= $r['durasi_menit'] ? (int)$r['durasi_menit'].' mnt' : '-' ?></td>
            <td class="small"><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Hapus catatan ini?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

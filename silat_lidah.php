<?php
/**
 * silat_lidah.php — Revisi Juli 2026.
 * Monitoring "Silat Lidah" — latihan komunikasi kepada teman sebaya.
 * Catat: kapan, siapa temannya, topik yang dibahas, evaluasi.
 *
 * SQL tambahan (PostgreSQL):
 *   CREATE TABLE IF NOT EXISTS silat_lidah (
 *     id          SERIAL PRIMARY KEY,
 *     user_id     INT NOT NULL,
 *     tanggal     DATE NOT NULL,
 *     teman       VARCHAR(160) NOT NULL,
 *     topik       VARCHAR(200) NOT NULL,
 *     durasi_menit INT,
 *     kualitas    SMALLINT, -- 1..5
 *     catatan     TEXT,
 *     created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
 *   );
 *   CREATE INDEX IF NOT EXISTS ix_silat_user_tgl ON silat_lidah(user_id, tanggal DESC);
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user();
$pageTitle = 'Monitoring Silat Lidah (Komunikasi)';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='add') {
        $tgl   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
        $teman = mb_substr(trim($_POST['teman'] ?? ''), 0, 160);
        $topik = mb_substr(trim($_POST['topik'] ?? ''), 0, 200);
        $du    = max(0, min(600, (int)($_POST['durasi_menit'] ?? 0)));
        $ku    = max(0, min(5, (int)($_POST['kualitas'] ?? 0)));
        $cat   = mb_substr(trim($_POST['catatan'] ?? ''), 0, 800);
        if ($teman!=='' && $topik!=='') {
            db_exec("INSERT INTO silat_lidah(user_id,tanggal,teman,topik,durasi_menit,kualitas,catatan)
                     VALUES($1,$2,$3,$4,NULLIF($5,0),NULLIF($6,0),NULLIF($7,''))",
                [(int)$u['id'],$tgl,$teman,$topik,$du,$ku,$cat]);
        }
    } elseif ($a==='delete') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec("DELETE FROM silat_lidah WHERE id=$1 AND user_id=$2", [$id, (int)$u['id']]);
    }
    header('Location: /silat_lidah.php'); exit;
}

$rows = [];
try {
    $rows = db_all("SELECT * FROM silat_lidah WHERE user_id=$1 ORDER BY tanggal DESC, id DESC LIMIT 200",
        [(int)$u['id']]);
} catch (Throwable $e) { $rows = []; }
$total7 = 0; $cut = date('Y-m-d', strtotime('-6 days'));
foreach ($rows as $r) if ($r['tanggal'] >= $cut) $total7++;

include __DIR__.'/includes/header.php'; ?>

<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/islami.php">Hub Islami</a></li>
    <li class="breadcrumb-item active">Monitoring Silat Lidah</li>
  </ol>
</nav>

<h2 class="mb-3"><i class="bi bi-chat-square-quote-fill text-info"></i> Monitoring Silat Lidah (Skill Komunikasi)</h2>
<p class="text-muted small">Latih kemampuan komunikasi Anda dengan berbicara sopan &amp; bermakna kepada teman sebaya. Catat siapa temannya dan topik apa yang dibahas.</p>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Sesi 7 hari terakhir</div><div class="h4 mb-0 text-info"><?= $total7 ?></div></div></div>
  <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="small text-muted">Total catatan</div><div class="h4 mb-0 text-primary"><?= count($rows) ?></div></div></div>
</div>

<div class="card shadow-sm mb-3 border-info">
  <div class="card-header bg-info-subtle text-info-emphasis"><i class="bi bi-plus-circle"></i> <strong>Tambah Sesi Silat Lidah</strong></div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="add">
      <div class="col-md-3"><label class="small">Tanggal</label>
        <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-4"><label class="small">Nama Teman *</label>
        <input type="text" class="form-control form-control-sm" name="teman" maxlength="160" placeholder="mis. Rizky, Adit, Salsa" required></div>
      <div class="col-md-5"><label class="small">Topik yang Dibahas *</label>
        <input type="text" class="form-control form-control-sm" name="topik" maxlength="200" placeholder="mis. persiapan lomba futsal, kajian minggu lalu, kuliah, hobi" required></div>
      <div class="col-md-3"><label class="small">Durasi (menit)</label>
        <input type="number" class="form-control form-control-sm" name="durasi_menit" min="0" max="600" placeholder="mis. 15"></div>
      <div class="col-md-3"><label class="small">Kualitas obrolan (1-5)</label>
        <select class="form-select form-select-sm" name="kualitas">
          <option value="0">–</option>
          <option value="1">1 · canggung</option>
          <option value="2">2 · biasa</option>
          <option value="3">3 · nyambung</option>
          <option value="4">4 · seru</option>
          <option value="5">5 · sangat bermakna</option>
        </select></div>
      <div class="col-md-6"><label class="small">Evaluasi / Catatan</label>
        <input type="text" class="form-control form-control-sm" name="catatan" maxlength="800" placeholder="mis. sempat menyampaikan hikmah, latih intonasi, dengarkan lebih dulu"></div>
      <div class="col-12">
        <button class="btn btn-sm btn-info text-white"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-list-ul text-primary"></i> <strong>Riwayat Silat Lidah</strong></div>
  <div class="card-body p-0">
    <div class="table-responsive" style="max-height:520px; overflow:auto;">
      <table class="table table-sm mb-0 align-middle" style="min-width:960px;">
        <thead class="table-light" style="position:sticky;top:0;z-index:2;">
          <tr>
            <th style="min-width:110px">Tanggal</th>
            <th style="min-width:160px">Teman</th>
            <th style="min-width:220px">Topik</th>
            <th style="min-width:90px">Durasi</th>
            <th style="min-width:90px">Kualitas</th>
            <th style="min-width:220px">Catatan</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted small py-4">Belum ada sesi tercatat.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><?= htmlspecialchars($r['teman']) ?></td>
            <td><?= htmlspecialchars($r['topik']) ?></td>
            <td class="small"><?= $r['durasi_menit'] ? (int)$r['durasi_menit'].' mnt' : '-' ?></td>
            <td class="small"><?= $r['kualitas'] ? str_repeat('★', (int)$r['kualitas']) : '-' ?></td>
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

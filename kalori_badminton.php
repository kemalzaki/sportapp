<?php
/**
 * Kalori Olahraga Badminton — Revisi (CRUD + Riwayat)
 * Rumus MET: kcal = MET × 3.5 × berat_kg / 200 × menit
 *
 * Skema PostgreSQL (auto-create lewat IF NOT EXISTS):
 *   CREATE TABLE IF NOT EXISTS kalori_log (
 *     id SERIAL PRIMARY KEY,
 *     user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 *     jenis VARCHAR(40) NOT NULL,
 *     intensitas VARCHAR(40) NOT NULL,
 *     berat_kg NUMERIC(5,1) NOT NULL,
 *     menit INTEGER NOT NULL,
 *     met NUMERIC(4,2) NOT NULL,
 *     kalori NUMERIC(7,2) NOT NULL,
 *     dibuat_pada TIMESTAMP NOT NULL DEFAULT now()
 *   );
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();

require_once __DIR__.'/includes/paket_helpers.php';
require_login();
// Revisi R6 (Juli 2026) — Halaman ini dikunci untuk paket Pro & Komunitas.
paket_require_or_lock('pro', current_user(), 'Kalori Badminton');
$pageTitle = 'Kalori Badminton';
$u = current_user();

// Auto-buat tabel kalori_log jika belum ada (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS kalori_log (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        jenis VARCHAR(40) NOT NULL,
        intensitas VARCHAR(40) NOT NULL,
        berat_kg NUMERIC(5,1) NOT NULL,
        menit INTEGER NOT NULL,
        met NUMERIC(4,2) NOT NULL,
        kalori NUMERIC(7,2) NOT NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT now()
    )");
} catch (Throwable $e) {}

$MET = [
    'santai'      => ['label' => 'Rekreasi / santai',   'met' => 4.5],
    'doubles'     => ['label' => 'Sosial / doubles',    'met' => 5.5],
    'singles'     => ['label' => 'Reguler / singles',   'met' => 7.0],
    'kompetitif'  => ['label' => 'Kompetitif / intens', 'met' => 8.5],
];

$prefBerat = '';
if ($u) {
    try {
        $row = db_one("SELECT berat_kg FROM users WHERE id=$1", [(int)$u['id']]);
        if ($row && !empty($row['berat_kg'])) $prefBerat = $row['berat_kg'];
    } catch (Throwable $e) {}
}

$hasil = null;
$flash = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $action = $_POST['_action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db_exec("DELETE FROM kalori_log WHERE id=$1 AND user_id=$2 AND jenis='badminton'",
                [$id, (int)$u['id']]);
            $flash = ['type'=>'success','msg'=>'Riwayat terhapus.'];
        } catch (Throwable $e) {
            $flash = ['type'=>'danger','msg'=>'Gagal menghapus.'];
        }
        header('Location: kalori_badminton.php'); exit;
    }

    // create / update
    $intens = $_POST['intensitas'] ?? 'doubles';
    if (!isset($MET[$intens])) $intens = 'doubles';
    $berat  = max(20, min(300, (float)($_POST['berat'] ?? 0)));
    $menit  = max(1, min(600, (int)($_POST['menit'] ?? 0)));
    $met    = $MET[$intens]['met'];
    $kcal   = round($met * 3.5 * $berat / 200 * $menit, 1);

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db_exec("UPDATE kalori_log
                     SET intensitas=$1, berat_kg=$2, menit=$3, met=$4, kalori=$5
                     WHERE id=$6 AND user_id=$7 AND jenis='badminton'",
                [$intens, $berat, $menit, $met, $kcal, $id, (int)$u['id']]);
            $flash = ['type'=>'success','msg'=>'Riwayat diperbarui.'];
        } catch (Throwable $e) {
            $flash = ['type'=>'danger','msg'=>'Gagal memperbarui.'];
        }
        header('Location: kalori_badminton.php'); exit;
    }

    // create
    try {
        db_exec("INSERT INTO kalori_log(user_id,jenis,intensitas,berat_kg,menit,met,kalori)
                 VALUES($1,'badminton',$2,$3,$4,$5,$6)",
            [(int)$u['id'], $intens, $berat, $menit, $met, $kcal]);
        $flash = ['type'=>'success','msg'=>'Tersimpan ke riwayat.'];
    } catch (Throwable $e) {
        $flash = ['type'=>'warning','msg'=>'Tidak dapat menyimpan: '.$e->getMessage()];
    }
    $hasil = [
        'intensitas'=>$intens,'label'=>$MET[$intens]['label'],
        'met'=>$met,'berat'=>$berat,'menit'=>$menit,'kalori'=>$kcal
    ];
}

// Mode edit?
if ($u && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $editing = db_one("SELECT * FROM kalori_log WHERE id=$1 AND user_id=$2 AND jenis='badminton'",
            [$id, (int)$u['id']]);
    } catch (Throwable $e) {}
}

// Riwayat (semua, dengan paginasi sederhana)
$riwayat = [];
$total = 0;
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 15;
$off = ($page - 1) * $per;
if ($u) {
    try {
        $total = (int) db_val("SELECT COUNT(*) FROM kalori_log WHERE user_id=$1 AND jenis='badminton'", [(int)$u['id']]);
        $riwayat = db_all("SELECT id, intensitas, berat_kg, menit, met, kalori, dibuat_pada
                           FROM kalori_log
                           WHERE user_id=$1 AND jenis='badminton'
                           ORDER BY dibuat_pada DESC
                           LIMIT $per OFFSET $off", [(int)$u['id']]);
    } catch (Throwable $e) {}
}
$totalPages = max(1, (int)ceil($total / $per));
$totalKcal = 0; foreach ($riwayat as $r) $totalKcal += (float)$r['kalori'];

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-stopwatch text-success"></i> Kalkulator Kalori Badminton</h2>
<p class="text-muted small mb-3">Hitung &amp; kelola perkiraan kalori terbakar saat bermain bulu tangkis. Rumus mengacu nilai <strong>MET</strong> (Compendium of Physical Activities).</p>

<?php if($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> py-2 small"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header">
        <i class="bi bi-<?= $editing ? 'pencil-square' : 'calculator' ?>"></i>
        <?= $editing ? 'Edit Riwayat #'.(int)$editing['id'] : 'Hitung Kalori' ?>
      </div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="<?= $editing ? 'update' : 'create' ?>">
          <?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
          <div class="col-12">
            <label class="form-label small fw-semibold">Intensitas permainan</label>
            <select name="intensitas" class="form-select" required>
              <?php
              $cur = $editing['intensitas'] ?? ($hasil['intensitas'] ?? 'doubles');
              foreach($MET as $k=>$v): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $cur===$k?'selected':'' ?>>
                  <?= htmlspecialchars($v['label']) ?> (MET <?= $v['met'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Berat badan (kg)</label>
            <input type="number" step="0.1" min="20" max="300" name="berat" class="form-control"
                   value="<?= htmlspecialchars($editing['berat_kg'] ?? ($hasil['berat'] ?? $prefBerat)) ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Durasi main (menit)</label>
            <input type="number" min="1" max="600" name="menit" class="form-control"
                   value="<?= htmlspecialchars($editing['menit'] ?? ($hasil['menit'] ?? 60)) ?>" required>
          </div>
          <div class="col-12 mt-2 d-flex gap-2">
            <button class="btn btn-success flex-fill">
              <i class="bi bi-<?= $editing ? 'save' : 'calculator' ?>"></i>
              <?= $editing ? 'Simpan Perubahan' : 'Hitung & Simpan' ?>
            </button>
            <?php if($editing): ?>
              <a href="kalori_badminton.php" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
          <?php if(!$u): ?>
            <div class="col-12"><div class="alert alert-info small mt-2 mb-0 py-2"><i class="bi bi-info-circle"></i> Login untuk menyimpan riwayat.</div></div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if ($hasil && !$editing): ?>
    <div class="card shadow-sm mt-3 border-success">
      <div class="card-body text-center">
        <div class="small text-muted">Estimasi kalori terbakar</div>
        <div class="display-5 fw-bold text-success"><?= number_format($hasil['kalori'], 1, ',', '.') ?> <small class="fs-6">kcal</small></div>
        <div class="small text-muted mt-1">
          <?= htmlspecialchars($hasil['label']) ?> · MET <?= $hasil['met'] ?> · <?= $hasil['berat'] ?> kg · <?= $hasil['menit'] ?> menit
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mt-3"><div class="card-body">
      <h3 class="h6 fw-semibold mb-2"><i class="bi bi-info-circle text-primary"></i> Tentang nilai MET</h3>
      <ul class="small text-muted mb-0 ps-3">
        <li><strong>MET 4.5</strong> — rekreasi santai (rally pelan)</li>
        <li><strong>MET 5.5</strong> — doubles / sosial</li>
        <li><strong>MET 7.0</strong> — singles reguler</li>
        <li><strong>MET 8.5</strong> — kompetitif / pertandingan intens</li>
      </ul>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Riwayat Perhitungan</span>
        <?php if($u && $riwayat): ?>
          <span class="badge bg-success">Total: <?= number_format($totalKcal,1,',','.') ?> kcal</span>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light small">
            <tr>
              <th>Tanggal</th><th>Intensitas</th>
              <th class="text-end">Berat</th><th class="text-end">Menit</th>
              <th class="text-end">Kalori</th><th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$u): ?>
            <tr><td colspan="6" class="text-center text-muted small py-4">Login untuk melihat riwayat.</td></tr>
          <?php elseif(!$riwayat): ?>
            <tr><td colspan="6" class="text-center text-muted small py-4">Belum ada riwayat.</td></tr>
          <?php else: foreach($riwayat as $r): ?>
            <tr>
              <td class="small"><?= htmlspecialchars(date('d M Y H:i', strtotime($r['dibuat_pada']))) ?></td>
              <td class="small"><?= htmlspecialchars($MET[$r['intensitas']]['label'] ?? $r['intensitas']) ?></td>
              <td class="text-end small"><?= htmlspecialchars($r['berat_kg']) ?> kg</td>
              <td class="text-end small"><?= (int)$r['menit'] ?></td>
              <td class="text-end fw-semibold text-success"><?= number_format($r['kalori'],1,',','.') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary py-0 px-1" href="?edit=<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus riwayat ini?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($totalPages > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center small">
        <span class="text-muted">Halaman <?= $page ?> / <?= $totalPages ?> · <?= $total ?> entri</span>
        <div class="btn-group btn-group-sm">
          <?php if($page>1): ?><a class="btn btn-outline-secondary" href="?p=<?= $page-1 ?>">&laquo;</a><?php endif; ?>
          <?php if($page<$totalPages): ?><a class="btn btn-outline-secondary" href="?p=<?= $page+1 ?>">&raquo;</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

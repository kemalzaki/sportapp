<?php
/**
 * Kalori Olahraga Badminton — Revisi 3 Jun 2026
 * Menghitung estimasi kalori terbakar saat bermain badminton.
 * Rumus MET (Compendium of Physical Activities):
 *   kcal/menit = MET × 3.5 × berat_kg / 200
 * MET badminton:
 *   - rekreasi/santai     : 4.5
 *   - sosial doubles      : 5.5
 *   - reguler (singles)   : 7.0
 *   - kompetitif (intens) : 8.5
 *
 * Catatan PostgreSQL:
 * Halaman ini OPSIONAL menyimpan riwayat ke tabel `kalori_log` jika ada.
 * Skema yang disarankan (tambahkan ke .sql Anda bila ingin disimpan):
 *
 *   CREATE TABLE IF NOT EXISTS kalori_log (
 *     id SERIAL PRIMARY KEY,
 *     user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 *     jenis VARCHAR(40) NOT NULL,
 *     intensitas VARCHAR(20) NOT NULL,
 *     berat_kg NUMERIC(5,1) NOT NULL,
 *     menit INTEGER NOT NULL,
 *     met NUMERIC(4,2) NOT NULL,
 *     kalori NUMERIC(7,2) NOT NULL,
 *     dibuat_pada TIMESTAMP NOT NULL DEFAULT now()
 *   );
 *
 * Tanpa tabel ini halaman tetap berfungsi (hanya tidak menyimpan riwayat).
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
send_security_headers(); enforce_session_timeout();

$pageTitle = 'Kalori Badminton';
$u = current_user();

$prefBerat = '';
if ($u) {
    try {
        $row = db_one("SELECT berat_kg FROM users WHERE id=$1", [(int)$u['id']]);
        if ($row && !empty($row['berat_kg'])) $prefBerat = $row['berat_kg'];
    } catch (Throwable $e) {}
}

$MET = [
    'santai'      => ['label' => 'Rekreasi / santai',  'met' => 4.5],
    'doubles'     => ['label' => 'Sosial / doubles',   'met' => 5.5],
    'singles'     => ['label' => 'Reguler / singles',  'met' => 7.0],
    'kompetitif'  => ['label' => 'Kompetitif / intens','met' => 8.5],
];

$hasil = null;
$saved = false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $intens = $_POST['intensitas'] ?? 'doubles';
    if (!isset($MET[$intens])) $intens = 'doubles';
    $berat  = max(20, min(300, (float)($_POST['berat'] ?? 0)));
    $menit  = max(1, min(600, (int)($_POST['menit'] ?? 0)));
    $met    = $MET[$intens]['met'];
    $kcal   = $met * 3.5 * $berat / 200 * $menit;
    $hasil = [
        'intensitas' => $intens,
        'label'      => $MET[$intens]['label'],
        'met'        => $met,
        'berat'      => $berat,
        'menit'      => $menit,
        'kalori'     => round($kcal, 1),
    ];
    // Opsional: simpan riwayat jika tabel tersedia & user login
    if ($u) {
        try {
            db_exec("INSERT INTO kalori_log(user_id,jenis,intensitas,berat_kg,menit,met,kalori)
                     VALUES($1,'badminton',$2,$3,$4,$5,$6)",
                [(int)$u['id'], $intens, $berat, $menit, $met, $hasil['kalori']]);
            $saved = true;
        } catch (Throwable $e) { /* tabel belum dibuat: abaikan */ }
    }
}

// Riwayat (opsional)
$riwayat = [];
if ($u) {
    try {
        $riwayat = db_all("SELECT intensitas, berat_kg, menit, met, kalori, dibuat_pada
                           FROM kalori_log
                           WHERE user_id=$1 AND jenis='badminton'
                           ORDER BY dibuat_pada DESC LIMIT 10", [(int)$u['id']]);
    } catch (Throwable $e) {}
}

include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-stopwatch text-success"></i> Kalkulator Kalori Badminton</h2>
<p class="text-muted small mb-3">Hitung perkiraan kalori yang terbakar saat bermain bulu tangkis berdasarkan berat badan, durasi, dan intensitas. Rumus mengacu pada nilai <strong>MET</strong> (Compendium of Physical Activities).</p>

<div class="row g-3" data-live="kalori-badminton">
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="col-12">
          <label class="form-label small fw-semibold">Intensitas permainan</label>
          <select name="intensitas" class="form-select" required>
            <?php foreach($MET as $k=>$v):
              $sel = ($hasil && $hasil['intensitas']===$k) ? 'selected' : ($k==='doubles' && !$hasil ? 'selected' : '');
            ?>
              <option value="<?= htmlspecialchars($k) ?>" <?= $sel ?>><?= htmlspecialchars($v['label']) ?> (MET <?= $v['met'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Berat badan (kg)</label>
          <input type="number" step="0.1" min="20" max="300" name="berat" class="form-control"
                 value="<?= htmlspecialchars($hasil['berat'] ?? $prefBerat) ?>" required>
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Durasi main (menit)</label>
          <input type="number" min="1" max="600" name="menit" class="form-control"
                 value="<?= htmlspecialchars($hasil['menit'] ?? 60) ?>" required>
        </div>
        <div class="col-12 mt-2">
          <button class="btn btn-success w-100"><i class="bi bi-calculator"></i> Hitung Kalori</button>
        </div>
      </form>
    </div></div>

    <?php if ($hasil): ?>
    <div class="card shadow-sm mt-3 border-success">
      <div class="card-body text-center">
        <div class="small text-muted">Estimasi kalori terbakar</div>
        <div class="display-5 fw-bold text-success"><?= number_format($hasil['kalori'], 1, ',', '.') ?> <small class="fs-6">kcal</small></div>
        <div class="small text-muted mt-1">
          <?= htmlspecialchars($hasil['label']) ?> · MET <?= $hasil['met'] ?> ·
          <?= $hasil['berat'] ?> kg · <?= $hasil['menit'] ?> menit
        </div>
        <?php if ($saved): ?>
          <div class="alert alert-success small mt-3 mb-0 py-2"><i class="bi bi-check2-circle"></i> Tersimpan ke riwayat.</div>
        <?php elseif ($u): ?>
          <div class="alert alert-warning small mt-3 mb-0 py-2"><i class="bi bi-info-circle"></i> Tabel <code>kalori_log</code> belum tersedia — riwayat tidak disimpan. Tambahkan skema di catatan file ini bila perlu.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <h3 class="h6 fw-semibold mb-2"><i class="bi bi-info-circle text-primary"></i> Tentang nilai MET</h3>
      <ul class="small text-muted mb-3 ps-3">
        <li><strong>MET 4.5</strong> — rekreasi santai (rally pelan)</li>
        <li><strong>MET 5.5</strong> — doubles / sosial</li>
        <li><strong>MET 7.0</strong> — singles reguler</li>
        <li><strong>MET 8.5</strong> — kompetitif / pertandingan intens</li>
      </ul>
      <h3 class="h6 fw-semibold mb-2"><i class="bi bi-list-check text-primary"></i> Tips</h3>
      <ul class="small text-muted mb-0 ps-3">
        <li>Pemanasan 5–10 menit sebelum bermain.</li>
        <li>Konsumsi air ≥ 500 ml per jam permainan.</li>
        <li>Asupan karbohidrat 1–4 jam sebelum main untuk energi optimal.</li>
      </ul>
    </div></div>

    <?php if ($u && $riwayat): ?>
    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-clock-history"></i> Riwayat 10 perhitungan terakhir</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Tanggal</th><th>Intensitas</th><th class="text-end">Berat</th><th class="text-end">Menit</th><th class="text-end">Kalori</th></tr></thead>
          <tbody>
          <?php foreach($riwayat as $r): ?>
            <tr>
              <td><?= htmlspecialchars(date('d M H:i', strtotime($r['dibuat_pada']))) ?></td>
              <td><?= htmlspecialchars($MET[$r['intensitas']]['label'] ?? $r['intensitas']) ?></td>
              <td class="text-end"><?= htmlspecialchars($r['berat_kg']) ?> kg</td>
              <td class="text-end"><?= (int)$r['menit'] ?></td>
              <td class="text-end fw-semibold text-success"><?= number_format($r['kalori'], 1, ',', '.') ?> kcal</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

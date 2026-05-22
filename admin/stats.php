<?php
// Admin: Statistik Kehadiran Pintar
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers(); enforce_session_timeout();
require_role('admin');
$pageTitle = 'Statistik Pintar';

// Member paling sering telat (8 minggu terakhir)
$telat = db_all("SELECT u.id,u.nama,u.foto_url, AVG(a.telat_menit) AS rata, COUNT(*) FILTER (WHERE a.telat_menit>0) AS kali
                 FROM absensi a JOIN users u ON u.id=a.user_id JOIN jadwal j ON j.id=a.jadwal_id
                 WHERE a.hadir=1 AND j.tanggal >= CURRENT_DATE - INTERVAL '60 days'
                 GROUP BY u.id,u.nama,u.foto_url HAVING COUNT(*) FILTER (WHERE a.telat_menit>0) > 0
                 ORDER BY rata DESC LIMIT 10");

// Absensi menurun: trend 4w vs 4w sebelumnya
$drop = db_all("WITH a AS (
  SELECT u.id,u.nama,u.foto_url,
    SUM(CASE WHEN j.tanggal >= CURRENT_DATE - INTERVAL '28 days' AND ab.hadir=1 THEN 1 ELSE 0 END) AS now4,
    SUM(CASE WHEN j.tanggal <  CURRENT_DATE - INTERVAL '28 days'
              AND j.tanggal >= CURRENT_DATE - INTERVAL '56 days' AND ab.hadir=1 THEN 1 ELSE 0 END) AS prev4
  FROM users u LEFT JOIN absensi ab ON ab.user_id=u.id
  LEFT JOIN jadwal j ON j.id=ab.jadwal_id
  WHERE u.role IN ('member','admin')
  GROUP BY u.id,u.nama,u.foto_url
) SELECT *, (now4 - prev4) AS delta FROM a WHERE prev4>0 AND now4 < prev4 ORDER BY delta ASC LIMIT 10");

// Hampir inactive: last absensi >21 hari yang lalu
$inactive = db_all("SELECT u.id,u.nama,u.foto_url, MAX(j.tanggal) AS last_act
                    FROM users u LEFT JOIN absensi a ON a.user_id=u.id AND a.hadir=1
                    LEFT JOIN jadwal j ON j.id=a.jadwal_id
                    WHERE u.role IN ('member','admin')
                    GROUP BY u.id,u.nama,u.foto_url
                    HAVING MAX(j.tanggal) IS NULL OR MAX(j.tanggal) < CURRENT_DATE - INTERVAL '21 days'
                    ORDER BY last_act ASC NULLS FIRST LIMIT 15");

// Prediksi dropout: skor = (hari sejak terakhir) + (3 * delta menurun)
$pred = [];
foreach ($inactive as $r) {
    $last = $r['last_act'] ? strtotime($r['last_act']) : strtotime('-1 year');
    $days = max(0, (int) round((time() - $last)/86400));
    $score = $days;
    $pred[] = ['nama'=>$r['nama'],'id'=>$r['id'],'foto_url'=>$r['foto_url'],'score'=>$score,'last'=>$r['last_act']];
}
usort($pred, fn($a,$b)=>$b['score']-$a['score']);
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> Statistik Kehadiran Pintar</h2>

<div class="alert alert-info border-0 shadow-sm small mb-3">
  <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Cara membaca statistik di halaman ini</div>
  <ul class="mb-0 ps-3">
    <li><b>Paling Sering Telat (60 hari)</b> — <code>Ø X mnt</code> = rata-rata keterlambatan, <code>Nx</code> = berapa kali telat dalam 60 hari terakhir.</li>
    <li><b>Absensi Menurun</b> — membandingkan jumlah kehadiran 4 minggu terakhir (<i>now4</i>) vs 4 minggu sebelumnya (<i>prev4</i>).
      Contoh <code>1 → 0 (-1)</code> artinya: minggu lalu hanya hadir 1 kali, sekarang 0 kali (turun 1 sesi). Semakin negatif <code>delta</code> = penurunan makin tajam.</li>
    <li><b>Hampir Inactive (&gt;21 hari)</b> — member yang terakhir hadir lebih dari 21 hari yang lalu (atau belum pernah hadir). Tanggal di samping = hari terakhir tercatat hadir.</li>
    <li><b>Prediksi Dropout (skor risiko)</b> — skor sederhana berbasis seberapa lama member tidak aktif. <code>risk N</code> = sudah <b>N hari</b> tidak hadir di sesi mana pun.
      <ul class="mb-0">
        <li><code>risk 30</code> ≈ tidak aktif sekitar 1 bulan (perlu di-<i>follow up</i>).</li>
        <li><code>risk 365</code> ≈ tidak aktif &gt; 1 tahun (kemungkinan besar sudah <i>dropout</i>).</li>
      </ul>
    </li>
    <li>Semua angka dihitung otomatis dari tabel <code>absensi</code> + <code>jadwal</code>. Refresh halaman / data otomatis tiap beberapa detik.</li>
  </ul>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header">⏰ Paling Sering Telat (60 hari)</div>
    <ul class="list-group list-group-flush">
    <?php foreach($telat as $r): ?>
      <li class="list-group-item d-flex justify-content-between">
        <a href="/user.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($r['foto_url'] ?? null, $r['nama'], false, 26) ?></a>
        <span><span class="badge bg-warning text-dark">Ø <?= number_format((float)$r['rata'],1) ?> mnt</span> <span class="badge bg-secondary"><?= (int)$r['kali'] ?>x</span></span>
      </li>
    <?php endforeach; if(!$telat): ?><li class="list-group-item text-muted small text-center">Tidak ada data telat.</li><?php endif; ?>
    </ul></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header">📉 Absensi Menurun</div>
    <ul class="list-group list-group-flush">
    <?php foreach($drop as $r): ?>
      <li class="list-group-item d-flex justify-content-between">
        <a href="/user.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($r['foto_url'] ?? null, $r['nama'], false, 26) ?></a>
        <span class="badge bg-danger"><?= (int)$r['prev4'] ?> → <?= (int)$r['now4'] ?> (<?= (int)$r['delta'] ?>)</span>
      </li>
    <?php endforeach; if(!$drop): ?><li class="list-group-item text-muted small text-center">Tidak ada penurunan.</li><?php endif; ?>
    </ul></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header">😴 Hampir Inactive (>21 hari)</div>
    <ul class="list-group list-group-flush">
    <?php foreach($inactive as $r): ?>
      <li class="list-group-item d-flex justify-content-between">
        <a href="/user.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($r['foto_url'] ?? null, $r['nama'], false, 26) ?></a>
        <small class="text-muted">terakhir: <?= htmlspecialchars($r['last_act'] ?? 'belum pernah') ?></small>
      </li>
    <?php endforeach; ?>
    </ul></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header">🚨 Prediksi Dropout (skor risiko)</div>
    <ol class="list-group list-group-flush list-group-numbered">
    <?php foreach(array_slice($pred,0,10) as $p): ?>
      <li class="list-group-item d-flex justify-content-between">
        <a href="/user.php?id=<?= $p['id'] ?>" class="text-decoration-none"><?= user_name_with_avatar($p['foto_url'] ?? null, $p['nama'], false, 26) ?></a>
        <span class="badge bg-dark">risk <?= (int)$p['score'] ?></span>
      </li>
    <?php endforeach; ?>
    </ol></div>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>

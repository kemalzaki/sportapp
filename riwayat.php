<?php
// Riwayat + Leaderboard + Riwayat Aktivitas (dengan bukti popup)
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Riwayat & Leaderboard';
$u = current_user();

$period = $_GET['period'] ?? 'monthly';
$cat = $_GET['cat'] ?? 'konsisten';

$periodSql = "j.tanggal >= CURRENT_DATE - INTERVAL '30 days'";
if ($period === 'weekly') $periodSql = "j.tanggal >= CURRENT_DATE - INTERVAL '7 days'";
if ($period === 'all')    $periodSql = "TRUE";

$uPeriodSql = str_replace('j.tanggal','uh.tanggal',$periodSql);

$lb = [];
if ($cat === 'konsisten') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COUNT(*) AS skor
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id JOIN users u ON u.id=a.user_id
                  WHERE a.hadir=1 AND $periodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'jarak') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.jarak_km),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} elseif ($cat === 'pace') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, MIN(uh.pace_detik) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql AND uh.pace_detik IS NOT NULL
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor ASC LIMIT 20");
} elseif ($cat === 'kalori') {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url, COALESCE(SUM(uh.kalori),0) AS skor
                  FROM upload_harian uh JOIN users u ON u.id=uh.user_id
                  WHERE $uPeriodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
} else {
    $lb = db_all("SELECT u.id,u.nama,u.foto_url,
                    (COUNT(DISTINCT j.jenis)*10 + COUNT(*)) AS skor
                  FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id JOIN users u ON u.id=a.user_id
                  WHERE a.hadir=1 AND $periodSql
                  GROUP BY u.id,u.nama,u.foto_url ORDER BY skor DESC LIMIT 20");
}

$riwayat = db_all("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto,
                          (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id AND a.hadir=1) AS hadir,
                          (SELECT COUNT(*) FROM absensi a WHERE a.jadwal_id=j.id) AS total,
                          (SELECT COUNT(*) FROM member_eksternal me WHERE me.jadwal_id=j.id) AS tamu
                   FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id
                   ORDER BY j.tanggal DESC LIMIT 50");

// detail peserta per sesi untuk popup
$sesiDetail = [];
$jids = array_map(fn($r)=>(int)$r['id'], $riwayat);
if ($jids) {
    $inList = implode(',', $jids);
    $absRows = db_all("SELECT a.jadwal_id, a.hadir, a.keterangan, u.nama, u.foto_url
                       FROM absensi a JOIN users u ON u.id=a.user_id
                       WHERE a.jadwal_id IN ($inList) ORDER BY a.hadir DESC, u.nama");
    foreach ($absRows as $ar) $sesiDetail[(int)$ar['jadwal_id']]['anggota'][] = $ar;
    $tamuRows = db_all("SELECT jadwal_id, nama_tamu AS nama FROM member_eksternal WHERE jadwal_id IN ($inList)");
    foreach ($tamuRows as $tr) $sesiDetail[(int)$tr['jadwal_id']]['tamu'][] = $tr;
}

// Riwayat aktivitas publik (semua user)
$publicActs = db_all("SELECT uh.id,uh.tanggal,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.kalori,uh.file_path,uh.deskripsi,u.id AS uid,u.nama,u.foto_url
                      FROM upload_harian uh JOIN users u ON u.id=uh.user_id ORDER BY uh.tanggal DESC LIMIT 30");

// Riwayat aktivitas saya (untuk bukti popup)
$myActs = $u ? db_all("SELECT id,tanggal,jenis,durasi_menit,jarak_km,kalori,file_path,deskripsi
                       FROM upload_harian WHERE user_id=$1 ORDER BY tanggal DESC LIMIT 30", [(int)$u['id']]) : [];
include __DIR__.'/includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-clock-history text-primary"></i> Riwayat & Leaderboard</h2>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2 align-items-end">
    <div class="col-md-3"><label class="small fw-semibold">Kategori</label>
      <select name="cat" class="form-select" onchange="this.form.submit()">
        <?php foreach(['konsisten'=>'Paling Konsisten','jarak'=>'Jarak Terbanyak','pace'=>'Pace Terbaik','kalori'=>'Kalori Terbanyak','all'=>'All Rounder'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $cat===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="small fw-semibold">Periode</label>
      <select name="period" class="form-select" onchange="this.form.submit()">
        <option value="weekly"  <?= $period==='weekly'?'selected':'' ?>>Mingguan</option>
        <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Bulanan</option>
        <option value="all"     <?= $period==='all'?'selected':'' ?>>All-time</option>
      </select></div>
  </form>
</div></div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Leaderboard — <?= htmlspecialchars($cat) ?></div>
    <ol class="list-group list-group-flush list-group-numbered">
      <?php foreach($lb as $i=>$row): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <a href="/user.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none">
            <?= user_name_with_avatar($row['foto_url'] ?? null, $row['nama'], false, 28) ?>
          </a>
          <span class="badge bg-primary rounded-pill">
            <?php
              if ($cat==='jarak') echo number_format((float)$row['skor'],2).' km';
              elseif ($cat==='pace') { $s=(int)$row['skor']; echo sprintf('%d:%02d /km', intdiv($s,60), $s%60); }
              elseif ($cat==='kalori') echo number_format((int)$row['skor']).' kkal';
              else echo (int)$row['skor'];
            ?>
          </span>
        </li>
      <?php endforeach; if(!$lb): ?><li class="list-group-item text-muted text-center small">Belum ada data.</li><?php endif; ?>
    </ol></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-calendar3 text-primary"></i> Riwayat Sesi</div>
    <div class="table-responsive"><table class="table table-hover table-stack mb-0" data-paginate="10">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Tempat</th><th>Koordinator</th><th>Durasi</th><th>Tamu Eks.</th><th>Kehadiran</th></tr></thead>
      <tbody>
      <?php foreach($riwayat as $r): ?>
        <tr>
          <td data-label="Tanggal"><?= htmlspecialchars($r['tanggal']) ?> <span class="pill"><?= hari_id($r['tanggal']) ?></span></td>
          <td data-label="Jenis"><?= htmlspecialchars($r['jenis']) ?></td>
          <td data-label="Tempat"><?= htmlspecialchars($r['tempat']) ?></td>
          <td data-label="Koordinator"><?= user_name_with_avatar($r['koord_foto'] ?? null, $r['koord'] ?? '-', false, 22) ?></td>
          <td data-label="Durasi"><?= !empty($r['durasi_menit']) ? (int)$r['durasi_menit'].' mnt' : '<span class="text-muted small">—</span>' ?></td>
          <td data-label="Tamu"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'tamu')" class="badge bg-info-subtle text-info-emphasis text-decoration-none" title="Klik untuk lihat nama tamu"><?= (int)$r['tamu'] ?> <i class="bi bi-zoom-in"></i></a></td>
          <td data-label="Hadir"><a href="#" onclick="event.preventDefault();showSesi(<?= (int)$r['id'] ?>,'anggota')" class="text-decoration-none" title="Klik untuk lihat siapa yang hadir"><?= (int)$r['hadir'] ?>/<?= (int)$r['total'] ?> <i class="bi bi-zoom-in text-muted small"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </div>

    <!-- Riwayat aktivitas publik (semua member) -->
    <div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-globe text-primary"></i> Riwayat Aktivitas Publik</div>
    <div class="table-responsive"><table class="table table-hover mb-0" data-paginate="10">
      <thead><tr><th>Tanggal</th><th>Member</th><th>Jenis</th><th>Durasi</th><th>Jarak</th><th>Bukti</th></tr></thead>
      <tbody>
        <?php foreach($publicActs as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['tanggal']) ?></td>
          <td><a class="text-decoration-none" href="/user.php?id=<?= (int)$a['uid'] ?>"><?= user_name_with_avatar($a['foto_url'], $a['nama'], false, 22) ?></a></td>
          <td><span class="pill"><?= htmlspecialchars($a['jenis']) ?></span></td>
          <td><?= (int)$a['durasi_menit'] ?> mnt</td>
          <td><?= htmlspecialchars($a['jarak_km'] ?? '0') ?> km</td>
          <td>
            <?php if(!empty($a['file_path'])): ?>
              <a href="#" onclick="showBukti(event,'<?= htmlspecialchars($a['file_path'],ENT_QUOTES) ?>','<?= htmlspecialchars($a['tanggal']) ?>')">
                <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="bukti" style="height:38px;width:38px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd;">
              </a>
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(!$publicActs): ?><tr><td colspan="6" class="text-center text-muted small py-3">Belum ada aktivitas.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>

    <?php if($u): ?>
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-activity text-primary"></i> Riwayat Aktifitas Saya</div>
    <div class="table-responsive"><table class="table table-hover mb-0" data-paginate="10">
      <thead><tr><th>Tanggal</th><th>Jenis</th><th>Durasi</th><th>Jarak</th><th>Kalori</th><th>Bukti</th></tr></thead>
      <tbody>
        <?php foreach($myActs as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['tanggal']) ?></td>
          <td><span class="pill"><?= htmlspecialchars($a['jenis']) ?></span></td>
          <td><?= (int)$a['durasi_menit'] ?> mnt</td>
          <td><?= htmlspecialchars($a['jarak_km']) ?> km</td>
          <td><?= (int)$a['kalori'] ?></td>
          <td>
            <?php if($a['file_path']): ?>
              <a href="#" onclick="showBukti(event,'<?= htmlspecialchars($a['file_path'],ENT_QUOTES) ?>','<?= htmlspecialchars($a['tanggal']) ?>')">
                <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="bukti" style="height:42px;width:42px;object-fit:cover;border-radius:6px;cursor:zoom-in;border:1px solid #ddd;">
              </a>
            <?php else: ?>-<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(!$myActs): ?><tr><td colspan="6" class="text-center text-muted small py-3">Belum ada aktivitas.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bukti modal -->
<div class="modal fade" id="buktiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-image"></i> Bukti Aktivitas <small id="bDate" class="text-muted ms-2"></small></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body text-center"><img id="bImg" src="" style="max-width:100%;border-radius:8px;"></div>
      <div class="modal-footer"><a id="bOpen" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka di tab baru</a></div>
    </div>
  </div>
</div>
<script>
let _bModal=null;
function showBukti(ev, src, date){
  if(ev) ev.preventDefault();
  if(!_bModal) _bModal = new bootstrap.Modal(document.getElementById('buktiModal'));
  document.getElementById('bImg').src = src;
  document.getElementById('bOpen').href = src;
  document.getElementById('bDate').textContent = date || '';
  _bModal.show();
}
</script>
<?php
$_sesiJs = [];
foreach($riwayat as $r){
  $jid = (int)$r['id'];
  $_sesiJs[$jid] = [
    'tanggal'=>$r['tanggal'],'jenis'=>$r['jenis'],'tempat'=>$r['tempat'],
    'koord'=>$r['koord']??'-','durasi'=>(int)$r['durasi_menit'],
    'hadir'=>(int)$r['hadir'],'total'=>(int)$r['total'],
    'anggota'=>$sesiDetail[$jid]['anggota']??[], 'tamu'=>$sesiDetail[$jid]['tamu']??[],
  ];
}
?>
<!-- Sesi Detail modal -->
<div class="modal fade" id="sesiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar3"></i> Detail Sesi</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="sesiBody"></div>
    </div>
  </div>
</div>
<script>
const SESI_DATA = <?= json_encode($_sesiJs, JSON_UNESCAPED_UNICODE) ?>;
let _sModal=null;
function showSesi(id, focus){
  const d=SESI_DATA[id]; if(!d) return;
  if(!_sModal) _sModal=new bootstrap.Modal(document.getElementById('sesiModal'));
  let html=`<div class="mb-2"><span class="pill">${d.tanggal}</span> <span class="pill">${d.jenis}</span> <span class="pill">${d.tempat}</span></div>`;
  html+=`<div class="small text-muted mb-3">Koordinator: <b>${d.koord}</b> · Durasi: ${d.durasi||'-'} mnt · Hadir: <b>${d.hadir}/${d.total}</b></div>`;
  html+=`<h6 class="mb-2"><i class="bi bi-people"></i> Daftar Anggota Hadir</h6>`;
  const hadir = (d.anggota||[]).filter(a=>a.hadir==1);
  if(hadir.length){
    html+=`<div class="table-responsive"><table class="table table-sm align-middle" data-paginate="10"><thead><tr><th>Nama</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>`;
    d.anggota.forEach(a=>{
      const ava=a.foto_url?`<img src="${a.foto_url}" style="width:26px;height:26px;border-radius:50%;object-fit:cover" class="me-1">`:'';
      const st=a.hadir==1?'<span class="badge bg-success">Hadir</span>':'<span class="badge bg-secondary">Tidak hadir</span>';
      html+=`<tr><td>${ava}${a.nama||'-'}</td><td>${st}</td><td class="small text-muted">${a.keterangan||''}</td></tr>`;
    });
    html+=`</tbody></table></div>`;
  } else html+=`<div class="text-muted small">Belum ada data absensi.</div>`;
  html+=`<h6 class="mt-3 mb-2"><i class="bi bi-person-plus"></i> Tamu Eksternal (${(d.tamu||[]).length})</h6>`;
  if((d.tamu||[]).length){
    html+=`<ul class="mb-0">`;
    d.tamu.forEach(t=>{html+=`<li>${t.nama||'-'}</li>`;});
    html+=`</ul>`;
  } else { html+=`<div class="text-muted small">Tidak ada tamu eksternal.</div>`; }
  document.getElementById('sesiBody').innerHTML=html;
  _sModal.show();
}
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

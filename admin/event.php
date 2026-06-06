<?php
// Admin: kelola event/tournament + bracket sederhana
// REVISI: dukung tipe selain olahraga (mis. "Nyate Bersama") + multi-select member
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_role('admin');
$pageTitle = 'Admin · Event';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='create') {
        // jenis: bisa dari select olahraga atau input bebas (mis: "Nyate Bersama")
        $jenisFinal = trim($_POST['jenis_custom'] ?? '') !== ''
            ? trim($_POST['jenis_custom'])
            : ($_POST['jenis'] ?? 'Lainnya');
        $tipeFinal  = trim($_POST['tipe_custom'] ?? '') !== ''
            ? trim($_POST['tipe_custom'])
            : ($_POST['tipe'] ?? 'sosial');

        $newRow = db_one("INSERT INTO event(nama,jenis,tipe,deskripsi,tanggal_mulai,tanggal_selesai,jam_mulai,jam_selesai,lokasi,batas_daftar,hadiah,status,created_by)
                      VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,'open',$12) RETURNING id",
            [trim($_POST['nama']), $jenisFinal, $tipeFinal, $_POST['deskripsi'] ?? '',
             $_POST['tanggal_mulai'], $_POST['tanggal_selesai'] ?: null,
             $_POST['jam_mulai'] ?: null, $_POST['jam_selesai'] ?: null,
             trim($_POST['lokasi'] ?? '') ?: null, $_POST['batas_daftar'] ?: null,
             $_POST['hadiah'] ?? '', (int)current_user()['id']]);
        $newId = (int)$newRow['id'];

        // Auto-tambahkan member yang dicentang sebagai peserta
        $pesertaIds = $_POST['peserta_ids'] ?? [];
        if (is_array($pesertaIds)) {
            foreach ($pesertaIds as $uidPick) {
                $uidPick = (int)$uidPick;
                if ($uidPick > 0) {
                    try { db_exec("INSERT INTO event_peserta(event_id,user_id) VALUES($1,$2)", [$newId, $uidPick]); } catch (Throwable $e) {}
                }
            }
        }
        notify_all('event', '🎉 Event baru: '.$_POST['nama'], 'Detail di menu Event.', '/event.php?id='.$newId);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM event WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='update') {
        // Revisi 6 Juni 2026 — edit event (termasuk waktu)
        $eid = (int)$_POST['id'];
        $jenisFinal = trim($_POST['jenis_custom'] ?? '') !== '' ? trim($_POST['jenis_custom']) : ($_POST['jenis'] ?? 'Lainnya');
        $tipeFinal  = trim($_POST['tipe_custom']  ?? '') !== '' ? trim($_POST['tipe_custom'])  : ($_POST['tipe']  ?? 'sosial');
        db_exec("UPDATE event SET nama=$1, jenis=$2, tipe=$3, deskripsi=$4,
                    tanggal_mulai=$5, tanggal_selesai=$6, jam_mulai=$7, jam_selesai=$8,
                    lokasi=$9, batas_daftar=$10, hadiah=$11
                 WHERE id=$12",
            [trim($_POST['nama']), $jenisFinal, $tipeFinal, $_POST['deskripsi'] ?? '',
             $_POST['tanggal_mulai'], $_POST['tanggal_selesai'] ?: null,
             $_POST['jam_mulai'] ?: null, $_POST['jam_selesai'] ?: null,
             trim($_POST['lokasi'] ?? '') ?: null, $_POST['batas_daftar'] ?: null,
             $_POST['hadiah'] ?? '', $eid]);
    } elseif ($a==='status') {
        db_exec("UPDATE event SET status=$1 WHERE id=$2", [$_POST['status'], (int)$_POST['id']]);
    } elseif ($a==='add_match') {
        db_exec("INSERT INTO event_match(event_id,round,tim_a,tim_b) VALUES($1,$2,$3,$4)",
            [(int)$_POST['event_id'], (int)$_POST['round'], (int)$_POST['tim_a'], (int)$_POST['tim_b']]);
        foreach ([(int)$_POST['tim_a'], (int)$_POST['tim_b']] as $tid) {
            if ($tid > 0) {
                try { db_exec("INSERT INTO event_peserta(event_id,tim_id) SELECT $1,$2
                               WHERE NOT EXISTS (SELECT 1 FROM event_peserta WHERE event_id=$1 AND tim_id=$2)",
                              [(int)$_POST['event_id'], $tid]); } catch (Throwable $e) {}
            }
        }
    } elseif ($a==='add_peserta') {
        $eid = (int)$_POST['event_id'];
        $tid = (int)($_POST['tim_id'] ?? 0) ?: null;
        $uid = (int)($_POST['user_id'] ?? 0) ?: null;
        if ($tid || $uid) {
            try { db_exec("INSERT INTO event_peserta(event_id,tim_id,user_id) VALUES($1,$2,$3)", [$eid,$tid,$uid]); } catch (Throwable $e) {}
        }
    } elseif ($a==='add_peserta_bulk') {
        $eid = (int)$_POST['event_id'];
        $uids = $_POST['user_ids'] ?? [];
        if (is_array($uids)) {
            foreach ($uids as $uPick) {
                $uPick = (int)$uPick;
                if ($uPick > 0) {
                    try { db_exec("INSERT INTO event_peserta(event_id,user_id) VALUES($1,$2)", [$eid, $uPick]); } catch (Throwable $e) {}
                }
            }
        }
    } elseif ($a==='del_peserta') {
        db_exec("DELETE FROM event_peserta WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='update_score') {
        $sa = (int)$_POST['score_a']; $sb = (int)$_POST['score_b'];
        $win = $sa > $sb ? (int)$_POST['tim_a'] : ($sb > $sa ? (int)$_POST['tim_b'] : null);
        db_exec("UPDATE event_match SET score_a=$1, score_b=$2, pemenang=$3 WHERE id=$4",
            [$sa, $sb, $win, (int)$_POST['id']]);
    } elseif ($a==='save_absensi') {
        // CRUD absensi langsung dari halaman admin event (mirip event_absensi.php)
        $eid = (int)($_POST['event_id'] ?? 0);
        $allowed = ['hadir','izin','sakit','telat','absen'];
        foreach (($_POST['status'] ?? []) as $pid => $st) {
            $pid = (int)$pid;
            $st = in_array($st, $allowed, true) ? $st : 'absen';
            $ket = trim((string)($_POST['keterangan'][$pid] ?? ''));
            try {
                db_exec("UPDATE event_peserta SET status=$1, keterangan=$2 WHERE id=$3 AND event_id=$4",
                    [$st, $ket ?: null, $pid, $eid]);
            } catch (Throwable $e) {}
        }
    } elseif ($a==='upload_banner') {
        $eid = (int)($_POST['event_id'] ?? 0);
        if ($eid && !empty($_FILES['banner']['tmp_name']) && is_uploaded_file($_FILES['banner']['tmp_name'])) {
            $mime = mime_content_type($_FILES['banner']['tmp_name']);
            if (in_array($mime, ['image/jpeg','image/png','image/webp'], true) && $_FILES['banner']['size'] < 5*1024*1024) {
                $ext = $mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');
                $safe = 'event-'.$eid.'-'.time().'.'.$ext;
                require_once __DIR__.'/../config/imagekit.php';
                global $imageKit;
                try {
                    $upl = $imageKit->uploadFile([
                        'file' => base64_encode(file_get_contents($_FILES['banner']['tmp_name'])),
                        'fileName' => $safe,
                        'folder' => '/sportapp/event'
                    ]);
                    if (!$upl->error) {
                        db_exec("UPDATE event SET banner_url=$1 WHERE id=$2", [$upl->result->url, $eid]);
                    }
                } catch (Throwable $e) {}
            }
        }
    } elseif ($a==='delete_banner') {
        $eid = (int)($_POST['event_id'] ?? 0);
        if ($eid) db_exec("UPDATE event SET banner_url=NULL WHERE id=$1", [$eid]);
    }
    header('Location: event.php'); exit;
}

$events = db_all("SELECT * FROM event ORDER BY tanggal_mulai DESC");
$tims = db_all("SELECT id,nama,jenis FROM tim ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama') ?: ['Jogging','Badminton','Futsal'];
// Tambahkan opsi non-olahraga sebagai preset
$jenisNonOlahraga = ['Nyate Bersama','Makan Bersama','Arisan','Pengajian','Outing','Rapat Komunitas','Bakti Sosial'];
$allMembers = db_all("SELECT id, nama, foto_url FROM users WHERE role IN ('member','admin') ORDER BY nama");
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-calendar-heart text-danger"></i> Manajemen Event &amp; Kegiatan</h2>
<p class="text-muted small">Bisa untuk event olahraga (tournament/challenge) maupun kegiatan sosial seperti <em>Nyate Bersama</em>, arisan, pengajian, dll.</p>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Event / Kegiatan</div>
<div class="card-body"><form method="post" class="row g-2">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="create">
  <div class="col-md-5"><label class="small fw-semibold">Nama</label><input class="form-control" name="nama" required placeholder="cth: Nyate Bersama Akhir Bulan"></div>

  <div class="col-md-3"><label class="small fw-semibold">Jenis (preset)</label>
    <select name="jenis" class="form-select">
      <optgroup label="Olahraga">
        <?php foreach($jenisList as $j): ?><option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
      </optgroup>
      <optgroup label="Non-Olahraga / Sosial">
        <?php foreach($jenisNonOlahraga as $j): ?><option value="<?= htmlspecialchars($j) ?>"><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
      </optgroup>
    </select>
  </div>
  <div class="col-md-4"><label class="small fw-semibold">Jenis Kustom (opsional)</label>
    <input class="form-control" name="jenis_custom" placeholder="Kosongkan jika pakai preset di atas">
  </div>

  <div class="col-md-3"><label class="small fw-semibold">Tipe</label>
    <select name="tipe" class="form-select">
      <option value="challenge">Challenge</option>
      <option value="tournament">Tournament</option>
      <option value="sparring">Sparring</option>
      <option value="mini">Mini Tournament</option>
      <option value="sosial">Sosial / Gathering</option>
      <option value="kuliner">Kuliner Bersama</option>
      <option value="lainnya">Lainnya</option>
    </select>
  </div>
  <div class="col-md-3"><label class="small fw-semibold">Tipe Kustom (opsional)</label>
    <input class="form-control" name="tipe_custom" placeholder="cth: Bakti Sosial">
  </div>

  <div class="col-md-3"><label class="small fw-semibold">Tanggal Mulai</label><input type="date" name="tanggal_mulai" class="form-control" required></div>
  <div class="col-md-3"><label class="small fw-semibold">Tanggal Selesai</label><input type="date" name="tanggal_selesai" class="form-control"></div>
  <div class="col-md-2"><label class="small fw-semibold">Jam Mulai</label><input type="time" name="jam_mulai" class="form-control"></div>
  <div class="col-md-2"><label class="small fw-semibold">Jam Selesai</label><input type="time" name="jam_selesai" class="form-control"></div>
  <div class="col-md-2"><label class="small fw-semibold">Batas Pendaftaran</label><input type="date" name="batas_daftar" class="form-control"></div>

  <div class="col-md-6"><label class="small fw-semibold">Lokasi / Tempat</label><input class="form-control" name="lokasi" placeholder="cth: Rumah Pak RT / GOR Senayan"></div>
  <div class="col-md-6"><label class="small fw-semibold">Hadiah / Catatan</label><input class="form-control" name="hadiah" placeholder="cth: Konsumsi gratis untuk peserta"></div>

  <div class="col-12"><label class="small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2" placeholder="Penjelasan acara, rundown singkat, dll"></textarea></div>

  <div class="col-12">
    <label class="small fw-semibold d-flex justify-content-between align-items-center">
      <span><i class="bi bi-people-fill text-primary"></i> Pilih Member yang Ikut Serta</span>
      <span>
        <button type="button" class="btn btn-sm btn-link p-0 me-2" onclick="document.querySelectorAll('.js-peserta-create').forEach(c=>c.checked=true)">Pilih semua</button>
        <button type="button" class="btn btn-sm btn-link p-0 text-muted" onclick="document.querySelectorAll('.js-peserta-create').forEach(c=>c.checked=false)">Bersihkan</button>
      </span>
    </label>
    <div class="border rounded p-2" style="max-height:220px;overflow:auto;">
      <div class="row g-1">
        <?php foreach($allMembers as $m): ?>
          <div class="col-sm-6 col-md-4 col-lg-3">
            <label class="d-flex align-items-center gap-2 small p-1 rounded peserta-pick">
              <input type="checkbox" class="form-check-input js-peserta-create" name="peserta_ids[]" value="<?= (int)$m['id'] ?>">
              <span class="text-truncate"><?= htmlspecialchars($m['nama']) ?></span>
            </label>
          </div>
        <?php endforeach; ?>
        <?php if(!$allMembers): ?><div class="text-muted small">Belum ada member.</div><?php endif; ?>
      </div>
    </div>
    <small class="text-muted">Member yang dicentang akan langsung terdaftar sebagai peserta event.</small>
  </div>

  <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Simpan Event</button></div>
</form></div></div>

<?php foreach($events as $e):
  $ms = db_all("SELECT m.*, a.nama AS a_n, b.nama AS b_n FROM event_match m
                LEFT JOIN tim a ON a.id=m.tim_a LEFT JOIN tim b ON b.id=m.tim_b
                WHERE m.event_id=$1 ORDER BY round,id", [(int)$e['id']]);
  $ps = db_all(
    "SELECT ep.id, ep.user_id, ep.tim_id, ep.status, ep.keterangan,
            u.nama AS user_nama, u.foto_url, t.nama AS tim_nama
     FROM (
       SELECT DISTINCT ON (COALESCE(user_id,0), COALESCE(tim_id,0)) *
       FROM event_peserta
       WHERE event_id=$1
       ORDER BY COALESCE(user_id,0), COALESCE(tim_id,0),
         CASE WHEN status IS NOT NULL AND status<>'absen' THEN 0 ELSE 1 END, id
     ) ep
     LEFT JOIN users u ON u.id=ep.user_id
     LEFT JOIN tim   t ON t.id=ep.tim_id
     ORDER BY COALESCE(u.nama, t.nama)", [(int)$e['id']]);
  $pesertaUidSet = array_filter(array_map(fn($r)=>(int)$r['user_id'], $ps));
?>
<div class="card shadow-sm mb-3"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2">
    <div><h5 class="mb-1"><?= htmlspecialchars($e['nama']) ?>
        <small class="text-muted">· <span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($e['jenis']) ?></span>
        <span class="badge bg-secondary"><?= htmlspecialchars($e['tipe']) ?></span></small></h5>
      <div class="small text-muted"><?= htmlspecialchars($e['tanggal_mulai']) ?> → <?= htmlspecialchars($e['tanggal_selesai'] ?? '-') ?> · Status: <?= htmlspecialchars($e['status']) ?> · Peserta: <?= count($ps) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="status"><input type="hidden" name="id" value="<?= $e['id'] ?>">
        <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
          <?php foreach(['open','ongoing','closed','done'] as $s): ?><option <?= $s===$e['status']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
      </form>
      <form method="post" onsubmit="return confirm('Hapus event?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editEvt<?= (int)$e['id'] ?>" title="Edit event (termasuk waktu)">
        <i class="bi bi-pencil-square"></i> Edit
      </button>
    </div>
  </div>

  <!-- Modal Edit Event (revisi 6 Juni 2026) -->
  <div class="modal fade" id="editEvt<?= (int)$e['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form method="post" class="modal-content">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update">
        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Event</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <div class="col-md-12"><label class="small fw-semibold">Nama Event</label>
            <input class="form-control" name="nama" required value="<?= htmlspecialchars($e['nama']) ?>"></div>
          <div class="col-md-4"><label class="small fw-semibold">Jenis (preset)</label>
            <select name="jenis" class="form-select">
              <optgroup label="Olahraga">
                <?php foreach($jenisList as $j): ?><option value="<?= htmlspecialchars($j) ?>" <?= $e['jenis']===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
              </optgroup>
              <optgroup label="Non-Olahraga / Sosial">
                <?php foreach($jenisNonOlahraga as $j): ?><option value="<?= htmlspecialchars($j) ?>" <?= $e['jenis']===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="col-md-4"><label class="small fw-semibold">Jenis Kustom</label>
            <input class="form-control" name="jenis_custom" placeholder="(opsional, override preset)"></div>
          <div class="col-md-4"><label class="small fw-semibold">Tipe</label>
            <select name="tipe" class="form-select">
              <?php foreach(['challenge','tournament','sparring','mini','sosial','kuliner','lainnya'] as $tp): ?>
                <option value="<?= $tp ?>" <?= $e['tipe']===$tp?'selected':'' ?>><?= $tp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><label class="small fw-semibold">Tanggal Mulai</label>
            <input type="date" name="tanggal_mulai" class="form-control" required value="<?= htmlspecialchars($e['tanggal_mulai']) ?>"></div>
          <div class="col-md-3"><label class="small fw-semibold">Tanggal Selesai</label>
            <input type="date" name="tanggal_selesai" class="form-control" value="<?= htmlspecialchars($e['tanggal_selesai'] ?? '') ?>"></div>
          <div class="col-md-2"><label class="small fw-semibold">Jam Mulai</label>
            <input type="time" name="jam_mulai" class="form-control" value="<?= htmlspecialchars(substr($e['jam_mulai']??'',0,5)) ?>"></div>
          <div class="col-md-2"><label class="small fw-semibold">Jam Selesai</label>
            <input type="time" name="jam_selesai" class="form-control" value="<?= htmlspecialchars(substr($e['jam_selesai']??'',0,5)) ?>"></div>
          <div class="col-md-2"><label class="small fw-semibold">Batas Daftar</label>
            <input type="date" name="batas_daftar" class="form-control" value="<?= htmlspecialchars($e['batas_daftar'] ?? '') ?>"></div>
          <div class="col-md-8"><label class="small fw-semibold">Lokasi</label>
            <input class="form-control" name="lokasi" value="<?= htmlspecialchars($e['lokasi'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="small fw-semibold">Hadiah</label>
            <input class="form-control" name="hadiah" value="<?= htmlspecialchars($e['hadiah'] ?? '') ?>"></div>
          <div class="col-md-12"><label class="small fw-semibold">Deskripsi</label>
            <textarea class="form-control" name="deskripsi" rows="3"><?= htmlspecialchars($e['deskripsi'] ?? '') ?></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- BANNER / GAMBAR EVENT (ImageKit) -->
  <div class="row g-2 mt-2 align-items-center border-top pt-2">
    <div class="col-md-3">
      <?php if(!empty($e['banner_url'])): ?>
        <img src="<?= htmlspecialchars($e['banner_url']) ?>" alt="Banner" class="img-fluid rounded" style="max-height:90px;object-fit:cover;width:100%">
      <?php else: ?>
        <div class="rounded bg-light text-muted small text-center py-4"><i class="bi bi-image"></i> Belum ada gambar</div>
      <?php endif; ?>
    </div>
    <div class="col-md-9">
      <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="upload_banner">
        <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
        <div class="col-md-7"><label class="small fw-semibold">Upload / Ganti Gambar Event (max 5MB, JPG/PNG/WEBP)</label>
          <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
      </form>
      <?php if(!empty($e['banner_url'])): ?>
        <form method="post" class="mt-1" onsubmit="return confirm('Hapus gambar event ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete_banner">
          <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus Gambar</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <hr>
  <div class="row g-2">
    <div class="col-lg-6">
      <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-people"></i> Peserta Member</h6>
      <?php if($ps): ?>
        <ul class="list-group list-group-flush small">
        <?php foreach($ps as $p): if(empty($p['user_id'])) continue; ?>
          <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span><?= htmlspecialchars($p['user_nama'] ?? '—') ?><?php if(!empty($p['tim_nama'])): ?> <small class="text-muted">(<?= htmlspecialchars($p['tim_nama']) ?>)</small><?php endif; ?></span>
            <form method="post" onsubmit="return confirm('Keluarkan peserta ini?')" class="d-inline">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="del_peserta">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button>
            </form>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php else: ?><div class="text-muted small">Belum ada peserta member.</div><?php endif; ?>

      <form method="post" class="mt-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="add_peserta_bulk">
        <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
        <details>
          <summary class="small fw-semibold text-primary" style="cursor:pointer"><i class="bi bi-person-plus"></i> Tambah member ke event ini</summary>
          <div class="border rounded p-2 mt-2" style="max-height:180px;overflow:auto;">
            <div class="row g-1">
              <?php foreach($allMembers as $m): if(in_array((int)$m['id'], $pesertaUidSet, true)) continue; ?>
                <div class="col-sm-6 col-md-4">
                  <label class="d-flex align-items-center gap-1 small">
                    <input type="checkbox" class="form-check-input" name="user_ids[]" value="<?= (int)$m['id'] ?>">
                    <span class="text-truncate"><?= htmlspecialchars($m['nama']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-plus"></i> Tambahkan terpilih</button>
        </details>
      </form>
    </div>

    <div class="col-lg-6">
      <h6 class="small fw-bold text-uppercase text-muted mb-2"><i class="bi bi-shield-shaded"></i> Bracket / Match (untuk olahraga)</h6>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add_match"><input type="hidden" name="event_id" value="<?= $e['id'] ?>">
        <div class="col-md-2"><label class="small fw-semibold">Round</label><input type="number" name="round" class="form-control form-control-sm" value="1" min="1"></div>
        <div class="col-md-4"><label class="small fw-semibold">Tim A</label><select name="tim_a" class="form-select form-select-sm"><?php foreach($tims as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="small fw-semibold">Tim B</label><select name="tim_b" class="form-select form-select-sm"><?php foreach($tims as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus"></i></button></div>
      </form>
      <?php if($ms): ?>
      <div class="table-responsive mt-2"><table class="table table-sm align-middle">
        <thead><tr><th>R</th><th>Tim A</th><th>Skor</th><th>Tim B</th><th></th></tr></thead><tbody>
        <?php foreach($ms as $m): ?>
        <tr>
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="update_score">
            <input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tim_a" value="<?= (int)$m['tim_a'] ?>"><input type="hidden" name="tim_b" value="<?= (int)$m['tim_b'] ?>">
          <td><?= (int)$m['round'] ?></td>
          <td><?= htmlspecialchars($m['a_n'] ?? '-') ?></td>
          <td style="width:160px"><div class="input-group input-group-sm"><input name="score_a" type="number" value="<?= (int)$m['score_a'] ?>" class="form-control"><span class="input-group-text">-</span><input name="score_b" type="number" value="<?= (int)$m['score_b'] ?>" class="form-control"></div></td>
          <td><?= htmlspecialchars($m['b_n'] ?? '-') ?></td>
          <td><button class="btn btn-sm btn-primary">Simpan</button></td>
          </form>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CRUD ABSENSI INLINE (terintegrasi dari event_absensi.php) -->
  <details class="mt-3 border-top pt-3">
    <summary class="small fw-semibold text-success" style="cursor:pointer">
      <i class="bi bi-clipboard2-check"></i> Input / Kelola Absensi Kehadiran Peserta
      <a href="/admin/event_absensi.php?id=<?= (int)$e['id'] ?>" class="ms-2 small text-decoration-none">(buka di halaman penuh →)</a>
    </summary>
    <?php
      $absOpts = [
        'hadir' => ['Hadir','success','bi-check-circle'],
        'telat' => ['Telat','warning','bi-clock-history'],
        'izin'  => ['Izin','info','bi-envelope'],
        'sakit' => ['Sakit','danger','bi-bandaid'],
        'absen' => ['Absen','secondary','bi-x-circle'],
      ];
      $absCnt = ['hadir'=>0,'telat'=>0,'izin'=>0,'sakit'=>0,'absen'=>0];
      foreach($ps as $pp){ $ss=$pp['status']?:'absen'; if(isset($absCnt[$ss])) $absCnt[$ss]++; }
    ?>
    <div class="mt-2 mb-2">
      <?php foreach($absOpts as $k=>$o): if($absCnt[$k]): ?>
        <span class="badge bg-<?= $o[1] ?>-subtle text-<?= $o[1] ?>"><?= $o[0] ?>: <?= $absCnt[$k] ?></span>
      <?php endif; endforeach; ?>
    </div>
    <?php if($ps): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="save_absensi">
      <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
      <div class="table-responsive"><table class="table table-sm align-middle mb-2">
        <thead class="table-light small"><tr><th>Peserta</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach($ps as $pp):
          $cur = $pp['status'] ?: 'absen';
          $lab = $pp['user_nama'] ?? ($pp['tim_nama'] ? 'Tim: '.$pp['tim_nama'] : '—');
        ?>
          <tr>
            <td class="small"><?= htmlspecialchars($lab) ?></td>
            <td>
              <div class="btn-group btn-group-sm" role="group">
              <?php foreach($absOpts as $k=>$o): $rid='es_'.$pp['id'].'_'.$k; ?>
                <input type="radio" class="btn-check" name="status[<?= $pp['id'] ?>]" id="<?= $rid ?>" value="<?= $k ?>" <?= $cur===$k?'checked':'' ?>>
                <label class="btn btn-outline-<?= $o[1] ?>" for="<?= $rid ?>" title="<?= $o[0] ?>"><i class="bi <?= $o[2] ?>"></i></label>
              <?php endforeach; ?>
              </div>
            </td>
            <td><input class="form-control form-control-sm" name="keterangan[<?= $pp['id'] ?>]" value="<?= htmlspecialchars($pp['keterangan'] ?? '') ?>" placeholder="(opsional)"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <button class="btn btn-sm btn-success"><i class="bi bi-save"></i> Simpan Absensi</button>
    </form>
    <?php else: ?>
      <div class="text-muted small">Tambah peserta dulu di atas sebelum input absensi.</div>
    <?php endif; ?>
  </details>
</div></div>
<?php endforeach; ?>

<style>
.peserta-pick:hover{background:#f8fafc;cursor:pointer;}
</style>
<?php include __DIR__.'/../includes/footer.php'; ?>

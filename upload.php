<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require_login();
$pageTitle='Upload Aktivitas Harian';
$msg=''; $err='';
$u = current_user();

// ---- Handle Delete ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $row = db_one("SELECT file_path FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
    if ($row) {
        if (!empty($row['file_path'])) {
            $abs = __DIR__ . '/' . ltrim(str_replace('\\', '/', $row['file_path']), '/');
            if (is_file($abs)) @unlink($abs);
        }
        db_exec("DELETE FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
        $msg = 'Aktivitas dihapus.';
    }
}

// ---- Handle Edit (update fields, optional new file) ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='edit') {
    csrf_check();
    $id      = (int)($_POST['id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $durasi  = (int)($_POST['durasi'] ?? 0);
    $jarak   = (float)($_POST['jarak'] ?? 0);
    $kalori  = (int)($_POST['kalori'] ?? 0);
    $desk    = trim($_POST['deskripsi'] ?? '');

    $old = db_one("SELECT * FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
    if (!$old) { $err='Data tidak ditemukan.'; }
    else {
        $filePath = $old['file_path'];
        if (!empty($_FILES['bukti']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
            $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama']) . "-{$tanggal}-Jogging-" . time() . "." . $ext;
            $bulanFolder = __DIR__.'/uploads/'.date('F_Y', strtotime($tanggal));
            if (!is_dir($bulanFolder)) mkdir($bulanFolder, 0775, true);
            $dest = $bulanFolder.'/'.$safe;
            if (move_uploaded_file($_FILES['bukti']['tmp_name'], $dest)) {
                if (!empty($old['file_path'])) {
                    $oa = __DIR__ . '/' . ltrim(str_replace('\\', '/', $old['file_path']), '/');
                    if (is_file($oa)) @unlink($oa);
                }
                $filePath = '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', str_replace(__DIR__, '', $dest)), '/');
            }
        }
        db_exec("UPDATE upload_harian SET tanggal=$1, jenis='Jogging', durasi_menit=$2, jarak_km=$3, kalori=$4, deskripsi=$5, file_path=$6
                 WHERE id=$7 AND user_id=$8",
                [$tanggal, $durasi, $jarak, $kalori, $desk, $filePath, $id, $u['id']]);
        $msg='Aktivitas diperbarui.';
    }
}

// ---- Handle Create ----
if ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['_action'])) {
    csrf_check();
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $jenis     = 'Jogging'; // dikunci sesuai revisi #15
    $durasi    = (int)($_POST['durasi'] ?? 0);
    $jarak     = (float)($_POST['jarak'] ?? 0);
    $kalori    = (int)($_POST['kalori'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    $filePath = null; $gdriveUrl = null;
    if (!empty($_FILES['bukti']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama']) . "-{$tanggal}-{$jenis}." . $ext;
        $bulanFolder = __DIR__.'/uploads/'.date('F_Y', strtotime($tanggal));
        if (!is_dir($bulanFolder)) mkdir($bulanFolder, 0775, true);
        $dest = $bulanFolder.'/'.$safe;
        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $dest)) {
            $filePath = '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', str_replace(__DIR__, '', $dest)), '/');
            /*
             * --- Google Drive integration (sesuai README.md) ---
             * 1. composer require google/apiclient:^2.15
             * 2. simpan service-account JSON di config/gdrive-credentials.json
             * 3. share folder "Aktivitas_Olahraga" ke email service account
             *
             *   $client = new Google\Client();
             *   $client->setAuthConfig(__DIR__.'/config/gdrive-credentials.json');
             *   $client->addScope(Google\Service\Drive::DRIVE_FILE);
             *   $drive = new Google\Service\Drive($client);
             *   $meta  = new Google\Service\Drive\DriveFile(['name'=>basename($dest), 'parents'=>[FOLDER_ID]]);
             *   $file  = $drive->files->create($meta, ['data'=>file_get_contents($dest),'mimeType'=>mime_content_type($dest),'uploadType'=>'multipart','fields'=>'webViewLink']);
             *   $gdriveUrl = $file->webViewLink;
             */
        } else { $err='Gagal upload file.'; }
    }

    if (!$err) {
        db_exec(
          "INSERT INTO upload_harian(user_id,tanggal,jenis,durasi_menit,jarak_km,kalori,deskripsi,file_path,gdrive_url)
           VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)",
           [$u['id'], $tanggal, $jenis, $durasi, $jarak, $kalori, $deskripsi, $filePath, $gdriveUrl]
        );
        $msg='Aktivitas berhasil dicatat.';
    }
}

$mine = db_all("SELECT * FROM upload_harian WHERE user_id=$1 ORDER BY tanggal DESC, id DESC LIMIT 50", [$u['id']]);
include __DIR__.'/includes/header.php'; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h2 class="mb-0"><i class="bi bi-cloud-upload text-primary"></i> Upload Aktivitas Harian</h2>
  <span class="badge bg-warning"><i class="bi bi-info-circle me-1"></i> Minimal 1 minggu 1x</span>
</div>

<?php if($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-plus-circle text-primary me-1"></i> Catat Aktivitas Baru</div>
    <div class="card-body">
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-megaphone"></i> <strong>Wajib:</strong> upload aktivitas <u>minimal 1 minggu 1 kali</u>.
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2"><label class="form-label small fw-semibold">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Jenis Olahraga</label>
          <input type="text" class="form-control" value="Jogging" readonly>
          <small class="text-muted">Saat ini upload harian hanya untuk Jogging.</small>
        </div>
        <div class="row g-2">
          <div class="col-4"><label class="form-label small fw-semibold">Durasi (menit)</label><input type="number" class="form-control" name="durasi" min="0" required></div>
          <div class="col-4"><label class="form-label small fw-semibold">Jarak (km)</label><input type="number" step="0.01" class="form-control" name="jarak" min="0" required></div>
          <div class="col-4"><label class="form-label small fw-semibold">Kalori</label><input type="number" class="form-control" name="kalori" min="0"></div>
        </div>
        <div class="my-2"><label class="form-label small fw-semibold">Deskripsi</label><textarea class="form-control" name="deskripsi" rows="2" placeholder="Rute, perasaan, dll."></textarea></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Bukti (screenshot smartwatch/Strava/foto)</label>
          <input type="file" class="form-control" name="bukti" accept="image/*"></div>
        <button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Simpan Aktivitas</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-list-check text-primary me-1"></i> Aktivitas Saya</span>
      <span class="badge bg-primary rounded-pill"><?= count($mine) ?></span>
    </div>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>No</th><th>Tanggal</th><th>Jenis</th><th>Durasi</th><th>Jarak</th><th>Kalori</th><th>Bukti</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($mine as $i=>$m): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= htmlspecialchars($m['tanggal']) ?></td>
            <td><span class="pill"><?= htmlspecialchars($m['jenis']) ?></span></td>
            <td><?= (int)$m['durasi_menit'] ?> mnt</td>
            <td><?= htmlspecialchars($m['jarak_km']) ?> km</td>
            <td><?= (int)$m['kalori'] ?></td>
            <td>
              <?php if($m['file_path']): ?>
                <button type="button" class="btn btn-sm btn-outline-primary"
                  onclick="showBukti('<?= htmlspecialchars($m['file_path']) ?>','<?= htmlspecialchars($m['tanggal']) ?>')">
                  <i class="bi bi-image"></i> Lihat
                </button>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $m['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus aktivitas ini?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; if(!$mine): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">Belum ada aktivitas. Ayo mulai jogging!</td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<!-- Modal Bukti -->
<div class="modal fade bukti-modal" id="buktiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-image"></i> Bukti Aktivitas <small id="buktiDate" class="text-muted ms-2"></small></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body text-center">
        <img id="buktiImg" src="" alt="Bukti aktivitas">
        <div id="buktiFallback" class="d-none mt-3">
          <p class="text-muted small mb-2">Pratinjau gagal? Buka file langsung:</p>
          <a id="buktiOpen" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka file</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Edit per item -->
<?php foreach($mine as $m): ?>
<div class="modal fade" id="editModal<?= $m['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" enctype="multipart/form-data" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $m['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Aktivitas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($m['tanggal']) ?>" required></div>
        <div class="row g-2">
          <div class="col-4"><label class="form-label small fw-semibold">Durasi</label><input type="number" name="durasi" class="form-control" value="<?= (int)$m['durasi_menit'] ?>"></div>
          <div class="col-4"><label class="form-label small fw-semibold">Jarak (km)</label><input type="number" step="0.01" name="jarak" class="form-control" value="<?= htmlspecialchars($m['jarak_km']) ?>"></div>
          <div class="col-4"><label class="form-label small fw-semibold">Kalori</label><input type="number" name="kalori" class="form-control" value="<?= (int)$m['kalori'] ?>"></div>
        </div>
        <div class="my-2"><label class="form-label small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($m['deskripsi'] ?? '') ?></textarea></div>
        <div class="mb-1"><label class="form-label small fw-semibold">Ganti Bukti (opsional)</label>
          <input type="file" class="form-control" name="bukti" accept="image/*"></div>
        <?php if($m['file_path']): ?><small class="text-muted">File saat ini: <?= htmlspecialchars(basename($m['file_path'])) ?></small><?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script>
let buktiModal = null;
function showBukti(src, date){
  if (!buktiModal) {
    buktiModal = new bootstrap.Modal(document.getElementById('buktiModal'));
  }
  const img = document.getElementById('buktiImg');
  const fb  = document.getElementById('buktiFallback');
  const op  = document.getElementById('buktiOpen');
  document.getElementById('buktiDate').textContent = date || '';
  img.classList.remove('d-none'); fb.classList.add('d-none');
  img.onerror = () => { img.classList.add('d-none'); fb.classList.remove('d-none'); op.href = src; };
  img.src = src; op.href = src;
  buktiModal.show();
}
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
$pageTitle='Upload Aktivitas Harian';
require_login();
$msg=''; $err='';
$u = current_user();
try { db_exec("ALTER TABLE upload_harian ADD COLUMN IF NOT EXISTS gear_sepatu VARCHAR(120)"); } catch (Throwable $e) {}

// ---- Handle Delete ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $row = db_one("SELECT file_path, gdrive_url FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
    if ($row) {
        if (!empty($row['gdrive_url'])) {
            require_once __DIR__.'/config/imagekit.php';
            global $imageKit;
            try { $imageKit->deleteFile($row['gdrive_url']); } catch (Throwable $e) {}
        }
        db_exec("DELETE FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
        $msg = 'Aktivitas dihapus.';
    }
}

// ---- Handle Edit ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='edit') {
    csrf_check();
    $id      = (int)($_POST['id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $durasi  = (int)($_POST['durasi'] ?? 0);
    $jarak   = (float)($_POST['jarak'] ?? 0);
    $kalori  = (int)($_POST['kalori'] ?? 0);
    $pace    = trim($_POST['pace'] ?? '');
    $desk    = trim($_POST['deskripsi'] ?? '');
    $gear    = trim($_POST['gear_sepatu'] ?? '');

    $old = db_one("SELECT * FROM upload_harian WHERE id=$1 AND user_id=$2", [$id, $u['id']]);
    if (!$old) { $err='Data tidak ditemukan.'; }
    else {
        $filePath = $old['file_path']; $gdriveUrl = $old['gdrive_url'];
        if (!empty($_FILES['bukti']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
            $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama']) . "-{$tanggal}-Jogging-" . time() . "." . $ext;
            require_once __DIR__.'/config/imagekit.php';
            global $imageKit;
            $uploadFile = $imageKit->uploadFile([
                'file' => base64_encode(file_get_contents($_FILES['bukti']['tmp_name'])),
                'fileName' => $safe,
                'folder' => '/sportapp/' . date('F_Y', strtotime($tanggal))
            ]);
            if (!$uploadFile->error) {
                if (!empty($old['gdrive_url'])) { try { $imageKit->deleteFile($old['gdrive_url']); } catch(Throwable $e){} }
                $filePath = $uploadFile->result->url;
                $gdriveUrl = $uploadFile->result->fileId;
            } else { $err = 'Gagal upload file ke ImageKit.'; }
        }
        if (!$err) {
            db_exec("UPDATE upload_harian SET tanggal=$1, jenis='Jogging', durasi_menit=$2, jarak_km=$3, kalori=$4, pace=$5, deskripsi=$6, file_path=$7, gdrive_url=$8, gear_sepatu=$9
                     WHERE id=$10 AND user_id=$11",
                    [$tanggal, $durasi, $jarak, $kalori, $pace, $desk, $filePath, $gdriveUrl, $gear, $id, $u['id']]);
            $msg='Aktivitas diperbarui.';
        }
    }
}

// ---- Handle Create ----
if ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['_action'])) {
    csrf_check();
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $jenis     = 'Jogging';
    $durasi    = (int)($_POST['durasi'] ?? 0);
    $jarak     = (float)($_POST['jarak'] ?? 0);
    $kalori    = (int)($_POST['kalori'] ?? 0);
    $pace      = trim($_POST['pace'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $gearSepatu = trim($_POST['gear_sepatu'] ?? '');

    $filePath = null; $gdriveUrl = null;
    if (!empty($_FILES['bukti']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        $safe = preg_replace('/[^a-z0-9]/i','_',$u['nama']) . "-{$tanggal}-{$jenis}." . $ext;
        require_once __DIR__.'/config/imagekit.php';
        global $imageKit;
        $uploadFile = $imageKit->uploadFile([
            'file' => base64_encode(file_get_contents($_FILES['bukti']['tmp_name'])),
            'fileName' => $safe,
            'folder' => '/sportapp/' . date('F_Y', strtotime($tanggal))
        ]);
        if (!$uploadFile->error) {
            $filePath = $uploadFile->result->url;
            $gdriveUrl = $uploadFile->result->fileId;
        } else { $err = 'Gagal upload file ke ImageKit.'; }
    }

    if (!$err) {
        db_exec(
          "INSERT INTO upload_harian(user_id,tanggal,jenis,durasi_menit,jarak_km,kalori,pace,deskripsi,file_path,gdrive_url,gear_sepatu)
           VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)",
           [$u['id'], $tanggal, $jenis, $durasi, $jarak, $kalori, $pace, $deskripsi, $filePath, $gdriveUrl, $gearSepatu]
        );
        $msg='Aktivitas berhasil dicatat.';
    }
}

$mine = db_all("SELECT * FROM upload_harian WHERE user_id=$1 ORDER BY tanggal DESC, id DESC LIMIT 500", [$u['id']]);
include __DIR__.'/includes/header.php'; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
  <h2 class="mb-0"><i class="bi bi-cloud-upload text-primary"></i> Upload Aktivitas Harian</h2>
  <span class="badge bg-warning"><i class="bi bi-info-circle me-1"></i> Minimal 1 minggu 1x</span>
</div>

<?php if($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-plus-circle text-primary me-1"></i> Catat Aktivitas Baru</span>
      <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#panduanUploadModal">
        <i class="bi bi-question-circle"></i> Panduan Upload
      </button>
    </div>
    <div class="card-body">
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-megaphone"></i> <strong>Wajib:</strong> upload aktivitas <u>minimal 1 minggu 1 kali</u>.
      </div>

      <!-- Revisi Nov 2026 — Tab Manual / AI Ekstraksi Strava -->
      <ul class="nav nav-pills nav-fill mb-3 small" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabManual" type="button" role="tab">
            <i class="bi bi-keyboard"></i> Isi Manual
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabAI" type="button" role="tab">
            <i class="bi bi-stars"></i> AI Strava (Otomatis)
          </button>
        </li>
      </ul>

      <div class="tab-content">
        <!-- === Tab AI (OCR Strava) === -->
        <div class="tab-pane fade" id="tabAI" role="tabpanel">
          <div class="alert alert-success py-2 small mb-2">
            <i class="bi bi-magic"></i> Upload <strong>screenshot Strava</strong>, AI akan mengisi otomatis
            durasi, jarak, pace, dan kalori. Anda cukup review & simpan pada tab <strong>Isi Manual</strong>.
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Screenshot Aktivitas (Strava / Garmin / Apple)</label>
            <input type="file" id="aiStravaFile" class="form-control" accept="image/*">
          </div>
          <button type="button" id="btnAiExtract" class="btn btn-success w-100">
            <i class="bi bi-stars"></i> Ekstrak dengan AI
          </button>
          <div id="aiExtractMsg" class="small mt-2 text-muted"></div>
          <div id="aiExtractPreview" class="small mt-2"></div>
        </div>

        <!-- === Tab Manual === -->
        <div class="tab-pane fade show active" id="tabManual" role="tabpanel">
          <form method="post" enctype="multipart/form-data" data-skip-preloader id="formManual">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="mb-2"><label class="form-label small fw-semibold">Tanggal</label>
              <input type="date" class="form-control" name="tanggal" id="fTanggal" value="<?= date('Y-m-d') ?>" required></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Jenis Olahraga</label>
              <input type="text" class="form-control" value="Jogging" readonly>
              <small class="text-muted">Saat ini upload harian hanya untuk Jogging.</small>
            </div>
            <div class="row g-2">
              <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Durasi</label>
                <input list="durasiOpt" type="number" class="form-control" name="durasi" id="fDurasi" min="0" required placeholder="pilih / ketik (menit)">
                <datalist id="durasiOpt">
                  <?php foreach([10,15,20,25,30,40,45,60,75,90,120] as $v): ?><option value="<?= $v ?>"><?= $v ?> menit</option><?php endforeach; ?>
                </datalist>
              </div>
              <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Jarak</label>
                <input list="jarakOpt" type="number" step="0.01" class="form-control" name="jarak" id="fJarak" min="0" required placeholder="pilih / ketik (km)">
                <datalist id="jarakOpt">
                  <?php foreach([1,1.5,2,3,3.5,5,7,10,15,21.1,42.2] as $v): ?><option value="<?= $v ?>"><?= $v ?> km</option><?php endforeach; ?>
                </datalist>
              </div>
              <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Pace</label>
                <input list="paceOpt" type="text" class="form-control" name="pace" id="fPace" placeholder="pilih / ketik">
                <datalist id="paceOpt">
                  <?php foreach(["4'30\"/km","5'00\"/km","5'30\"/km","6'00\"/km","6'30\"/km","7'00\"/km","7'30\"/km","8'00\"/km","9'00\"/km"] as $v): ?><option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                </datalist>
              </div>
              <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Kalori</label>
                <input list="kaloriOpt" type="number" class="form-control" name="kalori" id="fKalori" min="0" placeholder="pilih / ketik (kkal)">
                <datalist id="kaloriOpt">
                  <?php foreach([100,150,200,250,300,400,500,600,750,1000] as $v): ?><option value="<?= $v ?>"><?= $v ?> kkal</option><?php endforeach; ?>
                </datalist>
              </div>
            </div>
            <div class="my-2"><label class="form-label small fw-semibold"><i class="bi bi-shoe-prints"></i> Gear Sepatu Jogging</label>
              <input list="gearOpt" type="text" class="form-control" name="gear_sepatu" id="fGearSepatu" maxlength="120" placeholder="mis. Nike Pegasus 40, Adidas Adizero, Ortuseight Blitz...">
              <datalist id="gearOpt">
                <option value="Nike Pegasus 40"><option value="Nike Vomero 17"><option value="Nike Zoom Fly">
                <option value="Adidas Adizero Boston"><option value="Adidas Ultraboost"><option value="Adidas Supernova">
                <option value="Asics Novablast"><option value="Asics Gel-Nimbus"><option value="Asics Gel-Kayano">
                <option value="New Balance Fresh Foam 1080"><option value="Hoka Clifton 9"><option value="Hoka Mach 6">
                <option value="Saucony Endorphin Speed"><option value="Puma Velocity Nitro">
                <option value="Ortuseight Blitz"><option value="Specs Overdrive"><option value="Mizuno Wave Rider">
                <option value="Sepatu lokal / lainnya">
              </datalist>
              <small class="text-muted">Catat sepatu yang dipakai untuk memantau mileage & pergantian sepatu.</small>
            </div>
            <div class="my-2"><label class="form-label small fw-semibold">Deskripsi</label><textarea class="form-control" name="deskripsi" id="fDeskripsi" rows="2" placeholder="Rute, perasaan, dll."></textarea></div>
            <div class="mb-3"><label class="form-label small fw-semibold">Bukti (screenshot smartwatch/Strava/foto)</label>
              <input type="file" class="form-control" name="bukti" accept="image/*"></div>
            <button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Simpan Aktivitas</button>
          </form>
        </div>
      </div>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-list-check text-primary me-1"></i> Aktivitas Saya</span>
      <span class="badge bg-primary rounded-pill"><?= count($mine) ?></span>
    </div>
      <div class="table-responsive"><table class="table table-hover mb-0" data-paginate="10">
        <thead><tr><th>No</th><th>Tanggal</th><th>Jenis</th><th>Durasi</th><th>Jarak</th><th>Pace</th><th>Kalori</th><th>Sepatu</th><th>Bukti</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($mine as $i=>$m): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= htmlspecialchars($m['tanggal']) ?><br><small class="text-muted"><?= hari_id($m['tanggal']) ?></small></td>
            <td><span class="pill"><?= htmlspecialchars($m['jenis']) ?></span></td>
            <td><?= (int)$m['durasi_menit'] ?> mnt</td>
            <td><?= htmlspecialchars($m['jarak_km']) ?> km</td>
            <td><?= htmlspecialchars($m['pace'] ?? '') ?: '-' ?></td>
            <td><?= (int)$m['kalori'] ?></td>
            <td class="small"><?= htmlspecialchars($m['gear_sepatu'] ?? '') ?: '<span class="text-muted">–</span>' ?></td>
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
          <tr><td colspan="10" class="text-center text-muted py-3">Belum ada aktivitas. Ayo mulai jogging!</td></tr>
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
        <img id="buktiImg" src="" alt="Bukti" style="max-width:100%;">
        <div id="buktiFallback" class="d-none mt-3">
          <a id="buktiOpen" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka file</a>
        </div>
      </div>
    </div>
  </div>
</div>

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
          <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Durasi</label>
            <input list="durasiOpt" type="number" name="durasi" class="form-control" value="<?= (int)$m['durasi_menit'] ?>" placeholder="menit"></div>
          <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Jarak (km)</label>
            <input list="jarakOpt" type="number" step="0.01" name="jarak" class="form-control" value="<?= htmlspecialchars($m['jarak_km']) ?>" placeholder="5.20"></div>
          <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Pace</label>
            <input list="paceOpt" type="text" name="pace" class="form-control" value="<?= htmlspecialchars($m['pace'] ?? '') ?>" placeholder="6'00&quot;/km"></div>
          <div class="col-6 col-md-3"><label class="form-label small fw-semibold">Kalori</label>
            <input list="kaloriOpt" type="number" name="kalori" class="form-control" value="<?= (int)$m['kalori'] ?>" placeholder="kkal"></div>
        </div>
        <div class="my-2"><label class="form-label small fw-semibold"><i class="bi bi-shoe-prints"></i> Gear Sepatu Jogging</label>
          <input list="gearOpt" type="text" name="gear_sepatu" class="form-control" maxlength="120" value="<?= htmlspecialchars($m['gear_sepatu'] ?? '') ?>" placeholder="mis. Nike Pegasus 40"></div>
        <div class="my-2"><label class="form-label small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($m['deskripsi'] ?? '') ?></textarea></div>
        <div class="mb-1"><label class="form-label small fw-semibold">Ganti Bukti (opsional)</label>
          <input type="file" class="form-control" name="bukti" accept="image/*"></div>
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
  if (!buktiModal) buktiModal = new bootstrap.Modal(document.getElementById('buktiModal'));
  const img = document.getElementById('buktiImg'); const fb = document.getElementById('buktiFallback'); const op = document.getElementById('buktiOpen');
  document.getElementById('buktiDate').textContent = date || '';
  img.classList.remove('d-none'); fb.classList.add('d-none');
  img.onerror = () => { img.classList.add('d-none'); fb.classList.remove('d-none'); op.href = src; };
  img.src = src; op.href = src;
  buktiModal.show();
}
</script>

<!-- Revisi Nov 2026 — Modal Panduan Upload Screenshot Strava -->
<div class="modal fade" id="panduanUploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info-subtle">
        <h5 class="modal-title"><i class="bi bi-question-circle text-info"></i> Panduan Upload &amp; Ekstraksi AI Strava</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small">
        <h6 class="fw-bold"><i class="bi bi-1-circle text-primary"></i> Ambil Screenshot di Aplikasi Strava</h6>
        <ol>
          <li>Buka aplikasi <strong>Strava</strong> di HP Anda.</li>
          <li>Masuk ke tab <em>You</em> &rarr; pilih <em>Activities</em> &rarr; klik salah satu aktivitas jogging.</li>
          <li>Screenshot bagian yang <u>memperlihatkan statistik lengkap</u>:
            <ul>
              <li>Tanggal &amp; nama aktivitas</li>
              <li>Distance / Jarak (km)</li>
              <li>Moving Time / Durasi</li>
              <li>Pace / Kecepatan</li>
              <li>Calories / Kalori</li>
            </ul>
          </li>
          <li>Pastikan angka <strong>tidak terpotong</strong> dan cukup jelas terbaca.</li>
        </ol>

        <h6 class="fw-bold mt-3"><i class="bi bi-2-circle text-primary"></i> Ekstrak Otomatis di Halaman Ini</h6>
        <ol>
          <li>Klik tab <strong>AI Strava (Otomatis)</strong>.</li>
          <li>Pilih file screenshot yang tadi diambil.</li>
          <li>Klik tombol <strong>Ekstrak dengan AI</strong>. Tunggu ± 5-15 detik.</li>
          <li>Data otomatis mengisi form <strong>Isi Manual</strong>. Silakan review dan koreksi bila ada yang belum tepat.</li>
          <li>Klik <strong>Simpan Aktivitas</strong>.</li>
        </ol>

        <h6 class="fw-bold mt-3"><i class="bi bi-exclamation-triangle text-warning"></i> Tips Agar Akurasi Tinggi</h6>
        <ul>
          <li>Gunakan screenshot resolusi asli, jangan dikompres berlebihan.</li>
          <li>Hindari crop yang menghilangkan label satuan (km, min, kkal).</li>
          <li>Jika hasil kurang akurat, tinggal edit di form manual sebelum menyimpan.</li>
        </ul>

        <div class="alert alert-info small mt-3 mb-0">
          <i class="bi bi-shield-check"></i> Screenshot tidak disimpan di server saat proses ekstraksi. Jika Anda ingin
          menyimpannya sebagai bukti, pilih file yang sama pada kolom <em>Bukti</em> di tab Isi Manual.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<!-- Revisi Nov 2026 — Ekstraksi AI Screenshot Strava -->
<script>
(function(){
  var csrf = <?= json_encode(csrf_token()) ?>;
  var btn = document.getElementById('btnAiExtract');
  var inp = document.getElementById('aiStravaFile');
  var msg = document.getElementById('aiExtractMsg');
  var prev = document.getElementById('aiExtractPreview');
  if (!btn || !inp) return;

  function setField(id, val){
    var el = document.getElementById(id);
    if (el && (val !== null && val !== undefined && val !== '')) el.value = val;
  }

  btn.addEventListener('click', async function(){
    if (!inp.files || !inp.files[0]) { msg.className='small mt-2 text-danger'; msg.textContent = 'Pilih file screenshot dulu.'; return; }
    var f = inp.files[0];
    if (!/^image\//.test(f.type)) { msg.className='small mt-2 text-danger'; msg.textContent = 'File harus berupa gambar.'; return; }
    if (f.size > 8*1024*1024) { msg.className='small mt-2 text-danger'; msg.textContent = 'Ukuran file maksimal 8 MB.'; return; }

    btn.disabled = true;
    var orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> AI sedang menganalisis…';
    msg.className='small mt-2 text-muted';
    msg.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengekstrak data dari screenshot…';

    try {
      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('task', 'strava_ocr');
      fd.append('image', f);
      var r = await fetch('/api_ai.php', {method:'POST', body: fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j || !j.ok) {
        msg.className='small mt-2 text-danger';
        msg.innerHTML = '<i class="bi bi-x-circle"></i> Gagal: ' + ((j && j.err) || 'AI tidak dapat membaca screenshot.');
        return;
      }
      var d = j.data || {};
      // Revisi Juli 2026 (fix R12) — auto-submit dengan mengisi form manual
      // asli lalu memanggil form.submit() native. Ini menjamin alur upload
      // (ImageKit + INSERT ke upload_harian) identik dengan submit manual,
      // sehingga data pasti muncul di tabel "Aktivitas Saya".

      // Normalisasi tanggal ke format YYYY-MM-DD (input type=date wajib ISO).
      function toISODate(s){
        if (!s) return new Date().toISOString().slice(0,10);
        s = String(s).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        var m = s.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/);
        if (m) {
          var dd = ('0'+m[1]).slice(-2), mm = ('0'+m[2]).slice(-2);
          var yy = m[3].length===2 ? ('20'+m[3]) : m[3];
          return yy+'-'+mm+'-'+dd;
        }
        var t = Date.parse(s);
        if (!isNaN(t)) return new Date(t).toISOString().slice(0,10);
        return new Date().toISOString().slice(0,10);
      }

      var form = document.getElementById('formManual');
      if (!form) {
        msg.className='small mt-2 text-danger';
        msg.textContent = 'Form manual tidak ditemukan.';
        return;
      }

      setField('fTanggal', toISODate(d.tanggal));
      setField('fDurasi',  d.durasi_menit || 0);
      setField('fJarak',   d.jarak_km || 0);
      setField('fPace',    d.pace || '');
      setField('fKalori',  d.kalori || 0);
      var desc = document.getElementById('fDeskripsi');
      if (desc && !desc.value) desc.value = d.deskripsi || 'Diekstrak otomatis oleh AI dari screenshot Strava.';

      // Set file bukti pada input asli via DataTransfer (semua browser modern).
      try {
        var buktiInp = form.querySelector('input[type="file"][name="bukti"]');
        if (buktiInp) {
          var dt = new DataTransfer();
          dt.items.add(f);
          buktiInp.files = dt.files;
        }
      } catch(eFile){ /* fallback: user bisa lampirkan manual */ }

      prev.innerHTML = '<div class="border rounded p-2 bg-light">'+
        '<div>Tanggal: <b>'+ toISODate(d.tanggal) +'</b></div>'+
        '<div>Durasi: <b>'+ (d.durasi_menit||0) +' menit</b></div>'+
        '<div>Jarak: <b>'+ (d.jarak_km||0) +' km</b></div>'+
        '<div>Pace: <b>'+ (d.pace||'-') +'</b></div>'+
        '<div>Kalori: <b>'+ (d.kalori||0) +' kkal</b></div>'+
      '</div>';
      msg.className='small mt-2 text-success';
      msg.innerHTML = '<i class="bi bi-check-circle"></i> Data terekstrak. Menyimpan otomatis...';

      // Submit native — melewati semua interceptor JS/preloader kustom.
      setTimeout(function(){
        try { HTMLFormElement.prototype.submit.call(form); }
        catch(e3){ form.submit(); }
      }, 250);
    } catch (e) {
      msg.className='small mt-2 text-danger';
      msg.textContent = 'Gagal jaringan: ' + (e.message||'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

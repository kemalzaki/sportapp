<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/scope.php'; // Revisi R7 #5
require_role(['admin','superadmin']);
$pageTitle='Manajemen Jadwal';
$__isSuper = scope_is_super();
$__scopeKomArr = scope_kom_ids_sql_array();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? 'create';

    // Revisi 22 Juni 2026 R9 — Bungkus seluruh CRUD jadwal dalam try/catch agar
    // error apapun (ON CONFLICT dari trigger/migrasi, kolom hilang, dll) tidak
    // melempar halaman HTML dari set_exception_handler. Pesan tampil di flash.
    try {
        /* Revisi R18 (26 Jun 2026) — CRUD Jenis Jadwal (Tim Kantor KK / Tim Public KK).
         * Revisi Juli 2026 R8 #3 — hanya superadmin / komunitas SuperDuperAdmin.
         * Action: jj_create / jj_edit / jj_delete. */
        if (in_array($a, ['jj_create','jj_edit','jj_delete'], true) && !$__isSuper) {
            http_response_code(403);
            $_SESSION['flash_err'] = 'Akses ditolak: Jenis Jadwal hanya untuk superadmin / komunitas SuperDuperAdmin.';
            header('Location: jadwal.php'); exit;
        }
        if ($a === 'jj_create') {
            db_exec("INSERT INTO jenis_jadwal(nama, warna_bg, warna_text) VALUES($1,$2,$3)",
                [trim($_POST['nama'] ?? ''), $_POST['warna_bg'] ?? '#0ea5e9', $_POST['warna_text'] ?? '#ffffff']);
            $_SESSION['flash_ok'] = 'Jenis Jadwal ditambahkan.';
        } elseif ($a === 'jj_edit') {
            db_exec("UPDATE jenis_jadwal SET nama=$1, warna_bg=$2, warna_text=$3 WHERE id=$4",
                [trim($_POST['nama'] ?? ''), $_POST['warna_bg'] ?? '#0ea5e9', $_POST['warna_text'] ?? '#ffffff', (int)$_POST['id']]);
            $_SESSION['flash_ok'] = 'Jenis Jadwal diperbarui.';
        } elseif ($a === 'jj_delete') {
            db_exec("DELETE FROM jenis_jadwal WHERE id=$1", [(int)$_POST['id']]);
            $_SESSION['flash_ok'] = 'Jenis Jadwal dihapus.';
        } elseif ($a === 'delete') {
            db_exec("DELETE FROM jadwal WHERE id=$1", [(int)$_POST['id']]);
            $_SESSION['flash_ok'] = 'Jadwal dihapus.';
        } elseif ($a === 'edit') {
            $id    = (int)$_POST['id'];
            $tgl   = $_POST['tanggal'];
            $bulan = date('F', strtotime($tgl));
            $w     = 'W' . (int)ceil(date('j', strtotime($tgl))/7);
            $tempatId = (int)($_POST['tempat_id'] ?? 0) ?: null;
            $tempatNama = $_POST['tempat'] ?? '';
            if ($tempatId) { $row = db_one("SELECT nama FROM tempat WHERE id=$1", [$tempatId]); if ($row) $tempatNama = $row['nama']; }
            $jm = $_POST['jam_mulai'] ?: null;
            $js = $_POST['jam_selesai'] ?: null;
            $jenisJadwalId = (int)($_POST['jenis_jadwal_id'] ?? 0) ?: null;
            db_exec("UPDATE jadwal SET tanggal=$1, bulan=$2, minggu_ke=$3, jenis=$4, tempat=$5,
                                       tempat_id=$6, durasi_menit=$7, koordinator_id=$8,
                                       konten_obrolan=$9, catatan=$10, jam_mulai=$11, jam_selesai=$12,
                                       jenis_jadwal_id=$13
                     WHERE id=$14",
                    [$tgl, $bulan, $w, $_POST['jenis'], $tempatNama,
                     $tempatId, ((int)($_POST['durasi_menit'] ?? 0) ?: null),
                     (int)($_POST['koordinator_id'] ?? 0) ?: null,
                     $_POST['konten'] ?? '', $_POST['catatan'] ?? '', $jm, $js, $jenisJadwalId, $id]);
            $_SESSION['flash_ok'] = 'Jadwal diperbarui.';
        } else {
            $tgl   = $_POST['tanggal'];
            $bulan = date('F', strtotime($tgl));
            $w     = 'W' . (int)ceil(date('j', strtotime($tgl))/7);
            $tempatId = (int)($_POST['tempat_id'] ?? 0) ?: null;
            $tempatNama = '';
            if ($tempatId) { $row = db_one("SELECT nama FROM tempat WHERE id=$1", [$tempatId]); if ($row) $tempatNama = $row['nama']; }
            $jm = $_POST['jam_mulai'] ?: null;
            $js = $_POST['jam_selesai'] ?: null;
            $jenisJadwalId = (int)($_POST['jenis_jadwal_id'] ?? 0) ?: null;
            db_exec("INSERT INTO jadwal(tanggal,bulan,minggu_ke,jenis,tempat,tempat_id,durasi_menit,koordinator_id,konten_obrolan,catatan,jam_mulai,jam_selesai,jenis_jadwal_id)
                     VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13)",
                    [$tgl, $bulan, $w, $_POST['jenis'], $tempatNama, $tempatId,
                     ((int)($_POST['durasi_menit'] ?? 0) ?: null),
                     (int)($_POST['koordinator_id'] ?? 0) ?: current_user()['id'],
                     $_POST['konten'] ?? '', $_POST['catatan'] ?? '', $jm, $js, $jenisJadwalId]);
            $_SESSION['flash_ok'] = 'Jadwal ditambahkan.';
        }
    } catch (Throwable $e) {
        // Jangan biarkan set_exception_handler menampilkan halaman HTML.
        // Hilangkan juga $_SESSION['error_popup'] supaya modal tidak muncul ganda.
        unset($_SESSION['error_popup']);
        $msg = $e->getMessage();
        // Pesan ramah untuk error ON CONFLICT (DB belum di-migrasi).
        if (stripos($msg, 'ON CONFLICT') !== false) {
            $msg .= ' — jalankan migrations_r9.sql untuk menambah UNIQUE/PRIMARY KEY yang hilang.';
        }
        $_SESSION['flash_err'] = 'Gagal menyimpan jadwal: ' . $msg;
    }
    header('Location: jadwal.php'); exit;
}

$rows   = db_all("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto,
                          jj.nama AS jenis_jadwal_nama, jj.warna_bg AS jj_bg, jj.warna_text AS jj_text
                  FROM jadwal j
                  LEFT JOIN users u ON u.id=j.koordinator_id
                  LEFT JOIN jenis_jadwal jj ON jj.id=j.jenis_jadwal_id
                  WHERE ($1 = 1 OR j.komunitas_id IS NULL OR j.komunitas_id = ANY($2::int[]))
                  ORDER BY tanggal DESC", [$__isSuper?1:0, $__scopeKomArr]);
$admins = db_all("SELECT id,nama FROM users WHERE role IN ('admin','superadmin') ORDER BY nama");
$jenisRows = db_all("SELECT id,nama FROM jenis_olahraga ORDER BY nama");
$jenisList = array_column($jenisRows, 'nama');
if (!$jenisList) { $jenisList = ['Jogging','Badminton','Futsal','Senam','Renang','Lainnya']; $jenisRows = []; }
$tempatList = db_all("SELECT id,nama,jenis_id FROM tempat ORDER BY nama");
/* Revisi R18 — daftar Jenis Jadwal (Tim Kantor KK / Tim Public KK / dst.) */
$jenisJadwalList = db_all("SELECT id, nama, warna_bg, warna_text FROM jenis_jadwal ORDER BY nama");
require_once __DIR__.'/../includes/header.php';
?>

<h2 class="mb-3"><i class="bi bi-calendar-event text-primary"></i> Manajemen Jadwal</h2>

<?php if (!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div><?php unset($_SESSION['flash_ok']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<!-- ============ Revisi R18 (26 Jun 2026) — CRUD Jenis Jadwal ============ -->
<!-- Revisi Juli 2026 R8 #3 — hanya superadmin / komunitas SuperDuperAdmin. -->
<?php if ($__isSuper): ?>
<div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between align-items-center">
  <span><i class="bi bi-tags-fill me-1 text-info"></i> Jenis Jadwal (Tim Kantor KK / Tim Public KK)</span>
  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#jjPanel"><i class="bi bi-gear"></i> Kelola</button>
</div>
<div class="collapse <?= empty($jenisJadwalList)?'show':'' ?>" id="jjPanel"><div class="card-body">
  <form method="post" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="jj_create">
    <div class="col-md-4"><label class="form-label small fw-semibold">Nama Jenis</label>
      <input name="nama" class="form-control form-control-sm" placeholder="Tim Kantor KK / Tim Public KK" required></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Warna Background</label>
      <input type="color" name="warna_bg" class="form-control form-control-color form-control-sm" value="#0ea5e9"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Warna Tulisan</label>
      <input type="color" name="warna_text" class="form-control form-control-color form-control-sm" value="#ffffff"></div>
    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>#</th><th>Nama</th><th>Preview</th><th>BG</th><th>Text</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach($jenisJadwalList as $idx=>$jj): ?>
      <tr>
        <td><?= $idx+1 ?></td>
        <td><?= htmlspecialchars($jj['nama']) ?></td>
        <td><span class="badge" style="background:<?= htmlspecialchars($jj['warna_bg']) ?>;color:<?= htmlspecialchars($jj['warna_text']) ?>"><?= htmlspecialchars($jj['nama']) ?></span></td>
        <td><code><?= htmlspecialchars($jj['warna_bg']) ?></code></td>
        <td><code><?= htmlspecialchars($jj['warna_text']) ?></code></td>
        <td class="text-end" style="white-space:nowrap">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#jjEdit<?= $jj['id'] ?>"><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" onsubmit="return confirm('Hapus jenis jadwal ini?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="jj_delete">
            <input type="hidden" name="id" value="<?= $jj['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; if(!$jenisJadwalList): ?>
      <tr><td colspan="6" class="text-muted small">Belum ada Jenis Jadwal. Tambahkan minimal "Tim Kantor KK" dan "Tim Public KK".</td></tr>
    <?php endif; ?>
    </tbody></table></div>
</div></div>
</div>

<?php foreach($jenisJadwalList as $jj): ?>
<div class="modal fade" id="jjEdit<?= $jj['id'] ?>" tabindex="-1"><div class="modal-dialog">
  <form method="post" class="modal-content">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="jj_edit">
    <input type="hidden" name="id" value="<?= $jj['id'] ?>">
    <div class="modal-header"><h5 class="modal-title">Edit Jenis Jadwal</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
      <div class="col-12"><label class="form-label small">Nama</label><input name="nama" class="form-control" value="<?= htmlspecialchars($jj['nama']) ?>" required></div>
      <div class="col-6"><label class="form-label small">Warna BG</label><input type="color" name="warna_bg" class="form-control form-control-color" value="<?= htmlspecialchars($jj['warna_bg']) ?>"></div>
      <div class="col-6"><label class="form-label small">Warna Text</label><input type="color" name="warna_text" class="form-control form-control-color" value="<?= htmlspecialchars($jj['warna_text']) ?>"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
  </form>
</div></div>
<?php endforeach; ?>
<?php endif; /* end $__isSuper Jenis Jadwal */ ?>

<div class="card shadow-sm mb-3"><div class="card-header"><i class="bi bi-plus-circle me-1 text-primary"></i> Tambah Jadwal</div>
<div class="card-body">
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="col-md-2"><label class="form-label small fw-semibold">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jam Mulai</label>
      <input type="time" name="jam_mulai" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jam Selesai</label>
      <input type="time" name="jam_selesai" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Jenis</label>
      <select name="jenis" class="form-select">
        <?php foreach($jenisList as $j): ?><option><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
      </select></div>
    <?php if ($__isSuper): ?>
    <div class="col-md-4"><label class="form-label small fw-semibold">Jenis Jadwal</label>
      <select name="jenis_jadwal_id" class="form-select">
        <option value="">— Pilih (opsional) —</option>
        <?php foreach($jenisJadwalList as $jj): ?>
          <option value="<?= (int)$jj['id'] ?>"><?= htmlspecialchars($jj['nama']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-md-4"><label class="form-label small fw-semibold">Tempat</label>
      <select name="tempat_id" class="form-select" required>
        <option value="">— Pilih Tempat —</option>
        <?php foreach($tempatList as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Koordinator</label>
      <select name="koordinator_id" class="form-select">
        <option value="">— Pilih —</option>
        <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Lama Main (mnt)</label>
      <input type="number" name="durasi_menit" min="0" class="form-control" placeholder="cth 120"></div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
    <div class="col-12"><label class="form-label small fw-semibold mt-2">Konten Obrolan (WYSIWYG)</label>
      <textarea name="konten" data-wysiwyg placeholder="Topik obrolan, hikmah, dll..."></textarea></div>
    <div class="col-12"><label class="form-label small fw-semibold">Catatan Kondisi Kegiatan (WYSIWYG)</label>
      <textarea name="catatan" data-wysiwyg placeholder="Kondisi, cedera, izin, dll..."></textarea></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0" data-paginate="5">
  <thead><tr><th>#</th><th>Tanggal</th><th>Hari</th><th>Jam</th><th>Bulan</th><th>W</th><th>Jenis</th><th>Tempat</th><th>Durasi</th><th>Koordinator</th><th class="text-end">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $i=>$r): ?>
    <tr>
      <td class="text-muted"><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['tanggal']) ?></td>
      <td><span class="pill"><?= hari_id($r['tanggal']) ?></span></td>
      <td><small><?= htmlspecialchars(substr($r['jam_mulai'] ?? '',0,5)) ?: '—' ?><?= !empty($r['jam_selesai']) ? '<br>s/d '.htmlspecialchars(substr($r['jam_selesai'],0,5)) : '' ?></small></td>
      <td><?= htmlspecialchars($r['bulan']) ?></td>
      <td><span class="pill"><?= htmlspecialchars($r['minggu_ke']) ?></span></td>
      <td>
        <?= htmlspecialchars($r['jenis']) ?>
        <?php if(!empty($r['jenis_jadwal_nama'])): ?>
          <div class="mt-1"><span class="badge" style="background:<?= htmlspecialchars($r['jj_bg']) ?>;color:<?= htmlspecialchars($r['jj_text']) ?>"><?= htmlspecialchars($r['jenis_jadwal_nama']) ?></span></div>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($r['tempat']) ?></td>
      <td><?= !empty($r['durasi_menit']) ? ((int)$r['durasi_menit'].' mnt') : '<span class="text-muted small">—</span>' ?></td>
      <td><?= user_name_with_avatar($r['koord_foto'] ?? null, $r['koord'] ?? '-', false, 26) ?></td>
      <td class="text-end" style="white-space:nowrap">
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewJ<?= $r['id'] ?>" title="Lihat obrolan & catatan"><i class="bi bi-eye"></i></button>
        <a class="btn btn-sm btn-outline-primary" href="absensi.php?id=<?= $r['id'] ?>" title="Absensi"><i class="bi bi-check2-square"></i></a>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editJ<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php foreach($rows as $r): ?>
<!-- View modal: konten obrolan + catatan kondisi -->
<div class="modal fade" id="viewJ<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-journal-text"></i> <?= htmlspecialchars($r['tanggal']) ?> · <?= htmlspecialchars($r['jenis']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <h6 class="text-primary"><i class="bi bi-chat-square-text"></i> Konten Obrolan</h6>
        <div class="border rounded p-2 mb-3"><?= $r['konten_obrolan'] ?: '<span class="text-muted small">—</span>' ?></div>
        <h6 class="text-warning"><i class="bi bi-clipboard-pulse"></i> Catatan Kondisi Kegiatan</h6>
        <div class="border rounded p-2"><?= $r['catatan'] ?: '<span class="text-muted small">—</span>' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editJ<?= $r['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="edit">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Jadwal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body wysiwyg-body">
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label small fw-semibold">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($r['tanggal']) ?>" required></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jam Mulai</label>
            <input type="time" name="jam_mulai" class="form-control" value="<?= htmlspecialchars(substr($r['jam_mulai'] ?? '',0,5)) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jam Selesai</label>
            <input type="time" name="jam_selesai" class="form-control" value="<?= htmlspecialchars(substr($r['jam_selesai'] ?? '',0,5)) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Jenis</label>
            <select name="jenis" class="form-select">
              <?php foreach($jenisList as $j): ?><option <?= $r['jenis']===$j?'selected':'' ?>><?= htmlspecialchars($j) ?></option><?php endforeach; ?>
              <?php if (!in_array($r['jenis'], $jenisList, true)): ?><option selected><?= htmlspecialchars($r['jenis']) ?></option><?php endif; ?>
            </select></div>
          <?php if ($__isSuper): ?>
          <div class="col-md-4"><label class="form-label small fw-semibold">Jenis Jadwal</label>
            <select name="jenis_jadwal_id" class="form-select">
              <option value="">— Pilih (opsional) —</option>
              <?php foreach($jenisJadwalList as $jj): ?>
                <option value="<?= (int)$jj['id'] ?>" <?= ((int)($r['jenis_jadwal_id']??0))===(int)$jj['id']?'selected':'' ?>><?= htmlspecialchars($jj['nama']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <?php endif; ?>
          <div class="col-md-4"><label class="form-label small fw-semibold">Koordinator (admin)</label>
            <select name="koordinator_id" class="form-select">
              <option value="">— Pilih —</option>
              <?php foreach($admins as $a): ?><option value="<?= $a['id'] ?>" <?= $a['id']==$r['koordinator_id']?'selected':'' ?>><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-5"><label class="form-label small fw-semibold">Tempat</label>
            <select name="tempat_id" class="form-select">
              <option value="">— Pilih Tempat —</option>
              <?php foreach($tempatList as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id']==$r['tempat_id']?'selected':'' ?>><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
            </select>
            <input type="hidden" name="tempat" value="<?= htmlspecialchars($r['tempat']) ?>">
            <small class="text-muted">Saat ini: <?= htmlspecialchars($r['tempat']) ?: '—' ?></small></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Lama Main (menit)</label>
            <input type="number" name="durasi_menit" min="0" class="form-control" value="<?= (int)($r['durasi_menit'] ?? 0) ?: '' ?>" placeholder="cth 120"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Konten Obrolan</label>
            <textarea name="konten" data-wysiwyg><?= htmlspecialchars($r['konten_obrolan'] ?? '') ?></textarea></div>
          <div class="col-12"><label class="form-label small fw-semibold">Catatan Kondisi Kegiatan</label>
            <textarea name="catatan" data-wysiwyg><?= htmlspecialchars($r['catatan'] ?? '') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script>
/* ===== Filter Tempat berdasarkan Jenis Olahraga =====
 * Tempat hanya muncul jika jenis_id-nya cocok dengan jenis terpilih. */
(function(){
  const JENIS = <?= json_encode(array_map(function($r){ return ['id'=>(int)$r['id'],'nama'=>$r['nama']]; }, $jenisRows ?? []), JSON_UNESCAPED_UNICODE) ?>;
  const TEMPAT = <?= json_encode(array_map(function($t){ return ['id'=>(int)$t['id'],'nama'=>$t['nama'],'jenis_id'=>$t['jenis_id']?(int)$t['jenis_id']:null]; }, $tempatList), JSON_UNESCAPED_UNICODE) ?>;
  const NAME2ID = {}; JENIS.forEach(j => { NAME2ID[j.nama.toLowerCase()] = j.id; });

  function rebuildTempatOptions(jenisSel, tempatSel){
    const cur = tempatSel.value;
    const jenisName = (jenisSel.value || '').toLowerCase();
    const jid = NAME2ID[jenisName] || null;
    const placeholder = tempatSel.querySelector('option[value=""]');
    tempatSel.innerHTML = '';
    if (placeholder) tempatSel.appendChild(placeholder);
    else { const o=document.createElement('option'); o.value=''; o.textContent='— Pilih Tempat —'; tempatSel.appendChild(o); }
    TEMPAT.forEach(t=>{
      if (jid && t.jenis_id && t.jenis_id !== jid) return; // skip yang tidak cocok
      const o=document.createElement('option'); o.value=t.id; o.textContent = t.nama + (t.jenis_id?'':' (umum)');
      if (String(t.id)===String(cur)) o.selected = true;
      tempatSel.appendChild(o);
    });
  }
  function wire(scope){
    scope.querySelectorAll('select[name="jenis"]').forEach(js=>{
      const form = js.closest('form'); if(!form) return;
      const ts = form.querySelector('select[name="tempat_id"]'); if(!ts) return;
      if (js.dataset.jenisFilterInit) return; js.dataset.jenisFilterInit='1';
      js.addEventListener('change', ()=>rebuildTempatOptions(js, ts));
      rebuildTempatOptions(js, ts);
    });
  }
  document.addEventListener('DOMContentLoaded', ()=>wire(document));
  // Edit modal dirender saat sudah ada di DOM, jadi cukup re-wire saat dibuka.
  document.addEventListener('shown.bs.modal', (ev)=>wire(ev.target));
})();
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>

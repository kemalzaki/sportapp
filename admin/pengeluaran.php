<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Rekap Pengeluaran Kegiatan';

// Revisi 13 Juni 2026 — kolom "Dana Dari Siapa" pada pengeluaran_kegiatan.
try { db_exec("ALTER TABLE pengeluaran_kegiatan ADD COLUMN IF NOT EXISTS dana_dari VARCHAR(150)"); } catch (Throwable $e) {}

/**
 * Revisi 1 Jun 2026 #8: upload bukti pengeluaran ke ImageKit.
 * Return ['url'=>, 'fileId'=>] atau null.
 */
function peng_upload_imagekit($fileField, $namaPrefix) {
    if (empty($_FILES[$fileField]['name']) || !is_uploaded_file($_FILES[$fileField]['tmp_name'])) return null;
    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif','pdf'], true)) return null;
    if (filesize($_FILES[$fileField]['tmp_name']) > 8*1024*1024) return null; // 8MB cap
    require_once __DIR__.'/../config/imagekit.php';
    global $imageKit;
    $safe = preg_replace('/[^a-z0-9]/i','_', $namaPrefix ?: 'bukti') . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    try {
        $resp = $imageKit->uploadFile([
            'file'     => base64_encode(file_get_contents($_FILES[$fileField]['tmp_name'])),
            'fileName' => $safe,
            'folder'   => '/sportapp/pengeluaran/' . date('Y/m'),
        ]);
        if (!$resp->error && !empty($resp->result->url)) {
            return ['url'=>$resp->result->url, 'fileId'=>$resp->result->fileId ?? null];
        }
    } catch (Throwable $e) { /* swallow */ }
    return null;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0) ?: null;
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $kategori  = substr(trim($_POST['kategori'] ?? ''),0,60);
    $judul     = substr(trim($_POST['judul'] ?? ''),0,200);
    $jumlah    = max(0,(int)($_POST['jumlah'] ?? 0));
    $catatan   = trim($_POST['catatan'] ?? '');
    $bukti     = substr(trim($_POST['bukti_url'] ?? ''),0,500);
    $danaDari  = substr(trim($_POST['dana_dari'] ?? ''),0,150);

    $upl = peng_upload_imagekit('bukti_file', $judul ?: 'bukti');
    if ($upl) { $bukti = $upl['url']; }

    if ($a==='add' && $judul!=='') {
        db_exec("INSERT INTO pengeluaran_kegiatan(jadwal_id,tanggal,kategori,judul,jumlah,catatan,bukti_url,created_by,dana_dari)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)",
          [$jadwal_id,$tanggal,$kategori?:null,$judul,$jumlah,$catatan?:null,$bukti?:null,(int)current_user()['id'],$danaDari?:null]);
    } elseif ($a==='edit') {
        $id=(int)$_POST['id'];
        if (!$upl && $bukti==='') {
            $cur = db_one("SELECT bukti_url FROM pengeluaran_kegiatan WHERE id=$1",[$id]);
            $bukti = $cur['bukti_url'] ?? null;
        }
        db_exec("UPDATE pengeluaran_kegiatan SET jadwal_id=$1,tanggal=$2,kategori=$3,judul=$4,jumlah=$5,catatan=$6,bukti_url=$7,dana_dari=$8 WHERE id=$9",
          [$jadwal_id,$tanggal,$kategori?:null,$judul,$jumlah,$catatan?:null,$bukti?:null,$danaDari?:null,$id]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM pengeluaran_kegiatan WHERE id=$1", [(int)$_POST['id']]);
    }
    $qs = [];
    if (isset($_GET['jadwal_id'])) $qs['jadwal_id'] = (int)$_GET['jadwal_id'];
    if (isset($_GET['page']))      $qs['page']      = (int)$_GET['page'];
    header('Location: pengeluaran.php'.($qs?('?'.http_build_query($qs)):'')); exit;
}

$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
// Revisi 24 Juni 2026 — Filter Jadwal sekarang Per Bulan (format YYYY-MM).
// Memfilter pengeluaran berdasarkan bulan tanggal jadwal terkait (j.tanggal).
$filterBulan  = trim((string)($_GET['bulan'] ?? ''));
if ($filterBulan !== '' && !preg_match('/^\d{4}-\d{2}$/', $filterBulan)) $filterBulan = '';
$where = ''; $params = [];
if ($filterJadwal) {
    $where = "WHERE p.jadwal_id=$1"; $params=[$filterJadwal];
} elseif ($filterBulan !== '') {
    // Cocok bila jadwal terkait pada bulan tsb, ATAU (jika tidak terkait jadwal) pengeluaran-nya jatuh di bulan tsb.
    $where = "WHERE (
        (p.jadwal_id IS NOT NULL AND EXISTS (SELECT 1 FROM jadwal jj WHERE jj.id=p.jadwal_id AND to_char(jj.tanggal,'YYYY-MM')=$1))
        OR (p.jadwal_id IS NULL AND to_char(p.tanggal,'YYYY-MM')=$1)
    )";
    $params = [$filterBulan];
}

// ===== Revisi 1 Jun 2026 (Lanjutan) #1: pagination 5 entri =====
$PER_PAGE = 5;
$totalRows = (int) db_val("SELECT COUNT(*) FROM pengeluaran_kegiatan p $where", $params);
$totalPage = max(1, (int)ceil($totalRows / $PER_PAGE));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page-1) * $PER_PAGE;

$rows = db_all("SELECT p.*, j.tanggal AS j_tgl, j.jenis AS j_jenis, j.tempat AS j_tempat, u.nama AS pencatat
                FROM pengeluaran_kegiatan p
                LEFT JOIN jadwal j ON j.id=p.jadwal_id
                LEFT JOIN users u ON u.id=p.created_by
                $where ORDER BY p.tanggal DESC, p.id DESC
                LIMIT $PER_PAGE OFFSET $offset", $params);

// Total agregat seluruh (bukan hanya halaman aktif) — supaya rekap akurat
$totalAgg = (int) db_val("SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran_kegiatan p $where", $params);

$jadwalList = db_all("SELECT id, tanggal, jenis, tempat FROM jadwal ORDER BY tanggal DESC LIMIT 200");
// Revisi 24 Juni 2026 — Daftar bulan unik (dari tanggal jadwal + tanggal pengeluaran) untuk dropdown filter Per Bulan.
$bulanRows = db_all("
  SELECT bulan FROM (
    SELECT DISTINCT to_char(tanggal,'YYYY-MM') AS bulan FROM jadwal
    UNION
    SELECT DISTINCT to_char(tanggal,'YYYY-MM') AS bulan FROM pengeluaran_kegiatan
  ) x WHERE bulan IS NOT NULL ORDER BY bulan DESC LIMIT 60");

/* Revisi 22 Juni 2026 R12 — AJAX fragment: kembalikan hanya bagian tabel + pagination. */
if (!empty($_GET['ajax_table'])) {
    ob_start();
    ?>
    <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Tgl</th><th>Jadwal</th><th>Kategori</th><th>Judul</th><th class="text-end">Jumlah</th><th>Dana Dari</th><th>Catatan</th><th>Bukti (ImageKit)</th><th>Pencatat</th><th class="text-end" style="min-width:130px">Aksi</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($r['tanggal']) ?></td>
          <td class="small">
            <?php if($r['jadwal_id']): ?>
              <?= date('d M',strtotime($r['j_tgl'])) ?> · <?= htmlspecialchars($r['j_jenis']) ?>
              <div class="text-muted"><?= htmlspecialchars($r['j_tempat']) ?></div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['kategori'] ?? '-') ?></span></td>
          <td><?= htmlspecialchars($r['judul']) ?></td>
          <td class="text-end text-danger">Rp <?= number_format((int)$r['jumlah'],0,',','.') ?></td>
          <td class="small"><?= !empty($r['dana_dari']) ? '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-cash-coin"></i> '.htmlspecialchars($r['dana_dari']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td class="small text-muted"><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
          <td>
            <?php if(!empty($r['bukti_url'])):
              $isImg = (bool)preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $r['bukti_url']); ?>
              <a href="<?= htmlspecialchars($r['bukti_url']) ?>" target="_blank" rel="noopener">
                <?php if($isImg): ?>
                  <img src="<?= htmlspecialchars($r['bukti_url']) ?>" alt="bukti" style="height:36px;width:auto;border-radius:4px;object-fit:cover">
                <?php else: ?>
                  <i class="bi bi-file-earmark-image"></i> Lihat
                <?php endif; ?>
              </a>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td class="small"><?= htmlspecialchars($r['pencatat'] ?? '-') ?></td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-peng"
                    data-id="<?= (int)$r['id'] ?>" data-jadwal_id="<?= (int)($r['jadwal_id'] ?? 0) ?>"
                    data-tanggal="<?= htmlspecialchars($r['tanggal']) ?>"
                    data-kategori="<?= htmlspecialchars($r['kategori'] ?? '') ?>"
                    data-judul="<?= htmlspecialchars($r['judul']) ?>"
                    data-jumlah="<?= (int)$r['jumlah'] ?>"
                    data-catatan="<?= htmlspecialchars($r['catatan'] ?? '') ?>"
                    data-dana_dari="<?= htmlspecialchars($r['dana_dari'] ?? '') ?>"
                    data-bukti_url="<?= htmlspecialchars($r['bukti_url'] ?? '') ?>">
              <i class="bi bi-pencil"></i>
            </button>
            <form method="post" class="d-inline peng-form-ajax" onsubmit="return confirm('Hapus pengeluaran ini?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; if(!$rows): ?><tr><td colspan="10" class="text-center text-muted small">Belum ada pengeluaran.</td></tr><?php endif; ?>
      </tbody>
      <?php if($rows): ?>
      <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total (semua halaman)</th><th class="text-end text-danger">Rp <?= number_format($totalAgg,0,',','.') ?></th><th colspan="5"></th></tr></tfoot>
      <?php endif; ?>
    </table></div></div>
    <?php if ($totalPage > 1): ?>
    <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-1">
      <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="#" data-page="<?= max(1,$page-1) ?>">«</a></li>
      <?php for($p=1;$p<=$totalPage;$p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="#" data-page="<?= $p ?>"><?= $p ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="#" data-page="<?= min($totalPage,$page+1) ?>">»</a></li>
    </ul></nav>
    <div class="text-center small text-muted mb-2">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $totalRows ?> entri · 5 per halaman</div>
    <?php endif; ?>
    <?php
    echo ob_get_clean();
    exit;
}

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-cash-stack text-danger"></i> Rekap Pengeluaran Kegiatan</h2>
<p class="text-muted small">Revisi 1 Jun 2026: bukti pengeluaran kini disimpan ke <strong>ImageKit</strong>. Pembaruan terbaru: tombol <em>Edit</em> di setiap baris dan pagination 5 entri per halaman.</p>

<form method="get" class="mb-3 d-flex gap-2 align-items-end flex-wrap">
  <!-- Revisi 24 Juni 2026 — Filter Jadwal dibuat PER BULAN (YYYY-MM) -->
  <div>
    <label class="small">Filter Jadwal (Per Bulan)</label>
    <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">-- Semua Bulan --</option>
      <?php foreach($bulanRows as $b):
        $bv = $b['bulan'];
        $ts = strtotime($bv.'-01');
        $label = $ts ? strftime_id_my($ts) : $bv;
      ?>
        <option value="<?= htmlspecialchars($bv) ?>" <?= $filterBulan===$bv?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="small text-muted">Atau pilih satu jadwal spesifik</label>
    <select name="jadwal_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="0">-- (abaikan, pakai filter bulan) --</option>
      <?php foreach($jadwalList as $j): ?>
        <option value="<?= (int)$j['id'] ?>" <?= $filterJadwal===(int)$j['id']?'selected':'' ?>>
          <?= date('d M Y',strtotime($j['tanggal'])) ?> · <?= htmlspecialchars($j['jenis']) ?> @ <?= htmlspecialchars($j['tempat']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($filterJadwal || $filterBulan !== ''): ?>
    <div><a href="pengeluaran.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a></div>
  <?php endif; ?>
  <div class="ms-auto small text-muted">Total: <strong class="text-danger">Rp <?= number_format($totalAgg,0,',','.') ?></strong> · <?= $totalRows ?> entri</div>
</form>
<?php
// Helper bulan Indonesia (didefinisikan inline supaya tidak butuh ekstensi intl).
function strftime_id_my($ts){
  static $bln=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  return $bln[(int)date('n',$ts)-1].' '.date('Y',$ts);
}
?>

<div class="card mb-3"><div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Pengeluaran</div>
<div class="card-body">
  <form method="post" enctype="multipart/form-data" class="row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-3"><label class="small">Jadwal Olahraga (relasi)</label>
      <select class="form-select form-select-sm" name="jadwal_id">
        <option value="0">-- Tidak terkait jadwal --</option>
        <?php foreach($jadwalList as $j): ?>
          <option value="<?= (int)$j['id'] ?>" <?= $filterJadwal===(int)$j['id']?'selected':'' ?>>
            <?= date('d M Y',strtotime($j['tanggal'])) ?> · <?= htmlspecialchars($j['jenis']) ?> @ <?= htmlspecialchars($j['tempat']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="small">Tanggal</label>
      <input type="date" class="form-control form-control-sm" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-2"><label class="small">Kategori</label>
      <input class="form-control form-control-sm" name="kategori" placeholder="Sewa Lapangan / Konsumsi / Shuttlecock"></div>
    <div class="col-md-3"><label class="small">Judul Pengeluaran</label>
      <input class="form-control form-control-sm" name="judul" required></div>
    <div class="col-md-2"><label class="small">Jumlah (Rp)</label>
      <input type="number" class="form-control form-control-sm" name="jumlah" min="0" step="1000" required></div>
    <div class="col-md-3"><label class="small">Dana Dari (siapa)</label>
      <input class="form-control form-control-sm" name="dana_dari" placeholder="cth: Kas Tim / Pak Budi / Sponsor X"></div>
    <div class="col-md-6"><label class="small">Catatan</label>
      <input class="form-control form-control-sm" name="catatan"></div>
    <div class="col-md-3"><label class="small">URL Bukti (opsional, jika sudah punya)</label>
      <input class="form-control form-control-sm" name="bukti_url" placeholder="https://ik.imagekit.io/..."></div>
    <div class="col-md-3"><label class="small">Atau Upload Bukti ke ImageKit</label>
      <input type="file" class="form-control form-control-sm" name="bukti_file" accept="image/*,application/pdf">
      <div class="form-text small">JPG/PNG/WEBP/PDF, maks 8 MB.</div></div>
    <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-cloud-upload"></i> Simpan &amp; Upload</button></div>
  </form>
</div></div>

<!-- Revisi 22 Juni 2026 R12 — Bungkus tabel + pagination dalam wrapper untuk AJAX -->
<div id="pengTableWrap">
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Tgl</th><th>Jadwal</th><th>Kategori</th><th>Judul</th><th class="text-end">Jumlah</th><th>Dana Dari</th><th>Catatan</th><th>Bukti (ImageKit)</th><th>Pencatat</th><th class="text-end" style="min-width:130px">Aksi</th></tr></thead>
  <tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td class="small"><?= htmlspecialchars($r['tanggal']) ?></td>
      <td class="small">
        <?php if($r['jadwal_id']): ?>
          <?= date('d M',strtotime($r['j_tgl'])) ?> · <?= htmlspecialchars($r['j_jenis']) ?>
          <div class="text-muted"><?= htmlspecialchars($r['j_tempat']) ?></div>
        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
      </td>
      <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($r['kategori'] ?? '-') ?></span></td>
      <td><?= htmlspecialchars($r['judul']) ?></td>
      <td class="text-end text-danger">Rp <?= number_format((int)$r['jumlah'],0,',','.') ?></td>
      <td class="small"><?= !empty($r['dana_dari']) ? '<span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-cash-coin"></i> '.htmlspecialchars($r['dana_dari']).'</span>' : '<span class="text-muted">—</span>' ?></td>
      <td class="small text-muted"><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
      <td>
        <?php if(!empty($r['bukti_url'])):
          $isImg = (bool)preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $r['bukti_url']); ?>
          <a href="<?= htmlspecialchars($r['bukti_url']) ?>" target="_blank" rel="noopener" title="Buka di ImageKit">
            <?php if($isImg): ?>
              <img src="<?= htmlspecialchars($r['bukti_url']) ?>" alt="bukti" style="height:36px;width:auto;border-radius:4px;object-fit:cover">
            <?php else: ?>
              <i class="bi bi-file-earmark-image"></i> Lihat
            <?php endif; ?>
          </a>
        <?php else: ?>-<?php endif; ?>
      </td>
      <td class="small"><?= htmlspecialchars($r['pencatat'] ?? '-') ?></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-peng"
                data-id="<?= (int)$r['id'] ?>"
                data-jadwal_id="<?= (int)($r['jadwal_id'] ?? 0) ?>"
                data-tanggal="<?= htmlspecialchars($r['tanggal']) ?>"
                data-kategori="<?= htmlspecialchars($r['kategori'] ?? '') ?>"
                data-judul="<?= htmlspecialchars($r['judul']) ?>"
                data-jumlah="<?= (int)$r['jumlah'] ?>"
                data-catatan="<?= htmlspecialchars($r['catatan'] ?? '') ?>"
                data-dana_dari="<?= htmlspecialchars($r['dana_dari'] ?? '') ?>"
                data-bukti_url="<?= htmlspecialchars($r['bukti_url'] ?? '') ?>">
          <i class="bi bi-pencil"></i>
        </button>
        <form method="post" class="d-inline peng-form-ajax" onsubmit="return confirm('Hapus pengeluaran ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?><tr><td colspan="10" class="text-center text-muted small">Belum ada pengeluaran.</td></tr><?php endif; ?>
  </tbody>
  <?php if($rows): ?>
  <tfoot><tr class="table-light"><th colspan="4" class="text-end">Total (semua halaman)</th><th class="text-end text-danger">Rp <?= number_format($totalAgg,0,',','.') ?></th><th colspan="5"></th></tr></tfoot>
  <?php endif; ?>
</table></div></div>

<?php if ($totalPage > 1):
  $pq = function($p) use ($filterJadwal) {
      $a = ['page'=>$p];
      if ($filterJadwal) $a['jadwal_id'] = $filterJadwal;
      return '?'.http_build_query($a);
  };
?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-1">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $pq(max(1,$page-1)) ?>">«</a></li>
  <?php for($p=1;$p<=$totalPage;$p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $pq($p) ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="<?= $pq(min($totalPage,$page+1)) ?>">»</a></li>
</ul></nav>
<div class="text-center small text-muted mb-2">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $totalRows ?> entri · 5 per halaman</div>
<?php endif; ?>
</div><!-- /#pengTableWrap -->

<!-- ===== Modal Edit Pengeluaran ===== -->
<div class="modal fade" id="editPengModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="edit">
        <input type="hidden" name="id" id="ep_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Pengeluaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
          <div class="col-md-6"><label class="small">Jadwal Olahraga (relasi)</label>
            <select class="form-select form-select-sm" name="jadwal_id" id="ep_jadwal_id">
              <option value="0">-- Tidak terkait jadwal --</option>
              <?php foreach($jadwalList as $j): ?>
                <option value="<?= (int)$j['id'] ?>">
                  <?= date('d M Y',strtotime($j['tanggal'])) ?> · <?= htmlspecialchars($j['jenis']) ?> @ <?= htmlspecialchars($j['tempat']) ?>
                </option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-3"><label class="small">Tanggal</label>
            <input type="date" class="form-control form-control-sm" name="tanggal" id="ep_tanggal" required></div>
          <div class="col-md-3"><label class="small">Jumlah (Rp)</label>
            <input type="number" class="form-control form-control-sm" name="jumlah" id="ep_jumlah" min="0" step="1000" required></div>
          <div class="col-md-4"><label class="small">Kategori</label>
            <input class="form-control form-control-sm" name="kategori" id="ep_kategori"></div>
          <div class="col-md-8"><label class="small">Judul</label>
            <input class="form-control form-control-sm" name="judul" id="ep_judul" required></div>
          <div class="col-md-6"><label class="small">Dana Dari (siapa)</label>
            <input class="form-control form-control-sm" name="dana_dari" id="ep_dana_dari" placeholder="cth: Kas Tim / Pak Budi / Sponsor X"></div>
          <div class="col-12"><label class="small">Catatan</label>
            <input class="form-control form-control-sm" name="catatan" id="ep_catatan"></div>
          <div class="col-md-6"><label class="small">URL Bukti (ImageKit)</label>
            <input class="form-control form-control-sm" name="bukti_url" id="ep_bukti_url" placeholder="https://ik.imagekit.io/..."></div>
          <div class="col-md-6"><label class="small">Atau Upload Ulang Bukti</label>
            <input type="file" class="form-control form-control-sm" name="bukti_file" accept="image/*,application/pdf">
            <div class="form-text small">Kosongkan jika tidak ingin mengganti bukti.</div></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* Revisi R25 (28 Juni 2026) — Perbaikan definitif tombol Edit pengeluaran.
   Masalah sebelumnya:
   - Ada DUA IIFE yang sama-sama me-bind handler ke .btn-edit-peng → handler dobel,
     pada beberapa browser modal hanya "berkedip" lalu tertutup kembali.
   - Setelah AJAX refresh tabel, handler awal lepas dari DOM baru.
   Solusi: pakai event delegation di document, satu handler saja, buka modal
   via getOrCreateInstance. Form edit di-submit normal (full reload) supaya
   redirect server bekerja & file upload (multipart) konsisten. */
(function(){
  if (window.__pengEditBound) return;
  window.__pengEditBound = true;

  var modalEl = document.getElementById('editPengModal');
  if (!modalEl || !window.bootstrap) return;
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  function setVal(id, v){ var el = document.getElementById(id); if (el) el.value = (v==null?'':v); }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-edit-peng');
    if (!btn) return;
    e.preventDefault();
    var d = btn.dataset;
    setVal('ep_id',        d.id);
    setVal('ep_jadwal_id', d.jadwal_id || '0');
    setVal('ep_tanggal',   d.tanggal);
    setVal('ep_kategori',  d.kategori);
    setVal('ep_judul',     d.judul);
    setVal('ep_jumlah',    d.jumlah || '0');
    setVal('ep_catatan',   d.catatan);
    setVal('ep_dana_dari', d.dana_dari);
    setVal('ep_bukti_url', d.bukti_url);
    modal.show();
  });

  // Tombol submit modal — beri feedback "menyimpan" lalu biarkan submit normal.
  var editForm = modalEl.querySelector('form');
  if (editForm) {
    editForm.addEventListener('submit', function(){
      var btn = editForm.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan…'; }
      return true;
    });
  }
})();
</script>

<script>
/* AJAX filter + pagination + add + delete (tetap dari R12, dipangkas — tidak lagi me-bind edit). */
(function(){
  var wrap = document.getElementById('pengTableWrap');
  if (!wrap) return;
  var selFilter = document.querySelector('select[name="jadwal_id"]');
  var addForm   = document.querySelector('form input[name="_action"][value="add"]');
  addForm = addForm ? addForm.closest('form') : null;

  function getFilterJadwal(){
    var s = document.querySelector('form[method="get"] select[name="jadwal_id"]');
    return s ? s.value : '0';
  }

  function loadTable(page){
    var jid = getFilterJadwal();
    var qs = new URLSearchParams();
    qs.set('ajax_table','1');
    if (jid && jid !== '0') qs.set('jadwal_id', jid);
    if (page) qs.set('page', page);
    wrap.style.opacity = '0.5';
    fetch('/admin/pengeluaran.php?' + qs.toString(), {credentials:'same-origin', headers:{'X-Requested-With':'fetch'}})
      .then(function(r){ return r.text(); })
      .then(function(html){
        wrap.innerHTML = html;
        wrap.style.opacity = '1';
        try {
          var u = new URL(location.href);
          if (jid && jid !== '0') u.searchParams.set('jadwal_id', jid); else u.searchParams.delete('jadwal_id');
          if (page) u.searchParams.set('page', page); else u.searchParams.delete('page');
          history.replaceState(null,'', u.toString());
        } catch(e){}
      })
      .catch(function(){ wrap.style.opacity='1'; });
  }

  if (selFilter) {
    selFilter.removeAttribute('onchange');
    selFilter.addEventListener('change', function(){ loadTable(1); });
    var topForm = selFilter.closest('form');
    if (topForm) topForm.addEventListener('submit', function(e){ e.preventDefault(); loadTable(1); });
  }

  wrap.addEventListener('click', function(e){
    var a = e.target.closest('a[data-page]');
    if (a) {
      e.preventDefault();
      var li = a.parentElement;
      if (li && li.classList.contains('disabled')) return;
      loadTable(parseInt(a.dataset.page||'1',10));
    }
  });
  wrap.addEventListener('submit', function(e){
    var f = e.target.closest('form.peng-form-ajax');
    if (!f) return;
    e.preventDefault();
    var fd = new FormData(f);
    fetch('/admin/pengeluaran.php', {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(){ loadTable(1); });
  });

  if (addForm) {
    addForm.addEventListener('submit', function(e){
      e.preventDefault();
      var fd = new FormData(addForm);
      var btn = addForm.querySelector('button[type=submit],button:not([type])');
      if (btn) { btn.disabled = true; var oldH = btn.innerHTML; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan…'; }
      fetch('/admin/pengeluaran.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(){
          addForm.reset();
          loadTable(1);
        })
        .finally(function(){ if (btn) { btn.disabled = false; btn.innerHTML = oldH; } });
    });
  }
})();
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>

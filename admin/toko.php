<?php
/**
 * admin/toko.php  — CRUD Toko/Pedagang + daftar produk per toko.
 *
 * Revisi 2 Jun 2026:
 *  #1 CRUD nama toko (tambah/edit/hapus) + daftar produk (jajanan) yang
 *     terhubung ke toko tsb. Bisa juga assign / unassign produk.
 *
 * Migrasi schema: jalankan migrations_2jun2026_toko.sql sekali
 *                 (atau biarkan auto via config/db.php yang sudah dipatch).
 *
 * Catatan: SEMUA data lama tetap aman — kolom toko_id pada jajanan adalah
 *          nullable, produk yang belum di-assign tampil di kelompok "(Tanpa Toko)".
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'CRUD Toko & Produk';

function tk_parse_latlng($v, $min, $max) {
    if ($v === null || $v === '') return null;
    $f = (float) str_replace(',', '.', trim($v));
    if ($f < $min || $f > $max) return null;
    return $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'add' || $a === 'edit') {
            $nama  = substr(trim($_POST['nama'] ?? ''), 0, 160);
            $des   = trim($_POST['deskripsi'] ?? '');
            $alm   = trim($_POST['alamat'] ?? '');
            $wa    = preg_replace('/\D+/', '', $_POST['no_wa'] ?? '');
            $lat   = tk_parse_latlng($_POST['lat'] ?? '', -90, 90);
            $lng   = tk_parse_latlng($_POST['lng'] ?? '', -180, 180);
            $aktif = !empty($_POST['aktif']) ? 't' : 'f';
            if ($nama === '') throw new RuntimeException('Nama toko wajib diisi.');

            if ($a === 'add') {
                db_exec("INSERT INTO toko(nama,deskripsi,alamat,no_wa,lat,lng,aktif)
                         VALUES($1,$2,$3,$4,$5,$6,$7)",
                  [$nama, $des?:null, $alm?:null, $wa?:null, $lat, $lng, $aktif]);
                $_SESSION['flash'] = 'Toko ditambahkan.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                db_exec("UPDATE toko SET nama=$1,deskripsi=$2,alamat=$3,no_wa=$4,lat=$5,lng=$6,aktif=$7
                          WHERE id=$8",
                  [$nama, $des?:null, $alm?:null, $wa?:null, $lat, $lng, $aktif, $id]);
                $_SESSION['flash'] = 'Toko diperbarui.';
            }
        } elseif ($a === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            // Aman: jajanan.toko_id otomatis di-NULL-kan (ON DELETE SET NULL).
            db_exec("DELETE FROM toko WHERE id=$1", [$id]);
            $_SESSION['flash'] = 'Toko dihapus. Produk yang terhubung tidak ikut terhapus.';
        } elseif ($a === 'assign_products') {
            $tokoId = (int)($_POST['toko_id'] ?? 0);
            $ids    = $_POST['jajanan_ids'] ?? [];
            if (!is_array($ids)) $ids = [];
            $ids = array_values(array_unique(array_map('intval', $ids)));
            // Step 1: lepas semua produk dari toko ini terlebih dulu
            db_exec("UPDATE jajanan SET toko_id=NULL WHERE toko_id=$1", [$tokoId]);
            // Step 2: re-assign produk yang dicentang ke toko ini
            foreach ($ids as $jid) {
                if ($jid <= 0) continue;
                db_exec("UPDATE jajanan SET toko_id=$1 WHERE id=$2", [$tokoId, $jid]);
            }
            $_SESSION['flash'] = 'Daftar produk untuk toko diperbarui ('.count($ids).' produk).';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: toko.php'.(isset($_GET['edit'])?'?edit='.(int)$_GET['edit']:'')); exit;
}

$tokos = db_all("SELECT t.*, (SELECT COUNT(*) FROM jajanan j WHERE j.toko_id=t.id) AS jml_produk
                 FROM toko t ORDER BY t.aktif DESC, t.id DESC");
$editId = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? db_one("SELECT * FROM toko WHERE id=$1", [$editId]) : null;

// Semua jajanan (untuk picker assign) + grup by toko
$allJajanan = db_all("SELECT id, nama, harga, toko_id, aktif FROM jajanan ORDER BY nama");
$jjnByToko = []; $jjnNoToko = [];
foreach ($allJajanan as $j) {
    if (!empty($j['toko_id'])) {
        $jjnByToko[(int)$j['toko_id']][] = $j;
    } else {
        $jjnNoToko[] = $j;
    }
}

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shop-window text-warning"></i> CRUD Toko &amp; Produk</h2>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?>
  <div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div>
  <?php unset($_SESSION['flash_err']); endif; ?>

<p class="text-muted small">Kelola nama-nama toko / pedagang. Setiap toko bisa memiliki banyak produk
(diambil dari tabel <code>jajanan</code>). Produk yang belum di-assign tampil di kelompok
<em>(Tanpa Toko)</em> dan tetap muncul di halaman pembeli seperti biasa.</p>

<div class="row g-3">
  <!-- ============== Form Tambah / Edit ============== -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-<?= $editRow ? 'pencil-square' : 'plus-circle' ?>"></i>
        <?= $editRow ? 'Edit Toko #'.(int)$editRow['id'] : 'Tambah Toko Baru' ?>
      </div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="<?= $editRow ? 'edit' : 'add' ?>">
          <?php if ($editRow): ?>
            <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
          <?php endif; ?>
          <div class="col-12">
            <label class="small">Nama Toko</label>
            <input class="form-control form-control-sm" name="nama" required maxlength="160"
                   value="<?= htmlspecialchars($editRow['nama'] ?? '') ?>"
                   placeholder="cth: Warung Bu Aminah">
          </div>
          <div class="col-12">
            <label class="small">Deskripsi (opsional)</label>
            <textarea class="form-control form-control-sm" name="deskripsi" rows="2"><?= htmlspecialchars($editRow['deskripsi'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="small">Alamat</label>
            <input class="form-control form-control-sm" name="alamat"
                   value="<?= htmlspecialchars($editRow['alamat'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="small">No WA (cth: 6281…)</label>
            <input class="form-control form-control-sm" name="no_wa" inputmode="numeric"
                   value="<?= htmlspecialchars($editRow['no_wa'] ?? '') ?>">
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="aktif" id="tk_aktif"
                     <?= (!$editRow || ($editRow['aktif']==='t'||$editRow['aktif']===true)) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="tk_aktif">Aktif</label>
            </div>
          </div>
          <div class="col-6">
            <label class="small">Lat</label>
            <input class="form-control form-control-sm" name="lat" inputmode="decimal"
                   placeholder="-6.926263"
                   value="<?= htmlspecialchars($editRow['lat'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="small">Lng</label>
            <input class="form-control form-control-sm" name="lng" inputmode="decimal"
                   placeholder="107.717553"
                   value="<?= htmlspecialchars($editRow['lng'] ?? '') ?>">
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-primary">
              <i class="bi bi-save"></i> <?= $editRow ? 'Simpan Perubahan' : 'Tambah Toko' ?>
            </button>
            <?php if ($editRow): ?>
              <a href="toko.php" class="btn btn-sm btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ============== Daftar Toko ============== -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="bi bi-list-ul"></i> Daftar Toko <span class="badge bg-secondary"><?= count($tokos) ?></span></div>
        <a href="/admin/jajanan.php" class="small"><i class="bi bi-box-arrow-up-right"></i> CRUD Produk Jajanan</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Nama</th><th>Alamat / WA</th><th class="text-end">Produk</th><th>Status</th><th class="text-end" style="width:140px">Aksi</th></tr>
          </thead>
          <tbody>
          <?php if (!$tokos): ?>
            <tr><td colspan="5" class="text-center text-muted small py-3">Belum ada toko. Tambahkan dari form di kiri.</td></tr>
          <?php endif; ?>
          <?php foreach ($tokos as $t): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($t['nama']) ?></div>
                <?php if (!empty($t['deskripsi'])): ?>
                  <div class="text-muted small text-truncate" style="max-width:220px"><?= htmlspecialchars($t['deskripsi']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if (!empty($t['alamat'])): ?><div><?= htmlspecialchars($t['alamat']) ?></div><?php endif; ?>
                <?php if (!empty($t['no_wa'])): ?>
                  <a target="_blank" rel="noopener" href="https://wa.me/<?= htmlspecialchars($t['no_wa']) ?>">
                    <i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($t['no_wa']) ?>
                  </a>
                <?php endif; ?>
                <?php if (!empty($t['lat']) && !empty($t['lng'])): ?>
                  · <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= htmlspecialchars($t['lat']) ?>,<?= htmlspecialchars($t['lng']) ?>">
                    <i class="bi bi-geo-alt-fill text-danger"></i> Maps
                  </a>
                <?php endif; ?>
              </td>
              <td class="text-end"><span class="badge bg-info-subtle text-info-emphasis"><?= (int)$t['jml_produk'] ?></span></td>
              <td><?= ($t['aktif']==='t'||$t['aktif']===true) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$t['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse"
                        data-bs-target="#prods-<?= (int)$t['id'] ?>" title="Daftar Produk">
                  <i class="bi bi-box-seam"></i>
                </button>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus toko ini? Produk yang terhubung TIDAK akan terhapus, hanya kehilangan label toko.')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <!-- Collapse: list & assign produk -->
            <tr class="collapse" id="prods-<?= (int)$t['id'] ?>">
              <td colspan="5" class="bg-light-subtle">
                <form method="post" class="small">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="assign_products">
                  <input type="hidden" name="toko_id" value="<?= (int)$t['id'] ?>">
                  <div class="fw-semibold mb-1"><i class="bi bi-check2-square"></i> Pilih produk yang masuk ke toko ini:</div>
                  <div class="row g-1">
                    <?php
                      $assignedIds = [];
                      foreach (($jjnByToko[(int)$t['id']] ?? []) as $j) $assignedIds[(int)$j['id']] = true;
                      foreach ($allJajanan as $j):
                        $jid = (int)$j['id'];
                        $checked = isset($assignedIds[$jid]);
                        $belongsOther = !empty($j['toko_id']) && (int)$j['toko_id'] !== (int)$t['id'];
                    ?>
                      <div class="col-md-6">
                        <label class="form-check small d-flex align-items-center gap-2" style="<?= $belongsOther?'opacity:.65':'' ?>">
                          <input type="checkbox" class="form-check-input" name="jajanan_ids[]" value="<?= $jid ?>" <?= $checked?'checked':'' ?>>
                          <span><?= htmlspecialchars($j['nama']) ?> · <span class="text-success">Rp <?= number_format((int)$j['harga'],0,',','.') ?></span></span>
                          <?php if ($belongsOther): ?><span class="badge bg-warning-subtle text-warning-emphasis ms-auto">milik toko lain</span><?php endif; ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                    <?php if (!$allJajanan): ?>
                      <div class="col-12 text-muted">Belum ada produk jajanan. Tambahkan dari <a href="/admin/jajanan.php">CRUD Jajanan</a>.</div>
                    <?php endif; ?>
                  </div>
                  <div class="mt-2">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Simpan Daftar Produk</button>
                    <span class="text-muted ms-2">Mencentang produk milik toko lain akan memindahkannya ke toko ini.</span>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($jjnNoToko): ?>
      <div class="alert alert-warning small mt-3 mb-0">
        <i class="bi bi-exclamation-circle-fill"></i>
        <strong><?= count($jjnNoToko) ?> produk</strong> belum di-assign ke toko manapun:
        <?php
          $names = array_map(function($x){ return htmlspecialchars($x['nama']); }, array_slice($jjnNoToko,0,8));
          echo implode(', ', $names);
          if (count($jjnNoToko) > 8) echo ', …';
        ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

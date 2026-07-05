<?php
// kajian.php — Kajian Literatur Buku (Revisi R14 - 25 Juni 2026)
// Perubahan R14:
//  (#7) Pemilik literatur: 1 atau lebih, dipilih dari member atau diketik manual (eksternal).
//  (#8) CRUD Kategori (tabel kajian_kategori) + filter dropdown dinamis.
//  (#9) Pagination 10 per halaman (server-side).
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_helpers.php';
require_once __DIR__.'/includes/scope.php';
send_security_headers(); require_login();
$pageTitle = 'Kajian Literatur Buku';
$u = current_user();
$IS_SUPER = scope_is_super();

// === Auto-migration ringan (idempotent) ===
try { db_exec("ALTER TABLE islami_kajian ADD COLUMN IF NOT EXISTS kategori VARCHAR(60) DEFAULT 'Umum'"); } catch (Throwable $e) {}
try { db_exec("CREATE TABLE IF NOT EXISTS kajian_kategori (
    id BIGSERIAL PRIMARY KEY,
    nama VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL,
    warna VARCHAR(20) DEFAULT 'secondary',
    created_at TIMESTAMP NOT NULL DEFAULT now()
)"); } catch (Throwable $e) {}
try {
  $cnt = (int)db_val("SELECT COUNT(*) FROM kajian_kategori");
  if ($cnt === 0) {
    $seed = [
      ['Umum','umum','secondary'],['Aqidah','aqidah','primary'],['Fiqih','fiqih','success'],
      ['Tafsir','tafsir','info'],['Hadist','hadist','warning'],['Sirah','sirah','danger'],
      ['Akhlak','akhlak','primary'],['Tazkiyah','tazkiyah','info'],['Sains Islam','sains-islam','success'],
      ['Sejarah Islam','sejarah-islam','warning'],['Parenting','parenting','danger'],
      ['Ekonomi Syariah','ekonomi-syariah','primary'],['Lainnya','lainnya','secondary'],
    ];
    foreach ($seed as $s) {
      try { db_exec("INSERT INTO kajian_kategori(nama,slug,warna) VALUES($1,$2,$3) ON CONFLICT DO NOTHING", $s); } catch(Throwable $e){}
    }
  }
} catch(Throwable $e){}
try { db_exec("CREATE TABLE IF NOT EXISTS kajian_pemilik (
    id BIGSERIAL PRIMARY KEY,
    kajian_id BIGINT NOT NULL REFERENCES islami_kajian(id) ON DELETE CASCADE,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    nama_eksternal VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)"); } catch (Throwable $e) {}
try { db_exec("CREATE INDEX IF NOT EXISTS kajian_pemilik_kajian_idx ON kajian_pemilik(kajian_id)"); } catch (Throwable $e) {}

// Daftar kategori dinamis dari DB
$KATEGORI_ROWS = db_all("SELECT id, nama, warna FROM kajian_kategori ORDER BY nama");
$KATEGORI = array_map(fn($r)=>$r['nama'], $KATEGORI_ROWS);
if (!$KATEGORI) $KATEGORI = ['Umum'];

$upDir = __DIR__.'/uploads/kajian';
if (!is_dir($upDir)) @mkdir($upDir, 0775, true);

function save_pdf_upload(string $field, string $upDir): ?string {
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return null;
    if ($_FILES[$field]['size'] > 15*1024*1024) return null;
    $name = 'kajian_'.time().'_'.bin2hex(random_bytes(4)).'.pdf';
    $dest = $upDir.'/'.$name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) return '/uploads/kajian/'.$name;
    return null;
}

// === R14 #7: simpan daftar pemilik ke tabel relasi ===
function save_pemilik(int $kajianId): void {
    db_exec("DELETE FROM kajian_pemilik WHERE kajian_id=$1", [$kajianId]);
    $userIds = $_POST['pemilik_user_ids'] ?? [];
    if (is_array($userIds)) {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                try { db_exec("INSERT INTO kajian_pemilik(kajian_id,user_id) VALUES($1,$2)", [$kajianId, $uid]); } catch(Throwable $e){}
            }
        }
    }
    $ext = trim((string)($_POST['pemilik_eksternal'] ?? ''));
    if ($ext !== '') {
        $parts = array_filter(array_map('trim', preg_split('/[\n,;]+/', $ext)));
        foreach ($parts as $nm) {
            $nm = substr($nm, 0, 120);
            try { db_exec("INSERT INTO kajian_pemilik(kajian_id,nama_eksternal) VALUES($1,$2)", [$kajianId, $nm]); } catch(Throwable $e){}
        }
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $kat = in_array($_POST['kategori'] ?? 'Umum', $KATEGORI, true) ? $_POST['kategori'] : 'Umum';

    // === R14 #8 / Revisi Juli 2026 — CRUD kategori hanya SUPERADMIN ===
    if ($a === 'kat_create' && $IS_SUPER) {
        $nm = substr(trim($_POST['nama'] ?? ''),0,80);
        $wr = substr(trim($_POST['warna'] ?? 'secondary'),0,20);
        if ($nm !== '') {
            $sl = strtolower(preg_replace('/[^a-z0-9]+/i','-', $nm));
            try { db_exec("INSERT INTO kajian_kategori(nama,slug,warna) VALUES($1,$2,$3)", [$nm,$sl,$wr]); $_SESSION['flash']='Kategori ditambahkan.'; }
            catch(Throwable $e){ $_SESSION['flash_err']='Gagal: kategori sudah ada?'; }
        }
        header('Location: /kajian.php#kat'); exit;
    }
    if ($a === 'kat_delete' && $IS_SUPER) {
        $id = (int)$_POST['id'];
        try { db_exec("DELETE FROM kajian_kategori WHERE id=$1", [$id]); $_SESSION['flash']='Kategori dihapus.'; } catch(Throwable $e){}
        header('Location: /kajian.php#kat'); exit;
    }
    if ($a === 'kat_edit' && $IS_SUPER) {
        $id = (int)$_POST['id'];
        $nm = substr(trim($_POST['nama'] ?? ''),0,80);
        $wr = substr(trim($_POST['warna'] ?? 'secondary'),0,20);
        if ($nm !== '') {
            $sl = strtolower(preg_replace('/[^a-z0-9]+/i','-', $nm));
            try { db_exec("UPDATE kajian_kategori SET nama=$1, slug=$2, warna=$3 WHERE id=$4", [$nm,$sl,$wr,$id]); $_SESSION['flash']='Kategori diperbarui.'; } catch(Throwable $e){}
        }
        header('Location: /kajian.php#kat'); exit;
    }

    if ($a === 'create') {
        $pdf = save_pdf_upload('pdf_file', $upDir);
        $row = db_one("INSERT INTO islami_kajian(user_id,judul,penulis,tipe,kategori,isi,link_web,pdf_path,link_video)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING id",
          [(int)$u['id'],
           substr(trim($_POST['judul'] ?? ''), 0, 180),
           substr(trim($_POST['penulis'] ?? ''), 0, 120),
           in_array($_POST['tipe'] ?? 'buku', ['buku','artikel','jurnal','pdf','web'], true) ? $_POST['tipe'] : 'buku',
           $kat,
           substr($_POST['isi'] ?? '', 0, 5000),
           substr(trim($_POST['link_web'] ?? ''), 0, 500),
           $pdf,
           substr(trim($_POST['link_video'] ?? ''), 0, 255),
          ]);
        $newId = (int)($row['id'] ?? 0);
        if ($newId > 0) save_pemilik($newId);
        $_SESSION['flash'] = 'Literatur ditambahkan.';
    } elseif ($a === 'edit') {
        $id = (int)$_POST['id'];
        $o = db_one("SELECT user_id, pdf_path FROM islami_kajian WHERE id=$1", [$id]);
        if ($o && ((int)$o['user_id'] === (int)$u['id'] || $u['role']==='admin')) {
            $pdf = save_pdf_upload('pdf_file', $upDir) ?: $o['pdf_path'];
            db_exec("UPDATE islami_kajian SET judul=$1, penulis=$2, tipe=$3, kategori=$4, isi=$5, link_web=$6, pdf_path=$7, link_video=$8, updated_at=now() WHERE id=$9",
              [substr(trim($_POST['judul'] ?? ''), 0, 180),
               substr(trim($_POST['penulis'] ?? ''), 0, 120),
               in_array($_POST['tipe'] ?? 'buku', ['buku','artikel','jurnal','pdf','web'], true) ? $_POST['tipe'] : 'buku',
               $kat,
               substr($_POST['isi'] ?? '', 0, 5000),
               substr(trim($_POST['link_web'] ?? ''), 0, 500),
               $pdf,
               substr(trim($_POST['link_video'] ?? ''), 0, 255),
               $id]);
            save_pemilik($id);
            $_SESSION['flash'] = 'Literatur diperbarui.';
        }
    } elseif ($a === 'delete') {
        $id = (int)$_POST['id'];
        $o = db_one("SELECT user_id, pdf_path FROM islami_kajian WHERE id=$1", [$id]);
        if ($o && ((int)$o['user_id'] === (int)$u['id'] || $u['role']==='admin')) {
            if (!empty($o['pdf_path']) && file_exists(__DIR__.$o['pdf_path'])) @unlink(__DIR__.$o['pdf_path']);
            db_exec("DELETE FROM islami_kajian WHERE id=$1", [$id]);
            $_SESSION['flash'] = 'Literatur dihapus.';
        }
    }
    header('Location: /kajian.php'); exit;
}

// === Filter & pagination (R14 #8, #9) ===
$q   = trim($_GET['q'] ?? '');
$kat = trim($_GET['kat'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page-1)*$perPage;

$where = []; $params = []; $i = 1;
if ($q !== '')   { $where[] = "(k.judul ILIKE \$$i OR k.penulis ILIKE \$$i OR k.isi ILIKE \$$i)"; $params[] = '%'.$q.'%'; $i++; }
if ($kat !== '' && in_array($kat,$KATEGORI,true)) { $where[] = "k.kategori = \$$i"; $params[] = $kat; $i++; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$totalRow = db_one("SELECT COUNT(*) AS c FROM islami_kajian k $wsql", $params);
$total = (int)($totalRow['c'] ?? 0);
$totalPages = max(1, (int)ceil($total/$perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page-1)*$perPage; }

$rows = db_all("SELECT k.*, u.nama, kom.nama AS komunitas_nama
                FROM islami_kajian k
                LEFT JOIN users u ON u.id=k.user_id
                LEFT JOIN komunitas kom ON kom.id=u.komunitas_id
                $wsql ORDER BY k.created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Ambil pemilik untuk semua kajian di halaman ini (R14 #7) + komunitas member (Revisi Juli 2026)
$pemilikMap = [];
if ($rows) {
    $ids = array_map(fn($r)=>(int)$r['id'], $rows);
    $placeholders = implode(',', array_map(fn($n)=>'$'.($n+1), array_keys($ids)));
    $pemilikRows = db_all("SELECT kp.kajian_id,
                                  COALESCE(us.nama, kp.nama_eksternal) AS nama,
                                  kp.user_id IS NOT NULL AS is_member,
                                  us.id AS user_id,
                                  kom.nama AS komunitas_nama
                           FROM kajian_pemilik kp
                           LEFT JOIN users us ON us.id=kp.user_id
                           LEFT JOIN komunitas kom ON kom.id=us.komunitas_id
                           WHERE kp.kajian_id IN ($placeholders) ORDER BY kp.id", $ids);
    foreach ($pemilikRows as $pr) $pemilikMap[(int)$pr['kajian_id']][] = $pr;
}

// Edit row
$editId = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? db_one("SELECT * FROM islami_kajian WHERE id=$1", [$editId]) : null;
if ($editRow && (int)$editRow['user_id'] !== (int)$u['id'] && $u['role']!=='admin') $editRow = null;
$editPemilik = [];
if ($editRow) {
    $editPemilik = db_all("SELECT user_id, nama_eksternal FROM kajian_pemilik WHERE kajian_id=$1", [(int)$editRow['id']]);
}
$editUserIds = array_filter(array_map(fn($x)=>(int)$x['user_id'], $editPemilik));
$editExternal = implode(', ', array_filter(array_map(fn($x)=>(string)$x['nama_eksternal'], $editPemilik)));

// Daftar member untuk dropdown pemilik — dikelompokkan per KOMUNITAS
// (Revisi Juli 2026 — Pemilik Literatur tampil per komunitas)
$members = db_all("SELECT u.id, u.nama, COALESCE(k.nama,'(Tanpa Komunitas)') AS komunitas_nama, COALESCE(k.id,0) AS kom_id
                   FROM users u
                   LEFT JOIN komunitas k ON k.id=u.komunitas_id
                   WHERE COALESCE(u.aktif, 1) <> 0
                   ORDER BY komunitas_nama, u.nama");
$membersByKom = [];
foreach ($members as $mb) { $membersByKom[$mb['komunitas_nama']][] = $mb; }

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
  <li class="breadcrumb-item"><a href="/islami.php">Islami</a></li>
  <li class="breadcrumb-item active">Kajian Literatur Buku</li>
</ol></nav>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<h4 class="mb-3"><i class="bi bi-journal-bookmark text-info"></i> Kajian Literatur Buku</h4>
<p class="text-muted small">Bagikan ringkasan / catatan buku &amp; literatur islami. Tentukan kategori &amp; pemilik literatur (anggota / eksternal).</p>

<!-- R14 #8: Filter dengan kategori dinamis -->
<form class="row g-2 mb-3" method="get">
  <div class="col-md-6"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari judul, penulis, atau isi..."></div>
  <div class="col-md-3"><select class="form-select" name="kat">
    <option value="">— Semua Kategori —</option>
    <?php foreach($KATEGORI_ROWS as $kr): ?>
      <option value="<?= htmlspecialchars($kr['nama']) ?>" <?= $kat===$kr['nama']?'selected':'' ?>><?= htmlspecialchars($kr['nama']) ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><button class="btn btn-outline-info w-100"><i class="bi bi-search"></i> Cari</button></div>
  <?php if ($q || $kat): ?><div class="col-md-1"><a href="/kajian.php" class="btn btn-outline-secondary w-100">Reset</a></div><?php endif; ?>
</form>

<?php if ($IS_SUPER): /* Revisi Juli 2026 — Kelola Kategori hanya SuperAdmin */ ?>
<div class="card shadow-sm mb-3" id="kat">
  <div class="card-header bg-light d-flex justify-content-between align-items-center">
    <span><i class="bi bi-tags-fill text-warning"></i> <strong>Kelola Kategori Literatur</strong> <small class="text-muted">(superadmin)</small></span>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#katBody">Tampilkan/Sembunyikan</button>
  </div>
  <div class="collapse" id="katBody">
    <div class="card-body">
      <form method="post" class="row g-2 mb-3">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="kat_create">
        <div class="col-md-6"><input class="form-control" name="nama" maxlength="80" placeholder="Nama kategori baru" required></div>
        <div class="col-md-4"><select class="form-select" name="warna">
          <?php foreach(['primary','secondary','success','danger','warning','info','dark'] as $w): ?>
            <option value="<?= $w ?>"><?= ucfirst($w) ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><button class="btn btn-warning w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
      </form>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Nama</th><th>Warna</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach($KATEGORI_ROWS as $kr): ?>
          <tr>
            <td><span class="badge bg-<?= htmlspecialchars($kr['warna']) ?>"><?= htmlspecialchars($kr['nama']) ?></span></td>
            <td><small><?= htmlspecialchars($kr['warna']) ?></small></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ke<?= (int)$kr['id'] ?>"><i class="bi bi-pencil"></i></button>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus kategori <?= htmlspecialchars($kr['nama']) ?>?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="kat_delete">
                <input type="hidden" name="id" value="<?= (int)$kr['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <tr><td colspan="3" class="p-0 border-0">
            <div class="collapse" id="ke<?= (int)$kr['id'] ?>">
              <form method="post" class="row g-2 p-2 bg-light-subtle">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="_action" value="kat_edit">
                <input type="hidden" name="id" value="<?= (int)$kr['id'] ?>">
                <div class="col-md-6"><input class="form-control form-control-sm" name="nama" value="<?= htmlspecialchars($kr['nama']) ?>" required></div>
                <div class="col-md-4"><select class="form-select form-select-sm" name="warna">
                  <?php foreach(['primary','secondary','success','danger','warning','info','dark'] as $w): ?>
                    <option value="<?= $w ?>" <?= $kr['warna']===$w?'selected':'' ?>><?= ucfirst($w) ?></option>
                  <?php endforeach; ?>
                </select></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Simpan</button></div>
              </form>
            </div>
          </td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($u): ?>
<form method="post" enctype="multipart/form-data" class="card card-body mb-3">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="<?= $editRow ? 'edit' : 'create' ?>">
  <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
  <h6 class="mb-2"><?= $editRow ? '✏️ Edit Literatur' : '📚 Tambah Literatur' ?></h6>
  <div class="row g-2">
    <div class="col-md-5"><input class="form-control" name="judul" maxlength="180" required placeholder="Judul buku/artikel" value="<?= htmlspecialchars($editRow['judul'] ?? '') ?>"></div>
    <div class="col-md-3"><input class="form-control" name="penulis" maxlength="120" placeholder="Penulis" value="<?= htmlspecialchars($editRow['penulis'] ?? '') ?>"></div>
    <div class="col-md-2"><select class="form-select" name="tipe">
      <?php foreach (['buku','artikel','jurnal','pdf','web'] as $t): ?>
        <option value="<?= $t ?>" <?= (($editRow['tipe'] ?? 'buku')===$t)?'selected':'' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-2"><select class="form-select" name="kategori">
      <?php foreach ($KATEGORI as $k): ?>
        <option value="<?= htmlspecialchars($k) ?>" <?= (($editRow['kategori'] ?? 'Umum')===$k)?'selected':'' ?>><?= htmlspecialchars($k) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="col-md-6"><input class="form-control" name="link_web" maxlength="500" placeholder="Link web (https://...)" value="<?= htmlspecialchars($editRow['link_web'] ?? '') ?>"></div>
    <div class="col-md-6"><input class="form-control" name="link_video" maxlength="255" placeholder="Link YouTube (https://youtu.be/...)" value="<?= htmlspecialchars($editRow['link_video'] ?? '') ?>"></div>

    <!-- R14 #7: Pemilik literatur (member + eksternal) -->
    <div class="col-md-6">
      <label class="small fw-semibold text-muted">Pemilik Literatur — Member per Komunitas (boleh pilih lebih dari 1)</label>
      <select class="form-select" name="pemilik_user_ids[]" multiple size="8">
        <?php foreach ($membersByKom as $komNama => $mbList): ?>
          <optgroup label="🏷️ <?= htmlspecialchars($komNama) ?>">
            <?php foreach ($mbList as $mb): ?>
              <option value="<?= (int)$mb['id'] ?>" <?= in_array((int)$mb['id'],$editUserIds,true)?'selected':'' ?>><?= htmlspecialchars($mb['nama']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Tahan Ctrl/Cmd untuk pilih lebih dari 1. Dikelompokkan per komunitas.</small>
    </div>
    <div class="col-md-6">
      <label class="small fw-semibold text-muted">Pemilik Eksternal (di luar member) — pisahkan dengan koma / baris baru</label>
      <textarea class="form-control" name="pemilik_eksternal" rows="5" placeholder="Mis: Perpustakaan Masjid Al-Hikmah, Ust. Ahmad Zaki, ..."><?= htmlspecialchars($editExternal) ?></textarea>
    </div>

    <div class="col-12"><textarea class="form-control" name="isi" rows="4" placeholder="Ringkasan / catatan kajian..."><?= htmlspecialchars($editRow['isi'] ?? '') ?></textarea></div>
  </div>
  <div class="mt-2">
    <button class="btn btn-info"><?= $editRow ? 'Simpan Perubahan' : 'Tambah Literatur' ?></button>
    <?php if ($editRow): ?><a href="/kajian.php" class="btn btn-link">Batal</a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<div class="small text-muted mb-2">Total <strong><?= $total ?></strong> literatur — Halaman <?= $page ?> dari <?= $totalPages ?></div>

<?php foreach ($rows as $r):
  $tipeBadge = ['buku'=>'success','artikel'=>'primary','jurnal'=>'info','pdf'=>'danger','web'=>'secondary'][$r['tipe'] ?? 'buku'] ?? 'secondary';
  $pemiliks = $pemilikMap[(int)$r['id']] ?? []; ?>
<div class="card mb-2"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h6 class="m-0">
        <span class="badge bg-<?= $tipeBadge ?> text-uppercase me-1"><?= htmlspecialchars($r['tipe'] ?? 'buku') ?></span>
        <?php if(!empty($r['kategori'])): ?><span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($r['kategori']) ?></span><?php endif; ?>
        <?= htmlspecialchars($r['judul']) ?>
      </h6>
      <div class="small text-muted">
        <?php if(!empty($r['penulis'])): ?>oleh <strong><?= htmlspecialchars($r['penulis']) ?></strong> · <?php endif; ?>
        Dibagikan <?= htmlspecialchars($r['nama'] ?? 'Anon') ?><?php if(!empty($r['komunitas_nama'])): ?> <span class="badge bg-light text-dark border">🏷️ <?= htmlspecialchars($r['komunitas_nama']) ?></span><?php endif; ?> · <?= htmlspecialchars($r['created_at']) ?>
      </div>
      <?php if ($pemiliks): ?>
        <div class="small mt-1"><i class="bi bi-people-fill text-info"></i> <strong>Pemilik:</strong>
          <?php foreach ($pemiliks as $pe): $isMember = ($pe['is_member']==='t'||$pe['is_member']===true||$pe['is_member']==1); ?>
            <span class="badge bg-<?= $isMember?'info':'secondary' ?> me-1">
              <?= $isMember ? '👤' : '🌐' ?> <?= htmlspecialchars($pe['nama']) ?><?php if ($isMember && !empty($pe['komunitas_nama'])): ?> · <span class="opacity-75">🏷️ <?= htmlspecialchars($pe['komunitas_nama']) ?></span><?php endif; ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($u && ((int)$r['user_id']===(int)$u['id'] || $u['role']==='admin')): ?>
    <div class="d-flex gap-1">
      <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= (int)$r['id'] ?>#"><i class="bi bi-pencil"></i></a>
      <form method="post" onsubmit="return confirm('Hapus?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php if(!empty($r['isi'])): ?><div class="mt-2"><?= nl2br(htmlspecialchars($r['isi'])) ?></div><?php endif; ?>
  <div class="mt-2 d-flex flex-wrap gap-2">
    <?php if(!empty($r['link_web'])): ?>
      <a href="<?= htmlspecialchars($r['link_web']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-globe"></i> Buka Web</a>
    <?php endif; ?>
    <?php if(!empty($r['pdf_path'])): ?><a href="<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Baca PDF</a><?php endif; ?>
    <?php if(!empty($r['link_video'])): ?>
      <a href="<?= htmlspecialchars($r['link_video']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info"><i class="bi bi-play-circle"></i> Lihat Video (YouTube)</a>
    <?php endif; ?>
    <?php
      // Revisi 27 Juni 2026 #1 — WA share berisi info lengkap literatur
      $_pe = $pemilikMap[(int)$r['id']] ?? [];
      $_peNames = $_pe ? implode(', ', array_map(fn($x)=>$x['nama'], $_pe)) : '';
      $_appBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'kawankeringat.local');
      $_pdfUrl = !empty($r['pdf_path']) ? $_appBase . $r['pdf_path'] : '';
      $_shareLines = [];
      $_shareLines[] = "📚 *".$r['judul']."*";
      if (!empty($r['penulis']))  $_shareLines[] = "✍️ Penulis: " . $r['penulis'];
      if (!empty($r['tipe']))     $_shareLines[] = "🏷️ Tipe: "    . strtoupper($r['tipe']);
      if (!empty($r['kategori'])) $_shareLines[] = "📂 Kategori: ". $r['kategori'];
      if ($_peNames !== '')       $_shareLines[] = "👥 Pemilik: " . $_peNames;
      if (!empty($r['nama']))     $_shareLines[] = "🙋 Dibagikan: ". $r['nama'];
      if (!empty($r['isi']))      $_shareLines[] = "\n📝 Ringkasan:\n" . mb_strimwidth($r['isi'], 0, 400, '…');
      if (!empty($r['link_web']))   $_shareLines[] = "🔗 Web: "   . $r['link_web'];
      if (!empty($r['link_video'])) $_shareLines[] = "▶️ Video: " . $r['link_video'];
      if ($_pdfUrl !== '')          $_shareLines[] = "📄 PDF: "   . $_pdfUrl;
      $_shareLines[] = "\n— Dibagikan via KawanKeringat · Kajian Literatur Islami";
      $_shareText = implode("\n", $_shareLines);
    ?>
    <a class="btn btn-sm btn-outline-success" href="https://wa.me/?text=<?= rawurlencode($_shareText) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> Bagikan ke WhatsApp</a>
  </div>
</div></div>
<?php endforeach; if (!$rows): ?><div class="text-muted">Belum ada literatur untuk filter ini.</div><?php endif; ?>

<?php
// === R14 #9: Pagination ===
$qsBase = http_build_query(array_filter(['q'=>$q, 'kat'=>$kat]));
$qsBase = $qsBase ? '&'.$qsBase : '';
if ($totalPages > 1):
  $from = max(1, $page-3); $to = min($totalPages, $from+6); $from = max(1, $to-6);
?>
<nav aria-label="Pagination literatur"><ul class="pagination pagination-sm justify-content-center">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?page=<?= max(1,$page-1) ?><?= $qsBase ?>">«</a></li>
  <?php for ($p=$from; $p<=$to; $p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?><?= $qsBase ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="?page=<?= min($totalPages,$page+1) ?><?= $qsBase ?>">»</a></li>
</ul></nav>
<?php endif; ?>

<!-- Modal popup Video YouTube -->
<div class="modal fade" id="ytModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-youtube text-danger"></i> <span id="ytModalTitle">Video</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="ratio ratio-16x9"><iframe id="ytFrame" src="" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>
      </div>
      <div class="modal-footer">
        <a id="ytOpen" href="#" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-up-right"></i> Buka di YouTube</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal popup Web iframe -->
<div class="modal fade" id="webModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="height:85vh">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-globe text-primary"></i> <span id="webModalTitle">Web</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="webFrame" src="" style="width:100%;height:100%;border:0" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">Jika halaman kosong, gunakan tombol "Buka di Tab Baru".</small>
        <a id="webOpen" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka di Tab Baru</a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function ytId(url){
    if(!url) return null;
    var m = url.match(/(?:youtu\.be\/|v=|\/embed\/|\/shorts\/)([A-Za-z0-9_-]{6,})/);
    return m ? m[1] : null;
  }
  var ytModalEl = document.getElementById('ytModal');
  var webModalEl = document.getElementById('webModal');
  var ytModal = new bootstrap.Modal(ytModalEl);
  var webModal = new bootstrap.Modal(webModalEl);
  document.querySelectorAll('.js-video-pop').forEach(function(b){
    b.addEventListener('click', function(){
      var url = b.dataset.url, title = b.dataset.title || 'Video';
      var id = ytId(url);
      document.getElementById('ytModalTitle').textContent = title;
      document.getElementById('ytOpen').href = url;
      var src = id ? ('https://www.youtube.com/embed/'+id+'?autoplay=1&rel=0') : url;
      document.getElementById('ytFrame').src = src;
      ytModal.show();
    });
  });
  ytModalEl.addEventListener('hidden.bs.modal', function(){ document.getElementById('ytFrame').src = ''; });
  document.querySelectorAll('.js-web-pop').forEach(function(b){
    b.addEventListener('click', function(){
      var url = b.dataset.url, title = b.dataset.title || 'Web';
      document.getElementById('webModalTitle').textContent = title;
      document.getElementById('webOpen').href = url;
      document.getElementById('webFrame').src = url;
      webModal.show();
    });
  });
  webModalEl.addEventListener('hidden.bs.modal', function(){ document.getElementById('webFrame').src = 'about:blank'; });
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

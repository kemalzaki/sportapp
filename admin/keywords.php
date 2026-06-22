<?php
/**
 * admin/keywords.php — Revisi 22 Juni 2026 R7
 *
 * CRUD kata kunci pencarian video YouTube untuk halaman kalistenik.php
 * (kategori "olahraga") dan survival.php (kategori "survival").
 *
 * Tujuan: agar query pencarian user dibatasi pada kata kunci yang relevan
 *         dengan topik (olahraga / survival), bukan kata kunci lain.
 *
 * Tabel: search_keywords(id, kategori, kata, aktif, urutan, created_at)
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers(); require_login();
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { http_response_code(403); exit('Forbidden'); }
$pageTitle = 'Kata Kunci Filter Pencarian';

@db_exec("CREATE TABLE IF NOT EXISTS search_keywords (
    id BIGSERIAL PRIMARY KEY,
    kategori VARCHAR(20) NOT NULL,
    kata TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    urutan INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");
@db_exec("CREATE INDEX IF NOT EXISTS idx_search_keywords_kat ON search_keywords(kategori, aktif)");

// Seed awal kalau kosong
$cnt = (int) db_val("SELECT COUNT(*) FROM search_keywords");
if ($cnt === 0) {
    foreach (['olahraga','pertandingan','match','tutorial','teknik','latihan'] as $k)
        db_exec("INSERT INTO search_keywords(kategori,kata,aktif) VALUES('olahraga',$1,TRUE)", [$k]);
    foreach (['survival','bushcraft','wilderness','camping','hutan'] as $k)
        db_exec("INSERT INTO search_keywords(kategori,kata,aktif) VALUES('survival',$1,TRUE)", [$k]);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='add') {
        $kat = in_array($_POST['kategori'] ?? '', ['olahraga','survival'], true) ? $_POST['kategori'] : 'olahraga';
        $kata = trim((string)($_POST['kata'] ?? ''));
        if ($kata !== '') {
            db_exec("INSERT INTO search_keywords(kategori,kata,aktif,urutan) VALUES($1,$2,TRUE,$3)",
                [$kat, mb_substr($kata,0,80), (int)($_POST['urutan'] ?? 0)]);
            $_SESSION['flash_ok'] = 'Kata kunci ditambahkan.';
        }
    } elseif ($a==='toggle') {
        db_exec("UPDATE search_keywords SET aktif = NOT aktif WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM search_keywords WHERE id=$1", [(int)$_POST['id']]);
        $_SESSION['flash_ok'] = 'Kata kunci dihapus.';
    } elseif ($a==='update') {
        $kata = trim((string)($_POST['kata'] ?? ''));
        if ($kata !== '') {
            db_exec("UPDATE search_keywords SET kata=$1, urutan=$2 WHERE id=$3",
                [mb_substr($kata,0,80), (int)($_POST['urutan'] ?? 0), (int)$_POST['id']]);
        }
    }
    header('Location: keywords.php'); exit;
}

$rows = db_all("SELECT * FROM search_keywords ORDER BY kategori, urutan, id");
$byKat = ['olahraga'=>[], 'survival'=>[]];
foreach ($rows as $r) $byKat[$r['kategori']][] = $r;

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-funnel-fill text-primary"></i> Kata Kunci Filter Pencarian Video</h2>
<p class="text-muted small">
  Kata kunci di sini akan <strong>ditambahkan otomatis</strong> ke query pencarian YouTube pada halaman
  <code>kalistenik.php</code> (kategori <em>olahraga</em>) dan <code>survival.php</code> (kategori <em>survival</em>),
  agar hasil hanya menampilkan video yang relevan dengan topik tersebut.
</p>

<?php if(!empty($_SESSION['flash_ok'])): ?>
  <div class="alert alert-success small"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
  <?php unset($_SESSION['flash_ok']); endif; ?>

<div class="row g-3">
<?php foreach (['olahraga'=>'Olahraga (Kalistenik)','survival'=>'Survival'] as $kat=>$label): ?>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="bi bi-tag"></i> <?= htmlspecialchars($label) ?></div>
      <div class="card-body">
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="add">
          <input type="hidden" name="kategori" value="<?= $kat ?>">
          <div class="col-7"><input name="kata" class="form-control form-control-sm" placeholder="contoh: <?= $kat==='olahraga'?'pertandingan':'bushcraft' ?>" required></div>
          <div class="col-3"><input type="number" name="urutan" value="0" class="form-control form-control-sm" placeholder="urut"></div>
          <div class="col-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i></button></div>
        </form>
        <table class="table table-sm">
          <thead><tr><th>#</th><th>Kata Kunci</th><th>Aktif</th><th>Urut</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($byKat[$kat] as $i=>$r): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td>
                <form method="post" class="d-flex gap-1">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input name="kata" value="<?= htmlspecialchars($r['kata']) ?>" class="form-control form-control-sm">
                  <input type="number" name="urutan" value="<?= (int)$r['urutan'] ?>" class="form-control form-control-sm" style="width:70px">
                  <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-save"></i></button>
                </form>
              </td>
              <td>
                <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-<?= $r['aktif']==='t'||$r['aktif']===true ? 'success' : 'outline-secondary' ?>">
                    <?= ($r['aktif']==='t'||$r['aktif']===true) ? 'ON' : 'off' ?>
                  </button>
                </form>
              </td>
              <td><?= (int)$r['urutan'] ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Hapus?');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$byKat[$kat]): ?><tr><td colspan="5" class="text-center text-muted small">Belum ada kata kunci.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

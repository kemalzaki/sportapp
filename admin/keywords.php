<?php
/**
 * admin/keywords.php — Revisi 23 Juni 2026
 *
 * CRUD daftar KATA TERLARANG (blocklist) untuk pencarian video di
 * halaman artikel_olahraga.php. Bila user mengetik kata kunci yang
 * mengandung salah satu kata di tabel ini (kategori: kasar / abuse / porno),
 * pencarian DIBATALKAN dan ditampilkan peringatan popup.
 *
 * Tabel: search_keywords(id, kategori, kata, aktif, urutan, created_at)
 *   - kategori: 'kasar' | 'abuse' | 'porno'  (blocklist)
 *   - kategori lama 'olahraga' / 'survival' tidak dipakai lagi
 *     untuk filter pencarian, namun TIDAK dihapus otomatis.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
send_security_headers(); require_login();
$u = current_user();
if (($u['role'] ?? '') !== 'superadmin') { http_response_code(403); exit('Forbidden'); }
$pageTitle = 'Kata Terlarang Pencarian';

@db_exec("CREATE TABLE IF NOT EXISTS search_keywords (
    id BIGSERIAL PRIMARY KEY,
    kategori VARCHAR(20) NOT NULL,
    kata TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    urutan INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");
@db_exec("CREATE INDEX IF NOT EXISTS idx_search_keywords_kat ON search_keywords(kategori, aktif)");

// Seed awal blocklist kalau belum ada satupun baris dengan kategori blocklist.
$cntBlock = (int) db_val("SELECT COUNT(*) FROM search_keywords WHERE kategori IN('kasar','abuse','porno')");
if ($cntBlock === 0) {
    $seed = [
      'kasar' => ['anjing','bangsat','goblok','tolol','kontol','memek','asu','bajingan'],
      'abuse' => ['bunuh','bully','sadis','penyiksaan','kekerasan'],
      'porno' => ['porn','xxx','bokep','telanjang','sex','seks','vcs','ngentot'],
    ];
    foreach ($seed as $kat=>$arr) {
        foreach ($arr as $k) {
            db_exec("INSERT INTO search_keywords(kategori,kata,aktif) VALUES($1,$2,TRUE)", [$kat,$k]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    $allowedKat = ['kasar','abuse','porno'];
    if ($a==='add') {
        $kat = in_array($_POST['kategori'] ?? '', $allowedKat, true) ? $_POST['kategori'] : 'kasar';
        $kata = trim((string)($_POST['kata'] ?? ''));
        if ($kata !== '') {
            db_exec("INSERT INTO search_keywords(kategori,kata,aktif,urutan) VALUES($1,$2,TRUE,$3)",
                [$kat, mb_substr($kata,0,80), (int)($_POST['urutan'] ?? 0)]);
            $_SESSION['flash_ok'] = 'Kata terlarang ditambahkan.';
        }
    } elseif ($a==='toggle') {
        db_exec("UPDATE search_keywords SET aktif = NOT aktif WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM search_keywords WHERE id=$1", [(int)$_POST['id']]);
        $_SESSION['flash_ok'] = 'Kata terlarang dihapus.';
    } elseif ($a==='update') {
        $kata = trim((string)($_POST['kata'] ?? ''));
        if ($kata !== '') {
            db_exec("UPDATE search_keywords SET kata=$1, urutan=$2 WHERE id=$3",
                [mb_substr($kata,0,80), (int)($_POST['urutan'] ?? 0), (int)$_POST['id']]);
        }
    }
    header('Location: keywords.php'); exit;
}

$rows = db_all("SELECT * FROM search_keywords WHERE kategori IN('kasar','abuse','porno') ORDER BY kategori, urutan, id");
$byKat = ['kasar'=>[], 'abuse'=>[], 'porno'=>[]];
foreach ($rows as $r) if (isset($byKat[$r['kategori']])) $byKat[$r['kategori']][] = $r;

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-shield-exclamation text-danger"></i> Kata Terlarang Pencarian Video</h2>
<p class="text-muted small mb-2">
  Daftar kata yang <strong>tidak boleh</strong> diketik user di kotak pencarian video pada halaman
  <code>artikel_olahraga.php</code>. Bila kata kunci pencarian mengandung salah satu kata aktif di tabel ini
  (kasar, abuse, atau pornografi), pencarian <strong>dibatalkan</strong> dan ditampilkan
  <em>popup peringatan</em>. Video tidak akan diputar.
</p>

<div class="alert alert-info small py-2 mb-3">
  <i class="bi bi-info-circle"></i> <strong>Keterangan:</strong>
  <ul class="mb-0 ps-3">
    <li><strong>Aktif (ON/off)</strong> — <code>1 = aktif</code> kata ikut diblokir, <code>0 = nonaktif</code> kata diabaikan (tidak dihapus, bisa diaktifkan lagi).</li>
    <li><strong>Urut</strong> — nomor urutan tampil. <code>0</code> = default, makin kecil makin atas.</li>
    <li>Pencocokan dilakukan case-insensitive sebagai <em>substring</em>. Contoh kata <code>porn</code> akan memblokir <code>"pornhub"</code>, <code>"porno"</code>, dll.</li>
  </ul>
</div>

<?php if(!empty($_SESSION['flash_ok'])): ?>
  <div class="alert alert-success small"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
  <?php unset($_SESSION['flash_ok']); endif; ?>

<div class="row g-3">
<?php foreach (['kasar'=>['Kata Kasar','bi-emoji-angry text-danger','anjing'],
                'abuse'=>['Abuse / Kekerasan','bi-exclamation-octagon text-warning','bully'],
                'porno'=>['Pornografi','bi-eye-slash text-danger','bokep']] as $kat=>$meta):
  [$label,$icon,$ph] = $meta; ?>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($label) ?></div>
      <div class="card-body">
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="add">
          <input type="hidden" name="kategori" value="<?= $kat ?>">
          <div class="col-7"><input name="kata" class="form-control form-control-sm" placeholder="contoh: <?= htmlspecialchars($ph) ?>" required></div>
          <div class="col-3"><input type="number" name="urutan" value="0" class="form-control form-control-sm" placeholder="urut"></div>
          <div class="col-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i></button></div>
        </form>
        <table class="table table-sm">
          <thead><tr><th>#</th><th>Kata</th><th>Aktif</th><th>Urut</th><th></th></tr></thead>
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
          <?php if(!$byKat[$kat]): ?><tr><td colspan="5" class="text-center text-muted small">Belum ada kata.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

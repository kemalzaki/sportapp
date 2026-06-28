<?php
/**
 * admin/paket_member.php — Revisi R24 (28 Juni 2026)
 * CRUD "Pengaturan Paket Member" untuk LABEL paket per item menu navigasi.
 * Halaman ini terpisah dari admin/menu.php agar fokus mengatur badge paket
 * (Gratis/PRO/Komunitas) yang muncul di samping nama menu.
 *
 * Menggunakan tabel `nav_menu` yang sudah ada (kolom `paket`).
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Pengaturan Paket Member';

// Auto-migrasi kolom (idempotent)
try { db_exec("ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket VARCHAR(20)"); } catch (Throwable $e) {}

/* Revisi R25 (28 Juni 2026) — Seed otomatis dari struktur menu bottom-nav PWA
   ketika tabel nav_menu masih kosong. Item yang dipakai sesuai includes/bottom_nav.php
   (Beranda / Aktivitas / Upload / Kalori / Saya) + beberapa item drawer umum. */
try {
    $hasAny = (int)db_val("SELECT COUNT(*) FROM nav_menu");
    if ($hasAny === 0) {
        $seed = [
            // posisi 'bottom' = PWA bottom nav
            ['Beranda',   '/index.php',           'bi-house-door-fill', 'bottom', 1, 'gratis'],
            ['Aktivitas', '/riwayat.php',         'bi-bar-chart-fill',  'bottom', 2, 'gratis'],
            ['Upload',    '/upload.php',          'bi-plus-lg',         'bottom', 3, 'gratis'],
            ['Kalori',    '/kalori_mingguan.php', 'bi-egg-fried',       'bottom', 4, 'pro'],
            ['Saya',      '/profile.php',         'bi-person-fill',     'bottom', 5, 'gratis'],
            // posisi 'drawer' = item umum
            ['Tracking Jalur',  '/live_tracking.php', 'bi-geo-alt',      'drawer', 1, 'pro'],
            ['Survival Mode',   '/survival.php',      'bi-tree-fill',    'drawer', 2, 'komunitas'],
            ['Artikel Olahraga','/artikel_olahraga.php','bi-journal-text','drawer', 3, 'gratis'],
            ['Opini Viral',     '/opini_viral.php',   'bi-megaphone',    'drawer', 4, 'gratis'],
            ['Flyover Lirik',   '/flyover.php',       'bi-music-note',   'drawer', 5, 'pro'],
            // posisi 'top'
            ['Cari Aktivitas',  '/search.php',        'bi-search',       'top',    1, 'gratis'],
            ['Notifikasi',      '/index.php#notif',   'bi-bell',         'top',    2, 'gratis'],
        ];
        foreach ($seed as $r) {
            db_exec("INSERT INTO nav_menu(label,url,icon,posisi,urutan,paket,aktif,target)
                     VALUES($1,$2,$3,$4,$5,$6,true,'_self')", $r);
        }
    }
} catch (Throwable $e) { /* abaikan supaya halaman tetap render */ }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a==='set_one') {
            $id    = (int)($_POST['id'] ?? 0);
            $paket = trim((string)($_POST['paket'] ?? ''));
            if (!in_array($paket, ['','gratis','pro','komunitas'], true)) $paket = '';
            db_exec("UPDATE nav_menu SET paket=$1 WHERE id=$2", [$paket?:null, $id]);
            $_SESSION['flash'] = 'Label paket disimpan.';
        } elseif ($a==='bulk') {
            $items = $_POST['paket'] ?? [];
            if (is_array($items)) {
                $n = 0;
                foreach ($items as $id=>$pk) {
                    $id = (int)$id;
                    $pk = trim((string)$pk);
                    if (!in_array($pk, ['','gratis','pro','komunitas'], true)) $pk = '';
                    db_exec("UPDATE nav_menu SET paket=$1 WHERE id=$2", [$pk?:null, $id]);
                    $n++;
                }
                $_SESSION['flash'] = "Disimpan untuk $n item menu.";
            }
        } elseif ($a==='clear_all') {
            db_exec("UPDATE nav_menu SET paket=NULL");
            $_SESSION['flash'] = 'Semua label paket dihapus.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: paket_member.php'); exit;
}

/* Revisi R26 (28 Juni 2026) — Fix:
   - Warning "Undefined array key paket" (line 83): sebelumnya
     in_array($_GET['paket'] ?? '', [...]) yang true → return $_GET['paket']
     yang BISA undefined. Pakai $rawPaket variabel dulu.
   - "bind message supplies 0 parameters but prepared statement requires 1":
     terjadi ketika filter Paket dipilih lalu Reset/refresh meninggalkan
     placeholder $N yang tidak konsisten. Rebuild $args & $where pakai
     counter eksplisit. */
$rawPosisi = isset($_GET['posisi']) ? (string)$_GET['posisi'] : '';
$rawPaket  = isset($_GET['paket'])  ? (string)$_GET['paket']  : '';
$fPosisi = in_array($rawPosisi, ['drawer','top','bottom'], true) ? $rawPosisi : '';
$fPaket  = in_array($rawPaket,  ['gratis','pro','komunitas'],  true) ? $rawPaket  : '';
$fPaketTanpa = ($rawPaket === '-'); // (tanpa label)

$args = []; $clauses = ['1=1'];
if ($fPosisi !== '') { $args[] = $fPosisi; $clauses[] = 'm.posisi=$'.count($args); }
if ($fPaketTanpa)    { $clauses[] = "(m.paket IS NULL OR m.paket='')"; }
elseif ($fPaket !== '') { $args[] = $fPaket; $clauses[] = 'm.paket=$'.count($args); }
$where = 'WHERE '.implode(' AND ', $clauses);

$rows = db_all("SELECT m.id,m.label,m.url,m.icon,m.posisi,m.paket,p.label AS parent_label
                FROM nav_menu m LEFT JOIN nav_menu p ON p.id=m.parent_id
                $where ORDER BY m.posisi, COALESCE(m.parent_id,0), m.urutan, m.id", $args);

$stat = db_all("SELECT COALESCE(NULLIF(paket,''),'(tanpa)') AS k, COUNT(*) AS c
                FROM nav_menu GROUP BY 1 ORDER BY 1");

include __DIR__.'/../includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
  <li class="breadcrumb-item"><a href="/index.php">Admin</a></li>
  <li class="breadcrumb-item"><a href="/admin/sistem.php">Pengaturan Lainnya</a></li>
  <li class="breadcrumb-item active">Pengaturan Paket Member</li>
</ol></nav>

<h2 class="mb-1"><i class="bi bi-stars text-warning"></i> Pengaturan Paket Member</h2>
<p class="text-muted small mb-3">Tentukan <b>label paket</b> yang akan muncul di samping nama menu navigasi.
   Mendukung 3 paket: <span class="badge bg-secondary">🆓 Gratis</span>
   <span class="badge bg-warning text-dark">⭐ PRO</span>
   <span class="badge bg-success">👥 Komunitas</span>. Pilih <i>“— tanpa —”</i> jika item tidak perlu badge.</p>

<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="row g-2 mb-3">
  <?php foreach ($stat as $s): ?>
    <div class="col-6 col-md-3">
      <div class="card text-center"><div class="card-body py-2">
        <div class="small text-muted"><?= htmlspecialchars($s['k']) ?></div>
        <div class="h4 mb-0"><?= (int)$s['c'] ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Revisi R25 (28 Juni 2026) — FIX: form GET tidak boleh membungkus form POST (nested form invalid HTML).
     Sebelumnya menyebabkan tombol "Hapus Semua Label" mengirim parameter filter sebagai GET dan
     muncul warning/inkonsistensi. Sekarang dipisah jadi 2 form sejajar. -->
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <form method="get" class="d-flex flex-wrap gap-2 mb-0">
    <select name="posisi" class="form-select form-select-sm" style="width:auto">
      <option value="">— Semua posisi —</option>
      <?php foreach (['drawer','top','bottom'] as $p): ?>
        <option value="<?= $p ?>" <?= $fPosisi===$p?'selected':'' ?>><?= $p ?></option>
      <?php endforeach; ?>
    </select>
    <select name="paket" class="form-select form-select-sm" style="width:auto">
      <option value="">— Semua paket —</option>
      <option value="-"          <?= (isset($_GET['paket'])&&$_GET['paket']==='-')?'selected':'' ?>>(tanpa label)</option>
      <option value="gratis"     <?= $fPaket==='gratis'?'selected':'' ?>>🆓 Gratis</option>
      <option value="pro"        <?= $fPaket==='pro'?'selected':'' ?>>⭐ PRO</option>
      <option value="komunitas"  <?= $fPaket==='komunitas'?'selected':'' ?>>👥 Komunitas</option>
    </select>
    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
    <a href="/admin/paket_member.php" class="btn btn-sm btn-outline-secondary">Reset</a>
  </form>
  <form method="post" class="ms-auto mb-0" onsubmit="return confirm('Hapus SEMUA label paket?');">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="clear_all">
    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-eraser"></i> Hapus Semua Label</button>
  </form>
</div>

<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="_action" value="bulk">
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr>
        <th>#</th><th>Label Menu</th><th>URL</th><th>Posisi</th><th>Parent</th><th>Paket (label)</th><th class="text-end">Preview</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $cur = strtolower((string)($r['paket']??'')); ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= $r['icon']?'<i class="bi '.htmlspecialchars($r['icon']).'"></i> ':'' ?><?= htmlspecialchars($r['label']) ?></td>
          <td class="small text-muted"><?= htmlspecialchars($r['url']) ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($r['posisi']) ?></span></td>
          <td class="small"><?= htmlspecialchars($r['parent_label'] ?? '—') ?></td>
          <td>
            <select name="paket[<?= (int)$r['id'] ?>]" class="form-select form-select-sm" style="min-width:160px">
              <option value=""          <?= $cur===''?'selected':'' ?>>— tanpa —</option>
              <option value="gratis"    <?= $cur==='gratis'?'selected':'' ?>>🆓 Gratis</option>
              <option value="pro"       <?= $cur==='pro'?'selected':'' ?>>⭐ PRO</option>
              <option value="komunitas" <?= $cur==='komunitas'?'selected':'' ?>>👥 Komunitas</option>
            </select>
          </td>
          <td class="text-end small">
            <?php
              $map = ['gratis'=>['secondary','🆓 Gratis'],'pro'=>['warning','⭐ PRO'],'komunitas'=>['success','👥 Komunitas']];
              if (isset($map[$cur])) {
                $b = $map[$cur];
                echo '<span>'.htmlspecialchars($r['label']).'</span> <span class="badge bg-'.$b[0].'" style="font-size:.7em">'.$b[1].'</span>';
              } else {
                echo '<span class="text-muted">'.htmlspecialchars($r['label']).' <i>(tanpa label)</i></span>';
              }
            ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted small">Tidak ada item menu yang cocok dengan filter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex gap-2 sticky-bottom bg-body py-2 border-top">
    <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan Semua</button>
    <a href="/admin/menu.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-nested"></i> Kelola Menu Navigasi</a>
    <span class="text-muted small ms-auto align-self-center">Label akan muncul otomatis di drawer/header berkat <code>includes/menu_render.php</code>.</span>
  </div>
</form>

<?php include __DIR__.'/../includes/footer.php'; ?>

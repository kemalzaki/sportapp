<?php
/**
 * admin/menu.php
 * CRUD Navigasi Menu gaya CMS WordPress (parent/child 1 level).
 * Posisi: drawer (default), top, bottom.
 *
 * Revisi 29 Juni 2026:
 *  - Kolom `paket` diperbesar (TEXT) agar bisa menampung multi-paket (mis. "pro,komunitas").
 *  - Form mendukung CHECKBOX paket (pilih ≥1 label) — tidak lagi single dropdown.
 *  - Auto-seed 42 item drawer default (idempotent) bila tabel drawer masih kosong.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require_role('admin');
$pageTitle = 'Navigasi Menu (CMS)';

try { db_exec("ALTER TABLE nav_menu ADD COLUMN IF NOT EXISTS paket TEXT"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE nav_menu ALTER COLUMN paket TYPE TEXT"); } catch (Throwable $e) {}

/* ============================================================
 * Auto-seed drawer default (42 item) sesuai revisi 29 Juni 2026.
 * Hanya dijalankan bila DRAWER masih kosong, sehingga tidak menimpa
 * konfigurasi yang sudah ada.
 * ============================================================ */
function _menu_seed_default(): void {
    $count = (int)db_val("SELECT COUNT(*) FROM nav_menu WHERE posisi='drawer'");
    if ($count > 0) return;
    // [label, url, icon, paket]
    $items = [
        ['Monitoring',                                    '/monitoring.php',           'bi-activity',          'komunitas'],
        ['Upload Aktivitas',                              '/upload.php',               'bi-cloud-arrow-up',    ''],
        ['Riwayat Aktivitas',                             '/riwayat.php',              'bi-clock-history',     ''],
        ['Tracking Jalur',                                '/run.php',                  'bi-geo-alt',           'komunitas'],
        ['Live Tracking / Beacon',                        '/live_tracking.php',        'bi-broadcast',         'komunitas'],
        ['Video Flyover 3D',                              '/flyover.php',              'bi-badge-3d',          'komunitas'],
        ['Eksplorasi Rute & Peta Canggih',                '/run.php',                  'bi-map',               'pro,komunitas'],
        ['Kalori Badminton',                              '/kalori_badminton.php',     'bi-trophy',            'pro,komunitas'],
        ['Kalori Renang',                                 '/kalori_renang.php',        'bi-water',             'pro,komunitas'],
        ['Kalori Pingpong',                               '/kalori_pingpong.php',      'bi-circle',            'pro,komunitas'],
        ['Kalori Futsal',                                 '/kalori_futsal.php',        'bi-dribbble',          'pro,komunitas'],
        ['Kalori Mingguan',                               '/kalori_mingguan.php',      'bi-egg-fried',         ''],
        ['Kalender',                                      '/calendar.php',             'bi-calendar2-week',    ''],
        ['Event',                                         '/event.php',                'bi-calendar-event',    ''],
        ['Tempat Olahraga',                               '/tempat.php',               'bi-pin-map',           'komunitas'],
        ['Kalkulator Olahraga',                           '/kalkulator.php',           'bi-calculator',        ''],
        ['Kalkulator Jantung',                            '/kalkulator_jantung.php',   'bi-heart-pulse',       ''],
        ['Kalkulator Kesehatan',                          '/kalkulator_kesehatan.php', 'bi-clipboard2-pulse',  ''],
        ['Gaya Hidup Sehat',                              '/gaya_hidup.php',           'bi-emoji-smile',       ''],
        ['Berita Olahraga',                               '/berita.php',               'bi-newspaper',         ''],
        ['Informasi Opini Terkini / Viral',               '/opini_viral.php',          'bi-megaphone-fill',    ''],
        ['Perkiraan Cuaca',                               '/cuaca.php',                'bi-cloud-sun-fill',    ''],
        ['IPTV',                                          '/iptv.php',                 'bi-tv',                'pro,komunitas'],
        ['Toko Perlengkapan Olahraga Terdekat',           '/toko_olahraga.php',        'bi-shop',              'pro,komunitas'],
        ['Hidup Sehat',                                   '/hidup_sehat.php',          'bi-heart',             ''],
        ['Kesehatan',                                     '/kesehatan.php',            'bi-bandaid',           ''],
        ['Kalistenik',                                    '/kalistenik.php',           'bi-person-arms-up',    ''],
        ['Artikel Olahraga & Teknik',                     '/artikel_olahraga.php',     'bi-journal-text',      'pro,komunitas'],
        ['Cedera Olahraga & Penanganan',                  '/cedera_olahraga.php',      'bi-bandaid-fill',      'pro,komunitas'],
        ['Lacak Puskesmas / RS Terdekat',                 '/lacak_faskes.php',         'bi-hospital-fill',     'pro,komunitas'],
        ['Survival Mode',                                 '/survival.php',             'bi-compass',           'pro,komunitas'],
        ['Paket Anak 2-4 Tahun',                          '/paket_anak_2_4.php',       'bi-emoji-laughing',    'pro'],
        ['Paket Anak 4-6 Tahun',                          '/paket_anak_4_6.php',       'bi-emoji-laughing',    'pro'],
        ['Paket Anak 7-9 Tahun',                          '/paket_anak_7_9.php',       'bi-emoji-laughing',    'pro'],
        ['Paket Anak 10-12 Tahun',                        '/paket_anak_10_12.php',     'bi-emoji-laughing',    'pro'],
        ['Pesan / Pemandu Olahraga (WA) — Paket Anak',    'https://wa.me/?text=Halo%20saya%20butuh%20pemandu%20olahraga%20paket%20anak', 'bi-whatsapp', 'pro'],
        ['Paket Lansia 55-69 Tahun',                      '/paket_lansia_55_69.php',   'bi-person-walking',    'pro'],
        ['Paket Lansia 70+ Tahun',                        '/paket_lansia_70.php',      'bi-person-walking',    'pro'],
        ['Pesan / Pemandu Olahraga (WA) — Paket Lansia',  'https://wa.me/?text=Halo%20saya%20butuh%20pemandu%20olahraga%20paket%20lansia', 'bi-whatsapp', 'pro'],
        ['Daftar Tempat Olahraga',                        '/tempat_list.php',          'bi-pin-map-fill',      'komunitas'],
        ['Bookmark',                                      '/bookmark.php',             'bi-bookmark',          ''],
        ['Hub Islami',                                    '/islami.php',               'bi-moon-stars',        'komunitas'],
    ];
    $urut = 1;
    foreach ($items as $it) {
        $tgt = str_starts_with($it[1], 'http') ? '_blank' : '_self';
        db_exec("INSERT INTO nav_menu(label,url,icon,parent_id,urutan,aktif,target,posisi,paket)
                 VALUES($1,$2,$3,NULL,$4,'t',$5,'drawer',$6)",
            [$it[0], $it[1], $it[2], $urut, $tgt, $it[3] ?: null]);
        $urut++;
    }
}
try { _menu_seed_default(); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'add' || $a === 'edit') {
            $label  = substr(trim($_POST['label'] ?? ''),0,80);
            $url    = substr(trim($_POST['url'] ?? '#'),0,255);
            $icon   = substr(trim($_POST['icon'] ?? ''),0,60);
            $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
            $urut   = (int)($_POST['urutan'] ?? 0);
            $aktif  = !empty($_POST['aktif']);
            $target = in_array($_POST['target'] ?? '_self', ['_self','_blank'], true) ? $_POST['target'] : '_self';
            $posisi = in_array($_POST['posisi'] ?? 'drawer', ['drawer','top','bottom'], true) ? $_POST['posisi'] : 'drawer';
            // Revisi 29 Juni 2026 — paket bisa multi (checkbox). Disimpan sebagai CSV.
            $paketArr = (array)($_POST['paket'] ?? []);
            $paketArr = array_values(array_filter($paketArr, fn($v) => in_array($v, ['gratis','pro','komunitas'], true)));
            $paket = $paketArr ? implode(',', $paketArr) : null;
            if ($label === '') throw new RuntimeException('Label wajib diisi.');
            if ($a === 'add') {
                db_exec("INSERT INTO nav_menu(label,url,icon,parent_id,urutan,aktif,target,posisi,paket)
                         VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)",
                    [$label,$url,$icon?:null,$parent,$urut,$aktif?'t':'f',$target,$posisi,$paket]);
            } else {
                $id = (int)$_POST['id'];
                db_exec("UPDATE nav_menu SET label=$1,url=$2,icon=$3,parent_id=$4,urutan=$5,aktif=$6,target=$7,posisi=$8,paket=$9 WHERE id=$10",
                    [$label,$url,$icon?:null,$parent,$urut,$aktif?'t':'f',$target,$posisi,$paket,$id]);
            }
            $_SESSION['flash'] = 'Menu disimpan.';
        } elseif ($a === 'delete') {
            db_exec("DELETE FROM nav_menu WHERE id=$1",[(int)$_POST['id']]);
            $_SESSION['flash'] = 'Menu dihapus.';
        } elseif ($a === 'reseed') {
            db_exec("DELETE FROM nav_menu WHERE posisi='drawer'");
            _menu_seed_default();
            $_SESSION['flash'] = 'Drawer di-reset ke 42 menu default.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = 'Gagal: '.$e->getMessage();
    }
    header('Location: menu.php'); exit;
}

function _pkt_chk(string $key, string $cur): string {
    $arr = array_filter(array_map('trim', explode(',', strtolower($cur))));
    return in_array($key, $arr, true) ? 'checked' : '';
}

$rows = db_all("SELECT m.*, p.label AS parent_label
                FROM nav_menu m LEFT JOIN nav_menu p ON p.id=m.parent_id
                ORDER BY m.posisi, COALESCE(m.parent_id,0), m.urutan, m.id");
$parents = db_all("SELECT id, label FROM nav_menu WHERE parent_id IS NULL ORDER BY label");

include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-list-nested text-primary"></i> Navigasi Menu (CMS-style)</h2>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<div class="d-flex gap-2 mb-3">
  <form method="post" onsubmit="return confirm('Reset drawer ke 42 menu default? Semua item drawer akan dihapus dan dibuat ulang.')">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="reseed">
    <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-repeat"></i> Reset Drawer ke 42 Menu Default</button>
  </form>
</div>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-plus-circle"></i> Tambah Item Menu</div>
  <form method="post" class="card-body row g-2">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="add">
    <div class="col-md-3"><label class="small">Label</label><input class="form-control form-control-sm" name="label" required></div>
    <div class="col-md-3"><label class="small">URL</label><input class="form-control form-control-sm" name="url" value="#"></div>
    <div class="col-md-2"><label class="small">Icon (bi-…)</label><input class="form-control form-control-sm" name="icon" placeholder="bi-house-door"></div>
    <div class="col-md-2"><label class="small">Parent</label>
      <select name="parent_id" class="form-select form-select-sm">
        <option value="">(root)</option>
        <?php foreach ($parents as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1"><label class="small">Urutan</label><input type="number" class="form-control form-control-sm" name="urutan" value="0"></div>
    <div class="col-md-1"><label class="small">Posisi</label>
      <select name="posisi" class="form-select form-select-sm">
        <option value="drawer">drawer</option><option value="top">top</option><option value="bottom">bottom</option>
      </select>
    </div>
    <div class="col-md-1"><label class="small">Target</label>
      <select name="target" class="form-select form-select-sm">
        <option value="_self">_self</option><option value="_blank">_blank</option>
      </select>
    </div>
    <div class="col-md-1 form-check mt-4 ms-2"><input class="form-check-input" type="checkbox" name="aktif" id="ak" checked><label for="ak" class="small">aktif</label></div>
    <div class="col-md-12"><label class="small d-block">Paket (label, bisa pilih lebih dari satu)</label>
      <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paket[]" value="gratis" id="pk_g"><label class="form-check-label small" for="pk_g">🆓 Gratis</label></div>
      <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paket[]" value="pro" id="pk_p"><label class="form-check-label small" for="pk_p">⭐ PRO</label></div>
      <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="paket[]" value="komunitas" id="pk_k"><label class="form-check-label small" for="pk_k">👥 Komunitas</label></div>
    </div>
    <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</button></div>
  </form>
</div>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead><tr><th>#</th><th>Label</th><th>URL</th><th>Posisi</th><th>Parent</th><th>Urut</th><th>Paket</th><th>Aktif</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $cur = (string)($r['paket'] ?? ''); ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td>
        <?= $r['icon']?'<i class="bi '.htmlspecialchars($r['icon']).'"></i> ':'' ?><?= htmlspecialchars($r['label']) ?>
        <?php foreach (array_filter(array_map('trim', explode(',', strtolower($cur)))) as $pk):
              $bcls = $pk==='pro'?'warning':($pk==='komunitas'?'success':'secondary');
              $bico = $pk==='pro'?'⭐ PRO':($pk==='komunitas'?'👥 Komunitas':'🆓 Gratis'); ?>
          <span class="badge bg-<?= $bcls ?> ms-1"><?= $bico ?></span>
        <?php endforeach; ?>
      </td>
      <td class="small text-muted"><?= htmlspecialchars($r['url']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($r['posisi']) ?></span></td>
      <td class="small"><?= htmlspecialchars($r['parent_label'] ?? '—') ?></td>
      <td><?= (int)$r['urutan'] ?></td>
      <td>
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="edit">
          <input type="hidden" name="id"      value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="label"   value="<?= htmlspecialchars($r['label']) ?>">
          <input type="hidden" name="url"     value="<?= htmlspecialchars($r['url']) ?>">
          <input type="hidden" name="icon"    value="<?= htmlspecialchars((string)($r['icon']??'')) ?>">
          <input type="hidden" name="parent_id" value="<?= (int)($r['parent_id']??0) ?>">
          <input type="hidden" name="urutan"  value="<?= (int)$r['urutan'] ?>">
          <input type="hidden" name="target"  value="<?= htmlspecialchars($r['target']??'_self') ?>">
          <input type="hidden" name="posisi"  value="<?= htmlspecialchars($r['posisi']) ?>">
          <?php if ($r['aktif']==='t' || $r['aktif']===true): ?><input type="hidden" name="aktif" value="1"><?php endif; ?>
          <div class="d-flex flex-wrap gap-1">
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="paket[]" value="gratis"    id="pg<?= (int)$r['id'] ?>" <?= _pkt_chk('gratis',$cur) ?> onchange="this.form.submit()"><label class="form-check-label small" for="pg<?= (int)$r['id'] ?>">🆓</label></div>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="paket[]" value="pro"       id="pp<?= (int)$r['id'] ?>" <?= _pkt_chk('pro',$cur)    ?> onchange="this.form.submit()"><label class="form-check-label small" for="pp<?= (int)$r['id'] ?>">⭐</label></div>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="paket[]" value="komunitas" id="pk<?= (int)$r['id'] ?>" <?= _pkt_chk('komunitas',$cur)?> onchange="this.form.submit()"><label class="form-check-label small" for="pk<?= (int)$r['id'] ?>">👥</label></div>
          </div>
        </form>
      </td>
      <td><?= ($r['aktif']==='t'||$r['aktif']===true)?'✅':'⬜' ?></td>
      <td>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus menu ini?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<p class="text-muted small mt-3">Tip: panggil <code>nav_menu_html('drawer')</code> dari <code>includes/menu_render.php</code> di tempat manapun untuk merender menu yang dikelola di sini.</p>

<?php include __DIR__.'/../includes/footer.php'; ?>

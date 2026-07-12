<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/scope.php'; // Revisi R7 — scope komunitas & superadmin
// Revisi R7 #1 — role 'superadmin' juga boleh mengakses halaman ini
require_role(['admin','superadmin']);
$pageTitle='Manajemen Member';
$__isSuper = scope_is_super();          // R7 #2/#6 — hanya super yg lihat opsi/data lintas komunitas
$__roleOpts = $__isSuper ? ['publik','member','admin','superadmin'] : ['publik','member','admin'];
// ==== Migrasi idempotent ====
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN DEFAULT TRUE"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS nonaktif_catatan TEXT"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40)"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS komunitas_id INTEGER"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS paket VARCHAR(20) DEFAULT 'gratis'"); } catch (Throwable $e) {}
// Revisi R2 (Juli 2026) — masa expire paket akun & tabel pivot multi-komunitas.
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS paket_expires_at TIMESTAMP"); } catch (Throwable $e) {}
try {
    db_exec("CREATE TABLE IF NOT EXISTS user_komunitas (
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        komunitas_id INTEGER NOT NULL REFERENCES komunitas(id) ON DELETE CASCADE,
        created_at TIMESTAMP NOT NULL DEFAULT now(),
        PRIMARY KEY (user_id, komunitas_id)
    )");
} catch (Throwable $e) {}
// Migrasi one-shot: pindahkan users.komunitas_id lama ke pivot (jangan hapus kolomnya).
try {
    db_exec("INSERT INTO user_komunitas(user_id, komunitas_id)
             SELECT id, komunitas_id FROM users
             WHERE komunitas_id IS NOT NULL
             ON CONFLICT DO NOTHING");
} catch (Throwable $e) {}

/** Simpan ulang daftar komunitas untuk seorang user. */
function _save_user_komunitas(int $uid, array $ids): void {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    try { db_exec("DELETE FROM user_komunitas WHERE user_id=$1", [$uid]); } catch (Throwable $e) { return; }
    foreach ($ids as $kid) {
        try { db_exec("INSERT INTO user_komunitas(user_id,komunitas_id) VALUES($1,$2) ON CONFLICT DO NOTHING", [$uid, $kid]); } catch (Throwable $e) {}
    }
    // Sinkronkan users.komunitas_id (kompatibilitas mundur) → pakai komunitas pertama.
    $primary = $ids[0] ?? null;
    try { db_exec("UPDATE users SET komunitas_id=$1 WHERE id=$2", [$primary, $uid]); } catch (Throwable $e) {}
}

/** Ambil daftar id komunitas untuk 1 user. */
function _user_komunitas_ids(int $uid): array {
    try {
        $rows = db_all("SELECT komunitas_id FROM user_komunitas WHERE user_id=$1", [$uid]);
        return array_map(fn($r)=>(int)$r['komunitas_id'], $rows);
    } catch (Throwable $e) { return []; }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    // Revisi Juli 2026 R11 — Admin (non-super) TIDAK boleh mengedit/menghapus/mengubah
    // user dengan role 'superadmin'. Guard di semua aksi yang menyentuh user tertentu.
    if (!$__isSuper && in_array($a, ['update_role','update_pic','toggle_aktif','delete','reset_pwd','edit','upload_foto','delete_foto'], true)) {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId > 0) {
            $targetRole = (string) db_val("SELECT role FROM users WHERE id=$1", [$targetId]);
            if ($targetRole === 'superadmin') {
                $_SESSION['flash_err'] = 'Aksi ditolak: Anda tidak berwenang mengubah data akun superadmin.';
                $qs = isset($_GET['filter_komunitas']) ? ('?filter_komunitas='.(int)$_GET['filter_komunitas']) : '';
                header('Location: members.php'.$qs); exit;
            }
        }
        // Cegah admin men-set role user manapun menjadi 'superadmin' (defense in depth).
        if ($a === 'update_role' && ($_POST['role'] ?? '') === 'superadmin') {
            $_SESSION['flash_err'] = 'Aksi ditolak: hanya superadmin yang boleh menetapkan role superadmin.';
            $qs = isset($_GET['filter_komunitas']) ? ('?filter_komunitas='.(int)$_GET['filter_komunitas']) : '';
            header('Location: members.php'.$qs); exit;
        }
    }
    if ($a==='update_role') {
        // Revisi R7 #1/#2 — validasi role. 'superadmin' hanya boleh di-set oleh super-scope.
        $rolePosted = (string)($_POST['role'] ?? 'member');
        $allowedRoles = $__isSuper ? ['publik','member','admin','superadmin'] : ['publik','member','admin'];
        if (!in_array($rolePosted, $allowedRoles, true)) $rolePosted = 'member';
        db_exec("UPDATE users SET role=$1 WHERE id=$2", [$rolePosted, (int)$_POST['id']]);
    } elseif ($a==='update_pic') {
        $pic = ($_POST['pic_admin_id'] ?? '') !== '' ? (int)$_POST['pic_admin_id'] : null;
        db_exec("UPDATE users SET pic_admin_id=$1 WHERE id=$2", [$pic, (int)$_POST['id']]);
    } elseif ($a==='toggle_aktif') {
        $aktif = ((int)($_POST['aktif'] ?? 1)) === 1;
        $catatan = trim($_POST['nonaktif_catatan'] ?? '') ?: null;
        $dt = (string) db_val("SELECT data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='users' AND column_name='aktif'");
        $isBool = stripos($dt, 'bool') !== false;
        $val = $isBool ? ($aktif ? 't' : 'f') : ($aktif ? 1 : 0);
        try {
          db_exec("UPDATE users SET aktif=$1, nonaktif_catatan=$2 WHERE id=$3",
                  [$val, $aktif ? null : $catatan, (int)$_POST['id']]);
        } catch (Throwable $e) {
          $val2 = $isBool ? ($aktif ? 1 : 0) : ($aktif ? 't' : 'f');
          db_exec("UPDATE users SET aktif=$1, nonaktif_catatan=$2 WHERE id=$3",
                  [$val2, $aktif ? null : $catatan, (int)$_POST['id']]);
        }
        unset($_SESSION['error_popup']);
        $_SESSION['flash'] = $aktif ? 'Member berhasil diaktifkan.' : 'Member berhasil di-nonaktifkan.';
    } elseif ($a==='delete') {
        db_exec("DELETE FROM users WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='create') {
        $pwd = $_POST['password'] ?: 'changeme';
        $jk = in_array(($_POST['jenis_kelamin'] ?? ''), ['L','P'], true) ? $_POST['jenis_kelamin'] : null;
        $wa = trim($_POST['wa'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '') ?: null;
        $komIds = $_POST['komunitas_ids'] ?? [];
        if (!is_array($komIds)) $komIds = [];
        $paket = in_array(($_POST['paket'] ?? 'gratis'), ['gratis','pro','komunitas'], true) ? $_POST['paket'] : 'gratis';
        // Revisi R2 — masa expire paket akun (opsional).
        $exp = trim($_POST['paket_expires_at'] ?? '') ?: null;
        // Revisi R7 #1/#2 — role. 'superadmin' hanya boleh dibuat oleh super-scope.
        $roleNew = (string)($_POST['role'] ?? 'member');
        $allowedRoles = $__isSuper ? ['publik','member','admin','superadmin'] : ['publik','member','admin'];
        if (!in_array($roleNew, $allowedRoles, true)) $roleNew = 'member';
        try {
            $newId = (int) db_val(
                "INSERT INTO users(nama,email,password_hash,role,jenis_kelamin,wa,username,komunitas_id,paket,paket_expires_at)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING id",
                [$_POST['nama'], $_POST['email'], password_hash($pwd, PASSWORD_BCRYPT), $roleNew, $jk, $wa, $username,
                 (int)($komIds[0] ?? 0) ?: null, $paket, $exp]);
            if ($newId) _save_user_komunitas($newId, $komIds);
            $_SESSION['flash'] = 'Member baru ditambahkan.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'Gagal menambah member: '.$e->getMessage();
        }
    } elseif ($a==='reset_pwd') {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) >= 6) {
            db_exec("UPDATE users SET password_hash=$1 WHERE id=$2",
                    [password_hash($new, PASSWORD_BCRYPT), (int)$_POST['id']]);
            $_SESSION['flash'] = 'Password member berhasil diubah.';
        } else { $_SESSION['flash_err'] = 'Password minimal 6 karakter.'; }
    } elseif ($a==='edit') {
        $id = (int)$_POST['id'];
        $jk = in_array(($_POST['jenis_kelamin'] ?? ''), ['L','P'], true) ? $_POST['jenis_kelamin'] : null;
        $wa = trim($_POST['wa'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '') ?: null;
        $komIds = $_POST['komunitas_ids'] ?? [];
        if (!is_array($komIds)) $komIds = [];
        $paket = in_array(($_POST['paket'] ?? 'gratis'), ['gratis','pro','komunitas'], true) ? $_POST['paket'] : 'gratis';
        $exp = trim($_POST['paket_expires_at'] ?? '') ?: null;
        db_exec("UPDATE users SET nama=$1, email=$2, jenis_kelamin=$3, wa=$4, username=$5, paket=$6, paket_expires_at=$7 WHERE id=$8",
                [$_POST['nama'], $_POST['email'], $jk, $wa, $username, $paket, $exp, $id]);
        _save_user_komunitas($id, $komIds);
        $_SESSION['flash'] = 'Data member diperbarui.';
    } elseif ($a==='upload_foto') {
        $id = (int)$_POST['id'];
        $target = db_one("SELECT * FROM users WHERE id=$1", [$id]);
        if (!$target) {
            $_SESSION['flash_err'] = 'Member tidak ditemukan.';
        } elseif (empty($_FILES['foto']) || empty($_FILES['foto']['tmp_name']) || ($_FILES['foto']['error'] ?? 1) !== 0) {
            $errCode = $_FILES['foto']['error'] ?? 'tidak ada file';
            $_SESSION['flash_err'] = 'Upload foto gagal (error: '.$errCode.'). Cek ukuran file & upload_max_filesize.';
        } else {
            try {
                require_once __DIR__.'/../config/imagekit.php';
                global $imageKit;
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
                $safe = preg_replace('/[^a-z0-9]/i','_',$target['nama'])."-avatar-".time().".".$ext;
                $up = $imageKit->uploadFile([
                    'file' => base64_encode(file_get_contents($_FILES['foto']['tmp_name'])),
                    'fileName' => $safe,
                    'folder' => '/sportapp/avatar'
                ]);
                if (!empty($up->error)) {
                    $em = is_object($up->error) ? ($up->error->message ?? json_encode($up->error)) : (string)$up->error;
                    $_SESSION['flash_err'] = 'ImageKit menolak upload: '.$em;
                } else {
                    if (!empty($target['foto_file_id'])) { try { $imageKit->deleteFile($target['foto_file_id']); } catch(Throwable $e){} }
                    db_exec("UPDATE users SET foto_url=$1, foto_file_id=$2 WHERE id=$3",
                            [$up->result->url, $up->result->fileId, $id]);
                    $_SESSION['flash'] = 'Foto profil member berhasil diperbarui.';
                }
            } catch (Throwable $e) {
                $_SESSION['flash_err'] = 'Upload foto gagal: '.$e->getMessage();
            }
        }
        unset($_SESSION['error_popup']);
    } elseif ($a==='delete_foto') {
        $id = (int)$_POST['id'];
        $target = db_one("SELECT foto_file_id FROM users WHERE id=$1", [$id]);
        if ($target && !empty($target['foto_file_id'])) {
            require_once __DIR__.'/../config/imagekit.php';
            global $imageKit;
            try { $imageKit->deleteFile($target['foto_file_id']); } catch(Throwable $e){}
        }
        db_exec("UPDATE users SET foto_url=NULL, foto_file_id=NULL WHERE id=$1", [$id]);
    }
    // Preserve filter komunitas saat redirect.
    $qs = isset($_GET['filter_komunitas']) ? ('?filter_komunitas='.(int)$_GET['filter_komunitas']) : '';
    header('Location: members.php'.$qs); exit;
}

// ==== Filter by komunitas (Revisi R2) ====
$filterKom = isset($_GET['filter_komunitas']) && $_GET['filter_komunitas'] !== '' ? (int)$_GET['filter_komunitas'] : 0;

// Revisi R7 #6 — admin biasa hanya boleh melihat member dari komunitas-nya sendiri.
// Superadmin (role) atau anggota komunitas 'SuperDuperAdmin' boleh melihat SEMUA member.
$__scopeKomIds = scope_visible_komunitas_ids();
if (!$__isSuper) {
    if ($filterKom > 0 && !in_array($filterKom, $__scopeKomIds, true)) {
        // Filter meminta komunitas di luar scope → tolak diam2 (kosongkan).
        $__scopeKomIds = [-1];
    }
}

// Ambil daftar users (dengan agregasi komunitas dari pivot).
$baseSql = "SELECT u.*, p.nama AS pic_nama,
            COALESCE((SELECT string_agg(k.nama, ', ' ORDER BY k.nama)
                      FROM user_komunitas uk JOIN komunitas k ON k.id=uk.komunitas_id
                      WHERE uk.user_id=u.id), '') AS komunitas_nama,
            COALESCE((SELECT array_agg(uk.komunitas_id) FROM user_komunitas uk WHERE uk.user_id=u.id), ARRAY[]::int[]) AS komunitas_ids
            FROM users u
            LEFT JOIN users p ON p.id = u.pic_admin_id";
$conds = []; $params = [];
if ($filterKom > 0) {
    $conds[] = "EXISTS (SELECT 1 FROM user_komunitas uk WHERE uk.user_id=u.id AND uk.komunitas_id=$".(count($params)+1).")";
    $params[] = $filterKom;
}
if (!$__isSuper) {
    // Batasi ke user yang komunitasnya beririsan dengan scope admin ini.
    $arrLit = '{'.implode(',', array_map('intval', $__scopeKomIds ?: [-1])).'}';
    $conds[] = "(EXISTS (SELECT 1 FROM user_komunitas uk WHERE uk.user_id=u.id AND uk.komunitas_id = ANY($".(count($params)+1)."::int[]))
                OR u.komunitas_id = ANY($".(count($params)+1)."::int[])
                OR u.id = $".(count($params)+2).")";
    $params[] = $arrLit;
    $params[] = (int)current_user()['id'];
}
if ($conds) $baseSql .= " WHERE ".implode(' AND ', $conds);
$baseSql .= " ORDER BY u.role, u.nama";
$users = db_all($baseSql, $params);

$admins = db_all("SELECT id, nama FROM users WHERE role IN ('admin','superadmin') ORDER BY nama");
$komList = [];
try {
    if ($__isSuper) {
        $komList = db_all("SELECT id, nama FROM komunitas WHERE aktif=1 ORDER BY nama");
    } else {
        // Revisi R7 #6 — admin biasa hanya melihat komunitasnya sendiri.
        $arrLit = '{'.implode(',', array_map('intval', $__scopeKomIds ?: [-1])).'}';
        $komList = db_all("SELECT id, nama FROM komunitas WHERE aktif=1 AND id = ANY($1::int[]) ORDER BY nama", [$arrLit]);
    }
} catch (Throwable $e) {
    try { $komList = db_all("SELECT id, nama FROM komunitas ORDER BY nama"); } catch (Throwable $e2) {}
}
$paketOpts = ['gratis'=>'🆓 Gratis','pro'=>'⭐ Pro','komunitas'=>'👥 Komunitas'];

// ==== Statistik total MEMBER AKTIF per komunitas (Revisi R2 #7) ====
$statsKom = [];
try {
    // Revisi R7 (Juli 2026) — FIX statistik "belum sesuai".
    // Sumber keanggotaan HARUS sama dengan yang dipakai filter "Daftar Member"
    // (lihat baris ~179 yang memakai EXISTS pada user_komunitas). Versi lama
    // menggabungkan pivot + kolom lama users.komunitas_id sehingga angka statistik
    // BISA berbeda dari jumlah baris yang muncul saat komunitas di-klik/di-filter
    // (mis. member yang punya users.komunitas_id lama tapi sudah tidak ada di pivot).
    // Sekarang statistik dihitung MURNI dari tabel pivot user_komunitas agar
    // "angka di kartu" = "jumlah member yang tampil saat difilter".
    //
    // Definisi:
    //   total_aktif = member (role<>'admin') yang aktif pada komunitas tsb
    //   total_all   = seluruh member (role<>'admin') pada komunitas tsb
    // Kolom aktif bisa BOOLEAN atau SMALLINT → dinormalkan via ::text.
    $statsKom = db_all("
        SELECT k.id, k.nama,
               COUNT(DISTINCT u.id) FILTER (
                 WHERE COALESCE(u.aktif::text,'1') IN ('t','true','1','y','yes')
               ) AS total_aktif,
               COUNT(DISTINCT u.id) AS total_all
        FROM komunitas k
        LEFT JOIN user_komunitas uk ON uk.komunitas_id = k.id
        LEFT JOIN users u          ON u.id = uk.user_id AND u.role NOT IN ('admin','superadmin')
        WHERE ($1 = 1 OR k.id = ANY($2::int[]))
        GROUP BY k.id, k.nama
        ORDER BY total_aktif DESC, k.nama
    ", [$__isSuper?1:0, '{'.implode(',', array_map('intval', $__scopeKomIds ?: [-1])).'}']);
} catch (Throwable $e) {}


$flash = $_SESSION['flash'] ?? null; $flashE = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_err']);
include __DIR__.'/../includes/header.php'; ?>

<?php /* Revisi R7 (Juli 2026) #1 — FIX popup edit: tombol (Simpan/Batal) tidak
     boleh tertutup bottom-nav / FAB "Upload" yang position:fixed z-index tinggi.
     - Sembunyikan bottom-nav selama modal terbuka (body.modal-open).
     - Pastikan isi modal bisa di-scroll & footer selalu terlihat di layar HP. */ ?>
<style>
  /* Saat modal Bootstrap terbuka, sembunyikan bottom-nav + FAB agar tidak
     menutupi tombol di dalam popup (khususnya di layar HP). */
  body.modal-open .gj-nav,
  body.modal-open .gj-topbar { display: none !important; }
  body.modal-open { padding-bottom: 0 !important; }

  /* Modal edit/foto/password bisa di-scroll penuh; footer tetap terlihat. */
  #memberTable ~ .modal .modal-dialog,
  .modal .modal-dialog { margin-top: .75rem; margin-bottom: .75rem; }
  @media (max-width: 767.98px){
    .modal-dialog-scrollable .modal-content { max-height: calc(100dvh - 1.5rem); }
    .modal-dialog-scrollable .modal-body { overflow-y: auto; }
    /* beri ruang bawah supaya footer tidak mepet tepi layar */
    .modal .modal-footer { position: sticky; bottom: 0; background: var(--bs-modal-bg, #fff); z-index: 2; }
  }
</style>
<h2 class="mb-3"><i class="bi bi-people text-primary"></i> Manajemen Member</h2>

<?php if($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if($flashE): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($flashE) ?></div><?php endif; ?>

<?php /* Revisi R2 (Juli 2026) — Statistik total member aktif per komunitas */ ?>
<?php if ($statsKom): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-bar-chart-line text-primary"></i> Statistik Member Aktif per Komunitas</span>
    <span class="small text-muted"><?= count($statsKom) ?> komunitas</span>
  </div>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach($statsKom as $sk): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a href="?filter_komunitas=<?= (int)$sk['id'] ?>" class="text-decoration-none">
            <div class="card border h-100">
              <div class="card-body p-2 text-center">
                <div class="small text-muted text-truncate" title="<?= htmlspecialchars($sk['nama']) ?>">
                  <i class="bi bi-people-fill text-success"></i> <?= htmlspecialchars($sk['nama']) ?>
                </div>
                <div class="h4 mb-0 text-success"><?= (int)$sk['total_aktif'] ?></div>
                <div class="small text-muted">aktif / <?= (int)$sk['total_all'] ?> total</div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php /* Tambah Member (spoiler) */ ?>
<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-person-plus me-1 text-primary"></i> Tambah Member</span>
    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addMemberSpoiler" aria-expanded="false" aria-controls="addMemberSpoiler">
      <i class="bi bi-plus-lg"></i> Buka Form
    </button>
  </div>
  <div id="addMemberSpoiler" class="collapse">
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="create">
        <div class="col-md-3"><label class="form-label small mb-0">Nama Lengkap *</label>
          <input class="form-control form-control-sm" name="nama" required></div>
        <div class="col-md-3"><label class="form-label small mb-0">Email *</label>
          <input class="form-control form-control-sm" type="email" name="email" required></div>
        <div class="col-md-2"><label class="form-label small mb-0">Username</label>
          <input class="form-control form-control-sm" name="username" placeholder="mis. budi_88"></div>
        <div class="col-md-2"><label class="form-label small mb-0">Password</label>
          <input class="form-control form-control-sm" name="password" placeholder="default: changeme"></div>
        <div class="col-md-2"><label class="form-label small mb-0">No. WhatsApp</label>
          <input class="form-control form-control-sm" name="wa" placeholder="08xxxxxxxxxx"></div>
        <div class="col-md-1"><label class="form-label small mb-0">JK</label>
          <select class="form-select form-select-sm" name="jenis_kelamin"><option value="">—</option><option value="L">L</option><option value="P">P</option></select></div>
        <div class="col-md-2"><label class="form-label small mb-0">Role</label>
          <select class="form-select form-select-sm" name="role"><?php foreach($__roleOpts as $r): if($r==='publik') continue; ?><option value="<?= $r ?>" <?= $r==='member'?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
        <div class="col-md-5"><label class="form-label small mb-0"><i class="bi bi-people-fill text-success"></i> Komunitas <span class="text-muted">(bisa &gt; 1, tahan Ctrl/⌘)</span></label>
          <select class="form-select form-select-sm" name="komunitas_ids[]" multiple size="4">
            <?php foreach($komList as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-2"><label class="form-label small mb-0"><i class="bi bi-award text-warning"></i> Paket</label>
          <select class="form-select form-select-sm" name="paket">
            <?php foreach($paketOpts as $pk=>$pl): ?>
              <option value="<?= $pk ?>" <?= $pk==='gratis'?'selected':'' ?>><?= htmlspecialchars($pl) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label small mb-0"><i class="bi bi-calendar-event text-danger"></i> Masa Expire Paket</label>
          <input type="datetime-local" class="form-control form-control-sm" name="paket_expires_at">
          <div class="form-text small">Kosongkan jika paket berlaku selamanya.</div>
        </div>
        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Simpan Member Baru</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span><i class="bi bi-table"></i> Daftar Member <?php if($filterKom): ?><span class="badge bg-info-subtle text-info border">Filter aktif</span><?php endif; ?></span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?php /* Revisi R2 (Juli 2026) — Filter by komunitas */ ?>
      <form method="get" class="d-flex gap-1">
        <select name="filter_komunitas" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
          <option value="">— Semua komunitas —</option>
          <?php foreach($komList as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= $filterKom===(int)$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if($filterKom): ?><a href="members.php" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a><?php endif; ?>
      </form>
      <input id="memberSearch" class="form-control form-control-sm" style="max-width:220px" placeholder="🔍 Cari nama / email...">
      <select id="memberPageSize" class="form-select form-select-sm" style="max-width:110px">
        <option value="10">10 / hal</option>
        <option value="25" selected>25 / hal</option>
        <option value="50">50 / hal</option>
        <option value="100">100 / hal</option>
      </select>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0 align-middle" id="memberTable" data-paginate="10">
  <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>Email</th><th>WA</th><th>JK</th><th>Komunitas</th><th>Paket</th><th>Expire</th><th>PIC Admin</th><th>Role</th><th>Aktif</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
  <?php foreach($users as $i=>$u): $on = is_online($u['last_seen'] ?? null);
    $waDigits = preg_replace('/\D+/', '', ($u['wa'] ?: ($u['nomor_wa'] ?? '')));
    if ($waDigits && str_starts_with($waDigits, '0')) $waDigits = '62'.substr($waDigits,1);
    // parse komunitas_ids postgres array literal like "{1,2}"
    $curKomIds = [];
    $rawKom = $u['komunitas_ids'] ?? '';
    if (is_array($rawKom)) { $curKomIds = array_map('intval',$rawKom); }
    else if (is_string($rawKom) && $rawKom !== '' && $rawKom !== '{}') {
        $curKomIds = array_map('intval', array_filter(explode(',', trim($rawKom,'{}'))));
    }
    $expStr = null;
    if (!empty($u['paket_expires_at'])) {
        $ts = strtotime((string)$u['paket_expires_at']);
        if ($ts) $expStr = date('d M Y H:i', $ts);
    }
    $expInputVal = '';
    if (!empty($u['paket_expires_at'])) {
        $ts2 = strtotime((string)$u['paket_expires_at']);
        if ($ts2) $expInputVal = date('Y-m-d\TH:i', $ts2);
    }
  ?>
    <tr data-search="<?= htmlspecialchars(strtolower(($u['nama'] ?? '').' '.($u['email'] ?? '').' '.($u['username'] ?? '').' '.($u['komunitas_nama'] ?? ''))) ?>">
      <td class="text-muted row-num"><?= $i+1 ?></td>
      <td class="fw-semibold"><?= user_name_with_avatar($u['foto_url'] ?? null, $u['nama'], $on, 32) ?></td>
      <td class="small"><?= $u['username'] ? '<span class="badge bg-light text-dark border">@'.htmlspecialchars($u['username']).'</span>' : '<span class="text-muted">—</span>' ?></td>
      <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
      <td><?php $__wa = $u['wa'] ?: ($u['nomor_wa'] ?? ''); echo $__wa ? '<span class="small">'.htmlspecialchars($__wa).'</span>' : '<span class="text-muted small">—</span>'; ?></td>
      <td><?php $jk=$u['jenis_kelamin']??null; echo $jk==='L'?'<span class="pill">L</span>':($jk==='P'?'<span class="pill">P</span>':'<span class="text-muted small">—</span>'); ?></td>
      <td class="small" style="max-width:200px">
        <?php if(!empty($u['komunitas_nama'])): foreach(explode(', ', $u['komunitas_nama']) as $knm): ?>
          <span class="badge bg-success-subtle text-success border me-1 mb-1"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($knm) ?></span>
        <?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?>
      </td>
      <td><?php $pk = strtolower((string)($u['paket'] ?? 'gratis')); $pmap=['gratis'=>['secondary','🆓'],'pro'=>['warning','⭐ Pro'],'komunitas'=>['success','👥 Kom']]; $pb=$pmap[$pk]??$pmap['gratis']; ?>
        <span class="badge bg-<?= $pb[0] ?>"><?= $pb[1] ?></span></td>
      <td class="small">
        <?php if ($expStr):
            $expTs = strtotime((string)$u['paket_expires_at']);
            $isExpired = $expTs && $expTs < time();
        ?>
          <span class="badge bg-<?= $isExpired?'danger':'info-subtle text-info border' ?>" title="<?= $isExpired?'Sudah lewat masa berlaku':'Aktif hingga tanggal ini' ?>">
            <i class="bi bi-calendar-event"></i> <?= htmlspecialchars($expStr) ?>
          </span>
        <?php else: ?>
          <span class="text-muted">— selamanya —</span>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" class="d-flex">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_pic">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="pic_admin_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:130px" <?= (($u["role"]??"")==="superadmin" && !$__isSuper)?"disabled":"" ?>>
            <option value="">— belum —</option>
            <?php foreach($admins as $ad): ?>
              <option value="<?= (int)$ad['id'] ?>" <?= (string)$u['pic_admin_id']===(string)$ad['id']?'selected':'' ?>><?= htmlspecialchars($ad['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <form method="post" class="d-flex gap-1">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="update_role">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" <?= (($u["role"]??"")==="superadmin" && !$__isSuper)?"disabled":"" ?>>
            <?php foreach($__roleOpts as $r): ?><option <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <?php
          $_aktifRaw = $u['aktif'] ?? null;
          $_aktifBool = ($_aktifRaw === null) ? true : in_array(strtolower((string)$_aktifRaw), ['1','t','true','y','yes'], true);
        ?>
        <?php if ($_aktifBool): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Non-aktifkan <?= htmlspecialchars($u['nama']) ?>?')">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="toggle_aktif">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="aktif" value="0">
            <input type="hidden" name="nonaktif_catatan" value="Dinonaktifkan oleh admin">
            <button class="btn btn-sm btn-success" title="Klik untuk non-aktifkan" <?= (($u["role"]??"")==="superadmin" && !$__isSuper)?"disabled":"" ?>><i class="bi bi-person-check-fill"></i> Aktif</button>
          </form>
        <?php else: ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="toggle_aktif">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="aktif" value="1">
            <button class="btn btn-sm btn-outline-danger" title="Klik untuk aktifkan kembali" <?= (($u["role"]??"")==="superadmin" && !$__isSuper)?"disabled":"" ?>><i class="bi bi-person-x-fill"></i> Nonaktif</button>
          </form>
        <?php endif; ?>
      </td>
      <td><?= $on ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-secondary">Offline</span>' ?></td>
      <td class="text-end text-nowrap">
        <?php // Revisi Juli 2026 R11 — sembunyikan tombol ubah data utk baris superadmin bila operator bukan super.
              $__isTargetSuper = (($u['role'] ?? '') === 'superadmin');
              $__lockRow = $__isTargetSuper && !$__isSuper; ?>
        <?php if($waDigits): ?>
          <a href="https://wa.me/<?= htmlspecialchars($waDigits) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success" title="Hubungi via WhatsApp"><i class="bi bi-whatsapp"></i></a>
        <?php else: ?>
          <button class="btn btn-sm btn-outline-secondary" disabled title="No. WA belum diisi"><i class="bi bi-whatsapp"></i></button>
        <?php endif; ?>
        <?php if($__lockRow): ?>
          <span class="badge bg-secondary" title="Hanya superadmin yang dapat mengubah akun superadmin"><i class="bi bi-shield-lock"></i> superadmin</span>
        <?php else: ?>
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#foto<?= $u['id'] ?>" title="Foto"><i class="bi bi-image"></i></button>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#pwd<?= $u['id'] ?>" title="Reset Password"><i class="bi bi-key"></i></button>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edt<?= $u['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['nama']) ?>?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center small">
    <div id="memberPageInfo" class="text-muted"></div>
    <nav><ul class="pagination pagination-sm mb-0" id="memberPager"></ul></nav>
  </div>
</div>

<?php foreach($users as $u):
  $curKomIds = [];
  $rawKom = $u['komunitas_ids'] ?? '';
  if (is_array($rawKom)) $curKomIds = array_map('intval',$rawKom);
  else if (is_string($rawKom) && $rawKom !== '' && $rawKom !== '{}') {
      $curKomIds = array_map('intval', array_filter(explode(',', trim($rawKom,'{}'))));
  }
  $expInputVal = '';
  if (!empty($u['paket_expires_at'])) {
      $ts2 = strtotime((string)$u['paket_expires_at']);
      if ($ts2) $expInputVal = date('Y-m-d\TH:i', $ts2);
  }
?>
<div class="modal fade" id="foto<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-image"></i> Foto: <?= htmlspecialchars($u['nama']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body text-center">
    <div class="mb-3"><?= user_avatar($u['foto_url'] ?? null, $u['nama'], 96) ?></div>
    <input type="file" name="foto" class="form-control" accept="image/*" required>
    <small class="text-muted">Foto akan diunggah ke ImageKit.</small>
  </div>
  <div class="modal-footer">
    <?php if(!empty($u['foto_url'])): ?>
    <button type="submit" name="_action" value="delete_foto" class="btn btn-outline-danger" onclick="return confirm('Hapus foto?')"><i class="bi bi-trash"></i> Hapus Foto</button>
    <?php endif; ?>
    <button type="submit" name="_action" value="upload_foto" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
  </div>
</form></div></div>

<div class="modal fade" id="pwd<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="reset_pwd"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small fw-semibold">Password Baru (min 6)</label>
    <input type="text" name="new_password" class="form-control" minlength="6" required>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-warning"><i class="bi bi-shield-check"></i> Reset</button></div>
</form></div></div>

<div class="modal fade" id="edt<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="edit"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label small fw-semibold">Nama</label><input name="nama" class="form-control" value="<?= htmlspecialchars($u['nama']) ?>" required></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Username</label><input name="username" class="form-control" value="<?= htmlspecialchars($u['username'] ?? '') ?>" placeholder="mis. budi_88"></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Email</label><input name="email" type="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
    <div class="mb-2"><label class="form-label small fw-semibold"><i class="bi bi-whatsapp text-success"></i> No. WhatsApp</label><input name="wa" class="form-control" value="<?= htmlspecialchars($u['wa'] ?? '') ?>" placeholder="08xxxxxxxxxx"></div>
    <div class="mb-2"><label class="form-label small fw-semibold">Jenis Kelamin</label>
      <select class="form-select" name="jenis_kelamin">
        <option value="" <?= empty($u['jenis_kelamin'])?'selected':'' ?>>— Tidak diisi —</option>
        <option value="L" <?= ($u['jenis_kelamin']??'')==='L'?'selected':'' ?>>Laki-laki</option>
        <option value="P" <?= ($u['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option>
      </select></div>
    <div class="mb-2"><label class="form-label small fw-semibold"><i class="bi bi-people-fill text-success"></i> Komunitas <span class="text-muted">(bisa &gt; 1, tahan Ctrl/⌘)</span></label>
      <select class="form-select" name="komunitas_ids[]" multiple size="5">
        <?php foreach($komList as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $curKomIds, true)?'selected':'' ?>><?= htmlspecialchars($k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text small">Kosongkan semua jika member tidak berkomunitas.</div>
    </div>
    <div class="mb-2"><label class="form-label small fw-semibold"><i class="bi bi-award text-warning"></i> Paket Member</label>
      <select class="form-select" name="paket">
        <?php $cur = strtolower((string)($u['paket'] ?? 'gratis')); foreach($paketOpts as $pk=>$pl): ?>
          <option value="<?= $pk ?>" <?= $cur===$pk?'selected':'' ?>><?= htmlspecialchars($pl) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="mb-2"><label class="form-label small fw-semibold"><i class="bi bi-calendar-event text-danger"></i> Masa Expire Paket Akun</label>
      <input type="datetime-local" class="form-control" name="paket_expires_at" value="<?= htmlspecialchars($expInputVal) ?>">
      <div class="form-text small">Kosongkan jika paket berlaku selamanya. Setelah lewat tanggal ini, paket otomatis turun ke <em>gratis</em>.</div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div>
</form></div></div>
<?php endforeach; ?>

<script>
// === Client-side pagination & search untuk tabel member (tanpa reload) ===
(function(){
  var table = document.getElementById('memberTable');
  if(!table) return;
  var rows = Array.from(table.tBodies[0].rows);
  var searchInput = document.getElementById('memberSearch');
  var pageSizeSel = document.getElementById('memberPageSize');
  var pager = document.getElementById('memberPager');
  var info = document.getElementById('memberPageInfo');
  var page = 1;
  function filtered(){
    var q = (searchInput.value||'').toLowerCase().trim();
    return rows.filter(r => !q || (r.dataset.search||'').includes(q));
  }
  function render(){
    var ps = parseInt(pageSizeSel.value,10)||25;
    var data = filtered();
    var total = data.length;
    var pages = Math.max(1, Math.ceil(total/ps));
    if(page>pages) page = pages;
    var start = (page-1)*ps, end = Math.min(start+ps, total);
    rows.forEach(r => r.style.display='none');
    data.slice(start,end).forEach((r,i)=>{
      r.style.display='';
      var c = r.querySelector('.row-num'); if(c) c.textContent = start+i+1;
    });
    info.textContent = total ? ('Menampilkan '+(start+1)+'–'+end+' dari '+total+' member') : 'Tidak ada data';
    pager.innerHTML = '';
    function btn(label, p, dis, act){
      var li = document.createElement('li');
      li.className = 'page-item'+(dis?' disabled':'')+(act?' active':'');
      var a = document.createElement('a');
      a.className='page-link'; a.href='#'; a.textContent=label;
      a.addEventListener('click', function(e){e.preventDefault(); if(!dis && !act){ page=p; render(); }});
      li.appendChild(a); pager.appendChild(li);
    }
    btn('«', Math.max(1,page-1), page<=1, false);
    var maxBtn = 7;
    var from = Math.max(1, page - 3), to = Math.min(pages, from + maxBtn - 1);
    from = Math.max(1, to - maxBtn + 1);
    for(var p=from; p<=to; p++) btn(String(p), p, false, p===page);
    btn('»', Math.min(pages,page+1), page>=pages, false);
  }
  searchInput.addEventListener('input', function(){ page=1; render(); });
  pageSizeSel.addEventListener('change', function(){ page=1; render(); });
  render();
})();
</script>

<?php include __DIR__.'/../includes/footer.php'; ?>

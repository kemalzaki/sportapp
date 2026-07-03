<?php
/**
 * admin/members.php — Revisi R5 (Juli 2026)
 * Daftar Member + CRUD kolom Username & Komunitas + Tambah Member (spoiler)
 * dengan pilihan Komunitas & Paket.
 *
 * Butuh DB:
 *   - users(username, paket, komunitas_id)
 *   - komunitas(id, nama)
 * Lihat REVISI_JULI_2026_R5.sql bila kolom komunitas_id belum ada.
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require_login();
$me = current_user();
if (($me['role'] ?? '') !== 'admin') { http_response_code(403); exit('Khusus admin.'); }

// Pastikan kolom komunitas_id ada (aman jika sudah ada)
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL"); } catch (Throwable $e) {}
try { db_exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(40) NULL"); } catch (Throwable $e) {}

$flash = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    try {
        if ($act === 'create') {
            $nama = trim((string)($_POST['nama'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            $paket = (string)($_POST['paket'] ?? 'gratis');
            $kid   = (int)($_POST['komunitas_id'] ?? 0);
            if ($nama === '' || $username === '' || $pass === '') throw new RuntimeException('Nama, username, dan password wajib diisi.');
            if (!in_array($paket, ['gratis','pro','komunitas'], true)) $paket = 'gratis';
            $dup = db_one("SELECT id FROM users WHERE LOWER(username)=LOWER($1)", [$username]);
            if ($dup) throw new RuntimeException('Username sudah digunakan.');
            db_exec("INSERT INTO users (nama, username, email, password_hash, role, paket, komunitas_id, created_at)
                     VALUES ($1,$2,$3,$4,'user',$5,NULLIF($6,0),NOW())",
                    [$nama, $username, $email ?: null, hash_password($pass), $paket, $kid]);
            $flash = 'Member baru berhasil ditambahkan.';
        } elseif ($act === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim((string)($_POST['username'] ?? ''));
            $paket = (string)($_POST['paket'] ?? 'gratis');
            $kid   = (int)($_POST['komunitas_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('ID tidak valid.');
            if (!in_array($paket, ['gratis','pro','komunitas'], true)) $paket = 'gratis';
            if ($username !== '') {
                $dup = db_one("SELECT id FROM users WHERE LOWER(username)=LOWER($1) AND id<>$2", [$username, $id]);
                if ($dup) throw new RuntimeException('Username sudah digunakan member lain.');
            }
            db_exec("UPDATE users SET username=NULLIF($1,''), paket=$2, komunitas_id=NULLIF($3,0) WHERE id=$4",
                    [$username, $paket, $kid, $id]);
            $flash = 'Data member diperbarui.';
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int)$me['id']) {
                db_exec("DELETE FROM users WHERE id=$1 AND role<>'admin'", [$id]);
                $flash = 'Member dihapus.';
            } else { throw new RuntimeException('Tidak dapat menghapus akun ini.'); }
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$komunitasList = [];
try { $komunitasList = db_all("SELECT id, nama FROM komunitas ORDER BY nama ASC"); } catch (Throwable $e) {}

$rows = db_all("SELECT u.id, u.nama, u.username, u.email, u.role,
                       COALESCE(u.paket,'gratis') AS paket,
                       u.komunitas_id, k.nama AS komunitas_nama
                FROM users u
                LEFT JOIN komunitas k ON k.id = u.komunitas_id
                ORDER BY u.id DESC");
$csrf = csrf_token();
$pageTitle = 'Daftar Member';
include __DIR__ . '/../includes/header.php';
?>
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="bi bi-people-fill"></i> Daftar Member</h4>
    <a href="/admin/komunitas.php" class="btn btn-sm btn-outline-success"><i class="bi bi-people"></i> Kelola Komunitas</a>
  </div>

  <?php if ($flash): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($err)   ?></div><?php endif; ?>

  <!-- Spoiler Tambah Member -->
  <div class="card border-0 shadow-sm mb-3">
    <a class="text-decoration-none text-dark d-block p-3 d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse" href="#addForm" role="button" aria-expanded="false">
      <span><i class="bi bi-person-plus-fill text-primary"></i> <b>Tambah Member Baru</b></span>
      <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse" id="addForm">
      <div class="card-body border-top">
        <form method="post" class="row g-2">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="create">
          <div class="col-md-4"><label class="form-label small">Nama Lengkap</label>
            <input name="nama" class="form-control" required></div>
          <div class="col-md-4"><label class="form-label small">Username</label>
            <input name="username" class="form-control" required pattern="[A-Za-z0-9_.]{3,40}"
                   title="3-40 karakter huruf/angka/._"></div>
          <div class="col-md-4"><label class="form-label small">Email (opsional)</label>
            <input name="email" type="email" class="form-control"></div>
          <div class="col-md-4"><label class="form-label small">Password</label>
            <input name="password" type="text" class="form-control" required></div>
          <div class="col-md-4"><label class="form-label small">Paket Member</label>
            <select name="paket" class="form-select">
              <option value="gratis">🆓 Gratis</option>
              <option value="pro">⭐ Pro</option>
              <option value="komunitas">👥 Komunitas</option>
            </select></div>
          <div class="col-md-4"><label class="form-label small">Komunitas</label>
            <select name="komunitas_id" class="form-select">
              <option value="0">— Tidak ada —</option>
              <?php foreach ($komunitasList as $k): ?>
                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-12 text-end">
            <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Simpan Member</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="table-responsive card border-0 shadow-sm">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Nama</th><th>Username</th><th>Email</th>
          <th>Role</th><th>Paket</th><th>Komunitas</th><th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="update">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td style="min-width:160px">
              <input name="username" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$r['username']) ?>" pattern="[A-Za-z0-9_.]{3,40}">
            </td>
            <td class="small text-muted"><?= htmlspecialchars((string)$r['email']) ?></td>
            <td><span class="badge bg-<?= $r['role']==='admin'?'danger':'secondary' ?>"><?= htmlspecialchars($r['role']) ?></span></td>
            <td style="min-width:130px">
              <select name="paket" class="form-select form-select-sm">
                <?php foreach (['gratis'=>'🆓 Gratis','pro'=>'⭐ Pro','komunitas'=>'👥 Komunitas'] as $v=>$lab): ?>
                  <option value="<?= $v ?>" <?= $r['paket']===$v?'selected':'' ?>><?= $lab ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td style="min-width:170px">
              <select name="komunitas_id" class="form-select form-select-sm">
                <option value="0">—</option>
                <?php foreach ($komunitasList as $k): ?>
                  <option value="<?= (int)$k['id'] ?>" <?= ((int)$r['komunitas_id']===(int)$k['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($k['nama']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary" title="Simpan"><i class="bi bi-save"></i></button>
          </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Hapus member ini?')">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" <?= $r['role']==='admin'?'disabled':'' ?>><i class="bi bi-trash"></i></button>
              </form>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

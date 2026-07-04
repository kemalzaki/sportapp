<?php
/**
 * tim.php — Pembuatan Tim sesuai Jadwal Kegiatan (member-side)
 * Revisi 17 Juni 2026:
 *  - Buat tim langsung di-link ke jadwal kegiatan (jadwal.tim_id)
 *  - Tambah anggota internal (user) & eksternal (nama + WA) via tabel tim_external
 *  - Tombol submit: loading di tombol saja (tidak menutupi halaman)
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/scope.php'; // Revisi Juli 2026 #6 — list per komunitas
send_security_headers(); enforce_session_timeout();
require_role(['admin','superadmin']);


$u = current_user();
$uid = (int)$u['id'];
$pageTitle = 'Pembuatan Tim';

// ---- Migrasi idempotent untuk pemain eksternal ----
try {
    db_exec("CREATE TABLE IF NOT EXISTS tim_external (
        id          SERIAL PRIMARY KEY,
        tim_id      INTEGER NOT NULL REFERENCES tim(id) ON DELETE CASCADE,
        nama        VARCHAR(120) NOT NULL,
        nomor_wa    VARCHAR(30),
        catatan     VARCHAR(200),
        invited_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS idx_tim_external_tim ON tim_external(tim_id)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    rate_limit_or_die('tim_make:'.$uid, 30, 300);
    $a = $_POST['_action'] ?? '';
    try {
        if ($a === 'create') {
            $jid   = (int)($_POST['jadwal_id'] ?? 0);
            $nama  = trim((string)($_POST['nama'] ?? ''));
            $kuota = max(2, (int)($_POST['kuota'] ?? 4));
            $cat   = trim((string)($_POST['catatan'] ?? ''));
            if ($jid <= 0)  throw new Exception('Pilih jadwal kegiatan.');
            if ($nama==='') throw new Exception('Nama tim wajib diisi.');
            $j = db_one("SELECT id, jenis, tim_id FROM jadwal WHERE id=$1", [$jid]);
            if (!$j) throw new Exception('Jadwal tidak ditemukan.');
            // Buat / reuse tim
            if (!empty($j['tim_id'])) {
                $tid = (int)$j['tim_id'];
                db_exec("UPDATE tim SET nama=$1, kuota=$2, catatan=$3 WHERE id=$4",
                    [$nama,$kuota,$cat?:null,$tid]);
            } else {
                $row = db_one("INSERT INTO tim(nama,jenis,koordinator_id,kuota,catatan) VALUES($1,$2,$3,$4,$5) RETURNING id",
                    [$nama, $j['jenis'], $uid, $kuota, $cat?:null]);
                $tid = (int)$row['id'];
                db_exec("UPDATE jadwal SET tim_id=$1 WHERE id=$2", [$tid,$jid]);
            }
            // Koordinator otomatis jadi anggota
            try { db_exec("INSERT INTO tim_member(tim_id,user_id,peran) VALUES($1,$2,'koordinator') ON CONFLICT DO NOTHING", [$tid,$uid]); } catch (Throwable $e) {}
            $_SESSION['flash_ok'] = "Tim '$nama' dibuat untuk jadwal #$jid.";
            header("Location: /admin/tim.php?tim=$tid&jadwal=$jid"); exit;
        }
        elseif ($a === 'add_member') {
            $tid = (int)$_POST['tim_id']; $mid = (int)$_POST['user_id'];
            if ($tid && $mid) db_exec("INSERT INTO tim_member(tim_id,user_id,peran) VALUES($1,$2,'pemain') ON CONFLICT DO NOTHING", [$tid,$mid]);
            $_SESSION['flash_ok'] = "Anggota internal ditambahkan.";
        }
        elseif ($a === 'add_external') {
            // Revisi 22 Juni 2026 R12 — Pemain eksternal TIDAK lagi input manual.
            // Sumber data: nama_tamu pada tabel `member_eksternal` (diisi dari admin/absensi.php).
            $tid    = (int)$_POST['tim_id'];
            $nameIn = trim((string)($_POST['nama_ext_pick'] ?? ''));
            $cat    = trim((string)($_POST['cat_ext'] ?? ''));
            if (!$tid || $nameIn === '') {
                throw new Exception('Pilih nama tamu dari daftar absensi terlebih dahulu.');
            }
            // Validasi: nama harus benar-benar ada di member_eksternal
            $exists = db_one(
                "SELECT 1 FROM member_eksternal WHERE LOWER(TRIM(nama_tamu)) = LOWER(TRIM($1)) LIMIT 1",
                [$nameIn]
            );
            if (!$exists) {
                throw new Exception('Nama "'.$nameIn.'" tidak ditemukan di data absensi. Tambahkan dulu sebagai tamu di admin/absensi.php.');
            }
            // Cegah duplikasi untuk tim yang sama (opsional)
            $dup = db_one(
                "SELECT 1 FROM tim_external WHERE tim_id=$1 AND LOWER(TRIM(nama))=LOWER(TRIM($2)) LIMIT 1",
                [$tid, $nameIn]
            );
            if ($dup) {
                throw new Exception('Pemain eksternal "'.$nameIn.'" sudah ada di tim ini.');
            }
            db_exec(
                "INSERT INTO tim_external(tim_id,nama,nomor_wa,catatan,invited_by) VALUES($1,$2,$3,$4,$5)",
                [$tid, $nameIn, null, $cat ?: null, $uid]
            );
            $_SESSION['flash_ok'] = "Pemain eksternal '$nameIn' diundang (dari data absensi).";
        }
        elseif ($a === 'del_member') {
            db_exec("DELETE FROM tim_member WHERE tim_id=$1 AND user_id=$2", [(int)$_POST['tim_id'],(int)$_POST['user_id']]);
        }
        elseif ($a === 'del_external') {
            db_exec("DELETE FROM tim_external WHERE id=$1", [(int)$_POST['ext_id']]);
        }
        elseif ($a === 'del_tim') {
            // Revisi 24 Juni 2026 — Hapus tim beserta anggota & pemain eksternalnya.
            $tid = (int)($_POST['tim_id'] ?? 0);
            if ($tid > 0) {
                // Lepas relasi dari jadwal agar tidak ada FK menggantung
                db_exec("UPDATE jadwal SET tim_id=NULL WHERE tim_id=$1", [$tid]);
                // tim_external & tim_member ikut terhapus (tim_external via ON DELETE CASCADE),
                // tim_member dihapus eksplisit untuk jaga-jaga bila tanpa cascade.
                try { db_exec("DELETE FROM tim_member WHERE tim_id=$1", [$tid]); } catch (Throwable $e) {}
                try { db_exec("DELETE FROM tim_external WHERE tim_id=$1", [$tid]); } catch (Throwable $e) {}
                db_exec("DELETE FROM tim WHERE id=$1", [$tid]);
                $_SESSION['flash_ok'] = "Tim berhasil dihapus.";
                header("Location: /admin/tim.php"); exit;
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = $e->getMessage();
    }
    $back = '/admin/tim.php';
    if (!empty($_POST['tim_id'])) $back .= '?tim='.(int)$_POST['tim_id'];
    header("Location: $back"); exit;
}

$flash_ok  = $_SESSION['flash_ok']  ?? null; unset($_SESSION['flash_ok']);
$flash_err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);

// Revisi Juli 2026 #6 — jadwal & user difilter per komunitas admin login.
$__vids  = scope_user_ids_sql_array();
$__vkids = scope_kom_ids_sql_array();
$__jadwalScopeSql = scope_is_super() ? '' : ' AND (j.komunitas_id IS NULL OR j.komunitas_id = ANY($1::int[]))';
$__jadwalScopeParams = scope_is_super() ? [] : [$__vkids];
$jadwals = db_all("SELECT j.id, j.tanggal, j.jenis, j.tempat, j.jam_mulai, j.tim_id, t.nama AS tim_nama
                   FROM jadwal j LEFT JOIN tim t ON t.id=j.tim_id
                   WHERE j.tanggal >= CURRENT_DATE - INTERVAL '30 days' $__jadwalScopeSql
                   ORDER BY j.tanggal DESC LIMIT 50", $__jadwalScopeParams);

$selTim = (int)($_GET['tim'] ?? 0);
$tim = $selTim ? db_one("SELECT * FROM tim WHERE id=$1", [$selTim]) : null;
$tjadwal = $selTim ? db_one("SELECT * FROM jadwal WHERE tim_id=$1 ORDER BY tanggal DESC LIMIT 1", [$selTim]) : null;
$members = $selTim ? db_all("SELECT tm.*, u.nama, u.foto_url, u.nomor_wa FROM tim_member tm JOIN users u ON u.id=tm.user_id WHERE tm.tim_id=$1 ORDER BY u.nama", [$selTim]) : [];
$externals = $selTim ? db_all("SELECT * FROM tim_external WHERE tim_id=$1 ORDER BY id DESC", [$selTim]) : [];
// Dropdown "user komunitas" hanya menampilkan user dalam scope komunitas admin.
$allUsers = db_all("SELECT id, nama FROM users
                    WHERE role IN ('member','admin','superadmin')
                      AND id = ANY($1::int[])
                    ORDER BY nama", [$__vids]);


/* Revisi 22 Juni 2026 R12 — Daftar pemain eksternal diambil dari `member_eksternal`
   (tamu yang sudah terdaftar via admin/absensi.php). Tampilkan nama unik beserta
   info siapa yang membawa & jadwal terakhirnya. */
$externalTamuList = [];
try {
    // Revisi Juli 2026 #6 — filter daftar tamu per komunitas (via komunitas jadwal
    // atau via user pembawa yang berada di scope komunitas admin).
    $externalTamuList = db_all("
        SELECT DISTINCT ON (LOWER(TRIM(me.nama_tamu)))
               me.nama_tamu AS nama,
               u.nama AS dibawa_oleh,
               j.tanggal AS jadwal_tgl,
               j.jenis   AS jadwal_jenis
        FROM member_eksternal me
        LEFT JOIN users u  ON u.id = me.dibawa_oleh_id
        LEFT JOIN jadwal j ON j.id = me.jadwal_id
        WHERE me.nama_tamu IS NOT NULL AND TRIM(me.nama_tamu) <> ''
          AND ( me.dibawa_oleh_id = ANY($1::int[])
             OR j.komunitas_id    = ANY($2::int[]) )
        ORDER BY LOWER(TRIM(me.nama_tamu)), me.id DESC
    ", [$__vids, $__vkids]);
} catch (Throwable $e) { $externalTamuList = []; }


include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-1"><i class="bi bi-people-fill text-primary"></i> Pembuatan Tim</h2>
<p class="small text-muted mb-3">Buat tim langsung dari jadwal kegiatan. Anda bisa menambah anggota dari komunitas <em>atau</em> mengundang teman eksternal (luar komunitas).</p>

<?php if($flash_ok): ?><div class="alert alert-success py-2"><?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>
<?php if($flash_err): ?><div class="alert alert-warning py-2"><?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header"><i class="bi bi-plus-circle text-success"></i> Buat Tim dari Jadwal Kegiatan</div>
      <div class="card-body">
        <form method="post" class="vstack gap-2" data-loading-btn>
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="create">
          <label class="small fw-bold mb-0">Pilih Jadwal Kegiatan</label>
          <select name="jadwal_id" class="form-select form-select-sm" required>
            <option value="">— pilih jadwal —</option>
            <?php foreach($jadwals as $j): ?>
              <option value="<?= (int)$j['id'] ?>">
                <?= htmlspecialchars($j['tanggal'].' · '.$j['jenis'].' · '.$j['tempat']) ?>
                <?= !empty($j['tim_nama']) ? ' (sudah ada tim: '.htmlspecialchars($j['tim_nama']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label class="small fw-bold mb-0">Nama Tim</label>
          <input name="nama" class="form-control form-control-sm" maxlength="120" placeholder="Cth: Tim Sumringah" required>
          <div class="row g-2">
            <div class="col-6">
              <label class="small fw-bold mb-0">Kuota</label>
              <input type="number" name="kuota" min="2" max="40" value="6" class="form-control form-control-sm">
            </div>
          </div>
          <label class="small fw-bold mb-0">Catatan</label>
          <textarea name="catatan" rows="2" class="form-control form-control-sm" placeholder="opsional"></textarea>
          <button class="btn btn-success btn-sm mt-1"><i class="bi bi-check2-circle"></i> Buat / Simpan Tim</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header"><i class="bi bi-list-ul"></i> Tim dari Jadwal Terdekat</div>
      <div class="list-group list-group-flush">
        <?php foreach($jadwals as $j): if(empty($j['tim_id'])) continue; ?>
          <a href="?tim=<?= (int)$j['tim_id'] ?>" class="list-group-item list-group-item-action small">
            <strong><?= htmlspecialchars($j['tim_nama'] ?? '-') ?></strong>
            <span class="text-muted">· <?= htmlspecialchars($j['tanggal'].' '.$j['jenis']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <?php if(!$tim): ?>
      <div class="alert alert-info">Pilih tim di kiri atau buat tim baru untuk mulai mengundang anggota.</div>
    <?php else: ?>
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-people"></i> <strong><?= htmlspecialchars($tim['nama']) ?></strong> · <?= htmlspecialchars($tim['jenis']) ?></span>
          <span class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">Kuota <?= (int)$tim['kuota'] ?></span>
            <!-- Revisi 24 Juni 2026 — Tombol hapus tim -->
            <form method="post" onsubmit="return confirm('Hapus tim &quot;<?= htmlspecialchars($tim['nama'], ENT_QUOTES) ?>&quot; beserta seluruh anggota & pemain eksternalnya? Tindakan ini tidak bisa dibatalkan.')" data-loading-btn>
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="_action" value="del_tim">
              <input type="hidden" name="tim_id" value="<?= (int)$tim['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus Tim</button>
            </form>
          </span>
        </div>
        <div class="card-body">
          <?php if($tjadwal): ?>
            <div class="small text-muted mb-2"><i class="bi bi-calendar-event"></i>
              Jadwal: <?= htmlspecialchars($tjadwal['tanggal']) ?> · <?= htmlspecialchars($tjadwal['jenis']) ?> · <?= htmlspecialchars($tjadwal['tempat']) ?>
            </div>
          <?php endif; ?>

          <h6 class="mt-3"><i class="bi bi-person-check text-success"></i> Anggota Internal (<?= count($members) ?>)</h6>
          <form method="post" class="d-flex gap-2 mb-2" data-loading-btn>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="add_member">
            <input type="hidden" name="tim_id" value="<?= (int)$tim['id'] ?>">
            <select name="user_id" class="form-select form-select-sm" required>
              <option value="">— pilih user komunitas —</option>
              <?php foreach($allUsers as $au): ?>
                <option value="<?= (int)$au['id'] ?>"><?= htmlspecialchars($au['nama']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Tambah</button>
          </form>
          <ul class="list-group list-group-flush small mb-3">
            <?php foreach($members as $m): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><i class="bi bi-person"></i> <?= htmlspecialchars($m['nama']) ?> <?= $m['peran']==='koordinator' ? '<span class="badge bg-warning text-dark ms-1">Koordinator</span>':'' ?></span>
                <form method="post" onsubmit="return confirm('Hapus anggota?')" data-loading-btn>
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="_action" value="del_member">
                  <input type="hidden" name="tim_id" value="<?= (int)$tim['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                  <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button>
                </form>
              </li>
            <?php endforeach; if(!$members): ?><li class="text-muted px-0">Belum ada anggota internal.</li><?php endif; ?>
          </ul>

          <h6 class="mt-3"><i class="bi bi-person-plus text-info"></i> Pemain Eksternal (<?= count($externals) ?>)</h6>
          <!-- Revisi 22 Juni 2026 R12 — Pemain eksternal kini WAJIB dipilih dari data tamu
               yang sudah diinput admin di halaman admin/absensi.php (tabel member_eksternal).
               Tidak ada lagi input manual nama / WA untuk mencegah double-entry & typo. -->
          <p class="small text-muted">
            Pilih nama tamu yang sebelumnya sudah diinput di
            <a href="/admin/absensi.php"><i class="bi bi-check2-square"></i> Input Absensi</a>.
            Bila nama yang dicari belum ada, tambahkan dulu sebagai tamu pada jadwal terkait.
          </p>
          <form method="post" class="row g-2 mb-2" data-loading-btn>
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="_action" value="add_external">
            <input type="hidden" name="tim_id" value="<?= (int)$tim['id'] ?>">
            <div class="col-md-7">
              <select name="nama_ext_pick" class="form-select form-select-sm" required>
                <option value="">— pilih nama tamu dari absensi —</option>
                <?php foreach($externalTamuList as $ex): ?>
                  <option value="<?= htmlspecialchars($ex['nama']) ?>">
                    <?= htmlspecialchars($ex['nama']) ?>
                    <?= !empty($ex['dibawa_oleh']) ? ' · dibawa '.htmlspecialchars($ex['dibawa_oleh']) : '' ?>
                    <?= !empty($ex['jadwal_tgl']) ? ' · '.htmlspecialchars($ex['jadwal_tgl']) : '' ?>
                    <?= !empty($ex['jadwal_jenis']) ? ' ('.htmlspecialchars($ex['jadwal_jenis']).')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if(!$externalTamuList): ?>
                <div class="form-text text-warning small">Belum ada tamu di data absensi. Tambahkan dulu lewat halaman Input Absensi.</div>
              <?php endif; ?>
            </div>
            <div class="col-md-4"><input name="cat_ext" class="form-control form-control-sm" placeholder="Catatan / posisi (opsional)"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-sm btn-info text-white" <?= $externalTamuList ? '' : 'disabled' ?>><i class="bi bi-plus"></i></button></div>
          </form>
          <ul class="list-group list-group-flush small">
            <?php foreach($externals as $ex):
              $waLink = '';
              if (!empty($ex['nomor_wa'])) {
                $no = preg_replace('/\D+/','', $ex['nomor_wa']);
                if (str_starts_with($no,'0')) $no = '62'.substr($no,1);
                $msg = "Halo ".$ex['nama'].", kamu diundang gabung tim ".$tim['nama']." untuk kegiatan ".($tjadwal['jenis'] ?? 'olahraga').
                       ($tjadwal? ' tanggal '.$tjadwal['tanggal'].' di '.$tjadwal['tempat'] : '').". Bisa hadir?";
                $waLink = 'https://wa.me/'.$no.'?text='.rawurlencode($msg);
              }
            ?>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0 flex-wrap gap-2">
                <span>
                  <i class="bi bi-person-badge text-info"></i>
                  <strong><?= htmlspecialchars($ex['nama']) ?></strong>
                  <?php if(!empty($ex['nomor_wa'])): ?><span class="text-muted small"> · <?= htmlspecialchars($ex['nomor_wa']) ?></span><?php endif; ?>
                  <?php if(!empty($ex['catatan'])): ?><br><small class="text-muted">"<?= htmlspecialchars($ex['catatan']) ?>"</small><?php endif; ?>
                </span>
                <span class="d-flex gap-1">
                  <?php if($waLink): ?>
                    <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?= htmlspecialchars($waLink) ?>"><i class="bi bi-whatsapp"></i> Undang</a>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Hapus eksternal?')" data-loading-btn>
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="_action" value="del_external">
                    <input type="hidden" name="tim_id" value="<?= (int)$tim['id'] ?>">
                    <input type="hidden" name="ext_id" value="<?= (int)$ex['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                  </form>
                </span>
              </li>
            <?php endforeach; if(!$externals): ?><li class="text-muted px-0">Belum ada pemain eksternal.</li><?php endif; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

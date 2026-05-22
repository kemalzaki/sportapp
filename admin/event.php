<?php
// Admin: kelola event/tournament + bracket sederhana
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/notifications.php';
send_security_headers(); enforce_session_timeout();
require_role('admin');
$pageTitle = 'Admin · Event';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a==='create') {
        $id = db_one("INSERT INTO event(nama,jenis,tipe,deskripsi,tanggal_mulai,tanggal_selesai,jam_mulai,jam_selesai,lokasi,batas_daftar,hadiah,status,created_by)
                      VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,'open',$12) RETURNING id",
            [trim($_POST['nama']), $_POST['jenis'], $_POST['tipe'], $_POST['deskripsi'] ?? '',
             $_POST['tanggal_mulai'], $_POST['tanggal_selesai'] ?: null,
             $_POST['jam_mulai'] ?: null, $_POST['jam_selesai'] ?: null,
             trim($_POST['lokasi'] ?? '') ?: null, $_POST['batas_daftar'] ?: null,
             $_POST['hadiah'] ?? '', (int)current_user()['id']]);
        notify_all('event', '🏆 Event baru: '.$_POST['nama'], 'Daftar sekarang di menu Event.', '/event.php?id='.$id['id']);
    } elseif ($a==='delete') {
        db_exec("DELETE FROM event WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='status') {
        db_exec("UPDATE event SET status=$1 WHERE id=$2", [$_POST['status'], (int)$_POST['id']]);
    } elseif ($a==='add_match') {
        db_exec("INSERT INTO event_match(event_id,round,tim_a,tim_b) VALUES($1,$2,$3,$4)",
            [(int)$_POST['event_id'], (int)$_POST['round'], (int)$_POST['tim_a'], (int)$_POST['tim_b']]);
        // Auto-tambahkan tim ke peserta event (jika belum ada)
        foreach ([(int)$_POST['tim_a'], (int)$_POST['tim_b']] as $tid) {
            if ($tid > 0) {
                try { db_exec("INSERT INTO event_peserta(event_id,tim_id) SELECT $1,$2
                               WHERE NOT EXISTS (SELECT 1 FROM event_peserta WHERE event_id=$1 AND tim_id=$2)",
                              [(int)$_POST['event_id'], $tid]); } catch (Throwable $e) {}
            }
        }
    } elseif ($a==='add_peserta') {
        $eid = (int)$_POST['event_id'];
        $tid = (int)($_POST['tim_id'] ?? 0) ?: null;
        $uid = (int)($_POST['user_id'] ?? 0) ?: null;
        if ($tid || $uid) {
            try { db_exec("INSERT INTO event_peserta(event_id,tim_id,user_id) VALUES($1,$2,$3)", [$eid,$tid,$uid]); } catch (Throwable $e) {}
        }
    } elseif ($a==='del_peserta') {
        db_exec("DELETE FROM event_peserta WHERE id=$1", [(int)$_POST['id']]);
    } elseif ($a==='update_score') {
        $sa = (int)$_POST['score_a']; $sb = (int)$_POST['score_b'];
        $win = $sa > $sb ? (int)$_POST['tim_a'] : ($sb > $sa ? (int)$_POST['tim_b'] : null);
        db_exec("UPDATE event_match SET score_a=$1, score_b=$2, pemenang=$3 WHERE id=$4",
            [$sa, $sb, $win, (int)$_POST['id']]);
    }
    header('Location: event.php'); exit;
}

$events = db_all("SELECT * FROM event ORDER BY tanggal_mulai DESC");
$tims = db_all("SELECT id,nama,jenis FROM tim ORDER BY nama");
$jenisList = array_column(db_all("SELECT nama FROM jenis_olahraga ORDER BY nama"), 'nama') ?: ['Jogging','Badminton','Futsal'];
include __DIR__.'/../includes/header.php';
?>
<h2 class="mb-3"><i class="bi bi-trophy text-warning"></i> Manajemen Event</h2>

<div class="card shadow-sm mb-3"><div class="card-header">Tambah Event</div>
<div class="card-body"><form method="post" class="row g-2">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="create">
  <div class="col-md-4"><label class="small fw-semibold">Nama</label><input class="form-control" name="nama" required></div>
  <div class="col-md-2"><label class="small fw-semibold">Jenis</label>
    <select name="jenis" class="form-select"><?php foreach($jenisList as $j): ?><option><?= htmlspecialchars($j) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="small fw-semibold">Tipe</label>
    <select name="tipe" class="form-select">
      <option value="challenge">Challenge</option><option value="tournament">Tournament</option>
      <option value="sparring">Sparring</option><option value="mini">Mini Tournament</option>
    </select></div>
  <div class="col-md-2"><label class="small fw-semibold">Mulai</label><input type="date" name="tanggal_mulai" class="form-control" required></div>
  <div class="col-md-2"><label class="small fw-semibold">Selesai</label><input type="date" name="tanggal_selesai" class="form-control"></div>
  <div class="col-md-2"><label class="small fw-semibold">Jam Mulai</label><input type="time" name="jam_mulai" class="form-control"></div>
  <div class="col-md-2"><label class="small fw-semibold">Jam Selesai</label><input type="time" name="jam_selesai" class="form-control"></div>
  <div class="col-md-4"><label class="small fw-semibold">Lokasi / Tempat</label><input class="form-control" name="lokasi" placeholder="cth: GOR Senayan"></div>
  <div class="col-md-4"><label class="small fw-semibold">Batas Pendaftaran</label><input type="date" name="batas_daftar" class="form-control"></div>
  <div class="col-md-6"><label class="small fw-semibold">Hadiah</label><input class="form-control" name="hadiah" placeholder="cth: Badge eksklusif + Voucher"></div>
  <div class="col-12"><label class="small fw-semibold">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="2"></textarea></div>
  <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button></div>
</form></div></div>

<?php foreach($events as $e):
  $ms = db_all("SELECT m.*, a.nama AS a_n, b.nama AS b_n FROM event_match m
                LEFT JOIN tim a ON a.id=m.tim_a LEFT JOIN tim b ON b.id=m.tim_b
                WHERE m.event_id=$1 ORDER BY round,id", [(int)$e['id']]);
?>
<div class="card shadow-sm mb-3"><div class="card-body">
  <div class="d-flex justify-content-between">
    <div><h5><?= htmlspecialchars($e['nama']) ?> <small class="text-muted">· <?= htmlspecialchars($e['jenis']) ?> · <?= htmlspecialchars($e['tipe']) ?></small></h5>
      <div class="small text-muted"><?= htmlspecialchars($e['tanggal_mulai']) ?> → <?= htmlspecialchars($e['tanggal_selesai'] ?? '-') ?> · Status: <?= htmlspecialchars($e['status']) ?></div>
    </div>
    <div class="d-flex gap-2">
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="status"><input type="hidden" name="id" value="<?= $e['id'] ?>">
        <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
          <?php foreach(['open','ongoing','closed','done'] as $s): ?><option <?= $s===$e['status']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
      </form>
      <form method="post" onsubmit="return confirm('Hapus event?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
  </div>
  <hr>
  <form method="post" class="row g-2 align-items-end">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="add_match"><input type="hidden" name="event_id" value="<?= $e['id'] ?>">
    <div class="col-md-1"><label class="small fw-semibold">Round</label><input type="number" name="round" class="form-control" value="1" min="1"></div>
    <div class="col-md-4"><label class="small fw-semibold">Tim A</label><select name="tim_a" class="form-select"><?php foreach($tims as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="small fw-semibold">Tim B</label><select name="tim_b" class="form-select"><?php foreach($tims as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-outline-primary w-100"><i class="bi bi-plus"></i> Match</button></div>
  </form>
  <?php if($ms): ?>
  <div class="table-responsive mt-2"><table class="table table-sm align-middle" data-paginate="10">
    <thead><tr><th>R</th><th>Tim A</th><th>Skor</th><th>Tim B</th><th></th></tr></thead><tbody>
    <?php foreach($ms as $m): ?>
    <tr>
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="_action" value="update_score">
        <input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="tim_a" value="<?= (int)$m['tim_a'] ?>"><input type="hidden" name="tim_b" value="<?= (int)$m['tim_b'] ?>">
      <td><?= (int)$m['round'] ?></td>
      <td><?= htmlspecialchars($m['a_n'] ?? '-') ?></td>
      <td style="width:160px"><div class="input-group input-group-sm"><input name="score_a" type="number" value="<?= (int)$m['score_a'] ?>" class="form-control"><span class="input-group-text">-</span><input name="score_b" type="number" value="<?= (int)$m['score_b'] ?>" class="form-control"></div></td>
      <td><?= htmlspecialchars($m['b_n'] ?? '-') ?></td>
      <td><button class="btn btn-sm btn-primary">Simpan</button></td>
      </form>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/wa_notify.php';
require __DIR__.'/../includes/badges.php';
require __DIR__.'/../includes/scope.php'; // Revisi R7 #5
require_role(['admin','superadmin']);
$pageTitle='Input Absensi';

$jadwalId = (int)($_GET['id'] ?? 0);
// Revisi R7 #5 — cegah admin membuka jadwal komunitas lain (IDOR)
if ($jadwalId) {
    $__k = db_one("SELECT komunitas_id FROM jadwal WHERE id=$1", [$jadwalId]);
    if ($__k) scope_require_kom($__k['komunitas_id'] === null ? null : (int)$__k['komunitas_id']);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $jadwalId = (int)$_POST['jadwal_id'];
    // Kumpulkan user yang sudah ber-AUTO-SAKIT pada jadwal ini -> dipertahankan, tidak boleh diubah.
    $lockedRows = db_all(
        "SELECT user_id, status, hadir, keterangan FROM absensi
         WHERE jadwal_id=$1 AND status='sakit' AND COALESCE(keterangan,'') LIKE '[AUTO-SAKIT]%'",
        [$jadwalId]
    );
    $locked = [];
    foreach ($lockedRows as $lr) { $locked[(int)$lr['user_id']] = $lr; }

    // Hapus seluruh entry KECUALI yang locked
    if ($locked) {
        $ids = array_map('intval', array_keys($locked));
        db_exec("DELETE FROM absensi WHERE jadwal_id=$1 AND user_id <> ALL($2::int[])",
            [$jadwalId, '{'.implode(',', $ids).'}']);
    } else {
        db_exec("DELETE FROM absensi WHERE jadwal_id=$1", [$jadwalId]);
    }
    db_exec("DELETE FROM member_eksternal WHERE jadwal_id=$1", [$jadwalId]);

    $allowed = ['hadir','izin','sakit','telat','absen'];
    foreach (($_POST['status'] ?? []) as $uid => $st) {
        $uid = (int)$uid;
        if (isset($locked[$uid])) continue; // skip — entry AUTO-SAKIT tetap dipertahankan
        $st = in_array($st, $allowed, true) ? $st : 'absen';
        $hadir = ($st === 'hadir' || $st === 'telat') ? 1 : 0;
        $ket = trim((string)($_POST['keterangan'][$uid] ?? ''));

        db_exec("INSERT INTO absensi(jadwal_id,user_id,hadir,status,keterangan) VALUES($1,$2,$3,$4,$5)",
                [$jadwalId, $uid, $hadir, $st, $ket ?: null]);
        // Revisi 24 Juni 2026 — evaluasi badge (mis. First Check-in) untuk yang hadir,
        // karena check-in kini lewat input absensi admin (tanpa barcode/QR).
        if ($hadir === 1) { try { recompute_badges($uid); } catch (Throwable $e) {} }
    }
    foreach (($_POST['tamu_nama'] ?? []) as $i => $n) {
        $n = trim($n); if (!$n) continue;
        $dibawa = (int)($_POST['tamu_oleh'][$i] ?? 0) ?: null;
        db_exec("INSERT INTO member_eksternal(jadwal_id,nama_tamu,dibawa_oleh_id) VALUES($1,$2,$3)",
                [$jadwalId, $n, $dibawa]);
    }
    // Revisi: kirim notifikasi WA + in-app + FCM ke semua peserta + admin PIC
    try {
      $j = db_one("SELECT jenis, tanggal, tempat FROM jadwal WHERE id=$1", [$jadwalId]);
      if ($j) {
        $judul = 'Absensi '.($j['jenis'] ?? 'Event').' tanggal '.date('d M Y', strtotime($j['tanggal']));
        $isi   = 'Absensi telah diinput admin untuk kegiatan "'.$j['jenis'].'" di '.($j['tempat'] ?? '-').'. Cek riwayat kamu di aplikasi.';
        wa_notify_event($jadwalId, $judul, $isi);
        wa_notify_pic_admins('Reminder PIC: '.$judul, 'Mohon ingatkan member kamu untuk cek hasil absensi & jadwal berikutnya.');
      }
    } catch (Throwable $e) {}

    header("Location: absensi.php?id={$jadwalId}&saved=1"); exit;
}

$jadwal=null; $members=[]; $current=[]; $currentKet=[]; $tamu=[];
if ($jadwalId) {
    $jadwal  = db_one("SELECT j.*, u.nama AS koord, u.foto_url AS koord_foto FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id WHERE j.id=$1", [$jadwalId]);
    $members = db_all("SELECT id,nama,role,foto_url,last_seen FROM users WHERE role IN ('member','admin','superadmin') AND id = ANY($1::int[]) ORDER BY nama", [scope_user_ids_sql_array()]);
    foreach (db_all("SELECT user_id,hadir,status,keterangan FROM absensi WHERE jadwal_id=$1", [$jadwalId]) as $a) {
        $current[$a['user_id']] = $a['status'] ?: ($a['hadir']==1?'hadir':'absen');
        $currentKet[$a['user_id']] = $a['keterangan'] ?? '';
    }
    $tamu = db_all("SELECT * FROM member_eksternal WHERE jadwal_id=$1", [$jadwalId]);
}
// Revisi 24 Juni 2026 — Filter bulanan agar dropdown jadwal tidak memanjang ke bawah.
// Revisi Juli 2026 R8 #5 — dropdown jadwal & bulan DIFILTER per komunitas admin.
$__isSuper = scope_is_super();
$__vkidsAbs = scope_kom_ids_sql_array();
// Revisi R9 Juli 2026 — jadwal absensi wajib per komunitas (drop NULL fallback).
$__komFilter = $__isSuper ? '' : " AND (komunitas_id = ANY('".pg_escape_string($__vkidsAbs)."'::int[]))";
$blnList = db_all("SELECT DISTINCT to_char(tanggal,'YYYY-MM') AS ym FROM jadwal WHERE TRUE $__komFilter ORDER BY ym DESC");
$blnSel = (string)($_GET['bln'] ?? '');
if ($blnSel === '' && !$jadwalId && $blnList) { $blnSel = $blnList[0]['ym']; }
if ($blnSel !== '' && preg_match('/^\d{4}-\d{2}$/', $blnSel)) {
    $jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal WHERE to_char(tanggal,'YYYY-MM')=\$1 $__komFilter ORDER BY tanggal DESC", [$blnSel]);
} else {
    $jadwalList = db_all("SELECT id,tanggal,jenis,tempat FROM jadwal WHERE TRUE $__komFilter ORDER BY tanggal DESC");
}
$bulanNama = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$fmtBln = function($ym) use ($bulanNama){ [$y,$m]=explode('-',$ym); return ($bulanNama[(int)$m] ?? $m).' '.$y; };
include __DIR__.'/../includes/header.php'; ?>

<h2 class="mb-3"><i class="bi bi-check2-square text-primary"></i> Input Absensi (RSVP)</h2>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-4"><label class="form-label small mb-1">Filter Bulan</label>
    <select name="bln" class="form-select" onchange="this.form.submit()">
      <option value="">— Semua Bulan —</option>
      <?php foreach($blnList as $b): ?>
        <option value="<?= htmlspecialchars($b['ym']) ?>" <?= $b['ym']===$blnSel?'selected':'' ?>><?= htmlspecialchars($fmtBln($b['ym'])) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-8"><label class="form-label small mb-1">Pilih Jadwal</label>
    <select name="id" class="form-select" onchange="this.form.submit()">
      <option value="">— Pilih Jadwal —</option>
      <?php foreach($jadwalList as $j): ?>
        <option value="<?= $j['id'] ?>" <?= $j['id']==$jadwalId?'selected':'' ?>><?= $j['tanggal'] ?> — <?= $j['jenis'] ?> @ <?= htmlspecialchars($j['tempat']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
<?php if($jadwalId): ?><div class="col-md-4"><a href="/export.php?type=absensi&jadwal_id=<?= $jadwalId ?>&format=csv" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Export Excel</a>
<a href="/export.php?type=absensi&jadwal_id=<?= $jadwalId ?>&format=pdf" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a></div><?php endif; ?>
</form>

<?php if(isset($_GET['saved'])): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Absensi tersimpan.</div><?php endif; ?>

<?php if($jadwal): ?>
<div class="mb-3 small text-muted">Koordinator: <?= user_name_with_avatar($jadwal['koord_foto'] ?? null, $jadwal['koord'] ?? '-', false, 24) ?></div>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="jadwal_id" value="<?= $jadwal['id'] ?>">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-people me-1 text-primary"></i> Member Internal — pilih status RSVP</span>
        <!-- Revisi 22 Juni 2026 R12 — Filter pencarian + pagination supaya tidak memanjang ke bawah -->
        <input id="absFilter" type="search" class="form-control form-control-sm" style="max-width:220px" placeholder="🔎 Cari nama member...">
      </div>
      <ul class="list-group list-group-flush" id="absMemberList" data-per-page="10">
        <?php
          $opts = [
            'hadir' => ['Hadir','success','bi-check-circle'],
            'telat' => ['Telat','warning','bi-clock-history'],
            'izin'  => ['Izin','info','bi-envelope'],
            'sakit' => ['Sakit','danger','bi-bandaid'],
            'absen' => ['Absen','secondary','bi-x-circle'],
          ];
          foreach($members as $m): $st = $current[$m['id']] ?? 'absen'; $ket = $currentKet[$m['id']] ?? '';
          $isAutoSakit = ($st === 'sakit' && strpos((string)$ket, '[AUTO-SAKIT]') === 0);
        ?>
        <li class="list-group-item <?= $isAutoSakit ? 'bg-light' : '' ?>">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><?= user_name_with_avatar($m['foto_url'] ?? null, $m['nama'], is_online($m['last_seen'] ?? null), 28) ?> <span class="role role-<?= $m['role'] ?>"><?= $m['role'] ?></span>
              <?php if($isAutoSakit): ?><span class="badge bg-danger ms-1" title="Member set kondisi Sakit dari profil. Tidak bisa diedit di sini."><i class="bi bi-lock-fill"></i> AUTO-SAKIT</span><?php endif; ?>
            </span>
            <div class="btn-group btn-group-sm flex-wrap">
              <?php foreach($opts as $k=>$o):
                $rid = "rsvp_{$m['id']}_$k"; ?>
                <input type="radio" class="btn-check" name="status[<?= $m['id'] ?>]" value="<?= $k ?>" id="<?= $rid ?>" <?= $st===$k?'checked':'' ?> <?= $isAutoSakit?'disabled':'' ?>>
                <label class="btn btn-outline-<?= $o[1] ?> <?= $isAutoSakit?'disabled':'' ?>" for="<?= $rid ?>"><i class="bi <?= $o[2] ?>"></i> <?= $o[0] ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-2">
            <input type="text" class="form-control form-control-sm" name="keterangan[<?= $m['id'] ?>]" placeholder="Catatan (opsional) — mis. cedera, alasan izin/sakit, dll." value="<?= htmlspecialchars($ket) ?>" <?= $isAutoSakit?'readonly':'' ?>>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div id="absPagerWrap" class="card-footer d-flex justify-content-between align-items-center small text-muted py-2" style="display:none">
        <span id="absPagerInfo"></span>
        <nav><ul class="pagination pagination-sm mb-0" id="absPager"></ul></nav>
      </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm"><div class="card-header"><i class="bi bi-person-plus me-1 text-primary"></i> Tamu Eksternal</div>
      <div class="card-body" id="tamuBox">
        <?php foreach($tamu as $t): ?>
          <div class="row g-1 mb-2"><div class="col-7"><input class="form-control" name="tamu_nama[]" value="<?= htmlspecialchars($t['nama_tamu']) ?>"></div>
            <div class="col-5"><select class="form-select" name="tamu_oleh[]"><option value="">Dibawa oleh…</option>
              <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$t['dibawa_oleh_id']?'selected':'' ?>><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
            </select></div></div>
        <?php endforeach; ?>
        <div class="row g-1 mb-2"><div class="col-7"><input class="form-control" name="tamu_nama[]" placeholder="Nama tamu"></div>
          <div class="col-5"><select class="form-select" name="tamu_oleh[]"><option value="">Dibawa oleh…</option>
            <?php foreach($members as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
          </select></div></div>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('tamuBox').insertAdjacentHTML('beforeend', document.querySelector('#tamuBox .row:last-child').outerHTML)"><i class="bi bi-plus"></i> Tambah tamu</button>
      </div></div>
    </div>
  </div>
  <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Absensi</button>
</form>

<!-- Revisi 22 Juni 2026 R12 — Pagination + filter client-side untuk daftar member -->
<script>
(function(){
  var list = document.getElementById('absMemberList');
  if (!list) return;
  var perPage = parseInt(list.dataset.perPage || '10', 10);
  var filter = document.getElementById('absFilter');
  var pagerWrap = document.getElementById('absPagerWrap');
  var pager = document.getElementById('absPager');
  var info = document.getElementById('absPagerInfo');
  var page = 1;

  function visibleItems(){
    var q = (filter.value || '').trim().toLowerCase();
    return Array.from(list.children).filter(function(li){
      var name = (li.textContent || '').toLowerCase();
      var match = !q || name.indexOf(q) !== -1;
      li.dataset._match = match ? '1' : '0';
      return match;
    });
  }
  function render(){
    Array.from(list.children).forEach(function(li){ li.style.display='none'; });
    var items = visibleItems();
    var total = items.length;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    if (page > totalPages) page = totalPages;
    var start = (page-1)*perPage;
    items.slice(start, start+perPage).forEach(function(li){ li.style.display=''; });
    // pager
    pager.innerHTML = '';
    if (totalPages > 1) {
      pagerWrap.style.display = '';
      function btn(label, p, disabled, active){
        var li = document.createElement('li');
        li.className = 'page-item' + (disabled?' disabled':'') + (active?' active':'');
        var a = document.createElement('a');
        a.className = 'page-link'; a.href='#'; a.textContent = label;
        a.addEventListener('click', function(e){ e.preventDefault(); if(!disabled){ page = p; render(); }});
        li.appendChild(a); pager.appendChild(li);
      }
      btn('«', Math.max(1,page-1), page===1, false);
      var maxBtns = 7;
      var from = Math.max(1, page-3), to = Math.min(totalPages, from+maxBtns-1);
      from = Math.max(1, to-maxBtns+1);
      for (var p=from; p<=to; p++) btn(String(p), p, false, p===page);
      btn('»', Math.min(totalPages,page+1), page===totalPages, false);
      info.textContent = 'Halaman '+page+' / '+totalPages+' · '+total+' member';
    } else {
      pagerWrap.style.display = total ? 'none' : 'none';
      if (total) { pagerWrap.style.display=''; info.textContent = total+' member'; pager.innerHTML=''; }
    }
  }
  filter.addEventListener('input', function(){ page = 1; render(); });
  render();
})();
</script>
<?php endif; ?>

<?php include __DIR__.'/../includes/footer.php'; ?>

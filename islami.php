<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/cities_data.php';
require __DIR__.'/includes/islami_helpers.php';
require __DIR__.'/includes/shalat_data.php';
require __DIR__.'/includes/paket_helpers.php'; // R14 #1 — fitur PRO
send_security_headers(); require_login();
$pageTitle = 'Hub Islami';
$u = current_user();
$IS_PRO = paket_is_pro($u);
$USER_PAKET = paket_user($u);
// R15 #5: Hub Islami HANYA untuk paket Komunitas. Pro/Gratis tidak bisa akses.
$IS_KOMUNITAS = ($USER_PAKET === 'komunitas');

// Revisi 17 Juni 2026 Part I — tabel penyimpanan Tanya Jawab Islami (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS islami_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS islami_qa_user_idx ON islami_qa_saved(user_id, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'challenge_done') {
        $key = preg_replace('/[^a-z_]/','', $_POST['key'] ?? '');
        if ($key !== '') {
            islami_log_challenge((int)$u['id'], $key, $_POST['catatan'] ?? null);
            if ($key === 'ayat_harian') islami_touch_streak((int)$u['id'], 'quran_done');
            if ($key === 'dzikir_pagi') islami_touch_streak((int)$u['id'], 'dzikir_pagi');
            if ($key === 'dzikir_petang') islami_touch_streak((int)$u['id'], 'dzikir_petang');
            if ($key === 'subuh_walk') islami_touch_streak((int)$u['id'], 'subuh_walk');
            if ($key === 'doa') islami_touch_streak((int)$u['id'], 'doa_done');
        }
        $_SESSION['flash'] = 'Tercatat. Semoga istiqamah.';
        header('Location: /islami.php'); exit;
    } elseif ($a === 'save_pref') {
        islami_set_pref((int)$u['id'], [
            'kota' => substr(trim($_POST['kota'] ?? 'Jakarta'),0,60),
            'negara' => substr(trim($_POST['negara'] ?? 'Indonesia'),0,40),
            'mode_tenang' => isset($_POST['mode_tenang']) ? 1 : 0,
        ]);
        $_SESSION['flash'] = 'Preferensi disimpan.';
        header('Location: /islami.php'); exit;
    } elseif ($a === 'hide_sapa') {
        islami_set_pref((int)$u['id'], ['hide_sapa' => 1]);
        header('Location: /index.php'); exit;
    } elseif ($a === 'ssunnah_toggle') {
        // Revisi R22 — toggle Tahajud / Duha untuk tanggal tertentu
        header('Content-Type: application/json');
        $jenis = ($_POST['jenis'] ?? '') === 'duha' ? 'duha' : 'tahajud';
        $tgl   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
        $rakaat= max(0,min(20,(int)($_POST['rakaat'] ?? 2)));
        $cat   = mb_substr(trim($_POST['catatan'] ?? ''),0,500);
        try {
            $exists = (int)db_val("SELECT COUNT(*) FROM shalat_sunnah_log WHERE user_id=$1 AND jenis=$2 AND tanggal=$3",
                [(int)$u['id'],$jenis,$tgl]);
            if ($exists) {
                db_exec("DELETE FROM shalat_sunnah_log WHERE user_id=$1 AND jenis=$2 AND tanggal=$3",
                    [(int)$u['id'],$jenis,$tgl]);
                echo json_encode(['ok'=>true,'state'=>'off']); exit;
            } else {
                db_exec("INSERT INTO shalat_sunnah_log(user_id,jenis,tanggal,rakaat,catatan) VALUES($1,$2,$3,$4,$5)",
                    [(int)$u['id'],$jenis,$tgl,$rakaat,$cat ?: null]);
                echo json_encode(['ok'=>true,'state'=>'on']); exit;
            }
        } catch (Throwable $e) { echo json_encode(['ok'=>false,'err'=>'db']); exit; }
    } elseif ($a === 'qa_save') {
        // Revisi 17 Juni 2026 Part I — simpan Q&A AI
        header('Content-Type: application/json');
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban'] ?? ''));
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000) $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(), "INSERT INTO islami_qa_saved(user_id,pertanyaan,jawaban) VALUES($1,$2,$3) RETURNING id",
            [(int)$u['id'],$q,$j]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    } elseif ($a === 'qa_delete') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM islami_qa_saved WHERE id=$1 AND user_id=$2",[$id,(int)$u['id']]);
        echo json_encode(['ok'=>true]); exit;
    }
}

$pref = $u ? islami_pref((int)$u['id']) : null;
$streak = $u ? islami_streak_count((int)$u['id']) : 0;
$badges = $u ? db_all("SELECT badge_key, earned_at FROM islami_badges WHERE user_id=$1 ORDER BY earned_at DESC", [(int)$u['id']]) : [];
$qaSaved = $u ? db_all("SELECT id, pertanyaan, jawaban, created_at FROM islami_qa_saved WHERE user_id=$1 ORDER BY id DESC LIMIT 50", [(int)$u['id']]) : [];

$hijri = masehi_ke_hijriyah();
$ramadhan = hijri_event_to_gregorian(9, 1);
$iedAdha  = hijri_event_to_gregorian(12, 10);

include __DIR__.'/includes/header.php';
$pageSkeleton = 'feed';
?>
<link rel="stylesheet" href="assets/css/sport-islami.css">

<?php if (!$IS_KOMUNITAS): /* === R15 #5 / R21: Hub Islami hanya untuk paket KOMUNITAS === */ ?>
  <nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item active">Hub Islami</li>
  </ol></nav>
  <div class="hero-sport-islami hero-islami mb-3">
    <div class="hero-overlay">
      <span class="badge bg-success text-white mb-2"><i class="bi bi-stars"></i> HUB ISLAMI · KOMUNITAS</span>
      <h1 class="h3 mb-1 fw-bold">Assalāmu‘alaikum 🌙</h1>
      <p class="small mb-0 opacity-85">Akses lengkap Al-Qur'an, Sholat, Doa, Kalender Hijriyah, AI Tanya Jawab, Challenge, dan lainnya — eksklusif untuk paket <strong>KOMUNITAS</strong>. Paket <strong>Gratis</strong> dan <strong>Pro</strong> tidak bisa mengakses halaman ini.</p>
    </div>
  </div>
  <?php /* Revisi R21 — banner kunci sekarang memakai satu tombol terpadu
           "Lihat Paket & Upgrade" yang membuka /paket_upgrade.php. */ ?>
  <?= paket_komunitas_lock_banner('Hub Islami',
        'Halaman Hub Islami beserta semua menu Islami premium (Tanya Jawab AI, Tata Cara Wudhu/Shalat, Doa Harian Anak-Anak, Kajian Literatur, dll.) hanya tersedia untuk member dengan paket KOMUNITAS. Status paket Anda saat ini: '.strtoupper($USER_PAKET).'.') ?>
  <div class="card border-light shadow-sm">
    <div class="card-body">
      <h6 class="fw-bold"><i class="bi bi-check2-square text-success"></i> Yang akan Anda dapatkan di paket KOMUNITAS:</h6>
      <ul class="small mb-0">
        <li>🕌 Tata Cara Wudhu &amp; Shalat lengkap (ilustrasi AI tiap gerakan)</li>
        <li>🤖 Tanya Jawab Islami berbasis AI (Al-Qur'an &amp; Hadist)</li>
        <li>🔊 Doa Harian Anak-Anak dengan suara dewasa &amp; anak-anak</li>
        <li>📚 Kajian Literatur Buku Islami (PDF, video, web — kategori &amp; pemilik)</li>
        <li>🌙 Kalender Hijriyah, Jadwal Sholat, Dzikir Pagi &amp; Petang</li>
        <li>🏆 Challenge Islami, Leaderboard Amal, Statistik Streak</li>
      </ul>
    </div>
  </div>
  <?php include __DIR__.'/includes/footer.php'; exit; ?>
<?php endif; ?>


<!-- Revisi 18 Juni 2026 — sapaan Assalamu‘alaikum dipindah ke paling atas, di atas Tanya Jawab AI -->
<div class="hero-sport-islami hero-islami mb-3">
  <div class="hero-overlay d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
      <span class="badge bg-light text-success mb-2"><i class="bi bi-stars"></i> HUB ISLAMI</span>
      <h1 class="h3 mb-1 fw-bold">Assalāmu‘alaikum, semoga hari ini berkah</h1>
      <p class="small mb-0 opacity-85">Qur'an · Sholat · Dzikir · Doa · Kalender Hijriyah</p>
    </div>
    <span class="badge bg-light text-success fs-6 px-3 py-2"><i class="bi bi-moon-stars"></i> <?= $hijri['hari'] ?> <?= htmlspecialchars(hijriyah_nama_bulan($hijri['bulan'])) ?> <?= $hijri['tahun'] ?> H</span>
  </div>
</div>

<!-- Revisi 17 Juni 2026 — Tanya Jawab Islami (dipindah ke bawah sapaan, di atas grid menu) -->

<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success-subtle text-success-emphasis d-flex justify-content-between align-items-center">
    <span><i class="bi bi-patch-question-fill"></i> <strong>Tanya Jawab Islami</strong> &mdash; bertanya kepada AI berbasis Al-Qur'an &amp; Hadist</span>
  </div>
  <div class="card-body">
    <form id="tanyaForm" class="vstack gap-2 mb-2">
      <textarea id="tanyaInput" class="form-control" rows="3" placeholder="Contoh: Apa hukum shalat di kendaraan saat safar? atau Bagaimana adab makan menurut sunnah Rasulullah?" required></textarea>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-send"></i> Tanyakan</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="tanyaClear"><i class="bi bi-eraser"></i> Bersihkan</button>
        <small class="text-muted ms-auto align-self-center">Jawaban AI ditulis dengan referensi &amp; ditutup <em>Wallahu a'lam</em>.</small>
      </div>
    </form>
    <div id="tanyaOut" class="border rounded p-3 bg-body-tertiary small text-muted" style="min-height:80px">Tulis pertanyaan lalu klik <b>Tanyakan</b>.</div>
    <div id="tanyaActions" class="d-flex gap-2 mt-2" style="display:none !important">
      <button type="button" id="btnSimpanQA" class="btn btn-outline-success btn-sm"><i class="bi bi-bookmark-plus"></i> Simpan Q&amp;A ini</button>
      <span id="qaSaveStat" class="small text-muted align-self-center"></span>
    </div>
    <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-info-circle"></i> Jawaban AI bersifat panduan umum, bukan fatwa. Untuk masalah penting silakan rujuk ulama tepercaya.</div>

    <?php if ($u): ?>
    <div class="mt-3">
      <a class="small" data-bs-toggle="collapse" href="#qaSavedBox" role="button" aria-expanded="false">
        <i class="bi bi-bookmark-star"></i> Tanya Jawab Tersimpan (<?= count($qaSaved) ?>)
      </a>
      <div class="collapse mt-2" id="qaSavedBox">
        <?php if (!$qaSaved): ?>
          <div class="small text-muted">Belum ada Q&amp;A tersimpan. Klik <b>Simpan Q&amp;A ini</b> setelah AI menjawab.</div>
        <?php else: foreach ($qaSaved as $qa): ?>
          <div class="border rounded p-2 mb-2 small" data-qa-id="<?= (int)$qa['id'] ?>">
            <div class="d-flex justify-content-between">
              <strong class="text-success"><i class="bi bi-patch-question"></i> <?= htmlspecialchars(mb_strimwidth($qa['pertanyaan'],0,200,'…')) ?></strong>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 qa-del-btn" data-id="<?= (int)$qa['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
            <div class="text-muted small mb-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($qa['created_at']))) ?></div>
            <details><summary class="text-primary">Lihat jawaban</summary>
              <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($qa['jawaban']) ?></div>
            </details>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Revisi 22 Juni 2026 R12 — Pada tampilan MOBILE (<768px), countdown Hari Raya &
     Peristiwa dipindah ke atas (di bawah Tanya Jawab Islami). Di desktop tetap
     muncul di kolom kanan seperti semula. -->
<?php
  // Definisikan event countdown lebih awal supaya bisa dipakai di blok mobile & desktop.
  $cdEvents = [
    ['Ramadhan',       hijri_event_to_gregorian(9, 1),  'cdRamadhan',   'success'],
    ['Idul Fitri',     hijri_event_to_gregorian(10,1),  'cdIedFitri',   'warning'],
    ['Idul Adha',      hijri_event_to_gregorian(12,10), 'cdIedAdha',    'danger'],
    ['Tahun Baru Hijriyah', hijri_event_to_gregorian(1, 1),  'cdMuharram','info'],
    ['Asyura (10 Muharram)',hijri_event_to_gregorian(1,10),  'cdAsyura', 'secondary'],
    ['Maulid Nabi (12 Rabiul Awal)', hijri_event_to_gregorian(3,12), 'cdMaulid', 'primary'],
    ['Isra Mi\'raj (27 Rajab)',     hijri_event_to_gregorian(7,27), 'cdIsra',   'info'],
    ['Nisfu Sya\'ban (15 Sya\'ban)',hijri_event_to_gregorian(8,15), 'cdNisfu',  'dark'],
    ['Arafah (9 Dzulhijjah)',       hijri_event_to_gregorian(12,9), 'cdArafah', 'warning'],
  ];
?>
<div class="card shadow-sm mb-3 d-md-none">
  <div class="card-header"><i class="bi bi-hourglass-split text-success"></i> Countdown Hari Raya &amp; Peristiwa</div>
  <div class="card-body">
    <?php foreach ($cdEvents as $e): ?>
      <div class="mb-1 small"><strong class="text-<?= $e[3] ?>"><?= $e[0] ?></strong>
        <span class="text-muted">(<?= $e[1]->format('d M Y') ?>)</span>:
        <span id="<?= $e[2] ?>_m">…</span></div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function(){
  var form = document.getElementById('tanyaForm');
  var inp  = document.getElementById('tanyaInput');
  var out  = document.getElementById('tanyaOut');
  var actions = document.getElementById('tanyaActions');
  var btnSimpan = document.getElementById('btnSimpanQA');
  var qaStat = document.getElementById('qaSaveStat');
  if (!form) return;
  var isLoading = false;          // Revisi #2 — cegah pengiriman berulang
  var lastQ = '', lastA = '', lastSavedKey = '';
  document.getElementById('tanyaClear').addEventListener('click', function(){
    inp.value='';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Tulis pertanyaan lalu klik Tanyakan.';
    if (actions) actions.style.display='none';
    lastQ=''; lastA=''; lastSavedKey='';
  });
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    if (isLoading) return;        // guard utama
    var q = (inp.value||'').trim(); if (!q) return;
    // Revisi #2 — jika pertanyaan SAMA dengan yang baru dijawab, jangan kirim ulang.
    if (q === lastQ && lastA) {
      qaStat.textContent = 'Pertanyaan sama — gunakan jawaban sebelumnya (hemat kuota AI).';
      return;
    }
    isLoading = true;
    var btn = form.querySelector('button[type=submit]'); var oh = btn.innerHTML;
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> AI menjawab...';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Sedang menjawab... (hanya 1x kirim, mohon tunggu)';
    if (actions) actions.style.display='none';
    try {
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('task','tanya_islami');
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok) {
        out.className='border rounded p-3 bg-warning-subtle small';
        out.textContent='Gagal: '+(j.err||'?');
      } else {
        out.className='border rounded p-3 bg-body-tertiary';
        var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
        out.innerHTML = '<p>'+html+'</p>';
        lastQ = q; lastA = j.text || '';
        if (actions) actions.style.display='flex';
        qaStat.textContent = '';
      }
    } catch(err){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Error: '+err.message; }
    btn.disabled=false; btn.innerHTML=oh;
    isLoading = false;
  });

  // Revisi #1 — tombol Simpan Q&A
  if (btnSimpan) btnSimpan.addEventListener('click', async function(){
    if (!lastQ || !lastA) return;
    var key = lastQ+'|'+lastA.substring(0,32);
    if (key === lastSavedKey) { qaStat.textContent='Sudah disimpan sebelumnya.'; return; }
    btnSimpan.disabled = true;
    var fd = new FormData();
    fd.append('csrf','<?= csrf_token() ?>');
    fd.append('_action','qa_save');
    fd.append('pertanyaan', lastQ);
    fd.append('jawaban', lastA);
    try {
      var r = await fetch('/islami.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ lastSavedKey = key; qaStat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Tersimpan (#'+j.id+'). <a href="/islami.php#qaSavedBox">Lihat daftar</a>'; }
      else qaStat.textContent = 'Gagal menyimpan.';
    } catch(e){ qaStat.textContent = 'Error: '+e.message; }
    btnSimpan.disabled = false;
  });

  // Hapus item tersimpan
  document.querySelectorAll('.qa-del-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      if (!confirm('Hapus Q&A ini?')) return;
      var id = b.dataset.id;
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('_action','qa_delete');
      fd.append('id', id);
      var r = await fetch('/islami.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ var el = document.querySelector('[data-qa-id="'+id+'"]'); if(el) el.remove(); }
    });
  });
})();
</script>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div>
  <?php unset($_SESSION['flash']); endif; ?>

<!-- Hero sapaan sudah dipindah ke paling atas (lihat di bawah include header). -->

<!-- KOMPAS KIBLAT dihapus sesuai revisi 6 Juni 2026 -->

<div class="row g-3 mb-3">
  <!-- Revisi 27 Juni 2026 #8 — Kalender Hijriyah dipindah ke atas Al-Qur'an Digital -->
  <div class="col-md-3"><a href="/kalender_hijriyah.php" class="card text-decoration-none h-100 border-success"><div class="card-body text-center"><i class="bi bi-calendar3 fs-2 text-success"></i><div class="fw-semibold mt-1">Kalender Hijriyah</div></div></a></div>
  <div class="col-md-3"><a href="/quran.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book fs-2 text-success"></i><div class="fw-semibold mt-1">Al-Qur'an Digital</div></div></a></div>

  <!-- R17 #3-#6: setelah Al-Qur'an Digital → Ensiklopedia Hadist, Belajar Tajwid, Sejarah Nabi & Rasul, lalu Tata Cara Wudhu/Shalat/Rawatib/Duha-Tahajud -->
  <div class="col-md-3"><a href="/hadist.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book-half fs-2 text-success"></i><div class="fw-semibold mt-1">Ensiklopedia Hadist</div></div></a></div>
  <div class="col-md-3"><a href="/tajwid.php" class="card text-decoration-none h-100 border-success"><div class="card-body text-center"><i class="bi bi-mic-fill fs-2 text-success"></i><div class="fw-semibold mt-1">Belajar Tajwid</div><div class="small text-muted">Hukum nun sukun, mim, mad, qalqalah</div></div></a></div>
  <div class="col-md-3"><a href="/sejarah_nabi.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-book fs-2 text-warning"></i><div class="fw-semibold mt-1">Sejarah Nabi &amp; Rasul</div><div class="small text-muted">25 Nabi &amp; Rasul</div></div></a></div>
  <div class="col-md-3"><a href="/wudhu_tatacara.php" class="card text-decoration-none h-100 border-info"><div class="card-body text-center"><i class="bi bi-droplet-fill fs-2 text-info"></i><div class="fw-semibold mt-1">Tata Cara Wudhu</div><div class="small text-muted">Bacaan &amp; tuntunan</div></div></a></div>
  <div class="col-md-3"><a href="/shalat_tatacara.php" class="card text-decoration-none h-100 border-primary"><div class="card-body text-center"><i class="bi bi-person-arms-up fs-2 text-primary"></i><div class="fw-semibold mt-1">Tata Cara Shalat</div><div class="small text-muted">Bacaan &amp; tuntunan</div></div></a></div>
  <div class="col-md-3"><a href="/shalat_rawatib.php" class="card text-decoration-none h-100 border-warning"><div class="card-body text-center"><i class="bi bi-stars fs-2 text-warning"></i><div class="fw-semibold mt-1">Shalat Sunnah Rawatib</div><div class="small text-muted">12 rakaat mengiringi fardhu</div></div></a></div>
  <div class="col-md-3"><a href="/shalat_sunnah.php" class="card text-decoration-none h-100 border-info"><div class="card-body text-center"><i class="bi bi-sun fs-2 text-info"></i><div class="fw-semibold mt-1">Shalat Duha &amp; Tahajud</div><div class="small text-muted">Sunnah penambah pahala</div></div></a></div>

  <div class="col-md-3"><a href="/jadwal_sholat.php" class="card text-decoration-none h-100 border-primary"><div class="card-body text-center"><i class="bi bi-clock-history fs-2 text-primary"></i><div class="fw-semibold mt-1">Jadwal Sholat</div><div class="small text-muted">Waktu sholat 5 waktu</div></div></a></div>
  <div class="col-md-3"><a href="/doa.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-chat-quote fs-2 text-warning"></i><div class="fw-semibold mt-1">Doa Harian</div></div></a></div>
  <div class="col-md-3"><a href="/catatan_hafalan.php" class="card text-decoration-none h-100 border-success"><div class="card-body text-center"><i class="bi bi-bookmark-heart fs-2 text-success"></i><div class="fw-semibold mt-1">Catatan Hafalan</div><div class="small text-muted">Catat &amp; pantau hafalan Qur'an / Hadist</div></div></a></div>
  <div class="col-md-3"><a href="/kajian.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-journal-bookmark fs-2 text-info"></i><div class="fw-semibold mt-1">Kajian Literatur Buku</div></div></a></div>
  <div class="col-md-3"><a href="/catatan_baca_buku.php" class="card text-decoration-none h-100 border-info"><div class="card-body text-center"><i class="bi bi-journal-check fs-2 text-info"></i><div class="fw-semibold mt-1">Catatan Baca Buku</div><div class="small text-muted">Pantau progress baca dari Kajian Literatur</div></div></a></div>

  <div class="col-md-3"><a href="/dzikir.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-brightness-high fs-2 text-info"></i><div class="fw-semibold mt-1">Dzikir Pagi & Petang</div></div></a></div>
  <div class="col-md-3"><a href="/challenge.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-trophy fs-2 text-warning"></i><div class="fw-semibold mt-1">Challenge Islami</div></div></a></div>
  <div class="col-md-3"><a href="/leaderboard_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-bar-chart-line fs-2 text-danger"></i><div class="fw-semibold mt-1">Leaderboard Amal</div></div></a></div>
  <div class="col-md-3"><a href="/statistik_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-graph-up fs-2 text-primary"></i><div class="fw-semibold mt-1">Statistik & Streak</div></div></a></div>
  <div class="col-md-3"><a href="/artikel_sunnah.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-journal-text fs-2 text-success"></i><div class="fw-semibold mt-1">Artikel Sunnah</div></div></a></div>
  <div class="col-md-3"><a href="/feed_islami.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-chat-dots fs-2 text-warning"></i><div class="fw-semibold mt-1">Feed Quote Komunitas</div></div></a></div>
  <div class="col-md-3"><a href="/doa_antar_member.php" class="card text-decoration-none h-100"><div class="card-body text-center"><i class="bi bi-heart fs-2 text-danger"></i><div class="fw-semibold mt-1">Saling Mendoakan</div></div></a></div>
  <div class="col-md-3"><a href="/rukun_islam.php" class="card text-decoration-none h-100 border-success"><div class="card-body text-center"><i class="bi bi-bricks fs-2 text-success"></i><div class="fw-semibold mt-1">Rukun Islam</div><div class="small text-muted">5 Pilar · Syarat Sah &amp; Wajib</div></div></a></div>

  <?php if (!empty($u) && ($u['role'] ?? '') === 'admin'): ?>
  <div class="col-md-3"><a href="/admin/challenge.php" class="card text-decoration-none h-100 border-warning"><div class="card-body text-center"><i class="bi bi-trophy-fill fs-2 text-warning"></i><div class="fw-semibold mt-1">Kelola Challenge Islami</div><div class="small text-muted">Admin · CRUD Challenge</div></div></a></div>
  <?php endif; ?>
</div>

<!-- Revisi 15 Juni 2026: Panel TATA CARA SHALAT, RAWATIB, DUHA/TAHAJUD, RUKUN ISLAM
     dipindah ke halaman tersendiri (lihat icon-card di grid atas). -->
<div class="row g-3">
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-header"><i class="bi bi-fire text-danger"></i> Streak & Badge Saya</div><div class="card-body">
      <?php if ($u): ?>
        <div class="display-6"><?= $streak ?> <small class="fs-6 text-muted">hari berturut-turut</small></div>
        <div class="mt-2">
          <?php foreach ($badges as $b): ?>
            <span class="badge bg-success me-1 mb-1"><i class="bi bi-award"></i> <?= htmlspecialchars(islami_badge_label($b['badge_key'])) ?></span>
          <?php endforeach; if(!$badges): ?><div class="small text-muted">Belum ada badge. Mulai dari "1 hari 1 ayat".</div><?php endif; ?>
        </div>
      <?php else: ?><div>Login untuk mencatat streak.</div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-md-5">
    <!-- Revisi 22 Juni 2026 R12 — Di mobile disembunyikan (sudah ada di atas). Di desktop tetap muncul. -->
    <div class="card shadow-sm d-none d-md-block"><div class="card-header"><i class="bi bi-hourglass-split text-success"></i> Countdown Hari Raya & Peristiwa</div><div class="card-body">
      <?php
        // $cdEvents sudah dideklarasikan di blok mobile sebelumnya.
        foreach ($cdEvents as $e): ?>
          <div class="mb-1 small"><strong class="text-<?= $e[3] ?>"><?= $e[0] ?></strong>
            <span class="text-muted">(<?= $e[1]->format('d M Y') ?>)</span>:
            <span id="<?= $e[2] ?>">…</span></div>
      <?php endforeach; ?>
    </div></div>

    <?php if ($u): ?>
    <div class="card shadow-sm mt-3"><div class="card-header"><i class="bi bi-sliders text-primary"></i> Preferensi</div><div class="card-body">
      <form method="post" id="prefForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="save_pref">
        <div class="mb-2"><label class="small">Negara</label>
          <select class="form-select form-select-sm" name="negara" id="prefNegara">
            <?php foreach (array_keys($CITIES_BY_COUNTRY) as $neg): ?>
              <option value="<?= htmlspecialchars($neg) ?>" <?= ($pref['negara']===$neg)?'selected':'' ?>><?= htmlspecialchars($neg) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="small">Kota (autocomplete sesuai negara)</label>
          <input class="form-control form-control-sm" name="kota" id="prefKota" list="kotaList" value="<?= htmlspecialchars($pref['kota']) ?>" autocomplete="off">
          <datalist id="kotaList">
            <?php foreach (($CITIES_BY_COUNTRY[$pref['negara']] ?? []) as $kt): ?>
              <option value="<?= htmlspecialchars($kt) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="modeT" name="mode_tenang" <?= !empty($pref['mode_tenang'])?'checked':'' ?>>
          <label for="modeT" class="form-check-label small">Aktifkan Mode Tenang saat adzan</label></div>
        <button class="btn btn-sm btn-primary mt-2">Simpan</button>
      </form>
      <script>
        (function(){
          var citiesByCountry = <?= json_encode($CITIES_BY_COUNTRY, JSON_UNESCAPED_UNICODE) ?>;
          var sel = document.getElementById('prefNegara');
          var dl  = document.getElementById('kotaList');
          var kt  = document.getElementById('prefKota');
          if (!sel || !dl) return;
          function refresh(){
            var list = citiesByCountry[sel.value] || [];
            dl.innerHTML = list.map(function(c){ return '<option value="'+c.replace(/"/g,'&quot;')+'"></option>'; }).join('');
            if (list.length && list.indexOf(kt.value) === -1) kt.value = list[0];
          }
          sel.addEventListener('change', refresh);
        })();
      </script>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php
/* ===== Revisi R22 — Monitoring Tahajud & Duha Bulanan ===== */
$ssBulan = isset($_GET['ssbulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['ssbulan']) ? $_GET['ssbulan'] : date('Y-m');
$ssStart = $ssBulan.'-01';
$ssEnd   = date('Y-m-t', strtotime($ssStart));
$ssLogs  = $u ? db_all("SELECT jenis,tanggal,rakaat,catatan FROM shalat_sunnah_log
                        WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3",
                       [(int)$u['id'],$ssStart,$ssEnd]) : [];
$ssMap = ['tahajud'=>[], 'duha'=>[]];
foreach ($ssLogs as $r) { $ssMap[$r['jenis']][$r['tanggal']] = $r; }
$ssCount = ['tahajud'=>count($ssMap['tahajud']), 'duha'=>count($ssMap['duha'])];
$ssDays  = (int)date('t', strtotime($ssStart));
$ssToday = date('Y-m-d');
$ssPrev  = date('Y-m', strtotime($ssStart.' -1 month'));
$ssNext  = date('Y-m', strtotime($ssStart.' +1 month'));
?>
<div class="card shadow-sm mb-3 border-info" id="ssMonitor">
  <div class="card-header bg-info-subtle text-info-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-calendar2-check-fill"></i> <strong>Monitoring Tahajud &amp; Duha Bulanan</strong></span>
    <form method="get" class="d-flex align-items-center gap-1">
      <a class="btn btn-sm btn-outline-secondary" href="?ssbulan=<?= $ssPrev ?>#ssMonitor">&laquo;</a>
      <input type="month" name="ssbulan" value="<?= htmlspecialchars($ssBulan) ?>" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto">
      <a class="btn btn-sm btn-outline-secondary" href="?ssbulan=<?= $ssNext ?>#ssMonitor">&raquo;</a>
    </form>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-2">
      <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
          <div class="small text-muted">Tahajud bulan ini</div>
          <div class="h4 mb-0 text-primary"><?= $ssCount['tahajud'] ?> / <?= $ssDays ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="border rounded p-2 text-center">
          <div class="small text-muted">Duha bulan ini</div>
          <div class="h4 mb-0 text-warning"><?= $ssCount['duha'] ?> / <?= $ssDays ?></div>
        </div>
      </div>
      <div class="col-12 col-md-6 small text-muted align-self-center">
        Klik kotak tanggal untuk menandai/menghapus catatan. Klik 🌙 untuk <b>Tahajud</b>, ☀️ untuk <b>Duha</b>.
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle text-center mb-0 ssmonth-table">
        <thead class="table-light"><tr><th>Tgl</th><th>Tahajud 🌙</th><th>Duha ☀️</th><th class="d-none d-md-table-cell">Catatan</th></tr></thead>
        <tbody>
        <?php for ($d=1; $d<=$ssDays; $d++):
            $tgl = sprintf('%s-%02d', $ssBulan, $d);
            $tj  = $ssMap['tahajud'][$tgl] ?? null;
            $dh  = $ssMap['duha'][$tgl] ?? null;
            $isToday = $tgl === $ssToday;
            $isFuture= $tgl > $ssToday;
        ?>
          <tr class="<?= $isToday?'table-warning':'' ?>">
            <td class="fw-semibold"><?= $d ?><?php if($isToday): ?> <small class="badge bg-warning text-dark">Hari ini</small><?php endif; ?></td>
            <td>
              <button type="button" class="btn btn-sm <?= $tj?'btn-primary':'btn-outline-secondary' ?> ss-btn"
                      data-jenis="tahajud" data-tgl="<?= $tgl ?>" <?= $isFuture?'disabled':'' ?>
                      title="<?= $tj?'Sudah dicatat (klik untuk hapus)':'Catat shalat Tahajud' ?>">
                <?= $tj ? '<i class="bi bi-check-circle-fill"></i> '.((int)$tj['rakaat']).' rkt' : '<i class="bi bi-moon-stars"></i>' ?>
              </button>
            </td>
            <td>
              <button type="button" class="btn btn-sm <?= $dh?'btn-warning text-dark':'btn-outline-secondary' ?> ss-btn"
                      data-jenis="duha" data-tgl="<?= $tgl ?>" <?= $isFuture?'disabled':'' ?>
                      title="<?= $dh?'Sudah dicatat (klik untuk hapus)':'Catat shalat Duha' ?>">
                <?= $dh ? '<i class="bi bi-check-circle-fill"></i> '.((int)$dh['rakaat']).' rkt' : '<i class="bi bi-sun"></i>' ?>
              </button>
            </td>
            <td class="d-none d-md-table-cell small text-muted text-start">
              <?php if($tj && !empty($tj['catatan'])): ?>🌙 <?= htmlspecialchars($tj['catatan']) ?><br><?php endif; ?>
              <?php if($dh && !empty($dh['catatan'])): ?>☀️ <?= htmlspecialchars($dh['catatan']) ?><?php endif; ?>
            </td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function(){
  document.querySelectorAll('.ss-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      var rakaat = prompt('Berapa rakaat ' + b.dataset.jenis + ' tanggal ' + b.dataset.tgl + '? (kosongkan untuk hapus catatan)', '2');
      if (rakaat === null) return;
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('_action','ssunnah_toggle');
      fd.append('jenis', b.dataset.jenis);
      fd.append('tanggal', b.dataset.tgl);
      fd.append('rakaat', rakaat || '2');
      b.disabled = true;
      try {
        var r = await fetch('/islami.php', {method:'POST', body:fd, credentials:'same-origin'});
        var j = await r.json();
        if (j.ok) location.reload();
        else alert('Gagal: '+(j.err||'?'));
      } catch(e){ alert('Error: '+e.message); }
      b.disabled = false;
    });
  });
})();
</script>

<script src="/assets/js/islami.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (!window.islamiCountdown) return;
  <?php foreach ($cdEvents as $e): ?>
    window.islamiCountdown('<?= $e[2] ?>',      '<?= $e[1]->format('Y-m-d') ?>T00:00:00');
    window.islamiCountdown('<?= $e[2] ?>_m',    '<?= $e[1]->format('Y-m-d') ?>T00:00:00'); // Revisi R12 — copy mobile
  <?php endforeach; ?>
});
</script>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    if (window.islamiCountdown) {
      window.islamiCountdown('cdRamadhan', '<?= $ramadhan->format('Y-m-d') ?>T00:00:00');
      window.islamiCountdown('cdIedAdha', '<?= $iedAdha->format('Y-m-d') ?>T00:00:00');
    }
  });
</script>



<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
require __DIR__.'/includes/paket_helpers.php'; // R22 — gate PRO
send_security_headers(); enforce_session_timeout();
require_login();
$u = current_user();
$pageTitle = 'Paket Bugar Kalistenik';

// Revisi R22 — Paket Bugar Kalistenik khusus paket PRO / KOMUNITAS
paket_require_or_lock('pro', $u, 'Paket Bugar Kalistenik',
    'Program latihan kalistenik terstruktur tersedia untuk paket PRO atau Komunitas.');
$pageSkeleton = 'grid'; // Skeleton sesuai data: grid gerakan

// Revisi 6 Juni 2026:
//  - Setiap gerakan kini punya video YouTube embed yang langsung play di modal.
//  - Tombol "Cari Video Tutorial" dihapus.
$GERAKAN = [
  'push_up'    => [
    'nama'=>'Push-up','icon'=>'bi-arrow-down-up','target'=>'Dada, trisep, bahu',
    'tips'=>'Tubuh lurus dari kepala ke tumit, siku ±45°.',
    'img'=>'assets/img/kalistenik/push_up.jpg',
    'langkah'=>[
      'Posisi plank tinggi: tangan selebar bahu, kaki rapat, tubuh lurus.',
      'Turunkan dada hingga ±5 cm dari lantai, siku ±45° dari tubuh.',
      'Dorong kembali ke atas dengan kuat tanpa mengunci siku.',
      'Jaga inti perut & glutes aktif agar pinggul tidak turun.',
    ],
    'yt_id'=>'bTJIkQRsmaE',
  ],
  'pull_up'    => [
    'nama'=>'Pull-up','icon'=>'bi-arrow-up','target'=>'Punggung, bisep',
    'tips'=>'Gantung penuh, tarik hingga dagu melewati bar.',
    'img'=>'assets/img/kalistenik/pull_up.jpg',
    'langkah'=>[
      'Gantung pada bar, telapak menghadap ke depan, lebar bahu.',
      'Tarik bahu ke bawah-belakang, lalu tarik tubuh hingga dagu melewati bar.',
      'Turunkan secara terkontrol hingga lengan lurus penuh.',
      'Hindari mengayun; gunakan band elastis jika belum kuat.',
    ],
    'yt_id'=>'DC42x5z0aeE',
  ],
  'squat'      => [
    'nama'=>'Squat','icon'=>'bi-arrows-vertical','target'=>'Paha, bokong',
    'tips'=>'Lutut sejajar ujung kaki, punggung netral.',
    'img'=>'assets/img/kalistenik/squat.jpg',
    'langkah'=>[
      'Berdiri kaki selebar bahu, ujung kaki sedikit keluar.',
      'Tekuk lutut & pinggul bersamaan seperti hendak duduk.',
      'Turun sampai paha sejajar lantai (atau senyaman mungkin).',
      'Dorong kembali berdiri dengan tumit menapak penuh.',
    ],
    'yt_id'=>'aCQCvOfkXQY',
  ],
  'lunge'      => [
    'nama'=>'Lunge','icon'=>'bi-shoe-prints','target'=>'Paha, glutes',
    'tips'=>'Langkah panjang, lutut depan 90°.',
    'img'=>'assets/img/kalistenik/lunge.jpg',
    'langkah'=>[
      'Langkahkan satu kaki ke depan cukup panjang.',
      'Turunkan pinggul sampai kedua lutut ±90°.',
      'Lutut depan tegak lurus pergelangan kaki, tidak melewati ujung jari.',
      'Dorong tumit depan untuk kembali berdiri. Ulangi sisi lain.',
    ],
    'yt_id'=>'1DDTUeuQ9Eo',
  ],
  'plank'      => [
    'nama'=>'Plank','icon'=>'bi-dash-lg','target'=>'Core (perut)',
    'tips'=>'Tahan posisi lurus, jangan turunkan pinggul.',
    'img'=>'assets/img/kalistenik/plank.jpg',
    'langkah'=>[
      'Tumpuan siku tepat di bawah bahu, lengan bawah menempel matras.',
      'Tubuh lurus satu garis dari kepala–pinggul–tumit.',
      'Kencangkan perut & glutes, tarik pusar ke arah tulang belakang.',
      'Tahan sambil bernapas normal. Hindari pinggul turun atau naik.',
    ],
    'yt_id'=>'eS2NxmIjSmU',
  ],
  'dip'        => [
    'nama'=>'Dip','icon'=>'bi-arrow-down','target'=>'Trisep, dada bawah',
    'tips'=>'Pakai 2 kursi/bar paralel; turunkan badan terkontrol.',
    'img'=>'assets/img/kalistenik/dip.jpg',
    'langkah'=>[
      'Letakkan tangan di tepi kursi/bench di belakang Anda.',
      'Luruskan lengan, kaki ditekuk (atau lurus untuk versi sulit).',
      'Tekuk siku ke belakang sampai ±90°, jangan turun terlalu dalam.',
      'Dorong kembali ke atas tanpa mengunci siku.',
    ],
    'yt_id'=>'gYojD_1WiBI',
  ],
  'burpee'     => [
    'nama'=>'Burpee','icon'=>'bi-lightning','target'=>'Full body + kardio',
    'tips'=>'Squat → plank → push-up → lompat.',
    'img'=>'assets/img/kalistenik/burpee.jpg',
    'langkah'=>[
      'Berdiri tegak, turun ke posisi squat dan letakkan tangan di lantai.',
      'Lompat kedua kaki ke belakang menjadi posisi plank.',
      'Lakukan satu push-up (opsional untuk pemula).',
      'Lompat kedua kaki kembali ke tangan, lalu lompat ke atas dengan tangan terangkat.',
    ],
    'yt_id'=>'Uo_OPlN2YHA',
  ],
  'mountain'   => [
    'nama'=>'Mountain Climber','icon'=>'bi-speedometer','target'=>'Core + kardio',
    'tips'=>'Posisi plank, lari di tempat dengan lutut ke dada.',
    'img'=>'assets/img/kalistenik/mountain.jpg',
    'langkah'=>[
      'Mulai dari posisi plank tinggi dengan tubuh lurus.',
      'Tarik lutut kanan ke arah dada dengan cepat, lalu kembalikan.',
      'Bergantian dengan lutut kiri seperti berlari di tempat.',
      'Jaga pinggul tetap rendah dan inti aktif.',
    ],
    'yt_id'=>'boBoWvFdjnI',
  ],
  'jumping'    => [
    'nama'=>'Jumping Jack','icon'=>'bi-arrows-fullscreen','target'=>'Pemanasan, kardio',
    'tips'=>'Lompat sambil buka-tutup kaki dan tangan.',
    'img'=>'assets/img/kalistenik/jumping.jpg',
    'langkah'=>[
      'Berdiri tegak, tangan di samping, kaki rapat.',
      'Lompat sambil membuka kaki selebar bahu dan angkat tangan ke atas.',
      'Lompat kembali ke posisi awal.',
      'Lakukan dengan irama stabil, mendarat dengan lembut.',
    ],
    'yt_id'=>'gPIib9cgmU8',
  ],
  'leg_raise'  => [
    'nama'=>'Leg Raise','icon'=>'bi-arrow-up-short','target'=>'Perut bawah',
    'tips'=>'Berbaring, angkat kaki lurus 90°, turunkan perlahan.',
    'img'=>'assets/img/kalistenik/leg_raise.jpg',
    'langkah'=>[
      'Berbaring telentang, kaki lurus, tangan di samping atau di bawah pinggul.',
      'Angkat kedua kaki lurus hingga 90° terhadap lantai.',
      'Turunkan perlahan tanpa menyentuh lantai (jaga tegangan perut).',
      'Hindari mengangkat punggung bawah; kencangkan inti.',
    ],
    'yt_id'=>'IrxfZzRDjwk',
  ],
];

// Paket per level
$PAKET = [
  'pemula' => [
    'label' => 'Pemula','durasi'=> '20 menit · 3x / minggu',
    'desc'  => 'Cocok untuk yang baru mulai berolahraga atau setelah lama vakum.','badge' => 'success',
    'sesi'  => [
      ['gerakan'=>'jumping',  'set'=>2, 'rep'=>'30 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'squat',    'set'=>3, 'rep'=>'10 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'push_up',  'set'=>3, 'rep'=>'5–8 reps (boleh lutut menempel)', 'rest'=>'60 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'20 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'lunge',    'set'=>2, 'rep'=>'8 reps / kaki','rest'=>'45 detik'],
    ],
  ],
  'menengah' => [
    'label' => 'Menengah','durasi'=> '30 menit · 4x / minggu',
    'desc'  => 'Sudah terbiasa olahraga ringan; siap meningkatkan volume dan intensitas.','badge' => 'warning',
    'sesi'  => [
      ['gerakan'=>'jumping',  'set'=>2, 'rep'=>'45 detik',     'rest'=>'20 detik'],
      ['gerakan'=>'push_up',  'set'=>4, 'rep'=>'12 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'squat',    'set'=>4, 'rep'=>'20 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'lunge',    'set'=>3, 'rep'=>'12 reps / kaki','rest'=>'45 detik'],
      ['gerakan'=>'dip',      'set'=>3, 'rep'=>'8 reps',       'rest'=>'45 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'45 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'mountain', 'set'=>3, 'rep'=>'30 detik',     'rest'=>'30 detik'],
    ],
  ],
  'lanjutan' => [
    'label' => 'Lanjutan','durasi'=> '45 menit · 5x / minggu',
    'desc'  => 'Untuk yang sudah rutin berlatih dan ingin mengejar kekuatan + daya tahan.','badge' => 'danger',
    'sesi'  => [
      ['gerakan'=>'burpee',   'set'=>3, 'rep'=>'15 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'pull_up',  'set'=>4, 'rep'=>'6–8 reps',     'rest'=>'90 detik'],
      ['gerakan'=>'push_up',  'set'=>4, 'rep'=>'20 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'squat',    'set'=>4, 'rep'=>'25 reps (boleh jump squat)', 'rest'=>'60 detik'],
      ['gerakan'=>'dip',      'set'=>4, 'rep'=>'12 reps',      'rest'=>'60 detik'],
      ['gerakan'=>'leg_raise','set'=>3, 'rep'=>'15 reps',      'rest'=>'45 detik'],
      ['gerakan'=>'plank',    'set'=>3, 'rep'=>'60 detik',     'rest'=>'30 detik'],
      ['gerakan'=>'mountain', 'set'=>3, 'rep'=>'45 detik',     'rest'=>'30 detik'],
    ],
  ],
];

$level = $_GET['lvl'] ?? 'pemula';
if (!isset($PAKET[$level])) $level = 'pemula';
$cur = $PAKET[$level];

/* =========================================================================
 * Revisi Juli 2026 — Monitoring Paket Bugar Kalistenik.
 * Catat sesi latihan yang sudah dilakukan per hari, per level, dengan
 * catatan singkat. Rekap 30 hari terakhir ditampilkan di bawah paket.
 *
 * SQL tambahan (PostgreSQL):
 *   CREATE TABLE IF NOT EXISTS kalistenik_log (
 *     user_id  INT  NOT NULL,
 *     tanggal  DATE NOT NULL,
 *     level    VARCHAR(20) NOT NULL,
 *     catatan  TEXT,
 *     created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 *     PRIMARY KEY (user_id, tanggal, level)
 *   );
 * ========================================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action'] ?? '')==='kal_log_toggle') {
    csrf_check();
    header('Content-Type: application/json');
    $lv  = in_array($_POST['level'] ?? '', array_keys($PAKET), true) ? $_POST['level'] : 'pemula';
    $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['tanggal'] ?? '') ? $_POST['tanggal'] : date('Y-m-d');
    $cat = mb_substr(trim($_POST['catatan'] ?? ''), 0, 300);
    try {
        $has = (int)db_val("SELECT COUNT(*) FROM kalistenik_log WHERE user_id=$1 AND tanggal=$2 AND level=$3",
            [(int)$u['id'], $tgl, $lv]);
        if ($has) {
            db_exec("DELETE FROM kalistenik_log WHERE user_id=$1 AND tanggal=$2 AND level=$3",
                [(int)$u['id'], $tgl, $lv]);
            echo json_encode(['ok'=>true,'state'=>'off']); exit;
        }
        db_exec("INSERT INTO kalistenik_log(user_id,tanggal,level,catatan) VALUES($1,$2,$3,$4)",
            [(int)$u['id'], $tgl, $lv, $cat ?: null]);
        echo json_encode(['ok'=>true,'state'=>'on']); exit;
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'err'=>'db']); exit;
    }
}

$kalStart = date('Y-m-d', strtotime('-29 days'));
$kalToday = date('Y-m-d');
$kalLogs  = [];
try {
    $kalLogs = db_all("SELECT tanggal::text AS tanggal, level, catatan
                       FROM kalistenik_log
                       WHERE user_id=$1 AND tanggal BETWEEN $2 AND $3
                       ORDER BY tanggal DESC, level",
                      [(int)$u['id'], $kalStart, $kalToday]);
} catch (Throwable $e) { $kalLogs = []; }
$kalDoneToday = [];
foreach ($kalLogs as $r) {
    if ($r['tanggal'] === $kalToday) $kalDoneToday[$r['level']] = $r['catatan'];
}
$kalCountLevel = ['pemula'=>0,'menengah'=>0,'lanjutan'=>0];
foreach ($kalLogs as $r) { if(isset($kalCountLevel[$r['level']])) $kalCountLevel[$r['level']]++; }

include __DIR__.'/includes/header.php'; ?>

<link rel="stylesheet" href="assets/css/sport-islami.css">

<div class="hero-sport-islami mb-3" style="background-image:linear-gradient(135deg, rgba(15,98,72,.78), rgba(7,59,76,.78)), url('assets/img/kalistenik/hero.jpg');background-size:cover;background-position:center;">
  <div class="hero-overlay">
    <h1 class="h4 mb-1"><i class="bi bi-person-arms-up"></i> Paket Bugar Kalistenik</h1>
    <p class="small mb-0 opacity-85">Latihan beban tubuh sendiri — sehat lahir, kuat batin.</p>
  </div>
</div>

<?php ip_card_open('Paket Bugar Kalistenik', 'bi-person-arms-up'); ?>

<p class="text-muted small mb-3">
  Latihan <strong>kalistenik</strong> (beban tubuh sendiri) tanpa alat. Pilih paket sesuai level kebugaran Anda.
  Mulailah dengan pemanasan 3–5 menit dan akhiri dengan peregangan.
</p>

<ul class="nav nav-pills mb-3 gap-2 flex-wrap">
  <?php foreach($PAKET as $k=>$p): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k===$level?'active':'' ?>" href="?lvl=<?= $k ?>">
        <?= htmlspecialchars($p['label']) ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="card shadow-sm mb-4 border-<?= $cur['badge'] ?>">
  <div class="card-header bg-<?= $cur['badge'] ?> text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-trophy"></i> <strong>Paket <?= htmlspecialchars($cur['label']) ?></strong></span>
    <small class="opacity-75"><?= htmlspecialchars($cur['durasi']) ?></small>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3"><?= htmlspecialchars($cur['desc']) ?></p>
    <!-- Revisi #2 — tabel di-scroll horizontal agar tidak nabrak di layar sempit -->
    <div class="table-responsive" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table class="table table-sm align-middle" style="min-width:760px;">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Gerakan</th><th>Target</th><th>Set</th><th>Repetisi/Durasi</th><th>Istirahat</th>
            <th style="min-width:230px">Stopwatch (sinkron set)</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($cur['sesi'] as $i=>$s):
          $gk = $s['gerakan'];
          $g = $GERAKAN[$gk] ?? ['nama'=>$gk,'icon'=>'bi-dash','target'=>'-','tips'=>'','img'=>'','langkah'=>[],'yt'=>''];
          // Revisi #6 — durasi kerja: pakai angka "detik" bila ada, jika repetisi (reps) pakai default 40 dtk.
          $workSec = preg_match('/(\d+)\s*detik/i', $s['rep'], $mw) ? (int)$mw[1] : 40;
          $restSec = preg_match('/(\d+)\s*detik/i', $s['rest'], $mr) ? (int)$mr[1] : 30;
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td class="text-nowrap"><i class="bi <?= $g['icon'] ?> text-<?= $cur['badge'] ?>"></i> <strong><?= htmlspecialchars($g['nama']) ?></strong></td>
            <td class="small text-muted"><?= htmlspecialchars($g['target']) ?></td>
            <td><?= (int)$s['set'] ?></td>
            <td><?= htmlspecialchars($s['rep']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($s['rest']) ?></td>
            <td>
              <div class="kal-sw" data-set="<?= (int)$s['set'] ?>" data-work="<?= $workSec ?>" data-rest="<?= $restSec ?>">
                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-sm btn-<?= $cur['badge'] ?> kal-sw-start"><i class="bi bi-play-fill"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary kal-sw-reset"><i class="bi bi-arrow-counterclockwise"></i></button>
                  <span class="kal-sw-phase badge bg-secondary">Siap</span>
                </div>
                <div class="small mt-1">
                  Set <span class="kal-sw-cur">1</span>/<span class="kal-sw-tot"><?= (int)$s['set'] ?></span>
                  · <span class="kal-sw-time fw-bold">--:--</span>
                </div>
              </div>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-<?= $cur['badge'] ?> btn-lihat-gerakan" data-key="<?= htmlspecialchars($gk) ?>">
                <i class="bi bi-eye"></i> Lihat Gerakan
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Revisi #6 — Stopwatch repetisi & istirahat, otomatis berpindah tiap set -->
    <script>
    (function(){
      function fmt(t){ var m=Math.floor(t/60), s=t%60; return (m<10?'0':'')+m+':'+(s<10?'0':'')+s; }
      document.querySelectorAll('.kal-sw').forEach(function(box){
        var totalSet=+box.dataset.set||1, work=+box.dataset.work||40, rest=+box.dataset.rest||30;
        var startBtn=box.querySelector('.kal-sw-start'), resetBtn=box.querySelector('.kal-sw-reset');
        var phaseEl=box.querySelector('.kal-sw-phase'), curEl=box.querySelector('.kal-sw-cur'), timeEl=box.querySelector('.kal-sw-time');
        var timer=null, running=false, curSet=1, phase='work', left=work;
        function paint(){
          curEl.textContent=curSet; timeEl.textContent=fmt(left);
          if(phase==='work'){ phaseEl.textContent='Kerja'; phaseEl.className='kal-sw-phase badge bg-primary'; }
          else if(phase==='rest'){ phaseEl.textContent='Istirahat'; phaseEl.className='kal-sw-phase badge bg-warning text-dark'; }
          else if(phase==='done'){ phaseEl.textContent='Selesai'; phaseEl.className='kal-sw-phase badge bg-success'; }
          else { phaseEl.textContent='Siap'; phaseEl.className='kal-sw-phase badge bg-secondary'; }
        }
        function beep(){ try{ var c=new (window.AudioContext||window.webkitAudioContext)(); var o=c.createOscillator(); o.connect(c.destination); o.frequency.value=880; o.start(); setTimeout(function(){o.stop();c.close();},180);}catch(e){} }
        function tick(){
          left--;
          if(left<=0){
            beep();
            if(phase==='work'){
              if(curSet>=totalSet){ phase='done'; running=false; clearInterval(timer); startBtn.innerHTML='<i class="bi bi-play-fill"></i>'; paint(); return; }
              phase='rest'; left=rest;
            } else {
              curSet++; phase='work'; left=work;
            }
          }
          paint();
        }
        function start(){
          if(running){ running=false; clearInterval(timer); startBtn.innerHTML='<i class="bi bi-play-fill"></i>'; return; }
          if(phase==='done'){ reset(); }
          running=true; startBtn.innerHTML='<i class="bi bi-pause-fill"></i>';
          timer=setInterval(tick,1000);
        }
        function reset(){ running=false; clearInterval(timer); curSet=1; phase='work'; left=work; startBtn.innerHTML='<i class="bi bi-play-fill"></i>'; paint(); }
        startBtn.addEventListener('click',start);
        resetBtn.addEventListener('click',reset);
        left=work; phase='ready'; paint(); phase='work';
      });
    })();
    </script>
  </div>
</div>

<!-- Revisi #5 — Pencarian Video (spoiler) dipindah ke atas Monitoring Latihan -->
<div class="mb-3">
  <button class="btn btn-outline-success w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#spoilerYtSearch" aria-expanded="false">
    <span><i class="bi bi-youtube"></i> <strong>Pencarian Video Olahraga / Kalistenik</strong></span>
    <i class="bi bi-chevron-down"></i>
  </button>
  <div class="collapse mt-2" id="spoilerYtSearch">
<!-- Revisi 22 Juni 2026 R7 — Kolom pencarian video YouTube khusus olahraga.
     Server (api_yt_search.php?cat=olahraga) menyaring query agar tetap di topik
     olahraga berdasarkan tabel search_keywords (di-CRUD oleh admin di
     /admin/keywords.php — menu "Pengaturan Lainnya"). -->
<div class="card shadow-sm mb-3 border-success kal-yt-box">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <i class="bi bi-youtube"></i> <strong>Pencarian Video Olahraga / Kalistenik</strong>
    <small class="text-muted ms-2">Hasil di-filter agar relevan dengan olahraga</small>
  </div>
  <div class="card-body">
    <div class="input-group input-group-sm mb-2">
      <input type="text" class="form-control kal-yt-q" placeholder="Contoh: pertandingan badminton, tutorial push up, match futsal">
      <button type="button" class="btn btn-success kal-yt-btn"><i class="bi bi-search"></i> Cari &amp; Putar</button>
    </div>
    <div class="kal-yt-result small text-muted">Ketik kata kunci lalu klik <b>Cari &amp; Putar</b>. Kata kunci non-olahraga otomatis dikecualikan oleh sistem.</div>
  </div>
</div>
<script>
(function(){
  var box = document.querySelector('.kal-yt-box'); if (!box) return;
  var btn = box.querySelector('.kal-yt-btn'), inp = box.querySelector('.kal-yt-q'), out = box.querySelector('.kal-yt-result');
  function esc(s){ return String(s).replace(/[<>&"']/g,function(c){return ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'})[c];});}
  async function doSearch(){
    var q = (inp.value||'').trim(); if (!q) return;
    out.innerHTML = '<div class="small text-muted py-2"><span class="spinner-border spinner-border-sm"></span> Mencari video di YouTube…</div>';
    btn.disabled = true; var old = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
      var r = await fetch('/api_yt_search.php?cat=olahraga&q='+encodeURIComponent(q), {credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok) throw new Error(j.err || 'tidak ada hasil');
      var ids = (j.ids && j.ids.length) ? j.ids : (j.video ? [j.video] : []);
      if (!ids.length) throw new Error('tidak ada hasil');
      ids = ids.slice(0,5);
      var html = '<div class="small text-muted mb-2">Menampilkan <b>'+ids.length+'</b> video teratas untuk <b>'+esc(q)+'</b> (kategori: olahraga):</div><div class="row g-2">';
      ids.forEach(function(vid,i){
        html += '<div class="col-12 col-md-6"><div class="ratio ratio-16x9 rounded overflow-hidden border">'+
          '<iframe loading="lazy" allowfullscreen src="https://www.youtube-nocookie.com/embed/'+encodeURIComponent(vid)+'?rel=0" '+
          'allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>'+
          '</div><div class="small text-muted mt-1">#'+(i+1)+'</div></div>';
      });
      html += '</div>';
      out.innerHTML = html;
    } catch(e){
      out.innerHTML = '<div class="small text-danger py-2"><i class="bi bi-exclamation-triangle"></i> Gagal mencari: '+esc(e.message||String(e))+'.</div>';
    } finally { btn.disabled=false; btn.innerHTML=old; }
  }
  btn.addEventListener('click', doSearch);
  inp.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); doSearch(); }});
})();
</script>



  </div>
</div>

<!-- Revisi Juli 2026 — Monitoring Latihan Kalistenik -->
<div class="card shadow-sm mb-3 border-success" id="kalMonitor">
  <div class="card-header bg-success-subtle text-success-emphasis d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-clipboard2-check-fill"></i> <strong>Monitoring Latihan (30 Hari Terakhir)</strong></span>
    <small class="text-muted">Tandai sesi paket yang sudah Anda selesaikan hari ini.</small>
  </div>
  <div class="card-body">
    <!-- Revisi #7 — keterangan frekuensi paket per hari -->
    <div class="alert alert-info small d-flex align-items-start gap-2 mb-3">
      <i class="bi bi-info-circle-fill mt-1"></i>
      <div>
        <strong>Keterangan frekuensi:</strong> Dalam 1 hari, cukup lakukan paket ini <strong>1&times; (satu kali)</strong> &mdash; idealnya di pagi atau sore hari.
        Frekuensi mingguan yang dianjurkan mengikuti level paket:
        <span class="badge bg-success">Pemula 3&times;/minggu</span>
        <span class="badge bg-warning text-dark">Menengah 4&times;/minggu</span>
        <span class="badge bg-danger">Lanjutan 5&times;/minggu</span>.
        Sisakan minimal 1 hari istirahat penuh tiap pekan untuk pemulihan otot.
      </div>
    </div>
    <div class="row g-2 mb-3">
      <?php foreach ($PAKET as $lk => $pv):
        $doneToday = array_key_exists($lk, $kalDoneToday);
      ?>
        <div class="col-12 col-md-4">
          <div class="border rounded p-2 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold">Paket <?= htmlspecialchars($pv['label']) ?></div>
                <div class="small text-muted"><?= (int)$kalCountLevel[$lk] ?> sesi / 30 hari &middot; maks 1&times;/hari</div>
              </div>
              <button type="button"
                      class="btn btn-sm <?= $doneToday?'btn-success':'btn-outline-success' ?> kal-log-btn"
                      data-level="<?= $lk ?>" data-tgl="<?= $kalToday ?>"
                      title="<?= $doneToday?'Sudah dicatat hari ini (klik untuk hapus)':'Tandai selesai hari ini' ?>">
                <?= $doneToday ? '<i class="bi bi-check-circle-fill"></i> Selesai' : '<i class="bi bi-plus-circle"></i> Selesai hari ini' ?>
              </button>
            </div>
            <?php if ($doneToday && !empty($kalDoneToday[$lk])): ?>
              <div class="small text-muted mt-1"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($kalDoneToday[$lk]) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light" style="position:sticky;top:0;z-index:2;">
          <tr><th style="width:130px">Tanggal</th><th style="width:120px">Level</th><th>Catatan</th></tr>
        </thead>
        <tbody>
        <?php if (!$kalLogs): ?>
          <tr><td colspan="3" class="text-center text-muted small">Belum ada catatan latihan. Mulai hari ini!</td></tr>
        <?php else: foreach ($kalLogs as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><span class="badge bg-<?= $PAKET[$r['level']]['badge'] ?? 'secondary' ?>"><?= htmlspecialchars($PAKET[$r['level']]['label'] ?? $r['level']) ?></span></td>
            <td class="small"><?= htmlspecialchars($r['catatan'] ?? '') ?: '<span class="text-muted">–</span>' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function(){
  var CSRF = '<?= csrf_token() ?>';
  document.querySelectorAll('.kal-log-btn').forEach(function(b){
    b.addEventListener('click', async function(){
      var cat = '';
      if (typeof Swal !== 'undefined') {
        var r = await Swal.fire({
          title: 'Selesai Latihan ' + b.dataset.level.toUpperCase(),
          input: 'textarea',
          inputLabel: 'Catatan (opsional)',
          inputPlaceholder: 'mis. push-up 3x12 selesai, plank 45 dtk, sedikit pegal…',
          showCancelButton: true, confirmButtonText: 'Simpan',
          cancelButtonText: 'Batal', confirmButtonColor: '#198754'
        });
        if (!r.isConfirmed) return;
        cat = r.value || '';
      } else {
        cat = prompt('Catatan latihan (opsional):','') || '';
      }
      var fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('_action', 'kal_log_toggle');
      fd.append('level', b.dataset.level);
      fd.append('tanggal', b.dataset.tgl);
      fd.append('catatan', cat);
      b.disabled = true;
      try {
        var r2 = await fetch('/kalistenik.php?lvl=' + encodeURIComponent(b.dataset.level),
          { method:'POST', body:fd, credentials:'same-origin' });
        var j  = await r2.json();
        if (j.ok) location.reload();
        else alert('Gagal: ' + (j.err || '?'));
      } catch(e){ alert('Error: ' + e.message); }
      b.disabled = false;
    });
  });
})();
</script>

<!-- Panduan tiap gerakan (kartu dengan gambar) — Revisi #3 dijadikan spoiler -->
<div class="card shadow-sm mb-3">
  <div class="card-header p-0">
    <button class="btn btn-link text-decoration-none w-100 text-start d-flex justify-content-between align-items-center py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#spoilerPanduan" aria-expanded="false">
      <span><i class="bi bi-info-circle text-primary"></i> <strong>Panduan Gerakan</strong></span>
      <i class="bi bi-chevron-down"></i>
    </button>
  </div>
  <div class="collapse" id="spoilerPanduan">
  <div class="card-body">
    <div class="row g-3">
      <?php foreach($GERAKAN as $key=>$g): ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded h-100 overflow-hidden gerakan-card">
            <?php if (!empty($g['img'])): ?>
              <img src="<?= htmlspecialchars($g['img']) ?>" alt="<?= htmlspecialchars($g['nama']) ?>" loading="lazy"
                   class="w-100" style="height:160px;object-fit:cover;background:#f4f4f4;"
                   onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="p-3">
              <div class="fw-semibold mb-1"><i class="bi <?= $g['icon'] ?> text-primary"></i> <?= htmlspecialchars($g['nama']) ?></div>
              <div class="small text-muted mb-1"><i class="bi bi-bullseye"></i> <?= htmlspecialchars($g['target']) ?></div>
              <div class="small mb-2"><?= htmlspecialchars($g['tips']) ?></div>
              <button type="button" class="btn btn-sm btn-primary btn-lihat-gerakan" data-key="<?= htmlspecialchars($key) ?>">
                <i class="bi bi-play-circle"></i> Lihat Gerakan
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-lightbulb"></i>
  <strong>Tips:</strong> minum air cukup, jaga form di atas jumlah repetisi, dan istirahat 1 hari penuh tiap minggu untuk pemulihan otot.
  Hentikan latihan jika terasa nyeri tajam — konsultasikan ke dokter bila ragu.
</div>

<!-- Modal "Lihat Gerakan" -->
<div class="modal fade" id="modalGerakan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mgTitle"><i class="bi bi-person-arms-up"></i> Gerakan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <!-- Revisi 6 Juni 2026: Video YouTube langsung play di modal (gambar statis dihilangkan). -->
        <div class="ratio ratio-16x9 rounded overflow-hidden border mb-3" id="mgVideoWrap">
          <iframe id="mgVideo"
  width="100%"
  height="100%"
  src=""
  title="YouTube video player"
  frameborder="0"
  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
  referrerpolicy="strict-origin-when-cross-origin"
  allowfullscreen>
</iframe>
        </div>
        <div class="mb-2"><span class="badge bg-primary-subtle text-primary"><i class="bi bi-bullseye"></i> Target: <span id="mgTarget">-</span></span></div>
        <h6 class="mt-3">Langkah-langkah:</h6>
        <ol id="mgLangkah" class="ps-3"></ol>
        <div class="alert alert-warning small mb-0">
          <i class="bi bi-shield-exclamation"></i>
          <strong>Tips form:</strong> <span id="mgTips">-</span>
        </div>
      </div>
      <div class="modal-footer">
        <!-- Tombol "Cari Video Tutorial" dihapus sesuai revisi 6 Juni 2026. -->
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
window.__GERAKAN__ = <?= json_encode($GERAKAN, JSON_UNESCAPED_UNICODE) ?>;
window.addEventListener('load', function(){
  var el = document.getElementById('modalGerakan');
  if (!el || typeof bootstrap === 'undefined') return;
  var modal = new bootstrap.Modal(el);
  var iframe = document.getElementById('mgVideo');
  document.querySelectorAll('.btn-lihat-gerakan').forEach(function(btn){
    btn.addEventListener('click', function(){
      var key = this.getAttribute('data-key');
      var g = window.__GERAKAN__[key];
      if (!g) return;
      document.getElementById('mgTitle').innerHTML = '<i class="bi '+(g.icon||'bi-person-arms-up')+'"></i> '+ g.nama;
      document.getElementById('mgTarget').textContent = g.target || '-';
      document.getElementById('mgTips').textContent   = g.tips   || '-';
      var ol = document.getElementById('mgLangkah'); ol.innerHTML='';
      (g.langkah||[]).forEach(function(s){ var li=document.createElement('li'); li.textContent=s; ol.appendChild(li); });
      // Set video YouTube embed dengan autoplay
      if (g.yt_id) {
        iframe.src = 'https://www.youtube.com/embed/' +
             encodeURIComponent(g.yt_id);
      } else {
        iframe.src = '';
      }
      modal.show();
    });
  });
  // Hentikan video saat modal ditutup
  el.addEventListener('hidden.bs.modal', function(){ iframe.src=''; });
});
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

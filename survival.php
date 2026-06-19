<?php
/**
 * survival.php — Revisi 19 Juni 2026 Part O #3
 * Survival Mode: AI interaksi (mirip islami.php) + pengetahuan survival hutan,
 * makanan boleh/tidak, mitigasi tersesat, dan SOP jika sudah tersesat.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Survival Mode';
$u = current_user();

// Tabel penyimpanan Q&A Survival (idempotent)
try {
    db_exec("CREATE TABLE IF NOT EXISTS survival_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS survival_qa_user_idx ON survival_qa_saved(user_id, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u) {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'qa_save') {
        header('Content-Type: application/json');
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban'] ?? ''));
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000)  $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(), "INSERT INTO survival_qa_saved(user_id,pertanyaan,jawaban) VALUES($1,$2,$3) RETURNING id",
            [(int)$u['id'],$q,$j]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    } elseif ($a === 'qa_delete') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM survival_qa_saved WHERE id=$1 AND user_id=$2",[$id,(int)$u['id']]);
        echo json_encode(['ok'=>true]); exit;
    }
}

$qaSaved = $u ? db_all("SELECT id, pertanyaan, jawaban, created_at FROM survival_qa_saved WHERE user_id=$1 ORDER BY id DESC LIMIT 50", [(int)$u['id']]) : [];

include __DIR__.'/includes/header.php';
?>

<div class="hero-sport-islami mb-3" style="background:linear-gradient(135deg,#14532d,#166534);color:#fff;border-radius:14px;padding:1.25rem">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
      <span class="badge bg-light text-success mb-2"><i class="bi bi-tree-fill"></i> SURVIVAL MODE</span>
      <h1 class="h3 mb-1 fw-bold">Bertahan Hidup di Alam Liar</h1>
      <p class="small mb-0 opacity-85">Pengetahuan dasar &amp; AI Survival Coach untuk pendaki, pelari trail, dan petualang outdoor.</p>
    </div>
    <span class="badge bg-light text-dark fs-6 px-3 py-2"><i class="bi bi-telephone-fill"></i> Darurat: 115 (Basarnas) · 112</span>
  </div>
</div>

<!-- AI Survival Interaction -->
<div class="card shadow-sm mb-3 border-success">
  <div class="card-header bg-success-subtle text-success-emphasis">
    <i class="bi bi-robot"></i> <strong>AI Survival Interaction</strong> &mdash; tanya seputar bertahan hidup di hutan
  </div>
  <div class="card-body">
    <form id="survForm" class="vstack gap-2 mb-2">
      <textarea id="survInput" class="form-control" rows="3"
                placeholder="Contoh: Bagaimana cara membuat api tanpa korek di hutan basah? · Apa tanda sumber air aman diminum? · Saya tersesat di hutan, langkah pertama apa yang harus saya lakukan?" required></textarea>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-send"></i> Tanyakan</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="survClear"><i class="bi bi-eraser"></i> Bersihkan</button>
        <small class="text-muted ms-auto align-self-center">Jawaban AI bersifat panduan, BUKAN pengganti pelatihan SAR / dokter.</small>
      </div>
    </form>
    <div id="survOut" class="border rounded p-3 bg-body-tertiary small text-muted" style="min-height:80px">
      Tulis pertanyaan lalu klik <b>Tanyakan</b>. Contoh prompt: <em>"Saya tersasar saat trail running, baterai HP 10%, hari mulai gelap — apa yang harus dilakukan?"</em>
    </div>
    <div id="survActions" class="d-flex gap-2 mt-2" style="display:none !important">
      <button type="button" id="btnSimpanQAsv" class="btn btn-outline-success btn-sm"><i class="bi bi-bookmark-plus"></i> Simpan Q&amp;A ini</button>
      <span id="qaSaveStatSv" class="small text-muted align-self-center"></span>
    </div>

    <?php if ($u): ?>
    <div class="mt-3">
      <a class="small" data-bs-toggle="collapse" href="#qaSavedBoxSv" role="button" aria-expanded="false">
        <i class="bi bi-bookmark-star"></i> Tanya Jawab Tersimpan (<?= count($qaSaved) ?>)
      </a>
      <div class="collapse mt-2" id="qaSavedBoxSv">
        <?php if (!$qaSaved): ?>
          <div class="small text-muted">Belum ada Q&amp;A tersimpan. Klik <b>Simpan Q&amp;A ini</b> setelah AI menjawab.</div>
        <?php else: foreach ($qaSaved as $qa): ?>
          <div class="border rounded p-2 mb-2 small" data-qa-id="<?= (int)$qa['id'] ?>">
            <div class="d-flex justify-content-between">
              <strong class="text-success"><i class="bi bi-patch-question"></i> <?= htmlspecialchars(mb_strimwidth($qa['pertanyaan'],0,200,'…')) ?></strong>
              <button type="button" class="btn btn-sm btn-link text-danger p-0 qa-del-btn-sv" data-id="<?= (int)$qa['id'] ?>" title="Hapus"><i class="bi bi-trash"></i></button>
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

<!-- Pengetahuan Survival di Hutan -->
<div class="row g-3">
  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-success">
      <div class="card-header"><i class="bi bi-tree text-success"></i> <strong>Pengetahuan Dasar Survival di Hutan</strong></div>
      <div class="card-body">
        <p class="small text-muted mb-2">Prinsip <strong>STOP</strong> (Stop, Think, Observe, Plan) &amp; aturan <strong>3-3-3</strong>:
          3 menit tanpa udara, 3 jam tanpa naungan di cuaca ekstrem, 3 hari tanpa air, 3 minggu tanpa makanan.
          Atur prioritas dengan urutan tsb.</p>
        <ul class="small mb-2">
          <li><b>Shelter</b>: gunakan ponco/tenda darurat di bawah pohon besar, jauh dari sungai (banjir bandang) &amp; pohon mati.</li>
          <li><b>Api</b>: kumpulkan tinder kering (serbuk kayu lapuk, kulit kayu, daun pinus), kindling kecil, fuel besar. Pakai
            ferro rod / korek tahan air. Buat reflektor batang kayu agar panas memantul ke shelter.</li>
          <li><b>Air</b>: cari aliran air mengalir → SARING (kain) → REBUS minimal 1 menit pada didih kuat (3 menit di ketinggian &gt;2.000 m).
            Hindari air diam berbusa / berbau / dekat bangkai hewan.</li>
          <li><b>Sinyal</b>: 3 ledakan peluit / 3 kepulan asap / 3 nyala api = sinyal SOS internasional. Cermin/HP refleksi
            ke arah pesawat di siang hari.</li>
          <li><b>Navigasi</b>: matahari terbit Timur — terbenam Barat. Lumut tumbuh lebih tebal di sisi pohon yang lembap
            (umumnya selatan di Indonesia). Tetap di jalur, jangan turun ke jurang.</li>
        </ul>
        <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle"></i>
          Hindari sungai sebagai jalur turun saat hujan — risiko air bah. Pilih punggungan (ridge).
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-warning">
      <div class="card-header"><i class="bi bi-egg-fried text-warning"></i> <strong>Makanan: Boleh vs Dilarang</strong></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-12">
            <div class="small fw-semibold text-success mb-1"><i class="bi bi-check2-circle"></i> Umumnya AMAN dimakan (Indonesia)</div>
            <ul class="small mb-2">
              <li>Pisang hutan (buah &amp; jantung), pakis muda (digodok), bambu muda (rebung) — buang air rebusan pertama.</li>
              <li>Daun selada air liar di tepi sungai bersih — rebus dulu.</li>
              <li>Buah jambu hutan, markisa hutan, kelapa muda, salak hutan.</li>
              <li>Ikan kecil sungai, belalang &amp; jangkrik (buang sayap &amp; kaki, panggang sampai matang).</li>
              <li>Cacing tanah (rendam &amp; rebus untuk membersihkan).</li>
            </ul>
          </div>
          <div class="col-12">
            <div class="small fw-semibold text-danger mb-1"><i class="bi bi-x-octagon"></i> JANGAN dimakan / waspada tinggi</div>
            <ul class="small mb-0">
              <li>Jamur liar berwarna mencolok (merah, kuning cerah, putih bercak) — banyak yang mematikan, sulit dibedakan untuk awam. <b>Lewati.</b></li>
              <li>Buah bergetah putih susu, biji yang sangat pahit, atau yang membuat bibir kebas — tanda alkaloid beracun.</li>
              <li>Tanaman dengan duri tajam &amp; getah lengket (misal jarak pagar) — beracun.</li>
              <li>Hewan amfibi berwarna mencolok (kodok panah, kodok bufo) — racun di kulit.</li>
              <li>Daging hewan yang ditemukan sudah mati — risiko bakteri &amp; penyakit zoonosis.</li>
            </ul>
          </div>
          <div class="col-12">
            <div class="alert alert-info small mb-0">
              <b>Tes Edibilitas Universal (darurat, 24 jam):</b> oles ke kulit pergelangan → tunggu 15 menit; bila tidak gatal,
              oles ke bibir → tunggu 15 menit; bila aman, kunyah sedikit, jangan ditelan dulu → tunggu 15 menit; bila tidak ada reaksi,
              telan sangat sedikit, tunggu <b>8 jam</b> sebelum makan lebih banyak.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-primary">
      <div class="card-header"><i class="bi bi-compass text-primary"></i> <strong>Mitigasi Agar Tidak Tersesat</strong></div>
      <div class="card-body">
        <ul class="small mb-0">
          <li>Beritahu rencana perjalanan (waktu berangkat, jalur, estimasi pulang) ke 2 orang berbeda + grup komunitas.</li>
          <li>Bawa <b>peta offline</b> (mis. Maps.me, Locus, AlpineQuest) + kompas analog. Jangan andalkan GPS HP saja.</li>
          <li>Aktifkan <a href="/live_tracking.php">Live Tracking</a> SportApp sebelum masuk hutan — kontak darurat dapat melihat posisi terakhir.</li>
          <li>Bawa <b>survival kit</b>: peluit, ferro rod, pisau lipat, headlamp + baterai cadangan, ponco, garam, tablet purifikasi air, P3K.</li>
          <li>Setiap 15 menit, balik badan &amp; rekam <b>mental snapshot</b> jalur (untuk navigasi pulang).</li>
          <li>Tandai jalur dengan <b>flagging tape</b> biodegradable di percabangan; jangan rusak vegetasi (gunakan kembali saat pulang).</li>
          <li>Kembali sebelum gelap. Jika hujan deras tiba, berhenti &amp; berlindung — JANGAN paksa jalan.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100 border-danger">
      <div class="card-header"><i class="bi bi-exclamation-octagon-fill text-danger"></i> <strong>Jika Sudah Tersesat — Lakukan Ini</strong></div>
      <div class="card-body">
        <ol class="small mb-2">
          <li><b>STOP.</b> Tarik napas. JANGAN panik dan JANGAN terus berjalan — 80% korban hilang ditemukan lebih jauh karena terus bergerak acak.</li>
          <li><b>Hubungi 115 (Basarnas) atau 112</b> jika ada sinyal. Kirim koordinat HP (Google Maps → bagikan lokasi → WhatsApp). Hemat baterai: aktifkan mode pesawat saat tidak digunakan.</li>
          <li><b>Tetap di tempat terbuka</b> yang mudah terlihat dari udara (lapangan kecil, tepi sungai lebar). Tim SAR mencari sesuai jalur terakhir yang dilaporkan.</li>
          <li><b>Sinyal</b>: tiga bunyi peluit pendek berulang setiap 1 menit. Susun batu/ranting membentuk <b>SOS</b> atau <b>panah</b> ke arah pergerakan terakhir Anda.</li>
          <li><b>Buat shelter sebelum gelap</b>: cari naungan, tinggikan badan dari tanah (alas daun/ranting), tutupi dengan ponco untuk menghindari hipotermia.</li>
          <li><b>Air dulu</b>, makanan belakangan. Cari air mengalir → saring → rebus. Tubuh dapat bertahan 3 hari tanpa air, 3 minggu tanpa makanan.</li>
          <li>Saat helikopter / drone SAR mendekat: lambaikan kain berwarna terang, bukan kain hijau (sulit terlihat di hutan).</li>
        </ol>
        <div class="alert alert-danger small mb-0">
          <b>Hipotermia</b> (menggigil hebat, bingung) = darurat. Ganti pakaian basah, nyalakan api, pelukan kontak kulit ke kulit jika ada rekan.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var form = document.getElementById('survForm');
  var inp  = document.getElementById('survInput');
  var out  = document.getElementById('survOut');
  var actions = document.getElementById('survActions');
  var btnSimpan = document.getElementById('btnSimpanQAsv');
  var qaStat = document.getElementById('qaSaveStatSv');
  if (!form) return;
  var isLoading = false, lastQ='', lastA='', lastSavedKey='';
  document.getElementById('survClear').addEventListener('click', function(){
    inp.value=''; out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Tulis pertanyaan lalu klik Tanyakan.';
    if (actions) actions.style.display='none'; lastQ=''; lastA=''; lastSavedKey='';
  });
  form.addEventListener('submit', async function(e){
    e.preventDefault(); if (isLoading) return;
    var q = (inp.value||'').trim(); if (!q) return;
    if (q === lastQ && lastA){ qaStat.textContent = 'Pertanyaan sama — gunakan jawaban sebelumnya.'; return; }
    isLoading = true;
    var btn = form.querySelector('button[type=submit]'); var oh = btn.innerHTML;
    btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> AI menjawab...';
    out.className='border rounded p-3 bg-body-tertiary small text-muted';
    out.textContent='Sedang menjawab... (hanya 1x kirim, mohon tunggu)';
    if (actions) actions.style.display='none';
    try {
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>');
      fd.append('task','tanya_survival');
      fd.append('prompt', q);
      var r = await fetch('/api_ai.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (!j.ok){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Gagal: '+(j.err||'?'); }
      else {
        out.className='border rounded p-3 bg-body-tertiary';
        var html = (j.text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
        out.innerHTML = '<p>'+html+'</p>';
        lastQ = q; lastA = j.text || '';
        if (actions) actions.style.display='flex'; qaStat.textContent='';
      }
    } catch(err){ out.className='border rounded p-3 bg-warning-subtle small'; out.textContent='Error: '+err.message; }
    btn.disabled=false; btn.innerHTML=oh; isLoading=false;
  });

  if (btnSimpan) btnSimpan.addEventListener('click', async function(){
    if (!lastQ || !lastA) return;
    var key = lastQ+'|'+lastA.substring(0,32);
    if (key === lastSavedKey){ qaStat.textContent='Sudah disimpan sebelumnya.'; return; }
    btnSimpan.disabled = true;
    var fd = new FormData();
    fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','qa_save');
    fd.append('pertanyaan', lastQ); fd.append('jawaban', lastA);
    try {
      var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ lastSavedKey = key; qaStat.innerHTML = '<i class="bi bi-check-circle text-success"></i> Tersimpan (#'+j.id+').'; }
      else qaStat.textContent = 'Gagal menyimpan.';
    } catch(e){ qaStat.textContent='Error: '+e.message; }
    btnSimpan.disabled = false;
  });

  document.querySelectorAll('.qa-del-btn-sv').forEach(function(b){
    b.addEventListener('click', async function(){
      if (!confirm('Hapus Q&A ini?')) return;
      var id = b.dataset.id;
      var fd = new FormData();
      fd.append('csrf','<?= csrf_token() ?>'); fd.append('_action','qa_delete'); fd.append('id', id);
      var r = await fetch('/survival.php',{method:'POST', body:fd, credentials:'same-origin'});
      var j = await r.json();
      if (j.ok){ var el = document.querySelector('[data-qa-id="'+id+'"]'); if(el) el.remove(); }
    });
  });
})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>

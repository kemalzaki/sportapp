<?php
/**
 * Direct Message (chat antar member).
 * - /dm.php           → daftar percakapan
 * - /dm.php?u=ID      → ruang chat dengan user ID (atau username)
 * - /api_dm.php       → polling realtime + kirim pesan (JSON)
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/scope.php';
send_security_headers(); require_login();
$u = current_user();
$pageTitle = 'Pesan';
// Revisi Juli 2026 — DM dibatasi hanya ke sesama anggota komunitas user.
$__dmScopeIds = scope_visible_user_ids();
if (!$__dmScopeIds) { $__dmScopeIds = [(int)$u['id']]; }
$__dmScopeArr = '{'.implode(',', array_map('intval', $__dmScopeIds)).'}';

$peerId = (int)($_GET['u'] ?? 0);
if (!$peerId && !empty($_GET['username'])) {
    $row = db_one("SELECT id FROM users WHERE LOWER(username)=LOWER($1) OR LOWER(nama)=LOWER($1) LIMIT 1", [$_GET['username']]);
    if ($row) $peerId = (int)$row['id'];
}
// Hanya boleh chat dengan user yang berada pada scope komunitas yang sama.
$peer = null;
if ($peerId && in_array((int)$peerId, $__dmScopeIds, true) && (int)$peerId !== (int)$u['id']) {
    $peer = db_one("SELECT id,nama,foto_url FROM users WHERE id=$1", [$peerId]);
}
$peerBlocked = ($peerId && !$peer);

// Daftar percakapan: ambil partner terakhir
$threads = db_all("
  SELECT u.id, u.nama, u.foto_url,
    (SELECT pesan FROM dm_messages m
       WHERE (m.sender_id=u.id AND m.receiver_id=$1) OR (m.sender_id=$1 AND m.receiver_id=u.id)
       ORDER BY m.id DESC LIMIT 1) AS last_msg,
    (SELECT created_at FROM dm_messages m
       WHERE (m.sender_id=u.id AND m.receiver_id=$1) OR (m.sender_id=$1 AND m.receiver_id=u.id)
       ORDER BY m.id DESC LIMIT 1) AS last_at,
    (SELECT COUNT(*) FROM dm_messages m
       WHERE m.sender_id=u.id AND m.receiver_id=$1 AND m.read_at IS NULL) AS unread
  FROM users u
  WHERE u.id IN (
    SELECT DISTINCT CASE WHEN sender_id=$1 THEN receiver_id ELSE sender_id END
      FROM dm_messages WHERE sender_id=$1 OR receiver_id=$1)
    AND u.id = ANY($2::int[])
  ORDER BY last_at DESC NULLS LAST
", [(int)$u['id'], $__dmScopeArr]);

include __DIR__.'/includes/header.php';
?>
<style>
.dm-ticks{font-weight:700;letter-spacing:-1px;margin-left:4px;}
.dm-ticks.sent{color:#9aa0a6 !important;opacity:1;}      /* ✓ abu — terkirim ke server, belum sampai */
.dm-ticks.delivered{color:#9aa0a6 !important;opacity:1;} /* ✓✓ abu — sampai, belum dibaca */
.dm-ticks.read{color:#25d366 !important;opacity:1;}      /* ✓✓ hijau — sudah dibaca (WhatsApp style) */
.dm-peer-link{color:inherit;text-decoration:none;}
.dm-peer-link:hover{text-decoration:underline;}
</style>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-chat-dots text-primary"></i> Pesan</strong>
        <a href="#" class="small" data-bs-toggle="modal" data-bs-target="#newChatModal">+ Baru</a>
      </div>
      <div class="list-group list-group-flush" style="max-height:70vh;overflow:auto">
        <?php if(!$threads): ?>
          <div class="p-3 small text-muted">Belum ada percakapan. Klik "+ Baru" untuk memulai chat dengan member lain.</div>
        <?php endif; foreach($threads as $t): ?>
          <a href="/dm.php?u=<?= (int)$t['id'] ?>" class="list-group-item d-flex gap-2 align-items-center <?= $peerId==(int)$t['id']?'active':'' ?>">
            <?= user_avatar($t['foto_url'], $t['nama'], 36) ?>
            <div class="flex-grow-1 overflow-hidden">
              <div class="d-flex justify-content-between">
                <strong class="text-truncate"><?= htmlspecialchars($t['nama']) ?></strong>
                <?php if($t['unread']>0): ?><span class="badge bg-danger rounded-pill"><?= (int)$t['unread'] ?></span><?php endif; ?>
              </div>
              <div class="small text-muted text-truncate"><?= htmlspecialchars(mb_strimwidth($t['last_msg']??'',0,42,'…')) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <?php if(!$peer): ?>
      <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-chat-square-text fs-1"></i>
        <?php if(!empty($peerBlocked)): ?>
          <div class="mt-2 text-danger"><i class="bi bi-shield-lock"></i> Anda hanya dapat mengirim pesan ke sesama anggota komunitas Anda.</div>
        <?php else: ?>
          <div class="mt-2">Pilih percakapan di sebelah kiri, atau mulai chat baru.</div>
        <?php endif; ?>
      </div></div>
    <?php else: ?>
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <a href="/user.php?id=<?= (int)$peer['id'] ?>" class="dm-peer-link"><?= user_avatar($peer['foto_url'], $peer['nama'], 32) ?></a>
          <div class="flex-grow-1">
            <a href="/user.php?id=<?= (int)$peer['id'] ?>" class="dm-peer-link" title="Lihat profil <?= htmlspecialchars($peer['nama']) ?>">
              <strong><?= htmlspecialchars($peer['nama']) ?></strong>
            </a>
            <div class="small text-muted" id="peerStatus"><span class="text-secondary">memuat status…</span></div>
          </div>
          <a href="/user.php?id=<?= (int)$peer['id'] ?>" class="btn btn-sm btn-outline-primary" title="Lihat profil"><i class="bi bi-person-circle"></i></a>
        </div>
        <div id="dmBox" style="height:55vh;overflow:auto;padding:1rem;background:var(--bs-tertiary-bg,#f8fafc);"></div>
        <div class="card-footer">
          <form id="dmForm" class="d-flex gap-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="to" value="<?= (int)$peer['id'] ?>">
            <input class="form-control" name="pesan" placeholder="Tulis pesan…" maxlength="2000" autocomplete="off" required>
            <button class="btn btn-primary"><i class="bi bi-send"></i></button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="newChatModal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title">Mulai Chat Baru</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input id="newChatSearch" class="form-control" placeholder="Cari nama / username member…">
    <div id="newChatResults" class="list-group mt-2" style="max-height:300px;overflow:auto"></div>
  </div>
</div></div></div>

<script>
(function(){
  var search = document.getElementById('newChatSearch');
  var results = document.getElementById('newChatResults');
  var to;
  search && search.addEventListener('input', function(){
    clearTimeout(to);
    var q = search.value.trim();
    if (q.length < 2) { results.innerHTML=''; return; }
    to = setTimeout(function(){
      fetch('/api_dm.php?find=' + encodeURIComponent(q)).then(r=>r.json()).then(function(rows){
        results.innerHTML = (rows||[]).map(function(r){
          return '<a class="list-group-item list-group-item-action" href="/dm.php?u='+r.id+'">'+
            (r.foto_url ? '<img src="'+r.foto_url+'" style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:6px">' : '')+
            '<strong>'+r.nama+'</strong> <span class="small text-muted">'+(r.username||'')+'</span></a>';
        }).join('');
      });
    }, 250);
  });

  <?php if($peer): ?>
  var peerId = <?= (int)$peer['id'] ?>;
  var myId   = <?= (int)$u['id'] ?>;
  var lastId = 0;
  var lastDayKey = '';
  var box = document.getElementById('dmBox');
  var form = document.getElementById('dmForm');

  function esc(t){return (t||'').replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}
  function fmtTime(s){ var d=new Date(s); return d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}); }
  function dayKey(s){ var d=new Date(s); return d.getFullYear()+'-'+(d.getMonth()+1)+'-'+d.getDate(); }
  function fmtDayLabel(s){
    var d=new Date(s);
    var today=new Date(); var yest=new Date(); yest.setDate(today.getDate()-1);
    var hari=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][d.getDay()];
    var bln=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'][d.getMonth()];
    if (dayKey(s)===dayKey(today)) return 'Hari ini · '+hari+', '+d.getDate()+' '+bln+' '+d.getFullYear();
    if (dayKey(s)===dayKey(yest))  return 'Kemarin · '+hari+', '+d.getDate()+' '+bln+' '+d.getFullYear();
    return hari+', '+d.getDate()+' '+bln+' '+d.getFullYear();
  }
  function tickHtml(m){
    if (m.sender_id != myId) return '';
    if (m.read_at)      return ' <span class="dm-ticks read" title="Dibaca">✓✓</span>';
    if (m.delivered_at) return ' <span class="dm-ticks delivered" title="Terkirim">✓✓</span>';
    return ' <span class="dm-ticks sent" title="Dikirim">✓</span>';
  }

  function appendDaySeparator(s){
    var k = dayKey(s);
    if (k === lastDayKey) return;
    lastDayKey = k;
    box.insertAdjacentHTML('beforeend',
      '<div class="text-center my-2"><span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-1 rounded-pill" style="font-size:.72rem">'+
      esc(fmtDayLabel(s))+'</span></div>');
  }

  function render(rows){
    if (!rows.length) return;
    var atBottom = (box.scrollTop + box.clientHeight) >= (box.scrollHeight - 50);
    rows.forEach(function(m){
      if (m.id <= lastId) return;
      lastId = Math.max(lastId, m.id);
      appendDaySeparator(m.created_at);
      var mine = m.sender_id == myId;
      var html = '<div class="d-flex mb-2 '+(mine?'justify-content-end':'')+'" id="dm-'+m.id+'">'+
        '<div class="p-2 rounded-3 shadow-sm" style="max-width:75%;background:'+(mine?'#0ea5e9':'#fff')+';color:'+(mine?'#fff':'inherit')+'">'+
        '<div>'+esc(m.pesan)+'</div>'+
        '<div class="small '+(mine?'text-white-50':'text-muted')+'" style="font-size:.7rem">'+fmtTime(m.created_at)+tickHtml(m)+'</div>'+
        '</div></div>';
      box.insertAdjacentHTML('beforeend', html);
    });
    if (atBottom) box.scrollTop = box.scrollHeight;
  }

  function updateStatuses(statuses){
    if (!statuses || !statuses.length) return;
    statuses.forEach(function(s){
      var el = document.querySelector('#dm-'+s.id+' .dm-ticks');
      if (!el) return;
      if (s.read_at){ el.textContent='✓✓'; el.className='dm-ticks read'; el.title='Dibaca'; }
      else if (s.delivered_at){ el.textContent='✓✓'; el.className='dm-ticks delivered'; el.title='Terkirim'; }
      else { el.textContent='✓'; el.className='dm-ticks sent'; el.title='Dikirim'; }
    });
  }

  function poll(){
    fetch('/api_dm.php?peer='+peerId+'&since='+lastId).then(r=>r.json()).then(function(d){
      render(d.messages || []);
      updateStatuses(d.statuses || []);
    }).catch(()=>{});
  }
  poll(); setInterval(poll, 3000);

  // Tandai pesan masuk sebagai "delivered" agar sender melihat ceklis 2
  function pingDelivered(){ fetch('/api_dm.php?delivered=1').catch(()=>{}); }
  pingDelivered(); setInterval(pingDelivered, 8000);

  // Status online peer
  var peerStatusEl = document.getElementById('peerStatus');
  function fmtAgo(sec){
    if (sec < 60)  return 'baru saja';
    if (sec < 3600)return Math.floor(sec/60)+' menit lalu';
    if (sec < 86400)return Math.floor(sec/3600)+' jam lalu';
    return Math.floor(sec/86400)+' hari lalu';
  }
  function pollStatus(){
    if (!peerStatusEl) return;
    fetch('/api_dm.php?status='+peerId).then(r=>r.json()).then(function(d){
      if (!d) return;
      if (d.online) {
        peerStatusEl.innerHTML = '<span class="text-success"><i class="bi bi-circle-fill" style="font-size:.55rem"></i> online</span>';
      } else if (d.last_seen_ts) {
        var sec = Math.max(0, Math.floor(Date.now()/1000) - d.last_seen_ts);
        peerStatusEl.innerHTML = '<span class="text-muted">terakhir aktif '+fmtAgo(sec)+'</span>';
      } else {
        peerStatusEl.innerHTML = '<span class="text-muted">offline</span>';
      }
    }).catch(()=>{});
  }
  pollStatus(); setInterval(pollStatus, 30000);

  form.addEventListener('submit', function(e){
    e.preventDefault();
    // Revisi 22 Juni 2026 R5 — kirim pesan dm: lebih tahan error.
    //  - selalu kosongkan input setelah server merespon (apapun bentuknya)
    //  - jangan kandidatkan sebagai gagal hanya karena response bukan JSON
    //  - tampilkan toast singkat saat server membalas error.
    var txt = (form.pesan.value || '').trim();
    if (!txt) return;
    var btn = form.querySelector('button[type=submit], button:not([type])');
    if (btn) btn.disabled = true;
    var fd = new FormData(form);
    fetch('/api_dm.php', {method:'POST', body: fd, credentials:'same-origin'})
      .then(function(r){ return r.text().then(function(t){ return {status:r.status, text:t}; }); })
      .then(function(res){
        var ok = false, data = null;
        try { data = JSON.parse(res.text); ok = !!(data && data.ok); } catch(e){ ok = (res.status>=200 && res.status<300); }
        if (ok) { form.pesan.value=''; }
        else {
          var msg = (data && (data.err||data.msg)) ? (data.err||data.msg) : ('HTTP '+res.status);
          console.warn('dm send gagal:', msg, res.text.substring(0,200));
          alert('Pesan gagal terkirim: '+msg+'.\nCoba muat ulang halaman.');
        }
        poll();
      })
      .catch(function(err){
        console.error('dm fetch error', err);
        alert('Gagal mengirim pesan (jaringan). Coba lagi.');
      })
      .finally(function(){ if (btn) btn.disabled = false; });
  });
  <?php endif; ?>
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

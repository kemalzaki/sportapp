<?php
// Floating DM widget (Facebook-style). Disertakan di setiap halaman via bottom_nav.php
// Hanya tampil untuk user yang sudah login. Menggunakan endpoint /api_dm.php yang sudah ada.
if (!function_exists('current_user')) return;
$__dm_u = current_user();
if (!$__dm_u) return;

// Hindari double include di halaman /dm.php (sudah punya UI chat penuh)
$__self = $_SERVER['SCRIPT_NAME'] ?? '';
$__onDmPage = (basename($__self) === 'dm.php');
?>
<style>
#fbDmFab{position:fixed;right:18px;bottom:90px;z-index:1070;width:56px;height:56px;border-radius:50%;
  background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;border:0;box-shadow:0 6px 20px rgba(2,6,23,.25);
  display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.5rem;transition:transform .15s;}
#fbDmFab:hover{transform:scale(1.06);}
#fbDmFab .badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:999px;
  font-size:.65rem;padding:2px 6px;border:2px solid #fff;display:none;}
#fbDmPanel{position:fixed;right:18px;bottom:158px;z-index:1071;width:340px;max-width:92vw;height:480px;max-height:75vh;
  background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(2,6,23,.25);display:none;flex-direction:column;overflow:hidden;border:1px solid #e2e8f0;}
#fbDmPanel.open{display:flex;}
#fbDmPanel header{background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;padding:10px 14px;display:flex;align-items:center;gap:.5rem;}
#fbDmPanel header strong{flex:1;}
#fbDmPanel header button{background:transparent;border:0;color:#fff;font-size:1.1rem;cursor:pointer;padding:2px 6px;border-radius:4px;}
#fbDmPanel header button:hover{background:rgba(255,255,255,.18);}
#fbDmPanel .fb-body{flex:1;overflow:auto;background:#f8fafc;}
#fbDmPanel .fb-list{list-style:none;margin:0;padding:0;}
#fbDmPanel .fb-list li{border-bottom:1px solid #e2e8f0;}
#fbDmPanel .fb-list a{display:flex;gap:.6rem;align-items:center;padding:10px 12px;text-decoration:none;color:inherit;}
#fbDmPanel .fb-list a:hover{background:#f1f5f9;}
#fbDmPanel .fb-list .nm{font-weight:600;font-size:.92rem;color:#0f172a;}
#fbDmPanel .fb-list .lm{font-size:.78rem;color:#64748b;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
#fbDmPanel .fb-list .un{background:#ef4444;color:#fff;font-size:.65rem;border-radius:999px;padding:2px 6px;margin-left:auto;}
#fbDmPanel .fb-search{padding:8px;border-bottom:1px solid #e2e8f0;background:#fff;}
#fbDmPanel .fb-search input{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:.85rem;}
#fbDmPanel .fb-empty{padding:24px 18px;text-align:center;color:#64748b;font-size:.88rem;}
#fbDmPanel .fb-foot{padding:8px 12px;border-top:1px solid #e2e8f0;background:#fff;text-align:center;}
#fbDmPanel .fb-foot a{font-size:.82rem;color:#0ea5e9;text-decoration:none;font-weight:600;}

/* Chat window */
#fbDmChat{position:fixed;right:18px;bottom:90px;z-index:1072;width:340px;max-width:92vw;height:460px;max-height:72vh;
  background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(2,6,23,.25);display:none;flex-direction:column;overflow:hidden;border:1px solid #e2e8f0;}
#fbDmChat.open{display:flex;}
#fbDmChat header{background:#0ea5e9;color:#fff;padding:8px 12px;display:flex;align-items:center;gap:.5rem;}
#fbDmChat header img{width:30px;height:30px;border-radius:50%;object-fit:cover;}
#fbDmChat header .nm{flex:1;font-weight:600;font-size:.95rem;}
#fbDmChat header button{background:transparent;border:0;color:#fff;font-size:1rem;cursor:pointer;padding:2px 6px;border-radius:4px;}
#fbDmChat header button:hover{background:rgba(255,255,255,.18);}
#fbDmChat .chat-body{flex:1;overflow:auto;padding:10px;background:#f8fafc;}
#fbDmChat .msg{margin-bottom:6px;display:flex;}
#fbDmChat .msg.mine{justify-content:flex-end;}
#fbDmChat .msg .bub{max-width:78%;padding:6px 10px;border-radius:12px;font-size:.88rem;line-height:1.35;background:#fff;border:1px solid #e2e8f0;color:#0f172a;word-wrap:break-word;}
#fbDmChat .msg.mine .bub{background:#0ea5e9;color:#fff;border-color:#0ea5e9;}
#fbDmChat .msg .tm{display:block;font-size:.65rem;opacity:.7;margin-top:2px;}
#fbDmChat .dm-ticks{font-weight:700;margin-left:4px;}
#fbDmChat .dm-ticks.sent,#fbDmChat .dm-ticks.delivered{color:#cbd5e1;}
#fbDmChat .dm-ticks.read{color:#25d366;}
#fbDmChat .msg.mine .dm-ticks.sent,#fbDmChat .msg.mine .dm-ticks.delivered{color:#e2e8f0;}
#fbDmChat form{display:flex;gap:6px;padding:8px;border-top:1px solid #e2e8f0;background:#fff;}
#fbDmChat form input{flex:1;border:1px solid #cbd5e1;border-radius:18px;padding:6px 12px;font-size:.88rem;}
#fbDmChat form button{border:0;background:#0ea5e9;color:#fff;border-radius:50%;width:34px;height:34px;cursor:pointer;}
@media (max-width:600px){
  #fbDmFab{right:12px;bottom:80px;}
  #fbDmPanel,#fbDmChat{right:8px;left:8px;width:auto;max-width:none;}
}
</style>

<?php if (!$__onDmPage): ?>
<button id="fbDmFab" title="Pesan" aria-label="Pesan">
  <i class="bi bi-chat-dots-fill"></i>
  <span class="badge" id="fbDmBadge">0</span>
</button>

<div id="fbDmPanel" role="dialog" aria-label="Daftar Pesan">
  <header>
    <i class="bi bi-chat-dots-fill"></i>
    <strong>Pesan</strong>
    <button type="button" id="fbDmReload" title="Muat ulang"><i class="bi bi-arrow-clockwise"></i></button>
    <button type="button" id="fbDmClose" title="Tutup">×</button>
  </header>
  <div class="fb-search">
    <input type="text" id="fbDmSearch" placeholder="Cari nama member…" autocomplete="off">
  </div>
  <div class="fb-body">
    <div id="fbDmThreads">
      <div class="fb-empty"><div class="spinner-border spinner-border-sm"></div> Memuat percakapan…</div>
    </div>
  </div>
  <div class="fb-foot">
    <a href="/dm.php"><i class="bi bi-box-arrow-up-right"></i> Buka halaman pesan penuh</a>
  </div>
</div>

<div id="fbDmChat" role="dialog" aria-label="Jendela Chat">
  <header>
    <img id="fbChatAvatar" src="" alt="" onerror="this.style.visibility='hidden'">
    <span class="nm" id="fbChatName">…</span>
    <button type="button" id="fbChatBack" title="Kembali"><i class="bi bi-arrow-left"></i></button>
    <button type="button" id="fbChatClose" title="Tutup">×</button>
  </header>
  <div class="chat-body" id="fbChatBody"></div>
  <form id="fbChatForm" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="to" id="fbChatTo" value="">
    <input type="text" name="pesan" id="fbChatInput" placeholder="Tulis pesan…" maxlength="2000" required>
    <button type="submit" title="Kirim"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<script>
(function(){
  var MY_ID = <?= (int)$__dm_u['id'] ?>;
  var fab = document.getElementById('fbDmFab');
  var panel = document.getElementById('fbDmPanel');
  var chat = document.getElementById('fbDmChat');
  var badge = document.getElementById('fbDmBadge');
  var threadsBox = document.getElementById('fbDmThreads');
  var searchInput = document.getElementById('fbDmSearch');
  var chatBody = document.getElementById('fbChatBody');
  var chatForm = document.getElementById('fbChatForm');
  var chatTo = document.getElementById('fbChatTo');
  var chatInput = document.getElementById('fbChatInput');
  var chatName = document.getElementById('fbChatName');
  var chatAv = document.getElementById('fbChatAvatar');
  var currentPeer = 0, lastId = 0, chatTimer = null, threadsTimer = null;

  function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}
  function fmtTime(s){ try{ var d=new Date(s); return d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'}); }catch(e){return '';} }

  fab.addEventListener('click', function(){
    if (panel.classList.contains('open') || chat.classList.contains('open')) {
      panel.classList.remove('open'); chat.classList.remove('open'); stopChatPoll();
    } else {
      panel.classList.add('open'); loadThreads();
      if (!threadsTimer) threadsTimer = setInterval(loadThreads, 15000);
    }
  });
  document.getElementById('fbDmClose').addEventListener('click', function(){ panel.classList.remove('open'); });
  document.getElementById('fbDmReload').addEventListener('click', loadThreads);
  document.getElementById('fbChatClose').addEventListener('click', function(){ chat.classList.remove('open'); stopChatPoll(); });
  document.getElementById('fbChatBack').addEventListener('click', function(){ chat.classList.remove('open'); stopChatPoll(); panel.classList.add('open'); loadThreads(); });

  function loadThreads(){
    fetch('/api_dm.php?threads=1', {credentials:'same-origin'}).then(function(r){return r.text();}).then(function(txt){
      var data; try{ data = JSON.parse(txt); }catch(e){ return; }
      var rows = (data && data.threads) || [];
      var unread = 0;
      if (!rows.length) {
        threadsBox.innerHTML = '<div class="fb-empty">Belum ada percakapan.<br><a href="/dm.php" class="small">Mulai chat baru →</a></div>';
      } else {
        var html = '<ul class="fb-list">';
        rows.forEach(function(t){
          if (t.unread>0) unread += parseInt(t.unread,10)||0;
          var av = t.foto_url ? '<img src="'+esc(t.foto_url)+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover">'
                              : '<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">'+esc((t.nama||'?').charAt(0).toUpperCase())+'</div>';
          html += '<li><a href="#" data-peer="'+t.id+'" data-nama="'+esc(t.nama)+'" data-foto="'+esc(t.foto_url||'')+'" class="js-open-chat">'+
            av +
            '<div style="flex:1;overflow:hidden"><span class="nm">'+esc(t.nama)+'</span><span class="lm">'+esc(t.last_msg||'')+'</span></div>'+
            (t.unread>0 ? '<span class="un">'+t.unread+'</span>' : '') +
          '</a></li>';
        });
        html += '</ul>';
        threadsBox.innerHTML = html;
        threadsBox.querySelectorAll('.js-open-chat').forEach(function(a){
          a.addEventListener('click', function(e){ e.preventDefault(); openChat(parseInt(a.dataset.peer,10), a.dataset.nama, a.dataset.foto); });
        });
      }
      if (unread>0) { badge.textContent = unread>99?'99+':unread; badge.style.display='inline-block'; }
      else { badge.style.display='none'; }
    }).catch(function(){});
  }

  // Cek unread berkala walaupun panel tertutup
  function pingUnread(){
    fetch('/api_dm.php?unread=1', {credentials:'same-origin'}).then(function(r){return r.text();}).then(function(txt){
      try{ var d = JSON.parse(txt); if (d && typeof d.unread !== 'undefined'){
        if (d.unread>0) { badge.textContent = d.unread>99?'99+':d.unread; badge.style.display='inline-block'; }
        else badge.style.display='none';
      } }catch(e){}
    }).catch(function(){});
  }
  pingUnread(); setInterval(pingUnread, 20000);

  function openChat(peerId, nama, foto){
    currentPeer = peerId; lastId = 0;
    chatTo.value = peerId;
    chatName.textContent = nama || ('User #'+peerId);
    if (foto) { chatAv.src = foto; chatAv.style.visibility='visible'; } else { chatAv.style.visibility='hidden'; }
    chatBody.innerHTML = '<div class="text-center text-muted small py-3"><div class="spinner-border spinner-border-sm"></div> Memuat…</div>';
    panel.classList.remove('open');
    chat.classList.add('open');
    pollChat();
    if (chatTimer) clearInterval(chatTimer);
    chatTimer = setInterval(pollChat, 3000);
    setTimeout(function(){ chatInput.focus(); }, 100);
  }
  function stopChatPoll(){ if (chatTimer){ clearInterval(chatTimer); chatTimer=null; } currentPeer=0; }

  function pollChat(){
    if (!currentPeer) return;
    fetch('/api_dm.php?peer='+currentPeer+'&since='+lastId, {credentials:'same-origin'}).then(function(r){return r.text();}).then(function(txt){
      var d; try{ d = JSON.parse(txt); }catch(e){ return; }
      var rows = (d && d.messages) || [];
      var statuses = (d && d.statuses) || [];
      if (lastId === 0) chatBody.innerHTML = '';
      var atBottom = (chatBody.scrollTop + chatBody.clientHeight) >= (chatBody.scrollHeight - 60);
      rows.forEach(function(m){
        if (m.id <= lastId) return;
        lastId = Math.max(lastId, parseInt(m.id,10));
        var mine = parseInt(m.sender_id,10) === MY_ID;
        var ticks = '';
        if (mine) {
          if (m.read_at)        ticks = ' <span class="dm-ticks read" data-msg="'+m.id+'" title="Dibaca">✓✓</span>';
          else if (m.delivered_at) ticks = ' <span class="dm-ticks delivered" data-msg="'+m.id+'" title="Terkirim">✓✓</span>';
          else                  ticks = ' <span class="dm-ticks sent" data-msg="'+m.id+'" title="Dikirim">✓</span>';
        }
        var html = '<div class="msg '+(mine?'mine':'')+'" data-id="'+m.id+'"><div class="bub">'+esc(m.pesan)+'<span class="tm">'+fmtTime(m.created_at)+ticks+'</span></div></div>';
        chatBody.insertAdjacentHTML('beforeend', html);
      });
      // Update tick statuses pesan saya (yang sudah ditampilkan sebelumnya)
      statuses.forEach(function(s){
        var el = chatBody.querySelector('.dm-ticks[data-msg="'+s.id+'"]');
        if (!el) return;
        if (s.read_at){ el.textContent='✓✓'; el.className='dm-ticks read'; el.title='Dibaca'; el.dataset.msg=s.id; }
        else if (s.delivered_at){ el.textContent='✓✓'; el.className='dm-ticks delivered'; el.title='Terkirim'; el.dataset.msg=s.id; }
        else { el.textContent='✓'; el.className='dm-ticks sent'; el.title='Dikirim'; el.dataset.msg=s.id; }
      });
      if (atBottom) chatBody.scrollTop = chatBody.scrollHeight;
    }).catch(function(){});
  }

  chatForm.addEventListener('submit', function(e){
    e.preventDefault();
    var txt = chatInput.value.trim(); if (!txt || !currentPeer) return;
    var fd = new FormData(chatForm);
    chatInput.value = ''; chatInput.disabled = true;
    fetch('/api_dm.php', {method:'POST', body: fd, credentials:'same-origin'}).then(function(r){return r.text();}).then(function(t){
      chatInput.disabled = false; chatInput.focus();
      pollChat();
    }).catch(function(){ chatInput.disabled = false; });
  });

  // Search member untuk mulai chat baru
  var searchTo = null;
  searchInput.addEventListener('input', function(){
    clearTimeout(searchTo);
    var q = searchInput.value.trim();
    if (q.length < 2) { loadThreads(); return; }
    searchTo = setTimeout(function(){
      fetch('/api_dm.php?find='+encodeURIComponent(q), {credentials:'same-origin'}).then(function(r){return r.text();}).then(function(txt){
        var rows; try{ rows = JSON.parse(txt); }catch(e){ rows=[]; }
        if (!rows || !rows.length) { threadsBox.innerHTML = '<div class="fb-empty">Tidak ada member ditemukan.</div>'; return; }
        var html = '<ul class="fb-list">';
        rows.forEach(function(t){
          var av = t.foto_url ? '<img src="'+esc(t.foto_url)+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover">'
                              : '<div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">'+esc((t.nama||'?').charAt(0).toUpperCase())+'</div>';
          html += '<li><a href="#" data-peer="'+t.id+'" data-nama="'+esc(t.nama)+'" data-foto="'+esc(t.foto_url||'')+'" class="js-open-chat">'+av+
            '<div style="flex:1;overflow:hidden"><span class="nm">'+esc(t.nama)+'</span><span class="lm">@'+esc(t.username||'')+'</span></div></a></li>';
        });
        html += '</ul>';
        threadsBox.innerHTML = html;
        threadsBox.querySelectorAll('.js-open-chat').forEach(function(a){
          a.addEventListener('click', function(e){ e.preventDefault(); openChat(parseInt(a.dataset.peer,10), a.dataset.nama, a.dataset.foto); });
        });
      });
    }, 300);
  });
})();
</script>
<?php endif; ?>

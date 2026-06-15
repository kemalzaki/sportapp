<?php
/**
 * Revisi 15 Juni 2026 — Halaman "Live Tracking / Beacon"
 *
 * Pemilik akun memulai sesi → mendapatkan tautan publik yang bisa dikirim ke
 * keluarga / kontak darurat lewat WhatsApp / Telegram / SMS / email.
 * Browser pemilik akan mengirim titik GPS otomatis tiap 5 detik selama tab
 * dibiarkan terbuka. Penerima cukup buka tautan, peta langsung mengikuti.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];
$pageTitle = 'Live Tracking / Beacon';

// Auto-migrasi (lihat api_live_tracking.php) — dipanggil juga di sini supaya
// halaman aman dibuka pertama kali tanpa pernah hit API.
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_sessions (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    token VARCHAR(48) NOT NULL UNIQUE, judul TEXT NOT NULL DEFAULT 'Live Tracking',
    pesan TEXT, olahraga TEXT NOT NULL DEFAULT 'lari',
    started_at TIMESTAMP NOT NULL DEFAULT now(), ended_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT (now() + INTERVAL '12 hours'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_lat DOUBLE PRECISION, last_lng DOUBLE PRECISION, last_seen_at TIMESTAMP
)");
@db_exec("CREATE TABLE IF NOT EXISTS live_tracking_contacts (
    id BIGSERIAL PRIMARY KEY, user_id BIGINT NOT NULL,
    nama TEXT NOT NULL, nomor_wa TEXT, email TEXT, relasi TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT now()
)");

$mine     = db_all("SELECT * FROM live_tracking_sessions WHERE user_id=$1 ORDER BY id DESC LIMIT 10", [$uid]);
$contacts = db_all("SELECT * FROM live_tracking_contacts WHERE user_id=$1 ORDER BY id DESC", [$uid]);

include __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h4 class="mb-1"><i class="bi bi-broadcast text-danger"></i> Live Tracking / Beacon</h4>
<p class="text-muted small mb-3">
  Bagikan posisi GPS Anda secara langsung kepada keluarga / kontak darurat
  selama berolahraga. Mereka cukup membuka tautan yang dikirim — tidak perlu
  install apa-apa.
</p>

<div class="row g-3">
  <!-- KIRI: Kontrol sesi -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-play-circle text-success"></i> Mulai Sesi Berbagi</h6>
        <form id="frmStart" class="vstack gap-2">
          <input class="form-control form-control-sm" name="judul"    placeholder="Judul (mis. Lari sore taman)" value="Lari sore">
          <select class="form-select form-select-sm" name="olahraga">
            <option value="lari">Lari</option>
            <option value="sepeda">Sepeda</option>
            <option value="jalan">Jalan kaki</option>
            <option value="hiking">Hiking</option>
            <option value="lainnya">Lainnya</option>
          </select>
          <input type="number" min="1" max="24" class="form-control form-control-sm" name="durasi_jam" value="6" title="Durasi berlaku tautan (jam)">
          <textarea class="form-control form-control-sm" name="pesan" rows="2" placeholder="Pesan untuk penerima (opsional)"></textarea>
          <button class="btn btn-danger"><i class="bi bi-broadcast"></i> Mulai &amp; buat tautan</button>
        </form>

        <hr>
        <div id="liveBox" class="d-none">
          <div class="alert alert-success py-2 small mb-2">
            <i class="bi bi-check2-circle"></i> Sesi aktif. Browser akan kirim
            posisi setiap ~5 detik. <b>Jangan tutup tab ini.</b>
          </div>
          <label class="form-label small mb-1">Tautan untuk dibagikan</label>
          <div class="input-group input-group-sm mb-2">
            <input id="shareUrl" class="form-control" readonly>
            <button class="btn btn-outline-secondary" type="button" id="btnCopy"><i class="bi bi-clipboard"></i></button>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a id="waShare" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            <a id="tgShare" target="_blank" class="btn btn-info btn-sm text-white"><i class="bi bi-telegram"></i> Telegram</a>
            <a id="smsShare" class="btn btn-secondary btn-sm"><i class="bi bi-chat-dots"></i> SMS</a>
            <button id="btnStop" class="btn btn-outline-danger btn-sm ms-auto"><i class="bi bi-stop-circle"></i> Hentikan</button>
          </div>
          <div id="liveStat" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>

    <!-- Kontak darurat -->
    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-people text-primary"></i> Kontak Darurat</h6>
        <form id="frmContact" class="row g-2 mb-2">
          <div class="col-6"><input class="form-control form-control-sm" name="nama" placeholder="Nama" required></div>
          <div class="col-6"><input class="form-control form-control-sm" name="nomor_wa" placeholder="No. WA (628…)"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="email" placeholder="Email"></div>
          <div class="col-6"><input class="form-control form-control-sm" name="relasi" placeholder="Relasi (Istri, Ibu, …)"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm w-100">+ Tambah Kontak</button></div>
        </form>
        <ul class="list-group list-group-flush small" id="lstContact">
          <?php foreach ($contacts as $c): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <div>
                <b><?= htmlspecialchars($c['nama']) ?></b>
                <?php if ($c['relasi']): ?><span class="text-muted">· <?= htmlspecialchars($c['relasi']) ?></span><?php endif; ?>
                <?php if ($c['nomor_wa']): ?><div class="text-muted">WA: <?= htmlspecialchars($c['nomor_wa']) ?></div><?php endif; ?>
              </div>
              <button class="btn btn-link text-danger btn-sm p-0" data-del="<?= (int)$c['id'] ?>"><i class="bi bi-trash"></i></button>
            </li>
          <?php endforeach; if (!$contacts): ?>
            <li class="list-group-item px-0 text-muted">Belum ada kontak.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- KANAN: Peta + riwayat -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <div id="liveMap" style="height:380px;border-radius:10px;border:1px solid var(--bs-border-color,#e5e7eb)"></div>
        <div class="small text-muted mt-2">
          <i class="bi bi-info-circle"></i>
          Peta menampilkan lintasan yang baru saja terkirim. Penerima tautan
          akan melihat tampilan serupa di halaman publik <code>/track_view.php</code>.
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="bi bi-clock-history"></i> Sesi Sebelumnya</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Judul</th><th>Mulai</th><th>Status</th><th class="text-end">Tautan</th></tr></thead>
            <tbody>
              <?php foreach ($mine as $m):
                $url = '/track_view.php?token='.urlencode($m['token']);
                $st  = $m['is_active']==='t' || $m['is_active']===true ? '<span class="badge bg-success">aktif</span>' : '<span class="badge bg-secondary">selesai</span>';
              ?>
              <tr>
                <td><?= htmlspecialchars($m['judul']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($m['started_at']) ?></td>
                <td><?= $st ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= $url ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Buka</a></td>
              </tr>
              <?php endforeach; if (!$mine): ?>
                <tr><td colspan="4" class="text-muted">Belum ada sesi.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API = '/api_live_tracking.php';
const map = L.map('liveMap').setView([-6.2, 106.8], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OSM'}).addTo(map);
let poly = L.polyline([], {color:'#dc2626', weight:5}).addTo(map);
let me   = null;
let state = { token:null, watchId:null, timer:null, pts:[] };

function setShareUI(url){
  document.getElementById('shareUrl').value = url;
  const txt = encodeURIComponent('Pantau lokasi saya secara langsung: '+url);
  document.getElementById('waShare').href  = 'https://wa.me/?text='+txt;
  document.getElementById('tgShare').href  = 'https://t.me/share/url?url='+encodeURIComponent(url)+'&text='+encodeURIComponent('Pantau lokasi saya secara langsung');
  document.getElementById('smsShare').href = 'sms:?&body='+txt;
}

document.getElementById('btnCopy').onclick = ()=>{
  const el=document.getElementById('shareUrl'); el.select(); document.execCommand('copy');
};

document.getElementById('frmStart').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action','start');
  const r = await fetch(API+'?action=start',{method:'POST', body:fd, credentials:'same-origin'});
  const j = await r.json();
  if(!j.ok){ alert('Gagal: '+(j.err||'?')); return; }
  state.token = j.token;
  setShareUI(j.url);
  document.getElementById('liveBox').classList.remove('d-none');
  startGeo();
});

document.getElementById('btnStop').onclick = async ()=>{
  if(!state.token) return;
  stopGeo();
  const fd = new FormData(); fd.append('token', state.token);
  await fetch(API+'?action=stop',{method:'POST', body:fd, credentials:'same-origin'});
  document.getElementById('liveStat').textContent = 'Sesi dihentikan.';
};

function startGeo(){
  if(!('geolocation' in navigator)){ alert('Browser tidak mendukung GPS.'); return; }
  state.watchId = navigator.geolocation.watchPosition(pushPoint, err=>{
    document.getElementById('liveStat').textContent = 'GPS error: '+err.message;
  }, {enableHighAccuracy:true, maximumAge:2000, timeout:15000});
}
function stopGeo(){
  if(state.watchId!=null) navigator.geolocation.clearWatch(state.watchId);
  state.watchId = null;
}
let lastSend = 0;
function pushPoint(pos){
  const {latitude:lat, longitude:lng, accuracy, speed, heading} = pos.coords;
  state.pts.push([lat,lng]); poly.setLatLngs(state.pts);
  if(!me){ me = L.marker([lat,lng]).addTo(map); map.setView([lat,lng], 16); } else { me.setLatLng([lat,lng]); }
  const now = Date.now();
  if (now - lastSend < 4500) return;            // throttle ~5 detik
  lastSend = now;
  const fd = new FormData();
  fd.append('token', state.token);
  fd.append('lat', lat); fd.append('lng', lng);
  if(accuracy!=null) fd.append('accuracy', accuracy);
  if(speed!=null)    fd.append('speed', speed);
  if(heading!=null)  fd.append('heading', heading);
  fetch(API+'?action=push',{method:'POST', body:fd}).then(r=>r.json()).then(j=>{
    document.getElementById('liveStat').textContent =
      (j.ok?'Terkirim ':'Gagal kirim ') + new Date().toLocaleTimeString();
  }).catch(()=>{});
}

/* ---------- Kontak darurat ---------- */
document.getElementById('frmContact').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target); fd.append('action','contact_add');
  const j = await (await fetch(API+'?action=contact_add',{method:'POST', body:fd, credentials:'same-origin'})).json();
  if(j.ok) location.reload(); else alert('Gagal: '+(j.err||'?'));
});
document.querySelectorAll('[data-del]').forEach(b=>{
  b.onclick = async ()=>{
    if(!confirm('Hapus kontak ini?')) return;
    const fd = new FormData(); fd.append('id', b.dataset.del);
    await fetch(API+'?action=contact_del',{method:'POST', body:fd, credentials:'same-origin'});
    location.reload();
  };
});
</script>

<?php include __DIR__.'/includes/bottom_nav.php'; include __DIR__.'/includes/footer.php'; ?>

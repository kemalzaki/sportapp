<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/islami_data.php';
require __DIR__.'/includes/islami_helpers.php';
send_security_headers(); require_login();
$pageTitle = 'Jumlah Kata Bahasa Arab dalam Al-Qur\'an';
require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Quran Kata');

/**
 * Statistik kata-kata populer dalam Al-Qur'an (jumlah kemunculan menurut riset klasik:
 * Mu'jam al-Mufahras li Alfazh al-Qur'an karya Muhammad Fu'ad Abdul Baqi).
 * Angka di bawah adalah perkiraan jumlah kemunculan akar/lema kata di Mushaf Utsmani.
 */
$POPULAR = [
  ['الله',     'Allah',                  'Nama Dzat Yang Maha Esa',                       2699],
  ['رب',       'Rabb',                   'Tuhan / Pengatur / Pemelihara',                  970],
  ['يوم',      'Yawm',                   'Hari (termasuk hari kiamat)',                    405],
  ['قلب',      'Qalb',                   'Hati',                                           132],
  ['دين',      'Diin',                   'Agama / pembalasan / ketaatan',                   92],
  ['ملك',      'Malik / Mulk',           'Raja / kerajaan / pemilik',                      206],
  ['نور',      'Nur',                    'Cahaya',                                          43],
  ['نار',      'Naar',                   'Api / Neraka',                                   145],
  ['جنة',      'Jannah',                 'Surga / kebun',                                  147],
  ['رحمن',     'Ar-Rahman',              'Yang Maha Pengasih',                              57],
  ['رحيم',     'Ar-Rahim',               'Yang Maha Penyayang',                            115],
  ['ناس',      'An-Naas',                'Manusia',                                        240],
  ['إنسان',    'Insaan',                 'Manusia (sebagai makhluk)',                       65],
  ['شيطان',    'Syaithan',               'Setan',                                           88],
  ['ملائكة',   'Malaikat',               'Para malaikat',                                   88],
  ['نبي',      'Nabiyy',                 'Nabi',                                            75],
  ['رسول',     'Rasul',                  'Utusan',                                         332],
  ['كتاب',     'Kitaab',                 'Kitab',                                          261],
  ['قرآن',     'Qur\'aan',               'Bacaan / Al-Qur\'an',                             70],
  ['صلاة',     'Shalat',                 'Shalat / doa',                                    99],
  ['زكاة',     'Zakat',                  'Zakat / kesucian harta',                          32],
  ['صوم/صيام', 'Shaum / Shiyam',         'Puasa',                                           14],
  ['حج',       'Hajj',                   'Haji',                                            12],
  ['جهاد',     'Jihad',                  'Bersungguh-sungguh di jalan Allah',               41],
  ['تقوى',     'Taqwa',                  'Ketakwaan / takut kepada Allah',                 258],
  ['إيمان',    'Iimaan',                 'Iman / kepercayaan',                             811],
  ['كفر',      'Kufr',                   'Kekafiran / pengingkaran',                       525],
  ['علم',      '\'Ilm',                  'Ilmu / pengetahuan',                             854],
  ['عمل',      '\'Amal',                 'Amal / pekerjaan',                               360],
  ['حق',       'Haqq',                   'Kebenaran / hak',                                287],
  ['باطل',     'Baathil',                'Kebatilan',                                       26],
  ['صبر',      'Shabr',                  'Sabar',                                          103],
  ['شكر',      'Syukr',                  'Syukur',                                          75],
  ['عدل',      '\'Adl',                  'Adil',                                            29],
  ['ظلم',      'Zhulm',                  'Aniaya / kezaliman',                             315],
  ['دنيا',     'Dunya',                  'Dunia',                                          115],
  ['آخرة',     'Akhirah',                'Akhirat',                                        115],
  ['موت',      'Mawt',                   'Kematian',                                       165],
  ['حياة',     'Hayaat',                 'Kehidupan',                                      145],
  ['سماء',     'Samaa\'',                'Langit',                                         310],
  ['أرض',      'Ardh',                   'Bumi',                                           461],
];

?>
<h4 class="mb-3"><i class="bi bi-hash text-warning"></i> Jumlah Kata Bahasa Arab dalam Al-Qur'an</h4>
<div class="alert alert-info small py-2">
  <i class="bi bi-info-circle"></i>
  Angka di bawah adalah perkiraan jumlah kemunculan akar/lema kata dalam Mushaf Utsmani
  berdasarkan riset klasik <em>Mu'jam al-Mufahras li Alfazh al-Qur'an</em> (Muhammad Fu'ad Abdul Baqi).
  Untuk verifikasi langsung, gunakan kolom pencarian di bawah — sistem akan menampilkan jumlah ayat
  yang mengandung kata tersebut dari Quran.com (Uthmani).
</div>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form id="hitungForm" class="row g-2">
    <div class="col-md-9">
      <input id="kata" class="form-control form-control-lg" placeholder="Ketik kata Arab, mis. رب, ملك, دين, نور" autofocus>
    </div>
    <div class="col-md-3"><button class="btn btn-warning btn-lg w-100"><i class="bi bi-calculator"></i> Hitung</button></div>
  </form>
  <div id="hasilHitung" class="mt-3"></div>
</div></div>

<div class="card shadow-sm">
  <div class="card-header"><i class="bi bi-bar-chart"></i> Kata-kata Populer & Jumlah Kemunculan</div>
  <div class="table-responsive">
  <table class="table table-hover mb-0 align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th style="font-family:'Amiri',serif;font-size:1.3rem">Arab</th>
        <th>Transliterasi</th>
        <th>Arti</th>
        <th class="text-end">Jumlah</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($POPULAR as $i => $row): ?>
      <tr>
        <td class="text-muted"><?= $i+1 ?></td>
        <td class="text-end" dir="rtl" style="font-family:'Amiri',serif;font-size:1.6rem"><?= htmlspecialchars($row[0]) ?></td>
        <td><strong><?= htmlspecialchars($row[1]) ?></strong></td>
        <td class="small"><?= htmlspecialchars($row[2]) ?></td>
        <td class="text-end"><span class="badge bg-success-subtle text-success fs-6"><?= number_format($row[3]) ?></span></td>
        <td><a href="/quran_search.php?mode=ayat" onclick="event.preventDefault();document.querySelector('a[href=\'?mode=ayat\']').click();" class="btn btn-sm btn-outline-secondary js-cari" data-kata="<?= htmlspecialchars($row[0]) ?>"><i class="bi bi-search"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
function esc(t){return (t||'').toString().replace(/[<>&]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;' }[c]));}

document.querySelectorAll('.js-cari').forEach(function(b){
  b.addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('kata').value = b.dataset.kata;
    document.getElementById('hitungForm').dispatchEvent(new Event('submit'));
    window.scrollTo({top:0, behavior:'smooth'});
  });
});

document.getElementById('hitungForm').addEventListener('submit', async function(e){
  e.preventDefault();
  var q = document.getElementById('kata').value.trim();
  var box = document.getElementById('hasilHitung');
  if (!q) { box.innerHTML=''; return; }
  box.innerHTML = '<div class="text-muted"><div class="spinner-border spinner-border-sm"></div> Menghitung dari Quran.com…</div>';

  async function safeJson(url){
    var r = await fetch(url, {headers:{'Accept':'application/json'}});
    var txt = await r.text();
    if (!txt) throw new Error('Server mengembalikan respons kosong (HTTP '+r.status+')');
    try { return JSON.parse(txt); }
    catch(e){ throw new Error('Respons bukan JSON valid (HTTP '+r.status+')'); }
  }

  try {
    // Endpoint search Quran.com kadang mengembalikan body kosong / non-JSON.
    // Pakai parser aman + fallback ke endpoint alternatif.
    var j = null, lastErr = null;
    var urls = [
      'https://api.quran.com/api/v4/search?q='+encodeURIComponent(q)+'&size=20&language=id',
      'https://api.quran.com/api/v4/search?q='+encodeURIComponent(q)+'&size=20&language=ar',
      'https://api.qurancdn.com/api/qdc/search?q='+encodeURIComponent(q)+'&size=20&language=id'
    ];
    for (var i=0;i<urls.length;i++){
      try { j = await safeJson(urls[i]); if (j) break; }
      catch(e){ lastErr = e; }
    }
    if (!j) throw (lastErr || new Error('Tidak ada respons dari server'));

    var total = (j.search && j.search.total_results) || (j.pagination && j.pagination.total_records) || 0;
    var sample = (j.search && j.search.results) || j.results || [];
    var html = '<div class="alert alert-success py-2 mb-2"><i class="bi bi-check2-circle"></i> Kata <strong style="font-family:Amiri">'+esc(q)+'</strong> terdapat dalam ± <strong>'+total+'</strong> ayat (hasil pencarian Quran.com).</div>';
    if (sample.length){
      html += '<div class="small text-muted mb-1">Contoh ayat:</div><ul class="list-group">';
      sample.slice(0,10).forEach(function(h){
        var k = (h.verse_key||'').split(':');
        html += '<li class="list-group-item"><a href="/quran_surah.php?s='+k[0]+'#a'+k[1]+'" class="text-decoration-none"><span class="badge bg-success me-1">QS '+k[0]+':'+k[1]+'</span></a><span class="text-end d-block" dir="rtl" style="font-family:Amiri;font-size:1.3rem">'+esc(h.text||'')+'</span></li>';
      });
      html += '</ul>';
    } else if (total === 0) {
      html += '<div class="small text-muted">Tidak ada contoh ayat yang dikembalikan API.</div>';
    }
    box.innerHTML = html;
  } catch(err){
    box.innerHTML = '<div class="alert alert-danger small">Gagal menghitung: '+esc(err.message)+'. Coba lagi beberapa saat, atau periksa koneksi internet.</div>';
  }
});
</script>
<?php htmx_layout_end(); ?>

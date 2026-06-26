<?php
// tajwid.php — Belajar Tajwid (Revisi R17 - 26 Juni 2026)
// R17: Setiap hukum tajwid dibungkus accordion (spoiler) agar halaman tidak memanjang ke bawah.
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$pageTitle = 'Belajar Tajwid';
$u = current_user();

// Auto-migration opsional untuk progres
try {
  db_exec("CREATE TABLE IF NOT EXISTS tajwid_progress (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    hukum VARCHAR(60) NOT NULL,
    dipelajari BOOLEAN NOT NULL DEFAULT false,
    catatan TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE(user_id, hukum)
  )");
} catch (Throwable $e) {}

/* Toggle progres */
if ($u && $_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  $hukum = substr(trim($_POST['hukum'] ?? ''), 0, 60);
  $on    = !empty($_POST['on']);
  if ($hukum !== '') {
    db_exec("INSERT INTO tajwid_progress(user_id,hukum,dipelajari,updated_at) VALUES($1,$2,$3,now())
             ON CONFLICT (user_id,hukum) DO UPDATE SET dipelajari=EXCLUDED.dipelajari, updated_at=now()",
             [(int)$u['id'], $hukum, $on]);
  }
  header('Location: /tajwid.php#h'.md5($hukum)); exit;
}

$progress = [];
if ($u) {
  foreach (db_all("SELECT hukum, dipelajari FROM tajwid_progress WHERE user_id=$1",[(int)$u['id']]) as $p) {
    $progress[$p['hukum']] = $p['dipelajari'] === true || $p['dipelajari'] === 't' || $p['dipelajari'] === '1';
  }
}

$MATERI = [
  [
    'kategori'=>'Hukum Nun Sukun & Tanwin',
    'items'=>[
      ['nama'=>'Izhar Halqi','def'=>'Membaca nun sukun/tanwin dengan jelas tanpa dengung.','huruf'=>'ء ه ع ح غ خ','contoh'=>'مِنْ هَادٍ (min haadin)','tips'=>'Hafal 6 huruf halqi (tenggorokan).'],
      ['nama'=>'Idgham Bighunnah','def'=>'Memasukkan nun/tanwin ke huruf berikutnya disertai dengung 2 harakat.','huruf'=>'ي ن م و','contoh'=>'مَنْ يَّقُولُ (may yaquul)','tips'=>'Singkatan: "YANMU".'],
      ['nama'=>'Idgham Bilaghunnah','def'=>'Idgham tanpa dengung.','huruf'=>'ل ر','contoh'=>'مِنْ رَّبِّهِمْ (mir rabbihim)','tips'=>'Hanya 2 huruf: Lam & Ra.'],
      ['nama'=>'Iqlab','def'=>'Mengubah nun/tanwin menjadi mim dengan dengung.','huruf'=>'ب','contoh'=>'مِنْۢ بَعْدِ (mim ba\'di)','tips'=>'Ada tanda mim kecil di atas nun.'],
      ['nama'=>'Ikhfa Haqiqi','def'=>'Menyamarkan nun/tanwin disertai dengung 2 harakat.','huruf'=>'15 huruf selain di atas','contoh'=>'مِنْ قَبْلُ (min qoblu)','tips'=>'Hampir semua huruf lain → ikhfa.'],
    ],
  ],
  [
    'kategori'=>'Hukum Mim Sukun',
    'items'=>[
      ['nama'=>'Ikhfa Syafawi','def'=>'Menyamarkan mim sukun ketika bertemu ب dengan dengung.','huruf'=>'ب','contoh'=>'تَرْمِيْهِمْ بِحِجَارَةٍ','tips'=>'Mulut tetap menutup ringan.'],
      ['nama'=>'Idgham Mimi (Mutamatsilain)','def'=>'Mim sukun bertemu mim → dimasukkan dengan dengung.','huruf'=>'م','contoh'=>'لَهُمْ مَّا','tips'=>'Dengung sekitar 2 harakat.'],
      ['nama'=>'Izhar Syafawi','def'=>'Mim sukun dibaca jelas bila bertemu huruf selain ب dan م.','huruf'=>'24 huruf lain','contoh'=>'هُمْ فِيْهَا','tips'=>'Jaga agar bibir tidak menempel terlalu lama.'],
    ],
  ],
  [
    'kategori'=>'Hukum Mad (Panjang)',
    'items'=>[
      ['nama'=>'Mad Thabi\'i','def'=>'Mad asli, dibaca 2 harakat.','huruf'=>'ا و ي (sukun setelah harakat sejenis)','contoh'=>'قَالَ، يَقُوْلُ، قِيْلَ','tips'=>'Dasar dari semua jenis mad.'],
      ['nama'=>'Mad Wajib Muttashil','def'=>'Mad bertemu hamzah dalam SATU kata, panjang 4-5 harakat.','huruf'=>'ء setelah mad','contoh'=>'جَآءَ، السَّمَآءِ','tips'=>'Wajib panjang, tidak boleh pendek.'],
      ['nama'=>'Mad Jaiz Munfashil','def'=>'Mad di akhir kata bertemu hamzah di awal kata berikutnya. 2/4/5 harakat.','huruf'=>'ء di kata berikut','contoh'=>'يَا أَيُّهَا','tips'=>'Boleh pendek atau panjang, ikut riwayat qira\'ah.'],
      ['nama'=>'Mad Lazim','def'=>'Wajib panjang 6 harakat karena bertemu huruf bersukun asli atau bertasydid.','huruf'=>'-','contoh'=>'الٓمٓ، الْحَآقَّةُ','tips'=>'Konsisten 6 harakat.'],
    ],
  ],
  [
    'kategori'=>'Hukum Lain',
    'items'=>[
      ['nama'=>'Qalqalah','def'=>'Memantulkan suara pada huruf qalqalah saat sukun/waqaf.','huruf'=>'ق ط ب ج د (QOTBUJADIN)','contoh'=>'يَخْلُقْ، أَحَدٌ','tips'=>'Qalqalah Sughra (di tengah) lebih ringan dari Kubra (di akhir).'],
      ['nama'=>'Lam Jalalah','def'=>'Lam pada lafadz "Allah": tafkhim (tebal) bila didahului fathah/dhammah; tarqiq (tipis) bila didahului kasrah.','huruf'=>'الله','contoh'=>'قَالَ اللَّهُ (tebal); بِسْمِ اللَّهِ (tipis)','tips'=>'Perhatikan harakat huruf sebelum lam.'],
      ['nama'=>'Ra Tafkhim & Tarqiq','def'=>'Ra dibaca tebal (tafkhim) atau tipis (tarqiq) sesuai harakat sebelum/sesudah.','huruf'=>'ر','contoh'=>'الرَّحْمٰن (tebal); رِزْقًا (tipis)','tips'=>'Default: fathah/dhammah → tebal, kasrah → tipis.'],
      ['nama'=>'Waqaf & Ibtida','def'=>'Aturan berhenti & memulai bacaan agar makna tetap utuh.','huruf'=>'tanda waqaf di mushaf','contoh'=>'ﻢ (waqaf lazim), ﻻ (jangan berhenti)','tips'=>'Hafal 7 tanda waqaf umum di mushaf.'],
    ],
  ],
];

$total = 0; foreach($MATERI as $g) $total += count($g['items']);
$done = 0; foreach($progress as $v) if ($v) $done++;

include __DIR__.'/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="/index.php">Beranda</a></li>
    <li class="breadcrumb-item"><a href="/islami.php">Islami</a></li>
    <li class="breadcrumb-item active">Belajar Tajwid</li>
  </ol>
</nav>

<h2 class="mb-2"><i class="bi bi-mic-fill text-success"></i> Belajar Tajwid</h2>
<p class="text-muted small">Ringkasan hukum tajwid dasar untuk membaca Al-Qur'an dengan benar. Klik tiap hukum untuk membuka detail (spoiler). Tandai materi yang sudah dipelajari untuk memantau progres.</p>

<?php if($u): ?>
<div class="alert alert-success py-2 small d-flex align-items-center gap-2">
  <i class="bi bi-graph-up"></i>
  <div>Progres belajar Anda: <strong><?= $done ?>/<?= $total ?></strong> materi</div>
  <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-success" style="width:<?= $total? round(100*$done/$total):0 ?>%"></div></div>
</div>
<?php endif; ?>

<?php foreach($MATERI as $gi=>$grp): ?>
  <h5 class="mt-4 mb-2"><i class="bi bi-bookmark-check text-primary"></i> <?= htmlspecialchars($grp['kategori']) ?></h5>
  <div class="accordion mb-2" id="accTajwid<?= $gi ?>">
    <?php foreach($grp['items'] as $ii=>$m):
      $checked = !empty($progress[$m['nama']]);
      $hid = 'tj_'.$gi.'_'.$ii;
    ?>
      <div class="accordion-item <?= $checked?'border-success':'' ?>" id="h<?= md5($m['nama']) ?>">
        <h2 class="accordion-header" id="head_<?= $hid ?>">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#col_<?= $hid ?>" aria-expanded="false" aria-controls="col_<?= $hid ?>">
            <span class="me-2"><?= $checked? '<i class="bi bi-check2-circle text-success"></i>' : '<i class="bi bi-circle text-secondary"></i>' ?></span>
            <strong><?= htmlspecialchars($m['nama']) ?></strong>
            <?php if($checked): ?><span class="badge bg-success ms-2">Sudah dipelajari</span><?php endif; ?>
          </button>
        </h2>
        <div id="col_<?= $hid ?>" class="accordion-collapse collapse"
             aria-labelledby="head_<?= $hid ?>" data-bs-parent="#accTajwid<?= $gi ?>">
          <div class="accordion-body">
            <p class="small mb-2"><?= htmlspecialchars($m['def']) ?></p>
            <div class="small"><strong>Huruf:</strong> <span dir="rtl" style="font-family:'Amiri',serif;font-size:1.2rem"><?= htmlspecialchars($m['huruf']) ?></span></div>
            <div class="small mt-1"><strong>Contoh:</strong> <span dir="rtl" style="font-family:'Amiri',serif;font-size:1.2rem"><?= htmlspecialchars($m['contoh']) ?></span></div>
            <div class="small text-muted mt-1"><i class="bi bi-lightbulb"></i> <?= htmlspecialchars($m['tips']) ?></div>
            <?php if($u): ?>
              <form method="post" class="mt-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="hukum" value="<?= htmlspecialchars($m['nama']) ?>">
                <input type="hidden" name="on" value="<?= $checked?'0':'1' ?>">
                <button class="btn btn-sm <?= $checked?'btn-success':'btn-outline-success' ?>">
                  <i class="bi <?= $checked?'bi-check2-circle':'bi-circle' ?>"></i>
                  <?= $checked?'Batal tandai':'Tandai sudah dipelajari' ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="alert alert-info small mt-4">
  <i class="bi bi-info-circle"></i> Untuk latihan langsung dengan ayat, gunakan <a href="/quran.php">Al-Qur'an Digital</a> dan praktikkan hukum-hukum di atas pada setiap ayat.
</div>

<?php include __DIR__.'/includes/footer.php'; ?>

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/info_publik.php';
send_security_headers(); enforce_session_timeout();
require_login();
$pageTitle = 'Kesehatan & Obat Herbal';
$pageSkeleton = 'list';
$u = current_user(); $uid = (int)$u['id'];

// Revisi 18 Juni 2026 — tabel Q&A AI Doctor (idempotent, sharing tabel dgn ai_health)
try {
    db_exec("CREATE TABLE IF NOT EXISTS health_qa_saved (
        id BIGSERIAL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        kategori VARCHAR(20) NOT NULL DEFAULT 'health',
        pertanyaan TEXT NOT NULL,
        jawaban TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT now()
    )");
    db_exec("CREATE INDEX IF NOT EXISTS health_qa_user_idx ON health_qa_saved(user_id, kategori, created_at DESC)");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    header('Content-Type: application/json');
    $a = $_POST['_action'] ?? '';
    if ($a === 'qa_save') {
        $q = trim((string)($_POST['pertanyaan'] ?? ''));
        $j = trim((string)($_POST['jawaban'] ?? ''));
        if ($q==='' || $j==='') { echo json_encode(['ok'=>false,'err'=>'kosong']); exit; }
        if (mb_strlen($q)>4000) $q = mb_substr($q,0,4000);
        if (mb_strlen($j)>20000) $j = mb_substr($j,0,20000);
        $r = pg_query_params(db(), "INSERT INTO health_qa_saved(user_id,kategori,pertanyaan,jawaban) VALUES($1,'doctor',$2,$3) RETURNING id",
            [$uid,$q,$j]);
        $id = (int)(pg_fetch_row($r)[0] ?? 0);
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    } elseif ($a === 'qa_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) db_exec("DELETE FROM health_qa_saved WHERE id=$1 AND user_id=$2 AND kategori='doctor'",[$id,$uid]);
        echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'err'=>'unknown']); exit;
}

$qaSaved = db_all("SELECT id, pertanyaan, jawaban, created_at FROM health_qa_saved
                   WHERE user_id=$1 AND kategori='doctor' ORDER BY id DESC LIMIT 50", [$uid]);

/**
 * Catatan: Tidak ada API publik gratis terstandar untuk obat herbal Indonesia.
 * Data di bawah adalah ringkasan edukatif dari sumber publik (Kemenkes RI,
 * Badan POM, jurnal herbal). Bukan pengganti konsultasi dokter.
 */

$PENYAKIT = [
  [
    'nama'=>'Batuk & Pilek (ISPA ringan)','icon'=>'bi-emoji-frown',
    'gejala'=>'Hidung tersumbat, batuk, demam ringan, tenggorokan gatal.',
    'pencegahan'=>'Cuci tangan, masker saat sakit, istirahat cukup, perbanyak cairan.',
    'herbal'=>[
      ['Jahe','Anti-inflamasi & menghangatkan tenggorokan. Seduh 2 ruas jahe + madu.'],
      ['Madu murni','Meredakan batuk; 1 sdm sebelum tidur (>1 thn).'],
      ['Kencur + Beras (Beras Kencur)','Meredakan batuk berdahak ringan.'],
    ],
  ],
  [
    'nama'=>'Maag / Asam Lambung (GERD)','icon'=>'bi-cup-hot',
    'gejala'=>'Perih ulu hati, kembung, mual, sendawa, dada terbakar.',
    'pencegahan'=>'Makan teratur porsi kecil, hindari pedas/asam/kafein, jangan langsung tidur setelah makan.',
    'herbal'=>[
      ['Kunyit','Kurkumin melindungi mukosa lambung. Kunyit asam 1×/hari.'],
      ['Lidah Buaya','Gel 1 sdm sebelum makan menenangkan lambung.'],
      ['Adas (Foeniculum)','Mengurangi kembung dan gas.'],
    ],
  ],
  [
    'nama'=>'Hipertensi (Darah Tinggi)','icon'=>'bi-heart-pulse',
    'gejala'=>'Sakit kepala belakang, tengkuk berat, mudah lelah, tekanan ≥ 140/90.',
    'pencegahan'=>'Kurangi garam <5 g/hari, olahraga 30 menit/hari, kelola stres, cek tensi rutin.',
    'herbal'=>[
      ['Seledri','Daun seledri rebus 1 gelas pagi; menurunkan tekanan ringan.'],
      ['Bawang Putih','1-2 siung mentah/hari membantu vasodilatasi.'],
      ['Mengkudu (Noni)','Jus mengkudu rutin (uji klinis terbatas).'],
    ],
  ],
  [
    'nama'=>'Diabetes Melitus Tipe 2','icon'=>'bi-droplet',
    'gejala'=>'Sering haus, sering kencing, berat badan turun, luka lama sembuh.',
    'pencegahan'=>'Batasi gula & karbo olahan, olahraga, jaga BMI <25, cek GDP rutin.',
    'herbal'=>[
      ['Daun Insulin (Yacon)','Membantu menurunkan gula darah pasca makan.'],
      ['Kayu Manis','½ sdt/hari memperbaiki sensitivitas insulin.'],
      ['Pare','Jus pare pagi hari; pahit tapi membantu turunkan glukosa.'],
    ],
  ],
  [
    'nama'=>'Kolesterol Tinggi','icon'=>'bi-egg-fried',
    'gejala'=>'Sering tanpa gejala; bisa pegal tengkuk & xantelasma kelopak mata.',
    'pencegahan'=>'Kurangi gorengan & jeroan, perbanyak serat (oat, sayur), olahraga aerobik.',
    'herbal'=>[
      ['Bawang Putih','Menurunkan LDL dan trigliserida.'],
      ['Daun Salam','Rebusan 7 lembar daun salam, minum 1×/hari.'],
      ['Teh Hijau','EGCG membantu menurunkan kolesterol.'],
    ],
  ],
  [
    'nama'=>'Asam Urat (Gout)','icon'=>'bi-bandaid',
    'gejala'=>'Nyeri & bengkak pada jempol kaki, lutut; sering malam hari.',
    'pencegahan'=>'Hindari jeroan, melinjo, bayam, kacang; cukup air putih ≥ 2 L.',
    'herbal'=>[
      ['Sambiloto','Anti-inflamasi alami untuk nyeri sendi.'],
      ['Daun Kelor','Tinggi antioksidan, bantu turunkan radang.'],
      ['Jahe Merah','Kompres hangat & wedang jahe meredakan nyeri.'],
    ],
  ],
  [
    'nama'=>'Diare','icon'=>'bi-droplet-half',
    'gejala'=>'BAB cair >3×/hari, kram perut, lemas, dehidrasi.',
    'pencegahan'=>'Cuci tangan, masak air, makanan bersih, oralit bila perlu.',
    'herbal'=>[
      ['Daun Jambu Biji','Rebus 5 lembar; tanin membantu menghentikan diare.'],
      ['Kunyit','Antibakteri ringan untuk gangguan pencernaan.'],
      ['Pisang Kepok','Pektin alami memadatkan tinja.'],
    ],
  ],
  [
    'nama'=>'Insomnia & Stres','icon'=>'bi-moon-stars',
    'gejala'=>'Sulit tidur, sering terbangun, mudah cemas, lelah siang hari.',
    'pencegahan'=>'Jadwal tidur tetap, kurangi gadget malam, olahraga sore, meditasi/dzikir.',
    'herbal'=>[
      ['Chamomile','Teh chamomile sebelum tidur menenangkan.'],
      ['Akar Valerian','Membantu durasi tidur (jangan jangka panjang).'],
      ['Lavender (aromaterapi)','Menurunkan kecemasan & memperbaiki tidur.'],
    ],
  ],
];

include __DIR__.'/includes/header.php'; ?>

<?php ip_card_open('Kesehatan: Penyakit Umum & Obat Herbal', 'bi-heart-pulse'); ?>

<div class="alert alert-info small">
  <i class="bi bi-info-circle"></i> Informasi ini bersifat <strong>edukatif</strong> (sumber: Kemenkes RI, Badan POM, jurnal herbal).
  Bukan pengganti pemeriksaan dokter — segera konsultasi bila gejala berat atau menetap.
</div>

<?php
// Revisi 18 Juni 2026 — Widget AI Doctor Tanya Jawab
$aiTitle = 'AI Doctor — Tanya Jawab Penyakit & Obat Herbal';
$aiTask = 'ai_doctor';
$aiColor = 'success';
$aiIcon = 'bi-prescription2';
$aiPlaceholder = 'Contoh: Tanaman herbal apa yang baik untuk maag/asam lambung? Atau: Bagaimana mengelola hipertensi ringan secara alami?';
$aiPostUrl = '/kesehatan.php';
$aiSaved = $qaSaved;
$aiKey = 'aiDoctor';
$aiDisclaim = 'Jawaban AI bersifat edukatif — bukan pengganti konsultasi dokter, dan jangan menggantikan obat resep tanpa anjuran dokter.';
include __DIR__.'/includes/ai_qa_widget.php';
?>

<div class="row g-3">
  <?php foreach($PENYAKIT as $p): ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
          <h2 class="h5 mb-2"><i class="bi <?= $p['icon'] ?> text-danger"></i> <?= htmlspecialchars($p['nama']) ?></h2>
          <p class="small mb-1"><strong>Gejala:</strong> <?= htmlspecialchars($p['gejala']) ?></p>
          <p class="small mb-2"><strong>Pencegahan:</strong> <?= htmlspecialchars($p['pencegahan']) ?></p>
          <div class="border-top pt-2 mt-2">
            <div class="small fw-semibold mb-1"><i class="bi bi-flower1 text-success"></i> Rekomendasi Herbal</div>
            <ul class="mb-0 small ps-3">
              <?php foreach($p['herbal'] as $h): ?>
                <li><strong><?= htmlspecialchars($h[0]) ?></strong> — <?= htmlspecialchars($h[1]) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__.'/includes/bottom_nav.php'; ?>
<?php include __DIR__.'/includes/footer.php'; ?>

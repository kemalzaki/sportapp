<?php
/**
 * Revisi 4 Juni 2026
 *   #1 Tabel hasil "Cek Pesanan Saya" mendapat tombol DETAIL PESANAN
 *      (modal berisi rincian item, ongkir, kurir + nomor telpon kurir).
 *   #2 Tombol "Lacak Driver" tetap (sudah ada) — ikon pemesan/kurir didesain.
 *   #3 Preloader fullscreen good-looking (animasi cup + skeleton).
 *   #4 Fitur RATING bintang per pesanan untuk status=selesai.
 *   #5 "Pesan Sekarang" sekarang membuka modal TOKO berisi semua produk
 *      toko tersebut + input jumlah di samping tiap produk (multi-item).
 *   #6 Efek-efek tambahan: shimmer skeleton, hover-lift kartu, micro
 *      animations, gradient halus, badge berdenyut, dsb.
 *
 * Revisi 31 Mei 2026 — PPN 11%, mobile col-6, lightbox, wajib lokasi.
 */
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/app_settings.php';
require __DIR__.'/includes/invoice_email.php';
send_security_headers();
date_default_timezone_set('Asia/Jakarta');
$pageTitle = 'Pesan Jajanan Favorit';
$pageSkeleton = 'grid'; // Skeleton sesuai data: grid produk
$u = current_user();

$ADMIN_WA_FIRDAM = getenv('ADMIN_WA_FIRDAM') ?: '6281386369207';

/* ---------- Midtrans ---------- */
$MT_SERVER_KEY = getenv('MIDTRANS_SERVER_KEY') ?: '';
$MT_CLIENT_KEY = getenv('MIDTRANS_CLIENT_KEY') ?: '';
$MT_PROD       = (bool) (getenv('MIDTRANS_PROD') ?: false);
$MT_BASE       = $MT_PROD ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
$MT_API_BASE   = $MT_PROD ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
$MT_SNAP_JS    = $MT_PROD
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';

/* ---------- Konfigurasi ongkir & pajak ---------- */
$UIN_LAT = -6.926263;
$UIN_LNG = 107.717553;
$UIN_R_REKOM_KM = 1.5;
$UIN_R_MAX_KM   = 3.0;
$ONGKIR_BASE    = 3000;
$ONGKIR_PER_KM  = 2000;
$ONGKIR_FALLBACK = 5000;
$PER_PAGE       = 6;
$PPN_RATE       = 0.11; // PPN 11% (UU HPP)

/* Biaya admin Midtrans (potongan) — ditanggung pembeli.
 * Default mengikuti tarif umum Midtrans Snap (VA/QRIS):
 *   - Fixed Rp 4.000 / transaksi  (mis. VA BCA/BNI/BRI/Mandiri)
 *   - + 0.7% (QRIS) bila dipakai. Bisa diset 0 untuk dimatikan.
 * Bisa di-override via env: MIDTRANS_FEE_FIXED, MIDTRANS_FEE_PCT.
 */
// Revisi 2 Jun 2026: prioritas pengaturan dari /admin/biaya.php (tabel app_settings)
$MIDTRANS_FEE_FIXED = app_setting_int('biaya_admin_fixed', (int)(getenv('MIDTRANS_FEE_FIXED') ?: 4000));
$MIDTRANS_FEE_PCT   = app_setting_float('biaya_admin_pct', (float)(getenv('MIDTRANS_FEE_PCT') ?: 0.007));
$APP_FEE_FIXED      = app_setting_int('biaya_aplikasi_fixed', 1000);
$APP_FEE_PCT        = app_setting_float('biaya_aplikasi_pct', 0.0);

function jjn_haversine($lat1,$lng1,$lat2,$lng2){
    $R=6371000; $toRad=M_PI/180;
    $dLat=($lat2-$lat1)*$toRad; $dLng=($lng2-$lng1)*$toRad;
    $s=sin($dLat/2)**2 + cos($lat1*$toRad)*cos($lat2*$toRad)*sin($dLng/2)**2;
    return 2*$R*asin(sqrt($s));
}
function jjn_ongkir_from_dist_m($d, $base, $perKm){ return (int) round($base + ($d/1000.0) * $perKm); }

function jjn_normalize_phone($raw){
    $s = preg_replace('/\D+/','', (string)$raw);
    if ($s === '') return '';
    if (strpos($s,'62') === 0) return $s;
    if (strpos($s,'0')  === 0) return '62' . substr($s,1);
    return '62' . $s;
}

/** Cek apakah toko sedang buka berdasarkan jam_buka/jam_tutup (format HH:MM:SS) dan jam sekarang.
 *  Bila salah satu kosong, dianggap selalu buka. Mendukung jadwal lewat tengah malam (mis. 22:00–02:00). */
function jjn_is_open($jamBuka, $jamTutup, $now = null, $hariBuka = null) {
    // Cek hari (0=Minggu .. 6=Sabtu). Kosong/null = setiap hari.
    if (!empty($hariBuka)) {
        $today = (int) date('w');
        $allow = array_map('intval', array_filter(explode(',', (string)$hariBuka), 'is_numeric'));
        if ($allow && !in_array($today, $allow, true)) return false;
    }
    if (empty($jamBuka) || empty($jamTutup)) return true;
    $now = $now ?: date('H:i:s');
    if ($jamBuka <= $jamTutup) return ($now >= $jamBuka && $now <= $jamTutup);
    return ($now >= $jamBuka || $now <= $jamTutup);
}

function mt_snap_request(array $payload, $serverKey, $base) {
    $url = rtrim($base,'/') . '/snap/v1/transactions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($serverKey.':'),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException('Midtrans cURL: '.$err); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($resp, true);
    if ($code >= 400 || empty($j['token'])) {
        throw new RuntimeException('Midtrans error: '.($j['error_messages'][0] ?? $resp));
    }
    return $j;
}

function mt_status_request($orderId, $serverKey, $apiBase) {
    $url = rtrim($apiBase,'/') . '/v2/' . rawurlencode($orderId) . '/status';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($serverKey.':'),
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    return json_decode($resp, true) ?: [];
}

/* ============================================================
 * AJAX endpoints (JSON)
 * ============================================================ */
$ajax = $_GET['ajax'] ?? '';

if ($ajax === 'create_snap' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $jidLegacy = (int)($_POST['jajanan_id'] ?? 0);
        $qtyLegacy = max(1,(int)($_POST['qty'] ?? 1));
        $itemsRaw  = $_POST['items'] ?? '';
        $items     = [];
        if ($itemsRaw !== '') {
            $tmp = json_decode($itemsRaw, true);
            if (!is_array($tmp)) throw new RuntimeException('Format items tidak valid.');
            foreach ($tmp as $it) {
                $iid = (int)($it['id'] ?? 0);
                $iqt = max(1,(int)($it['qty'] ?? 0));
                if ($iid>0 && $iqt>0) $items[] = ['id'=>$iid, 'qty'=>$iqt];
            }
        } elseif ($jidLegacy>0) {
            $items[] = ['id'=>$jidLegacy, 'qty'=>$qtyLegacy];
        }
        if (!$items) throw new RuntimeException('Tidak ada produk yang dipilih.');

        $nama   = substr(trim($_POST['nama'] ?? ''),0,120);
        $no_wa  = jjn_normalize_phone($_POST['no_wa'] ?? '');
        $alamat = substr(trim($_POST['alamat'] ?? ''),0,500);
        $catat  = substr(trim($_POST['catatan'] ?? ''),0,500);
        $plat   = ($_POST['pickup_lat'] ?? '') !== '' ? (float)$_POST['pickup_lat'] : null;
        $plng   = ($_POST['pickup_lng'] ?? '') !== '' ? (float)$_POST['pickup_lng'] : null;
        if ($nama==='' || $no_wa==='' || $alamat==='') {
            throw new RuntimeException('Nama, nomor WA, dan alamat wajib diisi.');
        }
        if ($plat === null || $plng === null) {
            throw new RuntimeException('Mohon klik "Deteksi Lokasi Saya" terlebih dahulu sebelum membayar via Midtrans.');
        }

        // Ambil & validasi semua produk
        $resolved = [];
        $sub = 0;
        $itemNamesForOrder = [];
        foreach ($items as $it) {
            $j = db_one("SELECT id,nama,harga,stok,aktif,jam_buka,jam_tutup,toko_id,hari_buka FROM jajanan WHERE id=$1",[$it['id']]);
            if (!$j || !($j['aktif']==='t'||$j['aktif']===true)) throw new RuntimeException('Produk tidak tersedia ('.$it['id'].').');
            if (!jjn_is_open($j['jam_buka'] ?? null, $j['jam_tutup'] ?? null, null, $j['hari_buka'] ?? null)) {
                $jb = $j['jam_buka'] ? substr($j['jam_buka'],0,5) : '-';
                $jt = $j['jam_tutup']? substr($j['jam_tutup'],0,5): '-';
                throw new RuntimeException("Toko sedang tutup untuk \"{$j['nama']}\" (jam {$jb}–{$jt}).");
            }
            $q = min($it['qty'], max(0,(int)$j['stok']));
            if ($q<=0) throw new RuntimeException('Stok habis untuk "'.$j['nama'].'".');
            $resolved[] = ['j'=>$j, 'qty'=>$q];
            $sub += $q * (int)$j['harga'];
            $itemNamesForOrder[] = $q.'× '.$j['nama'];
        }

        $dist = jjn_haversine($UIN_LAT,$UIN_LNG,$plat,$plng);
        if ($dist/1000 > $UIN_R_MAX_KM) throw new RuntimeException('Lokasi diluar jangkauan layanan (>'.$UIN_R_MAX_KM.' km).');
        $ongkir = jjn_ongkir_from_dist_m($dist, $ONGKIR_BASE, $ONGKIR_PER_KM);

        $ppn      = (int) round($sub * $PPN_RATE);
        $feeAdmin = (int) round(($sub + $ppn + $ongkir) * $MIDTRANS_FEE_PCT) + (int)$MIDTRANS_FEE_FIXED;
        $feeApp   = (int) round(($sub + $ppn + $ongkir) * $APP_FEE_PCT) + (int)$APP_FEE_FIXED;
        $total    = $sub + $ppn + $ongkir + $feeAdmin + $feeApp;

        $kode = 'JJN-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        // Revisi 2 Jun 2026: simpan biaya_admin, biaya_aplikasi, email_pemesan
        $email_pemesan = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        db_exec("INSERT INTO jajanan_pesanan(kode,nama_pemesan,no_wa,alamat,catatan,subtotal,ongkir,total,metode,status,pickup_lat,pickup_lng,midtrans_order_id,payment_status,biaya_admin,biaya_aplikasi,email_pemesan)
                 VALUES($1,$2,$3,$4,$5,$6,$7,$8,'midtrans','pending_payment',$9,$10,$11,'pending',$12,$13,$14)",
          [$kode,$nama,$no_wa,$alamat,$catat?:null,$sub,$ongkir,$total,$plat,$plng,$kode,$feeAdmin,$feeApp,$email_pemesan]);
        $pid = (int) db_val("SELECT id FROM jajanan_pesanan WHERE kode=$1",[$kode]);
        $itemDetails = [];
        foreach ($resolved as $r) {
            $j = $r['j']; $q = $r['qty'];
            db_exec("INSERT INTO jajanan_pesanan_item(pesanan_id,jajanan_id,nama,harga,qty) VALUES($1,$2,$3,$4,$5)",
              [$pid,(int)$j['id'],$j['nama'],(int)$j['harga'],$q]);
            $itemDetails[] = ['id'=>'JJN-'.$j['id'], 'price'=>(int)$j['harga'], 'quantity'=>$q, 'name'=>substr($j['nama'],0,50)];
        }

        if ($MT_SERVER_KEY === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum disetel di environment. Hubungi admin untuk mengaktifkan pembayaran.');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $finish_url = $scheme.'://'.$host.'/jajanan.php?berhasil='.urlencode($kode);

        $itemDetails[] = ['id'=>'PPN11',  'price'=>(int)$ppn,      'quantity'=>1, 'name'=>'PPN 11%'];
        $itemDetails[] = ['id'=>'ONGKIR', 'price'=>(int)$ongkir,   'quantity'=>1, 'name'=>'Ongkir'];
        $itemDetails[] = ['id'=>'ADMIN',  'price'=>(int)$feeAdmin, 'quantity'=>1, 'name'=>'Biaya Admin Midtrans'];
        $itemDetails[] = ['id'=>'APPFEE', 'price'=>(int)$feeApp,   'quantity'=>1, 'name'=>'Biaya Aplikasi'];

        $payload = [
            'transaction_details' => ['order_id'=>$kode, 'gross_amount'=>$total],
            'item_details'        => $itemDetails,
            'customer_details'    => ['first_name'=>$nama, 'phone'=>$no_wa, 'shipping_address'=>['address'=>$alamat]],
            'enabled_payments'    => ['bca_va','bni_va','bri_va','mandiri_va','permata_va','gopay','shopeepay','qris','other_va','bank_transfer'],
            'callbacks'           => ['finish' => $finish_url],
        ];
        $resp = mt_snap_request($payload, $MT_SERVER_KEY, $MT_BASE);
        db_exec("UPDATE jajanan_pesanan SET snap_token=$1, snap_redirect=$2 WHERE id=$3",
            [$resp['token'], $resp['redirect_url'] ?? null, $pid]);

        echo json_encode(['ok'=>true,'token'=>$resp['token'],'kode'=>$kode,'redirect'=>$resp['redirect_url'] ?? null]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

if ($ajax === 'confirm_payment' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $kode = trim($_POST['kode'] ?? '');
        $order = $kode ? db_one("SELECT * FROM jajanan_pesanan WHERE kode=$1",[$kode]) : null;
        if (!$order) throw new RuntimeException('Pesanan tidak ditemukan.');
        $status = mt_status_request($kode, $MT_SERVER_KEY, $MT_API_BASE);
        $ts = $status['transaction_status'] ?? '';
        $fraud = $status['fraud_status'] ?? 'accept';
        if (in_array($ts, ['capture','settlement'], true) && $fraud === 'accept') {
            db_exec("UPDATE jajanan_pesanan SET payment_status='paid', status='baru', updated_at=now() WHERE id=$1",[(int)$order['id']]);
            try {
                $od = db_one("SELECT * FROM jajanan_pesanan WHERE id=$1",[(int)$order['id']]);
                $its = db_all("SELECT nama,harga,qty FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$order['id']]);
                if ($od && empty($od['invoice_sent_at']) && !empty($od['email_pemesan'])) {
                    $sch = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
                    kirim_invoice_email($od, $its, $sch.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'));
                }
            } catch (Throwable $e) { error_log('invoice send: '.$e->getMessage()); }
            if (empty($order['stok_dipotong']) || $order['stok_dipotong']==='f' || $order['stok_dipotong']===false) {
                $its = db_all("SELECT jajanan_id, qty FROM jajanan_pesanan_item WHERE pesanan_id=$1",[(int)$order['id']]);
                foreach ($its as $it) {
                    if ($it['jajanan_id']) db_exec("UPDATE jajanan SET stok=GREATEST(0,stok-$1) WHERE id=$2",[(int)$it['qty'],(int)$it['jajanan_id']]);
                }
                db_exec("UPDATE jajanan_pesanan SET stok_dipotong=true WHERE id=$1",[(int)$order['id']]);
            }
            echo json_encode(['ok'=>true,'status'=>'paid']);
        } elseif (in_array($ts, ['pending'], true)) {
            echo json_encode(['ok'=>true,'status'=>'pending']);
        } else {
            db_exec("UPDATE jajanan_pesanan SET payment_status=$1, status='dibatalkan', updated_at=now() WHERE id=$2",[$ts?:'failed',(int)$order['id']]);
            echo json_encode(['ok'=>true,'status'=>'failed','raw'=>$ts]);
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* === Revisi 3 Jun 2026 #2: AJAX polling lokasi driver untuk pemesan ===
   Pemesan cukup tahu KODE pesanan + nama pemesan untuk melacak driver. */
if ($ajax === 'driver_loc' && $_SERVER['REQUEST_METHOD']==='GET') {
    header('Content-Type: application/json');
    $kode = trim($_GET['kode'] ?? '');
    if ($kode==='') { echo json_encode(['ok'=>false,'error'=>'kode kosong']); exit; }
    $o = db_one("SELECT id,kode,status,kurir_user_id,driver_lat,driver_lng,driver_loc_updated_at,
                        pickup_lat,pickup_lng
                 FROM jajanan_pesanan WHERE kode=$1",[$kode]);
    if (!$o) { echo json_encode(['ok'=>false,'error'=>'tidak ditemukan']); exit; }
    $hasDriver = !empty($o['kurir_user_id']);
    echo json_encode([
      'ok'=>true,
      'status'    => $o['status'],
      'has_driver'=> $hasDriver,
      'driver'    => $hasDriver && $o['driver_lat']!==null ? [
          'lat'=>(float)$o['driver_lat'],
          'lng'=>(float)$o['driver_lng'],
          'updated_at'=>$o['driver_loc_updated_at'],
       ] : null,
      'pickup'    => $o['pickup_lat']!==null ? [
          'lat'=>(float)$o['pickup_lat'],
          'lng'=>(float)$o['pickup_lng']
       ] : null,
      'server_time' => date('c'),
    ]);
    exit;
}

/* === Revisi 4 Jun 2026 #1: AJAX Detail Pesanan (items + kurir + nomor telpon) === */
if ($ajax === 'detail_pesanan' && $_SERVER['REQUEST_METHOD']==='GET') {
    header('Content-Type: application/json');
    $kode = trim($_GET['kode'] ?? '');
    if ($kode==='') { echo json_encode(['ok'=>false,'error'=>'kode kosong']); exit; }
    $o = db_one("SELECT id,kode,nama_pemesan,no_wa,alamat,catatan,subtotal,ongkir,total,status,payment_status,
                        created_at,updated_at,kurir_user_id,rating,rating_komentar,rating_at
                 FROM jajanan_pesanan WHERE kode=$1",[$kode]);
    if (!$o) { echo json_encode(['ok'=>false,'error'=>'Pesanan tidak ditemukan']); exit; }
    $items = db_all("SELECT i.nama, i.harga, i.qty, (SELECT nama FROM toko t WHERE t.id=j.toko_id) AS toko_nama
                     FROM jajanan_pesanan_item i
                     LEFT JOIN jajanan j ON j.id=i.jajanan_id
                     WHERE i.pesanan_id=$1 ORDER BY i.id",[(int)$o['id']]);
    $kurir = null;
    if (!empty($o['kurir_user_id'])) {
        // Revisi: ambil foto & nomor telepon kurir; fallback ke kolom `wa` jika `nomor_wa` kosong.
        $u2 = db_one("SELECT id, nama,
                              COALESCE(NULLIF(nomor_wa,''), NULLIF(wa,'')) AS nomor_wa,
                              COALESCE(foto_url,'') AS foto_url
                       FROM users WHERE id=$1",[(int)$o['kurir_user_id']]);
        if ($u2) {
            $waN = jjn_normalize_phone($u2['nomor_wa'] ?? '');
            $kurir = [
                'nama'    => $u2['nama'],
                'foto_url'=> $u2['foto_url'] ?: null,
                'no_wa'   => $waN,
                'wa_display' => $waN ? ('+'.$waN) : 'Nomor belum tersedia',
                'wa_link' => $waN ? ('https://wa.me/'.$waN) : null,
                'tel'     => $waN ? ('tel:+'.$waN) : null,
            ];
        }
    }
    echo json_encode(['ok'=>true, 'order'=>$o, 'items'=>$items, 'kurir'=>$kurir]);
    exit;
}

/* === Revisi 4 Jun 2026 #5: AJAX list produk satu toko (untuk modal Pesan Sekarang) === */
if ($ajax === 'toko_produk' && $_SERVER['REQUEST_METHOD']==='GET') {
    // Bersihkan output buffer agar respons MURNI JSON (cegah "Koneksi gagal" di sisi JS).
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    try {
        $tid = (int)($_GET['toko_id'] ?? 0);
        if ($tid<=0) { echo json_encode(['ok'=>false,'error'=>'toko_id kosong']); exit; }
        $toko = db_one("SELECT id,nama,deskripsi,alamat,no_wa FROM toko WHERE id=$1",[$tid]);
        if (!$toko) { echo json_encode(['ok'=>false,'error'=>'Toko tidak ditemukan']); exit; }

        // Bangun daftar kolom secara defensif: tambahkan jam_buka/jam_tutup/hari_buka
        // hanya jika kolom-kolomnya benar-benar ada di tabel jajanan (idempotent vs migrasi).
        $cols = ['id','nama','harga','stok','foto_url','kategori','deskripsi'];
        $optional = ['jam_buka','jam_tutup','hari_buka'];
        try {
            $existing = db_all("SELECT column_name FROM information_schema.columns
                                WHERE table_name='jajanan' AND column_name = ANY($1)", [ '{'.implode(',', $optional).'}' ]);
            $have = array_map(function($r){ return $r['column_name']; }, $existing ?: []);
            foreach ($optional as $oc) { if (in_array($oc, $have, true)) $cols[] = $oc; }
        } catch (Throwable $e) { /* fallback: cols dasar saja */ }

        $sql = "SELECT ".implode(',', $cols)."
                FROM jajanan
                WHERE toko_id=$1 AND aktif=true AND stok>0
                ORDER BY nama";
        $prods = db_all($sql, [$tid]) ?: [];

        $now = date('H:i:s');
        foreach ($prods as &$p) {
            $p['is_open'] = jjn_is_open($p['jam_buka'] ?? null, $p['jam_tutup'] ?? null, $now, $p['hari_buka'] ?? null);
            $p['stok']  = (int)($p['stok'] ?? 0);
            $p['harga'] = (int)($p['harga'] ?? 0);
            $p['jam_buka_short']  = !empty($p['jam_buka'])  ? substr($p['jam_buka'],0,5)  : null;
            $p['jam_tutup_short'] = !empty($p['jam_tutup']) ? substr($p['jam_tutup'],0,5) : null;
        }
        unset($p);
        echo json_encode(['ok'=>true,'toko'=>$toko,'produk'=>$prods]);
    } catch (Throwable $e) {
        http_response_code(200); // tetap 200 agar fetch tidak gagal; pesan lewat JSON
        echo json_encode(['ok'=>false,'error'=>'Gagal memuat produk toko: '.$e->getMessage()]);
    }
    exit;
}

/* === Revisi 4 Jun 2026 #4: AJAX submit rating pesanan (1..5 bintang) === */
if ($ajax === 'submit_rating' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    try {
        csrf_check();
        $kode    = trim($_POST['kode'] ?? '');
        $rating  = (int)($_POST['rating'] ?? 0);
        $komen   = substr(trim($_POST['komentar'] ?? ''),0,500);
        if ($kode==='' || $rating<1 || $rating>5) throw new RuntimeException('Rating tidak valid (1–5).');
        $o = db_one("SELECT id,status,rating FROM jajanan_pesanan WHERE kode=$1",[$kode]);
        if (!$o) throw new RuntimeException('Pesanan tidak ditemukan.');
        if ($o['status'] !== 'selesai') throw new RuntimeException('Rating hanya untuk pesanan yang sudah selesai.');
        if (!empty($o['rating'])) throw new RuntimeException('Pesanan ini sudah dirating.');
        db_exec("UPDATE jajanan_pesanan SET rating=$1, rating_komentar=$2, rating_at=now() WHERE id=$3",
            [$rating, $komen ?: null, (int)$o['id']]);
        echo json_encode(['ok'=>true,'rating'=>$rating]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}



/* ============================================================
 * Non-AJAX POST: cek status
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $a = $_POST['_action'] ?? '';
    if ($a === 'cek_status') {
        $nm = trim($_POST['cek_nama'] ?? '');
        header('Location: /jajanan.php?cek_nama='.urlencode($nm).'#cek-status'); exit;
    }
}

/* ============================================================
 * Listing produk
 * ============================================================ */
$katAll = db_all("SELECT COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') AS kat, COUNT(*) AS n
                  FROM jajanan WHERE aktif=true AND stok>0
                  GROUP BY 1 ORDER BY 1");
$katPilih = trim($_GET['kat'] ?? '');
$qSearch  = trim($_GET['q'] ?? '');
$bukaFilter = trim($_GET['buka'] ?? ''); // '', 'open', 'closed'

$page = max(1,(int)($_GET['page'] ?? 1));
$where = "WHERE aktif=true AND stok>0";
$params = []; $i = 0;
if ($katPilih !== '' && $katPilih !== 'Semua') { $i++; $where .= " AND COALESCE(NULLIF(TRIM(kategori),''),'Lainnya') = \$$i"; $params[] = $katPilih; }
if ($qSearch !== '')                          { $i++; $where .= " AND (LOWER(nama) LIKE LOWER(\$$i) OR LOWER(COALESCE(deskripsi,'')) LIKE LOWER(\$$i))"; $params[] = '%'.$qSearch.'%'; }
/* Revisi #4: filter "buka sekarang" / "tutup" berdasarkan jam_buka/jam_tutup vs jam server.
   Buka jika jam_buka/jam_tutup NULL (selalu buka), atau jam sekarang ada di rentang.
   Mendukung jadwal yang melewati tengah malam (jam_buka > jam_tutup). */
if ($bukaFilter === 'open') {
    $where .= " AND ( (jam_buka IS NULL OR jam_tutup IS NULL)
                      OR (jam_buka <= jam_tutup AND CURRENT_TIME BETWEEN jam_buka AND jam_tutup)
                      OR (jam_buka >  jam_tutup AND (CURRENT_TIME >= jam_buka OR CURRENT_TIME <= jam_tutup)) )";
} elseif ($bukaFilter === 'closed') {
    $where .= " AND ( jam_buka IS NOT NULL AND jam_tutup IS NOT NULL
                      AND NOT (
                        (jam_buka <= jam_tutup AND CURRENT_TIME BETWEEN jam_buka AND jam_tutup)
                        OR (jam_buka >  jam_tutup AND (CURRENT_TIME >= jam_buka OR CURRENT_TIME <= jam_tutup))
                      ) )";
}

$totalProduk = (int) db_val("SELECT COUNT(*) FROM jajanan $where", $params);
$totalPage   = max(1,(int)ceil($totalProduk / $PER_PAGE));
if ($page > $totalPage) $page = $totalPage;
$offset = ($page-1) * $PER_PAGE;
$rows = db_all("SELECT *, (SELECT nama FROM toko WHERE toko.id = jajanan.toko_id) AS toko_nama FROM jajanan $where ORDER BY kategori NULLS LAST, nama
                LIMIT $PER_PAGE OFFSET $offset", $params);

$cekNama = trim($_GET['cek_nama'] ?? '');
$cekHasil = $cekNama !== ''
    ? db_all("SELECT id,kode,nama_pemesan,no_wa,total,status,payment_status,created_at,updated_at,rating
              FROM jajanan_pesanan
              WHERE LOWER(nama_pemesan) LIKE LOWER($1) AND status<>'pending_payment'
              ORDER BY created_at DESC LIMIT 20", ['%'.$cekNama.'%'])
    : [];

require_once __DIR__.'/includes/htmx.php'; htmx_layout_start($pageTitle ?? 'Jajanan');
?>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if (!empty($_SESSION['flash_err'])): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($_SESSION['flash_err']) ?></div><?php unset($_SESSION['flash_err']); endif; ?>

<?php
$berhasilKode = trim($_GET['berhasil'] ?? '');
if ($berhasilKode !== ''):
    $bOrder = db_one("SELECT kode,nama_pemesan,total,payment_status,status FROM jajanan_pesanan WHERE kode=$1",[$berhasilKode]);
?>
<div class="card border-success mb-3" id="bayarBerhasil">
  <div class="card-header bg-success text-white">
    <i class="bi bi-check-circle-fill"></i> Pemesanan Berhasil
  </div>
  <div class="card-body">
    <?php if (!$bOrder): ?>
      <div class="alert alert-warning mb-0 small">Kode pesanan <strong><?= htmlspecialchars($berhasilKode) ?></strong> tidak ditemukan.</div>
    <?php else: ?>
      <div class="mb-2">
        <div class="small text-muted">Kode Pesanan</div>
        <div class="h5 mb-0"><strong><?= htmlspecialchars($bOrder['kode']) ?></strong></div>
      </div>
      <div class="row g-2 small">
        <div class="col-6">Nama: <strong><?= htmlspecialchars($bOrder['nama_pemesan']) ?></strong></div>
        <div class="col-6">Total: <strong>Rp <?= number_format((int)$bOrder['total'],0,',','.') ?></strong></div>
        <div class="col-6">Pembayaran: <span id="bp_pay" class="badge bg-<?= $bOrder['payment_status']==='paid'?'success':'warning text-dark' ?>"><?= htmlspecialchars($bOrder['payment_status']) ?></span></div>
        <div class="col-6">Status: <span id="bp_st" class="badge bg-info"><?= htmlspecialchars($bOrder['status']) ?></span></div>
      </div>
      <div class="alert alert-info small mt-3 mb-2" id="bp_msg">
        <span class="spinner-border spinner-border-sm me-1"></span>
        Mengkonfirmasi pembayaran ke Midtrans…
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="/jajanan.php?cek_nama=<?= urlencode($bOrder['nama_pemesan']) ?>#cek-status" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-check"></i> Lihat Pesanan Saya</a>
        <a href="/jajanan.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
      <script>
      (function(){
        var kode = <?= json_encode($bOrder['kode']) ?>;
        var tries = 0, MAX = 8;
        function poll(){
          tries++;
          var fd = new FormData();
          fd.append('csrf', <?= json_encode(csrf_token()) ?>);
          fd.append('kode', kode);
          fetch('/jajanan.php?ajax=confirm_payment',{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(j){
              if (!j || !j.ok) { return finish('failed'); }
              if (j.status==='paid')    return finish('paid');
              if (j.status==='failed')  return finish('failed');
              if (tries < MAX) setTimeout(poll, 2000); else finish('pending');
            }).catch(function(){ if (tries<MAX) setTimeout(poll,2500); else finish('pending'); });
        }
        function finish(s){
          var msg = document.getElementById('bp_msg');
          var pay = document.getElementById('bp_pay');
          var st  = document.getElementById('bp_st');
          if (s==='paid'){
            msg.className='alert alert-success small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-check-circle-fill"></i> Pembayaran berhasil dikonfirmasi. Pesanan kamu sedang diproses penjual.';
            pay.className='badge bg-success'; pay.textContent='paid';
            st.className='badge bg-info'; st.textContent='baru';
          } else if (s==='failed'){
            msg.className='alert alert-danger small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-x-circle-fill"></i> Pembayaran gagal / dibatalkan. Silakan pesan ulang.';
          } else {
            msg.className='alert alert-warning small mt-3 mb-2';
            msg.innerHTML='<i class="bi bi-hourglass-split"></i> Pembayaran masih tertunda. Selesaikan pembayaran di Midtrans, lalu cek "Pesanan Saya".';
          }
        }
        poll();
      })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ===== Revisi 3 Jun 2026 #5: Hero baru dengan SVG dekoratif ===== -->
<style>
.jjn-hero{
  position:relative; overflow:hidden; border-radius:1rem; padding:1.5rem 1.25rem;
  background:
    radial-gradient(circle at 90% 10%, rgba(255,255,255,.25) 0, transparent 35%),
    radial-gradient(circle at 10% 90%, rgba(255,255,255,.18) 0, transparent 40%),
    linear-gradient(135deg,#16a34a 0%,#22c55e 40%,#0ea5e9 100%);
  color:#fff; box-shadow:0 10px 30px -12px rgba(14,165,233,.45);
}
.jjn-hero h1{ color:#fff; font-weight:800; letter-spacing:-.5px; }
.jjn-hero .deco{ position:absolute; right:-10px; top:-10px; width:180px; opacity:.85; pointer-events:none; }
.jjn-hero .deco-2{ position:absolute; left:-30px; bottom:-30px; width:140px; opacity:.55; pointer-events:none; }
@media (max-width:576px){ .jjn-hero .deco{ width:110px; opacity:.6 } .jjn-hero .deco-2{ display:none } }
.jjn-chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
.jjn-chip{ background:rgba(255,255,255,.18); color:#fff; padding:.2rem .6rem; border-radius:999px; font-size:.72rem; backdrop-filter:blur(4px); }
.jjn-card{ transition:transform .15s ease, box-shadow .15s ease; border:1px solid rgba(0,0,0,.06); }
.jjn-card:hover{ transform:translateY(-2px); box-shadow:0 8px 20px -8px rgba(0,0,0,.18); }
.jjn-time-pill{ display:inline-flex; align-items:center; gap:.25rem; font-size:.72rem; padding:.18rem .5rem; border-radius:999px; }
.jjn-time-open{ background:#dcfce7; color:#166534; border:1px solid #86efac; }
.jjn-time-closed{ background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
.jjn-overlay-tutup{ position:absolute; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; backdrop-filter:blur(1.5px); border-top-left-radius:.375rem; border-top-right-radius:.375rem; }
</style>
<div class="jjn-hero mb-3">
  <svg class="deco" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="100" cy="100" r="80" fill="#fff" opacity=".10"/>
    <g transform="translate(40,40)">
      <path d="M60 10c22 0 40 18 40 40 0 18-12 33-28 38v22H48v-22C32 83 20 68 20 50 20 28 38 10 60 10z" fill="#fff" opacity=".95"/>
      <circle cx="60" cy="48" r="6" fill="#f59e0b"/>
      <circle cx="44" cy="58" r="4" fill="#ef4444"/>
      <circle cx="76" cy="58" r="4" fill="#10b981"/>
    </g>
  </svg>
  <svg class="deco-2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M30 140c20-40 60-60 100-40s60 60 40 80-100 0-140-40z" fill="#fff" opacity=".15"/>
  </svg>
  <div style="position:relative; z-index:2; max-width:780px">
    <div class="jjn-chips mb-2">
      <span class="jjn-chip"><i class="bi bi-lightning-charge-fill"></i> Antar Cepat</span>
      <span class="jjn-chip"><i class="bi bi-shield-check"></i> Bayar Aman Midtrans</span>
      <span class="jjn-chip"><i class="bi bi-geo-alt-fill"></i> Tracking Realtime</span>
    </div>
    <h1 class="h4 mb-1"><i class="bi bi-bag-heart"></i> Jajanan Favorit UIN SGD Bandung</h1>
    <p class="mb-0 small" style="opacity:.95">
      Pesan per produk seperti Gojek. Pembayaran online via Midtrans (transfer/VA/QRIS/e-wallet).
      Termasuk PPN <?= (int)($PPN_RATE*100) ?>% &amp; biaya admin Midtrans
      (Rp <?= number_format($MIDTRANS_FEE_FIXED,0,",",".") ?> + <?= rtrim(rtrim(number_format($MIDTRANS_FEE_PCT*100,2,",","."),"0"),",") ?>%) &amp;
      biaya aplikasi (Rp <?= number_format($APP_FEE_FIXED,0,",",".") ?><?= $APP_FEE_PCT>0 ? " + ".rtrim(rtrim(number_format($APP_FEE_PCT*100,2,",","."),"0"),",")."%" : "" ?>).
    </p>
  </div>
</div>

<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle-fill"></i>
  Jarak rekomendasi pengantaran maks ±<?= $UIN_R_REKOM_KM ?> km, batas layanan <?= $UIN_R_MAX_KM ?> km dari
  <strong>UIN SGD Bandung</strong>. Ongkir Rp <?= number_format($ONGKIR_BASE,0,',','.') ?> + Rp <?= number_format($ONGKIR_PER_KM,0,',','.') ?>/km.
  Harga belum termasuk <strong>PPN <?= (int)($PPN_RATE*100) ?>%</strong> (otomatis ditambahkan saat checkout).
</div>

<!-- Cek status pesanan -->
<div class="card mb-3" id="cek-status">
  <div class="card-header bg-light"><i class="bi bi-search"></i> Cek Status Pesanan Saya</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end" id="formCekPesanan" onsubmit="(function(f){var b=f.querySelector('button[type=submit]');if(b){b.disabled=true;b.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Mengecek…';}})(this);">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="cek_status">
      <div class="col-md-8"><label class="small">Nama Pemesan</label>
        <input class="form-control form-control-sm" name="cek_nama" required value="<?= htmlspecialchars($cekNama) ?>"></div>
      <div class="col-md-4"><button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Cek Pesanan</button></div>
    </form>
    <?php if ($cekNama !== ''): ?>
      <hr>
      <?php if (!$cekHasil): ?>
        <div class="small text-muted">Tidak ditemukan pesanan atas nama <strong><?= htmlspecialchars($cekNama) ?></strong>.</div>
      <?php else: ?>
        <style>
          .jjn-cek-table{ font-size:.78rem; }
          .jjn-cek-table th, .jjn-cek-table td{ white-space:nowrap; vertical-align:middle; padding:.4rem .5rem; }
          .jjn-cek-table td.col-nama{ white-space:normal; min-width:120px; max-width:160px; word-break:break-word; }
          .jjn-cek-table td.col-tgl{ font-size:.7rem; color:#6b7280; }
          .jjn-cek-table .btn-lacak{ white-space:nowrap; }
          @media (max-width: 576px){
            .jjn-cek-table .col-hide-sm{ display:none; }
          }
        </style>
        <div class="table-responsive" style="overflow-x:auto">
          <table class="table table-sm align-middle mb-0 jjn-cek-table" style="min-width:640px">
            <thead class="table-light">
              <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th class="text-end">Total</th>
                <th>Bayar</th>
                <th>Status</th>
                <th class="col-hide-sm">Tgl</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($cekHasil as $r):
                $bisaLacak  = in_array($r['status'], ['diproses','diantar'], true);
                $sudahRate  = !empty($r['rating']);
                $bisaRate   = ($r['status']==='selesai') && !$sudahRate;
            ?>
              <tr>
                <td><span class="badge bg-dark-subtle text-dark-emphasis"><?= htmlspecialchars($r['kode']) ?></span></td>
                <td class="col-nama"><?= htmlspecialchars($r['nama_pemesan']) ?></td>
                <td class="text-end fw-semibold">Rp <?= number_format((int)$r['total'],0,',','.') ?></td>
                <td><span class="badge bg-<?= ($r['payment_status']??'')==='paid'?'success':'secondary' ?>"><?= htmlspecialchars($r['payment_status']??'-') ?></span></td>
                <td><span class="badge bg-info"><?= htmlspecialchars($r['status']) ?></span></td>
                <td class="col-tgl col-hide-sm"><?= htmlspecialchars(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-detail"
                            data-kode="<?= htmlspecialchars($r['kode']) ?>"
                            title="Lihat rincian pesanan & kontak kurir">
                      <i class="bi bi-receipt"></i> Detail
                    </button>
                    <?php if ($bisaLacak): ?>
                      <button type="button" class="btn btn-sm btn-danger btn-lacak"
                              data-kode="<?= htmlspecialchars($r['kode']) ?>">
                        <i class="bi bi-geo-alt-fill"></i> Lacak
                      </button>
                    <?php endif; ?>
                    <?php if ($bisaRate): ?>
                      <button type="button" class="btn btn-sm btn-warning btn-rating"
                              data-kode="<?= htmlspecialchars($r['kode']) ?>">
                        <i class="bi bi-star-fill"></i> Beri Rating
                      </button>
                    <?php elseif ($sudahRate): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis" title="Rating Anda">
                        <?php for($s=1;$s<=5;$s++): ?>
                          <i class="bi bi-star<?= $s<=(int)$r['rating']?'-fill':'' ?>"></i>
                        <?php endfor; ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Pencarian + Kategori -->
<form method="get" class="row g-2 mb-2" id="filterForm">
  <div class="col-md-6">
    <div class="input-group input-group-sm">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input class="form-control" type="search" name="q" value="<?= htmlspecialchars($qSearch) ?>" placeholder="Cari makanan, minuman, snack...">
      <?php if ($katPilih!==''): ?><input type="hidden" name="kat" value="<?= htmlspecialchars($katPilih) ?>"><?php endif; ?>
      <button class="btn btn-success">Cari</button>
      <?php if ($qSearch!==''): ?><a class="btn btn-outline-secondary" href="/jajanan.php<?= $katPilih!==''?'?kat='.urlencode($katPilih):'' ?>">Reset</a><?php endif; ?>
    </div>
  </div>
</form>

<?php if ($katAll): ?>
<div class="mb-2 d-flex flex-wrap gap-1 align-items-center">
  <span class="small text-muted me-1"><i class="bi bi-tags"></i> Kategori:</span>
  <?php
    $baseQs = function(array $extra=[]) use ($qSearch,$katPilih,$bukaFilter){
        $a = $extra;
        if (!array_key_exists('kat',$a)  && $katPilih!=='')   $a['kat']=$katPilih;
        if (!array_key_exists('q',$a)    && $qSearch!=='')    $a['q']=$qSearch;
        if (!array_key_exists('buka',$a) && $bukaFilter!=='') $a['buka']=$bukaFilter;
        return $a ? '?'.http_build_query($a) : '';
    };
  ?>
  <a href="/jajanan.php<?= $baseQs(['kat'=>'']) ?>" class="btn btn-sm <?= $katPilih===''?'btn-success':'btn-outline-success' ?>">Semua</a>
  <?php foreach($katAll as $k): ?>
    <a href="/jajanan.php<?= $baseQs(['kat'=>$k['kat']]) ?>"
       class="btn btn-sm <?= $katPilih===$k['kat']?'btn-success':'btn-outline-success' ?>">
       <?= htmlspecialchars($k['kat']) ?> <span class="badge bg-light text-success ms-1"><?= (int)$k['n'] ?></span>
    </a>
  <?php endforeach; ?>
</div>
<!-- Revisi #4: filter berdasar jam buka/tutup -->
<div class="mb-3 d-flex flex-wrap gap-1 align-items-center">
  <span class="small text-muted me-1"><i class="bi bi-clock"></i> Jam Operasional:</span>
  <a href="/jajanan.php<?= $baseQs(['buka'=>'']) ?>"
     class="btn btn-sm <?= $bukaFilter===''?'btn-dark':'btn-outline-dark' ?>">Semua</a>
  <a href="/jajanan.php<?= $baseQs(['buka'=>'open']) ?>"
     class="btn btn-sm <?= $bukaFilter==='open'?'btn-success':'btn-outline-success' ?>">
     <i class="bi bi-door-open-fill"></i> Buka Sekarang
  </a>
  <a href="/jajanan.php<?= $baseQs(['buka'=>'closed']) ?>"
     class="btn btn-sm <?= $bukaFilter==='closed'?'btn-danger':'btn-outline-danger' ?>">
     <i class="bi bi-door-closed-fill"></i> Tutup
  </a>
  <span class="small text-muted ms-2">Jam server: <strong><?= date('H:i') ?></strong> WIB</span>
</div>
<?php endif; ?>

<!-- Grid produk (Revisi #4: mobile 2 per baris → col-6) -->
<div class="row g-2">
<?php foreach($rows as $r):
    $stokR = (int)$r['stok'];
    $isOpen = jjn_is_open($r['jam_buka'] ?? null, $r['jam_tutup'] ?? null, null, $r['hari_buka'] ?? null);
    $jamLabel = '';
    if (!empty($r['jam_buka']) && !empty($r['jam_tutup'])) {
        $jamLabel = substr($r['jam_buka'],0,5).'–'.substr($r['jam_tutup'],0,5);
    }
?>
  <div class="col-md-4 col-6">
    <div class="card jjn-card h-100 shadow-sm position-relative">
      <div class="position-relative">
        <?php if(!empty($r['foto_url'])): ?>
          <img src="<?= htmlspecialchars($r['foto_url']) ?>"
               class="card-img-top jjn-zoomable"
               style="height:140px;object-fit:cover;cursor:zoom-in"
               alt="<?= htmlspecialchars($r['nama']) ?>"
               data-full="<?= htmlspecialchars($r['foto_url']) ?>"
               data-title="<?= htmlspecialchars($r['nama']) ?>"
               title="Klik untuk memperbesar foto">
        <?php else: ?>
          <div class="bg-light text-center py-4" style="height:140px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-bag fs-1 text-muted"></i>
          </div>
        <?php endif; ?>
        <?php if (!$isOpen): ?>
          <div class="jjn-overlay-tutup"><i class="bi bi-door-closed-fill me-1"></i> Tutup</div>
        <?php endif; ?>
        <?php if ($jamLabel): ?>
          <span class="position-absolute bottom-0 start-0 m-1 jjn-time-pill <?= $isOpen?'jjn-time-open':'jjn-time-closed' ?>">
            <i class="bi bi-clock<?= $isOpen?'-fill':'-history' ?>"></i> <?= htmlspecialchars($jamLabel) ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="card-body p-2 d-flex flex-column">
        <?php if(!empty($r['kategori'])): ?>
          <span class="badge bg-success-subtle text-success mb-1 align-self-start"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($r['kategori']) ?></span>
        <?php endif; ?>
        <?php if(!empty($r['toko_nama'])): ?>
          <div class="small text-warning-emphasis mb-1" style="font-size:.7rem"><i class="bi bi-shop"></i> <?= htmlspecialchars($r['toko_nama']) ?></div>
        <?php endif; ?>
        <div class="fw-semibold small"><?= htmlspecialchars($r['nama']) ?></div>
        <div class="text-success small fw-bold mb-1">Rp <?= number_format((int)$r['harga'],0,',','.') ?></div>
        <?php if(!empty($r['deskripsi'])): ?><div class="text-muted mb-2" style="font-size:.72rem"><?= htmlspecialchars($r['deskripsi']) ?></div><?php endif; ?>

        <div class="qty-counter d-flex align-items-center justify-content-between mb-2"
             data-id="<?= (int)$r['id'] ?>" data-stok="<?= $stokR ?>">
          <span class="small text-muted">Jumlah</span>
          <div class="input-group input-group-sm" style="max-width:130px">
            <button type="button" class="btn btn-outline-success qc-minus" aria-label="Kurangi" <?= $isOpen?'':'disabled' ?>>−</button>
            <input type="number" class="form-control text-center qc-input"
                   value="1" min="1" max="<?= $stokR ?>" inputmode="numeric" <?= $isOpen?'':'disabled' ?>>
            <button type="button" class="btn btn-outline-success qc-plus" aria-label="Tambah" <?= $isOpen?'':'disabled' ?>>+</button>
          </div>
        </div>

        <div class="mt-auto d-grid gap-1">
          <?php if ($isOpen): ?>
          <?php $hasToko = !empty($r['toko_id']); ?>
          <button type="button"
                  class="btn btn-sm btn-success <?= $hasToko?'btn-pesan-toko':'btn-pesan' ?>"
                  data-id="<?= (int)$r['id'] ?>"
                  data-toko-id="<?= $hasToko ? (int)$r['toko_id'] : '' ?>"
                  data-toko-nama="<?= htmlspecialchars($r['toko_nama'] ?? 'Toko') ?>"
                  data-nama="<?= htmlspecialchars($r['nama']) ?>"
                  data-harga="<?= (int)$r['harga'] ?>"
                  data-stok="<?= $stokR ?>"
                  data-foto="<?= htmlspecialchars($r['foto_url'] ?? '') ?>">
            <i class="bi bi-send-check"></i> Pesan Sekarang
          </button>
          <?php else: ?>
          <button type="button" class="btn btn-sm btn-secondary" disabled
                  title="Toko sedang tutup<?= $jamLabel ? ' (jam '.$jamLabel.')' : '' ?>">
            <i class="bi bi-door-closed"></i> Toko Tutup<?= $jamLabel ? ' • '.htmlspecialchars($jamLabel) : '' ?>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if(!$rows): ?><p class="text-muted small mt-2">Tidak ada produk yang cocok dengan filter ini.</p><?php endif; ?>

<?php if ($totalPage > 1):
    $qs = function($p) use ($katPilih,$qSearch) {
        $a = ['page'=>$p];
        if ($katPilih!=='') $a['kat']=$katPilih;
        if ($qSearch!=='')  $a['q']=$qSearch;
        return '?'.http_build_query($a);
    };
?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-1">
  <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $qs(max(1,$page-1)) ?>">«</a></li>
  <?php for($p=1;$p<=$totalPage;$p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $qs($p) ?>"><?= $p ?></a></li>
  <?php endfor; ?>
  <li class="page-item <?= $page>=$totalPage?'disabled':'' ?>"><a class="page-link" href="<?= $qs(min($totalPage,$page+1)) ?>">»</a></li>
</ul></nav>
<div class="text-center small text-muted mb-2">Halaman <?= $page ?> dari <?= $totalPage ?> · <?= $totalProduk ?> produk · 5 per halaman</div>
<?php endif; ?>

<!-- ===== Modal Lightbox Foto (Revisi #5) ===== -->
<div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0 py-2">
        <h6 class="modal-title small" id="zoomTitle">Foto Produk</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-1" style="background:#000">
        <img id="zoomImg" src="" alt="" style="max-width:100%;max-height:80vh;object-fit:contain;cursor:zoom-out">
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal Pemesanan ===== -->
<style>
/* Revisi: pastikan modal body benar2 scrollable & tombol Bayar di footer
   selalu terlihat (sticky), termasuk di mobile saat keyboard muncul. */
#pesanModal .modal-dialog{ display:flex; align-items:stretch; max-height:100dvh; }
#pesanModal .modal-content{
  display:flex; flex-direction:column;
  max-height:calc(100dvh - 1rem); height:auto;
  overflow:hidden;
}
#pesanModal .modal-header{ flex:0 0 auto; }
#pesanModal form{
  display:flex; flex-direction:column;
  flex:1 1 auto; min-height:0; overflow:hidden;
}
#pesanModal .modal-body{
  flex:1 1 auto; min-height:0;
  overflow-y:auto !important; -webkit-overflow-scrolling:touch;
  padding-bottom:1rem;
}
#pesanModal .modal-footer{
  flex:0 0 auto;
  position:sticky; bottom:0; background:#fff; z-index:5;
  border-top:1px solid #e5e7eb;
  box-shadow:0 -6px 14px -10px rgba(0,0,0,.2);
  padding-bottom:calc(.75rem + env(safe-area-inset-bottom,0px));
}
@media (max-width:576px){
  #pesanModal .modal-dialog{ margin:0; height:100dvh; max-height:100dvh; max-width:100%; }
  #pesanModal .modal-content{ height:100dvh; max-height:100dvh; border-radius:0; }
}
</style>
<div class="modal fade" id="pesanModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-send-check"></i> Pemesanan Jajan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="pesanForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="jajanan_id" id="mod_jid">
        <div class="modal-body">
          <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded">
            <img id="mod_foto" src="" alt="" class="rounded jjn-zoomable" style="width:54px;height:54px;object-fit:cover;display:none;cursor:zoom-in">
            <div class="flex-grow-1">
              <div class="fw-semibold small" id="mod_nama">-</div>
              <div class="text-success small fw-bold" id="mod_harga">-</div>
              <input type="hidden" id="mod_stok" value="0">
            </div>
          </div>

          <label class="small">Jumlah</label>
          <div class="input-group input-group-sm mb-2" style="max-width:180px">
            <button type="button" class="btn btn-outline-secondary" id="qtyMinus">−</button>
            <input type="number" min="1" value="1" class="form-control text-center" name="qty" id="mod_qty">
            <button type="button" class="btn btn-outline-secondary" id="qtyPlus">+</button>
          </div>

          <label class="small">Nama Pemesan</label>
          <input class="form-control form-control-sm mb-2" name="nama" required value="<?= htmlspecialchars($u['nama'] ?? '') ?>">

          <label class="small">Nomor WhatsApp</label>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text">+62</span>
            <input class="form-control" name="no_wa" id="mod_wa" required inputmode="numeric"
                   placeholder="81234567890"
                   value="<?= htmlspecialchars(preg_replace('/^(\+?62|0)/','', $u['nomor_wa'] ?? '')) ?>">
          </div>
          <div class="form-text small mb-2">Tanpa angka 0 di depan. Contoh: <strong>81234567890</strong>.</div>

          <label class="small">Alamat Lengkap Pengantaran</label>
          <textarea class="form-control form-control-sm mb-2" name="alamat" rows="2" required></textarea>
          <label class="small mt-2">Email (untuk invoice)</label>
          <input class="form-control form-control-sm mb-2" type="email" name="email" placeholder="email@anda.com (opsional)" value="<?= htmlspecialchars($u['email'] ?? '') ?>">


          <label class="small">Catatan (opsional)</label>
          <input class="form-control form-control-sm mb-2" name="catatan" placeholder="cth: gerbang biru, pagar besi">

          <div class="border rounded p-2 bg-light-subtle mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <button type="button" id="btnDetectLoc" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-geo-alt-fill"></i> Deteksi Lokasi Saya
              </button>
              <span id="locCoords" class="small text-muted">Lat/Lng belum terdeteksi</span>
              <input type="hidden" name="pickup_lat" id="pickup_lat">
              <input type="hidden" name="pickup_lng" id="pickup_lng">
            </div>
            <div class="alert alert-warning small mt-2 mb-0 py-1" id="locRequired">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <strong>Wajib:</strong> klik tombol di atas untuk mengizinkan akses lokasi.
              Tanpa lokasi, pembayaran Midtrans tidak dapat diproses.
            </div>
            <div id="locWarn" class="alert alert-danger small mt-2 mb-0 d-none">
              Lokasi di luar jangkauan layanan (>&nbsp;<?= $UIN_R_MAX_KM ?>&nbsp;km).
            </div>
          </div>

          <div class="p-2 bg-light rounded">
            <div class="d-flex justify-content-between small"><span>Subtotal</span><strong id="sumSub">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>PPN <?= (int)($PPN_RATE*100) ?>%</span><strong id="sumPpn">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Biaya Admin Midtrans</span><strong id="sumFee">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Biaya Aplikasi</span><strong id="sumApp">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Ongkir <span id="sumOngkirNote" class="text-muted">(flat)</span></span><strong id="sumOngkir">Rp <?= number_format($ONGKIR_FALLBACK,0,',','.') ?></strong></div>
            <hr class="my-1">
            <div class="d-flex justify-content-between"><span class="fw-semibold">Total Bayar</span><strong class="text-success" id="sumTot">Rp 0</strong></div>
          </div>

          <div class="alert alert-warning small mt-2 mb-0">
            <i class="bi bi-credit-card-2-front-fill"></i>
            Pembayaran <strong>Transfer/VA/QRIS/E-Wallet via Midtrans</strong>. Pesanan baru masuk setelah pembayaran berhasil.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm" id="btnBayar" disabled
                  title="Klik 'Deteksi Lokasi Saya' dulu untuk mengaktifkan pembayaran">
            <i class="bi bi-credit-card-2-front"></i> Bayar via Midtrans
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($MT_CLIENT_KEY !== ''): ?>
<script src="<?= $MT_SNAP_JS ?>" data-client-key="<?= htmlspecialchars($MT_CLIENT_KEY) ?>"></script>
<?php else: ?>
<script>console.warn('MIDTRANS_CLIENT_KEY belum diset — Snap popup tidak akan muncul.');</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var UIN = {lat: <?= $UIN_LAT ?>, lng: <?= $UIN_LNG ?>, rekom_km: <?= $UIN_R_REKOM_KM ?>, max_km: <?= $UIN_R_MAX_KM ?>};
  var ONGKIR_BASE = <?= (int)$ONGKIR_BASE ?>, ONGKIR_PER_KM = <?= (int)$ONGKIR_PER_KM ?>, ONGKIR_FALLBACK = <?= (int)$ONGKIR_FALLBACK ?>;
  var PPN_RATE = <?= json_encode($PPN_RATE) ?>;
  var MT_FEE_FIXED = <?= (int)$MIDTRANS_FEE_FIXED ?>, MT_FEE_PCT = <?= json_encode($MIDTRANS_FEE_PCT) ?>;
  var APP_FEE_FIXED = <?= (int)$APP_FEE_FIXED ?>, APP_FEE_PCT = <?= json_encode($APP_FEE_PCT) ?>;
  var currentDistKm = null, locValid = null, locDetected = false;
  var current = {harga:0, stok:0};

  function fmtRp(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID'); }
  function haversine(a,b){var R=6371000,toRad=Math.PI/180,dLat=(b.lat-a.lat)*toRad,dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2+Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));}
  function calcOngkir(){return currentDistKm!==null ? Math.round(ONGKIR_BASE+currentDistKm*ONGKIR_PER_KM) : ONGKIR_FALLBACK;}
  function recalc(){
    var q = Math.max(1, parseInt(document.getElementById('mod_qty').value||'1',10));
    if (current.stok && q>current.stok) { q=current.stok; document.getElementById('mod_qty').value=q; }
    var sub = q*current.harga;
    var ppn = Math.round(sub*PPN_RATE);
    var ong = calcOngkir();
    var fee = Math.round((sub+ppn+ong)*MT_FEE_PCT) + MT_FEE_FIXED;
    var appFee = Math.round((sub+ppn+ong)*APP_FEE_PCT) + APP_FEE_FIXED;
    document.getElementById('sumSub').textContent=fmtRp(sub);
    document.getElementById('sumPpn').textContent=fmtRp(ppn);
    document.getElementById('sumOngkir').textContent=fmtRp(ong);
    var fEl=document.getElementById('sumFee'); if(fEl) fEl.textContent=fmtRp(fee);
    var aEl=document.getElementById('sumApp'); if(aEl) aEl.textContent=fmtRp(appFee);
    document.getElementById('sumTot').textContent=fmtRp(sub+ppn+ong+fee+appFee);
    document.getElementById('sumOngkirNote').textContent = currentDistKm!==null
        ? '('+currentDistKm.toFixed(2)+' km)' : '(flat — share lokasi untuk akurat)';
    updateBayarBtn();
  }

  function updateBayarBtn(){
    var btn = document.getElementById('btnBayar');
    if (!btn) return;
    /* Revisi #6: tombol bayar baru aktif kalau lokasi sudah dideteksi & valid */
    var ok = locDetected && locValid !== false;
    btn.disabled = !ok;
    btn.title = ok ? '' : "Klik 'Deteksi Lokasi Saya' dulu untuk mengaktifkan pembayaran";
  }

  var modalEl = document.getElementById('pesanModal');
  var modal   = (typeof bootstrap !== 'undefined' && modalEl) ? new bootstrap.Modal(modalEl) : null;
  function showModal(){
    if (!modal && typeof bootstrap !== 'undefined' && modalEl) modal = new bootstrap.Modal(modalEl);
    if (modal) modal.show();
    else if (modalEl) { modalEl.classList.add('show'); modalEl.style.display='block'; document.body.classList.add('modal-open'); }
  }

  // ====== Counter qty per produk di kartu ======
  document.querySelectorAll('.qty-counter').forEach(function(box){
    var stok  = parseInt(box.dataset.stok || '0', 10);
    var input = box.querySelector('.qc-input');
    var minus = box.querySelector('.qc-minus');
    var plus  = box.querySelector('.qc-plus');
    if (!input || !minus || !plus) return;
    function clamp(){
      var v = parseInt(input.value || '1', 10); if (isNaN(v) || v<1) v=1;
      if (stok>0 && v>stok) v=stok;
      input.value = v;
      if (!input.disabled){
        minus.disabled = (v<=1);
        plus.disabled  = (stok>0 && v>=stok);
      }
    }
    minus.addEventListener('click', function(){ input.value = Math.max(1, (parseInt(input.value||'1',10)-1)); clamp(); });
    plus .addEventListener('click', function(){ input.value = (parseInt(input.value||'1',10)+1); clamp(); });
    input.addEventListener('input', clamp);
    clamp();
  });

  document.querySelectorAll('.btn-pesan').forEach(function(b){
    b.addEventListener('click', function(){
      current.harga = parseInt(b.dataset.harga||'0',10);
      current.stok  = parseInt(b.dataset.stok||'0',10);
      document.getElementById('mod_jid').value   = b.dataset.id;
      document.getElementById('mod_nama').textContent  = b.dataset.nama;
      document.getElementById('mod_harga').textContent = fmtRp(current.harga);
      document.getElementById('mod_stok').value = current.stok;
      var foto = b.dataset.foto, fimg = document.getElementById('mod_foto');
      if (foto) { fimg.src = foto; fimg.style.display=''; fimg.setAttribute('data-full',foto); fimg.setAttribute('data-title', b.dataset.nama||''); }
      else { fimg.style.display='none'; }
      var card = b.closest('.card');
      var qcInp = card ? card.querySelector('.qc-input') : null;
      var qtyFromCard = qcInp ? Math.max(1, parseInt(qcInp.value||'1',10)) : 1;
      if (current.stok && qtyFromCard > current.stok) qtyFromCard = current.stok;
      document.getElementById('mod_qty').value = qtyFromCard;
      document.getElementById('mod_qty').max = current.stok;
      // Reset state lokasi setiap kali modal dibuka
      currentDistKm=null; locValid=null; locDetected=false;
      document.getElementById('pickup_lat').value=''; document.getElementById('pickup_lng').value='';
      document.getElementById('locCoords').textContent='Lat/Lng belum terdeteksi';
      document.getElementById('locWarn').classList.add('d-none');
      var lr = document.getElementById('locRequired'); if (lr) lr.classList.remove('d-none');
      if (window.JJN_PRELOAD) { window.JJN_PRELOAD.show('Membuka form pemesanan…'); setTimeout(function(){ window.JJN_PRELOAD.hide(); }, 400); }
      recalc(); showModal();
    });
  });

  document.getElementById('qtyMinus').addEventListener('click',function(){
    var i=document.getElementById('mod_qty'); i.value=Math.max(1,parseInt(i.value||'1',10)-1); recalc();
  });
  document.getElementById('qtyPlus').addEventListener('click',function(){
    var i=document.getElementById('mod_qty'); var v=parseInt(i.value||'1',10)+1;
    if (current.stok && v>current.stok) v=current.stok; i.value=v; recalc();
  });
  document.getElementById('mod_qty').addEventListener('input',recalc);

  var waInp = document.getElementById('mod_wa');
  waInp.addEventListener('input', function(){
    var v = (this.value||'').replace(/\D+/g,'');
    v = v.replace(/^(62|0)+/, '');
    this.value = v;
  });

  document.getElementById('btnDetectLoc').addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var btn=this, orig=btn.innerHTML; btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude, lng=pos.coords.longitude;
      document.getElementById('pickup_lat').value=lat.toFixed(6);
      document.getElementById('pickup_lng').value=lng.toFixed(6);
      currentDistKm = haversine({lat:lat,lng:lng}, UIN)/1000;
      document.getElementById('locCoords').innerHTML =
        'Lat '+lat.toFixed(5)+' · Lng '+lng.toFixed(5)+' · <strong>'+currentDistKm.toFixed(2)+' km</strong> ke UIN';
      var warn=document.getElementById('locWarn');
      if (currentDistKm>UIN.max_km){warn.classList.remove('d-none'); locValid=false;}
      else {warn.classList.add('d-none'); locValid=true;}
      locDetected = true;
      var lr = document.getElementById('locRequired'); if (lr) lr.classList.add('d-none');
      recalc(); btn.disabled=false; btn.innerHTML=orig;
    }, function(err){
      alert('Gagal mendapatkan lokasi: '+err.message+'\n\nIzinkan akses lokasi pada browser untuk dapat melakukan pembayaran via Midtrans.');
      btn.disabled=false; btn.innerHTML=orig;
      locDetected = false; updateBayarBtn();
    },
    {enableHighAccuracy:true, timeout:15000});
  });

  document.getElementById('pesanForm').addEventListener('submit', function(e){
    e.preventDefault();
    /* Revisi #6: blok kalau lokasi belum dideteksi */
    if (!locDetected) {
      alert('Mohon klik "Deteksi Lokasi Saya" terlebih dahulu sebelum melakukan pembayaran via Midtrans.');
      return;
    }
    if (locValid===false) { alert('Lokasi di luar jangkauan.'); return; }
    var btn = document.getElementById('btnBayar');
    btn.disabled=true; var orig=btn.innerHTML;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Memproses…';
    if (window.JJN_PRELOAD) window.JJN_PRELOAD.show('Memproses pembayaran…');

    var fd = new FormData(this);
    fetch('/jajanan.php?ajax=create_snap', {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){
        btn.disabled=false; btn.innerHTML=orig;
        if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide();
        if (!j.ok) { alert(j.error||'Gagal membuat transaksi'); updateBayarBtn(); return; }
        if (typeof window.snap === 'undefined') {
          if (j.redirect) { window.location.href = j.redirect; return; }
          alert('Snap.js belum dimuat. Set MIDTRANS_CLIENT_KEY.'); return;
        }
        window.snap.pay(j.token, {
          onSuccess: function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onPending: function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onError:   function(){ alert('Pembayaran gagal.'); window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); },
          onClose:   function(){ window.location.href = '/jajanan.php?berhasil=' + encodeURIComponent(j.kode); }
        });
      })
      .catch(function(){ btn.disabled=false; btn.innerHTML=orig; if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide(); alert('Koneksi gagal'); updateBayarBtn(); });
  });

  /* ===== Revisi #5: lightbox foto produk (klik gambar → modal zoom) ===== */
  var zoomEl = document.getElementById('zoomModal');
  var zoomImg = document.getElementById('zoomImg');
  var zoomTitle = document.getElementById('zoomTitle');
  var zoomModal = (typeof bootstrap !== 'undefined' && zoomEl) ? new bootstrap.Modal(zoomEl) : null;
  document.addEventListener('click', function(ev){
    var img = ev.target.closest && ev.target.closest('.jjn-zoomable');
    if (!img) return;
    /* Jangan trigger zoom kalau gambar di-klik untuk tombol di dalam .btn-pesan */
    if (img.closest('.btn-pesan')) return;
    var src = img.getAttribute('data-full') || img.src;
    if (!src) return;
    ev.preventDefault();
    zoomImg.src = src;
    zoomTitle.textContent = img.getAttribute('data-title') || img.alt || 'Foto Produk';
    if (zoomModal) zoomModal.show();
  });
  if (zoomImg) zoomImg.addEventListener('click', function(){ if (zoomModal) zoomModal.hide(); });
});
</script>


<!-- ===== Revisi 3 Jun 2026 #2: Modal Lacak Driver Realtime ===== -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<div class="modal fade" id="lacakModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title"><i class="bi bi-geo-alt-fill"></i> Lacak Driver — <span id="lacakKode">-</span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2">
        <div class="d-flex justify-content-between align-items-center mb-2 small">
          <div>
            Status: <span id="lacakStatus" class="badge bg-info">-</span>
            <span id="lacakAuto" class="badge bg-success-subtle text-success ms-1">
              <i class="bi bi-arrow-repeat"></i> Auto-refresh 5 dtk
            </span>
          </div>
          <button type="button" id="btnRefreshDriver" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-arrow-clockwise"></i> Refresh
          </button>
        </div>
        <div id="lacakMap" style="height:360px;border-radius:.5rem;border:1px solid #e5e7eb"></div>
        <div class="small text-muted mt-2" id="lacakInfo">Mengambil data driver…</div>
      </div>
    </div>
  </div>
</div>

<style>
/* ===== Marker desain (pemesan & kurir) ===== */
.jjn-marker{
  display:flex;align-items:center;justify-content:center;
  width:44px;height:44px;border-radius:50%;
  font-size:20px;color:#fff;border:3px solid #fff;
  box-shadow:0 4px 14px rgba(0,0,0,.35);
  position:relative;
}
.jjn-marker::after{
  content:"";position:absolute;left:50%;bottom:-8px;
  transform:translateX(-50%);
  border:7px solid transparent;border-top-color:inherit;
}
.jjn-marker-pemesan{ background:#16a34a; border-color:#bbf7d0; }
.jjn-marker-pemesan::after{ color:#16a34a; }
.jjn-marker-kurir{ background:#dc2626; border-color:#fecaca; }
.jjn-marker-kurir::after{ color:#dc2626; }
.jjn-marker-uin{ background:#2563eb; border-color:#bfdbfe; width:38px;height:38px;font-size:17px; }
.jjn-marker-uin::after{ color:#2563eb; }
.jjn-pulse{
  position:absolute; inset:-6px; border-radius:50%;
  background:rgba(220,38,38,.35); animation:jjnPulse 1.6s ease-out infinite;
  z-index:-1;
}
@keyframes jjnPulse{
  0%{ transform:scale(.6); opacity:.9 }
  100%{ transform:scale(1.6); opacity:0 }
}
</style>
<script>
(function(){
  var map=null, mDriver=null, mPickup=null, mUIN=null, routeLine=null, pollTimer=null, currentKode=null;
  var UIN = {lat: <?= $UIN_LAT ?>, lng: <?= $UIN_LNG ?>};

  function divIcon(cls, html, size){
    size = size || 44;
    return L.divIcon({
      className:'jjn-divicon',
      html:'<div class="jjn-marker '+cls+'">'+html+'</div>',
      iconSize:[size,size], iconAnchor:[size/2,size/2], popupAnchor:[0,-size/2]
    });
  }

  function ensureMap(){
    if (map) return;
    map = L.map('lacakMap', {zoomControl:true}).setView([UIN.lat, UIN.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    mUIN = L.marker([UIN.lat, UIN.lng], {
      icon: divIcon('jjn-marker-uin','<i class="bi bi-mortarboard-fill"></i>',38),
      title:'UIN SGD Bandung'
    }).addTo(map).bindPopup('<strong>UIN SGD Bandung</strong><br><small>Titik referensi kampus</small>');
  }

  function timeAgo(iso){
    if (!iso) return '-';
    var d = new Date(iso), s = Math.max(0, Math.floor((Date.now()-d.getTime())/1000));
    if (s<60) return s+' detik lalu';
    if (s<3600) return Math.floor(s/60)+' menit lalu';
    return Math.floor(s/3600)+' jam lalu';
  }

  function drawRoute(driver, pickup){
    if (!driver || !pickup) { if (routeLine){ map.removeLayer(routeLine); routeLine=null; } return; }
    var latlngs = [[driver.lat,driver.lng],[pickup.lat,pickup.lng]];
    if (!routeLine){
      routeLine = L.polyline(latlngs, {color:'#dc2626', weight:4, opacity:.7, dashArray:'8 8'}).addTo(map);
    } else routeLine.setLatLngs(latlngs);
  }

  function fetchOnce(){
    if (!currentKode) return;
    fetch('/jajanan.php?ajax=driver_loc&kode='+encodeURIComponent(currentKode), {credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){
        if (!j.ok) { document.getElementById('lacakInfo').textContent = j.error||'Gagal memuat'; return; }
        var st = document.getElementById('lacakStatus');
        st.textContent = j.status; st.className='badge bg-info';
        var pts = [[UIN.lat,UIN.lng]];

        if (j.pickup) {
          var iconPemesan = divIcon('jjn-marker-pemesan','<i class="bi bi-person-fill"></i>');
          if (!mPickup) {
            mPickup = L.marker([j.pickup.lat, j.pickup.lng], {icon:iconPemesan, title:'Pemesan'})
                       .addTo(map).bindPopup('<strong>📍 Lokasi Pemesan</strong><br><small>Tujuan pengantaran</small>');
          } else { mPickup.setLatLng([j.pickup.lat, j.pickup.lng]); mPickup.setIcon(iconPemesan); }
          pts.push([j.pickup.lat, j.pickup.lng]);
        }
        if (j.has_driver && j.driver) {
          var iconKurir = divIcon('jjn-marker-kurir','<span class="jjn-pulse"></span><i class="bi bi-scooter"></i>');
          if (!mDriver) {
            mDriver = L.marker([j.driver.lat, j.driver.lng], {icon:iconKurir, title:'Kurir'})
                       .addTo(map).bindPopup('<strong>🛵 Kurir</strong><br><small>Posisi realtime</small>');
          } else { mDriver.setLatLng([j.driver.lat, j.driver.lng]); mDriver.setIcon(iconKurir); }
          pts.push([j.driver.lat, j.driver.lng]);
          drawRoute(j.driver, j.pickup);
          document.getElementById('lacakInfo').innerHTML =
            '<i class="bi bi-broadcast text-danger"></i> Kurir terakhir update: <strong>'+timeAgo(j.driver.updated_at)+'</strong>';
        } else if (j.has_driver) {
          document.getElementById('lacakInfo').innerHTML =
            '<i class="bi bi-hourglass-split"></i> Kurir sudah ditugaskan tapi belum mengaktifkan berbagi lokasi.';
        } else {
          document.getElementById('lacakInfo').innerHTML =
            '<i class="bi bi-info-circle"></i> Belum ada kurir yang mengambil pesanan ini.';
        }
        if (pts.length>1) map.fitBounds(pts, {padding:[40,40], maxZoom:17});
      })
      .catch(function(){ document.getElementById('lacakInfo').textContent='Koneksi gagal, akan dicoba lagi…'; });
  }

  var modalEl = document.getElementById('lacakModal');
  var modal = null;
  function getLacakModal(){
    if (!modal && typeof bootstrap !== 'undefined' && modalEl) modal = new bootstrap.Modal(modalEl);
    return modal;
  }

  document.addEventListener('click', function(ev){
    var btn = ev.target.closest && ev.target.closest('.btn-lacak');
    if (!btn) return;
    currentKode = btn.getAttribute('data-kode');
    document.getElementById('lacakKode').textContent = currentKode;
    document.getElementById('lacakInfo').textContent = 'Mengambil data kurir…';
    if (window.JJN_PRELOAD) window.JJN_PRELOAD.show('Membuka pelacak kurir…');
    var m = getLacakModal();
    if (m) m.show();
    setTimeout(function(){
      if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide();
      ensureMap(); map.invalidateSize(); fetchOnce();
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(fetchOnce, 5000);
    }, 300);
  });
  document.getElementById('btnRefreshDriver').addEventListener('click', fetchOnce);
  if (modalEl) modalEl.addEventListener('hidden.bs.modal', function(){
    if (pollTimer) { clearInterval(pollTimer); pollTimer=null; }
    if (mDriver){ map.removeLayer(mDriver); mDriver=null; }
    if (mPickup){ map.removeLayer(mPickup); mPickup=null; }
    if (routeLine){ map.removeLayer(routeLine); routeLine=null; }
  });
})();
</script>

<!-- ===================================================================
     Revisi 4 Juni 2026 — Preloader, Modal Toko (multi-item),
     Detail Pesanan, Rating Bintang, dan efek-efek indah.
     =================================================================== -->
<style>
/* ===== #3 Preloader good-looking ===== */
#jjnPreloader{
  position:fixed; inset:0; z-index:11000;
  background:linear-gradient(135deg,#16a34a 0%,#22c55e 45%,#0ea5e9 100%);
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  color:#fff; transition:opacity .5s ease, visibility .5s ease;
}
#jjnPreloader.hide{ opacity:0; visibility:hidden; pointer-events:none; }
#jjnPreloader .cup{
  position:relative; width:90px; height:90px; margin-bottom:1rem;
  animation: jjnFloat 2.4s ease-in-out infinite;
  filter: drop-shadow(0 10px 18px rgba(0,0,0,.25));
}
#jjnPreloader .cup .steam{
  position:absolute; left:50%; top:-22px; width:4px; height:20px;
  background:rgba(255,255,255,.85); border-radius:4px;
  transform:translateX(-50%); animation: jjnSteam 1.6s ease-in-out infinite;
  opacity:.85;
}
#jjnPreloader .cup .steam.s2{ left:calc(50% - 14px); animation-delay:.4s; }
#jjnPreloader .cup .steam.s3{ left:calc(50% + 14px); animation-delay:.8s; }
#jjnPreloader .title{ font-weight:800; letter-spacing:.3px; font-size:1.1rem; }
#jjnPreloader .sub{ font-size:.78rem; opacity:.9; margin-top:.25rem; }
#jjnPreloader .bar{
  margin-top:1rem; width:200px; height:6px; background:rgba(255,255,255,.25);
  border-radius:999px; overflow:hidden; position:relative;
}
#jjnPreloader .bar::after{
  content:""; position:absolute; inset:0; width:40%; background:#fff; border-radius:999px;
  animation: jjnBar 1.2s ease-in-out infinite;
}
@keyframes jjnFloat{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
@keyframes jjnSteam{
  0%{ transform:translate(-50%,0) scaleY(.4); opacity:.0 }
  40%{ opacity:.9 }
  100%{ transform:translate(-50%,-20px) scaleY(1.2); opacity:0 }
}
@keyframes jjnBar{
  0%{ left:-40% } 100%{ left:100% }
}

/* ===== #6 Efek tambahan ===== */
.jjn-card{ transition: transform .18s ease, box-shadow .18s ease; }
.jjn-card:hover{ transform: translateY(-3px); box-shadow:0 12px 24px -10px rgba(0,0,0,.18); }
.jjn-shimmer{
  background:linear-gradient(90deg,#eef2f7 0%,#f8fafc 50%,#eef2f7 100%);
  background-size:200% 100%; animation: jjnShimmer 1.2s linear infinite; border-radius:.4rem;
}
@keyframes jjnShimmer{ 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.jjn-fade-in{ animation: jjnFadeIn .35s ease both; }
@keyframes jjnFadeIn{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:none} }
.jjn-pop{ animation: jjnPop .25s ease both; }
@keyframes jjnPop{ 0%{transform:scale(.94); opacity:0} 100%{transform:scale(1); opacity:1} }
.jjn-chip-live{ display:inline-flex; align-items:center; gap:.3rem; }
.jjn-chip-live .dot{
  width:8px; height:8px; border-radius:50%; background:#16a34a;
  box-shadow:0 0 0 0 rgba(22,163,74,.6); animation: jjnDot 1.4s ease-out infinite;
}
@keyframes jjnDot{
  0%{ box-shadow:0 0 0 0 rgba(22,163,74,.6) }
  100%{ box-shadow:0 0 0 12px rgba(22,163,74,0) }
}

/* ===== #4 Rating bintang ===== */
.jjn-star-pick{ font-size:2rem; color:#d4d4d8; cursor:pointer; transition:transform .1s ease, color .1s ease; }
.jjn-star-pick:hover{ transform:scale(1.15); }
.jjn-star-pick.on{ color:#f59e0b; }

/* ===== #5 Modal Toko ===== */
.jjn-toko-head{
  background:linear-gradient(135deg,#16a34a,#0ea5e9);
  color:#fff; padding:1rem 1rem .8rem; border-top-left-radius:.5rem; border-top-right-radius:.5rem;
}
.jjn-toko-prod{
  display:flex; gap:.6rem; align-items:center; padding:.55rem; border:1px solid #e5e7eb;
  border-radius:.55rem; background:#fff; transition:background .15s ease, border-color .15s ease;
}
.jjn-toko-prod:hover{ background:#f8fafc; border-color:#cbd5e1; }
.jjn-toko-prod img{ width:56px; height:56px; object-fit:cover; border-radius:.4rem; flex-shrink:0; }
.jjn-toko-prod .ph{ width:56px; height:56px; border-radius:.4rem; background:#f1f5f9; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.jjn-toko-prod .nm{ font-weight:600; font-size:.85rem; line-height:1.2; }
.jjn-toko-prod .pr{ color:#16a34a; font-weight:700; font-size:.78rem; }
.jjn-toko-prod .meta{ font-size:.7rem; color:#6b7280; }
.jjn-toko-prod .qbox{ display:flex; align-items:center; gap:.15rem; flex-shrink:0; }
.jjn-toko-prod .qbox input{ width:46px; text-align:center; }
.jjn-toko-prod.disabled{ opacity:.55; background:#f8fafc; }
.jjn-cart-summary{
  position:sticky; bottom:0; background:#fff; border-top:1px solid #e5e7eb;
  padding:.65rem .75rem; margin:.5rem -1rem -1rem; box-shadow:0 -6px 14px -10px rgba(0,0,0,.15);
}

/* === Revisi: paksa #tokoModal scrollable. Wrapper <form> di antara
   .modal-content & .modal-body memutus layout flex bawaan Bootstrap
   `modal-dialog-scrollable`, sehingga body tidak punya tinggi terbatas
   dan tidak bisa di-scroll. Rule berikut memulihkan layout flex
   end-to-end (modal-content → form → modal-body) + sticky footer
   yang tetap terlihat saat keyboard mobile muncul. */
#tokoModal .modal-dialog{ display:flex; align-items:stretch; max-height:100dvh; }
#tokoModal .modal-content{
  display:flex; flex-direction:column;
  max-height:calc(100dvh - 1rem); height:auto;
  overflow:hidden;
}
#tokoModal .jjn-toko-head{ flex:0 0 auto; }
#tokoModal form{
  display:flex; flex-direction:column;
  flex:1 1 auto; min-height:0; overflow:hidden;
}
#tokoModal .modal-body{
  flex:1 1 auto; min-height:0;
  overflow-y:auto !important; -webkit-overflow-scrolling:touch;
  padding-bottom:1rem;
}
#tokoModal .modal-footer{
  flex:0 0 auto;
  position:sticky; bottom:0; background:#fff; z-index:5;
  border-top:1px solid #e5e7eb;
  box-shadow:0 -6px 14px -10px rgba(0,0,0,.2);
  padding-bottom:calc(.75rem + env(safe-area-inset-bottom,0px));
}
@media (max-width:576px){
  #tokoModal .modal-dialog{ margin:0; height:100dvh; max-height:100dvh; max-width:100%; }
  #tokoModal .modal-content{ height:100dvh; max-height:100dvh; border-radius:0; }
}

/* ===== Detail Pesanan ===== */
.jjn-detail-row{ display:flex; justify-content:space-between; padding:.25rem 0; font-size:.85rem; }
.jjn-detail-row.total{ font-weight:700; border-top:1px dashed #cbd5e1; padding-top:.4rem; margin-top:.3rem; color:#16a34a; }
.jjn-kurir-card{
  background:linear-gradient(135deg,#fef3c7,#fef9c3); border:1px solid #fde68a;
  border-radius:.5rem; padding:.6rem .7rem; font-size:.82rem;
}
.jjn-kurir-avatar{ width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.12); }
.jjn-kurir-avatar-fallback{ display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#f59e0b,#dc2626); color:#fff; font-weight:700; font-size:1.4rem; }
</style>

<!-- ===== Preloader ===== -->
<div id="jjnPreloader" aria-hidden="true">
  <div class="cup" aria-hidden="true">
    <span class="steam s1"></span><span class="steam s2"></span><span class="steam s3"></span>
    <!-- SVG cup -->
    <svg viewBox="0 0 100 100" width="90" height="90" xmlns="http://www.w3.org/2000/svg">
      <path d="M20 36h55v20a22 22 0 0 1-22 22h-11A22 22 0 0 1 20 56V36z" fill="#fff"/>
      <path d="M75 42h6a10 10 0 0 1 0 20h-6v-6h6a4 4 0 0 0 0-8h-6v-6z" fill="#fff"/>
      <ellipse cx="47.5" cy="36" rx="27.5" ry="5" fill="#fff" opacity=".7"/>
    </svg>
  </div>
  <div class="title">Memuat Jajanan Favorit</div>
  <div class="sub">Menyiapkan menu terbaik untukmu…</div>
  <div class="bar" aria-hidden="true"></div>
</div>
<script>
  // Preloader: auto-hide on load, plus expose JJN_PRELOAD.show/hide untuk submit & aksi tombol
  (function(){
    var pl = document.getElementById('jjnPreloader'); if (!pl) return;
    var subEl = pl.querySelector('.sub');
    var defaultSub = subEl ? subEl.textContent : '';
    function show(msg){
      if (subEl && msg) subEl.textContent = msg;
      pl.classList.remove('hide');
      pl.style.removeProperty('display');
      pl.style.opacity = '';
      pl.style.visibility = '';
    }
    function hide(){
      pl.classList.add('hide');
      if (subEl) subEl.textContent = defaultSub;
    }
    window.JJN_PRELOAD = { show: show, hide: hide };
    if (document.readyState === 'complete') { setTimeout(hide, 250); }
    else { window.addEventListener('load', function(){ setTimeout(hide, 250); }); }
    setTimeout(hide, 2500); // hard fallback awal load
  })();
</script>

<!-- ===== Modal Toko (multi-item) ===== -->
<div class="modal fade" id="tokoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg modal-fullscreen-sm-down">
    <div class="modal-content jjn-pop">
      <div class="jjn-toko-head">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="small opacity-75"><i class="bi bi-shop"></i> Toko</div>
            <h5 class="mb-0" id="tokoNama">-</h5>
            <div class="small" id="tokoAlamat" style="opacity:.9"></div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
      </div>
      <form id="tokoForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="items" id="tokoItemsJson">
        <div class="modal-body">
          <div id="tokoProdList" class="d-grid gap-2 mb-3">
            <div class="jjn-shimmer" style="height:64px"></div>
            <div class="jjn-shimmer" style="height:64px"></div>
            <div class="jjn-shimmer" style="height:64px"></div>
          </div>

          <div class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="small">Nama Pemesan</label>
                <input class="form-control form-control-sm" name="nama" required value="<?= htmlspecialchars($u['nama'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="small">Nomor WhatsApp</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text">+62</span>
                  <input class="form-control" name="no_wa" id="tk_wa" required inputmode="numeric"
                         placeholder="81234567890"
                         value="<?= htmlspecialchars(preg_replace('/^(\+?62|0)/','', $u['nomor_wa'] ?? '')) ?>">
                </div>
              </div>
              <div class="col-12">
                <label class="small">Alamat Lengkap Pengantaran</label>
                <textarea class="form-control form-control-sm" name="alamat" rows="2" required></textarea>
          <label class="small mt-2">Email (untuk invoice)</label>
          <input class="form-control form-control-sm mb-2" type="email" name="email" placeholder="email@anda.com (opsional)" value="<?= htmlspecialchars($u['email'] ?? '') ?>">

              </div>
              <div class="col-12">
                <label class="small">Catatan (opsional)</label>
                <input class="form-control form-control-sm" name="catatan" placeholder="cth: gerbang biru, pagar besi">
              </div>
            </div>
          </div>

          <div class="border rounded p-2 bg-light-subtle mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <button type="button" id="tkDetectLoc" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-geo-alt-fill"></i> Deteksi Lokasi Saya
              </button>
              <span id="tkLocCoords" class="small text-muted">Lat/Lng belum terdeteksi</span>
              <input type="hidden" name="pickup_lat" id="tk_lat">
              <input type="hidden" name="pickup_lng" id="tk_lng">
            </div>
            <div class="alert alert-warning small mt-2 mb-0 py-1" id="tkLocRequired">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <strong>Wajib:</strong> klik tombol di atas. Tanpa lokasi, pembayaran Midtrans tidak dapat diproses.
            </div>
            <div id="tkLocWarn" class="alert alert-danger small mt-2 mb-0 d-none">
              Lokasi di luar jangkauan layanan (>&nbsp;<?= $UIN_R_MAX_KM ?>&nbsp;km).
            </div>
          </div>

          <div class="jjn-cart-summary">
            <div class="d-flex justify-content-between small"><span>Subtotal (<span id="tk_qtot">0</span> item)</span><strong id="tk_sub">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>PPN <?= (int)($PPN_RATE*100) ?>%</span><strong id="tk_ppn">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Biaya Admin Midtrans</span><strong id="tk_fee">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Biaya Aplikasi</span><strong id="tk_app">Rp 0</strong></div>
            <div class="d-flex justify-content-between small"><span>Ongkir <span id="tk_ongnote" class="text-muted">(flat)</span></span><strong id="tk_ong">Rp <?= number_format($ONGKIR_FALLBACK,0,',','.') ?></strong></div>
            <hr class="my-1">
            <div class="d-flex justify-content-between"><span class="fw-semibold">Total Bayar</span><strong class="text-success" id="tk_total">Rp 0</strong></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm" id="tkBayar" disabled>
            <i class="bi bi-credit-card-2-front"></i> Bayar via Midtrans
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Modal Detail Pesanan ===== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content jjn-pop">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title"><i class="bi bi-receipt"></i> Detail Pesanan — <span id="dtKode">-</span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="dtBody">
        <div class="jjn-shimmer" style="height:120px"></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal Rating ===== -->
<div class="modal fade" id="ratingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content jjn-pop">
      <div class="modal-header bg-warning text-dark py-2">
        <h6 class="modal-title"><i class="bi bi-star-fill"></i> Beri Rating — <span id="rtKode">-</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="ratingForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="kode" id="rt_kode">
        <input type="hidden" name="rating" id="rt_value" value="0">
        <div class="modal-body text-center">
          <div class="small text-muted mb-2">Bagaimana pengalaman pesanan Anda?</div>
          <div id="rtStars" class="d-flex justify-content-center gap-2 mb-2" role="radiogroup" aria-label="Rating">
            <?php for($s=1;$s<=5;$s++): ?>
              <i class="bi bi-star jjn-star-pick" data-val="<?= $s ?>" role="radio" aria-checked="false" tabindex="0"></i>
            <?php endfor; ?>
          </div>
          <div class="small mb-2" id="rtLabel" style="min-height:1.2em">&nbsp;</div>
          <textarea class="form-control form-control-sm" name="komentar" rows="2" placeholder="Komentar (opsional)"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-warning" id="rtSubmit" disabled><i class="bi bi-send"></i> Kirim Rating</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  var UIN = {lat: <?= $UIN_LAT ?>, lng: <?= $UIN_LNG ?>, max_km: <?= $UIN_R_MAX_KM ?>};
  var ONGKIR_BASE = <?= (int)$ONGKIR_BASE ?>, ONGKIR_PER_KM = <?= (int)$ONGKIR_PER_KM ?>, ONGKIR_FALLBACK = <?= (int)$ONGKIR_FALLBACK ?>;
  var PPN_RATE = <?= json_encode($PPN_RATE) ?>;
  var MT_FEE_FIXED = <?= (int)$MIDTRANS_FEE_FIXED ?>, MT_FEE_PCT = <?= json_encode($MIDTRANS_FEE_PCT) ?>;
  var APP_FEE_FIXED = <?= (int)$APP_FEE_FIXED ?>, APP_FEE_PCT = <?= json_encode($APP_FEE_PCT) ?>;
  var CSRF = <?= json_encode(csrf_token()) ?>;

  function fmtRp(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID'); }
  function haversine(a,b){var R=6371000,toRad=Math.PI/180,dLat=(b.lat-a.lat)*toRad,dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)**2+Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(s));}

  /* ===================== MODAL TOKO (multi item) ===================== */
  var tkModalEl = document.getElementById('tokoModal');
  var tkModal = null;
  function getTkModal(){
    if (!tkModal && typeof bootstrap !== 'undefined' && tkModalEl) tkModal = new bootstrap.Modal(tkModalEl);
    return tkModal;
  }
  var tkState = { tokoId:null, prods:[], qty:{}, distKm:null, locValid:null, locDetected:false, preselect:null };

  function tkRecalc(){
    var sub=0, qtot=0;
    tkState.prods.forEach(function(p){
      var q = parseInt(tkState.qty[p.id]||0,10) || 0;
      if (q>0){ sub += q*p.harga; qtot += q; }
    });
    var ong = (tkState.distKm!==null) ? Math.round(ONGKIR_BASE + tkState.distKm*ONGKIR_PER_KM) : ONGKIR_FALLBACK;
    var ppn = Math.round(sub*PPN_RATE);
    var fee = sub>0 ? (Math.round((sub+ppn+ong)*MT_FEE_PCT) + MT_FEE_FIXED) : 0;
    var appFee = sub>0 ? (Math.round((sub+ppn+ong)*APP_FEE_PCT) + APP_FEE_FIXED) : 0;
    var tot = sub + ppn + ong + fee + appFee;
    document.getElementById('tk_sub').textContent  = fmtRp(sub);
    document.getElementById('tk_ppn').textContent  = fmtRp(ppn);
    document.getElementById('tk_fee').textContent  = fmtRp(fee);
    var tkA=document.getElementById('tk_app'); if(tkA) tkA.textContent=fmtRp(appFee);
    document.getElementById('tk_ong').textContent  = fmtRp(ong);
    document.getElementById('tk_total').textContent= fmtRp(tot);
    document.getElementById('tk_qtot').textContent = qtot;
    document.getElementById('tk_ongnote').textContent = (tkState.distKm!==null) ? '('+tkState.distKm.toFixed(2)+' km)' : '(flat — share lokasi untuk akurat)';
    var btn = document.getElementById('tkBayar');
    btn.disabled = !(qtot>0 && tkState.locDetected && tkState.locValid!==false);
    btn.title = btn.disabled
      ? (qtot<=0 ? 'Pilih minimal 1 produk' : 'Klik "Deteksi Lokasi Saya" dulu')
      : '';
  }

  function tkRenderProds(){
    var list = document.getElementById('tokoProdList'); list.innerHTML='';
    if (!tkState.prods.length){
      list.innerHTML = '<div class="alert alert-warning small mb-0">Toko ini belum memiliki produk aktif.</div>';
      return;
    }
    tkState.prods.forEach(function(p){
      var row = document.createElement('div');
      row.className = 'jjn-toko-prod jjn-fade-in' + (p.is_open?'':' disabled');
      var jam = (p.jam_buka_short && p.jam_tutup_short) ? (' · '+p.jam_buka_short+'–'+p.jam_tutup_short) : '';
      var img = p.foto_url
        ? '<img src="'+p.foto_url.replace(/"/g,'&quot;')+'" alt="">'
        : '<div class="ph"><i class="bi bi-bag text-muted"></i></div>';
      var qty = parseInt(tkState.qty[p.id]||0,10) || 0;
      row.innerHTML =
        img +
        '<div class="flex-grow-1 min-w-0">' +
          '<div class="nm">'+ p.nama.replace(/</g,'&lt;') +'</div>'+
          '<div class="pr">'+ fmtRp(p.harga) +'</div>'+
          '<div class="meta">Stok: '+ p.stok + (p.kategori?(' · '+p.kategori):'') + jam + (p.is_open?'':' · <strong class="text-danger">Tutup</strong>') +'</div>'+
        '</div>' +
        '<div class="qbox">' +
          '<button type="button" class="btn btn-outline-success btn-sm tk-minus" '+(p.is_open?'':'disabled')+'>−</button>'+
          '<input type="number" class="form-control form-control-sm tk-input" value="'+qty+'" min="0" max="'+p.stok+'" inputmode="numeric" '+(p.is_open?'':'disabled')+'>'+
          '<button type="button" class="btn btn-outline-success btn-sm tk-plus" '+(p.is_open?'':'disabled')+'>+</button>'+
        '</div>';
      list.appendChild(row);
      var inp = row.querySelector('.tk-input');
      function setQ(v){
        v = Math.max(0, Math.min(p.stok, parseInt(v||0,10)||0));
        tkState.qty[p.id] = v; inp.value = v; tkRecalc();
      }
      row.querySelector('.tk-minus').addEventListener('click', function(){ setQ((parseInt(inp.value||0,10)||0)-1); });
      row.querySelector('.tk-plus' ).addEventListener('click', function(){ setQ((parseInt(inp.value||0,10)||0)+1); });
      inp.addEventListener('input', function(){ setQ(inp.value); });
    });
    // Preselect dari kartu yang diklik
    if (tkState.preselect){
      var ps = tkState.preselect;
      if (tkState.qty[ps.id]==null) { tkState.qty[ps.id] = ps.qty; }
      var inps = list.querySelectorAll('.tk-input');
      tkState.prods.forEach(function(p, i){
        if (p.id === ps.id && inps[i]){ inps[i].value = tkState.qty[p.id]; }
      });
      tkState.preselect = null;
    }
    tkRecalc();
  }

  function tkOpen(tokoId, tokoNama, preselect){
    tkState = { tokoId:tokoId, prods:[], qty:{}, distKm:null, locValid:null, locDetected:false, preselect: preselect||null };
    document.getElementById('tokoNama').textContent = tokoNama || 'Toko';
    document.getElementById('tokoAlamat').textContent = '';
    document.getElementById('tk_lat').value=''; document.getElementById('tk_lng').value='';
    document.getElementById('tkLocCoords').textContent='Lat/Lng belum terdeteksi';
    document.getElementById('tkLocWarn').classList.add('d-none');
    document.getElementById('tkLocRequired').classList.remove('d-none');
    var list = document.getElementById('tokoProdList');
    list.innerHTML = '<div class="jjn-shimmer" style="height:64px"></div><div class="jjn-shimmer" style="height:64px"></div><div class="jjn-shimmer" style="height:64px"></div>';
    var _tkM = getTkModal(); if (_tkM) _tkM.show();
    if (window.JJN_PRELOAD) { window.JJN_PRELOAD.show('Memuat produk toko…'); setTimeout(function(){ window.JJN_PRELOAD.hide(); }, 600); }
    fetch('/jajanan.php?ajax=toko_produk&toko_id='+encodeURIComponent(tokoId), {credentials:'same-origin', headers:{'Accept':'application/json'}})
      .then(function(r){ return r.text().then(function(t){
          try { return JSON.parse(t); }
          catch(e){ return {ok:false, error:'Respons server tidak valid (HTTP '+r.status+').'}; }
      });})
      .then(function(j){
        if (!j.ok){ list.innerHTML='<div class="alert alert-danger small">'+(j.error||'Gagal memuat')+'</div>'; return; }
        if (j.toko){
          document.getElementById('tokoNama').textContent = j.toko.nama;
          document.getElementById('tokoAlamat').textContent = j.toko.alamat || '';
        }
        tkState.prods = j.produk || [];
        tkRenderProds();
      })
      .catch(function(err){ list.innerHTML='<div class="alert alert-danger small">Koneksi gagal: '+(err && err.message ? err.message : 'tidak dapat menghubungi server')+'</div>'; });
  }

  // Klik tombol "Pesan Sekarang" pada kartu → buka modal toko
  document.addEventListener('click', function(ev){
    var b = ev.target.closest && ev.target.closest('.btn-pesan-toko');
    if (!b) return;
    var tid = b.getAttribute('data-toko-id');
    if (!tid){ alert('Produk ini belum ditautkan ke toko.'); return; }
    var card = b.closest('.card');
    var qcInp = card ? card.querySelector('.qc-input') : null;
    var preQty = qcInp ? Math.max(1, parseInt(qcInp.value||'1',10)) : 1;
    tkOpen(parseInt(tid,10), b.getAttribute('data-toko-nama')||'Toko', { id: parseInt(b.getAttribute('data-id'),10), qty: preQty });
  });

  // Nomor WA bersihkan
  var tkWa = document.getElementById('tk_wa');
  if (tkWa) tkWa.addEventListener('input', function(){
    var v = (this.value||'').replace(/\D+/g,'').replace(/^(62|0)+/, ''); this.value = v;
  });

  // Deteksi lokasi
  document.getElementById('tkDetectLoc').addEventListener('click', function(){
    if (!navigator.geolocation){ alert('Browser tidak mendukung GPS'); return; }
    var btn=this, orig=btn.innerHTML; btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mendeteksi…';
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude, lng=pos.coords.longitude;
      document.getElementById('tk_lat').value=lat.toFixed(6);
      document.getElementById('tk_lng').value=lng.toFixed(6);
      tkState.distKm = haversine({lat:lat,lng:lng}, UIN)/1000;
      document.getElementById('tkLocCoords').innerHTML =
        'Lat '+lat.toFixed(5)+' · Lng '+lng.toFixed(5)+' · <strong>'+tkState.distKm.toFixed(2)+' km</strong> ke UIN';
      var warn=document.getElementById('tkLocWarn');
      if (tkState.distKm>UIN.max_km){ warn.classList.remove('d-none'); tkState.locValid=false; }
      else { warn.classList.add('d-none'); tkState.locValid=true; }
      tkState.locDetected = true;
      document.getElementById('tkLocRequired').classList.add('d-none');
      tkRecalc(); btn.disabled=false; btn.innerHTML=orig;
    }, function(err){
      alert('Gagal mendapatkan lokasi: '+err.message);
      btn.disabled=false; btn.innerHTML=orig;
      tkState.locDetected=false; tkRecalc();
    }, {enableHighAccuracy:true, timeout:15000});
  });

  // Submit checkout multi-item
  document.getElementById('tokoForm').addEventListener('submit', function(e){
    e.preventDefault();
    var items = [];
    tkState.prods.forEach(function(p){
      var q = parseInt(tkState.qty[p.id]||0,10)||0;
      if (q>0) items.push({id:p.id, qty:q});
    });
    if (!items.length){ alert('Pilih minimal 1 produk'); return; }
    if (!tkState.locDetected){ alert('Mohon klik "Deteksi Lokasi Saya" dulu'); return; }
    if (tkState.locValid===false){ alert('Lokasi di luar jangkauan.'); return; }
    document.getElementById('tokoItemsJson').value = JSON.stringify(items);
    var btn = document.getElementById('tkBayar'); btn.disabled=true; var orig=btn.innerHTML;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Memproses…';
    if (window.JJN_PRELOAD) window.JJN_PRELOAD.show('Memproses pembayaran…');
    var fd = new FormData(this);
    fetch('/jajanan.php?ajax=create_snap',{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){
        btn.disabled=false; btn.innerHTML=orig;
        if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide();
        if (!j.ok){ alert(j.error||'Gagal'); return; }
        if (typeof window.snap === 'undefined'){
          if (j.redirect){ window.location.href = j.redirect; return; }
          alert('Snap.js belum dimuat'); return;
        }
        window.snap.pay(j.token, {
          onSuccess: function(){ window.location.href='/jajanan.php?berhasil='+encodeURIComponent(j.kode); },
          onPending: function(){ window.location.href='/jajanan.php?berhasil='+encodeURIComponent(j.kode); },
          onError:   function(){ alert('Pembayaran gagal'); window.location.href='/jajanan.php?berhasil='+encodeURIComponent(j.kode); },
          onClose:   function(){ window.location.href='/jajanan.php?berhasil='+encodeURIComponent(j.kode); }
        });
      })
      .catch(function(){ btn.disabled=false; btn.innerHTML=orig; if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide(); alert('Koneksi gagal'); });
  });

  /* ===================== MODAL DETAIL PESANAN ===================== */
  var dtModalEl = document.getElementById('detailModal');
  var dtModal = null;
  function getDtModal(){
    if (!dtModal && typeof bootstrap !== 'undefined' && dtModalEl) dtModal = new bootstrap.Modal(dtModalEl);
    return dtModal;
  }

  document.addEventListener('click', function(ev){
    var b = ev.target.closest && ev.target.closest('.btn-detail');
    if (!b) return;
    var kode = b.getAttribute('data-kode');
    document.getElementById('dtKode').textContent = kode;
    var body = document.getElementById('dtBody');
    body.innerHTML = '<div class="jjn-shimmer mb-2" style="height:24px"></div>'+
                     '<div class="jjn-shimmer mb-2" style="height:80px"></div>'+
                     '<div class="jjn-shimmer" style="height:60px"></div>';
    if (window.JJN_PRELOAD) { window.JJN_PRELOAD.show('Memuat detail pesanan…'); setTimeout(function(){ window.JJN_PRELOAD.hide(); }, 500); }
    var _dtM = getDtModal(); if (_dtM) _dtM.show();
    fetch('/jajanan.php?ajax=detail_pesanan&kode='+encodeURIComponent(kode),{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){
        if (!j.ok){ body.innerHTML = '<div class="alert alert-danger small mb-0">'+(j.error||'Gagal memuat')+'</div>'; return; }
        var o = j.order || {};
        var itemsHtml = (j.items||[]).map(function(it){
          var line = (parseInt(it.harga,10)||0) * (parseInt(it.qty,10)||0);
          return '<div class="jjn-detail-row">'+
                  '<span><strong>'+(parseInt(it.qty,10)||0)+'×</strong> '+
                    (it.nama||'').replace(/</g,'&lt;') +
                    (it.toko_nama?'<div class="small text-muted">'+ it.toko_nama.replace(/</g,'&lt;') +'</div>':'') +
                  '</span>'+
                  '<strong>'+fmtRp(line)+'</strong>'+
                 '</div>';
        }).join('') || '<div class="text-muted small">Tidak ada item.</div>';
        var sub = parseInt(o.subtotal||0,10), ong = parseInt(o.ongkir||0,10), tot = parseInt(o.total||0,10);
        var sisa = Math.max(0, tot - sub - ong);
        var kurirHtml = '';
        if (j.kurir){
          var fotoSrc = j.kurir.foto_url ? j.kurir.foto_url : '';
          var initial = (j.kurir.nama||'?').charAt(0).toUpperCase();
          var avatar = fotoSrc
            ? '<img src="'+fotoSrc.replace(/"/g,'&quot;')+'" alt="" class="jjn-kurir-avatar">'
            : '<div class="jjn-kurir-avatar jjn-kurir-avatar-fallback">'+initial+'</div>';
          var waDisp = j.kurir.wa_display || (j.kurir.no_wa ? ('+'+j.kurir.no_wa) : '-');
          kurirHtml =
            '<div class="jjn-kurir-card mt-3">'+
              '<div class="fw-semibold mb-2"><i class="bi bi-scooter"></i> Kurir Anda</div>'+
              '<div class="d-flex align-items-center gap-3">'+
                avatar +
                '<div class="flex-grow-1">'+
                  '<div><strong>'+(j.kurir.nama||'-').replace(/</g,'&lt;')+'</strong></div>'+
                  '<div class="small"><i class="bi bi-telephone-fill"></i> '+waDisp+'</div>'+
                '</div>'+
              '</div>'+
              '<div class="mt-2 d-flex gap-2 flex-wrap">'+
                (j.kurir.wa_link?('<a class="btn btn-sm btn-success" href="'+j.kurir.wa_link+'" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Chat WhatsApp</a>'):'')+
                (j.kurir.tel    ?('<a class="btn btn-sm btn-outline-success" href="'+j.kurir.tel+'"><i class="bi bi-telephone"></i> Telepon</a>'):'')+
              '</div>'+
            '</div>';
        } else {
          kurirHtml = '<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle"></i> Belum ada kurir yang mengambil pesanan ini.</div>';
        }
        var ratingHtml = '';
        if (o.rating){
          var stars=''; for (var s=1;s<=5;s++){ stars += '<i class="bi bi-star'+(s<=parseInt(o.rating,10)?'-fill text-warning':'')+'"></i>'; }
          ratingHtml = '<div class="mt-3 small"><strong>Rating Anda:</strong> '+stars+
                       (o.rating_komentar?('<div class="text-muted">"'+ String(o.rating_komentar).replace(/</g,'&lt;') +'"</div>'):'') +'</div>';
        }
        body.innerHTML =
          '<div class="row g-2 small mb-2">'+
            '<div class="col-6"><div class="text-muted">Nama Pemesan</div><strong>'+(o.nama_pemesan||'-').replace(/</g,'&lt;')+'</strong></div>'+
            '<div class="col-6"><div class="text-muted">No WA Pemesan</div><strong>+'+(o.no_wa||'-')+'</strong></div>'+
            '<div class="col-12"><div class="text-muted">Alamat</div><div>'+(o.alamat||'-').replace(/</g,'&lt;')+'</div></div>'+
            (o.catatan?('<div class="col-12"><div class="text-muted">Catatan</div><div>'+ String(o.catatan).replace(/</g,'&lt;') +'</div></div>'):'')+
            '<div class="col-6"><div class="text-muted">Status</div><span class="badge bg-info">'+(o.status||'-')+'</span></div>'+
            '<div class="col-6"><div class="text-muted">Pembayaran</div><span class="badge bg-'+((o.payment_status==='paid')?'success':'secondary')+'">'+(o.payment_status||'-')+'</span></div>'+
          '</div>'+
          '<hr class="my-2">'+
          '<div class="small fw-semibold mb-1"><i class="bi bi-basket"></i> Item Pesanan</div>'+
          itemsHtml +
          '<div class="jjn-detail-row mt-2"><span>Subtotal</span><strong>'+fmtRp(sub)+'</strong></div>'+
          '<div class="jjn-detail-row"><span>PPN & Biaya Admin</span><strong>'+fmtRp(sisa)+'</strong></div>'+
          '<div class="jjn-detail-row"><span>Ongkir</span><strong>'+fmtRp(ong)+'</strong></div>'+
          '<div class="jjn-detail-row total"><span>Total</span><strong>'+fmtRp(tot)+'</strong></div>'+
          kurirHtml +
          ratingHtml;
      })
      .catch(function(){ body.innerHTML='<div class="alert alert-danger small mb-0">Koneksi gagal.</div>'; });
  });

  /* ===================== MODAL RATING ===================== */
  var rtModalEl = document.getElementById('ratingModal');
  var rtModal = null;
  function getRtModal(){
    if (!rtModal && typeof bootstrap !== 'undefined' && rtModalEl) rtModal = new bootstrap.Modal(rtModalEl);
    return rtModal;
  }
  var RATING_LABELS = ['','Sangat buruk','Buruk','Cukup','Bagus','Luar biasa!'];

  function rtSet(val){
    document.getElementById('rt_value').value = val;
    var stars = document.querySelectorAll('#rtStars .jjn-star-pick');
    stars.forEach(function(st){
      var v = parseInt(st.getAttribute('data-val'),10);
      st.classList.toggle('on', v<=val);
      st.classList.toggle('bi-star-fill', v<=val);
      st.classList.toggle('bi-star', v>val);
      st.setAttribute('aria-checked', v===val ? 'true':'false');
    });
    document.getElementById('rtLabel').textContent = RATING_LABELS[val] || '\u00A0';
    document.getElementById('rtSubmit').disabled = !(val>=1 && val<=5);
  }

  document.querySelectorAll('#rtStars .jjn-star-pick').forEach(function(st){
    st.addEventListener('click', function(){ rtSet(parseInt(st.getAttribute('data-val'),10)); });
    st.addEventListener('keydown', function(e){ if (e.key===' '||e.key==='Enter'){ e.preventDefault(); rtSet(parseInt(st.getAttribute('data-val'),10)); } });
  });

  document.addEventListener('click', function(ev){
    var b = ev.target.closest && ev.target.closest('.btn-rating');
    if (!b) return;
    var kode = b.getAttribute('data-kode');
    document.getElementById('rt_kode').value = kode;
    document.getElementById('rtKode').textContent = kode;
    rtSet(0);
    var ta = document.querySelector('#ratingForm textarea[name=komentar]'); if (ta) ta.value='';
    var _rtM = getRtModal(); if (_rtM) _rtM.show();
  });

  document.getElementById('ratingForm').addEventListener('submit', function(e){
    e.preventDefault();
    var btn = document.getElementById('rtSubmit'); btn.disabled=true; var orig=btn.innerHTML;
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Mengirim…';
    if (window.JJN_PRELOAD) window.JJN_PRELOAD.show('Mengirim rating…');
    var fd = new FormData(this);
    fetch('/jajanan.php?ajax=submit_rating',{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(j){
        btn.disabled=false; btn.innerHTML=orig;
        if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide();
        if (!j.ok){ alert(j.error||'Gagal mengirim rating'); return; }
        var _rtM = getRtModal(); if (_rtM) _rtM.hide();
        // Refresh halaman supaya tabel update badge bintang
        setTimeout(function(){ location.reload(); }, 250);
      })
      .catch(function(){ btn.disabled=false; btn.innerHTML=orig; if (window.JJN_PRELOAD) window.JJN_PRELOAD.hide(); alert('Koneksi gagal'); });
  });
})();
</script>

<?php htmx_layout_end(); ?>

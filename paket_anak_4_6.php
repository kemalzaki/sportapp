<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$u = current_user();
$pageTitle = 'Paket Anak — Usia 4–6 Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Anak — Usia 4–6 Tahun';
$pa_subjudul = 'Pengenalan olahraga melalui permainan menyenangkan.';
$pa_warna    = 'info';
$pa_icon     = 'bi-balloon-heart-fill';
$pa_rincian  = 'Pengenalan olahraga melalui permainan (lari, lompat, lempar, renang dasar, senam). '
             . 'Anak mulai dikenalkan dengan aturan sederhana, mengikuti instruksi pelatih/orang tua, '
             . 'serta belajar bergiliran (sportivitas awal).';
$pa_aktivitas = [
    'Lari estafet jarak pendek (10–20 m).',
    'Lompat tali dasar / lompat tanpa tali.',
    'Lempar bola ke target besar.',
    'Renang dasar (mengapung & meluncur dengan papan).',
    'Senam irama sederhana mengikuti musik.',
    'Permainan tradisional: petak umpet, engklek, ular naga.',
];
$pa_tips = [
    'Pemanasan ringan 5 menit sebelum bermain.',
    'Latihan 20–30 menit, 3–4x/minggu sudah cukup.',
    'Untuk renang: wajib pendamping & pelampung.',
    'Pakai sepatu olahraga lunak agar tidak licin.',
    'Berikan pujian positif, hindari kompetisi keras.',
];
$pa_aktivitas_img = '/assets/img/paket/anak_4_6.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

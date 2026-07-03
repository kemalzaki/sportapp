<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
require_once __DIR__.'/includes/paket_helpers.php';
require_login();
// Revisi R6 (Juli 2026) — Halaman ini dikunci untuk paket Pro & Komunitas.
paket_require_or_lock('pro', current_user(), 'Paket Anak 10-12 Tahun');
$u = current_user();
$pageTitle = 'Paket Anak — Usia 10–12 Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Anak — Usia 10–12 Tahun';
$pa_subjudul = 'Penguasaan teknik lanjutan & kompetisi tingkat dasar.';
$pa_warna    = 'primary';
$pa_icon     = 'bi-stars';
$pa_rincian  = 'Anak sudah mampu mengikuti latihan teknik lanjutan, strategi tim, dan kompetisi '
             . 'antar-sekolah / klub. Pada usia ini stamina & kekuatan otot mulai berkembang pesat, '
             . 'sehingga porsi latihan kekuatan ringan & kelincahan dapat ditingkatkan.';
$pa_aktivitas = [
    'Sepak bola / futsal 7v7 dengan posisi & taktik dasar.',
    'Bulu tangkis: smash, drop shot, lob.',
    'Renang 4 gaya (bebas, dada, punggung, kupu-kupu).',
    'Atletik: lari 100 m / 400 m, lompat tinggi, tolak peluru ringan.',
    'Basket mini & bola voli mini.',
    'Latihan kekuatan ringan (body-weight): push-up, plank, squat.',
];
$pa_tips = [
    'Latihan 4–5x/minggu dengan 1 hari istirahat penuh.',
    'Kenalkan jurnal latihan sederhana (durasi & perasaan).',
    'Awasi pertumbuhan: hindari beban angkat berlebih (cedera lempeng pertumbuhan).',
    'Pastikan tidur 9–10 jam/hari untuk pemulihan optimal.',
    'Konsumsi protein cukup (telur, ayam, tahu/tempe) & sayur-buah.',
];
$pa_aktivitas_img = '/assets/img/paket/anak_10_12.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

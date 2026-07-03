<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
require_once __DIR__.'/includes/paket_helpers.php';
require_login();
// Revisi R6 (Juli 2026) — Halaman ini dikunci untuk paket Pro & Komunitas.
paket_require_or_lock('pro', current_user(), 'Paket Lansia 70+ Tahun');
$u = current_user();
$pageTitle = 'Paket Lansia — Usia 70+ Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Lansia — Usia 70+ Tahun';
$pa_subjudul = 'Latihan ringan & aktivitas rekreasi yang menyenangkan.';
$pa_warna    = 'warning';
$pa_icon     = 'bi-house-heart-fill';
$pa_rincian  = "Latihan kekuatan ringan, Latihan keseimbangan untuk mengurangi risiko jatuh, "
             . "Latihan fleksibilitas, Latihan pernapasan, Aktivitas rekreasi yang menyenangkan.\n\n"
             . "Pada usia 70+ tujuan utama bukan lagi performa, melainkan kemandirian dan kualitas "
             . "hidup. Fokus: mencegah jatuh, menjaga massa otot (sarkopenia), serta menjaga "
             . "fungsi paru & mood. Pilih aktivitas yang menyenangkan agar konsisten.";
$pa_aktivitas = [
    'Latihan kekuatan ringan: berdiri-duduk dari kursi 10x, angkat botol air 500 ml.',
    'Latihan keseimbangan: berdiri dengan pegangan kursi 30 detik.',
    'Latihan fleksibilitas: peregangan lembut leher, bahu, kaki.',
    'Latihan pernapasan: napas perut 4-7-8 (4 menarik, 7 tahan, 8 mengeluarkan).',
    'Aktivitas rekreasi: berkebun ringan, bermain dengan cucu, jalan-jalan pagi.',
    'Senam duduk (chair exercise) bila keseimbangan kurang stabil.',
];
$pa_tips = [
    'WAJIB dengan pendamping bila keseimbangan menurun (cegah jatuh).',
    'Lingkungan latihan terang, lantai tidak licin, ada pegangan.',
    'Durasi singkat 10–20 menit, boleh dibagi 2 sesi per hari.',
    'Pantau tekanan darah & gula darah secara rutin.',
    'Konsumsi protein cukup (1,0–1,2 g/kg BB) untuk cegah sarkopenia.',
    'Hentikan & segera periksa bila ada keluhan nyeri, pusing, atau jatuh.',
];
$pa_aktivitas_img = '/assets/img/paket/lansia_70.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
require_once __DIR__.'/includes/paket_helpers.php';
require_login();
// Revisi R6 (Juli 2026) — Halaman ini dikunci untuk paket Pro & Komunitas.
paket_require_or_lock('pro', current_user(), 'Paket Lansia 55-69 Tahun');
$u = current_user();
$pageTitle = 'Paket Lansia — Usia 55–69 Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Lansia — Usia 55–69 Tahun';
$pa_subjudul = 'Aktivitas kebugaran ringan untuk menjaga vitalitas.';
$pa_warna    = 'info';
$pa_icon     = 'bi-heart-pulse-fill';
$pa_rincian  = "Senam kebugaran ringan, Jalan santai atau Nordic walking, Latihan keseimbangan, "
             . "Peregangan dan mobilitas sendi, Edukasi pola hidup sehat.\n\n"
             . "Fokus utama: menjaga kebugaran kardiovaskular, kelenturan otot, dan keseimbangan "
             . "tanpa membebani sendi. Latihan dilakukan dengan intensitas rendah-sedang dan "
             . "frekuensi 3–5x per minggu, 20–45 menit per sesi.";
$pa_aktivitas = [
    'Senam kebugaran ringan (senam lansia, tai chi).',
    'Jalan santai 30 menit / Nordic walking dengan tongkat.',
    'Latihan keseimbangan: berdiri 1 kaki 10–30 detik.',
    'Peregangan dinamis & statis untuk leher, bahu, pinggang.',
    'Mobilitas sendi lutut, panggul, pergelangan tangan.',
    'Edukasi pola hidup sehat: gizi seimbang, manajemen stres, tidur cukup.',
];
$pa_tips = [
    'Konsultasi dokter sebelum mulai bila ada hipertensi/diabetes/jantung.',
    'Pemanasan & pendinginan 5–10 menit, jangan dilewati.',
    'Pakai sepatu dengan bantalan baik untuk mencegah nyeri lutut.',
    'Minum air sebelum, selama, & setelah latihan (hindari dehidrasi).',
    'Hentikan latihan bila nyeri dada, pusing, atau sesak napas.',
];
$pa_aktivitas_img = '/assets/img/paket/lansia_55_69.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

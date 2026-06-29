<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$u = current_user();
$pageTitle = 'Paket Anak — Usia 2–4 Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Anak — Usia 2–4 Tahun';
$pa_subjudul = 'Aktivitas motorik dasar bersama pendamping orang tua.';
$pa_warna    = 'success';
$pa_icon     = 'bi-emoji-smile-fill';
$pa_rincian  = 'Aktivitas motorik dasar dengan pendamping orang tua. Belum berupa latihan olahraga formal. '
             . 'Fokus pada pengenalan gerak (merangkak, berjalan, melompat kecil, menendang & melempar bola lunak) '
             . 'serta stimulasi keseimbangan dan koordinasi mata–tangan–kaki melalui permainan singkat 10–15 menit.';
$pa_aktivitas = [
    'Berjalan & berlari kecil di area aman (matras / rumput).',
    'Melempar & menangkap bola lunak/plush.',
    'Menendang bola besar pelan-pelan.',
    'Menari mengikuti irama musik anak (10 menit).',
    'Bermain perosotan rendah dengan pendamping.',
    'Senam pemanasan ringan ala "Tepuk-tepuk" bersama orang tua.',
];
$pa_tips = [
    'Selalu didampingi orang tua / pengasuh — tidak boleh ditinggal.',
    'Durasi singkat 10–15 menit, hentikan bila anak lelah / rewel.',
    'Gunakan alas matras lembut untuk mencegah cedera jatuh.',
    'Hindari beban berat & gerakan melompat dari ketinggian.',
    'Sediakan air minum dan istirahat tiap 5 menit.',
];
$pa_aktivitas_img = '/assets/img/paket/anak_2_4.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

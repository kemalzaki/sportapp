<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); enforce_session_timeout();
$u = current_user();
$pageTitle = 'Paket Anak — Usia 7–9 Tahun';
include __DIR__.'/includes/header.php';

$pa_judul    = 'Paket Anak — Usia 7–9 Tahun';
$pa_subjudul = 'Mulai belajar teknik dasar olahraga secara terstruktur.';
$pa_warna    = 'warning';
$pa_icon     = 'bi-trophy-fill';
$pa_rincian  = 'Mulai belajar teknik dasar olahraga secara terstruktur. Anak siap mengikuti latihan '
             . 'beraturan dengan pelatih, mengenal posisi & strategi sederhana, serta mulai mengikuti '
             . 'turnamen tingkat sekolah / komunitas.';
$pa_aktivitas = [
    'Sepak bola / futsal mini (5v5) dengan teknik dribbling & passing dasar.',
    'Bulu tangkis: pegangan raket forehand/backhand & servis.',
    'Renang gaya bebas & gaya dada formal.',
    'Atletik: lari sprint 60 m, lompat jauh dasar.',
    'Senam lantai: roll depan, kayang, sikap lilin.',
    'Bela diri dasar: pencak silat / karate anak.',
];
$pa_tips = [
    'Pemanasan & pendinginan masing-masing 5–10 menit.',
    'Frekuensi 3–4x/minggu, durasi 45–60 menit per sesi.',
    'Gunakan pelindung (shin guard, helm sepeda) bila perlu.',
    'Pantau tanda kelelahan: napas tersengal lama, nyeri sendi.',
    'Asupan air & camilan sehat sebelum/sesudah latihan.',
];
$pa_aktivitas_img = '/assets/img/paket/anak_7_9.jpg';
include __DIR__.'/includes/paket_age_render.php';
include __DIR__.'/includes/footer.php';

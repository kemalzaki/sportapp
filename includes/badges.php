<?php
// Badge / XP / Level / Streak engine
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

function level_from_xp(int $xp): int {
    // Level naik tiap 200 XP, max 99
    return min(99, 1 + intdiv($xp, 200));
}

function award_badge(int $userId, string $kode): bool {
    $b = db_one("SELECT * FROM badges WHERE kode=$1", [$kode]);
    if (!$b) return false;
    $exists = db_one("SELECT id FROM user_badges WHERE user_id=$1 AND badge_id=$2", [$userId, (int)$b['id']]);
    if ($exists) return false;
    db_exec("INSERT INTO user_badges(user_id,badge_id) VALUES($1,$2)", [$userId, (int)$b['id']]);
    db_exec("UPDATE users SET xp = xp + $1 WHERE id=$2", [(int)$b['xp'], $userId]);
    $newXp = (int) db_val("SELECT xp FROM users WHERE id=$1", [$userId]);
    db_exec("UPDATE users SET level=$1 WHERE id=$2", [level_from_xp($newXp), $userId]);
    notify($userId, 'badge', '🏅 Badge baru: '.$b['nama'], $b['deskripsi'] ?? '', '/profile.php');
    return true;
}

/** Evaluasi semua badge user setelah aktivitas baru. */
function recompute_badges(int $userId): void {
    // FIRST_CHECKIN
    $any = (int) db_val("SELECT COUNT(*) FROM absensi WHERE user_id=$1 AND metode='qr'", [$userId]);
    if ($any >= 1) award_badge($userId, 'FIRST_CHECKIN');

    // JOGGING_10
    $jog = (int) db_val("SELECT COUNT(*) FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1 AND j.jenis ILIKE 'Jogging'", [$userId]);
    if ($jog >= 10) award_badge($userId, 'JOGGING_10');

    // BADMINTON_WARRIOR
    $bad = (int) db_val("SELECT COUNT(*) FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1 AND j.jenis ILIKE 'Badminton'", [$userId]);
    if ($bad >= 10) award_badge($userId, 'BADMINTON_WARRIOR');

    // ALL_ROUNDER (3 jenis berbeda)
    $jenis = (int) db_val("SELECT COUNT(DISTINCT j.jenis) FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id WHERE a.user_id=$1 AND a.hadir=1", [$userId]);
    if ($jenis >= 3) award_badge($userId, 'ALL_ROUNDER');

    // NIGHT_RUNNER (jadwal jam_mulai >= 18:00 atau tanpa jam tapi nama mengandung 'malam')
    $nr = (int) db_val("SELECT COUNT(*) FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                       WHERE a.user_id=$1 AND a.hadir=1 AND (j.jam_mulai >= TIME '18:00' OR j.catatan ILIKE '%malam%')", [$userId]);
    if ($nr >= 5) award_badge($userId, 'NIGHT_RUNNER');

    // RAJIN_4W: hadir minimal 1x di 4 minggu terakhir berturut-turut
    $weeks = db_all("SELECT date_trunc('week', j.tanggal) AS w, COUNT(*) c FROM absensi a JOIN jadwal j ON j.id=a.jadwal_id
                     WHERE a.user_id=$1 AND a.hadir=1 AND j.tanggal >= CURRENT_DATE - INTERVAL '6 weeks'
                     GROUP BY 1 ORDER BY 1 DESC", [$userId]);
    $streak = 0; $expected = strtotime('monday this week');
    foreach ($weeks as $w) {
        $wts = strtotime($w['w']);
        if ($wts === $expected) { $streak++; $expected = strtotime('-1 week', $expected); }
        else break;
    }
    db_exec("UPDATE users SET streak_minggu=$1 WHERE id=$2", [$streak, $userId]);
    if ($streak >= 4) award_badge($userId, 'RAJIN_4W');

    // FORUM_STAR
    $f = (int) db_val("SELECT COUNT(*) FROM chat_forum WHERE user_id=$1", [$userId]);
    if ($f >= 50) award_badge($userId, 'FORUM_STAR');
}

function user_badges(int $userId): array {
    // Revisi 22 Juni 2026 R5 — DISTINCT ON badge_id menghindari badge tampil double
    // bila user_badges memiliki baris duplikat (data lama tanpa UNIQUE).
    return db_all("SELECT DISTINCT ON (b.id) b.*, ub.earned_at
                   FROM user_badges ub JOIN badges b ON b.id=ub.badge_id
                   WHERE ub.user_id=$1
                   ORDER BY b.id, ub.earned_at DESC", [$userId]);
}

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/islami_migrations.php';

function islami_pref(int $uid): array {
    $row = db_one("SELECT * FROM user_islami_pref WHERE user_id=$1", [$uid]);
    if (!$row) {
        db_exec("INSERT INTO user_islami_pref(user_id) VALUES($1) ON CONFLICT DO NOTHING", [$uid]);
        $row = db_one("SELECT * FROM user_islami_pref WHERE user_id=$1", [$uid]);
    }
    return $row ?: ['user_id'=>$uid,'hide_sapa'=>0,'mode_tenang'=>1,'kota'=>'Jakarta','negara'=>'Indonesia'];
}

function islami_set_pref(int $uid, array $fields): void {
    islami_pref($uid);
    $set = []; $params = []; $i = 1;
    foreach ($fields as $k=>$v) { $set[] = "$k=\$".$i; $params[] = $v; $i++; }
    if (!$set) return;
    $params[] = $uid;
    db_exec("UPDATE user_islami_pref SET ".implode(',', $set).", updated_at=now() WHERE user_id=\$".$i, $params);
}

function islami_touch_streak(int $uid, string $field): void {
    $f = preg_replace('/[^a-z_]/','', $field);
    if (!in_array($f, ['quran_done','dzikir_pagi','dzikir_petang','doa_done','subuh_walk','sedekah'], true)) return;
    db_exec("INSERT INTO islami_streak(user_id,tanggal,$f,poin) VALUES($1,CURRENT_DATE,1,10)
             ON CONFLICT (user_id,tanggal) DO UPDATE SET $f=1, poin=islami_streak.poin+10", [$uid]);
    islami_check_badges($uid);
}

function islami_log_challenge(int $uid, string $key, ?string $catatan = null): bool {
    try {
        db_exec("INSERT INTO challenge_log(user_id,challenge_key,tanggal,catatan) VALUES($1,$2,CURRENT_DATE,$3)
                 ON CONFLICT DO NOTHING", [$uid, $key, $catatan]);
        return true;
    } catch (Throwable $e) { return false; }
}

function islami_streak_count(int $uid): int {
    // hitung streak hari berturut-turut sampai hari ini
    $rows = db_all("SELECT tanggal FROM islami_streak WHERE user_id=$1 ORDER BY tanggal DESC LIMIT 365", [$uid]);
    $streak = 0; $cur = new DateTime('today');
    foreach ($rows as $r) {
        $d = new DateTime($r['tanggal']);
        $diff = (int)$cur->diff($d)->format('%a');
        if ($diff === $streak) { $streak++; $cur = $d; }
        else break;
    }
    return $streak;
}

function islami_award_badge(int $uid, string $key): void {
    try {
        db_exec("INSERT INTO islami_badges(user_id,badge_key) VALUES($1,$2) ON CONFLICT DO NOTHING", [$uid, $key]);
    } catch (Throwable $e) {}
}

function islami_check_badges(int $uid): void {
    $s = islami_streak_count($uid);
    if ($s >= 3)  islami_award_badge($uid, 'istiqamah_3hari');
    if ($s >= 7)  islami_award_badge($uid, 'istiqamah_7hari');
    if ($s >= 30) islami_award_badge($uid, 'istiqamah_30hari');
    $total = (int) db_val("SELECT COUNT(*) FROM challenge_log WHERE user_id=$1 AND challenge_key='ayat_harian'", [$uid]);
    if ($total >= 10) islami_award_badge($uid, '10_ayat');
    if ($total >= 30) islami_award_badge($uid, '30_ayat');
    $sw = (int) db_val("SELECT COUNT(*) FROM challenge_log WHERE user_id=$1 AND challenge_key='subuh_walk'", [$uid]);
    if ($sw >= 7) islami_award_badge($uid, 'subuh_walk_7');
}

function islami_badge_label(string $key): string {
    return [
        'istiqamah_3hari'=>'Istiqamah 3 Hari',
        'istiqamah_7hari'=>'Istiqamah 7 Hari',
        'istiqamah_30hari'=>'Istiqamah 30 Hari',
        '10_ayat'=>'10 Ayat',
        '30_ayat'=>'30 Ayat',
        'subuh_walk_7'=>'Subuh Walk 7×',
    ][$key] ?? $key;
}

// Konversi tanggal Masehi ke Hijriyah (perkiraan algoritmik – tabular Islamic calendar)
function masehi_ke_hijriyah(?DateTime $date = null): array {
    $date = $date ?: new DateTime('today');
    $jd = gregoriantojd((int)$date->format('m'), (int)$date->format('d'), (int)$date->format('Y'));
    $l = $jd - 1948440 + 10632;
    $n = (int) floor(($l - 1) / 10631);
    $l = $l - 10631 * $n + 354;
    $j = ((int)floor((10985 - $l) / 5316)) * ((int)floor((50 * $l) / 17719))
       + ((int)floor($l / 5670)) * ((int)floor((43 * $l) / 15238));
    $l = $l - ((int)floor((30 - $j) / 15)) * ((int)floor((17719 * $j) / 50))
       - ((int)floor($j / 16)) * ((int)floor((15238 * $j) / 43)) + 29;
    $m = (int) floor((24 * $l) / 709);
    $d = $l - (int) floor((709 * $m) / 24);
    $y = 30 * $n + $j - 30;
    return ['hari'=>$d,'bulan'=>$m,'tahun'=>$y];
}
function hijriyah_nama_bulan(int $m): string {
    $names = ['Muharram','Safar','Rabiul Awal','Rabiul Akhir','Jumadil Awal','Jumadil Akhir',
              'Rajab','Sya\'ban','Ramadhan','Syawal','Dzulqa\'dah','Dzulhijjah'];
    return $names[$m-1] ?? '-';
}

// Hari puasa sunnah Senin-Kamis berikutnya
function next_puasa_seninkamis(): DateTime {
    $d = new DateTime('today');
    for ($i=0;$i<7;$i++) {
        $w = (int)$d->format('N'); // 1=Mon..7=Sun
        if ($w === 1 || $w === 4) return $d;
        $d->modify('+1 day');
    }
    return new DateTime('today');
}

// Perkiraan tanggal Ramadhan & Idul Adha tahun berjalan/berikutnya (1 Ramadhan & 10 Dzulhijjah)
function hijri_event_to_gregorian(int $hijriMonth, int $hijriDay): DateTime {
    $today = new DateTime('today');
    $h = masehi_ke_hijriyah($today);
    $yearCandidates = [$h['tahun'], $h['tahun']+1];
    foreach ($yearCandidates as $yh) {
        // Iterasi cari tanggal Masehi yang menghasilkan (yh, hijriMonth, hijriDay)
        $start = clone $today; $start->modify('-30 days');
        for ($i=0;$i<400;$i++) {
            $hh = masehi_ke_hijriyah($start);
            if ($hh['tahun']===$yh && $hh['bulan']===$hijriMonth && $hh['hari']===$hijriDay) {
                if ($start >= $today) return $start;
                break;
            }
            $start->modify('+1 day');
        }
    }
    return new DateTime('today');
}

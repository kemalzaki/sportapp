<?php
// Helpers umum untuk SportApp v2

function hari_id(?string $tgl): string {
    if (!$tgl) return '-';
    $map = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    $en = date('l', strtotime($tgl));
    return $map[$en] ?? $en;
}

function user_avatar(?string $foto, string $nama, int $size = 28): string {
    $initial = htmlspecialchars(mb_strtoupper(mb_substr(trim($nama) ?: '?', 0, 1)));
    if ($foto) {
        $src = htmlspecialchars($foto);
        return '<img src="'.$src.'" alt="" class="user-avatar" style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;object-fit:cover;">';
    }
    return '<span class="user-avatar-fallback" style="width:'.$size.'px;height:'.$size.'px;font-size:'.($size*0.45).'px;">'.$initial.'</span>';
}

function user_name_with_avatar(?string $foto, string $nama, bool $online = false, int $size = 28): string {
    $av = user_avatar($foto, $nama, $size);
    $dot = $online ? '<span class="online-dot" title="Online"></span>' : '';
    return '<span class="user-with-avatar">'.$av.$dot.'<span>'.htmlspecialchars($nama).'</span></span>';
}

function is_online(?string $last_seen): bool {
    if (!$last_seen) return false;
    return (time() - strtotime($last_seen)) <= 120; // 2 menit
}

function touch_online(): void {
    $u = $_SESSION['user'] ?? null;
    if (!$u) return;
    static $done = false;
    if ($done) return;
    $done = true;
    try { db_exec("UPDATE users SET last_seen=now() WHERE id=$1", [(int)$u['id']]); } catch (Throwable $e) {}
}

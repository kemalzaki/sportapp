<?php
// FCM + in-app notifications helper (guarded against double-include)
require_once __DIR__ . '/auth.php';

if (!function_exists('notify')) {
function notify(int $userId, string $jenis, string $judul, string $isi = '', string $url = ''): void {
    try {
        db_exec("INSERT INTO notifications(user_id,jenis,judul,isi,url) VALUES($1,$2,$3,$4,$5)",
            [$userId, $jenis, $judul, $isi, $url]);
    } catch (Throwable $e) {}
    push_fcm_to_user($userId, $judul, $isi, $url);
}
}

if (!function_exists('notify_all')) {
function notify_all(string $jenis, string $judul, string $isi='', string $url=''): void {
    $rows = db_all("SELECT id FROM users WHERE role IN ('member','admin')");
    foreach ($rows as $r) notify((int)$r['id'], $jenis, $judul, $isi, $url);
}
}

if (!function_exists('push_fcm_to_user')) {
function push_fcm_to_user(int $userId, string $title, string $body, string $url=''): void {
    $key = getenv('FCM_SERVER_KEY');
    if (!$key) return;
    try {
        $tokens = array_column(db_all("SELECT token FROM fcm_tokens WHERE user_id=$1", [$userId]), 'token');
        if (!$tokens) return;
        $payload = json_encode([
            'registration_ids' => $tokens,
            'notification' => ['title'=>$title, 'body'=>$body, 'click_action'=>$url ?: '/'],
            'data' => ['url'=>$url],
        ]);
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: key='.$key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch); curl_close($ch);
    } catch (Throwable $e) {}
}
}

if (!function_exists('unread_notif_count')) {
function unread_notif_count(int $userId): int {
    try { return (int) db_val("SELECT COUNT(*) FROM notifications WHERE user_id=$1 AND dibaca=0", [$userId]); }
    catch (Throwable $e) { return 0; }
}
}

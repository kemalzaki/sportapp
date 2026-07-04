<?php
// FCM + in-app notifications helper (guarded against double-include)
// Revisi R8 Juli 2026 — dukungan notifikasi PER-KOMUNITAS.
//   - Kolom baru: notifications.komunitas_id (nullable, ter-index).
//   - notify() menerima $komunitasId (default = komunitas user penerima).
//   - notify_all_komunitas($kid, ...) mengirim hanya ke anggota komunitas $kid.
//   - notify_all() dipertahankan untuk broadcast global (khusus superadmin).
require_once __DIR__ . '/auth.php';

if (!function_exists('notifications_ensure_migration')) {
function notifications_ensure_migration(): void {
    static $done = false; if ($done) return; $done = true;
    try { db_exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS komunitas_id INTEGER NULL"); } catch (Throwable $e) {}
    try { db_exec("CREATE INDEX IF NOT EXISTS notif_kom_idx ON notifications(komunitas_id, user_id, dibaca)"); } catch (Throwable $e) {}
}
}

if (!function_exists('_notif_user_komunitas_id')) {
function _notif_user_komunitas_id(int $userId): ?int {
    try {
        $kid = (int) db_val("SELECT komunitas_id FROM users WHERE id=$1", [$userId]);
        return $kid > 0 ? $kid : null;
    } catch (Throwable $e) { return null; }
}
}

if (!function_exists('notify')) {
function notify(int $userId, string $jenis, string $judul, string $isi = '', string $url = '', ?int $komunitasId = null): void {
    notifications_ensure_migration();
    // Jika komunitas_id tidak eksplisit, isi otomatis dari komunitas si penerima.
    if ($komunitasId === null) $komunitasId = _notif_user_komunitas_id($userId);
    try {
        db_exec("INSERT INTO notifications(user_id,jenis,judul,isi,url,komunitas_id) VALUES($1,$2,$3,$4,$5,$6)",
            [$userId, $jenis, $judul, $isi, $url, $komunitasId]);
    } catch (Throwable $e) {
        // fallback lama (jika kolom belum ter-migrasi)
        try {
            db_exec("INSERT INTO notifications(user_id,jenis,judul,isi,url) VALUES($1,$2,$3,$4,$5)",
                [$userId, $jenis, $judul, $isi, $url]);
        } catch (Throwable $e2) {}
    }
    push_fcm_to_user($userId, $judul, $isi, $url);
}
}

if (!function_exists('notify_all_komunitas')) {
/** Kirim notifikasi ke semua member/admin dari SATU komunitas saja. */
function notify_all_komunitas(int $komunitasId, string $jenis, string $judul, string $isi='', string $url=''): void {
    if ($komunitasId <= 0) return;
    notifications_ensure_migration();
    // Ambil semua user di komunitas tsb — via pivot user_komunitas ATAU kolom users.komunitas_id.
    $users = [];
    try {
        $rows = db_all(
            "SELECT DISTINCT u.id FROM users u
             LEFT JOIN user_komunitas uk ON uk.user_id = u.id
             WHERE u.role IN ('member','admin')
               AND (u.komunitas_id = $1 OR uk.komunitas_id = $1)",
            [$komunitasId]
        );
        foreach ($rows as $r) $users[] = (int)$r['id'];
    } catch (Throwable $e) {
        try {
            $rows = db_all("SELECT id FROM users WHERE role IN ('member','admin') AND komunitas_id=$1", [$komunitasId]);
            foreach ($rows as $r) $users[] = (int)$r['id'];
        } catch (Throwable $e2) {}
    }
    foreach (array_unique($users) as $uid) notify($uid, $jenis, $judul, $isi, $url, $komunitasId);
}
}

if (!function_exists('notify_all')) {
/**
 * Broadcast ke semua user aktif.
 * Revisi R8 Juli 2026 — sekarang hanya untuk superadmin/global; bila
 * dipanggil dari konteks komunitas tertentu, gunakan notify_all_komunitas().
 */
function notify_all(string $jenis, string $judul, string $isi='', string $url=''): void {
    $rows = db_all("SELECT id, komunitas_id FROM users WHERE role IN ('member','admin')");
    foreach ($rows as $r) {
        $kid = (int)($r['komunitas_id'] ?? 0);
        notify((int)$r['id'], $jenis, $judul, $isi, $url, $kid > 0 ? $kid : null);
    }
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
/**
 * Revisi R8 Juli 2026 — hitung unread hanya untuk komunitas user saat ini,
 * plus notifikasi tanpa komunitas (broadcast global).
 */
function unread_notif_count(int $userId): int {
    try {
        $kid = _notif_user_komunitas_id($userId);
        if ($kid !== null) {
            return (int) db_val(
                "SELECT COUNT(*) FROM notifications
                 WHERE user_id=$1 AND dibaca=0
                   AND (komunitas_id IS NULL OR komunitas_id=$2)",
                [$userId, $kid]);
        }
        return (int) db_val(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id=$1 AND dibaca=0 AND komunitas_id IS NULL",
            [$userId]);
    } catch (Throwable $e) {
        // fallback (kolom belum ada)
        try { return (int) db_val("SELECT COUNT(*) FROM notifications WHERE user_id=$1 AND dibaca=0", [$userId]); }
        catch (Throwable $e2) { return 0; }
    }
}
}

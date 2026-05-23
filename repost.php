<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/security.php';
require __DIR__.'/includes/helpers.php';
send_security_headers(); require_login();
$u = current_user(); $uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    rate_limit_or_die('repost:'.$uid, 30, 60);
    $pid = (int)($_POST['post_id'] ?? 0);
    $caption = substr(trim($_POST['caption'] ?? ''), 0, 500);
    $src = $pid ? db_one("SELECT id,user_id,foto_url,caption FROM posts WHERE id=$1", [$pid]) : null;
    if ($src) {
        // hindari double repost
        $exists = db_one("SELECT id FROM posts WHERE user_id=$1 AND repost_of=$2", [$uid,$pid]);
        if (!$exists) {
            db_exec("INSERT INTO posts(user_id, caption, foto_url, jenis, repost_of) VALUES($1,$2,$3,'post',$4)",
                [$uid, $caption, $src['foto_url'], $pid]);
            @pg_query_params(db(), "INSERT INTO notifications(user_id,judul,body,url) VALUES($1,$2,$3,$4)",
                [(int)$src['user_id'], 'Post Anda di-repost', $u['nama'].' me-repost post Anda', '/index.php']);
            $_SESSION['flash'] = 'Berhasil repost.';
        } else {
            $_SESSION['flash'] = 'Anda sudah pernah merepost post ini.';
        }
    }
    header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php')); exit;
}
http_response_code(405); die('Method not allowed.');

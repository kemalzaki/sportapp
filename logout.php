<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
app_login_cookie_clear();
session_unset();
session_destroy();
header('Location: /login.php');
exit;

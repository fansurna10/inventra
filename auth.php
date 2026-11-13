<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
if (empty($_SESSION['user_id'])) {
    $base = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/pages/') !== false) ? '../login.php' : 'login.php';
    header('Location: ' . $base);
    exit;
}

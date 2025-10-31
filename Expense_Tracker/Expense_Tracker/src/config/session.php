<?php
// Central session bootstrap - đảm bảo cookie có path chung và session được start
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'] ?? 0,
    'path'     => '/', // <- quan trọng: cookie có hiệu lực cho toàn site
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: small helper to check login
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($_SESSION['user_id']);
    }
}

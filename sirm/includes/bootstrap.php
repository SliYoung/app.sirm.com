<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    exit(APP_DEBUG ? '<pre>' . e((string)$e) . '</pre>' : 'Terjadi kesalahan pada server.');
});

// Semua request POST wajib lolos CSRF (login.php memeriksanya sendiri)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['PHP_SELF']) !== 'login.php') csrf_verify();

<?php
require_once __DIR__ . '/functions.php';

// Hapus data sesi saat ini dulu (user atau admin tergantung path)
logoutUser();
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

// Hapus sesi lainnya juga supaya logout berlaku untuk kedua mode
foreach (['USERSESSID', 'ADMINSESSID'] as $sessionName) {
    if (session_name() === $sessionName) {
        continue;
    }
    session_write_close();
    session_name($sessionName);
    session_start();
    $_SESSION = [];
    session_destroy();
    setcookie($sessionName, '', time() - 3600, '/');
}

header('Location: index.php');
exit;

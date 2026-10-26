<?php
date_default_timezone_set('Asia/Bangkok');

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $hostname = parse_url('http://' . $host, PHP_URL_HOST);
        if ($host && !in_array($hostname, ['localhost', '127.0.0.1'], true)) {
            header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? ''), true, 301);
            exit;
        }
    }
}

$db_host = '192.168.100.109';
$db_user = 'root';
$db_pass = '';
$db_name = 'borrow_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (' . $db_host . '): ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");


<?php
// thay bằng DSN đúng database:
$host = '127.0.0.1';
$db   = 'expense_tracker';
$user = 'root';           // chỉnh theo user của bạn
$pass = '';               // chỉnh theo password của bạn
$opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

// Thay đổi theo cấu hình của bạn (XAMPP mặc định: user=root, password='')
$dsn = 'mysql:host=127.0.0.1;dbname=expense_tracker;charset=utf8mb4';
$username = 'root';
$password = ''; // nếu bạn đã đặt password cho root thì điền vào đây

try {
    $pdo = new PDO($dsn, $user, $pass, $opts);
} catch (PDOException $e) {
    // tạm thông báo lỗi khi debug
    die("DB connect error: " . $e->getMessage());
}
?>
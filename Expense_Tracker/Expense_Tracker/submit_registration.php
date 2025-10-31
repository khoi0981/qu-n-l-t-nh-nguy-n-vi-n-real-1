<?php
// Xử lý submit đăng ký tham gia (trả JSON)
session_start();
header('Content-Type: application/json; charset=utf-8');

// kiểm tra login
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'message'=>'Bạn cần đăng nhập để đăng ký.']);
    exit;
}

// đơn giản validate request
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$name = trim(@$_POST['name'] ?? '');
$age = isset($_POST['age']) ? (int)$_POST['age'] : null;
$address = trim(@$_POST['address'] ?? '');

if (!$event_id || $name === '' || !$age || $address === '') {
    echo json_encode(['success'=>false, 'message'=>'Thiếu thông tin bắt buộc.']);
    exit;
}

// upload config
$uploadDir = __DIR__ . '/uploads/registrations/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// kiểm tra file
if (empty($_FILES['id_image']) || !is_uploaded_file($_FILES['id_image']['tmp_name'])) {
    echo json_encode(['success'=>false, 'message'=>'Vui lòng tải lên ảnh căn cước.']);
    exit;
}

$file = $_FILES['id_image'];
// limit kích thước (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success'=>false, 'message'=>'Kích thước file quá lớn (max 5MB).']);
    exit;
}
// kiểm tra mime thực tế
$info = @getimagesize($file['tmp_name']);
if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
    echo json_encode(['success'=>false, 'message'=>'File không phải ảnh hợp lệ (jpg/png/gif).']);
    exit;
}
$ext = image_type_to_extension($info[2], false);
$basename = bin2hex(random_bytes(12));
$targetName = $basename . '.' . $ext;
$targetPath = $uploadDir . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success'=>false, 'message'=>'Không thể lưu file ảnh.']);
    exit;
}

// kết nối PDO (tương tự index.php)
$pdo = null;
try {
    if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
    elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';

    if (!isset($pdo) && isset($dsn, $username, $password)) {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
} catch (Throwable $e) {
    // lỗi kết nối
    @unlink($targetPath);
    echo json_encode(['success'=>false, 'message'=>'Lỗi kết nối cơ sở dữ liệu.']);
    exit;
}
if (!$pdo) {
    @unlink($targetPath);
    echo json_encode(['success'=>false, 'message'=>'Không tìm thấy cấu hình DB.']);
    exit;
}

// tạo bảng registrations nếu chưa có (kiểu đơn giản)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `registrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `event_id` INT DEFAULT NULL,
            `name` VARCHAR(191),
            `age` INT,
            `address` VARCHAR(255),
            `id_image` VARCHAR(255),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {
    // không fatal, tiếp tục cố gắng insert có thể fail sau
}

// tránh đăng ký trùng (nếu muốn)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `registrations` WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$_SESSION['user_id'], $event_id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success'=>false, 'message'=>'Bạn đã đăng ký sự kiện này trước đó.']);
        exit;
    }
} catch (Throwable $e) {
    // ignore
}

// lưu bản ghi đăng ký
try {
    $stmt = $pdo->prepare("INSERT INTO `registrations` (user_id, event_id, name, age, address, id_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $event_id, $name, $age, $address, 'uploads/registrations/' . $targetName]);
} catch (Throwable $e) {
    @unlink($targetPath);
    echo json_encode(['success'=>false, 'message'=>'Lỗi lưu đăng ký: ' . $e->getMessage()]);
    exit;
}

// sau khi insert thành công:
// lấy số lượng thực từ bảng registrations
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) AS cnt FROM `registrations` WHERE event_id = ?");
    $stmtCount->execute([$event_id]);
    $currentCount = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {
    $currentCount = null;
}

// (nếu muốn) lấy tổng từ bảng events nếu có
try {
    $total = null;
    $cols = $pdo->query("SHOW COLUMNS FROM `events`")->fetchAll(PDO::FETCH_COLUMN);
    $has = function($n) use ($cols){ return in_array($n,$cols,true); };
    $colTotal = $has('participants') ? 'participants' : ($has('attendees') ? 'attendees' : ($has('total') ? 'total' : null));
    if ($colTotal) {
        $stmtT = $pdo->prepare("SELECT `$colTotal` FROM `events` WHERE `id` = ? LIMIT 1");
        $stmtT->execute([$event_id]);
        $total = (int)$stmtT->fetchColumn();
    }
} catch (Throwable $e) { $total = null; }

echo json_encode([
    'success' => true,
    'message' => 'Đăng ký thành công.',
    'current' => $currentCount,
    'total' => $total
]);
exit;
?>
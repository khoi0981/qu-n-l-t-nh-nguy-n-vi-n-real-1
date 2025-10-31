<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Bạn cần đăng nhập.']);
    exit;
}

$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
if (!$event_id) {
    echo json_encode(['success'=>false,'message'=>'Thiếu event_id.']);
    exit;
}

// kết nối PDO
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
    echo json_encode(['success'=>false,'message'=>'Lỗi kết nối DB.']);
    exit;
}

try {
    // lấy đường dẫn ảnh (nếu muốn xóa file)
    try {
        $st = $pdo->prepare("SELECT `id_image` FROM `registrations` WHERE event_id = ? AND user_id = ? LIMIT 1");
        $st->execute([$event_id, $_SESSION['user_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $img = $row['id_image'] ?? null;
    } catch (Throwable $e) {
        $img = null;
    }

    // xóa bản ghi (của user cho event)
    $del = $pdo->prepare("DELETE FROM `registrations` WHERE event_id = ? AND user_id = ?");
    $del->execute([$event_id, $_SESSION['user_id']]);

    // xóa file ảnh nếu có và tồn tại trong ổ
    if (!empty($img)) {
        $path = __DIR__ . '/' . ltrim($img, '/');
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // trả về số hiện tại
    $c = $pdo->prepare("SELECT COUNT(*) FROM `registrations` WHERE event_id = ?");
    $c->execute([$event_id]);
    $current = (int)$c->fetchColumn();

    // lấy total nếu có
    $total = null;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `events`")->fetchAll(PDO::FETCH_COLUMN);
        $has = function($n) use ($cols){ return in_array($n,$cols,true); };
        $colTotal = $has('participants') ? 'participants' : ($has('attendees') ? 'attendees' : ($has('total') ? 'total' : null));
        if ($colTotal) {
            $st2 = $pdo->prepare("SELECT `$colTotal` FROM `events` WHERE id = ? LIMIT 1");
            $st2->execute([$event_id]);
            $total = (int)$st2->fetchColumn();
        }
    } catch (Throwable $e) { $total = null; }

    echo json_encode(['success'=>true,'message'=>'Bạn đã hủy đăng ký.','current'=>$current,'total'=>$total]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Lỗi khi hủy đăng ký.']);
    exit;
}
?>
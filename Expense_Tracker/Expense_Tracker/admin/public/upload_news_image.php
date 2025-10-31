<?php
session_start();
include '../config/db.php';
include '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'error'=>'No file or upload error.']);
    exit;
}

$f = $_FILES['image'];
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
$mime = mime_content_type($f['tmp_name']);
if (!isset($allowed[$mime])) {
    echo json_encode(['success'=>false,'error'=>'Loại file không hợp lệ.']);
    exit;
}

if ($f['size'] > 5*1024*1024) {
    echo json_encode(['success'=>false,'error'=>'File quá lớn (≤5MB).']);
    exit;
}

$ext = $allowed[$mime];
$uploadDir = realpath(__DIR__ . '/../../') . '/uploads/news/';
if (!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);

$filename = uniqid('n_') . '.' . $ext;
$dest = $uploadDir . $filename;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    echo json_encode(['success'=>false,'error'=>'Không thể lưu file.']);
    exit;
}

// trả về đường dẫn có thể dùng trực tiếp trong <img>
$webPath = '/Expense_tracker-main/Expense_Tracker/uploads/news/' . rawurlencode($filename);
echo json_encode(['success'=>true,'url'=>$webPath]);
exit;
<?php
// Bắt đầu phiên làm việc
session_start();

// Kết nối cơ sở dữ liệu
require_once __DIR__ . '/../src/config/db.php';

// Kiểm tra xem có tham số 'id' trong URL hay không
if (!empty($_GET['id'])) {
    $id = (int) $_GET['id'];
    
    // Cập nhật số lượt xem bài viết
    $stmt = $pdo->prepare("UPDATE news SET views = COALESCE(views,0) + 1 WHERE id = ?");
    $stmt->execute([$id]);
}

// Chuyển hướng đến trang bài viết chuẩn
header('Location: /Expense_tracker-main/Expense_Tracker/news_web/news.php?id=' . $id);
exit;

if (isset($_GET['id'])) {
    require_once __DIR__ . '/../src/config/db.php';
    
    // Update view count
    $stmt = $pdo->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    
    // Rest of your detail page code...
}
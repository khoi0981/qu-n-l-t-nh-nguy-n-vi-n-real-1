<?php
// Bắt đầu session trước khi kiểm tra auth / sử dụng $_SESSION
session_start();

// Đảm bảo PHP sử dụng múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// include config DB đúng đường dẫn và tên file
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/../includes/auth.php';

// nếu file config không khởi tạo $pdo trực tiếp, tạo PDO an toàn từ $dsn/$username/$password
if (!isset($pdo)) {
    if (!empty($dsn) && isset($username)) {
        try {
            $pdo = new PDO($dsn, $username, $password ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection error: ' . $e->getMessage());
            die('Không thể kết nối tới cơ sở dữ liệu. Kiểm tra config/db.php');
        }
    } else {
        die('Cấu hình DB thiếu ($dsn / $username / $password). Kiểm tra config/db.php');
    }
}


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Xử lý đăng ký/hủy đăng ký hoạt động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    if ($userId && $eventId) {
        if (isset($_POST['register_event'])) {
            // Đăng ký tham gia
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$eventId, $userId]);
                $isRegistered = $stmt->fetchColumn() > 0;
                if (!$isRegistered) {
                    $insertStmt = $pdo->prepare("INSERT INTO event_registrations (event_id, user_id) VALUES (?, ?)");
                    $insertStmt->execute([$eventId, $userId]);
                }
            } catch (Throwable $e) {
                $message = 'Lỗi đăng ký hoạt động: ' . $e->getMessage();
            }
        } elseif (isset($_POST['unregister_event'])) {
            // Hủy đăng ký
            try {
                $deleteStmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
                $deleteStmt->execute([$eventId, $userId]);
            } catch (Throwable $e) {
                $message = 'Lỗi hủy đăng ký: ' . $e->getMessage();
            }
        }
        // Sau khi xử lý, reload lại trang để cập nhật trạng thái nút
        header("Location: manage_events.php?edit=$eventId");
        exit();
    }
}

$events = [];
$message = '';

// upload limits (20MB) and server-side PHP ini check
$maxSize = 5 * 1024 * 1024; // 5MB
function shorthand_to_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    if ($last === 'g') return $num * 1024 * 1024 * 1024;
    if ($last === 'm') return $num * 1024 * 1024;
    if ($last === 'k') return $num * 1024;
    return (int)$val;
}
$uploadMax = shorthand_to_bytes(ini_get('upload_max_filesize') ?: '2M');
$postMax = shorthand_to_bytes(ini_get('post_max_size') ?: '8M');
if ($uploadMax < $maxSize || $postMax < $maxSize) {
    $message .= ' Lưu ý: cấu hình PHP (upload_max_filesize/post_max_size) hiện nhỏ hơn giới hạn upload mong muốn (5MB). Hãy cập nhật php.ini và khởi động lại webserver.';
}

try {
    // Lấy danh sách cột trong bảng events để map linh hoạt
    $colsStmt = $pdo->query("SHOW COLUMNS FROM events");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    $cols = [];
    $message = 'Không thể đọc cấu trúc bảng events: ' . $e->getMessage();
}

// xác định cột tên, mô tả, ngày, địa chỉ, số người tham gia, điểm (nếu có)
$nameCol = in_array('title', $cols) ? 'title' : (in_array('name', $cols) ? 'name' : null);
$descCol = in_array('event_description', $cols) ? 'event_description' : (in_array('description', $cols) ? 'description' : null);
$dateCol = null;
foreach (['event_date', 'date', 'start_date', 'created_at'] as $c) {
    if (in_array($c, $cols)) { $dateCol = $c; break; }
}
$addressCol = in_array('address', $cols) ? 'address' : (in_array('location', $cols) ? 'location' : null);
$participantsCol = in_array('participants', $cols) ? 'participants' : (in_array('attendees', $cols) ? 'attendees' : null);
$pointsCol = in_array('points', $cols) ? 'points' : (in_array('reward_points', $cols) ? 'reward_points' : null);
// Thêm phát hiện cột ảnh
$imageCol = in_array('image', $cols) ? 'image' : (in_array('photo', $cols) ? 'photo' : (in_array('cover', $cols) ? 'cover' : null));
// detect end datetime column if available
$endDateCol = null;
foreach (['end_date','end_time','event_end','end_datetime','finish_time'] as $c) {
    if (in_array($c, $cols)) { $endDateCol = $c; break; }
}

// Fetch events — nếu không có cột date thì sắp theo id
try {
    $orderCol = $dateCol ? "`$dateCol`" : 'id';
    $stmt = $pdo->query("SELECT * FROM events ORDER BY $orderCol DESC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $message = 'Lỗi khi lấy hoạt động: ' . $e->getMessage();
    $events = [];
}

// Thêm cột points nếu admin muốn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_points_column'])) {
    try {
        $pdo->exec("ALTER TABLE events ADD COLUMN points INT NOT NULL DEFAULT 0");
        header("Location: manage_events.php");
        exit();
    } catch (Throwable $e) {
        $message = 'Không thể thêm cột points: ' . $e->getMessage();
    }
}
// Thêm cột end_date nếu admin muốn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_end_date_column'])) {
    try {
        $pdo->exec("ALTER TABLE events ADD COLUMN end_date DATETIME NULL");
        header("Location: manage_events.php");
        exit();
    } catch (Throwable $e) {
        $message = 'Không thể thêm cột end_date: ' . $e->getMessage();
    }
}

// Handle event update (edit)
$editEvent = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
            $stmt->execute([$editId]);
            $editEvent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $message = 'Không thể tải hoạt động để chỉnh sửa: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    if ($event_id <= 0) {
        $message = 'ID hoạt động không hợp lệ.';
    } else {
        // Thu thập giá trị từ form
        $event_name = trim($_POST['event_name'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        // normalize HTML datetime-local (YYYY-MM-DDTHH:MM) to SQL DATETIME (YYYY-MM-DD HH:MM:SS)
        if ($event_date !== '' && strpos($event_date, 'T') !== false) {
            $event_date = str_replace('T', ' ', $event_date);
            if (strlen($event_date) === 16) $event_date .= ':00';
        }
        $event_description = trim($_POST['event_description'] ?? '');
        $event_address = trim($_POST['event_address'] ?? '');
        $event_participants = (int)($_POST['event_participants'] ?? 0);
        $event_points = (int)($_POST['event_points'] ?? 0);
    $event_end = trim($_POST['event_end'] ?? '');
        if ($event_end !== '' && strpos($event_end, 'T') !== false) {
            $event_end = str_replace('T', ' ', $event_end);
            if (strlen($event_end) === 16) $event_end .= ':00';
        }

        // xử lý upload ảnh nếu có
        $uploadedImagePath = null;
        if ($imageCol && !empty($_FILES['event_image']) && is_uploaded_file($_FILES['event_image']['tmp_name'])) {
            $file = $_FILES['event_image'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                if (isset($maxSize) && $file['size'] > $maxSize) {
                    $message = 'File quá lớn (≤5MB).';
                } else {
                    $info = @getimagesize($file['tmp_name']);
                    if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                        $ext = image_type_to_extension($info[2], false);
                        $basename = bin2hex(random_bytes(10));
                        $uploadDir = __DIR__ . '/../../uploads/events/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $fileName = $basename . '.' . $ext;
                        $target = $uploadDir . $fileName;
                        if (@move_uploaded_file($file['tmp_name'], $target)) {
                            $uploadedImagePath = '/Expense_tracker-main/Expense_Tracker/uploads/events/' . $fileName;
                        } else {
                            $message = 'Không thể lưu file ảnh. Kiểm tra quyền thư mục uploads/events.';
                        }
                    } else {
                        $message = 'File ảnh không hợp lệ (chỉ chấp nhận jpg/png/gif).';
                    }
                }
            } else {
                $message = 'Lỗi upload ảnh (code: ' . intval($file['error']) . ').';
            }
        }

        // Xây dựng câu UPDATE dựa trên cột tồn tại
        $sets = [];
        $values = [];
        if ($nameCol) { $sets[] = "$nameCol = ?"; $values[] = $event_name; }
        if ($dateCol && $event_date !== '') { $sets[] = "$dateCol = ?"; $values[] = $event_date; }
        // handle end datetime: if column exists use it; otherwise attempt to create 'end_date' column and use it
        if (!empty($event_end)) {
            if ($endDateCol) {
                $sets[] = "$endDateCol = ?"; $values[] = $event_end;
            } else {
                // try to add column end_date
                try {
                    $pdo->exec("ALTER TABLE events ADD COLUMN end_date DATETIME NULL");
                    $endDateCol = 'end_date';
                    $sets[] = "end_date = ?"; $values[] = $event_end;
                } catch (Throwable $e) {
                    // ignore alter error, don't set end date
                    error_log('Could not add end_date column: ' . $e->getMessage());
                }
            }
        }
        if ($descCol) { $sets[] = "$descCol = ?"; $values[] = $event_description; }
        if ($addressCol) { $sets[] = "$addressCol = ?"; $values[] = $event_address; }
        if ($participantsCol) { $sets[] = "$participantsCol = ?"; $values[] = $event_participants; }
        if ($pointsCol) { $sets[] = "$pointsCol = ?"; $values[] = $event_points; }
        if ($imageCol && $uploadedImagePath !== null) { $sets[] = "$imageCol = ?"; $values[] = $uploadedImagePath; }

        if (empty($sets)) {
            $message = 'Không có cột hợp lệ để cập nhật.';
        } else {
            $sql = 'UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = ?';
            $values[] = $event_id;
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                header('Location: manage_events.php');
                exit();
            } catch (Throwable $e) {
                $message = 'Lỗi khi cập nhật hoạt động: ' . $e->getMessage();
            }
        }
    }
}

// Handle event creation (map linh hoạt cột)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $event_name = trim($_POST['event_name'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    if ($event_date !== '' && strpos($event_date, 'T') !== false) {
        $event_date = str_replace('T', ' ', $event_date);
        if (strlen($event_date) === 16) $event_date .= ':00';
    }
    $event_description = trim($_POST['event_description'] ?? '');
    $event_address = trim($_POST['event_address'] ?? '');
    $event_participants = (int)($_POST['event_participants'] ?? 0);
    $event_points = (int)($_POST['event_points'] ?? 0);
    $event_end = trim($_POST['event_end'] ?? '');
    if ($event_end !== '' && strpos($event_end, 'T') !== false) {
        $event_end = str_replace('T', ' ', $event_end);
        if (strlen($event_end) === 16) $event_end .= ':00';
    }

    // upload ảnh nếu có cột image và file được gửi
    $uploadedImagePath = null;
    if ($imageCol && !empty($_FILES['event_image']) && is_uploaded_file($_FILES['event_image']['tmp_name'])) {
        $file = $_FILES['event_image'];
        // kiểm tra cơ bản
        if ($file['error'] === UPLOAD_ERR_OK) {
            // kích thước
            if (isset($maxSize) && $file['size'] > $maxSize) {
                $message = 'File quá lớn (≤5MB).';
            } else {
                $info = @getimagesize($file['tmp_name']);
                if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                    $ext = image_type_to_extension($info[2], false);
                    $basename = bin2hex(random_bytes(10));
                    $uploadDir = __DIR__ . '/../../uploads/events/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fileName = $basename . '.' . $ext;
                    $target = $uploadDir . $fileName;
                    if (@move_uploaded_file($file['tmp_name'], $target)) {
                        // lưu đường dẫn public (bắt đầu bằng /) để <img src> luôn đúng từ bất kỳ trang nào
                        // điều chỉnh prefix theo đường dẫn app của bạn trên webserver
                        $uploadedImagePath = '/Expense_tracker-main/Expense_Tracker/uploads/events/' . $fileName;
                    } else {
                        $message = 'Không thể lưu file ảnh. Kiểm tra quyền thư mục uploads/events.';
                    }
                } else {
                    $message = 'File ảnh không hợp lệ (chỉ chấp nhận jpg/png/gif).';
                }
            }
        } else {
            $message = 'Lỗi upload ảnh (code: ' . intval($file['error']) . ').';
        }
    }

    // xây dựng SQL chèn dựa trên cột tồn tại
    $insertCols = [];
    $placeholders = [];
    $values = [];

    if ($nameCol) {
        $insertCols[] = $nameCol;
        $placeholders[] = '?';
        $values[] = $event_name;
    }
    if ($dateCol && $event_date !== '') {
        $insertCols[] = $dateCol;
        $placeholders[] = '?';
        $values[] = $event_date;
    }
    if ($descCol) {
        $insertCols[] = $descCol;
        $placeholders[] = '?';
        $values[] = $event_description;
    }
    if ($addressCol) {
        $insertCols[] = $addressCol;
        $placeholders[] = '?';
        $values[] = $event_address;
    }
    if ($participantsCol) {
        $insertCols[] = $participantsCol;
        $placeholders[] = '?';
        $values[] = $event_participants;
    }
    if ($pointsCol) {
        $insertCols[] = $pointsCol;
        $placeholders[] = '?';
        $values[] = $event_points;
    }
    // nếu có ảnh đã upload và có cột image thì thêm vào insert
    if ($imageCol && $uploadedImagePath !== null) {
        $insertCols[] = $imageCol;
        $placeholders[] = '?';
        $values[] = $uploadedImagePath;
    }

    // if admin supplied an end datetime, ensure column exists (create if missing) and include in insert
    if (!empty($event_end)) {
        if (!$endDateCol) {
            try {
                $pdo->exec("ALTER TABLE events ADD COLUMN end_date DATETIME NULL");
                $endDateCol = 'end_date';
            } catch (Throwable $e) {
                error_log('Could not add end_date column: ' . $e->getMessage());
                // continue without end
            }
        }
        if ($endDateCol) {
            $insertCols[] = $endDateCol;
            $placeholders[] = '?';
            $values[] = $event_end;
        }
    }

    if (empty($insertCols)) {
        $message = 'Bảng events không có cột phù hợp để chèn dữ liệu.';
    } else {
        $sql = 'INSERT INTO events (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            header("Location: manage_events.php");
            exit();
        } catch (Throwable $e) {
            $message = 'Lỗi khi tạo hoạt động: ' . $e->getMessage();
        }
    }
}

// Handle event deletion
if (isset($_GET['delete'])) {
    $event_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        header("Location: manage_events.php");
        exit();
    } catch (PDOException $e) {
        $message = 'Lỗi khi xóa hoạt động: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Hoạt động</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
/* Button styles */
.btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:8px 16px;
    border-radius:6px;
    border:1px solid #2e8b57;
    cursor:pointer;
    font-size:14px;
    font-weight:500;
    transition:all 0.15s ease;
    background:#fff;
    color:#2e8b57;
    text-decoration:none
}
.btn:hover{
    background:#f0f9f4
}
.btn-primary{
    border-color:#2e8b57;
    background:#2e8b57;
    color:#fff;
    position:relative;
    overflow:hidden
}
.btn-primary:hover{
    background:#3aa76c;
    border-color:#3aa76c
}
.btn-primary:active{
    transform:translateY(1px)
}
.btn-primary::after{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(255,255,255,0.1);
    opacity:0;
    transition:opacity 0.2s
}
.btn-primary:hover::after{
    opacity:1
}
.btn-icon svg{width:18px;height:18px;stroke:currentColor}

/* Action buttons (edit/delete) */
.action-btn{
    padding:4px 8px;
    border-radius:4px;
    border:1px solid #e0e0e0;
    cursor:pointer;
    background:#fff;
    color:#333;
    font-size:13px;
    transition:all 0.15s;
    text-decoration:none
}
.action-btn.edit{color:#2e8b57;border-color:#2e8b57}
.action-btn.edit:hover{background:#2e8b57;color:#fff}
.action-btn.delete{color:#dc3545;border-color:#dc3545}
.action-btn.delete:hover{background:#dc3545;color:#fff}

.container{max-width:1100px;margin:28px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 8px 24px rgba(10,10,10,0.04)}
h1{margin:0 0 12px 0}
.note{color:#666;margin-bottom:12px}
.alert{background:#fff3cd;padding:10px;border-radius:6px;margin-bottom:12px}
.form-toggle{display:flex;gap:8px;align-items:center;margin-bottom:12px}
form.event-form{background:#fafafa;padding:14px;border-radius:8px;border:1px solid #eee;margin-bottom:18px}

/* Form group spacing */
.form-group { margin-bottom: 16px }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500 }
/* ensure form shows when inside modal */
.modal.open form.event-form{display:block}
        .form-row{display:flex;gap:12px;flex-wrap:wrap}
        .form-row .col{flex:1;min-width:160px}
        input[type="text"], input[type="date"], input[type="datetime-local"], input[type="number"], textarea {
            width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;font-size:14px
        }
        textarea{min-height:100px;resize:vertical}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        th,td{padding:10px;border:1px solid #eee;text-align:left;vertical-align:top}
        th{background:#f7faf7}
        .actions a{margin-right:8px;color:#d9534f;text-decoration:none}
        .meta{font-size:13px;color:#666}
        .small{font-size:13px;color:#666}
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <h1>Quản lý Hoạt động</h1>
        <?php if ($message): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom:12px;font-size:13px;color:#666;display:flex;gap:12px;align-items:center">
            <span>
                <i class="fas fa-clock"></i>
                Thời gian máy chủ: <?php echo date('H:i:s d/m/Y'); ?>
            </span>
            <span>
                <i class="fas fa-globe"></i>
                Timezone: <?php echo date_default_timezone_get(); ?>
            </span>
        </div>

        <?php if (!$pointsCol): ?>
            <div class="alert" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                Bảng events chưa có cột "points" để lưu điểm hoàn thành. Nếu muốn lưu điểm cho hoạt động, nhấn nút dưới để thêm cột (INT, mặc định 0).
                <form method="POST" style="display:inline;margin-left:8px">
                    <button type="submit" name="add_points_column" class="btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Thêm cột điểm
                    </button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (!$endDateCol): ?>
            <div class="alert" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                Bảng events chưa có cột "end_date" (thời gian kết thúc). Nếu muốn lưu thời gian kết thúc, nhấn nút dưới để thêm cột (DATETIME NULL).
                <form method="POST" style="display:inline;margin-left:8px">
                    <button type="submit" name="add_end_date_column" class="btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Thêm cột end_date
                    </button>
                </form>
            </div>
        <?php endif; ?>
        <div class="toolbar" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
            <div class="left">
                                <button id="openEventModalBtn" class="btn btn-primary btn-icon create-news-btn" type="button" title="Tạo hoạt động mới">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Tạo hoạt động mới</span>
                </button>
                <div class="note">Nhấn để mở form nhập hoạt động mới</div>
            </div>
            <div class="right">
                <span class="small" style="color:#666">Số hoạt động: <?php echo count($events); ?></span>
            </div>
        </div>

        <!-- Modal: Add Event -->
        <div id="eventModal" class="modal" aria-hidden="true">
            <div class="modal-backdrop" tabindex="-1"></div>
            <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
                <div class="modal-header">
                    <h3 id="eventModalTitle"><?php echo $editEvent ? 'Chỉnh sửa hoạt động' : 'Thêm hoạt động mới'; ?></h3>
                    <button type="button" class="modal-close" aria-label="Đóng">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" class="event-form" id="eventForm" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="col">
                    <label>Tên hoạt động</label>
                    <input type="text" name="event_name" placeholder="Tên hoạt động" required
                        value="<?php echo htmlspecialchars($nameCol ? ($editEvent[$nameCol] ?? '') : ($editEvent['event_name'] ?? '')); ?>">
                            </div>
                            <div class="col" style="max-width:220px">
                                <label>Thời gian bắt đầu</label>
                                    <?php if ($dateCol): ?>
                                    <input type="datetime-local" name="event_date" placeholder="YYYY-MM-DD HH:MM"
                                           value="<?php echo htmlspecialchars($dateCol ? ($editEvent[$dateCol] ?? '') : ($editEvent['event_date'] ?? '')); ?>">
                                <?php else: ?>
                                    <input type="text" disabled placeholder="(Không có cột thời gian trong DB)">
                                <?php endif; ?>
                            </div>
                            <div class="col" style="max-width:220px">
                                <label>Thời gian kết thúc</label>
                                <?php if ($endDateCol): ?>
                                    <input type="datetime-local" name="event_end" placeholder="YYYY-MM-DD HH:MM"
                                           value="<?php echo htmlspecialchars($endDateCol ? ($editEvent[$endDateCol] ?? '') : ''); ?>">
                                <?php else: ?>
                                    <input type="text" name="event_end" placeholder="(Nếu muốn lưu, cột end_date sẽ được tạo tự động)" value="">
                                <?php endif; ?>
                            </div>
                            <div class="col" style="max-width:180px">
                                <label>Số người (dự kiến)</label>
                                <?php if ($participantsCol): ?>
                                    <input type="number" name="event_participants" value="<?php echo htmlspecialchars($participantsCol ? ($editEvent[$participantsCol] ?? 0) : ($editEvent['participants'] ?? 0)); ?>" min="0">
                                <?php else: ?>
                                    <input type="text" disabled placeholder="(Không có cột participants)">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top:10px">
                            <label>Địa chỉ</label>
                            <?php if ($addressCol): ?>
                                <input type="text" name="event_address" placeholder="Địa chỉ / Địa điểm"
                                       value="<?php echo htmlspecialchars($addressCol ? ($editEvent[$addressCol] ?? '') : ($editEvent['address'] ?? '')); ?>">
                            <?php else: ?>
                                <input type="text" disabled placeholder="(Không có cột address trong DB)">
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:10px">
                            <label>Chi tiết hoạt động</label>
                            <?php if ($descCol): ?>
                                <textarea name="event_description" placeholder="Mô tả chi tiết (nội dung, lịch trình, lưu ý)"><?php echo htmlspecialchars($descCol ? ($editEvent[$descCol] ?? '') : ($editEvent['event_description'] ?? '')); ?></textarea>
                            <?php else: ?>
                                <textarea disabled placeholder="(Không có cột mô tả trong DB)"></textarea>
                            <?php endif; ?>
                        </div>

                        <?php if ($imageCol): ?>
                        <div style="margin-top:10px">
                            <label>Ảnh đại diện hoạt động</label>
                            <label class="file-btn">Chọn ảnh cho hoạt động
                                <input type="file" name="event_image" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="event-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                        </div>
                        <?php else: ?>
                        <div style="margin-top:10px;color:#666;font-size:13px">
                            Bảng events chưa có cột ảnh. Nếu muốn lưu ảnh vào DB, thêm cột (image VARCHAR) trong bảng events.
                        </div>
                        <?php endif; ?>

                        <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
                                    <?php if ($pointsCol): ?>
                                <div style="max-width:160px">
                                    <label>Điểm hoàn thành</label>
                                    <input type="number" name="event_points" value="<?php echo htmlspecialchars($pointsCol ? ($editEvent[$pointsCol] ?? 0) : 0); ?>" min="0">
                                </div>
                            <?php endif; ?>

                            <div style="margin-left:auto;display:flex;gap:8px">
                                <?php if ($editEvent): ?>
                                    <input type="hidden" name="event_id" value="<?php echo (int)$editEvent['id']; ?>">
                                    <button type="submit" name="update_event" class="btn btn-primary btn-icon" title="Lưu thay đổi">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>Lưu thay đổi</span>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="create_event" class="btn btn-primary btn-icon" title="Lưu hoạt động">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>Lưu hoạt động</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Nút đăng ký/hủy đăng ký cho user -->
                        <?php
                        // Chỉ hiển thị nếu là popup chi tiết event (không phải admin tạo/sửa)
                        if (isset($editEvent) && $editEvent) {
                            $eventId = isset($editEvent['id']) ? (int)$editEvent['id'] : null;
                            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                            $isRegistered = false;
                            if ($eventId && $userId) {
                                // Kiểm tra trạng thái đăng ký
                                $regStmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND user_id = ?");
                                $regStmt->execute([$eventId, $userId]);
                                $isRegistered = $regStmt->fetchColumn() > 0;
                            }
                            if ($eventId && $userId) {
                                if ($isRegistered) {
                                    echo '<form method="POST" style="display:inline"><input type="hidden" name="event_id" value="' . $eventId . '"><button type="submit" name="unregister_event" class="btn btn-danger">Hủy đăng ký</button></form>';
                                } else {
                                    echo '<form method="POST" style="display:inline"><input type="hidden" name="event_id" value="' . $eventId . '"><button type="submit" name="register_event" class="btn btn-primary">Đăng ký tham gia</button></form>';
                                }
                            }
                        }
                        ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
        // tính số cột để dùng cho colspan khi không có bản ghi
        $baseCols = 6; // ID, Tên, Thời gian, Địa chỉ, Chi tiết, Số người
        $colspan = $baseCols + ($pointsCol ? 1 : 0) + ($imageCol ? 1 : 0) + 1; // +1 cho cột Hành động
        ?>

        <div class="container" style="max-width:1100px;margin:28px auto 0 auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 8px 24px rgba(10,10,10,0.04)">
            <h2>Danh sách hoạt động</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Tên</th>
                    <th>Thời gian</th>
                    <th>Địa chỉ</th>
                    <th>Chi tiết</th>
                    <th>Số người</th>
                    <th>Tình trạng</th>
                    <?php if ($pointsCol): ?><th>Điểm hoàn thành</th><?php endif; ?>
                    <?php if ($imageCol): ?><th>Ảnh</th><?php endif; ?>
                    <th>Hành động</th>
                </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr><td colspan="<?php echo $colspan + 1; ?>" class="small">Không có hoạt động.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            // Tính trạng hoạt động (theo phút)
                            $eventDateRaw = $dateCol ? ($event[$dateCol] ?? '') : ($event['event_date'] ?? '');
                            $statusText = '';
                            $statusKey = '';
                            $statusColor = '';
                            $minutesToStart = null;
                            
                            if ($eventDateRaw) {
                                try {
                                    // Dùng DateTime để parse chính xác hơn
                                    $startDate = new DateTime($eventDateRaw);
                                    $startTs = $startDate->getTimestamp();
                                    
                                    // Parse end date nếu có
                                    $endTs = null;
                                    if ($endDateCol && !empty($event[$endDateCol])) {
                                        $endDate = new DateTime($event[$endDateCol]);
                                        $endTs = $endDate->getTimestamp();
                                    }
                                    
                                    // Fallback: nếu không có end date, mặc định 2 tiếng
                                    if ($endTs === null) {
                                        $endTs = $startTs + (120 * 60);
                                    }
                                    
                                    // Lấy thời gian hiện tại một lần
                                    $nowTs = time();
                                } catch (Exception $e) {
                                    error_log("Lỗi parse datetime: " . $e->getMessage());
                                    $startTs = false;
                                    $endTs = null;
                                }

                                    if ($nowTs < $startTs) {
                                        $minutesToStart = (int) ceil(($startTs - $nowTs) / 60);
                                        $statusKey = 'upcoming';
                                        $statusText = 'Sắp diễn ra';
                                        $statusColor = '#2ecc40';
                                    } elseif ($nowTs >= $startTs && $nowTs < $endTs) {
                                        $statusKey = 'ongoing';
                                        $statusText = 'Đang diễn ra';
                                        $statusColor = '#f7b731';
                                    } else {
                                        $statusKey = 'past';
                                        $statusText = 'Đã diễn ra';
                                        $statusColor = '#888';
                                    }
                                }
                            
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['id']); ?></td>
                                <td><?php echo htmlspecialchars($nameCol ? ($event[$nameCol] ?? '') : ($event['event_name'] ?? '')); ?></td>
                                <td class="meta">
                                    <?php
                                        $dt = $dateCol ? ($event[$dateCol] ?? '') : ($event['event_date'] ?? '');
                                        echo htmlspecialchars($dt);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($addressCol ? ($event[$addressCol] ?? '') : ($event['address'] ?? '')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($descCol ? ($event[$descCol] ?? '') : ($event['event_description'] ?? ''))); ?></td>
                                <td><?php echo (int)($participantsCol ? ($event[$participantsCol] ?? 0) : ($event['participants'] ?? 0)); ?></td>
                                <td>
                                    <?php if ($statusText): ?>
                                        <?php
                                        // Chọn icon và gradient cho từng trạng thái — dùng $statusKey để so sánh
                                        if ($statusKey === 'upcoming') {
                                            $icon = '<i class="fas fa-hourglass-start"></i>';
                                            $bg = 'linear-gradient(90deg,#2ecc40 60%,#a8e063 100%)';
                                        } elseif ($statusKey === 'ongoing') {
                                            $icon = '<i class="fas fa-bolt"></i>';
                                            $bg = 'linear-gradient(90deg,#f7b731 60%,#ffe082 100%)';
                                        } else {
                                            $icon = '<i class="fas fa-check-circle"></i>';
                                            $bg = 'linear-gradient(90deg,#888 60%,#bdbdbd 100%)';
                                        }
                                        ?>
                                        <span style="display:inline-flex;align-items:center;gap:8px;padding:7px 18px;border-radius:16px;font-weight:700;font-size:15px;color:#fff;background:<?php echo $bg; ?>;min-width:120px;box-shadow:0 2px 8px rgba(0,0,0,0.10);text-align:center;letter-spacing:0.5px">
                                            <?php echo $icon; ?>
                                            <?php echo $statusText; ?>
                                        </span>
                                        <?php if (isset($startTs)): ?>
                                            <div style="margin-top:6px;font-size:12px;color:#666;border-left:3px solid #eee;padding-left:8px;margin-left:4px">
                                                <div>
                                                    <?php 
                                                        $minutesDiff = round(($startTs - $nowTs) / 60);
                                                        $hoursDiff = floor(abs($minutesDiff) / 60);
                                                        $minutesRem = abs($minutesDiff) % 60;
                                                        
                                                        if ($minutesDiff > 0) {
                                                            echo "<i class='fas fa-clock'></i> Bắt đầu sau: ";
                                                            if ($hoursDiff > 0) echo "$hoursDiff giờ ";
                                                            if ($minutesRem > 0) echo "$minutesRem phút";
                                                        } else {
                                                            echo "<i class='fas fa-history'></i> Đã bắt đầu ";
                                                            if ($hoursDiff > 0) echo "$hoursDiff giờ ";
                                                            if ($minutesRem > 0) echo "$minutesRem phút";
                                                            echo " trước";
                                                        }
                                                    ?>
                                                </div>
                                                <div style="margin-top:2px">
                                                    <?php if ($endTs !== null): ?>
                                                        <?php
                                                            $minutesToEnd = round(($endTs - $nowTs) / 60);
                                                            $hoursToEnd = floor(abs($minutesToEnd) / 60);
                                                            $minutesToEndRem = abs($minutesToEnd) % 60;
                                                            
                                                            if ($minutesToEnd > 0) {
                                                                echo "<i class='fas fa-hourglass-half'></i> Kết thúc sau: ";
                                                                if ($hoursToEnd > 0) echo "$hoursToEnd giờ ";
                                                                if ($minutesToEndRem > 0) echo "$minutesToEndRem phút";
                                                            } else {
                                                                echo "<i class='fas fa-check-circle'></i> Đã kết thúc ";
                                                                if ($hoursToEnd > 0) echo "$hoursToEnd giờ ";
                                                                if ($minutesToEndRem > 0) echo "$minutesToEndRem phút";
                                                                echo " trước";
                                                            }
                                                        ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="small">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($pointsCol): ?>
                                    <td><?php echo (int)($event[$pointsCol] ?? 0); ?></td>
                                <?php endif; ?>
                                <?php if ($imageCol): ?>
                                    <td>
                                        <?php
                                            $imgVal = trim($event[$imageCol] ?? '');
                                            if ($imgVal !== '' && $imgVal[0] !== '/') {
                                                $imgUrl = '/Expense_tracker-main/Expense_Tracker/' . ltrim($imgVal, './');
                                            } else {
                                                $imgUrl = $imgVal;
                                            }
                                            $projectRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
                                            $webPrefix = '/Expense_tracker-main/Expense_Tracker/';
                                            if ($imgUrl !== '' && strpos($imgUrl, $webPrefix) === 0) {
                                                $relPath = ltrim(substr($imgUrl, strlen($webPrefix)), '/');
                                            } else {
                                                $relPath = ltrim($imgUrl, '/');
                                            }
                                            $fsPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relPath);
                                        ?>
                                        <?php if ($imgUrl !== '' && file_exists($fsPath)): ?>
                                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Ảnh" style="max-width:100px;height:auto;border-radius:6px;display:block;">
                                        <?php elseif ($imgUrl !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Ảnh" style="max-width:100px;height:auto;border-radius:6px;display:block;opacity:0.6">
                                            <div class="small" style="color:#c33">File không tồn tại trên server (<?php echo htmlspecialchars($fsPath); ?>)</div>
                                        <?php else: ?>
                                            <span class="small">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="actions" style="white-space: nowrap">
                                    <a href="?edit=<?php echo (int)$event['id']; ?>" class="action-btn edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Chỉnh sửa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>

<script>
(function(){
    var openBtn = document.getElementById('openEventModalBtn');
    var modal = document.getElementById('eventModal');
    if (!openBtn || !modal) return;

    function openModal(){
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // focus first input
        var first = modal.querySelector('input, textarea, select');
        if (first) first.focus();
    }
    function closeModal(){
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        openBtn.focus();
    }

    openBtn.addEventListener('click', openModal);
    // close buttons (there are two places with class .modal-close)
    Array.prototype.forEach.call(modal.querySelectorAll('.modal-close'), function(b){ b.addEventListener('click', closeModal); });
    // backdrop click
    var backdrop = modal.querySelector('.modal-backdrop');
    if (backdrop) backdrop.addEventListener('click', closeModal);
    // Esc to close
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

    // Handle filename display
    const eventImageInput = document.querySelector('input[name="event_image"]');
    const filenameDiv = document.getElementById('event-image-filename');
    if (eventImageInput && filenameDiv) {
        eventImageInput.addEventListener('change', function() {
            if (this.files && this.files.length) {
                filenameDiv.textContent = this.files[0].name;
            } else {
                filenameDiv.textContent = '';
            }
        });
    }
})();
</script>
<?php if ($editEvent): ?>
<script>
// If server provided $editEvent, open the modal on DOM ready so admin can edit
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('eventModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    var first = modal.querySelector('input, textarea, select');
    if (first) first.focus();
});
</script>
<?php endif; ?>
</body>
</html>
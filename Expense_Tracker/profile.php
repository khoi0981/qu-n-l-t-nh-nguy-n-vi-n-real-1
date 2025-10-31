<?php
// safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}

require_once __DIR__ . '/src/config/db.php';

// Xác định tab hiện tại: ưu tiên giá trị từ GET (link ?tab=...), nếu không có dùng POST (form), mặc định 'edit'
$currentTab = $_GET['tab'] ?? $_POST['tab'] ?? 'edit';

// helper: chuẩn hoá đường dẫn ảnh dùng chung cho profile (trả về URL bắt đầu bằng '/' hoặc full http)
function resolve_url_path($path){
    $placeholder = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
    if (!$path) return $placeholder;
    $p = trim($path);
    if (preg_match('#^https?://#i',$p)) return $p;
    if (strpos($p,'/') === 0) return $p;

    // if contains uploads/ or reward_exchange, normalize under webroot
    if (stripos($p, 'uploads/') !== false || stripos($p, 'reward_exchange') !== false) {
        return '/Expense_tracker-main/Expense_Tracker/' . ltrim($p, './');
    }

    // try common server locations (uploads/users, uploads/rewards, reward_exchange assets)
    $candidates = [
        __DIR__ . '/uploads/users/' . $p => '/Expense_tracker-main/Expense_Tracker/uploads/users/' . rawurlencode($p),
        __DIR__ . '/uploads/rewards/' . $p => '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($p),
        __DIR__ . '/reward_exchange/asset/image/product-img/' . $p => '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/' . rawurlencode($p),
    ];
    foreach ($candidates as $file => $url) {
        if (file_exists($file)) return $url;
    }

    return '/Expense_tracker-main/Expense_Tracker/' . ltrim($p, './');
}

// When true, show exception details in the UI to help debugging. Set false in production.
$DEBUG_SHOW_ERRORS = true;

// require login
if (empty($_SESSION['user_id'])) {
    header('Location: /Expense_tracker-main/Expense_Tracker/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$user = [
    'id' => $userId,
    'name' => 'Người dùng',
    'email' => '',
    'avatar' => '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-1.jpg',
    'points' => 0
];

// fetch user from DB if possible
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $user['name'] = $row['name'] ?? $row['username'] ?? $user['name'];
            $user['email'] = $row['email'] ?? '';
            // try common points column names
            foreach (['points','reward_points','reward_point','score','balance','credits','coins','point'] as $c) {
                if (isset($row[$c])) { $user['points'] = (int)$row[$c]; break; }
            }
            if (!empty($row['avatar'])) $user['avatar'] = $row['avatar'];
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

// Define a default avatar URL
$defaultAvatar = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/default-avatar.jpg';

// When creating a new user, check if avatar is provided
if (empty($row['avatar'])) {
    $user['avatar'] = $defaultAvatar; // Set default avatar if none provided
}

// handle profile update
$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim((string)($_POST['name'] ?? ''));
    $newAvatar = trim((string)($_POST['avatar'] ?? ''));

    // detect avatar column if exists
    $avatarColumn = null;
    $nameColumn = null;
    if (isset($pdo)) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
            foreach (['avatar','user_avatar','photo','profile_pic'] as $c) {
                if (in_array($c, $cols, true)) { $avatarColumn = $c; break; }
            }
            // detect likely name/display column so UPDATE uses correct field
            foreach (['name','full_name','fullname','display_name','username','user_name'] as $nc) {
                if (in_array($nc, $cols, true)) { $nameColumn = $nc; break; }
            }
        } catch (Throwable $e) {
            // surface error message when debugging
            error_log($e->getMessage());
            if (!empty($DEBUG_SHOW_ERRORS)) {
                $updateMessage = 'Lỗi kiểm tra cột avatar: ' . $e->getMessage();
            }
        }
    }

    // If a file was uploaded, handle it and override $newAvatar with public path
    if (!empty($_FILES['avatar_file']) && is_uploaded_file($_FILES['avatar_file']['tmp_name'])) {
        $file = $_FILES['avatar_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $info = @getimagesize($file['tmp_name']);
            if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                $ext = image_type_to_extension($info[2], false);
                $basename = bin2hex(random_bytes(8));
                $uploadDir = __DIR__ . '/uploads/users/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = $basename . '.' . $ext;
                $target = $uploadDir . $fileName;
                if (@move_uploaded_file($file['tmp_name'], $target)) {
                    // public path used in the app
                    $newAvatar = '/Expense_tracker-main/Expense_Tracker/uploads/users/' . $fileName;
                } else {
                    $updateMessage = 'Không thể lưu file ảnh. Kiểm tra quyền thư mục uploads/users/';
                }
            } else {
                $updateMessage = 'Tập tin không phải ảnh hợp lệ (jpg/png/gif).';
            }
        } else {
            $updateMessage = 'Lỗi khi upload ảnh (code ' . intval($file['error']) . ').';
        }
    }

    if ($updateMessage === '') {
        if ($newName === '') $updateMessage = 'Tên không được để trống.';
        else {
            if (isset($pdo)) {
                try {
                    $fields = [];
                    $params = [];
                    // prefer to update real name column if available
                    if ($nameColumn) {
                        $fields[] = "`" . $nameColumn . "` = ?";
                        $params[] = $newName;
                    } else {
                        // DB has no writable name column; still update local value and inform user
                        $user['name'] = $newName;
                        $updateMessage = 'Lưu tên thành công. Trường tên không tồn tại trong DB, thay đổi chỉ hiển thị tạm.';
                    }

                    // only attempt to update avatar column if DB supports it
                    if ($newAvatar !== '' && $avatarColumn) {
                        $fields[] = "`" . $avatarColumn . "` = ?";
                        $params[] = $newAvatar;
                    } elseif ($newAvatar !== '' && !$avatarColumn) {
                        // avatar column missing
                        // still update local $user so UI shows the provided URL, but inform user
                        $user['avatar'] = $newAvatar;
                        $updateMessage = ($updateMessage ? $updateMessage . ' ' : '') . 'Trường avatar không tồn tại trong DB, ảnh chỉ hiển thị tạm thời.';
                    }

                    $params[] = $userId;
                    // execute update if there is at least one DB column to write to
                    if (count($fields) > 0 && ($nameColumn || $avatarColumn)) {
                        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ? LIMIT 1";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        // refresh local user info
                        if ($nameColumn) $user['name'] = $newName;
                        if ($newAvatar !== '') $user['avatar'] = $newAvatar;
                        if ($updateMessage === '') $updateMessage = 'Cập nhật thành công.';
                    } else {
                        // avatar column missing - still updated local user
                        if ($updateMessage === '') $updateMessage = 'Cập nhật tên thành công (avatar không được lưu vào DB).';
                    }
                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    $updateMessage = 'Lỗi khi cập nhật.';
                    if (!empty($DEBUG_SHOW_ERRORS)) {
                        $updateMessage .= ' Chi tiết: ' . $e->getMessage();
                    }
                }
            } else {
                $updateMessage = 'Không có kết nối DB.';
            }
        }
    }
}

// Additional features: change password, notifications and redemption management
$extraMessage = '';
try {
    // ensure notifications table exists
    if (isset($pdo)) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `type` VARCHAR(60) DEFAULT 'info',
            `message` TEXT NOT NULL,
            `meta` JSON DEFAULT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // ensure redemptions table (if not already)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `redemptions` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `reward_title` VARCHAR(255) NOT NULL,
          `reward_cost` INT NOT NULL,
          `image` VARCHAR(255) DEFAULT NULL,
          `redemption_code` VARCHAR(20) DEFAULT NULL,
          `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // detect points column for refund logic
    $pointsCol = null;
    if (isset($pdo)) {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $prefer = ['points','reward_points','reward_point','score','balance','credits','coins','point'];
        foreach ($prefer as $p) if (in_array($p, $cols, true)) { $pointsCol = $p; break; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action_extra'])) {
        $act = $_POST['action_extra'];

        // helper: detect password column
        $detectPasswordColumn = function() use ($pdo) {
            if (!isset($pdo)) return null;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                foreach (['password','pass','passwd','user_pass'] as $c) if (in_array($c, $cols, true)) return $c;
            } catch (Throwable $__e) {
                return null;
            }
            return null;
        };

        if ($act === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($new === '' || $confirm === '' || $new !== $confirm) {
                $extraMessage = 'Mật khẩu mới trống hoặc không khớp.';
            } else {
                if (!isset($pdo)) {
                    $extraMessage = 'DB không khả dụng để thay đổi mật khẩu.';
                } else {
                    // verify current password first and immediately update if correct
                    $pwCol = $detectPasswordColumn();
                    if (!$pwCol) {
                        $extraMessage = 'Không tìm thấy cột mật khẩu trong DB. Vui lòng liên hệ admin.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("SELECT {$pwCol} FROM users WHERE id = ? LIMIT 1");
                            $stmt->execute([$userId]);
                            $stored = (string)$stmt->fetchColumn();
                            $ok = false;
                            if (password_get_info($stored)['algo'] !== 0) {
                                $ok = password_verify($current, $stored);
                            } else {
                                $ok = ($current === $stored);
                            }
                            if (!$ok) {
                                $extraMessage = 'Mật khẩu hiện tại không đúng.';
                            } else {
                                // update immediately
                                $newHash = password_hash($new, PASSWORD_DEFAULT);
                                $u = $pdo->prepare("UPDATE users SET {$pwCol} = ? WHERE id = ? LIMIT 1");
                                $u->execute([$newHash, $userId]);
                                $extraMessage = 'Đổi mật khẩu thành công.';
                            }
                        } catch (Throwable $e) {
                            error_log($e->getMessage());
                            $extraMessage = 'Lỗi khi kiểm tra hoặc cập nhật mật khẩu.';
                        }
                    }
                }
            }
        }

        if ($act === 'cancel_redemption' && !empty($_POST['redemption_id'])) {
            $rid = (int)$_POST['redemption_id'];
            if (!isset($pdo)) throw new Exception('DB không khả dụng.');
            $pdo->beginTransaction();
            try {
                $sel = $pdo->prepare("SELECT user_id, reward_cost, status FROM redemptions WHERE id = ? FOR UPDATE");
                $sel->execute([$rid]);
                $r = $sel->fetch(PDO::FETCH_ASSOC);
                if (!$r || (int)$r['user_id'] !== $userId) throw new Exception('Không tìm thấy giao dịch.');
                if ($r['status'] !== 'pending') throw new Exception('Chỉ có thể hủy các yêu cầu ở trạng thái pending.');
                // update to cancelled
                $upd = $pdo->prepare("UPDATE redemptions SET status = 'cancelled' WHERE id = ?");
                $upd->execute([$rid]);
                // refund points if column exists
                if ($pointsCol) {
                    $refund = (int)$r['reward_cost'];
                    $pdo->prepare("UPDATE users SET {$pointsCol} = GREATEST(0, {$pointsCol} + ?) WHERE id = ?")->execute([$refund, $userId]);
                }
                // insert notification
                if (isset($pdo)) {
                    $n = $pdo->prepare("INSERT INTO notifications (user_id,type,message,meta) VALUES (?, 'info', ?, JSON_OBJECT('redemption_id',?))");
                    $n->execute([$userId, 'Yêu cầu đổi quà đã bị hủy và điểm được hoàn lại.', $rid]);
                }
                $pdo->commit();
                $extraMessage = 'Hủy yêu cầu thành công.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $extraMessage = 'Lỗi khi hủy: ' . $e->getMessage();
            }
        }

        if ($act === 'mark_notification_read' && !empty($_POST['notification_id'])) {
            $nid = (int)$_POST['notification_id'];
            if (isset($pdo)) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$nid, $userId]);
            }
        }

        if ($act === 'clear_notifications') {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            $extraMessage = 'Đã xoá thông báo.';
        }

        // after actions, reload data variables below
    }

    // fetch notifications and user's redemptions for display
    $notifications = [];
    if (isset($pdo)) {
        $notifications = $pdo->prepare("SELECT id,type,message,meta,is_read,created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $notifications->execute([$userId]);
        $notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);
    }

    $redemptions = [];
    if (isset($pdo)) {
        // detect possible description-like columns in redemptions and include them in SELECT
        $descColsToCheck = ['description','detail','info','long_description','notes','meta'];
    $selectCols = ['id','reward_title','reward_cost','image','status','created_at'];
    try {
      $availableRedCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'redemptions'")->fetchAll(PDO::FETCH_COLUMN);
      // include redemption_code if present in the table
      if (in_array('redemption_code', $availableRedCols, true)) {
        $selectCols[] = 'redemption_code';
      }
      foreach ($descColsToCheck as $dc) {
        if (in_array($dc, $availableRedCols, true)) {
          $selectCols[] = $dc;
        }
      }
    } catch (Throwable $__e) {
      // ignore - fall back to default columns
    }

        $sql = 'SELECT ' . implode(',', $selectCols) . ' FROM redemptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50';
        $rs = $pdo->prepare($sql);
        $rs->execute([$userId]);
        $redemptions = $rs->fetchAll(PDO::FETCH_ASSOC);

        // enrich redemptions with matching reward details when possible
        try {
            $hasRewards = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewards'")->fetchColumn();
            if ($hasRewards) {
                $lookup = $pdo->prepare("SELECT * FROM rewards WHERE title = ? OR name = ? OR image = ? OR image LIKE ? OR title LIKE ? LIMIT 1");
                $descFieldCandidates = ['description','detail','info','long_description','content','text','short_description'];
                foreach ($redemptions as &$rr) {
                    // initialize
                    $rr['desc'] = $rr['desc'] ?? '';
                    $rr['store'] = $rr['store'] ?? '';
                    $rr['link'] = $rr['link'] ?? '';

                    // if redemption row contained a description-like column, prefer it
                    foreach ($descColsToCheck as $dc) {
                        if (!empty($rr[$dc])) { $rr['desc'] = $rr[$dc]; break; }
                    }

                    // attempt to find a matching rewards row
                    $title = $rr['reward_title'] ?? '';
                    $img = $rr['image'] ?? '';
                    $bn = $img ? basename($img) : '';
                    try {
                        $lookup->execute([$title, $title, $img, '%' . $bn . '%', '%' . $title . '%']);
                        $found = $lookup->fetch(PDO::FETCH_ASSOC);
                        if ($found) {
                            // fill desc if empty by checking many possible fields
                            if (empty($rr['desc'])) {
                                foreach ($descFieldCandidates as $f) {
                                    if (!empty($found[$f])) { $rr['desc'] = $found[$f]; break; }
                                }
                            }
                            if (empty($rr['store']) && !empty($found['store'])) $rr['store'] = $found['store'];
                            if (empty($rr['link']) && !empty($found['link'])) $rr['link'] = $found['link'];
                            if (empty($rr['image']) && !empty($found['image'])) $rr['image'] = $found['image'];
                        }
                    } catch (Throwable $__e) {
                        // ignore individual lookup errors
                    }
                }
                unset($rr);
            }
        } catch (Throwable $e) {
            error_log('Enrich redemptions: ' . $e->getMessage());
        }
    }

    // Fetch user activities from the database 
$activities = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.event_id,
                e.title as activity_title,
                r.created_at,
                e.description as activity_description,
                e.image as activity_image 
            FROM registrations r
            LEFT JOIN events e ON r.event_id = e.id 
            WHERE r.user_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}
} catch (Throwable $e) {
    error_log('Profile extras error: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hồ sơ - GREENSTEP</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{--green:#2e8b57;--dark:#16321f;--muted:#55676a;--light:#f3fcf6;--accent:#8bd59b}
    *{box-sizing:border-box}
    html,body{height:100%;margin:0}
    body{display:flex;flex-direction:column;min-height:100vh;font-family:Inter,Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f6fbf7 0%, #eef8ef 100%);color:var(--dark);-webkit-font-smoothing:antialiased}
    .container{max-width:1100px;margin:0 auto;padding:0 20px}

    /* header */
    .topbar{background:rgba(255,255,255,0.6);backdrop-filter:saturate(120%) blur(6px);border-bottom:1px solid rgba(46,139,87,0.08);padding:12px 0;box-shadow:0 2px 8px rgba(30,60,40,0.03)}
    .navbar{display:flex;align-items:center;gap:16px;height:64px}
    .navbar-left{display:flex;align-items:center;gap:12px}
    .nav-leaf{width:48px;height:48px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(180deg,var(--green),#28a349);box-shadow:0 8px 24px rgba(46,139,87,0.12)}
    .nav-leaf i{color:#fff;font-size:18px}
    .brand-text{font-weight:800;color:var(--green);font-size:20px;letter-spacing:1px}
    .nav-center{flex:1;display:flex;justify-content:center}
    .nav-links{display:flex;gap:18px;align-items:center}
    .nav-links a{font-weight:700;color:var(--dark);text-decoration:none;padding:8px 12px;border-radius:10px}
    .nav-links a.active{background:#eef6f0;color:var(--green)}
    .nav-right{display:flex;gap:12px;align-items:center}

    /* buttons */
    .btn-primary{background:var(--green);color:#fff;padding:10px 14px;border-radius:10px;border:none;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 20px rgba(46,139,87,0.08)}
    .btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 30px rgba(46,139,87,0.12)}
    .btn-ghost{background:transparent;border:1px solid rgba(46,139,87,0.12);color:var(--green);padding:8px 12px;border-radius:10px;font-weight:700}
    .btn-ghost.active, .tab-btn.active{background:#fff;border:1px solid rgba(0,0,0,0.04);box-shadow:0 6px 18px rgba(30,60,40,0.06)}

    /* profile layout */
    main{flex:1;padding:36px 0}
    .profile{display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap}
    .card{background:linear-gradient(180deg,#ffffff,#fbfff9);border-radius:14px;padding:18px;box-shadow:0 14px 40px rgba(20,40,30,0.06);border:1px solid rgba(0,0,0,0.03)}
    .profile-left{width:340px;flex-shrink:0}
    .avatar{width:100%;height:340px;object-fit:cover;border-radius:12px;border:6px solid rgba(255,255,255,0.7);box-shadow:0 12px 30px rgba(20,40,20,0.08)}
    .meta{margin-top:14px}
    .meta h2{margin:6px 0 4px;font-size:22px;color:var(--dark)}
    .meta p{margin:0;color:var(--muted)}
    .profile-right{flex:1;min-width:320px}

    form .row{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
    label{font-weight:700;color:#274b36}
    input[type="text"], input[type="url"], input[type="email"], input[type="password"]{padding:10px;border-radius:10px;border:1px solid rgba(0,0,0,0.06);background:#fff}
    .pts{display:inline-block;background:linear-gradient(90deg,#eaf8ef,#f6fff9);color:var(--green);padding:8px 12px;border-radius:16px;font-weight:800;margin-right:8px}
    .actions{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
    .note{margin-top:10px;color:#2f4d3b}

    /* tabs */
    .profile-tabs{display:flex;gap:8px;align-items:center;margin-bottom:20px}
    /* Tab links styled like buttons: remove underline and ensure pointer */
    .profile-tabs .tab-btn{
      cursor:pointer;
      background:transparent;
      border:1px solid transparent;
      padding:8px 12px;
      border-radius:10px;
      color:var(--dark);
      font-weight:800;
      transition:all 0.15s ease;
      text-decoration:none; /* remove underline */
      display:inline-block;
    }
    .profile-tabs .tab-btn.active, .profile-tabs .tab-btn[aria-selected="true"]{
      background:#fff;
      border:1px solid rgba(0,0,0,0.04);
      color:var(--green);
      box-shadow:0 8px 20px rgba(30,60,40,0.06);
      text-decoration:none;
    }
    .profile-tabs .tab-btn:hover:not(.active):not([aria-selected="true"]){
      background:rgba(255,255,255,0.5);
      border:1px solid rgba(0,0,0,0.02);
      text-decoration:none;
    }

    .tab-panels{margin-top:8px}
    .tab-panel{padding:12px;display:none}
    .tab-panel.active{display:block}

    /* list items */
    ul{margin:0;padding:0}
    li{list-style:none}
    .notification-item, .redemption-item{display:flex;gap:12px;padding:12px;border-radius:10px;background:#fff;border:1px solid rgba(0,0,0,0.04);align-items:flex-start}
    .notification-item strong{color:var(--green)}

    /* modal */
    #redemption-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:12000}
    #redemption-modal > div{transform:translateY(0);transition:transform .18s ease}

    /* responsive */
    @media(max-width:900px){
      .profile{flex-direction:column}
      .profile-left{width:100%;order:1}
      .profile-right{order:2}
      .avatar{height:260px}
      .nav-center{display:none}
    }

    /* footer stays bottom */
    footer{background:var(--green);color:#fff;padding:28px 12px;margin-top:auto}
    footer .container{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container">
      <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="navbar-left">
          <a class="nav-leaf" href="/Expense_tracker-main/Expense_Tracker/index.php" title="Tin tức xanh"><i class="fas fa-leaf"></i></a>
          <span class="brand-text">GREENSTEP</span>
        </div>

        <div class="nav-center">
          <div class="nav-links" role="menubar" aria-label="Primary">
            <a href="/Expense_tracker-main/Expense_Tracker/index.php" role="menuitem">TRANG CHỦ</a>
            <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" role="menuitem">ĐỔI THƯỞNG</a>
            <a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" role="menuitem">TIN TỨC XANH</a>
            <a href="/Expense_tracker-main/Expense_Tracker/news_web/contact.php" role="menuitem">LIÊN HỆ</a>
          </div>
        </div>

        <div class="nav-right">
          <a href="/Expense_tracker-main/Expense_Tracker/logout.php" class="btn-primary">Đăng xuất</a>
        </div>
      </nav>
    </div>
  </header>

  <main class="container">
    <div class="profile">
      <div class="profile-left card">
        <img src="<?php echo htmlspecialchars(resolve_url_path($user['avatar'] ?? '')); ?>" alt="Avatar" class="avatar">
        <div class="meta">
          <h2><?php echo htmlspecialchars($user['name']); ?></h2>
          <p><?php echo htmlspecialchars($user['email']); ?></p>
          <div style="margin-top:10px">
            <span class="pts"><?php echo (int)$user['points']; ?> điểm</span>
            <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" class="btn-primary" style="margin-left:8px">Đổi ngay</a>
          </div>
        </div>
      </div>

      <div class="profile-right card">
        <!-- Tab navigation -->
        <div class="profile-tabs">
          <div style="display:flex;gap:8px">
            <a href="?tab=edit" class="tab-btn <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'edit') ? 'active' : ''; ?>">Chỉnh sửa hồ sơ</a>
            <a href="?tab=password" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'password') ? 'active' : ''; ?>">Đổi mật khẩu</a>
            <a href="?tab=notifications" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'notifications') ? 'active' : ''; ?>">Thông báo</a>
            <a href="?tab=redemptions" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'redemptions') ? 'active' : ''; ?>">Yêu cầu đổi quà</a>
            <a href="?tab=activity" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'activity') ? 'active' : ''; ?>">Lịch sử hoạt động</a>
          </div>
        </div>

        <div class="tab-panels">
          <div id="panel-edit" class="tab-panel" style="display: <?php echo $currentTab === 'edit' ? 'block' : 'none'; ?>">
            <h3>Chỉnh sửa hồ sơ</h3>
            <?php if ($updateMessage): ?>
              <div style="margin:10px 0;padding:10px;background:#f0fff4;border-left:4px solid var(--green)"><?php echo htmlspecialchars($updateMessage); ?></div>
            <?php endif; ?>
            <form method="post" action="" enctype="multipart/form-data">
              <div class="row">
                <label for="name">Tên hiển thị</label>
                <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" required>
              </div>
              
              <div class="row">
                <label for="avatar_file">tải lên avatar (jpg/png/gif)</label>
                <input id="avatar_file" name="avatar_file" type="file" accept="image/*">
              </div>
              <div class="actions">
                <button type="submit" class="btn-primary">Lưu thay đổi</button>
                <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/history.php" class="btn-primary">Xem lịch sử</a>
                <a href="/Expense_tracker-main/Expense_Tracker/index.php" class="btn-primary">Về trang chủ</a>
              </div>
              <p class="note">Lưu ý: mọi thay đổi sẽ cập nhật trực tiếp vào hồ sơ. Nếu DB không có cột avatar/name, thay đổi sẽ được bỏ qua.</p>
            </form>
          </div>

          <div id="panel-password" class="tab-panel" style="display: <?php echo $currentTab === 'password' ? 'block' : 'none'; ?>">
            <section id="change-password" style="margin-top:12px">
              <h3>Đổi mật khẩu</h3>
              <?php if (!empty($extraMessage)): ?>
                <div style="margin:8px 0;padding:10px;background:#f0fff4;border-left:4px solid var(--green)"><?php echo htmlspecialchars($extraMessage); ?></div>
              <?php endif; ?>
              <form method="post" action="" style="margin-top:8px">
                <input type="hidden" name="action_extra" value="change_password">
                <div class="row"><label for="current_password">Mật khẩu hiện tại</label><input id="current_password" name="current_password" type="password" required></div>
                <div class="row"><label for="new_password">Mật khẩu mới</label><input id="new_password" name="new_password" type="password" minlength="6" required></div>
                <div class="row"><label for="confirm_password">Nhập lại mật khẩu mới</label><input id="confirm_password" name="confirm_password" type="password" minlength="6" required></div>
                <div class="actions" style="margin-top:8px"><button type="submit" class="btn-primary">Đổi mật khẩu</button></div>
              </form>
            </section>
          </div>

          <div id="panel-notifications" class="tab-panel" style="display: <?php echo $currentTab === 'notifications' ? 'block' : 'none'; ?>">
            <section id="notifications" style="margin-top:18px">
              <h3>Thông báo</h3>
              <?php if (!empty($extraMessage) && strpos($extraMessage, 'Đổi mật khẩu') === false && !empty($extraMessage)): ?>
                <div style="margin:8px 0;padding:10px;background:#f0fff4;border-left:4px solid var(--green)"><?php echo htmlspecialchars($extraMessage); ?></div>
              <?php endif; ?>

              <?php if (empty($notifications)): ?>
                <div style="color:#666;margin:8px 0">Không có thông báo.</div>
              <?php else: ?>
                <ul style="list-style:none;padding:0;margin:8px 0;display:flex;flex-direction:column;gap:8px">
                  <?php foreach ($notifications as $note): ?>
                    <li style="background:#fff;border:1px solid rgba(0,0,0,0.04);padding:10px;border-radius:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                      <div style="flex:1">
                        <strong style="display:block;color:#223"><?php echo htmlspecialchars($note['type']); ?></strong>
                        <div style="color:#444;margin-top:6px;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($note['message'])); ?></div>
                        <div style="color:#888;font-size:12px;margin-top:6px"><?php echo htmlspecialchars($note['created_at']); ?><?php if (!empty($note['meta'])) echo ' • ' . htmlspecialchars($note['meta']); ?></div>
                      </div>
                      <div style="display:flex;flex-direction:column;gap:6px;margin-left:8px">
                        <?php if (!$note['is_read']): ?>
                          <form method="post" style="margin:0"><input type="hidden" name="action_extra" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?php echo (int)$note['id']; ?>"><button type="submit" class="btn-ghost">Đánh dấu đã đọc</button></form>
                        <?php endif; ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
                <form method="post" style="margin-top:8px"><input type="hidden" name="action_extra" value="clear_notifications"><button type="submit" class="btn-ghost">Xoá tất cả thông báo</button></form>
              <?php endif; ?>
            </section>
          </div>

          <div id="panel-redemptions" class="tab-panel" style="display: <?php echo $currentTab === 'redemptions' ? 'block' : 'none'; ?>">
            <section id="my-redemptions" style="margin-top:18px">
              <h3>Yêu cầu đổi quà của tôi</h3>
              <?php if (empty($redemptions)): ?>
                <div style="color:#666;margin:8px 0">Bạn chưa có yêu cầu đổi quà nào.</div>
              <?php else: ?>
                <ul style="list-style:none;padding:0;margin:8px 0;display:flex;flex-direction:column;gap:12px">
                  <?php foreach ($redemptions as $r): ?>
                    <li style="background:#fff;border:1px solid rgba(0,0,0,0.04);padding:12px;border-radius:8px;display:flex;gap:12px;align-items:center">
                      <?php
                        // normalize redemption image for display and modal
                        $redImg = resolve_url_path($r['image'] ?? '');
                      ?>
                      <img src="<?php echo htmlspecialchars($redImg); ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px">
                      <div style="flex:1">
                        <div style="font-weight:700"><?php echo htmlspecialchars($r['reward_title']); ?></div>
                        <div style="color:#777;font-size:13px"><?php echo (int)$r['reward_cost']; ?> điểm • <?php echo htmlspecialchars($r['created_at']); ?></div>
                        <?php if (!empty($r['redemption_code'])): ?>
                          <div style="color:#444;font-size:13px;margin-top:6px">Mã đổi thưởng: <strong><?php echo htmlspecialchars($r['redemption_code']); ?></strong></div>
                        <?php endif; ?>
                        <?php
                        $statusColor = '';
                        switch($r['status']) {
                            case 'approved':
                                $statusColor = '#2a8b49'; // Success - Green
                                break;
                            case 'pending':
                                $statusColor = '#f0ad4e'; // Pending - Yellow/Orange
                                break;
                            case 'rejected':
                                $statusColor = '#dc3545'; // Cancel - Red
                                break;
                        }
                        ?>
                        <?php
                        // map status values to Vietnamese labels
                        $statusLabel = htmlspecialchars($r['status']);
                        if ($r['status'] === 'approved') $statusLabel = 'Đã đổi thành công';
                        elseif ($r['status'] === 'pending') $statusLabel = 'Đang xử lý';
                        elseif ($r['status'] === 'rejected') $statusLabel = 'Đã hủy';
                        ?>
                        <div style="margin-top:6px;color:<?php echo $statusColor; ?>;font-weight:700"><?php echo $statusLabel; ?></div>
                      </div>
                      <div style="display:flex;flex-direction:column;gap:8px">
                        <button type="button" class="btn-ghost" data-rid="<?php echo (int)$r['id']; ?>" data-title="<?php echo htmlspecialchars($r['reward_title'], ENT_QUOTES); ?>" data-image="<?php echo htmlspecialchars($redImg, ENT_QUOTES); ?>" data-cost="<?php echo (int)$r['reward_cost']; ?>" data-store="<?php echo htmlspecialchars($r['store'] ?? '', ENT_QUOTES); ?>" data-desc="<?php echo htmlspecialchars($r['desc'] ?? '', ENT_QUOTES); ?>" data-link="<?php echo htmlspecialchars($r['link'] ?? '', ENT_QUOTES); ?>" onclick="showRedemptionDetail(this)">Xem chi tiết</button>
                        <?php if ($r['status'] === 'pending'): ?>
                          <form method="post" style="margin:0"><input type="hidden" name="action_extra" value="cancel_redemption"><input type="hidden" name="redemption_id" value="<?php echo (int)$r['id']; ?>"><button type="submit" class="btn-primary">Hủy</button></form>
                        <?php endif; ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>

            <!-- Modal: redemption / reward detail (richer UI from reward.php) -->
            <div id="redemption-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:12000;align-items:center;justify-content:center;padding:20px;">
              <div style="width:100%;max-width:760px;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;max-height:92vh;position:relative">
                <button aria-label="Đóng" onclick="closeRedemptionModal()" style="position:absolute;right:14px;top:12px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#333;z-index:11010">&times;</button>
                <div style="overflow:auto;padding:18px;">
                  <div style="height:360px;border-radius:8px;overflow:hidden;background:#f6f6f6;display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
                    <img id="redemp-img" src="/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg" alt="Ảnh sản phẩm" style="width:100%;height:100%;object-fit:contain;display:block;">
                  </div>

                  <h2 id="redemp-title" style="margin:0 0 6px;font-size:22px;color:#111"></h2>
                  <div style="color:#6b6b6b;margin-bottom:12px;display:flex;align-items:center;gap:12px">
                    <div id="redemp-cost" style="font-weight:800;color:var(--green);font-size:20px"></div>
                    <div id="redemp-store" style="color:#888;font-size:14px"></div>
                  </div>

                  <div style="background:#faf6f8;border-radius:10px;padding:14px;margin-bottom:12px">
                    <h4 style="margin:0 0 8px;color:var(--green);font-weight:700">Thông tin sản phẩm</h4>
                    <div id="redemp-desc" style="color:#333;line-height:1.6;white-space:pre-wrap">Chưa có thông tin chi tiết.</div>
                  </div>
                </div>

                <div style="border-top:1px solid rgba(0,0,0,0.06);padding:12px 18px;display:flex;gap:10px;align-items:center;justify-content:space-between;">
                  <a id="redemp-exchange" href="#" class="btn-primary" style="text-decoration:none;display:inline-block">Tiến hành đổi quà</a>
                  <button onclick="closeRedemptionModal()" class="btn-ghost" style="background:transparent;border:1px solid rgba(46,139,87,0.12);padding:8px 14px;border-radius:8px">Đóng</button>
                </div>
              </div>
            </div>

            <script>
              function normalizeImgPath(p){
                if (!p) return '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
                p = String(p).trim();
                if (/^https?:\/\//i.test(p)) return p;
                if (p.charAt(0) === '/') return p;
                return '/Expense_tracker-main/Expense_Tracker/' + p.replace(/^\.+\/*/, '');
              }

              function showRedemptionDetail(btn){
                var title = btn.getAttribute('data-title') || '';
                var img = btn.getAttribute('data-image') || '';
                var cost = btn.getAttribute('data-cost') || '';
                var store = btn.getAttribute('data-store') || '';
                var desc = btn.getAttribute('data-desc') || '';
                var link = btn.getAttribute('data-link') || ('/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php?item=' + encodeURIComponent(title));

                // normalize image path similarly to reward.php
                if (img && img.charAt(0) !== '/' && !/^https?:\/\//i.test(img)) img = '/Expense_tracker-main/Expense_Tracker/' + img.replace(/^\.+\/*/, '');

                document.getElementById('redemp-img').src = normalizeImgPath(img);
                document.getElementById('redemp-title').textContent = title;
                document.getElementById('redemp-cost').textContent = cost || (btn.getAttribute('data-cost') ? btn.getAttribute('data-cost') + ' điểm' : '');
                document.getElementById('redemp-store').textContent = store ? ('• ' + store) : '';
                document.getElementById('redemp-desc').textContent = desc || 'Chưa có thông tin chi tiết.';
                var ex = document.getElementById('redemp-exchange');
                if (ex) ex.href = link;

                document.getElementById('redemption-modal').style.display = 'flex';
              }
              function closeRedemptionModal(){ document.getElementById('redemption-modal').style.display = 'none'; }

              // close on overlay click and Escape
              document.getElementById('redemption-modal').addEventListener('click', function(e){ if (e.target === this) this.style.display='none'; });
              window.addEventListener('keydown', function(e){ if (e.key === 'Escape') { var m = document.getElementById('redemption-modal'); if (m && m.style.display==='flex') m.style.display='none'; } });
            </script>
          </div>

          <div id="panel-activity" class="tab-panel" style="display: <?php echo $currentTab === 'activity' ? 'block' : 'none'; ?>">
            <h3>Lịch sử hoạt động</h3>
            <?php if (empty($activities)): ?>
                <div style="color:#666;margin:8px 0">Bạn chưa tham gia hoạt động nào.</div>
            <?php else: ?>
                <ul style="list-style:none;padding:0;margin:8px 0;display:flex;flex-direction:column;gap:12px">
                    <?php foreach ($activities as $activity): ?>
                        <?php 
                            // Chuẩn hóa đường dẫn ảnh trước khi encode JSON
                            if (!empty($activity['activity_image'])) {
                                $activity['activity_image'] = resolve_url_path($activity['activity_image']);
                            }
                        ?>
                        <li style="background:#fff;border:1px solid rgba(0,0,0,0.04);padding:12px;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['activity_title']); ?></strong>
                                <div style="color:#777;font-size:13px"><?php echo htmlspecialchars($activity['created_at']); ?></div>
                            </div>
                            <button type="button" class="btn-ghost" onclick='showActivityDetail(<?php echo htmlspecialchars(json_encode($activity)); ?>)'>Chi tiết</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
          </div>
        </div>

        <!-- Popup Modal for Activity Details -->
        <div id="activity-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:12000">
            <div style="width:100%;max-width:600px;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;position:relative">
                <button aria-label="Đóng" onclick="closeActivityModal()" style="position:absolute;right:14px;top:12px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#333;z-index:11010">&times;</button>
                <div style="padding:18px;">
                    <!-- Thêm phần hiển thị ảnh -->
                    <div id="activity-image-container" style="height:300px;border-radius:8px;overflow:hidden;background:#f6f6f8;margin-bottom:14px;display:none">
                        <img id="activity-image" src="" alt="Ảnh hoạt động" style="width:100%;height:100%;object-fit:cover">
                    </div>
                    <h2 id="activity-title" style="margin:0 0 12px;color:var(--dark)"></h2>
                    <div style="background:#faf6f8;border-radius:10px;padding:14px;margin-bottom:12px">
                        <h4 style="margin:0 0 8px;color:var(--green);font-weight:700">Chi tiết hoạt động</h4>
                        <div id="activity-description" style="color:#333;line-height:1.6;white-space:pre-wrap"></div>
                    </div>
                    <div style="color:#666;font-size:13px" id="activity-time"></div>
                </div>
                <div style="border-top:1px solid rgba(0,0,0,0.06);padding:12px 18px;display:flex;justify-content:flex-end">
                    <button onclick="closeActivityModal()" class="btn-ghost">Đóng</button>
                </div>
            </div>
        </div>

        <script>
          function showActivityDetail(activity) {
              document.getElementById('activity-title').textContent = activity.activity_title || 'Chi tiết hoạt động';
              document.getElementById('activity-description').textContent = activity.activity_description || 'Chưa có thông tin chi tiết.';
              document.getElementById('activity-time').textContent = 'Thời gian: ' + activity.created_at;
              
              // Chuẩn hóa đường dẫn ảnh
              const imageContainer = document.getElementById('activity-image-container');
              const imageElement = document.getElementById('activity-image');
              if (activity.activity_image) {
                  // Chuẩn hóa đường dẫn ảnh
                  let imagePath = activity.activity_image;
                  if (!imagePath.startsWith('http') && !imagePath.startsWith('/')) {
                      imagePath = '/Expense_tracker-main/Expense_Tracker/' + imagePath.replace(/^\.+\/*/, '');
                  }
                  imageElement.src = imagePath;
                  imageContainer.style.display = 'block';
              } else {
                  imageContainer.style.display = 'none';
              }
              
              document.getElementById('activity-modal').style.display = 'flex';
          }

          function closeActivityModal() {
              document.getElementById('activity-modal').style.display = 'none';
          }

          // Thêm sự kiện đóng modal khi click bên ngoài
          document.getElementById('activity-modal').addEventListener('click', function(e) {
              if (e.target === this) {
                  closeActivityModal();
              }
          });

          // Thêm sự kiện đóng modal khi nhấn ESC
          window.addEventListener('keydown', function(e) {
              if (e.key === 'Escape') {
                  closeActivityModal();
              }
          });
        </script>

        <!-- Legacy tab JS removed to avoid interfering with PHP/GET based tabs -->
      </div>
    </div>
  </main>

  <!-- Footer copied from index.php -->
  <footer class="site-footer" role="contentinfo">
      <style>
        .site-footer{ background: linear-gradient(180deg,#0f6a52,#16a085); color:#fff; padding:48px 0 24px; }
        .site-footer .footer-inner{ max-width:1200px; margin:0 auto; display:flex; gap:24px; align-items:flex-start; justify-content:space-between; padding:0 18px; box-sizing:border-box; flex-wrap:wrap }
    .site-footer .col{ flex:1; min-width:200px }
    /* Ensure headings and list items inside site-footer are white (override Bootstrap) */
    .site-footer h4, .site-footer .col h4 { margin:0 0 12px; font-size:18px; font-weight:800; color: #fff !important; }
    .site-footer ul{ list-style:none; padding:0; margin:0 }
    .site-footer li{ margin-bottom:8px; color:rgba(255,255,255,0.95) !important }
    /* Links inside footer should be white and remain readable */
    .site-footer ul li a, .site-footer .col a, .site-footer .brand-sm a { color: #ffffff !important; text-decoration: none !important; }
    .site-footer ul li a:hover, .site-footer .col a:hover { color: rgba(255,255,255,0.98) !important; text-decoration: underline !important; }
    /* newsletter label and small text */
    .site-footer p, .site-footer .bottom { color: rgba(255,255,255,0.9) !important }
      /* Force footer links to white for better contrast inside news subsite */
      .site-footer a, .site-footer a:visited { color: #ffffff !important; text-decoration: none !important; }
      .site-footer a:hover, .site-footer a:focus { color: rgba(255,255,255,0.95) !important; text-decoration: underline !important; }
      .site-footer .brand-sm strong, .site-footer .brand-sm div { color: #ffffff !important; }
          .site-footer .bottom { color: rgba(255,255,255,0.9) }
          /* Ensure footer primary buttons are green (override other themes) */
          .site-footer .btn-primary, .site-footer button.btn-primary {
            background: linear-gradient(90deg,#2e8b57,#279046) !important;
            border-color: #2e8b57 !important;
            color: #ffffff !important;
            box-shadow: 0 8px 20px rgba(46,139,87,0.12) !important;
          }
          .site-footer .btn-primary:hover, .site-footer button.btn-primary:hover {
            background: linear-gradient(90deg,#279046,#1f6b39) !important;
            transform: translateY(-1px);
          }
        .socials { display:flex; gap:10px; margin-top:8px }
        .socials a{ display:inline-flex; width:38px; height:38px; align-items:center; justify-content:center; border-radius:8px; background:rgba(255,255,255,0.08); color:#fff; text-decoration:none }
        .site-footer .brand-sm{ display:flex; align-items:center; gap:12px; margin-bottom:12px }
        .site-footer .brand-sm .leaf{ width:40px; height:40px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(180deg,#fff,#fff); color:#16a085; }
        .site-footer .bottom { border-top:1px solid rgba(255,255,255,0.06); margin-top:18px; padding-top:16px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap }
        @media (max-width:720px){ .site-footer .footer-inner{ flex-direction:column } .site-footer .bottom{ flex-direction:column, align-items:center } }
      </style>

      <div class="footer-inner">
        <div class="col">
          <div class="brand-sm"><span class="leaf"><i class="fas fa-leaf"></i></span><div><strong>GREENSTEP</strong><div style="font-size:12px;color:rgba(255,255,255,0.9)">Hành động nhỏ - Tương lai xanh</div></div></div>
          <p style="color:rgba(255,255,255,0.9);max-width:320px">GREENSTEP kết nối cộng đồng tình nguyện viên vì môi trường. Tham gia các hoạt động, tích điểm và đổi phần thưởng có ý nghĩa.</p>
          <div class="socials">
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Linkedin"><i class="fab fa-linkedin-in"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          </div>
        </div>

        <div class="col">
          <h4>Liên kết</h4>
          <ul>
            <li><a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Sự kiện mới nhất</a></li>
            <li><a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Điều khoản &amp; Điều kiện</a></li>
            <li><a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Chính sách bảo mật</a></li>
            <li><a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Tuyển dụng</a></li>
          </ul>
        </div>

        <div class="col">
          <h4>Liên hệ</h4>
          <ul>
            <li><strong>Công ty Above</strong></li>
            <li>Đường JC Main, gần tòa nhà Silnie</li>
            <li>Mã vùng:21542 NewYork US.</li>
            <li>(123) 456-789</li>
            <li>email@domainname.com</li>
          </ul>
        </div>

        <div class="col">
          <h4>Bản tin</h4>
          <p style="color:rgba(255,255,255,0.9);margin-bottom:8px">Đăng ký nhận thông tin về hoạt động và chương trình mới.</p>
            <form action="#" method="post" onsubmit="return false;" style="display:flex;gap:8px;">
            <input type="email" placeholder="Email của bạn" style="flex:1;padding:8px;border-radius:8px;border:0;"> 
            <button class="btn-primary" style="padding:8px 12px;border-radius:8px;background:linear-gradient(90deg,#2e8b57,#279046) !important;border-color:#2e8b57 !important;color:#fff !important;box-shadow:0 8px 20px rgba(46,139,87,0.12) !important">OK</button>
          </form>
        </div>
      </div>

      <div class="bottom" style="max-width:1200px;margin:10px auto 0;padding:0 18px;box-sizing:border-box;color:rgba(255,255,255,0.9);">
        <div>© <?php echo date('Y'); ?> Green Initiative. All rights reserved.</div>
        <div>Thiết kế thân thiện | <a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Liên hệ</a></div>
      </div>
    </footer>
  <?php include './bottom_scripts.php'; ?>
  <script src="js/jquery.js"></script>
  <script src="js/jquery.easing.1.3.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/jquery.fancybox.pack.js"></script>
  <script src="js/jquery.fancybox-media.js"></script> 
  <script src="js/portfolio/jquery.quicksand.js"></script>
  <script src="js/portfolio/setting.js"></script>
  <script src="js/jquery.flexslider.js"></script>
  <script>
  $(window).load(function() {
    $('.flexslider').flexslider({
      animation: "fade",
      controlNav: true,
      directionNav: true,
      smoothHeight: true
    });
  });
  </script>
  <script src="js/animate.js"></script>
  <script src="js/custom.js"></script>
  <script src="js/owl-carousel/owl.carousel.js"></script>
  <script>
(function(){
  // footer scripts copied from index (kept minimal)
})();
  </script>

  <script>
    // Hàm ẩn tất cả panels
    function hideAllPanels() {
        document.getElementById('panel-edit').style.display = 'none';
        document.getElementById('panel-password').style.display = 'none';
        document.getElementById('panel-notifications').style.display = 'none';
        document.getElementById('panel-redemptions').style.display = 'none';
        document.getElementById('panel-activity').style.display = 'none';
    }

    // Hàm switch tab
    function openTab(tabName) {
        hideAllPanels();
        document.getElementById('panel-' + tabName).style.display = 'block';
    }

    // No client-side tab forcing: PHP controls which panel is visible via inline styles.
  </script>
</body>
</html>
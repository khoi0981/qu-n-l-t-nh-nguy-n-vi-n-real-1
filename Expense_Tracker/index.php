<?php
session_start();
// helper flags: logged-in state and current user id (avoid undefined variable warnings)
$isLogged = !empty($_SESSION['user_id']);
$userId = $isLogged ? (int)$_SESSION['user_id'] : null;

// Helper function to convert 12h to 24h format
function convertTo24Hour($timeStr) {
    if (strpos(strtoupper($timeStr), 'CH') !== false) {
        // Convert PM (Chiều) time
        $time = str_replace(' CH', '', $timeStr);
        $parts = explode(':', $time);
        if (count($parts) >= 2) {
            $hour = (int)$parts[0];
            if ($hour < 12) {
                $hour += 12;
            }
            return sprintf("%02d:%02d:00", $hour, (int)$parts[1]);
        }
    } elseif (strpos(strtoupper($timeStr), 'SA') !== false) {
        // Convert AM (Sáng) time
        $time = str_replace(' SA', '', $timeStr);
        $parts = explode(':', $time);
        if (count($parts) >= 2) {
            $hour = (int)$parts[0];
            if ($hour == 12) {
                $hour = 0;
            }
            return sprintf("%02d:%02d:00", $hour, (int)$parts[1]);
        }
    }
    return $timeStr; // Return as is if not in expected format
}

// Helper function to calculate event status
function calculateEventStatus($eventTime, $endTime = null) {
    if (!$eventTime) return ['status' => '', 'color' => '', 'minutes' => 0];
    
    try {
        // Đảm bảo timezone đồng nhất
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        
        // Parse start datetime - bảo đảm format đúng
        $startDate = new DateTime($eventTime);
        if (!$startDate) {
            error_log("Invalid start time format: $eventTime");
            return ['status' => '', 'color' => '', 'minutes' => 0];
        }
        
        // Parse end datetime if provided
        $endDate = null;
        if ($endTime) {
            try {
                $endDate = new DateTime($endTime);
            } catch (Exception $e) {
                error_log("Invalid end time format: $endTime");
                // If end time is invalid, we'll use fallback
            }
        }
        
        // If no valid end time, use start + 2 hours as fallback
        if (!$endDate) {
            $endDate = clone $startDate;
            $endDate->modify('+2 hours');
        }
        
        // Get current time with proper timezone
        $now = new DateTime();
        
        // Debug log with full datetime info
        error_log(sprintf(
            "Event times: start=%s, end=%s, now=%s",
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s')
        ));
        
        // Compare exact timestamps
        if ($now < $startDate) {
            // Sắp diễn ra: current time is before start time
            $diff = $now->diff($startDate);
            $minutesToStart = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            return [
                'status' => 'Sắp diễn ra',
                'minutes' => $minutesToStart,
                'color' => '#2ecc40'
            ];
        } elseif ($now <= $endDate) {
            // Đang diễn ra: current time is between start and end
            return [
                'status' => 'Đang diễn ra',
                'minutes' => 0,
                'color' => '#f7b731'
            ];
        } else {
            // Đã diễn ra: current time is after end time
            return [
                'status' => 'Đã diễn ra',
                'minutes' => 0,
                'color' => '#888'
            ];
        }
    } catch (Exception $e) {
        error_log("Lỗi tính status: " . $e->getMessage());
        return [
            'status' => 'Không xác định',
            'minutes' => 0,
            'color' => '#888'
        ];
    }
    
}

// Hàm helper để xác định trạng thái sự kiện
function determineEventStatus($eventDate) {
    if (!$eventDate) {
        return ['status' => '', 'color' => ''];
    }
    
    // Đặt timezone
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    // Lấy thời gian hiện tại
    $now = new DateTime();
    
    // Chuẩn hóa ngày và giờ sự kiện
    try {
        $eventTime = new DateTime($eventDate);
    } catch (Exception $e) {
        return ['status' => '', 'color' => ''];
    }
    
    // So sánh ngày và giờ
    $eventDay = $eventTime->format('Y-m-d');
    $nowDay = $now->format('Y-m-d');
    
    // Debug info
    error_log(sprintf(
        "Event: %s, Now: %s",
        $eventTime->format('Y-m-d H:i:s'),
        $now->format('Y-m-d H:i:s')
    ));
    
    if ($eventDay > $nowDay) {
        return ['status' => 'Sắp diễn ra', 'color' => '#2ecc40'];
    } elseif ($eventDay < $nowDay) {
        return ['status' => 'Đã diễn ra', 'color' => '#888'];
    } else {
        return ['status' => 'Đang diễn ra', 'color' => '#f7b731'];
    }
}

// Kiểm tra và tạo bảng registrations nếu chưa tồn tại
if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';

if (isset($pdo)) {
    try {
        // Kiểm tra bảng registrations đã tồn tại chưa
        $tableExists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'registrations'")->fetchColumn();
        
    if (!$tableExists) {
      // Tạo bảng registrations nếu chưa tồn tại (bao gồm các cột mới)
      $sql = "CREATE TABLE `registrations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `event_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `age` int(11) NOT NULL,
        `address` text NOT NULL,
        `health_status` varchar(100) DEFAULT NULL,
        `class_name` varchar(100) DEFAULT NULL,
        `school` varchar(255) DEFAULT NULL,
        `id_image` varchar(255) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `event_id` (`event_id`),
        KEY `user_id` (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

      $pdo->exec($sql);
    } else {
      // Nếu bảng đã tồn tại, đảm bảo các cột mới tồn tại (nếu không có thì thêm)
      $existingCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations'")->fetchAll(PDO::FETCH_COLUMN);
      $toAdd = [];
      if (!in_array('health_status', $existingCols, true)) {
        $toAdd[] = "ALTER TABLE `registrations` ADD COLUMN `health_status` varchar(100) DEFAULT NULL";
      }
      if (!in_array('class_name', $existingCols, true)) {
        $toAdd[] = "ALTER TABLE `registrations` ADD COLUMN `class_name` varchar(100) DEFAULT NULL";
      }
      if (!in_array('school', $existingCols, true)) {
        $toAdd[] = "ALTER TABLE `registrations` ADD COLUMN `school` varchar(255) DEFAULT NULL";
      }
      foreach ($toAdd as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) { error_log('Alter registrations error: '.$e->getMessage()); }
      }
    }
    } catch (Exception $e) {
        // Log lỗi nếu có
        error_log('Error creating registrations table: ' . $e->getMessage());
    }
}

// resolve user avatar (default if not found)
$userAvatarUrl = '/Expense_tracker-main/Expense_Tracker/news_web/img/avatar-default.png';
if ($isLogged) {
  // ensure PDO is available (same logic as later in the file)
  if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
    elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';
    if (!isset($pdo) && isset($dsn, $username, $password)) {
      try { $pdo = new PDO($dsn, $username, $password); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) { /* ignore */ }
    }
  }

  if (isset($pdo)) {
    try {
      // reuse column detection
      $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
      $pick = function(array $names) use ($cols){ foreach($names as $n) if (in_array($n,$cols,true)) return $n; return null; };

      // avatar
      $avatarCol = $pick(['avatar','user_avatar','photo','profile_pic','avatar_url']);
      if ($avatarCol) {
        $st = $pdo->prepare("SELECT `$avatarCol` FROM users WHERE id = ? LIMIT 1");
        $st->execute([$userId]);
        $val = $st->fetchColumn();
        if ($val && trim($val) !== '') {
          $val = trim((string)$val);
          if (preg_match('#^https?://#i', $val) || strpos($val,'/') === 0) {
            $userAvatarUrl = $val;
          } else {
            // correct local uploads path (uploads/users inside this project folder)
            $local = __DIR__ . '/uploads/users/' . $val;
            if (file_exists($local)) {
              $userAvatarUrl = '/Expense_tracker-main/Expense_Tracker/uploads/users/' . rawurlencode($val);
            } else {
              // fallback to attempt using as relative
              $userAvatarUrl = '/' . ltrim($val, '/');
            }
          }
        }
      }

      // points
      $pointsCol = $pick(['points','score','credits','balance','point','credits_balance']);
      $userPoints = 0;
      if ($pointsCol) {
        try {
          $pst = $pdo->prepare("SELECT `$pointsCol` FROM users WHERE id = ? LIMIT 1");
          $pst->execute([$userId]);
          $pv = $pst->fetchColumn();
          if ($pv !== false && $pv !== null && $pv !== '') {
            $userPoints = (int)$pv;
          }
        } catch (Throwable $e) {
          error_log('Error fetching user points: ' . $e->getMessage());
        }
      }
    } catch (Throwable $e) {
      // ignore, keep default
    }
  }
}
// xử lý đăng ký / hủy (chèn ở đây, trước output HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['user_id'])) {
    // đảm bảo kết nối PDO tồn tại
    if (!isset($pdo)) {
        if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
        elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';
    }
    try {
        if (!isset($pdo)) throw new Exception('DB not available');

        $user_id = (int)$_SESSION['user_id'];
        if ($_POST['action'] === 'register' && !empty($_POST['event_id'])) {
            $event_id = (int)$_POST['event_id'];
            $name = trim($_POST['name'] ?? '');
            $age = (int)($_POST['age'] ?? 0);
            $addr = trim($_POST['address'] ?? '');
            // new fields
            $health = trim($_POST['health'] ?? '') ?: null;
            $class_name = trim($_POST['class_name'] ?? '') ?: null;
            $school = trim($_POST['school'] ?? '') ?: null;
            $idImagePath = null;

            // upload id image nếu có
            if (!empty($_FILES['id_image']) && is_uploaded_file($_FILES['id_image']['tmp_name'])) {
                $file = $_FILES['id_image'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $info = @getimagesize($file['tmp_name']);
                    if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                        $ext = image_type_to_extension($info[2], false);
                        $basename = bin2hex(random_bytes(8));
                        $uploadDir = __DIR__ . '/uploads/registrations/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $fileName = $basename . '.' . $ext;
                        $target = $uploadDir . $fileName;
                        if (move_uploaded_file($file['tmp_name'], $target)) {
                            // lưu public path (thay prefix nếu app path khác)
                            $idImagePath = '/Expense_tracker-main/Expense_Tracker/uploads/registrations/' . $fileName;
                        }
                    }
                }
            }

            // chèn bản ghi vào bảng registrations (bao gồm các cột bổ sung)
            $stmt = $pdo->prepare("INSERT INTO `registrations` (`event_id`,`user_id`,`name`,`age`,`address`,`health_status`,`class_name`,`school`,`id_image`,`created_at`) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$event_id, $user_id, $name, $age, $addr, $health, $class_name, $school, $idImagePath]);
            // set session flag so we can show a popup after redirect
            $_SESSION['registration_success'] = true;
            // redirect để tránh resubmit và cập nhật giao diện
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }

        if ($_POST['action'] === 'unregister' && !empty($_POST['event_id'])) {
            $event_id = (int)$_POST['event_id'];
            $stmt = $pdo->prepare("DELETE FROM `registrations` WHERE event_id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$event_id, $user_id]);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    } catch (Throwable $e) {
        // ghi log, nhưng không lộ lỗi ra user
        error_log('Registration action error: ' . $e->getMessage());
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php include './top_scripts.php'; ?>
    <style>
        :root {
            --primary: #2e8b57;
            --primary-light: #eaf6f0;
            --text: #2c3e50;
            --text-light: #666;
            --border: #e5e7eb;
            --bg-light: #f8f6fc;
            --shadow: rgba(0,0,0,0.05);
        }
        
        body { 
            background: var(--bg-light); 
            margin: 0; 
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text);
            line-height: 1.6;
        }

        /* Scrollbar customization */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        /* Improved header/navbar */
        .navbar {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px 24px;
            background: linear-gradient(90deg, #fff, var(--primary-light));
            border-bottom: 1px solid rgba(46,139,87,0.08);
            flex-wrap: wrap;
            box-shadow: 0 2px 12px var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(8px);
        }

        .brand-wrap { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            flex-shrink: 0;
            transition: transform 0.2s;
        }
        .brand-wrap:hover {
            transform: translateY(-1px);
        }
        .brand-wrap img { 
            height: 48px; 
            display: block;
            filter: drop-shadow(0 2px 4px var(--shadow));
        }
        .brand-text {
            font-weight: 800;
            color: var(--primary);
            font-size: 22px;
            letter-spacing: 0.5px;
            white-space: nowrap;
            text-shadow: 0 1px 2px var(--shadow);
  }
  .nav-leaf {
    width: 40px; 
    height: 40px; 
    border-radius: 12px;
    display: inline-flex; 
    align-items: center; 
    justify-content: center;
    background: var(--primary); 
    color: #fff;
    box-shadow: 0 4px 12px rgba(46,139,87,0.15);
    transition: all 0.3s ease;
  }
  .nav-leaf:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(46,139,87,0.2);
  }

  .nav-links {
    display: flex;
    gap: 24px;
    flex: 1 1 auto;
    justify-content: center;
    align-items: center;
    padding: 0 20px;
  }
  .nav-links a {
    color: var(--text);
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.2s ease;
    position: relative;
  }
  .nav-links a:hover {
    color: var(--primary);
    background: var(--primary-light);
  }
  .nav-links a.active { 
    background: var(--primary-light); 
    color: var(--primary);
    font-weight: 700;
  }
  .nav-links a.active::after {
    content: '';
    position: absolute;
    bottom: 6px;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 3px;
    background: var(--primary);
    border-radius: 2px;
  }

  .nav-right {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-left: auto;
    flex-shrink: 0;
  }
  .points-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 8px 16px;
    border-radius: 12px;
    font-weight: 700;
    min-width: 70px;
    text-align: center;
    box-shadow: 0 2px 8px var(--shadow);
    transition: transform 0.2s;
  }
  .points-badge:hover {
    transform: translateY(-1px);
  }

  .btn-primary1, .btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
  }
    .btn-primary1 { 
      background: linear-gradient(180deg,var(--primary),#279046); 
      color: #fff; 
      border: 0; 
      box-shadow: 0 8px 20px rgba(46,139,87,0.12);
      padding: 9px 14px;
      border-radius: 10px;
      font-size:14px;
    }
    .btn-primary1:hover { 
      filter:brightness(.98);
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 30px rgba(46,139,87,0.18);
    }
    /* active / focus styles so the text visibly changes when clicked or focused */
    .btn-primary1:active,
    .btn-primary1:focus,
    .btn-primary1:focus-visible {
      /* keep visual stable: no color change, no underline */
      outline: none;
      text-decoration: none;
      color: #ffffff;
      background: linear-gradient(180deg,var(--primary),#279046);
      box-shadow: 0 10px 26px rgba(46,139,87,0.14);
      transform: translateY(-1px);
    }
    /* remove default tap highlight on mobile where appropriate */
    .btn-primary1, .filter-btn { -webkit-tap-highlight-color: rgba(0,0,0,0); }
    .btn-ghost { 
      background: transparent; 
      border: 1.5px solid rgba(46,139,87,0.14); 
      color: var(--primary);
      padding: 8px 12px;
      border-radius:10px;
    }
    .btn-ghost:hover { 
      background: rgba(46,139,87,0.04);
      transform: translateY(-1px);
    }

    /* Filter buttons under the activities title */
    .filter-btn {
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:12px;
      background: #fff;
      color:var(--primary);
      border:1px solid rgba(46,139,87,0.12);
      font-weight:700;
      text-decoration:none;
      box-shadow: 0 6px 18px rgba(20,40,30,0.03);
      transition: all .18s ease;
      cursor:pointer;
    }
    .filter-btn:hover { 
      background: linear-gradient(180deg,#f1fbf4,#e6f7ef); 
      color: var(--primary);
      transform: translateY(-3px);
      box-shadow:0 10px 26px rgba(20,40,30,0.06);
      border-color: rgba(46,139,87,0.12);
    }
    .filter-btn.active { background: var(--primary); color:#fff; border-color: transparent; box-shadow:0 14px 40px rgba(46,139,87,0.12); }

  @media (max-width: 900px) {
    .nav-links { 
      order: 2; 
      width: 100%; 
      justify-content: center; 
      margin: 12px 0; 
      flex-wrap: wrap;
      gap: 12px;
    }
    .nav-right { 
      order: 3; 
      width: 100%; 
      justify-content: center; 
      margin-top: 12px;
      flex-wrap: wrap;
      gap: 16px;
    }
    .brand-wrap { 
      order: 1; 
      width: 100%; 
      justify-content: center; 
      gap: 12px; 
    }
    .brand-text { 
      font-size: 18px;
    }
    .points-badge {
      width: 100%;
      max-width: 200px;
    }
  }

  /* Main banner styling */
  .banner {
    position: relative;
    width: 100%;
    min-height: 450px;
    background: linear-gradient(45deg, var(--primary), #3aa668);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 40px 20px;
  }
  .banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
    opacity: 0.3;
    animation: moveBg 30s linear infinite;
  }
  @keyframes moveBg {
    0% { background-position: 0 0; }
    100% { background-position: 100px 100px; }
  }

  .banner-text {
    position: relative;
    background: rgba(255,255,255,0.95);
    padding: 50px 70px;
    border-radius: 24px;
    text-align: center;
    max-width: 800px;
    backdrop-filter: blur(10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    animation: fadeInUp 1s ease-out;
  }
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .banner-text h1 {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 20px;
    color: var(--text);
    line-height: 1.2;
    letter-spacing: -0.5px;
  }
  .banner-text p {
    font-size: 1.4rem;
    color: var(--text-light);
    line-height: 1.6;
    margin-bottom: 30px;
  }

  .container {
    max-width: 1200px;
    margin: 40px auto;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 12px 24px var(--shadow);
    padding: 40px;
        }
        .section-title {
            text-align: center;
            font-size: 2.8rem;
            font-weight: 800;
            margin: 40px 0 20px;
            color: var(--text);
            position: relative;
            padding-bottom: 20px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        .section-desc {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 50px;
            font-size: 1.2rem;
            line-height: 1.6;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px auto;
            padding: 20px;
            max-width: 1200px;
        }
        
        .feature-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 24px var(--shadow);
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(46,139,87,0.12);
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-card .icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--primary);
            background: var(--primary-light);
            border-radius: 16px;
            padding: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .feature-card.red .icon { background: #e74c3c; }
        .feature-card.pink .icon { background: #d252a1; }
        .feature-card.green .icon { background: #27ae60; }
        .feature-card.blue .icon { background: #3498db; }
        .feature-card h3 {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .feature-card p {
            color: #555;
            font-size: 15px;
        }
        .expenses-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .footer {
            background: #16a085;
            color: #fff;
            padding: 40px 0 0 0;
            margin-top: 60px;
        }
        .footer .footer-container {
            max-width: 1100px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .footer-col {
            flex: 1;
            min-width: 220px;
            margin: 0 20px;
        }
        .footer-col h4 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 18px;
        }
        .footer-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-col ul li {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .footer-bottom {
            text-align: center;
            color: #fff;
            font-size: 14px;
            margin-top: 20px;
        }

        /* small leaf badge left of header */
        .nav-leaf {
          display:inline-flex;
          align-items:center;
          justify-content:center;
          width:36px;
          height:36px;
          border-radius:50%;
          background:#2e8b57;
          margin-right:12px;
          flex-shrink:0;
        }
        .nav-leaf i { color:#fff; font-size:16px; line-height:1; }

        /* brand (logo + title + news badge) on single line */
        .brand-wrap { display:flex; align-items:center; gap:12px; flex-wrap:nowrap; white-space:nowrap; }
        .brand-wrap a, .brand-wrap span, .brand-text { display:inline-flex; align-items:center; }
        .brand-text {
          font-size:20px;
          line-height:1;
          font-weight:800;
          color:#2e8b57;
          letter-spacing:1px;
          margin-left:6px;
        }
        @media (max-width:480px) {
          .brand-text { display:none !important; }
        }

        @media (max-width: 900px) {
            .features-row { flex-direction: column; align-items: center; }
            .footer .footer-container { flex-direction: column; align-items: center; }
            .footer-col { margin-bottom: 30px; }
        }

      .nav-links{ display:flex; gap:28px; flex:1; justify-content:center; align-items:center; }
      .nav-links a{ padding:10px 14px; border-radius:8px; text-decoration:none; color:inherit; font-weight:700; }
      .nav-links a.manage-link{ border:1px dashed rgba(46,139,87,0.18); padding:6px 10px; color:var(--green); }
      @media (max-width:900px){ .nav-links{ gap:16px } }

      /* Slider: fixed height as requested */
#main-slider, #main-slider .flexslider { height:720px; max-width:1100px; margin:22px auto; }
#main-slider .slides li { height:720px; }
#main-slider .slides img { width:100%; height:720px; object-fit:cover; display:block; }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/flexslider.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet" />
    <link href="css/fancybox/jquery.fancybox.css" rel="stylesheet">
    <link href="css/jcarousel.css" rel="stylesheet" />
    <link href="css/flexslider.css" rel="stylesheet" />
    <link href="js/owl-carousel/owl.carousel.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />    <!-- Enhanced body styles: typography, card, hero and small UI polish -->
    <style>
      :root{
        --green:#2e8b57; --green-2:#279046; --muted:#50655a; --bg:#f6fbf7; --card:#ffffff; --shadow: 0 12px 30px rgba(20,40,30,0.06);
      }
      html,body{height:100%;}
      body{font-family:Inter, 'Segoe UI', Roboto, Arial, sans-serif;background:linear-gradient(180deg,#f8fbf8 0%,#f3f8f4 100%);color:var(--muted);-webkit-font-smoothing:antialiased;margin:0}

      /* hero / slider tweaks */
      #main-slider{max-width:1100px;margin:22px auto;border-radius:14px;overflow:hidden;box-shadow:var(--shadow); height:720px;} 
      /* slide images use fixed height to match container */
      #main-slider .slides img{width:100%;height:720px;object-fit:cover;display:block}
      .flex-caption .item_introtext{background:linear-gradient(180deg,rgba(0,0,0,0.35),rgba(0,0,0,0.12));padding:20px;border-radius:10px;color:#fff}
      .flex-caption .item_introtext strong{font-size:20px;display:block;margin-bottom:6px}

      /* container / sections */
      .container{max-width:1100px;margin:28px auto;padding:26px;background:transparent}
      .section-title{font-family:Inter,Arial;font-size:32px;color:var(--green);letter-spacing:0.6px;margin-bottom:6px}
      .section-desc{color:#6b7a72;margin-bottom:18px}

      /* events grid and card */
      .events-list{display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:20px}
      @media(max-width:980px){.events-list{grid-template-columns:1fr}}

      .event-card{background:var(--card);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 24px rgba(15,40,30,0.04);transition:transform .18s ease,box-shadow .18s ease;border:1px solid rgba(10,20,15,0.03)}
      .event-card:hover{transform:translateY(-8px);box-shadow:0 18px 50px rgba(15,40,30,0.08)}
      .event-card .thumb{height:160px;background-size:cover;background-position:center}
      .event-card .body{padding:16px;display:flex;flex-direction:column;gap:10px}
      .event-card h3{margin:0;font-size:18px;color:var(--green)}
      .event-meta{font-size:13px;color:#6b7a72}
      .progress-wrap{display:flex;align-items:center;gap:12px}
      .progress-wrap .bar{flex:1;background:#f1f5f1;border-radius:12px;height:12px;overflow:hidden}
      .progress-wrap .bar > i{display:block;height:100%;background:linear-gradient(90deg,var(--green),var(--green-2));width:40%}

      .event-actions{display:flex;gap:10px;align-items:center;margin-top:auto}
      .btn-small{padding:8px 12px;border-radius:10px;font-weight:700;cursor:pointer;border:0}
      .btn-ghost{padding:8px 12px;border-radius:10px;background:transparent;border:1px solid rgba(46,139,87,0.12);color:var(--green)}
  .btn-primary1{padding:8px 12px;border-radius:10px;background:var(--green);color:#fff}

      /* Leaderboards polish */
      .leaderboard-item,.podium-item{transition:transform .12s,box-shadow .12s}
      .leaderboard-item:hover,.podium-item:hover{transform:translateY(-6px);box-shadow:var(--shadow)}

      /* modal improvements */
      #detail-modal > div{border-radius:12px;box-shadow:0 30px 80px rgba(10,30,20,0.18)}

      /* subtle helpers */
      small.muted{color:#7b8b82;font-size:13px}
    </style>

    <script>
      // small enhancements: lazy-load images and ensure slider images are not too tall
      document.addEventListener('DOMContentLoaded', function(){
        try{
          document.querySelectorAll('img').forEach(function(img){ if(!img.hasAttribute('loading')) img.setAttribute('loading','lazy'); });
          // cap slider height on small screens
          function capSlider(){ var s = document.getElementById('main-slider'); if(!s) return; if(window.innerWidth < 700) s.querySelectorAll('.slides img').forEach(i=>i.style.height='220px'); }
          capSlider(); window.addEventListener('resize', capSlider);

          // smooth-scroll to anchor if present (useful when links include ?view=..#upcoming-section)
          try{
            if (location.hash) {
              // delay slightly to allow server-rendered content to paint
              setTimeout(function(){
                var el = document.querySelector(location.hash);
                if (el && typeof el.scrollIntoView === 'function') {
                  el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
              }, 120);
            }
          } catch(e){}

        }catch(e){}
      });
    </script>
</head>
<body>
    <!-- Navbar -->
    <header class="site-header" role="banner">
  <style>
    /* Modern clean header */
    .site-header { background: linear-gradient(180deg, #ffffffcc, #f6fbf7); border-bottom: 1px solid rgba(46,139,87,0.06); backdrop-filter: blur(6px); }
    .site-header .header-inner { max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 18px; box-sizing:border-box; }
    .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit }
    .brand .leaf { width:48px; height:48px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(180deg,#2e8b57,#28a349); box-shadow:0 8px 24px rgba(46,139,87,0.12); }
    .brand .leaf i{ color:#fff; font-size:18px }
    .brand .title { font-weight:800; color:#16321f; font-size:20px; letter-spacing:1px }

    /* main nav */
    .main-nav { display:flex; gap:18px; align-items:center; justify-content:center; flex:1; }
    .main-nav a { color:#2b3d35; text-decoration:none; padding:8px 12px; border-radius:10px; font-weight:700; transition:background .12s, transform .08s }
    .main-nav a:hover, .main-nav a.active { background:#eef6f0; color:#2e8b57; transform:translateY(-1px) }

    /* actions */
    .header-actions { display:flex; gap:10px; align-items:center }
  .btn-ghost, .btn-primary1 { padding:8px 12px; border-radius:10px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px }
  .btn-primary1 { background:#2e8b57; color:#fff; border:none; box-shadow:0 8px 24px rgba(46,139,87,0.08) }
    .btn-ghost { background:transparent; border:1px solid rgba(46,139,87,0.12); color:#2e8b57 }

  /* header profile avatar (circle, border, shadow) */
  .profile-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none }
  .profile-link .user-avatar { width:40px; height:40px; border-radius:50%; overflow:hidden; flex-shrink:0; display:inline-block; border:4px solid rgba(46,139,87,0.12); box-shadow:0 6px 18px rgba(46,139,87,0.08); background:#fff }
  .profile-link .user-avatar img { width:100%; height:100%; object-fit:cover; display:block }

    /* responsive mobile menu */
    .mobile-toggle { display:none; background:transparent; border:0; font-size:20px; padding:8px; border-radius:8px }
    @media (max-width:900px) {
      .main-nav { display:none; position:absolute; top:64px; left:0; right:0; background:linear-gradient(#fff,#f9fff9); box-shadow:0 10px 30px rgba(20,40,30,0.06); padding:12px 18px; flex-direction:column; gap:8px }
      .main-nav.open { display:flex }
      .mobile-toggle { display:inline-flex }
      .header-actions { gap:8px }
    }
  </style>

  <!-- Ensure logout button never shows underline or color change on click/focus (override other rules) -->
  <style>
    a.btn-primary1, a.btn-primary1:visited, a.btn-primary1:active, a.btn-primary1:focus, a.btn-primary1:focus-visible,
    a.brand, a.brand:visited, a.brand:active, a.brand:focus, a.brand:focus-visible {
      color: #ffffff !important;
      text-decoration: none !important;
      outline: none !important;
      -webkit-appearance: none;
    }
    /* keep subtle visual pressed state but no underline/color swap */
    a.btn-primary1:active { transform: translateY(-1px) scale(1.01) !important; }
    /* Also prevent login/ghost buttons from changing color or showing underline when clicked/focused */
    a.btn-ghost, a.btn-ghost:visited, a.btn-ghost:active, a.btn-ghost:focus, a.btn-ghost:focus-visible,
    .btn-ghost, .btn-ghost:active, .btn-ghost:focus, .btn-ghost:focus-visible {
      color: var(--primary) !important;
      text-decoration: none !important;
      outline: none !important;
    }
    a.filter-btn, a.filter-btn:visited, a.filter-btn:active, a.filter-btn:focus, a.filter-btn:focus-visible {
      color: var(--primary) !important;
      text-decoration: none !important;
    }
  </style>
  <style>
    /* Prevent heading anchors and section links from getting underlines or color shifts on click/touch */
    h2 a, .section-title a, #upcoming-section a, #past-section a {
      color: inherit !important;
      text-decoration: none !important;
      -webkit-tap-highlight-color: rgba(0,0,0,0);
    }
    h2 a:active, h2 a:focus, h2 a:visited, .section-title a:active, .section-title a:focus, .section-title a:visited,
    #upcoming-section a:active, #upcoming-section a:focus, #upcoming-section a:visited,
    #past-section a:active, #past-section a:focus, #past-section a:visited {
      color: inherit !important;
      text-decoration: none !important;
    }
  </style>

  <div class="header-inner">
    <a class="brand" href="/Expense_tracker-main/Expense_Tracker/index.php" aria-label="Trang chủ">
      <span class="leaf"><i class="fas fa-leaf"></i></span>
      <span class="title">GREENSTEP</span>
    </a>

    <button class="mobile-toggle" aria-label="Mở menu" id="mobile-toggle">☰</button>

    <nav class="main-nav" role="menubar" aria-label="Primary navigation" id="main-nav">
      <a href="/Expense_tracker-main/Expense_Tracker/index.php" role="menuitem" class="active">TRANG CHỦ</a>
      <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" role="menuitem">ĐỔI THƯỞNG</a>
      <a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" role="menuitem">TIN TỨC XANH</a>
      <a href="/Expense_tracker-main/Expense_Tracker/news_web/contact.php" role="menuitem">LIÊN HỆ</a>
      <?php if (!empty($_SESSION['is_admin']) || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')): ?>
        <a href="/Expense_tracker-main/Expense_Tracker/admin/public/dashboard.php" class="manage-link" role="menuitem">QUẢN TRỊ</a>
      <?php endif; ?>
    </nav>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div style="display:flex;align-items:center;gap:12px">
        <div class="points-badge" aria-hidden="true"><?php echo number_format($userPoints ?? 0); ?> điểm</div>
        <a href="/Expense_tracker-main/Expense_Tracker/profile.php" class="profile-link" title="Hồ sơ">
            <span class="user-avatar" aria-hidden="true"><img src="<?php echo htmlspecialchars($userAvatarUrl); ?>" alt="Avatar"></span>
        </a>
        <a class="btn-primary1" href="/Expense_tracker-main/Expense_Tracker/logout.php">Đăng xuất</a>
      </div>
      <?php else: ?>
        <a class="btn-ghost" href="/Expense_tracker-main/Expense_Tracker/login.php">Đăng nhập</a>
  <a class="btn-primary1" href="/Expense_tracker-main/Expense_Tracker/register.php">Đăng ký</a>
      <?php endif; ?>
    </div>
  </div>

  <script>
    document.getElementById('mobile-toggle').addEventListener('click', function(){
      var nav = document.getElementById('main-nav'); nav.classList.toggle('open');
    });
  </script>
</header>
<main>
    <div id="main-slider" class="flexslider">
            <ul class="slides">
              <li>
                <img src="img/slides/D2115_69_889_1200.jpg" alt="" />
                <div class="flex-caption">
                   <div class="item_introtext"> 
                    <strong>Hành động nhỏ – Tương lai xanh!</strong>
                    <p>Mỗi thao tác của bạn hôm nay góp phần làm Trái Đất sạch hơn ngày mai.</p> </div>
                </div>
              </li>
              <li>
                <img src="img/slides/depositphotos_381170764-stock-photo-world-heart-day-environmental-protection.jpg" alt="" />
                <div class="flex-caption">
                     <div class="item_introtext"> 
                    <strong>Sống xanh hôm nay, giữ sạch ngày mai.</strong>
                    <p>Cùng lan tỏa thói quen xanh – vì một cộng đồng bền vững.</p> </div>
                </div>
              </li>
              <li>
                <img src="img/slides/f0242497-800px-wm.jpg" alt="" />
                <div class="flex-caption">
                     <div class="item_introtext"> 
                    <strong>Mỗi bước xanh – Một hành tinh bền vững.</strong>
                    <p>Từ hành động nhỏ nhất, chúng ta tạo nên thay đổi lớn nhất.</p> </div>
                </div>
              </li>
            </ul>
        </div>
    <!-- Hoạt động -->
    <div class="container">
        <div class="section-title">Hoạt động tình nguyện</div>

    <!-- quick filters: switch between upcoming / past (uses ?view=upcoming or ?view=past) -->
    <?php $currView = isset($_GET['view']) ? trim((string)$_GET['view']) : ''; ?>
    <div style="display:flex;gap:12px;align-items:center;margin:12px 0 20px">
      <a href="?view=upcoming#upcoming-section" class="filter-btn <?php echo ($currView === 'upcoming') ? 'active' : ''; ?>">Sắp & đang diễn ra</a>
      <a href="?view=past#past-section" class="filter-btn <?php echo ($currView === 'past') ? 'active' : ''; ?>">Đã diễn ra</a>
    </div>

<?php
// đảm bảo có kết nối PDO (nếu chưa)
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
    elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';
    // nếu db.php chỉ cung cấp $dsn/$username/$password
    if (!isset($pdo) && isset($dsn, $username, $password)) {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// lấy 5 hoạt động mới nhất
$events = [];
try {
    if (isset($pdo)) {
        // safe mapping: chỉ SELECT các cột thực sự tồn tại để tránh 'Unknown column'
$cols = $pdo->query("SHOW COLUMNS FROM `events`")->fetchAll(PDO::FETCH_COLUMN);
$has = function($n) use ($cols){ return in_array($n,$cols,true); };

$select = [];
$select[] = $has('id') ? '`id` AS id' : 'NULL AS id';
$select[] = $has('title') ? '`title` AS title' : ($has('event_name')? '`event_name` AS title' : 'NULL AS title');
// Ensure we get the most accurate start time
$select[] = $has('date') ? '`date` AS date' : ($has('event_date')? '`event_date` AS date' : ($has('start_date') ? '`start_date` AS date' : 'NULL AS date'));
$select[] = $has('location') ? '`location` AS location' : ($has('address') ? '`address` AS location' : 'NULL AS location');
$select[] = $has('participants') ? '`participants` AS participants' : 'NULL AS participants';
$select[] = $has('current_participants') ? '`current_participants` AS current_participants' : ($has('signed_up') ? '`signed_up` AS current_participants' : 'NULL AS current_participants');
$select[] = $has('points') ? '`points` AS points' : ($has('reward_points') ? '`reward_points` AS points' : '0 AS points');
$select[] = $has('image') ? '`image` AS image' : ($has('photo') ? '`photo` AS image' : ($has('cover') ? '`cover` AS image' : 'NULL AS image'));
$select[] = $has('description') ? '`description` AS description' : ($has('event_description') ? '`event_description` AS description' : 'NULL AS description');
// Always include end_date for proper status calculation
$select[] = $has('end_date') ? '`end_date` AS end_date' : 'NULL AS end_date';

$stmt = $pdo->query("SELECT ".implode(', ',$select)." FROM `events` ORDER BY `id` DESC LIMIT 10");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $events = [];
}
?>

<?php
$upcomingOrOngoing = [];
$recentPast = [];
date_default_timezone_set('Asia/Ho_Chi_Minh');
$now = time();

foreach ($events as $row) {
    $time = $row['event_date'] ?? $row['date'] ?? $row['start_date'] ?? '';
    $eventDate = false;
    if ($time && preg_match('/^(\\d{4}-\\d{2}-\\d{2})/', $time, $m)) {
        $eventDate = $m[1];
    } elseif ($time) {
        $eventDate = date('Y-m-d', strtotime($time));
    }
    
  if ($eventDate) {
    // Use datetime-aware status calculation (start datetime and optional end datetime)
    // Prefer the original $time (which may include time) and an end_date column if present.
    $startRaw = $time;
    $endRaw = $row['end_date'] ?? null;

    // Compute status using calculateEventStatus so classification matches display logic
    $status = calculateEventStatus($startRaw, $endRaw);

    // Determine event start timestamp for day-difference check (fallback to date-only)
    $eventTime = strtotime($startRaw ?: $eventDate);

    // Nếu sự kiện đã diễn ra và trong vòng 15 ngày gần đây
    if ($status['status'] === 'Đã diễn ra') {
      $daysDiff = ($now - $eventTime) / 86400;
      if ($daysDiff <= 15 && $daysDiff >= 0) {
        $recentPast[] = $row;
      }
    } else {
      // Nếu sự kiện sắp diễn ra hoặc đang diễn ra
      $upcomingOrOngoing[] = $row;
    }
  }
        }
 


    // view selector: allow linking to only upcoming or only past via ?view=upcoming or ?view=past
    $view = isset($_GET['view']) ? trim((string)$_GET['view']) : '';
    if (!in_array($view, ['upcoming', 'past'], true)) $view = '';
    $showUpcoming = ($view === '' || $view === 'upcoming');
    $showPast = ($view === '' || $view === 'past');
    ?>
      <?php if ($showUpcoming): ?>
      <div style="margin-bottom:38px" id="upcoming-section">
        <h2 style="font-size:22px;color:#2e8b57;margin-bottom:12px"><a href="?view=upcoming#upcoming-section" style="color:inherit;text-decoration:none">Sắp và đang diễn ra</a></h2>
        <div class="events-list">
    <?php if (empty($upcomingOrOngoing)): ?>
            <div style="grid-column:1/-1;padding:18px;background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.04)">Không có hoạt động sắp hoặc đang diễn ra.</div>
        <?php else: foreach ($upcomingOrOngoing as $row): ?>
            <?php
            $title = $row['event_name'] ?? $row['title'] ?? 'Hoạt động';
    $time = $row['event_date'] ?? $row['date'] ?? $row['start_date'] ?? '';
    $address = $row['address'] ?? $row['location'] ?? '';
    $total = isset($row['participants']) ? (int)$row['participants'] : (int)($row['attendees'] ?? 0);
    $points = isset($row['points']) ? (int)$row['points'] : (int)($row['reward_points'] ?? 0);
    $current = isset($row['current_participants']) ? (int)$row['current_participants'] : (int)($row['signed_up'] ?? floor(($total?:1)*0.7));
    if ($total <= 0) $total = 70;
    $pct = min(100, max(0, round($current / $total * 100)));

    // Tính trạng thái hoạt động dựa trên thời gian bắt đầu và kết thúc
    $statusText = '';
    $statusColor = '';
    if ($time) {
        $startDateTime = $time;
        $endDateTime = $row['end_date'] ?? null;
        
        // Tính toán trạng thái chính xác dựa trên thời gian
        $status = calculateEventStatus($startDateTime, $endDateTime);
        $statusText = $status['status'];
        $statusColor = $status['color'];
        $minutesToStart = $status['minutes'];
        
        // Debug log
        error_log(sprintf(
            "Event ID %d render: start=%s, end=%s, computed_status=%s",
            $row['id'] ?? 0,
            $startDateTime,
            $endDateTime ?? 'null',
            $statusText
        ));
        
          // Không hiển thị số phút nữa
          // if ($statusText === 'Sắp diễn ra' && $minutesToStart > 0) {
          //     $statusText .= ' (' . $minutesToStart . ' phút)';
          // }
        
          // Debug info nếu cần
          error_log(sprintf(
              "Event ID %d status: text=%s, color=%s, minutes=%d, start=%s, end=%s",
              $row['id'] ?? 0,
              $statusText,
              $statusColor,
              $minutesToStart,
              $time,
              $endTime ?? 'null'
          ));
      // Inline debug visible on the page when ?debug_events=1 is present
      if (isset($_GET['debug_events']) && $_GET['debug_events'] == '1') {
        $dbgStart = 'invalid';
        $dbgEnd = 'null';
        try { $d1 = new DateTime($time); $dbgStart = $d1->format('Y-m-d H:i:s'); } catch (Exception $e) {}
        if (!empty($endTime)) { try { $d2 = new DateTime($endTime); $dbgEnd = $d2->format('Y-m-d H:i:s'); } catch (Exception $e) { $dbgEnd = 'invalid'; } }
        else { $dbgEnd = '(fallback +2h)'; }
        $dbgNow = (new DateTime())->format('Y-m-d H:i:s');
        echo "<div style=\"margin-top:8px;padding:8px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;color:#856404;font-size:13px;\">";
        echo "<strong>DEBUG:</strong> start={$dbgStart}, end={$dbgEnd}, now={$dbgNow}, computed={$statusText}";
        echo "</div>";
      }
    }
    ?>
    <?php
    // kiểm tra user đã đăng ký event này chưa & lấy current/total
    $registered = false;
    $current = 0;
    $total = 0;
    if (!empty($row['id'])) {
        try {
            // lấy tổng (nếu có cột participants/attendees/total)
            $cols = isset($pdo) ? $pdo->query("SHOW COLUMNS FROM `events`")->fetchAll(PDO::FETCH_COLUMN) : [];
            $has = function($n) use ($cols){ return in_array($n,$cols,true); };
            $colTotal = $has('participants') ? 'participants' : ($has('attendees') ? 'attendees' : ($has('total') ? 'total' : null));
            if ($colTotal && isset($pdo)) {
                $st = $pdo->prepare("SELECT `$colTotal` FROM `events` WHERE `id` = ? LIMIT 1");
                $st->execute([(int)$row['id']]);
                $total = (int)$st->fetchColumn();
            }

      // đếm số đăng ký thực tế từ bảng đăng ký — hỗ trợ nhiều tên bảng (event_registrations, registrations)
      if (isset($pdo)) {
        try {
          $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
          $regTable = null;
          if (in_array('event_registrations', $tables, true)) $regTable = 'event_registrations';
          elseif (in_array('registrations', $tables, true)) $regTable = 'registrations';
          elseif (in_array('event_users', $tables, true)) $regTable = 'event_users';

          if ($regTable) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM `$regTable` WHERE event_id = ?");
            $s->execute([(int)$row['id']]);
            $current = (int)$s->fetchColumn();
          }
        } catch (Throwable $e) {
          // ignore and keep defaults
        }
      }

      // kiểm tra user đã đăng ký chưa
      if (isset($pdo) && isset($_SESSION['user_id'])) {
        try {
          if (!isset($regTable)) {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('event_registrations', $tables, true)) $regTable = 'event_registrations';
            elseif (in_array('registrations', $tables, true)) $regTable = 'registrations';
            elseif (in_array('event_users', $tables, true)) $regTable = 'event_users';
          }
          if ($regTable) {
            $r = $pdo->prepare("SELECT COUNT(*) FROM `$regTable` WHERE event_id = ? AND user_id = ?");
            $r->execute([(int)$row['id'], $_SESSION['user_id']]);
            $registered = $r->fetchColumn() > 0;
          }
        } catch (Throwable $e) {
          // ignore and keep defaults
        }
      }
        } catch (Throwable $e) {
            // ignore, giữ giá trị mặc định
        }
    }

    $pct = $total>0 ? min(100, max(0, round($current / $total * 100))) : ($current>0 ? 100 : 0);
    ?>
  <article class="event-card" style="background:#fff;padding:16px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.04);">
    <h3 style="margin:0 0 8px;font-size:18px;color:#2e8b57"><?php echo htmlspecialchars($row['title'] ?? $row['event_name'] ?? ''); ?></h3>
    <div style="font-size:14px;color:#666;margin-bottom:8px;"><?php echo htmlspecialchars($row['date'] ?? $row['event_date'] ?? ''); ?></div>
    <div style="font-size:14px;color:#666;margin-bottom:12px;"><?php echo htmlspecialchars($address ?: 'Địa điểm cập nhật'); ?></div>

    <div style="margin-bottom:10px;">
      <?php if ($statusText): ?>
        <span style="display:inline-block;padding:4px 12px;border-radius:12px;font-weight:500;color:#fff;background:<?php echo $statusColor; ?>;min-width:100px;text-align:center">
          <?php echo $statusText; ?>
        </span>
      <?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <div style="flex:1;background:#f1f5f1;border-radius:12px;height:12px;overflow:hidden;">
        <div data-progress-bar style="height:100%;background:#16a085;width:<?php echo (int)$pct; ?>%" data-fallback-total="<?php echo (int)$total; ?>"></div>
      </div>
      <div data-progress-label style="min-width:86px;font-size:14px;color:#333;"><?php echo $current . '/' . ($total>0 ? $total : $current); ?> người</div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center">
      <div style="color:#2e8b57;font-weight:700"><?php echo number_format((int)$points); ?> Điểm</div>
      <div>
    <?php
      // compute a simple status key so JS and logic don't break when we append minutes to the human text
      $statusKey = '';
      if (!empty($statusText)) {
        if (mb_strpos($statusText, 'Sắp diễn ra') === 0) $statusKey = 'upcoming';
        elseif ($statusText === 'Đang diễn ra') $statusKey = 'ongoing';
        elseif ($statusText === 'Đã diễn ra') $statusKey = 'past';
      }
    ?>
    <button
          type="button"
          class="detail-btn"
          data-event-id="<?php echo (int)$row['id']; ?>"
          data-registered="<?php echo $registered ? 1 : 0; ?>"
          data-title="<?php echo htmlspecialchars($row['title'] ?? $row['event_name'] ?? '', ENT_QUOTES); ?>"
          data-image="<?php echo htmlspecialchars($row['image'] ?? $row['photo'] ?? $row['cover'] ?? 'img/default-event.jpg', ENT_QUOTES); ?>"
          data-time="<?php echo htmlspecialchars($row['date'] ?? $row['event_date'] ?? '', ENT_QUOTES); ?>"
          data-address="<?php echo htmlspecialchars($address, ENT_QUOTES); ?>"
          data-current="<?php echo (int)$current; ?>"
          data-total="<?php echo (int)$total; ?>"
          data-points="<?php echo (int)$points; ?>"
          data-desc="<?php echo htmlspecialchars($row['description'] ?? $row['event_description'] ?? '', ENT_QUOTES); ?>"
          data-status="<?php echo $statusText; ?>"
          data-status-key="<?php echo $statusKey; ?>"
          data-user-id="<?php echo $userId ? (int)$userId : ''; ?>"
          style="background:#2e8b57;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer;margin-right:8px"
    >Chi tiết</button>

    <?php if ($statusKey === 'ongoing' && $registered): ?>
      <button class="qr-btn" data-event-id="<?php echo (int)$row['id']; ?>" data-user-id="<?php echo $userId ? (int)$userId : ''; ?>" style="margin-left:8px;background:#1e90ff;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer">QR Check-in</button>
    <?php endif; ?>

  <?php if ($statusKey === 'upcoming'): // Chỉ hiện nút đăng ký cho hoạt động sắp diễn ra ?>
      <?php if ($registered): ?>
        <button class="register-btn" data-event-id="<?php echo (int)$row['id']; ?>" data-registered="1" style="margin-left:8px;background:#d9534f!important;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer">Hủy tham gia</button>
      <?php else: ?>
        <button class="register-btn" data-event-id="<?php echo (int)$row['id']; ?>" data-registered="0" style="margin-left:8px;background:#16a085;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer">Đăng ký tham gia</button>
      <?php endif; ?>
    <?php endif; ?>
      </div>
    </div>
  </article>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Mục 2: Đã diễn ra 15 ngày gần nhất -->
<?php if ($showPast): ?>
<div id="past-section">
  <h2 style="font-size:22px;color:#888;margin-bottom:12px"><a href="?view=past#past-section" style="color:inherit;text-decoration:none">Đã diễn ra (15 ngày gần nhất)</a></h2>
  <div class="events-list">
    <?php if (empty($recentPast)): ?>
            <div style="grid-column:1/-1;padding:18px;background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.04)">Không có hoạt động đã diễn ra trong 15 ngày gần nhất.</div>
        <?php else: foreach ($recentPast as $row): ?>
            <?php
            $title = $row['event_name'] ?? $row['title'] ?? 'Hoạt động';
    $time = $row['event_date'] ?? $row['date'] ?? $row['start_date'] ?? '';
    $address = $row['address'] ?? $row['location'] ?? '';
    $total = isset($row['participants']) ? (int)$row['participants'] : (int)($row['attendees'] ?? 0);
    $points = isset($row['points']) ? (int)$row['points'] : (int)($row['reward_points'] ?? 0);
    $current = isset($row['current_participants']) ? (int)$row['current_participants'] : (int)($row['signed_up'] ?? floor(($total?:1)*0.7));
    if ($total <= 0) $total = 70;
    $pct = min(100, max(0, round($current / $total * 100)));

    // Tính trạng thái hoạt động
    $statusText = '';
    $statusColor = '';
    if ($time) {
      // Sử dụng hàm determineEventStatus để xác định trạng thái
      date_default_timezone_set('Asia/Ho_Chi_Minh');
      $status = determineEventStatus($time);
      $statusText = $status['status'];
      $statusColor = $status['color'];
    }
    ?>
    <?php
    // kiểm tra user đã đăng ký event này chưa & lấy current/total
    $registered = false;
    $current = 0;
    $total = 0;
    if (!empty($row['id'])) {
        try {
            // lấy tổng (nếu có cột participants/attendees/total)
            $cols = isset($pdo) ? $pdo->query("SHOW COLUMNS FROM `events`")->fetchAll(PDO::FETCH_COLUMN) : [];
            $has = function($n) use ($cols){ return in_array($n,$cols,true); };
            $colTotal = $has('participants') ? 'participants' : ($has('attendees') ? 'attendees' : ($has('total') ? 'total' : null));
            if ($colTotal && isset($pdo)) {
                $st = $pdo->prepare("SELECT `$colTotal` FROM `events` WHERE `id` = ? LIMIT 1");
                $st->execute([(int)$row['id']]);
                $total = (int)$st->fetchColumn();
            }

            // đếm số đăng ký thực tế từ bảng registrations
            if (isset($pdo)) {
                $s = $pdo->prepare("SELECT COUNT(*) FROM `registrations` WHERE event_id = ?");
                $s->execute([(int)$row['id']]);
                $current = (int)$s->fetchColumn();
            }

            // kiểm tra user đã đăng ký chưa
            if (isset($pdo) && isset($_SESSION['user_id'])) {
                $r = $pdo->prepare("SELECT COUNT(*) FROM `registrations` WHERE event_id = ? AND user_id = ?");
                $r->execute([(int)$row['id'], $_SESSION['user_id']]);
                $registered = $r->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
            // ignore, giữ giá trị mặc định
        }
    }

    $pct = $total>0 ? min(100, max(0, round($current / $total * 100))) : ($current>0 ? 100 : 0);
    ?>
  <article class="event-card" style="background:#fff;padding:16px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.04);">
    <h3 style="margin:0 0 8px;font-size:18px;color:#2e8b57"><?php echo htmlspecialchars($row['title'] ?? $row['event_name'] ?? ''); ?></h3>
    <div style="font-size:14px;color:#666;margin-bottom:8px;"><?php echo htmlspecialchars($row['date'] ?? $row['event_date'] ?? ''); ?></div>
    <div style="font-size:14px;color:#666;margin-bottom:12px;"><?php echo htmlspecialchars($address ?: 'Địa điểm cập nhật'); ?></div>

    <div style="margin-bottom:10px;">
      <?php if ($statusText): ?>
        <span style="display:inline-block;padding:4px 12px;border-radius:12px;font-weight:500;color:#fff;background:<?php echo $statusColor; ?>;min-width:100px;text-align:center">
          <?php echo $statusText; ?>
        </span>
      <?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <div style="flex:1;background:#f1f5f1;border-radius:12px;height:12px;overflow:hidden;">
        <div data-progress-bar style="height:100%;background:#16a085;width:<?php echo (int)$pct; ?>%" data-fallback-total="<?php echo (int)$total; ?>"></div>
      </div>
      <div data-progress-label style="min-width:86px;font-size:14px;color:#333;"><?php echo $current . '/' . ($total>0 ? $total : $current); ?> người</div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center">
      <div style="color:#2e8b57;font-weight:700"><?php echo number_format((int)$points); ?> Điểm</div>
      <div>
        <button
          type="button"
          class="detail-btn"
          data-event-id="<?php echo (int)$row['id']; ?>"
          data-registered="<?php echo $registered ? 1 : 0; ?>"
          data-title="<?php echo htmlspecialchars($row['title'] ?? $row['event_name'] ?? '', ENT_QUOTES); ?>"
          data-image="<?php echo htmlspecialchars($row['image'] ?? $row['photo'] ?? $row['cover'] ?? 'img/default-event.jpg', ENT_QUOTES); ?>"
          data-time="<?php echo htmlspecialchars($row['date'] ?? $row['event_date'] ?? '', ENT_QUOTES); ?>"
          data-address="<?php echo htmlspecialchars($address, ENT_QUOTES); ?>"
          data-current="<?php echo (int)$current; ?>"
          data-total="<?php echo (int)$total; ?>"
          data-points="<?php echo (int)$points; ?>"
          data-desc="<?php echo htmlspecialchars($row['description'] ?? $row['event_description'] ?? '', ENT_QUOTES); ?>"
          data-status="<?php echo $statusText; ?>"
          style="background:#2e8b57;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer;margin-right:8px"
        >Chi tiết</button>

        <?php if ($statusText === 'Sắp diễn ra'): // Chỉ hiện nút đăng ký cho hoạt động sắp diễn ra ?>
            <?php if ($registered): ?>
                <button class="register-btn" data-event-id="<?php echo (int)$row['id']; ?>" data-registered="1" style="margin-left:8px;background:#d9534f!important;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer">Hủy tham gia</button>
            <?php else: ?>
                <button class="register-btn" data-event-id="<?php echo (int)$row['id']; ?>" data-registered="0" style="margin-left:8px;background:#16a085;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer">Đăng ký tham gia</button>
            <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </article>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Leaderboards Row: coin + participation side-by-side -->
<?php
// Ensure $pdo exists
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/admin/config/db.php')) include_once __DIR__ . '/admin/config/db.php';
    elseif (file_exists(__DIR__ . '/config/db.php')) include_once __DIR__ . '/config/db.php';
}

// Prepare $leaderboard reliably: try to use users.coins if available, otherwise derive from registrations
$leaderboard = [];
try {
    if (!isset($pdo)) throw new Exception('DB not available');

    // detect users-like table
    $possibleUserTables = ['users','user','members','accounts'];
    $usersTable = null;
    foreach ($possibleUserTables as $t) {
        $found = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $t . "'")->fetchColumn();
        if ($found) { $usersTable = $t; break; }
    }

    // detect registrations table
    $hasRegistrations = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations'")->fetchColumn();

    if ($usersTable) {
        $cols = $pdo->query("SHOW COLUMNS FROM `" . $usersTable . "`")->fetchAll(PDO::FETCH_COLUMN);
        $pick = function(array $names) use ($cols){ foreach ($names as $n) if (in_array($n,$cols,true)) return $n; return null; };
        $idCol = $pick(['id','user_id']) ?? $cols[0] ?? 'id';
        $nameCol = $pick(['name','username','full_name','display_name','user_name','email']) ?? $idCol;
        $coinCol = $pick(['coins','coin','balance','points','reward_points','point_balance','score']);
        $avatarCol = $pick(['avatar','user_avatar','photo','profile_pic']);

        if ($coinCol) {
            $sel = "`".$idCol."` AS id, `".$nameCol."` AS name, `".$coinCol."` AS coins";
            if ($avatarCol) $sel .= ", `".$avatarCol."` AS avatar";
            $sql = "SELECT " . $sel . " FROM `".$usersTable."` ORDER BY `".$coinCol."` DESC LIMIT 10";
            $leaderboard = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // fallback: if registrations exist, use count of registrations as a proxy for coins
            if ($hasRegistrations) {
                $avatarSelect = $avatarCol ? "u.`$avatarCol` AS avatar, " : '';
                $sql = "SELECT " . $avatarSelect . "u.`".$idCol."` AS id, COALESCE(u.`".$nameCol."`, CONCAT('User ',u.`".$idCol."`)) AS name, COUNT(r.user_id) AS coins " .
                       "FROM registrations r LEFT JOIN `".$usersTable."` u ON u.`".$idCol."` = r.user_id GROUP BY r.user_id ORDER BY coins DESC LIMIT 10";
                $leaderboard = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // no data source
                $leaderboard = [];
            }
        }
    } else {
        // no users table: try to derive top users from registrations only
        if ($hasRegistrations) {
            $rows = $pdo->query("SELECT r.user_id AS id, COUNT(*) AS coins FROM registrations r GROUP BY r.user_id ORDER BY coins DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $rw) $leaderboard[] = ['id'=>$rw['id'],'name'=>'User '.$rw['id'],'coins'=>$rw['coins']];
        }
    }
} catch (Throwable $e) {
    error_log('Leaderboard error: ' . $e->getMessage());
    $leaderboard = [];
}

// Participant leaderboard (no change, but ensure users table detection same as above)
$participantLeaderboard = [];
try {
    $hasReg = isset($pdo) && $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations'")->fetchColumn();
    if ($hasReg) {
        if (isset($usersTable) && $usersTable) {
            $cols = $pdo->query("SHOW COLUMNS FROM `".$usersTable."`")->fetchAll(PDO::FETCH_COLUMN);
            $pick = function(array $names) use ($cols){ foreach ($names as $n) if (in_array($n,$cols,true)) return $n; return null; };
            $nameCol = $pick(['name','username','full_name','display_name','user_name','email']) ?? 'id';
            $avatarCol = $pick(['avatar','user_avatar','photo','profile_pic']);

       $avatarSelect = $avatarCol ? "u.`$avatarCol` AS avatar, " : '';
       // Use users LEFT JOIN registrations so users with 0 participations are included
       $sql = "SELECT " . $avatarSelect . "u.`".$idCol."` AS id, COALESCE(u.`".$nameCol."`, CONCAT('User ',u.`".$idCol."`)) AS name, COUNT(r.user_id) AS participations " .
         "FROM `".$usersTable."` u LEFT JOIN registrations r ON u.`".$idCol."` = r.user_id GROUP BY u.`".$idCol."` ORDER BY participations DESC LIMIT 10";
       $participantLeaderboard = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT r.user_id AS id, COUNT(*) AS participations FROM registrations r GROUP BY r.user_id ORDER BY participations DESC LIMIT 10";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $participantLeaderboard[] = ['id'=>$r['id'],'name'=>'User '.$r['id'],'participations'=>$r['participations']];
        }
    }
} catch (Throwable $e) {
    error_log('Participant leaderboard error: ' . $e->getMessage());
    $participantLeaderboard = [];
}

// Ensure the avatar resolver is defined before rendering leaderboards
if (!function_exists('resolve_avatar_path')) {
    function resolve_avatar_path($val) {
        $default = '/Expense_tracker-main/Expense_Tracker/news_web/img/avatar-default.png';
        if (empty($val)) return $default;
        $v = trim((string)$val);
        if ($v === '') return $default;
        // full URL
        if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) return $v;
        // already absolute web path
        if (isset($v[0]) && $v[0] === '/') return $v;
        // contains uploads path already (relative)
        if (strpos($v, 'uploads/users/') !== false) return '/' . ltrim($v, '/');
        // treat as filename stored in uploads/users/
        $local = __DIR__ . '/uploads/users/' . $v;
        if (file_exists($local)) return '/Expense_tracker-main/Expense_Tracker/uploads/users/' . rawurlencode($v);
        // try if stored with project path fragment
        if (strpos($v, 'Expense_tracker-main') !== false) return (strpos($v, '/') === 0) ? $v : '/' . ltrim($v, '/');
        return $default;
    }
}
?>

<style>
.leaderboards-row{max-width:1100px;margin:28px auto;display:flex;gap:18px;align-items:flex-start}
@media(max-width:920px){.leaderboards-row{flex-direction:column}}
.leaderboard,.podium{flex:1;background:#fff;padding:18px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.04)}
.leaderboard h2,.podium h2{margin:0 0 8px;font-size:18px;color:#2e8b57}
.leaderboard-list,.podium-list{display:flex;flex-direction:column;gap:10px;margin-top:12px}
.leaderboard-item,.podium-item{display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;border:1px solid rgba(0,0,0,0.04);background:linear-gradient(180deg,#fff,#fbfffb);text-decoration:none;color:inherit}
.leaderboard-avatar,.podium-avatar{width:56px;height:56px;border-radius:50%;overflow:hidden;flex-shrink:0;border:4px solid transparent}
.leaderboard-name,.podium-name{flex:1;min-width:0}
.leaderboard-score,.podium-count{font-weight:900;color:#2e8b57;min-width:96px;text-align:right}
/* podium/leaderboard special borders */
.leaderboard-item.rank-1 .leaderboard-avatar,.podium-item.rank-1 .podium-avatar{border-color:#f5c042;box-shadow:0 6px 18px rgba(245,196,66,0.12)}
.leaderboard-item.rank-2 .leaderboard-avatar,.podium-item.rank-2 .podium-avatar{border-color:#c0c0c0}
.leaderboard-item.rank-3 .leaderboard-avatar,.podium-item.rank-3 .podium-avatar{border-color:#cd7f32}
.crown{margin-right:8px;font-size:18px}
@media(max-width:720px){.leaderboard-avatar,.podium-avatar{width:48px;height:48px}}
</style>
<div class="section-title">Bảng xếp hạng</div>
<div class="leaderboards-row" role="region" aria-label="Leaderboards">
    <div class="leaderboard" aria-label="Bảng xếp hạng theo coin">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <h2>Bảng xếp hạng tình nguyện viên</h2>
            <small style="color:var(--muted);align-self:flex-end">Xếp theo số coin</small>
        </div>

        <div class="leaderboard-list">
            <?php if (!empty($leaderboard)): $rank=0; foreach ($leaderboard as $u): $rank++;
                $rankClass = in_array($rank, [1,2,3]) ? 'rank-'.$rank : '';
                $avatarUrl = resolve_avatar_path($u['avatar'] ?? ($u['user_avatar'] ?? ($u['photo'] ?? '')));
                $displayName = htmlspecialchars($u['name'] ?? ('User ' . ($u['id'] ?? '')));
            ?>
            <a class="leaderboard-item <?php echo $rankClass;?>" href="/Expense_tracker-main/Expense_Tracker/profile.php?id=<?php echo urlencode($u['id']); ?>">
                <div class="leaderboard-avatar" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
                <div class="leaderboard-name">
                    <div style="display:flex;align-items:center;gap:8px;font-weight:800">
                        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo $displayName; ?></span>
                    </div>
                </div>
                <div class="leaderboard-score"><?php echo number_format((int)($u['coins'] ?? 0)); ?> <small style="font-size:12px;color:#6b7a6f">coin</small></div>
            </a>
            <?php endforeach; else: ?>
             <div style="color:var(--muted);padding:12px;border-radius:8px;border:1px solid rgba(0,0,0,0.04);">Chưa có dữ liệu xếp hạng.</div>
             <?php endif; ?>
         </div>
     </div>

     <div class="podium" aria-label="Bảng xếp hạng tham gia">
         <div style="display:flex;align-items:center;justify-content:space-between">
             <h2>Bảng xếp hạng số lần tham gia</h2>
             <small style="color:var(--muted);align-self:flex-end">tham gia hoạt động nhiều nhất</small>
         </div>

         <div class="podium-list">
            <?php if (!empty($participantLeaderboard)): $r=0; foreach ($participantLeaderboard as $u): $r++;
                $rankClass = in_array($r,[1,2,3]) ? 'rank-'.$r : '';
                $avatarUrl = resolve_avatar_path($u['avatar'] ?? ($u['user_avatar'] ?? ($u['photo'] ?? '')));
                $displayName = htmlspecialchars($u['name'] ?? ('User ' . ($u['id'] ?? '')));
            ?>
            <a class="podium-item <?php echo $rankClass;?>" href="/Expense_tracker-main/Expense_Tracker/profile.php?id=<?php echo urlencode($u['id']); ?>">
                <div class="podium-avatar" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
                <div class="podium-name">
                    <div style="display:flex;align-items:center;gap:8px;font-weight:800">
                        <?php if ($r === 1): ?><span class="crown" aria-hidden="true">👑</span><?php endif; ?>
                        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo $r . '. ' . $displayName; ?></span>
                    </div>
                </div>
                <div class="podium-count"><?php echo number_format((int)($u['participations'] ?? 0)); ?> <small style="font-size:12px;color:#6b7a6f">lần</small></div>
            </a>
            <?php endforeach; else: ?>
             <div style="color:var(--muted);padding:12px;border-radius:8px;border:1px solid rgba(0,0,0,0.04);background:#fff">Chưa có dữ liệu tham gia.</div>
             <?php endif; ?>
         </div>
     </div>
 </div>

<!-- tiếp tục modal đăng ký và script hiện có (không thay đổi) -->
    <?php
    // Helper function to debug event status
    function debugEventStatus($time, $registered) {
        $eventDate = false;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $time, $m)) {
            $eventDate = $m[1];
        } else {
            $eventDate = date('Y-m-d', strtotime($time));
        }
        $today = '2025-10-25';
        $eventTime = strtotime($eventDate);
        $todayTime = strtotime($today);
        $daysDiff = floor(($eventTime - $todayTime) / (60 * 60 * 24));
        $status = '';
        
        if (abs($daysDiff) <= 1) {
            $status = 'Đang diễn ra';
        } elseif ($daysDiff > 1) {
            $status = 'Sắp diễn ra';
        } else {
            $status = 'Đã diễn ra';
        }
        
        return [
            'event_date' => $eventDate,
            'today' => $today,
            'days_diff' => $daysDiff,
            'status' => $status,
            'registered' => $registered ? 'yes' : 'no',
            'should_show_qr' => ($status === 'Đang diễn ra' && $registered) ? 'yes' : 'no'
        ];
    }
    ?>
    <!-- Popup đăng ký -->
<div id="register-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:30px 30px 20px 30px;border-radius:20px;max-width:400px;width:100%;position:relative;">
        <span id="close-modal" style="position:absolute;top:10px;right:20px;font-size:28px;cursor:pointer;">&times;</span>
        <h3 style="text-align:center;margin-bottom:20px;">Đăng ký tham gia</h3>
        <form id="register-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="event_id" id="register-event-id" value="">
            <div style="margin-bottom:10px;">
                <input type="text" name="name" placeholder="Họ tên" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:10px;">
                <input type="number" name="age" placeholder="Tuổi" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:10px;">
                <input type="text" name="address" placeholder="Quê quán" required style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
            </div>
      <div style="margin-bottom:10px;">
        <label style="display:block;margin-bottom:6px;font-weight:700;">Tình trạng sức khỏe</label>
        <select name="health" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
          <option value="">Chọn tình trạng</option>
          <option value="Tốt">Tốt</option>
          <option value="Bình thường">Bình thường</option>
          <option value="Cần lưu ý">Cần lưu ý</option>
        </select>
      </div>
      <div style="margin-bottom:10px;">
        <input type="text" name="class_name" placeholder="Lớp" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
      </div>
      <div style="margin-bottom:10px;">
        <input type="text" name="school" placeholder="Trường đang học" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;">
      </div>
      <div style="margin-bottom:10px;">
        <label style="display:block;margin-bottom:6px;font-weight:700;">Ảnh căn cước công dân hoặc thẻ sinh viên:</label>
        <!-- Custom file picker: hidden input + styled label -->
        <div style="display:flex;gap:12px;align-items:center">
          <input type="file" id="id_image" name="id_image" accept="image/*" required style="display:none;">
          <label for="id_image" id="id_image_btn" style="display:inline-block;padding:8px 14px;border-radius:10px;background:transparent;border:2px dashed rgba(46,139,87,0.16);color:#2e8b57;font-weight:700;cursor:pointer;">Chọn ảnh</label>
          <span id="id_image_name" style="color:#666;font-size:13px">Chưa có ảnh nào được chọn</span>
        </div>
      </div>
            <button type="submit" style="background:#2e8b57;color:#fff;border:none;padding:10px 30px;border-radius:20px;font-size:18px;cursor:pointer;">OK</button>
        </form>
        <div id="register-success" style="display:none;text-align:center;color:#16a085;font-size:18px;margin-top:15px;">
            Đăng ký hoàn tất!
        </div>
    </div>
</div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var input = document.getElementById('id_image');
      var nameSpan = document.getElementById('id_image_name');
      if (input && nameSpan) {
        input.addEventListener('change', function(){
          if (this.files && this.files.length) {
            nameSpan.textContent = this.files[0].name;
          } else {
            nameSpan.textContent = 'Chưa có tệp nào được chọn';
          }
        });
      }
    });
    </script>

    <!-- QR Code Modal -->
<div id="qr-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:30px;border-radius:20px;max-width:400px;width:100%;position:relative;text-align:center;">
        <span id="close-qr-modal" style="position:absolute;top:10px;right:20px;font-size:28px;cursor:pointer;">&times;</span>
        <h3 style="text-align:center;margin-bottom:20px;">QR Code Check-in</h3>
        <div id="qr-code" style="margin:20px auto;width:200px;height:200px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">
            <!-- Use web-friendly absolute path; place the file in the project img/ folder -->
            <img id="qr-image" src="/Expense_tracker-main/Expense_Tracker/img/Rickrolling_QR_code.png" alt="QR Code" style="width:100%;height:100%;object-fit:contain;">
        </div>
        <p style="color:#666;margin:15px 0;">Quét mã QR để điểm danh tham gia sự kiện</p>
    </div>
</div><?php if (!empty($_SESSION['registration_success'])): ?>
  <div id="popup-success" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.12);padding:28px 24px;max-width:360px;text-align:center;">
      <div style="font-size:20px;color:#2e8b57;font-weight:700;margin-bottom:10px">Đăng ký thành công</div>
      <div style="font-size:15px;color:#333;margin-bottom:18px">Cảm ơn bạn đã đăng ký tham gia hoạt động.</div>
      <button id="closePopupBtn" style="background:#2e8b57;color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:15px;cursor:pointer">Đóng</button>
    </div>
  </div>
  <script>
    (function(){
      var p = document.getElementById('popup-success');
      var b = document.getElementById('closePopupBtn');
      if (b) b.addEventListener('click', function(){ if(p) p.style.display='none'; });
      setTimeout(function(){ if(p) p.style.display='none'; }, 3500);
    })();
  </script>
<?php unset($_SESSION['registration_success']); endif; ?>

<?php
// Detail modal (chèn nếu đã mất)
?>
<div id="detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:10050;align-items:center;justify-content:center;padding:30px;">
  <div style="width:100%;max-width:720px;background:#fff;border-radius:14px;overflow:hidden;position:relative;display:flex;flex-direction:column;max-height:90vh;">
    <button id="detail-close" style="position:absolute;right:12px;top:12px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#333">&times;</button>
    <div style="overflow:auto;padding:22px;">
      <div style="width:100%;height:320px;border-radius:10px;overflow:hidden;background:#f6f6f6;margin-bottom:16px;display:flex;align-items:center;justify-content:center;">
        <img id="detail-img" src="img/default-event.jpg" alt="Ảnh hoạt động" style="width:100%;height:100%;object-fit:cover;display:block;">
      </div>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;">
        <div style="flex:1">
          <h2 id="detail-title" style="margin:0 0 8px;font-size:22px;color:#111"></h2>
          <div id="detail-meta" style="color:#666;font-size:14px;margin-bottom:12px;display:flex;gap:12px;align-items:center;">
            <span id="detail-time" style="display:inline-flex;align-items:center;"><i class="fa fa-calendar" style="margin-right:8px;color:#2e8b57"></i><span></span></span>
            <span id="detail-address" style="display:inline-flex;align-items:center;color:#666;"><i class="fa fa-map-marker-alt" style="margin-right:8px;color:#999"></i><span></span></span>
          </div>

          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="flex:1;background:#f1f5f1;border-radius:12px;height:12px;overflow:hidden;">
              <div id="detail-bar" style="height:100%;background:#16a085;width:0%"></div>
            </div>
            <div id="detail-count" style="min-width:110px;color:#333;font-weight:700;text-align:right;font-size:14px"></div>
          </div>

          <div id="detail-points" style="color:#2e8b57;font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:8px;font-size:16px">
            <span style="display:inline-flex;align-items:center;background:#eaf6f0;border-radius:16px;padding:6px 10px;color:#2e8b57"><i class="fa fa-seedling" style="margin-right:8px"></i><span id="detail-points-val"></span></span>
          </div>

          <div id="detail-desc" style="color:#333;line-height:1.6;font-size:15px;white-space:pre-wrap;"></div>
        </div>
      </div>
    </div>

    <div style="border-top:1px solid rgba(0,0,0,0.06);padding:14px;display:flex;gap:12px;align-items:center;justify-content:space-between;">
      <div style="color:#666;font-size:14px"></div>
      <div style="display:flex;gap:10px">
        <button id="detail-register" style="background:#16a085;color:#fff;border:0;padding:12px 22px;border-radius:10px;font-weight:700;cursor:pointer">Đăng ký</button>
        <button id="detail-qr-btn" class="qr-btn" type="button" style="display:none;margin-left:6px;background:#2e8b57;color:#fff;border:0;padding:12px 16px;border-radius:10px;cursor:pointer">QR Check-in</button>
        <button id="detail-close-2" style="background:transparent;border:1px solid #ddd;padding:10px 18px;border-radius:10px;cursor:pointer">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  function $q(sel, ctx){ return (ctx||document).querySelector(sel); }
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.detail-btn');
    if (!btn) return;
    var modal = document.getElementById('detail-modal');
    if (!modal) return;

    var eid = btn.getAttribute('data-event-id');
    var title = btn.getAttribute('data-title') || '';
    var img = btn.getAttribute('data-image') || 'img/default-event.jpg';
    var time = btn.getAttribute('data-time') || '';
    var addr = btn.getAttribute('data-address')
    var current = btn.getAttribute('data-current') || 0;
    var total = btn.getAttribute('data-total') || 0;
    var points = btn.getAttribute('data-points') || 0;
    var desc = btn.getAttribute('data-desc') || '';
  var status = btn.getAttribute('data-status') || '';
  var statusKey = btn.getAttribute('data-status-key') || '';
    
    // store event id in modal
    modal.setAttribute('data-event-id', eid);

    // set modal content
    $q('#detail-title').innerText = title;
    // Normalize image path: if it's a bare filename or relative path without leading slash,
    // prefix with the project base so browsers can resolve it correctly.
    (function(){
      var resolved = img || 'img/default-event.jpg';
      if (resolved && !/^https?:\/\//i.test(resolved) && resolved.charAt(0) !== '/') {
        // prefix the project path so relative names stored in DB still resolve
        resolved = window.location.origin + '/Expense_tracker-main/Expense_Tracker/' + resolved;
      }
      $q('#detail-img').src = resolved;
    })();
    $q('#detail-time span').innerText = time;
    $q('#detail-address span').innerText = addr;
    $q('#detail-points-val').innerText = points;
    $q('#detail-desc').innerText = desc;

    // progress bar
    var bar = $q('#detail-bar');
    if (bar) {
      bar.style.width = Math.min(100, Math.max(0, (current / total) * 100)) + '%';
    }
    $q('#detail-count').innerText = current + '/' + total + ' người';

    // show/hide register button and set its state (registered/unregistered)
    var registerBtn = $q('#detail-register');
    if (registerBtn) {
      var registered = btn.getAttribute('data-registered') || '0';
  if (statusKey === 'upcoming') {
        registerBtn.style.display = 'inline-flex';
        // set label, color and data state
        if (registered === '1' || registered === 1) {
          registerBtn.textContent = 'Hủy tham gia';
          registerBtn.style.background = '#d9534f';
          registerBtn.style.color = '#fff';
          registerBtn.dataset.registered = '1';
        } else {
          registerBtn.textContent = 'Đăng ký';
          registerBtn.style.background = '#16a085';
          registerBtn.style.color = '#fff';
          registerBtn.dataset.registered = '0';
        }
      } else {
        registerBtn.style.display = 'none';
      }
    }

    // Show QR button for ongoing events if user is registered
    var qrBtn = $q('#detail-qr-btn');
    if (qrBtn) {
      var uid = btn.getAttribute('data-user-id');
      // set data attributes on the modal QR button so the separate handler can read them
      qrBtn.dataset.eventId = eid;
      qrBtn.dataset.userId = uid || '';
      if (statusKey === 'ongoing' && registered === '1' && uid) {
        qrBtn.style.display = 'inline-flex';
        // Create QR code URL for this event and user
        var checkinUrl = window.location.origin + '/Expense_tracker-main/Expense_Tracker/checkin.php?event_id=' + 
                        encodeURIComponent(eid) + '&user_id=' + encodeURIComponent(uid);
        qrBtn.dataset.qrUrl = checkinUrl;
      } else {
        qrBtn.style.display = 'none';
      }

      // Optional debug output when ?debug_qr=1 is present in URL
      try {
        if (window.location.search.indexOf('debug_qr=1') !== -1) {
          var dbg = $q('#detail-debug');
          if (!dbg) {
            dbg = document.createElement('div');
            dbg.id = 'detail-debug';
            dbg.style.cssText = 'margin-top:8px;font-size:12px;color:#333;border-top:1px dashed #eee;padding-top:8px';
            var meta = $q('#detail-meta');
            if (meta) meta.appendChild(dbg);
          }
          dbg.innerHTML = '<strong>DEBUG</strong><br>statusKey=' + statusKey + '<br>registered=' + registered + '<br>uid=' + (uid||'') + '<br>eid=' + eid + '<br>qrUrl=' + (checkinUrl||'') ;
        }
      } catch (e) { console && console.error && console.error(e); }
    }
    
    modal.style.display = 'flex';
    e.preventDefault();
  });

  // close detail modal
  var closeBtns = document.querySelectorAll('#detail-close, #detail-close-2');
  closeBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var modal = document.getElementById('detail-modal');
      if (modal) modal.style.display = 'none';
    });
  });

  // register button in detail modal: register or unregister depending on state
  document.getElementById('detail-register').addEventListener('click', function(){
    var eid = document.querySelector('#detail-modal').getAttribute('data-event-id');
    if (!eid) return;
    var state = this.dataset.registered || '0';
    if (state === '1') {
      // user already registered -> perform unregister via POST
      if (!confirm('Bạn có chắc muốn hủy tham gia?')) return;
      var f = document.createElement('form');
      f.method = 'POST';
      f.style.display = 'none';
      var a = document.createElement('input'); a.name = 'action'; a.value = 'unregister'; f.appendChild(a);
      var ev = document.createElement('input'); ev.name = 'event_id'; ev.value = eid; f.appendChild(ev);
      document.body.appendChild(f);
      f.submit();
      return;
    }

    // otherwise open register modal and set event id
    var modal = document.getElementById('register-modal');
    var hid = document.getElementById('register-event-id');
    if (hid) hid.value = eid;
    if (modal) {
      modal.style.display = 'flex';
      // hide detail modal when opening register modal
      document.getElementById('detail-modal').style.display = 'none';
    }
  });
})();
</script>
</main>
    <!-- QR Modal -->
    <div id="qr-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:10060;align-items:center;justify-content:center;">
      <div style="background:#fff;padding:24px;border-radius:12px;max-width:420px;width:100%;position:relative;text-align:center;">
        <button id="qr-close" style="position:absolute;right:12px;top:8px;border:0;background:transparent;font-size:22px;cursor:pointer;color:#333">&times;</button>
        <h3 style="margin:6px 0 12px;font-size:18px;color:#2e8b57">QR Check-in</h3>
        <div id="qr-container" style="width:300px;height:300px;margin:0 auto;background:#f6f6f6;border-radius:8px;overflow:hidden;">
          <img id="qr-code-image" src="..\Expense_tracker-main\Expense_Tracker\img\Rickrolling_QR_code.png" alt="QR Code" style="width:100%;height:100%;object-fit:contain;display:block;">
        </div>
        <div style="font-size:13px;color:#666;margin-top:12px">Quét mã QR này để điểm danh khi bạn đến địa điểm</div>
      </div>
    </div>

<?php
// Create checkins table if not exists
if (isset($pdo)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            checkin_time DATETIME NOT NULL,
            UNIQUE KEY unique_checkin (event_id, user_id)
        )");
    } catch (Exception $e) {
        // Ignore if table exists or other error
    }
}
?>
    <!-- Footer -->
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
  // mở modal đăng ký nếu data-registered="0", hoặc gửi POST để hủy nếu data-registered="1"
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.register-btn');
    if (!btn) return;
    var reg = btn.getAttribute('data-registered');
    var eid = btn.getAttribute('data-event-id');

    if (reg === '0') {
      // open register modal and set event id in hidden input
      var modal = document.getElementById('register-modal');
      var hid = document.getElementById('register-event-id');
      if (hid) hid.value = eid;
      if (modal) modal.style.display = 'flex';
      e.preventDefault();
      return;
    }

    if (reg === '1') {
      if (!confirm('Bạn có chắc muốn hủy tham gia?')) { e.preventDefault(); return; }
      // tạo form tạm để POST action=unregister
      var f = document.createElement('form');
      f.method = 'POST';
      f.style.display = 'none';
      var a = document.createElement('input'); a.name = 'action'; a.value = 'unregister'; f.appendChild(a);
      var ev = document.createElement('input'); ev.name = 'event_id'; ev.value = eid; f.appendChild(ev);
      document.body.appendChild(f);
      f.submit();
      e.preventDefault();
      return;
    }
  });

  // Xử lý nút QR check-in
  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.qr-btn');
    if (!btn) return;
    
    var eventId = btn.getAttribute('data-event-id');
    var userId = btn.getAttribute('data-user-id');
    
    var modal = document.getElementById('qr-modal');
    var qrDiv = document.getElementById('qr-code');
    
      if (modal && qrDiv) {
  // Try to show a local static QR image (the one you added). If it doesn't load,
  // fall back to a generated QR from Google Charts.
  var checkInData = { eventId: eventId, userId: userId, timestamp: Date.now() };
  var qrData = JSON.stringify(checkInData);
  var generatedQrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl=' + encodeURIComponent(qrData);
        var localQrPath = window.location.origin + '/Expense_tracker-main/Expense_Tracker/img/Rickrolling_QR_code.png';

        function showImage(url) {
          qrDiv.innerHTML = '<img src="' + url + '" alt="QR Code Check-in" style="width:200px;height:200px;object-fit:contain;">';
          var altImg = document.getElementById('qr-img') || document.getElementById('qr-image');
          if (altImg) altImg.src = url;
          modal.style.display = 'flex';
        }

        // Probe the local file first to avoid showing the broken-image icon
        var probe = new Image();
        probe.onload = function() { showImage(localQrPath); };
        probe.onerror = function() { showImage(generatedQrUrl); };
        probe.src = localQrPath + '?_=' + Date.now();
      }
    
    e.preventDefault();
  });

  // đóng modal register
  var close = document.getElementById('close-modal');
  if (close) close.addEventListener('click', function(){ document.getElementById('register-modal').style.display = 'none'; });

  // đóng modal QR
  var closeQR = document.getElementById('close-qr-modal');
  if (closeQR) closeQR.addEventListener('click', function(){ document.getElementById('qr-modal').style.display = 'none'; });

  // khi gửi form đăng ký, default submit sẽ POST lên index.php và server xử lý (xem phần PHP)
})();
</script>
</body>
</html>
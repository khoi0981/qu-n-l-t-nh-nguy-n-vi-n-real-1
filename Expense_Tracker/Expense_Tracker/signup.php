<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* tìm file cấu hình DB (an toàn với __DIR__) */
$dbFile = __DIR__ . '/src/config/db.php';
if (!file_exists($dbFile)) {
    $dbFile = __DIR__ . '/../src/config/db.php';
}
if (!file_exists($dbFile)) {
    die('Không tìm thấy cấu hình DB. Vui lòng kiểm tra src/config/db.php');
}
require_once $dbFile;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($username === '') $errors[] = 'Tên đăng nhập không được để trống.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Mật khẩu phải >= 6 ký tự.';
    if ($password !== $password2) $errors[] = 'Mật khẩu xác nhận không khớp.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                $stmt2 = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmt2->execute([$username]);
                if ($stmt2->fetch()) $errors[] = 'Tên đăng nhập đã tồn tại.';
                $stmt3 = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt3->execute([$email]);
                if ($stmt3->fetch()) $errors[] = 'Email đã được sử dụng.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)";
                $stmtIns = $pdo->prepare($sql);
                $ok = $stmtIns->execute([$username, $email, $hash, 'user', date('Y-m-d H:i:s')]);
                if ($ok) {
                    header('Location: /Expense_tracker-main/Expense_Tracker/login.php?registered=1');
                    exit;
                } else {
                    $errors[] = 'Không thể tạo tài khoản, thử lại sau.';
                }
            }
        } catch (Throwable $e) {
            // DEV: hiển thị lỗi trực tiếp để debug
            error_log($e->getMessage());
            $errors[] = 'Lỗi hệ thống: ' . htmlspecialchars($e->getMessage());
            // khi đã fix, thay lại:
            // error_log($e->getMessage()); $errors[] = 'Lỗi hệ thống, thử lại sau.';
        }
    }
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Đăng ký - GREENSTEP</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <style>
    :root{--green:#2e8b57;--bg:#f6fbf7}
    body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#e8f5e9,#f8f6fc);color:#16321f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .box{background:#fff;padding:28px;border-radius:12px;max-width:420px;width:100%;box-shadow:0 12px 40px rgba(30,60,40,0.06)}
    h1{margin:0 0 14px;color:var(--green);font-size:22px;text-align:center}
    .field{margin-bottom:12px}
    input[type="text"],input[type="email"],input[type="password"]{width:100%;padding:10px;border-radius:8px;border:1px solid #e6efe6;font-size:15px}
    .btn{width:100%;padding:10px;border-radius:8px;border:0;background:linear-gradient(90deg,var(--green),#28a349);color:#fff;font-weight:700;cursor:pointer}
    .note{font-size:13px;color:#4b5d4f;margin-top:10px;text-align:center}
    /* register-link (match login.php) */
    .register-link{ text-align:center; margin-top:12px; }
    .register-link a{ color:var(--green); font-weight:600; text-decoration:none; }
    .register-link a:hover{ text-decoration:underline; }
  </style>
</head>
<body>
  <div class="box" role="main">
    <h1><i class="fa fa-leaf"></i> Đăng ký GREENSTEP</h1>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="signup.php" novalidate>
      <div class="field">
        <input type="text" name="username" placeholder="Tên đăng nhập" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <input type="password" name="password" placeholder="Mật khẩu (>=6 ký tự)" required>
      </div>
      <div class="field">
        <input type="password" name="password2" placeholder="Xác nhận mật khẩu" required>
      </div>
      <button class="btn" type="submit">Tạo tài khoản</button>
    </form>

    <div class="register-link">
      Đã có tài khoản? <a href="login.php">Đăng nhập</a>
    </div>
  </div>
</body>
</html>
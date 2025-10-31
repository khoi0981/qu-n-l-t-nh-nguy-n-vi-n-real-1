<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// load DB
$dbFile = __DIR__ . '/src/config/db.php';
if (!file_exists($dbFile)) $dbFile = __DIR__ . '/../src/config/db.php';
require $dbFile;

$msg = '';

// handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'request';

    if ($action === 'request') {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $msg = 'Vui lòng nhập email.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id,email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$u) {
                    $msg = 'Không tìm thấy tài khoản với email này.';
                } else {
                    // allow reset via session for 15 minutes
                    $_SESSION['pw_reset'] = [
                        'user_id' => (int)$u['id'],
                        'email' => $u['email'],
                        'verified' => true,
                        'expires' => time() + 900
                    ];
                    $msg = 'Tài khoản tồn tại. Bạn có thể đặt lại mật khẩu ngay bên dưới.';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $msg = 'Lỗi hệ thống.';
            }
        }
    }

    if ($action === 'reset') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (empty($_SESSION['pw_reset']) || !($_SESSION['pw_reset']['verified'] ?? false)) {
            $msg = 'Yêu cầu đặt lại mật khẩu không hợp lệ.';
        } elseif ($new === '' || $confirm === '' || $new !== $confirm) {
            $msg = 'Mật khẩu mới trống hoặc không khớp.';
        } else {
            try {
                // detect password column
                $pwCol = 'password';
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach (['password','pass','passwd','user_pass'] as $c) if (in_array($c, $cols, true)) { $pwCol = $c; break; }
                } catch (Throwable $__e) { }

                $uid = (int)($_SESSION['pw_reset']['user_id'] ?? 0);
                if ($uid <= 0) throw new Exception('Invalid user');
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $u = $pdo->prepare("UPDATE users SET {$pwCol} = ? WHERE id = ? LIMIT 1");
                $u->execute([$hash, $uid]);
                unset($_SESSION['pw_reset']);
                $msg = 'Đổi mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.';
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $msg = 'Lỗi khi cập nhật mật khẩu.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quên mật khẩu - GREENSTEP</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f6fbf7,#eef8ef);margin:0;padding:40px}
.container{max-width:520px;margin:36px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 10px 30px rgba(12,40,20,0.06)}
.input{width:100%;padding:10px;border-radius:8px;border:1px solid #e6efe6;margin-bottom:12px}
.btn{display:inline-block;background:#2e8b57;color:#fff;padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
.msg{padding:10px;margin-bottom:12px;border-radius:8px;background:#f7f9f7;border-left:4px solid #2e8b57}
.note{color:#666;font-size:14px}

/* register-link (match login.php register link style) */
.register-link{ text-align:center; margin-top:12px; }
.register-link a{ color:#2e8b57; font-weight:600; text-decoration:none; }
.register-link a:hover{ text-decoration:underline; }
</style>
</head>
<body>
<div class="container">
  <h2>Đặt lại mật khẩu</h2>
  <?php if ($msg): ?><div class="msg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <?php if (empty($_SESSION['pw_reset']) || time() > ($_SESSION['pw_reset']['expires'] ?? 0)): ?>
    <form method="post">
      <input type="hidden" name="action" value="request">
      <input class="input" type="email" name="email" placeholder="Email đã đăng ký" required>
      <button class="btn" type="submit">Tiếp tục</button>
    </form>
    <p class="note">Nếu email tồn tại, bạn sẽ được phép đặt mật khẩu mới trong thời gian giới hạn.</p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="action" value="reset">
      <input class="input" type="password" name="new_password" placeholder="Mật khẩu mới" required>
      <input class="input" type="password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required>
      <button class="btn" type="submit">Đổi mật khẩu</button>
    </form>
  <?php endif; ?>

  <p class="register-link"><a href="/Expense_tracker-main/Expense_Tracker/login.php">Quay về đăng nhập</a></p>
</div>
</body>
</html>

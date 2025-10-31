<?php
// Use the centralized session bootstrap so cookie params and save path are consistent
// across all pages (news.php, login.php, etc.).
require_once __DIR__ . '/src/config/session.php';

// Debug session state
error_log('Login.php - Session ID: ' . session_id());
error_log('Login.php - Session data: ' . print_r($_SESSION, true));

/* Đường dẫn tới file cấu hình DB (tương ứng với cấu trúc dự án hiện tại) */
$dbFile = __DIR__ . '/src/config/db.php';
if (!file_exists($dbFile)) {
    // thử thêm phương án nếu cấu trúc khác (ghi log để dễ debug)
    $dbFile = __DIR__ . '/../src/config/db.php';
}

require $dbFile;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // --- Forgot password: request code by email ---
    if ($action === 'forgot_request') {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '') {
            $error = 'Vui lòng nhập địa chỉ email.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$u) {
                    $error = 'Không tìm thấy tài khoản với email này.';
                } else {
                    // Simplified: immediately allow reset if email exists (no code step)
                    $_SESSION['pw_reset'] = [
                        'user_id' => (int)$u['id'],
                        'email' => $u['email'],
                        'verified' => true,
                        'expires' => time() + 900 // allow 15 minutes to reset
                    ];
                    $error = 'Tài khoản tìm thấy. Vui lòng nhập mật khẩu mới bên dưới.';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $error = 'Lỗi hệ thống.';
            }
        }
    }

    // --- Reset password after verification ---
    elseif ($action === 'reset_password') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (empty($_SESSION['pw_reset']) || !($_SESSION['pw_reset']['verified'] ?? false)) {
            $error = 'Yêu cầu đặt lại mật khẩu chưa được xác thực.';
        } elseif ($new === '' || $confirm === '' || $new !== $confirm) {
            $error = 'Mật khẩu mới trống hoặc không khớp.';
        } else {
            try {
                // detect password column similar to profile.php
                $pwCol = 'password';
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach (['password','pass','passwd','user_pass'] as $c) if (in_array($c, $cols, true)) { $pwCol = $c; break; }
                } catch (Throwable $__e) { /* ignore */ }

                $hash = password_hash($new, PASSWORD_DEFAULT);
                $uid = (int)($_SESSION['pw_reset']['user_id'] ?? 0);
                if ($uid <= 0) throw new Exception('Invalid user');
                $u = $pdo->prepare("UPDATE users SET {$pwCol} = ? WHERE id = ? LIMIT 1");
                $u->execute([$hash, $uid]);
                unset($_SESSION['pw_reset']);
                $error = 'Đổi mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.';
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $error = 'Lỗi khi cập nhật mật khẩu.';
            }
        }
    }

    // --- Default: attempt login ---
    else {
        $username = trim((string)($_POST['user']['username'] ?? ''));
        $password = (string)($_POST['user']['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = "Vui lòng nhập tên đăng nhập và mật khẩu.";
        } else {
            try {
                // detect password column name (so login and reset use the same column)
                $pwCol = 'password';
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach (['password','pass','passwd','user_pass'] as $c) {
                        if (in_array($c, $cols, true)) { $pwCol = $c; break; }
                    }
                } catch (Throwable $__e) { /* ignore and use default */ }

                // lấy user theo username hoặc email (người dùng có thể nhập username hoặc email)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $authenticated = false;
                if ($user) {
                    $stored = $user[$pwCol] ?? ($user['password'] ?? '');
                    // hashed password
                    if ($stored !== '' && password_verify($password, $stored)) {
                        $authenticated = true;
                    }
                    // fallback plaintext (not recommended)
                    elseif ($password === $stored) {
                        $authenticated = true;
                    }
                }

                if ($authenticated) {
                    // gán session cơ bản
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'] ?? null;
                    $_SESSION['full_name'] = $user['full_name'] ?? $user['name'] ?? null;

                    // xác định role / is_admin (tùy cấu trúc bảng)
                    $role = $user['role'] ?? $user['user_role'] ?? null;
                    $isAdmin = false;
                    if (isset($user['is_admin'])) {
                        // có thể là 0/1 hoặc '1'
                        $isAdmin = (bool)$user['is_admin'];
                    } elseif ($role) {
                        $r = strtolower((string)$role);
                        if (in_array($r, ['admin','administrator','superadmin','staff'])) {
                            $isAdmin = true;
                        }
                    }

                    $_SESSION['user_role'] = $role;
                    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;

                    // check for next parameter first, then fallback to role-based redirect
                    $next = $_GET['next'] ?? '';
                    if ($next !== '' && str_starts_with($next, '/')) {
                        // Only allow redirects to local URLs starting with /
                        header('Location: ' . $next);
                        exit();
                    } else if ($isAdmin) {
                        header('Location: /Expense_tracker-main/Expense_Tracker/admin/index.php');
                        exit();
                    } else {
                        header('Location: /Expense_tracker-main/Expense_Tracker/index.php');
                        exit();
                    }
                } else {
                    $error = "Sai tài khoản hoặc mật khẩu!";
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $error = "Lỗi hệ thống, thử lại sau.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700" rel="stylesheet">
    <link href="assets/css/font-awesome.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/templatemo-style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e8f5e9 0%, #f8f6fc 100%);
            font-family: 'Open Sans', Arial, sans-serif;
            min-height: 100vh;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: #fff;
            padding: 40px 35px 30px 35px;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.12);
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2e8b57;
            margin-bottom: 28px;
            text-align: center;
        }
        .login-box .input-group-addon {
            background: #f8f6fc;
            border: none;
            color: #2e8b57;
        }
        .login-box .form-control {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            font-size: 1.1rem;
        }
        .login-box .btn-login {
            background: linear-gradient(90deg, #2e8b57 60%, #43a047 100%);
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            font-size: 1.1rem;
            padding: 10px 0;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
            transition: background 0.2s;
        }
        .login-box .btn-login:hover {
            background: linear-gradient(90deg, #246b45 60%, #388e3c 100%);
        }
        .login-box .error {
            color: #d32f2f;
            background: #ffebee;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 1rem;
        }
        .login-box .register-link {
            text-align: center;
            margin-top: 18px;
            font-size: 1rem;
        }
        .login-box .register-link a {
            color: #2e8b57;
            font-weight: 600;
            text-decoration: none;
        }
        .login-box .register-link a:hover {
            text-decoration: underline;
        }
        .stylish-input-group .input-group-addon {
            background: #fff;
            border-right: 0;
        }
        .stylish-input-group .form-control {
            border-left: 0;
            box-shadow: none;
            transition: box-shadow 0.2s;
        }
        .stylish-input-group .form-control:focus {
            box-shadow: 0 0 0 2px #2e8b5733;
            border-color: #2e8b57;
        }
        .stylish-input-group .input-group-addon {
            border-radius: 8px 0 0 8px;
            border: 1px solid #e0e0e0;
            border-right: 0;
        }
        .stylish-input-group .form-control {
            border-radius: 0 8px 8px 0;
            border: 1px solid #e0e0e0;
            border-left: 0;
        }
        /* Login Widget */
        .templatemo-login-widget {
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
            padding: 50px;
        }
        .templatemo-login-widget .square {
            width: 18px;
            height: 18px;
        }
        .templatemo-login-widget header { margin-bottom: 40px; }
        .templatemo-login-widget h1 {
            display: inline-block;
            font-size: 1.8em;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .templatemo-login-form .form-group { margin-bottom: 20px; }
        .templatemo-login-form .form-group:last-child {	margin-bottom: 0; }
        .input-group-addon { background: none; }
        .templatemo-blue-button, 
        .templatemo-white-button {
            border-radius: 2px;
            padding: 10px 30px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .templatemo-blue-button {
            background-color: #39ADB4;
            border: none;	
            color: white;	
        }
        .templatemo-blue-button:hover {	background-color: #2A858B; }
        .templatemo-register-widget {
            max-width: 450px;
            padding: 15px;
            text-align: center;
        }
        .templatemo-register-widget p {	margin-bottom: 0; }
        .checkbox label { padding-left: 0; }
        .font-weight-400 { font-weight: 400; }

        /* Style checkboxes and radio buttons */
        input[type="checkbox"] {  display:none; }
        input[type="checkbox"] + label span {
            display:inline-block;
            width:26px;
            height:25px;
            margin:-1px 4px 0 0;
            vertical-align:middle;
            background:url(../images/checkbox-radio-sheet.png) left top no-repeat;
            cursor:pointer;
        }
        input[type="checkbox"]:checked + label span {
            background:url(../images/checkbox-radio-sheet.png) -26px top no-repeat;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <h1><i class="fa fa-leaf"></i>Login</h1>
        <form id="loginForm" action="login.php" method="post">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-user fa-fw"></i></div>
                    <input type="text" name="user[username]" class="form-control" placeholder="js@dashboard.com" required>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-key fa-fw"></i></div>
                    <input type="password" name="user[password]" class="form-control" placeholder="******" required>
                </div>
            </div>
            <div class="form-group">
                <div class="checkbox squaredTwo">
                    <input type="checkbox" id="c1" name="cc" />
                    <label for="c1"><span></span>Remember me</label>
                </div>
            </div>
            <button type="submit" class="btn btn-login btn-block" style="margin-top:20px;">Login</button>
        </form>
        <div id="registerLink" class="register-link">
            Chưa có tài khoản? <a href="/Expense_tracker-main/Expense_Tracker/signup.php">Đăng ký ngay</a>
        </div>
         <div id="registerLink" class="register-link">
            <a href="/Expense_tracker-main/Expense_Tracker/forgot.php" >Quên mật khẩu?</a>
        </div>
    </div>
</div>
</body>
</html>
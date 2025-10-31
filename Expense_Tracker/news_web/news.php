<?php
// public news view
// Include centralized session bootstrap (keeps save path and cookie params consistent)
require_once __DIR__ . '/../src/config/session.php';

require __DIR__ . '/../src/config/db.php';

// Debug session in detail
error_log('=== DEBUG SESSION START ===');
error_log('Session ID: ' . session_id());
error_log('Session status: ' . session_status());
error_log('SESSION data: ' . print_r($_SESSION, true));
error_log('Cookie data: ' . print_r($_COOKIE, true));
error_log('Session name: ' . session_name());
error_log('Session save path: ' . session_save_path());
error_log('Session cookie params: ' . print_r(session_get_cookie_params(), true));

// Check login status like index.php
$isLogged = !empty($_SESSION['user_id']);
$userId = $isLogged ? (int)$_SESSION['user_id'] : null;
error_log('Login check results:');
error_log('- isLogged: ' . ($isLogged ? 'true' : 'false'));
error_log('- userId: ' . ($userId ?? 'none'));
error_log('=== DEBUG SESSION END ===');

// Debug session
error_log('Session in news.php: ' . print_r($_SESSION, true));
error_log('Session ID: ' . session_id());

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(404);
    echo 'Bài viết không tồn tại.';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userId) {
        try {
            // Get user info from database using session user_id
            // select all columns so we can detect avatar / points fields flexibly
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Found user data: " . print_r($user, true));

            if ($user) {
                // Try different name fields
                $currentUser = $user['full_name'] ?? $user['name'] ?? $user['username'] ?? null;

                // Detect avatar field
                $avatarFields = ['avatar','avatar_url','image','photo','profile_pic','picture'];
                $currentAvatarRaw = null;
                foreach ($avatarFields as $f) {
                    if (array_key_exists($f, $user) && !empty($user[$f])) { $currentAvatarRaw = $user[$f]; break; }
                }

                // Detect points/credits field
                $pointsFields = ['points','score','credits','balance','point','credits_balance'];
                $currentPoints = 0;
                foreach ($pointsFields as $f) {
                    if (array_key_exists($f, $user) && $user[$f] !== null) { $currentPoints = (int)$user[$f]; break; }
                }

                error_log("Using display name: " . ($currentUser ?? 'none'));
                error_log("Avatar raw: " . ($currentAvatarRaw ?? 'none') . "; points: " . $currentPoints);
            } else {
                error_log("No user found in database for ID: " . $userId);
            }
        } catch (Throwable $e) {
            error_log("Error getting user info: " . $e->getMessage());
        }
    } else {
        error_log("No user_id in session");
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Lỗi hệ thống.';
    exit;
}
// determine logged-in user (for rendering the comment form)
$currentUser = null;
// $userId is already set above, no need to redefine
error_log("Using previously set user_id: " . ($userId ?? 'none'));

if ($userId) {
    try {
        // Modify query to get all possible user name fields
        $stmt = $pdo->prepare("SELECT id, username, name, display_name, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("User data from DB: " . print_r($user, true));
        
        if ($user) {
            // Try different possible name fields in order of preference
            $currentUser = $user['display_name'] ?? 
                          $user['full_name'] ?? 
                          $user['name'] ?? 
                          $user['username'] ?? 
                          $_SESSION['username'] ?? 
                          'Người dùng #' . $userId;
                          
            // Store username in session for future use
            $_SESSION['username'] = $user['username'] ?? $_SESSION['username'] ?? null;
            $_SESSION['display_name'] = $currentUser;
            
            error_log("Set currentUser to: " . $currentUser);
        } else {
            // Fallback to session data if user not found in DB
            $currentUser = $_SESSION['username'] ?? 
                          $_SESSION['display_name'] ?? 
                          'dangkhoi'; // Fallback to known username
            error_log("User not found in DB, using session data: " . $currentUser);
        }
    } catch (Throwable $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        $currentUser = $_SESSION['username'] ?? 
                      $_SESSION['display_name'] ?? 
                      'dangkhoi'; // Fallback to known username
    }
} else {
    error_log("No user_id in session");
}

// login page path used for redirects / links
$loginUrl = '/Expense_tracker-main/Expense_Tracker/login.php';

// Prepare comments storage for this news post and load existing comments.
// This ensures $comments is always defined (avoids undefined variable and count() errors).
$commentsFile = __DIR__ . '/comments.' . ($id ?: '0') . '.json';
$comments = [];
if (file_exists($commentsFile)) {
    $rawComments = @file_get_contents($commentsFile);
    $decoded = @json_decode($rawComments, true);
    if (is_array($decoded)) {
        $comments = $decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    error_log('=== COMMENT SUBMISSION DEBUG ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('isLogged: ' . ($isLogged ? 'true' : 'false'));
    error_log('currentUser: ' . ($currentUser ?? 'not set'));
    error_log('userId: ' . ($userId ?? 'not set'));
    
    // Use the same login check as established at the top of the file
    if (!$isLogged) {
        error_log('User not logged in, redirecting to login page');
        // not logged in -> redirect to login with return URL
        $next = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'];
        $loc = $loginUrl . '?next=' . rawurlencode($next);
        header('Location: ' . $loc);
        exit;
    }

    $name = trim(substr((string)$currentUser, 0, 60));
    $body = trim(substr($_POST['comment'] ?? '', 0, 2000));
    if ($body !== '') {
        $entry = [
            'name' => htmlspecialchars($name, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'),
            'body' => htmlspecialchars($body, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'),
            'ts' => time()
        ];
        $comments[] = $entry;
        @file_put_contents($commentsFile, json_encode($comments, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }
    // redirect để tránh resubmit
    // giữ lại id để trang không mất tham số ?id=
    $redirect = $_SERVER['PHP_SELF'] . '?id=' . urlencode($id);
    header('Location: ' . $redirect);
    exit;
}

// Add this temporarily to see table structure
try {
    $stmt = $pdo->query("DESCRIBE users");
    error_log("Users table structure:");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        error_log($row['Field'] . ' - ' . $row['Type']);
    }
} catch (Throwable $e) {
    error_log("Error checking table structure: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($post['title']); ?> - GREENSTEP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Merriweather:wght@700&display=swap" rel="stylesheet">
<style>
:root{--accent:#2e8b57;--accent-600:#247143;--muted:#6b7a6f;--card:#ffffff;--bg:#f3f6f4}
*{box-sizing:border-box}
html,body{height:100%}
body{font-family:Inter,system-ui,Segoe UI,Arial,Helvetica;background:linear-gradient(180deg,var(--bg),#ffffff);margin:0;color:#1f2933;line-height:1.6}
.header-spacer{height:28px}
.container{max-width:960px;margin:36px auto;padding:20px}
.article-card{background:var(--card);border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(12,40,20,0.06);border:1px solid rgba(30,60,40,0.05)}
.hero{position:relative;display:block;height:360px;background:#e9f3ec url('/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg') center/cover no-repeat}
.hero .overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,20,10,0.18),rgba(6,20,10,0.4));display:flex;align-items:flex-end;padding:28px}
.post-title{font-family:Merriweather,serif;font-size:32px;color:#fff;margin:0;line-height:1.05;text-shadow:0 6px 18px rgba(0,0,0,0.4)}
.meta-row{display:flex;gap:12px;align-items:center;padding:18px 24px;color:var(--muted);font-size:14px}
.meta-row .author{display:flex;gap:10px;align-items:center}
.avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#eaf5ee,#cfe8d6);display:inline-flex;align-items:center;justify-content:center;color:var(--accent-600);font-weight:700}
.content-wrap{padding:20px 28px 32px}
.post-content{color:#222;font-size:16px}
.tools{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.btn{cursor:pointer;border:0;padding:10px 14px;border-radius:10px;font-weight:600}
.btn-primary{background:var(--accent);color:#fff}
.btn-light{background:#fff;border:1px solid #e6efe6;color:var(--accent)}
.read-progress{position:fixed;left:0;top:0;height:4px;background:var(--accent);width:0;z-index:9999;transition:width 120ms linear}
@media(max-width:720px){.hero{height:220px}.post-title{font-size:22px}.container{padding:12px}}
</style>
</head>
<body>
<div class="read-progress" id="readProgress"></div>

<!-- Minimal header / navbar -->
<header style="background:linear-gradient(90deg,#f3fbf6,#eef6f2);border-bottom:1px solid rgba(0,0,0,0.04)">
    <div style="max-width:1100px;margin:0 auto;padding:10px 20px;display:flex;align-items:center;gap:18px">
        <a href="/C:\xampp\htdocs\Expense_tracker-main/Expense_Tracker" style="display:flex;align-items:center;text-decoration:none;color:var(--accent)">
            <div style="width:40px;height:40px;border-radius:8px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;margin-right:10px">G</div>
            <div style="font-weight:700;color:var(--accent);">GreenNews</div>
        </a>

        <nav style="margin-left:18px;display:flex;gap:12px;align-items:center">
            <a href="/Expense_tracker-main/Expense_Tracker/index.php" style="color:#3b6b4f;text-decoration:none">Trang chủ</a>
            <a href="/Expense_tracker-main/Expense_Tracker/news_web/contact.php" style="color:#3b6b4f;text-decoration:none">Liên hệ</a>
        </nav>

        <!-- Search form -->
        <form action="/Expense_tracker-main/Expense_Tracker/news_web/index.php" method="get" style="margin-left:16px;flex:1;max-width:420px">
            <div style="display:flex;align-items:center;background:#fff;border-radius:999px;padding:6px 8px;border:1px solid rgba(0,0,0,0.04)">
                <input name="q" type="search" placeholder="Tìm bài viết, chủ đề..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" style="border:0;outline:none;padding:8px 10px;font-size:14px;flex:1;background:transparent">
                <button type="submit" style="border:0;background:transparent;color:var(--accent);padding:6px 8px;cursor:pointer;font-weight:700">Tìm</button>
            </div>
        </form>

        <?php
        // Avatar / profile link
        // ensure helper exists before use
        if (!function_exists('resolve_avatar_path')) {
            function resolve_avatar_path($val) {
                $default = '/Expense_tracker-main/Expense_Tracker/news_web/img/avatar-default.png';
                if (empty($val)) return $default;
                $v = trim((string)$val);
                if ($v === '') return $default;
                if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) return $v;
                if (isset($v[0]) && $v[0] === '/') return $v;
                if (strpos($v, 'uploads/users/') !== false) return '/' . ltrim($v, '/');
                $local = __DIR__ . '/../uploads/users/' . $v;
                if (file_exists($local)) return '/Expense_tracker-main/Expense_Tracker/uploads/users/' . rawurlencode($v);
                if (strpos($v, 'Expense_tracker-main') !== false) return (strpos($v, '/') === 0) ? $v : '/' . ltrim($v, '/');
                return $default;
            }
        }
        
        // Render avatar and points in header
        $profileUrl = '/Expense_tracker-main/Expense_Tracker/profile.php';
        $avatarUrl = resolve_avatar_path($currentAvatarRaw ?? null);
        $pointsDisplay = isset($currentPoints) ? (int)$currentPoints : 0;
        ?>

        <?php if (!empty($isLogged)): ?>
            <div style="margin-left:auto;display:flex;gap:12px;align-items:center">
                <div style="font-size:14px;color:var(--muted);display:flex;align-items:center;gap:8px">
                    <div style="background:#fff;border-radius:10px;padding:6px 8px;border:1px solid rgba(0,0,0,0.04);font-weight:700;color:var(--accent);">
                        <?php echo number_format($pointsDisplay); ?> điểm
                    </div>
                </div>
                <a href="<?php echo $profileUrl; ?>" title="Xem hồ sơ">
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,0,0,0.06)">
                </a>
            </div>
        <?php else: ?>
            <div style="margin-left:auto;display:flex;gap:12px;align-items:center">
                <a href="<?php echo $loginUrl; ?>" class="btn btn-primary" style="padding:8px 12px;border-radius:8px;text-decoration:none;color:#fff">Đăng nhập</a>
            </div>
        <?php endif; ?>
     </div>
 </header>

<div class="header-spacer" style="height:18px;"></div>
<div class="container" style="margin-top:0;">
    <a class="back-link" href="/Expense_tracker-main/Expense_Tracker/news_web/" style="color:var(--accent);text-decoration:none;font-weight:600">&larr; Quay lại danh sách tin</a>

    <article class="article-card">
        <?php
        // xác định hero image (ưu tiên file upload nếu tồn tại)
        $heroUrl = '/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg';
        if (!empty($post['image']) && file_exists(__DIR__ . '/../uploads/news/' . $post['image'])) {
            $heroUrl = '/Expense_tracker-main/Expense_Tracker/uploads/news/' . rawurlencode($post['image']);
        } else {
            // nếu content chứa thẻ img, lấy ảnh đầu tiên
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $post['content'] ?? '', $m)) {
                $heroUrl = $m[1];
            }
        }
        ?>
        <figure class="hero" style="background-image: url('<?php echo htmlspecialchars($heroUrl); ?>')">
            <div class="overlay">
                <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            </div>
        </figure>

        <div class="meta-row">
            <div class="author">
                <div class="avatar"><?php echo strtoupper(substr(($post['author'] ?? 'G'),0,1)); ?></div>
                <div>
                    <div style="font-weight:700;color:#0b3;"><?php echo !empty($post['author'])?htmlspecialchars($post['author']):'GREENSTEP'; ?></div>
                    <div style="font-size:13px;color:var(--muted);"><?php echo htmlspecialchars($post['created_at'] ?? ''); ?></div>
                </div>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
                <button class="btn btn-primary" onclick="window.print()">In bài</button>
                <button class="btn btn-light" id="copyBtn">Sao chép liên kết</button>
                <a class="btn btn-light" href="mailto:?subject=<?php echo rawurlencode($post['title']); ?>&body=<?php echo rawurlencode((isset($_SERVER['HTTP_HOST'])?('http://'.$_SERVER['HTTP_HOST']):'').' '.$_SERVER['REQUEST_URI']); ?>">Gửi email</a>
            </div>
        </div>

        <div class="content-wrap">
            <div class="post-content">
                <?php
                // hiển thị nội dung (giả sử nội dung đã được kiểm soát trước khi lưu)
                echo $post['content'] ?? '';
                ?>
            </div>

            <!-- Tin khác -->
            <section style="margin-top:28px;border-top:1px solid rgba(0,0,0,0.04);padding-top:20px">
                <h3 style="margin:0 0 12px;font-size:18px;color:#234">Tin khác</h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    <?php
                    try {
                        $stmt2 = $pdo->prepare("SELECT id,title,image,created_at FROM news WHERE id != ? ORDER BY created_at DESC LIMIT 4");
                        $stmt2->execute([$id]);
                        $others = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $others = [];
                    }

                    foreach ($others as $o):
                        $thumb = '/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg';
                        if (!empty($o['image']) && file_exists(__DIR__ . '/../uploads/news/' . $o['image'])) {
                            $thumb = '/Expense_tracker-main/Expense_Tracker/uploads/news/' . rawurlencode($o['image']);
                        }
                    ?>
                        <a href="/Expense_tracker-main/Expense_Tracker/news_web/news.php?id=<?php echo $o['id']; ?>" style="display:flex;align-items:center;gap:12px;padding:8px;border-radius:8px;background:#fff;border:1px solid rgba(0,0,0,0.03);text-decoration:none;color:inherit;min-width:260px">
                            <img src="<?php echo htmlspecialchars($thumb); ?>" alt="" style="width:84px;height:60px;object-fit:cover;border-radius:6px">
                            <div style="flex:1">
                                <div style="font-weight:700;font-size:14px;margin-bottom:6px"><?php echo htmlspecialchars($o['title']); ?></div>
                                <div style="font-size:12px;color:var(--muted)"><?php echo htmlspecialchars($o['created_at']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Comments -->
            <section style="margin-top:30px">
                <h3 style="font-size:18px;margin-bottom:10px">Bình luận (<?php echo count($comments); ?>)</h3>

                <?php if ($isLogged): ?>
                    <form method="post" style="display:flex;flex-direction:column;gap:10px;max-width:680px">
                        <div style="color:var(--muted);font-size:14px;margin-bottom:6px">Bạn đang đăng nhập là <strong><?php echo htmlspecialchars($currentUser); ?></strong></div>
                        <textarea name="comment" placeholder="Viết bình luận..." rows="4" style="padding:10px;border-radius:8px;border:1px solid #e6efe6"></textarea>
                        <div>
                            <button class="btn btn-primary" type="submit" name="comment_submit">Gửi bình luận</button>
                            <button class="btn btn-light" type="reset">Xóa</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="max-width:680px;display:flex;flex-direction:column;gap:8px">
                        <div style="color:var(--muted)">Bạn cần đăng nhập để có thể bình luận.</div>
                        <a class="btn btn-primary" href="<?php echo $loginUrl . '?next=' . rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI']); ?>">Đăng nhập</a>
                    </div>
                <?php endif; ?>

                <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px;max-width:720px">
                    <?php if (empty($comments)): ?>
                        <div style="color:var(--muted)">Chưa có bình luận nào.</div>
                    <?php else: foreach ($comments as $c): ?>
                        <div style="background:#fff;padding:12px;border-radius:10px;border:1px solid rgba(0,0,0,0.04)">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#eaf5ee,#cfe8d6);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent-600)"><?php echo strtoupper(substr($c['name'] ?? 'K',0,1)); ?></div>
                                <div>
                                    <div style="font-weight:700"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <div style="font-size:12px;color:var(--muted)"><?php echo date('M d, Y H:i', $c['ts'] ?? time()); ?></div>
                                </div>
                            </div>
                            <div style="color:#222;white-space:pre-wrap"><?php echo htmlspecialchars($c['body']); ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>

        </div>
    </article>
</div>

<!-- Footer: reuse main site footer for consistent branding -->
<footer class="site-footer" role="contentinfo">
    <style>
        .site-footer{ background: linear-gradient(180deg,#0f6a52,#16a085); color:#fff; padding:48px 0 24px; }
        .site-footer .footer-inner{ max-width:1100px; margin:0 auto; display:flex; gap:24px; align-items:flex-start; justify-content:space-between; padding:0 18px; box-sizing:border-box; flex-wrap:wrap }
        .site-footer .col{ flex:1; min-width:200px }
        .site-footer h4{ margin:0 0 12px; font-size:18px; font-weight:800 }
        .site-footer ul{ list-style:none; padding:0; margin:0 }
        .site-footer li{ margin-bottom:8px; color:rgba(255,255,255,0.9) }
        /* Force footer links to white for better contrast inside news subsite */
        .site-footer a, .site-footer a:visited { color: #ffffff !important; text-decoration: none !important; }
        .site-footer a:hover, .site-footer a:focus { color: rgba(255,255,255,0.95) !important; text-decoration: underline !important; }
        .site-footer .brand-sm strong, .site-footer .brand-sm div { color: #ffffff !important; }
            .site-footer .bottom { border-top:1px solid rgba(255,255,255,0.06); margin-top:18px; padding-top:16px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; color: rgba(255,255,255,0.9); }
            /* footer primary button style override to green */
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
                <li><a href="#">Sự kiện mới nhất</a></li>
                <li><a href="#">Điều khoản &amp; Điều kiện</a></li>
                <li><a href="#">Chính sách bảo mật</a></li>
                <li><a href="#">Tuyển dụng</a></li>
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

    <div class="bottom" style="max-width:1200px;margin:10px auto 0;padding:0 18px;box-sizing:border-box;">
        <div>© <?php echo date('Y'); ?> Green Initiative. All rights reserved.</div>
        <div>Thiết kế thân thiện | <a href="#" style="color:rgba(255,255,255,0.95);text-decoration:none">Liên hệ</a></div>
    </div>
</footer>

<script>
// copy link
document.getElementById('copyBtn')?.addEventListener('click', function(){
    const url = location.href;
    navigator.clipboard?.writeText(url).then(()=> {
        this.textContent = 'Đã sao chép';
        setTimeout(()=> this.textContent = 'Sao chép liên kết', 2000);
    });
});
// reading progress
(function(){
    const prog = document.getElementById('readProgress');
    const doc = document.documentElement;
    function update(){
        const h = doc.scrollHeight - doc.clientHeight;
        const p = h ? (window.scrollY / h) * 100 : 0;
        prog.style.width = Math.min(100, Math.max(0,p)) + '%';
    }
    window.addEventListener('scroll', update, {passive:true});
    window.addEventListener('resize', update);
    update();
})();
</script>
</body>
</html>
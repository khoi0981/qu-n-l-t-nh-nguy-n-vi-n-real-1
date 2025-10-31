<?php
// an toàn với session (không gọi lại nếu đã active)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}

require_once __DIR__ . '/../src/config/db.php';

// user points
$userPoints = 0;
$isLogged = !empty($_SESSION['user_id']);
if ($isLogged && isset($pdo)) {
    try {
        $userId = (int) $_SESSION['user_id'];
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $pick = function(array $names) use ($cols) {
            foreach ($names as $n) if (in_array($n, $cols)) return $n;
            return null;
        };
        $pointsCol = $pick(['points','reward_points','reward_point','score','balance','credits','coins','point']);
        if ($pointsCol) {
            $stmt = $pdo->prepare("SELECT {$pointsCol} AS pts FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $userPoints = isset($r['pts']) ? (int)$r['pts'] : 0;
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}

// resolve user avatar (default if not found)
$userAvatarUrl = '/Expense_tracker-main/Expense_Tracker/news_web/img/avatar-default.png';
if ($isLogged && isset($pdo)) {
    try {
        // reuse column detection
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $pick = function(array $names) use ($cols){ foreach($names as $n) if (in_array($n,$cols,true)) return $n; return null; };
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
                    $local = __DIR__ . '/../uploads/users/' . $val;
                    if (file_exists($local)) {
                        $userAvatarUrl = '/Expense_tracker-main/Expense_Tracker/uploads/users/' . rawurlencode($val);
                    } else {
                        // fallback to attempt using as relative
                        $userAvatarUrl = '/' . ltrim($val, '/');
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // ignore, keep default
    }
}

// load rewards from DB (fallback to static)
$rewards = [];
if (isset($pdo)) {
    try {
        $has = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewards'")->fetchColumn();
        if ($has) {
            $rewards = $pdo->query("SELECT * FROM rewards ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) { /* ignore */ }
}
if (empty($rewards)) {
    $rewards = [
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-1.jpg','title'=>'Túi Vải Thân Thiện','store'=>'Green Shop','cost'=>'3000 điểm','link'=>'#'],
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-2.jpg','title'=>'Bình Nước Thép','store'=>'Eco Store','cost'=>'5000 điểm','link'=>'#'],
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-3.jpg','title'=>'Bộ Dụng Cụ Tái Sử Dụng','store'=>'Reuse Mart','cost'=>'7000 điểm','link'=>'#'],
    ];
}

// path to news logo (optional)
$newsLogo = __DIR__ . '/../news_web/img/logo.png';
$newsLogoWeb = '/Expense_tracker-main/Expense_Tracker/news_web/img/logo.png';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Green Rewards - Đổi thưởng</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root{--green:#2e8b57;--muted:#55676a;--light:#f3fcf6}
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:#f6fbf7;color:#16321f}
    /* top gradient + layout container like screenshot */
    .topbar{
      background:linear-gradient(90deg,#f6fbf7,#ffffff);
      border-bottom:1px solid rgba(46,139,87,0.06);
      padding:10px 0;
    }
    .container{max-width:1200px;margin:0 auto;padding:0 20px}
    /* navbar layout: brand left, links center, actions right */
    .navbar{
      display:flex;align-items:center;gap:16px;height:64px;
    }
    .navbar-left{display:flex;align-items:center;gap:12px;min-width:220px}
    .nav-leaf{
      width:44px;height:44px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
      background:linear-gradient(180deg,var(--green),#28a349);box-shadow:0 6px 18px rgba(46,139,87,0.08)
    }
    .nav-leaf i{color:#fff;font-size:18px}
    .brand-text{font-weight:800;color:var(--green);font-size:20px;letter-spacing:1px;white-space:nowrap}
    .nav-center{flex:1;display:flex;justify-content:center}
    .nav-links{display:flex;gap:22px;align-items:center}
    .nav-links a{font-weight:700;color:#2d3b33;text-decoration:none;padding:10px 14px;border-radius:8px}
    .nav-links a.active{background:#eef6f0;color:var(--green)}
    /* actions block on same row but visually matching screenshot */
    .nav-right{display:flex;gap:10px;align-items:center;justify-content:flex-end;min-width:260px}
    .points-badge{background:var(--green);color:#fff;padding:8px 14px;border-radius:20px;font-weight:700;box-shadow:0 6px 18px rgba(46,139,87,0.06)}
    /* consistent button sizing and vertical centering */
    .btn-primary{
      background:var(--green);
      color:#fff;
      padding:0 16px;
      height:40px;
      line-height:1;
      border-radius:8px;
      border:none;
      text-decoration:none;
      font-weight:700;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      box-shadow:0 6px 18px rgba(46,139,87,0.08);
      vertical-align:middle;
    }
    .btn-ghost{
      background:transparent;
      border:1px solid rgba(46,139,87,0.12);
      color:var(--green);
      padding:0 14px;
      height:40px;
      line-height:1;
      border-radius:8px;
      text-decoration:none;
      font-weight:700;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      vertical-align:middle;
    }
    /* user avatar in header */
    .profile-link{display:inline-flex;align-items:center;gap:8px;text-decoration:none}
    .user-avatar{width:40px;height:40px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,0.95);box-shadow:0 6px 18px rgba(0,0,0,0.06);background:#fff}
    .user-avatar img{width:100%;height:100%;object-fit:cover;display:block}
    /* hover + active transitions */
    .btn-primary,.btn-ghost,.points-badge,.nav-links a{transition:background-color .16s ease, transform .08s ease, box-shadow .12s ease}
    .btn-primary:hover{background:#279046;box-shadow:0 10px 22px rgba(46,139,87,0.12)}
    .btn-primary:active{transform:translateY(1px);background:#1f6a3f}
    .btn-ghost:hover{background:rgba(46,139,87,0.04)}
    .nav-links a:hover{background:rgba(0,0,0,0.03);transform:translateY(-1px)}
    /* Banner */
    .banner{padding:56px 0 28px;text-align:center}
    .banner h1{color:var(--green);font-size:34px;margin:0 0 8px;font-weight:700}
    .banner p{color:var(--muted);margin:0;font-size:14px}
    /* notes */
    .notes-container{display:flex;gap:26px;justify-content:center;max-width:1000px;margin:26px auto;padding:0 12px}
    .note{flex:1;background:var(--light);border-left:6px solid var(--green);border-radius:12px;padding:18px 20px;color:#26482f;box-shadow:0 6px 18px rgba(30,60,40,0.04)}
    .note h4{margin:0 0 8px;font-size:15px}
    .note p{margin:0;color:#2f4d3b;line-height:1.45;font-size:14px}
    /* cards */
    .featured{max-width:1200px;margin:34px auto;padding:0 20px}
    .cards{display:flex;gap:24px;flex-wrap:wrap;justify-content:center} /* centered cards */
    .card{background:#fff;border-radius:12px;padding:18px;width:320px;box-shadow:0 10px 30px rgba(34,60,40,0.06);border:1px solid rgba(0,0,0,0.02)}
    .card img{width:100%;height:180px;object-fit:cover;border-radius:8px}
    .card h3{margin:12px 0 8px;font-size:18px;color:#1f3a2e}
    .badges{display:flex;gap:8px;margin-bottom:8px}
    .badge{background:#eef9ef;color:var(--green);padding:6px 8px;border-radius:12px;font-size:13px}
    .card .actions{display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:8px}
    .card .actions a{min-width:130px;text-align:center}
    /* footer (reuse previous footer style) */
    footer{background:var(--green);color:#fff;padding:32px 12px;margin-top:40px}
    footer .container{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
    footer a{color:#fff;text-decoration:none;font-weight:600}
    /* responsive */
    @media(max-width:1000px){
      .cards{justify-content:center}
      .nav-links{gap:12px}
    }
    @media(max-width:800px){
      .nav-center{display:none} /* collapse center nav on narrow if needed */
      .navbar{height:auto;padding:12px 0;flex-wrap:wrap;align-items:center}
      .nav-right{width:100%;justify-content:center;margin-top:8px}
    }

    /* Force navbar to allow wrapping and put the points/buttons row on the next line */
    .navbar{ flex-wrap:wrap; align-items:center; }

    /* Keep the main nav links centered on the first row */
    .nav-center{ order:1; width:100%; display:flex; justify-content:center; }
    @media(min-width:900px){
      .nav-center{ width:auto; order:1; }
    }

    /* Place points + buttons on a separate row beneath the nav links,
       add spacing between items so they are not too close */
    .nav-right{
      order:2;
      width:100%;
      display:flex;
      gap:14px;
      justify-content:flex-end;
      margin-top:12px;
      padding-top:8px;
    }
    @media(max-width:900px){
      .nav-right{ justify-content:center; }
    }

    /* ensure individual controls have comfortable spacing */
    .nav-right .points-badge{ margin-right:6px; }
    .nav-right a.btn-primary,
    .nav-right a.btn-ghost{ min-width:120px; text-align:center; }

    /* subtle visual separator between nav and action row on wide screens */
    @media(min-width:900px){
      .nav-right{ border-top:0; margin-top:10px; justify-content:flex-end; width:100%; }
      .navbar > .nav-center { margin-bottom:0; }
    }

    /* Modern header / footer overrides */
    .site-topbar{ background: linear-gradient(180deg,#ffffff,#f6fbf7); border-bottom:1px solid rgba(46,139,87,0.06); }
    .site-topbar .header-inner{ max-width:1200px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; gap:16px; justify-content:space-between }
    .site-topbar .brand{ display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit }
    .site-topbar .brand .leaf{ width:46px; height:46px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(180deg,var(--green),#28a349); box-shadow:0 8px 24px rgba(46,139,87,0.08); color:#fff }
    .site-topbar .brand .title{ font-weight:800; color:var(--green); font-size:20px }
    
    .site-topbar .nav-center{ flex:1; display:flex; justify-content:center }
    .site-topbar .nav-links{ display:flex; gap:18px; align-items:center }
    .site-topbar .nav-links a{ padding:8px 12px; border-radius:10px; font-weight:700; color:#2b3d35; text-decoration:none }
    .site-topbar .nav-links a.active, .site-topbar .nav-links a:hover{ background:#eef6f0; color:var(--green) }
    
    .site-topbar .nav-right{ display:flex; gap:10px; align-items:center }
    .site-topbar .points-badge{ padding:8px 14px; border-radius:20px; font-weight:800; box-shadow:0 6px 18px rgba(46,139,87,0.06) }
    
    /* modern footer */
    .site-footer{ background:linear-gradient(180deg,#0f6a52,#16a085); color:#fff; padding:48px 0 22px; }
    .site-footer .footer-inner{ max-width:1200px; margin:0 auto; padding:0 20px; display:flex; gap:24px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap }
    .site-footer .col{ flex:1; min-width:200px }
    .site-footer h4{ margin:0 0 12px; font-size:16px; font-weight:800 }
    .site-footer p, .site-footer li{ color:rgba(255,255,255,0.92) }
    .site-footer .socials{ display:flex; gap:10px; margin-top:8px }
    .site-footer .socials a{ width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(255,255,255,0.08); color:#fff; text-decoration:none }
    @media(max-width:900px){ .site-topbar .nav-center{ display:none } .site-topbar .header-inner{ padding:12px } .site-footer .footer-inner{ flex-direction:column; gap:18px } }
  </style>
</head>
<body>
  <header class="site-topbar" role="banner">
    <div class="header-inner">
      <a class="brand" href="/Expense_tracker-main/Expense_Tracker/index.php" aria-label="Trang chủ">
        <span class="leaf"><i class="fas fa-leaf"></i></span>
        <span class="title">GREENSTEP</span>
      </a>

      <nav class="nav-center" role="navigation" aria-label="Primary navigation">
        <div class="nav-links" role="menubar">
          <a href="/Expense_tracker-main/Expense_Tracker/index.php" role="menuitem">TRANG CHỦ</a>
          <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" class="active" role="menuitem">ĐỔI THƯỞNG</a>
          <a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" role="menuitem">TIN TỨC XANH</a>
          <a href="/Expense_tracker-main/Expense_Tracker/news_web/contact.php" role="menuitem">LIÊN HỆ</a>
        </div>
    </div>
    <div class="nav-right" role="group" aria-label="User actions">
        <div class="points-badge"><?php echo (int)$userPoints; ?> điểm</div>
        <?php if ($isLogged): ?>
          <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php" class="btn-primary" role="button">Phần thưởng</a>
          <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/history.php" class="btn-primary" role="button">Lịch sử</a>
          <a href="/Expense_tracker-main/Expense_Tracker/profile.php" class="profile-link" title="Hồ sơ">
            <span class="user-avatar" aria-hidden="true"><img src="<?php echo htmlspecialchars($userAvatarUrl); ?>" alt="Avatar"></span>
            <span style="font-weight:700;color:var(--green);display:none;">Hồ sơ</span>
          </a>
        <?php else: ?>
          <a href="/Expense_tracker-main/Expense_Tracker/login.php" class="btn-primary" role="button">Đăng nhập</a>
        <?php endif; ?>
      </div>
  </header>

  <main>
    <section class="banner">
      <div class="container">
        <h1>Green Rewards - Đổi điểm vì hành động xanh</h1>
        <p>Sử dụng điểm để nhận quà từ các cửa hàng thân thiện với môi trường</p>
      </div>
    </section>

    <section class="notes-container" aria-labelledby="notes-title">
      <div class="note" id="note-1">
        <h4>Lưu ý khi đổi thưởng</h4>
        <p>Khi bạn đổi thưởng, số xu trong tài khoản sẽ bị trừ. Việc giảm xu có thể ảnh hưởng tới vị trí xếp hạng của bạn trên bảng thành tích.</p>
      </div>
      <div class="note" id="note-2">
        <h4>Điều kiện đổi thưởng hàng tháng</h4>
        <p>Để đủ điều kiện đổi thưởng trong tháng, bạn cần tham gia ít nhất một hoạt động tình nguyện trong tháng đó.</p>
      </div>
    </section>

    <section class="featured" aria-label="Danh sách phần thưởng">
      <div class="container">
        <div class="cards" role="list" aria-live="polite">
          <?php foreach ($rewards as $item): 
              // đảm bảo item là mảng
              if (!is_array($item)) continue;

              $title = trim((string)($item['title'] ?? $item['name'] ?? 'Untitled'));
              $store = $item['store'] ?? 'Store';
              $cost  = $item['cost'] ?? $item['cost_points'] ?? ($item['points'] ?? 'Tùy chọn');
              $link  = $item['link'] ?? '#';

              // Resolve image URL: nếu DB lưu filename thì lấy từ uploads/rewards, nếu là đường dẫn đầy đủ giữ nguyên
              $placeholder = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
              $rawImage = $item['image'] ?? '';
              $image = $placeholder;

              if ($rawImage !== '') {
                  // nếu là URL tuyệt đối hoặc path bắt đầu '/', dùng luôn
                  if (preg_match('#^https?://#i', $rawImage) || strpos($rawImage, '/') === 0) {
                      $image = $rawImage;
                  } else {
                      // kiểm tra file thực tế trên máy chủ (uploads/rewards)
                      $serverPath = realpath(__DIR__ . '/../uploads/rewards/') ? realpath(__DIR__ . '/../uploads/rewards/') . DIRECTORY_SEPARATOR . $rawImage : __DIR__ . '/../uploads/rewards/' . $rawImage;
                      if (file_exists($serverPath)) {
                          $image = '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($rawImage);
                      } else {
                          // thử đường dẫn tạm (nếu DB đã lưu 'uploads/rewards/xxx')
                          $maybe = '/' . ltrim($rawImage, '/');
                          $image = $maybe;
                      }
                  }
              }
              // tạo id an toàn cho aria-labelledby
              $id_attr = 'reward-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower(preg_replace('/\s+/', '-', $title)));
              ?>
            <article class="card" role="listitem" aria-labelledby="<?php echo htmlspecialchars($id_attr); ?>">
              <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($title); ?>">
              <h3 id="<?php echo htmlspecialchars($id_attr); ?>"><?php echo htmlspecialchars($title); ?></h3>
              <div class="badges">
                <span class="badge"><?php 
                  $costValue = trim(str_replace('điểm', '', $cost));
                  echo htmlspecialchars($costValue . ' điểm'); 
                ?></span>
              </div>
              <div class="actions">
                <button
                    type="button"
                    class="btn-ghost detail-btn"
                    aria-label="Chi tiết <?php echo htmlspecialchars($title); ?>"
                    data-image="<?php echo htmlspecialchars($image, ENT_QUOTES); ?>"
                    data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
                    data-cost="<?php echo htmlspecialchars($cost, ENT_QUOTES); ?>"
                    data-store="<?php echo htmlspecialchars($store, ENT_QUOTES); ?>"
                    data-desc="<?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES); ?>"
                    data-link="<?php echo htmlspecialchars($link, ENT_QUOTES); ?>"
                >Chi tiết →</button>

                <form method="POST" style="display:inline-block;margin:0">
                  <input type="hidden" name="action" value="exchange">
                  <input type="hidden" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>">
                  <input type="hidden" name="image" value="<?php echo htmlspecialchars($rawImage ?? $image, ENT_QUOTES); ?>">
                  <input type="hidden" name="cost" value="<?php echo htmlspecialchars($cost, ENT_QUOTES); ?>">
                  <button type="submit" class="btn-primary" aria-label="Đổi ngay <?php echo htmlspecialchars($title); ?>">Đổi ngay</button>
                </form>
             </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
      <div class="col">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px"><span class="leaf" style="width:40px;height:40px;border-radius:8px;background:#fff;display:inline-flex;align-items:center;justify-content:center;color:var(--green)"><i class="fas fa-leaf"></i></span><div><strong>GREENSTEP</strong><div style="font-size:12px;color:rgba(255,255,255,0.9)">Hành động nhỏ - Tương lai xanh</div></div></div>
        <p style="max-width:320px;color:rgba(255,255,255,0.92)">Kết nối cộng đồng tình nguyện viên vì môi trường. Tích điểm, tham gia hoạt động và đổi phần thưởng ý nghĩa.</p>
        <div class="socials">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="Linkedin"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>

      <div class="col">
        <h4>Khám phá</h4>
        <ul style="list-style:none;padding:0;margin:0">
          <li><a href="/Expense_tracker-main/Expense_Tracker/index.php" style="color:rgba(255,255,255,0.95);text-decoration:none">Sự kiện mới nhất</a></li>
          <li><a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" style="color:rgba(255,255,255,0.95);text-decoration:none">Đổi thưởng</a></li>
          <li><a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" style="color:rgba(255,255,255,0.95);text-decoration:none">Tin tức Xanh</a></li>
        </ul>
      </div>

      <div class="col">
        <h4>Liên hệ</h4>
        <ul style="list-style:none;padding:0;margin:0;color:rgba(255,255,255,0.95)">
          <li>Công ty Above</li>
          <li>Đường JC Main, gần tòa nhà Silnie</li>
          <li>(123) 456-789</li>
          <li>email@domainname.com</li>
        </ul>
      </div>

      <div class="col">
        <h4>Bản tin</h4>
        <form action="#" method="post" onsubmit="return false;" style="display:flex;gap:8px;align-items:center">
          <input type="email" placeholder="Email của bạn" style="flex:1;padding:8px;border-radius:8px;border:0"> 
          <button class="btn-primary" style="padding:8px 12px;border-radius:8px">OK</button>
        </form>
      </div>
    </div>

    <div style="max-width:1200px;margin:12px auto 0;padding:0 20px;color:rgba(255,255,255,0.85);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div>© <?php echo date('Y'); ?> Green Initiative</div>
      <div><a href="/Expense_tracker-main/Expense_Tracker/contact.php" style="color:rgba(255,255,255,0.95);text-decoration:none">Liên hệ</a></div>
    </div>
  </footer>

  <script>
    // nhỏ: hiệu ứng nhấn cho touch -> thêm lớp active trong thời gian touch
    document.addEventListener('pointerdown', function(e){
      const t = e.target.closest('.btn-primary, .btn-ghost');
      if(t){ t.classList.add('pressed'); }
    });
    document.addEventListener('pointerup', function(e){
      const t = e.target.closest('.btn-primary, .btn-ghost');
      if(t){ t.classList.remove('pressed'); }
    });
  </script>
<!-- chèn modal chi tiết & script ngay trước thẻ đóng </body> -->
<div id="reward-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:11000;align-items:center;justify-content:center;padding:20px;">
  <div style="width:100%;max-width:760px;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;max-height:92vh;">
    <button id="reward-detail-close" aria-label="Đóng" style="position:absolute;right:14px;top:12px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#333;z-index:11010">&times;</button>
    <div style="overflow:auto;padding:18px;">
      <div style="height:360px;border-radius:8px;overflow:hidden;background:#f6f6f6;display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <img id="reward-detail-img" src="" alt="Ảnh sản phẩm" style="width:100%;height:100%;object-fit:contain;display:block;">
      </div>

      <h2 id="reward-detail-title" style="margin:0 0 6px;font-size:22px;color:#111"></h2>
      <div style="color:#6b6b6b;margin-bottom:12px;display:flex;align-items:center;gap:12px">
        <div style="font-weight:800;color:var(--green);font-size:20px" id="reward-detail-cost"></div>
      </div>

      <div style="background:#faf6f8;border-radius:10px;padding:14px;margin-bottom:12px">
        <h4 style="margin:0 0 8px;color:var(--green);font-weight:700">Thông tin sản phẩm</h4>
        <div id="reward-detail-desc" style="color:#333;line-height:1.6;white-space:pre-wrap"></div>
      </div>
    </div>

    <div style="border-top:1px solid rgba(0,0,0,0.06);padding:12px 18px;display:flex;gap:10px;align-items:center;justify-content:space-between;">
      <form method="POST" style="display:inline-block;margin:0">
        <input type="hidden" name="action" value="exchange">
        <input type="hidden" name="title" id="reward-detail-form-title">
        <input type="hidden" name="image" id="reward-detail-form-image">
        <input type="hidden" name="cost" id="reward-detail-form-cost">
        <button type="submit" class="btn-primary" style="text-decoration:none">Tiến hành đổi quà</button>
      </form>
      <button id="reward-detail-close-2" class="btn-ghost" style="background:transparent;border:1px solid rgba(46,139,87,0.12);padding:8px 14px;border-radius:8px">Đóng</button>
    </div>
  </div>
</div>

<script>
(function(){
  function by(id){ return document.getElementById(id); }

  document.addEventListener('click', function(e){
    var btn = e.target.closest && e.target.closest('.detail-btn');
    if (!btn) return;
    var img = btn.getAttribute('data-image') || '';
    var title = btn.getAttribute('data-title') || '';
    var cost = btn.getAttribute('data-cost') || '';
    var store = btn.getAttribute('data-store') || '';
    var desc = btn.getAttribute('data-desc') || '';
    var link = btn.getAttribute('data-link') || ('/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php?item=' + encodeURIComponent(title));

    // Normalize image: nếu relative (không bắt đầu '/'), tiền tố app root
    if (img && img.charAt(0) !== '/' && !/^https?:\/\//i.test(img)) {
      img = '/Expense_tracker-main/Expense_Tracker/' + img.replace(/^\.\/*/, '');
    }

  by('reward-detail-img').src = img || '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
  by('reward-detail-title').textContent = title;
  by('reward-detail-cost').textContent = cost;
  by('reward-detail-desc').textContent = desc || 'Chưa có thông tin chi tiết.';
  
  // Update form hidden inputs
  by('reward-detail-form-title').value = title;
  by('reward-detail-form-image').value = btn.getAttribute('data-image') || ''; // Use original image path
  by('reward-detail-form-cost').value = cost;

    by('reward-detail-modal').style.display = 'flex';
    // prevent default if button inside anchor context
    e.preventDefault && e.preventDefault();
  });

  var close = by('reward-detail-close');
  var close2 = by('reward-detail-close-2');
  [close, close2].forEach(function(el){ if (!el) el=null; else el.addEventListener('click', function(){ by('reward-detail-modal').style.display='none'; }); });

  window.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      var m = by('reward-detail-modal');
      if (m && m.style.display === 'flex') m.style.display = 'none';
    }
  });

  // click overlay để đóng
  document.getElementById('reward-detail-modal').addEventListener('click', function(e){
    if (e.target === this) this.style.display = 'none';
  });
})();
</script>
<!-- end chèn modal -->
</body>
</html>

<?php
// Xử lý khi người dùng bấm "Đổi ngay" (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'exchange') {
    // đảm bảo session user
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if (!$userId) {
        header('Location: /Expense_tracker-main/Expense_Tracker/login.php');
        exit;
    }

    // đảm bảo kết nối $pdo
    if (!isset($pdo)) {
        error_log('DB không khả dụng khi xử lý đổi thưởng');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // tìm cột điểm trong bảng users (tương tự logic khác)
    $pointsCol = null;
    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $prefer = ['points','reward_points','reward_point','score','balance','credits','coins','point'];
        foreach ($prefer as $p) if (in_array($p, $cols, true)) { $pointsCol = $p; break; }
    } catch (Throwable $e) { /* ignore */ }

    // đảm bảo bảng redemptions tồn tại với cột redemption_code
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `redemptions` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `reward_title` VARCHAR(255) NOT NULL,
      `reward_cost` INT NOT NULL,
      `image` VARCHAR(255) DEFAULT NULL,
      `redemption_code` VARCHAR(20) DEFAULT NULL,
      `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `uq_redemption_code` (`redemption_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add redemption_code column if it doesn't exist
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM redemptions LIKE 'redemption_code'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE redemptions ADD COLUMN redemption_code VARCHAR(20) DEFAULT NULL, ADD UNIQUE KEY uq_redemption_code (redemption_code)");
        }
    } catch (Throwable $e) {
        error_log('Warning: Could not verify redemption_code column: ' . $e->getMessage());
    }

    // lấy dữ liệu POST
    $title = trim($_POST['title'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $costRaw = trim($_POST['cost'] ?? '');
    $cost = (int) preg_replace('/[^\d]/','',$costRaw);

    $message = '';
    try {
        if ($cost <= 0) throw new Exception('Số xu không hợp lệ.');

        // kiểm tra điểm hiện tại
        if (!$pointsCol) throw new Exception('Không tìm thấy cột điểm người dùng trong DB.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT {$pointsCol} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $current = (int)$stmt->fetchColumn();

        if ($current < $cost) {
            $pdo->rollBack();
            // redirect về trang để hiện thông báo (có thể dùng query param)
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?msg=' . rawurlencode('Không đủ điểm để đổi quà.'));
            exit;
        }

        // Generate redemption code
        $redemptionCode = 'R' . str_pad(mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        
        // chèn redemptions và trừ điểm
        $ins = $pdo->prepare("INSERT INTO redemptions (user_id, reward_title, reward_cost, image, redemption_code, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $ins->execute([$userId, $title, $cost, $image ?: null, $redemptionCode]);

        $upd = $pdo->prepare("UPDATE users SET {$pointsCol} = GREATEST(0, {$pointsCol} - ?) WHERE id = ?");
        $upd->execute([$cost, $userId]);

        $pdo->commit();
        
        // Use session flash message instead of URL parameter
        $_SESSION['flash_message'] = 'Yêu cầu đổi thưởng đã được gửi và đang xử lý. Mã đổi thưởng của bạn: ' . $redemptionCode;
        
        // Ensure no content has been sent yet
        if (!headers_sent()) {
            header('Location: /Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php');
            exit;
        } else {
            // Fallback if headers were already sent
            echo '<script>window.location.href = "/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php";</script>';
            echo 'Đang chuyển hướng...';
            exit;
        }
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Exchange error: ' . $e->getMessage());
        $_SESSION['flash_message'] = 'Lỗi khi đổi quà: ' . $e->getMessage();
        header('Location: /Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php');
        exit;
    }
}
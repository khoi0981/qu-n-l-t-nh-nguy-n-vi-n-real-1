<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../src/config/db.php';

// đảm bảo các biến user cơ bản đã được khởi tạo để tránh Notice/Warning
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userPoints = 0;          // sẽ được cập nhật sau khi dò cột điểm
$userAvatar = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/user-placeholder.png';

// helper: chuẩn hoá đường dẫn ảnh (trả về URL bắt đầu bằng '/' hoặc full http)
function resolve_url_path($path){
    $placeholder = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
    if (!$path) return $placeholder;
    $p = trim($path);
    if (preg_match('#^https?://#i',$p)) return $p;
    if (strpos($p,'/') === 0) return $p; // already absolute from webroot

    // if DB value already contains uploads/ (e.g. "uploads/rewards/abc.jpg") normalize to webroot
    if (stripos($p, 'uploads/') !== false) {
        $clean = preg_replace('#^\.*/*#', '', $p);
        return '/Expense_tracker-main/Expense_Tracker/' . str_replace('%2F','/', rawurlencode($clean));
    }

    // 1) check uploads/rewards on server (support filename or basename)
    $uploadsDir = realpath(__DIR__ . '/../uploads/rewards');
    if ($uploadsDir) {
        $serverPath = $uploadsDir . DIRECTORY_SEPARATOR . $p;
        if (file_exists($serverPath)) {
            return '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($p);
        }
        // try basename in case DB stored a path like "some/other/path/filename.jpg"
        $bn = basename($p);
        $serverPath2 = $uploadsDir . DIRECTORY_SEPARATOR . $bn;
        if (file_exists($serverPath2)) {
            return '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($bn);
        }
    }

    // 2) check reward asset folder
    $assetCandidate = __DIR__ . '/asset/image/product-img/' . $p;
    if (file_exists($assetCandidate)) {
        return '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/' . rawurlencode($p);
    }

    // 3) fallback prefix (keeps previous behavior)
    return '/Expense_tracker-main/Expense_Tracker/' . ltrim($p, './');
}

// tìm cột điểm trong users
$pointsCol = null;
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    $prefer = ['points','reward_points','reward_point','score','balance','credits','coins','point'];
    foreach ($prefer as $p) if (in_array($p, $cols, true)) { $pointsCol = $p; break; }
} catch (Throwable $e) { /* ignore */ }

// đảm bảo bảng redemptions tồn tại (bao gồm redemption_code)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `redemptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `reward_title` VARCHAR(255) NOT NULL,
  `reward_cost` INT NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `redemption_code` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// load available rewards (tương tự reward.php fallback)
$rewards = [];
try {
    $has = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rewards'")->fetchColumn();
    if ($has) $rewards = $pdo->query("SELECT * FROM rewards ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
if (empty($rewards)) {
    $rewards = [
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-1.jpg','title'=>'Túi Vải Thân Thiện','cost'=>'3000 điểm'],
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-2.jpg','title'=>'Bình Nước Thép','cost'=>'5000 điểm'],
        ['image'=>'/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/brand-3.jpg','title'=>'Bộ Dụng Cụ Tái Sử Dụng','cost'=>'7000 điểm'],
    ];
}

// build simple lookup from rewards table to enrich items (keyed by normalized title and image basename)
$rewardsIndex = [];
try {
  if (!empty($rewards) && isset($pdo)) {
    foreach ($rewards as $rr) {
      $key = strtolower(trim((string)($rr['title'] ?? $rr['name'] ?? $rr['reward_title'] ?? '')));
      if ($key !== '') $rewardsIndex[$key] = $rr;
      // also index by image basename when available
      if (!empty($rr['image'])) {
        $bn = strtolower(basename($rr['image']));
        if ($bn !== '') $rewardsIndex[$bn] = $rr;
      }
    }
  }
} catch (Throwable $__e) { /* ignore enrichment errors */ }

// helper: lấy số nguyên từ "3000 điểm"
function parse_cost_int($s) {
    // Xóa tất cả ký tự không phải số và dấu trừ
    $n = preg_replace('/[^\d\-]/', '', $s);
    // Kiểm tra chuỗi rỗng và chuyển đổi sang số
    if ($n === '') {
        return 0;
    }
    $value = (int)$n;
    // Đảm bảo giá trị dương
    return $value < 0 ? 0 : $value;
}

// xử lý POST: exchange hoặc cancel
$message = '';
// flash message support so redirects can show immediate feedback
if (!empty($_SESSION['flash_message'])) {
  $message = (string) $_SESSION['flash_message'];
  unset($_SESSION['flash_message']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    try {
        if (!empty($_POST['action']) && $_POST['action'] === 'exchange' && !empty($_POST['title'])) {
            $title = trim($_POST['title'] ?? '');
            $img = trim($_POST['image'] ?? '');
            // Xử lý cost từ chuỗi "3000 điểm" thành số
            $costRaw = trim($_POST['cost'] ?? '');
            $cost = parse_cost_int($costRaw);

            // Validation
            if (empty($title)) {
                throw new Exception('Thiếu thông tin quà tặng.');
            }
            if ($cost <= 0) {
                throw new Exception('Số xu không hợp lệ.');
            }

            // lấy điểm hiện tại
            $pdo->beginTransaction();
            $currentPts = 0;
            if ($pointsCol) {
                $stmt = $pdo->prepare("SELECT {$pointsCol} FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $currentPts = (int)$stmt->fetchColumn();
            } else {
                throw new Exception('Không tìm thấy cột điểm người dùng.');
            }

            if ($currentPts < $cost) throw new Exception('Không đủ điểm để đổi.');

            // tạo mã đổi thưởng và chèn redemptions + trừ điểm
            $redemptionCode = 'R' . str_pad(mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
            $ins = $pdo->prepare("INSERT INTO redemptions (user_id,reward_title,reward_cost,image,redemption_code,status) VALUES (?,?,?,?,?, 'pending')");
            $ins->execute([$userId, $title, $cost, $img ?: null, $redemptionCode]);

            $upd = $pdo->prepare("UPDATE users SET {$pointsCol} = GREATEST(0, {$pointsCol} - ?) WHERE id = ?");
            $upd->execute([$cost, $userId]);

      $pdo->commit();
  // show as pending to user (backend worker may later mark completed)
  $_SESSION['flash_message'] = 'Yêu cầu đổi thưởng đã được gửi và đang xử lý. Mã đổi thưởng của bạn: ' . $redemptionCode;
      header('Location: exchange.php'); exit();
        }

        if (!empty($_POST['action']) && $_POST['action'] === 'cancel' && !empty($_POST['redemption_id'])) {
            $rid = (int)$_POST['redemption_id'];
            // lấy red
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT reward_cost, status FROM redemptions WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$rid, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Không tìm thấy yêu cầu.');
            if ($row['status'] !== 'pending') throw new Exception('Chỉ có yêu cầu trạng thái pending mới được hủy.');

            $cost = (int)$row['reward_cost'];
            // cập nhật trạng thái
            $up = $pdo->prepare("UPDATE redemptions SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $up->execute([$rid, $userId]);

            // trả điểm lại
            if ($pointsCol) {
                $ref = $pdo->prepare("UPDATE users SET {$pointsCol} = {$pointsCol} + ? WHERE id = ?");
                $ref->execute([$cost, $userId]);
            }

      $pdo->commit();
      $_SESSION['flash_message'] = 'Hủy yêu cầu thành công. Điểm đã được hoàn lại.';
      header('Location: exchange.php'); exit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Lỗi: ' . $e->getMessage();
    }
}

// load user's pending redemptions
$pending = [];
$history = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM redemptions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all as $r) {
        if ($r['status'] === 'pending') $pending[] = $r;
        $history[] = $r;
    }
}

// <-- chèn bắt đầu
$userPoints = 0;
if (!empty($userId) && !empty($pointsCol) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT {$pointsCol} FROM `users` WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        $userPoints = $val !== false && $val !== null ? (int)$val : 0;
    } catch (Throwable $e) {
        error_log('Could not fetch user points: ' . $e->getMessage());
        $userPoints = 0;
    }
}
// <-- chèn kết thúc

// <-- chèn bắt đầu (lấy avatar user) -->
$userAvatar = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/user-placeholder.png';
if (!empty($userId) && isset($pdo)) {
    try {
        $colsUsers = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $pickAvatar = function(array $names) use ($colsUsers){
            foreach ($names as $n) if (in_array($n,$colsUsers,true)) return $n;
            return null;
        };
        $avatarCol = $pickAvatar(['avatar','photo','profile_pic','picture','image']);
        if ($avatarCol) {
            $stmt = $pdo->prepare("SELECT {$avatarCol} FROM `users` WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $av = $stmt->fetchColumn();
            if ($av) {
                $userAvatar = $av;
                if (!preg_match('#^https?://#i', $userAvatar) && strpos($userAvatar, '/') !== 0) {
                    $userAvatar = '/Expense_tracker-main/Expense_Tracker/' . ltrim($userAvatar, './');
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Avatar fetch: ' . $e->getMessage());
    }
}
// <-- chèn kết thúc
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đổi thưởng</title>
<style>
  :root{--green:#2e8b57;--muted:#6b6b6b;--bg:#f6fbf7;--card:#fff}
  *{box-sizing:border-box}
  body{font-family:Inter,Arial,Helvetica,sans-serif;margin:0;background:var(--bg);color:#222}
  .wrap{max-width:1100px;margin:28px auto;padding:18px}
  header.page{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
  header.page h1{margin:0;font-size:20px;color:var(--green)}
  .points{font-weight:700;color:var(--green);background:#eaf6f0;padding:8px 12px;border-radius:999px}
  .message{padding:10px;border-radius:8px;background:#fff8e6;border:1px solid #f0e0a8;margin-bottom:14px;color:#6a4b00}

  .cols{display:grid;grid-template-columns:1fr 2fr;gap:18px}
  @media(max-width:900px){ .cols{grid-template-columns:1fr; } }

  .panel{background:var(--card);border-radius:12px;padding:14px;box-shadow:0 6px 18px rgba(10,10,10,0.03)}
  .panel h2{margin:0 0 10px;font-size:16px;color:#233}

  /* pending list */
  .pending-list{display:flex;flex-direction:column;gap:10px}
  .pending-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border-radius:10px;border:1px solid #eef3ef;background:#fff}
  .pending-meta{color:var(--muted);font-size:13px}

  /* rewards grid */
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-top:6px}
  .card{background:#fff;border-radius:12px;padding:12px;border:1px solid #eee;display:flex;flex-direction:column;gap:10px;min-height:260px}
  .thumb{height:140px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fafafa;overflow:hidden}
  .thumb img{max-width:100%;max-height:100%;object-fit:contain;display:block}
  .title{font-weight:700;font-size:15px;color:#111}
  .cost{color:var(--green);font-weight:800}
  .actions{margin-top:auto;display:flex;gap:8px}
  .btn{border:0;padding:10px 12px;border-radius:8px;cursor:pointer;font-weight:700}
  .btn-primary{background:var(--green);color:#fff}
  .btn-ghost{background:transparent;border:1px solid rgba(0,0,0,0.06);color:#333}

  .small-link{font-size:13px;color:var(--muted);text-decoration:underline;cursor:pointer}

  footer.note{margin-top:18px;color:var(--muted);font-size:13px;text-align:center}
</style>
</head>
<body>
  <div class="wrap">
    <div style="background:#fff5e6;border:1px solid #ffd699;padding:12px 20px;border-radius:12px;margin-bottom:20px;text-align:center;width:100%">
      <p style="margin:0;font-size:16px;color:#333;font-weight:700">
        <i class="fas fa-mobile-alt" style="color:#0084ff;margin-right:8px"></i>
        Liên hệ Zalo: <a href="tel:0528041292" style="color:#0084ff;text-decoration:none;font-weight:800">0528041292</a> hoặc  để nhận quà
      </p>
    </div>

    <header class="page" style="align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" class="btn btn-ghost" style="padding:8px 12px;border-radius:10px;text-decoration:none;color:#333;border:1px solid rgba(0,0,0,0.06)">← Trang quà</a>
      </div>

      <div style="display:flex;align-items:center;gap:14px">
        <div class="points" style="display:flex;align-items:center;gap:8px;padding:8px 12px">
          <?php echo '<span style="font-weight:700;color:var(--green);">' . number_format($userPoints) . '</span> điểm'; ?>
        </div>

        <?php if (!empty($userId)): ?>
        <a href="/Expense_tracker-main/Expense_Tracker/profile.php" title="Trang cá nhân" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:inherit">
          <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="avatar" style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid rgba(0,0,0,0.04)">
        </a>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <?php if (!$userId): ?>
      <div class="panel"><p>Vui lòng <a href="/Expense_tracker-main/Expense_Tracker/login.php">đăng nhập</a> để đổi thưởng và lưu lịch sử.</p></div>
    <?php else: ?>

    <div class="cols">
      <div class="panel" aria-live="polite">
        <h2>Yêu cầu đang chờ xử lý</h2>
        <?php if (empty($pending)): ?>
          <div style="padding:12px;color:var(--muted)">Không có yêu cầu nào đang chờ.</div>
        <?php else: ?>
          <div class="pending-list">
    <?php foreach ($pending as $r):
      $img = htmlspecialchars(resolve_url_path($r['image'] ?? ''));
    $titleRaw = $r['reward_title'] ?? ($r['title'] ?? 'Quà');
    $title = htmlspecialchars($titleRaw);
    $cost = (int)($r['reward_cost'] ?? ($r['cost'] ?? 0));
    $created = htmlspecialchars($r['created_at'] ?? '');
  $rawStatus = isset($r['status']) ? (string)$r['status'] : 'pending';
        // map to colors and Vietnamese labels
        if ($rawStatus === 'completed') {
          $statusColor = '#2a8b49';
          $statusLabel = 'Đã đổi thành công';
        } elseif ($rawStatus === 'pending') {
          $statusColor = '#f0ad4e';
          $statusLabel = 'Đang xử lý';
        } elseif ($rawStatus === 'cancelled') {
          $statusColor = '#dc3545';
          $statusLabel = 'Đã hủy';
        } else {
          $statusColor = 'var(--muted)';
          $statusLabel = htmlspecialchars($rawStatus);
        }
        // try to enrich pending item with full reward metadata from rewardsIndex
        $desc = '';
        $store = '';
        $link = '/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php?item=' . rawurlencode($titleRaw);
        try {
          $lookupKey = strtolower(trim((string)$titleRaw));
          $imgKey = strtolower(basename((string)($r['image'] ?? '')));
          $en = null;
          if ($lookupKey !== '' && isset($rewardsIndex[$lookupKey])) $en = $rewardsIndex[$lookupKey];
          if (!$en && $imgKey !== '' && isset($rewardsIndex[$imgKey])) $en = $rewardsIndex[$imgKey];
          if ($en) {
            $desc = $en['description'] ?? $en['desc'] ?? $en['info'] ?? '';
            $store = $en['store'] ?? '';
            $link = $en['link'] ?? $link;
          }
          // allow redemption row to override
          if (!empty($r['description'])) $desc = $r['description'];
          if (!empty($r['store'])) $store = $r['store'];
          if (!empty($r['link'])) $link = $r['link'];
        } catch (Throwable $__e) { /* ignore */ }

    $data = json_encode(['title'=>$titleRaw,'reward_cost'=>$cost,'image'=>$img,'created_at'=>$created,'status'=>$rawStatus,'redemption_code'=>$r['redemption_code'] ?? '','desc'=>$desc,'store'=>$store,'link'=>$link]);
    ?>
      <div class="pending-item">
              <div style="display:flex;gap:12px;align-items:center">
                <div style="width:64px;height:64px;border-radius:8px;overflow:hidden;background:#fafafa;display:flex;align-items:center;justify-content:center;border:1px solid #f0f0f0">
                  <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" style="width:100%;height:100%;object-fit:cover">
                </div>
                <div>
                  <div style="font-weight:700;color:#111"><?php echo $title; ?></div>
                  <div class="pending-meta"><?php echo number_format($cost) . ' điểm • ' . $created; ?></div>
                  <?php if (!empty($r['redemption_code'])): ?>
                    <div style="color:#444;font-size:13px;margin-top:6px">Mã đổi thưởng: <strong><?php echo htmlspecialchars($r['redemption_code']); ?></strong></div>
                  <?php endif; ?>
                  <div style="color:<?php echo $statusColor; ?>;font-weight:700;margin-top:6px"><?php echo $statusLabel; ?></div>
                </div>
              </div>

              <div style="display:flex;gap:8px;align-items:center">
    <button class="btn btn-ghost detail-btn"
      data-image="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>"
      data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
      data-cost="<?php echo htmlspecialchars(number_format($cost) . ' điểm', ENT_QUOTES); ?>"
      data-store="<?php echo htmlspecialchars($store, ENT_QUOTES); ?>"
      data-desc="<?php echo htmlspecialchars($desc, ENT_QUOTES); ?>"
      data-link="<?php echo htmlspecialchars($link, ENT_QUOTES); ?>"
    >Xem chi tiết</button>
                <?php if ($rawStatus === 'pending'): ?>
                <form method="post" style="margin:0">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="redemption_id" value="<?php echo (int)$r['id']; ?>">
                  <button type="submit" class="btn" style="background:#f5f5f5;color:#333;border-radius:8px;padding:8px 10px">Hủy</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2>Danh sách quà (Đổi tại chỗ)</h2>
        <div class="grid" role="list">
          <?php foreach ($rewards as $r):
            // support both DB rows and fallback arrays
            $imgRaw = $r['image'] ?? $r['img'] ?? '';
            $img = htmlspecialchars(resolve_url_path($imgRaw));
            $imgUrl = resolve_url_path($imgRaw);
            $rawTitle = $r['title'] ?? $r['reward_title'] ?? $r['name'] ?? 'Quà';
            $title = htmlspecialchars($rawTitle);
            // Xử lý cost: chuẩn hoá thành chuỗi hiển thị như "3.000 điểm"
            $costRaw = $r['cost'] ?? $r['cost_points'] ?? $r['points'] ?? $r['reward_cost'] ?? '0';
            // parse_cost_int chuyển mọi dạng "3000 điểm" -> 3000
            $costInt = parse_cost_int((string)$costRaw);
            $costStr = number_format($costInt, 0, '.', ',') . ' điểm';
            $data = json_encode(['title'=>$r['title'] ?? $r['reward_title'] ?? $r['name'] ?? '','cost'=>$costStr,'image'=>$img]);
          ?>
          <div class="card" role="listitem">
            <div class="thumb"><img src="<?php echo $img; ?>" alt="<?php echo $title; ?>"></div>
            <div class="title"><?php echo $title; ?></div>
            <div class="cost"><?php echo htmlspecialchars($cost); ?></div>
            <div class="actions">
        <button class="btn-ghost detail-btn"
          type="button"
          data-image="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>"
          data-title="<?php echo htmlspecialchars($rawTitle, ENT_QUOTES); ?>"
          data-cost="<?php echo htmlspecialchars($costStr, ENT_QUOTES); ?>"
          data-store="<?php echo htmlspecialchars($r['store'] ?? '', ENT_QUOTES); ?>"
          data-desc="<?php echo htmlspecialchars($r['description'] ?? '', ENT_QUOTES); ?>"
          data-link="<?php echo htmlspecialchars($r['link'] ?? '/Expense_tracker-main/Expense_Tracker/reward_exchange/exchange.php?item=' . rawurlencode($rawTitle), ENT_QUOTES); ?>"
        >Xem chi tiết</button>
              <?php if (!empty($userId)): ?>
                <form method="post" style="margin:0;display:inline">
                  <input type="hidden" name="action" value="exchange">
                  <input type="hidden" name="title" value="<?php echo htmlspecialchars($rawTitle, ENT_QUOTES); ?>">
                  <input type="hidden" name="image" value="<?php echo htmlspecialchars($imgRaw, ENT_QUOTES); ?>">
                  <input type="hidden" name="cost" value="<?php echo htmlspecialchars($costStr, ENT_QUOTES); ?>">
                  <button class="btn btn-primary" type="submit">Đổi ngay</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <footer class="note">Ghi chú: các yêu cầu ở trạng thái "pending" có thể được hủy để trả lại điểm.</footer>
    

    <?php endif; ?>
  </div>
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
  <!-- reward.php style modal (richer detail popup) -->
  <div id="reward-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:11000;align-items:center;justify-content:center;padding:20px;">
    <div style="width:100%;max-width:760px;background:#fff;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;max-height:92vh;position:relative">
      <button id="reward-detail-close" aria-label="Đóng" style="position:absolute;right:14px;top:12px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#333;z-index=11010">&times;</button>
      <div style="overflow:auto;padding:18px;">
        <div style="height:360px;border-radius:8px;overflow:hidden;background:#f6f6f6;display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
          <img id="reward-detail-img" src="/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg" alt="Ảnh sản phẩm" style="max-width:100%;max-height:100%;object-fit:contain;display:block;">
        </div>

        <h2 id="reward-detail-title" style="margin:0 0 6px;font-size:22px;color:#111"></h2>
        <div style="color:#6b6b6b;margin-bottom:12px;display:flex;align-items:center;gap:12px">
          <div id="reward-detail-cost" style="font-weight:800;color:var(--green);font-size:20px"></div>
          <div id="reward-detail-store" style="color:#666;font-size:14px"></div>
        </div>

        <div style="background:#faf6f8;border-radius:10px;padding:14px;margin-bottom:12px">
          <h4 style="margin:0 0 8px;color:var(--green);font-weight:700">Thông tin sản phẩm</h4>
          <div id="reward-detail-desc" style="color:#333;line-height:1.6;white-space:pre-wrap">Chưa có thông tin chi tiết.</div>
        </div>
      </div>

      <div style="border-top:1px solid rgba(0,0,0,0.06);padding:12px 18px;display:flex;gap:10px;align-items:center;justify-content:flex-end;">
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

      if (img && img.charAt(0) !== '/' && !/^https?:\/\//i.test(img)) {
        img = '/Expense_tracker-main/Expense_Tracker/' + img.replace(/^\.\/*/, '');
      }

      by('reward-detail-img').src = img || '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
      by('reward-detail-title').textContent = title;
      by('reward-detail-cost').textContent = cost;
      by('reward-detail-store').textContent = store ? ('• ' + store) : '';
  by('reward-detail-desc').textContent = desc || 'Chưa có thông tin chi tiết.';

      by('reward-detail-modal').style.display = 'flex';
      e.preventDefault && e.preventDefault();
    });

    var close = by('reward-detail-close');
    var close2 = by('reward-detail-close-2');
    [close, close2].forEach(function(el){ if (!el) return; el.addEventListener('click', function(){ by('reward-detail-modal').style.display='none'; }); });

    window.addEventListener('keydown', function(e){ if (e.key === 'Escape') { var m = by('reward-detail-modal'); if (m && m.style.display === 'flex') m.style.display = 'none'; } });

    // click overlay để đóng
    var overlay = by('reward-detail-modal');
    if (overlay) overlay.addEventListener('click', function(e){ if (e.target === this) this.style.display='none'; });
  })();
  </script>

</body>
</html>
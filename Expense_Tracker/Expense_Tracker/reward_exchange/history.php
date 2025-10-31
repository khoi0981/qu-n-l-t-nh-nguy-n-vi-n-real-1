<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../src/config/db.php';

if (!isset($pdo)) die('DB not available.');
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// helper: chuẩn hoá đường dẫn ảnh (trả về URL bắt đầu bằng '/' hoặc full http)
function resolve_url_path($path){
    $placeholder = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
    if (!$path) return $placeholder;
    $p = trim($path);
    if (preg_match('#^https?://#i',$p)) return $p;
    if (strpos($p,'/') === 0) return $p; // already absolute from webroot

    // check uploads/rewards
    $uploadsDir = realpath(__DIR__ . '/../uploads/rewards');
    if ($uploadsDir) {
        $serverPath = $uploadsDir . DIRECTORY_SEPARATOR . $p;
        if (file_exists($serverPath)) {
            return '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($p);
        }
    }

    // check asset folder
    $assetCandidate = __DIR__ . '/asset/image/product-img/' . $p;
    if (file_exists($assetCandidate)) {
        return '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/' . rawurlencode($p);
    }

    return '/Expense_tracker-main/Expense_Tracker/' . ltrim($p, './');
}

// detect points column
$pointsCol = null;
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
    $prefer = ['points','reward_points','reward_point','score','balance','credits','coins','point'];
    foreach ($prefer as $p) if (in_array($p, $cols, true)) { $pointsCol = $p; break; }
} catch (Throwable $e) { /* ignore */ }

// fetch user points
$userPoints = 0;
if (!empty($userId) && !empty($pointsCol)) {
    try {
        $stmt = $pdo->prepare("SELECT {$pointsCol} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        $userPoints = $val !== false && $val !== null ? (int)$val : 0;
    } catch (Throwable $e) { error_log($e->getMessage()); }
}

// fetch user avatar if any
$userAvatar = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/user-placeholder.png';
if (!empty($userId)) {
    try {
        $colsUsers = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        $pickAvatar = function(array $names) use ($colsUsers){
            foreach ($names as $n) if (in_array($n,$colsUsers,true)) return $n;
            return null;
        };
        $avatarCol = $pickAvatar(['avatar','photo','profile_pic','picture','image']);
        if ($avatarCol) {
            $stmt = $pdo->prepare("SELECT {$avatarCol} FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $av = $stmt->fetchColumn();
            if ($av) $userAvatar = resolve_url_path($av);
        }
    } catch (Throwable $e) { error_log('Avatar fetch: '.$e->getMessage()); }
}

// load user's redemptions (only approved)
$rows = [];
if ($userId) {
  // only fetch redemptions that have been approved
  $stmt = $pdo->prepare("SELECT * FROM redemptions WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC");
  $stmt->execute([$userId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lịch sử đổi thưởng</title>
<style>
  :root{
    --green:#1fa463;
    --green-deep:#177a4f;
    --muted:#58626a;
    --bg:#eef8ef;
    --card:#ffffff;
    --glass: rgba(255,255,255,0.6);
    --accent: rgba(31,164,99,0.08);
    --radius:12px;
    --shadow: 0 8px 24px rgba(15,34,23,0.06);
  }

  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family:Inter, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(180deg, #f6fbf7 0%, #eef8ef 100%);
    color:#142121;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
  }

  .container{
    max-width:1100px;
    margin:28px auto;
    padding:22px;
  }

  .topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    margin-bottom:20px;
    padding:16px;
    border-radius:calc(var(--radius) + 4px);
    background: linear-gradient(90deg, rgba(31,164,99,0.06), rgba(31,164,99,0.03));
    box-shadow: var(--shadow);
    border: 1px solid rgba(26,100,60,0.06);
  }

  .topbar .left{
    display:flex;
    flex-direction:column;
    gap:8px;
  }

  .back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    background:transparent;
    border-radius:10px;
    text-decoration:none;
    color:var(--green-deep);
    font-weight:700;
    border:1px solid rgba(31,164,99,0.08);
  }

  h1{margin:0;font-size:20px;color:var(--green-deep);font-weight:800}
  .subtle{color:var(--muted);font-size:13px}

  .user-area{display:flex;align-items:center;gap:12px}
  .points{
    background:linear-gradient(180deg, rgba(250,255,250,1), rgba(235,250,238,1));
    color:var(--green-deep);
    padding:8px 14px;
    border-radius:999px;
    font-weight:800;
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid rgba(31,164,99,0.08);
    box-shadow: 0 2px 6px rgba(22,90,50,0.03);
  }

  .avatar{width:46px;height:46px;border-radius:50%;overflow:hidden;border:2px solid rgba(0,0,0,0.06)}
  .avatar img{width:100%;height:100%;object-fit:cover;display:block}

  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
    gap:16px;
    margin-top:12px;
  }

  .card{
    background:var(--card);
    border-radius:var(--radius);
    padding:12px;
    border:1px solid rgba(20,40,25,0.03);
    display:flex;
    gap:12px;
    align-items:flex-start;
    transition: transform .14s ease, box-shadow .14s ease;
    box-shadow: 0 6px 18px rgba(18,30,22,0.03);
    position:relative;
    overflow:hidden;
  }
  .card::before{
    content:'';
    position:absolute;
    left:0;top:0;bottom:0;
    width:6px;
    background: linear-gradient(180deg,var(--green),var(--green-deep));
    border-top-left-radius:var(--radius);
    border-bottom-left-radius:var(--radius);
  }
  .card:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(18,30,22,0.06)}

  .thumb{width:110px;height:110px;border-radius:10px;overflow:hidden;background:linear-gradient(180deg,#fbfff9,#f3fbf2);display:flex;align-items:center;justify-content:center;border:1px solid rgba(0,0,0,0.03)}
  .thumb img{width:100%;height:100%;object-fit:cover;display:block}

  .meta{flex:1;display:flex;flex-direction:column}
  .title{font-weight:800;font-size:16px;margin-bottom:6px;color:#0e3528}
  .info{color:var(--muted);font-size:13px;margin-bottom:8px}
  .cost{color:var(--green-deep);font-weight:800}

  .row{display:flex;align-items:center;justify-content:space-between;gap:12px}
  .status{
    padding:6px 10px;border-radius:999px;font-weight:700;font-size:13px;
    text-transform:capitalize;
  }
  .status.pending{background:rgba(255,244,230,0.9);color:#a35f00;border:1px solid rgba(243,217,182,0.9)}
  .status.completed{background:rgba(229,251,236,0.95);color:#0b6b39;border:1px solid rgba(196,236,208,0.95)}
  .status.cancelled{background:rgba(255,242,242,0.95);color:#a33b3b;border:1px solid rgba(247,214,214,0.95)}

  .actions{display:flex;gap:8px;margin-left:8px}
  .btn{border:0;padding:8px 12px;border-radius:10px;cursor:pointer;font-weight:700}
  .btn.ghost{background:transparent;border:1px solid rgba(0,0,0,0.06);color:#3b4b44}
  .btn.primary{background:var(--green);color:#fff}

  .empty{
    padding:28px;border-radius:12px;background:var(--card);border:1px solid rgba(0,0,0,0.03);
    color:var(--muted);text-align:center;margin-top:16px;
  }

  footer.center{margin-top:18px;text-align:center}
  footer.center .back{padding:10px 16px}

  @media(max-width:480px){
    .topbar{flex-direction:column;align-items:flex-start;padding:12px}
    .user-area{width:100%;justify-content:flex-end}
    .grid{grid-template-columns:1fr}
    .thumb{width:84px;height:84px}
  }
  /* modal simple */
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;padding:18px;z-index:12000}
  .modal-inner{width:100%;max-width:760px;background:#fff;border-radius:12px;padding:16px;max-height:92vh;overflow:auto}
  .modal-inner .hero{height:320px;border-radius:8px;background:#fafafa;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px}
  .modal-inner img{max-width:100%;max-height:100%;object-fit:contain;display:block}
  .close-x{position:absolute;right:18px;top:18px;border:0;background:transparent;font-size:20px;cursor:pointer}
</style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="left">
        <a class="back" href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php">← Trang Đổi thưởng</a>
        <h1>Lịch sử đổi thưởng</h1>
        <div class="subtle">Lưu trữ các yêu cầu đổi quà của bạn — trạng thái, ngày và số xu.</div>
      </div>

      <div class="user-area" aria-hidden="<?php echo empty($userId) ? 'true' : 'false'; ?>">
        <div class="points" title="Số điểm hiện có">
          <i class="fas fa-coins" style="margin-right:6px;"></i>
          <span><?php echo (int)$userPoints; ?> điểm</span>
        </div>

        <?php if (!empty($userId)): ?>
          <a href="/Expense_tracker-main/Expense_Tracker/profile.php" title="Trang cá nhân" style="text-decoration:none;color:inherit">
            <div class="avatar"><img src="<?php echo htmlspecialchars($userAvatar, ENT_QUOTES); ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;display:block"></div>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$userId): ?>
      <div class="empty">Vui lòng <a href="/Expense_tracker-main/Expense_Tracker/login.php">đăng nhập</a> để xem lịch sử.</div>
    <?php else: ?>

      <?php if (empty($rows)): ?>
        <div class="empty">Chưa có giao dịch đổi quà nào.</div>
      <?php else: ?>
        <div class="grid" role="list">
          <?php foreach ($rows as $r):
            $img = $r['image'] ?? '';
            $imgSrc = resolve_url_path($img);
            $title = $r['reward_title'] ?? 'Không tên';
            $cost = (int)($r['reward_cost'] ?? 0);
            $status = $r['status'] ?? 'pending';
            // map status to display class compatible with CSS (.completed/.cancelled/.pending)
            $statusClass = $status;
            if ($status === 'approved') $statusClass = 'completed';
            if ($status === 'rejected') $statusClass = 'cancelled';
            // display label in Vietnamese
            $displayStatus = 'Chưa xác định';
            if ($status === 'pending') $displayStatus = 'Đang chờ';
            if ($status === 'approved') $displayStatus = 'Đã xác nhận';
            if ($status === 'rejected') $displayStatus = 'Từ chối';
            $created = $r['created_at'] ?? '';

            // prepare object sent to modal: include server-normalized image path
            $rForJson = $r;
            $rForJson['__img_src'] = $imgSrc;

            // <-- chèn bắt đầu: enrich từ bảng rewards nếu tồn tại (dùng title để match)
            if (isset($pdo) && !empty($r['reward_title'])) {
                try {
                    $sq = $pdo->prepare("SELECT * FROM rewards WHERE title = ? LIMIT 1");
                    $sq->execute([$r['reward_title']]);
                    $rewardRow = $sq->fetch(PDO::FETCH_ASSOC);
                    if ($rewardRow) {
                        // copy common fields nếu chưa có trong $rForJson
                        foreach (['description','store','expiry','valid_until','link','image','cost','points'] as $k) {
                            if (!isset($rForJson[$k]) && isset($rewardRow[$k])) $rForJson[$k] = $rewardRow[$k];
                        }
                        // nếu rewardRow có image thì ưu tiên đường dẫn chuẩn hoá
                        if (!empty($rewardRow['image'])) {
                            $rForJson['__img_src'] = resolve_url_path($rewardRow['image']);
                        }
                    }
                } catch (Throwable $e) { /* ignore enrichment errors */ }
            }
            // <-- chèn kết thúc
          ?>
          <article class="card" role="listitem" aria-label="<?php echo htmlspecialchars($title); ?>">
            <div class="thumb">
              <img src="<?php echo htmlspecialchars($imgSrc, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($title); ?>" onerror="this.src='/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg'">
            </div>

            <div class="meta">
              <div class="title"><?php echo htmlspecialchars($title); ?></div>
              <div class="info"><?php echo htmlspecialchars($created); ?> · <span class="cost"><?php echo $cost; ?> điểm</span></div>

              <div class="row">
                <div><span class="status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($displayStatus); ?></span></div>
                <div class="actions">
                  <button class="btn ghost" onclick='openDetail(<?php echo json_encode($rForJson, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>Xem</button>
                  <?php if ($status === 'pending'): ?>
                    <form method="POST" action="exchange.php" style="display:inline-block;margin:0">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="redemption_id" value="<?php echo (int)$r['id']; ?>">
                      <button class="btn ghost" type="submit" onclick="return confirm('Bạn có chắc muốn hủy yêu cầu này?')">Hủy</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (!empty($r['note'])): ?><small><?php echo htmlspecialchars($r['note']); ?></small><?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

    <div style="margin-top:18px;text-align:center"><a href="/Expense_tracker-main/Expense_Tracker/reward_exchange/reward.php" class="back">Quay lại Đổi thưởng</a></div>
  </div>

  <!-- modal -->
  <div id="history-modal" class="modal" aria-hidden="true">
    <div class="modal-inner" role="dialog" aria-modal="true">
      <button class="close-x" onclick="closeModal()">✕</button>

      <div class="hero"><img id="modal-img" src="" alt=""></div>

      <h3 id="modal-title"></h3>
      <div id="modal-meta" style="color:var(--muted);margin-bottom:6px"></div>
      <div id="modal-store" style="color:#2f6b44;font-weight:700;margin-bottom:8px"></div>
      <div id="modal-desc" style="line-height:1.6;color:#333;white-space:pre-wrap;margin-bottom:12px"></div>
      <div id="modal-expiry" style="color:var(--muted);font-size:14px;margin-bottom:12px"></div>

      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button class="btn ghost" onclick="closeModal()">Đóng</button>
      </div>
    </div>
  </div>

<script>
function openDetail(raw){
  var item = (typeof raw === 'string') ? JSON.parse(raw) : raw;

  // image: ưu tiên __img_src
  var img = item.__img_src || item.image || '';
  if (img && img.charAt(0) !== '/' && !/^https?:\/\//i.test(img)) img = '/Expense_tracker-main/Expense_Tracker/' + img.replace(/^\.\/*/,'');
  var imgEl = document.getElementById('modal-img');
  imgEl.onerror = function(){
    imgEl.src = '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';
  };
  imgEl.src = img || '/Expense_tracker-main/Expense_Tracker/reward_exchange/asset/image/product-img/placeholder.jpg';

  // populate fields (try many possible keys)
  document.getElementById('modal-title').textContent = item.reward_title || item.title || item.name || '';
  var cost = (item.reward_cost || item.cost || item.points || item.points === 0) ? (parseInt(item.reward_cost || item.cost || item.points) + ' điểm') : '';
  document.getElementById('modal-meta').textContent = (item.created_at ? item.created_at + ' · ' : '') + cost;

  document.getElementById('modal-store').textContent = item.store || item.vendor || '';
  document.getElementById('modal-desc').textContent = item.note || item.description || item.long_description || item.info || '';

  var expiry = item.expiry || item.valid_until || item.expires || '';
  document.getElementById('modal-expiry').textContent = expiry ? ('Hạn: ' + expiry) : '';

  // Removed exchange link - modal is now view-only

  document.getElementById('history-modal').style.display = 'flex';
  document.getElementById('history-modal').setAttribute('aria-hidden','false');
}
function closeModal(){
  document.getElementById('history-modal').style.display = 'none';
  document.getElementById('history-modal').setAttribute('aria-hidden','true');
}
document.getElementById('history-modal').addEventListener('click', function(e){ if (e.target === this) closeModal(); });
window.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
<?php
session_set_cookie_params(['path' => '/']);
session_start();
include '../../src/config/db.php';
include '../includes/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

// kiểm tra $pdo tồn tại
if (!isset($pdo) || !$pdo) {
    echo '<div style="color:red">Chưa có kết nối PDO. Kiểm tra file config db include và biến $pdo.</div>';
    exit;
}

// Lấy danh sách cột của bảng volunteers (an toàn)
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM volunteers");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    $cols = [];
}

// Thống kê cơ bản (an toàn)
$volunteerCount = (int)@$pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn();
$eventCount     = (int)@$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$newsCount      = (int)@$pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$rewardCount    = (int)@$pdo->query("SELECT COUNT(*) FROM rewards")->fetchColumn();

// (Tùy chọn) số liệu mẫu theo tháng
$labelsMonths = ['Tháng -5','Tháng -4','Tháng -3','Tháng -2','Tháng -1','Tháng'];
$sampleTrend = [
    max(0, $volunteerCount - 3),
    max(0, $volunteerCount - 2),
    max(0, $volunteerCount - 1),
    $volunteerCount,
    max(0, intval($volunteerCount * 0.9)),
    $volunteerCount
];

// Tính số tình nguyện viên liên kết với user hiện tại (an toàn)
$userId = (int)($_SESSION['user_id'] ?? 0);
$linkedVols = 0;
if ($userId) {
    try {
        if (in_array('user_id', $cols, true)) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM volunteers WHERE user_id = ?');
            $stmt->execute([$userId]);
            $linkedVols = (int)$stmt->fetchColumn();
        } else {
            // kiểm tra bảng pivot user_volunteers
            $tbl = $pdo->query("SHOW TABLES LIKE 'user_volunteers'")->fetchColumn();
            if ($tbl) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_volunteers WHERE user_id = ?');
                $stmt->execute([$userId]);
                $linkedVols = (int)$stmt->fetchColumn();
            } else {
                // fallback: không có liên kết (hoặc bạn có thể set $linkedVols = $volunteerCount)
                $linkedVols = 0;
            }
        }
    } catch (Throwable $e) {
        $linkedVols = 0;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - Quản trị</title>

<link href="assets/css/font-awesome.min.css" rel="stylesheet">
<link href="assets/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --accent: #2e8b57;
  --accent-2:#196f45;
  --muted:#6b6b6b;
  --card-bg:#ffffff;
  --page-bg:#f5f7fb;
  --radius:12px;
}
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;color:#233; background:var(--page-bg);margin:0}
.app{
  display:grid;
  grid-template-columns: 260px 1fr;
  min-height:100vh;
}

/* sidebar (kept simple) */
.sidebar{
  background:#152022;color:#fff;padding:24px;
}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.brand .logo{width:44px;height:44px;border-radius:10px;background:linear-gradient(180deg,var(--accent),var(--accent-2));display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff}
.brand .title{font-weight:800;font-size:16px}

/* main */
.main{padding:28px}
.header-row{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.header-row h1{margin:0;font-size:20px;color:var(--accent)}
.header-row .subtitle{color:var(--muted);font-size:13px}

/* stats */
.stats{display:flex;gap:18px;flex-wrap:wrap;margin-bottom:22px}
.card{
  background:var(--card-bg);
  border-radius:var(--radius);
  padding:18px 18px 18px 90px; /* added left padding so the figure on the left doesn't overlap content */
  box-shadow:0 8px 30px rgba(20,30,40,0.06);
  flex:1; min-width:180px;
  display:flex;flex-direction:column;align-items:flex-start;gap:6px;
  position:relative; /* allow absolute-placed figure */
}
.card .icon{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;rgba(46,139,87,0.08);color:var(--accent);font-size:20px}
.card .figure{position:absolute;top:10px;left:12px;width:64px;height:64px;opacity:0.95;pointer-events:none}
.card .figure img,.card .figure svg{width:100%;height:100%;display:block}
.card h2{margin:0;font-size:26px;color:var(--accent)}
.card p{margin:0;color:var(--muted);font-weight:600;font-size:12px}

/* layout for charts & table */
.grid{
  display:grid;
  grid-template-columns: 2fr 1fr;
  gap:18px;
  margin-bottom:18px;
}
.chart-card{padding:18px;background:var(--card-bg);border-radius:var(--radius);box-shadow:0 8px 30px rgba(20,30,40,0.06)}
.table-card{padding:16px;background:var(--card-bg);border-radius:var(--radius);box-shadow:0 8px 30px rgba(20,30,40,0.06)}

/* quick links */
.quick-links ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
.quick-links a{display:inline-block;padding:8px 12px;border-radius:8px;background:linear-gradient(90deg,rgba(46,139,87,0.08),transparent);color:var(--accent);text-decoration:none;font-weight:600}

/* responsive */
@media (max-width:980px){
  .app{grid-template-columns:1fr}
  .grid{grid-template-columns:1fr}
  .stats{flex-direction:column}
}
footer{margin-top:18px;color:#999;font-size:13px;text-align:right}
</style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">EP</div>
            <div class="title">Khu vực quản trị</div>
        </div>
        <nav>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px">
                <li><a href="dashboard.php" style="color:#cfe9df;text-decoration:none;padding:8px;border-radius:8px;display:block">Bảng điều khiển</a></li>
                <li><a href="manage_volunteers.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Tình nguyện viên</a></li>
                <li><a href="manage_events.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Hoạt động</a></li>
                <li><a href="manage_news.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Tin tức</a></li>
                <li><a href="manage_rewards.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Phần thưởng</a></li>
                <li><a href="redeem_rewards.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Quản lý đổi quà</a></li>
                <li style="margin-top:12px"><a href="../../logout.php" style="color:#fff;text-decoration:none;padding:8px;display:block">Đăng xuất</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main">
        <div class="header-row">
            <div>
                <h1>Trang quản trị</h1>
                <div class="subtitle">Tổng quan nhanh về dự án bảo vệ môi trường</div>
            </div>

            <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
                <a href="../../index.php" style="background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:600" title="Về Trang Chủ">Về Trang Chủ</a>
            </div>
        </div>

        <div class="stats">
            <div class="card">
                <div class="figure"><img src="../../Assets/images/stats-volunteers.svg" alt=""/></div>
                <div class="icon"><i class="fa fa-users"></i></div>
                <h2><?php echo $volunteerCount; ?></h2>
                <p>Tình nguyện viên</p>
            </div>
            <div class="card">
                <div class="figure"><img src="../../Assets/images/stats-events.svg" alt=""/></div>
                <div class="icon"><i class="fa fa-calendar"></i></div>
                <h2><?php echo $eventCount; ?></h2>
                <p>Hoạt động</p>
            </div>
            <div class="card">
                <div class="figure"><img src="../../Assets/images/stats-news.svg" alt=""/></div>
                <div class="icon"><i class="fa fa-newspaper-o"></i></div>
                <h2><?php echo $newsCount; ?></h2>
                <p>Tin tức</p>
            </div>
            <div class="card">
                <div class="figure"><img src="../../Assets/images/stats-rewards.svg" alt=""/></div>
                <div class="icon"><i class="fa fa-gift"></i></div>
                <h2><?php echo $rewardCount; ?></h2>
                <p>Phần quà</p>
            </div>
        </div>

        <div class="grid">
            <div class="chart-card">
                <h3 style="margin-top:0;color:var(--accent)">Tình nguyện viên theo thời gian</h3>
                <canvas id="lineChart" height="120"></canvas>
                <hr style="margin:16px 0">
                <h4 style="margin:0;color:var(--muted)">Phân bố hiện tại</h4>
                <canvas id="donutChart" height="120"></canvas>
            </div>

            <div class="table-card">
                <h3 style="color:var(--accent);margin-top:0">Liên kết nhanh</h3>
                <div class="quick-links">
                    <ul>
                        <li><a href="manage_volunteers.php"><i class="fa fa-users"></i> Quản lý Tình nguyện viên</a></li>
                        <li><a href="manage_events.php"><i class="fa fa-calendar"></i> Quản lý Hoạt động</a></li>
                        <li><a href="manage_news.php"><i class="fa fa-newspaper-o"></i> Quản lý Tin tức</a></li>
                        <li><a href="manage_rewards.php"><i class="fa fa-gift"></i> Quản lý Phần thưởng</a></li>
                    </ul>
                </div>

                <hr style="margin:16px 0">

                <h4 style="color:var(--accent)">Tóm tắt</h4>
                <table style="width:100%;border-collapse:collapse;margin-top:8px">
                    <tr><th style="text-align:left;padding:8px;background:#f3f7f5">Tình nguyện viên</th><td style="padding:8px"><?php echo $volunteerCount; ?></td></tr>
                    <tr><th style="text-align:left;padding:8px;background:#f3f7f5">Hoạt động</th><td style="padding:8px"><?php echo $eventCount; ?></td></tr>
                    <tr><th style="text-align:left;padding:8px;background:#f3f7f5">Tin tức</th><td style="padding:8px"><?php echo $newsCount; ?></td></tr>
                    <tr><th style="text-align:left;padding:8px;background:#f3f7f5">Phần quà</th><td style="padding:8px"><?php echo $rewardCount; ?></td></tr>
                </table>

                <div class="small" style="margin-top:12px">
                    Số tình nguyện viên liên kết: <?php echo $linkedVols; ?>
                </div>
            </div>
        </div>

        <footer>&copy; <?php echo date('Y'); ?> GREENSTEP</footer>
    </main>
</div>

<script>
/* Line chart (mẫu dữ liệu) */
const ctxLine = document.getElementById('lineChart').getContext('2d');
new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labelsMonths); ?>,
        datasets: [{
            label: 'Tình nguyện viên (mẫu)',
            data: <?php echo json_encode($sampleTrend); ?>,
            borderColor: 'rgba(46,139,87,0.95)',
            backgroundColor: 'rgba(46,139,87,0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
    }
});

/* Donut chart (phân bố các mục hiện có) */
const ctxDonut = document.getElementById('donutChart').getContext('2d');
new Chart(ctxDonut, {
    type: 'doughnut',
    data: {
        labels: ['Tình nguyện viên','Hoạt động','Tin tức','Phần quà'],
        datasets: [{
            data: [<?php echo $volunteerCount;?>, <?php echo $eventCount;?>, <?php echo $newsCount;?>, <?php echo $rewardCount;?>],
            backgroundColor: [
                'rgba(46,139,87,0.9)',
                'rgba(57,173,180,0.9)',
                'rgba(100,181,246,0.9)',
                'rgba(255,193,7,0.9)'
            ],
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>
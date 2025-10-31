<?php
session_start();
require_once __DIR__ . '/../src/config/db.php';

// Add sorting and pagination parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5; // Hiển thị 5 tin tức mỗi trang
$offset = ($page - 1) * $per_page;

// --- thay thế phần truy vấn cũ ---
try {
    // lấy danh sách cột của bảng news
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news'")
                ->fetchAll(PDO::FETCH_COLUMN);

    $pick = function(array $names) use ($cols) {
        foreach ($names as $n) {
            if (in_array($n, $cols)) return $n;
        }
        return null;
    };

    $idCol       = $pick(['id', 'news_id']) ?? die('No id column in news table');
    $titleCol    = $pick(['title', 'headline', 'name']) ?? die('No title column in news table');
    $excerptCol  = $pick(['excerpt', 'summary']);
    $contentCol  = $pick(['content', 'body', 'description']);
    $imageCol    = $pick(['image', 'featured_image', 'thumbnail']);
    $createdCol  = $pick(['created_at', 'created', 'date', 'published_at']);

    $select = [];
    $select[] = "$idCol AS id";
    $select[] = "$titleCol AS title";
    if ($excerptCol) {
        $select[] = "$excerptCol AS excerpt";
    } elseif ($contentCol) {
        // tạo excerpt từ content nếu không có excerpt
        $select[] = "LEFT($contentCol, 300) AS excerpt";
    } else {
        $select[] = "'' AS excerpt";
    }
    $select[] = ($imageCol ? "$imageCol AS image" : "NULL AS image");
    $select[] = ($createdCol ? "$createdCol AS created_at" : "NULL AS created_at");

    // Add view count column 
    $viewsCol = "views";
    
    // Modify select array to include views
    $select[] = "$viewsCol AS views";

    // Get total count for pagination
    $total = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
    $total_pages = ceil($total / $per_page);

    $orderBy = $createdCol ?? $idCol; // fallback to ID if no created date column

    // Modify order clause based on sort parameter
    $orderClause = match($sort) {
        'popular' => "ORDER BY views DESC",
        'oldest' => "ORDER BY $orderBy ASC", 
        default => "ORDER BY $orderBy DESC" // newest
    };

    // Modified query with LIMIT and OFFSET
    $sql = "SELECT " . implode(', ', $select) . " FROM news $orderClause LIMIT $per_page OFFSET $offset";

    $stmt = $pdo->query($sql);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // fallback an toàn: không ném fatal, hiển thị log ngắn (có thể thay bằng file log)
    error_log("News list error: " . $e->getMessage());
    $articles = [];
    $total_pages = 0;
}

// helper function: định nghĩa 1 lần để tránh redeclare trong vòng lặp
if (!function_exists('get_first_image_src')) {
    function get_first_image_src($html){
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) return $m[1];
        return null;
    }
}

// helper resolve media path (uploads, local img folder, absolute URLs)
if (!function_exists('resolve_media_path')) {
    function resolve_media_path($val){
        $default = '/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg';
        $v = trim((string)$val);
        if ($v === '') return null;
        // full URL
        if (preg_match('~^https?://~i', $v)) return $v;
        // already absolute web path
        if (strpos($v, '/') === 0) return $v;
        // contains uploads path already (relative)
        if (stripos($v, 'uploads/') !== false) return '/' . ltrim($v, '/');

        // check common local locations (uploads/news, uploads, news_web/img)
        $try = [];
        // uploads/news (project root)/uploads/news/<file>
        $try[dirname(__DIR__) . '/uploads/news/' . $v] = '/Expense_tracker-main/Expense_Tracker/uploads/news/' . rawurlencode($v);
        // uploads/<file>
        $try[dirname(__DIR__) . '/uploads/' . $v] = '/Expense_tracker-main/Expense_Tracker/uploads/' . rawurlencode($v);
        // news_web/img/<file>
        $try[__DIR__ . '/img/' . $v] = '/Expense_tracker-main/Expense_Tracker/news_web/img/' . rawurlencode($v);

        foreach ($try as $local => $web) {
            if (@file_exists($local)) return $web;
        }

        // last resort: return filename under news_web img (may 404 but predictable)
        return '/Expense_tracker-main/Expense_Tracker/news_web/img/' . rawurlencode($v);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

    <head>
        <meta charset="utf-8">
        <title>GreenNews - Cập nhật môi trường</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries & CSS (server-absolute paths) -->
        <link href="/Expense_tracker-main/Expense_Tracker/news_web/lib/animate/animate.min.css" rel="stylesheet">
        <link href="/Expense_tracker-main/Expense_Tracker/news_web/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
        <link href="/Expense_tracker-main/Expense_Tracker/news_web/css_news/bootstrap.min.css" rel="stylesheet">
        <link href="/Expense_tracker-main/Expense_Tracker/news_web/css_news/style.css" rel="stylesheet">

        <style>
            /* small overrides to match green theme */
            :root { --brand: #2e8b57; --brand-dark: #246b45; }
            .text-primary, .btn-primary { background-color: var(--brand); border-color: var(--brand); color: #fff; }
            .btn-primary:hover { background-color: var(--brand-dark); border-color: var(--brand-dark); }

            /* ...existing styles... */
    
            .btn-group .btn-success {
                background-color: var(--brand);
                border-color: var(--brand);
            }
    
            .btn-group .btn-outline-success {
                color: var(--brand);
                border-color: var(--brand);
            }
    
            .btn-group .btn-outline-success:hover {
                background-color: var(--brand);
                border-color: var(--brand);
                color: #fff;
            }
    
            .pagination .page-item.active .page-link {
                background-color: var(--brand);
                border-color: var(--brand);
            }
    
            .pagination .page-link {
                color: var(--brand);
            }
    
            .pagination .page-link:hover {
                color: var(--brand-dark);
            }
        </style>
    </head>

    <body>

        <!-- Header (copied from index.html) -->
        <div class="container-fluid sticky-top px-0">
    <!-- Top bar -->
    <div class="container-fluid topbar bg-gradient d-none d-lg-block" style="background:linear-gradient(90deg,#f3fbf6,#eef6f2);">
        <div class="container px-0">
            <div class="topbar-top d-flex justify-content-between align-items-center flex-lg-wrap">
                <div class="d-flex align-items-center">
                    <span class="rounded-circle btn-sm-square bg-success me-2">
                        <i class="fas fa-leaf text-white"></i>
                    </span>
                        <div class="pe-3 border-end border-secondary d-flex align-items-center">
                            <p class="mb-0 text-muted fs-6 fw-normal">Tin nổi bật</p>
                        </div>
                    <div class="overflow-hidden ms-3" style="max-width:720px;">
                        <div id="note" class="ps-2">
                            <img src="/Expense_tracker-main/Expense_Tracker/news_web/img/features-fashion.jpg" class="img-fluid rounded-circle border border-2 border-success me-2" style="width:30px;height:30px;" alt="">
                            <a href="#" class="link-hover"><span class="text-muted">Community reforestation drive this weekend — join local volunteers.</span></a>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center text-muted">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span><?php echo date('l, M d, Y'); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-2 text-muted">Theo dõi:</span>
                        <a href="#" class="me-2 link-hover text-muted"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2 link-hover text-muted"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="link-hover text-muted"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main navbar -->
    <div class="container-fluid bg-light">
        <div class="container px-0">
            <nav class="navbar navbar-light navbar-expand-xl">
                <a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" class="navbar-brand d-flex align-items-start mt-2">
                    <div style="line-height:1;">
                        <p class="text-success display-6 mb-0" style="font-weight:700;">GreenNews</p>
                        <small class="text-muted fw-normal" style="letter-spacing:8px;">ENVIRONMENT</small>
                    </div>
                </a>

                <button class="navbar-toggler py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="fa fa-bars text-success"></span>
                </button>   

                <div class="collapse navbar-collapse bg-light py-3" id="navbarCollapse">
                    <div class="d-flex align-items-center gap-3 border-top pt-3 pt-xl-0 ms-auto justify-content-end" style="width:100%;">
                        <!-- moved to right with ms-auto + justify-content-end -->
                         <div class="d-flex align-items-center">
                             <img src="/Expense_tracker-main/Expense_Tracker/news_web/img/weather-icon.png" class="img-fluid me-2" style="width:36px;" alt="weather">
                             <div class="text-muted">
                                 <strong class="d-block">27°C</strong>
                                 <small>HANOI, VN</small>
                             </div>
                         </div>

                         <button class="btn btn-outline-success rounded-circle p-2" data-bs-toggle="modal" data-bs-target="#searchModal" aria-label="Tìm kiếm">
                             <i class="fas fa-search text-success"></i>
                         </button>

                         <!-- Exit to main site button -->
                         <a href="/Expense_tracker-main/Expense_Tracker/index.php" class="btn btn-success ms-2" title="Về trang chủ chính">
                             <i class="fas fa-home me-1"></i> Trang chủ
                         </a>
                    </div>
                </div>
            </nav>
        </div>
    </div>
</div>
<!-- Header End -->


        <!-- Search Modal -->
        <div class="modal fade" id="searchModal" tabindex="-1">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content rounded-0">
                    <div class="modal-header">
                        <h5 class="modal-title">Tìm kiếm GreenNews</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body d-flex align-items-center">
                        <div class="input-group w-75 mx-auto d-flex">
                            <input type="search" class="form-control p-3" placeholder="Tìm chủ đề môi trường">
                            <span class="input-group-text p-3"><i class="fa fa-search"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Search Modal End -->


        <!-- Main content -->
        <main class="container-fluid py-4">
            <div class="container py-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <!-- Thêm phần sorting và phân trang vào đây, trước danh sách bài viết -->
                        <div class="container mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group">
                                    <a href="?sort=newest<?= $page > 1 ? '&page=' . $page : '' ?>" class="btn btn<?= $sort === 'newest' ? '' : '-outline' ?>-success">Mới nhất</a>
                                    <a href="?sort=popular<?= $page > 1 ? '&page=' . $page : '' ?>" class="btn btn<?= $sort === 'popular' ? '' : '-outline' ?>-success">Phù hợp nhất</a>
                                    <a href="?sort=oldest<?= $page > 1 ? '&page=' . $page : '' ?>" class="btn btn<?= $sort === 'oldest' ? '' : '-outline' ?>-success">Cũ nhất</a>
                                </div>
                                
                                <!-- Hiển thị tổng số trang -->
                                <div class="text-muted mt-2">
                                    Trang <?= $page ?>/<?= $total_pages ?> (<?= $total ?> tin tức)
                                </div>
                            </div>

                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Phân trang" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?sort=<?= $sort ?>&page=<?= $page-1 ?>">&laquo; Trước</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?sort=<?= $sort ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?sort=<?= $sort ?>&page=<?= $page+1 ?>">Tiếp &raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($articles)): ?>
                            <?php foreach ($articles as $a): ?>
                                <article class="mb-4 bg-light rounded p-3">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-4">
                                            <?php
                                            $img = $a['image'] ?? '';
                                            $imgFromContent = get_first_image_src($a['excerpt'] ?? $a['content'] ?? '');
                                            if ($imgFromContent) {
                                                $imgSrc = $imgFromContent;
                                            } else {
                                                if ($img) {
                                                    $imgSrc = resolve_media_path($img) ?: '/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg';
                                                } else {
                                                    $imgSrc = '/Expense_tracker-main/Expense_Tracker/news_web/img/news-1.jpg';
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="img-fluid rounded" alt="">
                                        </div>
                                        <div class="col-md-8">
                                            <h3><a class="link-hover" href="/Expense_tracker-main/Expense_Tracker/news_web/detail-page.php?id=<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['title']); ?></a></h3>
                                            <p class="text-muted small"><?php echo date('M d, Y', strtotime($a['created_at'])); ?></p>
                                            <p class="text-muted small">
                                                <?php $views = isset($a['views']) ? (int)$a['views'] : 0; ?>
                                                <i class="fas fa-eye text-success me-1" aria-hidden="true"></i><?php echo number_format($views); ?> lượt xem
                                            </p>
                                            <p>
                                                <?php
                                                // hiển thị excerpt an toàn (loại bỏ tag nguy hiểm, hoặc chỉ giữ vài tag cơ bản)
                                                $safe_excerpt = strip_tags($a['excerpt'] ?? '', '<p><br><a><strong><em>');
                                                echo $safe_excerpt;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Không tìm thấy bài viết.</p>
                        <?php endif; ?>
                    </div>

                    <aside class="col-lg-4">
                        <div class="p-3 rounded bg-light mb-4">
                            <h5 class="mb-3">Sự kiện sắp tới</h5>
                            <?php
                            try {
                                $cols = $pdo->query(
                                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'"
                                )->fetchAll(PDO::FETCH_COLUMN);

                                $pick = function(array $names) use ($cols) {
                                    foreach ($names as $n) {
                                        if (in_array($n, $cols)) return $n;
                                    }
                                    return null;
                                };

                                $titleCol = $pick(['title','name','event_name']) ?? ($cols[1] ?? $cols[0] ?? null);
                                if (!$titleCol) throw new Exception('No title column in events table');

                                $dateCol = $pick(['event_date','date','start_date','scheduled_at','datetime']) ?? null;

                                $select = [$titleCol . " AS title"];
                                $select[] = ($dateCol ? "$dateCol AS event_date" : "NULL AS event_date");
                                $orderBy = $dateCol ?? $titleCol;

                                $sql = "SELECT " . implode(', ', $select) . " FROM events ORDER BY $orderBy ASC LIMIT 5";
                                $ev = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {
                                error_log("Events list error: " . $e->getMessage());
                                $ev = [];
                            }

                            if ($ev):
                                foreach ($ev as $e): ?>
                                    <div class="mb-2">
                                        <strong><?php echo htmlspecialchars($e['title']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo !empty($e['event_date']) ? date('M d, Y', strtotime($e['event_date'])) : 'TBA'; ?>
                                        </div>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <div>No upcoming events.</div>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 rounded bg-light">
                            <h5 class="mb-3">Liên kết nhanh</h5>
                            <ul class="list-unstyled">
                                <li><a href="/Expense_tracker-main/Expense_Tracker/news_web/volunteer.php" class="link-hover">Tình nguyện</a></li>
                                <li><a href="/Expense_tracker-main/Expense_Tracker/news_web/events.php" class="link-hover">Sự kiện</a></li>
                                <li><a href="/Expense_tracker-main/Expense_Tracker/news_web/contact.php" class="link-hover">Liên hệ</a></li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
        <!-- Main content End -->


        <!-- Footer: reuse main site footer -->
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
        <!-- Footer End -->


        <!-- Scripts (server-absolute paths) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="/Expense_tracker-main/Expense_Tracker/news_web/js/bootstrap.bundle.min.js"></script>
        <script src="/Expense_tracker-main/Expense_Tracker/news_web/lib/easing/easing.min.js"></script>
        <script src="/Expense_tracker-main/Expense_Tracker/news_web/lib/waypoints/waypoints.min.js"></script>
        <script src="/Expense_tracker-main/Expense_Tracker/news_web/lib/owlcarousel/owl.carousel.min.js"></script>
        <script src="/Expense_tracker-main/Expense_Tracker/news_web/js/news_custom.js"></script>
    </body>

</html>
<?php
session_start();
include '../config/db.php';
include '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Determine which reward table exists (reward_items or rewards)
$rewardTable = null;
try {
    $check = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('reward_items','rewards')");
    $check->execute();
    $tables = $check->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('reward_items', $tables, true)) {
        $rewardTable = 'reward_items';
    } elseif (in_array('rewards', $tables, true)) {
        $rewardTable = 'rewards';
    }
} catch (PDOException $ex) {
    // ignore; we'll handle below
}

$allowedStatuses = ['all','pending','approved','rejected'];
// If an id is provided we show the detail/confirm view; otherwise show the list
$redemption = null;
$db_error = null;
if (isset($_GET['id'])) {
    $redemption_id = $_GET['id'];

    $sql = "SELECT r.*, u.username,
              r.reward_title AS reward_name,
              r.reward_cost as points_required
              FROM redemptions r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$redemption_id]);
        $redemption = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
        $redemption = null;
    }

    // Tự động tạo mã đổi thưởng nếu chưa có
    if ($redemption && empty($redemption['redemption_code'])) {
        try {
            $code = 'R' . str_pad($redemption['id'], 6, '0', STR_PAD_LEFT);
            $update = $pdo->prepare("UPDATE redemptions SET redemption_code = ? WHERE id = ? AND (redemption_code IS NULL OR redemption_code = '')");
            $update->execute([$code, $redemption_id]);
            // Refresh data
            $stmt->execute([$redemption_id]);
            $redemption = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $db_error = "Không thể tạo mã đổi thưởng: " . $e->getMessage();
        }
    }

    // Handle update form submission on the detail page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $redemption) {
        // Đảm bảo status không bao giờ rỗng
        $status = $_POST['status'];
        if (empty($status)) {
            $status = 'pending';
        }
        $notes = $_POST['notes'] ?? '';
        try {
            // Kiểm tra cột status
            $columnCheck = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                                        FROM information_schema.COLUMNS 
                                        WHERE TABLE_SCHEMA = DATABASE() 
                                        AND TABLE_NAME = 'redemptions' 
                                        AND COLUMN_NAME = 'status'");
            $columnCheck->execute();
            $statusColumn = $columnCheck->fetch(PDO::FETCH_ASSOC);
            error_log("[redeem_rewards][DEBUG] Thông tin cột status: " . json_encode($statusColumn));
            // kiểm tra xem các cột admin_notes, updated_at có tồn tại không
            $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'redemptions' AND COLUMN_NAME IN ('admin_notes','updated_at')");
            $colStmt->execute();
            $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
            $hasAdminNotes = in_array('admin_notes', $cols, true);
            $hasUpdatedAt = in_array('updated_at', $cols, true);

            // build dynamic update
            $setParts = [];
            $params = [];
            $setParts[] = 'status = ?';
            $params[] = $status;
            if ($hasAdminNotes) {
                $setParts[] = 'admin_notes = ?';
                $params[] = $notes;
            }
            if ($hasUpdatedAt) {
                $setParts[] = 'updated_at = NOW()';
            }

            // Kiểm tra trạng thái trước khi cập nhật
            $checkStmt = $pdo->prepare("SELECT status FROM redemptions WHERE id = ?");
            $checkStmt->execute([$redemption_id]);
            $oldStatus = $checkStmt->fetchColumn();
            error_log("[redeem_rewards][DEBUG] Trạng thái cũ: " . $oldStatus);
            error_log("[redeem_rewards][DEBUG] Trạng thái mới: " . $status);

            $sqlUpdate = 'UPDATE redemptions SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $params[] = $redemption_id;
            error_log("[redeem_rewards][DEBUG] SQL: " . $sqlUpdate);
            error_log("[redeem_rewards][DEBUG] Params: " . json_encode($params));

            $update = $pdo->prepare($sqlUpdate);
            $ok = $update->execute($params);
            $affected = $update->rowCount();

            // Kiểm tra trạng thái sau khi cập nhật
            $checkStmt = $pdo->prepare("SELECT status FROM redemptions WHERE id = ?");
            $checkStmt->execute([$redemption_id]);
            $newStatus = $checkStmt->fetchColumn();
            error_log("[redeem_rewards][DEBUG] Trạng thái sau cập nhật: " . $newStatus);

            if ($ok && $affected > 0) {
                $_SESSION['success'] = "Cập nhật trạng thái đổi quà thành công!";
                error_log('[redeem_rewards] success set for id=' . $redemption_id);
                // ensure session is written before redirect
                session_write_close();
                // redirect back to the same detail page so the updated status is visible
                header('Location: redeem_rewards.php?id=' . urlencode($redemption_id) . '&status=success');
                exit;
            } elseif ($ok && $affected === 0) {
                // query executed but nothing changed (maybe same status)
                $_SESSION['error'] = "Không có thay đổi nào (có thể trạng thái đã giống trước đó).";
            } else {
                $_SESSION['error'] = "Có lỗi xảy ra khi cập nhật. Vui lòng thử lại.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Lỗi CSDL: " . $e->getMessage();
        }
    }

} else {
    // Chỉ cần lấy trực tiếp từ cột reward_title trong bảng redemptions
    $baseQuery = "SELECT r.*, u.username,
                  r.reward_title AS reward_name,
                  r.reward_cost as points_required
                  FROM redemptions r 
                  JOIN users u ON r.user_id = u.id";

    // Add search condition and status filter
    $searchCode = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = 'all';

    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    $whereParts = [];
    $params = [];
    if ($searchCode !== '') {
        $whereParts[] = "r.redemption_code LIKE ?";
        $params[] = "%$searchCode%";
    }
    if ($statusFilter !== 'all') {
        $whereParts[] = "r.status = ?";
        $params[] = $statusFilter;
    }

    $whereClause = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';

    // Count total for pagination
    try {
        $countSql = "SELECT COUNT(*) FROM redemptions r JOIN users u ON r.user_id = u.id" . $whereClause;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
    } catch (PDOException $e) {
        $totalRows = 0;
        $db_error = $e->getMessage();
    }

    $totalPages = $perPage > 0 ? (int)ceil($totalRows / $perPage) : 1;

    $query = $baseQuery . $whereClause . " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";

    try {
        $stmt = $pdo->prepare($query);
        // bind params (search/status) then limit/offset
        $execParams = $params;
        $execParams[] = $perPage;
        $execParams[] = $offset;
        $stmt->execute($execParams);
        $result = $stmt->fetchAll();
    } catch (PDOException $e) {
        $result = [];
        $db_error = $e->getMessage();
    }

}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đổi Quà - Trang Quản Trị</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        /* Base container style - đồng bộ với các trang khác */
        .btn-primary {
            background: #2e8b57;
            border-color: #2e8b57;
            color: #fff;
        }
        .btn-primary:hover {
            background: #3aa76c;
            border-color: #3aa76c;
        }
        .btn-outline-secondary {
            color: #2e8b57;
            border-color: #2e8b57;
        }
        .btn-outline-secondary:hover {
            background: #2e8b57;
            border-color: #2e8b57;
            color: #fff;
        }
        .btn {
            transition: all 0.15s ease;
        }
        /* Badge colors */
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: white;
        }
        .badge.bg-success {
            background-color: #2e8b57 !important;
            color: white;
        }
        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        /* Card styles - đồng bộ với các trang khác */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }

        .card-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }

        .card-title {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }

        /* Button styles - đồng bộ với các trang khác */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        /* Filter styles - đồng bộ với các trang khác */
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-button {
            padding: 8px 16px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #6c757d;
            text-decoration: none;
            background-color: #fff;
            transition: all 0.3s ease;
        }

        .filter-button:hover {
            background-color: #f8f9fa;
            color: #2e8b57;
            border-color: #2e8b57;
        }

        .filter-button.active {
            background-color: #2e8b57;
            color: white;
            border-color: #2e8b57;
        }

        /* Pagination styles - đồng bộ với các trang khác */
        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 0;
            margin-top: 20px;
            gap: 5px;
        }

        .page-item .page-link {
            display: block;
            padding: 8px 12px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .page-item .page-link:hover {
            background-color: #e9ecef;
        }

        .page-item.active .page-link {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
        {
            color: #2e8b57;
        }
        .pagination .page-item.disabled .page-link {
            background: #f5f5f5;
            color: #999;
        }
        
        /* Nút trong form chi tiết */
        .btn-secondary {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #6c757d;
            font-weight: 500;
        }
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            color: #495057;
        }
        form .btn {
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        form .btn-primary {
            box-shadow: 0 2px 4px rgba(46,139,87,0.15);
        }
        form .btn-primary:hover {
            box-shadow: 0 4px 8px rgba(46,139,87,0.2);
            transform: translateY(-1px);
        }

        /* Action button styles - đồng bộ với các trang khác */
        .table .btn-action {
            padding: 6px 12px;
            background: #fff;
            color: #2e8b57;
            border: 1px solid #2e8b57;
            border-radius: 4px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
        }

        .table .btn-action:hover {
            background: #2e8b57;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(46,139,87,0.2);
            text-decoration: none;
        }

        .table .btn-action i {
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Quản lý Đổi Quà</h2>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['status']) && $_GET['status'] === 'success'): ?>
                    <div class="alert alert-success">Cập nhật trạng thái đổi quà thành công!</div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if ($db_error): ?>
                    <div class="alert alert-warning">Lỗi CSDL: <?php echo htmlspecialchars($db_error); ?></div>
                <?php endif; ?>

                <?php if (!isset($_GET['id'])): ?>
                <!-- Thêm form tìm kiếm -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <label for="search_code" class="form-label">Tìm theo mã đổi thưởng:</label>
                                <input type="text" id="search_code" name="search_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($searchCode); ?>" 
                                       placeholder="Nhập mã đổi thưởng...">
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                                <?php if ($searchCode !== ''): ?>
                                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['search_code' => 1])); ?>" 
                                       class="btn btn-outline-secondary">Xoá tìm kiếm</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($redemption): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                <div class="card-header">
                    <h4 class="card-title">Chi tiết đổi quà #<?php echo $redemption['id']; ?></h4>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Người dùng:</strong> <?php echo htmlspecialchars($redemption['username']); ?></p>
                        <p><strong>Phần thưởng:</strong> <?php echo htmlspecialchars($redemption['reward_name']); ?></p>
                        <p><strong>Mã đổi quà:</strong> <?php echo htmlspecialchars($redemption['redemption_code'] ?? ($redemption['reference'] ?? 'N/A')); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($redemption['created_at'])); ?></p>
                        <p><strong>Trạng thái hiện tại:</strong>
                            <?php
                            $statusClass = 'bg-secondary'; // mặc định
                            $displayStatus = 'Chưa xác định';
                            
                            if (isset($redemption['status'])) {
                                switch($redemption['status']) {
                                    case 'pending':
                                        $statusClass = 'bg-warning';
                                        $displayStatus = 'Đang chờ';
                                        break;
                                    case 'approved':
                                        $statusClass = 'bg-success';
                                        $displayStatus = 'Đã xác nhận';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'bg-danger';
                                        $displayStatus = 'Từ chối';
                                        break;
                                }
                            }
                            ?>
                            <span class="badge <?php echo $statusClass; ?>" style="font-size: 14px; padding: 5px 10px;">
                                <?php echo $displayStatus; ?>
                            </span>
                        </p>
                    </div>
                </div>

                <form method="POST" class="mb-0">
                    <div class="mb-3">
                        <label for="status" class="form-label">Cập nhật trạng thái</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending" <?php echo ($redemption['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Đang chờ</option>
                            <option value="approved" <?php echo ($redemption['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>Đã xác nhận</option>
                            <option value="rejected" <?php echo ($redemption['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($redemption['admin_notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="redeem_rewards.php" class="btn btn-primary">Quay lại</a>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>

                <?php else: ?>
                    <!-- Filter container - đồng bộ với các trang khác -->
                    <div class="filter-container mb-3">
                        <?php
                        $tabs = ['all' => 'Tất cả', 'approved' => 'Đã xác nhận', 'pending' => 'Đang chờ', 'rejected' => 'Từ chối'];
                        foreach ($tabs as $k => $label):
                            $qs = $_GET;
                            $qs['status'] = $k;
                            $qs['page'] = 1; // reset page when switching filter
                            $url = '?' . http_build_query($qs);
                            $activeClass = ($k === ($statusFilter ?? 'all')) ? 'active' : '';
                        ?>
                            <a href="<?php echo $url; ?>" class="filter-button <?php echo $activeClass; ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Người dùng</th>
                            <th>Phần thưởng</th>
                            <th>Mã đổi quà</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result as $row): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['reward_name']); ?> (<?php echo number_format($row['points_required']); ?> pts)</td>
                                <td><?php echo htmlspecialchars($row['redemption_code'] ?? ($row['reference'] ?? 'N/A')); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['status'] == 'pending' ? 'bg-warning' : ($row['status'] == 'approved' ? 'bg-success' : 'bg-danger'); ?>">
                                        <?php 
                                        $status = $row['status'] ?? 'pending';
                                        echo $status == 'pending' ? 'Đang chờ' : ($status == 'approved' ? 'Đã xác nhận' : 'Từ chối');
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="redeem_rewards.php?id=<?php echo $row['id']; ?>" class="btn-action">
                                        <i class="fas fa-edit"></i> Chỉnh sửa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if (isset($totalPages) && $totalPages > 1): ?>
                    <div class="card-footer bg-white">
                        <nav aria-label="Trang">
                            <ul class="pagination mb-0">
                                <?php
                                $visible = 7; // number of visible page links
                                $start = max(1, $page - 3);
                                $end = min($totalPages, $start + $visible - 1);
                                if ($page > 1):
                                    $qs = $_GET; $qs['page'] = $page - 1; $prevUrl = '?' . http_build_query($qs);
                                ?>
                                    <li class="page-item"><a class="page-link" href="<?php echo $prevUrl; ?>">&laquo; Trước</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">&laquo; Trước</span></li>
                                <?php endif; ?>

                                <?php for ($p = $start; $p <= $end; $p++):
                                    $qs = $_GET; $qs['page'] = $p; $pUrl = '?' . http_build_query($qs);
                                ?>
                                    <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo $pUrl; ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages):
                                    $qs = $_GET; $qs['page'] = $page + 1; $nextUrl = '?' . http_build_query($qs);
                                ?>
                                    <li class="page-item"><a class="page-link" href="<?php echo $nextUrl; ?>">Sau &raquo;</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Sau &raquo;</span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
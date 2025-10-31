<?php
session_start();
include '../config/db.php';
include '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// PDO (config.php expected to provide $dsn,$username,$password)
$pdo = new PDO($dsn, $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// kiểm tra cột role có tồn tại không
$cols = [];
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM volunteers");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    // ignore
}
$hasRole = in_array('role', $cols);

// XỬ LÝ POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // thêm cột role nếu admin muốn
    if (isset($_POST['add_role_column'])) {
        try {
            $pdo->exec("ALTER TABLE volunteers ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'volunteer'");
            $hasRole = true;
            header("Location: manage_volunteers.php");
            exit();
        } catch (Throwable $e) {
            $message = 'Không thể thêm cột role: ' . $e->getMessage();
        }
    }

    // thay đổi role
    if (isset($_POST['change_role']) && $hasRole) {
        $id = (int)($_POST['id'] ?? 0);
        $role = trim($_POST['role'] ?? '');
        if ($id && $role !== '') {
            // chỉ cho phép các giá trị role hợp lệ
            $allowedRoles = ['volunteer','member','admin'];
            if (!in_array($role, $allowedRoles, true)) {
                $role = 'volunteer';
            }

            $stmt = $pdo->prepare("UPDATE volunteers SET role = ? WHERE id = ?");
            $stmt->execute([$role, $id]);

            // kiểm tra xem có hàng nào được cập nhật không
            if ($stmt->rowCount() > 0) {
                header("Location: manage_volunteers.php?q=" . urlencode($_GET['q'] ?? ''));
                exit();
            } else {
                // thường xảy ra khi ta đang hiển thị dữ liệu tạm từ bảng users (id không tồn tại trong volunteers)
                $message = 'Không thể cập nhật role: không tìm thấy bản ghi trong bảng volunteers (có thể đang hiển thị dữ liệu từ bảng users).';
            }
        }
    }

    // xóa tình nguyện viên
    if (isset($_POST['delete_volunteer'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM volunteers WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: manage_volunteers.php?q=" . urlencode($_GET['q'] ?? ''));
            exit();
        }
    }
}

// TÌM KIẾM
$q = trim((string)($_GET['q'] ?? ''));
$params = [];

// $cols đã được xác định ở trên bằng SHOW COLUMNS FROM volunteers
// cho phép tìm kiếm cả 'username' nếu volunteers dùng tên khác
$searchable = array_values(array_intersect(['name','email','phone','username'], $cols));

$using_users = false;

if ($q !== '') {
    $like = "%$q%";

    if (!empty($searchable)) {
        // tìm trong bảng volunteers theo các cột hiện có
        $w = [];
        foreach ($searchable as $c) {
            $w[] = "$c LIKE ?";
            $params[] = $like;
        }
        $sql = "SELECT * FROM volunteers WHERE " . implode(' OR ', $w) . " ORDER BY id DESC";
    } else {
        // nếu bảng volunteers không có cột tìm kiếm, thử tìm trong bảng users (nếu tồn tại)
        try {
            $uCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($uCount > 0) {
                $using_users = true;
                $sql = "SELECT id, username AS name, email, NULL AS phone, NULL AS role FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC";
                $params = [$like, $like];
            } else {
                // không có dữ liệu để tìm
                $sql = "SELECT * FROM volunteers ORDER BY id DESC";
            }
        } catch (Throwable $e) {
            // nếu không có bảng users, trả về tất cả volunteers
            $sql = "SELECT * FROM volunteers ORDER BY id DESC";
        }
    }
} else {
    // nếu q rỗng, trả về tất cả volunteers
    $sql = "SELECT * FROM volunteers ORDER BY id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Sau khi lấy dữ liệu từ volunteers, nếu muốn hiển thị thêm người dùng từ
// bảng `users` (những người chưa có trong `volunteers`), ta sẽ truy vấn và
// ghép vào danh sách. Điều này giúp hiển thị cả user trong trường hợp
// volunteers chỉ có một phần các user.
if (!$using_users) {
    try {
        // lưu trạng thái ban đầu (có volunteers hay không) để có thể hiển thị
        // thông báo fallback giống trước nếu cần
        $hadVolunteers = !empty($volunteers);

        // chuẩn bị danh sách loại trừ theo email và tên để tránh trùng lặp
        $emails = array_values(array_filter(array_column($volunteers, 'email')));
        $names = array_values(array_filter(array_column($volunteers, 'name')));

        $conds = [];
        $uParams = [];

        if (!empty($emails)) {
            $placeholders = implode(',', array_fill(0, count($emails), '?'));
            $conds[] = "email NOT IN ($placeholders)";
            foreach ($emails as $e) $uParams[] = $e;
        }

        if (!empty($names)) {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $conds[] = "username NOT IN ($placeholders)";
            foreach ($names as $n) $uParams[] = $n;
        }

        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $uSql = "SELECT id, username AS name, email, NULL AS phone, NULL AS role FROM users $where ORDER BY id DESC";
        $uStmt = $pdo->prepare($uSql);
        $uStmt->execute($uParams);
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($users)) {
            // ghép danh sách và sắp xếp theo id giảm dần (số nguyên)
            $volunteers = array_merge($volunteers, $users);
            usort($volunteers, function($a, $b) {
                return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
            });

            // nếu trước đó không có volunteers nhưng bây giờ có users, thông báo fallback
            if (!$hadVolunteers) {
                $message = 'Hiện tại không có tình nguyện viên nào. Hiển thị dữ liệu từ bảng users.';
                $using_users = true;
            }
        }
    } catch (Throwable $e) {
        // ignore lỗi kết nối/không có bảng users
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tình nguyện viên</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/buttons.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
/* Button styles */
.btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:8px 16px;
    border-radius:6px;
    border:1px solid #2e8b57;
    cursor:pointer;
    font-size:14px;
    font-weight:500;
    transition:all 0.15s ease;
    background:#fff;
    color:#2e8b57;
    text-decoration:none
}
.btn:hover{
    background:#f0f9f4
}
.btn-primary{
    border-color:#2e8b57;
    background:#2e8b57;
    color:#fff;
    position:relative;
    overflow:hidden
}
.btn-primary:hover{
    background:#3aa76c;
    border-color:#3aa76c
}
.btn-primary:active{
    transform:translateY(1px)
}
.btn-primary::after{
    content:'';
    position:absolute;
    inset:0;
    background:rgba(255,255,255,0.1);
    opacity:0;
    transition:opacity 0.2s
}
.btn-primary:hover::after{
    opacity:1
}
.btn-primary {
    text-decoration: none;  /* Loại bỏ gạch chân */
}
.btn-icon svg{width:18px;height:18px;stroke:currentColor}

/* Action buttons (edit/delete) */
.action-btn{
    padding:4px 8px;
    border-radius:4px;
    border:1px solid #e0e0e0;
    font-size:13px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:4px;
    transition:all 0.15s ease;
    font-weight:500;
    background:#fff;
    min-width:70px;
    justify-content:center
}
.action-btn.edit{color:#2e8b57;border-color:#2e8b57}
.action-btn.edit:hover{background:#2e8b57;color:#fff}
.action-btn.delete{color:#f44336;border-color:#f44336}
.action-btn.delete:hover{background:#f44336;color:#fff}

/* Base styles */
.container{max-width:1100px;margin:28px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
form.search{display:flex;gap:8px;align-items:center;margin-bottom:12px}
input[type="text"], input[type="email"]{padding:8px;border:1px solid #ddd;border-radius:4px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:10px;border:1px solid #eee;text-align:left}
select.role{padding:6px;border-radius:4px}
.note{font-size:13px;color:#666;margin-bottom:12px}
.alert{background:#fff3cd;border:1px solid #ffeeba;padding:8px;border-radius:6px;margin-bottom:12px}

        /* Footer làm nền xanh (mỏng lại) */
        footer, .footer, .site-footer {
            background: linear-gradient(90deg, #2e8b57 0%, #4fb07a 100%);
            color: #ffffff;
            padding: 8px 0;               /* giảm chiều cao footer */
            font-size: 14px;             /* chữ nhỏ hơn */
            line-height: 1.2;
            border-radius: 4px;          /* nếu muốn bo góc nhẹ */
        }
        footer .container, .footer .container, .site-footer .container {
            background: transparent;     /* giữ nội dung trong container trong suốt */
            box-shadow: none;
            padding-top: 4px;            /* giữ khoảng cách nhỏ bên trong */
            padding-bottom: 4px;
            margin: 0;
        }
        footer a, .footer a, .site-footer a {
            color: rgba(255,255,255,0.95);
            text-decoration: underline;
            font-weight: 600;
        }
        /* tuỳ chọn: nếu muốn footer dính đáy, đổi thành sticky */
        /* footer { position: sticky; bottom: 0; } */
        @media (prefers-color-scheme: dark) {
            footer, .footer, .site-footer { filter: brightness(0.95); }
        }

        /* ĐẶT THÊM (đảm bảo footer luôn ở đáy trang) */
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        .container { flex: 1 0 auto; } /* phần nội dung chiếm không gian còn lại */
        footer, .footer, .site-footer { margin-top: auto; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h1>Quản lý Tình nguyện viên</h1>

    <?php if ($message): ?><div class="alert"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <form method="GET" class="search" action="manage_volunteers.php" role="search" aria-label="Tìm tình nguyện viên">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Tìm theo tên, email hoặc điện thoại" style="flex:1">
        <button type="submit" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            Tìm
        </button>
        <a href="manage_volunteers.php" class="btn-primary">Hiển thị tất cả</a>
    </form>

    <div class="note">
        Chức năng: tìm kiếm, thay đổi vai trò (role) và xóa tình nguyện viên.
        <?php if (!$hasRole): ?>
            <div style="margin-top:8px">
                Bảng chưa có cột "role". Nhấn nút dưới để thêm cột role (VARCHAR, mặc định "volunteer").
                <form method="POST" style="display:inline;margin-left:8px">
                    <button type="submit" name="add_role_column" class="btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Thêm cột role
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <table aria-describedby="Danh sách tình nguyện viên">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>Email</th>
                <th>Điện thoại</th>
                <?php if ($hasRole): ?><th>Vai trò</th><?php endif; ?>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($volunteers)): ?>
                <tr><td colspan="<?php echo $hasRole?6:5; ?>">Không tìm thấy kết quả.</td></tr>
            <?php else: ?>
                <?php foreach ($volunteers as $v): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($v['id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($v['name'] ?? ($v['username'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($v['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($v['phone'] ?? ''); ?></td>

                        <?php if ($hasRole): ?>
                            <td>
                                <form method="POST" style="display:flex;gap:6px;align-items:center" class="role-form">
                                    <input type="hidden" name="id" value="<?php echo (int)($v['id'] ?? 0); ?>">
                                    <input type="hidden" name="change_role" value="1">
                                    <select name="role" class="role" aria-label="Chọn vai trò">
                                        <option value="volunteer" <?php echo ((($v['role'] ?? '') === 'volunteer')? 'selected':'' ); ?>>Tình nguyện viên</option>
                                        <option value="member" <?php echo ((($v['role'] ?? '') === 'member')? 'selected':'' ); ?>>Thành viên</option>
                                        <option value="admin" <?php echo ((($v['role'] ?? '') === 'admin')? 'selected':'' ); ?>>Quản trị</option>
                                    </select>
                                </form>
                            </td>
                        <?php endif; ?>

                        <td class="actions" style="white-space: nowrap">
                            <button type="button" class="action-btn edit" data-action="edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Chỉnh sửa
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn có chắc muốn xóa tình nguyện viên này?');">
                                <input type="hidden" name="id" value="<?php echo (int)($v['id'] ?? 0); ?>">
                                <button type="submit" name="delete_volunteer" class="action-btn delete">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>
<?php include '../includes/footer.php'; ?>
<script>
// Submit role form when the Edit button is clicked in the same row
(function(){
    document.addEventListener('click', function(e){
        var btn = e.target.closest('[data-action="edit"]');
        if (!btn) return;
        var row = btn.closest('tr');
        if (!row) return;
        // find the role form inside this row
        var form = row.querySelector('form.role-form');
        if (!form) {
            // nothing to submit
            return;
        }
        // optionally confirm before submitting
        if (!confirm('Xác nhận thay đổi vai trò cho người này?')) return;
        form.submit();
    });
})();
</script>
</body>
</html>
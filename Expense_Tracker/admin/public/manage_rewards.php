<?php
session_start();
include '../config/db.php';
include '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// kiểm tra cột image
$cols = [];
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM rewards");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    $cols = [];
}
$hasImageCol = in_array('image', $cols);

// thiết lập upload
$uploadBase = realpath(__DIR__ . '/../../') . '/uploads/rewards/';
if (!is_dir($uploadBase)) @mkdir($uploadBase, 0755, true);
$allowed = ['image/jpeg','image/png','image/gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

// helper: convert php shorthand (e.g. "20M") to bytes
function shorthand_to_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    if ($last === 'g') return $num * 1024 * 1024 * 1024;
    if ($last === 'm') return $num * 1024 * 1024;
    if ($last === 'k') return $num * 1024;
    return (int)$val;
}

// Warn if server's php.ini limits are lower than desired limit
$uploadMax = shorthand_to_bytes(ini_get('upload_max_filesize') ?: '2M');
$postMax = shorthand_to_bytes(ini_get('post_max_size') ?: '8M');
if ($uploadMax < $maxSize || $postMax < $maxSize) {
    $message .= ' Lưu ý: cấu hình PHP hiện tại (upload_max_filesize/post_max_size) nhỏ hơn giới hạn upload mong muốn (5MB). Vui lòng cập nhật php.ini (ví dụ upload_max_filesize=6M, post_max_size=6M) và khởi động lại Apache.';
}

$message = '';

// Thêm cột image nếu admin muốn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_image_column'])) {
    try {
        $pdo->exec("ALTER TABLE rewards ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        $hasImageCol = true;
        header("Location: manage_rewards.php");
        exit();
    } catch (Throwable $e) {
        $message = 'Không thể thêm cột image: ' . $e->getMessage();
    }
}

// Thêm phần thưởng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reward'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $points = (int)($_POST['points'] ?? 0);

    if ($title === '') {
        $message = 'Vui lòng nhập tiêu đề phần thưởng.';
    } else {
        $imageName = null;
        if ($hasImageCol && !empty($_FILES['image']['name'])) {
            $f = $_FILES['image'];
            if ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $maxSize && in_array(mime_content_type($f['tmp_name']), $allowed)) {
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $imageName = uniqid('r_') . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $uploadBase . $imageName);
            } else {
                $message = 'Ảnh không hợp lệ hoặc vượt quá 5MB.';
            }
        }

        if ($message === '') {
            // build insert dynamically based on existing columns in rewards table ($cols)
            $insertCols = [];
            $placeholders = [];
            $values = [];

            // map common columns
            if (in_array('title', $cols)) {
                $insertCols[] = 'title';
                $placeholders[] = '?';
                $values[] = $title;
            } elseif (in_array('name', $cols)) {
                $insertCols[] = 'name';
                $placeholders[] = '?';
                $values[] = $title;
            }

            if (in_array('description', $cols)) {
                $insertCols[] = 'description';
                $placeholders[] = '?';
                $values[] = $description;
            } elseif (in_array('detail', $cols)) {
                $insertCols[] = 'detail';
                $placeholders[] = '?';
                $values[] = $description;
            }

            if (in_array('points', $cols)) {
                $insertCols[] = 'points';
                $placeholders[] = '?';
                $values[] = $points;
            } elseif (in_array('cost', $cols)) {
                $insertCols[] = 'cost';
                $placeholders[] = '?';
                $values[] = $points;
            }

            if ($hasImageCol && in_array('image', $cols)) {
                $insertCols[] = 'image';
                $placeholders[] = '?';
                $values[] = $imageName;
            }

            // created_at fallback
            if (in_array('created_at', $cols)) {
                $insertCols[] = 'created_at';
                $placeholders[] = '?';
                $values[] = date('Y-m-d H:i:s');
            } elseif (in_array('created', $cols)) {
                $insertCols[] = 'created';
                $placeholders[] = '?';
                $values[] = date('Y-m-d H:i:s');
            }

            if (empty($insertCols)) {
                $message = 'Bảng rewards không có cột phù hợp để chèn dữ liệu. Hãy thêm cột title/description/points (hoặc chạy migration).';
            } else {
                $sql = 'INSERT INTO rewards (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    header("Location: manage_rewards.php");
                    exit();
                } catch (Throwable $e) {
                    $message = 'Lỗi khi tạo phần thưởng: ' . $e->getMessage();
                }
            }
        }
    }
}

// Xóa phần thưởng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reward'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        if ($hasImageCol) {
            $r = $pdo->prepare("SELECT image FROM rewards WHERE id = ? LIMIT 1");
            $r->execute([$id]);
            $row = $r->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['image']) && file_exists($uploadBase . $row['image'])) {
                @unlink($uploadBase . $row['image']);
            }
        }
        $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: manage_rewards.php");
        exit();
    }
}

// Chỉnh sửa phần thưởng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reward'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $points = (int)($_POST['points'] ?? 0);

        // get old image name to delete if replaced
        $oldImage = '';
        if ($hasImageCol) {
            $r = $pdo->prepare("SELECT image FROM rewards WHERE id = ? LIMIT 1");
            $r->execute([$id]);
            $oldImage = $r->fetchColumn() ?: '';
        }

        $newImageName = null;
        if ($hasImageCol && !empty($_FILES['image']['name'])) {
            $f = $_FILES['image'];
            if ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $maxSize && in_array(mime_content_type($f['tmp_name']), $allowed)) {
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $newImageName = uniqid('r_') . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $uploadBase . $newImageName)) {
                    $message = 'Không thể lưu file ảnh mới.';
                    $newImageName = null;
                }
            } else {
                $message = 'Ảnh không hợp lệ hoặc vượt quá 5MB.';
            }
        }

        if ($message === '') {
            // build update statement using available column names
            $sets = [];
            $params = [];

            if (in_array('title', $cols)) { $sets[] = 'title = ?'; $params[] = $title; }
            elseif (in_array('name', $cols)) { $sets[] = 'name = ?'; $params[] = $title; }

            if (in_array('description', $cols)) { $sets[] = 'description = ?'; $params[] = $description; }
            elseif (in_array('detail', $cols)) { $sets[] = 'detail = ?'; $params[] = $description; }

            if (in_array('points', $cols)) { $sets[] = 'points = ?'; $params[] = $points; }
            elseif (in_array('cost', $cols)) { $sets[] = 'cost = ?'; $params[] = $points; }

            if ($hasImageCol && in_array('image', $cols) && $newImageName) { $sets[] = 'image = ?'; $params[] = $newImageName; }

            if (!empty($sets)) {
                $params[] = $id;
                $sql = 'UPDATE rewards SET ' . implode(', ', $sets) . ' WHERE id = ?';
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // delete old image if replaced
                    if ($hasImageCol && $newImageName && $oldImage && file_exists($uploadBase . $oldImage)) {
                        @unlink($uploadBase . $oldImage);
                    }

                    header("Location: manage_rewards.php");
                    exit();
                } catch (Throwable $e) {
                    $message = 'Lỗi khi cập nhật phần thưởng: ' . $e->getMessage();
                }
            } else {
                $message = 'Không có cột nào để cập nhật trong bảng rewards.';
            }
        }
    }
}

// Lấy danh sách
try {
    $rewards = $pdo->query("SELECT * FROM rewards ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rewards = [];
    $message = 'Lỗi khi tải danh sách: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý Phần thưởng</title>
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
.btn-icon svg{width:18px;height:18px;stroke:currentColor}

/* Action buttons (edit/delete) */
.action-btn{
    min-width: 100px;
    padding: 6px 12px;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
    cursor: pointer;
    background: #fff;
    color: #333;
    font-size: 13px;
    transition: all 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-right: 8px
}
.action-btn:last-child {
    margin-right: 0
}
.action-btn.edit{color:#2e8b57;border-color:#2e8b57}
.action-btn.edit:hover{background:#2e8b57;color:#fff}
.action-btn.delete{color:#dc3545;border-color:#dc3545}
.action-btn.delete:hover{background:#dc3545;color:#fff}

.container{max-width:1100px;margin:28px auto;padding:20px;background:#fff;border-radius:6px}
.form-row{display:flex;gap:12px;flex-wrap:wrap}
.form-row .col{flex:1;min-width:200px}
input, textarea, select{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{padding:8px;border:1px solid #eee;text-align:left;vertical-align:top}
.alert{background:#fff3cd;padding:8px;border-radius:6px;margin-bottom:12px}
.small{font-size:13px;color:#666}
.thumbnail{max-width:120px;height:auto;border-radius:6px}

/* Form improvements */
.form-group{margin-bottom:16px}
.form-group label{display:block;margin-bottom:6px;font-weight:500}
input[type="file"]{
    padding:8px;
    border:1px dashed #ccc;
    border-radius:6px;
    background:#fafafa;
    width:100%;
    box-sizing:border-box
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h1>Quản lý Phần thưởng</h1>

    <?php if ($message): ?><div class="alert"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <?php if (!$hasImageCol): ?>
        <div class="alert">
            Bảng rewards chưa có cột "image". Nếu muốn lưu ảnh cho phần thưởng, nhấn nút dưới để thêm cột.
            <form method="POST" style="display:inline;margin-left:8px">
                <button type="submit" name="add_image_column" class="btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Thêm cột ảnh
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="toolbar" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
        <div class="left" style="display:flex;gap:12px;align-items:center">
            <button id="openRewardModalBtn" class="btn btn-primary btn-icon create-news-btn" type="button" title="Tạo phần thưởng mới">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Tạo phần thưởng mới</span>
            </button>
            <div class="note">Nhấn để mở form nhập phần thưởng mới</div>
        </div>
        <div class="right">
            <span class="small" style="color:#666">Số phần thưởng: <?php echo count($rewards); ?></span>
        </div>
    </div>

    <!-- Modal: Create Reward -->
    <div id="rewardModal" class="modal" aria-hidden="true">
        <div class="modal-backdrop" tabindex="-1"></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rewardModalTitle">
            <div class="modal-header">
                <h3 id="rewardModalTitle">Tạo phần thưởng mới</h3>
                <button type="button" class="modal-close" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="col">
                            <label>Tiêu đề</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="col" style="max-width:160px">
                            <label>Điểm</label>
                            <input type="number" name="points" value="0" min="0">
                        </div>
                    </div>
                    <div style="margin-top:8px">
                        <label>Mô tả</label>
                        <textarea name="description" rows="4"></textarea>
                    </div>

                    <?php if ($hasImageCol): ?>
                        <div style="margin-top:8px">
                            <label>Ảnh phần thưởng (jpg/png/gif ≤5MB)</label>
                            <label class="file-btn">Chọn ảnh phần thưởng
                                <input type="file" name="image" id="create-reward-image" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="reward-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:12px;text-align:right">
                        <button type="submit" name="add_reward" class="btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Tạo phần thưởng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Reward -->
    <div id="editRewardModal" class="modal" aria-hidden="true">
        <div class="modal-backdrop" tabindex="-1"></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="editRewardModalTitle">
            <div class="modal-header">
                <h3 id="editRewardModalTitle">Chỉnh sửa phần thưởng</h3>
                <button type="button" class="modal-close" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit-reward-id" value="">
                    <div class="form-row">
                        <div class="col">
                            <label>Tiêu đề</label>
                            <input type="text" name="title" id="edit-reward-title" required>
                        </div>
                        <div class="col" style="max-width:160px">
                            <label>Điểm</label>
                            <input type="number" name="points" id="edit-reward-points" value="0" min="0">
                        </div>
                    </div>
                    <div style="margin-top:8px">
                        <label>Mô tả</label>
                        <textarea name="description" id="edit-reward-description" rows="4"></textarea>
                    </div>

                    <?php if ($hasImageCol): ?>
                        <div style="margin-top:8px">
                            <label>Ảnh phần thưởng (jpg/png/gif ≤5MB)</label>
                            <div id="edit-current-image" style="margin-bottom:8px"></div>
                            <label class="file-btn">Chọn ảnh
                                <input type="file" name="image" id="edit-image-input" accept="image/*" style="display:none">
                            </label>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:12px;text-align:right">
                        <button type="submit" name="edit_reward" class="btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <h2>Danh sách phần thưởng</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Tiêu đề & mô tả</th><th>Ảnh</th><th>Điểm</th><th>Ngày tạo</th><th>Hành động</th></tr>
        </thead>
        <tbody>
            <?php if (empty($rewards)): ?>
                <tr><td colspan="6" class="small">Chưa có phần thưởng nào.</td></tr>
            <?php else: ?>
                <?php foreach ($rewards as $r): ?>
                    <?php
                        // fallback an toàn cho các cột có thể khác tên
                        $title = $r['title'] ?? $r['name'] ?? '';
                        $description = $r['description'] ?? $r['detail'] ?? '';
                        $image = $r['image'] ?? '';
                        $points = isset($r['points']) ? (int)$r['points'] : (isset($r['cost']) ? (int)$r['cost'] : 0);
                        $created = $r['created_at'] ?? $r['created'] ?? '';
                    ?>
                    <?php $rowData = ['id'=>$r['id'] ?? 0,'title'=>$title,'description'=>$description,'image'=>$image,'points'=>$points]; ?>
                    <tr data-item='<?php echo htmlspecialchars(json_encode($rowData, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES); ?>'>
                        <td><?php echo (int)($r['id'] ?? 0); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($title); ?></strong>
                            <div class="small"><?php echo nl2br(htmlspecialchars($description)); ?></div>
                        </td>
                        <td>
                            <?php if ($hasImageCol && $image && file_exists($uploadBase . $image)): ?>
                                <img src="<?php echo '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' . rawurlencode($image); ?>" class="thumbnail" alt="Ảnh">
                            <?php else: ?>
                                — 
                            <?php endif; ?>
                        </td>
                        <td><?php echo $points; ?></td>
                        <td><?php echo htmlspecialchars($created); ?></td>
                        <td class="actions" style="white-space: nowrap">
                            <button type="button" class="action-btn edit" data-action="edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Chỉnh sửa
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn có chắc muốn xóa phần thưởng này?');">
                                <input type="hidden" name="id" value="<?php echo (int)($r['id'] ?? 0); ?>">
                                <button type="submit" name="delete_reward" class="action-btn delete">
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
<script>
// Reward modal open/close
(function(){
    var openBtn = document.getElementById('openRewardModalBtn');
    var modal = document.getElementById('rewardModal');
    if (!openBtn || !modal) return;

    function openModal(){
        modal.classList.add('open');
        modal.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
        var first = modal.querySelector('input, textarea, select');
        if (first) first.focus();
    }
    function closeModal(){
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
        openBtn.focus();
    }

    openBtn.addEventListener('click', openModal);
    Array.prototype.forEach.call(modal.querySelectorAll('.modal-close'), function(b){ b.addEventListener('click', closeModal); });
    var backdrop = modal.querySelector('.modal-backdrop');
    if (backdrop) backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

    // Handle filename display
    const rewardImageInput = document.getElementById('create-reward-image');
    const filenameDiv = document.getElementById('reward-image-filename');
    if (rewardImageInput && filenameDiv) {
        rewardImageInput.addEventListener('change', function() {
            if (this.files && this.files.length) {
                filenameDiv.textContent = this.files[0].name;
            } else {
                filenameDiv.textContent = '';
            }
        });
    }
})();
</script>
<script>
// Edit reward modal handler
(function(){
    var editModal = document.getElementById('editRewardModal');
    if (!editModal) return;

    var editId = document.getElementById('edit-reward-id');
    var editTitle = document.getElementById('edit-reward-title');
    var editDesc = document.getElementById('edit-reward-description');
    var editPoints = document.getElementById('edit-reward-points');
    var editCurrentImage = document.getElementById('edit-current-image');

    function openEditModal(){
        editModal.classList.add('open');
        editModal.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
    }
    function closeEditModal(){
        editModal.classList.remove('open');
        editModal.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
        // clear preview when closing
        // editCurrentImage.innerHTML = '';
    }

    // handle click on edit buttons (event delegation)
    document.addEventListener('click', function(e){
        var btn = e.target.closest('[data-action="edit"]');
        if (!btn) return;
        var row = btn.closest('tr');
        if (!row) return;
        var dataJson = row.getAttribute('data-item');
        try {
            var data = JSON.parse(dataJson);
        } catch (err) {
            console.error('Invalid row data', err);
            return;
        }

        // populate fields
        editId.value = data.id || '';
        editTitle.value = data.title || '';
        editDesc.value = data.description || '';
        editPoints.value = data.points || 0;

        if (data.image) {
            var url = '/Expense_tracker-main/Expense_Tracker/uploads/rewards/' + encodeURIComponent(data.image);
            editCurrentImage.innerHTML = '<img src="'+url+'" alt="Ảnh hiện tại" style="max-width:120px;height:auto;border-radius:6px;display:block;margin-bottom:8px">';
        } else {
            editCurrentImage.innerHTML = '';
        }

        openEditModal();
    });

    // close handlers
    Array.prototype.forEach.call(editModal.querySelectorAll('.modal-close'), function(b){ b.addEventListener('click', closeEditModal); });
    var backdrop = editModal.querySelector('.modal-backdrop');
    if (backdrop) backdrop.addEventListener('click', closeEditModal);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && editModal.classList.contains('open')) closeEditModal(); });
})();
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
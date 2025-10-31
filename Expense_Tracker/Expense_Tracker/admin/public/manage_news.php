<?php
session_start();
include '../config/db.php';
include '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// kiểm tra cấu trúc bảng để biết có cột image không
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM news");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    $cols = [];
}
$hasImageCol = in_array('image', $cols);
$news = [];
$message = '';

try {
    $news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $message = 'Lỗi khi lấy tin: ' . $e->getMessage();
}

// thiết lập upload
$uploadBase = realpath(__DIR__ . '/../../') . '/uploads/news/'; // project_root/uploads/news/
if (!is_dir($uploadBase)) @mkdir($uploadBase, 0755, true);
$allowed = ['image/jpeg','image/png','image/gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

function shorthand_to_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    if ($last === 'g') return $num * 1024 * 1024 * 1024;
    if ($last === 'm') return $num * 1024 * 1024;
    if ($last === 'k') return $num * 1024;
    return (int)$val;
}

$uploadMax = shorthand_to_bytes(ini_get('upload_max_filesize') ?: '2M');
$postMax = shorthand_to_bytes(ini_get('post_max_size') ?: '8M');
if ($uploadMax < $maxSize || $postMax < $maxSize) {
    $message .= ' Lưu ý: cấu hình PHP hiện tại (upload_max_filesize/post_max_size) nhỏ hơn giới hạn upload mong muốn (5MB). Cập nhật php.ini và khởi động lại webserver.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Thêm tin
    if (isset($_POST['add_news'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower(trim($_POST['title']))) . '-' . time();

        $imageName = null;
        if ($hasImageCol && !empty($_FILES['image']['name'])) {
            $f = $_FILES['image'];
            if ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $maxSize && in_array(mime_content_type($f['tmp_name']), $allowed)) {
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $imageName = uniqid('n_') . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $uploadBase . $imageName);
            } else {
                $message = 'Tệp ảnh không hợp lệ hoặc vượt quá 20MB.';
            }
        }

        if ($message === '') {
            $colsIns = ['title','content','slug','created_at'];
            $vals = [$title, $content, $slug, date('Y-m-d H:i:s')];
            if ($hasImageCol && $imageName) {
                $colsIns[] = 'image';
                $vals[] = $imageName;
            }
            $sql = 'INSERT INTO news (' . implode(',', $colsIns) . ') VALUES (' . rtrim(str_repeat('?,', count($vals)), ',') . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($vals);
            header("Location: manage_news.php");
            exit();
        }
    }

    // Xóa tin
    if (isset($_POST['delete_news'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // xóa file ảnh nếu có
            if ($hasImageCol) {
                $r = $pdo->prepare("SELECT image FROM news WHERE id = ? LIMIT 1");
                $r->execute([$id]);
                $row = $r->fetch(PDO::FETCH_ASSOC);
                if (!empty($row['image']) && file_exists($uploadBase . $row['image'])) {
                    @unlink($uploadBase . $row['image']);
                }
            }
            $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: manage_news.php");
            exit();
        }
    }

    // Chỉnh sửa tin
    if (isset($_POST['edit_news'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            // lấy tên file cũ để xóa nếu upload mới thành công
            $oldImage = '';
            if ($hasImageCol) {
                $r = $pdo->prepare("SELECT image FROM news WHERE id = ? LIMIT 1");
                $r->execute([$id]);
                $oldImage = $r->fetchColumn() ?: '';
            }

            $newImageName = null;
            if ($hasImageCol && !empty($_FILES['image']['name'])) {
                $f = $_FILES['image'];
                if ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $maxSize && in_array(mime_content_type($f['tmp_name']), $allowed)) {
                    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                    $newImageName = uniqid('n_') . '.' . $ext;
                    if (!move_uploaded_file($f['tmp_name'], $uploadBase . $newImageName)) {
                        $message = 'Không thể lưu file ảnh mới.';
                        $newImageName = null;
                    }
                } else {
                    $message = 'Tệp ảnh không hợp lệ hoặc vượt quá giới hạn.';
                }
            }

            if ($message === '') {
                $sets = ['title = ?', 'content = ?'];
                $params = [$title, $content];
                if ($hasImageCol && $newImageName) {
                    $sets[] = 'image = ?';
                    $params[] = $newImageName;
                }
                $params[] = $id;
                $sql = 'UPDATE news SET ' . implode(', ', $sets) . ' WHERE id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // xóa file cũ nếu có và đã bị thay thế
                if ($hasImageCol && $newImageName && $oldImage && file_exists($uploadBase . $oldImage)) {
                    @unlink($uploadBase . $oldImage);
                }

                header("Location: manage_news.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý Tin tức</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/buttons.css">
<link rel="stylesheet" href="assets/css/common.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="container">
    <h1>Quản lý Tin tức</h1>
    <?php if ($message): ?><div class="alert"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="form-toggle" style="margin-bottom:12px;display:flex;gap:12px;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:12px;align-items:center">
            <button id="openModalBtn" class="btn btn-primary btn-icon create-news-btn" type="button" title="Tạo tin mới">
                <!-- plus/news SVG icon -->
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Tạo tin mới</span>
            </button>
            <div class="note">Nhấn để mở form nhập. Ảnh đại diện và chèn ảnh vào nội dung hoạt động như trước.</div>
        </div>
        <div style="font-size:13px;color:#666">Số tin: <?php echo count($news); ?></div>
    </div>

    <!-- Modal: Create News -->
    <div id="createModal" class="modal" aria-hidden="true">
        <div class="modal-backdrop" tabindex="-1"></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
            <div class="modal-header">
                <h3 id="newsModalTitle">Tạo Tin mới</h3>
                <button type="button" class="modal-close" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="manage_news.php" enctype="multipart/form-data">
                    <div class="form-row top-row">
                        <div class="col">
                            <input type="text" name="title" id="news-title" placeholder="Tiêu đề tin" required style="padding:8px;width:80%">
                        </div>
                        <?php if ($hasImageCol): ?>
                        <div class="col" style="max-width:260px">
                            <label>Ảnh đại diện (jpg/png/gif ≤5MB)</label><br>
                            <label class="file-btn">Chọn ảnh đại diện
                                <input type="file" name="image" id="create-image-input" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="create-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row-with-panel">
                        <div class="content">
                            <textarea name="content" id="news-content" placeholder="Nội dung tin" rows="10" style="padding:8px" required></textarea>
                        </div>
                        <div class="insert-panel">
                            <label style="display:block;margin-bottom:6px">Chèn ảnh vào nội dung</label>
                            <label class="file-btn">Chọn tệp ảnh
                                <input type="file" id="insert-image-file" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="insert-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                            <small style="display:block;color:#666;margin-top:6px">Chọn tệp ảnh để tải lên và chèn vào vị trí con trỏ dưới dạng &lt;img&gt;</small>

                            <hr style="margin:12px 0">

                            <button type="button" id="insert-image-btn" class="btn btn-primary" style="width:100%;margin-bottom:8px">Tải lên & Chèn ảnh</button>

                            <div id="upload-status" style="font-size:13px;color:#666;min-height:24px"></div>
                        </div>
                    </div>

                    <div style="margin-top:8px;text-align:right">
                        <button type="submit" name="add_news" class="btn btn-primary">Tạo bài</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit News -->
    <div id="editNewsModal" class="modal" aria-hidden="true">
        <div class="modal-backdrop" tabindex="-1"></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="editNewsModalTitle">
            <div class="modal-header">
                <h3 id="editNewsModalTitle">Chỉnh sửa Tin</h3>
                <button type="button" class="modal-close" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="manage_news.php" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit-news-id" value="">
                    <div class="form-row top-row">
                        <div class="col">
                            <input type="text" name="title" id="edit-news-title" placeholder="Tiêu đề tin" required style="padding:8px;width:100%">
                        </div>
                        <?php if ($hasImageCol): ?>
                        <div class="col" style="max-width:260px">
                            <label>Ảnh đại diện (jpg/png/gif ≤5MB)</label><br>
                            <div id="current-image-preview" style="margin-bottom:8px"></div>
                            <label class="file-btn">Chọn ảnh đại diện
                                <input type="file" name="image" id="edit-image-input" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="edit-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row-with-panel">
                        <div class="content">
                            <textarea name="content" id="edit-news-content" placeholder="Nội dung tin" rows="10" style="padding:8px" required></textarea>
                        </div>
                        <div class="insert-panel">
                            <label style="display:block;margin-bottom:6px">Chèn ảnh vào nội dung</label>
                            <label class="file-btn">Chọn tệp ảnh
                                <input type="file" id="edit-insert-image-file" accept="image/*" style="display:none">
                            </label>
                            <div class="file-name" id="edit-insert-image-filename" style="margin-top:6px;font-size:13px;color:#666"></div>
                            <small style="display:block;color:#666;margin-top:6px">Chọn tệp ảnh để tải lên và chèn vào vị trí con trỏ dưới dạng &lt;img&gt;</small>

                            <hr style="margin:12px 0">

                            <button type="button" id="edit-insert-image-btn" class="btn btn-primary" style="width:100%;margin-bottom:8px">Tải lên & Chèn ảnh</button>

                            <div id="edit-upload-status" style="font-size:13px;color:#666;min-height:24px"></div>
                        </div>
                    </div>

                    <div style="margin-top:8px;text-align:right">
                        <button type="submit" name="edit_news" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <h2>Danh sách tin tức</h2>
    <div class="news-list">
    <table>
        <thead>
            <tr><th>ID</th><th>Ảnh</th><th>Tiêu đề</th><th>Ngày</th><th>Hành động</th></tr>
        </thead>
        <tbody>
        <?php foreach ($news as $item): ?>
            <?php $rowData = ['id'=>$item['id'],'title'=>$item['title'] ?? '','content'=>$item['content'] ?? '','image'=>($item['image'] ?? '')]; ?>
            <tr data-item='<?php echo htmlspecialchars(json_encode($rowData, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES); ?>'>
                <td><?php echo htmlspecialchars($item['id']); ?></td>
                <td>
                    <?php if ($hasImageCol && !empty($item['image']) && file_exists(__DIR__ . '/../../uploads/news/' . $item['image'])): ?>
                        <img src="/Expense_tracker-main/Expense_Tracker/uploads/news/<?php echo rawurlencode($item['image']); ?>" class="thumbnail" alt="Ảnh đại diện">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><a href="/Expense_tracker-main/Expense_Tracker/news.php?id=<?php echo $item['id']; ?>" target="_blank"><?php echo htmlspecialchars($item['title']); ?></a></td>
                <td><?php echo htmlspecialchars($item['created_at'] ?? ''); ?></td>
                <td class="actions" style="white-space: nowrap">
                            <button type="button" class="action-btn edit" data-action="edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Chỉnh sửa
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Bạn có chắc muốn xóa tin này?');">
                                <input type="hidden" name="id" value="<?php echo (int)($item['id']); ?>">
                                <button type="submit" name="delete_news" class="action-btn delete">
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
        </tbody>
    </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
// Edit modal handler
(function(){
    const editModal = document.getElementById('editNewsModal');
    const editForm = editModal.querySelector('form');
    const editIdInput = document.getElementById('edit-news-id');
    const editTitleInput = document.getElementById('edit-news-title');
    const editContentInput = document.getElementById('edit-news-content');
    const editImageInput = document.getElementById('edit-image-input');
    const currentImagePreview = document.getElementById('current-image-preview');

    function openEditModal(){
        editModal.classList.add('open');
        editModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        editTitleInput.focus();
    }

    function closeEditModal(){
        editModal.classList.remove('open');
        editModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        editForm.reset();
        currentImagePreview.innerHTML = '';
    }

    // Edit button click handler
    document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-action="edit"]');
        if (!btn) return;

        const row = btn.closest('tr');
        const data = JSON.parse(row.getAttribute('data-item'));
        
        editIdInput.value = data.id;
        editTitleInput.value = data.title;
        editContentInput.value = data.content;

        // Show current image if exists
        if (data.image) {
            currentImagePreview.innerHTML = `
                <img src="/Expense_tracker-main/Expense_Tracker/uploads/news/${data.image}" 
                     alt="Ảnh hiện tại" style="max-width:100%;height:auto;border-radius:4px;margin-bottom:8px">
                <div style="font-size:13px;color:#666">Ảnh hiện tại (để trống nếu không đổi)</div>
            `;
        } else {
            currentImagePreview.innerHTML = '';
        }

        openEditModal();
    });

    // Close button & backdrop
    Array.from(editModal.querySelectorAll('.modal-close')).forEach(btn => 
        btn.addEventListener('click', closeEditModal)
    );
    editModal.querySelector('.modal-backdrop').addEventListener('click', closeEditModal);

    // Esc to close
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && editModal.classList.contains('open')) closeEditModal();
    });

    // Setup image insert for edit form (mirror of create form behavior)
    const insertBtn = document.getElementById('edit-insert-image-btn');
    const fileInput = document.getElementById('edit-insert-image-file');
    const status = document.getElementById('edit-upload-status');
    const textarea = document.getElementById('edit-news-content');
    const editInsertFilename = document.getElementById('edit-insert-image-filename');
    const editImageFilename = document.getElementById('edit-image-filename');

    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const before = textarea.value.substring(0, start);
        const after = textarea.value.substring(end);
        textarea.value = before + text + after;
        const pos = start + text.length;
        textarea.selectionStart = textarea.selectionEnd = pos;
        textarea.focus();
    }

    insertBtn.addEventListener('click', function(){
        const f = fileInput.files[0];
        if (!f) { status.textContent = 'Chưa chọn tệp.'; return; }
    if (f.size > 5*1024*1024) { status.textContent = 'Kích thước tệp vượt quá 5MB.'; return; }
        status.textContent = 'Đang tải lên...';
        const fd = new FormData();
        fd.append('image', f);
        fetch('upload_news_image.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(r => r.json())
        .then(j => {
            if (j.success && j.url) {
                insertAtCursor(textarea, '\n<img src="'+j.url+'" alt="Ảnh bài viết" style="max-width:100%;">\n');
                status.innerHTML = '<span style="color:green">Đã chèn ảnh.</span>';
            } else {
                status.innerHTML = '<span style="color:red">Lỗi: '+(j.error||'tải lên thất bại')+'</span>';
            }
        }).catch(err => {
            status.innerHTML = '<span style="color:red">Lỗi tải lên</span>';
            console.error(err);
        });
    });
    // show filename when file selected
    if (fileInput) {
        fileInput.addEventListener('change', function(){
            if (fileInput.files && fileInput.files.length) {
                editInsertFilename.textContent = fileInput.files[0].name;
            } else editInsertFilename.textContent = '';
        });
    }
    // filename for top image input
    if (editImageInput) {
        editImageInput.addEventListener('change', function(){
            if (editImageInput.files && editImageInput.files.length) editImageFilename.textContent = editImageInput.files[0].name; else editImageFilename.textContent = '';
        });
    }
})();

// Original image insert handler for create form
(function(){
    var openBtn = document.getElementById('openNewsModalBtn');
    var modal = document.getElementById('newsModal');
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
})();
</script>
<script>
(function(){
    const openBtn = document.getElementById('openModalBtn');
    const modal = document.getElementById('createModal');
    if (!openBtn || !modal) return;

    function openModal(){
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        const first = modal.querySelector('input[type="text"]');
        if (first) first.focus();
    }
    function closeModal(){
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        openBtn.focus();
    }

    openBtn.addEventListener('click', openModal);
    Array.from(modal.querySelectorAll('.modal-close')).forEach(btn => 
        btn.addEventListener('click', closeModal)
    );
    modal.querySelector('.modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });
})();

(function(){
    const fileInput = document.getElementById('insert-image-file');
    const btn = document.getElementById('insert-image-btn');
    const status = document.getElementById('upload-status');
    const textarea = document.getElementById('news-content');
    const insertFilename = document.getElementById('insert-image-filename');
    const createImageInput = document.getElementById('create-image-input');
    const createImageFilename = document.getElementById('create-image-filename');

    function insertAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const before = textarea.value.substring(0, start);
        const after  = textarea.value.substring(end, textarea.value.length);
        textarea.value = before + text + after;
        const pos = start + text.length;
        textarea.selectionStart = textarea.selectionEnd = pos;
        textarea.focus();
    }

    btn.addEventListener('click', function(){
        const f = fileInput.files[0];
        if (!f) { status.textContent = 'Chưa chọn tệp.'; return; }
    if (f.size > 5*1024*1024) { status.textContent = 'Kích thước tệp vượt quá 5MB.'; return; }
        status.textContent = 'Đang tải lên...';
        const fd = new FormData();
        fd.append('image', f);
        fetch('upload_news_image.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(r => r.json())
        .then(j => {
            if (j.success && j.url) {
                insertAtCursor(textarea, '\n<img src="'+j.url+'" alt="Ảnh bài viết" style="max-width:100%;">\n');
                status.innerHTML = '<span style="color:green">Đã chèn ảnh.</span>';
            } else {
                status.innerHTML = '<span style="color:red">Lỗi: '+(j.error||'tải lên thất bại')+'</span>';
            }
        }).catch(err=>{
            status.innerHTML = '<span style="color:red">Lỗi tải lên</span>';
            console.error(err);
        });
    });
    if (fileInput) {
        fileInput.addEventListener('change', function(){
            if (fileInput.files && fileInput.files.length) insertFilename.textContent = fileInput.files[0].name; else insertFilename.textContent = '';
        });
    }
    if (createImageInput) {
        createImageInput.addEventListener('change', function(){
            if (createImageInput.files && createImageInput.files.length) createImageFilename.textContent = createImageInput.files[0].name; else createImageFilename.textContent = '';
        });
    }
})();
</script>
</body>
</html>
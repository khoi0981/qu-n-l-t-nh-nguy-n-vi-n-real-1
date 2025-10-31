<?php
// Simple contact page with map and file-based message storage
session_start();

$messagesDir = __DIR__ . '/../data';
if (!is_dir($messagesDir)) @mkdir($messagesDir, 0755, true);
$messagesFile = $messagesDir . '/contacts.json';

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? '')); 
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $message === '') {
        $feedback = 'Vui lòng điền đủ tên, email và nội dung.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = 'Email không hợp lệ.';
    } else {
        $entry = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ts' => time()
        ];
        $all = [];
        if (file_exists($messagesFile)) {
            $raw = @file_get_contents($messagesFile);
            $all = json_decode($raw, true) ?: [];
        }
        $all[] = $entry;
        @file_put_contents($messagesFile, json_encode($all, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        // optional: mail to admin (commented out)
        // @mail('admin@example.com', "Contact: $subject", $message . "\n\nFrom: $name <$email>");
        $feedback = 'Cảm ơn! Tin nhắn của bạn đã gửi thành công.';
        // clear form values
        $name = $email = $subject = $message = '';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Liên hệ - GreenNews</title>
  <link href="/Expense_tracker-main/Expense_Tracker/news_web/css_news/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--brand:#2e8b57;--brand-dark:#246b45;--muted:#6b7a6f}
    body{font-family:Inter,Arial,Helvetica,sans-serif;background:#f6fbf7;color:#123;}
    .container{max-width:1000px;margin:24px auto;padding:18px}
    .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,0.06);border-left:6px solid var(--brand)}
    .grid{display:grid;grid-template-columns:1fr 380px;gap:18px}
    @media(max-width:880px){.grid{grid-template-columns:1fr}}
    .map-wrap iframe{width:100%;height:360px;border:0;border-radius:8px}
    .contact-info h3{margin-top:0}
    .form-row{display:flex;gap:8px}
    input,textarea{width:100%;padding:10px;border:1px solid #e6efe6;border-radius:8px}
    button{background:#2e8b57;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer}
    .note{color:#3b6b4f}
    .feedback{margin:12px 0;padding:10px;background:#f0fff4;border-left:4px solid #2e8b57}
    .site-btn{background:linear-gradient(90deg,var(--brand),var(--brand-dark));color:#fff;border:none;padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 20px rgba(46,139,87,0.12)}
    .site-btn:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(36,107,69,0.14)}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px">
        <div>
          <h2 style="margin:0;color:var(--brand)">Liên hệ GreenStep</h2>
          <div class="note">Gửi câu hỏi, phản hồi hoặc hợp tác cùng chúng tôi.</div>
        </div>
        <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px">
          <div style="font-weight:700">Green Initiative</div>
          <div style="font-size:14px;color:#556">support@greenstep.example</div>
          <!-- Nút về trang chính (chọn 1 trong 2 href bên dưới) -->
          <a href="/Expense_tracker-main/Expense_Tracker/index.php" class="site-btn" title="Về trang chính">
            <span style="font-size:16px"></span><span style="font-weight:600">Về trang chính</span>
          </a>
          <!-- Nếu muốn về subsite news_web index, thay bằng:
          <a href="/Expense_tracker-main/Expense_Tracker/news_web/index.php" class="site-btn">🌿 Về GreenNews</a>
          -->
        </div>
      </div>

      <div class="grid">
        <div>
          <div class="map-wrap" style="margin-bottom:12px">
            <!-- Google Maps embed (Hanoi) -->
            <iframe src="https://www.google.com/maps?q=21.028511,105.804817&hl=vi&z=15&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>

          <form method="post" action="" style="display:flex;flex-direction:column;gap:12px">
            <?php if (!empty($feedback)): ?><div class="feedback"><?php echo htmlspecialchars($feedback); ?></div><?php endif; ?>
            <div class="form-row">
              <input name="name" placeholder="Họ và tên" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
              <input name="email" placeholder="Email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
            <input name="subject" placeholder="Tiêu đề" value="<?php echo htmlspecialchars($subject ?? ''); ?>">
            <textarea name="message" rows="6" placeholder="Nội dung" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="color:#6b7a6f;font-size:13px">Hoạt động: Thứ 2 - Thứ 6, 9:00 - 17:00</div>
              <button type="submit" class="site-btn">Gửi liên hệ</button>
            </div>
          </form>
        </div>

        <aside class="contact-info">
          <h3>Thông tin liên hệ</h3>
          <p style="margin:6px 0"><strong>Địa chỉ:</strong><br>Green Initiative HQ<br>123 Lương Yên, Hai Bà Trưng, Hà Nội, Việt Nam</p>
          <p style="margin:6px 0"><strong>Điện thoại:</strong><br><a href="tel:+84987654321">+84 98 765 4321</a></p>
          <p style="margin:6px 0"><strong>Email:</strong><br><a href="mailto:support@greenstep.example">support@greenstep.example</a></p>
          <p style="margin:6px 0"><strong>Giờ làm việc:</strong><br>Thứ 2 - Thứ 6: 09:00 - 17:00</p>

          <hr>
          <h4>Theo dõi chúng tôi</h4>
          <p style="display:flex;gap:8px;margin:8px 0">
            <a href="#">Facebook</a>
            <a href="#">Twitter</a>
            <a href="#">Instagram</a>
          </p>

          <hr>
          <div style="font-size:13px;color:#667">Chúng tôi ghi lại liên hệ để phản hồi. Nếu muốn xóa dữ liệu, liên hệ <a href="mailto:support@greenstep.example">support@greenstep.example</a>.</div>
        </aside>
      </div>
    </div>
  </div>
</body>
</html>

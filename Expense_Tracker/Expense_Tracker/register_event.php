<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing event_id']);
    exit;
}

$eventId = (int)$_POST['event_id'];
if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event_id']);
    exit;
}

// include db
if (file_exists(__DIR__ . '/admin/config/db.php')) include __DIR__ . '/admin/config/db.php';
elseif (file_exists(__DIR__ . '/config/db.php')) include __DIR__ . '/config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['success' => false, 'message' => 'DB connection not available']);
    exit;
}

try {
    // ensure current_participants column exists
    $cols = $pdo->query("SHOW COLUMNS FROM events LIKE 'current_participants'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($cols) === 0) {
        // add column (safe default 0)
        $pdo->exec("ALTER TABLE events ADD COLUMN current_participants INT NOT NULL DEFAULT 0");
    }

    // begin transaction to avoid race condition
    $pdo->beginTransaction();

    // lock the row
    $stmt = $pdo->prepare("SELECT participants, current_participants FROM events WHERE id = ? FOR UPDATE");
    $stmt->execute([$eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    $total = (int)($row['participants'] ?? 0); // capacity (may be 0 if not set)
    $current = (int)($row['current_participants'] ?? 0);

    // if total > 0 treat as capacity; else allow infinite
    if ($total > 0 && $current >= $total) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Số lượng đã đầy', 'current' => $current, 'total' => $total]);
        exit;
    }

    // increment
    $upd = $pdo->prepare("UPDATE events SET current_participants = current_participants + 1 WHERE id = ?");
    $upd->execute([$eventId]);

    // fetch new value
    $stmt2 = $pdo->prepare("SELECT current_participants FROM events WHERE id = ?");
    $stmt2->execute([$eventId]);
    $new = $stmt2->fetch(PDO::FETCH_ASSOC);
    $pdo->commit();

    $newCurrent = (int)($new['current_participants'] ?? ($current + 1));
    echo json_encode(['success' => true, 'message' => 'Đăng ký thành công', 'current' => $newCurrent, 'total' => $total]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
    exit;
}
?>
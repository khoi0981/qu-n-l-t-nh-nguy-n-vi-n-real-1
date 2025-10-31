<?php
session_start();
header('Content-Type: application/json');

// Include database configuration
if (file_exists(__DIR__ . '/admin/config/db.php')) {
    include_once __DIR__ . '/admin/config/db.php';
} elseif (file_exists(__DIR__ . '/config/db.php')) {
    include_once __DIR__ . '/config/db.php';
}

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }

    // Get and validate parameters
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if (!$event_id || !$user_id) {
        throw new Exception('Invalid parameters');
    }

    // Verify event exists and is ongoing
    $stmt = $pdo->prepare("SELECT e.*, COALESCE(e.points, 0) as points FROM events e WHERE e.id = ? LIMIT 1");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Event not found');
    }

    // Check if event is ongoing (today)
    $eventDate = date('Y-m-d', strtotime($event['date'] ?? $event['event_date'] ?? ''));
    if ($eventDate !== date('Y-m-d')) {
        throw new Exception('Event check-in is only available on the event day');
    }

    // Verify user is registered
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('User not registered for this event');
    }

    // Check if already checked in
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Already checked in for this event');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Record check-in
        $stmt = $pdo->prepare("INSERT INTO checkins (event_id, user_id, checkin_time) VALUES (?, ?, NOW())");
        $stmt->execute([$event_id, $user_id]);

        // Update user points and participation count
        $points = (int)($event['points'] ?? 0);
        
        // Try to update points in users table if column exists
        try {
            $stmt = $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ?, participation_count = COALESCE(participation_count, 0) + 1 WHERE id = ?");
            $stmt->execute([$points, $user_id]);
        } catch (Exception $e) {
            // If column doesn't exist, ignore
        }

        $pdo->commit();
        $response = [
            'success' => true,
            'message' => 'Điểm danh thành công! Bạn nhận được ' . $points . ' điểm.',
            'points' => $points
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
<?php
// Simple server-side QR generator that proxies Google Charts and saves a PNG copy locally.
// POST params: event_id, user_id, timestamp (optional). Returns JSON {success:1,url:'/path/to/png'}

header('Content-Type: application/json; charset=utf-8');

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : (isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0);
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);
$timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : (isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : time());

if ($eventId <= 0 || $userId <= 0) {
    echo json_encode(['success' => 0, 'error' => 'Invalid event_id or user_id']);
    exit;
}

$payload = ['eventId' => $eventId, 'userId' => $userId, 'timestamp' => $timestamp];
$payloadJson = json_encode($payload);
$encoded = rawurlencode($payloadJson);
// request a 400x400 QR for better quality
$chartUrl = "https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl={$encoded}&choe=UTF-8";

// fetch the image (try cURL first)
$imgData = false;
if (function_exists('curl_init')) {
    $ch = curl_init($chartUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($imgData === false || $httpCode !== 200) {
        $imgData = false;
    }
} elseif (ini_get('allow_url_fopen')) {
    $imgData = @file_get_contents($chartUrl);
}

if ($imgData === false || strlen($imgData) < 50) {
    echo json_encode(['success' => 0, 'error' => 'Could not fetch QR image']);
    exit;
}

// ensure uploads/qrcodes exists
$uploadDir = __DIR__ . '/uploads/qrcodes/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$filename = sprintf('checkin_%d_%d_%d.png', $eventId, $userId, $timestamp);
$filepath = $uploadDir . $filename;
$written = @file_put_contents($filepath, $imgData);
if ($written === false) {
    echo json_encode(['success' => 0, 'error' => 'Could not save image to server']);
    exit;
}

// Build public URL — assume app is served under /Expense_tracker-main/Expense_Tracker/
$webPrefix = '/Expense_tracker-main/Expense_Tracker/uploads/qrcodes/';
$url = $webPrefix . rawurlencode($filename);

echo json_encode(['success' => 1, 'url' => $url, 'payload' => $payload]);
exit;

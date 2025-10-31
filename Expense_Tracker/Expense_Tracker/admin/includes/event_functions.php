<?php
// Các hàm xử lý cho Events

function getEventColumns($pdo) {
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM events");
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        return [
            'name' => in_array('title', $cols) ? 'title' : (in_array('name', $cols) ? 'name' : null),
            'description' => in_array('event_description', $cols) ? 'event_description' : (in_array('description', $cols) ? 'description' : null),
            'date' => detectDateColumn($cols),
            'address' => in_array('address', $cols) ? 'address' : (in_array('location', $cols) ? 'location' : null),
            'participants' => in_array('participants', $cols) ? 'participants' : (in_array('attendees', $cols) ? 'attendees' : null),
            'points' => in_array('points', $cols) ? 'points' : (in_array('reward_points', $cols) ? 'reward_points' : null),
            'image' => in_array('image', $cols) ? 'image' : (in_array('photo', $cols) ? 'photo' : (in_array('cover', $cols) ? 'cover' : null))
        ];
    } catch (Throwable $e) {
        error_log('Error getting event columns: ' . $e->getMessage());
        return [];
    }
}

function detectDateColumn($cols) {
    foreach (['event_date', 'date', 'start_date', 'created_at'] as $col) {
        if (in_array($col, $cols)) return $col;
    }
    return null;
}

function getEvents($pdo, $cols, $page = 1, $perPage = 10, $search = '', $fromDate = null, $toDate = null) {
    try {
        $where = [];
        $params = [];
        
        if ($search) {
            if ($cols['name']) {
                $where[] = "{$cols['name']} LIKE ?";
                $params[] = "%$search%";
            }
            if ($cols['description']) {
                $where[] = "{$cols['description']} LIKE ?";
                $params[] = "%$search%";
            }
            if ($cols['address']) {
                $where[] = "{$cols['address']} LIKE ?";
                $params[] = "%$search%";
            }
        }
        
        if ($fromDate && $cols['date']) {
            $where[] = "{$cols['date']} >= ?";
            $params[] = $fromDate;
        }
        
        if ($toDate && $cols['date']) {
            $where[] = "{$cols['date']} <= ?";
            $params[] = $toDate;
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' OR ', $where) : '';
        $orderCol = $cols['date'] ? $cols['date'] : 'id';
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM events $whereClause";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get paginated results
        $sql = "SELECT * FROM events $whereClause ORDER BY $orderCol DESC LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        array_push($params, $perPage, $offset);
        $stmt->execute($params);
        
        return [
            'events' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => ceil($total / $perPage)
        ];
    } catch (Throwable $e) {
        error_log('Error fetching events: ' . $e->getMessage());
        return ['events' => [], 'total' => 0, 'pages' => 0];
    }
}

function uploadEventImage($file, $uploadDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload error: ' . $file['error']];
    }
    
    // Validate mime type
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
        return ['error' => 'Invalid image format (jpg/png/gif only)'];
    }
    
    // Generate unique filename
    $ext = image_type_to_extension($info[2], false);
    $basename = bin2hex(random_bytes(10));
    $filename = $basename . '.' . $ext;
    
    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            return ['error' => 'Could not create upload directory'];
        }
    }
    
    $target = $uploadDir . $filename;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return ['error' => 'Failed to save uploaded file'];
    }
    
    return [
        'filename' => $filename,
        'path' => '/Expense_tracker-main/Expense_Tracker/uploads/events/' . $filename
    ];
}

function insertEvent($pdo, $data, $cols) {
    $insertCols = [];
    $placeholders = [];
    $values = [];
    
    // Map form fields to DB columns
    $mappings = [
        'event_name' => $cols['name'],
        'event_date' => $cols['date'],
        'event_description' => $cols['description'],
        'event_address' => $cols['address'],
        'event_participants' => $cols['participants'],
        'event_points' => $cols['points'],
        'image_path' => $cols['image']
    ];
    
    foreach ($mappings as $form_field => $db_col) {
        if ($db_col && isset($data[$form_field]) && $data[$form_field] !== '') {
            $insertCols[] = $db_col;
            $placeholders[] = '?';
            $values[] = $data[$form_field];
        }
    }
    
    if (empty($insertCols)) {
        return ['error' => 'No valid columns to insert'];
    }
    
    try {
        $sql = 'INSERT INTO events (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteEvent($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([(int)$id]);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['error' => 'Could not delete event: ' . $e->getMessage()];
    }
}

function addPointsColumn($pdo) {
    try {
        $pdo->exec("ALTER TABLE events ADD COLUMN points INT NOT NULL DEFAULT 0");
        return ['success' => true];
    } catch (Throwable $e) {
        return ['error' => 'Could not add points column: ' . $e->getMessage()];
    }
}
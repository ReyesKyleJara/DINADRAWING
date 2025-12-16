<?php
// File: DINADRAWING/Backend/events/update.php

header('Content-Type: application/json; charset=utf-8');

// 1. Allow POST method only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid event ID']);
    exit;
}

// 3. Prepare Fields (MATCHED SA SCREENSHOT MO)
$fields = [];
$params = [':id' => $id];

// Name
if (isset($input['name'])) {
    $fields[] = 'name = :name';
    $params[':name'] = trim($input['name']);
}

// Description
if (isset($input['description'])) {
    $fields[] = 'description = :description';
    $params[':description'] = trim($input['description']);
}

// Date (Match sa 'date' column)
if (isset($input['date'])) {
    $fields[] = 'date = :date';
    // Kung empty string, gawing NULL
    $params[':date'] = $input['date'] === '' ? null : trim($input['date']);
}

// Time (Match sa 'time' column)
if (isset($input['time'])) {
    $fields[] = 'time = :time';
    // Kung empty string, gawing NULL
    $params[':time'] = $input['time'] === '' ? null : trim($input['time']);
}

// Location (Match sa 'location' column - HINDI 'place')
if (isset($input['location'])) {
    $fields[] = 'location = :location';
    $params[':location'] = trim($input['location']);
}

if (count($fields) === 0) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

// 4. Database Connection
// Check kung nasaan ang database.php relative sa folder na ito
$dbPath = __DIR__ . "/../config/database.php"; 

if (file_exists($dbPath)) {
    require_once $dbPath;
    $pdo = getDatabaseConnection();
} else {
    // Fallback Connection (Kung sakaling hindi mahanap ang config)
    $host = "127.0.0.1";
    $port = "5432";
    $dbname = "dinadrawing";
    $username = "kai";
    $password = "DND2025";
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
}

// 5. Execute Update
try {
    // Note: Use "events" table name as verified in your screenshot
    $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
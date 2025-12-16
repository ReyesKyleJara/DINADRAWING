<?php
// File: DINADRAWING/Backend/events/delete.php

header('Content-Type: application/json; charset=utf-8');

// 1. Allow POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// 2. Receive JSON Input
$input = json_decode(file_get_contents('php://input'), true);
$eventId = isset($input['id']) ? (int)$input['id'] : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Event ID']);
    exit;
}

// 3. Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) {
    require_once $dbPath;
    $pdo = getDatabaseConnection();
} else {
    // Fallback logic
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

try {
    // 4. Execute Soft Delete (Archive) or Hard Delete
    // Note: Sa 'plan.php', ang tawag ay "Delete Event". Kung gusto mo permanent delete:
    // $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
    
    // Kung gusto mo "Archive" lang (Soft Delete):
    $stmt = $pdo->prepare("UPDATE events SET archived = TRUE WHERE id = :id");
    
    $stmt->execute([':id' => $eventId]);

    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
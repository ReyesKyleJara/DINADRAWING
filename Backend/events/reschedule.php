<?php
// File: DINADRAWING/Backend/events/reschedule.php
session_start();
header('Content-Type: application/json');

// 1. Check Login
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success'=>false, 'error'=>'Unauthorized']); 
    exit; 
}

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$newDate = $input['date'] ?? '';
$userId = $_SESSION['user_id'];

// 3. Connect to Database
require_once __DIR__ . "/../config/database.php";
// Fallback connection if config file is missing
if (function_exists('getDatabaseConnection')) {
    $pdo = getDatabaseConnection();
} else {
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025");
}

try {
    // 4. Verify Ownership (Security)
    $stmt = $pdo->prepare("SELECT owner_id FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || $event['owner_id'] != $userId) {
        echo json_encode(['success'=>false, 'error'=>'Access denied']); 
        exit;
    }

    // 5. Update the Date
    $pdo->prepare("UPDATE events SET date = :date WHERE id = :id")->execute([':date' => $newDate, ':id' => $id]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
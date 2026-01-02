<?php
// File: DINADRAWING/Backend/events/hard_delete.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); 
    exit; 
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

require_once __DIR__ . "/../config/database.php"; 
if (function_exists('getDatabaseConnection')) {
    $pdo = getDatabaseConnection();
} else {
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025");
}

try {
    // 1. Check ownership
    $stmt = $pdo->prepare("SELECT owner_id FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || $event['owner_id'] != $userId) {
        echo json_encode(['success' => false, 'error' => 'Access denied']); 
        exit;
    }

    // 2. Hard Delete (Actually remove the row)
    $pdo->prepare("DELETE FROM events WHERE id = :id")->execute([':id' => $id]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
<?php
// File: DINADRAWING/Backend/events/leave.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success'=>false, 'error'=>'Unauthorized']); 
    exit; 
}

$input = json_decode(file_get_contents('php://input'), true);
$eventId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

require_once __DIR__ . "/../config/database.php";
if (function_exists('getDatabaseConnection')) {
    $pdo = getDatabaseConnection();
} else {
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025");
}

try {
    // Check if user is actually a member
    $stmt = $pdo->prepare("DELETE FROM event_members WHERE event_id = :eid AND user_id = :uid");
    $stmt->execute([':eid' => $eventId, ':uid' => $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'You are not a member of this plan.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
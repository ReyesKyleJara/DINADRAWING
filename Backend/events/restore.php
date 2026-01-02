<?php
// File: DINADRAWING/Backend/events/restore_plan.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

require_once __DIR__ . "/../config/database.php";
$pdo = getDatabaseConnection();

try {
    // Check ownership
    $stmt = $pdo->prepare("SELECT owner_id FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $event = $stmt->fetch();

    if (!$event || $event['owner_id'] != $userId) {
        echo json_encode(['success'=>false, 'error'=>'Access denied']); exit;
    }

    // Restore (Set deleted_at back to NULL)
    $pdo->prepare("UPDATE events SET deleted_at = NULL WHERE id = :id")->execute([':id' => $id]);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
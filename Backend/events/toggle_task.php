<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$item_id = $data['item_id'];

try {
    $pdo = getDatabaseConnection();

    // 1. Get current status
    $stmt = $pdo->prepare("SELECT is_completed FROM task_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $current = $stmt->fetchColumn();

    if ($current === false) { echo json_encode(['success'=>false, 'error'=>'Item not found']); exit; }

    // 2. Toggle status (0 -> 1, 1 -> 0)
    $newStatus = ($current == 1) ? 0 : 1;
    
    $upd = $pdo->prepare("UPDATE task_items SET is_completed = ? WHERE id = ?");
    $upd->execute([$newStatus, $item_id]);

    echo json_encode(['success'=>true, 'new_status'=>$newStatus]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
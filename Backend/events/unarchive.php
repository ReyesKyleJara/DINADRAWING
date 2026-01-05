<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();

    // Verify ownership
    $stmt = $pdo->prepare("SELECT owner_id FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $owner = $stmt->fetchColumn();

    if ($owner != $user_id) {
        echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
    }

    // Set archived = false (Active)
    $pdo->prepare("UPDATE events SET archived = FALSE WHERE id = ?")->execute([$id]);

    echo json_encode(['success'=>true]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
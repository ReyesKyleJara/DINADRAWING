<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();

    // Check Owner
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $ownerId = $stmt->fetchColumn();

    if ($ownerId != $user_id) {
        echo json_encode(['success'=>false, 'error'=>'Permission denied']); exit;
    }

    // Delete
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);
    echo json_encode(['success'=>true]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
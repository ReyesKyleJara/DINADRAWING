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

    // 1. Check Ownership (Only owner can pin their post)
    // Note: You can also allow Event Owners to pin anyone's post by checking event_members role
    $stmt = $pdo->prepare("SELECT user_id, is_pinned FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post || $post['user_id'] != $user_id) {
        echo json_encode(['success'=>false, 'error'=>'Permission denied']); exit;
    }

    // 2. Toggle Pin (0 -> 1, 1 -> 0)
    $newStatus = ($post['is_pinned'] == 1) ? 0 : 1;
    $pdo->prepare("UPDATE posts SET is_pinned = ? WHERE id = ?")->execute([$newStatus, $post_id]);

    echo json_encode(['success'=>true, 'is_pinned'=>$newStatus]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; 
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$post_id) { echo json_encode(['success'=>false, 'error'=>'Missing ID']); exit; }

try {
    $pdo = getDatabaseConnection();
    
    // Check Ownership
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post || $post['user_id'] != $user_id) {
        echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
    }

    // Set Deadline to NOW (Instant End)
    $upd = $pdo->prepare("UPDATE polls SET deadline = CURRENT_TIMESTAMP WHERE post_id = ?");
    $upd->execute([$post_id]);

    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
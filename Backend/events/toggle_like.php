<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Someone';

try {
    $pdo = getDatabaseConnection();
    
    // 1. Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // UNLIKE
        $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
        $action = 'unliked';
    } else {
        // LIKE
        $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $user_id]);
        $action = 'liked';

        // --- NOTIFICATION LOGIC (Fixed Table Name) ---
        // We select from 'posts', NOT 'event_posts'
        $ownerStmt = $pdo->prepare("SELECT user_id, event_id FROM posts WHERE id = ?");
        $ownerStmt->execute([$post_id]);
        $postData = $ownerStmt->fetch(PDO::FETCH_ASSOC);

        // Send notification if the liker is NOT the owner
        if ($postData && $postData['user_id'] != $user_id) {
            $msg = "$user_name liked your post.";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (?, ?, ?, 'like', ?)");
            $notifStmt->execute([$postData['user_id'], $user_id, $post_id, $msg]);
        }
    }

    // Get new count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $count = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'action' => $action, 'new_count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
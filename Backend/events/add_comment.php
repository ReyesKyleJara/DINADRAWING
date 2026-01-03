<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila'); 
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'];
$content = trim($data['content']);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Someone';

if (empty($content)) { echo json_encode(['success'=>false, 'error'=>'Empty comment']); exit; }

try {
    $pdo = getDatabaseConnection();
    
    // Insert Comment
    $stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $content]);
    $newId = $pdo->lastInsertId();

    // Notification
    $ownerStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $ownerStmt->execute([$post_id]);
    $postOwnerId = $ownerStmt->fetchColumn();

    if ($postOwnerId && $postOwnerId != $user_id) {
        $shortContent = (strlen($content) > 30) ? substr($content, 0, 30) . '...' : $content;
        $msg = "$user_name commented: \"$shortContent\"";
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (?, ?, ?, 'comment', ?)");
        $notifStmt->execute([$postOwnerId, $user_id, $post_id, $msg]);
    }
    
    // Return Data
    $userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $avatar = $user['profile_picture'];
    if (!empty($avatar) && strpos($avatar, 'Assets') === 0) {
        $avatar = '/DINADRAWING/' . $avatar;
    } elseif (empty($avatar)) {
        $avatar = '/DINADRAWING/Assets/Profile Icon/profile.png';
    }

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $newId,
            'content' => htmlspecialchars($content),
            'user_name' => $user['name'],
            'user_avatar' => $avatar,
            'created_at' => date('g:i A'),
            'full_date' => date('M j, Y • g:i A') // <--- The JS needs this!
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
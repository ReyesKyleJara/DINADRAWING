<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$event_id = $data['event_id'];
$title = trim($data['title']);
$items = $data['items']; // Array of {text, assigned}

if (empty($title) || empty($items)) {
    echo json_encode(['success'=>false, 'error'=>'Task cannot be empty']); exit;
}

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    // 1. Create Post
    $stmt = $pdo->prepare("INSERT INTO posts (event_id, user_id, post_type, content, created_at) VALUES (?, ?, 'task', '', CURRENT_TIMESTAMP) RETURNING id");
    $stmt->execute([$event_id, $user_id]);
    $post_id = $stmt->fetchColumn(); 

    // 2. Create Task Header
    $taskStmt = $pdo->prepare("INSERT INTO tasks (post_id, title) VALUES (?, ?) RETURNING id");
    $taskStmt->execute([$post_id, $title]);
    $task_id = $taskStmt->fetchColumn();

    // 3. Create Task Items
    $itemStmt = $pdo->prepare("INSERT INTO task_items (task_id, item_text, assigned_to, is_completed) VALUES (?, ?, ?, 0)");
    
    $finalItems = [];
    foreach ($items as $item) {
        if(!empty(trim($item['text']))) {
            $assigned = !empty($item['assigned']) ? $item['assigned'] : null;
            $itemStmt->execute([$task_id, trim($item['text']), $assigned]);
            
            // Add to array for frontend response
            $finalItems[] = [
                'id' => $pdo->lastInsertId(), // or create separate RETURNING logic if needed
                'item_text' => trim($item['text']),
                'assigned_to' => $assigned,
                'is_completed' => 0
            ];
        }
    }

    $pdo->commit();

    // 4. Return Data
    $uStmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    
    $avatar = $user['profile_picture'];
    if (!empty($avatar) && strpos($avatar, 'Assets') === 0) $avatar = '/DINADRAWING/' . $avatar;
    else if (empty($avatar)) $avatar = '/DINADRAWING/Assets/Profile Icon/profile.png';

    $newPost = [
        'id' => $post_id,
        'post_type' => 'task',
        'is_owner' => true,
        'created_at' => 'Just now',
        'is_liked' => false,
        'like_count' => 0,
        'comment_count' => 0,
        'user' => [ 'name' => $user['username'], 'avatar' => $avatar ],
        'task_data' => [
            'id' => $task_id,
            'title' => htmlspecialchars($title),
            'items' => $finalItems
        ]
    ];

    echo json_encode(['success' => true, 'post' => $newPost]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
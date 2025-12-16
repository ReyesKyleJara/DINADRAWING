<?php
// File: DINADRAWING/Backend/events/create_task.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;
$title = trim($input['title'] ?? 'Assigned Tasks');
$deadline = !empty($input['deadline']) ? $input['deadline'] : null;
$items = $input['items'] ?? [];

if ($eventId <= 0 || empty($items)) {
    echo json_encode(['success'=>false, 'error'=>'Invalid data']); exit;
}

$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    $pdo->beginTransaction();

    // 1. Create Post
    $stmt = $pdo->prepare("INSERT INTO posts (event_id, user_id, post_type) VALUES (:eid, :uid, 'task') RETURNING id, created_at");
    $stmt->execute([':eid'=>$eventId, ':uid'=>$userId]);
    $newPost = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Create Task Group
    $stmt = $pdo->prepare("INSERT INTO tasks (post_id, title, deadline) VALUES (:pid, :title, :deadline) RETURNING id");
    $stmt->execute([':pid'=>$newPost['id'], ':title'=>$title, ':deadline'=>$deadline]);
    $taskId = $stmt->fetchColumn();

    // 3. Create Items
    $stmt = $pdo->prepare("INSERT INTO task_items (task_id, item_text, assigned_to) VALUES (:tid, :txt, :assign)");
    $savedItems = [];
    foreach ($items as $item) {
        if (empty(trim($item['text']))) continue;
        $stmt->execute([':tid'=>$taskId, ':txt'=>$item['text'], ':assign'=>$item['assigned']]);
        $savedItems[] = ['id'=>$pdo->lastInsertId(), 'item_text'=>$item['text'], 'assigned_to'=>$item['assigned']];
    }

    $pdo->commit();

    // Return Data for Rendering
    echo json_encode([
        'success' => true,
        'post' => [
            'id' => $newPost['id'],
            'post_type' => 'task',
            'created_at' => date('M j, Y • g:i A', strtotime($newPost['created_at'])),
            'user' => [
                'name' => $_SESSION['username'] ?? 'You',
                'avatar' => $_SESSION['profile_picture'] ?? 'Assets/Profile Icon/profile.png' // You might need the sophisticated logic here too if you want it perfect immediately
            ],
            'task_data' => [
                'title' => htmlspecialchars($title),
                'deadline' => $deadline,
                'items' => $savedItems
            ]
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
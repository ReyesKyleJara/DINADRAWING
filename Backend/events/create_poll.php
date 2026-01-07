<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$event_id = $data['event_id'];
$question = trim($data['question']);
$options = $data['options']; 

// ✅ FIX: Force Integer (1 or 0). 
$allow_multiple = (!empty($data['allow_multiple']) && $data['allow_multiple'] !== 'false') ? 1 : 0;
$is_anonymous = (!empty($data['is_anonymous']) && $data['is_anonymous'] !== 'false') ? 1 : 0;
$allow_user_add = (!empty($data['allow_user_add']) && $data['allow_user_add'] !== 'false') ? 1 : 0;
// ADDED: Get deadline from input
$deadline = !empty($data['deadline']) ? $data['deadline'] : null;

if (empty($question) || count($options) < 2) {
    echo json_encode(['success'=>false, 'error'=>'Invalid poll data']); exit;
}

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    // 1. Create Post
    $stmt = $pdo->prepare("INSERT INTO posts (event_id, user_id, post_type, content, created_at) VALUES (?, ?, 'poll', '', CURRENT_TIMESTAMP) RETURNING id");
    $stmt->execute([$event_id, $user_id]);
    $post_id = $stmt->fetchColumn(); 

    // 2. Create Poll (Strict 1/0)
    // ADDED: deadline column in INSERT
    $pollStmt = $pdo->prepare("INSERT INTO polls (post_id, question, allow_multiple, is_anonymous, allow_user_add, deadline) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
    $pollStmt->execute([$post_id, $question, $allow_multiple, $is_anonymous, $allow_user_add, $deadline]);
    $poll_id = $pollStmt->fetchColumn();

    // 3. Create Options
    $realOptions = [];
    $optStmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text, vote_count) VALUES (?, ?, 0) RETURNING id");
    
    foreach ($options as $opt) {
        if(!empty(trim($opt))) {
            $optStmt->execute([$poll_id, trim($opt)]);
            $newId = $optStmt->fetchColumn();
            $realOptions[] = [
                'id' => $newId, 
                'option_text' => trim($opt), 
                'vote_count' => 0, 
                'is_voted' => false
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
        'post_type' => 'poll',
        'content' => '',
        'created_at' => 'Just now',
        'is_liked' => false,
        'like_count' => 0,
        'comment_count' => 0,
        'user' => [
            'name' => $user['username'],
            'avatar' => $avatar
        ],
        'poll_data' => [
            'id' => $poll_id,
            'question' => htmlspecialchars($question),
            'allow_multiple' => (bool)$allow_multiple,
            'is_anonymous' => (bool)$is_anonymous,
            'allow_user_add' => (bool)$allow_user_add,
            'deadline' => $deadline, // ✅ ADDED: Pass deadline back to frontend
            'total_votes' => 0,
            'options' => $realOptions
        ]
    ];

    echo json_encode(['success' => true, 'post' => $newPost]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
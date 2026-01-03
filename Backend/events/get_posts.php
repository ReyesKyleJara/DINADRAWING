<?php
// File: DINADRAWING/Backend/events/get_posts.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}

$currentUserId = $_SESSION['user_id']; 
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($eventId <= 0) {
    echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit;
}

// Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    // 1. GET POSTS
    $sql = "
        SELECT 
            p.id, p.post_type, p.content, p.image_path, p.created_at,
            u.username AS user_name, 
            u.profile_picture,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = :uid) as is_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.event_id = :eid
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':uid' => $currentUserId]);
    $postsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedPosts = [];
    foreach ($postsDB as $p) {
        // 2. AVATAR PATH FIX
        $dbPic = $p['profile_picture'];
        $avatarPath = "/DINADRAWING/Assets/Profile Icon/profile.png"; 

        if (!empty($dbPic)) {
            if (strpos($dbPic, 'data:') === 0 || strpos($dbPic, 'http') === 0) {
                $avatarPath = $dbPic; 
            } else {
                $avatarPath = "/DINADRAWING/" . ltrim($dbPic, '/');
            }
        }

        // 3. BUILD POST OBJECT
        $post = [
            'id' => $p['id'],
            'post_type' => $p['post_type'] ?? 'standard',
            'content' => htmlspecialchars($p['content'] ?? ''),
            'image_path' => $p['image_path'] ? "/DINADRAWING/" . $p['image_path'] : null,
            'created_at' => date('M j, Y • g:i A', strtotime($p['created_at'])),
            'like_count' => (int)$p['like_count'],
            'comment_count' => (int)$p['comment_count'],
            'is_liked' => ($p['is_liked'] > 0),
            'user' => [
                'name' => !empty($p['user_name']) ? $p['user_name'] : 'Unknown User',
                'avatar' => $avatarPath
            ]
        ];

        // 4. POLL DATA (With Vote Checking)
        if ($post['post_type'] === 'poll') {
            $pollStmt = $pdo->prepare("SELECT id, question, allow_multiple, is_anonymous FROM polls WHERE post_id = :pid");
            $pollStmt->execute([':pid' => $p['id']]);
            $pollInfo = $pollStmt->fetch(PDO::FETCH_ASSOC);

            if ($pollInfo) {
                // Get Options
                $optStmt = $pdo->prepare("SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = :pollId ORDER BY id ASC");
                $optStmt->execute([':pollId' => $pollInfo['id']]);
                $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get MY Votes (To highlight yellow)
                $myVotes = [];
                try {
                    $mvStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                    $mvStmt->execute([$pollInfo['id'], $currentUserId]);
                    $myVotes = $mvStmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {
                    // Ignore error if table missing to prevent crash
                }

                $totalVotes = 0;
                foreach ($options as &$opt) {
                    $totalVotes += $opt['vote_count'];
                    $opt['is_voted'] = in_array($opt['id'], $myVotes); // TRUE if I voted for this
                }

                $post['poll_data'] = [
                    'id' => $pollInfo['id'], // Needed for click event
                    'question' => htmlspecialchars($pollInfo['question']),
                    'allow_multiple' => $pollInfo['allow_multiple'] === true, 
                    'is_anonymous' => $pollInfo['is_anonymous'] === true, 
                    'total_votes' => $totalVotes,
                    'options' => $options
                ];
            }
        }

        // 5. TASK DATA
        if ($post['post_type'] === 'task') {
            $taskStmt = $pdo->prepare("SELECT id, title, deadline FROM tasks WHERE post_id = :pid");
            $taskStmt->execute([':pid' => $p['id']]);
            $taskInfo = $taskStmt->fetch(PDO::FETCH_ASSOC);

            if ($taskInfo) {
                $itemStmt = $pdo->prepare("SELECT id, item_text, assigned_to FROM task_items WHERE task_id = :tid ORDER BY id ASC");
                $itemStmt->execute([':tid' => $taskInfo['id']]);
                $taskItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                $post['task_data'] = [
                    'title' => htmlspecialchars($taskInfo['title']),
                    'deadline' => $taskInfo['deadline'],
                    'items' => $taskItems
                ];
            }
        }

        $formattedPosts[] = $post;
    }

    echo json_encode(['success' => true, 'posts' => $formattedPosts]);

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
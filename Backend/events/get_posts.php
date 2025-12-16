<?php
// File: DINADRAWING/Backend/events/get_posts.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($eventId <= 0) {
    echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit;
}

// Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    // KINUKUHA ANG POSTS + USER INFO
    $sql = "
        SELECT 
            p.id, p.post_type, p.content, p.image_path, p.created_at,
            u.username AS user_name, 
            u.profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.event_id = :eid
        ORDER BY p.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId]);
    $postsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedPosts = [];
    foreach ($postsDB as $p) {
        // 1. SMART AVATAR PATH (Fix para sa 414 Error)
        $dbPic = $p['profile_picture'];
        $avatarPath = "/DINADRAWING/Assets/Profile Icon/profile.png"; // Default fallback

        if (!empty($dbPic)) {
            // Kapag Base64 (data:image...) o URL na siya, gamitin as-is
            if (strpos($dbPic, 'data:') === 0 || strpos($dbPic, 'http') === 0) {
                $avatarPath = $dbPic; 
            } else {
                // Kapag filename lang, lagyan ng path
                $avatarPath = "/DINADRAWING/" . ltrim($dbPic, '/');
            }
        }

        // 2. DEFINE POST ARRAY
        $post = [
            'id' => $p['id'],
            'post_type' => $p['post_type'] ?? 'standard', // Default to standard kung null
            'content' => htmlspecialchars($p['content'] ?? ''),
            'image_path' => $p['image_path'] ? "/DINADRAWING/" . $p['image_path'] : null,
            'created_at' => date('M j, Y • g:i A', strtotime($p['created_at'])),
            'user' => [
                'name' => !empty($p['user_name']) ? $p['user_name'] : 'Unknown User',
                'avatar' => $avatarPath
            ]
        ];

        // 3. FETCH DATA BASED ON TYPE

        // A. POLL DATA
        if ($post['post_type'] === 'poll') {
            $pollStmt = $pdo->prepare("SELECT id, question, allow_multiple, is_anonymous FROM polls WHERE post_id = :pid");
            $pollStmt->execute([':pid' => $p['id']]);
            $pollInfo = $pollStmt->fetch(PDO::FETCH_ASSOC);

            if ($pollInfo) {
                $optStmt = $pdo->prepare("SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = :pollId ORDER BY id ASC");
                $optStmt->execute([':pollId' => $pollInfo['id']]);
                $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

                $totalVotes = 0;
                foreach ($options as $opt) $totalVotes += $opt['vote_count'];

                $post['poll_data'] = [
                    'question' => htmlspecialchars($pollInfo['question']),
                    'allow_multiple' => $pollInfo['allow_multiple'] === true, 
                    'is_anonymous' => $pollInfo['is_anonymous'] === true, 
                    'total_votes' => $totalVotes,
                    'options' => $options
                ];
            }
        }

        // B. TASK DATA (This is the NEW part)
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
                    'items' => $taskItems // Contains item_text, assigned_to
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
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}

$currentUserId = $_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($eventId <= 0) { echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit; }

require_once __DIR__ . "/../config/database.php"; 
$pdo = getDatabaseConnection();

try {
    // 1. GET POSTS
    $sql = "
        SELECT 
            p.id, p.user_id, p.post_type, p.content, p.image_path, p.created_at, p.is_pinned, 
            u.username AS user_name, 
            u.profile_picture,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = :uid) as is_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.event_id = :eid
        ORDER BY p.is_pinned DESC, p.created_at DESC 
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':uid' => $currentUserId]);
    $postsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedPosts = [];
    foreach ($postsDB as $p) {
        // Fix Avatar Path Helper
        $avatarPath = '/DINADRAWING/Assets/Profile Icon/profile.png'; 
        if (!empty($p['profile_picture'])) {
            $avatarPath = (strpos($p['profile_picture'], 'Assets') === 0) 
                ? '/DINADRAWING/' . $p['profile_picture'] 
                : $p['profile_picture'];
        }

        $post = [
            'id' => $p['id'],
            'post_type' => $p['post_type'] ?? 'standard',
            'content' => htmlspecialchars($p['content'] ?? ''),
            'is_owner' => ($p['user_id'] == $currentUserId),
            'is_pinned' => ($p['is_pinned'] == 1), 

            'image_path' => $p['image_path'] ? "/DINADRAWING/" . $p['image_path'] : null,
            'created_at' => date('M j, Y • g:i A', strtotime($p['created_at'])),
            'like_count' => (int)$p['like_count'],
            'comment_count' => (int)$p['comment_count'],
            'is_liked' => ($p['is_liked'] > 0),
            'user' => [ 'name' => $p['user_name'], 'avatar' => $avatarPath ]
        ];

        // --- POLL DATA (With Voter Images) ---
        if ($post['post_type'] === 'poll') {
            $pollStmt = $pdo->prepare("SELECT id, question, allow_multiple, is_anonymous FROM polls WHERE post_id = ?");
            $pollStmt->execute([$p['id']]);
            $pollInfo = $pollStmt->fetch(PDO::FETCH_ASSOC);

            if ($pollInfo) {
                $optStmt = $pdo->prepare("SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
                $optStmt->execute([$pollInfo['id']]);
                $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

                // Check my votes
                $myVotes = [];
                try {
                    $mvStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                    $mvStmt->execute([$pollInfo['id'], $currentUserId]);
                    $myVotes = $mvStmt->fetchAll(PDO::FETCH_COLUMN);
                } catch(Exception $e){}

                $totalVotes = 0;
                $isAnon = ($pollInfo['is_anonymous'] == 1);

                foreach ($options as &$opt) {
                    $totalVotes += $opt['vote_count'];
                    $opt['is_voted'] = in_array($opt['id'], $myVotes);
                    
                    // --- FETCH VOTER AVATARS (Limit 3) ---
                    $opt['voters'] = [];
                    if (!$isAnon && $opt['vote_count'] > 0) {
                        $vStmt = $pdo->prepare("
                            SELECT u.profile_picture 
                            FROM poll_votes pv 
                            JOIN users u ON pv.user_id = u.id 
                            WHERE pv.option_id = ? 
                            ORDER BY pv.created_at DESC 
                            LIMIT 3
                        ");
                        $vStmt->execute([$opt['id']]);
                        $rawVoters = $vStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // Format paths
                        foreach($rawVoters as $rawPic) {
                            $vPath = '/DINADRAWING/Assets/Profile Icon/profile.png';
                            if(!empty($rawPic)) {
                                $vPath = (strpos($rawPic, 'Assets') === 0) ? '/DINADRAWING/'.$rawPic : $rawPic;
                            }
                            $opt['voters'][] = $vPath;
                        }
                    }
                }

                $post['poll_data'] = [
                    'id' => $pollInfo['id'],
                    'question' => htmlspecialchars($pollInfo['question']),
                    'allow_multiple' => ($pollInfo['allow_multiple'] == 1),
                    'is_anonymous' => $isAnon,
                    'total_votes' => $totalVotes,
                    'options' => $options
                ];
            }
        }


        // --- TASK DATA ---
        if ($post['post_type'] === 'task') {
            $taskStmt = $pdo->prepare("SELECT id, title, deadline FROM tasks WHERE post_id = ?");
            $taskStmt->execute([$p['id']]);
            $taskInfo = $taskStmt->fetch(PDO::FETCH_ASSOC);

            if ($taskInfo) {
                // ✅ UPDATE: Select is_completed
                $itemStmt = $pdo->prepare("SELECT id, item_text, assigned_to, is_completed FROM task_items WHERE task_id = ? ORDER BY id ASC");
                $itemStmt->execute([$taskInfo['id']]);
                
                $post['task_data'] = [
                    'id' => $taskInfo['id'],
                    'title' => htmlspecialchars($taskInfo['title']),
                    'deadline' => $taskInfo['deadline'],
                    'items' => $itemStmt->fetchAll(PDO::FETCH_ASSOC)
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
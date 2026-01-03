<?php
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

$post_id = $_GET['post_id'] ?? 0;

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT c.id, c.content, c.created_at, u.name as user_name, u.profile_picture as user_avatar
        FROM post_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$post_id]);
    $rawComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $comments = [];
    foreach ($rawComments as $c) {
        // Fix Avatar
        $avatar = $c['user_avatar'];
        if (!empty($avatar) && strpos($avatar, 'Assets') === 0) {
            $avatar = '/DINADRAWING/' . $avatar;
        } elseif (empty($avatar)) {
             $avatar = '/DINADRAWING/Assets/Profile Icon/profile.png';
        }

        // --- SMART DATE LOGIC ---
        $ts = strtotime($c['created_at']);
        $now = time();
        $diff = $now - $ts;
        $daysAgo = floor($diff / (60 * 60 * 24));

        // 1. Short Version (Display)
        if ($daysAgo == 0) {
            $shortTime = date('g:i A', $ts); // Today: "12:55 PM"
        } elseif ($daysAgo == 1) {
            $shortTime = "Yesterday";
        } elseif ($daysAgo < 3) {
            $shortTime = "$daysAgo days ago";
        } else {
            $shortTime = date('M j', $ts);   // Older: "Jan 3"
        }

        // 2. Full Version (Tooltip) - THIS IS WHAT WAS MISSING
        $fullDate = date('M j, Y • g:i A', $ts); 

        $comments[] = [
            'id' => $c['id'],
            'content' => htmlspecialchars($c['content']),
            'user_name' => htmlspecialchars($c['user_name']),
            'user_avatar' => $avatar,
            'created_at' => $shortTime, 
            'full_date' => $fullDate    // <--- The JS needs this!
        ];
    }

    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
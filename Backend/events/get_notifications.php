<?php
// Set Timezone to Philippines (or your local time) to fix the "6 hours ago" bug
date_default_timezone_set('Asia/Manila');

session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'notifications'=>[]]); exit; }

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();

    // Fetch notifications
    $stmt = $pdo->prepare("
        SELECT 
            n.id, n.type, n.created_at, n.is_read,
            u.name as actor_name, 
            u.profile_picture as actor_avatar,
            e.name as event_name,
            e.id as event_id
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        JOIN posts p ON n.post_id = p.id
        JOIN events e ON p.event_id = e.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $rawNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($rawNotifs as $n) {
        // Avatar Logic
        $avatar = $n['actor_avatar'];
        if (!empty($avatar) && strpos($avatar, 'Assets') === 0) {
            $avatar = '/DINADRAWING/' . $avatar;
        } elseif (empty($avatar)) {
             $avatar = '/DINADRAWING/Assets/Profile Icon/profile.png';
        }

        $actionText = "updated something in";
        if ($n['type'] === 'like') $actionText = "liked your post in";
        if ($n['type'] === 'comment') $actionText = "commented on your post in";

        $formatted[] = [
            'id' => $n['id'],
            'actor_name' => htmlspecialchars($n['actor_name']),
            'actor_avatar' => $avatar,
            'action_text' => $actionText,
            'event_name' => htmlspecialchars($n['event_name']),
            'is_read' => $n['is_read'],
            // Pass true to ensure it calculates based on current timezone
            'time_ago' => time_elapsed_string($n['created_at'])
        ];
    }

    echo json_encode(['success' => true, 'notifications' => $formatted]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Updated Helper Function to handle Timezones correctly
function time_elapsed_string($datetime, $full = false) {
    // 1. Create 'Now' using the set timezone (Asia/Manila)
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    
    // 2. Create 'Ago' from the DB string. 
    //    We assume the DB stores it as 'YYYY-MM-DD HH:MM:SS' in the same local time (from CURRENT_TIMESTAMP)
    //    If your DB is actually UTC, you might need to convert it here. 
    //    For most local setups (XAMPP/Postgres Local), DB matches System Time.
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year', 'm' => 'month', 'w' => 'week',
        'd' => 'day', 'h' => 'hour', 'i' => 'min', 's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
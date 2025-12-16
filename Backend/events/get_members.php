<?php
// File: DINADRAWING/Backend/events/get_members.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_GET['event_id'])) {
    echo json_encode(['success'=>false, 'members'=>[]]); exit;
}

$eventId = (int)$_GET['event_id'];

$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    // 1. Get Event Owner ID First
    $stmt = $pdo->prepare("SELECT user_id FROM events WHERE id = :eid");
    $stmt->execute([':eid' => $eventId]);
    $ownerId = $stmt->fetchColumn();

    // 2. Fetch Members + Owner (Using UNION to ensure owner is included)
    // We select users who are in event_members OR who are the creator
    $sql = "
        SELECT DISTINCT u.id, u.username, u.profile_picture 
        FROM users u
        LEFT JOIN event_members em ON u.id = em.user_id AND em.event_id = :eid
        WHERE em.user_id IS NOT NULL OR u.id = :ownerId
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':ownerId' => $ownerId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format Response
    $formatted = [];
    foreach($members as $m) {
        $pic = $m['profile_picture'];
        $avatar = "/DINADRAWING/Assets/Profile Icon/profile.png"; 
        
        if (!empty($pic)) {
            if (strpos($pic, 'data:') === 0 || strpos($pic, 'http') === 0) {
                $avatar = $pic;
            } else {
                $avatar = "/DINADRAWING/" . ltrim($pic, '/');
            }
        }
        
        $formatted[] = [
            'id' => $m['id'],
            'name' => $m['username'],
            'avatar' => $avatar,
            'is_owner' => ($m['id'] == $ownerId) // <--- THIS FLAG IS KEY
        ];
    }

    // Sort so Owner is always first
    usort($formatted, function($a, $b) {
        return $b['is_owner'] - $a['is_owner'];
    });

    echo json_encode(['success'=>true, 'members'=>$formatted]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
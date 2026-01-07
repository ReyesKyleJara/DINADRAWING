<?php
// File: DINADRAWING/Backend/events/join_event.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in first.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a code.']); exit;
}

require_once __DIR__ . "/../config/database.php";
$pdo = getDatabaseConnection();

try {
    // 1. Find Event by Code - Updated to also get the owner_id
    $stmt = $pdo->prepare("SELECT id, owner_id FROM events WHERE invite_code = :code");
    $stmt->execute([':code' => strtoupper($code)]); // Case-insensitive
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(['success' => false, 'error' => 'Invalid event code.']); exit;
    }

    $eventId = $event['id'];
    $ownerId = $event['owner_id']; // The creator who will receive the notification
    $userId = $_SESSION['user_id']; // The person joining now

    // 2. Add User to Members (Ignore if already joined)
    $sql = "INSERT INTO event_members (event_id, user_id, role) 
            VALUES (:eid, :uid, 'member') 
            ON CONFLICT (event_id, user_id) DO NOTHING";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':uid' => $userId]);

    // Check if the user actually joined (rowCount > 0 means it's a new join, not a duplicate)
    $isNewJoin = $stmt->rowCount() > 0;

    // 3. Create Notification for the Creator
    // We only notify if it's a new join and the joiner is NOT the creator
    if ($isNewJoin && $ownerId != $userId) {
        $notifSql = "INSERT INTO notifications (user_id, actor_id, event_id, type, is_read, created_at)
                     VALUES (:owner_id, :actor_id, :event_id, 'join', false, NOW())";
        $notifStmt = $pdo->prepare($notifSql);
        $notifStmt->execute([
            ':owner_id' => $ownerId,
            ':actor_id' => $userId,
            ':event_id' => $eventId
        ]);
    }

    echo json_encode(['success' => true, 'event_id' => $eventId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
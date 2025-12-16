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
    // 1. Find Event by Code
    $stmt = $pdo->prepare("SELECT id FROM events WHERE invite_code = :code");
    $stmt->execute([':code' => strtoupper($code)]); // Case-insensitive
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(['success' => false, 'error' => 'Invalid event code.']); exit;
    }

    $eventId = $event['id'];
    $userId = $_SESSION['user_id'];

    // 2. Add User to Members (Ignore if already joined)
    $sql = "INSERT INTO event_members (event_id, user_id, role) 
            VALUES (:eid, :uid, 'member') 
            ON CONFLICT (event_id, user_id) DO NOTHING";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':uid' => $userId]);

    echo json_encode(['success' => true, 'event_id' => $eventId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
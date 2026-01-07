<?php
header('Content-Type: application/json');
session_start();

// 1. Connection logic (Match your plan.php settings)
$host = "127.0.0.1";
$port = "5432";
$dbname = "dinadrawing";
$username = "kai";
$password = "DND2025";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $user_id = $_SESSION['user_id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    $post_id = $data['post_id'] ?? null;

    if (!$user_id || !$post_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized or missing data']);
        exit;
    }

    // 2. Permission Check: Verify user is owner of the post or admin of the event
    // We join posts with event_members to check roles
    $checkStmt = $conn->prepare("
        SELECT p.id, em.role 
        FROM posts p
        JOIN event_members em ON p.event_id = em.event_id
        WHERE p.id = :pid AND em.user_id = :uid
    ");
    $checkStmt->execute(['pid' => $post_id, 'uid' => $user_id]);
    $access = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$access || !in_array($access['role'], ['owner', 'admin'])) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    // 3. Toggle the pin status
    $stmt = $conn->prepare("UPDATE posts SET is_pinned = NOT is_pinned WHERE id = :pid RETURNING is_pinned");
    $stmt->execute(['pid' => $post_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'is_pinned' => $result['is_pinned']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
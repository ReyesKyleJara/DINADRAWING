<?php
// Backend/events/unclaim_task.php
header('Content-Type: application/json');
session_start();

// 1. DATABASE CONNECTION (Direct Fix)
$host = "127.0.0.1";
$port = "5432";
$dbname = "dinadrawing";
$username = "kai";
$password = "DND2025";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$itemId = $data['item_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$itemId || !$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // 2. GET USER NAME
    $userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $userName = $user['name'] ?? '';

    // 3. UNCLAIM LOGIC (Only if the task is assigned to YOU)
    $stmt = $conn->prepare("UPDATE task_items SET assigned_to = NULL WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$itemId, $userName]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'You cannot unclaim a task assigned to someone else.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
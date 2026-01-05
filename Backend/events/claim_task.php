<?php
// Backend/events/claim_task.php
header('Content-Type: application/json');
session_start();

// 1. DATABASE CONNECTION (Directly included to prevent 500 errors)
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// 2. GET DATA
$data = json_decode(file_get_contents("php://input"), true);
$itemId = $data['item_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$itemId || !$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // 3. GET USER NAME
    $userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $userName = $user['name'] ?? 'Member';

    // 4. ASSIGN TASK (Only if empty)
    $stmt = $conn->prepare("UPDATE task_items SET assigned_to = ? WHERE id = ? AND (assigned_to IS NULL OR assigned_to = '')");
    $stmt->execute([$userName, $itemId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Task already taken or invalid.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
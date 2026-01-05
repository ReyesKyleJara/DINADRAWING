<?php
// Backend/events/delete_task_item.php
header('Content-Type: application/json');
session_start();

// 1. DATABASE CONNECTION
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

// 2. GET INPUT
$data = json_decode(file_get_contents("php://input"), true);
$itemId = $data['item_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$itemId || !$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // 3. DELETE ITEM
    // Since you wanted "visible to everyone", we act similarly to a wiki:
    // Any logged-in member can delete an item.
    $stmt = $conn->prepare("DELETE FROM task_items WHERE id = ?");
    $stmt->execute([$itemId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
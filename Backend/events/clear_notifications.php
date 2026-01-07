<?php
// FILE: Backend/events/clear_notifications.php
session_start();
header('Content-Type: application/json');

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

require '../../Backend/db_connection.php'; // Verify this path is correct for your folder structure!

try {
    if (!isset($pdo)) {
        // Fallback connection if require fails
        $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    $userId = $_SESSION['user_id'];

    // 2. Perform the DELETE
    $sql = "DELETE FROM notifications WHERE user_id = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    
    // Check if any rows were actually touched (optional, but good for debugging)
    $count = $stmt->rowCount();

    echo json_encode(['success' => true, 'deleted_count' => $count]);

} catch (PDOException $e) {
    // Return the specific database error so we can see it in the console
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
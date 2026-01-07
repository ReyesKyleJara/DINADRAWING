<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'error' => 'Auth required']); 
    exit; 
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();
    
    // Updates all notifications for the logged-in user to 'read'
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) { 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); 
}
?>
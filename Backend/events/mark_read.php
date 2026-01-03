<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { exit; }
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) { echo json_encode(['success' => false]); }
?>
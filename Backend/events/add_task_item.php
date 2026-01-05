<?php
// Backend/events/add_task_item.php

// 1. Setup Error Handling (Prevents "Unexpected end of JSON" errors)
ini_set('display_errors', 0); // Don't print errors to screen (breaks JSON)
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

$response = [];

try {
    // 2. DATABASE CONNECTION
    $host = "127.0.0.1";
    $port = "5432";
    $dbname = "dinadrawing";
    $username = "kai";
    $password = "DND2025";

    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 3. GET INPUT
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("No data received");
    }

    $taskId = $data['task_id'] ?? null; // This is the ID from the 'tasks' table
    $text = trim($data['text'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    // 4. VALIDATION
    if (!$userId) throw new Exception("You must be logged in.");
    if (!$taskId) throw new Exception("Task ID is missing.");
    if (!$text)   throw new Exception("Task text cannot be empty.");

    // 5. CHECK PERMISSION
    // We assume 'task_id' comes from the 'tasks' table.
    // We join 'tasks' -> 'posts' to check 'allow_user_add' in the 'posts' table.
    $permSql = "
        SELECT p.allow_user_add, p.user_id 
        FROM tasks t
        JOIN posts p ON t.post_id = p.id
        WHERE t.id = ?
    ";
    $permStmt = $conn->prepare($permSql);
    $permStmt->execute([$taskId]);
    $row = $permStmt->fetch();

    if (!$row) {
        throw new Exception("Task list not found.");
    }

    // Check: Is Owner OR Is Allowed?
    $isOwner = ($row['user_id'] == $userId);
    // Handle Postgres Boolean (t/f/1/0)
    $isAllowed = ($row['allow_user_add'] === true || $row['allow_user_add'] === 't' || $row['allow_user_add'] === 1);

    if (!$isOwner && !$isAllowed) {
        throw new Exception("The owner has disabled adding new tasks.");
    }

    // 6. INSERT NEW ITEM
    // 'assigned_to' is NULL so anyone can volunteer
    $insertSql = "INSERT INTO task_items (task_id, item_text, is_completed) VALUES (?, ?, 0)";
    $stmt = $conn->prepare($insertSql);
    $stmt->execute([$taskId, $text]);

    $response['success'] = true;

} catch (Exception $e) {
    // Catch any error and send it as JSON
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
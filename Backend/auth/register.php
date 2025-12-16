<?php
// Path: DINADRAWING/Backend/auth/register.php

header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Go up one level (..) to find 'config' folder
require_once __DIR__ . "/../config/database.php";

$data = json_decode(file_get_contents("php://input"), true);

// 1. Validate Input
if (
    !isset($data['name']) ||
    !isset($data['email']) ||
    !isset($data['password']) ||
    !isset($data['username'])
) {
    echo json_encode(["success" => false, "message" => "All fields including Username are required"]);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$username = trim($data['username']);
$password = $data['password'];

// 2. Database Connection
$db = getDatabaseConnection();
if (!$db) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// 3. Check if Email OR Username already exists
$checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1");
$checkStmt->execute(["email" => $email, "username" => $username]);

if ($checkStmt->fetch()) {
    echo json_encode(["success" => false, "message" => "Email or Username already taken"]);
    exit;
}

// 4. Create User
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertStmt = $db->prepare("
    INSERT INTO users (username, name, email, password_hash, created_at)
    VALUES (:username, :name, :email, :password, NOW())
    RETURNING id
");

try {
    $insertStmt->execute([
        "username" => $username,
        "name" => $name,
        "email" => $email,
        "password" => $passwordHash
    ]);

    $userId = $insertStmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "user" => [
            "id" => $userId,
            "username" => $username,
            "name" => $name,
            "email" => $email
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Registration failed: " . $e->getMessage()]);
}
?>
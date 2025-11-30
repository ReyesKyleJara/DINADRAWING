<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db_connection.php';

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');
$name = trim($data['name'] ?? null);

// Validate required fields
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and password are required"]);
    exit;
}

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Create table if not exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id, email, name, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Optional: verify password if this is a login attempt
        if (password_verify($password, $user['password_hash'])) {
            echo json_encode(["success" => true, "message" => "User logged in", "user" => [
                "id" => $user['id'],
                "email" => $user['email'],
                "name" => $user['name']
            ]]);
        } else {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid password"]);
        }
        exit;
    }

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
    $stmt->execute([$email, $password_hash, $name]);

    // Return newly created user
    $stmt = $conn->prepare("SELECT id, email, name, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "message" => "User registered successfully", "user" => $user]);

} catch (PDOException $e) {
    if ($e->getCode() == 23505) { // Unique violation
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "User already exists"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
}

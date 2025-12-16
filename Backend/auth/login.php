<?php
// /DINADRAWING/Backend/auth/login.php

// 1. ENABLE ERROR REPORTING (So we see the error instead of just "500")
ini_set('display_errors', 0); // Hide from HTML output
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Start Session
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 2. SMART DATABASE PATH FINDER
    // This checks all possible locations for your database file
    $possiblePaths = [
        __DIR__ . "/../config/database.php",       // If auth & config are siblings
        __DIR__ . "/../api/config/database.php",   // If auth is outside, but config is in api
        __DIR__ . "/../../api/config/database.php" // If auth is deeper
    ];

    $dbPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $dbPath = $path;
            break;
        }
    }

    if (!$dbPath) {
        // If we still can't find it, show EXACTLY where we looked
        throw new Exception("Critical: database.php not found. Checked: " . implode(", ", $possiblePaths));
    }

    require_once $dbPath;
    
    // 3. TEST CONNECTION
    $db = getDatabaseConnection();
    if (!$db) {
        throw new Exception("Database connection failed. Check credentials in database.php");
    }

    // 4. PROCESS LOGIN
    $data = json_decode(file_get_contents("php://input"), true);
    $login = trim($data['login'] ?? '');
    $password = $data['password'] ?? '';

    if (!$login || !$password) {
        echo json_encode(["success" => false, "message" => "Username/Email and Password are required"]);
        exit;
    }

    // Login Query
    $stmt = $db->prepare("
        SELECT id, username, name, email, password_hash, profile_picture
        FROM users
        WHERE email = :login OR username = :login
        LIMIT 1
    ");

    $stmt->execute(["login" => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    // Set Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_picture'] = $user['profile_picture'] ?? 'Assets/Profile Icon/profile.png';
    $_SESSION['logged_in'] = true;

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => $user
    ]);

} catch (Exception $e) {
    // 5. RETURN ERROR AS JSON (Fixes the 500 issue)
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>
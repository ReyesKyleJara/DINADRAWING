<?php


require_once 'db_connection.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get input JSON as associative array
$input = json_decode(file_get_contents("php://input"), true);
$firebase_uid = $input['firebase_uid'] ?? '';
$name = $input['name'] ?? '';
$email = $input['email'] ?? '';

// Validate required field
if (empty($firebase_uid)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Firebase UID is required"]);
    exit;
}

try {
    // Begin transaction to prevent race conditions
    $conn->beginTransaction();

    // Look for existing user
    $stmt = $conn->prepare("SELECT * FROM users WHERE firebase_uid = ?");
    $stmt->execute([$firebase_uid]);

    if ($stmt->rowCount() > 0) {
        // User exists - return user info
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $conn->commit();
        echo json_encode([
            "success" => true, 
            "message" => "User logged in successfully",
            "user" => $user
        ]);
        exit;
    }

    // User doesn't exist - create new user
    $insert = $conn->prepare("
        INSERT INTO users (firebase_uid, name, email) 
        VALUES (?, ?, ?)
        RETURNING id, firebase_uid, name, email, created_at
    ");
    
    $insert->execute([$firebase_uid, $name, $email]);
    $newUser = $insert->fetch(PDO::FETCH_ASSOC);
    
    $conn->commit();

    echo json_encode([
        "success" => true, 
        "message" => "User created successfully",
        "user" => $newUser
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Handle duplicate entry error
    if ($e->getCode() == 23505) { // PostgreSQL unique violation
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "User already exists"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
}
?>
<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get all users
            $stmt = $conn->query("SELECT id, name, email, created_at FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "success" => true,
                "users" => $users
            ]);
            break;

        case 'POST':
            // Create user
            $data = json_decode(file_get_contents("php://input"));
            
            if (empty($data->name) || empty($data->email) || empty($data->password)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "All fields required"]);
                break;
            }
            
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$data->email]);
            if ($check->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(["success" => false, "message" => "Email exists"]);
                break;
            }
            
            // Insert user
            $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$data->name, $data->email, $hashed_password])) {
                echo json_encode(["success" => true, "message" => "User created"]);
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Creation failed"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
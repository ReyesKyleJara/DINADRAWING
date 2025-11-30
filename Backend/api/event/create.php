<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// HEADERS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// HANDLE PREFLIGHT 
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode([
        "success" => false, 
        "message" => "Method Not Allowed. Expected POST, got: " . $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

try {
    // DATABASE CONNECTION 
    $host = "127.0.0.1";
    $port = "5432";
    $dbname = "dinadrawing";
    $username = "kai";
    $password = "DND2025"; 

    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // PROCESS DATA
    $input = file_get_contents("php://input");
    $data = json_decode($input);

    if (!$data) {
        throw new Exception("No JSON data received.");
    }

    $name = trim($data->name ?? '');
    $description = trim($data->description ?? '');
    $date = trim($data->date ?? '');
    $time = trim($data->time ?? '');
    $location = trim($data->location ?? ''); 

    // Validation
    if ($name === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Event name is required"]);
        exit;
    }

    // Create Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS events (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            date DATE,
            time TIME,
            location VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Insert
    $sql = "INSERT INTO events (name, description, date, time, location)
            VALUES (:name, :description, :date, :time, :location)
            RETURNING id";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':description', $description ?: null);
    $stmt->bindValue(':date', $date ?: null);
    $stmt->bindValue(':time', $time ?: null);
    $stmt->bindValue(':location', $location ?: null);

    if ($stmt->execute()) {
        $newId = $stmt->fetchColumn();
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Event created", "id" => $newId]);
    } else {
        throw new Exception("Database insert failed.");
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
?>
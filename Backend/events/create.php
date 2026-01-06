<?php
// File: DINADRAWING/Backend/events/create.php

// 1. START SESSION
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// 2. CHECK IF LOGGED IN
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: You must be logged in."]);
    exit;
}

$owner_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit;
}

// 3. DATABASE CONNECTION
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) {
    require_once $dbPath;
    $conn = getDatabaseConnection();
} else {
    // Fallback connection
    $conn = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

try {
    // 4. PROCESS INPUT
    $input = file_get_contents("php://input");
    $data = json_decode($input);

    if (!$data) {
        throw new Exception("Invalid JSON data received.");
    }

    $name = trim($data->name ?? '');
    $descriptionRaw = trim($data->description ?? '');
    $dateRaw = trim($data->date ?? '');
    $timeRaw = trim($data->time ?? '');
    $locationRaw = trim($data->location ?? ''); 

    // Convert empty strings to NULL for PostgreSQL compatibility
    $description = ($descriptionRaw === '') ? null : $descriptionRaw;
    $date = ($dateRaw === '') ? null : $dateRaw;
    $time = ($timeRaw === '') ? null : $timeRaw;
    $location = ($locationRaw === '') ? null : $locationRaw;

    if ($name === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Event name is required"]);
        exit;
    }

    // GENERATE RANDOM 6-CHAR CODE
    $inviteCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

    // --- START TRANSACTION ---
    // This ensures both the event and the member entry are created together
    $conn->beginTransaction();

    // 5. INSERT QUERY
    $sql = "INSERT INTO events (owner_id, name, description, date, time, location, invite_code)
            VALUES (:owner_id, :name, :description, :date, :time, :location, :code)
            RETURNING id";

    $stmt = $conn->prepare($sql);
    
    // Explicit Type Binding
    $stmt->bindValue(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    
    // Explicitly handle NULLs for Postgres types (DATE, TIME, TEXT)
    $stmt->bindValue(':description', $description, $description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':date', $date, $date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':time', $time, $time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':location', $location, $location === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':code', $inviteCode, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $newId = $stmt->fetchColumn();
        
        // 6. AUTO-JOIN OWNER AS ADMIN
        $joinSql = "INSERT INTO event_members (event_id, user_id, role) VALUES (:eid, :uid, 'admin')";
        $joinStmt = $conn->prepare($joinSql);
        $joinStmt->execute([':eid' => $newId, ':uid' => $owner_id]);

        // COMMIT CHANGES
        $conn->commit();

        http_response_code(201);
        echo json_encode([
            "success" => true, 
            "message" => "Event created", 
            "id" => $newId,
            "code" => $inviteCode
        ]);
    } else {
        throw new Exception("Database insert failed.");
    }

} catch (Exception $e) {
    // ROLLBACK IF ANYTHING FAILED
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>  
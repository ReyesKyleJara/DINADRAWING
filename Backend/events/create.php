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
    $conn = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

try {
    // 4. PROCESS INPUT
    $input = file_get_contents("php://input");
    $data = json_decode($input);

    $name = trim($data->name ?? '');
    $descriptionRaw = trim($data->description ?? '');
    $dateRaw = trim($data->date ?? '');
    $timeRaw = trim($data->time ?? '');
    $locationRaw = trim($data->location ?? ''); 

    // --- FIX FOR POSTGRESQL (Convert empty strings to NULL) ---
    $description = ($descriptionRaw === '') ? null : $descriptionRaw;
    $date = ($dateRaw === '') ? null : $dateRaw;
    $time = ($timeRaw === '') ? null : $timeRaw;
    $location = ($locationRaw === '') ? null : $locationRaw;

    if ($name === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Event name is required"]);
        exit;
    }

    // --- 1. GENERATE RANDOM 6-CHAR CODE ---
    $inviteCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

    // --- 2. INSERT QUERY ---
    $sql = "INSERT INTO events (owner_id, name, description, date, time, location, invite_code)
            VALUES (:owner_id, :name, :description, :date, :time, :location, :code)
            RETURNING id";

    $stmt = $conn->prepare($sql);
    
    $stmt->bindValue(':owner_id', $owner_id);
    $stmt->bindValue(':name', $name);
    
    // Using explicitly prepared NULL variables
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':date', $date);
    $stmt->bindValue(':time', $time);
    $stmt->bindValue(':location', $location);
    $stmt->bindValue(':code', $inviteCode);

    if ($stmt->execute()) {
        $newId = $stmt->fetchColumn();
        
        // --- 3. AUTO-JOIN OWNER ---
        $joinSql = "INSERT INTO event_members (event_id, user_id, role) VALUES (:eid, :uid, 'admin')";
        $joinStmt = $conn->prepare($joinSql);
        $joinStmt->execute([':eid' => $newId, ':uid' => $owner_id]);

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
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // DB CONNECTION
    $host = "127.0.0.1";
    $port = "5432";
    $dbname = "dinadrawing";
    $username = "kai";
    $password = "DND2025";

    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // GET event id from URL
    $id = $_GET["id"] ?? null;

    if (!$id) {
        echo json_encode(["success" => false, "message" => "Missing event id"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->bindValue(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(["success" => false, "message" => "Event not found"]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "data" => $event
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
    exit;
}

// Allowed updatable fields
$fields = [];
$params = [':id' => $id];

if (isset($input['name'])) {
    $fields[] = 'name = :name';
    $params[':name'] = trim($input['name']);
}
if (isset($input['description'])) {
    $fields[] = 'description = :description';
    $params[':description'] = trim($input['description']);
}
if (isset($input['date'])) {
    // Accept null to clear date
    $params[':datetime'] = $input['date'] === null ? null : trim($input['date']);
    $fields[] = 'datetime = :datetime';
}
if (isset($input['place'])) {
    $fields[] = 'place = :place';
    $params[':place'] = trim($input['place']);
}

if (count($fields) === 0) {
    echo json_encode(['success' => false, 'error' => 'No updatable fields provided']);
    exit;
}

// DB connection - keep in sync with plan.php or move to shared config
$host = "127.0.0.1";
$port = "5432";
$dbname = "dinadrawing";
$username = "kai";
$password = "DND2025";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    // For debugging locally you can uncomment the next line, but avoid in production:
    // echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
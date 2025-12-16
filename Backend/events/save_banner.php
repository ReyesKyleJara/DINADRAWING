<?php
// File: DINADRAWING/Backend/events/save_banner.php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
$type = trim($input['type'] ?? '');

if ($id <= 0) {
    echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit;
}

// Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) {
    require_once $dbPath;
    $pdo = getDatabaseConnection();
} else {
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025");
}

try {
    $banner_image_db = null;

    // Handle Image Upload
    if ($type === 'image') {
        $imageData = $input['imageData'] ?? null;
        if (empty($imageData)) throw new Exception('No image data');

        // Extract Base64
        if (preg_match('#^data:image/(\w+);base64,#i', $imageData, $m)) {
            $ext = strtolower($m[1]) == 'jpeg' ? 'jpg' : $m[1];
            $content = base64_decode(substr($imageData, strpos($imageData, ',') + 1));
            
            // Define Path: Go up 3 levels from Backend/events/ -> DINADRAWING root
            $uploadDir = __DIR__ . '/../../../Assets/uploads/banners/';
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = "event_{$id}_" . time() . ".{$ext}"; // Added timestamp to avoid cache issues
            file_put_contents($uploadDir . $fileName, $content);
            
            $banner_image_db = "Assets/uploads/banners/" . $fileName;
        }
    }

    // Update Database
    $sql = "UPDATE events SET 
            banner_type = :type, 
            banner_color = :color, 
            banner_from = :from, 
            banner_to = :to, 
            banner_image = :image 
            WHERE id = :id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':type' => $type,
        ':color' => $input['color'] ?? null,
        ':from' => $input['from'] ?? null,
        ':to' => $input['to'] ?? null,
        ':image' => $banner_image_db
    ]);

    echo json_encode([
        'success' => true, 
        'banner' => [
            'type' => $type, 
            'imageUrl' => $banner_image_db ? '/DINADRAWING/' . $banner_image_db : null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
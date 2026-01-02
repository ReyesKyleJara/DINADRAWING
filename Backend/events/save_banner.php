<?php
// File: DINADRAWING/Backend/events/save_banner.php

session_start(); // Ensure session is started for auth checks
header('Content-Type: application/json; charset=utf-8');

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

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

// 2. Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) {
    require_once $dbPath;
    $pdo = getDatabaseConnection();
} else {
    // Fallback Connection
    try {
        $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false, 'error'=>'DB Connection failed']); exit;
    }
}

try {
    $banner_image_db = null;

    // 3. Handle Image Upload
    if ($type === 'image') {
        $imageData = $input['imageData'] ?? null;
        if (empty($imageData)) throw new Exception('No image data provided');

        // Extract Base64
        if (preg_match('#^data:image/(\w+);base64,#i', $imageData, $m)) {
            $ext = strtolower($m[1]);
            if ($ext == 'jpeg') $ext = 'jpg';
            
            $content = base64_decode(substr($imageData, strpos($imageData, ',') + 1));
            
            if ($content === false) throw new Exception('Base64 decode failed');

            // --- CRITICAL FIX: PATH CORRECTION ---
            // From: DINADRAWING/Backend/events/
            // To:   DINADRAWING/Assets/uploads/banners/
            // This requires going up TWO levels (../../), not three.
            $uploadDir = __DIR__ . '/../../Assets/uploads/banners/';
            
            // Create Folder if missing
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Generate Filename (timestamped to prevent caching)
            $fileName = "event_{$id}_" . time() . ".{$ext}";
            $filePath = $uploadDir . $fileName;
            
            // Save File
            if (file_put_contents($filePath, $content) === false) {
                throw new Exception("Failed to write image file");
            }
            
            // Relative path for database and frontend
            $banner_image_db = "Assets/uploads/banners/" . $fileName;
        } else {
            throw new Exception('Invalid image data format');
        }
    }

    // 4. Update Database
    // We update all fields to ensure the type switches correctly (e.g. from color to image)
    $sql = "UPDATE events SET 
            banner_type = :type, 
            banner_color = :color, 
            banner_from = :from, 
            banner_to = :to, 
            banner_image = :image 
            WHERE id = :id";
            
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':id' => $id,
        ':type' => $type,
        ':color' => $input['color'] ?? null,
        ':from' => $input['from'] ?? null,
        ':to' => $input['to'] ?? null,
        ':image' => $banner_image_db
    ]);

    if (!$result) throw new Exception('Database update failed');

    // 5. Success Response
    echo json_encode([
        'success' => true, 
        'banner' => [
            'type' => $type, 
            // Return full path so frontend can display immediately
            'imageUrl' => $banner_image_db ? '/DINADRAWING/' . $banner_image_db : null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
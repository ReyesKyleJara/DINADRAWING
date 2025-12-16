<?php
// File: DINADRAWING/Backend/events/create_post.php
// FINAL ATTEMPT NA MAY AUTO-PERMISSION FIX
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Basic Checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false, 'error'=>'Method not allowed']); exit;
}

$userId = $_SESSION['user_id'];
$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : ''; 

if ($eventId <= 0) {
    echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit;
}

if (empty($content) && (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK)) {
    echo json_encode(['success'=>false, 'error'=>'Post cannot be empty']); exit;
}

// Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    $imagePathDb = null;

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // --- PATH CALCULATION ---
            $projectRoot = realpath(__DIR__ . '/../../');
            $uploadDir = $projectRoot . '/Assets/uploads/posts/';
            
            // 1. Check existance
            if (!is_dir($uploadDir)) {
                 throw new Exception('Upload directory does not exist. Please create DINADRAWING/Assets/uploads/posts/ manually.');
            }
            
            // --- NEW: ATTEMPT TO FIX PERMISSIONS PROGRAMMATICALLY ---
            // Try to set full permissions (0777). Suppress errors with @ if OS denies it.
            @chmod($uploadDir, 0777);

            // 2. Check writability again
            if (!is_writable($uploadDir)) {
                 // Kung ayaw pa rin after chmod, kailangan na talagang manual fix sa OS.
                 throw new Exception('Server cannot write to upload directory. You must manually set "Full Control" permissions for the "Assets/uploads/posts" folder.');
            }

            $newFilename = "post_{$eventId}_{$userId}_" . uniqid() . ".{$ext}";
            $destPath = $uploadDir . $newFilename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $imagePathDb = "Assets/uploads/posts/" . $newFilename;
            } else { 
                $error = error_get_last();
                throw new Exception('Failed to move file. Server Error: ' . ($error['message'] ?? 'Unknown')); 
            }
        } else { throw new Exception('Invalid file type. Only JPG, PNG, and GIF allowed.'); }
    }

    // Insert into DB
    $stmt = $pdo->prepare("INSERT INTO posts (event_id, user_id, content, image_path) VALUES (:eid, :uid, :content, :img) RETURNING id, created_at");
    $stmt->execute([':eid'=>$eventId, ':uid'=>$userId, ':content'=>$content, ':img'=>$imagePathDb]);
    $newPost = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return new post data
    echo json_encode([
        'success' => true,
        'post' => [
            'id' => $newPost['id'],
            'post_type' => 'standard',
            'content' => htmlspecialchars($content),
            'image_path' => $imagePathDb ? "/DINADRAWING/" . $imagePathDb : null,
            'created_at' => date('M j, Y • g:i A', strtotime($newPost['created_at'])),
            'user' => [
                'name' => $_SESSION['name'] ?? $_SESSION['username'],
                'avatar' => $_SESSION['profile_picture'] ?? 'Assets/Profile Icon/profile.png'
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
<?php
// /DINADRAWING/backend/api/google-auth.php
header('Content-Type: application/json');
require_once 'config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $data['uid'] ?? '';
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $photoURL = $data['photoURL'] ?? '';
    
    if (empty($uid) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid user data']);
        exit;
    }
    
    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            // Update existing user with Firebase UID if not set
            if (empty($existingUser['firebase_uid'])) {
                $updateStmt = $pdo->prepare("UPDATE users SET firebase_uid = ?, profile_picture = ? WHERE id = ?");
                $updateStmt->execute([$uid, $photoURL, $existingUser['id']]);
            }
            
            // Return user data (don't return password)
            unset($existingUser['password']);
            $existingUser['profile_picture'] = $photoURL ?: $existingUser['profile_picture'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $existingUser
            ]);
        } else {
            // Register new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, firebase_uid, profile_picture, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $uid, $photoURL]);
            
            $userId = $pdo->lastInsertId();
            
            // Get the newly created user
            $stmt = $pdo->prepare("SELECT id, name, email, profile_picture, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user' => $newUser
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
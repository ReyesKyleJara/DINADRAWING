<?php
// /DINADRAWING/backend/api/google-auth.php

// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log request
error_log("Google Auth Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Google Auth Request URI: " . $_SERVER['REQUEST_URI']);

require_once 'config/database.php';

// Get POST data
$rawData = file_get_contents('php://input');
error_log("Raw POST data: " . $rawData);

$data = json_decode($rawData, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$data) {
        error_log("Invalid JSON data received");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid JSON data received'
        ]);
        exit;
    }
    
    $uid = $data['uid'] ?? '';
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $photoURL = $data['photoURL'] ?? '';
    
    error_log("Processing user - Email: $email, UID: $uid");
    
    if (empty($uid) || empty($email)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid user data: UID and email are required'
        ]);
        exit;
    }
    
    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR firebase_uid = ?");
        $stmt->execute([$email, $uid]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $isNewUser = false;
        
        if ($existingUser) {
            // USER EXISTS - LOG THEM IN
            error_log("Existing user found: " . $existingUser['id']);
            
            // Update existing user with Firebase UID if not set
            if (empty($existingUser['firebase_uid'])) {
                $updateStmt = $pdo->prepare("UPDATE users SET firebase_uid = ?, profile_picture = ? WHERE id = ?");
                $updateStmt->execute([$uid, $photoURL, $existingUser['id']]);
                error_log("Updated user with firebase_uid");
            }
            
            // Return user data (don't return password)
            unset($existingUser['password']);
            $existingUser['profile_picture'] = $photoURL ?: $existingUser['profile_picture'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $existingUser,
                'isNewUser' => false
            ]);
            
        } else {
            // NEW USER - CREATE ACCOUNT
            $isNewUser = true;
            error_log("Creating new user for email: $email");
            
            // Register new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, firebase_uid, profile_picture, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $uid, $photoURL]);
            
            $userId = $pdo->lastInsertId();
            error_log("New user created with ID: $userId");
            
            // Get the newly created user
            $stmt = $pdo->prepare("SELECT id, name, email, profile_picture, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user' => $newUser,
                'isNewUser' => true
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Use POST.',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]);
}
?>
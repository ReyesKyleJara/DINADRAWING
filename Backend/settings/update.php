<?php
// File: DINADRAWING/Backend/settings/update.php

session_start();
header("Content-Type: application/json");

// 1. Check if logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// 2. Database Connection
// Since we are in /Backend/settings/, the config is at ../config/database.php
$dbPath = __DIR__ . "/../config/database.php";

if (!file_exists($dbPath)) {
    // Fallback if your folder structure is slightly different (e.g. inside api/)
    $dbPath = __DIR__ . "/../../api/config/database.php"; 
}

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database config not found."]);
    exit;
}

require_once $dbPath;
$db = getDatabaseConnection();
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents("php://input"), true);
$type = $input['type'] ?? '';

try {
    // ==========================================
    // UPDATE PROFILE (Name & Username)
    // ==========================================
    if ($type === 'profile') {
        $name = trim($input['name']);
        $username = trim($input['username']);

        if (empty($name) || empty($username)) {
            throw new Exception("Name and Username are required.");
        }
        
        // Check uniqueness
        $check = $db->prepare("SELECT id FROM users WHERE username = :user AND id != :id");
        $check->execute(['user' => $username, 'id' => $userId]);
        if ($check->fetch()) throw new Exception("Username taken.");

        $stmt = $db->prepare("UPDATE users SET name = :name, username = :username WHERE id = :id");
        $stmt->execute(['name' => $name, 'username' => $username, 'id' => $userId]);

        $_SESSION['name'] = $name;
        $_SESSION['username'] = $username;

        echo json_encode(["success" => true, "message" => "Profile updated successfully!"]);
    }

    // ==========================================
    // UPDATE PHOTO
    // ==========================================
    elseif ($type === 'photo') {
        $photoData = $input['image'];
        if (empty($photoData)) throw new Exception("No image data.");

        $stmt = $db->prepare("UPDATE users SET profile_picture = :pic WHERE id = :id");
        $stmt->execute(['pic' => $photoData, 'id' => $userId]);

        $_SESSION['profile_picture'] = $photoData;

        echo json_encode(["success" => true, "message" => "Profile photo updated!"]);
    }

    // ==========================================
    // CHANGE PASSWORD
    // ==========================================
    elseif ($type === 'password') {
        $oldPass = $input['old_password'];
        $newPass = $input['new_password'];

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($oldPass, $user['password_hash'])) {
            throw new Exception("Incorrect current password.");
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $update->execute(['hash' => $newHash, 'id' => $userId]);

        echo json_encode(["success" => true, "message" => "Password changed successfully!"]);
    }

    // ==========================================
    // UPDATE EMAIL
    // ==========================================
    elseif ($type === 'email') {
        $currentEmailInput = trim($input['current_email']);
        $newEmail = trim($input['new_email']);

        if ($currentEmailInput !== $_SESSION['email']) {
            throw new Exception("Current email incorrect.");
        }

        $check = $db->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute(['email' => $newEmail]);
        if ($check->fetch()) throw new Exception("Email already in use.");

        $update = $db->prepare("UPDATE users SET email = :email WHERE id = :id");
        $update->execute(['email' => $newEmail, 'id' => $userId]);

        $_SESSION['email'] = $newEmail;

        echo json_encode(["success" => true, "message" => "Email updated successfully!"]);
    }
    else {
        throw new Exception("Invalid request type.");
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json");

// Connect to Database (Smart Path Finder)
$possiblePaths = [__DIR__ . "/../config/database.php", __DIR__ . "/../../api/config/database.php"];
$dbPath = null;
foreach ($possiblePaths as $path) { if (file_exists($path)) { $dbPath = $path; break; } }
if ($dbPath) require_once $dbPath;
$db = getDatabaseConnection();

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? 'check_user';

// ACTION 1: LOGIN / CHECK
if ($action === 'check_user') {
    $email = $input['email'];
    $google_uid = $input['google_uid'];
    $photo = $input['photo'] ?? null; // Kunin ang picture galing Google

    $stmt = $db->prepare("SELECT * FROM users WHERE google_uid = ? OR email = ? LIMIT 1");
    $stmt->execute([$google_uid, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // UPDATE PHOTO LOGIC:
        // Kung wala pang picture sa database, gamitin ang Google picture.
        // Kung meron na (baka inupdate ni user), 'wag galawin.
        if (empty($user['profile_picture']) && $photo) {
            $upd = $db->prepare("UPDATE users SET profile_picture = ?, google_uid = ? WHERE id = ?");
            $upd->execute([$photo, $google_uid, $user['id']]);
            $user['profile_picture'] = $photo; // Update variable for session
        }

        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        // Gamitin ang galing DB, or fallback sa default
        $_SESSION['profile_picture'] = $user['profile_picture'] ?? 'Assets/Profile Icon/profile.png';
        
        echo json_encode(["success" => true, "status" => "login_success", "user" => $user]);
    } else {
        // NEW USER
        echo json_encode([
            "success" => true, 
            "status" => "needs_completion", 
            "google_data" => $input // Ipasa lahat (kasama photo) pabalik sa frontend
        ]);
    }
}

// ACTION 2: COMPLETE SIGNUP
elseif ($action === 'complete_registration') {
    $username = $input['username'];
    $email = $input['email'];
    $google_uid = $input['google_uid'];
    $name = $input['name'];
    $photo = $input['photo'] ?? null; // Kunin ulit kung ipinasa

    // Check username
    $check = $db->prepare("SELECT 1 FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) { echo json_encode(["success" => false, "message" => "Username taken"]); exit; }

    // INSERT (Save photo immediately!)
    $stmt = $db->prepare("INSERT INTO users (username, name, email, google_uid, profile_picture) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $name, $email, $google_uid, $photo]);
    
    // Auto Login Session
    $_SESSION['user_id'] = $db->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['profile_picture'] = $photo ?? 'Assets/Profile Icon/profile.png';

    echo json_encode(["success" => true, "status" => "login_success"]);
}
?>
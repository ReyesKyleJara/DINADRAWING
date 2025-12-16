<?php
// File: DINADRAWING/invite.php
session_start();

// 1. Check if Event ID exists in link
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    die("Invalid invite link.");
}

// 2. Check if User is Logged In
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page, but remember where they wanted to go
    $_SESSION['redirect_after_login'] = "/DINADRAWING/invite.php?id=" . $eventId;
    header("Location: login.php"); // Change to your actual login file
    exit;
}

$userId = $_SESSION['user_id'];

// Database Connection
$dbPath = __DIR__ . "/Backend/config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    // 3. Check if event exists
    $stmt = $pdo->prepare("SELECT id FROM events WHERE id = :eid");
    $stmt->execute([':eid' => $eventId]);
    if (!$stmt->fetch()) {
        die("Event does not exist.");
    }

    // 4. Add user to event_members (Ignore error if already a member)
    // We use "ON CONFLICT DO NOTHING" for Postgres to handle duplicates gracefully
    $sql = "INSERT INTO event_members (event_id, user_id, role) 
            VALUES (:eid, :uid, 'member') 
            ON CONFLICT (event_id, user_id) DO NOTHING";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':eid' => $eventId, ':uid' => $userId]);

    // 5. Success! Redirect to the plan page
    header("Location: plan.php?id=" . $eventId);
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
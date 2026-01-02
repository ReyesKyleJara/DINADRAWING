<?php
// File: DINADRAWING/join.php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login, then come back here
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once "Backend/config/database.php"; 
$conn = getDatabaseConnection();

$code = $_GET['code'] ?? '';

if (!$code) {
    die("Invalid invite link.");
}

// 2. Find Event by Code
$stmt = $conn->prepare("SELECT id, name FROM events WHERE invite_code = :code");
$stmt->execute([':code' => $code]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("<h1>Invalid Invite</h1><p>This invite link is expired or incorrect.</p><a href='myplans.php'>Go Home</a>");
}

$event_id = $event['id'];
$user_id = $_SESSION['user_id'];

// 3. Check if already a member
$check = $conn->prepare("SELECT id FROM event_members WHERE event_id = :eid AND user_id = :uid");
$check->execute([':eid' => $event_id, ':uid' => $user_id]);

if ($check->rowCount() > 0) {
    // Already joined? Redirect to plan
    header("Location: plan.php?id=" . $event_id);
    exit;
}

// 4. Add to Members Table
try {
    $insert = $conn->prepare("INSERT INTO event_members (event_id, user_id, role) VALUES (:eid, :uid, 'member')");
    $insert->execute([':eid' => $event_id, ':uid' => $user_id]);
    
    // Success! Redirect to plan
    header("Location: plan.php?id=" . $event_id);
    exit;
} catch (Exception $e) {
    die("Error joining event: " . $e->getMessage());
}
?>
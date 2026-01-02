<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;

if ($eventId <= 0) {
    echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit;
}

require_once __DIR__ . "/../config/database.php";
$pdo = getDatabaseConnection();

try {
    $pdo->beginTransaction();

    // 1. Clear existing budget data for this event (Simple "Save" logic: Delete old -> Insert new)
    $pdo->prepare("DELETE FROM budget_expenses WHERE event_id = ?")->execute([$eventId]);
    $pdo->prepare("DELETE FROM budget_contributions WHERE event_id = ?")->execute([$eventId]);

    // 2. Insert Expenses
    $stmtExp = $pdo->prepare("INSERT INTO budget_expenses (event_id, name, estimated_cost, actual_cost, is_paid) VALUES (?, ?, ?, ?, ?)");
    foreach (($input['expenses'] ?? []) as $exp) {
        $stmtExp->execute([
            $eventId, 
            $exp['name'], 
            $exp['estimated'], 
            $exp['actual'] ?? 0, 
            $exp['paid'] ? 'true' : 'false'
        ]);
    }

    // 3. Insert Contributions
    $stmtCon = $pdo->prepare("INSERT INTO budget_contributions (event_id, member_name, avatar, amount, is_paid) VALUES (?, ?, ?, ?, ?)");
    foreach (($input['contributions'] ?? []) as $con) {
        
        // --- FIX: SHORTEN AVATAR URL ---
        $avatar = $con['avatar'] ?? 'Assets/Profile Icon/profile.png';
        
        // If it's a local path (contains DINADRAWING), remove the domain/folder prefix to save space
        if (strpos($avatar, 'DINADRAWING/') !== false) {
            $parts = explode('DINADRAWING/', $avatar);
            $avatar = end($parts); // Keep only "Assets/..."
        }
        // If it's a Base64 string (starts with data:), we can't save it in VARCHAR. 
        // Force fallback if it's too long and you didn't update the DB.
        if (strlen($avatar) > 250) {
            // Optional: You could save 'Assets/Profile Icon/profile.png' instead to prevent crash
            // $avatar = 'Assets/Profile Icon/profile.png'; 
        }
        // -------------------------------

        $stmtCon->execute([
            $eventId, 
            $con['name'], 
            $avatar,
            $con['amount'], 
            $con['paid'] ? 'true' : 'false'
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
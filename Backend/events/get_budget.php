<?php
session_start();
header('Content-Type: application/json');

// ==========================
// 1. SECURITY CHECK
// ==========================
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// ==========================
// 2. DATABASE CONNECTION
// ==========================
require_once __DIR__ . "/../config/database.php";
$conn = getDatabaseConnection();

// ==========================
// 3. VALIDATE INPUT
// ==========================
$eventId = $_GET['event_id'] ?? null;

if (!$eventId) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing event_id'
    ]);
    exit;
}

try {

    // ==========================
    // 4. FETCH EXPENSES
    // ==========================
    $expStmt = $conn->prepare("
        SELECT 
            name,
            estimated_cost AS estimated,
            actual_cost AS actual,
            is_paid AS paid
        FROM budget_expenses
        WHERE event_id = :eid
        ORDER BY id
    ");
    $expStmt->execute([':eid' => $eventId]);
    $expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // 5. FETCH CONTRIBUTIONS
    // ==========================
    $contStmt = $conn->prepare("
        SELECT 
            member_name AS name,
            avatar,
            amount,
            is_paid AS paid
        FROM budget_contributions
        WHERE event_id = :eid
        ORDER BY id
    ");
    $contStmt->execute([':eid' => $eventId]);
    $contributions = $contStmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // 6. FIX DATA TYPES
    // ==========================
    $totalBudget = 0;
    
    foreach ($expenses as &$e) {
        $e['estimated'] = (float)$e['estimated'];
        $e['actual'] = (float)$e['actual'];
        $e['paid'] = ($e['paid'] === true || $e['paid'] === 't' || $e['paid'] === 1);
        
        // Calculate total here
        $totalBudget += $e['estimated'];
    }

    foreach ($contributions as &$c) {
        $c['amount'] = (float)$c['amount'];
        $c['paid'] = ($c['paid'] === true || $c['paid'] === 't' || $c['paid'] === 1);
    }

    // ==========================
    // 7. CHECK EXISTENCE
    // ==========================
    $exists = (count($expenses) > 0 || count($contributions) > 0);

    // ==========================
    // 8. FINAL RESPONSE
    // ==========================
    echo json_encode([
        'success' => true,
        'exists' => $exists,
        
        // IMPORTANT: We wrap everything inside 'budget' 
        // to match what plan.php expects (data.budget)
        'budget' => [
            'expenses' => $expenses,
            'contributions' => $contributions,
            'totalBudget' => $totalBudget // <--- This was missing/misplaced before!
        ]
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
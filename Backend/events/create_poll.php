<?php
// File: DINADRAWING/Backend/events/create_poll.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Basic Checks (Logged in ba? POST request ba?)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['success'=>false, 'error'=>'Unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false, 'error'=>'Method not allowed']); exit;
}

$userId = $_SESSION['user_id'];
// Kunin ang raw JSON data galing sa frontend
$input = json_decode(file_get_contents('php://input'), true);

$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;
$question = trim($input['question'] ?? '');
$options = $input['options'] ?? [];
// Checkboxes send true/false
$allowMultiple = !empty($input['allow_multiple']);
$isAnonymous = !empty($input['is_anonymous']);

// 2. Validations
if ($eventId <= 0) { echo json_encode(['success'=>false, 'error'=>'Invalid Event ID']); exit; }
if (empty($question)) { echo json_encode(['success'=>false, 'error'=>'Poll question is required']); exit; }

// Linisin ang options (alisin ang empty strings)
$validOptions = array_filter(array_map('trim', $options));
if (count($validOptions) < 2) { echo json_encode(['success'=>false, 'error'=>'At least 2 options are required']); exit; }

// 3. Database Connection
$dbPath = __DIR__ . "/../config/database.php"; 
if (file_exists($dbPath)) { require_once $dbPath; $pdo = getDatabaseConnection(); } 
else { $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing", "kai", "DND2025"); }

try {
    // Start Transaction (Para sabay-sabay ma-save lahat)
    $pdo->beginTransaction();

    // A. Insert sa 'posts' table (type = 'poll')
    $stmt = $pdo->prepare("INSERT INTO posts (event_id, user_id, post_type) VALUES (:eid, :uid, 'poll') RETURNING id, created_at");
    $stmt->execute([':eid'=>$eventId, ':uid'=>$userId]);
    $newPost = $stmt->fetch(PDO::FETCH_ASSOC);
    $postId = $newPost['id'];

    // B. Insert sa 'polls' table (yung question at settings)
    // Note: Postgres needs 'true'/'false' strings for booleans in prepared statements sometimes depending on driver version, using ternary for safety.
    $stmt = $pdo->prepare("INSERT INTO polls (post_id, question, allow_multiple, is_anonymous) VALUES (:pid, :q, :multi, :anon) RETURNING id");
    $stmt->execute([':pid'=>$postId, ':q'=>$question, ':multi'=>$allowMultiple ? 'true':'false', ':anon'=>$isAnonymous ? 'true':'false']);
    $pollId = $stmt->fetchColumn();

    // C. Insert sa 'poll_options' table (isa-isa yung mga choices)
    $stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (:pid, :text) RETURNING id, option_text, vote_count");
    $createdOptions = [];
    foreach ($validOptions as $optText) {
        $stmt->execute([':pid'=>$pollId, ':text'=>$optText]);
        $createdOptions[] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Commit Transaction (Save na talaga)
    $pdo->commit();

    // 4. Ihanda ang data pabalik sa frontend para ma-display agad
    echo json_encode([
        'success' => true,
        'post' => [
            'id' => $postId,
            'post_type' => 'poll',
            'created_at' => date('M j, Y • g:i A', strtotime($newPost['created_at'])),
            'user' => [
                'name' => $_SESSION['name'] ?? $_SESSION['username'],
                'avatar' => $_SESSION['profile_picture'] ?? 'Assets/Profile Icon/profile.png'
            ],
            // Ito yung important part para sa poll rendering
            'poll_data' => [
                'question' => htmlspecialchars($question),
                'allow_multiple' => $allowMultiple,
                'is_anonymous' => $isAnonymous,
                'total_votes' => 0,
                'options' => $createdOptions // May id, text, at vote_count (0)
            ]
        ]
    ]);

} catch (Exception $e) {
    // Pag may error, i-undo lahat ng changes
    $pdo->rollBack();
    http_response_code(500); echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
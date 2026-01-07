<?php
// Backend/events/add_poll_option.php
session_start();
header('Content-Type: application/json');

// 1. DATABASE CONNECTION
require_once __DIR__ . "/../config/database.php"; 

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); 
    exit; 
}

// 2. GET INPUT
$input = json_decode(file_get_contents('php://input'), true);
$poll_id = $input['poll_id'] ?? null;
$text = trim($input['text'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$poll_id || empty($text)) {
    echo json_encode(['success' => false, 'error' => 'Missing poll ID or option text']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 3. PERMISSION CHECK
    $checkStmt = $pdo->prepare("SELECT allow_user_add, is_anonymous, allow_multiple FROM polls WHERE id = ?");
    $checkStmt->execute([$poll_id]);
    $poll = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        echo json_encode(['success' => false, 'error' => 'Poll not found']);
        exit;
    }

    $allowUserAdd = ($poll['allow_user_add'] === true || $poll['allow_user_add'] === 1 || $poll['allow_user_add'] === 't');

    if (!$allowUserAdd) {
        echo json_encode(['success' => false, 'error' => 'Adding options is not allowed for this poll.']);
        exit;
    }

    // 4. INSERT NEW OPTION
    $insertStmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text, vote_count) VALUES (?, ?, 0)");
    $insertStmt->execute([$poll_id, $text]);

    // =========================================================
    // 5. RE-FETCH POLL DATA (FIXED COLUMN NAMES)
    // =========================================================
    
    // Fetch Options
    $optStmt = $pdo->prepare("
        SELECT id, option_text, vote_count 
        FROM poll_options 
        WHERE poll_id = ? 
        ORDER BY id ASC
    ");
    $optStmt->execute([$poll_id]);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

    // FIX #1: Changed 'poll_option_id' to 'option_id'
    $myVotesStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $myVotesStmt->execute([$poll_id, $user_id]);
    $myVotes = $myVotesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate Total Votes
    $totalVotes = 0;
    foreach ($options as $opt) {
        $totalVotes += (int)$opt['vote_count'];
    }

    $isAnonymous = ($poll['is_anonymous'] === true || $poll['is_anonymous'] === 1 || $poll['is_anonymous'] === 't');
    
    // Build Response
    $processedOptions = [];
    foreach ($options as $opt) {
        $optId = $opt['id'];
        
        // Facepile (Voters)
        $voters = [];
        if (!$isAnonymous) {
            // FIX #2: Changed 'poll_option_id' to 'option_id' here too
            $vStmt = $pdo->prepare("
                SELECT u.profile_picture 
                FROM poll_votes pv 
                JOIN users u ON pv.user_id = u.id 
                WHERE pv.option_id = ? 
                LIMIT 5
            ");
            $vStmt->execute([$optId]);
            $rawVoters = $vStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($rawVoters as $pic) {
                if (empty($pic)) $pic = '/DINADRAWING/Assets/Profile Icon/profile.png';
                elseif (strpos($pic, 'Assets') === 0) $pic = '/DINADRAWING/' . $pic;
                $voters[] = $pic;
            }
        }

        $processedOptions[] = [
            'id' => $optId,
            'option_text' => htmlspecialchars($opt['option_text']),
            'vote_count' => (int)$opt['vote_count'],
            'is_voted' => in_array($optId, $myVotes),
            'voters' => $voters
        ];
    }

    $pollData = [
        'id' => $poll_id,
        'allow_multiple' => ($poll['allow_multiple'] === true || $poll['allow_multiple'] === 1 || $poll['allow_multiple'] === 't'),
        'is_anonymous' => $isAnonymous,
        'allow_user_add' => $allowUserAdd,
        'total_votes' => $totalVotes,
        'options' => $processedOptions
    ];

    echo json_encode(['success' => true, 'poll_data' => $pollData]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
}
?>
<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false, 'error'=>'Auth required']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$poll_id = $data['poll_id'];
$option_id = $data['option_id'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Someone';

try {
    $pdo = getDatabaseConnection();

    // 1. Get Poll Settings
    $pollStmt = $pdo->prepare("SELECT id, post_id, allow_multiple, is_anonymous FROM polls WHERE id = ?");
    $pollStmt->execute([$poll_id]);
    $poll = $pollStmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) { echo json_encode(['success'=>false, 'error'=>'Poll not found']); exit; }

    // ✅ THE FIX: STRICT CHECKING
    // This handles "false", "0", 0, false, "f" correctly.
    // If it's NOT explicitly true/1, we treat it as FALSE (Single Choice).
    $val = $poll['allow_multiple'];
    $isMultiple = ($val === true || $val === 'true' || $val === 1 || $val === '1' || $val === 't');
    
    $anonVal = $poll['is_anonymous'] ?? 0;
    $isAnon = ($anonVal === true || $anonVal === 'true' || $anonVal === 1 || $anonVal === '1' || $anonVal === 't');

    // 2. Voting Logic
    
    // Check if I already voted for THIS option
    $checkStmt = $pdo->prepare("SELECT id FROM poll_votes WHERE option_id = ? AND user_id = ?");
    $checkStmt->execute([$option_id, $user_id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // CASE: I clicked the same option again -> UNVOTE (Toggle Off)
        $pdo->prepare("DELETE FROM poll_votes WHERE id = ?")->execute([$existing['id']]);
    } else {
        // CASE: New Vote
        
        // 🔴 CRITICAL: If Single Choice, delete ALL my other votes for this poll first.
        if (!$isMultiple) {
            $delStmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $delStmt->execute([$poll_id, $user_id]);
        }
        
        // Insert new vote
        $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)")
            ->execute([$poll_id, $option_id, $user_id]);
        
        // Notify
        $postStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postStmt->execute([$poll['post_id']]);
        $ownerId = $postStmt->fetchColumn();
        if ($ownerId && $ownerId != $user_id) {
            $dupCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND actor_id=? AND post_id=? AND type='vote'");
            $dupCheck->execute([$ownerId, $user_id, $poll['post_id']]);
            if (!$dupCheck->fetch()) {
                $msg = "$user_name voted on your poll.";
                $pdo->prepare("INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (?, ?, ?, 'vote', ?)")
                    ->execute([$ownerId, $user_id, $poll['post_id'], $msg]);
            }
        }
    }

    // 3. Update Counts
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE option_id = ?");
    $countStmt->execute([$option_id]);
    $newCount = $countStmt->fetchColumn();
    $pdo->prepare("UPDATE poll_options SET vote_count = ? WHERE id = ?")->execute([$newCount, $option_id]);

    // 4. Return Data (WITH YOUR PRETTY PROFILE PICS)
    $optStmt = $pdo->prepare("SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
    $optStmt->execute([$poll_id]);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

    $myVotesStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $myVotesStmt->execute([$poll_id, $user_id]);
    $myVotes = $myVotesStmt->fetchAll(PDO::FETCH_COLUMN);

    $totalVotes = 0;
    foreach ($options as &$opt) {
        $totalVotes += $opt['vote_count'];
        $opt['is_voted'] = in_array($opt['id'], $myVotes);

        // Keep your Profile Picture Logic
        $opt['voters'] = [];
        if (!$isAnon && $opt['vote_count'] > 0) {
            $vStmt = $pdo->prepare("
                SELECT u.profile_picture 
                FROM poll_votes pv 
                JOIN users u ON pv.user_id = u.id 
                WHERE pv.option_id = ? 
                ORDER BY pv.created_at DESC 
                LIMIT 3
            ");
            $vStmt->execute([$opt['id']]);
            $rawVoters = $vStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach($rawVoters as $pic) {
                $vPath = '/DINADRAWING/Assets/Profile Icon/profile.png';
                if(!empty($pic)) {
                    $vPath = (strpos($pic, 'Assets') === 0) ? '/DINADRAWING/'.$pic : $pic;
                }
                $opt['voters'][] = $vPath;
            }
        }
    }

    echo json_encode([
        'success' => true, 
        'poll_data' => [
            'id' => $poll['id'],
            'total_votes' => $totalVotes,
            'options' => $options,
            'allow_multiple' => $isMultiple
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
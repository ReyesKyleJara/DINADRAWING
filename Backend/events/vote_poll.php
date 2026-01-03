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
    $pollStmt = $pdo->prepare("SELECT id, post_id, allow_multiple FROM polls WHERE id = ?");
    $pollStmt->execute([$poll_id]);
    $poll = $pollStmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) { echo json_encode(['success'=>false, 'error'=>'Poll not found']); exit; }

    // --- CRITICAL FIX: STRICT BOOLEAN CHECK ---
    // This handles "false" (string), 0 (int), 'f' (postgres), etc.
    $rawVal = $poll['allow_multiple'];
    $isMultiple = ($rawVal === true || $rawVal === 'true' || $rawVal === 't' || $rawVal == 1);
    
    // Explicitly check for "false" string to be safe
    if ($rawVal === 'false') $isMultiple = false;

    // 2. Check if I already voted for THIS option
    $checkStmt = $pdo->prepare("SELECT id FROM poll_votes WHERE option_id = ? AND user_id = ?");
    $checkStmt->execute([$option_id, $user_id]);
    $existingVote = $checkStmt->fetch();

    if ($existingVote) {
        // CASE: I clicked the same option again -> UNVOTE
        $pdo->prepare("DELETE FROM poll_votes WHERE id = ?")->execute([$existingVote['id']]);
        $action = 'removed';
    } else {
        // CASE: New Vote
        if (!$isMultiple) {
            // If Single Choice: Delete ALL my other votes for this poll first
            $delStmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $delStmt->execute([$poll_id, $user_id]);
        }
        
        // Add the new vote
        $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)")
            ->execute([$poll_id, $option_id, $user_id]);
        $action = 'voted';

        // Notify Owner (if not self)
        $postStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postStmt->execute([$poll['post_id']]);
        $ownerId = $postStmt->fetchColumn();

        if ($ownerId && $ownerId != $user_id) {
            $msg = "$user_name voted on your poll.";
            // Avoid notification spam
            $dupCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND actor_id=? AND post_id=? AND type='vote'");
            $dupCheck->execute([$ownerId, $user_id, $poll['post_id']]);
            if (!$dupCheck->fetch()) {
                $pdo->prepare("INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (?, ?, ?, 'vote', ?)")
                    ->execute([$ownerId, $user_id, $poll['post_id'], $msg]);
            }
        }
    }

    // 3. Recalculate Count (For Speed)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE option_id = ?");
    $countStmt->execute([$option_id]);
    $newCount = $countStmt->fetchColumn();
    $pdo->prepare("UPDATE poll_options SET vote_count = ? WHERE id = ?")->execute([$newCount, $option_id]);

    // 4. Return Fresh Data (For Smooth UI)
    $optStmt = $pdo->prepare("SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
    $optStmt->execute([$poll_id]);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark which ones I voted for
    $myVotesStmt = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $myVotesStmt->execute([$poll_id, $user_id]);
    $myVotes = $myVotesStmt->fetchAll(PDO::FETCH_COLUMN);

    $totalVotes = 0;
    foreach ($options as &$opt) {
        $totalVotes += $opt['vote_count'];
        $opt['is_voted'] = in_array($opt['id'], $myVotes);
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
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

    // 1. Get Poll Settings and Event ID (Needed for the new notification column)
    $pollStmt = $pdo->prepare("
        SELECT p.id, p.post_id, p.allow_multiple, p.is_anonymous, po.event_id 
        FROM polls p 
        JOIN posts po ON p.post_id = po.id 
        WHERE p.id = ?
    ");
    $pollStmt->execute([$poll_id]);
    $poll = $pollStmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) { echo json_encode(['success'=>false, 'error'=>'Poll not found']); exit; }

    $eventId = $poll['event_id'];
    $val = $poll['allow_multiple'];
    $isMultiple = ($val === true || $val === 'true' || $val === 1 || $val === '1' || $val === 't');
    
    $anonVal = $poll['is_anonymous'] ?? 0;
    $isAnon = ($anonVal === true || $anonVal === 'true' || $anonVal === 1 || $anonVal === '1' || $anonVal === 't');

    // 2. Voting Logic
    $checkStmt = $pdo->prepare("SELECT id FROM poll_votes WHERE option_id = ? AND user_id = ?");
    $checkStmt->execute([$option_id, $user_id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM poll_votes WHERE id = ?")->execute([$existing['id']]);
    } else {
        if (!$isMultiple) {
            $delStmt = $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $delStmt->execute([$poll_id, $user_id]);
        }
        
        $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)")
            ->execute([$poll_id, $option_id, $user_id]);
        
        // --- NOTIFICATION LOGIC ---
        // Find the owner of the post containing the poll
        $postStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postStmt->execute([$poll['post_id']]);
        $ownerId = $postStmt->fetchColumn();

        if ($ownerId && $ownerId != $user_id) {
            // Check if a vote notification for this post already exists from this user today
            $dupCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND actor_id=? AND post_id=? AND type='vote' AND created_at > NOW() - INTERVAL '1 day'");
            $dupCheck->execute([$ownerId, $user_id, $poll['post_id']]);
            
            if (!$dupCheck->fetch()) {
                // INSERT including the new event_id column
                $notifSql = "INSERT INTO notifications (user_id, actor_id, post_id, event_id, type, message, is_read, created_at) 
                             VALUES (?, ?, ?, ?, 'vote', ?, false, NOW())";
                $msg = "$user_name voted on your poll.";
                $pdo->prepare($notifSql)->execute([
                    $ownerId, 
                    $user_id, 
                    $poll['post_id'], 
                    $eventId, 
                    $msg
                ]);
            }
        }
    }

    // 3. Update Counts
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE option_id = ?");
    $countStmt->execute([$option_id]);
    $newCount = $countStmt->fetchColumn();
    $pdo->prepare("UPDATE poll_options SET vote_count = ? WHERE id = ?")->execute([$newCount, $option_id]);

    // 4. Return Data
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
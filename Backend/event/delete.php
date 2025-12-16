<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

// Same DB connection as myplans.php
$pdo = new PDO(
  "pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing",
  "kai",
  "DND2025",
  [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]
);

// 1. Dapat POST at may event_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['event_id'])) {
  header('Location: ../myplans.php');
  exit;
}

$eventId = (int) $_POST['event_id'];

// 2. SOFT DELETE: archived = TRUE
$stmt = $pdo->prepare("
  UPDATE events
  SET archived = TRUE
  WHERE id = :id
");
$stmt->execute([
  ':id' => $eventId,
]);

// 3. Redirect pabalik
$redirect = '../myplans.php';
if (!empty($_POST['from_archived'])) {
  $redirect .= '?archived=1';
}

header("Location: $redirect");
exit;

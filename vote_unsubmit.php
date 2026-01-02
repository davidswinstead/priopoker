<?php
session_start();
$voterId = $_SESSION['voter_id'] ?? 'anonymous_' . rand(1000, 9999);

require_once 'db.php';

$session = $conn->real_escape_string($_POST['session'] ?? '');
$ticket = $conn->real_escape_string($_POST['ticket'] ?? '');
$criterion = $conn->real_escape_string($_POST['criterion'] ?? '');
$voterId = $conn->real_escape_string($voterId);

if (!$session || !$ticket || !$criterion) {
    http_response_code(400);
    echo "Missing data";
    exit;
}

// Remove the vote for this user/ticket/criterion
$conn->query("
    DELETE FROM votes
    WHERE session_code = '$session'
      AND ticket_key = '$ticket'
      AND voter_id = '$voterId'
      AND criterion = '$criterion'
");

echo "OK";

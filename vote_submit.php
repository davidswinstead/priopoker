<?php
session_start();
$voterId = $_SESSION['voter_id'] ?? 'anonymous_' . rand(1000, 9999);

require_once 'db.php';

$session = $conn->real_escape_string($_POST['session'] ?? '');
$ticket = $conn->real_escape_string($_POST['ticket'] ?? '');
$criterion = $conn->real_escape_string($_POST['criterion'] ?? '');
$value = $conn->real_escape_string($_POST['value'] ?? '');
$voterId = $conn->real_escape_string($voterId);

if (!$session || !$ticket || !$criterion || !$value) {
    http_response_code(400);
    echo "Missing data";
    exit;
}

// Remove any existing vote for this user/ticket/criterion
$conn->query("
    DELETE FROM votes
    WHERE session_code = '$session'
      AND ticket_key = '$ticket'
      AND voter_id = '$voterId'
      AND criterion = '$criterion'
");

// Insert new vote
$conn->query("
    INSERT INTO votes (session_code, ticket_key, voter_id, criterion, value, created_at)
    VALUES ('$session', '$ticket', '$voterId', '$criterion', '$value', NOW())
");

echo "OK";

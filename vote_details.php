<?php
require_once 'db.php';

$session = $_GET['session'] ?? '';
$ticket = $_GET['ticket'] ?? '';

$session = $conn->real_escape_string($session);
$ticket = $conn->real_escape_string($ticket);

$result = $conn->query("
    SELECT voter_id, criterion, value
    FROM votes
    WHERE session_code = '$session' AND ticket_key = '$ticket'
");

if (!$result) {
    http_response_code(500);
    echo "Query failed: " . $conn->error;
    exit;
}

$grouped = []; // criterion => [voter_id => value]
while ($row = $result->fetch_assoc()) {
    $crit = $row['criterion'];
    $voter = $row['voter_id'];
    $val = $row['value'];
    $grouped[$crit][$voter] = $val;
}

header('Content-Type: application/json');
echo json_encode($grouped);

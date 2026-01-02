<?php
require_once 'db.php';

$session = $conn->real_escape_string($_GET['session'] ?? '');
$ticket = $conn->real_escape_string($_GET['ticket'] ?? '');

$result = $conn->query("SELECT COUNT(DISTINCT voter_id) AS count FROM votes WHERE session_code='$session' AND ticket_key='$ticket'");
$row = $result->fetch_assoc();

echo $row['count'] ?? '0';

<?php
require_once 'db.php';

$session = $_GET['session'] ?? '';
$session = $conn->real_escape_string($session);

$result = $conn->query("SELECT ticket_key FROM current_ticket WHERE session_code='$session' LIMIT 1");
$row = $result->fetch_assoc();

echo $row['ticket_key'] ?? '';

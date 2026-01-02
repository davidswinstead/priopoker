<?php
require_once 'db.php';

$session = $conn->real_escape_string($_POST['session'] ?? '');
$action = $conn->real_escape_string($_POST['action'] ?? '');
$ticketKey = $conn->real_escape_string($_POST['ticket_key'] ?? '');

if (!$session) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing session"]);
    exit;
}

$currentResult = $conn->query("SELECT ticket_key FROM current_ticket WHERE session_code='$session'");
$current = $currentResult->fetch_assoc();

if (!$current) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Session not found"]);
    exit;
}

$newTicketKey = null;

if ($action === 'next') {
    $next = $conn->query("
        SELECT ticket_key FROM tickets
        WHERE session_code='$session'
        AND id > (SELECT id FROM tickets WHERE session_code='$session' AND ticket_key='{$current['ticket_key']}' LIMIT 1)
        ORDER BY id ASC LIMIT 1
    ")->fetch_assoc();
    
    if ($next) {
        $newTicketKey = $next['ticket_key'];
        $conn->query("UPDATE current_ticket SET ticket_key='$newTicketKey', updated_at=NOW() WHERE session_code='$session'");
    }
} elseif ($action === 'prev') {
    $prev = $conn->query("
        SELECT ticket_key FROM tickets
        WHERE session_code='$session'
        AND id < (SELECT id FROM tickets WHERE session_code='$session' AND ticket_key='{$current['ticket_key']}' LIMIT 1)
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();
    
    if ($prev) {
        $newTicketKey = $prev['ticket_key'];
        $conn->query("UPDATE current_ticket SET ticket_key='$newTicketKey', updated_at=NOW() WHERE session_code='$session'");
    }
} elseif ($action === 'send_to' && $ticketKey) {
    // Verify the ticket exists in this session
    $ticket = $conn->query("SELECT ticket_key FROM tickets WHERE session_code='$session' AND ticket_key='$ticketKey'")->fetch_assoc();
    
    if ($ticket) {
        $newTicketKey = $ticketKey;
        $conn->query("UPDATE current_ticket SET ticket_key='$newTicketKey', updated_at=NOW() WHERE session_code='$session'");
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit;
}

echo json_encode([
    "success" => true,
    "current_ticket" => $newTicketKey ?? $current['ticket_key']
]);

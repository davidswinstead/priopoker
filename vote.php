<?php
session_start();

$session = $_GET['session'] ?? '';
if (!$session) {
    die("Session code missing.");
}

require_once 'db.php';

$ticketResult = $conn->query("
    SELECT ticket_key, title, reporter FROM tickets
    WHERE ticket_key = (
        SELECT ticket_key FROM current_ticket WHERE session_code = '$session'
    )
");

$ticket = $ticketResult->fetch_assoc();

if (!$ticket) {
    // fallback: get first ticket
    $fallback = $conn->query("SELECT ticket_key, title, reporter FROM tickets WHERE session_code = '$session' ORDER BY id ASC LIMIT 1")
                     ->fetch_assoc();

    if ($fallback) {
        $ticket = $fallback;
        $key = $conn->real_escape_string($ticket['ticket_key']);
        $conn->query("INSERT INTO current_ticket (session_code, ticket_key, updated_at) VALUES ('$session', '$key', NOW())");
    } else {
        die("No tickets found for this session.");
    }
}

$ticketKey = $ticket['ticket_key'];
$ticketTitle = $ticket['title'];
$reporter = $ticket['reporter'];

// Get position information
$totalTickets = $conn->query("SELECT COUNT(*) as count FROM tickets WHERE session_code = '$session'")->fetch_assoc()['count'];
$currentPosition = $conn->query("
    SELECT COUNT(*) as position 
    FROM tickets 
    WHERE session_code = '$session' 
    AND id <= (SELECT id FROM tickets WHERE session_code = '$session' AND ticket_key = '$ticketKey')
")->fetch_assoc()['position'];

// Get existing votes for this user on this ticket
$existingVotes = [];
$voterName = $_SESSION['voter_id'];
$voteQuery = $conn->query("
    SELECT criterion, value 
    FROM votes 
    WHERE session_code = '$session' 
    AND ticket_key = '$ticketKey' 
    AND voter_id = '$voterName'
");

while ($vote = $voteQuery->fetch_assoc()) {
    $existingVotes[$vote['criterion']] = $vote['value'];
}


// Ask for name if not stored
if (!isset($_SESSION['voter_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voter_name'])) {
        $_SESSION['voter_id'] = trim($_POST['voter_name']);
        header("Location: vote.php?session=" . urlencode($session));
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Enter your name</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: sans-serif; text-align: center; padding: 60px; }
            input { padding: 10px; font-size: 1em; }
            button { padding: 10px 20px; background: #116688; color: white; border: none; cursor: pointer; }
            h1, h2, h3 {
            color: #116688;
        }
        </style>
    </head>
    <body>          
        <h1><a href="./" style="color: inherit; text-decoration: none;">Supafly Prioritisation Voting Tool</a></h1>
        <h2>Enter your name to vote</h2>
        <form method="post">
            <input type="text" name="voter_name" required placeholder="Your name">
            <button type="submit">Continue</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vote: <?= htmlspecialchars($ticketKey) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: sans-serif;
            background: #f3f6f8;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        h1, h2, h3 {
            color: #116688;
        }

        .criteria-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid #116688;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .criteria-left {
            flex: 1 1 250px;
            min-width: 200px;
        }

        .criteria-right {
            flex: 1 1 300px;
            font-size: 0.9em;
            padding-left: 20px;
            color: #444;
        }

        .label {
            font-weight: bold;
            color: #116688;
            margin-bottom: 8px;
        }

        .options {
            margin-top: 4px;
        }

        .options button {
            margin: 4px 6px 4px 0;
            padding: 10px 14px;
            background: #eee;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .options button:hover {
            background: #ddd;
        }

        .options button.selected {
            background: #116688;
            color: white;
            border-color: #116688;
        }

        a.jira-link {
            color: #116688;
            text-decoration: none;
        }

        a.jira-link:hover {
            text-decoration: underline;
        }

        .ticket-title {
            margin: 4px 0;
        }

        .reporter {
            font-size: 0.9em;
            color: #555;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .ticket-position {
            font-size: 0.8em;
            color: #666;
            font-weight: normal;
        }

        .previous-votes-indicator {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 0.9em;
            color: #856404;
        }
    </style>
</head>
<body>
<div class="container">
    <h1><a href="./" style="color: inherit; text-decoration: none;">Supafly Prioritisation Voting Tool</a></h1>
    <div class="ticket-header">
        <h2 style="margin: 0;">
            Vote:
            <a href="https://jira.tools.3stripes.net/browse/<?= urlencode($ticketKey) ?>" target="_blank" class="jira-link">
                <?= htmlspecialchars($ticketKey) ?>
            </a>
        </h2>
        <div class="ticket-position">
            Ticket #<?= $currentPosition ?> out of <?= $totalTickets ?> in session
        </div>
    </div>
    <?php if ($ticketTitle): ?>
        <p class="ticket-title"><strong><?= htmlspecialchars($ticketTitle) ?></strong></p>
    <?php endif; ?>
    <?php if ($reporter): ?>
        <p class="reporter">Reported by: <?= htmlspecialchars($reporter) ?></p>
    <?php endif; ?>

    <?php if (!empty($existingVotes)): ?>
        <div class="previous-votes-indicator">
            <strong>📝 You've already voted on this ticket.</strong> Your previous votes are shown below. You can change them if needed.
        </div>
    <?php endif; ?>

    <form id="voteForm">
        <?php
        $criteria = [
            'traffic' => ['Low', 'Medium', 'High', 'XL'],
            'strategically_aligned' => ['Low', 'Medium', 'High'],
            'evidence' => ['Low', 'Medium', 'High'],
            'build_effort' => ['Low', 'Medium', 'High'],
            'alignment_effort' => ['Low', 'Medium', 'High']
        ];

        $explanations = [
            'traffic' => [
                'Low' => 'Up to 10k per day',
                'Medium' => '10k to 50k per day',
                'High' => '50k to 100k per day',
                'XL' => 'Above 100k per day'
            ],
            'strategically_aligned' => [
                'Low' => 'Not aligned to a strategic goal',
                'Medium' => 'Somewhat aligned to a strategic goal',
                'High' => 'Closely aligned to a strategic goal'
            ],
            'evidence' => [
                'Low' => 'Exploratory idea',
                'Medium' => 'Some evidence',
                'High' => 'Strong evidence'
            ],
            'build_effort' => [
                'Low' => 'Few hours or less',
                'Medium' => 'Up to a few days',
                'High' => 'A few days or more'
            ],
            'alignment_effort' => [
                'Low' => 'Team already aware and onboard',
                'Medium' => 'Not already aware, but no challenges expected',
                'High' => 'Political challenges expected'
            ]
        ];

        foreach ($criteria as $key => $options):
            $label = ucwords(str_replace('_', ' ', $key));
        ?>
        <div class="criteria-row criterion" data-key="<?= $key ?>">
            <div class="criteria-left">
                <div class="label"><?= $label ?></div>
                <div class="options">
                    <?php foreach ($options as $value): 
                        $isSelected = isset($existingVotes[$key]) && $existingVotes[$key] === $value;
                        $selectedClass = $isSelected ? ' selected' : '';
                    ?>
                        <button type="button" 
                                class="<?= $selectedClass ?>" 
                                data-value="<?= $value ?>"
                                onclick="submitVote('<?= $key ?>', '<?= $value ?>')"><?= $value ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="criteria-right">
                <div><strong style="color:#116688">What it means:</strong></div>
                <ul>
                    <?php foreach ($explanations[$key] as $opt => $desc): ?>
                        <li><strong><?= $opt ?>:</strong> <?= $desc ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
function submitVote(criterion, value) {
    const form = document.getElementById('voteForm');
    const buttons = form.querySelectorAll(`[data-key="${criterion}"] .options button`);
    const clickedButton = Array.from(buttons).find(btn => btn.getAttribute('data-value') === value);
    
    // Check if clicking on already selected button (to unvote)
    if (clickedButton && clickedButton.classList.contains('selected')) {
        // Unvote: remove selection and delete from database
        buttons.forEach(btn => btn.classList.remove('selected'));
        
        fetch("vote_unsubmit.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                session: "<?= $session ?>",
                ticket: "<?= $ticketKey ?>",
                criterion: criterion
            })
        });
    } else {
        // Vote: set new selection
        buttons.forEach(btn => btn.classList.remove('selected'));
        if (clickedButton) clickedButton.classList.add('selected');

        fetch("vote_submit.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                session: "<?= $session ?>",
                ticket: "<?= $ticketKey ?>",
                criterion: criterion,
                value: value
            })
        });
    }
}

// Poll for ticket change every 1.5 seconds
setInterval(() => {
    fetch("current_ticket.php?session=<?= $session ?>")
        .then(res => res.text())
        .then(newKey => {
            if (newKey !== "<?= $ticketKey ?>") {
                location.reload();
            }
        });
}, 1500);

</script>
</body>
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'db.php';

// Create session from tab-delimited input
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["tickets"])) {
    $code = substr(md5(uniqid()), 0, 6);
    $conn->query("INSERT INTO sessions (code, created_at) VALUES ('$code', NOW())");

    $tickets = explode("\n", trim($_POST["tickets"]));
    foreach ($tickets as $line) {
        $parts = explode("\t", trim($line));
        if (count($parts) >= 2) {
            $key = trim($parts[0]);
            $title = trim($parts[1]);
            $reporter = isset($parts[2]) ? trim($parts[2]) : null;

            $stmt = $conn->prepare("INSERT INTO tickets (session_code, ticket_key, title, reporter) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $code, $key, $title, $reporter);
            $stmt->execute();
        }
    }

    $first = $conn->query("SELECT ticket_key FROM tickets WHERE session_code='$code' ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($first) {
        $conn->query("INSERT INTO current_ticket (session_code, ticket_key, updated_at) VALUES ('$code', '{$first['ticket_key']}', NOW())");
    }

    header("Location: ?code=$code");
    exit;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Prioritization Voting</title>
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

    textarea {
        width: 100%;
        height: 200px;
        font-family: monospace;
    }

    h1, h2, h3 {
        color: #116688;
    }

    .btn {
        padding: 8px 12px;
        background: #116688;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn:hover {
        background: #0e4f5a;
    }

    .ticket {
        background: #fff;
        border: 1px solid #ddd;
        border-left: 4px solid #116688;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .ticket.current {
        background: #eef9f9;
    }

    .ticket.collapsed {
        padding: 12px 16px;
        cursor: pointer;
    }

    .ticket.collapsed:hover {
        background: #f8f9fa;
    }

    .ticket.collapsed .ticket-content {
        display: none;
    }

    .ticket-content {
        display: block;
        overflow: hidden;
    }
    
    .ticket.collapsing .ticket-content {
        animation: collapseContent 0.6s ease-in forwards;
    }
    
    .ticket.expanding .ticket-content {
        animation: expandContent 0.6s ease-out forwards;
    }
    
    .ticket.collapsed .ticket-content {
        display: none;
    }
    
    @keyframes collapseContent {
        from {
            max-height: 2000px;
        }
        to {
            max-height: 0;
        }
    }
    
    @keyframes expandContent {
        from {
            max-height: 0;
        }
        to {
            max-height: 2000px;
        }
    }

    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .ticket-title {
        flex-grow: 1;
    }

    .send-to-btn {
        padding: 4px 8px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.8em;
        text-decoration: none;
        display: inline-block;
        position: relative;
    }

    .send-to-btn:hover {
        background: #218838;
    }

    .send-to-btn:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        z-index: 1000;
        margin-bottom: 4px;
        max-width: 300px;
        white-space: normal;
        width: 250px;
        text-align: center;
        line-height: 1.2;
    }

    .send-to-btn:hover::before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 4px solid transparent;
        border-top-color: #333;
        z-index: 1000;
    }

    .navigation-controls {
        gap: 10px;
        align-items: center;
        margin: 20px 0;
        text-align: center;
        display: flex;
        justify-content: center;
    }

    .nav-btn {
        padding: 8px 12px;
        background: #116688;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }

    .nav-btn:hover {
        background: #0e4f5a;
    }

    .nav-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .vote-details {
        background: #f9f9f9;
        padding: 10px;
        border-radius: 6px;
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }

    .vote-details > div {
        margin: 0;
        margin-bottom: 15px;
    }

    /* Responsive grid breakpoints */
    @media (max-width: 900px) {
        .vote-details {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .vote-details {
            grid-template-columns: 1fr;
        }
    }

    .vote-details ul {
        margin: 4px 0 12px 20px;
    }

    a.session-link {
        color: #116688;
        text-decoration: none;
        font-weight: bold;
    }

    a.session-link:hover {
        text-decoration: underline;
    }

    .copied-msg {
        color: green;
        display: none;
        font-size: 0.9em;
    }

    .how-it-works {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .how-it-works-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-top: 15px;
    }

    .how-it-works-step {
        padding: 15px;
        background: #FFF;
        border-radius: 6px;
        border-left: 4px solid #116688;
    }

    .how-it-works-step h4 {
        margin-top: 0;
        color: #116688;
        font-size: 1.1em;
    }

    .how-it-works-step p {
        margin-bottom: 0;
        font-size: 0.9em;
        line-height: 1.4;
    }

    @media (max-width: 768px) {
        .how-it-works-grid {
            grid-template-columns: 1fr;
        }
    }

    .sessions-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        margin-top: 10px;
    }

    .sessions-table th {
        background: #116688;
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: 600;
    }

    .sessions-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }

    .sessions-table tr:hover {
        background: #f8f9fa;
    }

    .sessions-table tr:last-child td {
        border-bottom: none;
    }

    .session-code-link {
        color: #116688;
        text-decoration: none;
        font-weight: bold;
    }

    .session-code-link:hover {
        text-decoration: underline;
    }

    .stats-number {
        font-weight: bold;
        color: #116688;
    }

    .score-tooltip {
        position: relative;
        cursor: help;
        border-bottom: 1px dotted #116688;
    }

    .score-tooltip:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        white-space: nowrap;
        z-index: 1000;
        margin-bottom: 2px;
    }

    .score-tooltip:hover::before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 4px solid transparent;
        border-top-color: #333;
        z-index: 1000;
    }
</style>

</head>
<body>
<div class="container">
<h1><a href="./" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($app_name); ?></a></h1>
<?php if (!isset($_GET["code"])): ?>
    
    <h2>How It Works</h2>
    <div class="how-it-works-grid">
        <div class="how-it-works-step">
            <h4>1. Create Session</h4>
            <p>Copy-paste the tickets you will prioritise from smartsheet, and click Create Session. You only need the first 3 columns.</p>
        </div>
        <div class="how-it-works-step">
            <h4>2. Share with Team</h4>
            <p>Copy paste the voting link to your team. They then see the first ticket and can vote on it. You will see their votes coming through in real-time.</p>
        </div>
        <div class="how-it-works-step">
            <h4>3. Go Through the List</h4>
            <p>Navigate the team through the list using the back/next buttons. You can also click "Send To Team" on any ticket to zoom straight to that one.</p>
        </div>
    </div>
    
    <h2>Create Voting Session</h2>
    <form method="post">
        <p>Paste JIRA tickets in the format: <code>KEY [TAB] TITLE [TAB] REPORTER</code></p>
        <p><strong>Tip:</strong> It's the format you get when you paste from smartsheet</p>
        <textarea name="tickets" placeholder="ATP-7667	Top level nav	Swinstead, David [External]&#10;ATP-7708	Display keywords	Patel, Jash [External]" required></textarea>
        <br><br>
        <button class="btn" type="submit">Create Session</button>
    </form>

    <hr><br>
    <h2>Past Sessions</h2>
    <?php
    function formatNiceDate($datetime) {
        $date = new DateTime($datetime);
        $day = (int)$date->format('j');
        
        if (in_array($day, [1, 21, 31])) {
            $suffix = 'st';
        } elseif (in_array($day, [2, 22])) {
            $suffix = 'nd';
        } elseif (in_array($day, [3, 23])) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }
        
        return $date->format('F ') . $day . $suffix . $date->format(' Y');
    }

    $sessions = $conn->query("SELECT code, created_at FROM sessions ORDER BY created_at DESC");
    if ($sessions->num_rows > 0):
    ?>
        <table class="sessions-table">
            <thead>
                <tr>
                    <th>Session Code</th>
                    <th>Date Created</th>
                    <th># Tickets</th>
                    <th># Team Members</th>
                    <th># Votes</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = $sessions->fetch_assoc()):
                    $code = htmlspecialchars($s['code']);
                    $niceDate = formatNiceDate($s['created_at']);
                    
                    // Get ticket count
                    $ticketCount = $conn->query("SELECT COUNT(*) as count FROM tickets WHERE session_code='{$s['code']}'")->fetch_assoc()['count'];
                    
                    // Get unique team members who voted
                    $teamMemberCount = $conn->query("SELECT COUNT(DISTINCT voter_id) as count FROM votes WHERE session_code='{$s['code']}'")->fetch_assoc()['count'];
                    
                    // Get total vote count
                    $voteCount = $conn->query("SELECT COUNT(*) as count FROM votes WHERE session_code='{$s['code']}'")->fetch_assoc()['count'];
                ?>
                    <tr>
                        <td><a href="?code=<?= $code ?>" class="session-code-link"><?= $code ?></a></td>
                        <td><?= $niceDate ?></td>
                        <td><span class="stats-number"><?= $ticketCount ?></span></td>
                        <td><span class="stats-number"><?= $teamMemberCount ?></span></td>
                        <td><span class="stats-number"><?= $voteCount ?></span></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No sessions yet.</p>
    <?php endif; ?>
    

<?php else:
    $code = $_GET["code"];
    $result = $conn->query("SELECT * FROM tickets WHERE session_code='$code'");
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $current = $conn->query("SELECT ticket_key FROM current_ticket WHERE session_code='$code'")->fetch_assoc()["ticket_key"];
?>
    <h2>Session Code: <code><?= htmlspecialchars($code) ?></code></h2>
	<?php
		$baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
		$fullURL = $baseURL . "/priopoker/vote.php?session=" . urlencode($code);
	?>
	<p>
		Share this link with your team:
		<a href="<?= $fullURL ?>" target="_blank" style="color:#116688; font-weight:bold;">
			<?= $fullURL ?>
		</a>
		<button onclick="copyVoteLink()" title="Copy to clipboard" style="margin-left: 8px; background: none; border: none; cursor: pointer;">
			📋
		</button>
		<span id="copied-msg" style="color: green; display: none; font-size: 0.9em;">Copied!</span>
	</p>


    <h3>Tickets</h3>
    <?php 
    $currentIndex = 0;
    foreach ($tickets as $index => $row):
        $ticketKey = $row['ticket_key'];
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $ticketKey);
        $isCurrent = $ticketKey === $current;
        if ($isCurrent) {
            $currentIndex = $index;
        }
    ?>
        <div class="ticket<?= $isCurrent ? ' current' : ' collapsed' ?>" id="ticket-<?= $safeId ?>" onclick="<?= !$isCurrent ? 'toggleTicket(event, this)' : '' ?>">
            <div class="ticket-header">
                <div class="ticket-title">
                    <strong><?= htmlspecialchars($ticketKey) ?></strong> – <?= htmlspecialchars($row['title']) ?>
                    <?php if ($isCurrent): ?>
                        <span style="color: green;">(Current)</span>
                    <?php endif; ?>
                </div>
                <?php if (!$isCurrent): ?>
                    <button onclick="event.stopPropagation(); sendToTicket('<?= htmlspecialchars($ticketKey, ENT_QUOTES) ?>')" class="send-to-btn" data-tooltip="Instead of using the Back/Next controls, you can also click here to zoom the team's view directly to a specific ticket">Send To Team</button>
                <?php endif; ?>
            </div>
            <div class="ticket-content">
                <?php if (!empty($row['reporter'])): ?>
                    <div><em>Reporter:</em> <?= htmlspecialchars($row['reporter']) ?></div>
                <?php endif; ?>
                <br>
                <strong>Votes received:</strong> <span id="vote-count-<?= $safeId ?>">–</span>
                <div id="vote-breakdown-<?= $safeId ?>" class="vote-details">Loading vote details...</div>
                <br style="clear: both;" />
            </div>
        </div>
        
        <?php if ($isCurrent): ?>
            <div class="navigation-controls">
                <?php if ($currentIndex > 0): ?>
                    <button onclick="moveTicket('prev')" class="nav-btn">← Previous Ticket</button>
                <?php endif; ?>
                <?php if ($currentIndex < count($tickets) - 1): ?>
                    <button onclick="moveTicket('next')" class="nav-btn">Next Ticket →</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>



   
   
   <script>
function safeId(str) {
    return str.replace(/[^a-zA-Z0-9_-]/g, "_");
}

const scoringMap = {
    traffic: { "Low": 1, "Medium": 2, "High": 3, "XL": 4 },
    strategically_aligned: { "Low": 1, "Medium": 2, "High": 3 },
    evidence: { "Low": 1, "Medium": 2, "High": 3 },
    build_effort: { "Low": 3, "Medium": 2, "High": 1 },
    alignment_effort: { "Low": 3, "Medium": 2, "High": 1 }
};

function averageToWord(criterion, numericAvg) {
    if (numericAvg === "-" || isNaN(numericAvg)) return "-";
    
    const mapping = scoringMap[criterion];
    if (!mapping) return "-";
    
    // Determine if this is additive or subtractive
    const isAdditive = ["traffic", "strategically_aligned", "evidence"].includes(criterion);
    
    // Apply appropriate rounding
    const roundedScore = isAdditive ? Math.round(numericAvg) : Math.floor(numericAvg);
    
    // Find the word that corresponds to this score
    for (const [word, score] of Object.entries(mapping)) {
        if (score === roundedScore) {
            return word;
        }
    }
    
    // If no exact match, find closest valid score
    const scores = Object.values(mapping);
    const minScore = Math.min(...scores);
    const maxScore = Math.max(...scores);
    
    const clampedScore = Math.max(minScore, Math.min(maxScore, roundedScore));
    
    // Find word with the clamped score
    for (const [word, score] of Object.entries(mapping)) {
        if (score === clampedScore) {
            return word;
        }
    }
    
    return "-";
}

function updateVotes(ticketKey) {
    const safeKey = safeId(ticketKey);

    fetch("vote_count.php?session=<?= $code ?>&ticket=" + encodeURIComponent(ticketKey))
        .then(res => res.text())
        .then(count => {
            const el = document.getElementById("vote-count-" + safeKey);
            if (el) el.textContent = count;
        });

    fetch("vote_details.php?session=<?= $code ?>&ticket=" + encodeURIComponent(ticketKey))
        .then(res => res.json())
        .then(data => {
            let html = "";
            // Iterate through criteria in the order defined in scoringMap
            for (const crit of Object.keys(scoringMap)) {
                if (data[crit]) {
                    const votes = data[crit];
                    let total = 0, count = 0;
                    for (const voter in votes) {
                        const val = votes[voter];
                        const score = (scoringMap[crit] || {})[val];
                        if (score !== undefined) {
                            total += score;
                            count++;
                        }
                    }
                    const avg = count > 0 ? (total / count) : 0;
                    const avgWord = averageToWord(crit, avg);
                    const avgDisplay = count > 0 ? 
                        `<span class="score-tooltip" data-tooltip="${avg.toFixed(1)}">${avgWord}</span>` : 
                        "-";
                    html += `<div><strong>${crit}:</strong><ul>`;
                    for (const voter in votes) {
                        html += `<li>${voter}: ${votes[voter]}</li>`;
                    }
                    html += `</ul><div><strong>Avg: ${avgDisplay}</strong></div></div>`;
                }
            }

            const el = document.getElementById("vote-breakdown-" + safeKey);
            if (el) el.innerHTML = html || "<em>No votes yet</em>";
        });
}

const ticketKeys = <?= json_encode(array_column($tickets, 'ticket_key')) ?>;
const sessionCode = "<?= $code ?>";
let currentTicketKey = "<?= $current ?>";
let isAnimating = false;

// Toggle ticket expand/collapse
function toggleTicket(event, ticketElement) {
    const wasCollapsed = ticketElement.classList.contains('collapsed');
    ticketElement.classList.toggle('collapsed');
    
    // If we're expanding a ticket, fetch its vote data
    if (wasCollapsed) {
        const ticketId = ticketElement.id.replace('ticket-', '');
        // Find the original ticket key from the ticketId
        const ticketKey = ticketKeys.find(key => safeId(key) === ticketId);
        if (ticketKey) {
            updateVotes(ticketKey);
        }
    }
}

function updateAllTickets() {
    if (currentTicketKey) {
        updateVotes(currentTicketKey);
    }
}
updateAllTickets();
setInterval(updateAllTickets, 1500);

// Move to next/prev ticket via AJAX
function moveTicket(action) {
    fetch("ticket_move.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            session: sessionCode,
            action: action
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.current_ticket) {
            updateCurrentTicket(data.current_ticket);
        }
    });
}

// Send to specific ticket via AJAX
function sendToTicket(ticketKey) {
    fetch("ticket_move.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            session: sessionCode,
            action: 'send_to',
            ticket_key: ticketKey
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.current_ticket) {
            updateCurrentTicket(data.current_ticket);
        }
    });
}

// Update UI to reflect new current ticket
function updateCurrentTicket(newTicketKey) {
    if (newTicketKey === currentTicketKey) return;
    if (isAnimating) return; // avoid overlapping state changes during animation
    isAnimating = true;
    
    const oldCurrentTicket = document.querySelector('.ticket.current');
    currentTicketKey = newTicketKey;
    const newSafeId = safeId(newTicketKey);
    
    // First, collapse the old current ticket
    if (oldCurrentTicket) {
        // Start collapse animation
        oldCurrentTicket.classList.add('collapsing');

        // Sequence: wait for collapse animation to finish, then 200ms delay, then expand
        const onCollapseEnd = () => {
            oldCurrentTicket.removeEventListener('animationend', onCollapseEnd);

            oldCurrentTicket.classList.remove('current', 'collapsing');
            oldCurrentTicket.classList.add('collapsed');
            oldCurrentTicket.onclick = (e) => toggleTicket(e, oldCurrentTicket);

            // Remove all current labels from all tickets
            document.querySelectorAll('.ticket-title span').forEach(span => {
                if (span.textContent.includes('(Current)')) {
                    span.remove();
                }
            });

            // Remove all navigation controls
            document.querySelectorAll('.navigation-controls').forEach(nav => nav.remove());

            // Remove all send-to buttons
            document.querySelectorAll('.send-to-btn').forEach(btn => btn.remove());

            // 200ms delay before expanding the new current ticket
            setTimeout(() => {
                const newCurrentTicket = document.getElementById('ticket-' + newSafeId);
                if (newCurrentTicket) {
                    newCurrentTicket.classList.remove('collapsed');
                    newCurrentTicket.classList.add('expanding', 'current');
                    newCurrentTicket.onclick = null;

                    const titleDiv = newCurrentTicket.querySelector('.ticket-title');
                    const currentLabel = document.createElement('span');
                    currentLabel.style.color = 'green';
                    currentLabel.textContent = ' (Current)';
                    titleDiv.appendChild(currentLabel);

                    // Remove expanding class after animation (600ms)
                    const onExpandEnd = () => {
                        newCurrentTicket.classList.remove('expanding');
                        newCurrentTicket.removeEventListener('animationend', onExpandEnd);
                        isAnimating = false; // animation finished, allow polling updates
                    };
                    newCurrentTicket.addEventListener('animationend', onExpandEnd);

                    // Add navigation controls
                    const ticketIndex = ticketKeys.indexOf(newTicketKey);
                    const navControls = document.createElement('div');
                    navControls.className = 'navigation-controls';

                    if (ticketIndex > 0) {
                        const prevBtn = document.createElement('button');
                        prevBtn.className = 'nav-btn';
                        prevBtn.textContent = '← Previous Ticket';
                        prevBtn.onclick = () => moveTicket('prev');
                        navControls.appendChild(prevBtn);
                    }

                    if (ticketIndex < ticketKeys.length - 1) {
                        const nextBtn = document.createElement('button');
                        nextBtn.className = 'nav-btn';
                        nextBtn.textContent = 'Next Ticket →';
                        nextBtn.onclick = () => moveTicket('next');
                        navControls.appendChild(nextBtn);
                    }

                    newCurrentTicket.insertAdjacentElement('afterend', navControls);

                    // Add "Send To Team" buttons to all other tickets
                    ticketKeys.forEach(key => {
                        if (key !== newTicketKey) {
                            const ticketEl = document.getElementById('ticket-' + safeId(key));
                            if (ticketEl) {
                                const header = ticketEl.querySelector('.ticket-header');
                                const sendBtn = document.createElement('button');
                                sendBtn.className = 'send-to-btn';
                                sendBtn.textContent = 'Send To Team';
                                sendBtn.setAttribute('data-tooltip', 'Instead of using the Back/Next controls, you can also click here to zoom the team\'s view directly to a specific ticket');
                                sendBtn.onclick = (e) => { e.stopPropagation(); sendToTicket(key); };
                                header.appendChild(sendBtn);
                            }
                        }
                    });

                    // Refresh votes for the new current ticket immediately
                    updateVotes(newTicketKey);
                }
            }, 200);
        };
        oldCurrentTicket.addEventListener('animationend', onCollapseEnd);
    }
}

// Poll for ticket changes every 1.5 seconds
setInterval(() => {
    if (isAnimating) return; // pause polling reaction during animation
    fetch("current_ticket.php?session=" + sessionCode)
        .then(res => res.text())
        .then(newKey => {
            if (newKey && newKey !== currentTicketKey) {
                updateCurrentTicket(newKey);
            }
        });
}, 1500);
</script>

<?php endif; ?>
<script>
function copyVoteLink() {
    const url = "<?= $fullURL ?>";
    navigator.clipboard.writeText(url).then(() => {
        const msg = document.getElementById("copied-msg");
        msg.style.display = "inline";
        setTimeout(() => {
            msg.style.display = "none";
        }, 1500);
    });
}
</script>
</div>
</body>
</html>

<?php
// Server-Sent Events endpoint for real-time sync
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$salaId = isset($_GET['sala_id']) ? (int) $_GET['sala_id'] : 0;
if (!$salaId) {
    http_response_code(400);
    echo "sala_id required";
    exit;
}

if (ob_get_level())
    ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$lastUpdated = '';
$retries = 0;
$maxRetries = 120; // 2 min max connection

while ($retries < $maxRetries) {
    try {
        // Join gym_sessions to get real-time elapsed data via block_resumed_at
        $stmt = db()->prepare(
            "SELECT ss.state_json, ss.updated_at,
                    gs.status, gs.current_block_elapsed, gs.block_resumed_at
             FROM sync_state ss
             LEFT JOIN gym_sessions gs ON gs.id = ss.session_id
             WHERE ss.sala_id = ?"
        );
        $stmt->execute([$salaId]);
        $row = $stmt->fetch();

        if ($row && $row['updated_at'] !== $lastUpdated) {
            $lastUpdated = $row['updated_at'];
            $state = $row['state_json'] ? json_decode($row['state_json'], true) : ['status' => 'idle'];

            // Override 'elapsed' with real-time server calculation when playing
            if ($row['status'] === 'playing' && !empty($row['block_resumed_at'])) {
                $baseElapsed = (int) $row['current_block_elapsed'];
                $resumedTs = strtotime($row['block_resumed_at']);
                $state['elapsed'] = $baseElapsed + (time() - $resumedTs);
                $state['server_ts'] = time();
            }

            echo "event: sync\n";
            echo "data: " . json_encode($state) . "\n\n";
        } else {
            // Heartbeat only — no extra push while playing
            echo ": heartbeat\n\n";
        }
    } catch (Exception $e) {
        echo "event: error\ndata: {\"error\":\"DB error\"}\n\n";
    }

    ob_flush();
    flush();

    if (connection_aborted())
        break;

    usleep(500000); // poll every 0.5s for fast response to state changes
    $retries++;
}

echo "event: close\ndata: {}\n\n";

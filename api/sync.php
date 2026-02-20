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

// Disable output buffering
if (ob_get_level())
    ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Send initial heartbeat
$lastUpdated = '';
$retries = 0;
$maxRetries = 120; // 2 min max connection

while ($retries < $maxRetries) {
    try {
        $stmt = db()->prepare("SELECT state_json, updated_at FROM sync_state WHERE sala_id = ?");
        $stmt->execute([$salaId]);
        $row = $stmt->fetch();

        if ($row && $row['updated_at'] !== $lastUpdated) {
            $lastUpdated = $row['updated_at'];
            $state = $row['state_json'] ? json_decode($row['state_json'], true) : ['status' => 'idle'];
            echo "event: sync\n";
            echo "data: " . json_encode($state) . "\n\n";
        } else {
            // Heartbeat to keep connection alive
            echo ": heartbeat\n\n";
        }
    } catch (Exception $e) {
        echo "event: error\ndata: {\"error\":\"DB error\"}\n\n";
    }

    ob_flush();
    flush();

    if (connection_aborted())
        break;

    usleep(500000); // poll every 0.5s for tighter instructor→display sync
    $retries++;
}

echo "event: close\ndata: {}\n\n";

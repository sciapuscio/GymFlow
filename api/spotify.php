<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// ─── Helper: fetch Spotify API with auto token refresh ────────────────────────
function spotifyCall(int $userId, string $method, string $path, array $body = []): array
{
    $row = db()->prepare(
        "SELECT spotify_access_token, spotify_refresh_token, spotify_expires_at,
                spotify_client_id, spotify_client_secret
         FROM instructor_profiles WHERE user_id = ?"
    );
    $row->execute([$userId]);
    $profile = $row->fetch();

    if (!$profile || !$profile['spotify_access_token']) {
        return ['error' => 'not_connected', 'status' => 401];
    }

    // Refresh token if expired (or within 60s of expiry)
    if (!$profile['spotify_expires_at'] || strtotime($profile['spotify_expires_at']) < time() + 60) {
        $refreshed = spotifyRefreshToken($userId, $profile['spotify_refresh_token'], $profile['spotify_client_id'], $profile['spotify_client_secret']);
        if (isset($refreshed['error']))
            return $refreshed;
        $profile['spotify_access_token'] = $refreshed['access_token'];
    }

    $ch = curl_init("https://api.spotify.com/v1$path");
    $headers = ["Authorization: Bearer {$profile['spotify_access_token']}", "Content-Type: application/json"];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
    ]);
    if ($body && in_array(strtoupper($method), ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $resp ? (json_decode($resp, true) ?? []) : [];
    $decoded['_http_status'] = $code;
    return $decoded;
}

function spotifyRefreshToken(int $userId, string $refreshToken, string $clientId, string $clientSecret): array
{
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]),
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode("$clientId:$clientSecret")],
        CURLOPT_TIMEOUT => 8,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true) ?? [];
    if (empty($data['access_token']))
        return ['error' => 'refresh_failed'];

    $expiresAt = date('Y-m-d H:i:s', time() + ($data['expires_in'] ?? 3600));
    db()->prepare("UPDATE instructor_profiles SET spotify_access_token=?, spotify_expires_at=? WHERE user_id=?")
        ->execute([$data['access_token'], $expiresAt, $userId]);
    return $data;
}

// ─── OAUTH: Redirect to Spotify ───────────────────────────────────────────────
if ($action === 'auth') {
    $user = requireAuth('instructor', 'admin', 'superadmin');

    $profile = db()->prepare("SELECT spotify_client_id FROM instructor_profiles WHERE user_id=?");
    $profile->execute([$user['id']]);
    $p = $profile->fetch();

    if (empty($p['spotify_client_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Configurá tu Client ID primero en el perfil']);
        exit;
    }

    $redirectUri = BASE_URL ? 'https://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/spotify.php?action=callback'
        : 'https://' . $_SERVER['HTTP_HOST'] . '/api/spotify.php?action=callback';
    $scopes = 'user-read-playback-state user-modify-playback-state user-read-currently-playing playlist-read-private streaming';
    $state = base64_encode(json_encode(['uid' => $user['id']]));

    $url = 'https://accounts.spotify.com/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $p['spotify_client_id'],
        'scope' => $scopes,
        'redirect_uri' => $redirectUri,
        'state' => $state,
    ]);
    // Return as JSON so frontend can redirect
    jsonResponse(['redirect' => $url]);
}

// ─── OAUTH: Callback ──────────────────────────────────────────────────────────
if ($action === 'callback') {
    header('Content-Type: text/html; charset=utf-8');

    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $error = $_GET['error'] ?? '';

    if ($error || !$code || !$state) {
        echo "<script>window.opener?.postMessage({spotify:'error',msg:'" . addslashes($error) . "'},'*');window.close();</script>";
        exit;
    }

    $stateData = json_decode(base64_decode($state), true);
    $userId = (int) ($stateData['uid'] ?? 0);
    if (!$userId) {
        echo "<script>window.close();</script>";
        exit;
    }

    $profile = db()->prepare("SELECT spotify_client_id, spotify_client_secret FROM instructor_profiles WHERE user_id=?");
    $profile->execute([$userId]);
    $p = $profile->fetch();

    $redirectUri = BASE_URL ? 'https://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/spotify.php?action=callback'
        : 'https://' . $_SERVER['HTTP_HOST'] . '/api/spotify.php?action=callback';

    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]),
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode("{$p['spotify_client_id']}:{$p['spotify_client_secret']}")],
        CURLOPT_TIMEOUT => 8,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $tokens = json_decode($resp, true) ?? [];

    if (empty($tokens['access_token'])) {
        echo "<script>window.opener?.postMessage({spotify:'error',msg:'Token exchange failed'},'*');window.close();</script>";
        exit;
    }

    $expiresAt = date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600));
    db()->prepare("UPDATE instructor_profiles SET spotify_access_token=?, spotify_refresh_token=?, spotify_expires_at=? WHERE user_id=?")
        ->execute([$tokens['access_token'], $tokens['refresh_token'] ?? null, $expiresAt, $userId]);

    // Get display name for confirmation
    $me = spotifyCall($userId, 'GET', '/me');
    $name = htmlspecialchars($me['display_name'] ?? 'tu cuenta');

    echo "<script>window.opener?.postMessage({spotify:'connected',name:'" . addslashes($name) . "'},'*');window.close();</script>";
    exit;
}

// ─── The following actions require auth ───────────────────────────────────────
$user = requireAuth('instructor', 'admin', 'superadmin');

// ─── NOW PLAYING ──────────────────────────────────────────────────────────────
if ($action === 'now-playing') {
    // Allow querying by session/sala: find instructor from session
    $instructorId = $user['id'];
    if (isset($_GET['sala_id'])) {
        $salaId = (int) $_GET['sala_id'];
        $stmt = db()->prepare(
            "SELECT gs.instructor_id FROM gym_sessions gs
             JOIN salas s ON gs.sala_id = s.id
             WHERE gs.sala_id = ? AND gs.status IN ('playing','paused')
             ORDER BY gs.updated_at DESC LIMIT 1"
        );
        $stmt->execute([$salaId]);
        $sess = $stmt->fetch();
        $instructorId = (int) ($sess['instructor_id'] ?? $user['id']);
    }

    // ── APCu cache: share the Spotify response across all polling clients ────
    // Both live.php and sala.php poll now-playing independently; without caching
    // they can easily burn through Spotify's rate limit together.
    // Cache per instructor for 5 seconds — short enough for a live session,
    // cheap enough to avoid 429s on sustained polling.
    $cacheKey = "sp_now_playing_{$instructorId}";
    $useCache = function_exists('apcu_fetch');
    if ($useCache) {
        $cached = apcu_fetch($cacheKey, $found);
        if ($found) {
            jsonResponse($cached);
        }
    }

    $data = spotifyCall($instructorId, 'GET', '/me/player/currently-playing');

    // If Spotify is rate-limiting us, return a minimal response so the
    // frontend can apply backoff instead of hammering the endpoint.
    if (($data['_http_status'] ?? 0) === 429) {
        $resp = ['playing' => false, 'status' => 429];
        if ($useCache)
            apcu_store($cacheKey, $resp, 15); // cache the 429 for 15s
        jsonResponse($resp);
    }

    if (empty($data['item'])) {
        $resp = ['playing' => false];
        if ($useCache)
            apcu_store($cacheKey, $resp, 5);
        jsonResponse($resp);
    }
    $track = $data['item'];
    $resp = [
        'playing' => $data['is_playing'] ?? false,
        'track' => $track['name'] ?? '',
        'artists' => implode(', ', array_column($track['artists'] ?? [], 'name')),
        'album' => $track['album']['name'] ?? '',
        'cover' => $track['album']['images'][0]['url'] ?? null,
        'progress_ms' => $data['progress_ms'] ?? 0,
        'duration_ms' => $track['duration_ms'] ?? 0,
        'uri' => $track['uri'] ?? '',
    ];
    if ($useCache)
        apcu_store($cacheKey, $resp, 5);
    jsonResponse($resp);
}

// ─── PLAY ─────────────────────────────────────────────────────────────────────
if ($action === 'play') {
    $data = getBody();
    $body = [];
    if (!empty($data['context_uri']))
        $body['context_uri'] = $data['context_uri']; // playlist/album
    if (!empty($data['uris']))
        $body['uris'] = $data['uris'];          // track array
    if (!empty($data['offset']))
        $body['offset'] = $data['offset'];
    if (isset($data['position_ms']) && $data['position_ms'] > 0)
        $body['position_ms'] = (int) $data['position_ms']; // seek to position on resume

    // Use explicit device_id if provided, otherwise try without first
    $deviceParam = !empty($data['device_id']) ? '?device_id=' . urlencode($data['device_id']) : '';
    $result = spotifyCall($user['id'], 'PUT', "/me/player/play$deviceParam", $body);

    // Spotify returns 404 when there's no active device.
    // Auto-retry: pick the best available device (active > phone/tablet > computer)
    if (($result['_http_status'] ?? 0) === 404 && empty($data['device_id'])) {
        $devices = spotifyCall($user['id'], 'GET', '/me/player/devices');
        $available = $devices['devices'] ?? [];
        if (!empty($available)) {
            // Priority: is_active first, then non-Computer types (phone/tablet), then any
            $best = null;
            foreach ($available as $dev) {
                if ($dev['is_active']) {
                    $best = $dev;
                    break;
                }
            }
            if (!$best) {
                foreach ($available as $dev) {
                    if (strtolower($dev['type'] ?? '') !== 'computer') {
                        $best = $dev;
                        break;
                    }
                }
            }
            if (!$best)
                $best = $available[0];

            $result = spotifyCall($user['id'], 'PUT', "/me/player/play?device_id=" . urlencode($best['id']), $body);
        }
    }


    jsonResponse(['ok' => true, 'status' => $result['_http_status'] ?? 0]);
}


// ─── PAUSE ────────────────────────────────────────────────────────────────────
if ($action === 'pause') {
    spotifyCall($user['id'], 'PUT', '/me/player/pause');
    jsonResponse(['ok' => true]);
}

// ─── NEXT ─────────────────────────────────────────────────────────────────────
if ($action === 'next') {
    spotifyCall($user['id'], 'POST', '/me/player/next');
    jsonResponse(['ok' => true]);
}

// ─── PREVIOUS ─────────────────────────────────────────────────────────────────
if ($action === 'prev') {
    spotifyCall($user['id'], 'POST', '/me/player/previous');
    jsonResponse(['ok' => true]);
}

// ─── SEARCH ───────────────────────────────────────────────────────────────────
if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    $type = trim($_GET['type'] ?? 'track,playlist');
    if (!$q)
        jsonError('Query required');
    // Build query manually — http_build_query encodes commas in 'type' as %2C which Spotify rejects
    $qs = 'q=' . urlencode($q) . '&type=' . $type . '&limit=10';
    $data = spotifyCall($user['id'], 'GET', '/search?' . $qs);
    jsonResponse($data);
}

// ─── DEVICES ──────────────────────────────────────────────────────────────────
if ($action === 'devices') {
    $data = spotifyCall($user['id'], 'GET', '/me/player/devices');
    jsonResponse($data['devices'] ?? []);
}

// ─── VOLUME ───────────────────────────────────────────────────────────────────
if ($action === 'volume') {
    $vol = (int) ($_GET['vol'] ?? 50);
    spotifyCall($user['id'], 'PUT', '/me/player/volume?volume_percent=' . max(0, min(100, $vol)));
    jsonResponse(['ok' => true]);
}

// ─── REPEAT ───────────────────────────────────────────────────────────────────
// state: 'off' | 'track' | 'context'
if ($action === 'repeat') {
    $state = $_GET['state'] ?? 'off';
    if (!in_array($state, ['off', 'track', 'context']))
        $state = 'off';
    spotifyCall($user['id'], 'PUT', '/me/player/repeat?state=' . $state);
    jsonResponse(['ok' => true]);
}

// ─── DISCONNECT ───────────────────────────────────────────────────────────────
if ($action === 'disconnect') {
    db()->prepare("UPDATE instructor_profiles SET spotify_access_token=NULL, spotify_refresh_token=NULL, spotify_expires_at=NULL WHERE user_id=?")
        ->execute([$user['id']]);
    jsonResponse(['ok' => true]);
}

// ─── SAVE CREDENTIALS ─────────────────────────────────────────────────────────
if ($action === 'save-credentials') {
    $data = getBody();
    $cid = trim($data['client_id'] ?? '');
    $secret = trim($data['client_secret'] ?? '');
    if (!$cid || !$secret)
        jsonError('Client ID y Secret son requeridos');
    db()->prepare("INSERT INTO instructor_profiles (user_id, spotify_client_id, spotify_client_secret)
                   VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE
                       spotify_client_id = VALUES(spotify_client_id),
                       spotify_client_secret = VALUES(spotify_client_secret)")
        ->execute([$user['id'], $cid, $secret]);
    jsonResponse(['ok' => true]);
}

// ─── MY PLAYLISTS ─────────────────────────────────────────────────────────────
if ($action === 'playlists') {
    $data = spotifyCall($user['id'], 'GET', '/me/playlists?limit=20');
    $lists = [];
    foreach ($data['items'] ?? [] as $pl) {
        $lists[] = [
            'name' => $pl['name'],
            'uri' => $pl['uri'],
            'cover' => $pl['images'][0]['url'] ?? null,
            'tracks' => $pl['tracks']['total'] ?? 0,
        ];
    }
    jsonResponse($lists);
}

jsonError('Unknown action', 404);

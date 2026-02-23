<?php
/**
 * WOD Generator API
 * Generates a professional 50-minute workout session algorithmically.
 *
 * POST /api/wod_generator.php
 * Body: {
 *   "level": "beginner"|"intermediate"|"advanced",
 *   "upper_pct": 35,
 *   "lower_pct": 45,
 *   "core_pct": 20,
 *   "style": "crossfit"|"hiit"|"strength"|"mixed",
 *   "equipment": [] // optional
 * }
 *
 * Returns: { "blocks": [...], "meta": { ... } }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// ── Input validation & defaults ───────────────────────────────────────────────
$level = in_array($input['level'] ?? '', ['beginner', 'intermediate', 'advanced'])
    ? $input['level'] : 'intermediate';
$style = in_array($input['style'] ?? '', ['crossfit', 'hiit', 'strength', 'mixed'])
    ? $input['style'] : 'crossfit';
$upperPct = max(0, min(100, (int) ($input['upper_pct'] ?? 35)));
$lowerPct = max(0, min(100, (int) ($input['lower_pct'] ?? 45)));
$corePct = max(0, min(100, (int) ($input['core_pct'] ?? 20)));
$equipment = isset($input['equipment']) && is_array($input['equipment'])
    ? $input['equipment'] : [];

// Normalize percentages to 100
$total = $upperPct + $lowerPct + $corePct;
if ($total <= 0) {
    $upperPct = 35;
    $lowerPct = 45;
    $corePct = 20;
    $total = 100;
}
$upperPct = round($upperPct * 100 / $total);
$lowerPct = round($lowerPct * 100 / $total);
$corePct = 100 - $upperPct - $lowerPct;

// ── Exercise pool from DB ─────────────────────────────────────────────────────
$levelFilter = match ($level) {
    'beginner' => ['beginner', 'all'],
    'intermediate' => ['beginner', 'intermediate', 'all'],
    'advanced' => ['intermediate', 'advanced', 'all'],
};
$placeholders = implode(',', array_fill(0, count($levelFilter), '?'));
$params = array_merge([$gymId], $levelFilter);

$sql = "SELECT id, name, name_es, muscle_group, level, duration_rec, equipment, tags_json
        FROM exercises
        WHERE active = 1
          AND (gym_id = ? OR gym_id IS NULL)
          AND level IN ($placeholders)
        ORDER BY RAND()";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$allExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by equipment if provided
if (!empty($equipment)) {
    $allExercises = array_filter($allExercises, function ($ex) use ($equipment) {
        $exEquip = json_decode($ex['equipment'] ?? '[]', true) ?: [];
        // Include if exercise needs no equipment OR all its equipment is available
        if (empty($exEquip))
            return true;
        return count(array_intersect($exEquip, $equipment)) > 0;
    });
    $allExercises = array_values($allExercises);
}

// Group exercises by muscle zone
// upper = chest, back, shoulders, arms
// lower = legs, glutes
// core  = core
// cardio/full_body = wildcard (available for any zone)
$pools = [
    'upper' => [],
    'lower' => [],
    'core' => [],
    'cardio' => [],
    'full' => [],
    'warmup' => [],
];
$upperGroups = ['chest', 'back', 'shoulders', 'arms'];
$lowerGroups = ['legs', 'glutes'];

foreach ($allExercises as $ex) {
    $mg = $ex['muscle_group'];
    if (in_array($mg, $upperGroups))
        $pools['upper'][] = $ex;
    elseif (in_array($mg, $lowerGroups))
        $pools['lower'][] = $ex;
    elseif ($mg === 'core')
        $pools['core'][] = $ex;
    elseif ($mg === 'cardio')
        $pools['cardio'][] = $ex;
    elseif ($mg === 'full_body')
        $pools['full'][] = $ex;
}

// Warmup pool: cardio + full_body beginner/all only
$pools['warmup'] = array_filter(
    array_merge($pools['cardio'], $pools['full']),
    fn($ex) => in_array($ex['level'], ['beginner', 'all'])
);
$pools['warmup'] = array_values($pools['warmup']);

// ── Helper functions ──────────────────────────────────────────────────────────

function pickExercises(array &$pool, int $count, array &$used): array
{
    $picked = [];
    shuffle($pool);
    foreach ($pool as $ex) {
        if (in_array($ex['id'], $used))
            continue;
        $picked[] = $ex;
        $used[] = $ex['id'];
        if (count($picked) >= $count)
            break;
    }
    return $picked;
}

/**
 * Keywords in the exercise name that indicate it's a timed/isometric hold.
 * These exercises get a duration (seconds) instead of rep count.
 */
const ISOMETRIC_KEYWORDS = [
    'plancha',
    'plank',
    'wall sit',
    'isométric',
    'isometric',
    'hold',
    'l-sit',
    'hollow',
    'dead hang',
    'colgado',
    'parado',
    'pausa',
    'bridge hold',
    'glute bridge hold',
    'superman hold',
    'puente',
    'equilibrio',
    'balanceo'
];

function isIsometric(array $ex): bool
{
    $name = strtolower($ex['name_es'] ?? $ex['name'] ?? '');
    $tags = strtolower(json_encode($ex['tags_json'] ?? ''));
    foreach (ISOMETRIC_KEYWORDS as $kw) {
        if (str_contains($name, $kw) || str_contains($tags, $kw)) {
            return true;
        }
    }
    return false;
}

function exToBlock(array $ex, int $reps, int $isoDuration = 30): array
{
    if (isIsometric($ex)) {
        return [
            'id' => $ex['id'],
            'name' => $ex['name'] ?? $ex['name_es'],   // English first
            'duration' => $isoDuration,   // seconds held
        ];
    }
    return [
        'id' => $ex['id'],
        'name' => $ex['name'] ?? $ex['name_es'],           // English first
        'reps' => $reps,
    ];
}

function exToBlockTime(array $ex): array
{
    return [
        'id' => $ex['id'],
        'name' => $ex['name'] ?? $ex['name_es'],   // English first
    ];
}


/**
 * Randomly picks one valid block type for main blocks based on level + style.
 * This is the key driver of variety — every generation can produce a different structure.
 */
function pickBlockType(string $level, string $style, int $slot): string
{
    // slot 0 = Block 1 (main), slot 1 = Block 2 (secondary)
    if ($style === 'strength') {
        return 'series'; // strength always uses series
    }

    $options = match ($level) {
        'beginner' => $slot === 0
        ? ['circuit', 'interval', 'tabata']
        : ['circuit', 'interval'],
        'intermediate' => $slot === 0
        ? ['tabata', 'emom', 'interval', 'circuit']
        : ['circuit', 'interval', 'tabata'],
        'advanced' => $slot === 0
        ? ['emom', 'tabata', 'interval']
        : ['interval', 'circuit', 'amrap', 'emom'],
    };

    // Bias by style: hiit prefers tabata/interval, crossfit prefers emom/amrap
    if ($style === 'hiit') {
        $options = array_values(array_filter($options, fn($t) => in_array($t, ['tabata', 'interval', 'circuit']) ?: true));
    } elseif ($style === 'crossfit') {
        // Add crossfit-flavored types
        $options = array_merge($options, ['emom']);
    }

    return $options[array_rand($options)];
}

/**
 * Randomly picks a finisher type appropriate for the level.
 */
function pickFinisherType(string $level): string
{
    return match ($level) {
        'beginner' => ['amrap', 'circuit'][array_rand(['amrap', 'circuit'])],
        'intermediate' => ['fortime', 'amrap'][array_rand(['fortime', 'amrap'])],
        'advanced' => ['amrap', 'fortime', 'emom'][array_rand(['amrap', 'fortime', 'emom'])],
    };
}

// ── Level config ──────────────────────────────────────────────────────────────
$cfg = match ($level) {
    'beginner' => [
        'reps' => [8, 10, 8],   // [primary, secondary, finisher]
        'rounds' => [3, 2, 3],
        'work' => 30,            // seconds work for intervals
        'rest' => 30,            // seconds rest
        'amrap_min' => 5,
        'warmup_work' => 35,
        'warmup_rest' => 25,
    ],
    'intermediate' => [
        'reps' => [12, 10, 15],
        'rounds' => [4, 3, 4],
        'work' => 40,
        'rest' => 20,
        'amrap_min' => 7,
        'warmup_work' => 40,
        'warmup_rest' => 20,
    ],
    'advanced' => [
        'reps' => [15, 12, 21],
        'rounds' => [5, 4, 5],
        'work' => 45,
        'rest' => 15,
        'amrap_min' => 8,
        'warmup_work' => 40,
        'warmup_rest' => 20,
    ],
};

// ── Music catalogue ───────────────────────────────────────────────────────────
// Moods: chill (brief/rest), warmup, work (main blocks), intense (tabata/amrap/emom), finish (finisher)
$musicCatalogue = [
    'chill' => [
        'genre' => 'Reggae / Chill',
        'icon' => '🎵',
        'tracks' => [
            ['artist' => 'Bob Marley', 'track' => 'Three Little Birds'],
            ['artist' => 'Bob Marley', 'track' => 'Could You Be Loved'],
            ['artist' => 'Damian Marley', 'track' => 'Road to Zion'],
            ['artist' => 'Rebelution', 'track' => 'Feeling Alright'],
            ['artist' => 'Stick Figure', 'track' => 'Choice Is Yours'],
            ['artist' => 'Sublime', 'track' => 'What I Got'],
            ['artist' => 'Jack Johnson', 'track' => 'Better Together'],
            ['artist' => 'Ben Harper', 'track' => 'Burn One Down'],
            ['artist' => 'UB40', 'track' => 'Red Red Wine'],
            ['artist' => 'Steel Pulse', 'track' => 'Steppin Out'],
        ],
    ],
    'warmup' => [
        'genre' => 'Hip-Hop / Funk',
        'icon' => '🎶',
        'tracks' => [
            ['artist' => 'Fatboy Slim', 'track' => 'Praise You'],
            ['artist' => 'Gorillaz', 'track' => 'Feel Good Inc'],
            ['artist' => 'Chemical Brothers', 'track' => 'Block Rockin\' Beats'],
            ['artist' => 'Justice', 'track' => 'D.A.N.C.E.'],
            ['artist' => 'Dua Lipa', 'track' => 'Levitating'],
            ['artist' => 'Pharrell Williams', 'track' => 'Happy'],
            ['artist' => 'Mark Ronson', 'track' => 'Uptown Funk'],
            ['artist' => 'The Prodigy', 'track' => 'Breathe'],
            ['artist' => 'Outkast', 'track' => 'Hey Ya!'],
            ['artist' => 'Black Eyed Peas', 'track' => 'I Gotta Feeling'],
        ],
    ],
    'work' => [
        'genre' => 'Techno / EDM',
        'icon' => '⚡',
        'tracks' => [
            ['artist' => 'Daft Punk', 'track' => 'Harder Better Faster Stronger'],
            ['artist' => 'Chemical Brothers', 'track' => 'Hey Boy Hey Girl'],
            ['artist' => 'Swedish House Mafia', 'track' => 'Save the World'],
            ['artist' => 'Martin Garrix', 'track' => 'Animals'],
            ['artist' => 'Avicii', 'track' => 'Levels'],
            ['artist' => 'David Guetta', 'track' => 'Titanium'],
            ['artist' => 'Deadmau5', 'track' => 'Strobe'],
            ['artist' => 'Eric Prydz', 'track' => 'Call On Me'],
            ['artist' => 'Skrillex', 'track' => 'Bangarang'],
            ['artist' => 'Knife Party', 'track' => 'Internet Friends'],
            ['artist' => 'Alan Walker', 'track' => 'Faded'],
            ['artist' => 'Tiësto', 'track' => 'Red Lights'],
        ],
    ],
    'intense' => [
        'genre' => 'Rock / Metal',
        'icon' => '🔥',
        'tracks' => [
            ['artist' => 'AC/DC', 'track' => 'Thunderstruck'],
            ['artist' => 'Metallica', 'track' => 'Master of Puppets'],
            ['artist' => 'Rage Against the Machine', 'track' => 'Killing in the Name'],
            ['artist' => 'Linkin Park', 'track' => 'Numb/Encore'],
            ['artist' => 'System of a Down', 'track' => 'B.Y.O.B.'],
            ['artist' => 'The Prodigy', 'track' => 'Firestarter'],
            ['artist' => 'Slipknot', 'track' => 'Wait and Bleed'],
            ['artist' => 'Marilyn Manson', 'track' => 'The Beautiful People'],
            ['artist' => 'Rammstein', 'track' => 'Du Hast'],
            ['artist' => 'Nine Inch Nails', 'track' => 'Closer'],
        ],
    ],
    'finish' => [
        'genre' => 'Hip-Hop / Motivacional',
        'icon' => '🏁',
        'tracks' => [
            ['artist' => 'Eminem', 'track' => 'Till I Collapse'],
            ['artist' => 'Eminem', 'track' => 'Lose Yourself'],
            ['artist' => 'DMX', 'track' => 'X Gon\' Give It To Ya'],
            ['artist' => 'Survivor', 'track' => 'Eye of the Tiger'],
            ['artist' => 'Kanye West', 'track' => 'POWER'],
            ['artist' => 'Jay-Z', 'track' => 'Empire State of Mind'],
            ['artist' => '2Pac', 'track' => 'Ambitionz Az a Ridah'],
            ['artist' => "Kendrick Lamar", 'track' => 'HUMBLE.'],
            ['artist' => 'Rick Ross', 'track' => 'Hustlin\''],
            ['artist' => 'Meek Mill', 'track' => 'Dreams and Nightmares'],
        ],
    ],
];

// Maps block type → mood key
function moodForBlock(string $type): string
{
    return match ($type) {
        'briefing' => 'chill',
        'rest' => 'chill',
        'interval', 'tabata' => 'work',
        'emom' => 'intense',
        'amrap' => 'intense',
        'circuit' => 'work',
        'series' => 'work',
        'fortime' => 'finish',
        default => 'work',
    };
}

function pickMusicForBlock(string $type, array $catalogue): array
{
    // Warmup gets its own mood
    if ($type === '__warmup__') {
        $bucket = $catalogue['warmup'];
    } else {
        $mood = moodForBlock($type);
        $bucket = $catalogue[$mood];
    }
    $track = $bucket['tracks'][array_rand($bucket['tracks'])];
    return [
        'genre' => $bucket['genre'],
        'icon' => $bucket['icon'],
        'artist' => $track['artist'],
        'track' => $track['track'],
        'query' => urlencode($track['artist'] . ' ' . $track['track']),
    ];
}

// ── Determine exercise counts per zone per block ──────────────────────────────
// Total working blocks = 2 main + 1 finisher = 3 "slots"
// We distribute the % across all working exercises
// Each block has ~3 exercises → 9 total working exercises
// Distribute: upper, lower, core
$totalEx = 9;
$upperCount = max(1, round($upperPct / 100 * $totalEx));
$lowerCount = max(1, round($lowerPct / 100 * $totalEx));
$coreCount = $totalEx - $upperCount - $lowerCount;
if ($coreCount < 1) {
    $coreCount = 1;
    $lowerCount = $totalEx - $upperCount - 1;
}

// Spread counts across 3 blocks (warmup, bloque1, bloque2, finisher)
// Block1: upper+lower focus, Block2: core+opposite, Finisher: mixed
$b1Upper = (int) ceil($upperCount * 0.6);
$b1Lower = (int) ceil($lowerCount * 0.6);
$b1Core = (int) floor($coreCount * 0.3);

$b2Upper = $upperCount - $b1Upper;
$b2Lower = $lowerCount - $b1Lower;
$b2Core = (int) ceil($coreCount * 0.4);

$bfUpper = max(0, $upperCount - $b1Upper - $b2Upper);
$bfLower = max(0, $lowerCount - $b1Lower - $b2Lower);
$bfCore = $coreCount - $b1Core - $b2Core;

// ── Seed RNG with microseconds so every request is TRULY different ──────────
srand((int) (microtime(true) * 100000) % PHP_INT_MAX);

// ── Pick block types BEFORE picking exercises so names are consistent ─────────
$blockType1 = pickBlockType($level, $style, 0);
$blockType2 = pickBlockType($level, $style, 1);
$finisherType = pickFinisherType($level);

// ── Pick exercises ────────────────────────────────────────────────────────────
$used = [];
$warmupEx = pickExercises($pools['warmup'], 3, $used);
if (count($warmupEx) < 3) {
    $extra = pickExercises($pools['cardio'], 3 - count($warmupEx), $used);
    $warmupEx = array_merge($warmupEx, $extra);
}

function pickZonedExercises(array $pools, int $upper, int $lower, int $core, array &$used): array
{
    $ex = [];
    $upperPool = array_merge($pools['upper'], $pools['full']);
    $lowerPool = array_merge($pools['lower'], $pools['full']);
    $corePool = array_merge($pools['core'], $pools['full']);
    $ex = array_merge(
        pickExercises($upperPool, $upper, $used),
        pickExercises($lowerPool, $lower, $used),
        pickExercises($corePool, $core, $used)
    );
    shuffle($ex);
    return $ex;
}

$block1Ex = pickZonedExercises($pools, $b1Upper, $b1Lower, max(1, $b1Core), $used);
$block2Ex = pickZonedExercises($pools, max(0, $b2Upper), max(1, $b2Lower), max(1, $b2Core), $used);
$finisherEx = pickZonedExercises($pools, max(1, $bfUpper), max(0, $bfLower), max(1, $bfCore), $used);

// Ensure each block has at least 2 exercises
function ensureMin(array $ex, array $pools, array &$used, int $min = 2): array
{
    if (count($ex) < $min) {
        $fallback = array_merge($pools['full'], $pools['cardio'], $pools['upper'], $pools['lower']);
        $more = pickExercises($fallback, $min - count($ex), $used);
        $ex = array_merge($ex, $more);
    }
    return $ex;
}
$block1Ex = ensureMin($block1Ex, $pools, $used, 3);
$block2Ex = ensureMin($block2Ex, $pools, $used, 3);
$finisherEx = ensureMin($finisherEx, $pools, $used, 2);

// ── WOD description builder ───────────────────────────────────────────────────
$levelLabel = ['beginner' => 'Fácil', 'intermediate' => 'Intermedio', 'advanced' => 'Difícil'][$level];
$styleLabel = ['crossfit' => 'CrossFit', 'hiit' => 'HIIT', 'strength' => 'Fuerza', 'mixed' => 'Mixto'][$style];
$dateLabel = date('d/m/Y');

// ── Block builders ────────────────────────────────────────────────────────────

function buildWarmupBlock(array $exercises, array $cfg): array
{
    $exList = array_map('exToBlockTime', $exercises);
    return [
        'type' => 'interval',
        'name' => 'Entrada en Calor',
        'config' => [
            'rounds' => 3,
            'work' => $cfg['warmup_work'],
            'rest' => $cfg['warmup_rest'],
            'prep_time' => 10,
        ],
        'exercises' => $exList,
    ];
}

/**
 * Unified builder for main blocks and finisher — uses pre-randomized $blockType.
 * @param string $blockType  One of: circuit, interval, tabata, emom, amrap, fortime, series
 * @param int    $slot       0=block1, 1=block2, 2=finisher (affects naming + config)
 */
function buildWorkBlock(array $exercises, array $cfg, string $blockType, int $slot): array
{
    $repsIdx = min($slot, 2);
    $isoDur = match ($cfg['reps'][$repsIdx]) {  // isometric hold scales with level
        8, 10 => 20,   // beginner
        12, 15 => 30,   // intermediate
        default => 40,  // advanced
    };
    $exList = array_map(fn($ex) => exToBlock($ex, $cfg['reps'][$repsIdx], $isoDur), $exercises);

    $names = [
        0 => [
            'circuit' => 'Circuito Principal',
            'interval' => 'Intervalo Principal',
            'tabata' => 'Tabata Principal',
            'emom' => 'EMOM Principal',
            'series' => 'Bloque de Fuerza',
            'amrap' => 'AMRAP Principal'
        ],
        1 => [
            'circuit' => 'Circuito Secundario',
            'interval' => 'Bloque de Potencia',
            'tabata' => 'Tabata Secundario',
            'emom' => 'EMOM Secundario',
            'series' => 'Bloque de Accesorios',
            'amrap' => 'AMRAP Secundario'
        ],
        2 => [
            'amrap' => '🔥 Finisher AMRAP',
            'fortime' => '🏁 Finisher For Time',
            'emom' => '⚡ Finisher EMOM',
            'circuit' => '🔄 Finisher Circuit'
        ],
    ];
    $blockName = $names[$slot][$blockType] ?? ucfirst($blockType);

    return match ($blockType) {
        'circuit' => [
            'type' => 'circuit',
            'name' => $blockName,
            'config' => ['rounds' => $cfg['rounds'][$repsIdx], 'rest' => $cfg['rest'], 'prep_time' => 15],
            'exercises' => $exList,
        ],
        'interval' => [
            'type' => 'interval',
            'name' => $blockName,
            'config' => ['rounds' => $cfg['rounds'][$repsIdx], 'work' => $cfg['work'], 'rest' => $cfg['rest'], 'prep_time' => 15],
            'exercises' => $exList,
        ],
        'tabata' => [
            'type' => 'tabata',
            'name' => $blockName,
            'config' => ['rounds' => $cfg['rounds'][$repsIdx], 'work' => $cfg['work'], 'rest' => $cfg['rest'], 'prep_time' => 15],
            'exercises' => $exList,
        ],
        'emom' => [
            'type' => 'emom',
            'name' => $blockName,
            'config' => ['duration' => $cfg['rounds'][$repsIdx] * 60, 'interval' => 60, 'prep_time' => 15],
            'exercises' => $exList,
        ],
        'series' => [
            'type' => 'series',
            'name' => $blockName,
            'config' => ['sets' => $cfg['rounds'][$repsIdx], 'rest' => ($slot === 0 ? 90 : 60), 'prep_time' => 15],
            'exercises' => $exList,
        ],
        'amrap' => [
            'type' => 'amrap',
            'name' => $blockName,
            'config' => ['duration' => $cfg['amrap_min'] * 60, 'prep_time' => 10],
            'exercises' => $exList,
        ],
        'fortime' => [
            'type' => 'fortime',
            'name' => $blockName,
            'config' => ['rounds' => $cfg['rounds'][2], 'time_cap' => ($cfg['amrap_min'] * 60 + 120), 'prep_time' => 10],
            'exercises' => $exList,
        ],
        default => [
            'type' => 'circuit',
            'name' => $blockName,
            'config' => ['rounds' => $cfg['rounds'][$repsIdx], 'rest' => $cfg['rest'], 'prep_time' => 15],
            'exercises' => $exList,
        ],
    };
}



// ── Assemble WOD description ──────────────────────────────────────────────────
$exNames1 = implode(' + ', array_map(fn($e) => $e['name_es'] ?? $e['name'], $block1Ex));
$exNames2 = implode(' + ', array_map(fn($e) => $e['name_es'] ?? $e['name'], $block2Ex));
$exNamesF = implode(' + ', array_map(fn($e) => $e['name_es'] ?? $e['name'], $finisherEx));
$reps0 = $cfg['reps'][0];
$reps1 = $cfg['reps'][1];
$reps2 = $cfg['reps'][2];
$repsLabel = "{$reps0} reps c/u";

$wodDesc = "WOD {$dateLabel} — {$levelLabel} | {$styleLabel}\n";
$wodDesc .= "Tren Superior: {$upperPct}% | Inferior: {$lowerPct}% | Medio: {$corePct}%\n\n";
$wodDesc .= "B1: {$exNames1} × {$repsLabel}\n";
$wodDesc .= "B2: {$exNames2} × {$reps1} reps c/u\n";
$wodDesc .= "Finisher: {$exNamesF} × {$reps2} reps";

// ── Build full blocks array ───────────────────────────────────────────────────
$blocks = [];

// 1. Briefing
$blocks[] = [
    'type' => 'briefing',
    'name' => '📋 WOD del Día',
    'config' => [
        'duration' => 90,
        'title' => "WOD {$dateLabel}",
        'description' => $wodDesc,
    ],
    'exercises' => [],
];

// 2. Warm-up
$blocks[] = buildWarmupBlock($warmupEx, $cfg);

// 3. Rest between warmup and blocks
$blocks[] = [
    'type' => 'rest',
    'name' => 'Transición',
    'config' => ['duration' => 60],
    'exercises' => [],
];

// 4. Main Block 1  (type decided randomly per-request)
$blocks[] = buildWorkBlock($block1Ex, $cfg, $blockType1, 0);

// 5. Rest
$blocks[] = [
    'type' => 'rest',
    'name' => 'Descanso',
    'config' => ['duration' => ($level === 'beginner' ? 120 : 90)],
    'exercises' => [],
];

// 6. Main Block 2  (type decided randomly per-request)
$blocks[] = buildWorkBlock($block2Ex, $cfg, $blockType2, 1);

// 7. Rest
$blocks[] = [
    'type' => 'rest',
    'name' => 'Descanso',
    'config' => ['duration' => ($level === 'beginner' ? 90 : 60)],
    'exercises' => [],
];

// 8. Finisher  (type decided randomly per-request)
$blocks[] = buildWorkBlock($finisherEx, $cfg, $finisherType, 2);

// 9. Cool-down
$blocks[] = [
    'type' => 'rest',
    'name' => 'Vuelta a la Calma',
    'config' => ['duration' => 300],
    'exercises' => [],
];

$blocks[] = [
    'type' => 'briefing',
    'name' => '🧘 Elongación',
    'config' => [
        'duration' => 300,
        'title' => 'Elongación',
        'description' => "Tiempo de estiramiento: cuadriceps, isquiotibiales, dorsales, pectorales y movilidad de cadera.\nMantener cada posición 30 segundos.",
    ],
    'exercises' => [],
];

// ── Attach music to each block ────────────────────────────────────────────────
// Index 1 is always the warmup interval → special mood
foreach ($blocks as $i => &$block) {
    $typeForMusic = ($i === 1 && $block['type'] === 'interval') ? '__warmup__' : $block['type'];
    $block['music'] = pickMusicForBlock($typeForMusic, $musicCatalogue);
}
unset($block);

// ── Compute approximate total duration ───────────────────────────────────────
function estimateBlockDuration(array $block): int
{
    $t = $block['type'];
    $c = $block['config'];
    return match ($t) {
        'briefing' => (int) ($c['duration'] ?? 90),
        'rest' => (int) ($c['duration'] ?? 60),
        'interval' => (int) (($c['rounds'] ?? 3) * (($c['work'] ?? 40) + ($c['rest'] ?? 20))),
        'tabata' => (int) (($c['rounds'] ?? 8) * (($c['work'] ?? 20) + ($c['rest'] ?? 10)) * count($block['exercises'] ?: [[]])),
        'emom' => (int) ($c['duration'] ?? 600),
        'amrap' => (int) ($c['duration'] ?? 420),
        'fortime' => (int) ($c['time_cap'] ?? 480),
        'series' => (int) (($c['sets'] ?? 3) * (($c['rest'] ?? 60) + 40) * count($block['exercises'] ?: [[]])),
        'circuit' => (int) (($c['rounds'] ?? 3) * (count($block['exercises'] ?: [[]]) * 30 + ($c['rest'] ?? 30))),
        default => 120,
    };
}

$totalDuration = array_sum(array_map('estimateBlockDuration', $blocks));

// ── Compute muscle distribution ───────────────────────────────────────────────
$muscleCount = [];
$allWorkingEx = array_merge($block1Ex, $block2Ex, $finisherEx);
foreach ($allWorkingEx as $ex) {
    $mg = $ex['muscle_group'];
    $muscleCount[$mg] = ($muscleCount[$mg] ?? 0) + 1;
}
$totalEx_actual = array_sum($muscleCount);
$musclePct = [];
foreach ($muscleCount as $mg => $cnt) {
    $musclePct[$mg] = round($cnt / max(1, $totalEx_actual) * 100);
}

// ── Response ──────────────────────────────────────────────────────────────────
echo json_encode([
    'blocks' => $blocks,
    'meta' => [
        'level' => $level,
        'style' => $style,
        'label' => "$levelLabel · $styleLabel",
        'total_duration' => $totalDuration,
        'upper_pct' => $upperPct,
        'lower_pct' => $lowerPct,
        'core_pct' => $corePct,
        'muscle_dist' => $musclePct,
        'exercise_count' => $totalEx_actual,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

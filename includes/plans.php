<?php
/**
 * GymFlow — Plan Limits & Enforcement
 * Single source of truth for all plan-based restrictions.
 *
 * Plans:
 *   trial      → same limits as 'instructor' (1 sala, 1 instructor)
 *   instructor → $12.000 ARS/month  — 1 sala,  1 instructor
 *   gimnasio   → $29.000 ARS/month  — 3 salas, unlimited instructors
 *   centro     → $55.000 ARS/month  — 8 salas, unlimited instructors
 *
 * Add-on:
 *   extra_salas → +$9.000 ARS/month per extra sala on top of plan limit
 */

// ── Plan definitions ──────────────────────────────────────────────────────────

const PLAN_DEFINITIONS = [
    'trial' => [
        'label' => 'Trial (30 días)',
        'salas' => 1,
        'instructors' => 1,      // null = unlimited
        'price_ars' => 0,
        'description' => 'Prueba gratuita',
    ],
    'instructor' => [
        'label' => 'Instructor',
        'salas' => 1,
        'instructors' => 1,
        'price_ars' => 12000,
        'description' => '1 instructor · 1 sala',
    ],
    'gimnasio' => [
        'label' => 'Gimnasio',
        'salas' => 3,
        'instructors' => null,   // unlimited
        'price_ars' => 29000,
        'description' => 'Hasta 3 salas · instructores ilimitados',
    ],
    'centro' => [
        'label' => 'Centro',
        'salas' => 8,
        'instructors' => null,   // unlimited
        'price_ars' => 55000,
        'description' => 'Hasta 8 salas · instructores ilimitados',
    ],
];

const ADDON_SALA_PRICE = 9000; // ARS per extra sala per month

/**
 * Returns the effective limits for a gym, including the extra_salas add-on.
 *
 * @param string $plan       Plan slug (trial|instructor|gimnasio|centro)
 * @param int    $extraSalas Number of purchased extra salas
 * @return array{salas: int, instructors: int|null, label: string, price_ars: int, total_price: int}
 */
function getPlanLimits(string $plan, int $extraSalas = 0): array
{
    $def = PLAN_DEFINITIONS[$plan] ?? PLAN_DEFINITIONS['trial'];
    $totalSalas = $def['salas'] + max(0, $extraSalas);
    $addonCost = max(0, $extraSalas) * ADDON_SALA_PRICE;

    return [
        'plan' => $plan,
        'label' => $def['label'],
        'description' => $def['description'],
        'salas' => $totalSalas,
        'instructors' => $def['instructors'],
        'price_ars' => $def['price_ars'],
        'addon_cost' => $addonCost,
        'total_price' => $def['price_ars'] + $addonCost,
    ];
}

/**
 * Returns the current usage for a gym: how many active salas and instructors it has.
 *
 * @return array{salas: int, instructors: int}
 */
function getGymUsage(int $gymId): array
{
    $db = db();

    $s1 = $db->prepare("SELECT COUNT(*) FROM salas WHERE gym_id = ? AND active = 1");
    $s1->execute([$gymId]);
    $salaCount = (int) $s1->fetchColumn();

    $s2 = $db->prepare(
        "SELECT COUNT(*) FROM users
         WHERE gym_id = ? AND active = 1 AND role = 'instructor'"
    );
    $s2->execute([$gymId]);
    $instrCount = (int) $s2->fetchColumn();

    return ['salas' => $salaCount, 'instructors' => $instrCount];
}

/**
 * Fetches the subscription row and returns plan limits for a gym.
 * Returns null if no subscription exists.
 *
 * @return array|null  Array with 'limits' and 'usage' keys, or null.
 */
function getGymPlanInfo(int $gymId): ?array
{
    $sub = getGymSubscription($gymId);
    if (!$sub)
        return null;

    $plan = $sub['plan'] ?? 'trial';
    $extra = (int) ($sub['extra_salas'] ?? 0);
    $limits = getPlanLimits($plan, $extra);
    $usage = getGymUsage($gymId);

    return [
        'subscription' => $sub,
        'limits' => $limits,
        'usage' => $usage,
        'can_add_sala' => $usage['salas'] < $limits['salas'],
        'can_add_instructor' => $limits['instructors'] === null || $usage['instructors'] < $limits['instructors'],
    ];
}

/**
 * Returns true if the gym can create another sala.
 */
function checkSalaLimit(int $gymId): bool
{
    $info = getGymPlanInfo($gymId);
    if (!$info)
        return false; // no subscription → block
    return $info['can_add_sala'];
}

/**
 * Returns true if the gym can add another instructor.
 */
function checkInstructorLimit(int $gymId): bool
{
    $info = getGymPlanInfo($gymId);
    if (!$info)
        return false;
    return $info['can_add_instructor'];
}

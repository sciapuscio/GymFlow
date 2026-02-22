<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return own profile ───────────────────────────────────────────────
if ($method === 'GET') {
    jsonResponse([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'gym_name' => $user['gym_name'] ?? null,
        'last_login' => $user['last_login'] ?? null,
    ]);
}

// ── PUT: update own profile ───────────────────────────────────────────────
if ($method === 'PUT') {
    $data = getBody();

    $fields = [];
    $params = [];
    $errors = [];

    // Name
    if (isset($data['name'])) {
        $name = trim($data['name']);
        if (strlen($name) < 2) {
            $errors['name'] = 'El nombre debe tener al menos 2 caracteres.';
        } else {
            $fields[] = 'name = ?';
            $params[] = sanitize($name);
        }
    }

    // Email
    if (isset($data['email'])) {
        $email = strtolower(trim($data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido.';
        } else {
            // Ensure email not taken by another user
            $existing = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $existing->execute([$email, $user['id']]);
            if ($existing->fetch()) {
                $errors['email'] = 'Ese email ya está en uso por otra cuenta.';
            } else {
                $fields[] = 'email = ?';
                $params[] = $email;
            }
        }
    }

    // Password change
    if (!empty($data['new_password'])) {
        $current = trim($data['current_password'] ?? '');
        $newPass = $data['new_password'];
        $confirm = $data['confirm_password'] ?? '';

        // Re-fetch hash from DB (getCurrentUser() doesn't expose it)
        $row = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();

        if (!$current || !password_verify($current, $hash)) {
            $errors['current_password'] = 'La contraseña actual es incorrecta.';
        } elseif (strlen($newPass) < 8) {
            $errors['new_password'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($newPass !== $confirm) {
            $errors['confirm_password'] = 'Las contraseñas no coinciden.';
        } else {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($newPass, PASSWORD_BCRYPT);
        }
    }

    if (!empty($errors)) {
        jsonResponse(['error' => 'Validation failed', 'fields' => $errors], 422);
    }

    if (empty($fields)) {
        jsonResponse(['ok' => true, 'message' => 'Sin cambios.']);
    }

    $params[] = $user['id'];
    db()->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")
        ->execute($params);

    jsonResponse(['ok' => true, 'message' => 'Perfil actualizado correctamente.']);
}

jsonResponse(['error' => 'Method not allowed'], 405);

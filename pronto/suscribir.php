<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email = trim($_POST['email'] ?? '');

// Validación básica
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Email inválido']);
    exit;
}

$archivo = __DIR__ . '/leads.txt';

// Leer emails existentes para evitar duplicados
$existentes = file_exists($archivo) ? file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$existentes = array_map('trim', $existentes);

if (in_array($email, $existentes)) {
    echo json_encode(['ok' => true, 'msg' => 'Ya estabas anotado']);
    exit;
}

// Guardar: fecha/hora + email
$linea = date('Y-m-d H:i:s') . ' | ' . $email . PHP_EOL;
file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true, 'msg' => 'Registrado']);

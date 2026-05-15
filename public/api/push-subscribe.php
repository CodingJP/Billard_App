<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

// CSRF-Prüfung über HTTP-Header (für fetch()-Aufrufe aus JavaScript)
$csrf     = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$expected = (string)($_SESSION['csrf_token'] ?? '');

if ($expected === '' || !hash_equals($expected, $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültiger CSRF-Token']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json ?: '{}', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges JSON']);
    exit;
}

$endpoint = (string)($data['endpoint'] ?? '');
$p256dh   = (string)($data['keys']['p256dh'] ?? '');
$auth     = (string)($data['keys']['auth'] ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Fehlende Felder']);
    exit;
}

// Endpoint-Format validieren
$parsed = parse_url($endpoint);
if (!$parsed || !isset($parsed['scheme']) || !in_array($parsed['scheme'], ['https', 'http'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiger Endpoint']);
    exit;
}

$action = (string)($data['action'] ?? 'subscribe');

if ($action === 'unsubscribe') {
    db()->prepare('DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint = :ep')
        ->execute(['uid' => currentUserId(), 'ep' => $endpoint]);
    echo json_encode(['success' => true]);
    exit;
}

// Abo speichern (delete + insert für sauberes Upsert)
db()->prepare('DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint = :ep')
    ->execute(['uid' => currentUserId(), 'ep' => $endpoint]);

db()->prepare(
    'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key) VALUES (:uid, :ep, :p256dh, :auth)'
)->execute([
    'uid'    => currentUserId(),
    'ep'     => $endpoint,
    'p256dh' => $p256dh,
    'auth'   => $auth,
]);

echo json_encode(['success' => true]);

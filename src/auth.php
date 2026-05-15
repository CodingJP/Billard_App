<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function registerUser(string $username, string $email, string $password, string $secretCode): void
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    checkRateLimit('register:' . $ip, 3, 600);

    $expectedSecret = env('REGISTER_SECRET', '');
    if ($expectedSecret === '' || !hash_equals($expectedSecret, $secretCode)) {
        throw new RuntimeException('Ungültiger Secret-Code.');
    }

    if (strlen($username) < 3) {
        throw new RuntimeException('Benutzername muss mindestens 3 Zeichen lang sein.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ungültige E-Mail-Adresse.');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException('Passwort muss mindestens 8 Zeichen lang sein.');
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        'username' => $username,
        'email' => $email,
    ]);

    if ($stmt->fetch()) {
        throw new RuntimeException('Benutzername oder E-Mail ist bereits vergeben.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)');
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $hash,
    ]);
}

function loginUser(string $usernameOrEmail, string $password): void
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    checkRateLimit('login:' . $ip, 10, 300);

    $stmt = db()->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        'username' => $usernameOrEmail,
        'email' => $usernameOrEmail,
    ]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new RuntimeException('Anmeldung fehlgeschlagen.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

<?php

declare(strict_types=1);

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consumeFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function isLoggedIn(): bool
{
    return currentUserId() !== null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('error', 'Bitte zuerst anmelden.');
        redirect('index.php');
    }
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Einfaches IP-basiertes Rate-Limiting via Datenbank.
 * Wirft RuntimeException wenn das Limit überschritten wird.
 */
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): void
{
    $hash   = hash('sha256', $key);
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

    // Alte Einträge aufräumen
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff')
        ->execute(['cutoff' => $cutoff]);

    // Aktuelle Anzahl prüfen
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = :hash AND attempted_at >= :cutoff'
    );
    $stmt->execute(['hash' => $hash, 'cutoff' => $cutoff]);

    if ((int)$stmt->fetchColumn() >= $maxAttempts) {
        $minutes = (int)ceil($windowSeconds / 60);
        throw new RuntimeException("Zu viele Versuche. Bitte warte {$minutes} Minuten.");
    }

    // Versuch erfassen
    db()->prepare('INSERT INTO login_attempts (ip_hash) VALUES (:hash)')
        ->execute(['hash' => $hash]);
}

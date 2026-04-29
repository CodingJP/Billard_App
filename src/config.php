<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        [$name, $value] = $parts;
        $name = trim($name);
        $value = trim($value);

        // Keep runtime-provided environment values (e.g. Docker) authoritative.
        if (getenv($name) !== false) {
            $_ENV[$name] = (string)getenv($name);
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

$root = dirname(__DIR__);
loadEnv($root . '/.env');

date_default_timezone_set('Europe/Berlin');

$sessionName = env('SESSION_NAME', 'billard_session');
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start();
}

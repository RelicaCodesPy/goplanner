<?php
// config/env.php
// Simple .env file loader for GoPlanner

function loadEnv(string $path): void {
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;

        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove surrounding quotes
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    if ($value !== null) return $value;
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Load .env from project root
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

<?php

declare(strict_types=1);

/**
 * Shared bootstrap for pages and API endpoints.
 */

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/**
 * Absolute web path prefix for this app (supports subdirectories).
 */
function app_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));

    // API scripts live one level deeper
    if (str_ends_with($dir, '/api')) {
        $dir = dirname($dir);
    }

    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }

    return rtrim($dir, '/');
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');
    if ($path === '') {
        return $base !== '' ? $base . '/' : './';
    }
    return ($base !== '' ? $base : '') . '/' . $path;
}

/**
 * Public absolute URL for QR codes (prefers HTTPS when available).
 */
function app_public_url(string $path = 'index.php'): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = app_base_path();
    $path = ltrim($path, '/');

    return $scheme . '://' . $host . $base . '/' . $path;
}

/**
 * @param array<string, mixed> $data
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

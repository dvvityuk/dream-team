<?php

function app_root(): string
{
    return dirname(__DIR__);
}

function data_dir(): string
{
    $dir = app_root() . '/data';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог data');
    }
    return $dir;
}

function json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    return is_array($data) ? $data : [];
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    $count = preg_match_all('/./u', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function header_date_path(): string
{
    return app_root() . '/header_date.txt';
}

function header_date_read(): string
{
    $path = header_date_path();
    if (!is_file($path)) {
        return 'июнь 2025';
    }
    $value = trim((string) file_get_contents($path));
    return $value === '' ? 'июнь 2025' : $value;
}

function header_date_write(string $date): bool
{
    return file_put_contents(header_date_path(), $date, LOCK_EX) !== false;
}

function delete_public_image(string $relative): void
{
    $relative = str_replace('\\', '/', trim($relative));
    if (!preg_match('#^images/[A-Za-z0-9._-]+$#', $relative)) {
        return;
    }
    $root = realpath(app_root() . '/images');
    $path = realpath(app_root() . '/' . $relative);
    if ($root === false || $path === false || !is_file($path)) {
        return;
    }
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
    $pathNorm = str_replace('\\', '/', $path);
    if ($pathNorm !== $rootNorm && !str_starts_with($pathNorm, $rootNorm . '/')) {
        return;
    }
    unlink($path);
}

function employees_read(): array
{
    $path = app_root() . '/employees.json';
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }
    $employees = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        $achievements = trim((string) ($item['achievements'] ?? ''));
        $image = str_replace('\\', '/', trim((string) ($item['image'] ?? '')));
        if ($name === '') {
            continue;
        }
        $employees[] = [
            'name' => $name,
            'achievements' => $achievements,
            'image' => $image,
        ];
    }
    return $employees;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

require_once __DIR__ . '/auth.php';

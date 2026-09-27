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

function connection_is_tls(): bool
{
    if (strtolower((string) ($_SERVER['QDIGEST_HTTPS'] ?? '')) === 'on') {
        return true;
    }
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1') {
        return true;
    }
    return strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';
}

function browser_scheme(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $hostIsIp = $host !== '' && filter_var($host, FILTER_VALIDATE_IP) !== false;
    if ($hostIsIp) {
        return strtolower((string) ($_SERVER['QDIGEST_HTTPS'] ?? '')) === 'on' ? 'https' : 'http';
    }
    if (connection_is_tls()) {
        return 'https';
    }
    foreach ([$_SERVER['HTTP_ORIGIN'] ?? '', $_SERVER['HTTP_REFERER'] ?? ''] as $url) {
        $url = (string) $url;
        if (stripos($url, 'https://') === 0) {
            return 'https';
        }
        if (stripos($url, 'http://') === 0) {
            return 'http';
        }
    }
    return 'http';
}

function request_is_https(): bool
{
    return browser_scheme() === 'https';
}

function expire_cookie(string $name): void
{
    if ($name === '' || headers_sent()) {
        return;
    }
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function app_web_base(): string
{
    $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '';
    if ($host === '') {
        $host = 'localhost';
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim($dir, '/');
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }
    return (request_is_https() ? 'https' : 'http') . '://' . $host . $dir;
}

function redirect_to(string $target): void
{
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $target)) {
        $target = app_web_base() . '/' . ltrim($target, '/');
    }
    header('Location: ' . $target, true, 302);
    exit;
}

function session_directory(): ?string
{
    $candidates = ['/var/lib/php/sessions'];
    $configured = (string) ini_get('session.save_path');
    if ($configured !== '' && !str_contains($configured, ';') && !in_array($configured, $candidates, true)) {
        $candidates[] = $configured;
    }
    try {
        $candidates[] = data_dir() . '/sessions';
    } catch (RuntimeException) {
    }
    foreach ($candidates as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            continue;
        }
        if (!is_writable($dir)) {
            continue;
        }
        if (ini_set('session.save_path', $dir) === false) {
            continue;
        }
        $applied = rtrim(str_replace('\\', '/', (string) ini_get('session.save_path')), '/');
        $wanted = rtrim(str_replace('\\', '/', $dir), '/');
        if ($applied === $wanted) {
            return $dir;
        }
    }
    return null;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
session_directory();
session_name('QDIGEST');
ini_set('session.use_strict_mode', '0');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.lazy_write', '0');
ini_set('session.cookie_lifetime', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', request_is_https() ? '1' : '0');
if (session_start() !== true) {
    $GLOBALS['session_error'] = 'Сервер не сохранил сессию. Проверьте права на каталог сессий PHP.';
}

require_once __DIR__ . '/auth.php';

if (!headers_sent()) {
    header('X-Qdigest-Session: ' . (current_user() !== null ? 'ok' : 'anon'));
}

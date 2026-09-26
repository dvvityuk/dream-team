<?php

function users_path(): string
{
    return data_dir() . '/users.php';
}

function default_users(): array
{
    return [
        'admin' => [
            'role' => 'admin',
            'password' => password_hash('ventra_admin', PASSWORD_DEFAULT),
        ],
        'expert' => [
            'role' => 'expert',
            'password' => password_hash('ventra', PASSWORD_DEFAULT),
        ],
    ];
}

function users_save(array $users): bool
{
    $path = users_path();
    $php = "<?php\nreturn " . var_export($users, true) . ";\n";
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $php, LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($path, true);
    }
    return true;
}

function users_load(): array
{
    $path = users_path();
    if (!is_file($path)) {
        if (!users_save(default_users())) {
            return [];
        }
    }
    $data = include $path;
    return is_array($data) ? $data : [];
}

function storage_ready(): bool
{
    try {
        data_dir();
    } catch (RuntimeException $e) {
        return false;
    }
    return is_file(users_path()) || users_save(default_users());
}

function current_user(): ?string
{
    $name = $_SESSION['user'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function safe_next(?string $next): string
{
    $next = basename((string) $next);
    if (!in_array($next, ['index.php', 'team-building.php'], true)) {
        return 'index.php';
    }
    return $next;
}

function release_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function require_login(): void
{
    $user = current_user();
    release_session();
    if ($user !== null) {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $page = in_array($script, ['index.php', 'team-building.php'], true);
    if ($page && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        header('Location: login.php?next=' . rawurlencode($script));
        exit;
    }
    json_response(['success' => false, 'error' => 'Требуется авторизация'], 401);
}

function require_admin(): void
{
    require_login();
    if (is_admin()) {
        return;
    }
    json_response(['success' => false, 'error' => 'Недостаточно прав'], 403);
}

function attempt_login(string $username, string $password): bool
{
    $username = strtolower(trim($username));
    $users = users_load();
    if (!isset($users[$username]['password']) || !is_string($users[$username]['password'])) {
        return false;
    }
    if (!password_verify($password, $users[$username]['password'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    $_SESSION['role'] = ($users[$username]['role'] ?? '') === 'admin' ? 'admin' : 'expert';
    return true;
}

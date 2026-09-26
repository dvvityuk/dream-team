<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$password = (string) (read_json_body()['password'] ?? '');
$length = text_length($password);
if ($length < 4 || $length > 72) {
    json_response(['success' => false, 'error' => 'Пароль должен быть от 4 до 72 символов'], 400);
}

$users = users_load();
if ($users === []) {
    json_response(['success' => false, 'error' => 'Файл пользователей недоступен'], 500);
}
$users['expert'] = [
    'role' => 'expert',
    'password' => password_hash($password, PASSWORD_DEFAULT),
];
if (!users_save($users)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить пароль'], 500);
}
json_response(['success' => true]);

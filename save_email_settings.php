<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
if ($contentType !== 'application/json') {
    json_response(['success' => false, 'error' => 'Неверный формат запроса'], 415);
}

$data = read_json_body();
$sender = clean_header((string) ($data['sender'] ?? ''));
$subject = clean_header((string) ($data['subject'] ?? ''));
if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'error' => 'Некорректный email отправителя'], 400);
}
if ($subject === '' || text_length($subject) > 150) {
    json_response(['success' => false, 'error' => 'Введите тему до 150 символов'], 400);
}
$password = $data['app_password'] ?? '';
if (!is_string($password) || strlen($password) > 256 || preg_match('/[\x00-\x1F\x7F]/', $password)) {
    json_response(['success' => false, 'error' => 'Некорректный пароль внешнего приложения'], 400);
}

lock_project_storage();
$current = mail_settings_load();
$password = trim($password);
if ($password === '') {
    if ($current['app_password'] !== '' && strcasecmp($current['sender'], $sender) !== 0) {
        json_response(['success' => false, 'error' => 'Введите пароль внешнего приложения для нового отправителя'], 400);
    }
    $password = $current['app_password'];
}
if (!mail_settings_save($sender, $subject, $password)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить настройки'], 500);
}
json_response(['success' => true, 'settings' => mail_settings_public(mail_settings_load())]);

<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Неверный метод'], 405);
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
if (!mail_settings_save($sender, $subject)) {
    json_response(['success' => false, 'error' => 'Не удалось сохранить настройки'], 500);
}
json_response(['success' => true, 'settings' => mail_settings_load()]);
